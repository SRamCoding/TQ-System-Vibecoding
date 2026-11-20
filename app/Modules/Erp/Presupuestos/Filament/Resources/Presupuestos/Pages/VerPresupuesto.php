<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Filament\Resources\Presupuestos\Pages;

use App\Modules\Erp\Presupuestos\Estados\DefinicionPresupuesto;
use App\Modules\Erp\Presupuestos\Filament\Montos;
use App\Modules\Erp\Presupuestos\Filament\Resources\Presupuestos\PresupuestoResource;
use App\Modules\Erp\Presupuestos\Models\Presupuesto;
use App\Modules\Erp\Presupuestos\Services\ConsultaPresupuestos;
use App\Modules\Erp\Proyectos\Services\ProyectosService;
use App\Modules\Erp\Proyectos\Services\ResumenProyecto;
use App\Modules\Nucleo\Filament\AccionFilament;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Livewire\Attributes\On;

/**
 * La ficha de un presupuesto (plan 2.10, D4): cabecera con número, versión, estado y proyecto; los totales arriba y,
 * en pestañas, sus partidas, su lista de insumos y su historial. «Editar encabezado» solo en borrador. Las transiciones
 * (paso 7): «Ofertar» y «Traer al proyecto» con lo sin detallar y los materiales sin producto en su confirmación (D13
 * de la 2.9, avisa sin bloquear); «Nueva versión» desde el aprobado, que abre la versión creada; en «Más», cambiar con
 * o sin APU y «Anular» con motivo.
 *
 * @property-read Presupuesto $record
 */
final class VerPresupuesto extends ViewRecord
{
    protected static string $resource = PresupuestoResource::class;

    #[On('presupuesto-cambiado')]
    public function alCambiar(): void
    {
        $this->record->refresh();
    }

    public function getTitle(): string
    {
        return "{$this->record->numero} v{$this->record->numero_version} · {$this->record->nombre}";
    }

    public function getSubheading(): string
    {
        return PresupuestoResource::subtitulo($this->record);
    }

    /**
     * @return list<Action|ActionGroup>
     */
    protected function getHeaderActions(): array
    {
        $en = fn (string ...$estados): Closure => fn (Presupuesto $record): bool => ! in_array($record->estado->codigo, $estados, true);

        return [
            $this->transicion('ofertar')->name('ofertar')->label('Ofertar')->icon('heroicon-o-paper-airplane')
                ->modalDescription(fn (Presupuesto $record): string => $this->confirmacion($record, 'Queda congelado para enviarlo al cliente: ya no se edita.'))
                ->hidden($en(DefinicionPresupuesto::BORRADOR)),
            AccionFilament::desde('erp.presupuestos.traer-presupuesto-al-proyecto', fn (Presupuesto $record): array => ['presupuesto_id' => $record->id], $this->avisarGuardado(...),
                soloLectura: fn (Presupuesto $record): array => $record->proyecto_id === null ? [] : ['proyecto_id'],
                controles: ['proyecto_id' => fn (): Field => self::selectorProyecto()])
                ->name('traerAlProyecto')->label('Traer al proyecto')->icon('heroicon-o-arrow-down-on-square')
                ->modalDescription(fn (Presupuesto $record): string => $this->confirmacion($record, 'Lo aprueba y lo deja vigente en el proyecto: su costo es el que se controla. Ya no se edita; los cambios van en una versión nueva.'))
                ->fillForm(fn (Presupuesto $record): array => ['proyecto_id' => $record->proyecto_id, 'version' => $record->version])
                ->hidden($en(DefinicionPresupuesto::BORRADOR, DefinicionPresupuesto::OFERTADO)),
            AccionFilament::desde('erp.presupuestos.nueva-version-presupuesto', fn (Presupuesto $record): array => ['presupuesto_id' => $record->id], $this->abrirVersionNueva(...))
                ->name('nuevaVersion')->label('Nueva versión')->icon('heroicon-o-document-duplicate')->color('gray')
                ->requiresConfirmation()
                ->hidden($en(DefinicionPresupuesto::APROBADO)),
            AccionFilament::desde('erp.presupuestos.editar-encabezado-presupuesto', fn (Presupuesto $record): array => ['presupuesto_id' => $record->id], $this->avisarGuardado(...),
                controles: ['moneda_id' => fn () => PresupuestoResource::selectorMoneda()])
                ->name('editarEncabezado')->label('Editar encabezado')->icon('heroicon-o-pencil-square')->color('gray')->slideOver()
                ->fillForm(fn (Presupuesto $record): array => [
                    ...$record->only(['nombre', 'moneda_id', 'gastos_generales_pct', 'utilidad_pct', 'igv_pct']),
                    'version' => $record->version,
                ])
                // El formulario trae el porcentaje de utilidad (grupo «margen»): sin verlo, no se ofrece (ADR 0016).
                ->hidden(fn (Presupuesto $record): bool => $en(DefinicionPresupuesto::BORRADOR)($record) || ! Montos::visibles(GrupoSensible::Margen)),
            ActionGroup::make([
                AccionFilament::desde('erp.presupuestos.cambiar-usa-apu', fn (Presupuesto $record): array => ['presupuesto_id' => $record->id, 'usa_apu' => ! $record->usa_apu], $this->avisarGuardado(...),
                    ocultos: fn (Presupuesto $record): array => $record->usa_apu ? [] : ['confirma_descartar_apu'])
                    ->name('cambiarUsaApu')->icon('heroicon-o-arrows-right-left')
                    ->label(fn (Presupuesto $record): string => $record->usa_apu ? 'Pasar a precio por partida' : 'Pasar a análisis de precios')
                    ->modalDescription(fn (Presupuesto $record): string => $record->usa_apu
                        ? 'Cada partida se queda con su precio unitario y se descartan sus análisis de precios.'
                        : 'Cada partida recibe un análisis con su precio unitario como «Precio sin detallar»; después se detalla con insumos.')
                    ->fillForm(fn (Presupuesto $record): array => ['version' => $record->version])
                    ->hidden($en(DefinicionPresupuesto::BORRADOR)),
                $this->transicion('anular')->name('anular')->label('Anular')->icon('heroicon-o-no-symbol')->color('danger')
                    ->modalDescription('Solo en borrador. No se borra: queda en la lista como anulado.')
                    ->hidden($en(DefinicionPresupuesto::BORRADOR)),
            ])->label('Más')->icon('heroicon-o-ellipsis-vertical')->button(),
        ];
    }

