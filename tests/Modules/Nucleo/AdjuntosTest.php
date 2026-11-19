<?php

declare(strict_types=1);

use App\Modules\Nucleo\Adjuntos\TieneAdjuntosObligatorios;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Estados\RegistroDocumentos;
use App\Modules\Nucleo\Estados\TransicionDefinida;
use App\Modules\Nucleo\Excepciones\AccionRechazada;
use App\Modules\Nucleo\Excepciones\AdjuntoRechazado;
use App\Modules\Nucleo\Excepciones\ParametroRechazado;
use App\Modules\Nucleo\Excepciones\TransicionRechazada;
use App\Modules\Nucleo\Filament\Adjuntos\ArchivosDeRegistro;
use App\Modules\Nucleo\Models\Adjunto;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\TipoAdjunto;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\ArchivoSubido;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use App\Modules\Nucleo\Services\AdjuntosService;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Services\SincronizadorEstados;
use App\Modules\Nucleo\Services\TransicionesService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\Modules\Nucleo\Fixtures\DefinicionPrueba;
use Tests\Modules\Nucleo\Fixtures\DocumentoPrueba;

/*
 * M7 · Adjuntos (plan 1.8), sin pantalla salvo la última prueba. El disco es falso (Storage::fake); los archivos se
 * escriben en un temporal propio de cada prueba.
 */

beforeEach(function () {
    $GLOBALS['adTemporales'] = [];
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Storage::fake(AdjuntosService::DISCO);
    Filament::setCurrentPanel('app');
});

afterEach(function () {
    foreach ($GLOBALS['adTemporales'] ?? [] as $ruta) {
        @unlink($ruta);
    }
});

/** Un archivo temporal con ese nombre y ese contenido; se borra al terminar la prueba. */
function adArchivo(string $nombre, string $contenido): ArchivoSubido
{
    $ruta = tempnam(sys_get_temp_dir(), 'adj');
    file_put_contents($ruta, $contenido);
    $GLOBALS['adTemporales'][] = $ruta;

    return new ArchivoSubido($ruta, $nombre);
}

/** Un PDF mínimo de verdad (su contenido lo reconoce `finfo`); `$texto` lo hace distinto de otro. */
function adPdf(string $nombre = 'ficha-ruc.pdf', string $texto = 'uno'): ArchivoSubido
{
    return adArchivo($nombre, "%PDF-1.4\n1 0 obj<<>>endobj\n% {$texto}\ntrailer<<>>\n%%EOF\n");
}

function adParty(): Party
{
    return Party::factory()->create();
}

function adEditor(string ...$mas): User
{
    return User::factory()->create()->givePermissionTo(['parties.ver', 'parties.editar', ...$mas]);
}

function adAgregar(Party $party, ArchivoSubido $archivo, ?string $tipo = null, ?User $quien = null): Adjunto
{
    return app(AdjuntosService::class)->agregar('party', $party->id, $archivo, $tipo, $quien ?? adEditor());
}

test('adjunta un PDF: el archivo va al disco privado con un nombre interno y en la base solo su ruta', function () {
    $party = adParty();
    $adjunto = adAgregar($party, adPdf());

    expect($adjunto->nombre_original)->toBe('ficha-ruc.pdf')
        ->and($adjunto->mime)->toBe('application/pdf')
        ->and($adjunto->ruta)->toStartWith('party/')->not->toContain('ficha-ruc')
        ->and($adjunto->hash)->toHaveLength(64);
    Storage::disk(AdjuntosService::DISCO)->assertExists($adjunto->ruta);
});

test('rechaza lo que no vale: otra extensión, un falso PDF, uno vacío, uno muy grande y lo prohibido aunque el parámetro lo permita', function () {
    $party = adParty();
    $parametros = app(ParametrosService::class);

    expect(fn () => adAgregar($party, adArchivo('programa.exe', 'MZ')))->toThrow(AdjuntoRechazado::class, 'no se acepta')
        ->and(fn () => adAgregar($party, adArchivo('factura.pdf', 'esto es texto, no un PDF')))->toThrow(AdjuntoRechazado::class, 'su contenido no lo es')
        ->and(fn () => adAgregar($party, adArchivo('vacio.pdf', '')))->toThrow(AdjuntoRechazado::class, 'vacío');

    $parametros->cambiarDesdeTexto('adjuntos.tamano_maximo_mb', '1', 1);
    expect(fn () => adAgregar($party, adArchivo('grande.pdf', "%PDF-1.4\n".str_repeat('x', 1024 * 1024 + 10))))->toThrow(AdjuntoRechazado::class, 'más de 1 MB');

    $parametros->cambiarDesdeTexto('adjuntos.extensiones', 'pdf,svg', 1);
    expect(fn () => adAgregar($party, adArchivo('dibujo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>')))->toThrow(AdjuntoRechazado::class, 'no se acepta');

    expect(Adjunto::query()->count())->toBe(0)
        ->and(Storage::disk(AdjuntosService::DISCO)->allFiles())->toBe([]);
});

