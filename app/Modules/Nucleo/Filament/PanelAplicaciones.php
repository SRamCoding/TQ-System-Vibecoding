<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament;

use App\Modules\Nucleo\Excepciones\ErrorDeNegocio;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\Services\AplicacionesService;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Renderless;
use Livewire\Component;

/**
 * El panel de aplicaciones, en todas las pantallas (plan 1.4p, D1 a D9): muelle con las 3 más usadas, cuadrícula con
 * todas o cerrado en un botón redondo. El estado vive en la sesión (D3): sigue al ir de una pantalla a otra y vuelve
 * al muelle en cada ingreso (lo borra `olvidarEstado`, al iniciar sesión). Es estado de la pantalla, no un dato: no
 * pasa por una acción. Lo demás (agregar, ocultar, abrir…) sí: cada cosa con su acción registrada.
 */
final class PanelAplicaciones extends Component
{
    public const string SESION = 'panel_aplicaciones';

    /** Cerrado, al presionar el botón vuelve al último abierto (D2). */
    public const array ESTADOS = ['muelle', 'cuadricula', 'cerrado'];

    public static function olvidarEstado(): void
    {
        session()->forget(self::SESION);
    }

    #[Renderless]
    public function guardarEstado(mixed $estado, mixed $abierto): void
    {
        if (in_array($estado, self::ESTADOS, true) && in_array($abierto, ['muelle', 'cuadricula'], true)) {
            session()->put(self::SESION, ['estado' => $estado, 'abierto' => $abierto]);
        }
    }

    /** La persona abrió una aplicación (D5): suma un uso. Si no se pudo, la aplicación se abre igual, sin aviso. */
    #[Renderless]
    public function abrir(mixed $origen, mixed $id): void
    {
        $this->ejecutar('registrar-uso-aplicacion', ['origen' => $origen, 'aplicacion_id' => $id]);
    }

    /** Plan 1.4x, D2: arrastrarla sobre uno de los 3 lugares, o «Poner arriba» (el 3.º). */
    public function ponerPrincipal(mixed $posicion, mixed $origen, mixed $id): ?string
    {
        return $this->ejecutar('poner-aplicacion-principal', ['posicion' => $posicion, 'origen' => $origen, 'aplicacion_id' => $id]);
    }

    /** Plan 1.4z, D10: soltar una sobre otra de su misma sección; cambian de lugar. */
    public function mover(mixed $origen, mixed $id, mixed $sobreId): ?string
    {
        return $this->ejecutar('mover-aplicacion', ['origen' => $origen, 'aplicacion_id' => $id, 'sobre_id' => $sobreId]);
    }

    /** Plan 1.4z, D6: abrirla en su programa de escritorio o en la web. */
    public function abrirEn(mixed $origen, mixed $id, mixed $enEscritorio): ?string
    {
        return $this->ejecutar('elegir-donde-abrir-aplicacion', ['origen' => $origen, 'aplicacion_id' => $id, 'en_escritorio' => $enEscritorio]);
    }

    public function agregar(mixed $nombre, mixed $enlace, mixed $icono, mixed $escritorio = null): ?string
    {
        return $this->ejecutar('agregar-mi-aplicacion', ['nombre' => $nombre, 'enlace' => $enlace, 'icono' => $icono, 'escritorio' => $escritorio ?: null]);
    }

    public function cambiar(mixed $id, mixed $nombre, mixed $enlace, mixed $icono, mixed $version, mixed $escritorio = null): ?string
    {
        return $this->ejecutar('cambiar-mi-aplicacion', ['aplicacion_id' => $id, 'nombre' => $nombre, 'enlace' => $enlace, 'icono' => $icono, 'version' => $version, 'escritorio' => $escritorio ?: null]);
    }

    public function quitar(mixed $id, mixed $version): ?string
    {
        return $this->ejecutar('quitar-mi-aplicacion', ['aplicacion_id' => $id, 'version' => $version]);
    }

    public function ocultar(mixed $id): ?string
    {
        return $this->ejecutar('ocultar-aplicacion', ['aplicacion_id' => $id]);
    }

    public function mostrar(mixed $id): ?string
    {
        return $this->ejecutar('mostrar-aplicacion', ['aplicacion_id' => $id]);
    }

    public function render(AplicacionesService $aplicaciones): View
    {
        $usuario = Filament::auth()->user();
        $guardado = session(self::SESION);

        return view('nucleo::filament.panel-aplicaciones', [
            'estado' => is_array($guardado) ? $guardado['estado'] : 'muelle',
            'abierto' => is_array($guardado) ? $guardado['abierto'] : 'muelle',
            ...($usuario instanceof User ? $aplicaciones->delPanel($usuario) : ['muelle' => [], 'cuadricula' => ['techquk' => [], 'propias' => []]]),
            'ocultas' => $usuario instanceof User ? $aplicaciones->ocultas($usuario) : [],
        ]);
    }

    /**
     * Ejecuta la acción a nombre de quien tiene la sesión. Devuelve el mensaje para la persona si no procede; nulo si se
     * hizo.
     *
     * @param  array<string, mixed>  $datos
     */
    private function ejecutar(string $accion, array $datos): ?string
    {
        $usuario = Filament::auth()->user();

        if (! $usuario instanceof User) {
            return 'Vuelve a iniciar sesión.';
        }

        try {
            app(EjecutorAcciones::class)->ejecutar("nucleo.{$accion}", $datos, $usuario);

            return null;
        } catch (ValidationException $e) {
            return (string) collect($e->errors())->flatten()->first();
        } catch (ErrorDeNegocio $e) {
            return $e->getMessage();
        }
    }
}
