<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Historial\FilaHistorial;
use App\Modules\Nucleo\Historial\HistorialLegible;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\PartyRol;
use App\Modules\Nucleo\Models\Ubigeo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Parties\DatosParty;
use App\Modules\Nucleo\Seguridad\CamposVisibles;
use App\Modules\Nucleo\Services\ContextoAuditoria;
use App\Modules\Nucleo\Services\HistorialCambiosService;
use App\Modules\Nucleo\Services\PartiesService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/*
 * Plan 1.3, D10: la pestaña de historial muestra los cambios de una party y de sus roles en una sola línea
 * de tiempo, legibles, y solo los campos que la persona puede ver (CamposVisibles, requisito para la 1.4).
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
});

/**
 * Lo hace la persona, como si viniera de la pantalla.
 */
function comoPersona(User $usuario, Closure $hacer): void
{
    app(ContextoAuditoria::class)->comoUsuario($usuario, $hacer);
}

/**
 * Una party con su historia: la crea el sistema, Ana le cambia datos y le asigna y cierra un rol.
 *
 * @return array{0: Party, 1: User}
 */
function partyConHistoria(): array
{
    $ana = User::factory()->create(['name' => 'Ana Ruiz']);
    $chiclayo = Ubigeo::factory()->create(['codigo' => '140101', 'departamento' => 'LAMBAYEQUE', 'provincia' => 'CHICLAYO', 'distrito' => 'CHICLAYO']);
    $parties = app(PartiesService::class);

    test()->travelTo('2026-10-01 09:00');
    $id = $parties->registrar(new DatosParty('6', '20123456786', razonSocial: 'Comercial Ejemplo S.A.C.'))->id;

    test()->travelTo('2026-10-02 10:00');
    comoPersona($ana, fn () => $parties->editar($id, ['telefono' => '074 123456', 'ubigeo_id' => $chiclayo->id], 1));

    test()->travelTo('2026-10-03 11:00');
    comoPersona($ana, fn () => $parties->editarTributarios($id, ['es_agente_retencion' => true], 2));

    test()->travelTo('2026-10-04 12:00');
    comoPersona($ana, fn () => $parties->asignarRol($id, 'cliente', CarbonImmutable::parse('2026-10-04')));

    test()->travelTo('2026-10-05 13:00');
    comoPersona($ana, fn () => $parties->cerrarRol($id, 'cliente', CarbonImmutable::parse('2026-12-31'), 1));

    return [Party::query()->findOrFail($id), $ana];
}

/**
 * @return list<FilaHistorial>
 */
function historialDe(Party $party, User $quien): array
{
    return app(HistorialLegible::class)->filas($quien, [$party, ...$party->roles()->get()->all()]);
}

/**
 * @param  list<FilaHistorial>  $filas
 * @return list<string>
 */
function resumirFilas(array $filas): array
{
    return array_map(fn (FilaHistorial $f): string => $f->resumen !== null
        ? "{$f->autor} | {$f->resumen}"
        : "{$f->autor} | {$f->evento} · {$f->sujeto} | {$f->campo}: {$f->anterior} → {$f->nuevo}", $filas);
}

test('la party y sus roles salen en una sola línea de tiempo, la más reciente primero, legibles', function () {
    [$party, $ana] = partyConHistoria();

    $filas = historialDe($party, $ana);

    expect(resumirFilas(array_slice($filas, 0, 5)))->toBe([
        'Ana Ruiz | Cambió · Rol Cliente | Hasta: sin fin → 31/12/2026',
        'Ana Ruiz | Asignó rol Cliente desde 04/10/2026',
        'Ana Ruiz | Cambió · Datos | Agente de retención: No → Sí',
        'Ana Ruiz | Cambió · Datos | Ubigeo: — → CHICLAYO, CHICLAYO (LAMBAYEQUE)',
        'Ana Ruiz | Cambió · Datos | Teléfono: — → 074 123456',
    ])
        ->and($filas[0]->fecha->format('d/m/Y H:i'))->toBe('05/10/2026 13:00')
        // El alta, en una sola fila (plan 1.3b), como la de un rol.
        ->and(resumirFilas($filas))->toContain('Sistema | Registró persona jurídica · RUC 20123456786 · Comercial Ejemplo S.A.C.')
        ->and(resumirFilas($filas))->not->toContain('Sistema | Creó · Datos | Tipo de persona: — → Persona jurídica');
});

