<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages;

use App\Modules\Crm\Campanias\Filament\Resources\Leads\LeadResource;
use App\Modules\Crm\Campanias\Services\LeadsService;
use App\Modules\Crm\Licitaciones\Filament\Resources\Licitaciones\LicitacionResource;
use App\Modules\Crm\Licitaciones\Services\LicitacionesService;
use App\Modules\Crm\Oportunidades\Enums\EtapaAbierta;
use App\Modules\Crm\Oportunidades\Filament\DecideLoPorCalificar;
use App\Modules\Crm\Oportunidades\Filament\DibujoDeLinea;
use App\Modules\Crm\Oportunidades\Filament\MuestraLaFicha;
use App\Modules\Crm\Oportunidades\Filament\MuestraLasVistas;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\OportunidadResource;
use App\Modules\Crm\Oportunidades\Filament\VistasDeOportunidades;
use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Crm\Oportunidades\OportunidadesServiceProvider;
use App\Modules\Crm\Oportunidades\Services\FiltrosOportunidades;
use App\Modules\Crm\Oportunidades\Services\FotoOportunidadService;
use App\Modules\Crm\Oportunidades\Services\PortadaOportunidades;
use App\Modules\Nucleo\Models\LineaSolucion;
use App\Modules\Nucleo\Services\EstadoInterfazService;
use Carbon\CarbonImmutable;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Pagination\Paginator;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * La vista «Tarjetas» de Oportunidades (plan 2.21d, D1 y D11; prototipo aprobado
 * `docs/diseno/fuentes/prototipos/oportunidades-portada-ficha.html`), con el lenguaje de la portada de Proyectos (2.61):
 * cada tarjeta lleva arriba la foto de referencia o el dibujo de su línea de solución, con la línea y la etapa encima;
 * el anillo con la probabilidad, código, nombre, entidad, monto y ponderado, días en la etapa contra su plazo y el
 * ejecutivo. **Sin la barra de etapas** (D1): la ficha la muestra completa. A la izquierda, los filtros con su cantidad
 * (Atención, Etapa, Ejecutivo, Línea de solución, Cliente); arriba, las pestañas de vista con el embudo, el buscador y
 * el orden. Tocar una tarjeta lleva a su ficha completa (pedido del desarrollador, 2026-11-16: sin el panel lateral).
 */
final class TarjetasOportunidades extends Page
{
    use DecideLoPorCalificar;
    use MuestraLaFicha;
    use MuestraLasVistas;
    use WithPagination;

    public const int POR_PAGINA = 24;

    /** Observación 7.1: a menos de estos días, el plazo de la licitación va en coral. */
    public const int DIAS_URGENTE = 5;

    protected static string $resource = OportunidadResource::class;

    protected static ?string $title = 'Mis oportunidades';

    protected static ?string $breadcrumb = 'Tarjetas';

    #[Url(as: 'q', except: '')]
    public string $buscar = '';

    /** @var list<string> */
    #[Url(as: 'atencion', except: [])]
    public array $atencion = [];

    /** @var list<string> */
    #[Url(as: 'etapa', except: [])]
    public array $etapas = [];

    /** @var list<int|string> */
    #[Url(as: 'ejecutivo', except: [])]
    public array $ejecutivos = [];

    /** @var list<int|string> */
    #[Url(as: 'linea', except: [])]
    public array $lineas = [];

    /** @var list<string> */
    #[Url(as: 'cliente', except: [])]
    public array $clientes = [];

    /** @var list<string> Observación 7.1: Privada · Licitación pública · Licitación privada. */
    #[Url(as: 'tipo', except: [])]
    public array $tipos = [];

    #[Url(as: 'orden', except: 'monto')]
    public string $orden = 'monto';

    public static function canAccess(array $parameters = []): bool
    {
        return OportunidadResource::usuarioPuede(OportunidadesServiceProvider::PERMISO_VER);
    }

    public static function vista(): string
    {
        return VistasDeOportunidades::TARJETAS;
    }

