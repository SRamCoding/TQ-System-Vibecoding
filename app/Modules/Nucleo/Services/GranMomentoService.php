<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Services;

use App\Modules\Nucleo\Enums\IdiomaSaludo;
use App\Modules\Nucleo\Enums\Ocasion;
use App\Modules\Nucleo\Enums\TipoFraseSaludo;
use App\Modules\Nucleo\Excepciones\GranMomentoRechazado;
use App\Modules\Nucleo\Models\CategoriaFrase;
use App\Modules\Nucleo\Models\FraseSaludo;
use App\Modules\Nucleo\Models\GranMomento;
use App\Modules\Nucleo\Models\MomentoVisto;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Saludo\Ambientacion;
use App\Modules\Nucleo\Saludo\CalendarioFestivo;
use App\Modules\Nucleo\Saludo\EscenaGranMomento;
use App\Modules\Nucleo\Saludo\GranMomentoPendiente;
use App\Modules\Nucleo\Saludo\RegistroGranMomento;
use App\Modules\Nucleo\Saludo\RotacionAnual;
use App\Modules\Nucleo\Saludo\ValidadorAniversario;
use App\Modules\Nucleo\Saludo\VarianteGranMomento;
use App\Modules\Nucleo\Tiempo\Calendario;
use App\Modules\Nucleo\Tiempo\CalendarioLaboral;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * El gran momento de las fechas (plan 1.4d). Cada fecha tiene un día clave; se muestra desde ese día si es hábil (o el
 * primer día hábil después) hasta su plazo, que no se mueve con los feriados (D3). De los vigentes, solo el más
 * reciente; si la persona ya lo vio, nada (D4). Qué variante y qué versículo tocan se elige una vez por año y queda
 * guardado: el mismo para todas las personas, sin repetir lo del año anterior (D7). Quien apagó la ambientación, o la
 * empresa, no lo ve (D9).
 */
