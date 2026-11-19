<?php

declare(strict_types=1);

use App\Modules\Crm\Cuentas\CuentasServiceProvider;
use App\Modules\Crm\Cuentas\Models\Cuenta;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\FajaOportunidades;
use App\Modules\Crm\Oportunidades\OportunidadesServiceProvider;
use App\Modules\Crm\Oportunidades\Services\LimiteCajasFaja;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Models\Estado;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Modules\Crm\Oportunidades\Fixtures\OportunidadesDePrueba;

/*
 * La faja con muchas oportunidades, el teclado y las pausas (plan 2.21b, paso 8; pedido B6 y B7): con decenas de
 * oportunidades de prueba, cada lugar dibuja como mucho 11 cajas y su pila (D8), y el documento queda acotado; las
 * pilas, los terminales y las viñetas se operan con Enter y Espacio; lo que se repite se detiene fuera de la vista.
 * Empresas inventadas.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
    test()->travelTo('2026-11-10 10:00');
    $this->cuentaMedida = Cuenta::factory()->create([
        'party_id' => Party::factory()->juridica()->create(['razon_social' => 'Ferretería Los Cristales S.A.C.'])->id,
    ]);
});

function ftmEntrar(): User
{
    $usuario = User::factory()->create()->givePermissionTo([OportunidadesServiceProvider::PERMISO_VER, CuentasServiceProvider::PERMISO_VER]);
    test()->actingAs($usuario);

    return $usuario;
}

/** `cantidad` oportunidades en la etapa, sin pasar por las transiciones (la pantalla solo lee). */
function ftmLlenar(string $etapa, int $cantidad, User $quien): void
{
    $ids = [];
    for ($n = 1; $n <= $cantidad; $n++) {
        $ids[] = OportunidadesDePrueba::crear(test()->cuentaMedida->id, ['nombre' => "Enlace {$etapa} {$n}", 'monto_estimado' => (string) (1000 * $n)], $quien)->id;
    }
    DB::table('oportunidades')->whereIn('id', $ids)->update([
        'estado_id' => Estado::query()->where('documento_type', 'oportunidad')->where('codigo', $etapa)->value('id'),
        'en_etapa_desde' => '2026-11-01',
        'fecha_cierre_real' => in_array($etapa, ['ganada', 'perdida'], true) ? '2026-11-05' : null,
    ]);
}

/** La faja sin sus estilos ni sus guiones: solo el documento que dibuja el navegador. */
function ftmDocumento(string $html): string
{
    return (string) preg_replace('#<(style|script)\b[^>]*>.*?</\1>#s', '', $html);
}

test('con decenas de oportunidades, cada lugar dibuja como mucho 11 cajas y su pila, y el documento queda acotado', function () {
    $quien = ftmEntrar();
    ftmLlenar('propuesta_enviada', 40, $quien);
    foreach (['prospecto', 'calificada', 'negociacion'] as $etapa) {
        ftmLlenar($etapa, 13, $quien);
    }
    ftmLlenar('ganada', 15, $quien);
    ftmLlenar('perdida', 15, $quien);

    $inicio = hrtime(true);
    $html = Livewire::test(FajaOportunidades::class)->html();
    $milisegundos = (hrtime(true) - $inicio) / 1e6;
    $documento = ftmDocumento($html);

    $lugares = ['prospecto', 'calificada', 'propuesta_enviada', 'negociacion', 'ganadas', 'perdidas'];
    foreach ($lugares as $lugar) {
        expect(substr_count($documento, 'data-lugar="'.$lugar.'"'))->toBe(LimiteCajasFaja::DIBUJADAS_CON_PILA)
            ->and($documento)->toContain('data-pila="'.$lugar.'"');
    }
    expect($documento)->toContain('+29 más')
        ->and(substr_count($documento, 'data-caja="'))->toBe(6 * LimiteCajasFaja::DIBUJADAS_CON_PILA)
        ->and(substr_count($documento, 'data-pila="'))->toBe(6)
        // Con pila, todas solo con el código: el brillo desenfocado del grabado es solo de las completas.
        ->and(substr_count($documento, 'tq-faja-modo-completa'))->toBe(0);

    // Medida del documento (el paso 8 anota los números en la bitácora): la faja entera, con 66 cajas y 6 pilas, queda
    // por debajo de 2 000 elementos. Cada caja son 12 (lugar, desplazo, cubo, 6 caras, grabado y su texto).
    $elementos = preg_match_all('/<[a-zA-Z][^>]*>/', $documento);
    expect($elementos)->toBeLessThan(2000)
        ->and($milisegundos)->toBeLessThan(10000);
});

