<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Filament\Desarrollo;

use App\Modules\Erp\Presupuestos\Imputacion\DestinosDePresupuestos;
use App\Modules\Erp\Presupuestos\Services\EnMemoria\PartidasEnMemoria;
use App\Modules\Erp\Proyectos\Services\EnMemoria\ProyectosEnMemoria;
use App\Modules\Nucleo\Desarrollo\ModoDesarrollo;
use App\Modules\Nucleo\Filament\Imputacion\SelectorImputacion;
use App\Modules\Nucleo\Models\User;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * «Probar el selector de imputación» (plan 2.2, D8): el componente real con datos de prueba inventados (regla 10), para
 * verlo antes de que exista un documento con costo. **Solo en el modo desarrollo** (plan 1.4i): sin él responde 404.
 *
 * Arma su propia `FuenteDeDestinos` real sobre las versiones en memoria de C1 y C2, solo para esta página: no las
 * registra en el contenedor, así que el resto de la app sigue con las reales (D8, condición 2). No guarda nada.
 *
 * @property-read Schema $form
 */
final class ProbarSelectorImputacion extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrows-pointing-in';

    protected static ?string $navigationLabel = 'Probar el selector de imputación';

    /** No va en ningún menú: es del modo desarrollo. */
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Probar el selector de imputación';

    protected static ?string $slug = 'probar-selector-imputacion';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Filament::auth()->user() instanceof User;
    }

    /** Sin el modo desarrollo no existe, en cada petición. */
    public function boot(): void
    {
        abort_unless(ModoDesarrollo::activo(), 404);
    }

    public function mount(): void
    {
        $this->form->fill(['monto' => '300.00', 'moneda' => 'PEN', 'fecha' => now('America/Lima')->toDateString()]);
    }

    /** @return list<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('lineaGuardada')->label('Ver una línea ya guardada')->icon('heroicon-o-archive-box')->color('gray')
                // Una línea que se guardó cuando PC-03 estaba activa: se sigue mostrando aunque hoy no se pueda elegir.
                ->action(fn () => $this->form->fill([...$this->data ?? [], 'imputacion_tipo' => 'partida', 'imputacion_proyecto_id' => 1, 'imputacion_partida_id' => 5])),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedSchema::make('form')]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Línea de costo de prueba')
                ->description('Datos inventados, en memoria: no se guarda nada. «PC-01.02 Cable» ya tiene S/ 460.00 comprometidos de S/ 500.00; «PC-03 Movilidad» está desactivada.')
                ->columns(3)
                ->schema([
                    TextInput::make('monto')->label('Monto')->live(onBlur: true),
                    Select::make('moneda')->label('Moneda')->options(['PEN' => 'Soles', 'USD' => 'Dólares'])->live(),
                    DatePicker::make('fecha')->label('Fecha')->live(),
                ]),
            Section::make('Imputar a')
                ->schema(array_map(fn (Closure $control) => $control(), array_values(SelectorImputacion::controles(self::fuente())))),
        ]);
    }

    /** La fuente de esta página: la real, con las versiones en memoria (D8). Igual en cada petición. */
    public static function fuente(): DestinosDePresupuestos
    {
        $proyectos = new ProyectosEnMemoria;
        $proyecto = $proyectos->agregar('Videovigilancia de la plaza inventada');
        $partidas = new PartidasEnMemoria;
        $suministro = $partidas->agregar($proyecto->id, 'PC-01', 'Suministro');
        $partidas->agregar($proyecto->id, 'PC-01.01', 'Cámaras', $suministro, '1000.00');
        $cable = $partidas->agregar($proyecto->id, 'PC-01.02', 'Cable', $suministro, '500.00');
        $partidas->agregar($proyecto->id, 'PC-02', 'Obras civiles', null, '300.00');
        $partidas->agregar($proyecto->id, 'PC-03', 'Movilidad', activa: false);
        $partidas->agregar($proyecto->id, 'PC-99', 'No presupuestados');
        $partidas->imputar($cable, '460.00');

        return new DestinosDePresupuestos($partidas, $proyectos);
    }
}
