<?php

declare(strict_types=1);

namespace App\Modules\Erp\Proyectos\Estados;

use App\Modules\Erp\Proyectos\Condiciones\ProyectoSinImputaciones;
use App\Modules\Erp\Proyectos\Condiciones\ProyectoSinPendientes;
use App\Modules\Erp\Proyectos\Models\Proyecto;
use App\Modules\Nucleo\Enums\TonoEstado;
use App\Modules\Nucleo\Estados\DefinicionDocumento;
use App\Modules\Nucleo\Estados\EstadoDefinido;
use App\Modules\Nucleo\Estados\TransicionDefinida;
use Illuminate\Database\Eloquent\Model;

/**
 * Estados del proyecto (plan 2.1, D3). «En ejecución» lo pone el sistema al aprobar el árbol de partidas; la lista de
 * verificación del cierre (flujo 6) la suma el área 4 como condición de «cerrar». «Anulado» no está en el v4: se
 * agrega por la regla 6 (no se borra, se anula). El código lo pone `ProyectosService`, no la numeración de
 * documentos: la columna es `codigo` (DBML), no `numero`.
 */
final class DefinicionProyecto extends DefinicionDocumento
{
    public const string CLAVE = 'erp.proyectos.proyecto';

    public const string EN_PREPARACION = 'en_preparacion';

    public const string EN_EJECUCION = 'en_ejecucion';

    public const string CERRADO = 'cerrado';

    public const string ANULADO = 'anulado';

    /** Los estados en que el proyecto acepta costos (ADR 0020, C2: «no cerrado»; tampoco anulado). */
    public const array ACEPTAN_COSTOS = [self::EN_PREPARACION, self::EN_EJECUCION];

    public function modelo(): string
    {
        return Proyecto::class;
    }

    public function clave(): string
    {
        return self::CLAVE;
    }

    public function nombre(): string
    {
        return 'proyecto';
    }

    public function identificador(Model $documento): string
    {
        $codigo = $documento->getAttribute('codigo');

        return is_string($codigo) && $codigo !== '' ? $codigo : '#'.$documento->getKey();
    }

    public function estados(): array
    {
        return [
            new EstadoDefinido(self::EN_PREPARACION, 'En preparación', TonoEstado::Neutro, esInicial: true, esEditable: true),
            new EstadoDefinido(self::EN_EJECUCION, 'En ejecución', TonoEstado::EnCurso, esEditable: true),
            new EstadoDefinido(self::CERRADO, 'Cerrado', TonoEstado::Cerrado, esFinal: true),
            new EstadoDefinido(self::ANULADO, 'Anulado', TonoEstado::Anulado, esFinal: true, esCancelacion: true),
        ];
    }

    public function transiciones(): array
    {
        return [
            new TransicionDefinida('iniciar_ejecucion', 'Iniciar ejecución', self::EN_PREPARACION, self::EN_EJECUCION, esDelSistema: true),
            new TransicionDefinida('cerrar', 'Cerrar', self::EN_EJECUCION, self::CERRADO, condiciones: [ProyectoSinPendientes::class], efectos: ['erp.proyectos.avisar-cierre']),
            new TransicionDefinida('anular', 'Anular', self::EN_PREPARACION, self::ANULADO, requiereMotivo: true, condiciones: [ProyectoSinImputaciones::class]),
        ];
    }
}
