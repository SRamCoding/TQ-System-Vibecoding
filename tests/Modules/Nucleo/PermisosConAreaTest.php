<?php

declare(strict_types=1);

use App\Modules\Nucleo\Estados\RegistroDocumentos;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;

/*
 * Plan 2.20a, N7, y plan 2.1b, D4: en los módulos de negocio, permisos, acciones, claves de documento y efectos llevan
 * el prefijo de su área (`crm.cuentas.ver`, `erp.compras.orden.anular`); el núcleo no. Lo que se registra con el nombre
 * equivocado sale aquí, antes de que los roles o los efectos aplicados se guarden con él.
 */

/** El área de una clase de un módulo de negocio (`crm`, `erp`), o null si es del núcleo. */
function areaDeLaClase(string $clase): ?string
{
    return preg_match('/^App\\\\Modules\\\\(Crm|Erp)\\\\/', $clase, $m) === 1 ? strtolower($m[1]) : null;
}

/**
 * Lo mal nombrado de los módulos de negocio: «clave (clase)». Revisa acciones y sus permisos, claves de documento,
 * permisos de transición (y con ellos las listas de motivos de cada transición, que salen de la misma clave) y los
 * efectos que usan las transiciones, porque la clase de cada uno dice de qué módulo es. Las alertas y las listas de
 * motivos sueltas no guardan quién las registró: van por convención (codigo.md, «Nombres de permisos y acciones»).
 *
 * @return list<string>
 */
function nombresSinSuArea(): array
{
    $errores = [];
    $sinArea = fn (string $nombre, string $area): bool => ! str_starts_with($nombre, $area.'.');
    $acciones = app(RegistroAcciones::class);

    foreach ($acciones->todas() as $clave => $clase) {
        $area = areaDeLaClase($clase);

        if ($area === null) {
            continue;
        }

        foreach (array_filter([$clave, $acciones->obtener($clave)->permiso()]) as $nombre) {
            if ($sinArea($nombre, $area)) {
                $errores[] = "{$nombre} ({$clase})";
            }
        }
    }

    $documentos = app(RegistroDocumentos::class);

    foreach ($documentos->definiciones() as $definicion) {
        $area = areaDeLaClase($definicion::class);

        if ($area !== null && $sinArea($definicion->clave(), $area)) {
            $errores[] = $definicion->clave().' ('.$definicion::class.')';
        }

        foreach ($definicion->transiciones() as $transicion) {
            $permiso = $definicion->permiso($transicion);

            if ($area !== null && $permiso !== null && $sinArea($permiso, $area)) {
                $errores[] = "{$permiso} (".$definicion::class.')';
            }

            foreach ($transicion->efectos as $efecto) {
                $clase = $documentos->claseDeEfecto($efecto);
                $areaDelEfecto = $clase === null ? null : areaDeLaClase($clase);

                if ($areaDelEfecto !== null && $sinArea($efecto, $areaDelEfecto)) {
                    $errores[] = "{$efecto} ({$clase})";
                }
            }
        }
    }

    return array_values(array_unique($errores));
}

test('acciones, documentos, transiciones y efectos de los módulos de negocio llevan el prefijo de su área', function () {
    expect(nombresSinSuArea())->toBe([]);
});

test('el detector reconoce el área de una clase de negocio y deja pasar el núcleo', function () {
    expect(areaDeLaClase('App\\Modules\\Crm\\Cuentas\\Acciones\\CrearCuenta'))->toBe('crm')
        ->and(areaDeLaClase('App\\Modules\\Erp\\Compras\\Acciones\\AnularOrden'))->toBe('erp')
        ->and(areaDeLaClase('App\\Modules\\Nucleo\\Acciones\\Parties\\EditarParty'))->toBeNull();
});
