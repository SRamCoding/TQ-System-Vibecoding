<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Widgets;

use App\Modules\Nucleo\Enums\Temporada;
use App\Modules\Nucleo\Mien\RegistroPendientesMien;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Navegacion\CajaModulo;
use App\Modules\Nucleo\Navegacion\EstacionDeLaApariencia;
use App\Modules\Nucleo\Navegacion\Franja;
use App\Modules\Nucleo\Services\NavegacionService;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * Inicio (MV-016 a MV-018): Mien arriba y a lo ancho; debajo, las cajas de proceso agrupadas en franjas (Estratégicos,
 * Misionales, De Apoyo, Plataforma) y repartidas en dos columnas. Cada caja abre la portada de su proceso.
 */
final class CajasModulos extends Widget
{
    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    /**
     * @var view-string
     */
    protected string $view = 'nucleo::filament.widgets.cajas-modulos';

    /**
     * Las cajas de proceso que la persona ve (MV-016), Mien primero.
     *
     * @return list<CajaModulo>
     */
    public function cajas(): array
    {
        $usuario = Filament::auth()->user();

        return $usuario instanceof User ? app(NavegacionService::class)->cajasDeProceso($usuario) : [];
    }

    /** Mien, arriba de todo y a lo ancho (MV-017). */
    public function mien(): ?CajaModulo
    {
        foreach ($this->cajas() as $caja) {
            if ($caja->franja === Franja::Personal) {
                return $caja;
            }
        }

        return null;
    }

    /**
     * Cuántas cosas esperan a la persona, para el número de la estrella de Mien (MV-032).
     *
     * @return array{total: int, resumen: string}
     */
    public function pendientes(): array
    {
        $usuario = Filament::auth()->user();

        return $usuario instanceof User ? app(RegistroPendientesMien::class)->de($usuario) : ['total' => 0, 'resumen' => 'Todo al día'];
    }

    /**
     * Las franjas con alguna caja, repartidas en dos columnas de alto parecido (MV-018). Cada una lleva su lugar en el
     * orden de siempre, para el celular (una sola columna).
     *
     * @return array{0: list<array{franja: Franja, cajas: list<CajaModulo>, orden: int}>, 1: list<array{franja: Franja, cajas: list<CajaModulo>, orden: int}>}
     */
    public function columnas(): array
    {
        $navegacion = app(NavegacionService::class);
        $secciones = array_values(array_filter($navegacion->porFranja($this->cajas()), fn (array $s): bool => $s['franja'] !== Franja::Personal));

        foreach ($secciones as $i => $seccion) {
            $secciones[$i]['orden'] = $i;
        }

        return $navegacion->enDosColumnas($secciones);
    }

    /** Plan 1.4r: la estación que visten las cajas, o null con la preferencia apagada (las cajas como antes). */
    public function estacion(): ?Temporada
    {
        $usuario = Filament::auth()->user();

        return $usuario instanceof User ? EstacionDeLaApariencia::para($usuario) : null;
    }
}
