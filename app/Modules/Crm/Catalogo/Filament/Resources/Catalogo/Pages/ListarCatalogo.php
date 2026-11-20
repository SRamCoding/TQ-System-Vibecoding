<?php

declare(strict_types=1);

namespace App\Modules\Crm\Catalogo\Filament\Resources\Catalogo\Pages;

use App\Modules\Crm\Catalogo\Filament\Resources\Catalogo\CatalogoResource;
use App\Modules\Crm\Catalogo\Filament\Resources\Catalogo\FormularioProducto;
use App\Modules\Crm\Catalogo\Services\PreciosCatalogoService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use RuntimeException;

/** MVP rápido (t1-catalogo): la lista, con «Nuevo ítem» y «Carga masiva de precios». */
final class ListarCatalogo extends ListRecords
{
    protected static string $resource = CatalogoResource::class;

    /** MV-029: el título dice lo mismo que su caja. */
    protected static ?string $title = 'Catálogo de productos';

    protected function getHeaderActions(): array
    {
        $gestiona = fn (): bool => CatalogoResource::puede(PreciosCatalogoService::PERMISO_GESTIONAR);

        return [
            Action::make('carga')->label('Carga masiva de precios')->icon('heroicon-o-arrow-up-tray')->color('gray')->visible($gestiona)->slideOver()
                ->fillForm(fn (): array => ['desde' => Calendario::hoy()])
                ->schema([
                    DatePicker::make('desde')->label('Vigentes desde')->required()->format('Y-m-d'),
                    Textarea::make('filas')->label('Filas')->required()->rows(12)->placeholder("CAM-D4M\t1180.00\t850.00\t28\nCAM-B8M\t1640.00\t1210.00\t26"),
                ])
                ->action(function (array $data): void {
                    $r = app(PreciosCatalogoService::class)->cargarPrecios($data['filas'], CarbonImmutable::parse($data['desde']));
                    $n = Notification::make()->title("{$r['aplicados']} precios cargados.");
                    ($r['errores'] === [] ? $n->success() : $n->warning()->body(implode("\n", array_slice($r['errores'], 0, 8)).(count($r['errores']) > 8 ? "\n…" : '')))->persistent()->send();
                }),
            Action::make('nuevo')->label('Nuevo ítem')->icon('heroicon-o-plus')->visible($gestiona)->slideOver()
                ->schema(FormularioProducto::campos(true))
                ->action(function (array $data, Action $action): void {
                    try {
                        $p = app(PreciosCatalogoService::class)->crear(FormularioProducto::datos($data), filled($data['precio'] ?? null) ? (string) $data['precio'] : null,
                            filled($data['costo'] ?? null) ? (string) $data['costo'] : null, filled($data['margen'] ?? null) ? (string) $data['margen'] : null);
                    } catch (RuntimeException $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();
                        $action->halt();

                        return;
                    }

                    Notification::make()->success()->title("Creado {$p->codigo}.")->send();
                    $this->redirect(VerProducto::getUrl(['record' => $p]));
                }),
        ];
    }
}
