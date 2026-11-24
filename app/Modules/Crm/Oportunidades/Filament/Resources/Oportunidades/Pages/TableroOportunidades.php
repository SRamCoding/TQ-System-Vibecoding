<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages;

use App\Modules\Crm\Oportunidades\Enums\LugarFaja;
use App\Modules\Crm\Oportunidades\Enums\TallaDeGema;
use App\Modules\Crm\Oportunidades\Filament\DecideLoPorCalificar;
use App\Modules\Crm\Oportunidades\Filament\MuestraLaFicha;
use App\Modules\Crm\Oportunidades\Filament\MuestraLasVistas;
use App\Modules\Crm\Oportunidades\Filament\PanelesOportunidad;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\OportunidadResource;
use App\Modules\Crm\Oportunidades\Filament\VistasDeOportunidades;
use App\Modules\Crm\Oportunidades\OportunidadesServiceProvider;
use App\Modules\Crm\Oportunidades\Services\FiltrosOportunidades;
use App\Modules\Crm\Oportunidades\Services\OportunidadesService;
use App\Modules\Crm\Oportunidades\Services\PortadaOportunidades;
use App\Modules\Crm\Oportunidades\Services\ProximaAccionDeOportunidades;
use App\Modules\Crm\Oportunidades\Services\ResumenOportunidad;
use App\Modules\Crm\Oportunidades\Services\TableroDeBotonera;
use App\Modules\Crm\Oportunidades\Services\TableroDeGemas;
use App\Modules\Nucleo\Excepciones\ErrorDeNegocio;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;

/**
 * El Embudo de oportunidades (MV-057, MV-058; antes «Tablero»): las cuatro cifras, el embudo clásico con sus gemas, Ganadas
 * y Perdidas del año y el panel de la etapa elegida (`elegirEtapa`), cuya lista abre la ficha deslizante. Vista
 * `oportunidades::embudo.pantalla`.
 */
final class TableroOportunidades extends Page
{
    use DecideLoPorCalificar;
    use MuestraLaFicha;
    use MuestraLasVistas;

    protected static string $resource = OportunidadResource::class;

    protected static ?string $title = 'Embudo de oportunidades';

    protected static ?string $breadcrumb = 'Tablero';

    /** Soltar una tarjeta fuera de las columnas (D4): vuelve y lo dice, sin llamar a nada. */
    public const string SUELTALA_EN = 'Suéltala en la etapa siguiente, en Ganadas (desde Propuesta enviada o Negociación) o en Perdidas.';

    public static function canAccess(array $parameters = []): bool
    {
        return OportunidadResource::usuarioPuede(OportunidadesServiceProvider::PERMISO_VER);
    }

    public static function vista(): string
    {
        return VistasDeOportunidades::TABLERO;
    }

    public function mount(): void
    {
        $aviso = request()->query('aviso');

        if (is_string($aviso) && in_array($aviso, PortadaOportunidades::AVISOS, true)) {
            $this->redirect(TarjetasOportunidades::getUrl(['aviso' => $aviso]));

            return;
        }

        $this->calcularCifras();
        $this->abrirFichaDeLaDireccion();
    }

    /** La etapa cuya tabla está desplegada debajo del tablero (plan 2.21d, D4), o null. */
    #[Url(as: 'etapa')]
    public ?string $etapaAbierta = null;

    /** La botonera de una etapa (D4): despliega su tabla o la oculta. */
    public function abrirEtapa(string $lugar): void
    {
        $this->etapaAbierta = $this->etapaAbierta === $lugar || LugarFaja::tryFrom($lugar) === null ? null : $lugar;
    }

    /** MV-058: elegir una franja del embudo (o Ganadas o Perdidas) cambia el panel de la derecha. */
    public function elegirEtapa(string $lugar): void
    {
        if (LugarFaja::tryFrom($lugar) !== null || $lugar === TallaDeGema::POR_CALIFICAR) {
            $this->etapaAbierta = $lugar;
        }
    }

