<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Resources\Parties;

use App\Modules\Nucleo\Models\PartyContacto;
use App\Modules\Nucleo\Parties\ResumenContacto;
use App\Modules\Nucleo\Parties\ResumenParty;
use App\Modules\Nucleo\Services\PartiesService;
use Filament\Forms\Components\Select;

/**
 * Selectores de las pestañas de la ficha (plan 1.3b): una party por buscador, o un contacto vigente de la party.
 * Solo presentación: la acción y su servicio validan igual. Siempre ofrecen también el valor que el registro ya
 * tiene, aunque hoy no se pudiera elegir (AGENTS.md: si no, Filament no deja guardar).
 */
final class SelectoresParty
{
    /** Busca por nombre o número (mínimo dos caracteres); con rol, solo las que lo tienen vigente. */
    public static function party(string $nombre, ?string $codigoRol = null, ?int $excluir = null): Select
    {
        return Select::make($nombre)
            ->searchable()
            ->getSearchResultsUsing(fn (string $search): array => collect(app(PartiesService::class)->buscar($search, $codigoRol))
                ->reject(fn (ResumenParty $p): bool => $p->id === $excluir)
                ->mapWithKeys(fn (ResumenParty $p): array => [$p->id => $p->etiqueta])
                ->all())
            ->getOptionLabelUsing(fn (mixed $value): ?string => is_numeric($value) ? app(PartiesService::class)->etiquetaDe((int) $value) : null);
    }

    /** Los contactos vigentes de la party, más el que la sede ya tiene. */
    public static function contactoDe(string $nombre, int $partyId): Select
    {
        return Select::make($nombre)
            ->searchable()
            ->options(function (mixed $state) use ($partyId): array {
                $opciones = collect(app(PartiesService::class)->contactosVigentes($partyId))
                    ->mapWithKeys(fn (ResumenContacto $c): array => [$c->id => $c->etiqueta])
                    ->all();

                if (is_numeric($state) && ! array_key_exists((int) $state, $opciones)) {
                    $actual = PartyContacto::query()->where('party_id', $partyId)->find((int) $state);

                    if ($actual !== null) {
                        $opciones[$actual->id] = $actual->nombreCompleto().' (vínculo cerrado)';
                    }
                }

                return $opciones;
            });
    }
}
