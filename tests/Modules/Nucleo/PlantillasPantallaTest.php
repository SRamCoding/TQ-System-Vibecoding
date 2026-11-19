<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Filament\Plantillas\PlantillasDeTipo;
use App\Modules\Nucleo\Models\Plantilla;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Plantillas\RegistroPlantillas;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use App\Modules\Nucleo\Services\PlantillasService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\Modules\Nucleo\Fixtures\TipoPlantillaDePrueba;

/*
 * La pieza común de plantillas en pantalla (ADR 0022; plan 2.11, paso 5), con el tipo inventado «lista de cosas»: la
 * lista de las mías y las de la empresa, «Guardar como plantilla» con un documento abierto, «Usar» que avisa a la
 * pantalla, publicar, actualizar y desactivar según quién puede, y nada del contenido sin su permiso (D5).
 */

const TIPO_LISTAS = 'pruebas.listas.cosas';
const TIPO_LISTAS_PRECIOS = 'pruebas.listas.precios';

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');

    $registro = app(RegistroPlantillas::class);
    $registro->registrar(new TipoPlantillaDePrueba);
    $registro->registrar(new TipoPlantillaDePrueba(TIPO_LISTAS_PRECIOS, sensible: true));
    Permission::findOrCreate('pruebas.listas.editar');
    Permission::findOrCreate('pruebas.listas.plantillas.publicar');

    TipoPlantillaDePrueba::$documentos = [
        1 => [['nombre' => 'Cámara domo', 'precio' => '250.00']],
        2 => [['nombre' => 'Poste', 'precio' => '480.00']],
    ];
});

function entrarConPlantillas(string ...$extra): User
{
    $usuario = User::factory()->create()->givePermissionTo(['pruebas.listas.editar', ...$extra]);
    test()->actingAs($usuario);

    return $usuario;
}

test('lista las mías y las de la empresa con su versión, no las personales de otros; el filtro separa', function () {
    $ana = entrarConPlantillas('pruebas.listas.plantillas.publicar');
    $deAna = app(PlantillasService::class)->guardar(TIPO_LISTAS, 1, 'Zonas de Ana', null, $ana);
    app(PlantillasService::class)->publicar($deAna->id, 'Videovigilancia de la empresa', $ana);
    $luis = entrarConPlantillas();
    app(PlantillasService::class)->guardar(TIPO_LISTAS, 2, 'Postes de Luis', 'Para avenidas', $luis);

    Livewire::test(PlantillasDeTipo::class, ['tipo' => TIPO_LISTAS])
        ->assertSee('Lista de cosas')
        ->assertSeeInOrder(['Postes de Luis', 'Para avenidas', 'Mía', 'v1 · ', 'Videovigilancia de la empresa', 'De la empresa'])
        ->assertDontSee('Zonas de Ana')
        ->filterTable('alcance', 'empresa')
        ->assertDontSee('Postes de Luis')
        ->assertSee('Videovigilancia de la empresa');
});

test('con un documento abierto, «Guardar como plantilla» la deja como mía; sin documento no se ofrece', function () {
    entrarConPlantillas();

    Livewire::test(PlantillasDeTipo::class, ['tipo' => TIPO_LISTAS])->assertTableActionHidden('guardarPlantilla');

    Livewire::test(PlantillasDeTipo::class, ['tipo' => TIPO_LISTAS, 'documentoId' => 1])
        ->callTableAction('guardarPlantilla', data: ['nombre' => 'Videovigilancia básica', 'descripcion' => 'Plazas'])
        ->assertHasNoTableActionErrors()
        ->assertNotified('Guardada como plantilla tuya.')
        ->assertSee('Videovigilancia básica');

    expect(Plantilla::query()->where('nombre', 'Videovigilancia básica')->value('alcance'))->toBe(Plantilla::PERSONAL);
});

test('un nombre repetido se rechaza con el mensaje del servicio, no con un error', function () {
    $yo = entrarConPlantillas();
    app(PlantillasService::class)->guardar(TIPO_LISTAS, 1, 'Videovigilancia', null, $yo);

    Livewire::test(PlantillasDeTipo::class, ['tipo' => TIPO_LISTAS, 'documentoId' => 2])
        ->callTableAction('guardarPlantilla', data: ['nombre' => 'videovigilancia'])
        ->assertNotified('Ya tienes una plantilla «videovigilancia» de este tipo: elige otro nombre o actualiza esa.');
});

