<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Events\CuentaBancariaActivada;
use App\Modules\Nucleo\Events\CuentaBancariaDesactivada;
use App\Modules\Nucleo\Events\CuentaBancariaRegistrada;
use App\Modules\Nucleo\Excepciones\CuentaBancariaRechazada;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Models\Moneda;
use App\Modules\Nucleo\Models\Parametro;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\PartyCuentaBancaria;
use App\Modules\Nucleo\Models\PartyRol;
use App\Modules\Nucleo\Models\TipoDocumentoIdentidad;
use App\Modules\Nucleo\Parties\DatosCuentaBancaria;
use App\Modules\Nucleo\Services\CuentasBancariasService;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Services\PartiesService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/*
 * Cuentas bancarias (plan 1.3b, D2 y D5): banco, número y moneda no cambian; la misma cuenta solo en la misma
 * persona; la de detracciones es del Banco de la Nación, en soles, una activa por party y nunca de pago normal.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
});

function cuentas(): CuentasBancariasService
{
    return app(CuentasBancariasService::class);
}

/** Una empresa con el rol Banco vigente. RUC 20 inventado salvo que se pida otro. */
function bancoConRol(?string $ruc = null, string $nombre = 'Banco Ejemplo S.A.'): Party
{
    $banco = Party::factory()->juridica()->create(array_filter([
        'tipo_documento_id' => TipoDocumentoIdentidad::query()->where('codigo', Catalogo::RUC)->value('id'),
        'numero_documento' => $ruc,
        'razon_social' => $nombre,
    ]));
    app(PartiesService::class)->asignarRol($banco->id, 'banco', CarbonImmutable::parse('2026-01-01'));

    return $banco;
}

/** El Banco de la Nación: el RUC del parámetro `parties.ruc_banco_detracciones`. */
function bancoDeLaNacion(): Party
{
    return bancoConRol('20100030595', 'Banco de la Nación');
}

function datosCuenta(int $bancoId, string $numero = '191-1234567-0-12', string $moneda = 'PEN', mixed ...$otros): DatosCuentaBancaria
{
    return new DatosCuentaBancaria($bancoId, $numero, $moneda, ...$otros);
}

function versionCuenta(int $id): int
{
    return (int) PartyCuentaBancaria::query()->whereKey($id)->value('version');
}

test('registra la cuenta solo con dígitos, con su CCI, y emite su evento', function () {
    Event::fake([CuentaBancariaRegistrada::class]);
    $titular = Party::factory()->juridica()->create();

    $id = cuentas()->registrar($titular->id, datosCuenta(bancoConRol()->id, cci: '002-191-001234567012-55'));
    $cuenta = PartyCuentaBancaria::query()->findOrFail($id);

    expect($cuenta->numero_cuenta)->toBe('1911234567012')
        ->and($cuenta->cci)->toBe('00219100123456701255')
        ->and($cuenta->es_activa)->toBeTrue();
    Event::assertDispatched(CuentaBancariaRegistrada::class, fn (CuentaBancariaRegistrada $e): bool => $e->cuentaId === $id && $e->partyId === $titular->id);
});

test('se rechaza una cuenta con datos imposibles', function (Closure $datos, string $mensaje) {
    $titular = Party::factory()->juridica()->create();

    expect(fn () => cuentas()->registrar($titular->id, $datos(bancoConRol()->id)))->toThrow(CuentaBancariaRechazada::class, $mensaje);
})->with([
    'número con letras' => [fn (int $b) => datosCuenta($b, '191-ABC'), 'solo dígitos'],
    'número vacío' => [fn (int $b) => datosCuenta($b, ' - '), 'solo dígitos'],
    'CCI de 19 dígitos' => [fn (int $b) => datosCuenta($b, cci: '0021910012345670125'), 'exactamente 20 dígitos'],
    'moneda que no existe' => [fn (int $b) => datosCuenta($b, moneda: 'XXX'), 'La moneda «XXX» no existe'],
    'detracciones como principal' => [fn (int $b) => datosCuenta($b, esDetraccion: true, esPrincipal: true), 'no puede ser la principal'],
]);