test('la versión y las marcas de auditoría nunca salen en el historial', function () {
    [$party, $ana] = partyConHistoria();

    $campos = array_map(fn (FilaHistorial $f): string => $f->campo, historialDe($party, $ana));

    expect($campos)->not->toContain('version', 'created_by', 'updated_at', 'id');
});

test('los cambios de un campo que la persona no puede ver no salen en el historial', function () {
    [$party, $ana] = partyConHistoria();

    // Implementación de prueba: oculta el teléfono de las parties. La real decide por GrupoSensible.
    app()->instance(CamposVisibles::class, new class implements CamposVisibles
    {
        public function ocultos(User $usuario, Model $modelo): array
        {
            return $modelo instanceof Party ? ['telefono'] : [];
        }
    });

    $campos = array_map(fn (FilaHistorial $f): string => $f->campo, historialDe($party, $ana));

    expect($campos)->not->toContain('Teléfono')
        ->and($campos)->toContain('Ubigeo', 'Agente de retención', 'Hasta');
});

test('si la persona no puede ver un campo del rol, no hay resumen que lo deje ver: filas por campo', function () {
    $periodo = PartyRol::factory()->create(['desde' => '2026-01-01', 'hasta' => '2026-06-30']);

    // Implementación de prueba: oculta «desde» de los roles. El resumen «Asignó rol X desde…» lo mostraría.
    app()->instance(CamposVisibles::class, new class implements CamposVisibles
    {
        public function ocultos(User $usuario, Model $modelo): array
        {
            return $modelo instanceof PartyRol ? ['party_id', 'desde'] : [];
        }
    });

    $filas = app(HistorialLegible::class)->filas(User::factory()->create(), [$periodo]);

    expect(array_map(fn (FilaHistorial $f): ?string => $f->resumen, $filas))->each->toBeNull()
        ->and(array_map(fn (FilaHistorial $f): string => $f->campo, $filas))->toBe(['Rol', 'Hasta'])
        ->and(array_map(fn (FilaHistorial $f): string => $f->nuevo, $filas))->not->toContain('01/01/2026');
});

test('un rol asignado con fecha de cierre ya puesta se resume con su hasta', function () {
    $periodo = PartyRol::factory()->create(['desde' => '2026-01-01', 'hasta' => '2026-06-30']);
    $rol = $periodo->rol->nombre;

    $filas = app(HistorialLegible::class)->filas(User::factory()->create(), [$periodo]);

    expect(array_map(fn (FilaHistorial $f): ?string => $f->resumen, $filas))->toBe(["Asignó rol {$rol} desde 01/01/2026 hasta 30/06/2026"]);
});

test('un campo sin nombre legible se muestra con el nombre de la columna: nada desaparece', function () {
    [$party, $ana] = partyConHistoria();
    $sinEtiquetas = new class extends Party
    {
        public function etiquetasBitacora(): array
        {
            return [];
        }

        public function getMorphClass(): string
        {
            return (new Party)->getMorphClass();
        }
    };
    $sinEtiquetas->setRawAttributes($party->getAttributes(), true);
    $sinEtiquetas->exists = true;

    $campos = array_map(fn (FilaHistorial $f): string => $f->campo, app(HistorialLegible::class)->filas($ana, [$sinEtiquetas]));

    expect($campos)->toContain('telefono', 'es_agente_retencion');
});

test('leer varios registros a la vez trae solo los suyos; ninguno, nada', function () {
    [$party] = partyConHistoria();
    $otra = Party::factory()->juridica()->create();

    $cambios = app(HistorialCambiosService::class)->deVarios([$party, ...$party->roles()->get()->all()]);

    $leidos = $cambios->map(fn ($c): string => "{$c->auditable_type}#{$c->auditable_id}")->unique()->sort()->values()->all();

    expect($leidos)->toBe(collect(['party#'.$party->id, 'party_rol#'.PartyRol::query()->value('id')])->sort()->values()->all())
        ->and($leidos)->not->toContain('party#'.$otra->id)
        ->and(app(HistorialCambiosService::class)->deVarios([]))->toHaveCount(0);
});
