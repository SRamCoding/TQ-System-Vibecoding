<?php

declare(strict_types=1);

namespace App\Modules\Crm\Licitaciones\Filament;

use App\Modules\Crm\Licitaciones\Enums\EstadoLicitacion;
use App\Modules\Crm\Licitaciones\Filament\Resources\Licitaciones\LicitacionResource;
use App\Modules\Crm\Licitaciones\Models\Licitacion;
use App\Modules\Crm\Licitaciones\Services\LicitacionesService;
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
use RuntimeException;

/**
 * Las acciones de una licitación según su estado (participar, no participar, aceptar la lista sugerida, registrar
 * propuesta y registrar resultado). Las usan su ficha y, desde MV-023, la pestaña «Licitación» de la oportunidad.
 */
final class AccionesLicitacion
{
    /**
     * @param  Closure(): Licitacion  $registro
     * @param  Closure(Closure, string): void  $hacer
     * @return array<string, Action>
     */
    public static function todas(Closure $registro, Closure $hacer): array
    {
        $en = fn (EstadoLicitacion ...$estados): Closure => fn (): bool => in_array($registro()->estado, $estados, true) && LicitacionResource::puede(LicitacionesService::PERMISO_GESTIONAR);
        $usuario = fn (): User => auth()->user() instanceof User ? auth()->user() : throw new RuntimeException('Sin sesión.');

        return [
            'participar' => Action::make('participar')->label('Participar')->icon('heroicon-o-check-circle')->color('success')
                ->visible($en(EstadoLicitacion::Registrada))
                ->modalHeading('Decidir participar')
                ->fillForm(fn (): array => ['responsable_user_id' => auth()->id()])
                ->schema([Select::make('responsable_user_id')->label('Responsable')->required()->searchable()
                    ->options(fn (): array => User::query()->where('es_activo', true)->where('es_sistema', false)->orderBy('name')->pluck('name', 'id')->all())])
                ->action(fn (array $data) => $hacer(fn () => app(LicitacionesService::class)->participar($registro()->id, (int) $data['responsable_user_id'], $usuario()), 'Participamos: nació su oportunidad.')),
            'noParticipar' => Action::make('noParticipar')->label('No participar')->icon('heroicon-o-x-circle')->color('gray')
                ->visible($en(EstadoLicitacion::Registrada))
                ->schema([Textarea::make('motivo')->label('¿Por qué no participamos?')->required()->maxLength(300)
                    ->helperText('Muestra dónde está el techo comercial: experiencia, fianza, plazo, precio…')])
                ->action(fn (array $data) => $hacer(fn () => app(LicitacionesService::class)->noParticipar($registro()->id, $data['motivo']), 'Anotado.')),
            'confirmarSugerencias' => Action::make('confirmarSugerencias')->label('Aceptar lista sugerida')->icon('heroicon-o-sparkles')->color('gray')
                ->visible(fn (): bool => $en(EstadoLicitacion::EnPreparacion)() && $registro()->documentos()->where('es_confirmado', false)->exists())
                ->requiresConfirmation()->modalDescription('La lista del expediente que la IA leyó de las bases queda confirmada. Puedes quitar o agregar documentos en la pestaña Expediente.')
                ->action(fn () => $hacer(fn () => app(LicitacionesService::class)->confirmarSugerencias($registro()->id), 'Lista confirmada.')),
            'presentar' => Action::make('presentar')->label('Registrar propuesta')->icon('heroicon-o-paper-airplane')->color('primary')
                ->visible($en(EstadoLicitacion::EnPreparacion))
                ->modalDescription('No se presenta con faltantes: todo el expediente confirmado y listo.')
                ->fillForm(fn (): array => ['monto' => $registro()->valor_referencial, 'fecha' => Calendario::hoy()])
                ->schema([
                    TextInput::make('monto')->label('Monto ofertado (S/)')->numeric()->required()->minValue(0.01),
                    DatePicker::make('fecha')->label('Presentada el')->required()->format('Y-m-d'),
                ])
                ->action(fn (array $data) => $hacer(fn () => app(LicitacionesService::class)->presentar($registro()->id, (string) $data['monto'], CarbonImmutable::parse($data['fecha'])), 'Propuesta registrada.')),
            'resultado' => Action::make('resultado')->label('Registrar resultado')->icon('heroicon-o-trophy')->color('warning')
                ->visible($en(EstadoLicitacion::Presentada))->slideOver()
                ->fillForm(fn (): array => ['resultado' => EstadoLicitacion::BuenaPro->value, 'fecha' => Calendario::hoy(), 'participantes' => [
                    ['nombre' => 'Techquk S.A.C.', 'es_techquk' => true, 'oferta' => $registro()->monto_ofertado, 'puesto' => 1],
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
                ->action(fn (array $data) => $hacer(fn () => app(LicitacionesService::class)->registrarResultado(
                    $registro()->id, EstadoLicitacion::from($data['resultado']),
                    array_map(fn (array $p): array => [
                        'nombre' => $p['nombre'], 'party_id' => filled($p['party_id'] ?? null) ? (int) $p['party_id'] : null, 'es_techquk' => (bool) ($p['es_techquk'] ?? false),
                        'oferta' => filled($p['oferta'] ?? null) ? (string) $p['oferta'] : null, 'puntaje' => filled($p['puntaje'] ?? null) ? (string) $p['puntaje'] : null,
                        'puesto' => filled($p['puesto'] ?? null) ? (int) $p['puesto'] : null,
                    ], array_values($data['participantes'] ?? [])),
                    CarbonImmutable::parse($data['fecha']), $usuario(),
                ), 'Resultado registrado.')),
        ];
    }
}
