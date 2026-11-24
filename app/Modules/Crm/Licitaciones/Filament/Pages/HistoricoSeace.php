<?php

declare(strict_types=1);

namespace App\Modules\Crm\Licitaciones\Filament\Pages;

use App\Modules\Crm\Licitaciones\Services\HistoricoSeaceService;
use App\Modules\Crm\Licitaciones\Services\LicitacionesService;
use App\Modules\Nucleo\Filament\Clusters\Configuracion;
use App\Modules\Nucleo\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Observación 8.12 (MV-092): Configuración → Histórico del SEACE. El ensayo (lo que costaría la carga), el avance de la
 * carga de un año («Importando histórico 2025 · 62 %») con pausar y reanudar, y la verificación al final. La carga
 * corre en segundo plano como comando, nunca dentro de esta pantalla.
 */
final class HistoricoSeace extends Page
{
    protected static ?string $cluster = Configuracion::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-archive-box-arrow-down';

    protected static ?string $navigationLabel = 'Histórico del SEACE';

    protected static ?string $title = 'Histórico del SEACE';

    protected static ?string $slug = 'historico-seace';

    protected static ?int $navigationSort = 17;

    public int $anio = 2025;

    public ?array $verificacion = null;

    public static function canAccess(): bool
    {
        $u = Filament::auth()->user();

        return $u instanceof User && $u->can(LicitacionesService::PERMISO_GESTIONAR);
    }

    public function pausar(): void
    {
        app(HistoricoSeaceService::class)->pausar($this->anio);
        Notification::make()->success()->title('Pausa pedida: se detiene al terminar el lote en curso.')->send();
    }

    /** Reanuda (o empieza) la carga en segundo plano: un comando aparte que sigue desde donde quedó. Atajo del MVP. */
    public function reanudar(): void
    {
        if (app(HistoricoSeaceService::class)->avance($this->anio)['estado'] === 'en_curso') {
            return;
        }

        DB::table('seace_cargas')->where('anio', $this->anio)->where('es_ensayo', false)->whereIn('estado', ['pausada', 'error'])->update(['estado' => 'pendiente']);
        exec(sprintf('cd %s && nohup %s artisan seace:historico %d > %s 2>&1 &', escapeshellarg(base_path()), escapeshellarg(PHP_BINARY), $this->anio, escapeshellarg(storage_path('logs/historico-seace.log'))));
        Notification::make()->success()->title("Importando el histórico {$this->anio} en segundo plano.")->send();
    }

    public function verificar(): void
    {
        $this->verificacion = app(HistoricoSeaceService::class)->verificar($this->anio);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('licitaciones::historico.pantalla')->viewData(fn (): array => [
                'avance' => app(HistoricoSeaceService::class)->avance($this->anio),
                'regiones' => app(HistoricoSeaceService::class)->regiones(),
            ]),
        ]);
    }
}