    /** @var list<string> Observación 8.3: los grupos de filtros abiertos (por persona). */
    public array $abiertos = self::ABIERTOS;

    public const array ABIERTOS = ['atencion', 'etapas'];

    public const string CLAVE_ABIERTOS = 'oportunidades.filtros_abiertos';

    /** Observación 8.3: abre o pliega un grupo de filtros y lo recuerda para la persona. */
    public function plegar(string $grupo): void
    {
        $this->abiertos = in_array($grupo, $this->abiertos, true) ? array_values(array_diff($this->abiertos, [$grupo])) : [...$this->abiertos, $grupo];
        app(EstadoInterfazService::class)->guardar((int) auth()->id(), self::CLAVE_ABIERTOS, $this->abiertos);
        // Observación 8.13: las demás pestañas de la persona pliegan o abren lo mismo.
        $this->dispatch('tq-filtros-abiertos-guardados', abiertos: $this->abiertos);
    }

    public function mount(): void
    {
        $this->abiertos = (array) app(EstadoInterfazService::class)->leer((int) auth()->id(), self::CLAVE_ABIERTOS, self::ABIERTOS);
        $this->calcularCifras();
        $this->abrirFichaDeLaDireccion();
    }

    public function updated(string $propiedad): void
    {
        $this->resetPage();
    }

    public function quitarFiltros(): void
    {
        $this->reset('buscar', 'atencion', 'etapas', 'ejecutivos', 'lineas', 'clientes', 'tipos');
        $this->resetPage();
    }

