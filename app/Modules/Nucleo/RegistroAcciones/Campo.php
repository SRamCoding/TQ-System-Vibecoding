<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\RegistroAcciones;

use App\Modules\Nucleo\Enums\Catalogo;
use BackedEnum;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;

/**
 * Un campo tipado de una acción: lo que el formulario muestra, lo que se valida y el tipo con el que
 * llega a ejecutar().
 */
final readonly class Campo
{
    /**
     * @param  list<mixed>  $reglas  reglas de Laravel propias del tipo y las que agregue la acción
     * @param  ?class-string<BackedEnum>  $enum
     */
    private function __construct(
        public string $nombre,
        public string $etiqueta,
        public TipoCampo $tipo,
        public bool $obligatorio = true,
        public array $reglas = [],
        public ?string $enum = null,
        /** Dato de control (la versión leída): lo llena la pantalla al abrir, oculto; no lo escribe la persona. */
        public bool $control = false,
        /** Catálogo de donde se elige, en un campo ElementoCatalogo o ListaCatalogo. */
        public ?Catalogo $catalogo = null,
        /** Tabla y columna visible de donde se elige, en un campo ListaIds. */
        public ?string $tabla = null,
        public ?string $columnaEtiqueta = null,
        /** Largo máximo de cada elemento, en un campo ListaTextos. */
        public ?int $maximoElemento = null,
        /** Código de la familia de motivos (`tipos_motivo.codigo`), en un campo Motivo. */
        public ?string $familiaMotivos = null,
        /** Nombre del campo de detalle que se pide con «Otro», en un campo Motivo. */
        public ?string $campoDetalle = null,
    ) {}

    public static function texto(string $nombre, string $etiqueta, int $maximo = 255): self
    {
        return new self($nombre, $etiqueta, TipoCampo::Texto, reglas: ['string', "max:{$maximo}"]);
    }

    public static function entero(string $nombre, string $etiqueta): self
    {
        return new self($nombre, $etiqueta, TipoCampo::Entero, reglas: ['integer']);
    }

    public static function decimal(string $nombre, string $etiqueta, int $escala = 2): self
    {
        return new self($nombre, $etiqueta, TipoCampo::Decimal, reglas: ['numeric', "decimal:0,{$escala}"]);
    }

    public static function fecha(string $nombre, string $etiqueta): self
    {
        return new self($nombre, $etiqueta, TipoCampo::Fecha, reglas: ['date_format:Y-m-d']);
    }

    public static function booleano(string $nombre, string $etiqueta): self
    {
        return new self($nombre, $etiqueta, TipoCampo::Booleano, reglas: ['boolean']);
    }

    /**
     * @param  class-string<BackedEnum>  $enum
     */
    public static function opcion(string $nombre, string $etiqueta, string $enum): self
    {
        return new self($nombre, $etiqueta, TipoCampo::Opcion, reglas: [Rule::enum($enum)], enum: $enum);
    }

    /**
     * Un elemento de un catálogo de M5, por su id. La base confirma que existe; que esté activo lo decide
     * el servicio (un elemento ya guardado puede seguir apuntando a uno que se desactivó después). En un catálogo con
     * «Otro» (plan 2.0, D5), `$detalle` es el campo de la acción donde se escribe cuál es: la pantalla lo pide solo con
     * «Otro», como el detalle de un motivo, y el servicio lo valida con `CatalogosService::detalleDeOtro()`.
     */
    public static function elementoDe(string $nombre, string $etiqueta, Catalogo $catalogo, ?string $detalle = null): self
    {
        return new self($nombre, $etiqueta, TipoCampo::ElementoCatalogo, reglas: ['integer', "exists:{$catalogo->value},id"], catalogo: $catalogo, campoDetalle: $detalle);
    }

    /**
     * Un motivo de una familia (plan 2.21, D7.3b): la pieza común para elegir el motivo de una transición o de una
     * acción, como `elementoDe()` para los catálogos. La base confirma que existe; que sea de la familia, que esté
     * activo y el detalle de «Otro» los decide el servicio. El detalle es otro campo de la acción (`$detalle`), que la
     * pantalla pide solo con «Otro».
     */
    public static function motivoDe(string $nombre, string $etiqueta, string $familia, string $detalle = 'motivo_detalle'): self
    {
        return new self($nombre, $etiqueta, TipoCampo::Motivo, reglas: ['integer', 'exists:motivos,id'], familiaMotivos: $familia, campoDetalle: $detalle);
    }

    /**
     * Varios elementos de un catálogo, por sus ids y sin repetir. Igual que elementoDe(): la base confirma que
     * existen y el servicio decide si siguen activos. Vacío o ausente llega como lista vacía.
     */
    public static function listaDe(string $nombre, string $etiqueta, Catalogo $catalogo): self
    {
        return new self($nombre, $etiqueta, TipoCampo::ListaCatalogo, reglas: ['array'], catalogo: $catalogo);
    }

    /**
     * Varios registros de una tabla que no es catálogo (los permisos de un rol), por sus ids y sin repetir. La base
     * confirma que existen. Vacío o ausente llega como lista vacía.
     */
    public static function listaDeIds(string $nombre, string $etiqueta, string $tabla, string $columnaEtiqueta = 'nombre'): self
    {
        return new self($nombre, $etiqueta, TipoCampo::ListaIds, reglas: ['array'], tabla: $tabla, columnaEtiqueta: $columnaEtiqueta);
    }

    /**
     * Varios textos cortos, sin repetir (las claves de los módulos que la persona oculta). Qué valores valen lo decide
     * el servicio. Vacío o ausente llega como lista vacía.
     */
    public static function listaDeTextos(string $nombre, string $etiqueta, int $maximo = 255): self
    {
        return new self($nombre, $etiqueta, TipoCampo::ListaTextos, reglas: ['array'], maximoElemento: $maximo);
    }

    /**
     * Un archivo (plan 1.8). Desde la pantalla, el que sube la persona; sin interfaz, `['ruta' => …, 'nombre' => …]` o
     * una ruta. Llega a ejecutar() como ArchivoSubido; qué archivos valen lo decide el servicio.
     */
    public static function archivo(string $nombre, string $etiqueta): self
    {
        return new self($nombre, $etiqueta, TipoCampo::Archivo);
    }

    /**
     * El mismo campo con otro nombre (plan 2.21, D12.4): una sección que otro módulo suma a un formulario lleva su clave
     * delante para no chocar con los campos de la acción.
     */
    public function renombrado(string $nombre): self
    {
        return new self($nombre, $this->etiqueta, $this->tipo, $this->obligatorio, $this->reglas, $this->enum, $this->control, $this->catalogo, $this->tabla, $this->columnaEtiqueta, $this->maximoElemento, $this->familiaMotivos, $this->campoDetalle);
    }

    public function opcional(): self
    {
        return new self($this->nombre, $this->etiqueta, $this->tipo, false, $this->reglas, $this->enum, $this->control, $this->catalogo, $this->tabla, $this->columnaEtiqueta, $this->maximoElemento, $this->familiaMotivos, $this->campoDetalle);
    }

    public function conReglas(mixed ...$reglas): self
    {
        return new self($this->nombre, $this->etiqueta, $this->tipo, $this->obligatorio, [...$this->reglas, ...array_values($reglas)], $this->enum, $this->control, $this->catalogo, $this->tabla, $this->columnaEtiqueta, $this->maximoElemento, $this->familiaMotivos, $this->campoDetalle);
    }

    /**
     * Marca el campo como dato de control: la pantalla lo captura al abrir el formulario (por ejemplo,
     * la versión para el bloqueo optimista) y lo envía oculto. Sin interfaz se pasa como cualquier otro.
     */
    public function deControl(): self
    {
        return new self($this->nombre, $this->etiqueta, $this->tipo, $this->obligatorio, $this->reglas, $this->enum, true, $this->catalogo, $this->tabla, $this->columnaEtiqueta, $this->maximoElemento, $this->familiaMotivos, $this->campoDetalle);
    }

    /**
     * @return list<mixed>
     */
    public function reglasDeValidacion(): array
    {
        return [$this->obligatorio ? 'required' : 'nullable', ...$this->reglas];
    }

    /**
     * Reglas de cada elemento de una lista (`campo.*`); nulo si el campo no es una lista.
     *
     * @return ?list<mixed>
     */
    public function reglasDeElementos(): ?array
    {
        return match (true) {
            $this->tipo === TipoCampo::ListaCatalogo && $this->catalogo !== null => ['integer', 'distinct', "exists:{$this->catalogo->value},id"],
            $this->tipo === TipoCampo::ListaIds && $this->tabla !== null => ['integer', 'distinct', "exists:{$this->tabla},id"],
            $this->tipo === TipoCampo::ListaTextos => ['string', 'distinct', 'max:'.($this->maximoElemento ?? 255)],
            default => null,
        };
    }

    /**
     * Valor ya validado → tipo con el que lo recibe ejecutar().
     */
    public function convertir(mixed $valor): mixed
    {
        if ($this->tipo === TipoCampo::ListaCatalogo || $this->tipo === TipoCampo::ListaIds) {
            return array_values(array_map(fn (mixed $id): int => (int) $id, (array) $valor));
        }

        if ($this->tipo === TipoCampo::ListaTextos) {
            return array_values(array_map(fn (mixed $texto): string => (string) $texto, (array) $valor));
        }

        if ($valor === null) {
            return null;
        }

        return match ($this->tipo) {
            TipoCampo::Texto => (string) $valor,
            TipoCampo::Entero => (int) $valor,
            TipoCampo::Decimal => (string) $valor,
            TipoCampo::Fecha => CarbonImmutable::createFromFormat('Y-m-d', (string) $valor)?->startOfDay(),
            TipoCampo::Booleano => filter_var($valor, FILTER_VALIDATE_BOOLEAN),
            TipoCampo::Opcion => $this->convertirOpcion($valor),
            TipoCampo::ElementoCatalogo, TipoCampo::Motivo => (int) $valor,
            TipoCampo::Archivo => ArchivoSubido::desde($valor),
        };
    }

    private function convertirOpcion(mixed $valor): BackedEnum
    {
        /** @var class-string<BackedEnum> $enum */
        $enum = $this->enum;

        return $valor instanceof $enum ? $valor : $enum::from(is_int($valor) ? $valor : (string) $valor);
    }
}
