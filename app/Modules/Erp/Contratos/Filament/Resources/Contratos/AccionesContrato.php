<?php

declare(strict_types=1);

namespace App\Modules\Erp\Contratos\Filament\Resources\Contratos;

use App\Modules\Erp\Contratos\Models\Contrato;
use App\Modules\Erp\Contratos\Services\ContratosService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use RuntimeException;

/**
 * Observación 6.11: los botones del contrato en un solo lugar, para la ficha (`VerContrato` y sus pestañas) y para el
 * panel deslizante de `/contratos`. Cada uno recibe cómo encontrar su contrato (`$contrato`, que puede leer los
 * argumentos de la acción) y qué hacer después de un éxito (`$despues`, p. ej. recargar la ficha).
 */
final class AccionesContrato
{
    /** @param  Closure(array<string, mixed>): Contrato  $contrato */
    public static function activar(Closure $contrato, ?Closure $despues = null, string $nombre = 'activar'): Action
    {
        return Action::make($nombre)->label('Completar y activar')->icon('heroicon-o-check-badge')->color('success')->slideOver()
            ->visible(fn (): bool => ContratoResource::puede(ContratosService::PERMISO_GESTIONAR))
            ->modalHeading(fn (array $arguments): string => 'Completar y activar '.$contrato($arguments)->codigo)
            ->fillForm(fn (array $arguments): array => ['firma' => Calendario::hoy(), 'forma_pago' => $contrato($arguments)->forma_pago ?? '100 % a la conformidad', 'garantia' => $contrato($arguments)->garantia_meses ?? 24,
                'tope' => '10', 'factor' => ContratosService::factor($contrato($arguments)->plazo_dias)])
            ->schema([
                TextInput::make('numero')->label('N.° de contrato de la entidad')->required()->maxLength(80)->placeholder('Contrato N.° 045-2026-MPVN'),
                DatePicker::make('firma')->label('Fecha de firma')->required()->format('Y-m-d'),
                TextInput::make('forma_pago')->label('Forma de pago')->maxLength(200),
                TextInput::make('garantia')->label('Garantía técnica (meses)')->numeric()->integer(),
                Select::make('factor')->label('Factor F de la penalidad')->options(['0.40' => '0.40 (plazo hasta 60 días)', '0.25' => '0.25 (plazo mayor)'])->required(),
                TextInput::make('tope')->label('Tope de penalidad (%)')->numeric()->required(),
            ])
            ->action(fn (array $data, array $arguments) => self::hacer(fn () => app(ContratosService::class)->activar($contrato($arguments)->id, $data['numero'], CarbonImmutable::parse($data['firma']),
                $data['forma_pago'] ?? null, filled($data['garantia'] ?? null) ? (int) $data['garantia'] : null, (string) $data['tope'], $data['factor']), 'Contrato vigente: hitos programados.', $despues));
    }

    /** @param  Closure(array<string, mixed>): Contrato  $contrato */
    public static function penalidad(Closure $contrato, ?Closure $despues = null, string $nombre = 'penalidad'): Action
    {
        $s = fn (): ContratosService => app(ContratosService::class);

        return Action::make($nombre)->label('Aplicar penalidad')->icon('heroicon-o-exclamation-triangle')->color('danger')
            ->visible(fn (): bool => ContratoResource::puede(ContratosService::PERMISO_GESTIONAR))
            ->modalDescription(fn (array $arguments): string => 'Penalidad diaria '.ContratoResource::soles($s()->penalidadDiaria($contrato($arguments))).'; nunca pasa el tope de '.ContratoResource::soles($s()->tope($contrato($arguments))).'.')
            ->fillForm(function (array $arguments) use ($contrato, $s): array {
                $c = $contrato($arguments);
                $vencido = $c->estado === 'vigente' ? -$s()->diasRestantes($c) : 0;

                return ['fecha' => Calendario::hoy(), 'dias' => $vencido > 0 ? $vencido : null, 'motivo' => $vencido > 0 ? "Atraso de {$vencido} días en la conformidad." : null];
            })
            ->schema([
                TextInput::make('dias')->label('Días de atraso')->numeric()->integer()->minValue(1)->required(),
                DatePicker::make('fecha')->label('Fecha')->required()->format('Y-m-d'),
                TextInput::make('motivo')->label('Motivo')->required()->maxLength(300),
            ])
            ->action(fn (array $data, array $arguments) => self::hacer(fn () => $s()->aplicarPenalidad($contrato($arguments)->id, (int) $data['dias'], CarbonImmutable::parse($data['fecha']), $data['motivo']),
                'Penalidad aplicada: baja lo vendido del proyecto.', $despues));
    }

