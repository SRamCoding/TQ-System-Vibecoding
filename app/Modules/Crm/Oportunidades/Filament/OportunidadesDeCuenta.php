<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Filament;

use App\Modules\Crm\Cuentas\Pestanas\ComponentePestanaCuenta;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\OportunidadResource;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\VerOportunidad;
use App\Modules\Crm\Oportunidades\Models\Oportunidad;
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
 * Pestaña «Oportunidades» de la ficha de la cuenta (plan 2.21, D13 y D11.9): todas las de esa cuenta, las abiertas
 * primero; cada una abre su ficha. «Nueva oportunidad» es el botón de la cabecera de la cuenta.
 */
final class OportunidadesDeCuenta extends ComponentePestanaCuenta implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    #[On('cuenta-cambiada')]
    public function alCambiarLaCuenta(): void
    {
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Oportunidad::query()->with(['estado', 'moneda', 'ejecutivo:id,name'])
                ->where('cuenta_id', $this->cuentaId)
                ->orderByRaw('fecha_cierre_real IS NOT NULL')->orderBy('fecha_cierre_estimada')->orderByDesc('id'))
            ->paginated([10, 25, 50])
            ->columns([
                TextColumn::make('numero')->label('Oportunidad')->fontFamily('mono')->wrap()
                    ->description(fn (Oportunidad $record): string => $record->nombre),
                TextColumn::make('monto_estimado')->label('Monto')->alignEnd()
                    ->formatStateUsing(fn (Oportunidad $record): string => OportunidadResource::monto($record)),
                TextColumn::make('estado_id')->label('Etapa')->badge()
                    ->formatStateUsing(fn (Oportunidad $record): string => OportunidadResource::etapa($record))
                    ->color(fn (Oportunidad $record): string => $record->estado->colorEnPantalla()),
                TextColumn::make('fecha_cierre_estimada')->label('Cierre estimado')->date('d/m/Y'),
                TextColumn::make('ejecutivo.name')->label('Ejecutivo'),
            ])
            ->recordUrl(fn (Oportunidad $record): string => VerOportunidad::getUrl(['record' => $record]))
            ->emptyStateHeading('Sin oportunidades')
            ->emptyStateDescription('Créala con «Nueva oportunidad», arriba.');
    }

    public function render(): View
    {
        return view('oportunidades::tabla');
    }
}
