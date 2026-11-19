<?php

declare(strict_types=1);

namespace App\Modules\Erp\Proyectos\Filament\Resources\Proyectos\Pages;

use App\Modules\Erp\Proyectos\Estados\DefinicionProyecto;
use App\Modules\Erp\Proyectos\Filament\Portada\DibujoProyecto;
use App\Modules\Erp\Proyectos\Filament\Resources\Proyectos\ProyectoResource;
use App\Modules\Erp\Proyectos\Filament\SelectoresProyecto;
use App\Modules\Erp\Proyectos\Models\Proyecto;
use App\Modules\Erp\Proyectos\Resumen\SemaforoDeCosto;
use App\Modules\Erp\Proyectos\Services\FiltrosPortada;
use App\Modules\Erp\Proyectos\Services\FotoProyectoService;
use App\Modules\Erp\Proyectos\Services\PortadaProyectos;
use App\Modules\Erp\Proyectos\Services\ResumenProyecto;
use App\Modules\Erp\Proyectos\Tablero\TarjetaPlazo;
use App\Modules\Nucleo\Filament\AccionFilament;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Pagination\Paginator;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * La portada de Proyectos (plan 2.61, D4; prototipo aprobado `docs/diseno/fuentes/prototipos/proyectos-portada-ficha.html`):
 * tarjetas con la foto (o el dibujo genérico, D12), estado, código, nombre, cliente, la barra del plazo, el semáforo de
 * costo para quien ve costos, el jefe y los días que faltan. A la izquierda, los filtros con la cantidad de cada opción
 * (Salud, Estado, Jefe de proyecto y «Solo los míos»); arriba, el buscador y el orden. Todo se filtra en la base
 * (`PortadaProyectos`). Reemplaza la portada de pestañas de la 2.10; `?aviso=jefe-inactivo` sigue abriendo ese filtro.
 */
final class ListarProyectos extends Page
{
    use WithPagination;

    protected static string $resource = ProyectoResource::class;

    protected static ?string $title = 'Proyectos';

    protected string $view = 'proyectos::portada.index';

    public const int POR_PAGINA = 24;

    #[Url(as: 'q', except: '')]
    public string $buscar = '';

    /** @var list<string> */
    #[Url(as: 'estado', except: [])]
    public array $estados = [];

    /** @var list<int|string> */
    #[Url(as: 'jefe', except: [])]
    public array $jefes = [];

    /** @var list<string> */
    #[Url(as: 'salud', except: [])]
    public array $salud = [];

    #[Url(as: 'mios', except: false)]
    public bool $mios = false;

    #[Url(as: 'orden', except: 'recientes')]
    public string $orden = 'recientes';

    #[Url(as: 'aviso')]
    public ?string $aviso = null;

    public function mount(): void
    {
        // El enlace del aviso de la 2.10 (`?aviso=jefe-inactivo`) abre su opción de «Salud».
        if ($this->aviso !== null && ! in_array($this->aviso, $this->salud, true)) {
            $this->salud[] = $this->aviso;
        }

        $this->aviso = null;
    }

    public function updated(string $propiedad): void
    {
        if (in_array($propiedad, ['buscar', 'orden', 'mios'], true) || str_starts_with($propiedad, 'estados') || str_starts_with($propiedad, 'jefes') || str_starts_with($propiedad, 'salud')) {
            $this->resetPage();
        }
    }

    public function quitarFiltros(): void
    {
        $this->reset('buscar', 'estados', 'jefes', 'salud', 'mios');
        $this->resetPage();
    }

