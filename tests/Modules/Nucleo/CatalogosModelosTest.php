<?php

declare(strict_types=1);

use App\Modules\Nucleo\Enums\EventoAuditoria;
use App\Modules\Nucleo\Enums\OrigenTipoCambio;
use App\Modules\Nucleo\Models\AuditoriaCambio;
use App\Modules\Nucleo\Models\Cubso;
use App\Modules\Nucleo\Models\FormaPago;
use App\Modules\Nucleo\Models\LineaSolucion;
use App\Modules\Nucleo\Models\Moneda;
use App\Modules\Nucleo\Models\TipoCambio;
use App\Modules\Nucleo\Models\TipoDocumento;
use App\Modules\Nucleo\Models\TipoPenalidad;
use App\Modules\Nucleo\Models\TipoTrabajo;
use App\Modules\Nucleo\Models\Ubigeo;
use App\Modules\Nucleo\Models\UnidadMedida;
use App\Modules\Nucleo\Services\UsuarioSistema;
use Illuminate\Database\Eloquent\Model;

test('cada catálogo se crea desde su factory con su autor', function (string $clase) {
    /** @var class-string<Model> $clase */
    $modelo = $clase::factory()->create();

    expect($modelo->created_by)->toBe(app(UsuarioSistema::class)->id());
})->with([
    Moneda::class, TipoCambio::class, UnidadMedida::class, Ubigeo::class, TipoDocumento::class,
    FormaPago::class, LineaSolucion::class, Cubso::class, TipoPenalidad::class, TipoTrabajo::class,
]);

test('el tipo de cambio guarda compra y venta como texto exacto y deja bitácora al corregirse', function () {
    $tc = TipoCambio::factory()->create(['compra' => '3.7500', 'venta' => '3.7600', 'origen' => OrigenTipoCambio::Manual]);

    $tc->update(['venta' => '3.9500']);

    $ultimo = AuditoriaCambio::query()
        ->where('auditable_type', $tc->getMorphClass())
        ->where('auditable_id', $tc->id)
        ->latest('id')
        ->firstOrFail();

    expect($tc->refresh()->venta)->toBe('3.9500')
        ->and($tc->origen)->toBe(OrigenTipoCambio::Manual)
        ->and($ultimo->evento)->toBe(EventoAuditoria::Actualizado)
        ->and($ultimo->valores_anteriores)->toBe(['venta' => '3.7600'])
        ->and($ultimo->valores_nuevos)->toBe(['venta' => '3.9500']);
});

test('cubso se relaciona con su unidad de medida', function () {
    $unidad = UnidadMedida::factory()->create();
    $cubso = Cubso::factory()->create(['unidad_medida_id' => $unidad->id]);

    expect($cubso->unidadMedida?->is($unidad))->toBeTrue();
});

test('editar un elemento de catálogo deja bitácora sin anotar la versión', function () {
    $tipo = TipoDocumento::factory()->create(['signo' => 1]);

    $tipo->forceFill(['signo' => -1, 'version' => 2])->save();

    $ultimo = AuditoriaCambio::query()
        ->where('auditable_type', $tipo->getMorphClass())
        ->where('auditable_id', $tipo->id)
        ->latest('id')
        ->firstOrFail();

    expect($ultimo->evento)->toBe(EventoAuditoria::Actualizado)
        ->and($ultimo->valores_anteriores)->toBe(['signo' => 1])
        ->and($ultimo->valores_nuevos)->toBe(['signo' => -1]);
});
