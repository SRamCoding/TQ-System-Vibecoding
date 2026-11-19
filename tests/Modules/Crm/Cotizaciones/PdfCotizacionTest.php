<?php

declare(strict_types=1);

use App\Modules\Crm\Cotizaciones\CotizacionesServiceProvider;
use App\Modules\Crm\Cotizaciones\Filament\Resources\Cotizaciones\Pages\VerCotizacion;
use App\Modules\Crm\Cotizaciones\Models\Cotizacion;
use App\Modules\Crm\Cotizaciones\Services\DatosCotizacion;
use App\Modules\Crm\Cotizaciones\Services\PdfCotizacion;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Excepciones\AdjuntoRechazado;
use App\Modules\Nucleo\Models\Adjunto;
use App\Modules\Nucleo\Models\FormaPago;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\ArchivoSubido;
use App\Modules\Nucleo\Services\AdjuntosService;
use App\Modules\Nucleo\Services\EmpresaService;
use App\Modules\Nucleo\Services\ParametrosService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\Modules\Crm\Cotizaciones\Fixtures\CotizacionesDePrueba;

/*
 * D20 (plan 2.22c): el PDF de la cotización. Al emitir se guarda como adjunto de la versión y no cambia después; lleva el
 * encabezado de Techquk, el cliente, las líneas, los totales y las condiciones, nunca costo ni margen (D15); la ficha
 * muestra el guardado o, antes de emitir, una vista previa marcada como borrador. Nombres inventados.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
    test()->cot = CotizacionesDePrueba::nueva();
    test()->cot->ejecutivo->givePermissionTo(Permission::findOrCreate(CotizacionesServiceProvider::PERMISO_VER));
    test()->actingAs(test()->cot->ejecutivo);
});

/** La ficha de Techquk (inventada), para que el PDF lleve su encabezado. */
function pdfEmpresa(string $razonSocial = 'Integradora Andina de Prueba S.A.C.'): Party
{
    $ficha = Party::factory()->juridica()->create(['razon_social' => $razonSocial]);
    app(ParametrosService::class)->cambiarDesdeTexto(EmpresaService::PARAMETRO, $ficha->numero_documento, 1);

    return $ficha;
}

/** v1 con dos líneas y sus condiciones, en borrador. */
function pdfCotizacion(): Cotizacion
{
    $c = test()->cot->crear(new DatosCotizacion(plazoEntregaDias: 45, formaPagoId: (int) FormaPago::query()->value('id'), formaPagoDetalle: '50 % adelanto', garantiaMeses: 24, lugarEntrega: 'Planta de prueba'));
    $c = test()->cot->libre($c, 'Cámara IP bullet 4 MP', '24', '1180.00', '807.12', '5');

    return test()->cot->libre($c, 'Instalación y puesta en marcha', '1', '8000.00', '7752.78');
}

test('el PDF dice el encabezado de Techquk, el cliente, las líneas, los totales y las condiciones; nunca costo ni margen', function () {
    pdfEmpresa();
    $c = pdfCotizacion();

    $html = app(PdfCotizacion::class)->html($c->id);

    expect($html)->toContain('Integradora Andina de Prueba S.A.C.')
        ->toContain("Cotización {$c->numero} v1")
        ->toContain('Borrador: vista previa')
        ->toContain('Cámara IP bullet 4 MP')
        ->toContain('26,904.00')
        ->toContain('45 días calendario')
        ->toContain('50 % adelanto')
        ->toContain('24 meses')
        ->toContain('Planta de prueba')
        ->toContain(number_format((float) $c->total, 2))
        ->not->toContain('807.12')
        ->not->toContain('7752.78')
        ->not->toContain('7,752.78')
        ->not->toContain('Margen')
        ->not->toContain('Costo');
});

test('al emitir, el PDF queda como adjunto de la versión, y después no cambia ni se quita', function () {
    pdfEmpresa();
    $c = test()->cot->libre(test()->cot->crear(), 'Cableado estructurado', '10', '100.00', '60.00');
    $c = test()->cot->servicio()->emitir($c->id, $c->version, null, test()->cot->ejecutivo);

    $adjunto = Adjunto::query()->findOrFail($c->adjunto_pdf_id);
    $contenido = Storage::disk(AdjuntosService::DISCO)->get($adjunto->ruta);

    expect([$adjunto->adjuntable_type, $adjunto->adjuntable_id])->toBe(['cotizacion', $c->id])
        ->and($adjunto->nombre_original)->toBe("{$c->numero} v1.pdf")
        ->and($adjunto->mime)->toBe('application/pdf')
        ->and($contenido)->toStartWith('%PDF-');

    // Cambian los datos de Techquk: lo que se envió sigue igual.
    Party::query()->whereKey(app(EmpresaService::class)->ficha()?->id)->update(['razon_social' => 'Otro Nombre de Prueba S.A.C.']);
    expect(app(PdfCotizacion::class)->paraVer($c->id, test()->cot->ejecutivo)['contenido'])->toBe($contenido)
        ->and(fn () => app(AdjuntosService::class)->quitar($adjunto->id, test()->cot->ejecutivo))->toThrow(AdjuntoRechazado::class)
        ->and($c->refresh()->adjunto_pdf_id)->toBe($adjunto->id);
});

test('con el logo de Techquk, el PDF lo incrusta (dompdf lo dibuja con GD: sin ella, emitir fallaría)', function () {
    $ficha = pdfEmpresa();
    $ruta = sys_get_temp_dir().'/logo-'.Str::random(12).'.png';
    file_put_contents($ruta, (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGOQkzsBAAFiAQURG6MhAAAAAElFTkSuQmCC'));
    $editor = User::factory()->create();
    $editor->givePermissionTo('parties.editar');
    app(AdjuntosService::class)->agregar($ficha->getMorphClass(), $ficha->id, new ArchivoSubido($ruta, 'logo.png'), EmpresaService::TIPO_LOGO, $editor);
    @unlink($ruta);

    $c = test()->cot->emitida();

    expect(app(PdfCotizacion::class)->html($c->id))->toContain('src="data:image/png;base64,')
        ->and(Storage::disk(AdjuntosService::DISCO)->get(Adjunto::query()->findOrFail($c->adjunto_pdf_id)->ruta))->toStartWith('%PDF-');
});

test('sin la ficha de Techquk elegida, se emite igual y el PDF sale sin encabezado', function () {
    $c = test()->cot->emitida();

    expect($c->adjunto_pdf_id)->not->toBeNull()
        ->and(app(PdfCotizacion::class)->html($c->id))->not->toContain('data-tq-encabezado-empresa');
});

test('la ficha: «Vista previa del PDF» en borrador y «PDF» con descarga cuando está emitida', function () {
    $c = pdfCotizacion();

    Livewire::test(VerCotizacion::class, ['record' => $c->id])
        ->assertActionHasLabel('pdf', 'Vista previa del PDF')
        ->mountAction('pdf')
        ->assertMountedActionModalSee('Así saldría el PDF con los datos de ahora')
        ->assertMountedActionModalDontSee('Descargar');

    $c = test()->cot->servicio()->emitir($c->id, $c->refresh()->version, null, test()->cot->ejecutivo);

    Livewire::test(VerCotizacion::class, ['record' => $c->id])
        ->assertActionHasLabel('pdf', 'PDF')
        ->mountAction('pdf')
        ->assertMountedActionModalSee(['lo que recibió el cliente', 'Descargar', route('filament.app.adjuntos.descargar', ['adjunto' => $c->adjunto_pdf_id])]);
});
