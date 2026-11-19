<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Services;

use App\Modules\Nucleo\Enums\TipoParametro;
use App\Modules\Nucleo\Excepciones\ParametroNoDisponible;
use App\Modules\Nucleo\Excepciones\ParametroRechazado;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Models\Parametro;
use App\Modules\Nucleo\RegistroParametros\RegistroParametros;
use App\Modules\Nucleo\RegistroParametros\ValidadorParametro;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Lectura tipada y cambio de los parámetros del negocio (M9), por `grupo.clave`. Se recuerda lo leído
 * durante la petición o el job (binding `scoped`); un cambio lo olvida.
 */
final class ParametrosService
{
    /** @var array<string, Parametro> */
    private array $leidos = [];

    public function __construct(private readonly RegistroParametros $registro) {}

    public function texto(string $nombre): string
    {
        return (string) $this->valor($nombre, TipoParametro::Texto);
    }

    /**
     * Igual que texto(), leído de la base aunque ya se haya leído en esta petición: para una regla que se revisa dentro
     * de un bloqueo (plan 1.15, D3), donde un valor guardado antes del bloqueo podría haber cambiado.
     */
    public function textoReciente(string $nombre): string
    {
        unset($this->leidos[$nombre]);

        return $this->texto($nombre);
    }

    public function entero(string $nombre): int
    {
        return (int) $this->valor($nombre, TipoParametro::Entero);
    }

    /** Siempre string, para bcmath: nunca float. */
    public function decimal(string $nombre): string
    {
        return (string) $this->valor($nombre, TipoParametro::Decimal);
    }

    public function booleano(string $nombre): bool
    {
        return (bool) $this->valor($nombre, TipoParametro::Booleano);
    }

    public function fecha(string $nombre): CarbonImmutable
    {
        $valor = $this->valor($nombre, TipoParametro::Fecha);

        return $valor instanceof CarbonImmutable ? $valor : throw ParametroNoDisponible::deOtroTipo($nombre, 'fecha', 'fecha');
    }

    /** La moneda en la que se suman los resultados (contabilidad en soles). */
    public function monedaLocal(): string
    {
        return $this->texto('general.moneda_local');
    }

    /** Si decide a dónde va el dinero: lo cambia solo quien tiene `parametros.editar-sensibles` (plan 1.3b). */
    public function esSensible(string $nombre): bool
    {
        return $this->registro->definicion($nombre)->esSensible ?? false;
    }

    /** RUC del banco donde va la cuenta de detracciones (Banco de la Nación; plan 1.3b, D5). */
    public function rucBancoDetracciones(): string
    {
        return $this->texto('parties.ruc_banco_detracciones');
    }

    /**
     * Cambia el valor (y la descripción, si llega) si sigue en la versión que el usuario leyó (bloqueo
     * optimista). Deja bitácora. Crear y borrar parámetros sigue en código: RegistroParametros (plan 1.2b, E2).
     *
     * @param  mixed  $valor  en su tipo de PHP (lo que entrega Campo::convertir)
     * @param  ?string  $descripcion  null conserva la actual
     *
     * @throws ParametroNoDisponible|ParametroRechazado|VersionDesactualizada
     */
    public function cambiar(string $nombre, mixed $valor, int $versionLeida, ?string $descripcion = null): void
    {
        // Un parámetro de dinero se cambia solo con cambiarDeDinero(), que llama la acción de su permiso (plan 1.3b).
        // Uno que ni se edita lo dice aplicar() («no se puede cambiar»), que es lo más cierto.
        if ($this->esSensible($nombre) && ($this->registro->definicion($nombre)->esEditable ?? true)) {
            throw ParametroRechazado::sensible($nombre);
        }

        $this->aplicar($nombre, $valor, $versionLeida, $descripcion);
    }

    /**
     * Igual que cambiar(), para un parámetro de dinero (`esSensible`). Lo llama solo la acción «Cambiar parámetro de
     * dinero», que exige `parametros.editar-sensibles`.
     *
     * @throws ParametroNoDisponible|ParametroRechazado|VersionDesactualizada
     */
    public function cambiarDeDinero(string $nombre, mixed $valor, int $versionLeida, ?string $descripcion = null): void
    {
        if (! $this->esSensible($nombre)) {
            throw ParametroRechazado::noSensible($nombre);
        }

        $this->aplicar($nombre, $valor, $versionLeida, $descripcion);
    }

