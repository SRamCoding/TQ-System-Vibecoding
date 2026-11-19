<?php

declare(strict_types=1);

use App\Modules\Nucleo\Desarrollo\ModoDesarrollo;
use App\Modules\Nucleo\Desarrollo\Piezas\EstacionDelInicio;
use App\Modules\Nucleo\Enums\Temporada;
use App\Modules\Nucleo\Filament\Desarrollo\BarraDesarrollo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Navegacion\AreaModulo;
use App\Modules\Nucleo\Navegacion\CajaModulo;
use App\Modules\Nucleo\Navegacion\ColoresMarca;
use App\Modules\Nucleo\Navegacion\EstacionDeLaApariencia;
use App\Modules\Nucleo\Navegacion\EstiloEstacion;
use App\Modules\Nucleo\Navegacion\Oklch;
use App\Modules\Nucleo\Navegacion\RegistroModulos;
use App\Modules\Nucleo\Services\PreferenciasUsuarioService;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

/*
 * Las cajas del inicio según la estación (plan 1.4r): la estación sale de la fecha (hemisferio sur, también la simulada
 * del modo desarrollo), las fechas especiales no les cambian nada, la preferencia apagada las deja como antes, una caja
 * nueva hereda el estilo sin configurar nada y el nombre se lee (AA) en las cuatro estaciones y los dos temas. Personas
 * y cajas inventadas.
 */

/** El inicio en esa fecha (hora de Lima), como documento. */
function inicioEnLaFecha(string $fecha, ?User $usuario = null): DOMXPath
{
    test()->travelTo(CarbonImmutable::parse($fecha.' 10:00', 'America/Lima'));
    $html = (string) test()->actingAs($usuario ?? User::factory()->create())->get('/')->assertOk()->getContent();

    $documento = new DOMDocument;
    libxml_use_internal_errors(true);
    $documento->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    libxml_clear_errors();

    return new DOMXPath($documento);
}

function estacionDelInicio(DOMXPath $inicio): ?string
{
    $contenedor = $inicio->query('//div[contains(@class, "tq-inicio-cajas")]')?->item(0);

    return $contenedor instanceof DOMElement && $contenedor->hasAttribute('data-tq-estacion') ? $contenedor->getAttribute('data-tq-estacion') : null;
}

/** Lo que las cajas visten ese día: su estación y las variables de color. */
function vestimentaDeLasCajas(DOMXPath $inicio): string
{
    $estilos = '';

    foreach ($inicio->query('//style') ?: [] as $nodo) {
        if (str_contains($nodo->textContent, '--tq-e-fondo1:')) {
            $estilos .= $nodo->textContent;
        }
    }

    return estacionDelInicio($inicio).'|'.$estilos;
}

// --- La estación de la fecha ---

it('elige la estación por la fecha, también en los bordes', function (string $fecha, Temporada $esperada) {
    $this->travelTo(CarbonImmutable::parse($fecha.' 00:05', 'America/Lima'));

    expect(EstacionDeLaApariencia::para(User::factory()->create()))->toBe($esperada);
})->with([
    'último día de primavera' => ['2026-12-20', Temporada::Primavera],
    'empieza el verano (21 dic)' => ['2026-12-21', Temporada::Verano],
    'último día de verano' => ['2027-03-19', Temporada::Verano],
    'empieza el otoño (20 mar)' => ['2027-03-20', Temporada::Otono],
    'último día de otoño' => ['2027-06-20', Temporada::Otono],
    'empieza el invierno (21 jun)' => ['2027-06-21', Temporada::Invierno],
    'último día de invierno' => ['2027-09-21', Temporada::Invierno],
    'empieza la primavera (22 sep)' => ['2027-09-22', Temporada::Primavera],
]);

it('el inicio viste las cajas con la estación del día', function () {
    expect(estacionDelInicio(inicioEnLaFecha('2026-10-28')))->toBe('primavera')
        ->and(estacionDelInicio(inicioEnLaFecha('2027-01-15')))->toBe('verano');
});

it('una fecha especial no cambia nada de las cajas', function (string $especial, string $comun) {
    expect(vestimentaDeLasCajas(inicioEnLaFecha($especial)))->toBe(vestimentaDeLasCajas(inicioEnLaFecha($comun)));
})->with([
    'Navidad' => ['2026-12-25', '2027-01-10'],
    'Año Nuevo' => ['2027-01-01', '2027-01-10'],
    'Semana Santa' => ['2027-03-26', '2027-04-15'],
    'Fiestas Patrias' => ['2027-07-28', '2027-08-10'],
]);

