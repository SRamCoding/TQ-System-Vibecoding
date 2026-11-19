<?php

declare(strict_types=1);

use App\Modules\Crm\Cuentas\Enums\CasoRuc;
use App\Modules\Crm\Cuentas\Models\Cuenta;
use App\Modules\Crm\Cuentas\Services\CuentaCreada;
use App\Modules\Crm\Cuentas\Services\CuentaRechazada;
use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Nucleo\database\seeders\ParametrosSeeder;
use App\Modules\Nucleo\database\seeders\PermisosSeeder;
use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Excepciones\AccionRechazada;
use App\Modules\Nucleo\Excepciones\DocumentoIdentidadInvalido;
use App\Modules\Nucleo\Models\AuditoriaCambio;
use App\Modules\Nucleo\Models\NivelGobierno;
use App\Modules\Nucleo\Models\OrigenComercial;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\RolParty;
use App\Modules\Nucleo\Models\Rubro;
use App\Modules\Nucleo\Models\TipoDocumentoIdentidad;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Parties\DocumentoIdentidad;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;
use App\Modules\Nucleo\Seguridad\PermisosLegibles;
use App\Modules\Nucleo\Services\PartiesService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/*
 * Crear cuenta desde el RUC (plan 2.20, D1 a D4 y D7), por la acción registrada y sin pantalla: los cuatro casos,
 * los tipos de la ficha, el nivel de gobierno, el ejecutivo y que nada quede a medias. Empresas y RUC inventados.
 */

/** Un RUC válido inventado: los diez primeros dígitos y su verificador. */
function rucDePrueba(string $diez): string
{
    return $diez.DocumentoIdentidad::digitoVerificadorRuc($diez);
}

function crearCuentaComo(User $usuario, array $datos): CuentaCreada
{
    return app(EjecutorAcciones::class)->ejecutar('crm.cuentas.crear', $datos, $usuario);
}

/** Lo mínimo de una cuenta privada nueva; se completa o reemplaza con `$mas`. */
function datosDeCuenta(array $mas = []): array
{
    return [
        'ruc' => rucDePrueba('2060000001'),
        'es_publica' => false,
        'ejecutivo_user_id' => test()->ejecutivo->id,
        'origen_comercial_id' => test()->origen->id,
        'razon_social' => 'Ferretería Inventada del Norte S.A.C.',
        ...$mas,
    ];
}

function fichaExistente(string $ruc, array $mas = []): Party
{
    return Party::factory()->juridica()->create(['numero_documento' => $ruc, 'razon_social' => 'Comercial Ejemplo S.A.C.', ...$mas]);
}

beforeEach(function () {
    $this->seed([ParametrosSeeder::class, PermisosSeeder::class]);
    TipoDocumentoIdentidad::query()->firstOrCreate(['codigo' => Catalogo::RUC], ['nombre' => 'RUC', 'longitud' => 11]);
    TipoDocumentoIdentidad::query()->firstOrCreate(['codigo' => Catalogo::DNI], ['nombre' => 'DNI', 'longitud' => 8]);
    RolParty::factory()->create(['codigo' => CuentasService::ROL_CLIENTE, 'nombre' => 'Cliente']);
    RolParty::factory()->create(['codigo' => CuentasService::ROL_ENTIDAD, 'nombre' => 'Entidad pública']);
    $this->origen = OrigenComercial::factory()->create(['nombre' => 'Feria']);
    $this->nivel = NivelGobierno::factory()->create(['nombre' => 'Gobierno local']);
    $this->ejecutivo = User::factory()->create(['name' => 'Lucía Ramos Prueba'])->givePermissionTo('crm.cuentas.atender');
    $this->quien = User::factory()->create()->givePermissionTo('crm.cuentas.crear');
});

test('el módulo registra la acción con su permiso y su nombre legible', function () {
    expect(app(RegistroAcciones::class)->obtener('crm.cuentas.crear')->permiso())->toBe('crm.cuentas.crear')
        ->and(PermisosLegibles::etiqueta('crm.cuentas.crear'))->toBe('Crear cuentas');
});

test('caso 4: sin ficha, crea la ficha con el tipo Cliente, su rubro y la cuenta, en una sola operación', function () {
    $rubro = Rubro::factory()->create();

    $creada = crearCuentaComo($this->quien, datosDeCuenta(['nombre_comercial' => 'Ferre Norte', 'rubro_id' => $rubro->id, 'notas' => '  Llegó por la feria de octubre. ']));

    $ficha = Party::query()->findOrFail($creada->cuenta->partyId);
    $cuenta = Cuenta::query()->findOrFail($creada->cuenta->id);
    expect($creada->fichaNueva)->toBeTrue()
        ->and($ficha->razon_social)->toBe('Ferretería Inventada del Norte S.A.C.')
        ->and($ficha->nombre_comercial)->toBe('Ferre Norte')
        ->and($ficha->rubro_id)->toBe($rubro->id)
        ->and(app(PartiesService::class)->tieneRol($ficha->id, 'cliente'))->toBeTrue()
        ->and(app(PartiesService::class)->tieneRol($ficha->id, 'entidad'))->toBeFalse()
        ->and($creada->cuenta->esPublica)->toBeFalse()
        ->and($creada->cuenta->clienteDesde)->toBeNull()
        ->and($creada->cuenta->ejecutivoNombre)->toBe('Lucía Ramos Prueba')
        ->and($cuenta->origen_comercial_id)->toBe($this->origen->id)
        ->and($cuenta->notas)->toBe('Llegó por la feria de octubre.')
        ->and(AuditoriaCambio::query()->where('auditable_type', 'cuenta')->where('auditable_id', $cuenta->id)->value('user_id'))->toBe($this->quien->id);
});

