<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Enums;

use App\Modules\Nucleo\Models\Aplicacion;
use App\Modules\Nucleo\Models\CategoriaFrase;
use App\Modules\Nucleo\Models\CentroCosto;
use App\Modules\Nucleo\Models\Concerns\ElementoCatalogo;
use App\Modules\Nucleo\Models\Cubso;
use App\Modules\Nucleo\Models\Feriado;
use App\Modules\Nucleo\Models\FormaPago;
use App\Modules\Nucleo\Models\FraseSaludo;
use App\Modules\Nucleo\Models\LineaSolucion;
use App\Modules\Nucleo\Models\Moneda;
use App\Modules\Nucleo\Models\NivelGobierno;
use App\Modules\Nucleo\Models\OrigenComercial;
use App\Modules\Nucleo\Models\RedSocial;
use App\Modules\Nucleo\Models\RolParty;
use App\Modules\Nucleo\Models\Rubro;
use App\Modules\Nucleo\Models\TipoAdjunto;
use App\Modules\Nucleo\Models\TipoDocumento;
use App\Modules\Nucleo\Models\TipoDocumentoIdentidad;
use App\Modules\Nucleo\Models\TipoPenalidad;
use App\Modules\Nucleo\Models\TipoTrabajo;
use App\Modules\Nucleo\Models\Ubigeo;
use App\Modules\Nucleo\Models\UnidadMedida;
use App\Modules\Nucleo\Navegacion\AreaModulo;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;

/**
 * Los catálogos compartidos de M5, más los dos de identidad de M1 (plan 1.3, D3). Los demás módulos los piden por este nombre a CatalogosService y
 * nunca tocan los modelos del núcleo (regla 2). Un catálogo nuevo: un caso aquí, su modelo con EsCatalogo
 * y sus campos editables abajo.
 *
 * Los campos se llaman como sus columnas: las acciones de edición los declaran tal cual y el servicio
 * los guarda sin traducir (plan 1.2b, E5).
 */
enum Catalogo: string
{
    /** Códigos SUNAT (catálogo 06) que usa el código del sistema. */
    public const string DNI = '1';

    public const string RUC = '6';

    case Monedas = 'monedas';
    case UnidadesMedida = 'unidades_medida';
    case Ubigeos = 'ubigeos';
    case TiposDocumento = 'tipos_documento';
    case FormasPago = 'formas_pago';
    case LineasSolucion = 'lineas_solucion';
    case Cubso = 'cubso';
    case TiposPenalidad = 'tipos_penalidad';
    case TiposTrabajo = 'tipos_trabajo';
    case TiposDocumentoIdentidad = 'tipos_documento_identidad';
    case RolesParty = 'roles_party';
    case CategoriasFrase = 'categorias_frase';
    case FrasesSaludo = 'frases_saludo';
    /** Feriados y días no laborables (plan 1.4d, D2): el código es la fecha. */
    case Feriados = 'feriados';
    case TiposAdjunto = 'tipos_adjunto';
    case CentrosCosto = 'centros_costo';
    /** Redes sociales de las parties, sus contactos y el equipo (plan 1.4o, D1). */
    case RedesSociales = 'redes_sociales';
    /** Las aplicaciones web del panel de aplicaciones, para todos (plan 1.4p, D7). */
    case Aplicaciones = 'aplicaciones';
    /** Plan 2.20a, N3: de dónde llega una cuenta, un lead o una oportunidad. */
    case OrigenesComerciales = 'origenes_comerciales';
    case NivelesGobierno = 'niveles_gobierno';
    /** Las 21 secciones de la CIIU Rev. 4: cerrado (plan 2.20a, N2). */
    case Rubros = 'rubros';