    /** @param  Closure(array<string, mixed>): Contrato  $contrato */
    public static function adenda(Closure $contrato, ?Closure $despues = null, string $nombre = 'adenda'): Action
    {
        return Action::make($nombre)->label('Registrar adenda')->icon('heroicon-o-document-plus')
            ->visible(fn (): bool => ContratoResource::puede(ContratosService::PERMISO_GESTIONAR))
            ->fillForm(fn (array $arguments): array => ['fecha' => Calendario::hoy(), 'numero' => 'Adenda N.° '.($contrato($arguments)->adendas()->count() + 1), 'monto' => '0', 'dias' => 0])
            ->schema([
                TextInput::make('numero')->label('Número')->required()->maxLength(40),
                DatePicker::make('fecha')->label('Fecha')->required()->format('Y-m-d'),
                TextInput::make('motivo')->label('Motivo')->required()->maxLength(300),
                TextInput::make('monto')->label('Monto adicional con IGV (negativo si es deductivo)')->numeric()->required(),
                TextInput::make('dias')->label('Días adicionales')->numeric()->integer()->required(),
            ])
            ->action(fn (array $data, array $arguments) => self::hacer(fn () => app(ContratosService::class)->registrarAdenda($contrato($arguments)->id, $data['numero'], CarbonImmutable::parse($data['fecha']),
                $data['motivo'], (string) $data['monto'], (int) $data['dias']), 'Adenda registrada: cambian el monto, el plazo y la penalidad diaria.', $despues));
    }

    /** @param  Closure(array<string, mixed>): Contrato  $contrato */
    public static function garantia(Closure $contrato, ?Closure $despues = null, string $nombre = 'garantia'): Action
    {
        return Action::make($nombre)->label('Registrar garantía')->icon('heroicon-o-shield-check')
            ->visible(fn (): bool => ContratoResource::puede(ContratosService::PERMISO_GESTIONAR))
            ->fillForm(fn (array $arguments): array => ['tipo' => 'fiel_cumplimiento', 'monto' => app(ContratosService::class)->tope($contrato($arguments)), 'emision' => Calendario::hoy(),
                'vencimiento' => app(ContratosService::class)->fin($contrato($arguments))->addDays(30)->toDateString(), 'estado' => 'vigente'])
            ->schema([
                Select::make('tipo')->label('Tipo')->options(ContratosService::TIPOS_GARANTIA)->required(),
                TextInput::make('banco')->label('Banco')->required()->maxLength(120),
                TextInput::make('numero')->label('N.° de carta fianza')->maxLength(60),
                TextInput::make('monto')->label('Monto garantizado')->numeric()->required(),
                DatePicker::make('emision')->label('Emisión')->required()->format('Y-m-d'),
                DatePicker::make('vencimiento')->label('Vencimiento')->required()->format('Y-m-d'),
                TextInput::make('comision')->label('Comisión del banco')->numeric()->helperText('Se imputa a los gastos generales del proyecto.'),
                Select::make('estado')->label('Estado')->options(ContratosService::ESTADOS_GARANTIA)->required(),
            ])
            ->action(fn (array $data, array $arguments) => self::hacer(fn () => app(ContratosService::class)->registrarGarantia($contrato($arguments)->id, $data['tipo'], $data['banco'], $data['numero'] ?? null,
                (string) $data['monto'], CarbonImmutable::parse($data['emision']), CarbonImmutable::parse($data['vencimiento']), filled($data['comision'] ?? null) ? (string) $data['comision'] : null, $data['estado']),
                'Garantía registrada.', $despues));
    }

    /** El contrato de los argumentos de la acción (`['contrato' => id]`). */
    public static function delArgumento(): Closure
    {
        return fn (array $arguments): Contrato => Contrato::query()->findOrFail((int) ($arguments['contrato'] ?? 0));
    }

    private static function hacer(Closure $paso, string $exito, ?Closure $despues): void
    {
        try {
            $paso();
        } catch (RuntimeException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            return;
        }

        Notification::make()->success()->title($exito)->send();

        if ($despues !== null) {
            $despues();
        }
    }
}
