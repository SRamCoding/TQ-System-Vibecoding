<?php

declare(strict_types=1);

use App\Modules\Nucleo\Models\AuditoriaCambio;
use App\Modules\Nucleo\Models\Moneda;
use App\Modules\Nucleo\Models\User;
use Illuminate\Database\ClassMorphViolationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/*
 * Plan 1.4, paso 1: la base guarda alias, no nombres de clase. Un modelo sin alias falla al guardarse en la
 * bitácora o en spatie; esta prueba lo atrapa antes.
 */

/**
 * @return list<class-string<Model>>
 */
function modelosDeLosModulos(): array
{
    $raiz = dirname(__DIR__, 3).'/app/Modules';
    $modelos = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS)) as $archivo) {
        $ruta = str_replace('\\', '/', $archivo->getPathname());

        if (! preg_match('#/Models/([A-Za-z0-9]+)\.php$#', $ruta)) {
            continue;
        }

        $clase = 'App\\Modules\\'.str_replace('/', '\\', substr($ruta, strlen($raiz) + 1, -4));

        if (class_exists($clase) && is_subclass_of($clase, Model::class) && ! (new ReflectionClass($clase))->isAbstract()) {
            $modelos[] = $clase;
        }
    }

    sort($modelos);

    return $modelos;
}

test('todo modelo de los módulos tiene alias en el morph map', function () {
    $sinAlias = array_values(array_diff(modelosDeLosModulos(), array_values(Relation::morphMap())));

    expect(modelosDeLosModulos())->toContain(User::class)
        ->and($sinAlias)->toBe([]);
});

test('el mapa es obligatorio: un modelo sin alias no se puede guardar como polimórfico', function () {
    $sinAlias = new class extends Model
    {
        protected $table = 'monedas';
    };

    expect(fn () => $sinAlias->getMorphClass())->toThrow(ClassMorphViolationException::class);
});

test('la bitácora y spatie guardan el alias, no el nombre de la clase', function () {
    $moneda = Moneda::factory()->create();
    $usuario = User::factory()->create();
    $usuario->assignRole(Role::findOrCreate('rol-de-prueba'));

    expect(AuditoriaCambio::query()->where('auditable_id', $moneda->id)->value('auditable_type'))->toBe('moneda')
        ->and(DB::table('model_has_roles')->where('model_id', $usuario->id)->value('model_type'))->toBe('user');
});
