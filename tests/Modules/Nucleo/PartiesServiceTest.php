<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Enums\EventoAuditoria;
use App\Modules\Nucleo\Enums\TipoPersona;
use App\Modules\Nucleo\Excepciones\DocumentoIdentidadInvalido;
use App\Modules\Nucleo\Excepciones\PartyRechazada;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Models\AuditoriaCambio;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\PartyRol;
use App\Modules\Nucleo\Models\RolParty;
use App\Modules\Nucleo\Models\TipoDocumentoIdentidad;
use App\Modules\Nucleo\Models\Ubigeo;
use App\Modules\Nucleo\Parties\DatosParty;
use App\Modules\Nucleo\Services\EmpresaService;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Services\PartiesService;
use App\Modules\Nucleo\Services\UsuarioSistema;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Plan 1.3, D4 a D9: PartiesService registra, obtiene o crea, edita, cambia el estado, borra y maneja los
 * periodos de rol. Documentos inventados; los RUC tienen el dígito verificador calculado.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
});

function parties(): PartiesService
{
    return app(PartiesService::class);
}

function empresa(string $ruc = '20123456786', string $razonSocial = 'Comercial Ejemplo S.A.C.'): DatosParty
{
    return new DatosParty('6', $ruc, razonSocial: $razonSocial);
}

function persona(string $dni = '45678912', string $nombres = 'Ana María', string $apellidos = 'Pérez Gómez'): DatosParty
{
    return new DatosParty('1', $dni, nombres: $nombres, apellidos: $apellidos);
}

function partyRegistrada(DatosParty $datos): Party
{
    return Party::query()->findOrFail(parties()->registrar($datos)->id);
}

function fechaRol(string $fecha): CarbonImmutable
{
    return CarbonImmutable::parse($fecha);
}

// ——— Registrar ———

test('registra una empresa con el RUC limpio, el tipo deducido, en versión 1 y con bitácora', function () {
    $party = partyRegistrada(empresa(' 20-123.456.786 ', '  Comercial Ejemplo S.A.C. '));

    expect($party->numero_documento)->toBe('20123456786')
        ->and($party->tipo)->toBe(TipoPersona::Juridica)
        ->and($party->razon_social)->toBe('Comercial Ejemplo S.A.C.')
        ->and($party->version)->toBe(1)
        ->and($party->es_agente_retencion)->toBeFalse()
        ->and(AuditoriaCambio::query()->where('auditable_type', $party->getMorphClass())->where('auditable_id', $party->id)->value('evento'))
        ->toBe(EventoAuditoria::Creado);
});

test('en una persona natural la razón social se arma con apellidos y nombres', function () {
    $party = partyRegistrada(persona(nombres: ' Ana María ', apellidos: 'Pérez Gómez '));

    expect($party->tipo)->toBe(TipoPersona::Natural)
        ->and($party->nombres)->toBe('Ana María')
        ->and($party->razon_social)->toBe('Pérez Gómez Ana María');
});

test('un documento ya registrado se rechaza aunque se escriba distinto, y dice si está inactivo', function () {
    partyRegistrada(empresa());

    expect(fn () => parties()->registrar(empresa('20 123 456 786', 'Otro nombre S.A.')))
        ->toThrow(PartyRechazada::class, 'Ya está registrada: Comercial Ejemplo S.A.C. · 20123456786.');

    Party::query()->update(['es_activo' => false]);

    expect(fn () => parties()->registrar(empresa()))
        ->toThrow(PartyRechazada::class, 'Ya está registrada, pero está inactiva: Comercial Ejemplo S.A.C. · 20123456786.')
        ->and(Party::query()->count())->toBe(1);
});

