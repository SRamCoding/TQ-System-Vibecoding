<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\SeguridadService;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class DatabaseSeeder extends Seeder
{
    /**
     * Datos maestros del núcleo en todo ambiente; además, solo en desarrollo, un usuario para iniciar sesión con su
     * persona (DNI inventado). Nombre y correo salen de config('nucleo.usuario_desarrollo'): DEV_USER_* del .env.
     * Sin WithoutModelEvents: el trait de auditoría pone al usuario de sistema como autor.
     */
    public function run(): void
    {
        $this->call(NucleoSeeder::class);

        if (! app()->environment('local')) {
            return;
        }

        // Para ver todas las pantallas en local: por roles, nunca permisos directos (plan 1.4, D7 y D8). `desarrollo`
        // tiene todos los permisos y solo existe aquí; `administrador` es el de verdad.
        $desarrollo = Role::findOrCreate('desarrollo', 'web');
        $desarrollo->syncPermissions(Permission::all());
        $dev = $this->usuarioDeDesarrollo();
        $dev->syncPermissions([]);
        $dev->syncRoles([SeguridadService::ROL_ADMINISTRADOR, $desarrollo->name]);
        $this->gerenteDeDesarrollo();

        // MVP rápido (ficha t0-datos-demo): la cartera de demostración, solo en local.
        $this->call(Demo\DemoSeeder::class);
        // MV-028: las herramientas de la demo.
        $this->call(Demo\HerramientasSeeder::class);
        $this->call(Demo\TicketsAyudaSeeder::class);
        $this->call(Demo\OportunidadesVencidasSeeder::class);
        $this->call(Demo\FotosPersonalSeeder::class);
        $this->call(Demo\TareoCasosSeeder::class);
        $this->call(Demo\ContratosCasosSeeder::class);
        $this->call(Demo\SubcontratosCasosSeeder::class);
        $this->call(Demo\PostulacionCasosSeeder::class);
        $this->call(Demo\CampaniasCasosSeeder::class);
        $this->call(Demo\CanalesCasosSeeder::class);
        $this->call(Demo\ContactosRegistradosSeeder::class);
        $this->call(Demo\MejorasOportunidadesSeeder::class);
        $this->call(Demo\BusquedaSeaceSeeder::class);
        $this->call(Demo\GanadoresCasosSeeder::class);
    }

    /**
     * Sin gerente general activo el panel no arranca (plan 1.10, D2): en local, uno inventado, que no es el usuario de
     * desarrollo (el administrador y el gerente son personas distintas). Si ya hay uno activo, no se toca.
     */
    private function gerenteDeDesarrollo(): void
    {
        if (app(SeguridadService::class)->hayGerenteGeneralActivo()) {
            return;
        }

        $persona = Party::query()->where('numero_documento', '00000001')->first() ?? Party::factory()->create([
            'numero_documento' => '00000001',
            'nombres' => 'Gerente',
            'apellidos' => 'de Desarrollo',
            'razon_social' => 'de Desarrollo Gerente',
        ]);
        $gerente = User::query()->where('party_id', $persona->id)->first()
            ?? User::factory()->create(['party_id' => $persona->id, 'name' => $persona->razon_social, 'email' => 'gerencia.dev@techquk.test']);

        $gerente->forceFill(['es_activo' => true])->save();
        $gerente->assignRole(SeguridadService::ROL_GERENCIA_GENERAL);
    }

    private function usuarioDeDesarrollo(): User
    {
        /** @var array{nombres: string, apellidos: string, email: string, dni: string} $datos */
        $datos = config('nucleo.usuario_desarrollo');
        $email = mb_strtolower($datos['email']);

        $existente = User::query()->whereRaw('lower(email) = ?', [$email])->first();

        if ($existente !== null) {
            return $existente;
        }

        $persona = Party::query()->where('numero_documento', $datos['dni'])->first();

        // Una persona, una cuenta: si cambió el correo del .env sin reconstruir la base, se queda la cuenta que había.
        if ($persona !== null) {
            return User::query()->where('party_id', $persona->id)->first()
                ?? User::factory()->create(['party_id' => $persona->id, 'name' => $persona->razon_social, 'email' => $email]);
        }

        $persona = Party::factory()->create([
            'numero_documento' => $datos['dni'],
            'nombres' => $datos['nombres'],
            'apellidos' => $datos['apellidos'],
            'razon_social' => "{$datos['apellidos']} {$datos['nombres']}",
        ]);

        return User::factory()->create(['party_id' => $persona->id, 'name' => $persona->razon_social, 'email' => $email]);
    }
}
