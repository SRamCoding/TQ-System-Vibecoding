<?php

declare(strict_types=1);

use App\Modules\Crm\Cuentas\Actividad\ActividadDeCuentas;
use App\Modules\Crm\Cuentas\Actividad\AporteActividad;
use App\Modules\Crm\Cuentas\Actividad\AporteOportunidadesAbiertas;
use App\Modules\Crm\Cuentas\Actividad\AporteUltimoContacto;
use App\Modules\Crm\Cuentas\Actividad\DatoActividad;
use App\Modules\Crm\Cuentas\Actividad\DatosActividadCuenta;
use App\Modules\Crm\Cuentas\Models\Cuenta;
use App\Modules\Crm\Cuentas\Pestanas\BotonCuenta;
use App\Modules\Crm\Cuentas\Pestanas\PestanaCuenta;
use App\Modules\Crm\Cuentas\Pestanas\RegistroPestanasCuenta;
use App\Modules\Nucleo\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\Modules\Crm\Cuentas\Fixtures\PestanaCuentaPrueba;

/*
 * Los dos registros donde los demás módulos se enchufan a Cuentas (plan 2.20, D11 y D12).
 */

function usuarioDeRegistrosCon(string ...$permisos): User
{
    $usuario = User::factory()->create();

    foreach ($permisos as $permiso) {
        Permission::findOrCreate($permiso, 'web');
        $usuario->givePermissionTo($permiso);
    }

    return $usuario;
}

/** Un aporte de prueba que da el último contacto, como el que sumará Actividades (CRM-05). */
function aporteDeContactos(array $contactos, array $impedimentos = []): AporteUltimoContacto
{
    return new class($contactos, $impedimentos) implements AporteUltimoContacto
    {
        /** @param  array<int, string>  $contactos  cuenta => día */
        public function __construct(private array $contactos, private array $impedimentos) {}

        public function datos(): array
        {
            return [DatoActividad::UltimoContacto];
        }

        public function leer(array $cuentaIds): array
        {
            return array_map(fn (string $dia): DatosActividadCuenta => new DatosActividadCuenta(ultimoContacto: CarbonImmutable::parse($dia)), array_intersect_key($this->contactos, array_flip($cuentaIds)));
        }

        public function impedimentosParaDesactivar(int $cuentaId): array
        {
            return $this->impedimentos;
        }

        public function ultimosContactos(): QueryBuilder
        {
            $filas = array_map(
                fn (int $cuenta, string $dia): QueryBuilder => DB::query()->selectRaw('?::bigint AS cuenta_id, ?::date AS ultimo_contacto', [$cuenta, $dia]),
                array_keys($this->contactos),
                $this->contactos,
            );
            $vacia = DB::query()->selectRaw('NULL::bigint AS cuenta_id, NULL::date AS ultimo_contacto')->whereRaw('false');

            return array_reduce($filas, fn (QueryBuilder $union, QueryBuilder $fila): QueryBuilder => $union->unionAll($fila), $vacia);
        }
    };
}