test('el banco debe ser una empresa activa con el rol Banco vigente', function () {
    $titular = Party::factory()->juridica()->create();
    $sinRol = Party::factory()->juridica()->create();
    $inactivo = bancoConRol();
    $inactivo->update(['es_activo' => false]);

    expect(fn () => cuentas()->registrar($titular->id, datosCuenta($sinRol->id)))->toThrow(CuentaBancariaRechazada::class, 'rol Banco')
        ->and(fn () => cuentas()->registrar($titular->id, datosCuenta($inactivo->id)))->toThrow(CuentaBancariaRechazada::class, 'rol Banco');
});

test('una party desactivada no recibe cuentas nuevas', function () {
    expect(fn () => cuentas()->registrar(Party::factory()->juridica()->inactiva()->create()->id, datosCuenta(bancoConRol()->id)))
        ->toThrow(CuentaBancariaRechazada::class, 'está desactivada');
});

test('la misma cuenta a nombre de otra persona se rechaza con un mensaje de negocio', function () {
    $banco = bancoConRol();
    cuentas()->registrar(Party::factory()->juridica()->create()->id, datosCuenta($banco->id));

    expect(fn () => cuentas()->registrar(Party::factory()->juridica()->create()->id, datosCuenta($banco->id, '1911234567012')))
        ->toThrow(CuentaBancariaRechazada::class, 'ya está registrada a nombre de otra persona o empresa');
});

test('la misma cuenta sí se registra en el DNI y el RUC 10 de una persona', function () {
    $dni = Party::factory()->create(['numero_documento' => '01234567', 'nombres' => 'Ana', 'apellidos' => 'Pérez Soto', 'razon_social' => 'Pérez Soto Ana']);
    $ruc10 = Party::factory()->create([
        'tipo_documento_id' => TipoDocumentoIdentidad::query()->where('codigo', Catalogo::RUC)->value('id'),
        'numero_documento' => '10012345670', 'nombres' => 'Ana', 'apellidos' => 'Pérez Soto', 'razon_social' => 'Pérez Soto Ana',
    ]);
    $banco = bancoConRol();

    cuentas()->registrar($dni->id, datosCuenta($banco->id, cci: '00219100123456701255'));
    cuentas()->registrar($ruc10->id, datosCuenta($banco->id, cci: '00219100123456701255'));

    expect(PartyCuentaBancaria::query()->where('numero_cuenta', '1911234567012')->count())->toBe(2);
});

test('la misma cuenta dos veces en una party, o el mismo CCI en otra cuenta suya, se rechaza', function () {
    $titular = Party::factory()->juridica()->create();
    $banco = bancoConRol();
    cuentas()->registrar($titular->id, datosCuenta($banco->id, cci: '00219100123456701255'));

    expect(fn () => cuentas()->registrar($titular->id, datosCuenta($banco->id, moneda: 'USD')))->toThrow(CuentaBancariaRechazada::class, 'ya está registrada para esta persona')
        ->and(fn () => cuentas()->registrar($titular->id, datosCuenta($banco->id, '999', cci: '00219100123456701255')))->toThrow(CuentaBancariaRechazada::class, 'Ese CCI ya está en otra cuenta');
});

test('una principal por moneda: marcar otra desmarca la anterior de la misma moneda', function () {
    $titular = Party::factory()->juridica()->create();
    $banco = bancoConRol();
    $soles = cuentas()->registrar($titular->id, datosCuenta($banco->id, '111', esPrincipal: true));
    $dolares = cuentas()->registrar($titular->id, datosCuenta($banco->id, '222', 'USD', esPrincipal: true));
    $otraSoles = cuentas()->registrar($titular->id, datosCuenta($banco->id, '333'));

    cuentas()->marcarPrincipal($otraSoles, 1);

    expect(PartyCuentaBancaria::query()->findOrFail($soles)->es_principal)->toBeFalse()
        ->and(PartyCuentaBancaria::query()->findOrFail($dolares)->es_principal)->toBeTrue()
        ->and(PartyCuentaBancaria::query()->findOrFail($otraSoles)->es_principal)->toBeTrue()
        ->and(fn () => cuentas()->marcarPrincipal($otraSoles, 2))->toThrow(CuentaBancariaRechazada::class, 'ya es la principal');
});