test('caso 4 pública: asigna también Entidad pública y exige el nivel de gobierno', function () {
    $creada = crearCuentaComo($this->quien, datosDeCuenta(['razon_social' => 'Municipalidad Distrital de Prueba', 'es_publica' => true, 'nivel_gobierno_id' => $this->nivel->id]));

    expect($creada->cuenta->esPublica)->toBeTrue()
        ->and($creada->cuenta->nivelGobiernoNombre)->toBe('Gobierno local')
        ->and(fn () => crearCuentaComo($this->quien, datosDeCuenta(['ruc' => rucDePrueba('2060000002'), 'es_publica' => true])))
        ->toThrow(CuentaRechazada::class, 'necesita su nivel de gobierno')
        ->and(fn () => crearCuentaComo($this->quien, datosDeCuenta(['ruc' => rucDePrueba('2060000002'), 'nivel_gobierno_id' => $this->nivel->id])))
        ->toThrow(CuentaRechazada::class, 'privada no lleva nivel')
        ->and(Party::query()->where('numero_documento', rucDePrueba('2060000002'))->exists())->toBeFalse();
});

test('caso 4 con RUC 10: la ficha es de persona natural y avisa su posible pareja con DNI, sin bloquear', function () {
    $dni = Party::factory()->create(['numero_documento' => '45678912', 'nombres' => 'Carmen', 'apellidos' => 'Torres Inventada']);
    $ruc = rucDePrueba('1045678912');

    $creada = crearCuentaComo($this->quien, datosDeCuenta(['ruc' => $ruc, 'razon_social' => null, 'nombres' => 'Carmen', 'apellidos' => 'Torres Inventada']));

    expect($creada->posibleMismaPersona?->id)->toBe($dni->id)
        ->and(app(CuentasService::class)->revisarRuc($ruc)->esPersonaNatural)->toBeTrue();
});

test('caso 2: con ficha activa sin cuenta, usa la ficha sin tocar sus datos y le suma Cliente sin fallar si ya lo tiene', function () {
    $proveedor = fichaExistente(rucDePrueba('2060000003'));
    $yaCliente = fichaExistente(rucDePrueba('2060000004'));
    app(PartiesService::class)->asignarRol($yaCliente->id, 'cliente', CarbonImmutable::parse('2026-01-01'));

    $primera = crearCuentaComo($this->quien, datosDeCuenta(['ruc' => $proveedor->numero_documento, 'razon_social' => 'Otro nombre que no se usa']));
    $segunda = crearCuentaComo($this->quien, datosDeCuenta(['ruc' => $yaCliente->numero_documento]));

    expect($primera->fichaNueva)->toBeFalse()
        ->and($primera->cuenta->partyId)->toBe($proveedor->id)
        ->and($proveedor->refresh()->razon_social)->toBe('Comercial Ejemplo S.A.C.')
        ->and(app(PartiesService::class)->tieneRol($proveedor->id, 'cliente'))->toBeTrue()
        ->and($segunda->cuenta->partyId)->toBe($yaCliente->id);
});

test('caso 2: una ficha que ya es Entidad pública da una cuenta pública, y pide su nivel', function () {
    $entidad = fichaExistente(rucDePrueba('2060000005'), ['razon_social' => 'Gobierno Regional de Prueba']);
    app(PartiesService::class)->asignarRol($entidad->id, 'entidad', CarbonImmutable::parse('2026-01-01'));

    expect(fn () => crearCuentaComo($this->quien, datosDeCuenta(['ruc' => $entidad->numero_documento])))->toThrow(CuentaRechazada::class, 'necesita su nivel de gobierno')
        ->and(crearCuentaComo($this->quien, datosDeCuenta(['ruc' => $entidad->numero_documento, 'nivel_gobierno_id' => $this->nivel->id]))->cuenta->esPublica)->toBeTrue();
});

