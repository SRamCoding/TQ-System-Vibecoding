<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Estados;

use App\Modules\Erp\Presupuestos\Condiciones\ArbolCuadra;
use App\Modules\Erp\Presupuestos\Condiciones\ProyectoDelArbolAceptaCostos;
use App\Modules\Erp\Presupuestos\Models\ArbolPartidas;
use App\Modules\Nucleo\Enums\TonoEstado;
use App\Modules\Nucleo\Estados\DefinicionDocumento;
use App\Modules\Nucleo\Estados\EstadoDefinido;
use App\Modules\Nucleo\Estados\TransicionDefinida;
use Illuminate\Database\Eloquent\Model;

/**
 * El árbol de partidas como documento (plan 2.1, D5; v4, parte 6). Aprobar exige que cuadre y que el proyecto acepte
 * costos; crea la PC-99 y pasa el proyecto a «en ejecución». «Nueva versión» llega con las pantallas del árbol.
 */
final class DefinicionArbolPartidas extends DefinicionDocumento
{
    public const string CLAVE = 'erp.presupuestos.arbol';

    public const string BORRADOR = 'borrador';

    public const string APROBADO = 'aprobado';

    public function modelo(): string
    {
        return ArbolPartidas::class;
    }

    public function clave(): string
    {
        return self::CLAVE;
    }

    public function nombre(): string
    {
        return 'árbol de partidas';
    }

    public function identificador(Model $documento): string
    {
        return 'v'.$documento->getAttribute('numero_version').' del proyecto #'.$documento->getAttribute('proyecto_id');
    }

    public function estados(): array
    {
        return [
            new EstadoDefinido(self::BORRADOR, 'Borrador', TonoEstado::Neutro, esInicial: true, esEditable: true),
            new EstadoDefinido(self::APROBADO, 'Aprobado', TonoEstado::Aprobado, esFinal: true),
        ];
    }

    public function transiciones(): array
    {
        return [
            new TransicionDefinida(
                'aprobar',
                'Aprobar',
                self::BORRADOR,
                self::APROBADO,
                efectos: ['erp.presupuestos.crear-pc99', 'erp.presupuestos.iniciar-ejecucion'],
                condiciones: [ProyectoDelArbolAceptaCostos::class, ArbolCuadra::class],
            ),
        ];
    }
}
