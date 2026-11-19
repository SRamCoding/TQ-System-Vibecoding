<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Resources\TiposCambio\Pages;

use App\Modules\Nucleo\Acciones\SincronizarTipoCambio;
use App\Modules\Nucleo\Enums\ResultadoSincronizacion;
use App\Modules\Nucleo\Filament\AccionFilament;
use App\Modules\Nucleo\Filament\Resources\TiposCambio\TipoCambioResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

final class ListarTiposCambio extends ListRecords
{
    protected static string $resource = TipoCambioResource::class;

    protected static ?string $title = 'Tipos de cambio';

    protected function getHeaderActions(): array
    {
        return [
            AccionFilament::desde(
                app(SincronizarTipoCambio::class),
                alTerminar: function (ResultadoSincronizacion $resultado): void {
                    Notification::make()->success()->title(match ($resultado) {
                        ResultadoSincronizacion::Creado => 'Tipo de cambio del día traído de SUNAT.',
                        ResultadoSincronizacion::Actualizado => 'SUNAT corrigió el tipo de cambio del día: actualizado.',
                        ResultadoSincronizacion::SinCambios => 'El tipo de cambio del día ya estaba al día.',
                        ResultadoSincronizacion::SeRespetaManual => 'El tipo de cambio del día fue registrado a mano: no se reemplaza.',
                    })->send();
                },
            )->icon('heroicon-o-arrow-path'),
            TipoCambioResource::accionRegistrar()
                ->label('Registrar a mano')
                ->color('gray')
                ->icon('heroicon-o-plus')
                ->fillForm(fn (): array => ['moneda' => 'USD', 'fecha' => now()->toDateString()]),
        ];
    }
}
