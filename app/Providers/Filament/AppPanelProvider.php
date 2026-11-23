<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Modules\Nucleo\Desarrollo\ModoDesarrollo;
use App\Modules\Nucleo\Enums\Ocasion;
use App\Modules\Nucleo\Filament\AvatarIniciales;
use App\Modules\Nucleo\Filament\BarraModulos;
use App\Modules\Nucleo\Filament\BienvenidaConfiguracion;
use App\Modules\Nucleo\Filament\BordeBarraLateral;
use App\Modules\Nucleo\Filament\CampanaAvisos;
use App\Modules\Nucleo\Filament\Desarrollo\BarraDesarrollo;
use App\Modules\Nucleo\Filament\GloboMien;
use App\Modules\Nucleo\Filament\GranMomentoEnPantalla;
use App\Modules\Nucleo\Filament\GuardarTema;
use App\Modules\Nucleo\Filament\Pages\Inicio;
use App\Modules\Nucleo\Filament\Pages\MisDatos;
use App\Modules\Nucleo\Filament\Pages\MisPreferencias;
use App\Modules\Nucleo\Filament\PanelAplicaciones;
use App\Modules\Nucleo\Http\Controllers\DescargarAdjunto;
use App\Modules\Nucleo\Http\Controllers\MostrarFoto;
use App\Modules\Nucleo\Http\Controllers\MostrarImagen;
use App\Modules\Nucleo\Http\ExigirClaveRenovada;
use App\Modules\Nucleo\Http\ExigirGerenteGeneral;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Navegacion\BarraLateral;
use App\Modules\Nucleo\Navegacion\ClimaDelEntorno;
use App\Modules\Nucleo\Navegacion\ColoresMarca;
use App\Modules\Nucleo\Navegacion\RegistroPantallas;
use App\Modules\Nucleo\Saludo\Ambientacion;
use App\Modules\Nucleo\Saludo\LogoTechquk;
use App\Modules\Nucleo\Services\ConfiguracionInicialService;
use App\Modules\Nucleo\Services\GranMomentoService;
use App\Modules\Nucleo\Services\NavegacionService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationBuilder;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Livewire\Livewire;

/**
 * Única aplicación, un solo modo de navegación (ARCHITECTURE.md, «Interfaz»; plan 1.4f). Las pantallas las declara
 * cada módulo en RegistroPantallas para el panel `app`; aquí no se descubre nada.
 */
