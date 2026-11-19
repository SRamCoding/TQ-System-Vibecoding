<?php

declare(strict_types=1);

namespace App\Modules\Crm\Campanias\Filament\Resources\Leads\Pages;

use App\Modules\Crm\Campanias\Filament\Resources\Campanias\CampaniaResource;
use App\Modules\Crm\Campanias\Filament\Resources\Leads\LeadResource;
use App\Modules\Crm\Campanias\Models\Campania;
use App\Modules\Crm\Campanias\Models\Lead;
use App\Modules\Crm\Campanias\Services\LeadsService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\DB;

/** MVP rápido (t1-campanias): «Campañas y leads»: la lista de leads, con «Nuevo lead» y el enlace a las campañas. */
final class ListarLeads extends ListRecords
{
    protected static string $resource = LeadResource::class;

    public function getTitle(): string
    {
        return 'Campañas y leads';
    }

    public function getSubheading(): string
    {
        $n = LeadResource::sinAtender();

        return ($n > 0 ? "Sin atender +3 días · {$n} · " : '').'En Techquk el negocio entra por convocatorias: los leads se convierten solo después de revisar coincidencias.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('campanias')->label('Campañas')->icon('heroicon-o-megaphone')->color('gray')->url(CampaniaResource::getUrl()),
            Action::make('nuevo')->label('Nuevo lead')->icon('heroicon-o-plus')->slideOver()
                ->visible(fn (): bool => LeadResource::puede(LeadsService::PERMISO_GESTIONAR))
                ->schema([
                    TextInput::make('nombre_contacto')->label('Nombre')->required()->maxLength(150),
                    TextInput::make('cargo')->label('Cargo')->maxLength(120),
                    TextInput::make('organizacion')->label('Organización')->maxLength(200),
                    TextInput::make('ruc')->label('RUC')->maxLength(11),
                    TextInput::make('email')->label('Correo')->email()->maxLength(150),
                    TextInput::make('telefono')->label('Teléfono')->maxLength(30),
                    Select::make('origen_comercial_id')->label('Origen')->required()->options(fn (): array => DB::table('origenes_comerciales')->orderBy('nombre')->pluck('nombre', 'id')->all()),
                    Select::make('campania_id')->label('Campaña')->options(fn (): array => Campania::query()->where('es_activa', true)->pluck('nombre', 'id')->all()),
                    Textarea::make('mensaje')->label('Qué pidió')->rows(3),
                ])
                ->action(function (array $data): void {
                    Lead::query()->create([...$data, 'estado' => 'nuevo']);
                    Notification::make()->success()->title('Lead registrado.')->send();
                }),
        ];
    }
}
