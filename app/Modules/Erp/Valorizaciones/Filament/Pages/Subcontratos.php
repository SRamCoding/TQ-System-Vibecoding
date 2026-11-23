<?php

declare(strict_types=1);

namespace App\Modules\Erp\Valorizaciones\Filament\Pages;

use App\Modules\Erp\Compras\Filament\Compras;
use App\Modules\Erp\Valorizaciones\Services\SubcontratosService;
use App\Modules\Erp\Valorizaciones\Services\ValorizacionesService;
use App\Modules\Nucleo\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;

/**
 * MVP rápido (t4-valorizaciones; observaciones 6, punto 12): una tarjeta por subcontrato con su dinero, la bandeja «Por
 * aprobar», la alarma «le pagas más avance del que cobras», cinco cifras, filtros en chips y orden por urgencia. Un
 * clic abre el panel con el resumen; doble clic, la ficha. Todo sale de `SubcontratosService::estados()`.
 */
final class Subcontratos extends Page
{
    use AccionesDeSubcontrato;

    public const array FILTROS = ['por_aprobar' => 'Por aprobar', 'por_pagar' => 'Por pagar', 'con_aviso' => 'Con aviso', 'terminados' => 'Terminados'];

    protected static ?string $slug = 'subcontratos';

    protected static ?string $title = 'Subcontratos';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected string $view = 'valorizaciones::subcontratos.pantalla';

    #[Url]
    public ?string $filtro = null;

    #[Url]
    public string $buscar = '';

    #[Url]
    public ?int $proyecto = null;

    public static function canAccess(): bool
    {
        $u = Filament::auth()->user();

        return $u instanceof User && $u->can(ValorizacionesService::PERMISO_VER);
    }

    public function poner(string $filtro): void
    {
        $this->filtro = $this->filtro === $filtro || ! array_key_exists($filtro, self::FILTROS) ? null : $filtro;
    }

    public function limpiar(): void
    {
        $this->filtro = null;
        $this->buscar = '';
        $this->proyecto = null;
    }

    /** @return array{todos: list<array<string, mixed>>, tarjetas: list<array<string, mixed>>, cifras: array<string, mixed>, bandeja: list<array<string, mixed>>, proyectos: array<int, string>} */
    public function datos(): array
    {
        $servicio = app(SubcontratosService::class);
        $todos = $servicio->estados();
        $texto = Str::lower(Str::ascii(trim($this->buscar)));
        $tarjetas = array_values(array_filter($todos, fn (array $e): bool => ($this->proyecto === null || $e['proyecto_id'] === $this->proyecto)
            && ($texto === '' || str_contains(Str::lower(Str::ascii("{$e['subcontratista']} {$e['objeto']} {$e['codigo']}")), $texto))
            && match ($this->filtro) {
                'por_aprobar' => $e['pendientes'] !== [],
                'por_pagar' => bccomp($e['por_pagar'], '0', 2) > 0,
                'con_aviso' => $e['alarma'],
                'terminados' => $e['terminado'],
                default => true,
            }));
        $bandeja = [];

        foreach ($todos as $e) {
            foreach ($e['pendientes'] as $v) {
                $bandeja[] = [...$v, 'sc' => $e];
            }
        }

        usort($bandeja, fn (array $a, array $b): int => $b['dias'] <=> $a['dias']);

        return [
            'todos' => $todos, 'tarjetas' => $tarjetas, 'cifras' => $servicio->cifras($todos), 'bandeja' => $bandeja,
            'proyectos' => DB::table('proyectos')->whereIn('id', array_unique(array_column($todos, 'proyecto_id')))->orderBy('codigo')->selectRaw("id, codigo || ' · ' || nombre AS t")->pluck('t', 'id')->all(),
        ];
    }

    public static function urlFicha(int $id): string
    {
        return FichaSubcontrato::getUrl(['id' => $id]);
    }

    /** Un clic en la tarjeta: el panel deslizante con el resumen y sus botones. */
    public function resumenAction(): Action
    {
        return Action::make('resumen')->slideOver()->modalWidth('lg')
            ->modalHeading(fn (array $arguments): string => (string) (app(SubcontratosService::class)->estado((int) ($arguments['id'] ?? 0))['codigo'] ?? 'Subcontrato'))
            ->modalContent(fn (array $arguments) => view('valorizaciones::subcontratos.panel', ['e' => app(SubcontratosService::class)->estado((int) ($arguments['id'] ?? 0))]))
            ->modalSubmitAction(false)->modalCancelActionLabel('Cerrar');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('nuevo')->label('Nuevo subcontrato')->icon('heroicon-o-plus')->color('gray')->visible(fn (): bool => self::puedeGestionar())->slideOver()
                ->schema([
                    Select::make('proyecto')->label('Proyecto')->required()->live()->options(fn (): array => DB::table('proyectos as p')->join('estados as e', 'e.id', '=', 'p.estado_id')->where('e.codigo', 'en_ejecucion')->selectRaw("p.id, p.codigo || ' · ' || p.nombre AS t")->pluck('t', 'p.id')->all()),
                    TextInput::make('subcontratista')->label('Subcontratista')->required(),
                    TextInput::make('objeto')->label('Objeto')->required(),
                    TextInput::make('adelanto')->label('Adelanto (%)')->numeric()->default(20),
                    TextInput::make('retencion')->label('Retención de garantía (%)')->numeric()->default(5),
                    Repeater::make('alcance')->label('Alcance por partida')->columns(2)->schema([
                        Select::make('partida')->label('Partida')->required()->options(fn (Get $get): array => Compras::partidasDe(is_numeric($get('../../proyecto')) ? (int) $get('../../proyecto') : null)),
                        TextInput::make('monto')->label('Monto')->numeric()->required(),
                    ]),
                ])
                ->action(fn (array $data) => app(ValorizacionesService::class)->crearSubcontrato((int) $data['proyecto'], $data['subcontratista'], $data['objeto'], (string) $data['adelanto'], (string) $data['retencion'],
                    collect($data['alcance'])->mapWithKeys(fn (array $a): array => [(int) $a['partida'] => (string) $a['monto']])->all())),
            $this->valorizarAction(),
        ];
    }
}
