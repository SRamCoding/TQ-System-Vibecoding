<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/*
 * Las pruebas corren contra PostgreSQL (techquk_test), nunca SQLite: unaccent, CHECK e índices
 * parciales tienen que comportarse como en producción.
 */
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    // Ninguna prueba sale a internet (SUNAT, INEI, IA, correo): lo que no se simula con Http::fake falla.
    ->beforeEach(function () {
        Http::preventStrayRequests();
        // Plan 1.10, D2: el panel exige un gerente general activo. Lo prueba GerenteGeneralTest; las demás, sin él.
        config(['nucleo.exigir_gerente_general' => false]);
    })
    ->in('Feature', 'Modules');

/*
 * Concurrencia (plan 1.6): procesos de verdad contra su propia base (techquk_concurrencia), sin la transacción de
 * RefreshDatabase, que los aislaría y escondería las carreras. La prueba prepara esa base y la limpia.
 */
pest()->extend(TestCase::class)
    ->beforeEach(fn () => Http::preventStrayRequests())
    ->in('Concurrencia');