final class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $pantallas = app(RegistroPantallas::class);

        return $panel
            ->default()
            ->id('app')
            // Plan 1.4o: las iniciales de quien no tiene foto se dibujan aquí; el proveedor de Filament mandaba el nombre a
            // ui-avatars.com.
            ->defaultAvatarProvider(AvatarIniciales::class)
            ->path('')
            ->login()
            // Una cuenta nueva nace con clave aleatoria: la persona pone la suya desde aquí (plan 1.4, D4).
            ->passwordReset()
            ->brandName('TechQuk')
            // Plan 1.4b, D9: la T de Techquk con su animación de construcción, junto al nombre.
            ->brandLogo(fn (): HtmlString => new HtmlString($this->logo()))
            ->brandLogoHeight('2rem')
            // El ícono de la pestaña: la T y la Q de Techquk en tres dimensiones, imagen del desarrollador (2026-10-23).
            // `favicon.ico` es su respaldo de 16, 32 y 48 px; `apple-touch-icon.png`, el de iPhone.
            ->favicon(asset('favicon.png'))
            // Identidad visual de Techquk: los colores del logo (ADR 0017, docs/convenciones/interfaz.md).
            ->colors([
                'primary' => ColoresMarca::paletaPrincipal(),
                'info' => Color::hex(ColoresMarca::TURQUESA),
                'morado' => ColoresMarca::paletaMorada(),
                'dorado' => ColoresMarca::paletaDorada(),
            ])
            ->pages([
                Inicio::class,
                ...$pantallas->paginas('app'),
            ])
            ->resources($pantallas->recursos('app'))
            // Plan 1.4f, D5: la barra lateral se arma con las cajas permitidas; apagada u oculta, la esconde el CSS.
            ->navigation(fn (): NavigationBuilder|false => BarraModulos::navegacion($this->usuario()))
            // Plan 1.3d, D5 y D6: el ancho que guardó cada persona; plegada, solo íconos (el plegado de Filament).
            // MV-037: cambiar de pantalla sin recargar la página entera (Livewire navigate); lo que debe seguir vivo
            // (el muelle, el asistente, el cielo, la ambientación) se conserva con `x-persist`.
            ->spa()
            ->sidebarCollapsibleOnDesktop()
            ->sidebarWidth(fn (): string => $this->anchoBarraLateral().'px')
            ->renderHook(PanelsRenderHook::SIDEBAR_START, fn (): string => Livewire::mount(BordeBarraLateral::class))
            // Plan 1.4g, D4 y D5: tema, letra, densidad y animaciones de la persona, antes de dibujar; el tema elegido con
            // el selector de Filament se guarda en la base.
            // En «Sistema» manda el cielo: responde `prefers-color-scheme` antes de que Filament decida (observación 7.7).
            ->renderHook(PanelsRenderHook::HEAD_START, fn (): string => rescue(fn (): string => view('nucleo::filament.cielo-arranque')->render(), ''))
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => view('nucleo::filament.apariencia')->render())
            // MVP rápido (MV-012): en el tema «Sistema», la pantalla sigue el cielo (en curso).
            ->renderHook(PanelsRenderHook::BODY_END, fn (): string => self::persistir('tq-cielo', view('nucleo::filament.cielo')->render()))
            // MV-043: al cambiar de pantalla, una carga de energía recorre la franja bajo la barra de arriba.
            ->renderHook(PanelsRenderHook::BODY_END, fn (): string => Filament::auth()->check() ? self::persistir('tq-energia', view('nucleo::filament.carga-energia')->render()) : '')
            // MV-052: el globo de Mien que abre la estrella de la barra lateral.
            ->renderHook(PanelsRenderHook::BODY_END, fn (): string => Filament::auth()->check() ? self::persistir('tq-globo-mien', Livewire::mount(GloboMien::class)) : '')
            // MVP rápido (op-asistente): el asistente de ayuda «n_n».
            ->renderHook(PanelsRenderHook::BODY_END, fn (): string => self::persistir('tq-asistente', view('nucleo::filament.asistente')->render()))
            // MVP rápido (op-gran-momento): la ambientación de pantalla completa de Navidad y Año Nuevo.
            ->renderHook(PanelsRenderHook::BODY_END, fn (): string => self::persistir('tq-ambientacion', view('nucleo::filament.ambientacion-festiva')->render()))
            ->renderHook(PanelsRenderHook::BODY_END, fn (): string => Filament::auth()->check() ? self::persistir('tq-guardar-tema', Livewire::mount(GuardarTema::class)) : '')
            // El panel de aplicaciones, en todas las pantallas (plan 1.4p, D1). Si falla, la pantalla sale igual, sin él.
            ->renderHook(PanelsRenderHook::BODY_END, fn (): string => Filament::auth()->check() ? self::persistir('tq-muelle', rescue(fn (): string => Livewire::mount(PanelAplicaciones::class), '')) : '')
            // Plan 1.4d, D5: el gran momento de la fecha, solo si a la persona le toca uno. Es decoración: si falla, nada.
            ->renderHook(PanelsRenderHook::BODY_END, fn (): string => rescue(fn (): string => $this->granMomento(), ''))
            // Plan 1.13b, D6: la bienvenida de la configuración inicial, en la primera pantalla tras iniciar sesión. Si
            // falla, la pantalla sale igual, sin ella: la configuración sigue en su página.
            ->renderHook(PanelsRenderHook::BODY_END, fn (): string => rescue(fn (): string => $this->bienvenida(), ''))
            // Plan 1.4i, D5 y D7: la barra del modo desarrollo, solo con el modo prendido.
            ->renderHook(PanelsRenderHook::BODY_END, fn (): string => ModoDesarrollo::activo() ? Livewire::mount(BarraDesarrollo::class) : '')
            // Plan 1.3d: fondo propio de la barra superior y estado guardado de la barra lateral.
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => view('nucleo::filament.navegacion')->render())
            // MV-041: al filtrar u ordenar, las filas de toda tabla o lista se mueven en vez de cambiar de golpe.
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => view('nucleo::filament.animacion-tablas')->render())
            // MV-044: al navegar, lo que navega se queda quieto y solo se desliza la marca de lo elegido.
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => view('nucleo::filament.navegacion-interna')->render())
            // MV-035: arriba de cada pantalla de un proceso, su barra de cajas y su frase.
            ->renderHook(PanelsRenderHook::PAGE_START, fn (): string => rescue(fn (): string => ($u = $this->usuario()) !== null && ($datos = app(NavegacionService::class)->procesoDeRuta($u, BarraModulos::rutaActual())) !== null ? view('nucleo::filament.procesos.pestanas', ['datos' => $datos])->render() : '', ''))
            // Plan 1.4ab: la escala de letra del prototipo de Proyectos y la línea fina bajo la barra superior.
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => view('nucleo::filament.tipografia')->render())
            // Plan 1.4u, D1: el clima del lugar de la computadora, en todas las pantallas. Es decoración: si falla, nada. Los
            // datos se piden antes de dibujar: una excepción dentro de una vista limpia la pila de componentes de la página.
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => rescue(fn (): string => ($datos = ClimaDelEntorno::datos(Filament::auth()->user())) === null ? '' : view('nucleo::filament.clima', ['datos' => $datos])->render(), ''))
            // Botones más visibles en modo oscuro: el tono 600 de primary, más luminoso solo ahí (ColoresMarca).
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => '<style>html.dark { --primary-600: '.ColoresMarca::principalModoOscuro().'; }</style>')
            // MV-059: todos los colores siguen al ambiente (superficies del fondo, franjas con temperatura, vidrio en lo que flota).
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => rescue(fn (): string => view('nucleo::filament.colores-ambiente')->render(), ''))
            // Ambientación de la fecha (plan 1.4b, D8): estilos y franja bajo la barra superior. El logo cambia en pocas fechas (D9).
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => rescue(fn (): string => view('nucleo::filament.ambientacion.estilos')->render(), ''))
            ->renderHook(PanelsRenderHook::TOPBAR_AFTER, fn (): string => $this->ambientacion('nucleo::filament.ambientacion.franja'))
            // Plan 1.4t: en otoño, hojas que caen cada tanto dentro de la barra superior. Es decoración: si falla, nada.
            ->renderHook(PanelsRenderHook::TOPBAR_START, fn (): string => rescue(fn (): string => view('nucleo::filament.barra-estacion')->render(), ''))
            // Plan 1.4f, D10: en el celular, una casa a la izquierda del logo para volver al inicio.
            ->renderHook(PanelsRenderHook::TOPBAR_LOGO_BEFORE, fn (): string => BarraModulos::irAlInicio())
            // Plan 1.4b, D10: en el aniversario, el agradecimiento a Dios por los años, entre el logo y el buscador.
            ->renderHook(PanelsRenderHook::TOPBAR_LOGO_AFTER, fn (): string => $this->agradecimiento())
            ->userMenuItems([
                // Plan 1.4f, D6: la barra se prende, se apaga y se elige al instante, sin recargar.
                'barra-modulos' => fn (): Action => BarraModulos::accionMostrar(),
                // Plan 1.4h, D4: los datos de la cuenta (cumpleaños, teléfono, contraseña); reemplaza a «Mi cumpleaños».
                'mis-datos' => fn (): Action => Action::make('misDatos')->label('Mis datos')
                    ->icon('heroicon-o-identification')->url(fn (): string => MisDatos::getUrl()),
                // «Mis avisos» ya no va aquí (plan 1.4aa, D2): se llega desde la campana, con «Ver todos mis avisos».
                // Plan 1.4g, D6: todas las preferencias en una pantalla.
                'mis-preferencias' => fn (): Action => Action::make('misPreferencias')->label('Mis preferencias')
                    ->icon('heroicon-o-adjustments-vertical')->url(fn (): string => MisPreferencias::getUrl()),
                // Plan 1.4i: solo en el equipo del desarrollador, con las tres llaves (D7); en cualquier otro lado no se ve.
                'modo-desarrollo' => fn (): Action => BarraDesarrollo::accionDelMenu(),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            // Persistentes: Livewire las vuelve a aplicar en sus peticiones (si no, una pantalla ya abierta seguiría
            // funcionando para quien debe renovar su contraseña).
            // Plan 1.8, D6: la campana de avisos (de `notificaciones`, por la relación de User), al día cada 30 segundos.
            // Plan 1.4y, D1: la nuestra, solo para leer (sin borrar) y con «Ver todos mis avisos».
            ->databaseNotifications(livewireComponent: CampanaAvisos::class)
            ->databaseNotificationsPolling('30s')
            // Plan 1.8, D1: la única puerta a un adjunto, con sesión y revisando permisos.
            ->authenticatedRoutes(function (): void {
                Route::get('adjuntos/{adjunto}', DescargarAdjunto::class)->whereNumber('adjunto')->name('adjuntos.descargar');
                // Plan 1.4o, D4: la foto de perfil, también con sesión.
                Route::get('fotos/{usuario}', MostrarFoto::class)->whereNumber('usuario')->name('foto');
                // Plan 2.61: las imágenes de los módulos (la foto de un proyecto), con sesión (RegistroImagenes).
                Route::get('imagenes/{clave}/{id}', MostrarImagen::class)->where('clave', '[a-z][a-z0-9-]*')->whereNumber('id')->name('imagen');
            })
            ->authMiddleware([
                Authenticate::class,
                // Plan 1.4h, D5: quien debe renovar su contraseña no usa nada más.
                ExigirClaveRenovada::class,
                // Plan 1.10, D2: sin gerente general activo, el sistema no arranca.
                ExigirGerenteGeneral::class,
            ], isPersistent: true);
    }

    /** El agradecimiento del aniversario, o nada. Es decoración: si falla, no sale (D8). */
    private function agradecimiento(): string
    {
        return rescue(function (): string {
            $texto = app(Ambientacion::class)->agradecimiento($this->usuario(), ModoDesarrollo::ahoraDecorativo());

            return $texto === null ? '' : view('nucleo::filament.agradecimiento', ['texto' => $texto])->render();
        }, '');
    }

    /**
     * El logo con sus colores del día; si algo falla, el de siempre (es decoración, D8). Adentro, anima al cargar solo
     * en la primera pantalla tras el ingreso; el resto de los casos de D1 (actualizar, cambiar de modo, el mouse) los
     * decide el navegador. Sin sesión iniciada (el ingreso), hace la espera (D2).
     */
    private function logo(): string
    {
        $recienIngresa = $this->recienIngresa();
        $ocasion = rescue(fn (): ?Ocasion => app(Ambientacion::class)->ocasionDelLogo($this->usuario(), ModoDesarrollo::ahoraDecorativo()));

        // Si el dibujo falla, queda el nombre: una pantalla nunca se cae por la decoración.
        return rescue(
            fn (): string => view('nucleo::filament.logo', ['paleta' => LogoTechquk::paleta($ocasion), 'escombros' => LogoTechquk::escombros(), 'animar' => $recienIngresa, 'espera' => $this->usuario() === null])->render(),
            '<span style="font-weight: 700; font-size: 1.25rem">TechQuk</span>',
        );
    }

    /**
     * Si esta es la primera pantalla tras el ingreso: la marca que dejó `RespuestaDeIngreso` se toma una sola vez, y
     * queda en la petición porque el logo se dibuja más de una vez por página. Sin sesión iniciada no se toca.
     */
    private function recienIngresa(): bool
    {
        $peticion = request();
        if (! $peticion->attributes->has(LogoTechquk::MARCA_INGRESO)) {
            $peticion->attributes->set(LogoTechquk::MARCA_INGRESO, $this->usuario() !== null && (bool) session()->pull(LogoTechquk::MARCA_INGRESO, false));
        }

        return (bool) $peticion->attributes->get(LogoTechquk::MARCA_INGRESO);
    }

    /**
     * La vista de ambientación con la variante del día, o nada si no hay (apagada o sin dibujo). Es decoración: si
     * falla, se registra el error y la pantalla sale sin ella (`rescue`), nunca rota.
     */
    private function ambientacion(string $vista): string
    {
        return rescue(function () use ($vista): string {
            $usuario = $this->usuario();
            $variante = $usuario === null ? null : app(Ambientacion::class)->para($usuario, ModoDesarrollo::ahoraDecorativo());

            return $variante === null ? '' : view($vista, ['variante' => $variante])->render();
        }, '');
    }

    /**
     * El gran momento pendiente de la persona, esperando a que la pestaña se vea; o nada. En modo desarrollo no sale
     * solo (plan 1.4i): elegirlo por la fecha simulada escribiría en la base; se elige desde su barra y se ve sin marcar.
     */
    private function granMomento(): string
    {
        if (ModoDesarrollo::activo()) {
            return '';
        }

        $usuario = $this->usuario();
        $pendiente = $usuario === null ? null : app(GranMomentoService::class)->pendientePara($usuario, Calendario::ahora());

        return $pendiente === null ? '' : Livewire::mount(GranMomentoEnPantalla::class, ['granMomento' => $pendiente->id]);
    }

    /**
     * La bienvenida de la configuración inicial (plan 1.13b, D1 y D6), solo en la primera pantalla tras iniciar sesión
     * (la marca del logo) y si a la persona le toca. No se apilan dos capas: si a este ingreso le toca un gran momento, la
     * bienvenida espera al siguiente.
     */
    private function bienvenida(): string
    {
        $usuario = $this->usuario();

        if ($usuario === null || ! $this->recienIngresa() || ! app(ConfiguracionInicialService::class)->muestraBienvenida($usuario)) {
            return '';
        }

        // En modo desarrollo el gran momento no sale solo (plan 1.4i): no la hace esperar.
        if (! ModoDesarrollo::activo() && app(GranMomentoService::class)->pendientePara($usuario, Calendario::ahora()) !== null) {
            return '';
        }

        return Livewire::mount(BienvenidaConfiguracion::class);
    }

    /** El ancho abierto de la barra lateral; si la persona la plegó, el inicial (al abrirla con el botón vuelve a él). */
    private function anchoBarraLateral(): int
    {
        // MV-050: expandida, siempre el mismo ancho; comprimida, solo íconos (lo decide el estado guardado).
        return BarraLateral::ANCHO_INICIAL;
    }

    private function usuario(): ?User
    {
        $usuario = Filament::auth()->user();

        return $usuario instanceof User ? $usuario : null;
    }

    /** MV-037: lo que sigue vivo al cambiar de pantalla sin recargar (Livewire `x-persist`). */
    private static function persistir(string $nombre, string $html): string
    {
        return $html === '' ? '' : '<div x-persist="'.$nombre.'">'.$html.'</div>';
    }
}
