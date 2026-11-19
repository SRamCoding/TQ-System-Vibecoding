<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Parties;

use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Enums\TipoPersona;
use App\Modules\Nucleo\Excepciones\DocumentoIdentidadInvalido;

/**
 * Un documento de identidad ya normalizado y validado (plan 1.3, D4). Todo número pasa por aquí antes de
 * guardarse o buscarse, por cualquier camino: así la unicidad de `parties` no se salta con un espacio o un
 * guion, y ningún RUC se guarda con el dígito verificador mal.
 *
 * El número es siempre texto: convertirlo a entero borraría el cero inicial de un DNI.
 *
 * DNI y RUC tienen reglas fijas de SUNAT; los demás tipos usan la longitud de su catálogo, si la tienen.
 */
final readonly class DocumentoIdentidad
{
    /** Carné de extranjería y pasaporte: solo los tiene una persona (catálogo 06 de SUNAT). */
    public const string CARNE_EXTRANJERIA = '4';

    public const string PASAPORTE = '7';

    private const array PESOS_RUC = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];

    private const array PREFIJOS_RUC_NATURAL = ['10', '15', '16', '17'];

    private const string PREFIJO_RUC_JURIDICA = '20';

    private function __construct(
        public string $codigoTipo,
        public string $numero,
    ) {}

    /**
     * @param  ?int  $longitud  la del catálogo; no se usa en DNI ni RUC
     * @param  string  $nombreTipo  para el mensaje de error
     *
     * @throws DocumentoIdentidadInvalido
     */
    public static function de(string $codigoTipo, string $numero, ?int $longitud = null, string $nombreTipo = 'documento'): self
    {
        $numero = self::normalizar($numero);

        if ($numero === '') {
            throw DocumentoIdentidadInvalido::vacio();
        }

        if (preg_match('/^[0-9A-Z]{1,20}$/', $numero) !== 1) {
            throw DocumentoIdentidadInvalido::caracteres();
        }

        if ($codigoTipo === Catalogo::DNI) {
            self::validarDigitos($numero, 8, 'DNI');
        } elseif ($codigoTipo === Catalogo::RUC) {
            self::validarRuc($numero);
        } elseif ($longitud !== null && strlen($numero) !== $longitud) {
            throw DocumentoIdentidadInvalido::longitud($nombreTipo, $longitud, false);
        }

        return new self($codigoTipo, $numero);
    }

    /** Sin espacios, guiones ni puntos, en mayúsculas. No valida. */
    public static function normalizar(string $numero): string
    {
        return mb_strtoupper((string) preg_replace('/[\s.\-]+/u', '', $numero));
    }

    /**
     * El dígito verificador de un RUC a partir de sus diez primeros dígitos (módulo 11 de SUNAT).
     */
    public static function digitoVerificadorRuc(string $diezDigitos): string
    {
        $suma = 0;

        foreach (str_split($diezDigitos) as $i => $digito) {
            $suma += (int) $digito * self::PESOS_RUC[$i];
        }

        $resto = 11 - ($suma % 11);

        return (string) match ($resto) {
            10 => 0,
            11 => 1,
            default => $resto,
        };
    }

    /**
     * Lo que dice el documento sobre quién es. Nulo: el documento no lo dice y lo elige quien registra.
     */
    public function tipoPersona(): ?TipoPersona
    {
        return match (true) {
            in_array($this->codigoTipo, [Catalogo::DNI, self::CARNE_EXTRANJERIA, self::PASAPORTE], true) => TipoPersona::Natural,
            $this->codigoTipo === Catalogo::RUC && in_array(substr($this->numero, 0, 2), self::PREFIJOS_RUC_NATURAL, true) => TipoPersona::Natural,
            $this->codigoTipo === Catalogo::RUC => TipoPersona::Juridica,
            default => null,
        };
    }

    /**
     * El otro documento de la misma persona: el DNI que contiene un RUC 10, o el RUC 10 que corresponde a un
     * DNI (`10` + DNI + dígito). Para el aviso de posible duplicado (plan 1.3, D9). Nulo en los demás casos.
     */
    public function pareja(): ?self
    {
        if ($this->codigoTipo === Catalogo::RUC && str_starts_with($this->numero, '10')) {
            return new self(Catalogo::DNI, substr($this->numero, 2, 8));
        }

        if ($this->codigoTipo === Catalogo::DNI) {
            $base = '10'.$this->numero;

            return new self(Catalogo::RUC, $base.self::digitoVerificadorRuc($base));
        }

        return null;
    }

    private static function validarDigitos(string $numero, int $longitud, string $nombreTipo): void
    {
        if (preg_match('/^[0-9]{'.$longitud.'}$/', $numero) !== 1) {
            throw DocumentoIdentidadInvalido::longitud($nombreTipo, $longitud, true);
        }
    }

    private static function validarRuc(string $numero): void
    {
        self::validarDigitos($numero, 11, 'RUC');

        $prefijo = substr($numero, 0, 2);

        if ($prefijo !== self::PREFIJO_RUC_JURIDICA && ! in_array($prefijo, self::PREFIJOS_RUC_NATURAL, true)) {
            throw DocumentoIdentidadInvalido::prefijoRuc();
        }

        if (self::digitoVerificadorRuc(substr($numero, 0, 10)) !== $numero[10]) {
            throw DocumentoIdentidadInvalido::digitoVerificador($numero);
        }
    }
}
