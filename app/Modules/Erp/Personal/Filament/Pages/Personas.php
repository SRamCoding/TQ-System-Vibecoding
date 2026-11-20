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
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

/** MVP rápido (t7-personas): las personas con su contrato, su habilitación, sus certificaciones, su experiencia y su salida. */
final class Personas extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'personas';

    protected static ?string $title = 'Personas';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    public const array TIPOS_DOC = ['sctr' => 'SCTR', 'emo' => 'Examen médico ocupacional', 'induccion' => 'Inducción de seguridad', 'certificacion' => 'Certificación'];

    public static function canAccess(): bool
    {
        $u = Filament::auth()->user();

        return $u instanceof User && $u->can(PersonalService::PERMISO_VER);
    }

    public function getSubheading(): string
    {
        $hoy = CarbonImmutable::parse(Calendario::hoy());
        $vencen = DB::table('colaborador_documentos as d')->join('colaboradores as c', 'c.id', '=', 'd.colaborador_id')->where('c.es_activo', true)->whereBetween('d.vence', [$hoy->toDateString(), $hoy->addDays(30)->toDateString()])->count();

        return "{$vencen} documentos vencen en 30 días · Varias convocatorias exigen personal certificado vigente: esta información también es comercial.";
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        $hoy = Calendario::hoy();

        return $table
            ->records(fn (?array $filters): array => DB::table('colaboradores as c')->join('categorias_personal as k', 'k.id', '=', 'c.categoria_id')
                ->when(($filters['vista']['value'] ?? 'activos') === 'activos', fn ($q) => $q->where('c.es_activo', true))->when(($filters['vista']['value'] ?? '') === 'salidas', fn ($q) => $q->where('c.es_activo', false))
                ->orderBy('c.nombres')->get(['c.*', 'k.nombre as categoria'])->map(function (object $c) use ($hoy): array {
                    $docs = DB::table('colaborador_documentos')->where('colaborador_id', $c->id)->orderBy('vence')->get();
                    $exp = DB::table('asignaciones as a')->join('proyectos as p', 'p.id', '=', 'a.proyecto_id')->where('a.colaborador_id', $c->id)->distinct()->pluck('p.codigo');

                    return ['__key' => (string) $c->id, ...(array) $c, 'habilitacion' => $c->habilitado_hasta === null ? 'sin habilitación' : 'hasta '.CarbonImmutable::parse($c->habilitado_hasta)->format('d/m/Y'),
                        'habil_ok' => $c->habilitado_hasta !== null && $c->habilitado_hasta >= $hoy,
                        'certificaciones' => $docs->where('tipo', 'certificacion')->map(fn ($d): string => $d->nombre.($d->vence ? ' (vence '.CarbonImmutable::parse($d->vence)->format('d/m/Y').')' : ''))->implode(' · ') ?: '—',
                        'experiencia' => $exp->isEmpty() ? '—' : $exp->count().' proyectos: '.$exp->implode(', ')];
                })->all())
            ->paginated(false)
            ->columns([
                TextColumn::make('nombres')->label('Persona')->weight('bold')->description(fn (array $record): string => $record['categoria'].' · '.$record['vinculo'].($record['regimen'] ? ' · '.strtoupper($record['regimen']) : '')),
                TextColumn::make('remuneracion')->label('Remuneración')->alignEnd()->fontFamily('mono')->formatStateUsing(fn ($state): string => $state === null ? '—' : number_format((float) $state, 2))->placeholder('—'),
                TextColumn::make('habilitacion')->label('Habilitación de campo')->color(fn (array $record): string => $record['habil_ok'] ? 'success' : 'danger'),
                TextColumn::make('certificaciones')->label('Certificaciones')->wrap(),
                TextColumn::make('experiencia')->label('Experiencia')->wrap(),
                TextColumn::make('salida')->label('Salida')->date('d/m/Y')->placeholder('—')->description(fn (array $record): ?string => $record['motivo_salida'] ? $record['motivo_salida'].' · liquidación '.number_format((float) $record['liquidacion'], 2) : null),
            ])
            ->filters([SelectFilter::make('vista')->label('Ver')->default('activos')->options(['activos' => 'Activos', 'salidas' => 'Que se fueron', 'todos' => 'Todos'])->query(fn ($q) => $q)])
            ->recordActions([
                Action::make('documento')->label('Documento')->color('gray')->visible(fn (): bool => auth()->user()?->can(PersonalService::PERMISO_TAREAR) ?? false)
                    ->schema([Select::make('tipo')->label('Tipo')->options(self::TIPOS_DOC)->required(), TextInput::make('nombre')->label('Nombre')->required(), TextInput::make('emisor')->label('Emisor'), DatePicker::make('vence')->label('Vence')->format('Y-m-d')])
                    ->action(fn (array $record, array $data) => app(PersonasService::class)->documento((int) $record['id'], $data['tipo'], $data['nombre'], $data['emisor'] ?? null, $data['vence'] ?? null)),
                Action::make('salida')->label('Salida')->color('danger')->hidden(fn (array $record): bool => ! $record['es_activo'] || ! (auth()->user()?->can(PersonasService::PERMISO_PLANILLA) ?? false))
                    ->modalHeading(fn (array $record): string => "Salida de {$record['nombres']}")
                    ->modalDescription(fn (array $record): string => 'Lo que hay que resolver: '.collect(app(PersonasService::class)->pendientesDeSalida((int) $record['id']))->map(fn (array $p): string => ($p['resuelto'] ? '✓ ' : '• ')."{$p['que']} ({$p['detalle']})")->implode('; ').'. Al registrar: se liberan sus asignaciones, se revoca su acceso y se calcula su liquidación.')
                    ->fillForm(fn (): array => ['fecha' => Calendario::hoy()])
                    ->schema([DatePicker::make('fecha')->label('Fecha de salida')->required()->format('Y-m-d'), TextInput::make('motivo')->label('Motivo y nota de traspaso')->required()->maxLength(200)])
                    ->action(fn (array $record, array $data) => Notification::make()->success()->title('Salida registrada · liquidación '.number_format((float) app(PersonasService::class)->salida((int) $record['id'], CarbonImmutable::parse($data['fecha']), $data['motivo']), 2))->send()),
            ]);
    }
}
