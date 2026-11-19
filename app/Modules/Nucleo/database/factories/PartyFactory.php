<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\database\factories;

use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Enums\TipoPersona;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\TipoDocumentoIdentidad;
use App\Modules\Nucleo\Parties\DocumentoIdentidad;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Por defecto, una persona natural con DNI. `juridica()` da una empresa con RUC 20 de dígito verificador
 * correcto. Datos inventados (regla 10). Usa los tipos de documento del catálogo, o los crea si la prueba
 * no sembró los datos maestros.
 *
 * @extends Factory<Party>
 */
class PartyFactory extends Factory
{
    protected $model = Party::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nombres = fake()->firstName();
        $apellidos = fake()->lastName().' '.fake()->lastName();

        return [
            'tipo' => TipoPersona::Natural,
            'tipo_documento_id' => fn (): int => self::tipoDocumento(Catalogo::DNI, 'DNI', 8),
            'numero_documento' => fake()->unique()->numerify('########'),
            'razon_social' => "{$apellidos} {$nombres}",
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'es_activo' => true,
        ];
    }

    public function juridica(): static
    {
        return $this->state(function (): array {
            $base = '20'.fake()->unique()->numerify('########');

            return [
                'tipo' => TipoPersona::Juridica,
                'tipo_documento_id' => fn (): int => self::tipoDocumento(Catalogo::RUC, 'RUC', 11),
                'numero_documento' => $base.DocumentoIdentidad::digitoVerificadorRuc($base),
                'razon_social' => fake()->company().' S.A.C.',
                'nombres' => null,
                'apellidos' => null,
            ];
        });
    }

    public function inactiva(): static
    {
        return $this->state(['es_activo' => false]);
    }

    private static function tipoDocumento(string $codigo, string $nombre, int $longitud): int
    {
        return TipoDocumentoIdentidad::query()->firstOrCreate(['codigo' => $codigo], ['nombre' => $nombre, 'longitud' => $longitud])->id;
    }
}
