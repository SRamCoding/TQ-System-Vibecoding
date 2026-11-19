<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Filament;

use App\Modules\Crm\Oportunidades\Enums\EtapaAbierta;
use App\Modules\Crm\Oportunidades\Enums\TallaDeGema;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\VerOportunidad;
use App\Modules\Crm\Oportunidades\Requisitos\RequisitosDeEtapa;
use App\Modules\Crm\Oportunidades\Services\OportunidadesService;
use App\Modules\Crm\Oportunidades\Services\PortadaOportunidades;

/**
 * Lo que muestra la ficha del tablero en su panel deslizante (plan 2.21c, D6 y D8): la gema en grande, los datos, los
 * requisitos de la transición siguiente (de `RequisitosDeEtapa::revisar`, con si se cumplen) y los botones Pasar, Ganar
 * y Perder que la persona puede usar (`MovimientoDeOportunidad::puede`, el mismo aviso anticipado del arrastre). Una
 * cerrada, lo de su cierre y ningún botón. Sin reglas propias: los datos son los de `OportunidadesService::filaDeLaFaja`
 * (las anuladas no están). La próxima acción no sale hasta CRM-05 (D8).
 */
final readonly class FichaDeOportunidad
{
    /**
     * @return array<string, mixed>
     */
    public function datos(int $oportunidadId): array
    {
        $fila = app(OportunidadesService::class)->filaDeLaFaja($oportunidadId);
        $registro = TablaDeLaFaja::registro($fila);
        $etapa = EtapaAbierta::tryFrom($fila->etapa);
        $talla = TallaDeGema::desdeEtapa($fila->etapa);
        $probabilidad = $etapa === null ? null : app(PortadaOportunidades::class)->probabilidad($etapa);
        $nombreEtapa = $etapa?->etiqueta() ?? ($fila->etapa === MovimientoDeOportunidad::GANADA ? 'Ganada' : 'Perdida');
        $dias = $fila->diasEnEtapa === 1 ? '1 día' : "{$fila->diasEnEtapa} días";

        return [
            'id' => $fila->id,
            'numero' => $fila->numero,
            'nombre' => $fila->nombre,
            'cuenta' => $fila->cuenta,
            'etapa' => $fila->etapa,
            'talla' => $talla,
            'estado' => $talla->etiqueta().' · '.$nombreEtapa,
            'montoEnSoles' => $fila->montoEnSoles,
            'abierta' => $etapa !== null,
            'atascada' => $fila->atascada,
            'version' => $fila->version,
            'url' => VerOportunidad::getUrl(['record' => $fila->id]),
            'filas' => array_values(array_filter([
                ['Número', $fila->numero],
                ['Cuenta', $fila->cuenta],
                ['Ejecutivo', $fila->ejecutivo],
                // D17: la ficha es la ventana de lectura, con espacio: el monto completo, sin «k» ni «M».
                ['Monto', $registro['monto'].' · '.$fila->moneda],
                ['Etapa', $nombreEtapa],
                $probabilidad === null ? null : ['Probabilidad', "{$probabilidad} %"],
                $etapa === null ? null : ['Cierre estimado', $fila->cierreEstimado->format('d/m/Y')],
                $etapa === null ? null : ['Días en la etapa', $dias.($fila->atascada ? ' · atascada' : '')],
            ])),
            'siguiente' => $etapa === null ? null : self::siguiente($fila->id, $etapa),
            'cierre' => $etapa === null ? DespieceDeOportunidad::cierre($fila, $registro) : [],
        ];
    }

    /**
     * La transición siguiente: lo que exige (acumulado) y los botones que la persona puede usar, cada uno con su destino.
     *
     * @return array{titulo: string, requisitos: list<array{texto: string, cumple: bool}>, botones: list<array{hacia: string, etiqueta: string, color: string}>}
     */
    private static function siguiente(int $oportunidadId, EtapaAbierta $etapa): array
    {
        $siguiente = $etapa->siguiente();
        $hacia = $siguiente->value ?? MovimientoDeOportunidad::GANADA;
        $candidatos = [
            $siguiente === null
                ? ['hacia' => MovimientoDeOportunidad::GANADA, 'etiqueta' => 'Ganar', 'color' => 'success']
                : ['hacia' => $siguiente->value, 'etiqueta' => "Pasar a {$siguiente->etiqueta()}", 'color' => 'primary'],
            ['hacia' => MovimientoDeOportunidad::PERDIDA, 'etiqueta' => 'Perder', 'color' => 'danger'],
        ];

        return [
            'titulo' => $siguiente === null ? 'Para ganarla' : "Para pasar a {$siguiente->etiqueta()}",
            'requisitos' => app(RequisitosDeEtapa::class)->revisar($oportunidadId, $hacia),
            'botones' => array_values(array_filter(
                $candidatos,
                fn (array $boton): bool => MovimientoDeOportunidad::puede($oportunidadId, $etapa->value, $boton['hacia']),
            )),
        ];
    }
}