test('la de detracciones va en el Banco de la Nación, en soles, una activa por party', function () {
    $titular = Party::factory()->juridica()->create();
    $nacion = bancoDeLaNacion();

    $id = cuentas()->registrar($titular->id, datosCuenta($nacion->id, '00-123-456789', esDetraccion: true));

    expect(PartyCuentaBancaria::query()->findOrFail($id)->es_detraccion)->toBeTrue()
        ->and(fn () => cuentas()->registrar($titular->id, datosCuenta(bancoConRol()->id, '555', esDetraccion: true)))->toThrow(CuentaBancariaRechazada::class, 'solo puede ser del Banco de la Nación')
        ->and(fn () => cuentas()->registrar($titular->id, datosCuenta($nacion->id, '666', 'USD', esDetraccion: true)))->toThrow(CuentaBancariaRechazada::class, 'moneda local')
        ->and(fn () => cuentas()->registrar($titular->id, datosCuenta($nacion->id, '777', esDetraccion: true)))->toThrow(CuentaBancariaRechazada::class, 'Ya tiene una cuenta de detracciones activa');
});

test('la de detracciones nunca es principal ni cuenta de pago normal', function () {
    $titular = Party::factory()->juridica()->create();
    $detraccion = cuentas()->registrar($titular->id, datosCuenta(bancoDeLaNacion()->id, '00123456789', esDetraccion: true));
    $normal = cuentas()->registrar($titular->id, datosCuenta(bancoConRol()->id, '1911234567012', esPrincipal: true));
    $parties = app(PartiesService::class);

    expect(fn () => cuentas()->marcarPrincipal($detraccion, 1))->toThrow(CuentaBancariaRechazada::class, 'no puede ser la principal')
        ->and($parties->cuentaPrincipal($titular->id, 'PEN')?->id)->toBe($normal)
        ->and(array_map(fn ($c): int => $c->id, $parties->cuentasDePago($titular->id)))->toBe([$normal])
        ->and($parties->cuentaDetracciones($titular->id)?->id)->toBe($detraccion)
        ->and($parties->cuentaDetracciones($titular->id)?->esDetraccion)->toBeTrue()
        ->and($parties->cuentaPrincipal($titular->id, 'USD'))->toBeNull();
});

test('las lecturas dan la etiqueta con banco, número y moneda, y no dan las desactivadas', function () {
    $titular = Party::factory()->juridica()->create();
    $banco = bancoConRol(nombre: 'Banco Ejemplo S.A.');
    $activa = cuentas()->registrar($titular->id, datosCuenta($banco->id, '111', 'USD'));
    $inactiva = cuentas()->registrar($titular->id, datosCuenta($banco->id, '222'));
    cuentas()->desactivar($inactiva, 1);

    $pago = app(PartiesService::class)->cuentasDePago($titular->id);

    expect($pago)->toHaveCount(1)
        ->and($pago[0]->id)->toBe($activa)
        ->and($pago[0]->etiqueta)->toBe('Banco Ejemplo S.A. · 111 · USD')
        ->and($pago[0]->codigoMoneda)->toBe('USD');
});

test('el CCI solo se completa si estaba vacío', function () {
    $titular = Party::factory()->juridica()->create();
    $id = cuentas()->registrar($titular->id, datosCuenta(bancoConRol()->id));

    cuentas()->completarCci($id, '002 191 001234567012 55', 1);

    expect(PartyCuentaBancaria::query()->findOrFail($id)->cci)->toBe('00219100123456701255')
        ->and(fn () => cuentas()->completarCci($id, '00219100123456701299', 2))->toThrow(CuentaBancariaRechazada::class, 'no se cambia')
        ->and(fn () => cuentas()->completarCci($id, '123', 2))->toThrow(CuentaBancariaRechazada::class, '20 dígitos');
});

