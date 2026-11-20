<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Services;

use App\Modules\Nucleo\Excepciones\PreferenciaRechazada;
use App\Modules\Nucleo\Models\ModuloOculto;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Navegacion\AreaModulo;
use App\Modules\Nucleo\Navegacion\CajaModulo;
use App\Modules\Nucleo\Navegacion\Franja;
use App\Modules\Nucleo\Navegacion\OcultosDelPedido;
use App\Modules\Nucleo\Navegacion\Proceso;
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
        $permitidos = array_map(fn (CajaModulo $caja): string => $caja->clave, $this->cajasDeProceso($usuario));

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
            $this->cajasDeProceso($usuario),
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

    /**
     * Los procesos que la persona ve (MV-016): los que tienen al menos una caja interna permitida, en el orden de
     * `Proceso`, cada uno con su caja del inicio y sus cajas internas (con el nombre que les da el proceso).
     *
     * @return list<array{proceso: Proceso, caja: CajaModulo, internas: list<CajaModulo>}>
     */
    public function procesos(User $usuario): array
    {
        $permitidas = [];

        foreach ($this->permitidos($usuario) as $caja) {
            $permitidas[$caja->clave] = $caja;
        }

        $procesos = [];

        foreach (Proceso::cases() as $proceso) {
            $internas = [];

            foreach ($proceso->cajas() as $clave => $nombre) {
                if (isset($permitidas[$clave])) {
                    $internas[] = $permitidas[$clave]->conNombre($nombre);
                }
            }

            if ($internas !== []) {
                $procesos[] = ['proceso' => $proceso, 'caja' => $this->cajaDeProceso($proceso, $internas), 'internas' => $internas];
            }
        }

        return $procesos;
    }

    /**
     * Las cajas de proceso que la persona ve, Mien primero: lo que muestran el inicio y la barra lateral.
     *
     * @return list<CajaModulo>
     */
    public function cajasDeProceso(User $usuario): array
    {
        return array_column($this->procesos($usuario), 'caja');
    }

    /**
     * El proceso y la caja interna de una ruta (`/ordenes/12` es Órdenes, de Abastecer), para las pestañas y la miga
     * (MV-020). Gana la URL más larga que sea prefijo.
     *
     * @return array{proceso: Proceso, caja: CajaModulo, internas: list<CajaModulo>, actual: CajaModulo}|null
     */
    public function procesoDeRuta(User $usuario, string $ruta): ?array
    {
        $ruta = '/'.trim($ruta, '/').'/';
        $mejor = null;
        $largo = 0;

        foreach ($this->procesos($usuario) as $proceso) {
            foreach ($proceso['internas'] as $interna) {
                $url = '/'.trim((string) parse_url($interna->url, PHP_URL_PATH), '/').'/';

                if (str_starts_with($ruta, $url) && strlen($url) > $largo) {
                    $mejor = [...$proceso, 'actual' => $interna];
                    $largo = strlen($url);
                }
            }
        }

        return $mejor;
    }

    /**
     * Cajas de proceso agrupadas por franja, en el orden de `Franja`; solo las franjas con alguna caja (MV-016: una franja
     * vacía desaparece con su título).
     *
     * @param  list<CajaModulo>  $cajas
     * @return list<array{franja: Franja, cajas: list<CajaModulo>}>
     */
    public function porFranja(array $cajas): array
    {
        $secciones = [];

        foreach (Franja::cases() as $franja) {
            $deLaFranja = array_values(array_filter($cajas, fn (CajaModulo $caja): bool => $caja->franja === $franja));

            if ($deLaFranja !== []) {
                $secciones[] = ['franja' => $franja, 'cajas' => $deLaFranja];
            }
        }

        return $secciones;
    }

    /**
     * Reparte las franjas en dos columnas de alto parecido (MV-018), cada franja entera. El alto de una franja se estima
     * como su título más sus filas de tres cajas; de los repartos posibles gana el de menor diferencia y, a igual
     * diferencia, el que deja las franjas en su orden (las primeras a la izquierda).
     *
     * @template T of array{cajas: list<CajaModulo>}
     *
     * @param  list<T>  $secciones
     * @return array{0: list<T>, 1: list<T>}
     */
    public function enDosColumnas(array $secciones): array
    {
        $n = count($secciones);
        $altos = array_map(fn (array $s): float => 1.3 + ceil(count($s['cajas']) / 3), $secciones);
        $mejor = null;
        $puntaje = INF;

        // Bit en 1: va a la derecha. La primera franja siempre a la izquierda (el espejo es el mismo reparto).
        for ($mascara = 0; $mascara < (1 << max($n - 1, 0)); $mascara++) {
            $izquierda = $derecha = 0.0;
            $enOrden = true;
            $yaDerecha = false;

            for ($i = 0; $i < $n; $i++) {
                $aLaDerecha = $i > 0 && (($mascara >> ($i - 1)) & 1) === 1;
                $aLaDerecha ? $derecha += $altos[$i] : $izquierda += $altos[$i];
                $enOrden = $enOrden && ! ($yaDerecha && ! $aLaDerecha);
                $yaDerecha = $yaDerecha || $aLaDerecha;
            }

            $valor = abs($izquierda - $derecha) * 10 + ($enOrden ? 0 : 1);

            if ($valor < $puntaje) {
                $puntaje = $valor;
                $mejor = $mascara;
            }
        }

        $columnas = [[], []];

        foreach ($secciones as $i => $seccion) {
            $columnas[$i > 0 && ((($mejor ?? 0) >> ($i - 1)) & 1) === 1 ? 1 : 0][] = $seccion;
        }

        return $columnas;
    }

    /** @param  list<CajaModulo>  $internas */
    private function cajaDeProceso(Proceso $proceso, array $internas): CajaModulo
    {
        if ($proceso === Proceso::Mien) {
            $mien = $internas[0];

            return new CajaModulo($mien->clave, $mien->nombre, $mien->url, null, $mien->area, $mien->icono, $proceso->descripcion(), $mien->preguntas, $mien->animacion, Franja::Personal);
        }

        // Las preguntas del proceso: la primera de cada caja interna, hasta tres.
        $preguntas = array_slice(array_values(array_unique(array_filter(array_map(fn (CajaModulo $c): ?string => $c->preguntas[0] ?? null, $internas)))), 0, 3);

        return new CajaModulo(
            $proceso->value, $proceso->nombre(), $proceso->url(), permiso: null, area: $proceso->franja()->areaDeColor(),
            icono: $proceso->icono(), descripcion: $proceso->descripcion(), preguntas: $preguntas, animacion: $proceso->animacion(), franja: $proceso->franja(),
        );
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

        foreach ([...$this->cajasDeProceso($usuario), ...$this->permitidos($usuario)] as $caja) {
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
        $permitidos = array_map(fn (CajaModulo $caja): string => $caja->clave, $this->cajasDeProceso($usuario));

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
