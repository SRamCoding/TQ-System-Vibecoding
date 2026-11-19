<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Estados;

use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Nucleo\Enums\TonoEstado;
use App\Modules\Nucleo\Estados\DefinicionDocumento;
use App\Modules\Nucleo\Estados\EstadoDefinido;
use App\Modules\Nucleo\Estados\TransicionDefinida;
use App\Modules\Nucleo\Numeracion\FormatoNumeracion;

/**
 * La oportunidad como documento con estados (plan 2.21, D1): las etapas son sus estados. Se avanza de una en una, sin
 * saltar hacia adelante; las transiciones son datos (ganar desde «Propuesta enviada» y los motivos de retroceso son
 * provisionales: pendiente «Embudo comercial»). Avanzar, ganar y perder son acciones propias (piden más datos).
 */
final class DefinicionOportunidad extends DefinicionDocumento
{
    public const string CLAVE = 'crm.oportunidades.oportunidad';

    public const string EFECTO_ENTRA_EN_ETAPA = 'crm.oportunidades.entra-en-etapa';

    public const string EFECTO_MARCA_CLIENTE = 'crm.oportunidades.ganada-marca-cliente';

    public const string EFECTO_AVISA_ERP = 'crm.oportunidades.ganada-avisa-erp';

    public function modelo(): string
    {
        return Oportunidad::class;
    }

    public function clave(): string
    {
        return self::CLAVE;
    }

    public function nombre(): string
    {
        return 'oportunidad';
    }

    public function articulo(): string
    {
        return 'la';
    }

    public function numeracion(): FormatoNumeracion
    {
        return new FormatoNumeracion('OP');
    }

    public function estados(): array
    {
        return [
            new EstadoDefinido('prospecto', 'Prospecto', TonoEstado::Neutro, esInicial: true, esEditable: true),
            new EstadoDefinido('calificada', 'Calificada', TonoEstado::EnCurso, esEditable: true),
            new EstadoDefinido('propuesta_enviada', 'Propuesta enviada', TonoEstado::EnCurso, esEditable: true),
            new EstadoDefinido('negociacion', 'Negociación', TonoEstado::PorAprobar, esEditable: true),
            new EstadoDefinido('ganada', 'Ganada', TonoEstado::Aprobado, esFinal: true),
            new EstadoDefinido('perdida', 'Perdida', TonoEstado::Cerrado, esFinal: true),
            new EstadoDefinido('anulada', 'Anulada', TonoEstado::Anulado, esFinal: true, esCancelacion: true),
        ];
    }

    public function transiciones(): array
    {
        $etapa = [self::EFECTO_ENTRA_EN_ETAPA];
        $avanzar = [SobreLoPropio::class, CumpleRequisitosDeEtapa::class];
        $abiertas = ['prospecto', 'calificada', 'propuesta_enviada', 'negociacion'];

        return [
            new TransicionDefinida('calificar', 'Calificar', 'prospecto', 'calificada', efectos: $etapa, condiciones: $avanzar, accionPropia: true),
            new TransicionDefinida('enviar_propuesta', 'Enviar propuesta', 'calificada', 'propuesta_enviada', efectos: $etapa, condiciones: $avanzar, accionPropia: true),
            new TransicionDefinida('negociar', 'Negociar', 'propuesta_enviada', 'negociacion', efectos: $etapa, condiciones: $avanzar, accionPropia: true),
            // Provisional (pendiente «Embudo comercial»): se puede retroceder a la etapa anterior, con motivo.
            ...array_map(
                fn (array $par): TransicionDefinida => new TransicionDefinida('retroceder', 'Retroceder', $par[0], $par[1], requiereMotivo: true, efectos: $etapa, condiciones: [SobreLoPropio::class, SinImpedimentos::class]),
                [['calificada', 'prospecto'], ['propuesta_enviada', 'calificada'], ['negociacion', 'propuesta_enviada']],
            ),
            // Provisional (pendiente «Embudo comercial»; propuesta del desarrollador, plan 2.22, P2): se gana desde Propuesta
            // enviada o desde Negociación. El tablero, la faja y la ficha leen este origen (`MovimientoDeOportunidad::ganaDesde`).
            new TransicionDefinida('ganar', 'Ganar', ['propuesta_enviada', 'negociacion'], 'ganada', efectos: [...$etapa, self::EFECTO_MARCA_CLIENTE, self::EFECTO_AVISA_ERP], condiciones: [...$avanzar, CuentaUsable::class], accionPropia: true),
            new TransicionDefinida('perder', 'Perder', $abiertas, 'perdida', requiereMotivo: true, efectos: $etapa, condiciones: [SobreLoPropio::class, SinImpedimentos::class], accionPropia: true),
            // D10: anular es la corrección común; anular una ganada desarma el contrato y es solo de Gerencia Comercial.
            new TransicionDefinida('anular', 'Anular', [...$abiertas, 'perdida'], 'anulada', requiereMotivo: true, efectos: $etapa, condiciones: [SobreLoPropio::class]),
            new TransicionDefinida('anular_ganada', 'Anular ganada', 'ganada', 'anulada', requiereMotivo: true, efectos: $etapa),
        ];
    }
}