test('el mismo archivo dos veces en un registro se rechaza, y el que no entró no queda en el disco', function () {
    $party = adParty();
    adAgregar($party, adPdf());

    expect(fn () => adAgregar($party, adPdf('otro-nombre.pdf')))->toThrow(AdjuntoRechazado::class, 'ya está adjunto')
        ->and(Storage::disk(AdjuntosService::DISCO)->allFiles())->toHaveCount(1);

    // En otro registro, sí.
    adAgregar(adParty(), adPdf());
    expect(Adjunto::query()->count())->toBe(2);
});

test('quitar deja rastro y conserva el archivo; reemplazar quita el anterior y apunta a él', function () {
    $party = adParty();
    $editor = adEditor();
    $primero = adAgregar($party, adPdf(), quien: $editor);
    $servicio = app(AdjuntosService::class);

    $nuevo = $servicio->reemplazar($primero->id, adPdf('ficha-2026.pdf', 'dos'), $editor);
    expect($primero->refresh()->quitado_por_user_id)->toBe($editor->id)
        ->and($nuevo->reemplaza_a_adjunto_id)->toBe($primero->id);

    $servicio->quitar($nuevo->id, $editor);
    expect($nuevo->refresh()->quitado_at)->not->toBeNull()
        ->and($servicio->vigentes($party, $editor))->toHaveCount(0)
        ->and(fn () => $servicio->quitar($nuevo->id, $editor))->toThrow(AdjuntoRechazado::class, 'ya se quitó');
    Storage::disk(AdjuntosService::DISCO)->assertExists([$primero->ruta, $nuevo->ruta]);
});

test('sin permiso para editar la party no se adjunta ni se quita', function () {
    $party = adParty();
    $adjunto = adAgregar($party, adPdf());
    $solo = User::factory()->create()->givePermissionTo('parties.ver');

    expect(fn () => adAgregar($party, adPdf('x.pdf', 'tres'), quien: $solo))->toThrow(AdjuntoRechazado::class, 'No puedes cambiar')
        ->and(fn () => app(AdjuntosService::class)->quitar($adjunto->id, $solo))->toThrow(AdjuntoRechazado::class, 'No puedes cambiar');
});

test('en un documento no editable se agrega, pero no se quita ni se reemplaza (plan 1.5, D8)', function () {
    DocumentoPrueba::crearTabla();
    app(RegistroDocumentos::class)->registrar(new DefinicionPrueba(transiciones: [new TransicionDefinida('enviar', 'Enviar', 'borrador', 'enviado')]));
    app(SincronizadorEstados::class)->sincronizar();
    $persona = User::factory()->create();
    Permission::findOrCreate('nucleo.prueba.enviar');
    $persona->givePermissionTo('nucleo.prueba.enviar');
    $documento = app(TransicionesService::class)->crear(new DocumentoPrueba(['nombre' => 'Pedido']), $persona)->refresh();
    $servicio = app(AdjuntosService::class);
    $antes = $servicio->agregar('documento_prueba', $documento->id, adPdf(), null, $persona);

    app(TransicionesService::class)->transicionar($documento, 'enviar', $documento->version, $persona);

    $despues = $servicio->agregar('documento_prueba', $documento->id, adPdf('acta.pdf', 'cuatro'), null, $persona);
    expect($despues->exists)->toBeTrue()
        ->and(fn () => $servicio->quitar($antes->id, $persona))->toThrow(AdjuntoRechazado::class, 'solo agregar otro')
        ->and(fn () => $servicio->reemplazar($antes->id, adPdf('nuevo.pdf', 'cinco'), $persona))->toThrow(AdjuntoRechazado::class, 'solo agregar otro');
});

