<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Filament;

use App\Modules\Crm\Cotizaciones\CotizacionesServiceProvider;
use App\Modules\Crm\Cotizaciones\Filament\Resources\Cotizaciones\CotizacionResource;
use App\Modules\Crm\Cotizaciones\Filament\Resources\Cotizaciones\Pages\VerCotizacion;
use App\Modules\Crm\Cotizaciones\Models\Cotizacion;
use App\Modules\Crm\Cotizaciones\Services\ComparadorVersiones;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Pestaña «Versiones» de la ficha (D19; `interfaz.md`, la versión más reciente primero): todas las del mismo número, cada
 * una con su estado; «Comparar» abre el comparador contra la versión que se está viendo, en el panel deslizante.
 */
final class VersionesDeCotizacion extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    #[Locked]
    public int $cotizacionId;

    public function mount(): void
    {
        abort_unless(Filament::auth()->user()?->can(CotizacionesServiceProvider::PERMISO_VER) ?? false, 403);
    }

    #[On('cotizacion-cambiada')]
    public function alCambiar(): void
    {
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        $numero = (string) Cotizacion::query()->whereKey($this->cotizacionId)->value('numero');

        return $table
            ->query(fn (): Builder => Cotizacion::query()->with('estado')->where('numero', $numero))
            ->defaultSort('numero_version', 'desc')
            ->paginated(false)
            ->columns([
                TextColumn::make('numero_version')->label('Versión')
                    ->formatStateUsing(fn (Cotizacion $record): string => "versión {$record->numero_version}".($record->id === $this->cotizacionId ? ' (esta)' : '')),
                TextColumn::make('estado_id')->label('Estado')->badge()
                    ->formatStateUsing(fn (Cotizacion $record): string => $record->estado->nombre.($record->es_aceptada ? ' · la aceptada' : ''))
                    ->color(fn (Cotizacion $record): string => $record->estado->colorEnPantalla()),
                TextColumn::make('total')->label('Total')->alignEnd()->fontFamily('mono')
                    ->formatStateUsing(fn (Cotizacion $record): string => CotizacionResource::monto($record->moneda_id, (string) $record->total)),
                TextColumn::make('margen_porcentaje')->label('Margen')->alignEnd()->fontFamily('mono')->placeholder('—')
                    ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : "{$state} %")
                    ->visible(fn (): bool => CotizacionResource::ve(GrupoSensible::Margen)),
                TextColumn::make('motivo_version_detalle')->label('Por qué')->placeholder('—')->wrap(),
            ])
            ->recordActions([
                self::comparar(fn (Cotizacion $record): int => $record->id, fn (): int => $this->cotizacionId)
                    ->hidden(fn (Cotizacion $record): bool => $record->id === $this->cotizacionId),
            ])
            ->recordUrl(fn (Cotizacion $record): string => VerCotizacion::getUrl(['record' => $record]));
    }

    /**
     * «Comparar»: el panel con el comparador entre dos versiones (la ficha también lo usa para «Comparar con la anterior»).
     *
     * @param  \Closure  $una  el id de una versión (Filament le inyecta `$record`)
     * @param  \Closure  $otra  el id de la otra
     */
    public static function comparar(\Closure $una, \Closure $otra): Action
    {
        return Action::make('comparar')->label('Comparar')->icon('heroicon-m-arrows-right-left')->color('gray')
            ->slideOver()->modalSubmitAction(false)->modalCancelActionLabel('Cerrar')
            ->modalHeading('Comparar versiones')
            ->modalContent(function (Action $action) use ($una, $otra): View {
                $comparacion = app(ComparadorVersiones::class)->comparar((int) $action->evaluate($una), (int) $action->evaluate($otra));

                return view('cotizaciones::comparar', [
                    'c' => $comparacion,
                    'moneda' => CotizacionResource::moneda((int) Cotizacion::query()->whereKey($comparacion['despues']['id'])->value('moneda_id')),
                    'veMargen' => CotizacionResource::ve(GrupoSensible::Margen),
                    'veCosto' => CotizacionResource::ve(GrupoSensible::Costos),
                ]);
            });
    }

    public function render(): View
    {
        return view('cotizaciones::tabla');
    }
}
