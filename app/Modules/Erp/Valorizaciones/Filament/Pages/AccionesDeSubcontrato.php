<?php

declare(strict_types=1);

namespace App\Modules\Erp\Valorizaciones\Filament\Pages;

use App\Modules\Erp\Valorizaciones\Models\Subcontrato;
use App\Modules\Erp\Valorizaciones\Models\SubcontratoValorizacion;
use App\Modules\Erp\Valorizaciones\Services\SubcontratosService;
use App\Modules\Erp\Valorizaciones\Services\ValorizacionesService;
use App\Modules\Nucleo\Filament\AccionFilament;
use App\Modules\Nucleo\Tiempo\Calendario;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;
use RuntimeException;

/**
 * Observaciones 6, punto 12: las operaciones de un subcontrato, iguales en la pantalla de tarjetas, su panel y su ficha.
 * Cada una recibe el subcontrato o la valorización en sus argumentos (`mountAction('aprobar', {id})`).
 */
trait AccionesDeSubcontrato
{
    public static function puedeGestionar(): bool
    {
        return auth()->user()?->can(ValorizacionesService::PERMISO_GESTIONAR) ?? false;
    }

    public static function soles(string|float|int|null $monto): string
    {
        return 'S/ '.number_format((float) $monto, 2);
    }

    public static function pct(?string $valor): string
    {
        return $valor === null ? '—' : number_format((float) $valor, 0).' %';
    }

    /** Aprobar una valorización del subcontratista: lo que hace no cambia; si el subcontrato tiene la alarma, la recuerda. */
    public function aprobarAction(): Action
    {
        return Action::make('aprobar')->label('Aprobar')->color('success')->icon('heroicon-o-check')->requiresConfirmation()->visible(fn (): bool => self::puedeGestionar())
            ->modalHeading(function (array $arguments): string {
                $v = SubcontratoValorizacion::query()->find($arguments['id'] ?? 0);

                return $v === null ? 'Aprobar' : 'Aprobar la valorización '.$v->numero.' de '.Subcontrato::query()->whereKey($v->subcontrato_id)->value('subcontratista');
            })
            ->modalDescription(function (array $arguments): ?HtmlString {
                $v = SubcontratoValorizacion::query()->find($arguments['id'] ?? 0);

                if ($v === null) {
                    return null;
                }

                $e = app(SubcontratosService::class)->estado((int) $v->subcontrato_id);
                $aviso = $e !== null && $e['alarma'] ? '<br><strong style="color: #C2532F">Le pagas '.self::pct($e['avance']).', cobraste '.self::pct($e['cliente']).'.</strong>' : '';

                return new HtmlString(e("{$v->periodo} · avance ".self::pct((string) $v->avance_pct).': '.self::soles($v->monto).' − '.self::soles($v->amortizacion).' − '.self::soles($v->retencion).' = neto '.self::soles($v->neto)).$aviso);
            })
            ->action(function (array $arguments): void {
                try {
                    app(ValorizacionesService::class)->aprobarSubcontrato((int) ($arguments['id'] ?? 0));
                    Notification::make()->success()->title('Aprobada: pasa a ejecutado en las partidas del alcance y queda por pagar.')->send();
                } catch (RuntimeException $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();
                }
            });
    }

    public function valorizarAction(): Action
    {
        return Action::make('valorizar')->label('Valorización del subcontratista')->icon('heroicon-o-plus')->visible(fn (): bool => self::puedeGestionar())
            ->fillForm(fn (array $arguments): array => ['subcontrato' => $arguments['sc'] ?? null, 'periodo' => Calendario::ahora()->format('Y-m')])
            ->schema([
                Select::make('subcontrato')->label('Subcontrato')->required()
                    ->options(fn (): array => Subcontrato::query()->where('estado', '<>', 'terminado')->orderBy('codigo')->get()->mapWithKeys(fn (Subcontrato $s): array => [$s->id => "{$s->codigo} · {$s->subcontratista}"])->all()),
                TextInput::make('periodo')->label('Periodo (AAAA-MM)')->required()->regex('/^\d{4}-\d{2}$/'),
                TextInput::make('avance')->label('Avance del periodo (%)')->numeric()->required()->minValue(0.01)->maxValue(100),
            ])
            ->action(function (array $data, Action $action): void {
                try {
                    app(ValorizacionesService::class)->valorizarSubcontrato((int) $data['subcontrato'], $data['periodo'], (string) $data['avance']);
                    Notification::make()->success()->title('Valorización registrada: queda por aprobar.')->send();
                } catch (RuntimeException $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();
                    $action->halt();
                }
            });
    }

    /** «Liberar retención», la acción registrada con su permiso (`erp.valorizaciones.liberar-retencion`). */
    public function erpValorizacionesLiberarRetencionAction(): Action
    {
        return AccionFilament::desde('erp.valorizaciones.liberar-retencion', fn (array $arguments): array => ['subcontrato' => (int) ($arguments['id'] ?? 0)],
            fn () => Notification::make()->success()->title('Retención liberada: queda por pagar al subcontratista y el subcontrato pasa a terminado.')->send())
            ->icon('heroicon-o-lock-open')
            ->modalHeading(fn (array $arguments): string => 'Liberar la retención de '.(Subcontrato::query()->whereKey($arguments['id'] ?? 0)->value('codigo') ?? 'el subcontrato'))
            ->modalDescription(function (array $arguments): ?string {
                $e = app(SubcontratosService::class)->estado((int) ($arguments['id'] ?? 0));

                return $e === null ? null : "{$e['subcontratista']} · retención acumulada ".self::soles($e['retencion']).': se devuelve y queda por pagar.';
            })
            ->fillForm(fn (): array => ['conformidad' => Calendario::hoy()]);
    }
}
