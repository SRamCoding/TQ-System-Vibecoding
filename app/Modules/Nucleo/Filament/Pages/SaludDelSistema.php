<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Pages;

use App\Modules\Nucleo\Filament\Clusters\Configuracion;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Salud\ComprobacionSalud;
use App\Modules\Nucleo\Salud\EstadoSalud;
use App\Modules\Nucleo\Salud\RegistroSalud;
use App\Modules\Nucleo\Salud\ResultadoComprobacion;
use App\Modules\Nucleo\Services\SaludService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Configuración → Salud del sistema (plan 1.12, D2): un recuadro por comprobación de `RegistroSalud` (el núcleo trae
 * programador, respaldos, huérfanos, cola e IA; cada módulo suma los suyos) y el enlace a Pulse. Solo lee. Solo con
 * `salud.ver`. Una comprobación que falla al revisarse se muestra como falla, sin tumbar la pantalla, y su error va al
 * registro solo con su clase (regla 10).
 */
final class SaludDelSistema extends Page
{
    protected static ?string $cluster = Configuracion::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-heart';

    protected static ?string $navigationLabel = 'Salud del sistema';

    protected static ?string $title = 'Salud del sistema';

    protected static ?string $slug = 'salud';

    protected static ?int $navigationSort = 16;

    public static function canAccess(): bool
    {
        $usuario = Filament::auth()->user();

        return $usuario instanceof User && $usuario->can(SaludService::PERMISO_VER);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            // Se revisan antes de dibujar la vista: una excepción dentro de una vista rompe lo que se dibuja después
            // (interfaz.md).
            View::make('nucleo::filament.salud.pantalla')->viewData(fn (): array => ['recuadros' => $this->recuadros()]),
        ]);
    }

    /** @return list<array{clave: string, titulo: string, icono: string, resultado: ResultadoComprobacion}> */
    public function recuadros(): array
    {
        return array_values(array_map(function (ComprobacionSalud $comprobacion): array {
            // Todo lo de la comprobación, dentro del try (revisión de arquitectura de la 1.12): un ícono que no existe o un
            // título que falla tampoco tumban la página.
            try {
                $recuadro = ['clave' => $comprobacion->clave(), 'titulo' => $comprobacion->titulo(), 'icono' => $comprobacion->icono()];
                svg($recuadro['icono']);
                $recuadro['resultado'] = $comprobacion->revisar();
            } catch (Throwable $error) {
                // Solo la comprobación y la clase del error (regla 10): el mensaje de una de otro módulo puede traer datos.
                Log::error('Una comprobación de la página de salud falló al revisarse.', ['comprobacion' => $comprobacion::class, 'error' => $error::class]);
                $recuadro = [
                    'clave' => $recuadro['clave'] ?? $comprobacion::class,
                    'titulo' => $recuadro['titulo'] ?? 'Comprobación',
                    'icono' => 'heroicon-o-exclamation-triangle',
                    'resultado' => new ResultadoComprobacion(EstadoSalud::Falla, 'No se pudo revisar: el error quedó en el registro.'),
                ];
            }

            return $recuadro;
        }, app(RegistroSalud::class)->comprobaciones()));
    }
}