test('el servicio no ofrece ningún método para cambiar número, banco o moneda', function () {
    $metodos = array_map(fn (ReflectionMethod $m): string => $m->getName(), (new ReflectionClass(CuentasBancariasService::class))->getMethods(ReflectionMethod::IS_PUBLIC));

    expect($metodos)->toEqualCanonicalizing(['__construct', 'registrar', 'completarCci', 'marcarPrincipal', 'desactivar', 'activar', 'borrar']);
});

test('desactivar quita la principal y emite su evento; activar emite el suyo', function () {
    Event::fake([CuentaBancariaDesactivada::class, CuentaBancariaActivada::class]);
    $titular = Party::factory()->juridica()->create();
    $id = cuentas()->registrar($titular->id, datosCuenta(bancoConRol()->id, esPrincipal: true));

    cuentas()->desactivar($id, 1);

    expect(PartyCuentaBancaria::query()->findOrFail($id)->es_principal)->toBeFalse()
        ->and(fn () => cuentas()->desactivar($id, 2))->toThrow(CuentaBancariaRechazada::class, 'ya está desactivada')
        ->and(fn () => cuentas()->marcarPrincipal($id, 2))->toThrow(CuentaBancariaRechazada::class, 'está desactivada');

    cuentas()->activar($id, 2);

    expect(PartyCuentaBancaria::query()->findOrFail($id)->es_activa)->toBeTrue()
        ->and(PartyCuentaBancaria::query()->findOrFail($id)->es_principal)->toBeFalse();
    Event::assertDispatched(CuentaBancariaDesactivada::class, fn ($e): bool => $e->cuentaId === $id);
    Event::assertDispatched(CuentaBancariaActivada::class, fn ($e): bool => $e->cuentaId === $id);
});

test('reactivar una de detracciones con otra activa se rechaza', function () {
    $titular = Party::factory()->juridica()->create();
    $nacion = bancoDeLaNacion();
    $vieja = cuentas()->registrar($titular->id, datosCuenta($nacion->id, '111', esDetraccion: true));
    cuentas()->desactivar($vieja, 1);
    cuentas()->registrar($titular->id, datosCuenta($nacion->id, '222', esDetraccion: true));

    expect(fn () => cuentas()->activar($vieja, 2))->toThrow(CuentaBancariaRechazada::class, 'Ya tiene una cuenta de detracciones activa');
});

test('la versión leída se exige y una cuenta no usada se borra', function () {
    $id = cuentas()->registrar(Party::factory()->juridica()->create()->id, datosCuenta(bancoConRol()->id));

    expect(fn () => cuentas()->desactivar($id, 5))->toThrow(VersionDesactualizada::class, 'esta cuenta bancaria');

    cuentas()->borrar($id, 1);

    expect(PartyCuentaBancaria::query()->find($id))->toBeNull();
});

test('los mensajes de rechazo no muestran el número de la cuenta', function () {
    $banco = bancoConRol();
    cuentas()->registrar(Party::factory()->juridica()->create()->id, datosCuenta($banco->id));
    $mensaje = null;

    try {
        cuentas()->registrar(Party::factory()->juridica()->create()->id, datosCuenta($banco->id));
    } catch (CuentaBancariaRechazada $e) {
        $mensaje = $e->getMessage();
    }

    expect($mensaje)->toBeString()->not->toContain('1911234567012')->not->toContain('191-1234567');
});

test('borrar una party nunca usada se lleva también sus cuentas', function () {
    $titular = Party::factory()->juridica()->create();
    cuentas()->registrar($titular->id, datosCuenta(bancoConRol()->id));

    app(PartiesService::class)->borrar($titular->id, 1);

    expect(PartyCuentaBancaria::query()->count())->toBe(0);
});