    /**
     * MV-058: los datos del embudo: las cuatro cifras, las cuatro etapas abiertas (con su gema, probabilidad, monto,
     * cantidad y cuántas avanzan a la siguiente), Ganadas y Perdidas, y la lista de la etapa elegida.
     *
     * @return array<string, mixed>
     */
    private function datosEmbudo(): array
    {
        $columnas = app(TableroDeGemas::class)->columnas();
        $abiertas = [LugarFaja::Prospecto, LugarFaja::Calificada, LugarFaja::PropuestaEnviada, LugarFaja::Negociacion];
        $estilo = [
            'prospecto' => [100, '#A6F2FF', '#7FE9FF'], 'calificada' => [86, '#7FE9FF', '#4DDDF5'],
            'propuesta_enviada' => [72, '#4DDDF5', '#1FCDEB'], 'negociacion' => [58, '#1FCDEB', '#00B8D9'],
        ];
        $soles = fn (string $m): string => 'S/ '.number_format((float) $m, 0);
        // Cuántas pasan a la siguiente: las que están más adelante (y las ganadas) sobre las que llegaron a esta o más allá.
        $adelante = fn (int $i): int => array_sum(array_map(fn (LugarFaja $l): int => $columnas[$l->value]->cantidad, array_slice($abiertas, $i))) + $columnas['ganadas']->cantidad;
        $etapas = [];

        foreach ($abiertas as $i => $lugar) {
            $c = $columnas[$lugar->value];
            $talla = TallaDeGema::desdeEtapa($lugar->etapa()?->value ?? $lugar->value);
            $siguiente = $i < 3 ? $adelante($i + 1) : null;
            $etapas[] = [
                'lugar' => $lugar->value, 'nombre' => $lugar->etiqueta(), 'talla' => $talla, 'prob' => ($c->probabilidad ?? 0).' %',
                'monto' => $soles($c->totalEnSoles), 'cantidad' => $c->cantidad, 'ancho' => $estilo[$lugar->value][0],
                'color1' => $estilo[$lugar->value][1], 'color2' => $estilo[$lugar->value][2],
                'avanza' => $siguiente === null || $adelante($i) === 0 ? null : (int) round($siguiente * 100 / $adelante($i)).' %',
                'atascadas' => count(array_filter($c->visibles, fn ($f) => $f->atascada)),
            ];
        }

        // Observación 8.2: «Por calificar» también abre su panel.
        if ($this->etapaAbierta === TallaDeGema::POR_CALIFICAR) {
            return $this->conPanelPorCalificar($etapas, $soles);
        }

        if ($this->etapaAbierta === null || LugarFaja::tryFrom($this->etapaAbierta) === null) {
            $masAtascada = collect($etapas)->sortByDesc('atascadas')->first();
            $this->etapaAbierta = ($masAtascada['atascadas'] ?? 0) > 0 ? $masAtascada['lugar'] : LugarFaja::PropuestaEnviada->value;
        }

        $elegida = LugarFaja::from($this->etapaAbierta);
        $col = $columnas[$elegida->value];
        $filas = $elegida->esSala() ? [] : app(OportunidadesService::class)->destacadasDeLaFaja($elegida, 50);
        $proximas = app(ProximaAccionDeOportunidades::class)->deLote(array_map(fn ($f) => $f->id, $filas));
        $tallaElegida = match ($elegida) {
            LugarFaja::Ganadas => TallaDeGema::Brillante,
            LugarFaja::Perdidas => TallaDeGema::Fisurada,
            default => TallaDeGema::desdeEtapa($elegida->etapa()?->value ?? $elegida->value),
        };

        $filasPanel = array_map(fn ($f): array => [
            'id' => $f->id, 'nombre' => $f->nombre, 'cliente' => Str::before($f->cuenta, ' · '),
            'monto' => $f->montoEnSoles !== null ? $soles($f->montoEnSoles) : $f->simbolo.' '.number_format((float) $f->monto, 0),
            'dias' => $f->diasEnEtapa === 1 ? '1 día' : $f->diasEnEtapa.' días',
            'accion' => $proximas[$f->id]['texto'] ?? 'Sin próxima acción',
            'vencida' => ! isset($proximas[$f->id]) || ($proximas[$f->id]['vencida'] ?? false),
        ], $filas);
        $resumen = $col->cantidad.' oportunidades · '.$soles($col->totalEnSoles).($col->ponderadoEnSoles !== null ? ' · ponderado '.$soles($col->ponderadoEnSoles) : '');

        if ($elegida->esSala()) {
            $sala = $this->delAnio($elegida === LugarFaja::Ganadas ? 'ganada' : 'perdida');
            $filasPanel = array_map(fn (array $o): array => ['id' => $o['id'], 'nombre' => $o['nombre'], 'cliente' => $o['cliente'], 'monto' => $soles($o['monto']),
                'dias' => ($elegida === LugarFaja::Ganadas ? 'ganada el ' : 'perdida el ').$o['fecha'], 'accion' => '', 'vencida' => $elegida === LugarFaja::Perdidas], $sala);
            $resumen = count($sala).' este año · '.$soles(self::sumar(array_column($sala, 'monto')));
        }

        return [
            ...$this->datosBase($etapas, $soles),
            'elegida' => [
                'lugar' => $elegida->value, 'nombre' => $elegida->etiqueta(), 'talla' => $tallaElegida,
                'resumen' => $resumen,
                'filas' => $filasPanel,
                'porCalificar' => null,
            ],
            'urlMias' => TarjetasOportunidades::getUrl(),
        ];
    }