    /** Una transición del núcleo (`AccionTransicion`) sobre este presupuesto, con el motivo si lo pide. */
    private function transicion(string $codigo): Action
    {
        return AccionFilament::desde(DefinicionPresupuesto::CLAVE.".{$codigo}", fn (Presupuesto $record): array => ['documento_id' => $record->id], $this->avisarGuardado(...))
            ->fillForm(fn (Presupuesto $record): array => ['version' => $record->version]);
    }

    /**
     * Lo que dice la confirmación de ofertar o traer al proyecto: qué pasa, cuánto va sin detallar (D10 de la 2.9) y los
     * materiales sin producto (D13): avisa, no bloquea.
     */
    private function confirmacion(Presupuesto $presupuesto, string $quePasa): string
    {
        $consulta = app(ConsultaPresupuestos::class);
        $sinDetallar = $presupuesto->usa_apu ? $consulta->sinDetallar($presupuesto->id) : '0.00';

        return implode(' ', array_filter([
            $quePasa,
            bccomp($sinDetallar, '0', 2) === 0 ? null : (Montos::visibles() ? 'Sin detallar: '.Montos::texto($sinDetallar).' del costo directo.' : 'Parte del costo directo va sin detallar.'),
            $consulta->materialesSinProducto($presupuesto->id)->mensaje(),
        ]));
    }

    /** Los proyectos que aceptan costos, por C2 (nunca su modelo); el ya elegido se muestra aunque no esté entre ellos. */
    private static function selectorProyecto(): Field
    {
        return Select::make('proyecto_id')->searchable()
            ->getSearchResultsUsing(fn (string $search): array => collect(app(ProyectosService::class)->activos($search))
                ->mapWithKeys(fn (ResumenProyecto $p): array => [$p->id => "{$p->codigo} · {$p->nombre}"])->all())
            ->getOptionLabelUsing(fn (mixed $value): ?string => is_numeric($value) ? PresupuestoResource::proyecto((int) $value) : null);
    }

    private function abrirVersionNueva(Presupuesto $nueva): void
    {
        Notification::make()->success()->title("Versión {$nueva->numero_version} creada, en borrador.")->send();
        $this->redirect(VerPresupuesto::getUrl(['record' => $nueva]), navigate: true);
    }

    private function avisarGuardado(): void
    {
        Notification::make()->success()->title('Guardado.')->send();
        $this->record->refresh();
        $this->dispatch('presupuesto-cambiado');
    }
}
