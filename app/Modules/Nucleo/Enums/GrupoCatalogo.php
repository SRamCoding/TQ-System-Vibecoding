<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Enums;

/**
 * Los grupos en que se muestran los catálogos en Configuración → Catálogos (plan 1.4j, D3). El orden de los casos es
 * el de la pantalla.
 */
enum GrupoCatalogo: string
{
    case ComercioYPagos = 'comercio_y_pagos';
    case PersonasYLugares = 'personas_y_lugares';
    case ProyectosYObras = 'proyectos_y_obras';
    case DocumentosYArchivos = 'documentos_y_archivos';
    case CalendarioYSaludo = 'calendario_y_saludo';

    public function etiqueta(): string
    {
        return match ($this) {
            self::ComercioYPagos => 'Comercio y pagos',
            self::PersonasYLugares => 'Personas y lugares',
            self::ProyectosYObras => 'Proyectos y obras',
            self::DocumentosYArchivos => 'Documentos y archivos',
            self::CalendarioYSaludo => 'Calendario y saludo',
        };
    }

    /**
     * Los catálogos del grupo, en el orden en que se muestran.
     *
     * @return list<Catalogo>
     */
    public function catalogos(): array
    {
        return match ($this) {
            self::ComercioYPagos => [Catalogo::Monedas, Catalogo::FormasPago, Catalogo::TiposDocumento, Catalogo::CentrosCosto, Catalogo::OrigenesComerciales],
            self::PersonasYLugares => [Catalogo::TiposDocumentoIdentidad, Catalogo::RolesParty, Catalogo::Rubros, Catalogo::NivelesGobierno, Catalogo::RedesSociales, Catalogo::Ubigeos],
            self::ProyectosYObras => [Catalogo::LineasSolucion, Catalogo::Cubso, Catalogo::UnidadesMedida, Catalogo::TiposTrabajo, Catalogo::TiposPenalidad],
            self::DocumentosYArchivos => [Catalogo::TiposAdjunto, Catalogo::Aplicaciones],
            self::CalendarioYSaludo => [Catalogo::Feriados, Catalogo::FrasesSaludo, Catalogo::CategoriasFrase],
        };
    }
}
