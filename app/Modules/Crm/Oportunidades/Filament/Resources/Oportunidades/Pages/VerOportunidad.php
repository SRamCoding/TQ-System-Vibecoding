<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages;

use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Oportunidades\Estados\DefinicionOportunidad;
use App\Modules\Crm\Oportunidades\Estados\SobreLoPropio;
use App\Modules\Crm\Oportunidades\Filament\CumpleRequisitos;
use App\Modules\Crm\Oportunidades\Filament\MovimientoDeOportunidad;
use App\Modules\Crm\Oportunidades\Filament\PanelesOportunidad;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\OportunidadResource;
use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Crm\Oportunidades\Pestanas\BotonOportunidad;
use App\Modules\Crm\Oportunidades\Pestanas\RegistroPestanasOportunidad;
use App\Modules\Crm\Oportunidades\Services\FotoOportunidadService;
use App\Modules\Crm\Oportunidades\Services\PortadaOportunidades;
use App\Modules\Nucleo\Filament\AccionFilament;
use App\Modules\Nucleo\Services\RevisionDeFoto;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\On;

/**
 * La ficha de una oportunidad (plan 2.21, D11.5): cabecera con nombre, cuenta (enlace), monto, etapa · %, ponderado y
 * días en la etapa; «Avanzar etapa» y «Cerrar» (ganada o perdida) en paneles deslizantes; Retroceder, Anular y Anular
 * ganada en «Más», cada una solo donde aplica y con su permiso (D10); y los botones que suman otros módulos. Cada
 * formulario toma la versión al abrirse.
 *
 * @property-read Oportunidad $record
 */
final class VerOportunidad extends ViewRecord
{
    use CumpleRequisitos;

    protected static string $resource = OportunidadResource::class;

    #[On('oportunidad-cambiada')]
    public function alCambiarLaOportunidad(): void
    {
        $this->record->refresh();
    }

    public function getTitle(): string
    {
        return $this->record->nombre;
    }

    public function getSubheading(): Htmlable
    {
        $oportunidad = $this->record;
        $portada = app(PortadaOportunidades::class);
        $partes = [
            e($oportunidad->numero),
            '<a href="'.e(url('/personas/'.app(CuentasService::class)->resumen($oportunidad->cuenta_id)->partyId)).'" style="text-decoration: underline">'.e(OportunidadResource::cuenta($oportunidad->cuenta_id)).'</a>',
            e(OportunidadResource::monto($oportunidad)),
            e(OportunidadResource::etapa($oportunidad)),
        ];
        $ponderado = OportunidadResource::ponderado($oportunidad);

        if ($ponderado !== null) {
            $dias = $portada->diasEnEtapa($oportunidad);
            $partes[] = e("Ponderado {$ponderado}");
            $partes[] = e(($dias === 1 ? '1 día' : "{$dias} días").' en la etapa'.($portada->pasaElMaximo($oportunidad, $oportunidad->estado->codigo) ? ' (estancada)' : ''));
        }

        $partes[] = e("Ejecutivo: {$oportunidad->ejecutivo->name}");

        return new HtmlString(implode(' · ', $partes));
    }

