<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Excepciones\AdjuntoRechazado;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\PartyRedSocial;
use App\Modules\Nucleo\Models\RedSocial;
use App\Modules\Nucleo\Models\Ubigeo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Parties\ResumenRedSocial;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\Services\EmpresaService;
use App\Modules\Nucleo\Services\ParametrosService;
use Illuminate\Support\Facades\Storage;

/*
 * Los datos de Techquk que imprime un documento (plan 1.15, D1) y su logo como adjunto de la ficha (D2). Nombres y
 * archivos inventados, creados aquí.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Storage::fake('adjuntos');
});

afterEach(function () {
    foreach ($GLOBALS['logosTemporales'] ?? [] as $ruta) {
        @unlink($ruta);
    }
});

function fichaDeLaEmpresa(array $atributos = []): Party
{
    $ficha = Party::factory()->juridica()->create($atributos);
    app(ParametrosService::class)->cambiarDesdeTexto(EmpresaService::PARAMETRO, $ficha->numero_documento, 1);

    return $ficha;
}

/** Un PNG de 1×1 (rojo o azul, para que dos logos sean distintos), subido a la ficha con la acción de siempre. */
function subirLogo(Party $ficha, bool $azul = false, string $nombre = 'logo.png', ?string $tipo = EmpresaService::TIPO_LOGO): mixed
{
    $ruta = tempnam(sys_get_temp_dir(), 'logo');
    file_put_contents($ruta, (string) base64_decode($azul ? 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGOQkzsBAAFiAQURG6MhAAAAAElFTkSuQmCC' : 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGM4IScHAAK2AQU0pnWqAAAAAElFTkSuQmCC'));
    $GLOBALS['logosTemporales'][] = $ruta;
    $editor = User::factory()->create();
    $editor->givePermissionTo('parties.editar');

    return app(EjecutorAcciones::class)->ejecutar('nucleo.adjuntos.agregar', [
        'adjuntable_type' => $ficha->getMorphClass(),
        'adjuntable_id' => $ficha->id,
        'archivo' => ['ruta' => $ruta, 'nombre' => $nombre],
        'tipo' => $tipo,
    ], $editor);
}

it('sin ficha elegida no hay datos: el documento sabe que faltan', function () {
    expect(app(EmpresaService::class)->datos())->toBeNull();
});

it('una ficha desactivada por fuera del servicio deja de dar datos, y una natural con ese número no es la ficha', function () {
    $ficha = fichaDeLaEmpresa();
    $natural = Party::factory()->make(['numero_documento' => $ficha->numero_documento]);

    expect(app(EmpresaService::class)->esLaFicha($ficha))->toBeTrue()
        ->and(app(EmpresaService::class)->esLaFicha($natural))->toBeFalse();

    Party::query()->whereKey($ficha->id)->update(['es_activo' => false]);
    expect(app(EmpresaService::class)->datos())->toBeNull();
});

it('entrega los datos de la ficha, con la dirección completa y lo que falta', function () {
    $ubigeo = Ubigeo::factory()->create(['distrito' => 'Distrito Uno', 'provincia' => 'Provincia Dos', 'departamento' => 'Departamento Tres']);
    $ficha = fichaDeLaEmpresa([
        'razon_social' => 'Empresa de Prueba S.A.C.',
        'nombre_comercial' => 'Prueba',
        'direccion_fiscal' => 'Av. Inventada 123',
        'ubigeo_id' => $ubigeo->id,
        'telefono' => '074 000000',
    ]);

    $datos = app(EmpresaService::class)->datos();

    expect($datos?->partyId)->toBe($ficha->id)
        ->and($datos?->razonSocial)->toBe('Empresa de Prueba S.A.C.')
        ->and($datos?->nombreComercial)->toBe('Prueba')
        ->and($datos?->ruc)->toBe($ficha->numero_documento)
        ->and($datos?->direccionFiscal)->toBe('Av. Inventada 123, Distrito Uno, Provincia Dos (Departamento Tres)')
        ->and($datos?->telefono)->toBe('074 000000')
        ->and($datos?->correo)->toBeNull()
        ->and($datos?->faltantes())->toBe(['Sin logo', 'Sin correo', 'Sin sitio web']);
});

it('la dirección sin ubigeo va sola, y sin dirección no se inventa con el ubigeo', function () {
    fichaDeLaEmpresa(['direccion_fiscal' => 'Calle Inventada 45']);
    expect(app(EmpresaService::class)->datos()?->direccionFiscal)->toBe('Calle Inventada 45');

    $sinDireccion = Party::factory()->juridica()->create(['ubigeo_id' => Ubigeo::factory()->create()->id]);
    app(ParametrosService::class)->cambiarDesdeTexto(EmpresaService::PARAMETRO, $sinDireccion->numero_documento, 2);
    expect(app(EmpresaService::class)->datos()?->direccionFiscal)->toBeNull()
        ->and(app(EmpresaService::class)->datos()?->faltantes())->toContain('Sin dirección fiscal');
});

