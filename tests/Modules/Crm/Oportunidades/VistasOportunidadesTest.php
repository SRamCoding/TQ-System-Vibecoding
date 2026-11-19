<?php

declare(strict_types=1);

use App\Modules\Crm\Cuentas\CuentasServiceProvider;
use App\Modules\Crm\Cuentas\Models\Cuenta;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\OportunidadResource;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\FajaOportunidades;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\LineaDeTiempoOportunidades;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\ListarOportunidades;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\TableroOportunidades;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\TarjetasOportunidades;
use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Crm\Oportunidades\OportunidadesServiceProvider;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Models\Estado;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Modules\Crm\Oportunidades\Fixtures\OportunidadesDePrueba;

/*
 * Las cuatro vistas de Oportunidades (plan 2.21c, paso 4; D1 y D12): Tablero (la de entrada) · Línea de tiempo · Lista ·
 * Faja 3D, en pestañas con la activa marcada, y la fila de cifras arriba de todas. Las direcciones viejas siguen
 * sirviendo. Empresas inventadas.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
    test()->travelTo('2026-11-11 10:00');
    $this->cuentaVistas = Cuenta::factory()->create([
        'party_id' => Party::factory()->juridica()->create(['razon_social' => 'Vidriería Inventada S.A.C.'])->id,
    ]);
});

function voEntrar(): User
{
    $usuario = User::factory()->create()->givePermissionTo([OportunidadesServiceProvider::PERMISO_VER, CuentasServiceProvider::PERMISO_VER]);
    test()->actingAs($usuario);

    return $usuario;
}

/** Crea una oportunidad y la pone en el estado sin pasar por las transiciones: las vistas solo leen. */
function voEnEstado(string $estado, string $monto, string $cierreEstimado, string $desde = '2026-11-10'): Oportunidad
{
    $oportunidad = OportunidadesDePrueba::crear(test()->cuentaVistas->id, ['monto_estimado' => $monto, 'fecha_cierre_estimada' => $cierreEstimado]);
    DB::table('oportunidades')->where('id', $oportunidad->id)->update([
        'estado_id' => Estado::query()->where('documento_type', 'oportunidad')->where('codigo', $estado)->value('id'),
        'en_etapa_desde' => $desde,
        'fecha_cierre_real' => in_array($estado, ['ganada', 'perdida', 'anulada'], true) ? '2026-11-05' : null,
    ]);

    return $oportunidad->refresh();
}

/**
 * Las pestañas de vista de un HTML: clave => [etiqueta, activa].
 *
 * @return array<string, array{0: string, 1: bool}>
 */
function voPestanas(string $html): array
{
    preg_match('/<nav[^>]*aria-label="Vistas de Oportunidades".*?<\/nav>/s', $html, $barra);
    preg_match_all('/<a([^>]*data-vista="([^"]+)"[^>]*)>(.*?)<\/a>/s', $barra[0] ?? '', $enlaces, PREG_SET_ORDER);
    $pestanas = [];

    foreach ($enlaces as [, $atributos, $clave, $contenido]) {
        $pestanas[$clave] = [trim(strip_tags($contenido)), str_contains($atributos, 'aria-current="page"')];
    }

    return $pestanas;
}

/** El valor y el detalle de una cifra, como texto. */
function voCifra(string $html, string $clave): string
{
    preg_match('/data-cifra="'.$clave.'".*?<div class="tq-vistas-cifra-valor[^"]*">(.*?)<\/div>\s*<div class="tq-vistas-cifra-detalle">(.*?)<\/div>/s', $html, $cifra);

    // D16 de la 2.21c: lo que se ve; el monto exacto va aparte, para el lector de pantalla (`voCifraExacta`).
    $visible = fn (string $parte): string => trim((string) preg_replace('/\s+/', ' ', strip_tags((string) preg_replace(['/<style>.*?<\/style>/s', '/<span class="tq-monto-leer">.*?<\/span>/s'], '', $parte))));

    return html_entity_decode($visible($cifra[1] ?? '')).' | '.html_entity_decode($visible($cifra[2] ?? ''));
}

/** El monto exacto de una cifra, en su `title` y para el lector de pantalla (D16 de la 2.21c). */
function voCifraExacta(string $html, string $clave): string
{
    preg_match('/data-cifra="'.$clave.'".*?<span class="tq-monto-leer">(.*?)<\/span>/s', $html, $exacta);

    return html_entity_decode($exacta[1] ?? '');
}

