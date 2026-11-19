<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Excepciones;

use App\Modules\Nucleo\Enums\Catalogo;

/** La edición de un elemento de catálogo no procede (plan 1.2b). */
final class ElementoCatalogoRechazado extends ErrorDeNegocio
{
    public static function noExiste(Catalogo $catalogo, string $codigo): self
    {
        return new self("«{$codigo}» no está en {$catalogo->nombre()}.");
    }

    /** Una regla de forma del elemento, con el mensaje ya escrito para quien lo edita. */
    public static function formato(string $motivo): self
    {
        return new self($motivo);
    }

    public static function codigoDuplicado(Catalogo $catalogo, string $codigo): self
    {
        return new self("Ya existe «{$codigo}» en {$catalogo->nombre()}. El código no se repite.");
    }

    /** E8: el código es inmutable; E6: los campos que mueven dinero tienen su propia acción. */
    public static function campoNoEditable(Catalogo $catalogo, string $campo): self
    {
        return new self("El campo «{$campo}» de {$catalogo->nombre()} no se cambia por esta vía.");
    }

    public static function referenciaInactiva(string $etiqueta): self
    {
        return new self("Lo elegido en «{$etiqueta}» no existe o está desactivado.");
    }

    public static function yaEstaEnEseEstado(Catalogo $catalogo, string $codigo, bool $activo): self
    {
        $estado = $activo ? 'activo' : 'desactivado';

        return new self("«{$codigo}» de {$catalogo->nombre()} ya está {$estado}.");
    }

    /** La moneda local la usa todo cálculo de tipo de cambio: no se borra ni se desactiva. */
    public static function monedaLocal(string $codigo): self
    {
        return new self("«{$codigo}» es la moneda local del sistema: no se puede borrar ni desactivar.");
    }

    /** Plan 2.20a, N2: un catálogo cerrado viene de una lista oficial; solo se edita su nombre. */
    public static function catalogoCerrado(Catalogo $catalogo): self
    {
        return new self(mb_ucfirst($catalogo->nombre()).' es una lista oficial cerrada: no se agregan ni se borran elementos, solo se cambia su nombre.');
    }

    /** Plan 1.3, D3: lo que usa el código del sistema (DNI, RUC, `cliente`…) no se borra ni se desactiva. */
    public static function delSistema(Catalogo $catalogo, string $codigo): self
    {
        return new self("«{$codigo}» de {$catalogo->nombre()} lo usa el sistema: no se puede borrar ni desactivar.");
    }

    /** Plan 1.3, D3: la longitud de DNI y RUC es la de SUNAT. */
    public static function campoFijoDelSistema(Catalogo $catalogo, string $codigo, string $campo): self
    {
        return new self("El campo «{$campo}» de «{$codigo}» en {$catalogo->nombre()} lo fija el sistema: no se cambia.");
    }

    /** E1: lo que ya está en algún documento no se borra; la base lo impide con su llave foránea. */
    public static function enUso(Catalogo $catalogo, string $codigo): self
    {
        return new self("«{$codigo}» de {$catalogo->nombre()} ya se usó en otros registros y no se puede borrar. Desactívalo para que no se elija más.");
    }
}