    /**
     * @return class-string<Model&ElementoCatalogo>
     */
    public function modelo(): string
    {
        return match ($this) {
            self::Monedas => Moneda::class,
            self::UnidadesMedida => UnidadMedida::class,
            self::Ubigeos => Ubigeo::class,
            self::TiposDocumento => TipoDocumento::class,
            self::FormasPago => FormaPago::class,
            self::LineasSolucion => LineaSolucion::class,
            self::Cubso => Cubso::class,
            self::TiposPenalidad => TipoPenalidad::class,
            self::TiposTrabajo => TipoTrabajo::class,
            self::TiposDocumentoIdentidad => TipoDocumentoIdentidad::class,
            self::RolesParty => RolParty::class,
            self::CategoriasFrase => CategoriaFrase::class,
            self::FrasesSaludo => FraseSaludo::class,
            self::Feriados => Feriado::class,
            self::TiposAdjunto => TipoAdjunto::class,
            self::CentrosCosto => CentroCosto::class,
            self::RedesSociales => RedSocial::class,
            self::Aplicaciones => Aplicacion::class,
            self::OrigenesComerciales => OrigenComercial::class,
            self::NivelesGobierno => NivelGobierno::class,
            self::Rubros => Rubro::class,
        };
    }

    /** Para mensajes: «el catálogo de …». */
    public function nombre(): string
    {
        return match ($this) {
            self::Monedas => 'monedas',
            self::UnidadesMedida => 'unidades de medida',
            self::Ubigeos => 'ubigeos',
            self::TiposDocumento => 'tipos de documento',
            self::FormasPago => 'formas de pago',
            self::LineasSolucion => 'líneas de solución',
            self::Cubso => 'CUBSO',
            self::TiposPenalidad => 'tipos de penalidad',
            self::TiposTrabajo => 'tipos de trabajo',
            self::TiposDocumentoIdentidad => 'tipos de documento de identidad',
            // «Tipos», no «roles»: no son los roles de seguridad de las cuentas (pedido del desarrollador, 2026-10-23).
            self::RolesParty => 'tipos de persona y empresa',
            self::CategoriasFrase => 'categorías de frases del saludo',
            self::FrasesSaludo => 'frases del saludo',
            self::Feriados => 'feriados y días no laborables',
            self::TiposAdjunto => 'tipos de adjunto',
            self::CentrosCosto => 'centros de costo',
            self::RedesSociales => 'redes sociales',
            self::Aplicaciones => 'aplicaciones del panel',
            self::OrigenesComerciales => 'orígenes comerciales',
            self::NivelesGobierno => 'niveles de gobierno',
            self::Rubros => 'rubros',
        };
    }

    /** Nombre de su caja y de su panel en Configuración → Catálogos. */
    public function titulo(): string
    {
        return mb_ucfirst($this->nombre());
    }

    /** Ícono de su caja (plan 1.4j, D2). Ninguno se repite con otro catálogo ni con un módulo o pantalla (prueba). */
    public function icono(): string
    {
        return match ($this) {
            self::Monedas => 'heroicon-o-banknotes',
            self::UnidadesMedida => 'heroicon-o-scale',
            self::Ubigeos => 'heroicon-o-map-pin',
            self::TiposDocumento => 'heroicon-o-document-text',
            self::FormasPago => 'heroicon-o-credit-card',
            self::LineasSolucion => 'heroicon-o-puzzle-piece',
            self::Cubso => 'heroicon-o-tag',
            self::TiposPenalidad => 'heroicon-o-exclamation-triangle',
            self::TiposTrabajo => 'heroicon-o-wrench-screwdriver',
            self::TiposDocumentoIdentidad => 'heroicon-o-finger-print',
            self::RolesParty => 'tq-persona-edificio',
            self::CategoriasFrase => 'heroicon-o-rectangle-stack',
            self::FrasesSaludo => 'tq-mano-paz',
            self::Feriados => 'heroicon-o-calendar-days',
            self::TiposAdjunto => 'heroicon-o-paper-clip',
            self::CentrosCosto => 'heroicon-o-chart-pie',
            self::RedesSociales => 'tq-redes-sociales',
            self::Aplicaciones => 'heroicon-o-rectangle-group',
            self::OrigenesComerciales => 'heroicon-o-inbox-arrow-down',
            self::NivelesGobierno => 'heroicon-o-globe-americas',
            self::Rubros => 'heroicon-o-view-columns',
        };
    }

