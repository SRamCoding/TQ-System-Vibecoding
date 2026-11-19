<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Filament;

use App\Modules\Erp\Presupuestos\Services\ComparacionOfertado;
use App\Modules\Erp\Proyectos\Pestanas\ComponentePestanaProyecto;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;

/**
 * MVP rápido (t1-puente-erp, MV-008): la pestaña «Ofertado vs. presupuesto» de la ficha del proyecto. Partida por
 * partida, lo ofertado (copia v1 de la cotización) contra el presupuesto vigente, y arriba el costo y el margen de cada
 * uno sobre lo vendido. Solo lectura; los montos con `campos.ver-costos`.
 */
final class OfertadoVsPresupuesto extends ComponentePestanaProyecto implements HasActions, HasSchemas, HasTable
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
        $c = app(ComparacionOfertado::class)->de($this->proyectoId);
        $dinero = fn (string $state): string => Montos::siVisible($state);
        $signo = fn (string $state): string => (bccomp($state, '0', 2) > 0 ? '+' : '').Montos::siVisible($state);
        $color = fn (string $state): ?string => match (bccomp($state, '0', 2)) {
            1 => 'danger',
            -1 => 'success',
            default => null,
        };

        return $table
            ->records(fn (): array => array_map(fn (array $f): array => [...$f, '__key' => $f['codigo']], $c['filas'] ?? []))
            ->paginated(false)
            ->heading($c === null ? 'Sin comparación' : "Ofertado {$c['ofertado']} contra vigente {$c['vigente']}")
            ->description($c === null ? null : self::resumen($c))
            ->columns([
                TextColumn::make('codigo')->label('Partida')->fontFamily('mono'),
                TextColumn::make('descripcion')->label('Descripción')->wrap(),
                TextColumn::make('ofertado')->label('Ofertado')->alignEnd()->fontFamily('mono')->formatStateUsing($dinero),
                TextColumn::make('vigente')->label('Presupuesto vigente')->alignEnd()->fontFamily('mono')->formatStateUsing($dinero),
                TextColumn::make('diferencia')->label('Diferencia')->alignEnd()->fontFamily('mono')->formatStateUsing($signo)->color($color),
            ])
            ->emptyStateHeading('Este proyecto no nació de una cotización')
            ->emptyStateDescription('La comparación aparece cuando el proyecto nace al ganar una oportunidad con su cotización aceptada.');
    }

    /** «Costo ofertado S/ … · vigente S/ … · Margen ofertado S/ … → vigente S/ …». */
    private static function resumen(array $c): string
    {
        $texto = 'Costo ofertado '.Montos::siVisible($c['costo_ofertado']).' · vigente '.Montos::siVisible($c['costo_vigente']).' · diferencia '.(bccomp($c['diferencia'], '0', 2) > 0 ? '+' : '').Montos::siVisible($c['diferencia']);

        return $c['venta'] === null ? $texto : $texto.' · Vendido '.Montos::siVisible($c['venta']).' · margen ofertado '.Montos::siVisible((string) $c['margen_ofertado']).' → vigente '.Montos::siVisible((string) $c['margen_vigente']);
    }

    public function render(): View
    {
        return view('presupuestos::tabla');
    }
}
