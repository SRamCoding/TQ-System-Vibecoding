<?php

declare(strict_types=1);

use App\Modules\Crm\Oportunidades\Estados\DefinicionOportunidad;
use App\Modules\Erp\Proyectos\Estados\DefinicionProyecto;
use App\Modules\Nucleo\database\seeders\EstadosSeeder;
use App\Modules\Nucleo\Enums\TonoEstado;
use App\Modules\Nucleo\Models\Estado;
use Illuminate\Database\Eloquent\Relations\Relation;

/*
 * Plan 1.4ab, D4 (desarrollador, 2026-11-15, «solo lo del color según si es ERP o CRM»): el estado «en curso» toma el
 * color del área de su documento; los demás tonos no cambian.
 */

test('«en curso» es turquesa en el ERP y en el núcleo, y morado en el CRM', function () {
    expect(TonoEstado::EnCurso->colorDeDocumento('erp.proyectos.proyecto'))->toBe('info')
        ->and(TonoEstado::EnCurso->colorDeDocumento('crm.oportunidades.oportunidad'))->toBe('morado')
        ->and(TonoEstado::EnCurso->colorDeDocumento('compras.orden'))->toBe('info')
        ->and(TonoEstado::EnCurso->colorFilament())->toBe('info');
});

test('los demás tonos no dependen del área', function (TonoEstado $tono) {
    expect($tono->colorDeDocumento('crm.oportunidades.oportunidad'))->toBe($tono->colorFilament())
        ->and($tono->colorDeDocumento('erp.proyectos.proyecto'))->toBe($tono->colorFilament());
})->with(array_values(array_filter(TonoEstado::cases(), fn (TonoEstado $t): bool => $t !== TonoEstado::EnCurso)));

test('cada estado guardado se pinta con el área de su documento', function () {
    $this->seed(EstadosSeeder::class);

    $estado = fn (string $modelo, string $codigo): Estado => Estado::query()
        ->where('documento_type', Relation::getMorphAlias($modelo))->where('codigo', $codigo)->firstOrFail();

    expect($estado((new DefinicionOportunidad)->modelo(), 'calificada')->colorEnPantalla())->toBe('morado')
        ->and($estado((new DefinicionProyecto)->modelo(), DefinicionProyecto::EN_EJECUCION)->colorEnPantalla())->toBe('info');
});
