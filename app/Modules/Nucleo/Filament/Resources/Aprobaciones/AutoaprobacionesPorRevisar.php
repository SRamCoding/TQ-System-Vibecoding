<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Resources\Aprobaciones;

use App\Modules\Nucleo\Estados\RegistroDocumentos;
use App\Modules\Nucleo\Filament\AccionFilament;
use App\Modules\Nucleo\Models\FirmaAprobacion;
use App\Modules\Nucleo\Models\User;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * La pestaña «Autoaprobaciones por revisar» de Configuración → Aprobaciones (plan 1.7, D6): lo que alguien aprobó de sí
 * mismo, con su motivo, hasta que otra persona lo marca revisado (por la acción registrada). El aviso llega en la 1.10.
 */
final class AutoaprobacionesPorRevisar
{
    public static function tabla(Table $table): Table
    {
        return $table
            ->query(fn () => FirmaAprobacion::query()->with('ronda')->where('es_autoaprobacion', true)->whereNull('revisada_at'))
            ->defaultSort('firmado_at', 'desc')
            ->columns([
                TextColumn::make('documento')->label('Documento')
                    ->state(fn (FirmaAprobacion $record): string => self::documento($record)),
                TextColumn::make('ronda.monto_soles')->label('Monto (S/)')
                    ->formatStateUsing(fn (?string $state): string => number_format((float) $state, 2)),
                TextColumn::make('user_id')->label('Aprobó')
                    ->formatStateUsing(fn (?int $state): string => (string) User::query()->whereKey($state)->value('name')),
                TextColumn::make('firmado_at')->label('Cuándo')->dateTime('d/m/Y H:i', 'America/Lima'),
                TextColumn::make('motivo_autoaprobacion')->label('Por qué')->wrap(),
            ])
            ->recordActions([
                AccionFilament::desde('nucleo.aprobaciones.revisar-autoaprobacion', fn (FirmaAprobacion $record): array => ['firma_id' => $record->id], function (): void {
                    Notification::make()->success()->title('Marcada como revisada.')->send();
                })
                    ->label('Marcar revisada')->icon('heroicon-o-clipboard-document-check')->requiresConfirmation(),
            ])
            ->emptyStateHeading('No hay autoaprobaciones por revisar');
    }

    /** «Documento de prueba DPR-2026-0001»: su tipo y su número (o su id). */
    private static function documento(FirmaAprobacion $firma): string
    {
        $ronda = $firma->ronda;
        $definicion = app(RegistroDocumentos::class)->definicionDe($ronda->aprobable_type);
        $modelo = Relation::getMorphedModel($ronda->aprobable_type);
        $documento = $modelo === null ? null : $modelo::query()->find($ronda->aprobable_id);

        if ($definicion === null || $documento === null) {
            return "{$ronda->aprobable_type} #{$ronda->aprobable_id}";
        }

        return ucfirst($definicion->nombre()).' '.$definicion->identificador($documento);
    }
}
