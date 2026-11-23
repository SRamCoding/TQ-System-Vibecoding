<?php

declare(strict_types=1);

namespace App\Modules\Crm\Campanias\Filament\Resources\Campanias\Pages;

use App\Modules\Crm\Campanias\Filament\Resources\Campanias\CampaniaResource;
use App\Modules\Crm\Campanias\Filament\Resources\Leads\LeadResource;
use App\Modules\Crm\Campanias\Models\Campania;
use App\Modules\Crm\Campanias\Services\LeadsService;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\TarjetasOportunidades;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;

/** MVP rápido (t1-campanias): la lista de campañas, con «Nueva campaña». */
final class ListarCampanias extends ListRecords
{
    protected static string $resource = CampaniaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('leads')->label('Por calificar')->icon('heroicon-o-inbox-arrow-down')->color('gray')->url(TarjetasOportunidades::getUrl(['etapa' => ['por_calificar']])),
            Action::make('nueva')->label('Nueva campaña')->icon('heroicon-o-plus')->slideOver()
                ->visible(fn (): bool => LeadResource::puede(LeadsService::PERMISO_GESTIONAR))
                ->schema([
                    TextInput::make('nombre')->label('Nombre')->required()->maxLength(150),
                    Select::make('tipo')->label('Tipo')->required()->options(Campania::TIPOS),
                    DatePicker::make('desde')->label('Desde')->required(),
                    DatePicker::make('hasta')->label('Hasta'),
                    TextInput::make('presupuesto')->label('Presupuesto (S/)')->numeric()->minValue(0),
                    Textarea::make('objetivo')->label('Objetivo')->rows(3),
                ])
                ->action(fn (array $data) => $this->redirect(VerCampania::getUrl(['record' => Campania::query()->create([...$data, 'es_activa' => true])]))),
        ];
    }
}
