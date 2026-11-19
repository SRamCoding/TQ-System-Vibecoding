<?php

declare(strict_types=1);

use App\Modules\Crm\Cuentas\CuentasServiceProvider;
use App\Modules\Crm\Cuentas\Enums\MotivoNoUsable;
use App\Modules\Crm\Cuentas\Models\Cuenta;
use App\Modules\Crm\Cuentas\Services\CuentaRechazada;
use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Cuentas\Services\ResumenCuenta;
use App\Modules\Nucleo\database\seeders\EstadosSeeder;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\NivelGobierno;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\RolParty;
use App\Modules\Nucleo\Models\TipoMotivo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\PartiesService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/*
 * Lo que Cuentas ofrece para leer (plan 2.20, D3, D9 y D12): buscar con incluirId, resumen a una fecha y deParty.
 */

function cuentaDe(string $razonSocial, string $ruc, array $cuenta = [], array $party = []): Cuenta
{
    $ficha = Party::factory()->juridica()->create(['razon_social' => $razonSocial, 'numero_documento' => $ruc, ...$party]);

    return Cuenta::factory()->create(['party_id' => $ficha->id, ...$cuenta]);
}

function motivoDeDesactivar(): int
{
    test()->seed(EstadosSeeder::class);
    $familia = TipoMotivo::query()->where('codigo', CuentasServiceProvider::FAMILIA_DESACTIVAR)->firstOrFail();

    return Motivo::query()->where('tipo_motivo_id', $familia->id)->where('es_otro', false)->value('id');
}

/** @param  list<ResumenCuenta>  $resumenes */
function idsDe(array $resumenes): array
{
    return array_map(fn (ResumenCuenta $r): int => $r->id, $resumenes);
}

beforeEach(function () {
    RolParty::factory()->create(['codigo' => CuentasService::ROL_ENTIDAD, 'nombre' => 'Entidad pública']);
    $this->servicio = app(CuentasService::class);
});

test('buscar encuentra por razón social sin tildes ni mayúsculas, o por el comienzo del RUC, el RUC exacto primero', function () {
    $andes = cuentaDe('Constructora Los Andes S.A.C.', '20100000017');
    $peru = cuentaDe('Andes Perú Ingeniería S.A.', '20600000011');
    $otra = cuentaDe('Molinos del Norte S.A.', '20100000025');

    expect(idsDe($this->servicio->buscar('ANDES PERU')))->toBe([$peru->id])
        ->and(idsDe($this->servicio->buscar('andes')))->toBe([$peru->id, $andes->id])
        ->and(idsDe($this->servicio->buscar('201000')))->toBe([$andes->id, $otra->id])
        ->and(idsDe($this->servicio->buscar('20100000025')))->toBe([$otra->id])
        ->and($this->servicio->buscar('a'))->toBe([])
        ->and($this->servicio->buscar('andes', limite: 1))->toHaveCount(1);
});

test('buscar solo ofrece cuentas usables: ni la inactiva ni la de ficha inactiva', function () {
    $usable = cuentaDe('Andes Uno S.A.C.', '20100000033');
    cuentaDe('Andes Dos S.A.C.', '20100000041', party: ['es_activo' => false]);
    $inactiva = cuentaDe('Andes Tres S.A.C.', '20100000050');
    Cuenta::query()->whereKey($inactiva->id)->update(['es_activa' => false, 'motivo_inactiva_id' => motivoDeDesactivar()]);

    expect(idsDe($this->servicio->buscar('andes')))->toBe([$usable->id]);
});

test('con incluirId, la cuenta del registro va siempre, marcada si no es usable, y una sola vez', function () {
    $usable = cuentaDe('Andes Uno S.A.C.', '20100000033');
    $deFichaInactiva = cuentaDe('Molinos Dos S.A.C.', '20100000041', party: ['es_activo' => false]);

    $conInactiva = $this->servicio->buscar('andes', incluirId: $deFichaInactiva->id);
    $sinTexto = $this->servicio->buscar('', incluirId: $usable->id);

    expect(idsDe($conInactiva))->toBe([$usable->id, $deFichaInactiva->id])
        ->and($conInactiva[1]->usable)->toBeFalse()
        ->and($conInactiva[1]->etiquetaParaSelector())->toBe('Molinos Dos S.A.C. · 20100000041 (Ficha inactiva)')
        ->and(idsDe($this->servicio->buscar('andes', incluirId: $usable->id)))->toBe([$usable->id])
        ->and(idsDe($sinTexto))->toBe([$usable->id])
        ->and($sinTexto[0]->etiquetaParaSelector())->toBe('Andes Uno S.A.C. · 20100000033');
});