// --- La preferencia (D5) ---

it('nace prendida y, apagada, las cajas se ven como antes', function () {
    $usuario = User::factory()->create();

    expect(estacionDelInicio(inicioEnLaFecha('2026-10-28', $usuario)))->toBe('primavera');

    app(PreferenciasUsuarioService::class)->cambiar($usuario, EstacionDeLaApariencia::PREFERENCIA, false);
    $inicio = inicioEnLaFecha('2026-10-28', $usuario);

    expect(estacionDelInicio($inicio))->toBeNull()
        ->and(vestimentaDeLasCajas($inicio))->toBe('|');
});

// --- Una caja nueva lo hereda ---

it('una caja nueva de cualquier área hereda el estilo sin configurar nada', function () {
    $registro = app(RegistroModulos::class);

    foreach (AreaModulo::cases() as $area) {
        $registro->registrar(new CajaModulo("nueva-{$area->value}", "Nueva {$area->value}", "/nueva-{$area->value}", permiso: null, area: $area));
    }

    $inicio = inicioEnLaFecha('2027-01-15');
    $estilos = vestimentaDeLasCajas($inicio);

    foreach (AreaModulo::cases() as $area) {
        $caja = $inicio->query("//a[@data-modulo=\"nueva-{$area->value}\"]")?->item(0);

        expect($caja)->toBeInstanceOf(DOMElement::class)
            // Su sección trae las variables de la estación, con el matiz del área (en verano, su color de playa).
            ->and($estilos)->toContain("[data-tq-estacion=\"verano\"] .tq-area[data-area=\"{$area->value}\"] { --tq-e-fondo1: oklch(")
            ->and($estilos)->toContain(sprintf('%.1F);', EstiloEstacion::MATIZ_DE_ESTACION['verano'][$area->value] ?? ColoresMarca::matizDeArea($area, 'claro')))
            // Y su degradado del trazo, con un id propio.
            ->and($caja instanceof DOMElement ? $caja->getAttribute('style') : '')->toContain("url(#tq-trazo-nueva-{$area->value})")
            ->and($inicio->query("//*[@id=\"tq-trazo-nueva-{$area->value}\"]")?->length)->toBe(1);
    }
});

it('cada caja tiene su propio degradado: ningún id se repite en la página', function () {
    $inicio = inicioEnLaFecha('2027-04-15');
    $ids = [];

    foreach ($inicio->query('//*[starts-with(@id, "tq-trazo-")]') ?: [] as $degradado) {
        $ids[] = $degradado instanceof DOMElement ? $degradado->getAttribute('id') : '';
    }

    expect($ids)->not->toBeEmpty()
        ->and($ids)->toBe(array_values(array_unique($ids)));
});

// --- Los colores ---

it('el nombre se lee (AA) en las cuatro estaciones, en claro y en oscuro, en todas las áreas', function (Temporada $estacion, string $tema) {
    foreach (AreaModulo::cases() as $area) {
        expect(EstiloEstacion::contrasteDelNombre($estacion, $tema, $area))->toBeGreaterThanOrEqual(4.5);
    }
})->with(Temporada::cases())->with(['claro', 'oscuro']);

it('el matiz de cada área sale de su color de la marca y no cambia con la estación; el CRM, como el logo, cambia con el tema', function () {
    expect(ColoresMarca::matizDeArea(AreaModulo::Nucleo, 'claro'))->toBe(round(Oklch::matiz(ColoresMarca::AZUL_NOCHE), 1))
        ->and(ColoresMarca::matizDeArea(AreaModulo::Personal, 'oscuro'))->toBe(round(Oklch::matiz(ColoresMarca::DORADO), 1))
        ->and(ColoresMarca::matizDeArea(AreaModulo::Crm, 'claro'))->toBe(round(Oklch::matiz(ColoresMarca::MORADO_CLARO), 1))
        ->and(ColoresMarca::matizDeArea(AreaModulo::Crm, 'oscuro'))->toBe(round(Oklch::matiz(ColoresMarca::MORADO), 1));

    foreach (Temporada::cases() as $estacion) {
        foreach (['claro' => '', 'oscuro' => '.dark '] as $tema => $prefijo) {
            foreach (AreaModulo::cases() as $area) {
                preg_match_all('/^'.preg_quote($prefijo, '/').'\.tq-inicio-cajas\S* \.tq-area\[data-area="'.$area->value.'"\] \{([^}]*)\}/m', EstiloEstacion::css($estacion), $reglas);
                // En verano, cada área con su color de playa (1.4t).
                preg_match_all('/oklch\([\d.]+ [\d.]+ ([\d.]+)\)/', implode(' ', $reglas[1]), $matices);
                $esperado = EstiloEstacion::MATIZ_DE_ESTACION[$estacion->value][$area->value] ?? ColoresMarca::matizDeArea($area, $tema);

                expect($reglas[1])->not->toBeEmpty()
                    ->and(array_values(array_unique($matices[1])))->toBe([sprintf('%.1F', $esperado)]);
            }
        }
    }
});

