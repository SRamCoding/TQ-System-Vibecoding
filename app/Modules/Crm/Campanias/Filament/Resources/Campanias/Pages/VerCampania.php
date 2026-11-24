<?php

declare(strict_types=1);

namespace App\Modules\Crm\Campanias\Filament\Resources\Campanias\Pages;

use App\Modules\Crm\Campanias\Filament\AtajosDeCampania;
use App\Modules\Crm\Campanias\Filament\Resources\Campanias\CampaniaResource;
use App\Modules\Crm\Campanias\Models\Campania;
use App\Modules\Crm\Campanias\Services\CampaniasService;
use App\Modules\Crm\Campanias\Services\LeadsService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\DB;

/**
 * Observación 9.10 (MV-104): la ficha de la campaña, como la de una oportunidad o un proyecto: cabecera con sus botones
 * (Formulario y QR, Registrar gasto, Editar), las cifras de la tarjeta en grande y pestañas Contactos · Oportunidades ·
 * Gastos · Seguimiento · Actividad. Se vuelve a la lista donde se estaba.
 */
final class VerCampania extends ViewRecord
{
    use AtajosDeCampania;

    protected static string $resource = CampaniaResource::class;

    public string $volver = '/campanias';

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->campaniaId = (int) $this->record->getKey();
        $anterior = url()->previous();
        $this->volver = str_contains($anterior, '/campanias') && ! str_contains($anterior, '/campanias/'.$this->campaniaId) ? $anterior : '/campanias';
    }

    public function getTitle(): string
    {
        return (string) $this->record->getAttribute('nombre');
    }

    public function getHeading(): string
    {
        return '';
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->formularioAction()->icon('heroicon-o-qr-code')->color('gray'),
            $this->registrarGastoAction()->color('gray'),
            Action::make('editar')->label('Editar')->icon('heroicon-o-pencil-square')->modalWidth('2xl')
                ->visible(fn (): bool => self::puedeGestionar())
                ->fillForm(fn (): array => $this->record->only(['nombre', 'tipo', 'canal_lead_id', 'desde', 'hasta', 'presupuesto', 'responsable_user_id', 'objetivo', 'es_activa']))
                ->schema([
                    TextInput::make('nombre')->label('Nombre')->required()->maxLength(150),
                    Select::make('tipo')->label('Tipo')->required()->options(Campania::TIPOS),
                    Select::make('canal_lead_id')->label('Canal')->options(fn (): array => array_map(fn (array $c): string => $c['nombre'], app(LeadsService::class)->canales(soloActivos: true))),
                    DatePicker::make('desde')->label('Desde')->required(),
                    DatePicker::make('hasta')->label('Hasta'),
                    TextInput::make('presupuesto')->label('Presupuesto (S/)')->numeric()->minValue(0),
                    Select::make('responsable_user_id')->label('Responsable')->options(fn (): array => DB::table('users')->where('es_sistema', false)->orderBy('name')->pluck('name', 'id')->all()),
                    Textarea::make('objetivo')->label('Objetivo')->rows(3),
                    Toggle::make('es_activa')->label('Activa'),
                ])
                ->action(function (array $data): void {
                    $this->record->update($data);
                    Notification::make()->success()->title('Campaña guardada.')->send();
                }),
        ];
    }

    /** @return array<string, mixed> */
    public function datosFicha(): array
    {
        /** @var Campania $c */
        $c = $this->record->refresh();
        $s = app(CampaniasService::class);
        $canal = $c->canal_lead_id === null ? null : (app(LeadsService::class)->canales()[$c->canal_lead_id] ?? null);

        return [
            'c' => $c, 'tarjeta' => $s->tarjeta($c), 'estado' => $s->estado($c), 'canal' => $canal,
            'responsable' => $c->responsable_user_id === null ? null : DB::table('users')->where('id', $c->responsable_user_id)->value('name'),
            'contactos' => $s->contactosLista($c->id), 'oportunidades' => $s->oportunidadesLista($c->id), 'gastos' => $s->gastosLista($c->id),
            'ficha' => $s->ficha($c), 'actividad' => $s->actividad($c), 'atajo' => $this->datosAtajo(), 'volver' => $this->volver,
        ];
    }
}
