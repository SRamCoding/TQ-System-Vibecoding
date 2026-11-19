<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Filament;

use App\Modules\Crm\Oportunidades\Enums\LugarFaja;
use App\Modules\Crm\Oportunidades\Enums\TallaDeGema;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\VerOportunidad;
use App\Modules\Crm\Oportunidades\Services\FilaFaja;
use App\Modules\Crm\Oportunidades\Services\OportunidadesService;
use App\Modules\Crm\Oportunidades\Services\TableroDeBotonera;
use App\Modules\Nucleo\Models\User;

/**
 * Lo que dibuja el tablero de botonera (plan 2.21d, D3–D6), sin cuentas en la vista: por columna (las cuatro etapas,
 * Ganadas y Perdidas), su gema en el título, la botonera con la cantidad, los códigos que la persona puso (hasta 5) y,
 * si es la abierta, la tabla de la etapa con lo marcado. **Sin montos** (D3): ya están en el embudo de arriba.
 */
final readonly class DibujoDeLaBotonera
{
    /** Cuántas filas trae la tabla de una etapa de una vez. */
    public const int FILAS_DE_LA_TABLA = 50;

    /** @return list<array<string, mixed>> */
    public function columnas(User $quien, ?string $abierta): array
    {
        $servicio = app(OportunidadesService::class);
        $puestas = app(TableroDeBotonera::class)->puestas($quien);
        $columnas = [];

        foreach (LugarFaja::cases() as $lugar) {
            $ids = $puestas[$lugar->value];
            $siguiente = $lugar->etapa()?->siguiente();

            $columnas[] = [
                'lugar' => $lugar->value,
                'estado' => $lugar->estado(),
                'nombre' => $lugar->etiqueta(),
                'talla' => TallaDeGema::desdeLugar($lugar),
                'cantidad' => $servicio->totalDeLugarDeLaFaja($lugar)->cantidad,
                'puestas' => array_map(fn (int $id): array => self::codigo($servicio->filaDeLaFaja($id)), $ids),
                'lleno' => count($ids) >= TableroDeBotonera::MAXIMO,
                'abierta' => $abierta === $lugar->value,
                // «Avanzar a …» desde la tabla (D6): a la etapa siguiente; desde Negociación, a Ganadas.
                'avanzaA' => match (true) {
                    $siguiente !== null => ['estado' => $siguiente->value, 'nombre' => $siguiente->etiqueta()],
                    $lugar === LugarFaja::Negociacion => ['estado' => MovimientoDeOportunidad::GANADA, 'nombre' => LugarFaja::Ganadas->etiqueta()],
                    default => null,
                },
                'filas' => $abierta === $lugar->value
                    ? array_map(fn (FilaFaja $f): array => self::fila($f, $ids), $servicio->tablaDeLaFaja($lugar, porPagina: self::FILAS_DE_LA_TABLA)->filas)
                    : [],
            ];
        }

        return $columnas;
    }

    /** @return array<string, mixed> */
    private static function codigo(FilaFaja $f): array
    {
        return ['id' => $f->id, 'numero' => $f->numero, 'titulo' => "{$f->nombre} · {$f->cuenta}", 'version' => $f->version, 'estado' => $f->etapa];
    }

    /**
     * @param  list<int>  $puestas
     * @return array<string, mixed>
     */
    private static function fila(FilaFaja $f, array $puestas): array
    {
        return [
            'id' => $f->id,
            'numero' => $f->numero,
            'nombre' => $f->nombre,
            'cuenta' => $f->cuenta,
            'monto' => PanelesOportunidad::dinero($f->monto, $f->simbolo),
            'dias' => $f->diasEnEtapa,
            'atascada' => $f->atascada,
            'cierre' => $f->fechaCierre?->format('d/m/Y'),
            'motivo' => $f->motivo,
            'version' => $f->version,
            'estado' => $f->etapa,
            'puesta' => in_array($f->id, $puestas, true),
            'url' => VerOportunidad::getUrl(['record' => $f->id]),
        ];
    }
}