it('no pide colores que la pantalla no puede mostrar', function () {
    foreach (Temporada::cases() as $estacion) {
        preg_match_all('/oklch\(([\d.]+) ([\d.]+) ([\d.]+)\)/', EstiloEstacion::css($estacion), $colores, PREG_SET_ORDER);

        foreach ($colores as [, $claridad, $croma, $matiz]) {
            expect((float) $croma)->toBeLessThanOrEqual(Oklch::cromaMaxima((float) $claridad, (float) $matiz) + 0.0005);
        }
    }
});

it('en oscuro, las cajas son algo más claras que el fondo gris carbón: no se hunden (1.4t)', function () {
    preg_match('/^oklch\(([\d.]+) /', ColoresMarca::grisesModoOscuro()[950], $fondo);

    foreach (Temporada::cases() as $estacion) {
        preg_match_all('/^\.dark .* \{ (.*) \}$/m', EstiloEstacion::css($estacion), $reglas);

        expect($reglas[1])->not->toBeEmpty();

        foreach ($reglas[1] as $variables) {
            preg_match_all('/--tq-e-fondo[12]: oklch\(([\d.]+) /', $variables, $claridades);

            foreach ($claridades[1] as $claridad) {
                expect((float) $claridad)->toBeGreaterThan((float) $fondo[1] + 0.03, $estacion->value);
            }
        }
    }
});

it('las cajas pesan poco: la primavera clara casi blanca y el verano oscuro con menos intensidad (1.4t)', function () {
    preg_match_all('/^\.tq-inicio-cajas.* \{ (.*) \}$/m', EstiloEstacion::css(Temporada::Primavera), $primavera);

    foreach ($primavera[1] as $variables) {
        preg_match_all('/--tq-e-(?:fondo[12]|ficha[12]): oklch\(([\d.]+) ([\d.]+) /', $variables, $partes, PREG_SET_ORDER);

        foreach ($partes as [, $claridad, $croma]) {
            expect((float) $claridad)->toBeGreaterThanOrEqual(0.88)->and((float) $croma)->toBeLessThanOrEqual(0.06);
        }
    }

    // El verano oscuro, con menos intensidad (el claro, de playa, lo revisa su propia prueba).
    preg_match_all('/^\.dark .*--tq-e-fondo1: oklch\([\d.]+ ([\d.]+) .*--tq-e-fondo2: oklch\([\d.]+ ([\d.]+) /m', EstiloEstacion::css(Temporada::Verano), $verano);

    expect($verano[1])->not->toBeEmpty();

    foreach ([...$verano[1], ...$verano[2]] as $croma) {
        expect((float) $croma)->toBeLessThanOrEqual(0.10);
    }
});

it('en oscuro brillan como fosforescentes: cada caja tiene su neón, intenso y claro, en el matiz de su área (1.4t)', function (Temporada $estacion) {
    preg_match_all('/^\.dark .*data-area="(\w+)"\] \{ (.*) \}$/m', EstiloEstacion::css($estacion), $reglas, PREG_SET_ORDER);

    expect($reglas)->toHaveCount(count(AreaModulo::cases()));

    foreach ($reglas as [, $area, $variables]) {
        expect(preg_match('/--tq-e-neon: oklch\(([\d.]+) ([\d.]+) ([\d.]+)\)/', $variables, $neon))->toBe(1)
            ->and((float) $neon[1])->toBeGreaterThanOrEqual(0.7)
            ->and((float) $neon[2])->toBeGreaterThanOrEqual(0.08)
            ->and((float) $neon[3])->toBe(EstiloEstacion::MATIZ_DE_ESTACION[$estacion->value][$area] ?? ColoresMarca::matizDeArea(AreaModulo::from($area), 'oscuro'));
    }
})->with(Temporada::cases());

