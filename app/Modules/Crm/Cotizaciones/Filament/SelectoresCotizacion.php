<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Filament;

use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Oportunidades\Services\OportunidadesService;
use App\Modules\Crm\Oportunidades\Services\ResumenOportunidad;
use App\Modules\Nucleo\Parties\ResumenContacto;
use App\Modules\Nucleo\Services\PartiesService;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Controles de presentación de las acciones de Cotizaciones (D4, D19). Quien valida es el servicio. Siempre ofrecen
 * también el valor que el registro ya tiene (AGENTS.md).
 */
final class SelectoresCotizacion
{
    /**
     * D4: solo oportunidades abiertas que ya existen, por número o nombre (C13). Cotizaciones no crea oportunidades: se
     * crean en Oportunidades (desarrollador, 2026-11-12).
     */
    public static function oportunidad(string $nombre = 'oportunidad_id'): Select
    {
        $opciones = fn (array $resumenes): array => collect($resumenes)
            ->mapWithKeys(fn (ResumenOportunidad $o): array => [$o->id => $o->etiqueta()])->all();

        return Select::make($nombre)->searchable()->live()
            ->getSearchResultsUsing(fn (string $search): array => $opciones(app(OportunidadesService::class)->buscar($search)))
            ->getOptionLabelUsing(fn (mixed $value): ?string => is_numeric($value)
                ? ($opciones(app(OportunidadesService::class)->buscar('', null, (int) $value, 1))[(int) $value] ?? null)
                : null)
            ->helperText('Solo oportunidades abiertas. Si no existe, créala primero en Oportunidades.');
    }

    /** D4: «Atención», un contacto vigente de la cuenta de la oportunidad elegida, más el que ya tiene. */
    public static function contacto(string $nombre = 'contacto_id', string $campoOportunidad = 'oportunidad_id', ?int $oportunidadFija = null): Select
    {
        return Select::make($nombre)->searchable()->options(function (Get $get, mixed $state) use ($campoOportunidad, $oportunidadFija): array {
            $oportunidadId = $oportunidadFija ?? (is_numeric($get($campoOportunidad)) ? (int) $get($campoOportunidad) : null);

            if ($oportunidadId === null) {
                return [];
            }

            $cuentaId = app(OportunidadesService::class)->resumen($oportunidadId)->cuentaId;
            $partyId = app(CuentasService::class)->resumen($cuentaId)->partyId;

            $opciones = collect(app(PartiesService::class)->contactosVigentes($partyId))
                ->mapWithKeys(fn (ResumenContacto $c): array => [$c->id => $c->etiqueta])->all();

            if (is_numeric($state) && ! array_key_exists((int) $state, $opciones)) {
                $opciones[(int) $state] = 'Contacto anterior (vínculo cerrado)';
            }

            return $opciones;
        });
    }
}
