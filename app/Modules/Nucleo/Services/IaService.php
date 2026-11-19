<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Services;

use App\Modules\Nucleo\Alertas\AlertasDelNucleo;
use App\Modules\Nucleo\Enums\AccionSugerenciaIa;
use App\Modules\Nucleo\Enums\EstadoSugerenciaIa;
use App\Modules\Nucleo\Enums\ResultadoLlamadaIa;
use App\Modules\Nucleo\Enums\ResultadoPedidoIa;
use App\Modules\Nucleo\Enums\SeguridadCampoIa;
use App\Modules\Nucleo\Excepciones\IaRechazada;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Ia\ArchivoParaIa;
use App\Modules\Nucleo\Ia\CitaIa;
use App\Modules\Nucleo\Ia\ClientesModeloLenguaje;
use App\Modules\Nucleo\Ia\CostoLlamadaIa;
use App\Modules\Nucleo\Ia\DefinicionProcesoIa;
use App\Modules\Nucleo\Ia\PedidoModeloIa;
use App\Modules\Nucleo\Ia\PromptIa;
use App\Modules\Nucleo\Ia\PromptsIa;
use App\Modules\Nucleo\Ia\RegistroProcesosIa;
use App\Modules\Nucleo\Ia\RespuestaModeloIa;
use App\Modules\Nucleo\Ia\SalidaIa;
use App\Modules\Nucleo\Ia\SeguridadPorCampo;
use App\Modules\Nucleo\Ia\SinCupoIa;
use App\Modules\Nucleo\Ia\TextoDeAdjunto;
use App\Modules\Nucleo\Ia\ValidadorEsquemaIa;
use App\Modules\Nucleo\Jobs\SugerirConIa;
use App\Modules\Nucleo\Models\Adjunto;
use App\Modules\Nucleo\Models\IaLlamada;
use App\Modules\Nucleo\Models\IaModelo;
use App\Modules\Nucleo\Models\IaPrecioModelo;
use App\Modules\Nucleo\Models\IaProceso;
use App\Modules\Nucleo\Models\IaProveedor;
use App\Modules\Nucleo\Models\IaSugerencia;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Numeracion\FormatoNumeracion;
use App\Modules\Nucleo\Numeracion\ReinicioNumeracion;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use InvalidArgumentException;
use LogicException;
use Throwable;

/**
 * M12 · Pedir una sugerencia a la IA (plan 1.11, D8). `sugerir()` revisa los candados (también el precio vigente: sin
 * precio no hay llamada) y encola; el job llama a
 * `ejecutar()`, que los vuelve a revisar, llama al modelo fuera de toda transacción (con un reintento corto si está
 * saturado y el modelo de respaldo si el principal falla), registra cada llamada en `ia_llamadas`, revisa la salida y
 * su evidencia, y guarda la sugerencia con su seguridad por campo. Si algo falla, no hay sugerencia y el formulario
 * sigue en manual. Los registros nunca llevan datos del origen ni del documento.
 */
