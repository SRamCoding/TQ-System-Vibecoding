<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament;

use App\Modules\Nucleo\Excepciones\ErrorDeNegocio;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Navegacion\CajaModulo;
use App\Modules\Nucleo\Navegacion\EleccionDeModulos;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\Services\NavegacionService;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
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
        $permitidos = $usuario === null ? [] : $navegacion->permitidos($usuario);

        if ($usuario === null || $permitidos === []) {
            return false;
        }

        $ocultos = $navegacion->ocultos($usuario);
        $grupos = array_map(
            fn (array $seccion): NavigationGroup => NavigationGroup::make($seccion['area']->etiqueta())
                ->extraSidebarAttributes(['data-tq-area' => $seccion['area']->value])
                ->items(array_map(fn (CajaModulo $caja): NavigationItem => self::item($caja, in_array($caja->clave, $ocultos, true)), $seccion['cajas'])),
            $navegacion->porArea($permitidos),
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

        if ($prendida || app(EleccionDeModulos::class)->yaSeAcepto()) {
            return AccionFilament::desde('nucleo.mostrar-barra-modulos', fn (): array => ['visible' => ! $prendida])
                ->label(self::ETIQUETA)
                ->extraAttributes(['class' => 'tq-solo-escritorio tq-interruptor'.($prendida ? ' tq-interruptor-prendido' : '')])
                ->icon($prendida ? 'tq-interruptor-prendido' : 'tq-interruptor-apagado')
                ->hidden(fn (): bool => ! self::hayModulos())
                ->after(fn (Component $livewire) => self::avisar($livewire));
        }

        return self::ventanaElegir(alAceptar: fn () => self::prender())
            ->name('prenderBarraModulos')
            ->label(self::ETIQUETA)
            ->extraAttributes(['class' => 'tq-solo-escritorio tq-interruptor'])
            ->icon('tq-interruptor-apagado')
            ->modalHeading('Elegir módulos')
            ->modalSubmitActionLabel('Aceptar');
    }

    /** «Elegir módulos…» (D6), con la barra prendida: aplica al instante. */
    public static function accionElegir(): Action
    {
        return self::ventanaElegir()
            ->label('Elegir módulos…')
            ->extraAttributes(['class' => 'tq-solo-escritorio'])
            ->icon('heroicon-o-adjustments-horizontal')
            ->modalHeading('Elegir módulos')
            ->hidden(fn (): bool => ! self::barraVisible() || ! self::hayModulos());
    }

    private static function item(CajaModulo $caja, bool $oculto): NavigationItem
    {
        $ruta = self::ruta($caja->url);

        return NavigationItem::make($caja->nombre)
            ->url($caja->url)
            ->icon($caja->icono)
            // Activo también en sus pantallas internas: la URL de la caja es el prefijo.
            ->isActiveWhen(fn (): bool => str_starts_with(self::rutaActual().'/', rtrim($ruta, '/').'/'))
            ->extraAttributes(array_filter([
                'data-modulo' => $caja->clave,
                'data-tq-barra' => true,
                'class' => $oculto ? 'tq-oculto' : null,
            ]));
    }

    /**
     * La ventana con los permitidos marcados salvo los ocultos. La acción guarda lo oculto (D4): lo desmarcado de lo que
     * se mostró **al abrir** (`mostrados`), no de lo permitido al guardar: un módulo que se permite con la ventana
     * abierta no se oculta por accidente. Cancelar o cerrarla no guarda nada.
     */
    private static function ventanaElegir(?Closure $alAceptar = null): Action
    {
        return AccionFilament::desde('nucleo.elegir-modulos', alTerminar: $alAceptar, controles: [
            'ocultos' => fn (): CheckboxList => CheckboxList::make('ocultos')
                ->hiddenLabel()
                ->options(fn (Get $get): array => array_column(array_map(
                    fn (CajaModulo $caja): array => [$caja->clave, $caja->nombre],
                    array_values(array_filter(self::permitidos(), fn (CajaModulo $caja): bool => in_array($caja->clave, self::lista($get('mostrados')), true))),
                ), 1, 0))
                // En pantalla, lo marcado es lo que se ve; a la acción le llega lo desmarcado.
                ->formatStateUsing(fn (mixed $state): array => array_values(array_diff(self::clavesPermitidas(), self::lista($state))))
                ->dehydrateStateUsing(fn (mixed $state, Get $get): array => array_values(array_diff(self::lista($get('mostrados')), self::lista($state)))),
        ], deApoyo: [fn (): Hidden => Hidden::make('mostrados')])
            ->modalDescription('Marca los módulos que quieres ver en tu barra.')
            ->fillForm(fn (): array => [
                'ocultos' => ($usuario = self::usuario()) === null ? [] : app(NavegacionService::class)->ocultos($usuario),
                'mostrados' => self::clavesPermitidas(),
            ])
            ->hidden(fn (): bool => ! self::hayModulos())
            ->after(fn (Component $livewire) => self::avisar($livewire));
    }

    /**
     * Segunda mitad de la ventana al prender: ya guardada la elección, prende la barra. Solo entonces cuenta como
     * aceptada en este inicio de sesión: cancelar, cerrar o un rechazo la dejan para la próxima vez. Son dos acciones
     * registradas, cada una en su transacción (D6): si esta fallara, la elección queda guardada y la barra apagada, y se
     * avisa. Aceptado: son preferencias propias, no un documento, y prenderla otra vez lo completa.
     */
    private static function prender(): void
    {
        $usuario = self::usuario();

        if ($usuario === null) {
            return;
        }

        try {
            app(EjecutorAcciones::class)->ejecutar('nucleo.mostrar-barra-modulos', ['visible' => true], $usuario);
        } catch (ErrorDeNegocio $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            return;
        }

        app(EleccionDeModulos::class)->marcarAceptada();
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

        return $usuario === null ? [] : array_merge(...array_map(fn (array $seccion): array => $seccion['cajas'], $navegacion->porArea($navegacion->permitidos($usuario))));
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
