<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Filament;

use App\Modules\Erp\Presupuestos\Filament\Resources\Presupuestos\Pages\ListarPresupuestos;
use App\Modules\Erp\Presupuestos\Filament\Resources\Presupuestos\Pages\VerPresupuesto;
use App\Modules\Erp\Presupuestos\Models\Presupuesto;
use App\Modules\Erp\Proyectos\Pestanas\ComponentePestanaProyecto;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

/**
 * La pestaña «Presupuesto» de la ficha del proyecto (plan 2.10, paso 8, D3): arriba, el presupuesto vigente con su
 * costo directo y su total; debajo, todas sus versiones (la vigente, las reemplazadas y la nueva en curso), cada una
 * con enlace a su ficha. Solo lectura: el presupuesto se arma y se trae al proyecto desde Presupuestos.
 */
final class PresupuestoDeProyecto extends ComponentePestanaProyecto implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    #[On('proyecto-cambiado')]
    public function alCambiar(): void
    {
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        $vigente = Presupuesto::query()->where('proyecto_id', $this->proyectoId)->where('es_vigente', true)->first();

        return $table
            ->query(fn (): Builder => Presupuesto::query()->with('estado')->where('proyecto_id', $this->proyectoId))
            ->defaultSort(fn (Builder $query): Builder => $query->orderByDesc('numero_version'))
            ->paginated(false)
            ->heading($vigente === null ? 'Sin presupuesto vigente' : "Vigente: {$vigente->numero} v{$vigente->numero_version} · {$vigente->nombre}")
            ->description($vigente === null
                ? 'El presupuesto se arma en Presupuestos y se trae al proyecto desde su ficha.'
                : 'Costo directo '.Montos::siVisible($vigente->costo_directo).' · Total '.Montos::siVisible($vigente->total))
            ->columns([
                TextColumn::make('numero')->label('Número')->fontFamily('mono')
                    ->description(fn (Presupuesto $record): string => "Versión {$record->numero_version}"),
                TextColumn::make('nombre')->label('Nombre')->wrap(),
                TextColumn::make('estado_id')->label('Estado')->badge()
                    ->formatStateUsing(fn (Presupuesto $record): string => $record->estado->nombre.($record->es_vigente ? ' · vigente' : ''))
                    ->color(fn (Presupuesto $record): string => $record->estado->colorEnPantalla()),
                TextColumn::make('costo_directo')->label('Costo directo')->alignEnd()->fontFamily('mono')
                    ->formatStateUsing(fn (string $state): string => Montos::siVisible($state)),
                TextColumn::make('total')->label('Total')->alignEnd()->fontFamily('mono')
                    ->formatStateUsing(fn (string $state): string => Montos::siVisible($state)),
            ])
            ->recordUrl(fn (Presupuesto $record): string => VerPresupuesto::getUrl(['record' => $record]))
            ->emptyStateHeading('Este proyecto aún no tiene presupuesto')
            ->emptyStateDescription('Ábrelo en Presupuestos y usa «Traer al proyecto».')
            ->emptyStateActions([
                Action::make('irAPresupuestos')->label('Ir a Presupuestos')->color('gray')
                    ->url(ListarPresupuestos::getUrl()),
            ]);
    }

    public function render(): View
    {
        return view('presupuestos::tabla');
    }
}