test('tipos: uno de otro registro se rechaza; uno con menos extensiones manda; el obligatorio lo exige su condición', function () {
    DocumentoPrueba::crearTabla();
    app(RegistroDocumentos::class)->registrar(new DefinicionPrueba(transiciones: [new TransicionDefinida('enviar', 'Enviar', 'borrador', 'enviado', condiciones: [TieneAdjuntosObligatorios::class])]));
    app(SincronizadorEstados::class)->sincronizar();
    TipoAdjunto::factory()->create(['codigo' => 'cotizacion', 'nombre' => 'Cotización', 'documento_type' => 'documento_prueba', 'es_obligatorio' => true]);
    TipoAdjunto::factory()->create(['codigo' => 'foto', 'nombre' => 'Foto', 'extensiones' => 'jpg,png']);
    $persona = User::factory()->create();
    Permission::findOrCreate('nucleo.prueba.enviar');
    $persona->givePermissionTo('nucleo.prueba.enviar');
    $documento = app(TransicionesService::class)->crear(new DocumentoPrueba(['nombre' => 'Pedido']), $persona)->refresh();
    $servicio = app(AdjuntosService::class);

    expect(fn () => adAgregar(adParty(), adPdf(), 'cotizacion'))->toThrow(AdjuntoRechazado::class, 'no es para este tipo de registro')
        ->and(fn () => $servicio->agregar('documento_prueba', $documento->id, adPdf(), 'foto', $persona))->toThrow(AdjuntoRechazado::class, 'Se aceptan: jpg, png')
        ->and(fn () => app(TransicionesService::class)->transicionar($documento, 'enviar', $documento->version, $persona))->toThrow(TransicionRechazada::class, 'Falta adjuntar: Cotización.');

    $servicio->agregar('documento_prueba', $documento->id, adPdf(), 'cotizacion', $persona);
    app(TransicionesService::class)->transicionar($documento, 'enviar', $documento->version, $persona);
    expect($documento->refresh()->version)->toBe(2);
});

test('la descarga revisa permisos: quien ve la party la baja; quien no, no; un sensible solo con su permiso', function () {
    $party = adParty();
    TipoAdjunto::factory()->create(['codigo' => 'examen', 'nombre' => 'Examen médico', 'grupo_sensible' => GrupoSensible::DetalleMedico]);
    $comun = adAgregar($party, adPdf());
    $sensible = adAgregar($party, adPdf('examen.pdf', 'seis'), 'examen');
    $ruta = fn (Adjunto $a): string => route('filament.app.adjuntos.descargar', ['adjunto' => $a->id]);

    test()->actingAs(User::factory()->create())->get($ruta($comun))->assertForbidden();

    $lector = User::factory()->create()->givePermissionTo('parties.ver');
    test()->actingAs($lector)->get($ruta($comun))->assertOk()->assertDownload('ficha-ruc.pdf');
    test()->actingAs($lector)->get($ruta($sensible))->assertForbidden();
    expect(app(AdjuntosService::class)->vigentes($party, $lector)->pluck('id')->all())->toBe([$comun->id]);

    $medico = User::factory()->create()->givePermissionTo(['parties.ver', GrupoSensible::DetalleMedico->permiso()]);
    test()->actingAs($medico)->get($ruta($sensible))->assertOk();
});

test('sin pantalla: agregar, reemplazar y quitar son acciones registradas, con el archivo como ruta y nombre', function () {
    $party = adParty();
    $editor = adEditor();
    $ejecutor = app(EjecutorAcciones::class);
    $archivo = adPdf();

    $adjunto = $ejecutor->ejecutar('nucleo.adjuntos.agregar', ['adjuntable_type' => 'party', 'adjuntable_id' => $party->id, 'archivo' => ['ruta' => $archivo->ruta, 'nombre' => 'ficha-ruc.pdf']], $editor);
    $otro = adPdf('nueva.pdf', 'siete');
    $nuevo = $ejecutor->ejecutar('nucleo.adjuntos.reemplazar', ['adjunto_id' => $adjunto->id, 'archivo' => ['ruta' => $otro->ruta, 'nombre' => 'nueva.pdf']], $editor);
    $ejecutor->ejecutar('nucleo.adjuntos.quitar', ['adjunto_id' => $nuevo->id], $editor);

    expect(Adjunto::query()->whereNull('quitado_at')->count())->toBe(0)
        ->and(fn () => $ejecutor->ejecutar('nucleo.adjuntos.agregar', ['adjuntable_type' => 'party', 'adjuntable_id' => $party->id], $editor))
        ->toThrow(ValidationException::class);

    // El sistema no actúa como persona.
    expect(fn () => $ejecutor->ejecutar('nucleo.adjuntos.quitar', ['adjunto_id' => $adjunto->id], User::query()->where('es_sistema', true)->sole()))
        ->toThrow(AccionRechazada::class);
});

test('la pestaña «Archivos» muestra los vigentes con su descarga, y a quien solo ve no le ofrece cambiarlos', function () {
    $party = adParty();
    adAgregar($party, adPdf());
    $lector = User::factory()->create()->givePermissionTo('parties.ver');
    test()->actingAs($lector);

    Livewire::test(ArchivosDeRegistro::class, ['adjuntableType' => 'party', 'adjuntableId' => $party->id])
        ->assertSee('ficha-ruc.pdf')
        ->assertSee(route('filament.app.adjuntos.descargar', ['adjunto' => Adjunto::query()->sole()->id]))
        ->assertActionHidden(TestAction::make('nucleoAdjuntosAgregar')->table());

    test()->actingAs(User::factory()->create());
    Livewire::test(ArchivosDeRegistro::class, ['adjuntableType' => 'party', 'adjuntableId' => $party->id])->assertForbidden();
});