test('lo que no cuadra con el documento o el tipo de persona se rechaza', function (DatosParty $datos, string $clase, string $mensaje) {
    expect(fn () => parties()->registrar($datos))->toThrow($clase, $mensaje)
        ->and(Party::query()->count())->toBe(0);
})->with([
    'RUC con dígito verificador mal' => [fn () => empresa('20123456787'), DocumentoIdentidadInvalido::class, 'su último dígito no corresponde'],
    'DNI de siete dígitos' => [fn () => persona('4567891'), DocumentoIdentidadInvalido::class, 'El DNI tiene 8 dígitos.'],
    'tipo de documento que no existe' => [fn () => new DatosParty('9', '123', razonSocial: 'X'), PartyRechazada::class, 'El tipo de documento «9» no existe'],
    'no domiciliado sin tipo de persona' => [fn () => new DatosParty('0', 'US123', razonSocial: 'Acme Inc.'), PartyRechazada::class, 'Elige si es persona natural o jurídica'],
    'RUC 20 como persona natural' => [fn () => new DatosParty('6', '20123456786', tipo: TipoPersona::Natural, nombres: 'Ana', apellidos: 'Pérez'), PartyRechazada::class, 'Ese documento es de una persona jurídica.'],
    'natural sin apellidos' => [fn () => persona(apellidos: '  '), PartyRechazada::class, 'Una persona natural lleva nombres y apellidos.'],
    'natural con razón social escrita' => [fn () => new DatosParty('1', '45678912', razonSocial: 'Otra', nombres: 'Ana', apellidos: 'Pérez'), PartyRechazada::class, 'la razón social no se escribe'],
    'jurídica sin razón social' => [fn () => empresa(razonSocial: ' '), PartyRechazada::class, 'Una persona jurídica lleva razón social.'],
    'jurídica con nombres' => [fn () => new DatosParty('6', '20123456786', razonSocial: 'X S.A.', nombres: 'Ana'), PartyRechazada::class, 'no lleva nombres ni apellidos'],
    'correo mal escrito' => [fn () => new DatosParty('6', '20123456786', razonSocial: 'X S.A.', email: 'ventas@'), PartyRechazada::class, '«ventas@» no es un correo válido.'],
]);

test('un no domiciliado registra el tipo de persona que se elige', function () {
    $party = partyRegistrada(new DatosParty('0', 'us-12345', tipo: TipoPersona::Juridica, razonSocial: 'Acme Inc.'));

    expect($party->numero_documento)->toBe('US12345')
        ->and($party->tipo)->toBe(TipoPersona::Juridica);
});

test('un tipo de documento desactivado ya no sirve para registrar', function () {
    TipoDocumentoIdentidad::query()->where('codigo', '7')->update(['es_activo' => false]);

    expect(fn () => parties()->registrar(new DatosParty('7', 'AB123456', nombres: 'Ana', apellidos: 'Pérez')))
        ->toThrow(PartyRechazada::class, 'El tipo de documento «7» no existe o está desactivado.');
});

test('el ubigeo debe estar activo y el correo se guarda en minúsculas', function () {
    $activo = Ubigeo::factory()->create();
    $inactivo = Ubigeo::factory()->create(['es_activo' => false]);

    $party = partyRegistrada(new DatosParty('6', '20123456786', razonSocial: 'X S.A.', ubigeoId: $activo->id, email: 'Ventas@Ejemplo.PE'));

    expect($party->email)->toBe('ventas@ejemplo.pe')
        ->and(fn () => parties()->registrar(new DatosParty('6', '20555555556', razonSocial: 'Y S.A.', ubigeoId: $inactivo->id)))
        ->toThrow(PartyRechazada::class, 'El ubigeo elegido no existe o está desactivado.');
});

// ——— Aviso DNI ↔ RUC 10 (D9) ———

test('registrar un RUC 10 avisa si ya existe el DNI que contiene, y crea igual', function () {
    $dni = partyRegistrada(persona('45678912'));

    $registro = parties()->registrar(new DatosParty('6', '10456789124', nombres: 'Ana María', apellidos: 'Pérez Gómez'));

    expect($registro->pareja?->id)->toBe($dni->id)
        ->and($registro->pareja?->etiqueta)->toBe('Pérez Gómez Ana María · 45678912')
        ->and(Party::query()->count())->toBe(2);
});

test('registrar un DNI avisa si ya existe su RUC 10, también con cero inicial', function () {
    $ruc = partyRegistrada(new DatosParty('6', '10012345670', nombres: 'Luis', apellidos: 'Díaz'));

    $registro = parties()->registrar(persona('01234567', 'Luis', 'Díaz'));

    expect($registro->pareja?->id)->toBe($ruc->id)
        ->and(Party::query()->findOrFail($registro->id)->numero_documento)->toBe('01234567');
});