    /**
     * Lo que va igual con cualquier etapa elegida: la boca «Por calificar» (observación 7.1), las etapas, Ganadas y
     * Perdidas.
     *
     * @param  list<array<string, mixed>>  $etapas
     * @return array<string, mixed>
     */
    private function datosBase(array $etapas, \Closure $soles): array
    {
        $porCalificar = app(PortadaOportunidades::class)->porCalificar(new FiltrosOportunidades);
        $leads = count(array_filter($porCalificar, fn (array $t): bool => $t['clase'] === 'lead'));

        return [
            'porCalificar' => [
                'cantidad' => count($porCalificar), 'leads' => $leads, 'licitaciones' => count($porCalificar) - $leads,
                'referencial' => $soles(self::sumar(array_values(array_filter(array_column($porCalificar, 'monto'))))),
                'url' => TarjetasOportunidades::getUrl(['etapa' => [TallaDeGema::POR_CALIFICAR]]),
            ],
            'etapas' => $etapas,
            'ganadas' => ['cantidad' => count($ganadas = $this->delAnio('ganada')), 'monto' => $soles(self::sumar(array_column($ganadas, 'monto')))],
            'perdidas' => ['cantidad' => count($perdidas = $this->delAnio('perdida')), 'monto' => $soles(self::sumar(array_column($perdidas, 'monto')))],
        ];
    }

    /**
     * Observación 8.2: el panel de «Por calificar»: primero las licitaciones por decidir por su próximo plazo, luego los
     * leads del más antiguo al más nuevo, cada uno con su origen, monto si lo hay y cuánto lleva esperando.
     *
     * @param  list<array<string, mixed>>  $etapas
     * @return array<string, mixed>
     */
    private function conPanelPorCalificar(array $etapas, \Closure $soles): array
    {
        $hoy = CarbonImmutable::parse(Calendario::hoy());
        $items = app(PortadaOportunidades::class)->porCalificar(new FiltrosOportunidades);
        usort($items, fn (array $a, array $b): int => [$a['clase'] === 'lead' ? 1 : 0, $a['clase'] === 'lead' ? $a['desde']->getTimestamp() : ($a['fecha']?->getTimestamp() ?? PHP_INT_MAX)]
            <=> [$b['clase'] === 'lead' ? 1 : 0, $b['clase'] === 'lead' ? $b['desde']->getTimestamp() : ($b['fecha']?->getTimestamp() ?? PHP_INT_MAX)]);
        $conMonto = array_values(array_filter(array_column($items, 'monto')));
        $leads = count(array_filter($items, fn (array $t): bool => $t['clase'] === 'lead'));
        $base = $this->datosBase($etapas, $soles);

        return [...$base, 'elegida' => [
            'lugar' => TallaDeGema::POR_CALIFICAR, 'nombre' => 'Por calificar', 'talla' => TallaDeGema::EnRoca,
            'resumen' => count($items).' por calificar · '.$leads.' leads · '.(count($items) - $leads).' licitaciones'.($conMonto === [] ? '' : ' · ref. '.$soles(self::sumar($conMonto))),
            'filas' => [],
            'porCalificar' => array_map(function (array $t) use ($hoy): array {
                $espera = (int) $t['desde']->startOfDay()->diffInDays($hoy);
                $plazo = $t['hito'] === null ? null : ['texto' => $t['hito']['etapa'].' '.($t['hito']['dias'] < 0 ? 'venció' : ($t['hito']['dias'] === 0 ? 'hoy' : "en {$t['hito']['dias']} días")), 'urgente' => $t['hito']['dias'] < 5];

                return [...$t, 'espera' => $espera === 0 ? 'hoy' : ($espera === 1 ? 'hace 1 día' : "hace {$espera} días"), 'esperaUrgente' => $espera >= 3, 'plazo' => $plazo,
                    'montoTexto' => $t['monto'] === null ? null : 'S/ '.number_format((float) $t['monto'], 2)];
            }, $items),
        ], 'urlMias' => TarjetasOportunidades::getUrl(['etapa' => [TallaDeGema::POR_CALIFICAR]])];
    }

