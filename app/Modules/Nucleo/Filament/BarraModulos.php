<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Navegacion\CajaModulo;
use App\Modules\Nucleo\Services\NavegacionService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Livewire\Component;
use Livewire\Livewire;

/**
 * La barra lateral de módulos (plan 1.4f, D5 a D7): se arma con las cajas, no con la navegación automática de Filament,
 * y se prende, se apaga y se elige desde el menú del avatar al instante, sin recargar.
 *
 * El servidor siempre dibuja todos los permitidos (si hay alguno); los ocultos llevan `tq-oculto` y, con la barra
 * apagada, `<html>` lleva `tq-barra-apagada`: el CSS de la vista `navegacion` los esconde. Al guardar, el navegador
 * recibe el evento `tq-barra-modulos` y aplica lo mismo sin recargar. Qué se ve lo decide NavegacionService.
 */
final class BarraModulos
{
    /** Evento del navegador con lo que hay que mostrar: `visible` (bool) y `ocultos` (claves). */
    public const string EVENTO = 'tq-barra-modulos';

    /** El nombre del ítem del menú de la foto (pedido del desarrollador, 2026-10-23: corto; el interruptor dice el estado). */
    public const string ETIQUETA = 'Barra de módulos';

    /**
     * La navegación del panel: un grupo por área con los permitidos. Sin permitidos, sin barra.
     */
    public static function navegacion(?User $usuario): NavigationBuilder|false
    {
        $navegacion = app(NavegacionService::class);
        $permitidos = $usuario === null ? [] : $navegacion->cajasDeProceso($usuario);

        if ($usuario === null || $permitidos === []) {
            return false;
        }

        $ocultos = $navegacion->ocultos($usuario);
        $grupos = array_map(
            fn (array $seccion): NavigationGroup => NavigationGroup::make($seccion['franja']->etiqueta())
                ->extraSidebarAttributes(['data-tq-area' => $seccion['franja']->areaDeColor()->value])
                ->items(array_map(fn (CajaModulo $caja): NavigationItem => self::item($caja, in_array($caja->clave, $ocultos, true), $usuario), $seccion['cajas'])),
            $navegacion->porFranja($permitidos),
        );

        return (new NavigationBuilder)->groups($grupos);
    }

    /**
     * «Barra de módulos» (D6). Prendida, la apaga sin ventana. Apagada, la primera vez de cada inicio de sesión abre
     * «Elegir módulos» y al aceptar la prende; las siguientes, la prende al instante con lo ya guardado. El ícono dice el
     * estado como un interruptor típico (pedido del desarrollador, 2026-10-27; antes, una palanca de alarma): apagado,
     * gris con la perilla a la izquierda; prendido, en el azul del tema con la perilla blanca a la derecha.
     */
    public static function accionMostrar(): Action
    {
        $prendida = self::barraVisible();

        // MV-049: nadie elige qué módulos ver; la barra muestra siempre lo que su rol permite. Prenderla ya no abre
        // «Elegir módulos».
        return AccionFilament::desde('nucleo.mostrar-barra-modulos', fn (): array => ['visible' => ! $prendida])
            ->label(self::ETIQUETA)
            ->extraAttributes(['class' => 'tq-solo-escritorio tq-interruptor'.($prendida ? ' tq-interruptor-prendido' : '')])
            ->icon($prendida ? 'tq-interruptor-prendido' : 'tq-interruptor-apagado')
            ->hidden(fn (): bool => ! self::hayModulos())
            ->after(fn (Component $livewire) => self::avisar($livewire));
    }

    private static function item(CajaModulo $caja, bool $oculto, User $usuario): NavigationItem
    {
        $ruta = self::ruta($caja->url);

        return NavigationItem::make($caja->nombre)
            ->url($caja->url)
            ->icon($caja->icono)
            // Activo en su portada y en sus pantallas internas (MV-020).
            ->isActiveWhen(fn (): bool => str_starts_with(self::rutaActual().'/', rtrim($ruta, '/').'/')
                || (app(NavegacionService::class)->procesoDeRuta($usuario, self::rutaActual())['caja'] ?? null)?->clave === $caja->clave)
            ->extraAttributes(array_filter([
                'data-modulo' => $caja->clave,
                'data-tq-barra' => true,
                'class' => $oculto ? 'tq-oculto' : null,
            ]));
    }

    /** Le dice al navegador lo que quedó guardado, para mostrarlo sin recargar. */
    private static function avisar(Component $livewire): void
    {
        $usuario = self::usuario();

        if ($usuario === null) {
            return;
        }

        $navegacion = app(NavegacionService::class);
        $livewire->dispatch(self::EVENTO, visible: $navegacion->barraVisible($usuario), ocultos: $navegacion->ocultos($usuario));
        // Filament arma los ítems del menú al empezar la petición: este evento suyo lo redibuja con el ícono al día.
        $livewire->dispatch('refresh-topbar');
    }

    /**
     * Los permitidos en el orden de la barra (por área), para la ventana.
     *
     * @return list<CajaModulo>
     */
    private static function permitidos(): array
    {
        $usuario = self::usuario();
        $navegacion = app(NavegacionService::class);

        return $usuario === null ? [] : $navegacion->cajasDeProceso($usuario);
    }

    /** @return list<string> */
    private static function clavesPermitidas(): array
    {
        return array_map(fn (CajaModulo $caja): string => $caja->clave, self::permitidos());
    }

    /** @return list<string> */
    private static function lista(mixed $valor): array
    {
        return is_array($valor) ? array_values(array_filter($valor, is_string(...))) : [];
    }

    private static function barraVisible(): bool
    {
        $usuario = self::usuario();

        return $usuario !== null && app(NavegacionService::class)->barraVisible($usuario);
    }

    private static function hayModulos(): bool
    {
        return self::permitidos() !== [];
    }

    /**
     * La ruta de la página que se está viendo, también en una petición de Livewire (que va a otra URL): así una pieza que
     * Livewire redibuja (la barra superior tras `refresh-topbar`) sigue sabiendo dónde está la persona.
     */
    public static function rutaActual(): string
    {
        return self::ruta(Livewire::originalUrl());
    }

    /** La casa del celular (D10): en cualquier pantalla menos el inicio. */
    public static function irAlInicio(): string
    {
        $inicio = Filament::getHomeUrl() ?? '/';

        return self::rutaActual() === self::ruta($inicio) ? '' : view('nucleo::filament.ir-al-inicio', ['url' => $inicio])->render();
    }

    private static function ruta(string $url): string
    {
        $ruta = parse_url($url, PHP_URL_PATH);

        return '/'.trim(is_string($ruta) ? $ruta : '', '/');
    }

    private static function usuario(): ?User
    {
        $usuario = Filament::auth()->user();

        return $usuario instanceof User ? $usuario : null;
    }
}
