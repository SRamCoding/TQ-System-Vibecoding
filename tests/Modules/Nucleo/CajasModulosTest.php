<?php

declare(strict_types=1);

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Navegacion\AreaModulo;
use App\Modules\Nucleo\Navegacion\CajaModulo;
use App\Modules\Nucleo\Navegacion\RegistroModulos;

/*
 * Inicio (docs/convenciones/interfaz.md, «Inicio»): todas las cajas miden lo mismo, sin
 * importar cuánto texto tengan, y se agrupan por área (Personal, Núcleo, CRM, ERP) con su color. Se verifica que cada
 * caja sale de la misma vista, con la misma clase de tamaño, dentro de la grilla de filas de alto fijo.
 */

/**
 * El inicio de una persona que ve todos los módulos de prueba, como documento HTML.
 */
function inicioDeCajas(): DOMXPath
{
    $html = (string) test()->actingAs(User::factory()->create())->get('/')->assertOk()->getContent();

    $documento = new DOMDocument;
    libxml_use_internal_errors(true);
    $documento->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    libxml_clear_errors();

    return new DOMXPath($documento);
}

test('todas las cajas usan la misma clase de tamaño, con texto corto o largo', function () {
    // Solo las cajas de prueba: las del Núcleo («Mien», que ve todo el mundo) se prueban en MienTest.
    app()->instance(RegistroModulos::class, new RegistroModulos);
    $registro = app(RegistroModulos::class);
    $registro->registrar(new CajaModulo('corta', 'RRHH', '/rrhh', permiso: null, area: AreaModulo::Erp));
    $registro->registrar(new CajaModulo('larga', 'Importaciones, embarques, gastos de importación y prorrateo', '/importaciones', permiso: null, area: AreaModulo::Erp,
        descripcion: str_repeat('Una descripción muy larga que no debe agrandar la caja. ', 10)));
    $registro->registrar(new CajaModulo('sin-descripcion', 'Compras', '/compras', permiso: null, area: AreaModulo::Erp));

    $cajas = inicioDeCajas()->query('//a[@data-modulo]');

    expect($cajas)->not->toBeFalse();

    $clases = [];
    $padres = [];

    foreach ($cajas ?: [] as $caja) {
        // Plan 1.4q: cada caja en su celda, que guarda el lugar mientras la caja crece.
        if ($caja instanceof DOMElement && $caja->parentNode instanceof DOMElement && $caja->parentNode->parentNode instanceof DOMElement) {
            $clases[] = $caja->getAttribute('class');
            $padres[] = $caja->parentNode->getAttribute('class').' < '.$caja->parentNode->parentNode->getAttribute('class');
        }
    }

    expect($clases)->toHaveCount(3)
        ->and(array_values(array_unique($clases)))->toBe(['fi-section tq-caja-modulo'])
        ->and(array_values(array_unique($padres)))->toBe(['tq-celda-modulo < tq-cajas-modulos']);
});

test('cada caja lleva el ícono arriba y el nombre debajo, y nada más', function () {
    app(RegistroModulos::class)->registrar(new CajaModulo('compras', 'Compras', '/compras', permiso: null, area: AreaModulo::Erp, descripcion: 'Órdenes y proveedores'));

    $caja = inicioDeCajas()->query('//a[@data-modulo="compras"]')?->item(0);
    $hijos = [];

    foreach ($caja instanceof DOMElement ? $caja->childNodes : [] as $hijo) {
        if ($hijo instanceof DOMElement) {
            // La última clase: la propia (las de Filament van antes).
            $clases = explode(' ', $hijo->getAttribute('class'));
            $hijos[] = $hijo->tagName.'.'.end($clases);
        }
    }

    $marco = $caja instanceof DOMElement ? $caja->getElementsByTagName('span')->item(0) : null;
    $icono = $marco instanceof DOMElement ? $marco->getElementsByTagName('svg')->item(0) : null;

    // Sin preguntas, como antes: el ícono (en su marco, plan 1.4q) y el nombre. Plan 1.4r: además, dos piezas que no se
    // leen ni ocupan lugar: el degradado del trazo y la marca de agua del otoño. Plan 1.4t: y los adornos (hojas, escarcha).
    expect($hijos)->toBe(['svg.tq-caja-degradados', 'span.tq-caja-modulo-marco', 'svg.tq-caja-marca-agua', 'span.tq-caja-adornos', 'span.tq-caja-modulo-nombre'])
        ->and($icono?->getAttribute('class'))->toContain('tq-caja-modulo-icono')
        ->and(trim((string) $caja?->textContent))->toBe('Compras');
});

test('la grilla fija ancho y alto, justos para ícono y nombre, y centra el contenido', function () {
    app(RegistroModulos::class)->registrar(new CajaModulo('compras', 'Compras', '/compras', permiso: null, area: AreaModulo::Erp));

    $estilos = '';

    foreach (inicioDeCajas()->query('//style') ?: [] as $nodo) {
        $estilos .= $nodo->textContent."\n";
    }

    expect($estilos)
        // Plan 1.4q: celdas de tamaño fijo en filas que se acomodan solas.
        ->toMatch('/\.tq-cajas-modulos\s*\{[^}]*flex-wrap:\s*wrap/')
        ->toMatch('/\.tq-celda-modulo\s*\{[^}]*width:\s*9rem;\s*height:\s*6\.5rem/')
        ->toMatch('/\.tq-caja-modulo\s*\{[^}]*flex-direction:\s*column[^}]*align-items:\s*center[^}]*justify-content:\s*center[^}]*height:\s*100%[^}]*overflow:\s*hidden[^}]*text-align:\s*center/')
        ->toMatch('/\.tq-caja-modulo-nombre\s*\{[^}]*line-clamp:\s*2/');
});

