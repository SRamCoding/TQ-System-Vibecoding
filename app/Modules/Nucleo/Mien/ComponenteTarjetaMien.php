<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Mien;

use App\Modules\Nucleo\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * La base de toda tarjeta de «Mien» (plan 1.4l, D3). Se dibuja aparte, después de abrir la página (carga diferida):
 * una tarjeta lenta no demora las demás. Si algo falla (al montarla, en una acción, al leer o al dibujar), dice «No se
 * pudo cargar», el error va al registro y las demás siguen. Cada tarjeta solo escribe `contenido()`, con datos de los
 * servicios de su propio módulo; su vista se dibuja dentro de Livewire (puede usar `$this`).
 */
abstract class ComponenteTarjetaMien extends Component
{
    #[Locked]
    public string $titulo = '';

    #[Locked]
    public bool $fallida = false;

    abstract protected function contenido(): View;

    /**
     * El permiso se revisa en cada petición, también en la carga diferida y en las acciones: la marca que deja la espera
     * no queda atada a la persona. Reservado a la base: una tarjeta usa `mount()` o `hydrate()`.
     */
    final public function boot(): void
    {
        $registro = app(RegistroTarjetasMien::class);
        $tarjeta = $registro->porNombre($this->getName());
        $usuario = auth()->user();

        abort_unless($tarjeta !== null && $usuario instanceof User && $registro->puedeVer($usuario, $tarjeta), 403);
    }

    /**
     * Livewire la llama ante un error en el montaje, los ganchos o una acción. Los parámetros llevan los nombres que
     * Livewire inyecta (`$e`, `$stopPropagation`): con otro nombre, los pide al contenedor, falla y vuelve a llamarla
     * sin fin.
     */
    public function exception(Throwable $e, callable $stopPropagation): void
    {
        // Un rechazo de permiso sigue su curso: no es una tarjeta rota.
        if ($e instanceof HttpExceptionInterface) {
            return;
        }

        report($e);
        $this->fallida = true;
        $stopPropagation();
    }

    final public function render(): View
    {
        if ($this->fallida) {
            return view('nucleo::filament.mien.tarjeta', ['titulo' => $this->titulo, 'dibujar' => null]);
        }

        try {
            $contenido = $this->contenido();
        } catch (Throwable $error) {
            report($error);
            $contenido = null;
        }

        // La vista de la tarjeta la dibuja `tarjeta.blade.php`, dentro del ciclo de Livewire, y atrapa su error. Va en una
        // función: una vista pasada como dato, Laravel la dibuja antes, fuera de ese `try`.
        return view('nucleo::filament.mien.tarjeta', [
            'titulo' => $this->titulo,
            'dibujar' => $contenido === null ? null : fn (): string => $contenido->render(),
        ]);
    }

    public function placeholder(): View
    {
        return view('nucleo::filament.mien.tarjeta-cargando', ['titulo' => $this->titulo]);
    }
}
