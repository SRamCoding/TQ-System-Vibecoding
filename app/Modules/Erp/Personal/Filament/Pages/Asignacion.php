<?php

declare(strict_types=1);

namespace App\Modules\Erp\Personal\Filament\Pages;

use App\Modules\Erp\Personal\Models\Colaborador;
use App\Modules\Erp\Personal\Services\PersonalService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Tiempo\Calendario;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;

/** MVP rápido (t4-personal-tareo): Personal asignado, una tarjeta por persona con sus cuatro semanas (MV-067); marca la sobreasignación antes del conflicto. */
final class Asignacion extends Page
{
    protected static ?string $slug = 'asignacion';

    protected static ?string $title = 'Personal asignado';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    public static function canAccess(): bool
    {
        $u = Filament::auth()->user();

        return $u instanceof User && $u->can(PersonalService::PERMISO_VER);
    }

    protected string $view = 'personal::asignacion.pantalla';

    public string $buscar = '';

    /** @return list<array<string, mixed>> las tarjetas de Personal asignado (MV-067) */
    public function tarjetas(): array
    {
        return app(PersonalService::class)->tableroAsignacion($this->buscar);
    }

    /** Clic en la tarjeta: el detalle de la persona en el panel deslizante, semana por semana. */
    public function detalleAction(): Action
    {
        $tarjeta = fn (array $arguments): ?array => collect(app(PersonalService::class)->tableroAsignacion())->firstWhere('id', (int) ($arguments['colaborador'] ?? 0));

        return Action::make('detalle')->slideOver()->modalWidth('md')
            ->modalHeading(fn (array $arguments): string => $tarjeta($arguments)['nombres'] ?? 'Persona')
            ->modalContent(fn (array $arguments) => view('personal::asignacion.detalle', ['t' => $tarjeta($arguments)]))
            ->modalSubmitAction(false)->modalCancelActionLabel('Cerrar')
            ->extraModalFooterActions(fn (array $arguments): array => [
                $this->fotoAccion((int) ($arguments['colaborador'] ?? 0)),
            ]);
    }

    /** Subir o cambiar la foto (se recorta cuadrada) o quitarla; sin foto vuelve el avatar por defecto. */
    private function fotoAccion(int $id): Action
    {
        $tiene = DB::table('colaboradores')->where('id', $id)->value('foto_ruta') !== null;

        return Action::make('foto')->label($tiene ? 'Cambiar foto' : 'Subir foto')->icon('heroicon-o-camera')->color('gray')
            ->visible(fn (): bool => auth()->user()?->can(PersonalService::PERMISO_TAREAR) ?? false)
            ->modalHeading('Foto de perfil')->modalSubmitActionLabel('Guardar foto')
            ->schema([self::campoFoto()])
            ->extraModalFooterActions($tiene ? [Action::make('quitarFoto')->label('Quitar foto')->color('danger')->link()->cancelParentActions()
                ->action(function () use ($id): void {
                    app(PersonalService::class)->quitarFoto($id);
                    Notification::make()->success()->title('Foto quitada.')->send();
                })] : [])
            ->action(function (array $data) use ($id): void {
                self::guardarFoto($id, $data['foto'] ?? null);
            });
    }

    public static function campoFoto(): FileUpload
    {
        return FileUpload::make('foto')->label('Foto')->image()->required()->imageEditor()->imageCropAspectRatio('1:1')->automaticallyOpenImageEditorForAspectRatio()
            ->automaticallyResizeImagesToWidth('400')->automaticallyResizeImagesToHeight('400')
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(5 * 1024)->storeFiles(false);
    }

    public static function guardarFoto(int $id, mixed $archivo): void
    {
        $archivo = is_array($archivo) ? reset($archivo) : $archivo;

        if (! $archivo instanceof TemporaryUploadedFile) {
            return;
        }

        app(PersonalService::class)->cambiarFoto($id, (string) $archivo->get(), $archivo->guessExtension() ?: 'jpg');
        Notification::make()->success()->title('Foto guardada.')->send();
    }

    protected function getHeaderActions(): array
    {
        $puede = fn (): bool => auth()->user()?->can(PersonalService::PERMISO_TAREAR) ?? false;

        return [
            Action::make('colaborador')->label('Nuevo colaborador')->icon('heroicon-o-user-plus')->color('gray')->visible($puede)
                ->schema([
                    TextInput::make('nombres')->label('Nombres y apellidos')->required()->maxLength(120),
                    TextInput::make('dni')->label('DNI')->required()->length(8),
                    Select::make('categoria_id')->label('Categoría')->required()->options(fn (): array => DB::table('categorias_personal')->pluck('nombre', 'id')->all()),
                    Select::make('vinculo')->label('Vínculo')->required()->options(['planilla' => 'Planilla', 'eventual' => 'Eventual']),
                    Select::make('sexo')->label('Sexo')->required()->options(PersonalService::SEXOS),
                    DatePicker::make('habilitado_hasta')->label('SCTR vigente hasta'),
                ])
                ->action(fn (array $data) => Colaborador::query()->create($data)),
            Action::make('asignar')->label('Asignar')->icon('heroicon-o-plus')->visible($puede)
                ->fillForm(fn (): array => ['desde' => Calendario::hoy(), 'porcentaje' => 100])
                ->schema([
                    Select::make('colaborador_id')->label('Persona')->required()->options(fn (): array => Colaborador::query()->where('es_activo', true)->orderBy('nombres')->pluck('nombres', 'id')->all()),
                    Select::make('proyecto_id')->label('Proyecto')->required()->options(fn (): array => DB::table('proyectos as p')->join('estados as e', 'e.id', '=', 'p.estado_id')->whereIn('e.codigo', ['en_preparacion', 'en_ejecucion'])->selectRaw("p.id, p.codigo || ' · ' || p.nombre AS t")->pluck('t', 'p.id')->all()),
                    TextInput::make('porcentaje')->label('Dedicación (%)')->numeric()->integer()->required(),
                    DatePicker::make('desde')->label('Desde')->required(),
                    DatePicker::make('hasta')->label('Hasta'),
                ])
                ->action(function (array $data): void {
                    try {
                        $s = app(PersonalService::class);
                        $s->asignar((int) $data['colaborador_id'], (int) $data['proyecto_id'], (int) $data['porcentaje'], CarbonImmutable::parse($data['desde']), filled($data['hasta'] ?? null) ? CarbonImmutable::parse($data['hasta']) : null);
                        $total = $s->dedicacion((int) $data['colaborador_id'], CarbonImmutable::parse($data['desde']));
                        Notification::make()->{$total > 100 ? 'warning' : 'success'}()->title($total > 100 ? "Asignado, pero queda sobreasignado al {$total} %." : 'Asignado.')->send();
                    } catch (RuntimeException $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();
                    }
                }),
        ];
    }
}
