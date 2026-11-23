<?php

declare(strict_types=1);

namespace App\Modules\Erp\Personal\Filament\Pages;

use App\Modules\Erp\Personal\Services\PersonalService;
use App\Modules\Erp\Personal\Services\PersonasService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Tiempo\Calendario;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

/** MVP rápido (t7-personas): la demanda de los próximos 90 días y la selección, de la vacante a la contratación. */
final class Vacantes extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'vacantes';

    protected static ?string $title = 'Demanda y selección';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-plus';

    public static function canAccess(): bool
    {
        $u = Filament::auth()->user();

        return $u instanceof User && $u->can(PersonalService::PERMISO_VER);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([View::make('personal::demanda')->viewData(['filas' => app(PersonasService::class)->demanda()]), EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => DB::table('candidatos as c')->join('vacantes as v', 'v.id', '=', 'c.vacante_id')->join('categorias_personal as k', 'k.id', '=', 'v.categoria_id')->leftJoin('proyectos as p', 'p.id', '=', 'v.proyecto_id')
                ->orderBy('v.desde')->get(['c.*', 'k.nombre as categoria', 'p.codigo as proyecto', 'v.desde', 'v.estado as estado_vacante'])->map(fn (object $c): array => ['__key' => (string) $c->id, ...(array) $c])->all())
            ->paginated(false)
            ->heading('Candidatos por vacante')
            ->columns([
                TextColumn::make('categoria')->label('Vacante')->description(fn (array $record): string => ($record['proyecto'] ?? 'empresa').' · desde '.CarbonImmutable::parse($record['desde'])->format('d/m/Y').' · '.$record['estado_vacante']),
                TextColumn::make('nombres')->label('Candidato')->weight('bold')->description(fn (array $record): string => 'DNI '.$record['dni']),
                TextColumn::make('nota')->label('Nota')->wrap()->placeholder('—'),
                TextColumn::make('estado')->label('Estado')->badge()->color(fn (string $state): string => ['contratado' => 'success', 'descartado' => 'gray', 'entrevistado' => 'info'][$state] ?? 'warning'),
            ])
            ->recordActions([
                Action::make('contratar')->label('Contratar')->button()->color('success')->hidden(fn (array $record): bool => in_array($record['estado'], ['contratado', 'descartado'], true))
                    ->fillForm(fn (): array => ['ingreso' => Calendario::hoy(), 'regimen' => 'onp'])
                    ->schema([Select::make('sexo')->label('Sexo')->options(PersonalService::SEXOS)->required(), TextInput::make('remuneracion')->label('Remuneración mensual')->numeric()->required(), Select::make('regimen')->label('Régimen')->options(['onp' => 'ONP', 'afp' => 'AFP'])->required(), DatePicker::make('ingreso')->label('Ingreso')->required()->format('Y-m-d')])
                    ->action(fn (array $record, array $data) => app(PersonasService::class)->contratar((int) $record['id'], (string) $data['remuneracion'], $data['regimen'], CarbonImmutable::parse($data['ingreso']), $data['sexo'])),
                Action::make('descartar')->label('Descartar')->color('gray')->hidden(fn (array $record): bool => in_array($record['estado'], ['contratado', 'descartado'], true))
                    ->action(fn (array $record) => DB::table('candidatos')->where('id', $record['id'])->update(['estado' => 'descartado', 'updated_at' => now()])),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('vacante')->label('Nueva vacante')->icon('heroicon-o-plus')
                ->schema([Select::make('categoria')->label('Categoría')->options(fn (): array => DB::table('categorias_personal')->pluck('nombre', 'id')->all())->required(),
                    Select::make('proyecto')->label('Proyecto')->options(fn (): array => DB::table('proyectos')->orderBy('codigo')->pluck('codigo', 'id')->all()),
                    TextInput::make('cantidad')->label('Cantidad')->numeric()->integer()->required()->default(1), DatePicker::make('desde')->label('Desde')->required()->format('Y-m-d'), TextInput::make('perfil')->label('Perfil')])
                ->action(fn (array $data) => app(PersonasService::class)->vacante((int) $data['categoria'], filled($data['proyecto'] ?? null) ? (int) $data['proyecto'] : null, (int) $data['cantidad'], $data['desde'], $data['perfil'] ?? null)),
            Action::make('candidato')->label('Agregar candidato')->icon('heroicon-o-user')->color('gray')
                ->schema([Select::make('vacante')->label('Vacante')->required()->options(fn (): array => DB::table('vacantes as v')->join('categorias_personal as k', 'k.id', '=', 'v.categoria_id')->where('v.estado', 'abierta')->selectRaw("v.id, k.nombre || ' · desde ' || v.desde AS t")->pluck('t', 'v.id')->all()),
                    TextInput::make('nombres')->label('Nombres y apellidos')->required(), TextInput::make('dni')->label('DNI')->required()->length(8), TextInput::make('nota')->label('Nota')])
                ->action(fn (array $data) => app(PersonasService::class)->candidato((int) $data['vacante'], $data['nombres'], $data['dni'], $data['nota'] ?? null)),
        ];
    }
}
