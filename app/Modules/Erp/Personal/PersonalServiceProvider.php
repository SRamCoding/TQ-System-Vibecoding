<?php

declare(strict_types=1);

namespace App\Modules\Erp\Personal;

use App\Modules\Erp\Personal\Filament\Pages\Asignacion;
use App\Modules\Erp\Personal\Filament\Pages\TareoSemanal;
use App\Modules\Erp\Personal\Models\TareoLinea;
use App\Modules\Erp\Personal\Services\PersonalService;
use App\Modules\Nucleo\Navegacion\AreaModulo;
use App\Modules\Nucleo\Navegacion\CajaModulo;
use App\Modules\Nucleo\Navegacion\RegistroModulos;
use App\Modules\Nucleo\Navegacion\RegistroPantallas;
use App\Modules\Nucleo\Seguridad\RegistroPermisos;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

/** MVP rápido (ficha t4-personal-tareo): personal, asignación y tareo (flujo 3). */
final class PersonalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->make(RegistroPantallas::class)->registrar('app', paginas: [Asignacion::class, TareoSemanal::class]);
    }

    public function boot(): void
    {
        Relation::morphMap(['tareo_linea' => TareoLinea::class, 'colaborador' => Models\Colaborador::class, 'tareo' => Models\Tareo::class]);
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $p = $this->app->make(RegistroPermisos::class);
        $p->declarar(PersonalService::PERMISO_VER, 'Ver personal, asignación y tareo');
        $p->declarar(PersonalService::PERMISO_TAREAR, 'Asignar personas y registrar horas');
        $p->declarar(PersonalService::PERMISO_CERRAR, 'Cerrar la semana de tareo');
        $m = $this->app->make(RegistroModulos::class);
        $m->registrar(new CajaModulo('asignacion', 'Personal', '/asignacion', permiso: PersonalService::PERMISO_VER, area: AreaModulo::Erp, icono: 'heroicon-o-user-group',
            descripcion: 'Quién está en qué proyecto y con qué dedicación', preguntas: ['¿Quién está sobreasignado?', '¿A quién se le vence el SCTR?']));
        $m->registrar(new CajaModulo('tareo', 'Tareo', '/tareo', permiso: PersonalService::PERMISO_VER, area: AreaModulo::Erp, icono: 'heroicon-o-clock',
            descripcion: 'Las horas de la gente por día y partida, que al cerrar la semana son costo', preguntas: ['¿Cuántas horas se trabajaron esta semana?', '¿Cuánto costó la mano de obra?']));
    }
}
