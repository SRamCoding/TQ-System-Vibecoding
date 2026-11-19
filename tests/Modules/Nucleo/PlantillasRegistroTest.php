<?php

declare(strict_types=1);

use App\Modules\Nucleo\Excepciones\DefinicionPlantillaInvalida;
use App\Modules\Nucleo\Excepciones\PlantillaRechazada;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Plantillas\DefinicionPlantilla;
use App\Modules\Nucleo\Plantillas\RegistroPlantillas;
use Tests\Modules\Nucleo\Fixtures\TipoPlantillaDePrueba;

/*
 * El contrato entre el núcleo y cada tipo de plantilla (ADR 0022; plan 2.11, paso 3, D4), probado con un tipo
 * inventado: el núcleo no sabe nada del árbol ni del presupuesto. Un tipo que rompe las reglas no se registra.
 */

/** Un tipo que cambia solo lo que la prueba quiere romper. */
function tipoRoto(string $clave = 'pruebas.listas.rotas', string $nombre = 'Rota', string $usar = 'pruebas.listas.editar', string $publicar = 'pruebas.listas.publicar', int $formato = 1): DefinicionPlantilla
{
    return new class($clave, $nombre, $usar, $publicar, $formato) extends DefinicionPlantilla
    {
        public function __construct(private string $c, private string $n, private string $u, private string $p, private int $f) {}

        public function clave(): string
        {
            return $this->c;
        }

        public function nombre(): string
        {
            return $this->n;
        }

        public function permisoUsar(): string
        {
            return $this->u;
        }

        public function permisoPublicar(): string
        {
            return $this->p;
        }

        public function formatoActual(): int
        {
            return $this->f;
        }

        public function validar(array $contenido, int $formato): array
        {
            return [];
        }

        public function capturar(int $documentoId, User $usuario): array
        {
            return [];
        }

        public function proponer(array $contenido, int $formato, array $contexto, User $usuario): object
        {
            return new stdClass;
        }
    };
}

test('un tipo bien hecho se registra y se obtiene por su clave; uno desconocido se rechaza con un mensaje claro', function () {
    $registro = new RegistroPlantillas;
    $tipo = new TipoPlantillaDePrueba;
    $registro->registrar($tipo);

    expect($registro->obtener('pruebas.listas.cosas'))->toBe($tipo)
        ->and($registro->existe('pruebas.listas.cosas'))->toBeTrue()
        ->and($registro->todos())->toBe([$tipo])
        ->and($tipo->documentoOrigen())->toBe('pruebas.listas.cosas')
        ->and($tipo->grupoSensible())->toBeNull()
        ->and(fn () => $registro->obtener('erp.retirado.cosa'))->toThrow(PlantillaRechazada::class, 'ya no conoce');
});

test('no se registran: clave sin área y módulo, en mayúsculas o repetida; sin nombre; permisos sin forma; formato 0', function (DefinicionPlantilla $tipo, string $problema) {
    $registro = new RegistroPlantillas;
    $registro->registrar(new TipoPlantillaDePrueba);

    expect(fn () => $registro->registrar($tipo))->toThrow(DefinicionPlantillaInvalida::class, $problema);
})->with([
    'sin área ni módulo' => fn () => [tipoRoto(clave: 'arbol'), '<area>.<modulo>.<cosa>'],
    'solo módulo' => fn () => [tipoRoto(clave: 'presupuestos.arbol'), '<area>.<modulo>.<cosa>'],
    'mayúsculas' => fn () => [tipoRoto(clave: 'erp.Presupuestos.arbol'), '<area>.<modulo>.<cosa>'],
    'más de 80' => fn () => [tipoRoto(clave: 'erp.presupuestos.'.str_repeat('a', 70)), '<area>.<modulo>.<cosa>'],
    'repetida' => fn () => [tipoRoto(clave: 'pruebas.listas.cosas'), 'ya hay un tipo'],
    'sin nombre' => fn () => [tipoRoto(nombre: '  '), 'falta el nombre'],
    'permiso de usar' => fn () => [tipoRoto(usar: 'Editar listas'), 'permiso para usar'],
    'permiso de publicar' => fn () => [tipoRoto(publicar: ''), 'permiso para publicar'],
    'formato 0' => fn () => [tipoRoto(formato: 0), 'formato empieza en 1'],
]);

test('el tipo de prueba captura, valida y propone sin que el núcleo lea su contenido; lee también su formato viejo', function () {
    $tipo = new TipoPlantillaDePrueba(sensible: true);
    $usuario = new User;
    TipoPlantillaDePrueba::$documentos = [7 => [['nombre' => 'Conector', 'precio' => '1.50']]];

    $contenido = $tipo->capturar(7, $usuario);

    expect($tipo->validar($contenido, $tipo->formatoActual()))->toBe([])
        ->and($tipo->validar(['cosas' => []], 2))->toBe(['la lista está vacía'])
        ->and($tipo->proponer($contenido, 2, [], $usuario)->cosas)->toBe([['nombre' => 'Conector', 'precio' => '1.50']])
        ->and($tipo->proponer(['nombres' => ['Caja']], 1, [], $usuario)->cosas)->toBe([['nombre' => 'Caja', 'precio' => '0.00']])
        ->and($tipo->grupoSensible()?->permiso())->toBe('campos.ver-costos')
        ->and(fn () => $tipo->proponer($contenido, 2, ['ocupado' => true], $usuario))->toThrow(PlantillaRechazada::class, 'vacíala antes')
        ->and(fn () => $tipo->capturar(99, $usuario))->toThrow(PlantillaRechazada::class, 'no existe');
});

test('el núcleo registra un solo RegistroPlantillas para toda la app', function () {
    expect(app(RegistroPlantillas::class))->toBe(app(RegistroPlantillas::class));
});
