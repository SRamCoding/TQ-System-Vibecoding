<?php

declare(strict_types=1);

namespace App\Modules\Crm\Catalogo\Filament\Resources\Catalogo\Pages;

use App\Modules\Crm\Catalogo\Filament\Resources\Catalogo\CatalogoResource;
use App\Modules\Crm\Catalogo\Filament\Resources\Catalogo\FormularioProducto;
use App\Modules\Crm\Catalogo\Models\Producto;
use App\Modules\Crm\Catalogo\Services\PreciosCatalogoService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use RuntimeException;

/** MVP rápido (t1-catalogo): la ficha del ítem, con «Nuevo precio», «Editar» y «Descontinuar». */
final class VerProducto extends ViewRecord
{
    protected static string $resource = CatalogoResource::class;

    public function getTitle(): string
    {
        return "{$this->producto()->codigo} · ".($this->producto()->descripcion_corta ?? $this->producto()->descripcion);
    }

    private function producto(): Producto
    {
        /** @var Producto $p */
        $p = $this->record;

        return $p;
    }

    protected function getHeaderActions(): array
    {
        $gestiona = fn (): bool => CatalogoResource::puede(PreciosCatalogoService::PERMISO_GESTIONAR);

        return [
            Action::make('precio')->label('Nuevo precio')->icon('heroicon-o-currency-dollar')->visible($gestiona)
                ->modalDescription('El precio vigente se cierra el día anterior y queda en la historia.')
                ->fillForm(fn (): array => ['desde' => Calendario::hoy(), 'precio' => $this->producto()->precioVigente?->precio, 'costo' => $this->producto()->precioVigente?->costo_referencial, 'margen' => $this->producto()->precioVigente?->margen_objetivo])
                ->schema([
                    TextInput::make('precio')->label('Precio sin IGV')->numeric()->required(),
                    TextInput::make('costo')->label('Costo referencial')->numeric(),
                    TextInput::make('margen')->label('Margen objetivo (%)')->numeric(),
                    DatePicker::make('desde')->label('Vigente desde')->required()->format('Y-m-d'),
                ])
                ->action(fn (array $data) => $this->hacer(fn () => app(PreciosCatalogoService::class)->cambiarPrecio($this->producto()->id, (string) $data['precio'],
                    filled($data['costo'] ?? null) ? (string) $data['costo'] : null, filled($data['margen'] ?? null) ? (string) $data['margen'] : null, CarbonImmutable::parse($data['desde'])), 'Precio nuevo vigente.')),
            Action::make('editar')->label('Editar')->icon('heroicon-o-pencil-square')->color('gray')->visible($gestiona)->slideOver()
                ->fillForm(fn (): array => $this->producto()->only(['codigo', 'descripcion', 'descripcion_corta', 'familia_producto_id', 'unidad_medida_id', 'linea_solucion_id', 'marca', 'modelo', 'cubso_id', 'es_servicio', 'requiere_serie']))
                ->schema(FormularioProducto::campos(false))
                ->action(fn (array $data) => $this->hacer(fn () => $this->producto()->update(FormularioProducto::datos($data)), 'Guardado.')),
            Action::make('descontinuar')->label(fn (): string => $this->producto()->es_activo ? 'Descontinuar' : 'Reactivar')->icon('heroicon-o-archive-box')->color('gray')->visible($gestiona)
                ->requiresConfirmation()->modalDescription('Un ítem descontinuado ya no se ofrece al cotizar ni al comprar; lo ya hecho con él no cambia.')
                ->action(fn () => $this->hacer(fn () => app(PreciosCatalogoService::class)->descontinuar($this->producto()->id, $this->producto()->es_activo), 'Hecho.')),
        ];
    }

    private function hacer(\Closure $paso, string $exito): void
    {
        try {
            $paso();
        } catch (RuntimeException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            return;
        }

        Notification::make()->success()->title($exito)->send();
        $this->redirect(self::getUrl(['record' => $this->record]));
    }
}
