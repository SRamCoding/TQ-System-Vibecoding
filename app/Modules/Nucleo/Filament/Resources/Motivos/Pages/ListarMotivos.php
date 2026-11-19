<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Resources\Motivos\Pages;

use App\Modules\Nucleo\Filament\Resources\Motivos\LoMasEscritoEnOtro;
use App\Modules\Nucleo\Filament\Resources\Motivos\MotivoResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Livewire;

final class ListarMotivos extends ListRecords
{
    protected static string $resource = MotivoResource::class;

    protected static ?string $title = 'Motivos';

    protected function getHeaderActions(): array
    {
        return [
            MotivoResource::accionAgregar(),
            // Plan 2.0, D3: lo que se escribe en «Otro», para sumar a la lista lo que se repite.
            Action::make('loMasEscritoEnOtro')->label('Lo más escrito en «Otro»')->icon('heroicon-o-chat-bubble-left-ellipsis')->color('gray')
                ->slideOver()
                ->modalHeading('Lo más escrito en «Otro»')
                ->schema([Livewire::make(LoMasEscritoEnOtro::class)->key('lo-mas-escrito-en-otro')])
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar'),
        ];
    }
}
