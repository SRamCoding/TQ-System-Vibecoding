<?php

declare(strict_types=1);

namespace App\Modules\Erp\Personal\Filament\Pages;

use App\Modules\Erp\Compras\Filament\Compras;
use App\Modules\Erp\Personal\Services\PersonalService;
use App\Modules\Erp\Personal\Services\TareoService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Tiempo\Calendario;
use BackedEnum;
use Carbon\CarbonImmutable;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use RuntimeException;

/**
 * MVP rápido (t4-personal-tareo): el tareo semanal de un proyecto. Desde MV-069 y MV-070, una cuadrícula tipo hoja de
 * cálculo que se rellena con la jornada de la gente asignada, con la tira de semanas del mes, cuatro cifras, avisos en las
 * celdas, vista por persona o por partida, cierre con resumen y la asistencia del día para el celular.
 */
final class TareoSemanal extends Page
{
    protected static ?string $slug = 'tareo';

    protected static ?string $title = 'Tareo';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected string $view = 'personal::tareo.pantalla';

    #[Url]
    public ?int $proyecto = null;

    #[Url]
    public ?string $semana = null;

    #[Url]
    public string $agrupar = 'persona';

    /** semana | hoy; nula, la elige la pantalla (Hoy en el celular). */
    #[Url]
    public ?string $vista = null;

    public ?string $dia = null;

    public static function canAccess(): bool
    {
        $u = Filament::auth()->user();

        return $u instanceof User && $u->can(PersonalService::PERMISO_VER);
    }

    public function mount(): void
    {
        $this->proyecto ??= (int) DB::table('tareos')->orderByDesc('semana')->value('proyecto_id') ?: null;
        $this->semana = CarbonImmutable::parse($this->semana ?? Calendario::hoy())->startOfWeek()->toDateString();
        $this->abrirSemana();
    }

    public function getSubheading(): ?string
    {
        return null;
    }

