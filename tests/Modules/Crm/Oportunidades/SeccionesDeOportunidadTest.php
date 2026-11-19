<?php

declare(strict_types=1);

use App\Modules\Crm\Oportunidades\Secciones\SeccionDeOportunidad;
use App\Modules\Crm\Oportunidades\Secciones\SeccionesDeOportunidad;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/*
 * El punto de extensión de los formularios (plan 2.21, D12.4): otro módulo suma su sección a Nueva oportunidad y a
 * Avanzar, con sus campos y su guardado. Aquí con una sección falsa, como la «Próxima acción» que traerá CRM-05.
 */

function sdoSeccion(): SeccionDeOportunidad
{
    return new class implements SeccionDeOportunidad
    {
        /** @var list<array{int, string, array<string, mixed>}> */
        public array $guardados = [];

        public function clave(): string
        {
            return 'proxima_accion';
        }

        public function titulo(): string
        {
            return 'Próxima acción';
        }

        public function formularios(): array
        {
            return [self::AL_CREAR, self::AL_AVANZAR];
        }

        public function campos(): array
        {
            return [Campo::fecha('fecha', 'Fecha'), Campo::texto('que', 'Qué se hará')->opcional()];
        }

        public function guardar(int $oportunidadId, string $formulario, array $datos, User $usuario): void
        {
            $this->guardados[] = [$oportunidadId, $formulario, $datos];
        }
    };
}

test('los campos de la sección llegan con su clave delante, sin perder su tipo ni su obligatoriedad', function () {
    $registro = new SeccionesDeOportunidad;
    $registro->registrar(sdoSeccion());

    $campos = $registro->campos(SeccionDeOportunidad::AL_AVANZAR);

    expect(array_map(fn (Campo $c): string => $c->nombre, $campos))->toBe(['proxima_accion__fecha', 'proxima_accion__que'])
        ->and($campos[0]->etiqueta)->toBe('Fecha')
        ->and($campos[0]->obligatorio)->toBeTrue()
        ->and($campos[1]->obligatorio)->toBeFalse()
        ->and($registro->campos('otro'))->toBe([]);
});

test('al guardar, cada sección recibe solo lo suyo y con los nombres cortos', function () {
    $registro = new SeccionesDeOportunidad;
    $seccion = sdoSeccion();
    $registro->registrar($seccion);

    $registro->guardar(7, SeccionDeOportunidad::AL_CREAR, ['nombre' => 'Cableado', 'proxima_accion__fecha' => '2026-11-20', 'proxima_accion__que' => 'Visita'], User::factory()->make());

    expect($seccion->guardados)->toBe([[7, 'crear', ['fecha' => '2026-11-20', 'que' => 'Visita']]]);
});

test('una clave no se repite ni lleva el separador', function () {
    $registro = new SeccionesDeOportunidad;
    $registro->registrar(sdoSeccion());

    expect(fn () => $registro->registrar(sdoSeccion()))->toThrow(InvalidArgumentException::class, 'ya está registrada');
});
