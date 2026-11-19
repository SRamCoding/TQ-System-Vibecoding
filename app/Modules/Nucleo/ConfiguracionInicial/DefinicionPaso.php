<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\ConfiguracionInicial;

use Closure;
use Filament\Pages\Page;

/**
 * Un paso del asistente de configuración inicial (plan 1.13, D1 y D5). No guarda nada ni tiene reglas propias: dice qué
 * pantalla de siempre abre (se mete en el panel) y un resumen de cómo está, solo de lectura.
 *
 * `pantalla` es la clase, o una función que la elige con sus parámetros (los datos de la empresa: la ficha de Techquk,
 * o la lista para elegirla). `parametros` son los de montarla cuando es una clase (una búsqueda ya escrita).
 */
final readonly class DefinicionPaso
{
    /**
     * @param  string  $clave  única y estable: `modulo.cosa`
     * @param  string  $icono  el de la pantalla que abre (la pantalla y su paso comparten ícono)
     * @param  class-string<Page>|(Closure(): array{clase: class-string<Page>, parametros: array<string, mixed>})  $pantalla
     * @param  (Closure(): string)|null  $resumen  cómo está, en pocas palabras («2 unidades»); solo lee
     * @param  bool  $obligatorio  si falta, el inicio avisa; uno opcional (la IA) no
     * @param  array<string, mixed>  $parametros
     */
    public function __construct(
        public string $clave,
        public string $titulo,
        public string $descripcion,
        public string $icono,
        public string|Closure $pantalla,
        public ?Closure $resumen = null,
        public bool $obligatorio = true,
        public array $parametros = [],
    ) {}

    /** @return array{clase: class-string<Page>, parametros: array<string, mixed>} */
    public function abrir(): array
    {
        return is_string($this->pantalla) ? ['clase' => $this->pantalla, 'parametros' => $this->parametros] : ($this->pantalla)();
    }
}
