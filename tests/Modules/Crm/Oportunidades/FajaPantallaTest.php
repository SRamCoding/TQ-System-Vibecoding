<?php

declare(strict_types=1);

use App\Modules\Crm\Cuentas\CuentasServiceProvider;
use App\Modules\Crm\Cuentas\Models\Cuenta;
use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Oportunidades\Filament\FajaDeVidrio;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\FajaOportunidades;
use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Crm\Oportunidades\OportunidadesServiceProvider;
use App\Modules\Crm\Oportunidades\Services\OportunidadesService;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Models\Estado;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Modules\Crm\Oportunidades\Fixtures\OportunidadesDePrueba;

/*
 * La pantalla de la faja de vidrio (plan 2.21b, paso 3): permiso, las cuatro zonas con sus viñetas, las cajas según el
 * límite de la zona (D8), la grieta de las atascadas, que las anuladas no salen, la accesibilidad de las cajas (D14) y
 * la guarda de «reducir animaciones» (D12). Empresas inventadas.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
    test()->travelTo('2026-11-10 10:00');
    $this->cuentaFaja = Cuenta::factory()->create([
        'party_id' => Party::factory()->juridica()->create(['razon_social' => 'Distribuidora Vidrio S.A.C.'])->id,
    ]);
});

function fpEntrar(): User
{
    $usuario = User::factory()->create()->givePermissionTo([OportunidadesServiceProvider::PERMISO_VER, CuentasServiceProvider::PERMISO_VER]);
    test()->actingAs($usuario);

    return $usuario;
}

/** Crea una oportunidad y la pone en la etapa sin pasar por las transiciones: la pantalla solo lee. */
function fpEnEtapa(string $etapa, string $nombre, string $monto = '10000.00', string $desde = '2026-11-10'): Oportunidad
{
    $oportunidad = OportunidadesDePrueba::crear(test()->cuentaFaja->id, ['nombre' => $nombre, 'monto_estimado' => $monto]);
    DB::table('oportunidades')->where('id', $oportunidad->id)->update([
        'estado_id' => Estado::query()->where('documento_type', 'oportunidad')->where('codigo', $etapa)->value('id'),
        'en_etapa_desde' => $desde,
        'fecha_cierre_real' => in_array($etapa, ['ganada', 'perdida'], true) ? '2026-11-05' : null,
    ]);

    return $oportunidad->refresh();
}

/** La etiqueta de la cuenta como la da Cuentas (razón social y documento). */
function fpCuenta(): string
{
    $id = test()->cuentaFaja->id;

    return app(CuentasService::class)->resumenesDe([$id])[$id]->etiquetaParaSelector();
}

function fpHtml(): string
{
    return Livewire::test(FajaOportunidades::class)->html();
}

/**
 * Los grabados de las cajas de un lugar, como texto.
 *
 * @return list<string>
 */
function fpGrabados(string $html, string $lugar): array
{
    preg_match_all('/data-lugar="'.$lugar.'".*?<div class="tq-faja-grabado">(.*?)<\/div>/s', $html, $coincidencias);

    return array_map(fn (string $grabado): string => trim((string) preg_replace('/\s+/', ' ', strip_tags(str_replace('<', ' <', $grabado)))), $coincidencias[1]);
}

test('sin ver oportunidades no se entra a la faja; con el permiso, abre', function () {
    $this->actingAs(User::factory()->create())->get('/oportunidades/faja')->assertForbidden();

    fpEntrar();
    $this->get('/oportunidades/faja')->assertOk()->assertSee('Faja de oportunidades')->assertSee('data-faja', false);
});