    public function filtros(): FiltrosOportunidades
    {
        return new FiltrosOportunidades(
            texto: $this->buscar,
            atencion: array_map(strval(...), $this->atencion),
            etapas: array_map(strval(...), $this->etapas),
            ejecutivos: array_map(intval(...), $this->ejecutivos),
            lineas: array_map(intval(...), $this->lineas),
            clientes: array_map(strval(...), $this->clientes),
            orden: array_key_exists($this->orden, FiltrosOportunidades::ORDENES) ? $this->orden : 'monto',
            tipos: array_values(array_intersect(array_map(strval(...), $this->tipos), array_keys(FiltrosOportunidades::TIPOS))),
        );
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->cabeceraDeVistas(),
            View::make('oportunidades::tarjetas.pantalla')->viewData(fn (): array => $this->datosDeLaVista()),
            $this->fichaDeOportunidad(),
        ]);
    }

    /** @return array<string, mixed> */
    private function datosDeLaVista(): array
    {
        $portada = app(PortadaOportunidades::class);
        $filtros = $this->filtros();
        /** @var Paginator<int, Oportunidad> $pagina */
        $pagina = $portada->ordenarVitrina($portada->filtrarVitrina($filtros)->with(['estado', 'ejecutivo:id,name']), $filtros->orden)->paginate(self::POR_PAGINA);
        $oportunidades = $pagina->items();
        $cuentas = OportunidadResource::cuentas(array_map(fn (Oportunidad $o): int => $o->cuenta_id, $oportunidades));
        $lineas = LineaSolucion::query()->whereKey(array_map(fn (Oportunidad $o): int => $o->linea_solucion_id, $oportunidades))->get(['id', 'codigo', 'nombre'])->keyBy('id');
        $licitaciones = $portada->licitaciones();
        // Observación 7.1: las tarjetas por calificar van primero (son la primera etapa), solo en la primera página.
        $porCalificar = $pagina->currentPage() === 1 ? $portada->porCalificar($filtros) : [];
        usort($porCalificar, fn (array $a, array $b): int => [$a['fecha'] === null ? 1 : 0, $a['fecha']?->getTimestamp() ?? 0, $a['desde']->getTimestamp()] <=> [$b['fecha'] === null ? 1 : 0, $b['fecha']?->getTimestamp() ?? 0, $b['desde']->getTimestamp()]);

        return [
            'pagina' => $pagina,
            'porCalificar' => array_map(fn (array $t): array => [...$t, 'plazo' => self::plazo($t['hito']), 'montoTexto' => $t['monto'] === null ? null : 'S/ '.number_format((float) $t['monto'], 2)], $porCalificar),
            'puedeLeads' => LeadResource::puede(LeadsService::PERMISO_GESTIONAR),
            'puedeLicitaciones' => LicitacionResource::puede(LicitacionesService::PERMISO_GESTIONAR),
            'tarjetas' => array_map(fn (Oportunidad $o): array => [...self::tarjeta($o, $cuentas[$o->cuenta_id] ?? '—', $lineas->get($o->linea_solucion_id)), 'plazo' => in_array($o->estado->codigo, ['ganada', 'perdida'], true) ? null : self::plazo($licitaciones[$o->id]['hito'] ?? null), 'licitacion' => isset($licitaciones[$o->id])], $oportunidades),
            'opciones' => $portada->opcionesVitrina($filtros),
            'ordenes' => FiltrosOportunidades::ORDENES,
            'conFiltros' => $this->buscar !== '' || $this->atencion !== [] || $this->etapas !== [] || $this->ejecutivos !== [] || $this->lineas !== [] || $this->clientes !== [] || $this->tipos !== [],
            'fichaAbierta' => $this->ficha,
        ];
    }

    /**
     * Observación 7.1: el próximo plazo de la licitación, como se lee en la tarjeta («Presentación en 3 días»); coral a
     * menos de 5 días o vencido.
     *
     * @param  array{etapa: string, fecha: CarbonImmutable, dias: int}|null  $hito
     * @return array{texto: string, urgente: bool, titulo: string}|null
     */
    private static function plazo(?array $hito): ?array
    {
        if ($hito === null) {
            return null;
        }

        $d = $hito['dias'];
        $cuando = match (true) {
            $d < 0 => 'venció hace '.abs($d).(abs($d) === 1 ? ' día' : ' días'),
            $d === 0 => 'hoy',
            $d === 1 => 'mañana',
            default => "en {$d} días",
        };

        return ['texto' => "{$hito['etapa']} {$cuando}", 'urgente' => $d < self::DIAS_URGENTE, 'titulo' => $hito['etapa'].': '.$hito['fecha']->format('d/m/Y')];
    }

    /** @return array<string, mixed> lo que dibuja una tarjeta */
    private static function tarjeta(Oportunidad $o, string $entidad, ?LineaSolucion $linea): array
    {
        $portada = app(PortadaOportunidades::class);
        $codigo = $o->estado->codigo;
        $etapa = EtapaAbierta::tryFrom($codigo);

        return [
            'id' => $o->id,
            'url' => VerOportunidad::getUrl(['record' => $o->id]),
            'numero' => $o->numero,
            'nombre' => $o->nombre,
            'entidad' => $entidad,
            'monto' => OportunidadResource::monto($o),
            'ponderado' => OportunidadResource::ponderado($o),
            'estado' => $o->estado->nombre,
            'codigo_estado' => $codigo,
            'probabilidad' => match (true) {
                $etapa !== null => $portada->probabilidad($etapa),
                $codigo === 'ganada' => 100,
                default => 0,
            },
            'linea' => $linea->nombre ?? '—',
            'foto' => FotoOportunidadService::url($o),
            'dibujo' => DibujoDeLinea::svg($o->id, $linea->codigo ?? '', $o->linea_solucion_id),
            'desde' => $o->en_etapa_desde->format('d/m/Y'),
            'dias' => $etapa === null ? null : $portada->diasEnEtapa($o),
            'maximo' => $etapa === null ? null : $portada->diasMaximos($etapa),
            'atascada' => $etapa !== null && $portada->pasaElMaximo($o, $codigo),
            'cierre' => ($o->fecha_cierre_real ?? $o->fecha_cierre_estimada)->format('d/m/Y'),
            'ejecutivo' => $o->ejecutivo->name,
        ];
    }
}