test('el resumen dice ejecutivo, nivel, cliente desde y si es pública a la fecha pedida', function () {
    $ejecutivo = User::factory()->create(['name' => 'Rosa Quispe Prueba']);
    $nivel = NivelGobierno::factory()->create(['nombre' => 'Gobierno local']);
    $cuenta = cuentaDe('Municipalidad de Prueba', '20100000068', ['ejecutivo_user_id' => $ejecutivo->id, 'nivel_gobierno_id' => $nivel->id, 'cliente_desde' => '2026-02-10']);
    app(PartiesService::class)->asignarRol($cuenta->party_id, CuentasService::ROL_ENTIDAD, CarbonImmutable::parse('2026-03-01'));

    $antes = $this->servicio->resumen($cuenta->id, CarbonImmutable::parse('2026-02-28'));
    $despues = $this->servicio->resumen($cuenta->id, CarbonImmutable::parse('2026-03-01'));

    expect($antes->esPublica)->toBeFalse()
        ->and($despues->esPublica)->toBeTrue()
        ->and($despues->etiqueta)->toBe('Municipalidad de Prueba · 20100000068')
        ->and($despues->partyId)->toBe($cuenta->party_id)
        ->and($despues->ejecutivoNombre)->toBe('Rosa Quispe Prueba')
        ->and($despues->nivelGobiernoNombre)->toBe($nivel->nombre)
        ->and($despues->clienteDesde?->toDateString())->toBe('2026-02-10')
        ->and($despues->esCliente())->toBeTrue()
        ->and($despues->usable)->toBeTrue()
        ->and($despues->motivoNoUsable)->toBeNull();
});

test('deParty da la cuenta de la ficha aunque no sea usable, con su motivo; nula si no tiene', function () {
    $sinCuenta = Party::factory()->juridica()->create();
    $inactiva = cuentaDe('Andes Tres S.A.C.', '20100000050', party: ['es_activo' => false]);
    Cuenta::query()->whereKey($inactiva->id)->update(['es_activa' => false, 'motivo_inactiva_id' => motivoDeDesactivar()]);
    $deFichaInactiva = cuentaDe('Molinos Dos S.A.C.', '20100000041', party: ['es_activo' => false]);

    expect($this->servicio->deParty($sinCuenta->id))->toBeNull()
        ->and($this->servicio->deParty($inactiva->party_id)?->motivoNoUsable)->toBe(MotivoNoUsable::CuentaInactiva)
        ->and($this->servicio->deParty($deFichaInactiva->party_id)?->motivoNoUsable)->toBe(MotivoNoUsable::FichaInactiva)
        ->and($this->servicio->deParty($deFichaInactiva->party_id)?->usable)->toBeFalse();
});

test('el resumen trae el origen de la cuenta, el de fábrica de sus oportunidades (plan 2.21, D7)', function () {
    $cuenta = cuentaDe('Constructora Origen S.A.C.', '20600000201');

    expect($this->servicio->resumen($cuenta->id)->origenComercialId)->toBe($cuenta->origen_comercial_id);
});

test('exigirUsable bloquea la cuenta dentro de la transacción de quien la usa y falla si no es usable (plan 2.21, D6)', function () {
    $activa = cuentaDe('Constructora Activa S.A.C.', '20600000202');
    $inactiva = cuentaDe('Constructora Dormida S.A.C.', '20600000203', ['es_activa' => false, 'motivo_inactiva_id' => motivoDeDesactivar()]);
    $sinFicha = cuentaDe('Constructora Sin Ficha S.A.C.', '20600000204', party: ['es_activo' => false]);

    $compartido = DB::transaction(fn (): ResumenCuenta => $this->servicio->exigirUsable($activa->id));
    $exclusivo = DB::transaction(fn (): ResumenCuenta => $this->servicio->exigirUsable($activa->id, exclusivo: true));

    expect($compartido->id)->toBe($activa->id)
        ->and($exclusivo->usable)->toBeTrue()
        ->and(fn () => DB::transaction(fn () => $this->servicio->exigirUsable($inactiva->id)))->toThrow(CuentaRechazada::class, MotivoNoUsable::CuentaInactiva->mensaje())
        ->and(fn () => DB::transaction(fn () => $this->servicio->exigirUsable($sinFicha->id, exclusivo: true)))->toThrow(CuentaRechazada::class, MotivoNoUsable::FichaInactiva->mensaje())
        ->and(fn () => DB::transaction(fn () => $this->servicio->exigirUsable(999999)))->toThrow(CuentaRechazada::class);
});
