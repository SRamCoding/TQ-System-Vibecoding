<?php

declare(strict_types=1);

namespace Tests\Modules\Erp\Presupuestos\Fixtures;

use App\Modules\Nucleo\Aprobaciones\MontoAprobable;
use App\Modules\Nucleo\Enums\TonoEstado;
use App\Modules\Nucleo\Estados\DefinicionDocumento;
use App\Modules\Nucleo\Estados\EstadoDefinido;
use App\Modules\Nucleo\Estados\TransicionDefinida;
use App\Modules\Nucleo\Imputacion\LineaDeCosto;
use App\Modules\Nucleo\Services\ImputacionService;
use Illuminate\Database\Eloquent\Model;

/**
 * La orden de prueba como la declararía una orden de compra (costura C12): «enviar» es de acción propia (su servicio
 * revisa el exceso antes), «aprobar» requiere aprobación y su efecto sube el comprometido, y declara sus líneas de costo.
 */
final class DefinicionOrdenDePrueba extends DefinicionDocumento
{
    public function modelo(): string
    {
        return OrdenDePrueba::class;
    }

    public function clave(): string
    {
        return 'erp.presupuestos.orden_prueba';
    }

    public function nombre(): string
    {
        return 'orden de prueba';
    }

    public function estados(): array
    {
        return [
            new EstadoDefinido('borrador', 'Borrador', TonoEstado::Neutro, esInicial: true, esEditable: true),
            new EstadoDefinido('por_aprobar', 'Por aprobar', TonoEstado::PorAprobar),
            new EstadoDefinido('aprobado', 'Aprobado', TonoEstado::Aprobado, esFinal: true),
        ];
    }

    public function transiciones(): array
    {
        return [
            new TransicionDefinida('enviar', 'Enviar', 'borrador', 'por_aprobar', accionPropia: true),
            new TransicionDefinida('aprobar', 'Aprobar', 'por_aprobar', 'aprobado', requiereAprobacion: true, efectos: ['erp.presupuestos.prueba.comprometer']),
            new TransicionDefinida('devolver', 'Devolver', 'por_aprobar', 'borrador', esDelSistema: true),
        ];
    }

    public function transicionAlObservar(string $transicionConAprobacion): ?string
    {
        return 'devolver';
    }

    /** La suma de sus líneas en soles, con el tipo de cambio de cada una (el de su imputación). */
    public function montoParaAprobacion(Model $documento): ?MontoAprobable
    {
        $imputaciones = app(ImputacionService::class);
        $total = '0';

        foreach (LineaOrdenDePrueba::query()->where('orden_prueba_id', $documento->getKey())->get() as $linea) {
            $tipoCambio = (string) ($imputaciones->de($linea)?->tipo_cambio ?? '1');
            $total = bcadd($total, bcmul((string) $linea->monto, $tipoCambio, 6), 6);
        }

        return new MontoAprobable(bcadd($total, '0', 2), 'PEN');
    }

    public function unidadDeAprobacion(Model $documento): ?int
    {
        $unidad = $documento->getAttribute('unidad_id');

        return $unidad === null ? null : (int) $unidad;
    }

    public function lineasDeCosto(Model $documento): array
    {
        return OrdenDePrueba::query()->findOrFail($documento->getKey())->lineas
            ->map(fn (LineaOrdenDePrueba $linea): LineaDeCosto => new LineaDeCosto($linea, (string) $linea->monto))
            ->values()
            ->all();
    }
}
