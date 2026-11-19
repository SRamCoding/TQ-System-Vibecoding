<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Pages;

use App\Modules\Nucleo\ConfiguracionInicial\DefinicionPaso;
use App\Modules\Nucleo\ConfiguracionInicial\RegistroPasosConfiguracion;
use App\Modules\Nucleo\Filament\AccionFilament;
use App\Modules\Nucleo\Filament\Clusters\Configuracion;
use App\Modules\Nucleo\Filament\ConfiguracionInicial\MuestraPasoAbierto;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\ConfiguracionInicialService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Livewire\Attributes\Url;
use Throwable;

/**
 * Configuración → Configuración inicial (plan 1.13). Una lista guiada de los pasos de `RegistroPasosConfiguracion`: cada
 * uno dice cómo está y abre su pantalla de siempre en el panel deslizante, sin recargar (D4). No repite formularios ni
 * reglas: lo que se cambia pasa por las acciones de esa pantalla. «Listo» lo marca el administrador (D3). Lo que muestra
 * el paso abierto es común con la ventana de bienvenida (`MuestraPasoAbierto`, plan 1.13b).
 */
final class ConfiguracionInicial extends Page
{
    use MuestraPasoAbierto;

    protected static ?string $cluster = Configuracion::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rocket-launch';

    protected static ?string $navigationLabel = 'Configuración inicial';

    protected static ?string $title = 'Configuración inicial';

    protected static ?string $slug = 'inicial';

    protected static ?int $navigationSort = 0;

    /** El paso abierto en el panel, o null: la lista. */
    #[Url]
    public ?string $paso = null;

    /** La ruta de otra pantalla abierta dentro del panel (la ficha de una fila, por ejemplo), o null: la del paso. */
    #[Url]
    public ?string $ficha = null;

    public static function canAccess(): bool
    {
        $usuario = Filament::auth()->user();

        return $usuario instanceof User && $usuario->can(ConfiguracionInicialService::PERMISO);
    }

    public function abrir(string $paso): void
    {
        $this->paso = app(RegistroPasosConfiguracion::class)->paso($paso) !== null ? $paso : null;
        $this->ficha = null;
    }

    public function cerrar(): void
    {
        $this->paso = null;
        $this->ficha = null;
    }

    /** Plan 1.13b, D1: quien apagó la bienvenida con «Seguir después» la vuelve a encender aquí. */
    protected function getHeaderActions(): array
    {
        return [
            AccionFilament::desde('nucleo.configuracion-inicial.mostrar-bienvenida')
                ->name('mostrarBienvenida')
                ->label('Volver a mostrar la bienvenida')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->hidden(fn (): bool => ! ($usuario = Filament::auth()->user()) instanceof User || ! app(ConfiguracionInicialService::class)->bienvenidaApagada($usuario))
                ->after(fn () => Notification::make()->success()->title('La bienvenida vuelve a salir en tu próximo ingreso.')->send()),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('nucleo::filament.configuracion-inicial.pantalla')->viewData(fn (): array => $this->datosVista()),
        ]);
    }

    /** @return array<string, mixed> */
    private function datosVista(): array
    {
        $pasos = app(RegistroPasosConfiguracion::class)->pasos();
        $listos = app(ConfiguracionInicialService::class)->listos();
        $abierto = $this->abierto();

        return [
            'pasos' => array_map(fn (DefinicionPaso $paso): array => [
                'definicion' => $paso,
                'listo' => in_array($paso->clave, $listos, true),
                'resumen' => $this->resumen($paso),
            ], $pasos),
            'obligatorios' => count(array_filter($pasos, fn (DefinicionPaso $paso): bool => $paso->obligatorio)),
            'faltan' => count(app(ConfiguracionInicialService::class)->faltan($listos)),
            'abiertoListo' => $abierto !== null && in_array($abierto->clave, $listos, true),
            ...$this->datosDelPasoAbierto(),
        ];
    }

    /** El resumen de un paso; si falla al calcularse, no tumba la lista. */
    private function resumen(DefinicionPaso $paso): ?string
    {
        try {
            return $paso->resumen === null ? null : ($paso->resumen)();
        } catch (Throwable) {
            return null;
        }
    }

    public function marcarListoAction(): Action
    {
        return AccionFilament::desde('nucleo.configuracion-inicial.marcar-paso-listo', fn (array $arguments): array => ['paso' => (string) ($arguments['paso'] ?? '')])
            ->name('marcarListo')
            ->label('Marcar como listo')
            ->icon('heroicon-m-check')
            ->color('primary')
            ->size('sm')
            ->after(fn () => Notification::make()->success()->title('Paso listo.')->send());
    }
}
