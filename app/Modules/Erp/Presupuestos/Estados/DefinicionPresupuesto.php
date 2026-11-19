<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Estados;

use App\Modules\Erp\Presupuestos\Condiciones\PresupuestoCompleto;
use App\Modules\Erp\Presupuestos\Models\Presupuesto;
use App\Modules\Nucleo\Enums\TonoEstado;
use App\Modules\Nucleo\Estados\DefinicionDocumento;
use App\Modules\Nucleo\Estados\EstadoDefinido;
use App\Modules\Nucleo\Estados\TransicionDefinida;
use App\Modules\Nucleo\Numeracion\FormatoNumeracion;
use Illuminate\Database\Eloquent\Model;

/**
 * El presupuesto como documento (plan 2.9, D6). Borrador editable; «Ofertar» lo congela sin proyecto; «Traer al
 * proyecto» (acción propia: pide el proyecto) lo aprueba y lo deja vigente, desde ofertado o directo desde borrador;
 * al aprobarse la versión siguiente, el sistema lo pasa a reemplazado; uno ofertado, también al ofertarse o aprobarse una
 * versión posterior (plan 2.22, D8). Anular, solo en borrador y con motivo.
 * El número (`PRE-AAAA-NNNN`) lo pone el motor a la versión 1; las siguientes lo heredan (plan 2.22, D2).
 */
final class DefinicionPresupuesto extends DefinicionDocumento
{
    public const string CLAVE = 'erp.presupuestos.presupuesto';

    public const string BORRADOR = 'borrador';

    public const string OFERTADO = 'ofertado';

    public const string APROBADO = 'aprobado';

    public const string REEMPLAZADO = 'reemplazado';

    public const string ANULADO = 'anulado';

    public const string TRAER_AL_PROYECTO = 'traer_al_proyecto';

    public const string REEMPLAZAR = 'reemplazar';

    public const string OFERTAR = 'ofertar';

    /** La que dispara Comercial al emitir una cotización (C8, plan 2.22, D8): del sistema, a nombre de quien emite. */
    public const string OFERTAR_CON_COTIZACION = 'ofertar_con_cotizacion';

    private const string REEMPLAZA_OFERTADAS = 'erp.presupuestos.reemplazar-ofertadas';

    public function modelo(): string
    {
        return Presupuesto::class;
    }

    public function clave(): string
    {
        return self::CLAVE;
    }

    public function nombre(): string
    {
        return 'presupuesto';
    }

    public function numeracion(): FormatoNumeracion
    {
        return new FormatoNumeracion('PRE');
    }

    public function versionesCompartenNumero(): bool
    {
        return true;
    }

    public function identificador(Model $documento): string
    {
        return $documento->getAttribute('numero').' v'.$documento->getAttribute('numero_version');
    }

    public function estados(): array
    {
        return [
            new EstadoDefinido(self::BORRADOR, 'Borrador', TonoEstado::Neutro, esInicial: true, esEditable: true),
            new EstadoDefinido(self::OFERTADO, 'Ofertado', TonoEstado::EnCurso),
            new EstadoDefinido(self::APROBADO, 'Aprobado', TonoEstado::Aprobado),
            new EstadoDefinido(self::REEMPLAZADO, 'Reemplazado', TonoEstado::Cerrado, esFinal: true),
            new EstadoDefinido(self::ANULADO, 'Anulado', TonoEstado::Anulado, esFinal: true, esCancelacion: true),
        ];
    }

    public function transiciones(): array
    {
        return [
            new TransicionDefinida(self::OFERTAR, 'Ofertar', self::BORRADOR, self::OFERTADO, efectos: [self::REEMPLAZA_OFERTADAS], condiciones: [PresupuestoCompleto::class]),
            new TransicionDefinida(self::OFERTAR_CON_COTIZACION, 'Ofertar con la cotización', self::BORRADOR, self::OFERTADO, esDelSistema: true, efectos: [self::REEMPLAZA_OFERTADAS], condiciones: [PresupuestoCompleto::class]),
            new TransicionDefinida(
                self::TRAER_AL_PROYECTO,
                'Traer al proyecto',
                [self::BORRADOR, self::OFERTADO],
                self::APROBADO,
                efectos: ['erp.presupuestos.hacer-vigente', self::REEMPLAZA_OFERTADAS],
                condiciones: [PresupuestoCompleto::class],
                accionPropia: true,
            ),
            new TransicionDefinida(self::REEMPLAZAR, 'Reemplazar', [self::APROBADO, self::OFERTADO], self::REEMPLAZADO, esDelSistema: true, efectos: ['erp.presupuestos.quitar-vigencia']),
            new TransicionDefinida('anular', 'Anular', self::BORRADOR, self::ANULADO, requiereMotivo: true),
        ];
    }
}
