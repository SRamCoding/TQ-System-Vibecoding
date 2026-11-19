<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\database\factories;

use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\PreferenciasUsuarioService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Un usuario con su persona (DNI inventado, plan 1.4, D4) y el nombre de ella.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /** La contraseña de las cuentas de prueba: cumple la regla del sistema (plan 1.4h, D3), así no hay que renovarla. */
    public const string CLAVE = 'Clave-de-prueba1';

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'party_id' => Party::factory(),
            'name' => fn (array $atributos): string => (string) Party::query()->whereKey($atributos['party_id'])->value('razon_social'),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make(self::CLAVE),
            'remember_token' => Str::random(10),
        ];
    }

    /** Con la barra de módulos prendida (plan 1.4f; preferencia del registro desde la 1.4g). */
    public function conBarraModulos(): static
    {
        return $this->afterCreating(fn (User $usuario) => app(PreferenciasUsuarioService::class)->cambiar($usuario, 'navegacion.barra_modulos', true));
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'es_activo' => false,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
