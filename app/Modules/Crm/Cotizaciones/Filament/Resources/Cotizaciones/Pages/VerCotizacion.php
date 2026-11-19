<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Filament\Resources\Cotizaciones\Pages;

use App\Modules\Crm\Cotizaciones\Estados\DefinicionCotizacion;
use App\Modules\Crm\Cotizaciones\Filament\Resources\Cotizaciones\CotizacionResource;
use App\Modules\Crm\Cotizaciones\Filament\SelectoresCotizacion;
use App\Modules\Crm\Cotizaciones\Filament\VersionesDeCotizacion;
use App\Modules\Crm\Cotizaciones\Models\Cotizacion;
use App\Modules\Crm\Cotizaciones\Services\PdfCotizacion;
use App\Modules\Nucleo\Filament\AccionFilament;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Tiempo\Calendario;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;

/**
 * La ficha de una versión de la cotización (D19): número, versión, estado, moneda, TC y validez arriba; totales y margen
 * en vivo; condiciones; y en pestañas las líneas y el historial. Las transiciones van en paneles
 * (`AccionFilament::desde`), cada una con la versión leída al abrirla (AGENTS.md): solicitar aprobación, emitir,
 * aceptar, «Rechazada por el cliente», y en «Más» volver a borrador, nueva versión, anular y anular la aceptación. El
 * servicio decide; aquí solo se ocultan los botones que el estado no permite.
 *
 * @property-read Cotizacion $record
 */
final class VerCotizacion extends ViewRecord
{
    protected static string $resource = CotizacionResource::class;

    #[On('cotizacion-cambiada')]
    public function alCambiar(): void
    {
        $this->record->refresh();
    }

    public function getTitle(): string
    {
        return "{$this->record->numero} v{$this->record->numero_version} · ".CotizacionResource::cuenta($this->record->cuenta_id);
    }

    public function getSubheading(): string
    {
        return CotizacionResource::subtitulo($this->record);
    }

