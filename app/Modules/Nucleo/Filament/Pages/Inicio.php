<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Pages;

use App\Modules\Nucleo\Desarrollo\ModoDesarrollo;
use App\Modules\Nucleo\Filament\Widgets\AvisoConfiguracionInicial;
use App\Modules\Nucleo\Filament\Widgets\CajasModulos;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Saludo\Ambientacion;
use App\Modules\Nucleo\Saludo\GeneradorSaludo;
use App\Modules\Nucleo\Saludo\Saludo;
use App\Modules\Nucleo\Saludo\SemillaSesion;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * Página de inicio: las cajas de los módulos que la persona tiene permitidos (plan 1.4f, D8). En vez de «Inicio», el saludo de
 * la persona con el dibujo de la fecha y, debajo, su frase (plan 1.4b). La pestaña del navegador sigue diciendo «Inicio».
 */
final class Inicio extends Dashboard
{
    protected static ?string $title = 'Inicio';

    private ?Saludo $saludo = null;

    private bool $saludoCalculado = false;

    /**
     * @return array<class-string<Widget> | WidgetConfiguration>
     */
    public function getWidgets(): array
    {
        // Plan 1.13, D6: mientras falte configurar, el aviso va antes que las cajas.
        return [AvisoConfiguracionInicial::class, CajasModulos::class];
    }

    public function getHeading(): string|Htmlable
    {
        $usuario = Filament::auth()->user();
        $saludo = $usuario instanceof User ? $this->saludo($usuario) : null;

        if ($saludo === null) {
            return self::$title ?? 'Inicio';
        }

        return rescue(fn (): Htmlable => new HtmlString(view('nucleo::filament.saludo-inicio', [
            'saludo' => $saludo,
            'variante' => app(Ambientacion::class)->para($usuario, ModoDesarrollo::ahoraDecorativo()),
        ])->render()), $saludo->saludo);
    }

    public function getSubheading(): ?Htmlable
    {
        $usuario = Filament::auth()->user();
        $saludo = $usuario instanceof User ? $this->saludo($usuario) : null;

        if ($saludo?->frase === null) {
            return null;
        }

        $texto = $saludo->fuente === null ? $saludo->frase : "«{$saludo->frase}» — {$saludo->fuente}";

        return new HtmlString('<span class="tq-frase">'.e($texto).'</span>');
    }

    /**
     * Se calcula una vez por petición: el encabezado y la frase lo piden. Es decoración: si falla, se registra el
     * error y el inicio dice «Inicio» (`rescue`), nunca se rompe.
     */
    private function saludo(User $usuario): ?Saludo
    {
        if (! $this->saludoCalculado) {
            $this->saludo = rescue(fn (): Saludo => app(GeneradorSaludo::class)->para($usuario, ModoDesarrollo::ahoraDecorativo(), SemillaSesion::actual()));
            $this->saludoCalculado = true;
        }

        return $this->saludo;
    }

    public function getColumns(): int
    {
        return 1;
    }
}
