<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Filament;

use App\Modules\Erp\Presupuestos\Models\Insumo;
use App\Modules\Erp\Presupuestos\PresupuestosServiceProvider;
use App\Modules\Erp\Presupuestos\Services\AjusteDePartida;
use App\Modules\Erp\Presupuestos\Services\ConsultaPresupuestos;
use App\Modules\Erp\Presupuestos\Services\InsumoEnLista;
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
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * La pestaña «Lista de insumos» de un presupuesto con APU (plan 2.10, paso 7; plan 2.9, D5 y D10): los insumos por tipo
 * (mano de obra, materiales, equipos, subcontratos) con la cantidad de todo el presupuesto y su monto; aparte, lo que
 * va «Precio sin detallar» y el «Ajuste por redondeo» con las partidas que lo generan. Suma el costo directo.
 */
final class ListaDeInsumosDePresupuesto extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    /** Los títulos de cada tipo, como en S10. */
    private const array GRUPOS = [
        Insumo::MANO_OBRA => 'MANO DE OBRA',
        Insumo::MATERIAL => 'MATERIALES',
        Insumo::EQUIPO => 'EQUIPOS',
        Insumo::SUBCONTRATO => 'SUBCONTRATOS',
    ];

    #[Locked]
    public int $presupuestoId;

    public function mount(): void
    {
        abort_unless(Filament::auth()->user()?->can(PresupuestosServiceProvider::PERMISO_VER) ?? false, 403);
    }

    #[On('presupuesto-cambiado')]
    public function alCambiar(): void
    {
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        $resaltada = fn (array $record): ?string => $record['clase'] === 'insumo' ? null : 'bold';

        return $table
            ->records(fn (): array => $this->filas())
            ->paginated(false)
            ->columns([
                TextColumn::make('codigo')->label('Código')->fontFamily('mono')->placeholder('')
                    ->weight($resaltada),
                TextColumn::make('nombre')->label('Insumo')->wrap()->weight($resaltada)
                    ->description(fn (array $record): ?string => $record['detalle']),
                TextColumn::make('unidad')->label('Unidad')->placeholder(''),
                TextColumn::make('cantidad')->label('Cantidad')->alignEnd()->fontFamily('mono')->placeholder(''),
                TextColumn::make('monto')->label('Monto')->alignEnd()->fontFamily('mono')->placeholder('')->weight($resaltada)
                    ->formatStateUsing(fn (?string $state): string => $state === null ? '' : Montos::siVisible($state)),
            ])
            ->emptyStateHeading('Sin insumos')
            ->emptyStateDescription('Los insumos salen de los análisis de precios de las partidas.');
    }

    public function render(): View
    {
        return view('presupuestos::tabla');
    }

    /**
     * Un título por tipo con sus insumos; después lo sin detallar, el ajuste (si lo hay) y el total.
     *
     * @return array<string, array{clase: string, codigo: ?string, nombre: string, detalle: ?string, unidad: ?string, cantidad: ?string, monto: ?string}>
     */
    private function filas(): array
    {
        $lista = app(ConsultaPresupuestos::class)->listaDeInsumos($this->presupuestoId);

        if ($lista->insumos === [] && bccomp($lista->sinDetallar, '0', 2) === 0) {
            return [];
        }

        $filas = [];
        $fila = fn (string $clase, string $nombre, ?string $monto, ?string $codigo = null, ?string $detalle = null, ?string $unidad = null, ?string $cantidad = null): array => compact('clase', 'codigo', 'nombre', 'detalle', 'unidad', 'cantidad', 'monto');

        foreach (collect($lista->insumos)->groupBy(fn (InsumoEnLista $i): string => $i->tipo) as $tipo => $insumos) {
            $suma = $insumos->reduce(fn (string $s, InsumoEnLista $i): string => bcadd($s, $i->monto, 2), '0.00');
            $filas["tipo-{$tipo}"] = $fila('tipo', self::GRUPOS[$tipo] ?? mb_strtoupper($tipo), $suma);

            foreach ($insumos as $insumo) {
                $filas["insumo-{$insumo->insumoId}"] = $fila('insumo', $insumo->nombre, $insumo->monto, $insumo->codigo, null, $insumo->unidad, $insumo->cantidad);
            }
        }

        if (bccomp($lista->sinDetallar, '0', 2) !== 0) {
            $filas['sin-detallar'] = $fila('aparte', 'Precio sin detallar', $lista->sinDetallar, detalle: 'Partidas o parte de ellas con solo su monto, sin insumos (D10 de la 2.9).');
        }

        if ($lista->ajustes !== []) {
            $filas['ajuste'] = $fila('aparte', 'Ajuste por redondeo', $lista->ajuste, detalle: implode('; ', array_map(
                fn (AjusteDePartida $a): string => "{$a->codigo} {$a->descripcion}, {$a->metrado} {$a->unidad}, ".Montos::siVisible($a->monto),
                $lista->ajustes,
            )));
        }

        $filas['total'] = $fila('total', 'Costo directo', $lista->total);

        return $filas;
    }
}
