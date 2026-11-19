<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Filament\EtiquetasEnEspanol;
use App\Modules\Nucleo\Filament\Resources\Parties\Pages\ListarParties;
use App\Modules\Nucleo\Filament\Resources\Parties\PartyResource;
use App\Modules\Nucleo\Filament\Resources\TiposCambio\TipoCambioResource;
use App\Modules\Nucleo\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Livewire\Livewire;

/*
 * Títulos en español: mayúscula solo al inicio (docs/convenciones/interfaz.md, «Mayúsculas»). Filament pone
 * mayúscula en cada palabra del nombre de un Resource («Personas Y Empresas»): todo Resource usa EtiquetasEnEspanol.
 */

/** @return list<class-string<resource>> */
function resourcesDeLaAplicacion(): array
{
    $clases = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path('Modules'), FilesystemIterator::SKIP_DOTS)) as $archivo) {
        if (! str_ends_with($archivo->getFilename(), 'Resource.php')) {
            continue;
        }

        $clase = 'App\\'.str_replace(['/', '.php'], ['\\', ''], substr($archivo->getPathname(), strlen(app_path()) + 1));

        if (class_exists($clase) && is_subclass_of($clase, Resource::class)) {
            $clases[] = $clase;
        }
    }

    return $clases;
}

test('todo Resource escribe sus títulos con mayúscula solo al inicio', function () {
    $resources = resourcesDeLaAplicacion();

    expect($resources)->not->toBeEmpty();

    foreach ($resources as $resource) {
        expect(class_uses_recursive($resource))->toContain(EtiquetasEnEspanol::class);
    }
});

test('«Personas y empresas» y «Tipos de cambio», no con cada palabra en mayúscula', function () {
    expect(PartyResource::getTitleCasePluralModelLabel())->toBe('Personas y empresas')
        ->and(PartyResource::getTitleCaseModelLabel())->toBe('Persona o empresa')
        ->and(TipoCambioResource::getTitleCasePluralModelLabel())->toBe('Tipos de cambio');
});

test('la lista de parties se titula «Personas y empresas»', function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
    test()->actingAs(User::factory()->create()->givePermissionTo('parties.ver'));

    Livewire::test(ListarParties::class)->assertSee('Personas y empresas')->assertDontSee('Personas Y Empresas');
});