    public function filtros(): FiltrosPortada
    {
        return new FiltrosPortada(
            texto: $this->buscar,
            estados: array_map(strval(...), $this->estados),
            jefes: array_map(intval(...), $this->jefes),
            salud: array_map(strval(...), $this->salud),
            mios: $this->mios,
            orden: array_key_exists($this->orden, FiltrosPortada::ORDENES) ? $this->orden : 'recientes',
        );
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $portada = app(PortadaProyectos::class);
        $usuario = ProyectoResource::usuario();
        $filtros = $this->filtros();
        $saludes = $portada->saludes($usuario);
        /** @var Paginator<int, Proyecto> $pagina */
        $pagina = $portada->ordenar($portada->filtrar($filtros, $usuario, saludes: $saludes)->with(['estado', 'jefe:id,name']), $filtros->orden)->paginate(self::POR_PAGINA);
        $ids = array_map(fn (Proyecto $p): int => $p->id, $pagina->items());
        $veCostos = $usuario?->can(GrupoSensible::Costos->permiso()) ?? false;
        $tonos = $veCostos ? app(SemaforoDeCosto::class)->tonos($ids, $usuario) : [];

        return [
            'pagina' => $pagina,
            'tarjetas' => array_map(fn (Proyecto $p): array => self::tarjeta($p, $tonos[$p->id] ?? null), $pagina->items()),
            'opciones' => $portada->opciones($filtros, $usuario, $saludes),
            'ordenes' => FiltrosPortada::ORDENES,
            'total' => Proyecto::query()->count(),
            'cambios' => array_map(fn (array $c): array => [
                ...$c,
                'url' => $c['proyecto_id'] === null ? null : VerProyecto::getUrl(['record' => $c['proyecto_id']]),
            ], $portada->ultimosCambios()),
            'conFiltros' => $this->buscar !== '' || $this->estados !== [] || $this->jefes !== [] || $this->salud !== [] || $this->mios,
        ];
    }

    /** @return array<string, mixed> lo que dibuja una tarjeta */
    private static function tarjeta(Proyecto $proyecto, ?string $semaforo): array
    {
        $abierto = in_array($proyecto->estado->codigo, DefinicionProyecto::ACEPTAN_COSTOS, true);
        $plazo = TarjetaPlazo::plazo($proyecto);

        return [
            'id' => $proyecto->id,
            'url' => VerProyecto::getUrl(['record' => $proyecto->id]),
            'codigo' => $proyecto->codigo,
            'nombre' => $proyecto->nombre,
            'cliente' => ProyectoResource::cliente($proyecto),
            'estado' => $proyecto->estado->nombre,
            'color_estado' => $proyecto->estado->colorEnPantalla(),
            'foto' => FotoProyectoService::url($proyecto),
            'dibujo' => DibujoProyecto::svg($proyecto->id),
            'jefe' => $proyecto->jefe->name,
            'plazo' => $abierto ? $plazo['porcentaje'] : null,
            'dias' => match (true) {
                ! $abierto => 'Fin previsto el '.$proyecto->fecha_fin_prevista->format('d/m/Y'),
                $plazo['dias'] < 0 => (-$plazo['dias']).' '.(-$plazo['dias'] === 1 ? 'día' : 'días').' vencido',
                default => 'Faltan '.$plazo['dias'].' '.($plazo['dias'] === 1 ? 'día' : 'días'),
            },
            'tono_dias' => $abierto ? $plazo['tono'] : null,
            'semaforo' => $semaforo,
        ];
    }

    /**
     * @return list<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            AccionFilament::desde('erp.proyectos.crear-interno', alTerminar: function (mixed $resumen): void {
                Notification::make()->success()->title('Proyecto creado.')->send();

                if ($resumen instanceof ResumenProyecto) {
                    $this->redirect(VerProyecto::getUrl(['record' => $resumen->id]), navigate: true);
                }
            }, controles: ['jefe_proyecto_user_id' => fn (): Field => SelectoresProyecto::jefe()])
                ->name('nuevo')->label('Nuevo proyecto interno')->icon('heroicon-o-plus')->slideOver()
                ->modalDescription('Sin contrato y con Techquk como cliente. Los proyectos de un contrato nacen del contrato.'),
        ];
    }
}
