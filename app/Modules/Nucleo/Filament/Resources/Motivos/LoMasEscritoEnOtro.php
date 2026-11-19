<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Resources\Motivos;

use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Estados\DetalleDeOtro;
use App\Modules\Nucleo\Filament\AccionFilament;
use App\Modules\Nucleo\Filament\SelectorCatalogo;
use App\Modules\Nucleo\Models\TipoMotivo;
use App\Modules\Nucleo\Services\CatalogosService;
use App\Modules\Nucleo\Services\MotivosService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * «Lo más escrito en Otro» (plan 2.0, D3 y D5): lo que la gente escribió al elegir «Otro» en una familia de motivos o
 * en un catálogo con «Otro» (orígenes comerciales, redes sociales), agrupado sin acentos ni mayúsculas, con cuántas
 * veces y la última, de los últimos 12 meses. «Agregar a la lista» abre el alta con ese texto ya escrito, por la acción
 * registrada de siempre: así Administración suma lo que se repite y nadie queda bloqueado sin permiso.
 */
final class LoMasEscritoEnOtro extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    /** De cuántos meses atrás (D3). */
    public const int MESES = 12;

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (?array $filters): array => self::filas((string) ($filters['lista']['value'] ?? '')))
            ->paginated(false)
            ->heading('Lo más escrito en «Otro»')
            // En los catálogos, la fecha es la del último cambio del registro que lo usa (revisión de arquitectura).
            ->description('Lo que se eligió como «Otro» en los últimos '.self::MESES.' meses, de lo más repetido a lo menos. Agrega a la lista lo que se repite. En los catálogos, «Última vez» es el último cambio del registro que lo usa.')
            ->columns([
                TextColumn::make('texto')->label('Escrito')->wrap()->weight('bold'),
                TextColumn::make('veces')->label('Veces')->alignEnd(),
                TextColumn::make('ultimo_uso')->label('Última vez'),
            ])
            ->filters([
                SelectFilter::make('lista')->label('Lista')->options(self::listas())->default(array_key_first(self::listas())),
            ])
            ->recordActions([
                AccionFilament::desde('nucleo.motivos.agregar', fn (array $record): array => ['tipo_motivo_id' => (int) $record['familia_id']], fn () => $this->avisar())
                    ->name('agregarMotivo')->label('Agregar a la lista')->icon('heroicon-o-plus')
                    ->fillForm(fn (array $record): array => ['nombre' => $record['texto']])
                    ->hidden(fn (array $record): bool => $record['familia_id'] === null),
                ...array_map(fn (Catalogo $catalogo) => AccionFilament::desde(SelectorCatalogo::claveAgregar($catalogo), alTerminar: fn () => $this->avisar())
                    ->name('agregar'.str_replace('_', '', ucwords($catalogo->value, '_')))->label('Agregar a la lista')->icon('heroicon-o-plus')
                    ->fillForm(fn (array $record): array => ['nombre' => $record['texto'], 'codigo' => $catalogo->codigoDesdeNombre((string) $record['texto'])])
                    ->hidden(fn (array $record): bool => $record['catalogo'] !== $catalogo->value), self::catalogosConOtro()),
            ])
            ->emptyStateHeading('Nada escrito en «Otro»')
            ->emptyStateDescription('En esta lista nadie eligió «Otro» en los últimos '.self::MESES.' meses.');
    }

    /**
     * Las familias de motivos y los catálogos con «Otro»: «motivos:<id>» y «catalogo:<valor>».
     *
     * @return array<string, string>
     */
    public static function listas(): array
    {
        $listas = [];

        foreach (TipoMotivo::query()->orderBy('nombre')->get(['id', 'nombre']) as $familia) {
            $listas["motivos:{$familia->id}"] = $familia->nombre;
        }

        foreach (self::catalogosConOtro() as $catalogo) {
            $listas["catalogo:{$catalogo->value}"] = 'Catálogo: '.$catalogo->titulo();
        }

        return $listas;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function filas(string $lista): array
    {
        $desde = Calendario::ahora()->subMonths(self::MESES);
        [$tipo, $cual] = array_pad(explode(':', $lista, 2), 2, '');
        $familia = $tipo === 'motivos' ? TipoMotivo::query()->find((int) $cual) : null;
        $catalogo = $tipo === 'catalogo' ? Catalogo::tryFrom($cual) : null;

        $detalles = match (true) {
            $familia !== null => app(MotivosService::class)->detallesDeOtro($familia->codigo, $desde),
            $catalogo !== null => app(CatalogosService::class)->detallesDeOtro($catalogo, $desde),
            default => [],
        };

        $filas = [];

        foreach ($detalles as $detalle) {
            $filas[$detalle->clave] = self::fila($detalle, $familia?->id, $catalogo);
        }

        return $filas;
    }

    /** @return list<Catalogo> */
    private static function catalogosConOtro(): array
    {
        return array_values(array_filter(Catalogo::cases(), fn (Catalogo $c): bool => $c->codigoOtro() !== null));
    }

    /** @return array<string, mixed> */
    private static function fila(DetalleDeOtro $detalle, ?int $familiaId, ?Catalogo $catalogo): array
    {
        return [
            'texto' => $detalle->texto,
            'veces' => $detalle->veces,
            'ultimo_uso' => $detalle->ultimoUso->format('d/m/Y'),
            'familia_id' => $familiaId,
            'catalogo' => $catalogo?->value,
        ];
    }

    private function avisar(): void
    {
        Notification::make()->success()->title('Agregado a la lista.')->send();
        $this->resetTable();
    }

    public function render(): View
    {
        return view('nucleo::filament.motivos.lo-mas-escrito-en-otro');
    }
}
