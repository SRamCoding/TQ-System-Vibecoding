<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Filament\Pages\SaludDelSistema;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Salud\ComprobacionCola;
use App\Modules\Nucleo\Salud\ComprobacionIa;
use App\Modules\Nucleo\Salud\ComprobacionSalud;
use App\Modules\Nucleo\Salud\EstadoSalud;
use App\Modules\Nucleo\Salud\RegistroSalud;
use App\Modules\Nucleo\Salud\ResultadoComprobacion;
use App\Modules\Nucleo\Services\SeguridadService;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;

/*
 * Configuración → Salud del sistema (plan 1.12, D2): un recuadro por comprobación, con su estado escrito, y el enlace a
 * Pulse. La cola y el gasto de IA (las dos comprobaciones de este paso). Personas y trabajos de prueba inventados.
 */

beforeEach(function () {
    // Las pruebas corren con la cola `sync`; la comprobación de la cola solo revisa una de base de datos.
    config(['nucleo.ubigeos.csv' => '/no/existe.csv', 'queue.default' => 'database']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
    test()->travelTo(CarbonImmutable::parse('2027-03-20 10:00', 'America/Lima'));
});

it('entra solo quien tiene salud.ver (el administrador)', function () {
    $this->actingAs(User::factory()->create()->assignRole(SeguridadService::ROL_ADMINISTRADOR))->get(SaludDelSistema::getUrl())->assertOk();
    $this->actingAs(User::factory()->create())->get(SaludDelSistema::getUrl())->assertForbidden();
});

it('muestra un recuadro por comprobación, con su estado escrito, y el enlace a Pulse', function () {
    $html = Livewire::actingAs(User::factory()->create()->assignRole(SeguridadService::ROL_ADMINISTRADOR))->test(SaludDelSistema::class)->html();

    foreach (['nucleo.programador', 'nucleo.respaldos', 'nucleo.huerfanos-adjuntos', 'nucleo.cola', 'nucleo.ia'] as $clave) {
        expect($html)->toContain('data-tq-salud="'.$clave.'"');
    }

    expect($html)->toContain('Sin datos')->toContain('data-tq-salud="pulse"')->toContain('/pulse');
});

it('una comprobación que falla al revisarse se ve como falla, sin tumbar la pantalla', function () {
    $rota = new class implements ComprobacionSalud
    {
        public function clave(): string
        {
            return 'prueba.rota';
        }

        public function titulo(): string
        {
            return 'Rota';
        }

        public function icono(): string
        {
            return 'heroicon-o-beaker';
        }

        public function revisar(): ResultadoComprobacion
        {
            throw new RuntimeException('Sueldo de prueba: 5000');
        }
    };
    app()->instance($rota::class, $rota);
    app(RegistroSalud::class)->registrar($rota::class);

    Log::spy();
    $html = Livewire::actingAs(User::factory()->create()->assignRole(SeguridadService::ROL_ADMINISTRADOR))->test(SaludDelSistema::class)->html();

    // Al registro va solo la clase del error, nunca su mensaje (regla 10).
    Log::shouldHaveReceived('error')->withArgs(fn (string $mensaje, array $contexto): bool => $contexto['error'] === RuntimeException::class && ! str_contains(json_encode($contexto), 'Sueldo'));
    expect($html)->toContain('data-tq-salud="prueba.rota"')->toContain('No se pudo revisar')->not->toContain('Sueldo');
});

it('la cola: bien sin pendientes; falla si un trabajo espera más de 15 minutos o falló en 24 horas; atender si falló en la semana', function () {
    $cola = app(ComprobacionCola::class);
    expect($cola->revisar()->estado)->toBe(EstadoSalud::Bien);

    DB::table('failed_jobs')->insert(['uuid' => fake()->uuid(), 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'Prueba', 'failed_at' => now()->subDays(3)]);
    expect($cola->revisar()->estado)->toBe(EstadoSalud::Aviso);

    DB::table('failed_jobs')->insert(['uuid' => fake()->uuid(), 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'Prueba', 'failed_at' => now()->subHours(2)]);
    expect($cola->revisar()->estado)->toBe(EstadoSalud::Falla);

    DB::table('failed_jobs')->delete();
    DB::table('jobs')->insert(['queue' => 'ia', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->subMinutes(20)->getTimestamp(), 'created_at' => now()->subMinutes(20)->getTimestamp()]);
    $resultado = $cola->revisar();
    expect($resultado->estado)->toBe(EstadoSalud::Falla)
        ->and($resultado->detalle)->toHaveKey('En espera (ia)')
        ->and($resultado->detalle['En espera (ia)'])->toContain('hace 20 minutos');
});

it('el gasto de IA: sin procesos encendidos, sin datos; bien, atender desde el 80 % y falla al tope', function () {
    expect(app(ComprobacionIa::class)->revisar()->estado)->toBe(EstadoSalud::SinDatos)
        ->and(ComprobacionIa::estado('7.99', '10.00'))->toBe(EstadoSalud::Bien)
        ->and(ComprobacionIa::estado('8.00', '10.00'))->toBe(EstadoSalud::Aviso)
        ->and(ComprobacionIa::estado('10.00', '10.00'))->toBe(EstadoSalud::Falla)
        ->and(ComprobacionIa::estado('0.00', '0.00'))->toBe(EstadoSalud::Falla)
        ->and(ComprobacionIa::porcentaje('2.50', '10.00'))->toBe(25);
});

it('una comprobación con un ícono que no existe tampoco tumba la pantalla', function () {
    $sinIcono = new class implements ComprobacionSalud
    {
        public function clave(): string
        {
            return 'prueba.sin-icono';
        }

        public function titulo(): string
        {
            return 'Sin ícono';
        }

        public function icono(): string
        {
            return 'heroicon-o-no-existe-este-icono';
        }

        public function revisar(): ResultadoComprobacion
        {
            return new ResultadoComprobacion(EstadoSalud::Bien, 'Bien.');
        }
    };
    app()->instance($sinIcono::class, $sinIcono);
    app(RegistroSalud::class)->registrar($sinIcono::class);

    $html = Livewire::actingAs(User::factory()->create()->assignRole(SeguridadService::ROL_ADMINISTRADOR))->test(SaludDelSistema::class)->html();

    expect($html)->toContain('data-tq-salud="prueba.sin-icono"')->toContain('No se pudo revisar')->toContain('data-tq-salud="nucleo.programador"');
});

it('el registro de comprobaciones no se arma al arrancar la app, solo cuando alguien lo pide', function () {
    expect(app()->resolved(RegistroSalud::class))->toBeFalse()
        ->and(app(RegistroSalud::class)->comprobaciones())->toHaveKey('nucleo.ia');
});

it('con una cola que no es de base de datos, la cola sale sin datos, no en verde', function () {
    config(['queue.default' => 'redis']);

    expect(app(ComprobacionCola::class)->revisar()->estado)->toBe(EstadoSalud::SinDatos);
});

it('los montos de la IA salen con dos decimales sin pasar por float', function () {
    expect(ComprobacionIa::monto('1234.5678'))->toBe('1,234.57')
        ->and(ComprobacionIa::monto('0'))->toBe('0.00')
        ->and(ComprobacionIa::monto('20.004'))->toBe('20.00');
});