    /** Qué reúne, para el tooltip de su caja (plan 1.4j, D2). */
    public function descripcion(): string
    {
        return match ($this) {
            self::Monedas => 'Monedas con las que se cotiza y se paga',
            self::UnidadesMedida => 'Unidades de cantidad: unidad, metro, kilo…',
            self::Ubigeos => 'Departamentos, provincias y distritos (INEI)',
            self::TiposDocumento => 'Comprobantes: factura, boleta, guía… (SUNAT)',
            self::FormasPago => 'Contado, crédito, plazos',
            self::LineasSolucion => 'Qué vende Techquk, por línea',
            self::Cubso => 'Códigos de bienes y servicios del Estado',
            self::TiposPenalidad => 'Penalidades de los contratos',
            self::TiposTrabajo => 'Clases de trabajo en obra',
            self::TiposDocumentoIdentidad => 'DNI, RUC, carné de extranjería… (SUNAT)',
            self::RolesParty => 'Cliente, proveedor, entidad, personal…',
            self::CategoriasFrase => 'Grupos de frases del saludo',
            self::FrasesSaludo => 'Frases y versículos del inicio',
            self::Feriados => 'Feriados y días no laborables',
            self::TiposAdjunto => 'Clases de archivo adjunto: cotización, orden firmada…',
            self::CentrosCosto => 'A dónde va el gasto directo de la empresa',
            self::RedesSociales => 'LinkedIn, Facebook, sitio web… con su ícono',
            self::Aplicaciones => 'Word, Excel, PowerPoint… del panel de aplicaciones de todos',
            self::OrigenesComerciales => 'De dónde llegan las cuentas, los leads y las oportunidades',
            self::NivelesGobierno => 'Gobierno local, regional o nacional de una entidad pública',
            self::Rubros => 'A qué se dedica una empresa: secciones de la CIIU Rev. 4 (SUNAT)',
        };
    }

    /** Su grupo en la pantalla (plan 1.4j, D3). */
    public function grupo(): GrupoCatalogo
    {
        foreach (GrupoCatalogo::cases() as $grupo) {
            if (in_array($this, $grupo->catalogos(), true)) {
                return $grupo;
            }
        }

        throw new LogicException("El catálogo [{$this->value}] no está en ningún grupo de GrupoCatalogo.");
    }

    /**
     * Columnas por las que se busca en la pantalla, además del código.
     *
     * @return list<string>
     */
    public function columnasBusqueda(): array
    {
        return match ($this) {
            self::Ubigeos => ['distrito', 'provincia', 'departamento'],
            self::Cubso => ['descripcion'],
            self::FrasesSaludo => ['texto', 'fuente'],
            default => ['nombre'],
        };
    }