test('pilas, terminales y viñetas se operan con Enter y Espacio; lo oculto no toma el foco', function () {
    $quien = ftmEntrar();
    ftmLlenar('calificada', 13, $quien);

    $html = Livewire::test(FajaOportunidades::class)->html();

    expect($html)
        // La pila «+N más» y las cajas.
        ->toContain('x-on:keydown.space.prevent="abrirLugar(\'calificada\')"')
        ->toMatch('/x-on:keydown\.space\.prevent="abrirCaja\(\d+\)"/')
        // Los terminales de cada zona y los de las salas.
        ->toContain('x-on:keydown.space.prevent="verLugar(\'calificada\')"')
        ->toContain('x-on:keydown.space.prevent="entrarGanadas()"')
        ->toContain('x-on:keydown.space.prevent="entrarPerdidas()"')
        // Las viñetas: enfocables, con su nombre para el lector, y el foco pasa a la zona.
        ->toMatch('/data-vineta="calificada"[^>]*tabindex="0" role="button" aria-label="Ir a Calificada: 13 oportunidades"/')
        ->toContain('x-on:keydown.enter.prevent="irDesdeVineta(1)"')
        // Lo que se oculta según la distancia se oculta también para Tab.
        ->toContain('.tq-faja-visor.lejos .tq-faja-ir { opacity: 0; visibility: hidden;')
        ->toContain('.tq-faja-visor.cerca .tq-faja-vineta { opacity: 0; visibility: hidden;')
        // La cámara sigue al foco y el visor no se desplaza solo; el foco no se pierde al cambiar de nivel.
        ->toContain('x-on:focusin="alEnfocar($event)"')
        ->toContain('x-on:scroll="$el.scrollTop = 0; $el.scrollLeft = 0"')
        ->toContain('activo.getClientRects().length === 0) this.$root.focus({ preventScroll: true })');
});

test('lo que se repite se detiene fuera de la vista o con la pestaña oculta, y nada anima sin necesidad', function () {
    ftmEntrar();

    $html = Livewire::test(FajaOportunidades::class)->html();

    expect($html)
        // La cámara observa si la faja se ve y si la pestaña está visible; en pausa congela el CSS y avisa al hombrecito.
        ->toContain('new IntersectionObserver(')
        ->toContain("document.addEventListener('visibilitychange', pestana)")
        ->toContain("'en-pausa': pausada")
        ->toContain('.tq-faja-visor.en-pausa *, .tq-faja-visor.en-pausa *::before, .tq-faja-visor.en-pausa *::after { animation-play-state: paused !important; }')
        ->toContain("new CustomEvent('tq-faja-pausa'")
        ->toContain("escuchar(h.visor, 'tq-faja-pausa'")
        // La cortina pide cuadros solo cuando se ve y se mueve.
        ->toContain('if (this.pausada || this.sala !== null || c.tiras.length === 0) { this.pararCortina(); return }')
        // Las animaciones que se repiten van con transform (sin repintar): la banda y el barrido de los escáneres.
        ->toContain('@keyframes tq-faja-banda { to { transform: translateX(280px); } }')
        ->toContain('@keyframes tq-faja-barrido { 0%, 100% { transform: translateY(10%); } 50% { transform: translateY(88%); } }')
        ->not->toContain('background-position: 280px 0')
        // `will-change` solo mientras se mueve una caja.
        ->and(substr_count($html, 'will-change'))->toBe(2)
        ->and($html)->toContain('.levantada { will-change: transform; }')
        // Las hojas de otoño del núcleo no se ven mientras está la faja.
        ->toContain('html:has(.tq-faja-visor) .tq-barra-hojas { visibility: hidden; }');
});

test('la clase que la faja oculta sigue en la barra del núcleo (contrato con `barra-estacion`)', function () {
    // La faja oculta `.tq-barra-hojas` por CSS; si el núcleo la renombra, esta prueba avisa en lugar de volver las hojas.
    $barra = (string) file_get_contents(base_path('app/Modules/Nucleo/resources/views/filament/barra-estacion.blade.php'));

    expect($barra)->toContain('<span class="tq-barra-hojas"');
});