test('sin pareja registrada, o con un RUC 20, no hay aviso', function () {
    partyRegistrada(persona('45678912'));

    expect(parties()->registrar(persona('87654321'))->pareja)->toBeNull()
        ->and(parties()->registrar(empresa())->pareja)->toBeNull();
});

// ——— Obtener o crear ———

test('obtenerOCrear crea la primera vez y devuelve la misma después, sin pisar sus datos', function () {
    $primera = parties()->obtenerOCrear(empresa());
    $segunda = parties()->obtenerOCrear(empresa(' 20123456786', 'Nombre distinto del OECE'));

    expect($primera->creada)->toBeTrue()
        ->and($segunda->creada)->toBeFalse()
        ->and($segunda->id)->toBe($primera->id)
        ->and($segunda->etiqueta)->toBe('Comercial Ejemplo S.A.C. · 20123456786')
        ->and(Party::query()->count())->toBe(1);
});

test('si otro proceso la creó justo antes, obtenerOCrear devuelve esa y la transacción sigue sana', function () {
    // Simula la carrera: la fila de «otro proceso» ya está cuando este intenta insertar. No se pregunta antes:
    // se inserta y, si la unicidad lo impide, se lee la existente.
    $ruc = TipoDocumentoIdentidad::query()->where('codigo', '6')->value('id');
    DB::table('parties')->insert([
        'tipo' => 'juridica', 'tipo_documento_id' => $ruc, 'persona_tipo_id' => $ruc,
        'numero_documento' => '20123456786', 'persona_numero' => '20123456786', 'razon_social' => 'Creada por otro proceso S.A.',
        // Plan 2.20a, N1: lo que el modelo llenaría al guardar; aquí se inserta a mano para simular al otro proceso.
        'nombre_normalizado' => 'creada por otro proceso',
        'created_by' => app(UsuarioSistema::class)->id(),
    ]);

    DB::transaction(function (): void {
        $obtenida = parties()->obtenerOCrear(empresa());
        $otra = parties()->obtenerOCrear(empresa('20555555556', 'Siguiente S.A.'));

        expect($obtenida->creada)->toBeFalse()
            ->and($obtenida->etiqueta)->toBe('Creada por otro proceso S.A. · 20123456786')
            ->and($otra->creada)->toBeTrue();
    });

    expect(Party::query()->count())->toBe(2);
});

test('obtenerOCrear devuelve una desactivada sin reactivarla y avisa que está inactiva', function () {
    $party = Party::factory()->juridica()->inactiva()->create();

    $obtenida = parties()->obtenerOCrear(empresa($party->numero_documento));

    expect($obtenida->id)->toBe($party->id)
        ->and($obtenida->activa)->toBeFalse()
        ->and($obtenida->creada)->toBeFalse()
        ->and($party->refresh()->es_activo)->toBeFalse();
});

test('obtenerOCrear también rechaza un RUC inválido: no se guarda', function () {
    expect(fn () => parties()->obtenerOCrear(empresa('20123456780')))->toThrow(DocumentoIdentidadInvalido::class)
        ->and(Party::query()->count())->toBe(0);
});

test('buscar por documento normaliza el número; la etiqueta de una inactiva lo dice', function () {
    $party = partyRegistrada(empresa());
    $party->update(['es_activo' => false]);

    expect(parties()->buscarPorDocumento('6', '20-123-456-786')?->id)->toBe($party->id)
        ->and(parties()->buscarPorDocumento('1', '20123456786'))->toBeNull()
        ->and(parties()->etiquetaDe($party->id))->toBe('Comercial Ejemplo S.A.C. · 20123456786 (inactiva)')
        ->and(parties()->etiquetaDe($party->id + 1000))->toBeNull();
});

// ——— Editar ———