    /**
     * MV-058: las ganadas o perdidas de este año (por su fecha de cierre real), con su cliente y monto, para Ganadas y
     * Perdidas del embudo. Atajo del MVP: consulta directa y el monto estimado como está (sin convertir monedas).
     *
     * @return list<array{id: int, nombre: string, cliente: string, monto: string, fecha: string}>
     */
    private function delAnio(string $estado): array
    {
        return DB::table('oportunidades as o')->join('estados as e', 'e.id', '=', 'o.estado_id')
            ->join('cuentas as c', 'c.id', '=', 'o.cuenta_id')->join('parties as p', 'p.id', '=', 'c.party_id')
            ->where('e.codigo', $estado)->whereYear('o.fecha_cierre_real', Calendario::ahora()->year)
            ->orderByDesc('o.fecha_cierre_real')->get(['o.id', 'o.nombre', 'p.razon_social', 'o.monto_estimado', 'o.fecha_cierre_real'])
            ->map(fn (object $o): array => ['id' => (int) $o->id, 'nombre' => $o->nombre, 'cliente' => $o->razon_social, 'monto' => (string) $o->monto_estimado,
                'fecha' => CarbonImmutable::parse($o->fecha_cierre_real)->format('d/m')])->all();
    }

    /** @param  list<string>  $montos */
    private static function sumar(array $montos): string
    {
        return array_reduce($montos, fn (string $t, string $m): string => bcadd($t, $m, 2), '0');
    }

    /** Marcar en la tabla (D5): la pone en el tablero (hasta 5 por etapa) o la quita. Por la acción registrada. */
    public function alternarEnTablero(int $oportunidadId, bool $poner): void
    {
        try {
            app(EjecutorAcciones::class)->ejecutar($poner ? TableroDeBotonera::FIJAR : TableroDeBotonera::SOLTAR, ['oportunidad_id' => $oportunidadId], self::usuario());
        } catch (ErrorDeNegocio|ValidationException $e) {
            Notification::make()->warning()->title($e->getMessage())->send();
        }
    }

    private static function usuario(): User
    {
        $usuario = Filament::auth()->user();

        return $usuario instanceof User ? $usuario : throw new AuthorizationException;
    }

    /**
     * @return list<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            PanelesOportunidad::nueva(null, function (mixed $resultado): void {
                Notification::make()->success()->title('Oportunidad creada.')->send();

                if ($resultado instanceof ResumenOportunidad) {
                    $this->redirect(VerOportunidad::getUrl(['record' => $resultado->id]), navigate: true);
                }
            }),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->cabeceraDeVistas(),
            // MV-058: el embudo clásico con sus gemas (lienzo «A refinada»), en lugar de la botonera.
            View::make('oportunidades::embudo.pantalla')->viewData(fn (): array => $this->datosEmbudo()),
            $this->fichaDeOportunidad(),
        ]);
    }
}