test('solo toma archivos de las carpetas de subida: nunca otro archivo del servidor, y menos otro adjunto', function () {
    $party = adParty();
    $sensible = adAgregar($party, adPdf());
    $enElDisco = Storage::disk(AdjuntosService::DISCO)->path($sensible->ruta);

    expect(fn () => adAgregar(adParty(), new ArchivoSubido($enElDisco, 'copia.pdf')))->toThrow(AdjuntoRechazado::class, 'no llegó')
        ->and(fn () => adAgregar(adParty(), new ArchivoSubido(base_path('composer.json'), 'composer.xml')))->toThrow(AdjuntoRechazado::class, 'no llegó');
});

test('desde la pantalla, si el navegador cambia el archivo por una ruta del servidor, se rechaza', function () {
    $party = adParty();
    test()->actingAs(adEditor());
    $falso = adPdf();

    Livewire::test(ArchivosDeRegistro::class, ['adjuntableType' => 'party', 'adjuntableId' => $party->id])
        ->mountAction(TestAction::make('nucleoAdjuntosAgregar')->table())
        ->set('mountedActions.0.data.archivo', ['falso' => $falso->ruta])
        ->callMountedAction()
        ->assertNotified('Vuelve a elegir el archivo.');

    expect(Adjunto::query()->count())->toBe(0);
});

test('al cambiar los archivos, la pestaña avisa a la pantalla que la contiene (plan 1.15: la vista previa del asistente)', function () {
    $party = adParty();
    $adjunto = adAgregar($party, adPdf());
    test()->actingAs(adEditor());

    Livewire::test(ArchivosDeRegistro::class, ['adjuntableType' => 'party', 'adjuntableId' => $party->id])
        ->callAction(TestAction::make('nucleoAdjuntosQuitar')->table($adjunto))
        ->assertNotified('Listo.')
        ->assertDispatched('archivos-cambiados');
});

test('un sensible no lo quita ni lo reemplaza quien no puede verlo', function () {
    $party = adParty();
    TipoAdjunto::factory()->create(['codigo' => 'examen', 'nombre' => 'Examen médico', 'grupo_sensible' => GrupoSensible::DetalleMedico]);
    $sensible = adAgregar($party, adPdf(), 'examen', adEditor(GrupoSensible::DetalleMedico->permiso()));
    $editor = adEditor();

    expect(fn () => app(AdjuntosService::class)->quitar($sensible->id, $editor))->toThrow(AdjuntoRechazado::class, 'No puedes ver')
        ->and(fn () => app(AdjuntosService::class)->reemplazar($sensible->id, adPdf('otro.pdf', 'ocho'), $editor))->toThrow(AdjuntoRechazado::class, 'No puedes ver');
});

test('uno quitado se sigue descargando: se conserva para revisar qué había (D2)', function () {
    $party = adParty();
    $editor = adEditor();
    $adjunto = adAgregar($party, adPdf(), quien: $editor);
    app(AdjuntosService::class)->quitar($adjunto->id, $editor);

    test()->actingAs(User::factory()->create()->givePermissionTo('parties.ver'))
        ->get(route('filament.app.adjuntos.descargar', ['adjunto' => $adjunto->id]))->assertOk();
});

test('el nombre del archivo se guarda sin carpetas, barras ni caracteres de control, y se puede descargar', function () {
    $adjunto = adAgregar(adParty(), adPdf("../../etc/pas\\swd\x01.pdf"));

    expect($adjunto->nombre_original)->not->toContain('/')->not->toContain('\\')->toEndWith('.pdf');
    test()->actingAs(User::factory()->create()->givePermissionTo('parties.ver'))
        ->get(route('filament.app.adjuntos.descargar', ['adjunto' => $adjunto->id]))->assertOk();
});

test('el tamaño máximo va de 1 al techo del servidor, el mismo que Livewire acepta al subir (plan 1.12b)', function () {
    $parametros = app(ParametrosService::class);

    expect(fn () => $parametros->cambiarDesdeTexto('adjuntos.tamano_maximo_mb', '51', 1))->toThrow(ParametroRechazado::class, 'entre 1 y 50')
        ->and(fn () => $parametros->cambiarDesdeTexto('adjuntos.tamano_maximo_mb', '0', 1))->toThrow(ParametroRechazado::class)
        ->and(config('livewire.temporary_file_upload.rules'))->toContain('max:51200')
        ->and(config('livewire.temporary_file_upload.preview_mimes'))->not->toContain('svg');
});

test('un adjunto de 15 MB entra con el tope de fábrica del parámetro (20 MB); el de Livewire lo vigila la prueba de arriba', function () {
    $party = adParty();

    adAgregar($party, adArchivo('plano.pdf', "%PDF-1.4\n".str_repeat('x', 15 * 1024 * 1024)));

    expect(Adjunto::query()->count())->toBe(1);
});