    /**
     * El código con que lo busca el sistema. Se escribe al crear y no cambia (plan 1.2b, E8).
     */
    public function campoCodigo(): Campo
    {
        return match ($this) {
            self::Monedas => Campo::texto('codigo', 'Código ISO', maximo: 3)->conReglas('regex:/^[A-Z]{3}$/'),
            self::UnidadesMedida => Campo::texto('codigo', 'Código SUNAT', maximo: 10),
            self::Ubigeos => Campo::texto('codigo', 'Código INEI', maximo: 6)->conReglas('regex:/^[0-9]{6}$/'),
            self::TiposDocumento => Campo::texto('codigo', 'Código SUNAT', maximo: 3),
            self::Cubso => Campo::texto('codigo', 'Código CUBSO', maximo: 20),
            self::FormasPago, self::LineasSolucion, self::TiposPenalidad, self::TiposTrabajo => Campo::texto('codigo', 'Código', maximo: 20),
            self::TiposDocumentoIdentidad => Campo::texto('codigo', 'Código SUNAT', maximo: 3)->conReglas('regex:/^[0-9A-Z]{1,3}$/'),
            // En minúsculas: es lo que escribe el código (`cliente`, `proveedor`).
            self::RolesParty, self::CategoriasFrase => Campo::texto('codigo', 'Código', maximo: 20)->conReglas('regex:/^[a-z][a-z0-9_]*$/'),
            self::FrasesSaludo => Campo::texto('codigo', 'Código', maximo: 40)->conReglas('regex:/^[a-z0-9][a-z0-9-]*$/'),
            self::Feriados => Campo::texto('codigo', 'Fecha (AAAA-MM-DD)', maximo: 10)->conReglas('regex:/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/'),
            self::TiposAdjunto, self::RedesSociales, self::Aplicaciones => Campo::texto('codigo', 'Código', maximo: 20)->conReglas('regex:/^[a-z][a-z0-9_]*$/'),
            self::CentrosCosto => Campo::texto('codigo', 'Código (mayúsculas, como ADM)', maximo: 20)->conReglas('regex:/^[A-Z][A-Z0-9]*$/'),
            self::OrigenesComerciales, self::NivelesGobierno => Campo::texto('codigo', 'Código (mayúsculas, como FERIA)', maximo: 20)->conReglas('regex:/^[A-Z][A-Z0-9_]*$/'),
            self::Rubros => Campo::texto('codigo', 'Sección CIIU (A a U)', maximo: 1)->conReglas('regex:/^[A-U]$/'),
        };
    }

    /**
     * El código de su elemento «Otro», en los catálogos que lo tienen (plan 2.0, D5): quien lo elige escribe cuál es, y
     * esos detalles van a «Lo más escrito en Otro» para que Administración agregue lo que se repite. Es del sistema: no
     * se borra ni se desactiva.
     */
    public function codigoOtro(): ?string
    {
        return match ($this) {
            self::OrigenesComerciales => 'OTRO',
            self::RedesSociales => 'otra',
            default => null,
        };
    }

    /**
     * El código que se sugiere desde el nombre al agregar con el «+» de un selector (plan 2.0, D2): con el formato de
     * `campoCodigo()` («Convocatoria privada» → `CONVOCATORIA_PRIVADA`, `convocatoria_privada` o `convocatoria-privada`).
     * Nulo en los de código oficial (ISO, INEI, SUNAT, CUBSO, CIIU, fechas): ese se escribe tal cual.
     */
    public function codigoDesdeNombre(string $nombre): ?string
    {
        $palabras = trim((string) preg_replace('/[^a-z0-9]+/', ' ', strtolower(Str::ascii($nombre))));

        if ($palabras === '') {
            return null;
        }

        $codigo = match ($this) {
            self::Monedas, self::UnidadesMedida, self::Ubigeos, self::TiposDocumento, self::Cubso,
            self::TiposDocumentoIdentidad, self::Feriados, self::Rubros => null,
            self::CentrosCosto => strtoupper(str_replace(' ', '', $palabras)),
            self::RolesParty, self::CategoriasFrase, self::TiposAdjunto, self::RedesSociales, self::Aplicaciones => str_replace(' ', '_', $palabras),
            self::FrasesSaludo => str_replace(' ', '-', $palabras),
            default => strtoupper(str_replace(' ', '_', $palabras)),
        };

        if ($codigo === null) {
            return null;
        }

        // El código empieza con letra (salvo las frases): «3 cuotas» → C3_CUOTAS o c3_cuotas.
        if ($this !== self::FrasesSaludo && preg_match('/^[a-z]/i', $codigo) !== 1) {
            $codigo = ($codigo === strtoupper($codigo) ? 'C' : 'c').$codigo;
        }

        return rtrim(substr($codigo, 0, $this === self::FrasesSaludo ? 40 : 20), '_-');
    }