    /**
     * @return list<Action|ActionGroup>
     */
    protected function getHeaderActions(): array
    {
        $etapa = fn (Oportunidad $record): string => $record->estado->codigo;
        $id = fn (Oportunidad $record): int => $record->id;
        $campos = fn (Oportunidad $record): array => [
            ...$record->only(['nombre', 'descripcion', 'monto_estimado', 'moneda_id', 'linea_solucion_id', 'origen_comercial_id', 'origen_detalle', 'necesidad_confirmada', 'presupuesto_confirmado', 'decisor_contacto_id']),
            'fecha_cierre_estimada' => $record->fecha_cierre_estimada->toDateString(),
            'version' => $record->version,
        ];
        // D10: el decisor se elige en Avanzar, con los contactos de la cuenta.
        $sinDecisor = fn (): array => ['decisor_contacto_id'];

        // Plan 2.21d, D2 y D13: la foto de referencia, su ejecutivo o Gerencia Comercial; recortada a lo ancho y achicada en
        // el navegador antes de subirla, como la de Proyectos.
        $puedeFoto = fn (Oportunidad $record): bool => ($u = OportunidadResource::usuario()) !== null && FotoOportunidadService::puede($record, $u);

        return [
            AccionFilament::desde('crm.oportunidades.cambiar-foto', fn (Oportunidad $record): array => ['oportunidad_id' => $record->id], $this->avisarGuardado(...), controles: [
                'foto' => fn (): Field => FileUpload::make('foto')->image()
                    ->imageEditor()->imageEditorAspectRatioOptions(['16:9'])->automaticallyOpenImageEditorForAspectRatio()
                    ->imageAspectRatio('16:9')->automaticallyCropImagesToAspectRatio()->automaticallyResizeImagesToWidth('1600')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(RevisionDeFoto::TOPE_MB * 1024)->storeFiles(false),
            ])
                ->name('cambiarFoto')->label('Foto')->icon('heroicon-o-camera')->color('gray')
                ->modalHeading('Foto de la oportunidad')->modalDescription('Una foto de referencia: la sala, el edificio, el equipo. Encuádrala a lo ancho.')
                ->modalSubmitActionLabel('Guardar foto')
                ->hidden(fn (Oportunidad $record): bool => ! $puedeFoto($record)),
            AccionFilament::desde('crm.oportunidades.editar', fn (Oportunidad $record): array => ['oportunidad_id' => $record->id], $this->avisarGuardado(...), ocultos: $sinDecisor)
                ->label('Editar')->icon('heroicon-o-pencil-square')->color('gray')->slideOver()->fillForm($campos)
                ->hidden(fn (Oportunidad $record): bool => ! in_array($etapa($record), PanelesOportunidad::ABIERTAS, true)),
            AccionFilament::desde('crm.oportunidades.editar-propias', fn (Oportunidad $record): array => ['oportunidad_id' => $record->id], $this->avisarGuardado(...), ocultos: $sinDecisor)
                ->label('Editar')->icon('heroicon-o-pencil-square')->color('gray')->slideOver()->fillForm($campos)
                // Solo si no tiene el de todas y es su ejecutivo (D10).
                ->hidden(fn (Oportunidad $record): bool => ! in_array($etapa($record), PanelesOportunidad::ABIERTAS, true) || OportunidadResource::usuarioPuede(SobreLoPropio::EDITAR) || ! OportunidadResource::puedeEditar($record)),
            // D11.6: «Avanzar etapa» es la transición siguiente de la etapa actual.
            ...array_map(
                fn (string $desde): Action => PanelesOportunidad::avanzar($desde, $id, $this->avisarGuardado(...))
                    ->hidden(fn (Oportunidad $record): bool => $etapa($record) !== $desde),
                array_keys(PanelesOportunidad::AVANCE_DESDE),
            ),
            ActionGroup::make([
                PanelesOportunidad::ganar($id, $this->avisarGuardado(...))
                    ->hidden(fn (Oportunidad $record): bool => ! in_array($etapa($record), MovimientoDeOportunidad::ganaDesde(), true)),
                PanelesOportunidad::perder($id, $this->avisarGuardado(...))
                    ->hidden(fn (Oportunidad $record): bool => ! in_array($etapa($record), PanelesOportunidad::ABIERTAS, true)),
            ])->label('Cerrar')->icon('heroicon-o-flag')->button()->color('gray'),
            ...array_map(fn (BotonOportunidad $boton): Action => $boton->accion($this->record->id), app(RegistroPestanasOportunidad::class)->botonesPara(OportunidadResource::usuario())),
            ActionGroup::make([
                AccionFilament::desde('crm.oportunidades.quitar-foto', fn (Oportunidad $record): array => ['oportunidad_id' => $record->id], $this->avisarGuardado(...))
                    ->label('Quitar la foto')->icon('heroicon-o-trash')->color('gray')->requiresConfirmation()
                    ->modalDescription('La tarjeta y la ficha vuelven al dibujo de su línea de solución.')
                    ->hidden(fn (Oportunidad $record): bool => ! $puedeFoto($record) || $record->foto_ruta === null),
                $this->transicion('retroceder')->label('Retroceder')->icon('heroicon-o-arrow-uturn-left')
                    ->modalDescription('Vuelve a la etapa anterior, con su motivo. Los días en la etapa empiezan de nuevo.')
                    ->hidden(fn (Oportunidad $record): bool => ! in_array($etapa($record), ['calificada', 'propuesta_enviada', 'negociacion'], true)),
                $this->transicion('anular')->label('Anular')->icon('heroicon-o-no-symbol')->color('danger')
                    ->modalDescription('Para una oportunidad registrada por error. No se borra: deja de contar en el embudo.')
                    ->hidden(fn (Oportunidad $record): bool => ! in_array($etapa($record), [...PanelesOportunidad::ABIERTAS, 'perdida'], true)),
                $this->transicion('anular_ganada')->label('Anular ganada')->icon('heroicon-o-no-symbol')->color('danger')
                    ->modalDescription('Deshace la ganada: la cuenta deja de ser cliente por ella y se avisa a Contratos.')
                    ->hidden(fn (Oportunidad $record): bool => $etapa($record) !== 'ganada'),
            ])->label('Más')->icon('heroicon-o-ellipsis-vertical')->button(),
        ];
    }

    /** Una transición del núcleo (`AccionTransicion`) sobre esta oportunidad, con el motivo de su lista. */
    private function transicion(string $codigo): Action
    {
        return AccionFilament::desde(DefinicionOportunidad::CLAVE.".{$codigo}", fn (Oportunidad $record): array => ['documento_id' => $record->id], $this->avisarGuardado(...))
            ->fillForm(fn (Oportunidad $record): array => ['version' => $record->version]);
    }

    private function avisarGuardado(): void
    {
        Notification::make()->success()->title('Guardado.')->send();
        $this->record->refresh();
        $this->dispatch('oportunidad-cambiada');
    }
}