test('la descripción no se muestra en la caja: va en el tooltip de Filament', function () {
    app(RegistroModulos::class)->registrar(new CajaModulo('compras', 'Compras', '/compras', permiso: null, area: AreaModulo::Erp, descripcion: 'Órdenes y proveedores'));
    app(RegistroModulos::class)->registrar(new CajaModulo('rrhh', 'RRHH', '/rrhh', permiso: null, area: AreaModulo::Erp));

    $xpath = inicioDeCajas();
    $compras = $xpath->query('//a[@data-modulo="compras"]')?->item(0);
    $rrhh = $xpath->query('//a[@data-modulo="rrhh"]')?->item(0);

    expect($compras)->toBeInstanceOf(DOMElement::class)
        ->and($compras instanceof DOMElement ? $compras->getAttribute('x-tooltip') : '')->toContain('Órdenes y proveedores')->toContain('$store.theme')
        ->and($compras instanceof DOMElement && $compras->hasAttribute('title'))->toBeFalse()
        ->and((string) $compras?->textContent)->not->toContain('Órdenes y proveedores')
        ->and($rrhh instanceof DOMElement && $rrhh->hasAttribute('x-tooltip'))->toBeFalse();
});

test('el widget dibuja cada caja con la misma vista, y solo con ella', function () {
    $vista = (string) file_get_contents(dirname(__DIR__, 3).'/app/Modules/Nucleo/resources/views/filament/widgets/cajas-modulos.blade.php');

    expect(substr_count($vista, "@include('nucleo::filament.widgets.caja-modulo'"))->toBe(1)
        ->and($vista)->not->toContain('<a ');
});

test('las cajas se agrupan por área, en orden Personal, Núcleo, CRM y ERP, y solo salen las áreas con cajas', function () {
    $registro = app(RegistroModulos::class);
    $registro->registrar(new CajaModulo('compras', 'Compras', '/compras', permiso: null, area: AreaModulo::Erp));
    $registro->registrar(new CajaModulo('ajustes', 'Ajustes', '/ajustes', permiso: null, area: AreaModulo::Nucleo));
    $registro->registrar(new CajaModulo('cuentas', 'Cuentas', '/cuentas', permiso: null, area: AreaModulo::Crm));
    $registro->registrar(new CajaModulo('almacen', 'Almacén', '/almacen', permiso: null, area: AreaModulo::Erp));
    $registro->registrar(new CajaModulo('rrhh', 'RRHH', '/rrhh', permiso: 'ver-rrhh', area: AreaModulo::Erp));

    $xpath = inicioDeCajas();
    $areas = [];

    foreach ($xpath->query('//section[@data-area]') ?: [] as $seccion) {
        if (! $seccion instanceof DOMElement) {
            continue;
        }

        $cajas = [];

        foreach ($xpath->query('.//a[@data-modulo]', $seccion) ?: [] as $caja) {
            if ($caja instanceof DOMElement) {
                $cajas[] = $caja->getAttribute('data-modulo').':'.$caja->getAttribute('data-area');
            }
        }

        $titulo = trim((string) $xpath->query('.//h2', $seccion)?->item(0)?->textContent);
        $areas[$seccion->getAttribute('data-area')] = [$titulo, $cajas];
    }

    // RRHH no sale (sin permiso); de las cajas reales del núcleo, solo «Mien» (plan 1.4l): las demás piden permisos que
    // esta persona no tiene.
    expect(array_keys($areas))->toBe(['personal', 'nucleo', 'crm', 'erp'])
        ->and($areas['personal'])->toBe(['Personal', ['mien:personal']])
        ->and($areas['nucleo'])->toBe(['Núcleo', ['ajustes:nucleo']])
        ->and($areas['crm'])->toBe(['CRM', ['cuentas:crm']])
        ->and($areas['erp'])->toBe(['ERP', ['compras:erp', 'almacen:erp']])
        ->and($xpath->query('//section[@data-area="erp"]//p')?->item(0)?->textContent)->toBe(AreaModulo::Erp->descripcion());
});

test('cada área tiene su color de marca por nombre de Filament y las secciones van separadas por una línea', function () {
    app(RegistroModulos::class)->registrar(new CajaModulo('compras', 'Compras', '/compras', permiso: null, area: AreaModulo::Erp));

    $estilos = '';

    // Solo el bloque de estilos del inicio: Filament agrega los suyos a la página.
    foreach (inicioDeCajas()->query('//style[contains(., ".tq-area")]') ?: [] as $nodo) {
        $estilos .= $nodo->textContent."\n";
    }

    foreach (AreaModulo::cases() as $area) {
        expect($estilos)
            ->toContain(".tq-area[data-area=\"{$area->value}\"] { --tq-area-color: var(--{$area->color()}-600); }")
            ->toContain(".dark .tq-area[data-area=\"{$area->value}\"] { --tq-area-color: var(--{$area->color()}-400); }");
    }

    expect($estilos)
        ->toMatch('/\.tq-area \+ \.tq-area\s*\{[^}]*border-top:/')
        ->toMatch('/\.tq-caja-modulo\s*\{[^}]*border-top:\s*3px solid var\(--tq-area-color\)/')
        ->not->toMatch('/#[0-9a-fA-F]{3,6}\b/');
});

test('los tres colores de área están declarados en el proveedor del panel', function () {
    $colores = Filament\Facades\Filament::getPanel('app')->getColors();

    foreach (AreaModulo::cases() as $area) {
        expect($colores)->toHaveKey($area->color());
    }
});
