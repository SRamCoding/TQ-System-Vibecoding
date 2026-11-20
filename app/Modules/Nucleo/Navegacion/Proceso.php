<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Navegacion;

/**
 * Las 11 cajas del inicio (MV-016), una por proceso, más la de Mien. Cada una agrupa pantallas que ya existen (las
 * cajas que registra cada módulo, aquí llamadas «cajas internas»): este enum es el único lugar donde se decide qué
 * pantalla va en qué proceso, en qué orden y con qué nombre. Un módulo registra su caja como siempre; si no está en
 * ningún proceso, no aparece en el inicio.
 *
 * Al abrir un proceso se ve su portada, con sus cajas internas (MV-020); dentro de una pantalla interna, pestañas con
 * sus hermanas y la miga «Proceso › Pantalla».
 */
enum Proceso: string
{
    case Mien = 'mien';
    case Techquk = 'techquk';
    case ConseguirNegocio = 'conseguir-negocio';
    case GerenciaProyectos = 'gerencia-proyectos';
    case Abastecer = 'abastecer';
    case FacturacionCobranza = 'facturacion-cobranza';
    case Postventa = 'postventa';
    case PresupuestarCotizar = 'presupuestar-cotizar';
    case Finanzas = 'finanzas';
    case Personas = 'personas';
    case Directorio = 'directorio';
    case Sistema = 'sistema';

    public function nombre(): string
    {
        return match ($this) {
            self::Mien => 'Mien',
            self::Techquk => 'Techquk',
            self::ConseguirNegocio => 'Conseguir el negocio',
            self::GerenciaProyectos => 'Gerencia de proyectos',
            self::Abastecer => 'Abastecer',
            self::FacturacionCobranza => 'Facturación y cobranza',
            self::Postventa => 'Postventa',
            self::PresupuestarCotizar => 'Presupuestar y cotizar',
            self::Finanzas => 'Finanzas',
            self::Personas => 'Personas',
            self::Directorio => 'Directorio y catálogos',
            self::Sistema => 'Sistema',
        };
    }

    public function franja(): Franja
    {
        return match ($this) {
            self::Mien => Franja::Personal,
            self::Techquk => Franja::Estrategicos,
            self::ConseguirNegocio, self::GerenciaProyectos, self::Abastecer, self::FacturacionCobranza, self::Postventa => Franja::Misionales,
            self::PresupuestarCotizar, self::Finanzas, self::Personas, self::Directorio => Franja::Apoyo,
            self::Sistema => Franja::Plataforma,
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::Mien => 'heroicon-o-star',
            self::Techquk => 'heroicon-o-flag',
            self::ConseguirNegocio => 'heroicon-o-rocket-launch',
            self::GerenciaProyectos => 'heroicon-o-briefcase',
            self::Abastecer => 'heroicon-o-truck',
            self::FacturacionCobranza => 'heroicon-o-currency-dollar',
            self::Postventa => 'heroicon-o-hand-thumb-up',
            self::PresupuestarCotizar => 'heroicon-o-clipboard-document-check',
            self::Finanzas => 'heroicon-o-wallet',
            self::Personas => 'heroicon-o-face-smile',
            self::Directorio => 'heroicon-o-book-open',
            self::Sistema => 'heroicon-o-cog-8-tooth',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::Mien => 'Cómo va tu trabajo de un vistazo: tus pendientes, tus aprobaciones y el tablero de tu rol.',
            self::Techquk => 'Cuánto se ganó, el presupuesto de la empresa, lo aprendido y las reglas del negocio',
            self::ConseguirNegocio => 'Oportunidades, convocatorias del SEACE y campañas',
            self::GerenciaProyectos => 'Proyectos, contratos, personal asignado, tareo y subcontratos',
            self::Abastecer => 'Requerimientos, órdenes, almacén y herramientas',
            self::FacturacionCobranza => 'Valorizaciones, facturas emitidas y cobranzas',
            self::Postventa => 'Tickets, series y suscripciones',
            self::PresupuestarCotizar => 'Presupuestos y cotizaciones',
            self::Finanzas => 'Facturas de proveedor, pagos, caja, rendiciones y lo del contador',
            self::Personas => 'Fichas laborales, demanda y selección, y planilla',
            self::Directorio => 'Personas y empresas, productos, listas y organigrama',
            self::Sistema => 'Usuarios, configuración, solicitudes de permiso y tickets de ayuda',
        };
    }

