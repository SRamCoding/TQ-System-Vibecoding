<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cuentas\Actividad;

use App\Modules\Crm\Cuentas\Models\Cuenta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Lo que otros módulos dicen de una cuenta (plan 2.20, D9 y D11). Es un registro: cada módulo suma su aporte cuando
 * exista. Sin aportes es la versión vacía, y `tiene()` dice qué hay para que la pantalla no muestre lo que no existe
 * (ni avisos de datos que aún no llegan).
 */
final class ActividadDeCuentas
{
    /** @var list<AporteActividad> */
    private array $aportes = [];

    public function registrar(AporteActividad $aporte): void
    {
        foreach ($aporte->datos() as $dato) {
            if ($this->tiene($dato)) {
                throw new LogicException("El dato «{$dato->value}» de las cuentas ya lo aporta otro módulo.");
            }
        }

        if (in_array(DatoActividad::UltimoContacto, $aporte->datos(), true) && ! $aporte instanceof AporteUltimoContacto) {
            throw new LogicException('Quien aporta el último contacto debe filtrar y ordenar por él (AporteUltimoContacto).');
        }

        if (in_array(DatoActividad::OportunidadesAbiertas, $aporte->datos(), true) && ! $aporte instanceof AporteOportunidadesAbiertas) {
            throw new LogicException('Quien aporta las oportunidades abiertas debe filtrar por ellas (AporteOportunidadesAbiertas).');
        }

        $this->aportes[] = $aporte;
    }

    public function tiene(DatoActividad $dato): bool
    {
        foreach ($this->aportes as $aporte) {
            if (in_array($dato, $aporte->datos(), true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Los datos de una página de cuentas, en lote: una llamada por aporte, no por fila.
     *
     * @param  list<int>  $cuentaIds
     * @return array<int, DatosActividadCuenta> una entrada por cada id pedido
     */
    public function datosDe(array $cuentaIds): array
    {
        $datos = array_fill_keys($cuentaIds, new DatosActividadCuenta);

        foreach ($this->aportes as $aporte) {
            foreach ($aporte->leer($cuentaIds) as $cuentaId => $delAporte) {
                if (isset($datos[$cuentaId])) {
                    $datos[$cuentaId] = $datos[$cuentaId]->con($delAporte);
                }
            }
        }

        return $datos;
    }

    /**
     * «Sin actividad» (D11): último contacto anterior al día, o sin contacto y creada antes de ese día. Sin quien
     * aporte el último contacto, no hay cómo saberlo: falla, y la pantalla no ofrece el filtro (`tiene()`). El aporte da
     * su subconsulta y el cruce lo hace Cuentas (plan 2.21, D13.4).
     *
     * @param  Builder<Cuenta>  $cuentas
     */
    public function sinActividadDesde(Builder $cuentas, string $dia): void
    {
        $contactos = $this->aporteDe(DatoActividad::UltimoContacto, AporteUltimoContacto::class)->ultimosContactos();

        // Una cuenta con un contacto anterior al día ya existía ese día: la condición de creación vale para las dos.
        $cuentas->where('cuentas.created_at', '<', $dia)
            ->whereNotIn('cuentas.id', DB::query()->fromSub($contactos, 'contactos')->where('contactos.ultimo_contacto', '>=', $dia)->select('contactos.cuenta_id'));
    }

    /**
     * Las que no tienen contacto, al final en los dos sentidos.
     *
     * @param  Builder<Cuenta>  $cuentas
     * @param  'asc'|'desc'  $direccion
     */
    public function ordenarPorUltimoContacto(Builder $cuentas, string $direccion): void
    {
        $contactos = $this->aporteDe(DatoActividad::UltimoContacto, AporteUltimoContacto::class)->ultimosContactos();

        if ($cuentas->getQuery()->columns === null) {
            $cuentas->select('cuentas.*');
        }

        $cuentas->leftJoinSub($contactos, 'contactos', 'contactos.cuenta_id', '=', 'cuentas.id')
            ->orderByRaw('contactos.ultimo_contacto '.($direccion === 'desc' ? 'desc' : 'asc').' nulls last');
    }

    /**
     * @param  Builder<Cuenta>  $cuentas
     */
    public function conOportunidadesAbiertas(Builder $cuentas): void
    {
        $cuentas->whereIn('cuentas.id', $this->aporteDe(DatoActividad::OportunidadesAbiertas, AporteOportunidadesAbiertas::class)->cuentasConAbiertas());
    }

    /**
     * Lo que impide desactivar la cuenta, de todos los aportes (D9). Hoy, nada: la regla funciona sola cuando lleguen.
     *
     * @return list<string>
     */
    public function impedimentosParaDesactivar(int $cuentaId): array
    {
        $impedimentos = [];

        foreach ($this->aportes as $aporte) {
            array_push($impedimentos, ...$aporte->impedimentosParaDesactivar($cuentaId));
        }

        return $impedimentos;
    }

    /**
     * @template T of AporteActividad
     *
     * @param  class-string<T>  $tipo
     * @return T
     */
    private function aporteDe(DatoActividad $dato, string $tipo): AporteActividad
    {
        foreach ($this->aportes as $aporte) {
            if ($aporte instanceof $tipo && in_array($dato, $aporte->datos(), true)) {
                return $aporte;
            }
        }

        throw new LogicException("Ningún módulo aporta «{$dato->value}» de las cuentas: revisa tiene() antes de usarlo.");
    }
}
