<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades;

use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Crm\Oportunidades\OportunidadesServiceProvider;
use App\Modules\Nucleo\Estados\FilaHistorialEstados;
use App\Modules\Nucleo\Services\EstadosService;
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
 * «Historial de etapas» de la oportunidad (plan 2.21, D11.5): cada cambio de etapa del núcleo (`historialDe`), con su
 * motivo, quién y los días que pasó en la etapa de la que salió. Una tabla de Filament con filas que no son modelos,
 * como TablaHistorial.
 */
final class EtapasDeOportunidad extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    /** Fija: el navegador no puede cambiarla para leer otra oportunidad. */
    #[Locked]
    public int $oportunidadId;

    public function mount(): void
    {
        abort_unless(Filament::auth()->user()?->can(OportunidadesServiceProvider::PERMISO_VER) ?? false, 403);
    }

    #[On('oportunidad-cambiada')]
    public function alCambiarLaOportunidad(): void
    {
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => $this->filas())
            ->paginated(false)
            ->columns([
                TextColumn::make('cuando')->label('Cuándo'),
                TextColumn::make('cambio')->label('Cambio')->wrap(),
                TextColumn::make('dias')->label('Días en la etapa anterior')->alignEnd()->placeholder('—'),
                TextColumn::make('motivo')->label('Motivo')->wrap()->placeholder('—'),
                TextColumn::make('comentario')->label('Comentario')->wrap()->placeholder('—'),
                TextColumn::make('quien')->label('Quién'),
            ])
            ->emptyStateHeading('Todavía no hay cambios de etapa.');
    }

    public function render(): View
    {
        return view('oportunidades::tabla');
    }

    /**
     * Del más reciente al primero.
     *
     * @return array<string, array<string, string|null>>
     */
    private function filas(): array
    {
        $historial = app(EstadosService::class)->historialDe(Oportunidad::query()->findOrFail($this->oportunidadId));
        $filas = [];

        foreach ($historial as $i => $fila) {
            $anterior = $historial[$i - 1] ?? null;
            $filas["e{$i}"] = self::fila($fila, $anterior);
        }

        return array_reverse($filas, true);
    }

    /**
     * @return array<string, string|null>
     */
    private static function fila(FilaHistorialEstados $fila, ?FilaHistorialEstados $anterior): array
    {
        $zona = (string) config('app.timezone');

        return [
            'cuando' => $fila->fecha->timezone($zona)->format('d/m/Y H:i'),
            'cambio' => $fila->desde === null ? "Creada en «{$fila->hacia}»" : "{$fila->transicion}: de «{$fila->desde}» a «{$fila->hacia}»",
            'dias' => $anterior === null ? null : (string) (int) $anterior->fecha->timezone($zona)->startOfDay()->diffInDays($fila->fecha->timezone($zona)->startOfDay()),
            'motivo' => $fila->motivo === null ? null : trim($fila->motivo.($fila->motivoDetalle === null ? '' : " · {$fila->motivoDetalle}")),
            'comentario' => $fila->comentario,
            'quien' => $fila->originadoPor === null ? $fila->usuario : "{$fila->usuario} (por {$fila->originadoPor})",
        ];
    }
}