final readonly class IaService
{
    /** Espera antes del único reintento por saturación (429, 529). */
    public const int ESPERA_REINTENTO_MS = 1500;

    /** Segundos que espera el job si el proveedor no tiene cupo. */
    public const int ESPERA_SIN_CUPO = 20;

    /** Lo que dura como máximo el cupo tomado: más que una llamada. */
    private const int DURACION_CUPO = 300;

    public function __construct(
        private RegistroProcesosIa $registro,
        private IaConfiguracionService $configuracion,
        private ClientesModeloLenguaje $clientes,
        private PromptsIa $prompts,
        private TextoDeAdjunto $textoDeAdjunto,
        private NumeracionService $numeracion,
        private AlertasService $alertas,
        private ContextoAuditoria $auditoria,
    ) {}

    /**
     * Pide la sugerencia: si puede llamar, la encola y devuelve `Encolado`; si no, por qué no (camino manual).
     */
    public function sugerir(string $proceso, Model $origen, ?Adjunto $adjunto = null): ResultadoPedidoIa
    {
        $motivo = $this->motivoParaNoLlamar($proceso, $origen, $adjunto);

        if ($motivo !== null) {
            return $motivo;
        }

        SugerirConIa::dispatch($proceso, $origen->getMorphClass(), (int) $origen->getKey(), $adjunto?->id, $this->auditoria->idActor());

        return ResultadoPedidoIa::Encolado;
    }

    /**
     * Los candados (D8): encendido, origen y adjunto permitidos, nada sensible, autorización vigente, modelo, tope y
     * clave. Nulo si se puede llamar.
     */
    public function motivoParaNoLlamar(string $proceso, Model $origen, ?Adjunto $adjunto): ?ResultadoPedidoIa
    {
        $definicion = $this->registro->procesos()[$proceso] ?? null;
        $fila = IaProceso::query()->where('codigo', $proceso)->first();

        if ($definicion === null || $fila === null || ! $fila->esta_activo) {
            return ResultadoPedidoIa::Apagado;
        }

        try {
            $this->registro->revisar($definicion);
        } catch (InvalidArgumentException $error) {
            Log::warning('Un proceso de IA declara enviar algo que no puede salir: no se llama.', ['proceso' => $proceso, 'motivo' => $error->getMessage()]);

            return ResultadoPedidoIa::Apagado;
        }

        if (! in_array($origen->getMorphClass(), $definicion->origenes, true)) {
            return ResultadoPedidoIa::OrigenNoPermitido;
        }

        if ($adjunto !== null && ! $this->adjuntoPermitido($definicion, $origen, $adjunto)) {
            return ResultadoPedidoIa::AdjuntoNoPermitido;
        }

        if (! $this->configuracion->autorizacionVigente($fila)) {
            return ResultadoPedidoIa::SinAutorizacion;
        }

        $modelo = $this->modeloPrincipal($fila);

        if ($modelo === null) {
            return ResultadoPedidoIa::SinModelo;
        }

        if ($this->topeAlcanzado($fila)) {
            return ResultadoPedidoIa::TopeAlcanzado;
        }

        if ($this->clave($modelo->proveedor) === null) {
            return ResultadoPedidoIa::SinClave;
        }

        // Ajuste del 2026-10-28: sin precio vigente no hay llamada; se avisa como proveedor no disponible.
        if ($this->precio($modelo) === null) {
            $this->avisarNoDisponible($modelo->proveedor, 'sin-precio', 'el modelo «'.$modelo->nombre.'» no tiene precio vigente');

            return ResultadoPedidoIa::SinPrecio;
        }

        return null;
    }

    /**
     * Llama al modelo y guarda la sugerencia; nulo si no hubo (camino manual). Corre en el job.
     *
     * @throws SinCupoIa si el proveedor del modelo principal no tiene cupo: el job vuelve a la cola
     */
    public function ejecutar(string $proceso, string $origenType, int $origenId, ?int $adjuntoId): ?IaSugerencia
    {
        $clase = Relation::getMorphedModel($origenType);
        $origen = $clase === null ? null : $clase::query()->find($origenId);
        $adjunto = $adjuntoId === null ? null : Adjunto::query()->find($adjuntoId);

        if (! $origen instanceof Model || ($adjuntoId !== null && $adjunto === null)) {
            return null;
        }

        // Pudo cambiar desde que se encoló: se revisa otra vez antes de llamar.
        $motivo = $this->motivoParaNoLlamar($proceso, $origen, $adjunto);

        if ($motivo !== null) {
            Log::info('Sugerencia de IA no pedida.', ['proceso' => $proceso, 'motivo' => $motivo->value]);

            return null;
        }

        $definicion = $this->registro->proceso($proceso);
        $fila = IaProceso::query()->where('codigo', $proceso)->firstOrFail();
        $prompt = $this->prompts->vigente($proceso);
        $archivo = $adjunto === null ? null : new ArchivoParaIa($adjunto->mime, (string) Storage::disk($adjunto->disco)->get($adjunto->ruta));
        $contexto = $this->contexto($definicion, $origen);

        $sinCuenta = null;

        foreach ([$this->modeloPrincipal($fila), $this->modeloRespaldo($fila)] as $n => $modelo) {
            // Al llegar al tope el proceso se apaga: no pasa al respaldo (D5). Sin crédito o con la clave inválida, el
            // respaldo del mismo proveedor fallaría igual (ajuste del 2026-10-28).
            if (! $modelo instanceof IaModelo || ($n > 0 && ($this->topeAlcanzado($fila) || $this->clave($modelo->proveedor) === null || $modelo->proveedor_id === $sinCuenta))) {
                continue;
            }

            $precio = $this->precio($modelo);

            if ($precio === null) {
                $this->avisarNoDisponible($modelo->proveedor, 'sin-precio', 'el modelo «'.$modelo->nombre.'» no tiene precio vigente');

                continue;
            }

            $pedido = new PedidoModeloIa($modelo->codigo, $modelo->max_tokens_salida, $prompt->texto, $contexto, $archivo === null ? [] : [$archivo], SalidaIa::esquema($definicion));
            [$respuesta, $llamada] = $this->llamarConReintento($fila, $definicion, $origen, $adjunto, $modelo, $precio, $n > 0, $prompt, $pedido);
            $this->revisarTope($fila, $definicion);

            if ($respuesta->resultado === ResultadoLlamadaIa::Valida) {
                return $this->guardarSugerencia($fila, $definicion, $origen, $adjunto, $archivo, $llamada, $respuesta->salida ?? []);
            }

            if (! $respuesta->resultado->pasaAlRespaldo()) {
                return null;
            }

            if (in_array($respuesta->resultado, [ResultadoLlamadaIa::SinCredito, ResultadoLlamadaIa::ClaveInvalida], true)) {
                $sinCuenta = $modelo->proveedor_id;
            }
        }

        return null;
    }

    /**
     * Resuelve una sugerencia (D9; ajuste del desarrollador del 2026-10-28). La resolución la hace **el módulo**: al
     * aceptarla o corregirla, la acción que crea el registro llama aquí dentro de su misma transacción, con ese registro
     * como resultado, del tipo que el proceso declara producir. Rechazarla no crea nada (`rechazar()`). Exige el permiso
     * que el proceso declara para resolver sus sugerencias. Con bloqueo optimista.
     *
     * @param  array<string, mixed>|null  $correccion  campo => valor corregido; solo con `Corregida`
     */
    public function resolver(int $sugerenciaId, AccionSugerenciaIa $accion, ?array $correccion, ?Model $resultado, int $versionLeida): IaSugerencia
    {
        if ($accion !== AccionSugerenciaIa::Rechazada && DB::transactionLevel() === 0) {
            throw new LogicException('Una sugerencia se acepta o se corrige dentro de la transacción que crea su registro.');
        }

        return DB::transaction(function () use ($sugerenciaId, $accion, $correccion, $resultado, $versionLeida): IaSugerencia {
            $sugerencia = IaSugerencia::query()->lockForUpdate()->findOrFail($sugerenciaId);
            $definicion = $this->registro->procesos()[IaProceso::query()->whereKey($sugerencia->proceso_id)->value('codigo')] ?? null;

            if ($definicion === null) {
                throw IaRechazada::resultadoNoPermitido('su proceso ya no existe');
            }

            $persona = User::query()->find($this->auditoria->idActor(null));

            if (! $persona instanceof User || $persona->es_sistema || ! $persona->can($definicion->permisoResolver)) {
                throw IaRechazada::sinPermisoParaResolver($definicion->nombre);
            }

            if ($sugerencia->version !== $versionLeida) {
                throw VersionDesactualizada::de("la sugerencia {$sugerencia->numero}");
            }

            if ($sugerencia->estado !== EstadoSugerenciaIa::PorRevisar) {
                throw IaRechazada::sugerenciaResuelta($sugerencia->numero);
            }

            $this->revisarCorreccion($sugerencia, $definicion, $accion, $correccion);
            $this->revisarResultado($definicion, $accion, $resultado);

            $sugerencia->fill([
                'estado' => EstadoSugerenciaIa::Resuelta,
                'accion' => $accion,
                'correccion' => $accion === AccionSugerenciaIa::Corregida ? $correccion : null,
                'resuelta_por' => $persona->id,
                'resuelta_at' => now(),
                'resultado_type' => $resultado?->getMorphClass(),
                'resultado_id' => $resultado?->getKey(),
            ]);
            $sugerencia->version++;
            $sugerencia->save();

            return $sugerencia;
        });
    }

    /** Rechazarla: no se usó y no se creó nada con ella. */
    public function rechazar(int $sugerenciaId, int $versionLeida): IaSugerencia
    {
        return $this->resolver($sugerenciaId, AccionSugerenciaIa::Rechazada, null, null, $versionLeida);
    }

    /** @param array<string, mixed>|null $correccion */
    private function revisarCorreccion(IaSugerencia $sugerencia, DefinicionProcesoIa $definicion, AccionSugerenciaIa $accion, ?array $correccion): void
    {
        if ($accion !== AccionSugerenciaIa::Corregida) {
            if ($correccion !== null) {
                throw IaRechazada::correccionInvalida('solo se corrige al elegir «corregida»');
            }

            return;
        }

        if ($correccion === null || $correccion === []) {
            throw IaRechazada::correccionInvalida('falta lo que se cambió');
        }

        foreach ($correccion as $campo => $valor) {
            if (! array_key_exists($campo, $sugerencia->propuesta)) {
                throw IaRechazada::correccionInvalida("«{$campo}» no es un campo de la sugerencia");
            }

            if (! ValidadorEsquemaIa::valida($valor, $definicion->campos[$campo] ?? [])) {
                throw IaRechazada::correccionInvalida("el valor de «{$campo}» no es del tipo que pide el proceso");
            }
        }
    }

    private function revisarResultado(DefinicionProcesoIa $definicion, AccionSugerenciaIa $accion, ?Model $resultado): void
    {
        if ($accion === AccionSugerenciaIa::Rechazada) {
            if ($resultado !== null) {
                throw IaRechazada::resultadoNoPermitido('una sugerencia rechazada no creó nada');
            }

            return;
        }

        if ($resultado === null || ! $resultado->exists) {
            throw IaRechazada::resultadoNoPermitido('al aceptarla o corregirla va el registro que se creó con ella');
        }

        if (! in_array($resultado->getMorphClass(), $definicion->produce, true)) {
            throw IaRechazada::resultadoNoPermitido("el proceso «{$definicion->nombre}» no produce «{$resultado->getMorphClass()}»");
        }
    }

    /** Lo gastado este mes (Lima), en dólares, desde `ia_llamadas` (D3). */
    public function gastoDelMes(IaProceso $proceso): string
    {
        $inicio = CarbonImmutable::now('America/Lima')->startOfMonth();

        return (string) (IaLlamada::query()
            ->where('proceso_id', $proceso->id)
            ->where('created_at', '>=', $inicio)
            ->selectRaw('coalesce(sum(costo_usd), 0) as total')
            ->value('total') ?? '0');
    }

    /** Con tope 0 no se llama nunca (D3: sin monto decidido). */
    public function topeAlcanzado(IaProceso $proceso): bool
    {
        return bccomp($this->gastoDelMes($proceso), (string) $proceso->tope_mensual_usd, 6) >= 0;
    }

    private function adjuntoPermitido(DefinicionProcesoIa $definicion, Model $origen, Adjunto $adjunto): bool
    {
        return $adjunto->adjuntable_type === $origen->getMorphClass()
            && (int) $adjunto->adjuntable_id === (int) $origen->getKey()
            && $adjunto->quitado_at === null
            && ! $adjunto->esSensible()
            && in_array($adjunto->mime, ArchivoParaIa::TIPOS, true)
            && in_array($adjunto->tipo?->codigo, $definicion->tiposAdjunto, true);
    }

    private function modeloPrincipal(IaProceso $proceso): ?IaModelo
    {
        $modelo = $proceso->modelo_id === null
            ? IaModelo::query()->where('es_por_defecto', true)->first()
            : IaModelo::query()->find($proceso->modelo_id);

        return $this->usable($modelo);
    }

    private function modeloRespaldo(IaProceso $proceso): ?IaModelo
    {
        return $proceso->modelo_respaldo_id === null ? null : $this->usable(IaModelo::query()->find($proceso->modelo_respaldo_id));
    }

    private function usable(?IaModelo $modelo): ?IaModelo
    {
        return $modelo !== null && $modelo->es_activo && $modelo->proveedor->es_activo ? $modelo : null;
    }

    /** La clave del proveedor; '' si no la necesita (un modelo propio); nulo si la necesita y no está. */
    private function clave(IaProveedor $proveedor): ?string
    {
        if ($proveedor->variable_clave === null) {
            return '';
        }

        $clave = $this->clientes->clave($proveedor);

        return $clave === null || $clave === '' ? null : $clave;
    }

    /** Lo que se envía del registro, solo los campos declarados y autorizados, y las opciones válidas (D2, D8). */
    private function contexto(DefinicionProcesoIa $definicion, Model $origen): string
    {
        // Valores crudos de las columnas declaradas: nunca una relación ni un accessor (revisión de arquitectura).
        $crudos = $origen->getAttributes();
        $campos = $definicion->camposEnviados[$origen->getMorphClass()] ?? [];
        $datos = ['registro' => array_map(fn (string $campo): mixed => $crudos[$campo] ?? null, array_combine($campos, $campos))];

        if ($definicion->opciones !== null) {
            // Solo las listas y los campos declarados, que son los que Gerencia autorizó con la huella.
            $todas = ($definicion->opciones)($origen);
            $datos['opciones'] = [];

            foreach ($definicion->opcionesEnviadas as $lista => $camposDeLaLista) {
                $filas = [];

                foreach ((array) ($todas[$lista] ?? []) as $opcion) {
                    $filas[] = array_intersect_key((array) $opcion, array_flip($camposDeLaLista));
                }

                $datos['opciones'][$lista] = $filas;
            }
        }

        return (string) json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * Una llamada y, si el proveedor está saturado, un reintento corto (D7). Cada intento queda en `ia_llamadas`.
     *
     * @return array{0: RespuestaModeloIa, 1: IaLlamada}
     */
    private function llamarConReintento(IaProceso $fila, DefinicionProcesoIa $definicion, Model $origen, ?Adjunto $adjunto, IaModelo $modelo, IaPrecioModelo $precio, bool $esRespaldo, PromptIa $prompt, PedidoModeloIa $pedido): array
    {
        $intento = 1;

        while (true) {
            $respuesta = $this->conCupo($modelo->proveedor, $esRespaldo, fn (): RespuestaModeloIa => $this->clientes->para($modelo->proveedor)->llamar($pedido, (string) $this->clave($modelo->proveedor)));

            // Una respuesta que no tiene la forma pedida es inválida: cuenta para el tope y no crea sugerencia (D9).
            if ($respuesta->resultado === ResultadoLlamadaIa::Valida && ! SalidaIa::esValida($definicion, $respuesta->salida)) {
                $respuesta = new RespuestaModeloIa(ResultadoLlamadaIa::Invalida, $respuesta->tokensEntrada, $respuesta->tokensSalida, $respuesta->duracionMs);
            }

            $llamada = $this->registrarLlamada($fila, $origen, $adjunto, $modelo, $precio, $esRespaldo, $intento, $prompt, $respuesta);
            $this->avisarSiNoDisponible($modelo->proveedor, $respuesta->resultado);

            if ($respuesta->resultado !== ResultadoLlamadaIa::Saturado || $intento === 2) {
                return [$respuesta, $llamada];
            }

            Sleep::for(self::ESPERA_REINTENTO_MS)->milliseconds();
            $intento++;
        }
    }

    /**
     * Toma uno de los cupos en paralelo del proveedor (D5) mientras dura la llamada. Sin cupo para el principal, el job
     * espera; para el respaldo, cuenta como saturado.
     *
     * @param  callable(): RespuestaModeloIa  $llamar
     */
    private function conCupo(IaProveedor $proveedor, bool $esRespaldo, callable $llamar): RespuestaModeloIa
    {
        $cupo = $this->tomarCupo($proveedor);

        if ($cupo === null) {
            if (! $esRespaldo) {
                throw new SinCupoIa("El proveedor de IA {$proveedor->codigo} no tiene cupo.");
            }

            return new RespuestaModeloIa(ResultadoLlamadaIa::Saturado, 0, 0, 0);
        }

        try {
            return $llamar();
        } finally {
            $cupo->release();
        }
    }

    private function tomarCupo(IaProveedor $proveedor): ?Lock
    {
        for ($n = 0; $n < max(1, $proveedor->llamadas_en_paralelo); $n++) {
            $cupo = Cache::lock("ia-proveedor:{$proveedor->id}:cupo:{$n}", self::DURACION_CUPO);

            if ($cupo->get()) {
                return $cupo;
            }
        }

        return null;
    }

    /** El precio con que se cobra la llamada: se toma antes de llamar y es el que queda registrado. */
    private function precio(IaModelo $modelo): ?IaPrecioModelo
    {
        return CostoLlamadaIa::precioVigente($modelo->id, CarbonImmutable::now());
    }

    private function registrarLlamada(IaProceso $fila, Model $origen, ?Adjunto $adjunto, IaModelo $modelo, IaPrecioModelo $precio, bool $esRespaldo, int $intento, PromptIa $prompt, RespuestaModeloIa $respuesta): IaLlamada
    {
        return IaLlamada::query()->create([
            'proceso_id' => $fila->id,
            'origen_type' => $origen->getMorphClass(),
            'origen_id' => $origen->getKey(),
            'adjunto_id' => $adjunto?->id,
            'modelo_id' => $modelo->id,
            'es_respaldo' => $esRespaldo,
            'intento' => $intento,
            'prompt_version' => $prompt->version,
            'resultado' => $respuesta->resultado,
            'tokens_entrada' => $respuesta->tokensEntrada,
            'tokens_salida' => $respuesta->tokensSalida,
            'duracion_ms' => $respuesta->duracionMs,
            'precio_id' => $precio->id,
            'costo_usd' => CostoLlamadaIa::costo($precio, $respuesta->tokensEntrada, $respuesta->tokensSalida),
        ]);
    }

    /** Sin crédito o con la clave inválida: aviso a su responsable (D7). */
    private function avisarSiNoDisponible(IaProveedor $proveedor, ResultadoLlamadaIa $resultado): void
    {
        $motivo = match ($resultado) {
            ResultadoLlamadaIa::SinCredito => 'no tiene crédito',
            ResultadoLlamadaIa::ClaveInvalida => 'la clave no vale o no tiene permiso',
            default => null,
        };

        if ($motivo !== null) {
            $this->avisarNoDisponible($proveedor, $resultado->value, $motivo);
        }
    }

    /** El aviso «proveedor no disponible»: uno por proveedor, motivo y día. */
    private function avisarNoDisponible(IaProveedor $proveedor, string $clave, string $motivo): void
    {
        $hoy = CarbonImmutable::now('America/Lima')->toDateString();
        $this->emitir(AlertasDelNucleo::IA_PROVEEDOR_NO_DISPONIBLE, "proveedor:{$proveedor->id}:{$clave}:{$hoy}", ['proveedor' => $proveedor->nombre, 'motivo' => $motivo], $proveedor);
    }

    /** Si la llamada lo llevó al tope, el aviso: uno por proceso y mes (D3). */
    private function revisarTope(IaProceso $fila, DefinicionProcesoIa $definicion): void
    {
        if (bccomp((string) $fila->tope_mensual_usd, '0', 2) <= 0 || ! $this->topeAlcanzado($fila)) {
            return;
        }

        $mes = CarbonImmutable::now('America/Lima')->format('Y-m');
        $this->emitir(AlertasDelNucleo::IA_TOPE_ALCANZADO, "proceso:{$fila->id}:{$mes}", ['proceso' => $definicion->nombre, 'tope' => (string) $fila->tope_mensual_usd], $fila);
    }

    /** @param array<string, string> $datos */
    private function emitir(string $clave, string $hecho, array $datos, Model $entidad): void
    {
        // Un aviso que falla no deja la sugerencia sin guardar: se registra y se sigue.
        try {
            $this->alertas->emitir($clave, $hecho, $datos, $entidad);
        } catch (Throwable $error) {
            Log::error('No se pudo emitir un aviso de IA.', ['alerta' => $clave, 'error' => $error::class]);
        }
    }

    /**
     * Revisa la evidencia de cada campo, calcula su seguridad y guarda la sugerencia con su número (D6, D8).
     *
     * @param  array<string, mixed>  $salida
     */
    private function guardarSugerencia(IaProceso $fila, DefinicionProcesoIa $definicion, Model $origen, ?Adjunto $adjunto, ?ArchivoParaIa $archivo, IaLlamada $llamada, array $salida): IaSugerencia
    {
        $paginas = $archivo?->esPdf() ? $this->textoDeAdjunto->paginas($archivo->contenido) : null;
        $enviados = $definicion->camposEnviados[$origen->getMorphClass()] ?? [];
        $propuesta = [];
        $seguridad = [];

        /** @var array<string, array{valor: mixed, evidencia: array{pagina: ?int, texto: ?string, campo: ?string}, seguridad: string}> $campos */
        $campos = $salida['campos'];

        foreach ($campos as $campo => $dato) {
            $regla = $definicion->reglas[$campo] ?? null;
            $propuesta[$campo] = ['valor' => $dato['valor'], 'evidencia' => $dato['evidencia']];
            $seguridad[$campo] = SeguridadPorCampo::calcular(
                $regla === null ? null : (bool) $regla($dato['valor']),
                $this->evidenciaVerificada($dato['evidencia'], $archivo, $paginas, $enviados),
                SeguridadCampoIa::from($dato['seguridad']),
            )->value;
        }

        return DB::transaction(fn (): IaSugerencia => IaSugerencia::query()->create([
            'numero' => $this->numeracion->siguiente('ia_sugerencia', new FormatoNumeracion('SUG', ReinicioNumeracion::Anual, 6), CarbonImmutable::now('America/Lima')),
            'proceso_id' => $fila->id,
            'llamada_id' => $llamada->id,
            'origen_type' => $origen->getMorphClass(),
            'origen_id' => $origen->getKey(),
            'origen_adjunto_id' => $adjunto?->id,
            'propuesta' => $propuesta,
            'seguridad_por_campo' => $seguridad,
            'explicacion' => trim((string) $salida['explicacion']),
        ]));
    }

    /**
     * Si la evidencia está donde dice (D6): con un PDF que tiene texto, la cita en esa página; con registros, un campo
     * de los enviados. Nulo si no se puede verificar (una foto, una página escaneada); falso si no está o el PDF no se
     * pudo leer.
     *
     * @param  array{pagina: ?int, texto: ?string, campo: ?string}  $evidencia
     * @param  list<string>|null  $paginas
     * @param  list<string>  $enviados
     */
    private function evidenciaVerificada(array $evidencia, ?ArchivoParaIa $archivo, ?array $paginas, array $enviados): ?bool
    {
        if ($archivo === null) {
            return $evidencia['campo'] !== null && in_array($evidencia['campo'], $enviados, true);
        }

        if (! $archivo->esPdf()) {
            return null;
        }

        $pagina = $evidencia['pagina'];

        if ($paginas === null || $pagina === null || $pagina < 1 || $pagina > count($paginas)) {
            return false;
        }

        if (trim($paginas[$pagina - 1]) === '') {
            return null;
        }

        return $evidencia['texto'] !== null && CitaIa::estaEn($evidencia['texto'], $paginas[$pagina - 1]);
    }
}
