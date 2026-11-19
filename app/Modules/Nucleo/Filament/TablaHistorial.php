<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament;

use App\Modules\Nucleo\Historial\DescribibleEnBitacora;
use App\Modules\Nucleo\Historial\FilaHistorial;
use App\Modules\Nucleo\Historial\HistorialLegible;
use App\Modules\Nucleo\Models\User;
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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * La tabla «Historial» de cualquier ficha (v4 parte 7; plan 1.3, D10; plan 1.3b): una tabla de Filament con las
 * filas que arma HistorialLegible, que ya quitó lo que la persona no puede ver. Es una tabla de Filament, no HTML
 * propio, para que se vea y se comporte como las demás (paginación, modo oscuro) sin clases que el tema no trae
 * (docs/convenciones/interfaz.md, «Vistas propias»). Cada ficha la extiende y dice de qué registros leerla.
 */
abstract class TablaHistorial extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    /** Fija: el navegador no puede cambiarla para leer el historial de otro registro. */
    #[Locked]
    public int $registroId;

    /**
     * La ficha y sus partes cuyo historial se muestra, ya filtradas por lo que la persona puede ver.
     *
     * @return list<Model&DescribibleEnBitacora>
     */
    abstract protected function modelos(): array;

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (int $page, int $recordsPerPage): LengthAwarePaginator => $this->pagina($page, $recordsPerPage))
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->columns([
                TextColumn::make('cuando')->label('Cuándo')->wrap(false),
                TextColumn::make('quien')->label('Quién'),
                TextColumn::make('que')->label('Qué')->wrap(),
                TextColumn::make('campo')->label('Campo')->placeholder(''),
                TextColumn::make('antes')->label('Antes')->color('gray')->wrap()->placeholder(''),
                TextColumn::make('despues')->label('Después')->wrap()->placeholder(''),
            ])
            ->emptyStateHeading('Todavía no hay cambios registrados.')
            ->emptyStateIcon('heroicon-o-clock');
    }

    public function render(): View
    {
        return view('nucleo::filament.tabla-historial');
    }

    /**
     * @return LengthAwarePaginator<string, array<string, string>>
     */
    private function pagina(int $pagina, int $porPagina): LengthAwarePaginator
    {
        $usuario = Filament::auth()->user();
        $filas = $usuario instanceof User ? app(HistorialLegible::class)->filas($usuario, $this->modelos()) : [];
        $registros = [];

        foreach (array_slice($filas, ($pagina - 1) * $porPagina, $porPagina, true) as $i => $fila) {
            $registros["f{$i}"] = self::registro($fila);
        }

        return new LengthAwarePaginator($registros, count($filas), $porPagina, $pagina);
    }

    /**
     * Una fila de la bitácora como la lee la tabla. Si se lee en una frase, va en «Qué» y el detalle queda vacío.
     *
     * @return array<string, string>
     */
    private static function registro(FilaHistorial $fila): array
    {
        return [
            'cuando' => $fila->fecha->timezone(config('app.timezone'))->format('d/m/Y H:i'),
            'quien' => $fila->autor,
            'que' => $fila->resumen ?? "{$fila->evento} · {$fila->sujeto}",
            'campo' => $fila->resumen === null ? $fila->campo : '',
            'antes' => $fila->resumen === null ? $fila->anterior : '',
            'despues' => $fila->resumen === null ? $fila->nuevo : '',
        ];
    }
}