    private function aplicar(string $nombre, mixed $valor, int $versionLeida, ?string $descripcion): void
    {
        $descripcion = $descripcion === null ? null : trim($descripcion);

        if ($descripcion === '') {
            throw ParametroRechazado::descripcionVacia($nombre);
        }

        DB::transaction(function () use ($nombre, $valor, $versionLeida, $descripcion): void {
            $parametro = $this->consulta($nombre)->lockForUpdate()->first() ?? throw ParametroNoDisponible::noExiste($nombre);

            if (! $parametro->es_editable) {
                throw ParametroRechazado::noEditable($nombre);
            }

            if ($parametro->version !== $versionLeida) {
                throw VersionDesactualizada::de("el parámetro «{$nombre}»");
            }

            $valor = $this->validarRegla($nombre, $valor);
            $parametro->valor = $this->aTexto($parametro, $valor);

            if ($descripcion !== null) {
                $parametro->descripcion = $descripcion;
            }

            $parametro->version++;
            $parametro->save();
        });

        unset($this->leidos[$nombre]);
    }

    /**
     * Igual que cambiar(), con el valor tal como lo escribe una persona: se interpreta según el tipo
     * del parámetro (la acción no conoce el tipo de antemano).
     *
     * @throws ParametroNoDisponible|ParametroRechazado|VersionDesactualizada
     */
    public function cambiarDesdeTexto(string $nombre, string $entrada, int $versionLeida, ?string $descripcion = null, bool $deDinero = false): void
    {
        $tipo = $this->parametro($nombre)->tipo;
        $valor = $tipo->interpretar($entrada) ?? throw ParametroRechazado::valorInvalido($nombre, $tipo);

        $deDinero ? $this->cambiarDeDinero($nombre, $valor, $versionLeida, $descripcion) : $this->cambiar($nombre, $valor, $versionLeida, $descripcion);
    }

    /**
     * Lo que una pantalla necesita para editar: valor actual, tipo y versión.
     *
     * @return array{nombre: string, tipo: TipoParametro, valor: string|int|bool|CarbonImmutable, version: int, es_editable: bool, descripcion: string}
     */
    public function ficha(string $nombre): array
    {
        $parametro = $this->parametro($nombre);

        return [
            'nombre' => $nombre,
            'tipo' => $parametro->tipo,
            'valor' => $parametro->tipo->desdeTexto($parametro->valor),
            'version' => $parametro->version,
            'es_editable' => $parametro->es_editable,
            'descripcion' => $parametro->descripcion,
        ];
    }

    private function valor(string $nombre, TipoParametro $pedido): string|int|bool|CarbonImmutable
    {
        $parametro = $this->parametro($nombre);

        if ($parametro->tipo !== $pedido) {
            throw ParametroNoDisponible::deOtroTipo($nombre, $parametro->tipo->value, $pedido->value);
        }

        return $parametro->tipo->desdeTexto($parametro->valor);
    }

    private function parametro(string $nombre): Parametro
    {
        return $this->leidos[$nombre] ??= $this->consulta($nombre)->first() ?? throw ParametroNoDisponible::noExiste($nombre);
    }

    /**
     * @return Builder<Parametro>
     */
    private function consulta(string $nombre): Builder
    {
        [$grupo, $clave] = array_pad(explode('.', $nombre, 2), 2, '');

        return Parametro::query()->where('grupo', $grupo)->where('clave', $clave);
    }

    private function aTexto(Parametro $parametro, mixed $valor): string
    {
        $nombre = "{$parametro->grupo}.{$parametro->clave}";

        try {
            $texto = $parametro->tipo->aTexto($valor);
        } catch (\LogicException) {
            throw ParametroRechazado::valorInvalido($nombre, $parametro->tipo);
        }

        // La forma que exige el CHECK de la tabla: un número del formulario puede llegar como «.5» o «+5».
        if ($parametro->tipo === TipoParametro::Decimal && preg_match('/^-?\d+(\.\d+)?$/', $texto) !== 1) {
            throw ParametroRechazado::valorInvalido($nombre, $parametro->tipo);
        }

        return $texto;
    }

    /**
     * La regla de negocio que declaró el parámetro, si tiene (`DefinicionParametro::$validador`). Devuelve el
     * valor normalizado, que es el que se guarda.
     *
     * @throws ParametroRechazado
     */
    private function validarRegla(string $nombre, mixed $valor): mixed
    {
        $clase = $this->registro->definicion($nombre)?->validador;

        if ($clase === null) {
            return $valor;
        }

        /** @var ValidadorParametro $validador */
        $validador = app($clase);
        $valor = $validador->normalizar($valor);
        $motivo = $validador->rechazo($valor);

        if ($motivo !== null) {
            throw ParametroRechazado::noAceptado($nombre, $motivo);
        }

        return $valor;
    }
}