it('en verano, colores de playa que se distinguen: sol, piscina, mar y coral, a 30° o más entre dos áreas (1.4t)', function () {
    $matices = EstiloEstacion::MATIZ_DE_ESTACION['verano'];

    expect(array_keys($matices))->toEqualCanonicalizing(array_map(fn (AreaModulo $area): string => $area->value, AreaModulo::cases()));

    foreach ($matices as $a => $matizA) {
        foreach ($matices as $b => $matizB) {
            $distancia = abs($matizA - $matizB);

            expect($a === $b || min($distancia, 360 - $distancia) >= 30)->toBeTrue("{$a} y {$b}");
        }
    }

    // Claro: fondo claro y nombre oscuro.
    foreach (AreaModulo::cases() as $area) {
        expect(EstiloEstacion::lch(Temporada::Verano, 'claro', $area, 'fondo2')[0])->toBeGreaterThanOrEqual(0.85)
            ->and(EstiloEstacion::lch(Temporada::Verano, 'claro', $area, 'texto')[0])->toBeLessThanOrEqual(0.4);
    }
});

it('en claro las cajas tienen relieve: borde y sombras del color de cada caja (1.4t)', function () {
    $estilos = (string) file_get_contents(base_path('app/Modules/Nucleo/resources/views/filament/widgets/cajas-modulos.blade.php'));

    expect($estilos)->toContain('html:not(.dark) .tq-inicio-cajas[data-tq-estacion] .tq-caja-modulo { border: 1px solid color-mix(in oklab, var(--tq-e-sombra)');
});

it('el verano no lleva ficha en el ícono; primavera e invierno sí (1.4t)', function () {
    $estilos = (string) file_get_contents(base_path('app/Modules/Nucleo/resources/views/filament/widgets/cajas-modulos.blade.php'));

    expect($estilos)->toContain('.tq-inicio-cajas:is([data-tq-estacion="primavera"], [data-tq-estacion="invierno"]) .tq-caja-modulo-marco {')
        ->not->toMatch('/data-tq-estacion="verano"\][^{]*\.tq-caja-modulo-marco/');
});

it('el azul del Núcleo no se vuelve azul rey: su intensidad queda baja aun en verano', function () {
    preg_match_all('/oklch\(([\d.]+) ([\d.]+) 264\.5\)/', EstiloEstacion::css(Temporada::Verano).EstiloEstacion::css(Temporada::Primavera), $colores, PREG_SET_ORDER);

    expect($colores)->not->toBeEmpty();

    foreach ($colores as [, , $croma]) {
        expect((float) $croma)->toBeLessThanOrEqual(0.14);
    }
});

// --- El modo desarrollo (D6) ---

it('en el modo desarrollo, la fecha simulada cambia la estación y un clic elige cualquiera sin guardar nada', function () {
    app()->detectEnvironment(fn (): string => 'local');
    config(['app.debug' => true, 'nucleo.modo_desarrollo' => true]);
    $usuario = User::factory()->create();
    $this->actingAs($usuario);
    $this->travelTo(CarbonImmutable::parse('2026-10-28 10:00', 'America/Lima'));
    ModoDesarrollo::prender();

    ModoDesarrollo::simular(CarbonImmutable::parse('2027-07-15 10:00', 'America/Lima'));
    expect(EstacionDeLaApariencia::para($usuario))->toBe(Temporada::Invierno);

    Livewire::test(BarraDesarrollo::class)->call('elegirEstacion', 'verano');
    expect(EstacionDeLaApariencia::para($usuario))->toBe(Temporada::Verano)
        ->and(ModoDesarrollo::eleccion(EstacionDelInicio::CLAVE))->toBe('verano');

    Livewire::test(BarraDesarrollo::class)->call('elegirEstacion', 'no-existe');
    expect(EstacionDeLaApariencia::para($usuario))->toBe(Temporada::Verano);

    Livewire::test(BarraDesarrollo::class)->call('elegirEstacion', null);
    expect(EstacionDeLaApariencia::para($usuario))->toBe(Temporada::Invierno)
        ->and($this->getConnection()->table('preferencias_usuario')->where('user_id', $usuario->id)->count())->toBe(0);
});

it('sin el modo desarrollo, una elección vieja en la sesión no cuenta', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-28 10:00', 'America/Lima'));
    session()->put('nucleo.modo_desarrollo.piezas.'.EstacionDelInicio::CLAVE, 'invierno');

    expect(EstacionDeLaApariencia::para(User::factory()->create()))->toBe(Temporada::Primavera);
});