test('las cuatro zonas con su rótulo y su viñeta, que cuentan lo mismo que totalesDeLaFaja', function () {
    fpEntrar();
    fpEnEtapa('prospecto', 'Red del colegio', '1000.00');
    fpEnEtapa('prospecto', 'Cámaras del mercado', '2500.50');
    fpEnEtapa('calificada', 'Fibra del hospital');
    fpEnEtapa('negociacion', 'Centro de datos');

    $html = fpHtml();
    $totales = app(OportunidadesService::class)->totalesDeLaFaja();

    foreach (['prospecto' => 'PROSPECTO', 'calificada' => 'CALIFICADA', 'propuesta_enviada' => 'PROPUESTA ENVIADA', 'negociacion' => 'NEGOCIACIÓN'] as $lugar => $rotulo) {
        expect(preg_match('/data-vineta="'.$lugar.'"[^>]*><b>(\d+)<\/b>/', $html, $vineta))->toBe(1)
            ->and((int) $vineta[1])->toBe($totales[$lugar]->cantidad)
            ->and($html)->toContain('data-rotulo="'.$lugar.'"')->toContain($rotulo)
            ->and($html)->toContain('data-escaner=');
    }

    expect($totales['prospecto']->cantidad)->toBe(2)
        ->and($html)->toContain('title="S/ 3,500.50">10 % · S/ 3.5k')
        ->and(substr_count($html, 'data-mini="'))->toBe(4);
});

test('hasta 6 cajas: nombre, monto y entidad; de 7 a 12: solo el código; más de 12: 11 cajas y la pila «+N más»', function () {
    fpEntrar();
    foreach (range(1, 3) as $n) {
        fpEnEtapa('prospecto', "Prospecto {$n}", (string) (1000 * $n));
    }
    foreach (range(1, 9) as $n) {
        fpEnEtapa('calificada', "Calificada {$n}");
    }
    foreach (range(1, 15) as $n) {
        fpEnEtapa('propuesta_enviada', "Propuesta {$n}", (string) (1000 * $n));
    }

    $html = fpHtml();

    $completas = fpGrabados($html, 'prospecto');
    expect($completas)->toHaveCount(3)
        ->and($completas[0])->toBe('Prospecto 3 S/ 3k '.fpCuenta())
        ->and(preg_match_all('/class="[^"]*tq-faja-modo-completa/', $html))->toBe(3);

    $codigos = fpGrabados($html, 'calificada');
    expect($codigos)->toHaveCount(9);
    foreach ($codigos as $grabado) {
        expect($grabado)->toMatch('/^\S+$/')->not->toContain('Calificada');
    }

    $conPila = fpGrabados($html, 'propuesta_enviada');
    expect($conPila)->toHaveCount(11)
        ->and(preg_match_all('/class="[^"]*tq-faja-modo-codigo/', $html))->toBe(9 + 11)
        ->and($html)->toContain('data-pila="propuesta_enviada"')->toContain('+4 más')
        ->and(substr_count($html, 'data-pila="'))->toBe(1);

    // Las 11 de mayor monto: la de 1 000 y las de 2 000 a 4 000 quedan en la pila.
    $dibujadas = Oportunidad::query()->whereIn('nombre', ['Propuesta 15', 'Propuesta 5'])->pluck('id', 'nombre');
    expect($html)->toContain('data-caja="'.$dibujadas['Propuesta 15'].'"')->toContain('data-caja="'.$dibujadas['Propuesta 5'].'"')
        ->and($html)->not->toContain('data-caja="'.Oportunidad::query()->where('nombre', 'Propuesta 4')->value('id').'"');
});

test('una atascada lleva la grieta ámbar; sin próxima acción hasta CRM-05 no hay punto que late', function () {
    fpEntrar();
    // Solo Calificada trae máximo de días (14) en los parámetros iniciales.
    fpEnEtapa('calificada', 'Al día');
    $atascada = fpEnEtapa('calificada', 'Olvidada hace meses', desde: '2026-08-01');

    $html = fpHtml();

    expect(substr_count($html, 'class="tq-faja-grieta"'))->toBe(1)
        ->and(preg_match('/tq-faja-atascada"[^>]*data-caja="'.$atascada->id.'"/', $html))->toBe(1)
        ->and($html)->not->toContain('class="tq-faja-pulso"');
});

