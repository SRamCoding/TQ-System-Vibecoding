<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\RegistroAcciones;

/**
 * Tipos de campo de una acción registrada. Se agrega uno cuando una acción lo necesita.
 */
enum TipoCampo: string
{
    case Texto = 'texto';
    case Entero = 'entero';
    /** Montos y cantidades: llegan y salen como string, nunca float. */
    case Decimal = 'decimal';
    /** Fecha de negocio (Y-m-d), en la zona de la aplicación. */
    case Fecha = 'fecha';
    case Booleano = 'booleano';
    /** Un caso de un enum respaldado. */
    case Opcion = 'opcion';
    /** Un elemento de un catálogo de M5 (Catalogo), por su id. */
    case ElementoCatalogo = 'elemento_catalogo';
    /** Varios elementos de un mismo catálogo, por sus ids, sin repetir (roles iniciales de una party). */
    case ListaCatalogo = 'lista_catalogo';
    /** Varios registros de una tabla que no es catálogo, por sus ids, sin repetir (los permisos de un rol). */
    case ListaIds = 'lista_ids';
    /** Varios textos cortos, sin repetir (las claves de los módulos ocultos de la barra, plan 1.4f). */
    case ListaTextos = 'lista_textos';
    /**
     * Un motivo de una familia (plan 2.21, D7.3b), por su id. Va con su campo de detalle, que se pide con «Otro».
     */
    case Motivo = 'motivo';
    case Archivo = 'archivo';
}
