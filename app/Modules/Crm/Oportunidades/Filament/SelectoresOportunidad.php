<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Filament;

use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Cuentas\Services\ResumenCuenta;
use App\Modules\Nucleo\Excepciones\ErrorDeNegocio;
use App\Modules\Nucleo\Filament\AccionFilament;
use App\Modules\Nucleo\Filament\SelectorCatalogo;
use App\Modules\Nucleo\Models\PartyContacto;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Parties\ResumenContacto;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;
use App\Modules\Nucleo\Services\PartiesService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Exceptions\Halt;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Controles de presentación para las acciones de Oportunidades (plan 2.21, D5, D6, D9). Quien valida sigue siendo el
 * servicio. Siempre ofrecen también el valor que el registro ya tiene (AGENTS.md).
 */
final class SelectoresOportunidad
{
    /** D6: las cuentas usables por buscador (`CuentasService::buscar`); la elegida se muestra aunque ya no sea usable. */
    public static function cuenta(string $nombre = 'cuenta_id'): Select
    {
        return Select::make($nombre)
            ->searchable()
            ->getSearchResultsUsing(fn (string $search): array => self::opcionesDeCuentas(app(CuentasService::class)->buscar($search, 20)))
            ->getOptionLabelUsing(fn (mixed $value): ?string => is_numeric($value)
                ? (self::opcionesDeCuentas(app(CuentasService::class)->buscar('', 1, (int) $value))[(int) $value] ?? null)
                : null);
    }

    /**
     * D5: los usuarios activos que pueden atender cuentas y, si el valor actual dejó de poder, también ese, marcado
     * (como el de Cuentas: «quién vende» tiene una sola definición).
     */
    public static function ejecutivo(string $nombre = 'ejecutivo_user_id'): Select
    {
        return Select::make($nombre)->searchable()->options(function (mixed $state): array {
            $opciones = User::permission(CuentasService::PERMISO_ATENDER)
                ->where('es_activo', true)->where('es_sistema', false)
                ->orderBy('name')->pluck('name', 'id')->all();

            if (is_numeric($state) && ! isset($opciones[(int) $state])) {
                $actual = User::query()->whereKey((int) $state)->value('name');
                $opciones[(int) $state] = "{$actual} (ya no puede atender cuentas)";
            }

            return $opciones;
        });
    }

    /** D9: un contacto vigente de la ficha de la cuenta (leída del campo de apoyo `party_id`), más el que ya tiene. */
    public static function decisor(string $nombre = 'decisor_contacto_id', bool $conRegistrar = false): Select
    {
        $selector = Select::make($nombre)->searchable()->options(function (Get $get, mixed $state): array {
            $partyId = (int) $get('party_id');
            $opciones = collect(app(PartiesService::class)->contactosVigentes($partyId))
                ->mapWithKeys(fn (ResumenContacto $c): array => [$c->id => $c->esDecisor ? "{$c->etiqueta} (decisor)" : $c->etiqueta])
                ->all();

            if (is_numeric($state) && ! array_key_exists((int) $state, $opciones)) {
                $actual = PartyContacto::query()->where('party_id', $partyId)->find((int) $state);

                if ($actual !== null) {
                    $opciones[$actual->id] = $actual->nombreCompleto().' (vínculo cerrado)';
                }
            }

            return $opciones;
        });

        return $conRegistrar ? self::conRegistrarDecisor($selector) : $selector;
    }

    /**
     * Plan 2.21d, D8: si el decisor no está entre los contactos de la cuenta, se registra ahí mismo con la acción del
     * núcleo `nucleo.parties.agregar-contacto` (queda marcado como quien decide la compra) y queda elegido.
     */
    private static function conRegistrarDecisor(Select $selector): Select
    {
        $clave = 'nucleo.parties.agregar-contacto';

        return $selector
            ->createOptionForm(fn (): array => array_values(array_filter(array_map(
                fn (string $campo): ?Field => in_array($campo, ['nombres', 'apellidos', 'cargo', 'area', 'telefono', 'email'], true) ? AccionFilament::campo($clave, $campo) : null,
                array_map(fn ($c): string => $c->nombre, app(RegistroAcciones::class)->obtener($clave)->campos()),
            ))))
            ->createOptionUsing(function (array $data, Get $get) use ($clave): int {
                $usuario = Filament::auth()->user();

                try {
                    $contacto = app(EjecutorAcciones::class)->ejecutar($clave, [...$data, 'party_id' => (int) $get('party_id'), 'es_decisor' => true], $usuario instanceof User ? $usuario : throw new AuthorizationException);

                    return (int) (is_object($contacto) && isset($contacto->id) ? $contacto->id : $contacto);
                } catch (ErrorDeNegocio|AuthorizationException|ValidationException $e) {
                    Notification::make()->danger()->title($e instanceof ValidationException ? (string) collect($e->errors())->flatten()->first() : ($e->getMessage() ?: 'No tienes permiso para registrar contactos.'))->send();

                    throw new Halt;
                }
            })
            ->createOptionAction(fn (Action $action): Action => $action->label('Registrar otro contacto')->modalHeading('Registrar el decisor')->modalSubmitActionLabel('Registrar y elegir')
                ->visible(fn (): bool => SelectorCatalogo::puedeAgregar($clave)));
    }

    /**
     * @param  list<ResumenCuenta>  $cuentas
     * @return array<int, string>
     */
    private static function opcionesDeCuentas(array $cuentas): array
    {
        $opciones = [];

        foreach ($cuentas as $cuenta) {
            $opciones[$cuenta->id] = $cuenta->etiquetaParaSelector();
        }

        return $opciones;
    }
}
