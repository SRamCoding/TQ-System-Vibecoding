<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Filament;

use App\Modules\Crm\Oportunidades\Enums\EtapaAbierta;
use App\Modules\Crm\Oportunidades\Estados\DefinicionOportunidad;
use App\Modules\Crm\Oportunidades\Requisitos\RequisitosDeEtapa;
use App\Modules\Crm\Oportunidades\Services\OportunidadesService;
use App\Modules\Nucleo\Excepciones\AccionRechazada;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;
use Filament\Facades\Filament;

/**
 * Qué acción registrada corresponde a mover una oportunidad de un lugar a otro, y el aviso anticipado de si la acción la
 * rechazaría (plan 2.21b, D3 y paso 9; plan 2.21c, D4 y D5). Lo usan la faja, el tablero de gemas, el despiece y la
 * ficha del tablero: un solo camino para el arrastre y los botones. No decide reglas: elige la acción y repite lo que la
 * acción y su servicio revisan al ejecutarse (permiso, «propias», requisitos de la etapa), solo para avisar antes.
 */
final class MovimientoDeOportunidad
{
    /** Los destinos fuera de las etapas abiertas. */
    public const string GANADA = 'ganada';

    public const string PERDIDA = 'perdida';

    public const string NO_SE_SALTA = 'No se saltan etapas: pasa de una en una por su escáner.';

    public const string NO_SE_RETROCEDE = 'Para retroceder una oportunidad se hace desde su ficha, con motivo.';

    /**
     * Las etapas desde las que se gana: el origen de la transición `ganar`, que es dato (plan 2.22b). La ficha, el tablero
     * y la faja lo leen de aquí, nunca escrito en su código.
     *
     * @return list<string>
     */
    public static function ganaDesde(): array
    {
        return (new DefinicionOportunidad)->transicion('ganar')->desde ?? [];
    }

    /**
     * La acción del movimiento (código de la transición y nombre de su diálogo), o la línea que explica por qué no se
     * hace (D3 de la 2.21b): saltar etapas, retroceder o ganar desde una etapa que no lo permite (`ganaDesde`).
     *
     * @return array{codigo: string, dialogo: string}|string
     */
    public static function accion(string $desde, string $hacia): array|string
    {
        $origen = EtapaAbierta::tryFrom($desde);

        if ($origen === null) {
            return 'Esa oportunidad ya no está abierta.';
        }

        if ($hacia === self::PERDIDA) {
            return ['codigo' => 'perder', 'dialogo' => 'perder'];
        }

        if ($hacia === self::GANADA) {
            return in_array($origen->value, self::ganaDesde(), true) ? ['codigo' => 'ganar', 'dialogo' => 'ganar'] : self::NO_SE_SALTA;
        }

        $destino = EtapaAbierta::tryFrom($hacia);

        if ($destino === null) {
            return 'Ese lugar no es una etapa del embudo.';
        }

        if ($destino === $origen->siguiente()) {
            $codigo = PanelesOportunidad::AVANCE_DESDE[$origen->value];

            return ['codigo' => $codigo, 'dialogo' => lcfirst(str_replace('_', '', ucwords($codigo, '_')))];
        }

        $orden = array_flip(array_map(fn (EtapaAbierta $etapa): string => $etapa->value, EtapaAbierta::cases()));

        return $orden[$destino->value] > $orden[$origen->value] ? self::NO_SE_SALTA : self::NO_SE_RETROCEDE;
    }

    /**
     * Por qué la acción rechazaría este movimiento por permiso, o nulo (plan 2.21b, paso 9): el permiso de la acción
     * como EjecutorAcciones (global, salvo que la acción vaya por ámbito; las de Oportunidades no van) y que sea suya o
     * pueda editar todas, como OportunidadesService (D10 de la 2.21; la regla de `SobreLoPropio`).
     */
    public static function rechazo(int $oportunidadId, string $codigo): ?string
    {
        $accion = app(RegistroAcciones::class)->obtener(DefinicionOportunidad::CLAVE.".{$codigo}");
        $usuario = Filament::auth()->user();

        if (! $usuario instanceof User) {
            return AccionRechazada::sinPermiso($accion->nombre())->getMessage();
        }

        $permiso = $accion->permiso();

        if ($permiso !== null && ! $accion->permisoPorAmbito() && ! $usuario->can($permiso)) {
            return AccionRechazada::sinPermiso($accion->nombre())->getMessage();
        }

        return app(OportunidadesService::class)->rechazoAlMoverla($oportunidadId, $usuario);
    }

    /**
     * Lo que falta para llegar a `$hacia` (los requisitos acumulados de `RequisitosDeEtapa`, D9 de la 2.21); perder no
     * exige ninguno. Es lo que la rueda de tallado revisa antes de dejar pasar (D4 de la 2.21c).
     *
     * @return list<string>
     */
    public static function faltantes(int $oportunidadId, string $hacia): array
    {
        return $hacia === self::PERDIDA ? [] : app(RequisitosDeEtapa::class)->faltantes($oportunidadId, $hacia);
    }

    /** Si la acción del movimiento lo aceptaría por permiso: para ofrecer, o no, su botón. */
    public static function puede(int $oportunidadId, string $desde, string $hacia): bool
    {
        $accion = self::accion($desde, $hacia);

        return is_array($accion) && self::rechazo($oportunidadId, $accion['codigo']) === null;
    }

    /**
     * «Para pasar a Calificada falta: Decisor identificado, Presupuesto confirmado.»
     *
     * @param  list<string>  $faltan
     */
    public static function textoDeFaltantes(string $hacia, array $faltan): string
    {
        $destino = EtapaAbierta::tryFrom($hacia)?->etiqueta() ?? ($hacia === self::GANADA ? 'Ganadas' : $hacia);

        return "Para pasar a {$destino} falta: ".implode(', ', $faltan).'.';
    }
}