    /**
     * Lo que se escribe al crear y se cambia al editar, sin el código ni la columna de activo (tienen sus
     * propias acciones). Incluye los sensibles.
     *
     * @return list<Campo>
     */
    public function campos(): array
    {
        return match ($this) {
            self::Monedas => [
                Campo::texto('nombre', 'Nombre', maximo: 40),
                Campo::texto('simbolo', 'Símbolo', maximo: 5),
                Campo::entero('decimales', 'Decimales')->conReglas('min:0', 'max:4'),
            ],
            self::UnidadesMedida => [
                Campo::texto('nombre', 'Nombre', maximo: 60),
                Campo::texto('abreviatura', 'Abreviatura', maximo: 10),
                // Plan 2.9: con qué código se factura (GLB → ZZ). Vacío al crear: el propio código.
                Campo::texto('codigo_sunat', 'Código SUNAT al facturar', maximo: 10)->opcional(),
                Campo::booleano('permite_decimales', 'Permite decimales'),
            ],
            self::Ubigeos => [
                Campo::texto('departamento', 'Departamento', maximo: 60),
                Campo::texto('provincia', 'Provincia', maximo: 60),
                Campo::texto('distrito', 'Distrito', maximo: 60),
            ],
            self::TiposDocumento => [
                Campo::texto('nombre', 'Nombre', maximo: 80),
                Campo::booleano('es_electronico', 'Es electrónico'),
                Campo::booleano('afecta_cuenta_corriente', 'Afecta la cuenta corriente'),
                Campo::entero('signo', 'Signo (1 suma, -1 resta)')->conReglas('in:1,-1'),
            ],
            self::FormasPago => [
                Campo::texto('nombre', 'Nombre', maximo: 80),
                Campo::entero('dias', 'Días de crédito (vacío: contado)')->opcional()->conReglas('min:0', 'max:32767'),
            ],
            self::Cubso => [
                Campo::texto('descripcion', 'Descripción', maximo: 500),
                Campo::elementoDe('unidad_medida_id', 'Unidad de medida', self::UnidadesMedida)->opcional(),
                Campo::texto('version_catalogo', 'Versión del catálogo CUBSO', maximo: 20)->opcional(),
            ],
            self::LineasSolucion, self::TiposPenalidad => [
                Campo::texto('nombre', 'Nombre', maximo: 80),
            ],
            self::TiposTrabajo => [
                Campo::texto('nombre', 'Nombre', maximo: 60),
                Campo::booleano('es_facturable_default', 'Facturable por defecto'),
            ],
            self::TiposDocumentoIdentidad => [
                Campo::texto('nombre', 'Nombre', maximo: 60),
                Campo::entero('longitud', 'Longitud exacta (vacío: sin longitud fija)')->opcional()->conReglas('min:1', 'max:20'),
            ],
            self::RolesParty => [
                Campo::texto('nombre', 'Nombre', maximo: 60),
                Campo::texto('descripcion', 'Descripción', maximo: 255)->opcional(),
            ],
            self::CategoriasFrase => [
                Campo::texto('nombre', 'Nombre', maximo: 60),
            ],
            // Lo que no puede ir junto (un saludo con categoría, un saludo sin {nombre}) lo rechaza la base.
            self::FrasesSaludo => [
                Campo::texto('texto', 'Texto (un saludo lleva {nombre})', maximo: 300),
                Campo::opcion('tipo', 'Tipo', TipoFraseSaludo::class),
                Campo::elementoDe('categoria_frase_id', 'Categoría (solo frases)', self::CategoriasFrase)->opcional()->conReglas('present'),
                Campo::opcion('idioma', 'Idioma', IdiomaSaludo::class),
                Campo::texto('fuente', 'Fuente (autor, «Refrán», o versículo con versión: «Proverbios 16:3 (RVR1960)»)', maximo: 120)->opcional()->conReglas('present'),
                Campo::opcion('momento', 'Solo en este momento del día', MomentoDia::class)->opcional()->conReglas('present'),
                // Plan 1.4e: vacío, del horario normal; si no, solo fuera de horario y en esa situación.
                Campo::opcion('fuera_de_horario', 'Solo fuera de horario', FueraDeHorario::class)->opcional()->conReglas('present'),
                // Plan 1.4aa, D8: vacío, cualquier sesión; si no, solo en esa clase de sesión (solo saludos, en horario).
                Campo::opcion('sesion', 'Solo en esta sesión (solo saludos)', SesionSaludo::class)->opcional()->conReglas('present'),
                Campo::entero('dia_semana', 'Solo este día (1 lunes … 7 domingo)')->opcional()->conReglas('present', 'min:1', 'max:7'),
                Campo::opcion('area', 'Solo para esta área', AreaModulo::class)->opcional()->conReglas('present', 'in:crm,erp'),
                Campo::opcion('ocasion', 'Solo en esta fecha especial', Ocasion::class)->opcional()->conReglas('present'),
            ],
            // Un feriado nacional o un día libre de la empresa nunca se trabaja: lo exige la base.
            self::Feriados => [
                Campo::texto('nombre', 'Nombre', maximo: 80),
                Campo::opcion('tipo', 'Tipo', TipoFeriado::class),
                Campo::booleano('techquk_no_trabaja', 'Techquk no trabaja ese día'),
            ],
            self::CentrosCosto, self::Rubros => [
                Campo::texto('nombre', 'Nombre', maximo: 120),
            ],
            self::OrigenesComerciales, self::NivelesGobierno => [
                Campo::texto('nombre', 'Nombre', maximo: 80),
            ],
            // Plan 1.8, D4: vacíos, los parámetros `adjuntos.*`; el tipo de registro, vacío, cualquiera.
            self::TiposAdjunto => [
                Campo::texto('nombre', 'Nombre', maximo: 80),
                Campo::texto('documento_type', 'Solo para este tipo de registro (vacío: cualquiera)', maximo: 120)->opcional()->conReglas('present', 'regex:/^[a-z][a-z0-9_]*$/'),
                Campo::texto('extensiones', 'Extensiones (vacío: las de los parámetros)', maximo: 150)->opcional()->conReglas('present', 'regex:/^[a-z0-9]+(,[a-z0-9]+)*$/'),
                Campo::entero('tamano_maximo_mb', 'Tamaño máximo en MB (vacío: el de los parámetros)')->opcional()->conReglas('present', 'min:1', 'max:1024'),
                Campo::booleano('es_obligatorio', 'Obligatorio'),
                Campo::opcion('grupo_sensible', 'Sensible: solo lo ve quien tiene este permiso', GrupoSensible::class)->opcional()->conReglas('present'),
            ],
            // Plan 1.4o, D1: un enlace a la red debe ser de uno de sus dominios o de un subdominio; vacío, cualquiera.
            self::RedesSociales => [
                Campo::texto('nombre', 'Nombre', maximo: 40),
                Campo::texto('dominios', 'Dominios, separados por comas (vacío: cualquiera, como un sitio web)', maximo: 200)->opcional()->conReglas('present', 'regex:/^[a-z0-9-]+(\\.[a-z0-9-]+)+(,[a-z0-9-]+(\\.[a-z0-9-]+)+)*$/'),
                Campo::opcion('icono', 'Ícono', IconoRed::class),
            ],
            // Plan 1.4p: solo `https://`, como exige la base. El orden es el de la cuadrícula y, sin uso, el del muelle (D5).
            self::Aplicaciones => [
                Campo::texto('nombre', 'Nombre', maximo: 40),
                Campo::texto('enlace', 'Enlace (https://…)', maximo: 300)->conReglas('regex:/^https:\/\/[^\s\/]+\.[^\s]+$/'),
                Campo::opcion('icono', 'Ícono', IconoAplicacion::class),
                // Plan 1.4z, D6: su programa de escritorio, de la lista cerrada.
                Campo::opcion('escritorio', 'Programa de escritorio (si lo tiene)', ProgramaEscritorio::class)->opcional(),
                Campo::entero('orden', 'Orden en el panel (el menor va primero)')->conReglas('min:0', 'max:32767'),
            ],
        };
    }

