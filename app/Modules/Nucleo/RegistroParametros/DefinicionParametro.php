<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\RegistroParametros;

use App\Modules\Nucleo\Enums\TipoParametro;
use InvalidArgumentException;

/**
 * Un parámetro que un módulo necesita: lo declara en su provider y el núcleo lo crea en `parametros`
 * con su valor inicial si todavía no existe. El valor vigente es el de la base, no este.
 */
final readonly class DefinicionParametro
{
    public string $valorInicial;

    /**
     * @param  mixed  $valorInicial  en su tipo de PHP: string para texto y decimal, int, bool, CarbonImmutable
     * @param  bool  $esSensible  decide a dónde va el dinero: solo lo cambia quien tiene `parametros.editar-sensibles`
     *                            (como los campos de dinero de los catálogos, plan 1.2b E6; plan 1.3b)
     * @param  ?class-string<ValidadorParametro>  $validador  regla de negocio que se revisa al cambiarlo
     */
    public function __construct(
        public string $grupo,
        public string $clave,
        public TipoParametro $tipo,
        mixed $valorInicial,
        public string $descripcion,
        public bool $esEditable = true,
        public bool $esSensible = false,
        public ?string $validador = null,
    ) {
        if (preg_match('/^[a-z][a-z0-9_]*$/', $grupo) !== 1 || preg_match('/^[a-z][a-z0-9_]*$/', $clave) !== 1) {
            throw new InvalidArgumentException("Grupo y clave de parámetro van en snake_case: «{$grupo}.{$clave}».");
        }

        $this->valorInicial = $tipo->aTexto($valorInicial);
    }

    public function igualA(self $otra): bool
    {
        return [$this->grupo, $this->clave, $this->tipo, $this->valorInicial, $this->descripcion, $this->esEditable, $this->esSensible, $this->validador]
            === [$otra->grupo, $otra->clave, $otra->tipo, $otra->valorInicial, $otra->descripcion, $otra->esEditable, $otra->esSensible, $otra->validador];
    }

    /** `grupo.clave`: como se pide a ParametrosService. */
    public function nombre(): string
    {
        return "{$this->grupo}.{$this->clave}";
    }
}