test('editar una natural rearma la razón social, sube la versión y deja bitácora', function () {
    $party = partyRegistrada(persona());

    parties()->editar($party->id, ['apellidos' => 'Pérez Rojas', 'telefono' => ' 979 111 222 '], 1);
    $party->refresh();

    expect($party->razon_social)->toBe('Pérez Rojas Ana María')
        ->and($party->telefono)->toBe('979 111 222')
        ->and($party->version)->toBe(2)
        ->and(AuditoriaCambio::query()->where('auditable_id', $party->id)->where('auditable_type', $party->getMorphClass())->latest('id')->value('valores_nuevos'))
        ->toEqual(['apellidos' => 'Pérez Rojas', 'razon_social' => 'Pérez Rojas Ana María', 'telefono' => '979 111 222']);
});

test('editar una jurídica cambia su razón social; sin cambios no sube la versión', function () {
    $party = partyRegistrada(empresa());

    parties()->editar($party->id, ['razon_social' => 'Comercial Nueva S.A.C.'], 1);
    parties()->editar($party->id, ['razon_social' => 'Comercial Nueva S.A.C.'], 2);

    expect($party->refresh()->razon_social)->toBe('Comercial Nueva S.A.C.')
        ->and($party->version)->toBe(2);
});

test('editar no toca el documento, el tipo, las marcas tributarias ni los datos de SUNAT', function (string $campo, mixed $valor) {
    $party = partyRegistrada(empresa());

    expect(fn () => parties()->editar($party->id, [$campo => $valor], 1))
        ->toThrow(PartyRechazada::class, "El campo «{$campo}» no se cambia por esta vía.");
})->with([
    ['numero_documento', '20555555556'],
    ['tipo_documento_id', 1],
    ['tipo', 'natural'],
    ['es_agente_retencion', true],
    ['estado_sunat', 'ACTIVO'],
    ['es_activo', false],
]);

test('editar una natural no deja borrar sus nombres ni escribir la razón social', function () {
    $party = partyRegistrada(persona());

    expect(fn () => parties()->editar($party->id, ['nombres' => ' '], 1))->toThrow(PartyRechazada::class, 'lleva nombres y apellidos')
        ->and(fn () => parties()->editar($party->id, ['razon_social' => 'Otra'], 1))->toThrow(PartyRechazada::class, 'la razón social no se escribe');
});

test('editar una jurídica no acepta nombres ni apellidos con valor', function () {
    $party = partyRegistrada(empresa());

    expect(fn () => parties()->editar($party->id, ['nombres' => 'Ana'], 1))->toThrow(PartyRechazada::class, 'no lleva nombres ni apellidos');
});

test('los campos del otro tipo de persona pueden llegar vacíos, como los manda una acción', function () {
    $natural = partyRegistrada(persona());
    $juridica = partyRegistrada(empresa());

    parties()->editar($natural->id, ['nombres' => 'Ana', 'apellidos' => 'Pérez', 'razon_social' => null], 1);
    parties()->editar($juridica->id, ['razon_social' => 'Nueva S.A.', 'nombres' => null, 'apellidos' => ''], 1);

    expect($natural->refresh()->razon_social)->toBe('Pérez Ana')
        ->and($juridica->refresh()->razon_social)->toBe('Nueva S.A.')
        ->and($juridica->nombres)->toBeNull();
});

test('si otra persona guardó antes, se avisa y no se pisa su cambio', function () {
    $party = partyRegistrada(empresa());
    parties()->editar($party->id, ['telefono' => '111'], 1);

    expect(fn () => parties()->editar($party->id, ['telefono' => '222'], 1))
        ->toThrow(VersionDesactualizada::class, 'Otra persona cambió «Comercial Ejemplo S.A.C.»')
        ->and($party->refresh()->telefono)->toBe('111');
});

test('las marcas tributarias se cambian solo por su propia vía', function () {
    $party = partyRegistrada(empresa());

    parties()->editarTributarios($party->id, ['es_agente_retencion' => true, 'es_buen_contribuyente' => true], 1);
    $party->refresh();

    expect($party->es_agente_retencion)->toBeTrue()
        ->and($party->es_buen_contribuyente)->toBeTrue()
        ->and($party->es_agente_percepcion)->toBeFalse()
        ->and($party->version)->toBe(2)
        ->and(fn () => parties()->editarTributarios($party->id, ['razon_social' => 'X'], 2))
        ->toThrow(PartyRechazada::class, '«razon_social» no se cambia');
});

// ——— Estado y borrado ———

