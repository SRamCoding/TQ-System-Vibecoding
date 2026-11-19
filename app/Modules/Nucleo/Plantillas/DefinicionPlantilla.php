<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Plantillas;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Seguridad\GrupoSensible;

/**
 * Un tipo de plantilla que registra su módulo (ADR 0022; plan 2.11, D4): el contrato entre el núcleo y el tipo. El
 * núcleo guarda, versiona, da permisos y muestra; el tipo dice qué se captura de un documento, cómo se valida su
 * contenido y cómo se aplica (copiando, a una propuesta editable). El núcleo nunca interpreta el contenido: el árbol
 * de partidas y el presupuesto con APU caben en este mismo contrato sin que el núcleo sepa de ninguno.
 *
 * Un formato nuevo del contenido sube `formatoActual()`; `validar()` y `proponer()` siguen sabiendo leer los
 * anteriores, porque las versiones guardadas no se modifican.
 */
abstract class DefinicionPlantilla
{
    /** `<area>.<modulo>.<cosa>` (ADR 0021), por ejemplo `erp.presupuestos.arbol`. Es lo que guarda `plantillas.tipo`. */
    abstract public function clave(): string;

    /** Cómo se llama para las personas: «Árbol de partidas de control». */
    abstract public function nombre(): string;

    /** Quién puede usar y guardar plantillas de este tipo: el mismo que crea el documento (D1). */
    abstract public function permisoUsar(): string;

    /** Quién publica en la empresa (D2): uno por tipo, con su área; varios tipos de un módulo pueden compartirlo. */
    abstract public function permisoPublicar(): string;

    /** El formato en que `capturar()` entrega el contenido; desde 1. */
    abstract public function formatoActual(): int;

    /**
     * Qué le falta o sobra a un contenido de ese formato para ser válido; vacío si está bien. Lo usa el núcleo al
     * guardar cada versión: un contenido inválido no se guarda.
     *
     * @param  array<string, mixed>  $contenido
     * @return list<string>
     */
    abstract public function validar(array $contenido, int $formato): array;

    /**
     * El contenido de un documento de este tipo, en el formato actual, para guardarlo como plantilla. Sin clientes ni
     * personas (ADR 0022); con montos solo si el tipo declara su grupo sensible. Rechaza con un `PlantillaRechazada`
     * (o un error de negocio de su módulo) lo que no se puede capturar.
     *
     * @return array<string, mixed>
     */
    abstract public function capturar(int $documentoId, User $usuario): array;

    /**
     * Aplicar: arma la propuesta editable del documento con una copia del contenido (D3). No crea nada: lo crea la
     * acción normal del módulo cuando la persona confirma. `$contexto` lleva lo que el tipo necesita (por ejemplo, el
     * proyecto); el tipo rechaza con un mensaje que dice por qué y qué hacer (D7 en el árbol).
     *
     * @param  array<string, mixed>  $contenido
     * @param  array<string, mixed>  $contexto
     */
    abstract public function proponer(array $contenido, int $formato, array $contexto, User $usuario): object;

    /** La clave del documento del que se captura (`plantilla_versiones.origen_documento`). Por omisión, la del tipo. */
    public function documentoOrigen(): string
    {
        return $this->clave();
    }

    /**
     * D5: si el contenido lleva montos, su grupo (ADR 0016). Sin ese permiso, la plantilla se ve en la lista, pero su
     * contenido no se muestra ni se envía al navegador, y no se aplica ni se guarda.
     */
    public function grupoSensible(): ?GrupoSensible
    {
        return null;
    }
}
