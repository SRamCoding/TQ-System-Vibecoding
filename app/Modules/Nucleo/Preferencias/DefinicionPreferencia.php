<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Preferencias;

use App\Modules\Nucleo\Models\User;
use Closure;
use InvalidArgumentException;

/**
 * Una preferencia que declara un módulo en su provider (plan 1.4g, D1 y D2). Su defecto vive aquí, no en la base: sin
 * fila en `preferencias_usuario`, vale el defecto. Solo cambia la presentación; nunca datos personales ni estado de
 * trabajo (D7).
 */
final readonly class DefinicionPreferencia
{
    /**
     * @param  array<string, string>|Closure(User): array<string, string>|null  $opciones  de una opción: valor → nombre;
     *                                                                                     calculadas por persona si
     *                                                                                     dependen de sus permisos
     * @param  ?class-string<FuenteDeLista>  $fuente  de una lista: el módulo dueño
     */
    private function __construct(
        public GrupoPreferencia $grupo,
        public string $nombre,
        public string $etiqueta,
        public string $ayuda,
        public TipoPreferencia $tipo,
        public bool|int|string|null $defecto,
        public array|Closure|null $opciones = null,
        public ?int $minimo = null,
        public ?int $maximo = null,
        public ?string $fuente = null,
        /** Solo aplica en escritorio: en el celular, la pantalla lo muestra con esa nota (D6). */
        public bool $soloEscritorio = false,
        /** Si sale en «Mis preferencias»; «Restablecer» la vuelve al defecto igual (el ancho de la barra, D6). */
        public bool $seMuestra = true,
        /** @var ?Closure(int): bool una condición más de un número, además de sus límites (el ancho: 0 o 200-480) */
        public ?Closure $regla = null,
    ) {
        if (preg_match('/^[a-z][a-z0-9_]*$/', $nombre) !== 1) {
            throw new InvalidArgumentException("El nombre de la preferencia va en snake_case: «{$nombre}».");
        }

        if (mb_strlen($this->clave()) > 80) {
            throw new InvalidArgumentException("La clave de la preferencia pasa de 80 caracteres: «{$this->clave()}».");
        }
    }

    public static function siNo(GrupoPreferencia $grupo, string $nombre, string $etiqueta, string $ayuda, bool $defecto): self
    {
        return new self($grupo, $nombre, $etiqueta, $ayuda, TipoPreferencia::SiNo, $defecto);
    }

    public static function numero(GrupoPreferencia $grupo, string $nombre, string $etiqueta, string $ayuda, int $defecto, int $minimo, int $maximo): self
    {
        return new self($grupo, $nombre, $etiqueta, $ayuda, TipoPreferencia::Numero, $defecto, minimo: $minimo, maximo: $maximo);
    }

    /**
     * @param  array<string, string>|Closure(User): array<string, string>  $opciones
     */
    public static function opcion(GrupoPreferencia $grupo, string $nombre, string $etiqueta, string $ayuda, string $defecto, array|Closure $opciones): self
    {
        return new self($grupo, $nombre, $etiqueta, $ayuda, TipoPreferencia::Opcion, $defecto, opciones: $opciones);
    }

    /**
     * @param  class-string<FuenteDeLista>  $fuente
     */
    public static function lista(GrupoPreferencia $grupo, string $nombre, string $etiqueta, string $ayuda, string $fuente): self
    {
        return new self($grupo, $nombre, $etiqueta, $ayuda, TipoPreferencia::Lista, null, fuente: $fuente);
    }

    public function soloEnEscritorio(): self
    {
        return new self($this->grupo, $this->nombre, $this->etiqueta, $this->ayuda, $this->tipo, $this->defecto, $this->opciones, $this->minimo, $this->maximo, $this->fuente, true, $this->seMuestra, $this->regla);
    }

    public function sinMostrar(): self
    {
        return new self($this->grupo, $this->nombre, $this->etiqueta, $this->ayuda, $this->tipo, $this->defecto, $this->opciones, $this->minimo, $this->maximo, $this->fuente, $this->soloEscritorio, false, $this->regla);
    }

    /**
     * @param  Closure(int): bool  $regla
     */
    public function soloSi(Closure $regla): self
    {
        return new self($this->grupo, $this->nombre, $this->etiqueta, $this->ayuda, $this->tipo, $this->defecto, $this->opciones, $this->minimo, $this->maximo, $this->fuente, $this->soloEscritorio, $this->seMuestra, $regla);
    }

    /** `grupo.nombre`: como se guarda y como se pide al servicio. */
    public function clave(): string
    {
        return "{$this->grupo->value}.{$this->nombre}";
    }

    /**
     * Las opciones de una opción para esta persona (valor → nombre); vacías en otro tipo.
     *
     * @return array<string, string>
     */
    public function opcionesPara(User $usuario): array
    {
        $opciones = $this->opciones instanceof Closure ? ($this->opciones)($usuario) : $this->opciones;

        return $opciones ?? [];
    }

    /**
     * El valor en su tipo si vale para esta persona; si no, null. Una lista no se valida aquí: la cambia su módulo.
     */
    public function validar(mixed $entrada, User $usuario): bool|int|string|null
    {
        $valor = $this->tipo->interpretar($entrada);

        return match (true) {
            $valor === null => null,
            $this->tipo === TipoPreferencia::Numero => is_int($valor) && $valor >= (int) $this->minimo && $valor <= (int) $this->maximo
                && ($this->regla === null || ($this->regla)($valor)) ? $valor : null,
            $this->tipo === TipoPreferencia::Opcion => is_string($valor) && array_key_exists($valor, $this->opcionesPara($usuario)) ? $valor : null,
            default => $valor,
        };
    }
}
