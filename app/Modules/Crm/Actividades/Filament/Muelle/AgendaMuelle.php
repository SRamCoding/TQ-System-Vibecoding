<?php

declare(strict_types=1);

namespace App\Modules\Crm\Actividades\Filament\Muelle;

use App\Modules\Crm\Actividades\Models\Actividad;
use App\Modules\Crm\Actividades\Services\ActividadesService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use RuntimeException;

/**
 * MV-025: la agenda como herramienta del muelle. Dos recuadros pegados en el centro: el calendario (el año, y solo se
 * acerca al mes actual; el botón «Año» se aleja) y, al lado, las actividades del día elegido, donde se marca una como
 * hecha o se registra una nueva (MV-026). Solo las actividades de la persona.
 */
final class AgendaMuelle extends Component
{
    public int $anio;

    public int $mes;

    public ?string $dia = null;

    /** @var array{tipo: string, cuenta_id: ?string, oportunidad_id: ?string, asunto: string, hora: string} */
    public array $nueva = ['tipo' => 'llamada', 'cuenta_id' => null, 'oportunidad_id' => null, 'asunto' => '', 'hora' => '10:00'];

    public bool $registrando = false;

    /** Observación 8.8: la actividad que se está marcando como hecha (pide «Qué pasó»). */
    public ?int $cumpliendo = null;

    public string $quePaso = '';

    public function mount(): void
    {
        $hoy = CarbonImmutable::parse(Calendario::hoy());
        $this->anio = $hoy->year;
        $this->mes = $hoy->month;
        $this->dia = $hoy->toDateString();
    }

    public function irAMes(int $mes): void
    {
        $this->mes = max(1, min(12, $mes));
    }

    public function moverMes(int $paso): void
    {
        $f = CarbonImmutable::create($this->anio, $this->mes, 1)->addMonths($paso);
        $this->anio = $f->year;
        $this->mes = $f->month;
    }

    public function moverAnio(int $paso): void
    {
        $this->anio += $paso;
    }

    public function elegirDia(string $dia): void
    {
        $this->dia = CarbonImmutable::parse($dia)->toDateString();
        $this->registrando = false;
    }

    public function marcarHecha(int $id): void
    {
        $this->hacer(fn () => app(ActividadesService::class)->cumplir($id, trim($this->quePaso) === '' ? null : trim($this->quePaso)), 'Marcada como hecha.');
        $this->cumpliendo = null;
        $this->quePaso = '';
    }

    public function irAHoy(): void
    {
        $this->mount();
    }

    public function registrarEnDia(): void
    {
        $this->registrando = true;
    }

    public function registrar(): void
    {
        $this->hacer(function (): void {
            if (blank($this->nueva['cuenta_id'])) {
                throw new RuntimeException('Elige el cliente.');
            }

            $fecha = CarbonImmutable::parse($this->dia.' '.$this->nueva['hora'], 'America/Lima');
            app(ActividadesService::class)->registrar($this->nueva['tipo'], (int) $this->nueva['cuenta_id'], blank($this->nueva['oportunidad_id']) ? null : (int) $this->nueva['oportunidad_id'],
                $this->nueva['asunto'], $fecha, null, null, null, $this->usuario());
            $this->registrando = false;
            $this->nueva = ['tipo' => 'llamada', 'cuenta_id' => null, 'oportunidad_id' => null, 'asunto' => '', 'hora' => '10:00'];
        }, 'Actividad registrada.');
    }

    private function hacer(\Closure $paso, string $exito): void
    {
        try {
            $paso();
            Notification::make()->success()->title($exito)->send();
        } catch (RuntimeException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
        }
    }

    private function usuario(): User
    {
        $u = auth()->user();

        return $u instanceof User ? $u : throw new RuntimeException('Sin sesión.');
    }

    public function render(): View
    {
        $servicio = app(ActividadesService::class);
        $usuario = $this->usuario();

        return view('actividades::muelle.agenda', [
            'dias' => $servicio->diasDelAnio($usuario->id, $this->anio),
            'hoy' => CarbonImmutable::parse(Calendario::hoy()),
            'actividades' => $this->dia === null ? [] : $servicio->delDia($usuario->id, $this->dia),
            'delMes' => $servicio->delMes($usuario->id, $this->anio, $this->mes),
            'vencidasAntes' => $this->dia === null ? 0 : $servicio->vencidasAntesDe($usuario->id, $this->dia),
            'puedeRegistrar' => $usuario->can(ActividadesService::PERMISO_REGISTRAR),
            'tipos' => Actividad::TIPOS,
            // Atajo del MVP: las listas del formulario se leen aquí.
            'cuentas' => $this->registrando ? DB::table('cuentas')->join('parties', 'parties.id', '=', 'cuentas.party_id')->orderBy('parties.razon_social')->limit(300)->pluck('parties.razon_social', 'cuentas.id')->all() : [],
            'oportunidades' => $this->registrando && filled($this->nueva['cuenta_id'])
                ? DB::table('oportunidades')->where('cuenta_id', (int) $this->nueva['cuenta_id'])->orderByDesc('id')->limit(50)->selectRaw("id, numero || ' · ' || nombre AS t")->pluck('t', 'id')->all() : [],
        ]);
    }
}