test('«Usar» avisa a la pantalla con la plantilla elegida; donde no hay a qué aplicar, no se ofrece', function () {
    $yo = entrarConPlantillas();
    $p = app(PlantillasService::class)->guardar(TIPO_LISTAS, 1, 'Videovigilancia', null, $yo);

    Livewire::test(PlantillasDeTipo::class, ['tipo' => TIPO_LISTAS])
        ->callTableAction('usarPlantilla', "p{$p->id}")
        ->assertDispatched('plantilla-elegida', plantillaId: $p->id, tipo: TIPO_LISTAS);

    Livewire::test(PlantillasDeTipo::class, ['tipo' => TIPO_LISTAS, 'ofrecerUsar' => false])
        ->assertTableActionHidden('usarPlantilla', "p{$p->id}");
});

test('publicar solo con su permiso; actualizar desde el documento crea la v2; desactivar la saca de la lista', function () {
    $yo = entrarConPlantillas();
    $p = app(PlantillasService::class)->guardar(TIPO_LISTAS, 1, 'Videovigilancia', null, $yo);

    Livewire::test(PlantillasDeTipo::class, ['tipo' => TIPO_LISTAS, 'documentoId' => 2])
        ->assertTableActionHidden('publicarPlantilla', "p{$p->id}")
        ->callTableAction('actualizarPlantilla', "p{$p->id}")
        ->assertNotified('Plantilla actualizada: desde ahora se usa la versión nueva.')
        ->assertSee('v2 · ')
        ->callTableAction('desactivarPlantilla', "p{$p->id}")
        ->assertNotified('Plantilla desactivada.')
        ->assertDontSee('Videovigilancia');

    $yo->givePermissionTo('pruebas.listas.plantillas.publicar');
    $q = app(PlantillasService::class)->guardar(TIPO_LISTAS, 1, 'Postes', null, $yo);

    Livewire::test(PlantillasDeTipo::class, ['tipo' => TIPO_LISTAS])
        ->callTableAction('publicarPlantilla', "p{$q->id}")
        ->assertNotified('Publicada: ya la puede usar todo el equipo.');

    // Recién dibujada (tras una acción, la respuesta escapa las tildes): la mía primero y la de la empresa después.
    Livewire::test(PlantillasDeTipo::class, ['tipo' => TIPO_LISTAS])->assertSeeInOrder(['Postes', 'Mía', 'Postes', 'De la empresa']);
});

test('una de la empresa, quien solo la usa no la cambia ni la desactiva', function () {
    $editor = entrarConPlantillas('pruebas.listas.plantillas.publicar');
    $mia = app(PlantillasService::class)->guardar(TIPO_LISTAS, 1, 'Videovigilancia', null, $editor);
    $empresa = app(PlantillasService::class)->publicar($mia->id, 'Videovigilancia municipal', $editor);
    entrarConPlantillas();

    Livewire::test(PlantillasDeTipo::class, ['tipo' => TIPO_LISTAS, 'documentoId' => 2])
        ->assertSee('Videovigilancia municipal')
        ->assertTableActionHidden('editarPlantilla', "p{$empresa->id}")
        ->assertTableActionHidden('actualizarPlantilla', "p{$empresa->id}")
        ->assertTableActionHidden('desactivarPlantilla', "p{$empresa->id}")
        ->assertTableActionVisible('usarPlantilla', "p{$empresa->id}");
});

test('D5: un tipo con montos, sin ver costos, no muestra ni la lista; el contenido nunca llega a la pantalla', function () {
    $conCostos = entrarConPlantillas(GrupoSensible::Costos->permiso());
    app(PlantillasService::class)->guardar(TIPO_LISTAS_PRECIOS, 1, 'Con precios', null, $conCostos);

    Livewire::test(PlantillasDeTipo::class, ['tipo' => TIPO_LISTAS_PRECIOS])
        ->assertSee('Con precios')
        ->assertDontSee('250.00')
        ->assertDontSee('Cámara domo');

    entrarConPlantillas();

    Livewire::test(PlantillasDeTipo::class, ['tipo' => TIPO_LISTAS_PRECIOS])
        ->assertDontSee('Con precios')
        ->assertSee('Todavía no hay plantillas de este tipo');
});

test('un tipo que no existe no abre', function () {
    entrarConPlantillas();

    Livewire::test(PlantillasDeTipo::class, ['tipo' => 'erp.retirado.cosa'])->assertForbidden();
});

test('el panel deslizante lleva su título, la X para cerrar con el método de la pantalla y la lista dentro', function () {
    entrarConPlantillas();

    $html = Blade::render("@include('nucleo::filament.plantillas.panel', ['tipo' => '".TIPO_LISTAS."', 'documentoId' => 1, 'alCerrar' => 'cerrarPlantillas'])");

    expect($html)->toContain('data-plantillas-abierto="'.TIPO_LISTAS.'"')
        ->toContain('$wire.cerrarPlantillas()')
        ->toContain('Cerrar las plantillas')
        ->toContain('Lista de cosas');
});
