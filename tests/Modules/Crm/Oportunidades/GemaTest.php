<?php

declare(strict_types=1);

use App\Modules\Crm\Oportunidades\Enums\LugarFaja;
use App\Modules\Crm\Oportunidades\Enums\TallaDeGema;
use App\Modules\Crm\Oportunidades\Filament\DibujoDeGema;
use App\Modules\Crm\Oportunidades\Filament\EscalaDeGema;
use App\Modules\Nucleo\Navegacion\ColoresMarca;
use Illuminate\Support\Facades\Blade;

/*
 * La gema de una oportunidad (plan 2.21c, paso 3; D3): la talla sale de la etapa, cada talla se dibuja con su nombre
 * accesible, los ids del SVG no chocan entre gemas y el tamaño crece con el monto entre un mínimo y un máximo.
 */

/** @param  array<string, mixed>  $datos */
function gmDibujar(array $datos): string
{
    return Blade::render('<x-oportunidades::gema :talla="$talla" :monto="$monto" :tamano="$tamano" :semilla="$semilla" :decorativa="$decorativa" />', [
        'talla' => TallaDeGema::Tallada, 'monto' => null, 'tamano' => null, 'semilla' => 0, 'decorativa' => false, ...$datos,
    ]);
}

function gmAncho(string $html): int
{
    preg_match('/<svg[^>]*\swidth="(\d+)"/', $html, $ancho);

    return (int) $ancho[1];
}

test('la talla sale del código de la etapa, con su nombre', function (string $etapa, TallaDeGema $talla, string $nombre) {
    expect(TallaDeGema::desdeEtapa($etapa))->toBe($talla)
        ->and($talla->etiqueta())->toBe($nombre);
})->with([
    'prospecto' => ['prospecto', TallaDeGema::EnBruto, 'En bruto'],
    'calificada' => ['calificada', TallaDeGema::Desbastada, 'Desbastada'],
    'propuesta enviada' => ['propuesta_enviada', TallaDeGema::Tallada, 'Tallada'],
    'negociación' => ['negociacion', TallaDeGema::Pulida, 'Pulida'],
    'ganada' => ['ganada', TallaDeGema::Brillante, 'Brillante'],
    'perdida' => ['perdida', TallaDeGema::Fisurada, 'Fisurada'],
]);

test('cada lugar de la faja tiene su talla y una etapa sin gema se rechaza', function () {
    expect(array_map(fn (LugarFaja $l): TallaDeGema => TallaDeGema::desdeLugar($l), LugarFaja::cases()))->toBe(TallaDeGema::cases())
        ->and(fn () => TallaDeGema::desdeEtapa('anulada'))->toThrow(InvalidArgumentException::class);
});

test('cada talla se dibuja como imagen con su nombre accesible, o escondida si es decorativa', function (TallaDeGema $talla) {
    $html = gmDibujar(['talla' => $talla->value]);

    expect($html)->toContain('role="img"')
        ->and($html)->toContain('aria-label="'.$talla->textoAccesible().'"')
        ->and($html)->toContain('data-talla="'.$talla->value.'"')
        ->and($html)->toContain('tq-gema-'.$talla->value)
        ->and($html)->not->toContain('<img')
        ->and($html)->not->toContain('href=')
        ->and(gmDibujar(['talla' => $talla, 'decorativa' => true]))->toContain('aria-hidden="true"')->not->toContain('role="img"');
})->with(TallaDeGema::cases());

test('los nombres accesibles van en minúscula después de «Gema»', function () {
    expect(TallaDeGema::EnBruto->textoAccesible())->toBe('Gema en bruto')
        ->and(TallaDeGema::Tallada->textoAccesible())->toBe('Gema tallada');
});

test('el reflejo va en la pulida y la brillante; los destellos y el turquesa, en la brillante; la grieta, en la fisurada', function () {
    $brillante = gmDibujar(['talla' => TallaDeGema::Brillante]);

    $reflejo = 'class="tq-gema-reflejo"';
    $destello = 'class="tq-gema-destello"';

    expect(gmDibujar(['talla' => TallaDeGema::Pulida]))->toContain($reflejo)->not->toContain($destello)
        ->and($brillante)->toContain($reflejo)->toContain($destello)->toContain(ColoresMarca::TURQUESA)
        ->and(gmDibujar(['talla' => TallaDeGema::Tallada]))->not->toContain($reflejo)
        ->and(DibujoDeGema::de(TallaDeGema::Fisurada)->grieta)->not->toBe([])
        ->and(DibujoDeGema::de(TallaDeGema::Brillante)->grieta)->toBe([]);
});