test('por ningún camino de modelo cambian banco, número, moneda, titular ni un CCI ya registrado', function (Closure $cambio) {
    $cuenta = PartyCuentaBancaria::query()->findOrFail(cuentas()->registrar(Party::factory()->juridica()->create()->id, datosCuenta(bancoConRol()->id, cci: '00219100123456701255')));

    expect(fn () => $cambio($cuenta))->toThrow(CuentaBancariaRechazada::class, 'no cambian')
        ->and($cuenta->refresh()->numero_cuenta)->toBe('1911234567012');
})->with([
    'número' => [fn (PartyCuentaBancaria $c) => $c->update(['numero_cuenta' => '999'])],
    'banco' => [fn (PartyCuentaBancaria $c) => $c->update(['banco_party_id' => bancoConRol(nombre: 'Otro Banco S.A.')->id])],
    'moneda' => [fn (PartyCuentaBancaria $c) => $c->update(['moneda_id' => Moneda::query()->where('codigo', 'USD')->value('id')])],
    'titular' => [fn (PartyCuentaBancaria $c) => $c->update(['party_id' => Party::factory()->juridica()->create()->id])],
    'CCI ya registrado' => [fn (PartyCuentaBancaria $c) => $c->update(['cci' => '00219100123456701299'])],
]);

test('si dos personas marcan la principal a la vez y la base rechaza a una, el mensaje es de negocio', function () {
    $titular = Party::factory()->juridica()->create();
    $banco = bancoConRol();
    $una = cuentas()->registrar($titular->id, datosCuenta($banco->id, '111'));
    $otra = cuentas()->registrar($titular->id, datosCuenta($banco->id, '222'));

    // La otra persona guarda su principal justo antes de que este servicio guarde la suya.
    PartyCuentaBancaria::saving(function (PartyCuentaBancaria $cuenta) use ($una, $otra): void {
        if ($cuenta->id === $una && $cuenta->es_principal) {
            DB::table('party_cuentas_bancarias')->where('id', $otra)->update(['es_principal' => true]);
        }
    });

    DB::transaction(function () use ($una, $titular): void {
        expect(fn () => cuentas()->marcarPrincipal($una, 1))
            ->toThrow(CuentaBancariaRechazada::class, 'Otra persona acaba de cambiar la cuenta principal. Vuelve a abrir la ficha.');

        expect(PartyCuentaBancaria::query()->where('party_id', $titular->id)->count())->toBe(2);
    });
});

test('las cuentas de pago dejan fuera las de un banco desactivado o sin el rol vigente', function () {
    $titular = Party::factory()->juridica()->create();
    $desactivado = bancoConRol(nombre: 'Banco Cerrado S.A.');
    $sinRol = bancoConRol(nombre: 'Ex Banco S.A.');
    $vigente = bancoConRol(nombre: 'Banco Vigente S.A.');
    cuentas()->registrar($titular->id, datosCuenta($desactivado->id, '111', esPrincipal: true));
    cuentas()->registrar($titular->id, datosCuenta($sinRol->id, '222'));
    $buena = cuentas()->registrar($titular->id, datosCuenta($vigente->id, '333'));
    $desactivado->update(['es_activo' => false]);
    PartyRol::query()->where('party_id', $sinRol->id)->update(['hasta' => '2026-02-01']);

    expect(array_map(fn ($c): int => $c->id, app(PartiesService::class)->cuentasDePago($titular->id)))->toBe([$buena])
        ->and(app(PartiesService::class)->cuentaPrincipal($titular->id, 'PEN'))->toBeNull();
});

test('si cambia el banco de detracciones, la cuenta del banco anterior deja de darse como de detracciones', function () {
    $titular = Party::factory()->juridica()->create();
    cuentas()->registrar($titular->id, datosCuenta(bancoDeLaNacion()->id, '00123456789', esDetraccion: true));
    $otro = bancoConRol('20999999990', 'Banco Nuevo de Detracciones S.A.');
    $version = (int) Parametro::query()->where('clave', 'ruc_banco_detracciones')->value('version');

    expect(app(PartiesService::class)->cuentaDetracciones($titular->id))->not->toBeNull();

    app(ParametrosService::class)->cambiarDeDinero('parties.ruc_banco_detracciones', $otro->numero_documento, $version);

    expect(app(PartiesService::class)->cuentaDetracciones($titular->id))->toBeNull();
});
