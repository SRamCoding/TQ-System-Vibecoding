<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Ia;

use Closure;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Un proceso de IA (plan 1.11, D1): lo declara su módulo en RegistroProcesosIa. Dice de qué registros parte, qué envía
 * al modelo (tipos de adjunto y, del registro de origen, qué campos) y qué campos propone. Lo que envía es lo que
 * Gerencia autoriza (D2): si cambia, la autorización cae. Lo editable (encendido, tope, modelos) vive en `ia_procesos`.
 */
final readonly class DefinicionProcesoIa
{
    /**
     * @param  list<string>  $origenes  claves del morph map de los registros de los que parte (`party`)
     * @param  list<string>  $tiposAdjunto  códigos de `tipos_adjunto` que puede enviar; [] si no envía archivos
     * @param  array<string, list<string>>  $camposEnviados  por origen, los campos del registro que envía; nunca sensibles
     * @param  array<string, array<string, mixed>>  $campos  lo que propone: campo => esquema JSON de su valor
     * @param  string  $permisoResolver  el permiso del módulo que se exige para resolver sus sugerencias (el de la acción
     *                                   que crea el registro con ellas): `compras.registrar-recepcion`
     * @param  list<string>  $produce  claves del morph map de los registros que se crean con sus sugerencias
     * @param  array<string, Closure(mixed): bool>  $reglas  reglas fijas por campo (RUC válido, sumas): la seguridad del
     *                                                       campo sube si se cumplen y baja si no (D6)
     * @param  (Closure(Model): array<string, list<array<string, mixed>>>)|null  $opciones  opciones válidas que van en el
     *                                                                                      contexto (D8): catálogos,
     *                                                                                      partidas del proyecto
     * @param  array<string, list<string>>  $opcionesEnviadas  por cada lista de opciones, qué campos de cada una salen:
     *                                                         entra en la huella, así Gerencia lo autoriza (revisión de
     *                                                         arquitectura); lo demás que devuelva `$opciones` no sale
     */
    public function __construct(
        /** El código en `ia_procesos`: `licitacion_bases`. */
        public string $clave,
        public string $nombre,
        /** Para qué sirve, en Configuración → IA. */
        public string $descripcion,
        /** El área que sabe si la respuesta es correcta (ia.md): «Tesorería». */
        public string $dueno,
        public array $origenes,
        public array $tiposAdjunto,
        public array $camposEnviados,
        public array $campos,
        public string $permisoResolver,
        public array $produce,
        public array $reglas = [],
        public ?Closure $opciones = null,
        public array $opcionesEnviadas = [],
    ) {
        if (($opciones === null) !== ($opcionesEnviadas === [])) {
            throw new InvalidArgumentException("El proceso de IA «{$clave}» declara juntas sus opciones y qué campos de ellas envía.");
        }
        if (preg_match('/^[a-z][a-z0-9_]{0,59}$/', $clave) !== 1) {
            throw new InvalidArgumentException("El proceso de IA «{$clave}» va en minúsculas y guion bajo.");
        }

        if (preg_match('/^[a-z][a-z0-9_]*(\.[a-z0-9_-]+)+$/', $permisoResolver) !== 1) {
            throw new InvalidArgumentException("El proceso de IA «{$clave}» declara el permiso de su módulo para resolver sus sugerencias (`modulo.accion`).");
        }
        if ($produce === []) {
            throw new InvalidArgumentException("El proceso de IA «{$clave}» declara qué registros se crean con sus sugerencias.");
        }
        if ($origenes === [] || $campos === []) {
            throw new InvalidArgumentException("El proceso de IA «{$clave}» necesita al menos un origen y un campo que proponer.");
        }

        foreach (array_keys($camposEnviados) as $origen) {
            if (! in_array($origen, $origenes, true)) {
                throw new InvalidArgumentException("El proceso de IA «{$clave}» envía campos de «{$origen}», que no es uno de sus orígenes.");
            }
        }

        foreach (array_keys($reglas) as $campo) {
            if (! array_key_exists($campo, $campos)) {
                throw new InvalidArgumentException("La regla «{$campo}» del proceso de IA «{$clave}» no es de un campo que propone.");
            }
        }
        foreach (array_keys($campos) as $campo) {
            if (preg_match('/^[a-z][a-z0-9_]{0,59}$/', $campo) !== 1) {
                throw new InvalidArgumentException("El campo «{$campo}» del proceso de IA «{$clave}» va en minúsculas y guion bajo.");
            }
        }
    }

    /**
     * La huella de lo que sale (D2): orígenes, tipos de adjunto y campos enviados, sin importar el orden. No incluye el
     * prompt ni los campos que propone: no cambian lo que sale de la empresa.
     */
    public function huella(): string
    {
        $origenes = $this->origenes;
        sort($origenes);
        $tipos = $this->tiposAdjunto;
        sort($tipos);
        $campos = [];

        foreach ($this->camposEnviados as $origen => $lista) {
            sort($lista);
            $campos[$origen] = $lista;
        }

        ksort($campos);

        $opciones = [];
        foreach ($this->opcionesEnviadas as $lista => $camposDeLaLista) {
            sort($camposDeLaLista);
            $opciones[$lista] = $camposDeLaLista;
        }
        ksort($opciones);
        $huella = ['origenes' => $origenes, 'tipos_adjunto' => $tipos, 'campos' => $campos];

        // Las opciones entran solo si las hay: un proceso sin opciones conserva la huella que ya tenía autorizada.
        if ($opciones !== []) {
            $huella['opciones'] = $opciones;
        }

        return hash('sha256', (string) json_encode($huella, JSON_THROW_ON_ERROR));
    }
}
