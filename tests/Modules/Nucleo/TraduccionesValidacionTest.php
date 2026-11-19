<?php

declare(strict_types=1);

use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;
use App\Modules\Nucleo\RegistroAcciones\TipoCampo;
use Illuminate\Support\Facades\Lang;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\Modules\Nucleo\Fixtures\AccionDePrueba;

/*
 * ADR 0011: los mensajes de validación son nuestros (lang/es/validation.php). Si una acción usa una
 * regla sin línea, el usuario vería «validation.xxx»: esta prueba lo impide.
 */

/**
 * Claves de lang/es/validation.php que un campo puede necesitar.
 *
 * @return list<string>
 */
function clavesDeMensaje(Campo $campo): array
{
    $claves = [];

    // Las de cada elemento de una lista (`campo.*`) también se muestran al usuario.
    foreach ([...$campo->reglasDeValidacion(), ...($campo->reglasDeElementos() ?? [])] as $regla) {
        $nombre = match (true) {
            $regla instanceof Enum => 'enum',
            is_string($regla) => explode(':', $regla, 2)[0],
            default => throw new LogicException('Regla sin clave de mensaje conocida: '.get_debug_type($regla).'. Agrégala aquí y en lang/es/validation.php.'),
        };

        if ($nombre === 'nullable') {
            continue;
        }

        // Las reglas de tamaño tienen un mensaje por tipo de dato.
        if (in_array($nombre, ['max', 'min', 'between', 'size'], true)) {
            $nombre .= match ($campo->tipo) {
                // En una lista de textos, el tamaño es el de cada elemento.
                TipoCampo::Texto, TipoCampo::ListaTextos => '.string',
                TipoCampo::Entero, TipoCampo::Decimal => '.numeric',
                default => throw new LogicException("Regla {$nombre} en un campo {$campo->tipo->value}: define su mensaje."),
            };
        }

        $claves[] = $nombre;
    }

    return $claves;
}

/**
 * Las acciones registradas por los módulos y la de prueba, que tiene un campo de cada tipo.
 *
 * @return list<Accion>
 */
function accionesAVerificar(): array
{
    $registro = app(RegistroAcciones::class);

    return [
        new AccionDePrueba,
        ...array_map(fn (string $clave): Accion => $registro->obtener($clave), array_keys($registro->todas())),
    ];
}

test('toda regla usada por un campo de una acción tiene su mensaje en español', function () {
    $faltan = [];

    foreach (accionesAVerificar() as $accion) {
        foreach ($accion->campos() as $campo) {
            foreach (clavesDeMensaje($campo) as $clave) {
                if (! Lang::hasForLocale("validation.{$clave}", 'es')) {
                    $faltan[] = "{$accion->clave()} · {$campo->nombre} · {$clave}";
                }
            }
        }
    }

    expect($faltan)->toBe([]);
});

test('toda regla de los campos de un catálogo tiene su mensaje en español', function () {
    $faltan = [];

    foreach (Catalogo::cases() as $catalogo) {
        foreach ([$catalogo->campoCodigo(), ...$catalogo->campos()] as $campo) {
            foreach (clavesDeMensaje($campo) as $clave) {
                if (! Lang::hasForLocale("validation.{$clave}", 'es')) {
                    $faltan[] = "{$catalogo->value} · {$campo->nombre} · {$clave}";
                }
            }
        }
    }

    expect($faltan)->toBe([]);
});

test('un error de validación sale en español con la etiqueta del campo', function () {
    Permission::create(['name' => 'probar-acciones']);
    $usuario = User::factory()->create()->givePermissionTo('probar-acciones');

    try {
        app(EjecutorAcciones::class)->ejecutar(new AccionDePrueba, [
            'nombre' => str_repeat('x', 11),
            'cantidad' => '0',
            'monto' => '1.234',
        ], $usuario);
        $this->fail('Debió rechazar la validación.');
    } catch (ValidationException $e) {
        $errores = $e->errors();

        expect($errores['nombre'][0])->toBe('El campo Nombre no puede tener más de 10 caracteres.')
            ->and($errores['cantidad'][0])->toBe('El campo Cantidad debe ser al menos 1.')
            ->and($errores['fecha_entrega'][0])->toBe('El campo Fecha de entrega es obligatorio.')
            ->and($e->getMessage())->toContain('errores más')
            ->and(collect($errores)->flatten()->filter(fn (string $m): bool => str_contains($m, 'validation.'))->all())->toBe([]);
    }
});