test('las anuladas no se dibujan; las ganadas y perdidas no van en las zonas sino en sus salas (paso 5)', function () {
    fpEntrar();
    fpEnEtapa('prospecto', 'Abierta visible');
    fpEnEtapa('anulada', 'Anulada escondida');
    $ganada = fpEnEtapa('ganada', 'Ganada en su sala');
    $perdida = fpEnEtapa('perdida', 'Perdida en su sala');

    $html = fpHtml();

    expect($html)->toContain('Abierta visible')->not->toContain('Anulada escondida')
        ->and(preg_match('/data-caja="'.$ganada->id.'" data-lugar="ganadas"/', $html))->toBe(1)
        ->and(preg_match('/data-caja="'.$perdida->id.'" data-lugar="perdidas"/', $html))->toBe(1)
        ->and(fpGrabados($html, 'prospecto'))->toHaveCount(1);
});

test('cada caja es enfocable, con su número, nombre, cuenta y monto para el lector, y Enter la abre', function () {
    fpEntrar();
    $oportunidad = fpEnEtapa('calificada', 'Enlaces de radio', '24715.10');

    $html = fpHtml();

    expect(preg_match('/<div class="[^"]*tq-faja-caja[^"]*"[^>]*data-caja="'.$oportunidad->id.'"[^>]*>/s', $html, $caja))->toBe(1)
        ->and($caja[0])->toContain('tabindex="0"')->toContain('role="button"')
        ->toContain('aria-label="'.e($oportunidad->numero.', Enlaces de radio, '.fpCuenta().', S/ 24,715.10').'"')
        ->toContain('x-on:keydown.enter.prevent="abrirCaja('.$oportunidad->id.')"')
        ->toContain('data-version="'.$oportunidad->version.'"');
});

test('la faja respeta «reducir animaciones» (sistema y preferencia) y no toma el teclado mientras se escribe', function () {
    fpEntrar();

    $html = fpHtml();

    expect($html)->toContain('@media (prefers-reduced-motion: reduce)')
        // Paso 8: una sola regla para todo lo de la faja (sistema y preferencia), sin animaciones ni transiciones.
        ->toContain('.tq-faja *, .tq-faja *::before, .tq-faja *::after { animation: none !important; transition: none !important; }')
        ->toContain('html.tq-sin-animaciones .tq-faja *, html.tq-sin-animaciones .tq-faja *::before')
        ->toContain("classList.contains('tq-sin-animaciones')")
        ->toContain("closest('input, textarea, select, [contenteditable]");
});

test('el alto de la caja crece con el monto y tiene tope', function () {
    expect(FajaDeVidrio::alto(null))->toBe(90)
        ->and(FajaDeVidrio::alto('410000'))->toBe(210)
        ->and(FajaDeVidrio::alto('100000000'))->toBe(270);
});