it('el sitio web va aparte; las demás redes públicas de la empresa, en el orden del catálogo, sin las de sus contactos', function () {
    $ficha = fichaDeLaEmpresa();
    $red = fn (string $codigo): RedSocial => RedSocial::query()->where('codigo', $codigo)->firstOrFail();
    PartyRedSocial::factory()->create(['party_id' => $ficha->id, 'red_social_id' => $red('sitio_web')->id, 'enlace' => 'https://empresa-de-prueba.test']);
    PartyRedSocial::factory()->create(['party_id' => $ficha->id, 'red_social_id' => $red('instagram')->id]);
    PartyRedSocial::factory()->create(['party_id' => $ficha->id, 'red_social_id' => $red('linkedin')->id]);
    PartyRedSocial::factory()->create(['party_id' => $ficha->id, 'red_social_id' => $red('facebook')->id, 'es_privada' => true]);

    $datos = app(EmpresaService::class)->datos();

    expect($datos?->sitioWeb)->toBe('https://empresa-de-prueba.test')
        ->and(array_map(fn (ResumenRedSocial $r): string => $r->red, $datos->redes ?? []))->toBe(['LinkedIn', 'Instagram'])
        ->and($datos?->faltantes())->not->toContain('Sin sitio web');
});

it('el logo es el adjunto «Logo» vigente más reciente de la ficha; otros archivos no cuentan', function () {
    $ficha = fichaDeLaEmpresa();
    $sinTipo = subirLogo($ficha, nombre: 'otro.png', tipo: null);
    expect(app(EmpresaService::class)->datos()?->logo)->toBeNull();

    // El mismo archivo no se repite en un registro: el de sin tipo se quita antes de subirlo como logo.
    $quita = User::factory()->create();
    $quita->givePermissionTo('parties.editar');
    app(EjecutorAcciones::class)->ejecutar('nucleo.adjuntos.quitar', ['adjunto_id' => $sinTipo->id], $quita);
    $primero = subirLogo($ficha);
    $segundo = subirLogo($ficha, azul: true);

    expect(app(EmpresaService::class)->datos()?->logo?->id)->toBe($segundo->id)
        ->and(app(EmpresaService::class)->datos()?->faltantes())->not->toContain('Sin logo');

    app(EjecutorAcciones::class)->ejecutar('nucleo.adjuntos.quitar', ['adjunto_id' => $segundo->id], $quita);
    expect(app(EmpresaService::class)->datos()?->logo?->id)->toBe($primero->id);
});

it('el logo es una imagen PNG, JPG o WEBP, nunca otro archivo', function () {
    expect(fn () => subirLogo(fichaDeLaEmpresa(), nombre: 'logo.pdf'))->toThrow(AdjuntoRechazado::class);
});

it('cualquier ficha puede tener su logo (D2), pero los documentos solo toman el de Techquk', function () {
    fichaDeLaEmpresa();
    $cliente = Party::factory()->juridica()->create();

    expect(subirLogo($cliente)->adjuntable_id)->toBe($cliente->id)
        ->and(app(EmpresaService::class)->datos()?->logo)->toBeNull();
});

it('el logo se incrusta como imagen; sin logo, nada', function () {
    $ficha = fichaDeLaEmpresa();
    $servicio = app(EmpresaService::class);
    expect($servicio->imagenDelLogo($servicio->datos() ?? throw new LogicException))->toBeNull();

    subirLogo($ficha);

    expect($servicio->imagenDelLogo($servicio->datos() ?? throw new LogicException))->toStartWith('data:image/png;base64,');
});

it('el encabezado de los documentos dibuja lo que hay, sin esquema en los enlaces y sin dibujar lo que falta', function () {
    $ficha = fichaDeLaEmpresa(['razon_social' => 'Empresa <de> Prueba S.A.C.', 'nombre_comercial' => 'Prueba', 'email' => 'hola@empresa-de-prueba.test']);
    PartyRedSocial::factory()->create(['party_id' => $ficha->id, 'red_social_id' => RedSocial::query()->where('codigo', 'sitio_web')->firstOrFail()->id, 'enlace' => 'https://www.empresa-de-prueba.test/']);
    PartyRedSocial::factory()->create(['party_id' => $ficha->id, 'red_social_id' => RedSocial::query()->where('codigo', 'linkedin')->firstOrFail()->id, 'enlace' => 'https://linkedin.com/company/prueba']);

    $html = view('nucleo::empresa.encabezado', ['empresa' => app(EmpresaService::class)->datos(), 'logo' => null])->render();

    expect($html)->toContain('>Prueba</p>')
        ->toContain('Empresa &lt;de&gt; Prueba S.A.C.')
        ->toContain('RUC '.$ficha->numero_documento)
        ->toContain('hola@empresa-de-prueba.test · empresa-de-prueba.test')
        ->toContain('LinkedIn: linkedin.com/company/prueba')
        ->not->toContain('<img');
});