test('desactivar y activar se alternan; repetir el mismo estado se rechaza', function () {
    $party = partyRegistrada(empresa());

    parties()->desactivar($party->id, 1);
    expect(fn () => parties()->desactivar($party->id, 2))->toThrow(PartyRechazada::class, 'Ya está desactivada.');

    parties()->activar($party->id, 2);
    expect($party->refresh()->es_activo)->toBeTrue()
        ->and($party->version)->toBe(3);
});

test('la ficha de Techquk no se desactiva ni se borra; elegida otra, la anterior sí (plan 1.15, D3)', function () {
    $techquk = partyRegistrada(empresa());
    $otra = partyRegistrada(empresa('20000000001', 'Otra Ejemplo S.A.C.'));
    app(ParametrosService::class)->cambiarDesdeTexto(EmpresaService::PARAMETRO, $techquk->numero_documento, 1);

    expect(fn () => parties()->desactivar($techquk->id, 1))->toThrow(PartyRechazada::class, 'Es la ficha de Techquk: elige otra en Configuración inicial antes de desactivarla.')
        ->and(fn () => parties()->borrar($techquk->id, 1))->toThrow(PartyRechazada::class, 'antes de borrarla.')
        ->and($techquk->refresh()->es_activo)->toBeTrue();

    app(ParametrosService::class)->cambiarDesdeTexto(EmpresaService::PARAMETRO, $otra->numero_documento, 2);
    parties()->desactivar($techquk->id, 1);

    expect($techquk->refresh()->es_activo)->toBeFalse()
        ->and(app(EmpresaService::class)->ficha()?->id)->toBe($otra->id);
});

test('una party que nunca se usó se borra con sus roles, y la bitácora lo registra', function () {
    $party = partyRegistrada(empresa());
    parties()->asignarRol($party->id, 'cliente', fechaRol('2026-01-01'));

    parties()->borrar($party->id, 1);

    expect(Party::query()->count())->toBe(0)
        ->and(PartyRol::query()->count())->toBe(0)
        ->and(AuditoriaCambio::query()->where('auditable_type', $party->getMorphClass())->where('auditable_id', $party->id)->latest('id')->value('evento'))
        ->toBe(EventoAuditoria::Eliminado);
});

test('una party ya usada no se borra: ni ella ni sus roles, y se sugiere desactivarla', function () {
    // Tabla de prueba que la usa, como lo hará `cuentas` del CRM.
    Schema::create('usos_de_party_prueba', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('party_id')->index()->constrained('parties');
    });
    $party = partyRegistrada(empresa());
    parties()->asignarRol($party->id, 'cliente', fechaRol('2026-01-01'));
    DB::table('usos_de_party_prueba')->insert(['party_id' => $party->id]);

    expect(fn () => parties()->borrar($party->id, 1))
        ->toThrow(PartyRechazada::class, 'Ya se usó en otros registros y no se puede borrar. Desactívala')
        ->and(Party::query()->count())->toBe(1)
        ->and(PartyRol::query()->count())->toBe(1);
});

// ——— Roles ———

test('asignar un rol lo deja vigente desde su fecha', function () {
    $party = partyRegistrada(empresa());

    parties()->asignarRol($party->id, 'proveedor', fechaRol('2026-02-01'));

    expect(parties()->tieneRol($party->id, 'proveedor', fechaRol('2026-02-01')))->toBeTrue()
        ->and(parties()->tieneRol($party->id, 'proveedor', fechaRol('2026-01-31')))->toBeFalse()
        ->and(parties()->tieneRol($party->id, 'cliente', fechaRol('2026-02-01')))->toBeFalse();
});

test('el día «hasta» todavía tiene el rol; el día siguiente ya no', function () {
    $party = partyRegistrada(empresa());
    parties()->asignarRol($party->id, 'cliente', fechaRol('2026-01-01'));
    parties()->cerrarRol($party->id, 'cliente', fechaRol('2026-03-31'), 1);

    expect(parties()->tieneRol($party->id, 'cliente', fechaRol('2026-03-31')))->toBeTrue()
        ->and(parties()->tieneRol($party->id, 'cliente', fechaRol('2026-04-01')))->toBeFalse();
});

