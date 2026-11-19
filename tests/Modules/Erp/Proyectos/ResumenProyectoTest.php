<?php

declare(strict_types=1);

use App\Modules\Erp\Proyectos\Models\Proyecto;
use App\Modules\Erp\Proyectos\Resumen\ContenidoTarjeta;
use App\Modules\Erp\Proyectos\Resumen\RegistroTarjetasResumen;
use App\Modules\Erp\Proyectos\Resumen\SeccionResumen;
use App\Modules\Erp\Proyectos\Resumen\TarjetaResumen;
use App\Modules\Erp\Proyectos\Services\DatosProyecto;
use App\Modules\Erp\Proyectos\Services\ProyectosService;
use App\Modules\Erp\Proyectos\Tablero\TarjetaPlazo;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use App\Modules\Nucleo\Services\EmpresaService;
use App\Modules\Nucleo\Services\ParametrosService;
use Carbon\CarbonImmutable;

/*
 * Plan 2.61, D6 y D9: el registro de tarjetas del Resumen y las de Proyectos (plazo y jefe inactivo). Personas y
 * proyectos inventados.
 */

beforeEach(function () {
    test()->seed(NucleoSeeder::class);
    $techquk = Party::factory()->juridica()->create(['razon_social' => 'Techquk S.A.C.']);
    app(ParametrosService::class)->cambiarDesdeTexto(EmpresaService::PARAMETRO, $techquk->numero_documento, 1);
});

function proyectoDelResumen(?User $jefe = null): Proyecto
{
    $jefe ??= User::factory()->create();
    $creado = app(ProyectosService::class)->crear(
        new DatosProyecto('Red del almacén inventado', $jefe->id, CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-12-01')),
        User::factory()->create(),
    );

    return Proyecto::query()->findOrFail($creado->id);
}

function tarjetaDePrueba(string $clave, SeccionResumen $seccion, int $orden, bool $costos): TarjetaResumen
{
    return new class($clave, $seccion, $orden, $costos) implements TarjetaResumen
    {
        public function __construct(private string $c, private SeccionResumen $s, private int $o, private bool $costos) {}

        public function clave(): string
        {
            return $this->c;
        }

        public function seccion(): SeccionResumen
        {
            return $this->s;
        }

        public function orden(): int
        {
            return $this->o;
        }

        public function exigeCostos(): bool
        {
            return $this->costos;
        }

        public function contenido(int $proyectoId, User $usuario): ?ContenidoTarjeta
        {
            return new ContenidoTarjeta($this->c);
        }
    };
}

test('el registro ordena por sección y orden, no repite claves y esconde las de costos a quien no los ve', function () {
    $registro = new RegistroTarjetasResumen;
    $registro->registrar(tarjetaDePrueba('segunda', SeccionResumen::Cobranza, 20, false));
    $registro->registrar(tarjetaDePrueba('primera', SeccionResumen::Cobranza, 10, false));
    $registro->registrar(tarjetaDePrueba('con-montos', SeccionResumen::Dinero, 10, true));

    $titulos = fn (array $s): array => array_map(fn (array $lista): array => array_map(fn (ContenidoTarjeta $t) => $t->titulo, $lista), $s);
    $conCostos = User::factory()->create()->givePermissionTo(GrupoSensible::Costos->permiso());

    expect($titulos($registro->paraProyecto(1, User::factory()->create())))->toBe(['dinero' => [], 'plazo' => [], 'cobranza' => ['primera', 'segunda'], 'atencion' => []])
        ->and($titulos($registro->paraProyecto(1, $conCostos))['dinero'])->toBe(['con-montos'])
        ->and(fn () => $registro->registrar(tarjetaDePrueba('primera', SeccionResumen::Plazo, 1, false)))->toThrow(InvalidArgumentException::class, 'ya está registrada')
        ->and(fn () => $registro->registrar(tarjetaDePrueba('Mal Clave', SeccionResumen::Plazo, 1, false)))->toThrow(InvalidArgumentException::class, 'minúsculas');
});

test('el plazo: fechas, cuánto pasó y cuántos días faltan, en ámbar los últimos 15 y en rojo vencido', function () {
    $proyecto = proyectoDelResumen();

    test()->travelTo(CarbonImmutable::parse('2026-11-11 10:00', 'America/Lima'));
    expect(TarjetaPlazo::plazo($proyecto))->toBe(['porcentaje' => 33, 'dias' => 20, 'tono' => null]);

    test()->travelTo(CarbonImmutable::parse('2026-11-20 10:00', 'America/Lima'));
    expect(TarjetaPlazo::plazo($proyecto))->toBe(['porcentaje' => 63, 'dias' => 11, 'tono' => 'warning']);

    test()->travelTo(CarbonImmutable::parse('2026-12-04 10:00', 'America/Lima'));
    expect(TarjetaPlazo::plazo($proyecto))->toBe(['porcentaje' => 100, 'dias' => -3, 'tono' => 'danger']);

    $lineas = app(RegistroTarjetasResumen::class)->paraProyecto($proyecto->id, User::factory()->create())['plazo'][0]->lineas;
    expect(array_map(fn ($l) => [$l->etiqueta, $l->valor], $lineas))->toBe([
        ['Inicio', '01/11/2026'], ['Fin previsto', '01/12/2026'], ['Plazo transcurrido', '100 %'], ['Venció hace', '3 días'],
    ]);
});

test('con el jefe inactivo, «Atención» lo dice', function () {
    $jefe = User::factory()->create(['name' => 'Marta Jefa Inventada']);
    $proyecto = proyectoDelResumen($jefe);
    $atencion = fn (): array => app(RegistroTarjetasResumen::class)->paraProyecto($proyecto->id, User::factory()->create())['atencion'];

    expect(array_map(fn ($t) => $t->titulo, $atencion()))->not->toContain('Jefe de proyecto');

    $jefe->forceFill(['es_activo' => false])->save();
    expect($atencion()[0]->avisos[0]->texto)->toContain('Marta Jefa Inventada, ya no está activo');
});