final readonly class GranMomentoService
{
    /** Las fechas con gran momento, las cinco con logo propio. */
    public const array OCASIONES = [Ocasion::Navidad, Ocasion::AnioNuevo, Ocasion::SemanaSanta, Ocasion::FiestasPatrias, Ocasion::Aniversario];

    public function __construct(
        private RegistroGranMomento $registro,
        private CalendarioLaboral $laboral,
        private ParametrosService $parametros,
        private Ambientacion $ambientacion,
        private UsuarioSistema $sistema,
    ) {}

    /**
     * El día clave de una fecha en un año, o nulo: el aniversario sin fecha de fundación, o antes del primer año.
     */
    public function diaClave(Ocasion $ocasion, int $anio): ?CarbonImmutable
    {
        $dia = fn (int $mes, int $dia): CarbonImmutable => CarbonImmutable::create($anio, $mes, $dia, 0, 0, 0, Calendario::ZONA) ?? throw new LogicException('Fecha imposible.');

        return match ($ocasion) {
            Ocasion::Navidad => $dia(12, 25),
            Ocasion::AnioNuevo => $dia(1, 1),
            Ocasion::SemanaSanta => CalendarioFestivo::domingoDeResurreccion($anio)->setTimezone(Calendario::ZONA)->startOfDay(),
            Ocasion::FiestasPatrias => $dia(7, 28),
            Ocasion::Aniversario => $this->aniversario($anio),
            default => null,
        };
    }

    /**
     * Desde cuándo y hasta cuándo sale el gran momento de una fecha un año, o nulo si ese año no sale (sin día clave, o
     * el primer día hábil cae después del plazo).
     *
     * @return ?array{desde: CarbonImmutable, hasta: CarbonImmutable}
     */
    public function ventana(Ocasion $ocasion, int $anio): ?array
    {
        $clave = $this->diaClave($ocasion, $anio);

        if ($clave === null) {
            return null;
        }

        $desde = $this->laboral->primerHabilDesde($clave);
        $hasta = $clave->addDays($this->parametros->entero("interfaz.gran_momento_plazo_{$ocasion->value}"));

        return $desde->greaterThan($hasta) ? null : ['desde' => $desde, 'hasta' => $hasta];
    }

    /**
     * El gran momento vigente hoy, el de día clave más reciente: la fecha y el año. Solo fechas con alguna variante.
     *
     * @return ?array{0: Ocasion, 1: int}
     */
    public function vigente(CarbonImmutable $ahora): ?array
    {
        $hoy = $ahora->setTimezone(Calendario::ZONA)->startOfDay();
        $mejor = null;

        foreach (self::OCASIONES as $ocasion) {
            if ($this->registro->de($ocasion) === []) {
                continue;
            }

            foreach ([$hoy->year - 1, $hoy->year] as $anio) {
                $ventana = $this->ventana($ocasion, $anio);
                $clave = $this->diaClave($ocasion, $anio);

                if ($ventana === null || $clave === null || $hoy->lessThan($ventana['desde']) || $hoy->greaterThan($ventana['hasta'])) {
                    continue;
                }

                if ($mejor === null || $clave->greaterThan($mejor[2])) {
                    $mejor = [$ocasion, $anio, $clave];
                }
            }
        }

        return $mejor === null ? null : [$mejor[0], $mejor[1]];
    }

    /**
     * Lo que le toca ver hoy a la persona, o nada: sin vigente, ya visto, o con la ambientación apagada.
     */
    public function pendientePara(User $usuario, CarbonImmutable $ahora): ?GranMomentoPendiente
    {
        $vigente = $this->vigente($ahora);

        if ($vigente === null || ! $this->ambientacion->estaActiva($usuario)) {
            return null;
        }

        $elegido = $this->elegido(...$vigente);

        if (MomentoVisto::query()->where('user_id', $usuario->id)->where('gran_momento_id', $elegido->id)->exists()) {
            return null;
        }

        $escena = $this->escena($elegido->id) ?? throw new LogicException('La variante elegida no está registrada.');

        return new GranMomentoPendiente($elegido->id, $escena);
    }

    /**
     * Lo que se dibuja de un gran momento ya elegido: su variante y su versículo, si sigue activo. Nula si ya no existe
     * o su variante no está registrada.
     */
    public function escena(int $granMomentoId): ?EscenaGranMomento
    {
        $elegido = GranMomento::query()->find($granMomentoId);
        $variante = $elegido === null ? null : $this->registro->variante($elegido->ocasion, $elegido->variante);

        if ($elegido === null || $variante === null) {
            return null;
        }

        $versiculo = $elegido->frase_saludo_id === null ? null : FraseSaludo::query()->activos()->find($elegido->frase_saludo_id);

        return new EscenaGranMomento($variante, $elegido->anio, $versiculo?->texto, $versiculo?->fuente);
    }

    /**
     * «Lo vi» (D4): solo el gran momento vigente; marcarlo dos veces no falla. Devuelve si fue la primera marca: la
     * pantalla solo lo empieza entonces (D5).
     *
     * @throws GranMomentoRechazado
     */
    public function marcarVisto(User $usuario, int $granMomentoId, CarbonImmutable $ahora): bool
    {
        $vigente = $this->vigente($ahora);

        if ($vigente === null || $this->elegido(...$vigente)->id !== $granMomentoId) {
            throw GranMomentoRechazado::noVigente();
        }

        return MomentoVisto::query()->insertOrIgnore([
            'user_id' => $usuario->id, 'gran_momento_id' => $granMomentoId, 'created_by' => $usuario->id, 'created_at' => now(),
        ]) === 1;
    }

    /**
     * La elección del año para una fecha: la guardada o, la primera vez, una nueva que salta la variante y el versículo
     * del año anterior. Si la guardada ya no existe en el registro, se elige otra y se actualiza la fila (D7).
     */
    private function elegido(Ocasion $ocasion, int $anio): GranMomento
    {
        $guardado = GranMomento::query()->where('ocasion', $ocasion->value)->where('anio', $anio)->first();

        if ($guardado !== null && $this->registro->variante($ocasion, $guardado->variante) !== null) {
            return $guardado;
        }

        $anterior = GranMomento::query()->where('ocasion', $ocasion->value)->where('anio', $anio - 1)->first();
        $variante = (string) RotacionAnual::elegir(array_map(fn (VarianteGranMomento $v): string => $v->clave, $this->registro->de($ocasion)), $anio, $ocasion->value, $anterior?->variante);

        return DB::transaction(function () use ($ocasion, $anio, $variante, $anterior): GranMomento {
            // «insert … on conflict do nothing»: si dos personas entran a la vez, gana una sola elección.
            GranMomento::query()->insertOrIgnore([
                'ocasion' => $ocasion->value, 'anio' => $anio, 'variante' => $variante,
                'frase_saludo_id' => RotacionAnual::elegir(array_map(fn (FraseSaludo $frase): int => $frase->id, $this->versiculos($ocasion)), $anio, "versiculo|{$ocasion->value}", $anterior?->frase_saludo_id),
                'created_by' => $this->sistema->id(), 'created_at' => now(),
            ]);
            $fila = GranMomento::query()->where('ocasion', $ocasion->value)->where('anio', $anio)->lockForUpdate()->firstOrFail();

            // La guardada ya no está en el registro: otra, para todos igual.
            if ($this->registro->variante($ocasion, $fila->variante) === null) {
                $fila->variante = $variante;
                $fila->save();
            }

            return $fila;
        });
    }

    /** El aniversario de Techquk en ese año, o nulo sin fecha de fundación o antes del primer aniversario. */
    private function aniversario(int $anio): ?CarbonImmutable
    {
        $fundacion = ValidadorAniversario::fecha($this->parametros->texto('interfaz.aniversario_techquk'));

        if ($fundacion === null || $anio <= $fundacion['anio']) {
            return null;
        }

        // Un 29 de febrero se celebra el 28 en los años no bisiestos.
        $dia = $fundacion['mes'] === 2 && $fundacion['dia'] === 29 && ! checkdate(2, 29, $anio) ? 28 : $fundacion['dia'];

        return CarbonImmutable::create($anio, $fundacion['mes'], $dia, 0, 0, 0, Calendario::ZONA);
    }

    /**
     * Los versículos en español de la fecha, activos y por código (D7): ya cotejados con RVR1960 en el catálogo.
     *
     * @return list<FraseSaludo>
     */
    public function versiculos(Ocasion $ocasion): array
    {
        $versiculos = CategoriaFrase::query()->where('codigo', 'versiculos')->value('id');

        return array_values(FraseSaludo::query()
            ->where('tipo', TipoFraseSaludo::Frase->value)
            ->where('categoria_frase_id', $versiculos)
            ->where('ocasion', $ocasion->value)
            ->where('idioma', IdiomaSaludo::Espanol->value)
            ->where('es_activa', true)
            ->orderBy('codigo')
            ->get()
            ->all());
    }

    /**
     * La escena de una variante para la vista previa (D8): con el versículo que tocaría ese año por la rotación. No
     * guarda nada ni marca «Lo vi».
     */
    public function previa(Ocasion $ocasion, string $clave, int $anio): ?EscenaGranMomento
    {
        $variante = $this->registro->variante($ocasion, $clave);

        if ($variante === null) {
            return null;
        }

        $versiculos = $this->versiculos($ocasion);
        $id = RotacionAnual::elegir(array_map(fn (FraseSaludo $frase): int => $frase->id, $versiculos), $anio, "versiculo|{$ocasion->value}");
        $versiculo = collect($versiculos)->firstWhere('id', $id);

        return new EscenaGranMomento($variante, $anio, $versiculo?->texto, $versiculo?->fuente);
    }
}
