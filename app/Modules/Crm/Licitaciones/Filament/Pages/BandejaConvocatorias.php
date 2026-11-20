<?php

declare(strict_types=1);

namespace App\Modules\Crm\Licitaciones\Filament\Pages;

use App\Modules\Crm\Licitaciones\Filament\Resources\Licitaciones\Pages\VerLicitacion;
use App\Modules\Crm\Licitaciones\Services\ConvocatoriasSeaceService;
use App\Modules\Crm\Licitaciones\Services\LicitacionesService;
use App\Modules\Nucleo\Models\User;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
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
use RuntimeException;

/** MVP rápido (ficha t1-licitaciones v2): la bandeja de convocatorias del SEACE que coinciden con lo que vende Techquk. */
final class BandejaConvocatorias extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'convocatorias';

    protected static ?string $title = 'Convocatorias del SEACE';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-inbox-stack';

    public static function canAccess(): bool
    {
        $u = Filament::auth()->user();

        return $u instanceof User && $u->can(LicitacionesService::PERMISO_VER);
    }

    public function getSubheading(): string
    {
        $nuevas = DB::table('convocatorias_seace')->where('estado', 'nueva')->count();
        $ultima = DB::table('convocatorias_seace')->max('created_at');

        return "{$nuevas} nuevas · Llegan del SEACE por los datos abiertos del OECE y solo entran las que mencionan: ".implode(', ', app(ConvocatoriasSeaceService::class)->palabras())
            .($ultima === null ? '' : ' · última lectura '.CarbonImmutable::parse($ultima)->format('d/m/Y H:i'));
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (?array $filters): array => DB::table('convocatorias_seace')->when(($filters['estado']['value'] ?? 'nueva') !== 'todas', fn ($q) => $q->where('estado', $filters['estado']['value'] ?? 'nueva'))
                ->orderBy('presentacion')->get()->map(fn (object $c): array => ['__key' => (string) $c->id, ...(array) $c,
                    'es_cuenta' => $c->ruc_entidad !== null && DB::table('cuentas')->join('parties', 'parties.id', '=', 'cuentas.party_id')->where('parties.numero_documento', $c->ruc_entidad)->exists()])->all())
            ->paginated(false)
            ->columns([
                TextColumn::make('nomenclatura')->label('Convocatoria')->fontFamily('mono')->wrap()->description(fn (array $record): string => $record['tipo']),
                TextColumn::make('objeto')->label('Objeto')->wrap()->limit(110)->description(fn (array $record): string => $record['entidad'].($record['region'] ? " · {$record['region']}" : '')),
                TextColumn::make('es_cuenta')->label('Entidad')->badge()->formatStateUsing(fn (bool $state): string => $state ? 'ya es cuenta' : 'nueva')->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                TextColumn::make('valor_referencial')->label('Valor referencial')->alignEnd()->fontFamily('mono')->formatStateUsing(fn ($state, array $record): string => $state === null ? '—' : ($record['moneda'] === 'PEN' ? 'S/ ' : $record['moneda'].' ').number_format((float) $state, 2)),
                TextColumn::make('presentacion')->label('Presentación')->date('d/m/Y')->placeholder('—')
                    ->color(fn (array $record): ?string => $record['presentacion'] !== null && $record['presentacion'] < now()->addDays(5)->toDateString() ? 'danger' : null),
                TextColumn::make('coincide')->label('Coincide con')->badge()->color('info'),
            ])
            ->filters([SelectFilter::make('estado')->label('Ver')->default('nueva')->options(['nueva' => 'Nuevas', 'registrada' => 'Registradas', 'descartada' => 'Descartadas', 'todas' => 'Todas'])->query(fn ($q) => $q)])
            ->recordActions([
                Action::make('registrar')->label('Registrar')->button()->color('success')->hidden(fn (array $record): bool => $record['estado'] !== 'nueva')
                    ->requiresConfirmation()->modalDescription('Nace la licitación «por decidir» con su entidad (se crea la cuenta si no existe), objeto, valor referencial y cronograma. Después decides si participas.')
                    ->action(function (array $record): void {
                        try {
                            $id = app(ConvocatoriasSeaceService::class)->registrar((int) $record['id'], auth()->user());
                            $this->redirect(VerLicitacion::getUrl(['record' => $id]));
                        } catch (RuntimeException $e) {
                            Notification::make()->danger()->title($e->getMessage())->send();
                        }
                    }),
                Action::make('descartar')->label('Descartar')->color('gray')->hidden(fn (array $record): bool => $record['estado'] !== 'nueva')
                    ->schema([Select::make('motivo')->label('Motivo')->required()->options(array_combine($m = ['No es nuestro rubro', 'Fuera de nuestra zona', 'Monto muy alto para nosotros', 'Monto muy bajo', 'Plazo imposible'], $m))])
                    ->action(fn (array $record, array $data) => app(ConvocatoriasSeaceService::class)->descartar((int) $record['id'], $data['motivo'])),
                Action::make('seace')->label('Ver en el SEACE')->color('gray')->url(fn (array $record): ?string => $record['url'], true)->hidden(fn (array $record): bool => $record['url'] === null),
            ])
            ->emptyStateHeading('Sin convocatorias nuevas');
    }

    protected function getHeaderActions(): array
    {
        $puede = fn (): bool => auth()->user()?->can(LicitacionesService::PERMISO_GESTIONAR) ?? false;
        $avisar = fn (array $r) => Notification::make()->success()->title("Leídas {$r['leidas']}: {$r['nuevas']} nuevas que coinciden; {$r['descartadas_por_filtro']} fuera del filtro.")->send();

        return [
            Action::make('sincronizar')->label('Leer ahora del OECE')->icon('heroicon-o-arrow-path')->visible($puede)
                ->action(function () use ($avisar): void {
                    try {
                        $avisar(app(ConvocatoriasSeaceService::class)->sincronizar());
                    } catch (RuntimeException $e) {
                        Notification::make()->warning()->title($e->getMessage())->send();
                    }
                }),
            Action::make('importar')->label('Importar archivo OCDS')->icon('heroicon-o-arrow-up-tray')->color('gray')->visible($puede)
                ->modalDescription('Un archivo JSON de contrataciones abiertas (release o record package) descargado del portal de datos abiertos del OECE.')
                ->schema([FileUpload::make('archivo')->label('Archivo JSON')->required()->storeFiles(false)->acceptedFileTypes(['application/json', 'text/plain'])])
                ->action(function (array $data) use ($avisar): void {
                    try {
                        $avisar(app(ConvocatoriasSeaceService::class)->importar($data['archivo']->get()));
                    } catch (RuntimeException $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();
                    }
                }),
        ];
    }
}