test('la animación se detiene con «reducir movimiento» y con «reducir animaciones», y no se repite sin fin', function () {
    $html = gmDibujar(['talla' => TallaDeGema::Brillante]);

    expect($html)->toContain('@media (prefers-reduced-motion: reduce)')
        ->and($html)->toContain('html.tq-sin-animaciones .tq-gema .tq-gema-reflejo { animation: none !important;')
        ->and($html)->toContain('html.tq-sin-animaciones .tq-gema .tq-gema-destello { animation: none !important;')
        ->and($html)->not->toContain('infinite');
});

test('dos gemas en la misma página tienen ids distintos, y cada una usa el suyo', function () {
    $html = Blade::render('<x-oportunidades::gema talla="pulida" /><x-oportunidades::gema talla="pulida" />');

    preg_match_all('/<clipPath id="([^"]+)"/', $html, $ids);
    preg_match_all('/clip-path="url\(#([^)]+)\)"/', $html, $usos);

    expect($ids[1])->toHaveCount(2)
        ->and($ids[1][0])->not->toBe($ids[1][1])
        ->and($usos[1])->toBe($ids[1])
        // Los estilos van una sola vez.
        ->and(substr_count($html, '<style>'))->toBe(1);
});

test('la misma semilla da la misma piedra y otra semilla la varía', function () {
    expect(DibujoDeGema::de(TallaDeGema::EnBruto, 12)->contorno)->toBe(DibujoDeGema::de(TallaDeGema::EnBruto, 12)->contorno)
        ->and(DibujoDeGema::de(TallaDeGema::EnBruto, 12)->contorno)->not->toBe(DibujoDeGema::de(TallaDeGema::EnBruto, 13)->contorno);
});

test('el tamaño crece con el monto entre el mínimo y el máximo', function () {
    $tamanos = array_map(fn (?string $monto): int => EscalaDeGema::tamano($monto), [null, '0.00', '1000.00', '125000.00', '500000.00', '90000000.00']);

    // Raíz del monto sobre 500 000: 1 000 → 44 + 12 × 0,045 = 44,5 → 45; 125 000 → 44 + 12 × 0,5 = 50.
    expect($tamanos)->toBe([44, 44, 45, 50, 56, 56])
        ->and(EscalaDeGema::tamano('125000.00', 20, 40))->toBe(30)
        ->and(fn () => EscalaDeGema::tamano('1.00', 50, 40))->toThrow(InvalidArgumentException::class);
});

test('el componente toma el tamaño del monto, o el fijo si se lo dan', function () {
    $chica = gmAncho(gmDibujar(['monto' => '1000.00']));
    $grande = gmAncho(gmDibujar(['monto' => '400000.00']));

    expect($chica)->toBeLessThan($grande)
        ->and($chica)->toBeGreaterThanOrEqual(EscalaDeGema::MINIMO)
        ->and(gmAncho(gmDibujar(['monto' => '99999999.00'])))->toBe(EscalaDeGema::MAXIMO)
        ->and(gmAncho(gmDibujar(['monto' => '400000.00', 'tamano' => 120])))->toBe(120)
        ->and(gmDibujar(['tamano' => 100]))->toContain('height="88"');
});

test('el monto va como texto decimal, entero o nulo: un float falla (el dinero nunca es float)', function () {
    expect(gmAncho(gmDibujar(['monto' => 1000])))->toBe(gmAncho(gmDibujar(['monto' => '1000.00'])))
        ->and(gmAncho(gmDibujar(['monto' => null])))->toBe(EscalaDeGema::MINIMO)
        ->and(fn () => gmDibujar(['monto' => 1000.5]))->toThrow('El monto de la gema va como texto decimal, entero o nulo; llegó float.');
});