test('las pestañas salen en su orden y solo con su permiso; la clave no se repite', function () {
    $registro = new RegistroPestanasCuenta;
    $registro->registrar(new PestanaCuenta('prueba-cotizaciones', 'Cotizaciones', 'crm.cotizaciones.ver', PestanaCuentaPrueba::class, 'heroicon-o-document-text', orden: 20));
    $registro->registrar(new PestanaCuenta('prueba-oportunidades', 'Oportunidades', null, PestanaCuentaPrueba::class, 'heroicon-o-light-bulb', orden: 10));
    $titulos = fn (User $u): array => array_map(fn (PestanaCuenta $p): string => $p->titulo, $registro->pestanasPara($u));

    expect($titulos(usuarioDeRegistrosCon('crm.cotizaciones.ver')))->toBe(['Oportunidades', 'Cotizaciones'])
        ->and($titulos(usuarioDeRegistrosCon()))->toBe(['Oportunidades'])
        ->and($registro->pestanasPara(null))->toBe([])
        ->and(fn () => $registro->registrar(new PestanaCuenta('prueba-oportunidades', 'Otra', null, PestanaCuentaPrueba::class, 'heroicon-o-star')))->toThrow(InvalidArgumentException::class, 'ya está registrada')
        ->and(fn () => new PestanaCuenta('Mal clave', 'X', null, PestanaCuentaPrueba::class, 'heroicon-o-star'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new PestanaCuenta('sin-componente', 'X', null, User::class, 'heroicon-o-star'))->toThrow(InvalidArgumentException::class, 'debe extender ComponentePestanaCuenta');
});

test('los botones de cabecera salen con su permiso y arman la acción para la cuenta', function () {
    $registro = new RegistroPestanasCuenta;
    $registro->registrarBoton(new BotonCuenta('nueva-oportunidad', 'crm.oportunidades.crear', fn (int $cuentaId): Action => Action::make('x')->label("Nueva oportunidad para {$cuentaId}")));

    $botones = $registro->botonesPara(usuarioDeRegistrosCon('crm.oportunidades.crear'));

    expect($botones)->toHaveCount(1)
        ->and($botones[0]->accion(7)->getName())->toBe('nueva-oportunidad')
        ->and($botones[0]->accion(7)->getLabel())->toBe('Nueva oportunidad para 7')
        ->and($registro->botonesPara(usuarioDeRegistrosCon()))->toBe([])
        ->and(fn () => $registro->registrarBoton(new BotonCuenta('nueva-oportunidad', null, fn (int $c): Action => Action::make('y'))))->toThrow(InvalidArgumentException::class, 'ya está registrado');
});

test('una pestaña de cuenta exige ver cuentas y su propio permiso en cada petición', function () {
    app(RegistroPestanasCuenta::class)->registrar(new PestanaCuenta('prueba', 'Prueba', 'crm.prueba.ver', PestanaCuentaPrueba::class, 'heroicon-o-beaker'));

    Livewire::actingAs(usuarioDeRegistrosCon('crm.cuentas.ver', 'crm.prueba.ver'))->test(PestanaCuentaPrueba::class, ['cuentaId' => 7])->assertSee('Oportunidades de la cuenta 7');
    Livewire::actingAs(usuarioDeRegistrosCon('crm.prueba.ver'))->test(PestanaCuentaPrueba::class, ['cuentaId' => 7])->assertForbidden();
    Livewire::actingAs(usuarioDeRegistrosCon('crm.cuentas.ver'))->test(PestanaCuentaPrueba::class, ['cuentaId' => 7])->assertForbidden();
});

test('sin aportes, la actividad es la versión vacía: no tiene nada, no impide nada y no filtra', function () {
    $actividad = new ActividadDeCuentas;

    expect($actividad->tiene(DatoActividad::UltimoContacto))->toBeFalse()
        ->and($actividad->datosDe([3, 5]))->toEqual([3 => new DatosActividadCuenta, 5 => new DatosActividadCuenta])
        ->and($actividad->impedimentosParaDesactivar(3))->toBe([])
        ->and(fn () => $actividad->sinActividadDesde(Cuenta::query(), '2026-08-01'))->toThrow(LogicException::class, 'revisa tiene()');
});

test('con un aporte, la actividad junta sus datos en lote, suma sus impedimentos y filtra «sin actividad» en la base', function () {
    $this->travelTo('2026-11-05 10:00');
    $vieja = Cuenta::factory()->create(['created_at' => '2026-01-10 09:00']);
    $reciente = Cuenta::factory()->create(['created_at' => '2026-01-10 09:00']);
    $sinContacto = Cuenta::factory()->create(['created_at' => '2026-01-10 09:00']);
    $nueva = Cuenta::factory()->create(['created_at' => '2026-10-20 09:00']);
    $actividad = new ActividadDeCuentas;
    $actividad->registrar(aporteDeContactos([$vieja->id => '2026-05-02', $reciente->id => '2026-10-30'], ['2 oportunidades abiertas']));

    $sinActividad = Cuenta::query();
    $actividad->sinActividadDesde($sinActividad, '2026-08-07');

    expect($actividad->tiene(DatoActividad::UltimoContacto))->toBeTrue()
        ->and($actividad->tiene(DatoActividad::Facturado))->toBeFalse()
        ->and($actividad->datosDe([$vieja->id, $nueva->id])[$vieja->id]->ultimoContacto?->toDateString())->toBe('2026-05-02')
        ->and($actividad->datosDe([$vieja->id, $nueva->id])[$nueva->id]->ultimoContacto)->toBeNull()
        ->and($actividad->impedimentosParaDesactivar($vieja->id))->toBe(['2 oportunidades abiertas'])
        ->and($sinActividad->orderBy('id')->pluck('id')->all())->toBe([$vieja->id, $sinContacto->id]);
});

test('un dato lo aporta un solo módulo, y quien da el último contacto debe poder filtrar por él', function () {
    $actividad = new ActividadDeCuentas;
    $actividad->registrar(aporteDeContactos([]));
    $sinFiltro = new class implements AporteActividad
    {
        public function datos(): array
        {
            return [DatoActividad::OportunidadesAbiertas];
        }

        public function leer(array $cuentaIds): array
        {
            return [];
        }

        public function impedimentosParaDesactivar(int $cuentaId): array
        {
            return [];
        }
    };

    expect(fn () => $actividad->registrar(aporteDeContactos([])))->toThrow(LogicException::class, 'ya lo aporta otro módulo')
        ->and(fn () => $actividad->registrar($sinFiltro))->toThrow(LogicException::class, 'AporteOportunidadesAbiertas');
});

test('ordenar por último contacto cruza la subconsulta del aporte y deja al final las que no tienen contacto', function () {
    $mayo = Cuenta::factory()->create();
    $octubre = Cuenta::factory()->create();
    $sinContacto = Cuenta::factory()->create();
    $actividad = new ActividadDeCuentas;
    $actividad->registrar(aporteDeContactos([$mayo->id => '2026-05-02', $octubre->id => '2026-10-30']));

    $ascendente = Cuenta::query();
    $actividad->ordenarPorUltimoContacto($ascendente, 'asc');
    $descendente = Cuenta::query();
    $actividad->ordenarPorUltimoContacto($descendente, 'desc');

    expect($ascendente->pluck('cuentas.id')->all())->toBe([$mayo->id, $octubre->id, $sinContacto->id])
        ->and($descendente->pluck('cuentas.id')->all())->toBe([$octubre->id, $mayo->id, $sinContacto->id])
        ->and($ascendente->first())->toBeInstanceOf(Cuenta::class);
});

test('«con oportunidades abiertas» compone la subconsulta del aporte, que nombra solo su tabla', function () {
    $conAbiertas = Cuenta::factory()->create();
    Cuenta::factory()->create();
    $actividad = new ActividadDeCuentas;
    $actividad->registrar(new class($conAbiertas->id) implements AporteOportunidadesAbiertas
    {
        public function __construct(private int $cuentaId) {}

        public function datos(): array
        {
            return [DatoActividad::OportunidadesAbiertas];
        }

        public function leer(array $cuentaIds): array
        {
            return [];
        }

        public function impedimentosParaDesactivar(int $cuentaId): array
        {
            return [];
        }

        public function cuentasConAbiertas(): QueryBuilder
        {
            return DB::query()->selectRaw('?::bigint AS cuenta_id', [$this->cuentaId]);
        }
    });

    $consulta = Cuenta::query();
    $actividad->conOportunidadesAbiertas($consulta);

    expect($consulta->pluck('id')->all())->toBe([$conAbiertas->id]);
});
