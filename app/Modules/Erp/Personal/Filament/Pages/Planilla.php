<?php

declare(strict_types=1);

namespace App\Modules\Erp\Personal\Filament\Pages;

use App\Modules\Erp\Personal\Services\PersonasService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Tiempo\Calendario;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * MVP rápido (t7-personas): la planilla del mes. Sale de los contratos y del tareo; al cerrarla, las boletas, el archivo
 * para la declaración ante SUNAT (PLAME) y los asientos. Muestra la diferencia entre el costo real y el estándar por proyecto.
 */
final class Planilla extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'planilla';

    protected static ?string $title = 'Planilla';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    #[Url]
    public ?string $periodo = null;

    public static function canAccess(): bool
    {
        $u = Filament::auth()->user();

        return $u instanceof User && $u->can(PersonasService::PERMISO_PLANILLA);
    }

    public function mount(): void
    {
        $this->periodo ??= (string) (DB::table('planillas')->orderByDesc('periodo')->value('periodo') ?? Calendario::ahora()->format('Y-m'));
    }

    private function planilla(): ?object
    {
        return DB::table('planillas')->where('periodo', $this->periodo)->first();
    }

    public function getSubheading(): string
    {
        $p = $this->planilla();
        $t = $p === null ? null : DB::table('planilla_lineas')->where('planilla_id', $p->id)->selectRaw('SUM(neto) AS neto, SUM(costo_empresa) AS costo')->first();

        return "Periodo {$this->periodo} · ".($p === null ? 'sin calcular' : ($p->estado === 'cerrada' ? 'cerrada' : 'en cálculo').' · neto '.number_format((float) $t->neto, 2).' · costo empresa '.number_format((float) $t->costo, 2));
    }

    public function content(Schema $schema): Schema
    {
        $p = $this->planilla();

        return $schema->components([EmbeddedTable::make(), View::make('personal::diferencia')->viewData(['filas' => $p === null ? [] : app(PersonasService::class)->diferenciaPorProyecto($p->id)])]);
    }

    public function table(Table $table): Table
    {
        $m = fn (string $c, string $l) => TextColumn::make($c)->label($l)->alignEnd()->fontFamily('mono')->formatStateUsing(fn ($state): string => number_format((float) $state, 2));

        return $table
            ->records(fn (): array => DB::table('planilla_lineas as l')->join('colaboradores as c', 'c.id', '=', 'l.colaborador_id')->where('l.planilla_id', $this->planilla()?->id ?? 0)
                ->orderBy('c.nombres')->get(['l.*', 'c.nombres', 'c.dni'])->map(fn (object $l): array => ['__key' => (string) $l->id, ...(array) $l])->all())
            ->paginated(false)
            ->columns([
                TextColumn::make('nombres')->label('Trabajador')->weight('bold')->description(fn (array $record): string => 'DNI '.$record['dni'].' · '.rtrim(rtrim((string) $record['horas_tareadas'], '0'), '.').' h tareadas'),
                TextColumn::make('regimen')->label('Régimen')->formatStateUsing(fn (string $state): string => strtoupper($state)),
                $m('remuneracion', 'Remuneración'), $m('aporte', 'Aporte'), $m('neto', 'Neto'), $m('essalud', 'EsSalud 9 %'), $m('costo_empresa', 'Costo empresa'),
            ])
            ->recordActions([
                Action::make('boleta')->label('Boleta')->color('gray')->modalSubmitAction(false)->modalCancelActionLabel('Cerrar')->modalHeading(fn (array $record): string => "Boleta de pago · {$this->periodo}")
                    ->modalContent(fn (array $record) => view('personal::boleta', ['l' => (object) $record, 'periodo' => $this->periodo])),
            ])
            ->emptyStateHeading('Sin calcular');
    }

    protected function getHeaderActions(): array
    {
        $abierta = fn (): bool => $this->planilla()?->estado !== 'cerrada';

        return [
            Action::make('periodo')->label('Periodo')->icon('heroicon-o-calendar')->color('gray')->fillForm(fn (): array => ['periodo' => $this->periodo])
                ->schema([TextInput::make('periodo')->label('Periodo (AAAA-MM)')->required()->regex('/^\d{4}-\d{2}$/')])
                ->action(function (array $data): void {
                    $this->periodo = $data['periodo'];
                    $this->resetTable();
                }),
            Action::make('calcular')->label('Calcular')->icon('heroicon-o-calculator')->visible($abierta)
                ->action(function (): void {
                    try {
                        app(PersonasService::class)->calcularPlanilla((string) $this->periodo);
                        Notification::make()->success()->title('Planilla calculada.')->send();
                    } catch (RuntimeException $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();
                    }
                }),
            Action::make('cerrar')->label('Cerrar planilla')->icon('heroicon-o-lock-closed')->color('success')->requiresConfirmation()->visible(fn (): bool => $abierta() && $this->planilla() !== null)
                ->modalDescription('Al cerrar quedan las boletas, el archivo PLAME y los asientos; ya no se recalcula.')
                ->action(fn () => app(PersonasService::class)->cerrarPlanilla((int) $this->planilla()?->id)),
            Action::make('plame')->label('Exportar a PLAME')->icon('heroicon-o-arrow-down-tray')->color('gray')->visible(fn (): bool => $this->planilla() !== null)
                ->action(fn (): StreamedResponse => response()->streamDownload(function (): void {
                    $f = fopen('php://output', 'w');
                    foreach (DB::table('planilla_lineas as l')->join('colaboradores as c', 'c.id', '=', 'l.colaborador_id')->where('l.planilla_id', $this->planilla()?->id)->get() as $l) {
                        fwrite($f, implode('|', ['01', $l->dni, '0121', $l->remuneracion, '0601', $l->aporte, '0804', $l->essalud])."|\n");
                    }
                    fclose($f);
                }, 'plame-'.str_replace('-', '', (string) $this->periodo).'.txt')),
            Action::make('asientos')->label('Asientos')->icon('heroicon-o-document-text')->color('gray')->visible(fn (): bool => $this->planilla() !== null)
                ->action(fn (): StreamedResponse => response()->streamDownload(function (): void {
                    $t = DB::table('planilla_lineas')->where('planilla_id', $this->planilla()?->id)->selectRaw('SUM(remuneracion) r, SUM(aporte) a, SUM(neto) n, SUM(essalud) e')->first();
                    $f = fopen('php://output', 'w');
                    fwrite($f, "\xEF\xBB\xBF");
                    foreach ([['6211 Sueldos y salarios', $t->r, ''], ['6271 EsSalud', $t->e, ''], ['4031 EsSalud por pagar', '', $t->e], ['4032 ONP / 417 AFP por pagar', '', $t->a], ['4111 Remuneraciones por pagar', '', $t->n]] as $fila) {
                        fputcsv($f, $fila, ';');
                    }
                    fclose($f);
                }, 'asiento-planilla-'.$this->periodo.'.csv')),
        ];
    }
}