    /** @return list<Action|ActionGroup> */
    protected function getHeaderActions(): array
    {
        $en = fn (string ...$estados): Closure => fn (Cotizacion $record): bool => ! in_array($record->estado->codigo, $estados, true);
        $propia = fn (string $codigo): Action => AccionFilament::desde(DefinicionCotizacion::CLAVE.".{$codigo}", fn (Cotizacion $record): array => ['cotizacion_id' => $record->id], $this->avisarGuardado(...))
            ->fillForm(fn (Cotizacion $record): array => ['version' => $record->version]);

        return [
            $propia(DefinicionCotizacion::SOLICITAR_APROBACION)->name('solicitarAprobacion')->label('Solicitar aprobación')->icon('heroicon-o-hand-raised')
                ->modalDescription(fn (Cotizacion $record): string => 'El margen está bajo el mínimo: va a quien dicen las reglas de aprobación. '.CotizacionResource::metaDeMargen($record))
                ->hidden(fn (Cotizacion $record): bool => $en(DefinicionCotizacion::BORRADOR)($record) || ! CotizacionResource::bajoElMinimo($record)),
            $propia(DefinicionCotizacion::EMITIR)->name('emitir')->label('Emitir')->icon('heroicon-o-paper-airplane')
                ->modalDescription('Queda congelada para enviarla al cliente: ya no se edita. Empieza a correr su validez.')
                ->hidden(fn (Cotizacion $record): bool => $en(DefinicionCotizacion::BORRADOR, DefinicionCotizacion::APROBADA)($record)
                    || ($record->estado->codigo === DefinicionCotizacion::BORRADOR && CotizacionResource::bajoElMinimo($record))),
            // Plan 2.22b: el archivo del respaldo se sube aquí y queda adjunto a la cotización.
            AccionFilament::desde(DefinicionCotizacion::CLAVE.'.'.DefinicionCotizacion::ACEPTAR, fn (Cotizacion $record): array => ['cotizacion_id' => $record->id], $this->avisarGuardado(...),
                ocultos: fn (): array => ['respaldo_adjunto_id'])->name('aceptar')->label('Aceptar')->icon('heroicon-o-check-circle')->color('success')->slideOver()
                ->modalDescription('El cliente la aceptó: registra el documento que lo respalda (orden de compra, buena pro, contrato o correo).')
                ->fillForm(fn (Cotizacion $record): array => ['version' => $record->version, 'fecha_aceptacion' => Calendario::hoy()])
                ->hidden($en(DefinicionCotizacion::EMITIDA)),
            $this->transicion(DefinicionCotizacion::RECHAZAR)->name('rechazar')->label('Rechazada por el cliente')->icon('heroicon-o-x-circle')->color('gray')
                ->hidden($en(DefinicionCotizacion::EMITIDA)),
            // D20: el PDF guardado al emitir o, antes, la vista previa con los datos de ahora.
            Action::make('pdf')
                ->label(fn (Cotizacion $record): string => $record->adjunto_pdf_id === null ? 'Vista previa del PDF' : 'PDF')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->slideOver()
                ->modalWidth(Width::FiveExtraLarge)
                ->modalHeading(fn (Cotizacion $record): string => "{$record->numero} v{$record->numero_version}")
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar')
                ->modalContent(function (Cotizacion $record): View {
                    $usuario = auth()->user();
                    abort_unless($usuario instanceof User, 403);
                    $pdf = app(PdfCotizacion::class)->paraVer($record->id, $usuario);

                    return view('cotizaciones::pdf.vista-previa', [
                        'base64' => base64_encode($pdf['contenido']),
                        'descarga' => $pdf['adjuntoId'] === null ? null : route('filament.app.adjuntos.descargar', ['adjunto' => $pdf['adjuntoId']]),
                    ]);
                }),
            VersionesDeCotizacion::comparar(fn (Cotizacion $record): int => $record->id, fn (Cotizacion $record): int => $this->anterior($record) ?? $record->id)
                ->name('compararAnterior')
                ->label(fn (Cotizacion $record): string => 'Comparar con la versión '.($record->numero_version - 1))
                ->hidden(fn (Cotizacion $record): bool => $this->anterior($record) === null),
            ActionGroup::make([
                AccionFilament::desde('crm.cotizaciones.editar-cotizacion', fn (Cotizacion $record): array => ['cotizacion_id' => $record->id], $this->avisarGuardado(...),
                    controles: ['contacto_id' => fn () => SelectoresCotizacion::contacto(oportunidadFija: $this->record->oportunidad_id)])
                    ->name('editarEncabezado')->label('Editar encabezado')->icon('heroicon-o-pencil-square')->slideOver()
                    ->fillForm(fn (Cotizacion $record): array => [
                        ...$record->only(['contacto_id', 'moneda_id', 'dias_validez', 'plazo_entrega_dias', 'forma_pago_id', 'forma_pago_detalle', 'garantia_meses', 'lugar_entrega']),
                        'version' => $record->version,
                    ])
                    ->hidden($en(DefinicionCotizacion::BORRADOR)),
                $this->transicion(DefinicionCotizacion::REABRIR)->name('reabrir')->label('Volver a borrador')->icon('heroicon-o-arrow-uturn-left')
                    ->hidden($en(DefinicionCotizacion::APROBADA)),
                AccionFilament::desde('crm.cotizaciones.nueva-version-cotizacion', fn (Cotizacion $record): array => ['cotizacion_id' => $record->id], $this->abrirVersionNueva(...))
                    ->name('nuevaVersion')->label('Nueva versión')->icon('heroicon-o-document-duplicate')->slideOver()
                    ->modalDescription('Copia esta versión en una nueva, en borrador. Al emitir la nueva, esta queda reemplazada.')
                    ->hidden($en(...DefinicionCotizacion::SALE_VERSION_NUEVA)),
                $this->transicion(DefinicionCotizacion::ANULAR)->name('anular')->label('Anular')->icon('heroicon-o-no-symbol')->color('danger')
                    ->modalDescription('No se borra: queda en la lista como anulada.')
                    ->hidden($en(...DefinicionCotizacion::EN_CURSO)),
                $propia(DefinicionCotizacion::ANULAR_ACEPTACION)->name('anularAceptacion')->label('Anular la aceptación')->icon('heroicon-o-arrow-uturn-left')->color('danger')
                    ->modalDescription('Vuelve a emitida. Solo mientras la oportunidad no esté ganada.')
                    ->hidden($en(DefinicionCotizacion::ACEPTADA)),
            ])->label('Más')->icon('heroicon-o-ellipsis-vertical')->button(),
        ];
    }

    /** La versión anterior del mismo número, si existe. */
    private function anterior(Cotizacion $c): ?int
    {
        $id = Cotizacion::query()->where('numero', $c->numero)->where('numero_version', $c->numero_version - 1)->value('id');

        return is_numeric($id) ? (int) $id : null;
    }

    /** Una transición del núcleo (`AccionTransicion`) sobre esta versión, con el motivo si lo pide. */
    private function transicion(string $codigo): Action
    {
        return AccionFilament::desde(DefinicionCotizacion::CLAVE.".{$codigo}", fn (Cotizacion $record): array => ['documento_id' => $record->id], $this->avisarGuardado(...))
            ->fillForm(fn (Cotizacion $record): array => ['version' => $record->version]);
    }

    private function abrirVersionNueva(mixed $nueva): void
    {
        if (! $nueva instanceof Cotizacion) {
            return;
        }

        Notification::make()->success()->title("Versión {$nueva->numero_version} creada, en borrador.")->send();
        $this->redirect(VerCotizacion::getUrl(['record' => $nueva]), navigate: true);
    }

    private function avisarGuardado(): void
    {
        Notification::make()->success()->title('Guardado.')->send();
        $this->record->refresh();
        $this->dispatch('cotizacion-cambiada');
    }
}
