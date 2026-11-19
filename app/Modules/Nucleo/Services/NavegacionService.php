<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Services;

use App\Modules\Nucleo\Excepciones\PreferenciaRechazada;
use App\Modules\Nucleo\Models\ModuloOculto;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Navegacion\AreaModulo;
use App\Modules\Nucleo\Navegacion\CajaModulo;
use App\Modules\Nucleo\Navegacion\OcultosDelPedido;
use App\Modules\Nucleo\Navegacion\RegistroModulos;
use Illuminate\Support\Facades\DB;

/**
 * Qué módulos ve cada persona, en el inicio y en la barra lateral (plan 1.4f, D3). Único lugar con la regla: el inicio
 * muestra los permitidos; la barra, los permitidos menos los que la persona ocultó. La preferencia nunca da acceso.
 * Las dos preferencias las escriben sus acciones (D4), sin versión: la última escritura gana, como el ancho de la barra.
 */
final class NavegacionService
{
    public function __construct(private readonly RegistroModulos $modulos, private readonly PreferenciasUsuarioService $preferencias) {}

    /**
     * Las cajas cuyo permiso tiene hoy la persona, en el orden del registro.
     *
     * @return list<CajaModulo>
     */
    public function permitidos(User $usuario): array
    {
        return array_values(array_filter(
            $this->modulos->todas(),
            fn (CajaModulo $caja): bool => $caja->permiso === null || $usuario->can($caja->permiso),
        ));
    }

    /**
     * Las claves que la persona sacó de su barra y que todavía puede abrir: un oculto de un módulo sin permiso, o de una
     * caja que ya no existe, no cuenta.
     *
     * @return list<string>
     */
    public function ocultos(User $usuario): array
    {
        // Una sola lectura por pedido (plan 1.12, prueba de carga: se leía 5 veces).
        $guardados = app(OcultosDelPedido::class)->de($usuario->id);
        $permitidos = array_map(fn (CajaModulo $caja): string => $caja->clave, $this->permitidos($usuario));

        return array_values(array_intersect($permitidos, $guardados));
    }

    /**
     * Las cajas de la barra lateral: permitidas y no ocultas, en el orden del registro.
     *
     * @return list<CajaModulo>
     */
    public function deLaBarra(User $usuario): array
    {
        $ocultos = $this->ocultos($usuario);

        return array_values(array_filter(
            $this->permitidos($usuario),
            fn (CajaModulo $caja): bool => ! in_array($caja->clave, $ocultos, true),
        ));
    }

    /**
     * Las cajas agrupadas por área, en el orden de `AreaModulo` y, dentro de cada una, en el del registro. Solo las áreas
     * con alguna caja. El inicio y la barra se agrupan con esto: salen en el mismo orden (D5).
     *
     * @param  list<CajaModulo>  $cajas
     * @return list<array{area: AreaModulo, cajas: list<CajaModulo>}>
     */
    public function porArea(array $cajas): array
    {
        $secciones = [];

        foreach (AreaModulo::cases() as $area) {
            $delArea = array_values(array_filter($cajas, fn (CajaModulo $caja): bool => $caja->area === $area));

            if ($delArea !== []) {
                $secciones[] = ['area' => $area, 'cajas' => $delArea];
            }
        }

        return $secciones;
    }

    /** Si la persona prendió la barra de módulos (preferencia `navegacion.barra_modulos`, plan 1.4g). */
    public function barraVisible(User $usuario): bool
    {
        return $this->preferencias->siNo($usuario, 'navegacion.barra_modulos');
    }

    /** El ancho de su barra en píxeles, o `BarraLateral::PLEGADA` (preferencia `navegacion.ancho_barra`). */
    public function anchoBarra(User $usuario): int
    {
        return $this->preferencias->numero($usuario, 'navegacion.ancho_barra');
    }

    /**
     * La URL del módulo al que la persona eligió llegar al iniciar sesión (preferencia `navegacion.pantalla_inicio`,
     * plan 1.4g, D4), o null para el inicio. Un módulo que ya no tiene permitido no es opción válida: el registro lo
     * lee como el defecto, el inicio.
     */
    public function pantallaDeInicio(User $usuario): ?string
    {
        $clave = $this->preferencias->opcion($usuario, 'navegacion.pantalla_inicio');

        foreach ($this->permitidos($usuario) as $caja) {
            if ($caja->clave === $clave) {
                return $caja->url;
            }
        }

        return null;
    }

    public function mostrarBarra(User $usuario, bool $visible): void
    {
        $this->preferencias->cambiar($usuario, 'navegacion.barra_modulos', $visible);
    }

    /**
     * Reemplaza la lista de ocultos (D4). Se guarda lo oculto y no lo elegido: un módulo recién permitido aparece en la
     * barra solo. Cada clave debe ser de un módulo que la persona puede abrir hoy, y al menos uno debe quedar a la vista.
     * Lo que ya no está en la lista (también un oculto de un módulo cuyo permiso se perdió) se borra.
     *
     * @param  list<string>  $ocultos
     *
     * @throws PreferenciaRechazada
     */
    public function ocultar(User $usuario, array $ocultos): void
    {
        $ocultos = array_values(array_unique($ocultos));
        $permitidos = array_map(fn (CajaModulo $caja): string => $caja->clave, $this->permitidos($usuario));

        if (array_diff($ocultos, $permitidos) !== []) {
            throw PreferenciaRechazada::moduloNoDisponible();
        }

        if ($permitidos !== [] && array_diff($permitidos, $ocultos) === []) {
            throw PreferenciaRechazada::barraSinModulos();
        }

        DB::transaction(function () use ($usuario, $ocultos): void {
            // Dos guardados a la vez de la misma persona se ponen en fila: el segundo ve lo que dejó el primero.
            User::query()->whereKey($usuario->id)->lockForUpdate()->first();

            ModuloOculto::query()->where('user_id', $usuario->id)->whereNotIn('clave_modulo', $ocultos)->delete();
            $ya = ModuloOculto::query()->where('user_id', $usuario->id)->pluck('clave_modulo')->all();

            foreach (array_diff($ocultos, $ya) as $clave) {
                ModuloOculto::query()->create(['user_id' => $usuario->id, 'clave_modulo' => $clave]);
            }
        });

        // Después de guardar: una lectura dentro de la transacción no deja guardado lo de antes.
        app(OcultosDelPedido::class)->olvidar($usuario->id);
    }
}
