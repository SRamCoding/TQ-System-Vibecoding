<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Pages;

use App\Modules\Nucleo\Aprobaciones\DatoParaAprobar;
use App\Modules\Nucleo\Aprobaciones\PendienteDeFirma;
use App\Modules\Nucleo\Aprobaciones\RegistroResumenesAprobacion;
use App\Modules\Nucleo\Filament\AccionFilament;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\AprobacionesService;
use App\Modules\Nucleo\Services\MotivosService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;

/**
 * «Mis aprobaciones» (plan 2.22, P6): lo que a la persona le toca firmar, de todos los documentos, con el resumen que
 * aporta cada uno (`RegistroResumenesAprobacion`). Se firma aquí mismo: «Aprobar» y «Observar» abren el panel deslizante
 * con el resumen y llevan la versión del documento leída al abrirlo; si cambió, no se firma. Los datos sensibles ya
 * llegan filtrados del servicio. Se abre desde su tarjeta de «Mien».
 *
 * @phpstan-type Fila array{firma: int, type: string, id: int, transicion: string, documento: string, paso: string, solicitante: string, pedida: string, monto: string, version: int, datos: list<array{etiqueta: string, valor: string}>, enlace: ?string}
 */
final class MisAprobaciones extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'mis-aprobaciones';

    protected static ?string $title = 'Mis aprobaciones';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-inbox';

    /** No va en la barra de módulos: se abre desde «Mien». */
    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return Filament::auth()->user() instanceof User;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        $fijos = fn (array $record): array => ['documento_type' => $record['type'], 'documento_id' => $record['id'], 'transicion' => $record['transicion']];
        $resumen = fn (array $record): View => view('nucleo::filament.aprobaciones.resumen', ['fila' => $record]);
        $alTerminar = function (): void {
            $this->resetTable();
        };

        return $table
            ->records(fn (): array => $this->filas())
            ->paginated(false)
            ->columns([
                TextColumn::make('documento')->label('Documento')->weight('bold')
                    ->description(fn (array $record): string => "Firma: {$record['paso']}")->wrap(),
                TextColumn::make('solicitante')->label('Lo pide')
                    ->description(fn (array $record): string => $record['pedida']),
                TextColumn::make('monto')->label('Monto (S/)')->alignEnd()->fontFamily('mono'),
            ])
            ->recordActions([
                AccionFilament::desde('nucleo.aprobaciones.firmar', datosFijos: $fijos, alTerminar: function () use ($alTerminar): void {
                    Notification::make()->success()->title('Firmaste la aprobación.')->send();
                    $alTerminar();
                })
                    ->label('Aprobar')->icon('heroicon-m-check')->color('success')
                    ->slideOver()->modalHeading(fn (array $record): string => $record['documento'])
                    ->modalContent($resumen)->modalSubmitActionLabel('Aprobar')
                    // La versión se toma al abrir el panel (AGENTS.md): el servicio la compara al firmar.
                    ->fillForm(fn (array $record): array => ['version' => $record['version']]),
                AccionFilament::desde('nucleo.aprobaciones.observar', datosFijos: $fijos, alTerminar: function () use ($alTerminar): void {
                    Notification::make()->success()->title('Observaste el documento: vuelve a quien lo pidió.')->send();
                    $alTerminar();
                }, controles: [
                    'motivo_id' => fn (): Select => Select::make('motivo_id')
                        ->options(fn (): array => app(MotivosService::class)->opciones(AprobacionesService::FAMILIA_OBSERVAR, null)),
                    'comentario' => fn (): Textarea => Textarea::make('comentario')->rows(3),
                ])
                    ->label('Observar')->icon('heroicon-m-arrow-uturn-left')->color('warning')
                    ->slideOver()->modalHeading(fn (array $record): string => $record['documento'])
                    ->modalContent($resumen)->modalSubmitActionLabel('Observar')
                    ->fillForm(fn (array $record): array => ['version' => $record['version']]),
                Action::make('abrir')->label('Abrir')->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(fn (array $record): ?string => $record['enlace'])
                    ->hidden(fn (array $record): bool => $record['enlace'] === null),
            ])
            ->emptyStateHeading('No tienes nada por firmar')
            ->emptyStateDescription('Cuando un documento necesite tu firma, aparecerá aquí.');
    }

    /** @return array<string, Fila> */
    private function filas(): array
    {
        $usuario = Filament::auth()->user();

        if (! $usuario instanceof User) {
            return [];
        }

        $filas = [];

        foreach (app(AprobacionesService::class)->bandeja($usuario, app(RegistroResumenesAprobacion::class)) as $p) {
            $filas["firma-{$p->firmaId}"] = self::fila($p);
        }

        return $filas;
    }

    /** @return Fila */
    private static function fila(PendienteDeFirma $p): array
    {
        return [
            'firma' => $p->firmaId,
            'type' => $p->documentoType,
            'id' => $p->documentoId,
            'transicion' => $p->transicion,
            'documento' => $p->documento,
            'paso' => $p->paso,
            'solicitante' => $p->solicitante,
            'pedida' => $p->pedidaAt->setTimezone('America/Lima')->format('d/m/Y H:i'),
            'monto' => number_format((float) $p->montoSoles, 2),
            'version' => $p->version,
            'datos' => array_map(fn (DatoParaAprobar $d): array => ['etiqueta' => $d->etiqueta, 'valor' => $d->valor], $p->datos),
            'enlace' => $p->enlace,
        ];
    }
}
