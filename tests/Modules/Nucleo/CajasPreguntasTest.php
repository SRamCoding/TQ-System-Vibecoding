<?php

declare(strict_types=1);

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Navegacion\AnimacionCaja;
use App\Modules\Nucleo\Navegacion\AreaModulo;
use App\Modules\Nucleo\Navegacion\CajaModulo;
use App\Modules\Nucleo\Navegacion\RegistroModulos;
use BladeUI\Icons\Factory as IconFactory;
use Illuminate\Support\Js;
use Spatie\Permission\Models\Permission;

/*
 * Plan 1.4q: al dejar el mouse sobre una caja del inicio, sus preguntas una por una y al final la animación de su ícono.
 */

/** El inicio de quien ve las cuatro cajas del Núcleo. */
function inicioConPreguntas(): string
{
    foreach (['parties.ver', 'usuarios.ver', 'catalogos.ver'] as $permiso) {
        Permission::findOrCreate($permiso, 'web');
    }

    return (string) test()->actingAs(User::factory()->create()->givePermissionTo(['parties.ver', 'usuarios.ver', 'catalogos.ver']))
        ->get('/')->assertOk()->getContent();
}

test('las cuatro cajas del Núcleo traen sus preguntas literales y su animación', function () {
    $cajas = collect(app(RegistroModulos::class)->todas())->keyBy('clave');

    expect($cajas['mien']->preguntas)->toBe(['¿Qué tengo pendiente hoy?', '¿Qué espera mi aprobación?', '¿Cómo van los números de mi área?'])
        ->and($cajas['mien']->animacion)->toBe(AnimacionCaja::Camara)
        ->and($cajas['personas']->preguntas)->toBe(['¿Quién es este cliente y cómo lo contacto?', '¿Con qué proveedores trabajamos?', '¿Quién es el contacto principal de cada empresa?', '¿Dónde están sus sedes y sus cuentas bancarias?'])
        ->and($cajas['personas']->animacion)->toBe(AnimacionCaja::Apreton)
        ->and($cajas['seguridad']->preguntas)->toBe(['¿Quién tiene acceso al sistema?', '¿Qué puede hacer cada persona?', '¿Quién es jefe de quién?'])
        ->and($cajas['seguridad']->animacion)->toBe(AnimacionCaja::Personas)
        ->and($cajas['seguridad']->icono)->toBe('tq-tres-personas')
        ->and($cajas['configuracion']->preguntas)->toBe(['¿A cuánto está el dólar hoy?', '¿Qué monedas, unidades y catálogos usamos?', '¿Qué estados y motivos tiene cada documento?'])
        ->and($cajas['configuracion']->animacion)->toBe(AnimacionCaja::Rueda);
});

test('los íconos que se animan por piezas traen sus piezas', function () {
    $iconos = app(IconFactory::class);

    expect($iconos->svg('tq-apreton-manos')->contents())->toContain('class="tq-mano-izq"', 'class="tq-mano-der"')
        ->and($iconos->svg('tq-tres-personas')->contents())->toContain('tq-persona-centro', 'tq-persona-der', 'tq-persona-izq');
});

test('una caja con preguntas las lleva al navegador, sin la nube, con su animación', function () {
    $html = inicioConPreguntas();

    expect($html)->toContain('x-data="tqCajaConPreguntas('.e(Js::from(['¿Quién tiene acceso al sistema?', '¿Qué puede hacer cada persona?', '¿Quién es jefe de quién?'])).')"')
        ->toContain('data-tq-animacion="personas"')
        ->toContain('data-tq-animacion="apreton"')
        ->toContain('data-tq-animacion="camara"')
        ->toContain('data-tq-animacion="rueda"')
        ->not->toContain('x-tooltip="{ content: '.e(Js::from('Cuentas, roles, unidades y puestos')));
});

test('un módulo sin preguntas se ve como antes; uno sin animación propia, con el pulso', function () {
    app(RegistroModulos::class)->registrar(new CajaModulo('compras', 'Compras', '/compras', permiso: null, area: AreaModulo::Erp, descripcion: 'Órdenes y proveedores'));
    app(RegistroModulos::class)->registrar(new CajaModulo('ventas', 'Ventas', '/ventas', permiso: null, area: AreaModulo::Crm, preguntas: ['¿Cuánto vendimos este mes?']));

    $html = inicioConPreguntas();
    $documento = new DOMDocument;
    libxml_use_internal_errors(true);
    $documento->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    libxml_clear_errors();
    $compras = (new DOMXPath($documento))->query('//a[@data-modulo="compras" and contains(@class, "tq-caja-modulo")]')?->item(0);

    expect($compras)->toBeInstanceOf(DOMElement::class)
        ->and($compras?->getAttribute('x-tooltip'))->toBe('{ content: '.Js::from('Órdenes y proveedores').', theme: $store.theme }')
        ->and($compras?->hasAttribute('data-tq-preguntas'))->toBeFalse()
        ->and((new CajaModulo('x', 'X', '/x', permiso: null, area: AreaModulo::Erp))->animacion)->toBe(AnimacionCaja::Pulso)
        ->and($html)->toContain('data-tq-animacion="pulso"');
});

test('el clic corta la animación y el foco de un clic no la vuelve a arrancar; el del teclado, sí', function () {
    $html = inicioConPreguntas();

    expect($html)->toContain('x-on:click="$data.parar?.(); if (window.innerWidth')
        ->toContain('x-on:focus="$el.matches(\':focus-visible\') && empezar()"');
});

test('una pregunta vacía no se registra', function () {
    expect(fn () => new CajaModulo('x', 'X', '/x', permiso: null, area: AreaModulo::Erp, preguntas: ['¿Algo?', '  ']))
        ->toThrow(InvalidArgumentException::class, 'tiene una pregunta vacía');
});

test('3 segundos por pregunta, solo con mouse, y quietas con animaciones reducidas', function () {
    $html = inicioConPreguntas();

    expect($html)->toContain('window.tqCajaConPreguntas = (preguntas) => ({')
        ->toContain("window.matchMedia('(hover: hover) and (pointer: fine)')")
        ->toContain('this.despues(n * 3000 + 150')
        // Crece su celda y empuja a sus vecinas; con mouse, cada fila reserva ese crecimiento para que ninguna salte de fila.
        ->toContain('.tq-celda-modulo:has(> .tq-activa) { width: 11.5rem; height: 7.75rem; }')
        // Al pasar al final, la cámara sube.
        ->toContain('.tq-caja-modulo.tq-final .tq-caja-modulo-marco { animation: tq-sube-camara-icono 700ms')
        ->toContain('.tq-caja-modulo.tq-final .tq-caja-modulo-nombre { animation: tq-sube-camara-nombre 700ms')
        // Sin ícono mientras pasan las preguntas; vuelve al final.
        ->toContain('.tq-caja-modulo.tq-activa:not(.tq-final) .tq-caja-modulo-marco { display: none; }')
        // Las vecinas, a la altura del centro de la caja crecida.
        ->toContain('.tq-cajas-modulos { display: flex; flex-wrap: wrap; align-items: center; gap: 1rem; }')
        ->toContain('.tq-cajas-modulos { max-width: calc(100% - 2.5rem); transition: max-width 0s linear 300ms; }')
        ->toContain('.tq-cajas-modulos:has(.tq-activa) { max-width: 100%; transition: none; }')
        ->toContain('html.tq-sin-animaciones .tq-caja-modulo.tq-final * { animation: none !important; }')
        ->toContain('.tq-caja-modulo.tq-final * { animation: none !important; }');
});