    /**
     * Campos que mueven dinero: solo los cambia quien tiene `catalogos.editar-sensibles` (plan 1.2b, E6).
     *
     * @return list<string>
     */
    public function camposSensibles(): array
    {
        return match ($this) {
            self::Monedas => ['decimales'],
            self::TiposDocumento => ['afecta_cuenta_corriente', 'signo'],
            default => [],
        };
    }

    /**
     * Los campos que cambia la edición general: todos menos los sensibles.
     *
     * @return list<string>
     */
    public function camposNoSensibles(): array
    {
        return array_values(array_diff(array_map(fn (Campo $campo): string => $campo->nombre, $this->campos()), $this->camposSensibles()));
    }

    public function tieneSensibles(): bool
    {
        return $this->camposSensibles() !== [];
    }

    /**
     * Códigos que usa el código del sistema: no se desactivan ni se borran desde la pantalla (plan 1.3, D3).
     * La moneda local no está aquí: sale de un parámetro (EdicionCatalogosService la suma).
     *
     * @return list<string>
     */
    public function codigosDelSistema(): array
    {
        $otro = $this->codigoOtro() === null ? [] : [$this->codigoOtro()];

        return [...$otro, ...match ($this) {
            self::TiposDocumentoIdentidad => [self::DNI, self::RUC],
            self::RolesParty => ['cliente', 'proveedor', 'entidad', 'colaborador', 'banco'],
            // Plan 1.4z, D9: lo usa el ícono subido de las aplicaciones.
            self::TiposAdjunto => ['icono_aplicacion'],
            // Plan 2.20a, N3: las 21 secciones de la CIIU.
            self::Rubros => self::seccionesCiiu(),
            default => [],
        }];
    }