test('la entrada de Oportunidades abre el Tablero; cada vista tiene su dirección y marca su pestaña', function () {
    voEntrar();

    expect(OportunidadResource::getUrl())->toBe(TableroOportunidades::getUrl())
        ->and(parse_url(TableroOportunidades::getUrl(), PHP_URL_PATH))->toBe('/oportunidades')
        ->and(parse_url(LineaDeTiempoOportunidades::getUrl(), PHP_URL_PATH))->toBe('/oportunidades/linea-de-tiempo')
        ->and(parse_url(ListarOportunidades::getUrl(), PHP_URL_PATH))->toBe('/oportunidades/lista')
        ->and(parse_url(FajaOportunidades::getUrl(), PHP_URL_PATH))->toBe('/oportunidades/faja');

    $paginas = [
        // Plan 2.21d, D11: «Tarjetas» va primero; el Tablero sigue siendo la entrada.
        'tarjetas' => [TarjetasOportunidades::class, 'Oportunidades'],
        'tablero' => [TableroOportunidades::class, 'Tablero de oportunidades'],
        'linea-de-tiempo' => [LineaDeTiempoOportunidades::class, 'Línea de tiempo de oportunidades'],
        'lista' => [ListarOportunidades::class, 'Oportunidades'],
        'faja' => [FajaOportunidades::class, 'Faja de oportunidades'],
    ];

    foreach ($paginas as $activa => [$pagina, $titulo]) {
        $html = Livewire::withQueryParams([])->test($pagina)->assertSee($titulo)->html();
        $esperadas = [];
        foreach (['tarjetas' => 'Tarjetas', 'tablero' => 'Tablero', 'linea-de-tiempo' => 'Línea de tiempo', 'lista' => 'Lista', 'faja' => 'Faja 3D'] as $clave => $etiqueta) {
            $esperadas[$clave] = [$etiqueta, $clave === $activa];
        }

        expect(voPestanas($html))->toBe($esperadas)
            ->and($html)->toContain('href="'.e($paginas['lista'][0]::getUrl()).'"');
    }

    $this->get('/oportunidades')->assertOk()->assertSee('Tablero de oportunidades');
});

test('sin ver oportunidades no se entra a ninguna vista', function () {
    $this->actingAs(User::factory()->create());

    foreach (['/oportunidades', '/oportunidades/tarjetas', '/oportunidades/linea-de-tiempo', '/oportunidades/lista', '/oportunidades/faja', '/oportunidades/tablero'] as $direccion) {
        $this->get($direccion)->assertForbidden();
    }
});

test('las direcciones viejas sirven: /tablero lleva al Tablero y ?aviso= a la Lista filtrada', function () {
    voEntrar();

    $this->get('/oportunidades/tablero')->assertRedirect(TableroOportunidades::getUrl());
    // Con lo que traía la dirección: la ficha o el aviso siguen.
    $this->get('/oportunidades/tablero?ficha=7')->assertRedirect(TableroOportunidades::getUrl(['ficha' => '7']));
    $this->get('/oportunidades/tablero?aviso=estancadas')->assertRedirect(TableroOportunidades::getUrl(['aviso' => 'estancadas']));
    $this->get('/oportunidades?aviso=estancadas')->assertRedirect(ListarOportunidades::getUrl(['aviso' => 'estancadas']));
    $this->get('/oportunidades?aviso=cualquiera')->assertOk()->assertSee('Tablero de oportunidades');
});

test('la fila de cifras va arriba de las cuatro vistas con embudo, ponderado, este mes, atascadas y vencidas', function () {
    voEntrar();
    voEnEstado('prospecto', '1000.00', '2026-11-25');
    voEnEstado('calificada', '2000.00', '2026-11-05', desde: '2026-10-01');
    voEnEstado('propuesta_enviada', '4000.00', '2026-12-15');
    voEnEstado('negociacion', '8000.00', '2026-10-31');
    voEnEstado('ganada', '30000.00', '2026-11-01');
    voEnEstado('anulada', '50000.00', '2026-11-01');

    foreach ([TableroOportunidades::class, LineaDeTiempoOportunidades::class, ListarOportunidades::class, FajaOportunidades::class] as $pagina) {
        $html = Livewire::withQueryParams([])->test($pagina)->html();

        // Ponderado: 1 000 × 10 % + 2 000 × 25 % + 4 000 × 50 % + 8 000 × 75 % = 8 600.
        expect(voCifra($html, 'embudo'))->toBe('S/ 15k | 4 oportunidades abiertas')
            ->and(voCifra($html, 'ponderado'))->toBe('S/ 8.6k | Cada monto por la probabilidad de su etapa')
            ->and(voCifra($html, 'este-mes'))->toBe('S/ 3k | 2 oportunidades abiertas')
            ->and(voCifra($html, 'atascadas'))->toBe('1 | S/ 2k · pasan los días máximos de su etapa')
            ->and(voCifra($html, 'vencidas'))->toBe('2 | S/ 10k · siguen abiertas')
            ->and(voCifraExacta($html, 'embudo'))->toBe('S/ 15,000.00')
            ->and(voCifraExacta($html, 'vencidas'))->toBe('S/ 10,000.00 · siguen abiertas')
            ->and(substr_count($html, 'class="tq-vistas-cifra-valor tq-vistas-cifra-alerta"'))->toBe(2)
            ->and(strpos($html, 'data-vistas'))->toBeLessThan(strpos($html, 'data-cifra="embudo"'));
    }
});
