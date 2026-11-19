<?php

declare(strict_types=1);

namespace App\Modules\Crm\Licitaciones\Filament\Resources\Licitaciones\Pages;

use App\Modules\Crm\Licitaciones\Enums\EstadoLicitacion;
use App\Modules\Crm\Licitaciones\Filament\Resources\Licitaciones\LicitacionResource;
use App\Modules\Crm\Licitaciones\Models\Licitacion;
use App\Modules\Crm\Licitaciones\Services\LicitacionesService;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\VerOportunidad;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use RuntimeException;

/**
 * MVP rápido (t1-licitaciones): la ficha. Arriba, las acciones de su estado; abajo, «Datos generales» y las pestañas
 * Cronograma, Expediente, Consultas y Resultado.
 */
final class VerLicitacion extends ViewRecord
{
    protected static string $resource = LicitacionResource::class;

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return 'Datos generales';
    }

    public function getTitle(): string
    {
        return "{$this->registro()->codigo} · ".$this->registro()->cuenta?->party?->razon_social;
    }

    public function getSubheading(): string
    {
        $l = $this->registro();
        $proximo = $l->hitos()->where('es_cumplido', false)->first();
        $faltan = $l->documentos()->where(fn ($q) => $q->where('es_listo', false)->orWhere('es_confirmado', false))->count();
        $partes = [$l->estado->etiqueta()];

        if ($l->estado->esAbierta() && $proximo !== null) {
            $dias = (int) CarbonImmutable::parse(Calendario::hoy())->diffInDays($proximo->fecha, false);
            $partes[] = "{$proximo->etapa} ".($dias >= 0 ? "en {$dias} días" : 'vencida hace '.abs($dias).' días');
        }

        if ($l->estado === EstadoLicitacion::EnPreparacion) {
            $partes[] = $faltan === 0 ? 'expediente completo' : "faltan {$faltan} documentos del expediente";
        }

        if ($l->responsable !== null) {
            $partes[] = "responsable: {$l->responsable->name}";
        }

        return implode(' · ', $partes);
    }

    private function registro(): Licitacion
    {
        /** @var Licitacion $l */
        $l = $this->record;

        return $l;
    }

    protected function getHeaderActions(): array
    {
        $en = fn (EstadoLicitacion ...$estados): Closure => fn (): bool => in_array($this->registro()->estado, $estados, true) && LicitacionResource::puede(LicitacionesService::PERMISO_GESTIONAR);
        $usuario = fn (): User => auth()->user() instanceof User ? auth()->user() : throw new RuntimeException('Sin sesión.');

        return [
            Action::make('participar')->label('Participar')->icon('heroicon-o-check-circle')->color('success')
                ->visible($en(EstadoLicitacion::Registrada))
                ->modalHeading('Decidir participar')
                ->modalDescription('Nace su oportunidad con el valor referencial y la IA propone la lista del expediente desde las bases.')
                ->fillForm(fn (): array => ['responsable_user_id' => auth()->id()])
                ->schema([Select::make('responsable_user_id')->label('Responsable')->required()->searchable()
                    ->options(fn (): array => User::query()->where('es_activo', true)->where('es_sistema', false)->orderBy('name')->pluck('name', 'id')->all())])
                ->action(fn (array $data) => $this->hacer(fn () => app(LicitacionesService::class)->participar($this->registro()->id, (int) $data['responsable_user_id'], $usuario()), 'Participamos: nació su oportunidad.')),
            Action::make('noParticipar')->label('No participar')->icon('heroicon-o-x-circle')->color('gray')
                ->visible($en(EstadoLicitacion::Registrada))
                ->schema([Textarea::make('motivo')->label('¿Por qué no participamos?')->required()->maxLength(300)
                    ->helperText('Muestra dónde está el techo comercial: experiencia, fianza, plazo, precio…')])
                ->action(fn (array $data) => $this->hacer(fn () => app(LicitacionesService::class)->noParticipar($this->registro()->id, $data['motivo']), 'Anotado.')),
            Action::make('confirmarSugerencias')->label('Aceptar lista sugerida')->icon('heroicon-o-sparkles')->color('gray')
                ->visible(fn (): bool => $en(EstadoLicitacion::EnPreparacion)() && $this->registro()->documentos()->where('es_confirmado', false)->exists())
                ->requiresConfirmation()->modalDescription('La lista del expediente que la IA leyó de las bases queda confirmada. Puedes quitar o agregar documentos en la pestaña Expediente.')
                ->action(fn () => $this->hacer(fn () => app(LicitacionesService::class)->confirmarSugerencias($this->registro()->id), 'Lista confirmada.')),
            Action::make('presentar')->label('Registrar propuesta')->icon('heroicon-o-paper-airplane')->color('primary')
                ->visible($en(EstadoLicitacion::EnPreparacion))
                ->modalDescription('No se presenta con faltantes: todo el expediente confirmado y listo.')
                ->fillForm(fn (): array => ['monto' => $this->registro()->valor_referencial, 'fecha' => Calendario::hoy()])
                ->schema([
                    TextInput::make('monto')->label('Monto ofertado (S/)')->numeric()->required()->minValue(0.01),
                    DatePicker::make('fecha')->label('Presentada el')->required()->format('Y-m-d'),
                ])
                ->action(fn (array $data) => $this->hacer(fn () => app(LicitacionesService::class)->presentar($this->registro()->id, (string) $data['monto'], CarbonImmutable::parse($data['fecha'])), 'Propuesta registrada.')),
            Action::make('resultado')->label('Registrar resultado')->icon('heroicon-o-trophy')->color('warning')
                ->visible($en(EstadoLicitacion::Presentada))->slideOver()
                ->modalDescription('Se completa gane quien gane: el precio al que adjudicó la entidad es más útil cuando se perdió.')
                ->fillForm(fn (): array => ['resultado' => EstadoLicitacion::BuenaPro->value, 'fecha' => Calendario::hoy(), 'participantes' => [
                    ['nombre' => 'Techquk S.A.C.', 'es_techquk' => true, 'oferta' => $this->registro()->monto_ofertado, 'puesto' => 1],
                ]])
                ->schema([
                    Select::make('resultado')->label('Resultado')->required()->options([
                        EstadoLicitacion::BuenaPro->value => 'Buena pro para nosotros', EstadoLicitacion::Perdida->value => 'La ganó otro', EstadoLicitacion::Desierta->value => 'Desierta',
                    ]),
                    DatePicker::make('fecha')->label('Fecha')->required()->format('Y-m-d'),
                    Repeater::make('participantes')->label('Participantes')->columns(5)->schema([
                        TextInput::make('nombre')->label('Postor')->required()->columnSpan(2),
                        Select::make('party_id')->label('Ficha')->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => Party::query()->where('razon_social', 'ilike', "%{$search}%")->limit(20)->pluck('razon_social', 'id')->all())
                            ->getOptionLabelUsing(fn ($value): ?string => Party::query()->whereKey($value)->value('razon_social')),
                        TextInput::make('oferta')->label('Oferta')->numeric(),
                        TextInput::make('puntaje')->label('Puntaje')->numeric(),
                        TextInput::make('puesto')->label('Puesto')->numeric()->integer(),
                        Toggle::make('es_techquk')->label('Somos nosotros'),
                    ]),
                ])
                ->action(fn (array $data) => $this->hacer(fn () => app(LicitacionesService::class)->registrarResultado(
                    $this->registro()->id, EstadoLicitacion::from($data['resultado']),
                    array_map(fn (array $p): array => [
                        'nombre' => $p['nombre'], 'party_id' => filled($p['party_id'] ?? null) ? (int) $p['party_id'] : null, 'es_techquk' => (bool) ($p['es_techquk'] ?? false),
                        'oferta' => filled($p['oferta'] ?? null) ? (string) $p['oferta'] : null, 'puntaje' => filled($p['puntaje'] ?? null) ? (string) $p['puntaje'] : null,
                        'puesto' => filled($p['puesto'] ?? null) ? (int) $p['puesto'] : null,
                    ], array_values($data['participantes'] ?? [])),
                    CarbonImmutable::parse($data['fecha']), $usuario(),
                ), 'Resultado registrado.')),
            Action::make('irOportunidad')->label('Ir a la oportunidad')->icon('heroicon-o-arrow-top-right-on-square')->color('gray')
                ->visible(fn (): bool => $this->registro()->oportunidad_id !== null)
                ->url(fn (): string => VerOportunidad::getUrl(['record' => $this->registro()->oportunidad_id])),
        ];
    }

    private function hacer(Closure $paso, string $exito): void
    {
        try {
            $paso();
        } catch (RuntimeException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            return;
        }

        Notification::make()->success()->title($exito)->send();
        $this->record->refresh();
        $this->redirect(self::getUrl(['record' => $this->record]));
    }
}