test('caso 1: si la ficha ya tiene cuenta, no crea nada y dice quién la atiende', function () {
    $creada = crearCuentaComo($this->quien, datosDeCuenta());

    expect(fn () => crearCuentaComo($this->quien, datosDeCuenta()))->toThrow(CuentaRechazada::class, 'Esta cuenta ya existe: Ferretería Inventada del Norte S.A.C. · '.rucDePrueba('2060000001').', que atiende Lucía Ramos Prueba')
        ->and(Cuenta::query()->count())->toBe(1)
        ->and(app(CuentasService::class)->revisarRuc(rucDePrueba('2060000001'))->cuenta?->id)->toBe($creada->cuenta->id);
});

test('caso 3: con la ficha inactiva no crea la cuenta y pide reactivarla en Personas y empresas', function () {
    $inactiva = fichaExistente(rucDePrueba('2060000006'), ['es_activo' => false]);

    expect(fn () => crearCuentaComo($this->quien, datosDeCuenta(['ruc' => $inactiva->numero_documento])))->toThrow(CuentaRechazada::class, 'Esta ficha está inactiva')
        ->and(Cuenta::query()->count())->toBe(0);
});

test('el primer paso dice el caso de cada RUC sin escribir nada, y rechaza un RUC con el dígito verificador mal', function () {
    $servicio = app(CuentasService::class);
    $sinCuenta = fichaExistente(rucDePrueba('2060000003'));
    $inactiva = fichaExistente(rucDePrueba('2060000006'), ['es_activo' => false]);
    $conCuenta = Cuenta::factory()->create(['party_id' => fichaExistente(rucDePrueba('2060000007'))->id]);
    $malo = substr(rucDePrueba('2060000008'), 0, 10).((int) substr(rucDePrueba('2060000008'), -1) + 1) % 10;

    expect($servicio->revisarRuc(rucDePrueba('2060000009'))->caso)->toBe(CasoRuc::FichaNueva)
        ->and($servicio->revisarRuc($sinCuenta->numero_documento)->caso)->toBe(CasoRuc::FichaSinCuenta)
        ->and($servicio->revisarRuc($inactiva->numero_documento)->caso)->toBe(CasoRuc::FichaInactiva)
        ->and($servicio->revisarRuc(substr($conCuenta->party->numero_documento, 0, 2).'-'.substr($conCuenta->party->numero_documento, 2))->caso)->toBe(CasoRuc::CuentaExiste)
        ->and(fn () => $servicio->revisarRuc($malo))->toThrow(DocumentoIdentidadInvalido::class)
        ->and(fn () => $servicio->revisarRuc('45678912'))->toThrow(DocumentoIdentidadInvalido::class)
        ->and(Cuenta::query()->count())->toBe(1);
});

test('el aviso por nombre encuentra una ficha parecida y nunca bloquea', function () {
    fichaExistente(rucDePrueba('2060000003'), ['razon_social' => 'Ferretería Inventada del Norte S.A.C.']);

    $parecidas = app(CuentasService::class)->parecidosPorNombre('Ferreteria Inventada del Nortee');

    expect($parecidas)->toHaveCount(1)
        ->and(app(CuentasService::class)->parecidosPorNombre('  '))->toBe([])
        ->and(crearCuentaComo($this->quien, datosDeCuenta())->fichaNueva)->toBeTrue();
});

test('el ejecutivo debe estar activo y poder atender cuentas', function () {
    $sinPermiso = User::factory()->create();
    $inactivo = User::factory()->create(['es_activo' => false])->givePermissionTo('crm.cuentas.atender');

    expect(fn () => crearCuentaComo($this->quien, datosDeCuenta(['ejecutivo_user_id' => $sinPermiso->id])))->toThrow(CuentaRechazada::class, 'no puede atender cuentas')
        ->and(fn () => crearCuentaComo($this->quien, datosDeCuenta(['ejecutivo_user_id' => $inactivo->id])))->toThrow(CuentaRechazada::class, 'no puede atender cuentas')
        ->and(Party::query()->where('numero_documento', rucDePrueba('2060000001'))->exists())->toBeFalse();
});

test('si un tipo no se puede asignar, no queda ni la ficha ni la cuenta', function () {
    RolParty::query()->where('codigo', 'entidad')->update(['es_activo' => false]);

    expect(fn () => crearCuentaComo($this->quien, datosDeCuenta(['es_publica' => true, 'nivel_gobierno_id' => $this->nivel->id])))->toThrow(Exception::class)
        ->and(Party::query()->where('numero_documento', rucDePrueba('2060000001'))->exists())->toBeFalse()
        ->and(Cuenta::query()->count())->toBe(0);
});

test('la acción rechaza a quien no tiene el permiso y valida sus campos', function () {
    expect(fn () => crearCuentaComo(User::factory()->create(), datosDeCuenta()))->toThrow(AccionRechazada::class)
        ->and(fn () => crearCuentaComo($this->quien, datosDeCuenta(['origen_comercial_id' => null])))->toThrow(ValidationException::class)
        ->and(fn () => crearCuentaComo($this->quien, datosDeCuenta(['ejecutivo_user_id' => 0])))->toThrow(ValidationException::class);
});