test('sin fecha, tieneRol mira el día de hoy en Lima', function () {
    $party = partyRegistrada(empresa());
    parties()->asignarRol($party->id, 'cliente', fechaRol('2026-01-01'));
    parties()->cerrarRol($party->id, 'cliente', fechaRol('2026-10-15'), 1);

    // 23:30 del 15 en Lima ya es 16 en UTC: debe seguir contando el 15.
    test()->travelTo(CarbonImmutable::parse('2026-10-15 23:30', 'America/Lima'));
    expect(parties()->tieneRol($party->id, 'cliente'))->toBeTrue();

    test()->travelTo(CarbonImmutable::parse('2026-10-16 00:30', 'America/Lima'));
    expect(parties()->tieneRol($party->id, 'cliente'))->toBeFalse();
});

test('cerrar y reabrir al día siguiente deja los dos periodos; reabrir el mismo día se rechaza', function () {
    $party = partyRegistrada(empresa());
    parties()->asignarRol($party->id, 'cliente', fechaRol('2026-01-01'));
    parties()->cerrarRol($party->id, 'cliente', fechaRol('2026-03-31'), 1);

    expect(fn () => parties()->asignarRol($party->id, 'cliente', fechaRol('2026-03-31')))
        ->toThrow(PartyRechazada::class, 'Ya tiene el rol Cliente en esas fechas.');

    parties()->asignarRol($party->id, 'cliente', fechaRol('2026-04-01'));

    expect(PartyRol::query()->where('party_id', $party->id)->orderBy('desde')->get()->map(fn (PartyRol $p): array => [$p->desde->toDateString(), $p->hasta?->toDateString()])->all())
        ->toBe([['2026-01-01', '2026-03-31'], ['2026-04-01', null]]);
});

test('asignar un rol abierto dos veces se rechaza con un mensaje claro', function () {
    $party = partyRegistrada(empresa());
    parties()->asignarRol($party->id, 'cliente', fechaRol('2026-01-01'));

    expect(fn () => parties()->asignarRol($party->id, 'cliente', fechaRol('2026-06-01')))
        ->toThrow(PartyRechazada::class, 'Ya tiene el rol Cliente en esas fechas.')
        ->and(PartyRol::query()->count())->toBe(1);
});

test('no se asigna un rol a una party desactivada ni un rol desactivado o inexistente', function () {
    $party = partyRegistrada(empresa());
    RolParty::query()->where('codigo', 'aliado')->update(['es_activo' => false]);

    expect(fn () => parties()->asignarRol($party->id, 'aliado', fechaRol('2026-01-01')))->toThrow(PartyRechazada::class, 'El rol «aliado» no existe o está desactivado.')
        ->and(fn () => parties()->asignarRol($party->id, 'socio', fechaRol('2026-01-01')))->toThrow(PartyRechazada::class, 'El rol «socio» no existe');

    parties()->desactivar($party->id, 1);

    expect(fn () => parties()->asignarRol($party->id, 'cliente', fechaRol('2026-01-01')))->toThrow(PartyRechazada::class, 'Está desactivada');
});

test('cerrar exige un periodo abierto, una fecha no anterior al inicio y la versión leída', function () {
    $party = partyRegistrada(empresa());

    expect(fn () => parties()->cerrarRol($party->id, 'cliente', fechaRol('2026-03-31'), 1))->toThrow(PartyRechazada::class, 'No tiene el rol Cliente abierto.');

    parties()->asignarRol($party->id, 'cliente', fechaRol('2026-02-01'));

    expect(fn () => parties()->cerrarRol($party->id, 'cliente', fechaRol('2026-01-31'), 1))
        ->toThrow(PartyRechazada::class, 'no puede ser anterior al inicio del periodo (01/02/2026)')
        ->and(fn () => parties()->cerrarRol($party->id, 'cliente', fechaRol('2026-03-31'), 2))
        ->toThrow(VersionDesactualizada::class, 'Otra persona cambió el rol Cliente');

    parties()->cerrarRol($party->id, 'cliente', fechaRol('2026-02-01'), 1);

    expect(PartyRol::query()->value('hasta')?->toDateString())->toBe('2026-02-01');
});