    public function animacion(): AnimacionCaja
    {
        return match ($this) {
            self::Mien => AnimacionCaja::Camara,
            self::Directorio => AnimacionCaja::Apreton,
            self::Sistema => AnimacionCaja::Rueda,
            default => AnimacionCaja::Pulso,
        };
    }

    /**
     * Las cajas internas del proceso, por su clave, en su orden; con nombre, el que se muestra en vez del que registró
     * su módulo (MV-016 dice los nombres exactos).
     *
     * @return array<string, string|null>
     */
    public function cajas(): array
    {
        return match ($this) {
            self::Mien => ['mien' => null],
            self::Techquk => ['resultados' => '¿Cuánto se ganó?', 'presupuesto-empresa' => 'Presupuesto de la empresa', 'lo-aprendido' => 'Lo aprendido', 'reglas-negocio' => 'Reglas del negocio'],
            self::ConseguirNegocio => ['oportunidades' => 'Oportunidades', 'convocatorias' => 'Convocatorias del SEACE', 'campanias' => 'Campañas'],
            self::GerenciaProyectos => ['proyectos' => 'Proyectos', 'contratos' => 'Contratos', 'asignacion' => 'Personal asignado', 'tareo' => 'Tareo', 'subcontratos' => 'Subcontratos'],
            self::Abastecer => ['requerimientos' => 'Requerimientos', 'ordenes' => 'Órdenes', 'almacen' => 'Almacén', 'herramientas' => 'Herramientas'],
            self::FacturacionCobranza => ['valorizaciones' => 'Valorizaciones', 'facturas-emitidas' => 'Facturas emitidas', 'cobranzas' => 'Cobranzas'],
            self::Postventa => ['postventa' => 'Tickets y series', 'suscripciones' => 'Suscripciones'],
            self::PresupuestarCotizar => ['presupuestos' => 'Presupuestos', 'cotizaciones' => 'Cotizaciones'],
            self::Finanzas => ['facturas-proveedor' => 'Facturas de proveedor', 'tablero-pagos' => 'Tablero de pagos', 'flujo-caja' => 'Flujo de caja y conciliación', 'rendiciones' => 'Rendiciones', 'servicios-vigencia' => 'Servicios con vigencia', 'exportar-contador' => 'Para el contador'],
            self::Personas => ['fichas-laborales' => 'Fichas laborales', 'vacantes' => 'Demanda y selección', 'planilla' => 'Planilla'],
            self::Directorio => ['personas' => 'Personas y empresas', 'catalogo' => 'Catálogo de productos', 'listas' => 'Listas', 'organigrama' => 'Organigrama'],
            self::Sistema => ['usuarios' => 'Usuarios', 'configuracion' => 'Configuración', 'solicitudes-permiso' => 'Solicitudes de permiso', 'tickets-ayuda' => 'Tickets de ayuda'],
        };
    }

    /**
     * Pantallas sin caja propia que viven dentro de otra (MV-023): su ruta cuenta como la de esa caja para las pestañas y
     * la miga. «Por calificar» (los leads) y «Licitaciones» son pestañas de la portada de Oportunidades.
     *
     * @return array<string, string> prefijo de ruta => clave de la caja
     */
    public static function rutasDentroDe(): array
    {
        return ['/leads' => 'oportunidades', '/licitaciones' => 'oportunidades'];
    }

    public function url(): string
    {
        return $this === self::Mien ? '/mien' : '/procesos/'.$this->value;
    }
}
