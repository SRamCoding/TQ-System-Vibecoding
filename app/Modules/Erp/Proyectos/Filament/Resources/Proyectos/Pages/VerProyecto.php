<?php

declare(strict_types=1);

namespace App\Modules\Erp\Proyectos\Filament\Resources\Proyectos\Pages;

use App\Modules\Erp\Proyectos\Estados\DefinicionProyecto;
use App\Modules\Erp\Proyectos\Filament\Resources\Proyectos\ProyectoResource;
use App\Modules\Erp\Proyectos\Models\Proyecto;
use App\Modules\Nucleo\Filament\AccionFilament;
use App\Modules\Nucleo\Services\RevisionDeFoto;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Livewire\Attributes\On;

/**
 * La ficha de un proyecto (plan 2.10, D3): cabecera con código, cliente, estado, jefe y fechas; «Cerrar» y «Anular»
 * en «Más», cada una solo donde aplica y con su permiso. Lo del presupuesto y el árbol vive en sus pestañas.
 *
 * @property-read Proyecto $record
 */
final class VerProyecto extends ViewRecord
{
    protected static string $resource = ProyectoResource::class;

    #[On('proyecto-cambiado')]
    public function alCambiarElProyecto(): void
    {
        $this->record->refresh();
    }

    public function getTitle(): string
    {
        return "{$this->record->codigo} · {$this->record->nombre}";
    }

    /**
     * @return list<Action|ActionGroup>
     */
    protected function getHeaderActions(): array
    {
        $estado = fn (Proyecto $record): string => $record->estado->codigo;

        return [
            $this->cambiarFoto(),
            ActionGroup::make([
                AccionFilament::desde('erp.proyectos.quitar-foto', fn (Proyecto $record): array => ['proyecto_id' => $record->id], $this->avisarGuardado(...))
                    ->label('Quitar la foto')->icon('heroicon-o-trash')->color('gray')
                    ->requiresConfirmation()->modalDescription('La tarjeta y la cabecera vuelven al dibujo del sistema.')
                    ->hidden(fn (Proyecto $record): bool => ! $this->esJefe($record) || $record->foto_ruta === null),
                $this->transicion('cerrar')->label('Cerrar')->icon('heroicon-o-lock-closed')
                    ->modalDescription('Deja de recibir costos. Se avisa a quienes siguen el proyecto.')
                    ->hidden(fn (Proyecto $record): bool => $estado($record) !== DefinicionProyecto::EN_EJECUCION),
                $this->transicion('anular')->label('Anular')->icon('heroicon-o-no-symbol')->color('danger')
                    ->modalDescription('Solo en preparación y sin costos cargados. No se borra.')
                    ->hidden(fn (Proyecto $record): bool => $estado($record) !== DefinicionProyecto::EN_PREPARACION),
            ])->label('Más')->icon('heroicon-o-ellipsis-vertical')->button(),
        ];
    }

    /**
     * Plan 2.61, D1 y D2: solo el jefe del proyecto. El navegador abre el editor para encuadrarla en 16:9 y la achica
     * antes de subirla, como la foto de perfil.
     */
    private function cambiarFoto(): Action
    {
        return AccionFilament::desde('erp.proyectos.cambiar-foto', fn (Proyecto $record): array => ['proyecto_id' => $record->id], $this->avisarGuardado(...), controles: [
            'foto' => fn (): Field => FileUpload::make('foto')->image()
                ->imageEditor()->imageEditorAspectRatioOptions(['16:9'])->automaticallyOpenImageEditorForAspectRatio()
                ->imageAspectRatio('16:9')->automaticallyCropImagesToAspectRatio()->automaticallyResizeImagesToWidth('1600')
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->maxSize(RevisionDeFoto::TOPE_MB * 1024)
                ->storeFiles(false),
        ])
            ->name('cambiarFoto')
            ->label(fn (Proyecto $record): string => $record->foto_ruta === null ? 'Subir foto' : 'Cambiar foto')
            ->icon('heroicon-o-camera')->color('gray')
            ->modalHeading('Foto del proyecto')
            ->modalSubmitActionLabel('Guardar foto')
            ->hidden(fn (Proyecto $record): bool => ! $this->esJefe($record));
    }

    private function esJefe(Proyecto $proyecto): bool
    {
        return $proyecto->jefe_proyecto_user_id === auth()->id();
    }

    /** Una transición del núcleo (`AccionTransicion`) sobre este proyecto, con el motivo si lo pide. */
    private function transicion(string $codigo): Action
    {
        return AccionFilament::desde(DefinicionProyecto::CLAVE.".{$codigo}", fn (Proyecto $record): array => ['documento_id' => $record->id], $this->avisarGuardado(...))
            ->fillForm(fn (Proyecto $record): array => ['version' => $record->version]);
    }

    private function avisarGuardado(): void
    {
        Notification::make()->success()->title('Guardado.')->send();
        $this->record->refresh();
        $this->dispatch('proyecto-cambiado');
    }
}