    private function lunes(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->semana);
    }

    private function puedeTarear(): bool
    {
        return auth()->user()?->can(PersonalService::PERMISO_TAREAR) ?? false;
    }

    /** Una semana nueva se abre al llegar a ella y ya trae a la gente asignada (MV-069). */
    private function abrirSemana(): void
    {
        if ($this->proyecto !== null && $this->puedeTarear()) {
            app(PersonalService::class)->semana($this->proyecto, $this->lunes());
        }
    }

    /** @return array<string, mixed> */
    public function datos(): array
    {
        if ($this->proyecto === null) {
            return ['vacio' => true];
        }

        $d = app(TareoService::class)->tablero($this->proyecto, $this->lunes(), $this->agrupar);
        $p = DB::table('proyectos')->where('id', $this->proyecto)->first(['codigo', 'nombre']);
        $dia = $this->dia ?? ($d['hoy']->between($this->lunes(), $this->lunes()->addDays(6)) ? array_keys(TareoService::DIAS)[$d['hoy']->dayOfWeekIso - 1] : 'lu');

        return [...$d, 'vacio' => false, 'proyectoCodigo' => $p->codigo ?? '', 'proyectoNombre' => $p->nombre ?? '', 'puede' => $this->puedeTarear() && $d['abierta'], 'dia' => $dia,
            'partidas' => Compras::partidasDe($this->proyecto), 'personas' => DB::table('colaboradores')->where('es_activo', true)->orderBy('nombres')->pluck('nombres', 'id')->all()];
    }

    private function intentar(Closure $paso, ?string $listo = null): void
    {
        try {
            $paso();

            if ($listo !== null) {
                Notification::make()->success()->title($listo)->send();
            }
        } catch (RuntimeException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
        }
    }

    public function celda(int $linea, string $dia, string $horas): void
    {
        $this->intentar(fn () => app(TareoService::class)->celda($linea, $dia, $horas));
    }

    public function llenarFila(int $linea): void
    {
        $this->intentar(fn () => app(TareoService::class)->llenarFila($linea));
    }

    public function cambiarPartida(int $linea, string $partida): void
    {
        if ($partida !== '') {
            $this->intentar(fn () => app(TareoService::class)->cambiarPartida($linea, (int) $partida));
        }
    }

    public function quitarFila(int $linea): void
    {
        $this->intentar(fn () => app(TareoService::class)->quitarFila($linea));
    }

    public function agregarFila(string $persona, string $partida): void
    {
        if ($persona === '' || $partida === '') {
            Notification::make()->warning()->title('Elige la persona y la partida.')->send();

            return;
        }

        $this->intentar(fn () => app(TareoService::class)->nuevaFila($this->tareoId(), (int) $persona, (int) $partida));
    }

    public function asistencia(int $persona, bool $vino): void
    {
        $this->intentar(fn () => app(TareoService::class)->asistencia($this->tareoId(), $persona, (string) $this->datos()['dia'], $vino));
    }

    public function irSemana(string $lunes): void
    {
        $this->semana = CarbonImmutable::parse($lunes)->startOfWeek()->toDateString();
        $this->abrirSemana();
    }

    public function moverSemana(int $cuantas): void
    {
        $this->irSemana($this->lunes()->addWeeks($cuantas)->toDateString());
    }

    public function poner(string $campo, string $valor): void
    {
        match ($campo) {
            'agrupar' => $this->agrupar = $valor === 'partida' ? 'partida' : 'persona',
            'vista' => $this->vista = $valor === 'hoy' ? 'hoy' : 'semana',
            'dia' => $this->dia = isset(TareoService::DIAS[$valor]) ? $valor : null,
            default => null,
        };
    }

    private function tareoId(): int
    {
        return app(PersonalService::class)->semana((int) $this->proyecto, $this->lunes())->id;
    }

    /** El proyecto como chip: clic para cambiarlo, con buscador. */
    public function proyectoAction(): Action
    {
        return Action::make('proyecto')->modalHeading('Proyecto')->modalWidth('md')->modalSubmitActionLabel('Ver su tareo')
            ->fillForm(fn (): array => ['proyecto' => $this->proyecto])
            ->schema([Select::make('proyecto')->label('Proyecto')->required()->searchable()->options(fn (): array => DB::table('proyectos as p')->join('estados as e', 'e.id', '=', 'p.estado_id')
                ->where(fn ($q) => $q->whereIn('e.codigo', ['en_preparacion', 'en_ejecucion'])->orWhere('p.id', $this->proyecto))
                ->orderBy('p.codigo')->selectRaw("p.id, p.codigo || ' · ' || p.nombre AS t")->pluck('t', 'p.id')->all())])
            ->action(function (array $data): void {
                $this->proyecto = (int) $data['proyecto'];
                $this->abrirSemana();
            });
    }

    protected function getHeaderActions(): array
    {
        $abierta = fn (): bool => $this->proyecto !== null && $this->puedeTarear() && DB::table('tareos')->where('proyecto_id', $this->proyecto)->where('semana', $this->semana)->value('estado') === 'abierto';

        return [
            Action::make('copiar')->label('Copiar la semana anterior')->icon('heroicon-o-document-duplicate')->color('gray')->visible($abierta)
                ->action(fn () => $this->intentar(fn () => app(TareoService::class)->copiarAnterior($this->tareoId()), 'Semana anterior copiada.')),
            Action::make('rellenar')->label('Rellenar con la jornada')->icon('heroicon-o-sparkles')->color('gray')->visible($abierta)
                ->action(fn () => $this->intentar(fn () => app(TareoService::class)->rellenar($this->tareoId()), 'Listo: las líneas que nadie tocó vienen de la jornada.')),
            Action::make('cerrar')->label('Cerrar semana')->icon('heroicon-o-lock-closed')->color('success')
                ->visible(fn (): bool => $abierta() && (auth()->user()?->can(PersonalService::PERMISO_CERRAR) ?? false))
                ->slideOver()->modalWidth('md')->modalHeading('Cerrar la semana')->modalSubmitActionLabel('Cerrar semana')
                ->modalContent(fn () => view('personal::tareo.cierre', ['r' => app(TareoService::class)->resumenCierre((int) $this->proyecto, $this->lunes())]))
                ->action(fn () => $this->intentar(fn () => app(PersonalService::class)->cerrar($this->tareoId(), auth()->user()), 'Semana cerrada: las horas son costo del proyecto.')),
        ];
    }
}
