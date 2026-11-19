<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Estados;

use App\Modules\Nucleo\Aprobaciones\MontoAprobable;
use App\Modules\Nucleo\Enums\CondicionRegla;
use App\Modules\Nucleo\Imputacion\LineaDeCosto;
use App\Modules\Nucleo\Numeracion\FormatoNumeracion;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * La máquina de estados de un tipo de documento (plan 1.5, D1). Cada módulo escribe una por documento y la
 * registra en RegistroDocumentos desde su provider; el sincronizador la copia a `estados` y `transiciones_estado`.
 */
abstract class DefinicionDocumento
{
    /**
     * Modelo del documento: su alias del morph map es el `documento_type` y su `estado_id`, el estado actual.
     *
     * @return class-string<Model>
     */
    abstract public function modelo(): string;

    /** Prefijo de permisos y listas de motivos: `compras.orden` da `compras.orden.anular`. */
    abstract public function clave(): string;

    /** Cómo se nombra el tipo en los mensajes, en minúsculas y sin artículo: «orden de compra». */
    abstract public function nombre(): string;

    /** «el» o «la», para los mensajes: «la orden de compra está en…». */
    public function articulo(): string
    {
        return 'el';
    }

    public function nombreConArticulo(): string
    {
        return "{$this->articulo()} {$this->nombre()}";
    }

    /**
     * @return list<EstadoDefinido>
     */
    abstract public function estados(): array;

    /**
     * @return list<TransicionDefinida>
     */
    abstract public function transiciones(): array;

    /**
     * Lo que impide anular el documento mientras viva (D4). Un módulo que depende de este y que este no conoce
     * registra la suya en RegistroDocumentos.
     *
     * @return list<class-string<RevisionAntesDeAnular>>
     */
    public function revisionesAntesDeAnular(): array
    {
        return [];
    }

    public function tipo(): string
    {
        return Relation::getMorphAlias($this->modelo());
    }

    /**
     * Permiso de una transición de persona. Las del sistema y las que requieren aprobación no tienen: quién firma
     * vive solo en las reglas de aprobación (1.7), no en un permiso por transición.
     */
    public function permiso(TransicionDefinida $transicion): ?string
    {
        return $transicion->esDelSistema || $transicion->requiereAprobacion ? null : "{$this->clave()}.{$transicion->codigo}";
    }

    /** Código de la lista de motivos de una transición que los exige. */
    public function familiaDeMotivos(TransicionDefinida $transicion): ?string
    {
        return $transicion->requiereMotivo ? "{$this->clave()}.{$transicion->codigo}" : null;
    }

    /**
     * Cómo se numera (plan 1.6, D5): `new FormatoNumeracion('REQ')` da `REQ-2026-0001`. Null: el documento no lleva
     * número propio (uno de terceros, como la factura del proveedor). Con numeración, la tabla del documento tiene la
     * columna `numero` (única) y `TransicionesService::crear` la llena al crearlo (D2).
     */
    public function numeracion(): ?FormatoNumeracion
    {
        return null;
    }

    /**
     * Si sus versiones comparten número (planes 2.9 y 2.22, D2: `PRE-2026-0001` v1, v2…; `COT-2026-0184` v1, v2…). La
     * tabla lleva `numero` y `numero_version`, únicos juntos. `TransicionesService::crear` numera la versión 1 y, en
     * las siguientes, deja el número que copió el servicio de su versión anterior (que tiene que existir).
     */
    public function versionesCompartenNumero(): bool
    {
        return false;
    }

    /** La fecha que decide el periodo del número (D3): la del documento si la tiene; por defecto, hoy. */
    public function fechaDeNumeracion(Model $documento): CarbonImmutable
    {
        return CarbonImmutable::now();
    }

    /**
     * El ámbito del contador (D4): vacío = toda la empresa; si no, el código del proyecto o contrato (`CT0003`), que va
     * dentro del número: `VAL-CT0003-2026-0001`.
     */
    public function ambitoDeNumeracion(Model $documento): string
    {
        return '';
    }

    /**
     * El monto que decide qué reglas de aprobación tocan (plan 1.7, D3), en su moneda. Un documento con un paso que
     * requiere aprobación lo tiene que declarar: con null, pedir la aprobación falla (si no, se aprobaría en el nivel más
     * bajo sin avisar).
     */
    public function montoParaAprobacion(Model $documento): ?MontoAprobable
    {
        return null;
    }

    /**
     * Las condiciones propias del documento que hacen entrar las reglas que las piden, además del monto (plan 2.22,
     * D14: «margen bajo el mínimo» en la cotización). Si se cumple una y no hay regla para ella, no se abre la ronda:
     * nunca pasa sin que alguien la vea aparte. El exceso de partida y la PC-99 salen de `lineasDeCosto()`.
     *
     * @return list<CondicionRegla>
     */
    public function condicionesDeAprobacion(Model $documento): array
    {
        return [];
    }

    /** La unidad del documento, para los pasos que firma «su jefatura» (plan 1.7, D1). */
    public function unidadDeAprobacion(Model $documento): ?int
    {
        return null;
    }

    /**
     * La transición del sistema que devuelve el documento a un estado editable cuando un firmante lo observa (plan 1.7,
     * D4). Null: el documento no se puede observar (solo firmar).
     */
    public function transicionAlObservar(string $transicionConAprobacion): ?string
    {
        return null;
    }

    /**
     * Las líneas con costo del documento (plan 2.2, D5; costura C12): las reglas de aprobación con la condición «exceso
     * de partida» entran solo si alguna excede. Un documento sin costo no las declara.
     *
     * @return list<LineaDeCosto>
     */
    public function lineasDeCosto(Model $documento): array
    {
        return [];
    }

    /** Cómo se nombra un documento en los mensajes: su número, o el id mientras no tenga (`#15`). */
    public function identificador(Model $documento): string
    {
        $numero = $this->numeracion() === null ? null : $documento->getAttribute('numero');

        return is_string($numero) && $numero !== '' ? $numero : '#'.$documento->getKey();
    }

    public function transicion(string $codigo): ?TransicionDefinida
    {
        foreach ($this->transiciones() as $transicion) {
            if ($transicion->codigo === $codigo) {
                return $transicion;
            }
        }

        return null;
    }
}
