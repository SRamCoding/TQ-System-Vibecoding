<?php

declare(strict_types=1);

use App\Modules\Nucleo\Models\PartyCuentaBancaria;
use App\Modules\Nucleo\Seguridad\DeclaraCamposSensibles;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use App\Modules\Nucleo\Seguridad\RevisorCamposSensibles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Contra el olvido (plan 1.4, D6): toda columna que suena sensible está declarada como sensible o, con su motivo, como
 * no sensible. Lee las columnas reales de la base, no las migraciones. Un olvido rompe `composer check`.
 */

/**
 * @return list<class-string<Model>>
 */
function modelosConTabla(): array
{
    $raiz = dirname(__DIR__, 3).'/app/Modules';
    $modelos = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS)) as $archivo) {
        $ruta = str_replace('\\', '/', $archivo->getPathname());

        if (preg_match('#/Models/[A-Za-z0-9]+\.php$#', $ruta) !== 1) {
            continue;
        }

        $clase = 'App\\Modules\\'.str_replace('/', '\\', substr($ruta, strlen($raiz) + 1, -4));

        if (class_exists($clase) && is_subclass_of($clase, Model::class) && ! (new ReflectionClass($clase))->isAbstract()) {
            $modelos[] = $clase;
        }
    }

    return $modelos;
}

/** Un modelo de prueba sobre una tabla con `costo_unitario`, que declara lo que se le pase. */
function modeloConCosto(array $sensibles = [], array $noSensibles = []): Model
{
    Schema::create('pruebas_con_costo', function (Blueprint $table): void {
        $table->id();
        $table->string('nombre', 60);
        $table->decimal('costo_unitario', 14, 2);
    });

    return new class($sensibles, $noSensibles) extends Model implements DeclaraCamposSensibles
    {
        protected $table = 'pruebas_con_costo';

        /**
         * @param  array<string, GrupoSensible>  $sensibles
         * @param  array<string, string>  $noSensibles
         */
        public function __construct(private array $sensibles = [], private array $noSensibles = [])
        {
            parent::__construct();
        }

        public function camposSensibles(): array
        {
            return $this->sensibles;
        }

        public function camposNoSensibles(): array
        {
            return $this->noSensibles;
        }
    };
}

test('toda columna que suena sensible en los modelos de los módulos está declarada', function () {
    $problemas = [];

    foreach (modelosConTabla() as $clase) {
        array_push($problemas, ...RevisorCamposSensibles::problemas(new $clase));
    }

    expect(modelosConTabla())->not->toBeEmpty()
        ->and($problemas)->toBe([]);
});

test('una columna costo_unitario sin declarar hace fallar la revisión', function () {
    expect(RevisorCamposSensibles::problemas(modeloConCosto()))
        ->toBe(['pruebas_con_costo.costo_unitario: suena sensible y no está en camposSensibles() ni en camposNoSensibles()']);
});

test('declarada como sensible, pasa', function () {
    expect(RevisorCamposSensibles::problemas(modeloConCosto(['costo_unitario' => GrupoSensible::Costos])))->toBe([]);
});

test('declarada como no sensible exige el motivo, y lo declarado tiene que existir', function () {
    expect(RevisorCamposSensibles::problemas(modeloConCosto([], ['costo_unitario' => ' '])))
        ->toBe(['pruebas_con_costo.costo_unitario: camposNoSensibles() sin motivo'])
        ->and(RevisorCamposSensibles::problemas(new class extends Model implements DeclaraCamposSensibles
        {
            protected $table = 'monedas';

            public function camposSensibles(): array
            {
                return ['precio' => GrupoSensible::Costos];
            }

            public function camposNoSensibles(): array
            {
                return [];
            }
        }))->toBe(['monedas.precio: está declarada y la tabla no la tiene']);
});

test('la cuenta bancaria declara número y CCI como datos bancarios', function () {
    $cuenta = new PartyCuentaBancaria;

    expect($cuenta->camposSensibles())->toBe(['numero_cuenta' => GrupoSensible::DatosBancarios, 'cci' => GrupoSensible::DatosBancarios])
        ->and(GrupoSensible::DatosBancarios->permiso())->toBe('campos.ver-datos_bancarios');
});