    /**
     * Cerrado (plan 2.20a, N2): desde la pantalla no se crea ni se borra; solo se edita lo editable. Sus elementos son
     * los del sistema, y vienen de una lista oficial.
     */
    public function esCerrado(): bool
    {
        return $this === self::Rubros;
    }

    /** @return list<string> las letras de `database/data/rubros-ciiu.php` */
    public static function seccionesCiiu(): array
    {
        return array_keys(self::datosCiiu());
    }

    /**
     * Las secciones de la CIIU: letra => [nombre abreviado, nombre oficial].
     *
     * @return array<string, array{string, string}>
     */
    public static function datosCiiu(): array
    {
        /** @var array<string, array{string, string}> */
        return require dirname(__DIR__).'/database/data/rubros-ciiu.php';
    }

    /**
     * Campos que no cambian en los códigos del sistema: la longitud de DNI y RUC es la de SUNAT, fija en
     * DocumentoIdentidad (plan 1.3, D3).
     *
     * @return list<string>
     */
    public function camposFijosDelSistema(): array
    {
        return match ($this) {
            self::TiposDocumentoIdentidad => ['longitud'],
            // Plan 1.4z: el ícono de aplicación es solo PNG o WEBP, de hasta 1 MB, y solo de aplicaciones.
            self::TiposAdjunto => ['documento_type', 'extensiones', 'tamano_maximo_mb'],
            default => [],
        };
    }

    /**
     * Columnas que no se editan en el catálogo porque las pone un servicio (plan 1.4z, D9: el ícono subido de una
     * aplicación lo pone IconosAplicacionService). `CatalogoDefinicionTest` las descuenta de las editables.
     *
     * @return list<string>
     */
    public function columnasDeServicio(): array
    {
        return match ($this) {
            self::Aplicaciones => ['icono_adjunto_id'],
            default => [],
        };
    }

    public function esDelSistema(string $codigo): bool
    {
        return in_array($codigo, $this->codigosDelSistema(), true);
    }
}