test('el hombrecito (paso 7) es decorativo: oculto al lector, sin tomar el puntero, solo en el navegador y quieto con «reducir animaciones»', function () {
    fpEntrar();
    fpEnEtapa('prospecto', 'Cableado de sede');

    $html = fpHtml();
    preg_match('/<div[^>]*data-hueco="hombrecito"[^>]*>/', $html, $hueco);

    expect($hueco)->toHaveCount(1)
        ->and($hueco[0])->toContain('aria-hidden="true"')
        ->toContain('wire:ignore')
        ->toContain('x-data="tqHombrecito(')
        ->and($html)->toContain('data-hombre-zzz')
        ->toContain('.tq-faja .tq-hombre-hueco, .tq-faja .tq-hombre-hueco * { pointer-events: none; }')
        // Un uniforme por zona (pedido B4), en un solo lugar.
        ->toContain("nombre: 'explorador'")->toContain("nombre: 'inspector'")->toContain("nombre: 'mensajero'")->toContain("nombre: 'ejecutivo'")
        // El desorden y su lugar viven en el navegador (D11), con try/catch.
        ->toContain('window.sessionStorage.getItem(CLAVE)')
        ->toContain('window.sessionStorage.setItem(CLAVE')
        // Reducir animaciones (D12) y la pestaña oculta.
        ->toContain('html.tq-sin-animaciones .tq-faja .tq-hombre *')
        ->toContain("'visibilitychange'")
        // Los ganchos de la faja que usa.
        ->toContain("new CustomEvent('tq-faja-empujar'")
        ->toContain("new CustomEvent('tq-faja-tocar'")
        ->toContain("new CustomEvent('tq-faja-desordenar'")
        ->toContain("new CustomEvent('tq-faja-devuelta'")
        ->toContain("new CustomEvent('tq-faja-redibujada'");
});

/** La declaración de una regla de CSS de la faja (el texto entre sus llaves). */
function fpRegla(string $html, string $selector): string
{
    preg_match('/\n\s*'.preg_quote($selector, '/').' \{(.*?)\}/s', $html, $regla);

    return $regla[1] ?? '';
}

test('el mundo 3D es oscuro siempre y todo lo demás sigue el tema claro u oscuro del panel (D10 de la 2.21c)', function () {
    fpEntrar();
    fpEnEtapa('prospecto', 'Cableado de sede');

    $html = fpHtml();
    $claro = fpRegla($html, '.tq-faja');
    $oscuro = fpRegla($html, '.dark .tq-faja');

    // Los colores del mundo se definen una vez y el tema oscuro no los toca.
    expect($claro)->toContain('--tq-faja-noche: #0B0A1A')
        ->and($oscuro)->not->toContain('--tq-faja-noche')
        ->and($html)->toContain('.tq-faja-mundo, .tq-despiece-escena { color-scheme: dark; }')
        ->and(fpRegla($html, '.tq-despiece-escena'))->toContain('var(--tq-faja-noche)')
        // Lo de alrededor, con las variables del panel en los dos temas.
        ->and($claro)->toContain('--tq-faja-ui-fondo: #fff')->toContain('--tq-faja-ui-tinta: var(--gray-950)')->toContain('--tq-faja-ui-acento: var(--morado-600)')
        ->and($oscuro)->toContain('--tq-faja-ui-fondo: var(--gray-900)')->toContain('--tq-faja-ui-tinta: #fff')->toContain('--tq-faja-ui-acento: var(--morado-400)');

    // Botones de arriba, ayudas, avisos, etiquetas de lugar, barra de etapas, y el marco y la cabecera del despiece.
    foreach (['.tq-faja-boton', '.tq-faja-lugar', '.tq-faja-pista', '.tq-faja-mensaje', '.tq-faja-mini', '.tq-faja .tq-despiece', '.tq-despiece-panel'] as $selector) {
        $regla = fpRegla($html, $selector);

        expect($regla)->toContain('var(--tq-faja-ui-')
            ->and($regla)->not->toContain('rgba(20, 18, 43')
            ->and($regla)->not->toContain('var(--tq-faja-noche)')
            ->and($regla)->not->toContain('var(--tq-faja-tinta)');
    }

    expect(fpRegla($html, '.tq-despiece-sub'))->toContain('var(--tq-faja-ui-suave)')
        ->and(fpRegla($html, '.tq-despiece-atascada'))->toContain('var(--tq-faja-ui-ambar)')
        // Nada fuerza el modo oscuro alrededor: ni una clase `dark` ni `color-scheme` fuera del mundo (los diálogos y las
        // tablas emergentes de Filament siguen el tema).
        ->and($html)->not->toMatch('/class="[^"]*\bdark\b[^"]*"/')
        ->and(substr_count($html, 'color-scheme'))->toBe(1);
});
