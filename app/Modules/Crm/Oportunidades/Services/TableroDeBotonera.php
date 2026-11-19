<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Services;

use App\Modules\Crm\Oportunidades\Enums\LugarFaja;
use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Crm\Oportunidades\Models\OportunidadEnTablero;
use App\Modules\Nucleo\Models\Estado;
use App\Modules\Nucleo\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Lo que cada persona puso en su tablero (plan 2.21d, D5): en la tabla de una etapa marca oportunidades y esas pasan al
 * tablero solo con su código, apiladas, **hasta 5 por etapa**; se quitan con su ×. Se recuerdan por persona
 * (`oportunidades_en_tablero`). Al avanzar, el código se va con su oportunidad a la columna nueva; si allí ya hay 5, la
 * columna muestra las 5 primeras y la tabla de la etapa sigue teniendo todas (el tope se exige al marcar).
 */
final class TableroDeBotonera
{
    public const int MAXIMO = 5;

    public const string FIJAR = 'crm.oportunidades.fijar-en-tablero';

    public const string SOLTAR = 'crm.oportunidades.soltar-del-tablero';

    /**
     * Las puestas por la persona, por lugar del tablero, en el orden en que las puso; sin las anuladas. A lo sumo
     * MAXIMO por lugar.
     *
     * @return array<string, list<int>> por valor de LugarFaja
     */
    public function puestas(User $quien): array
    {
        $porLugar = array_fill_keys(array_map(fn (LugarFaja $l): string => $l->value, LugarFaja::cases()), []);
        $filas = OportunidadEnTablero::query()->where('oportunidades_en_tablero.user_id', $quien->id)
            ->join('oportunidades', 'oportunidades.id', '=', 'oportunidades_en_tablero.oportunidad_id')
            ->join('estados', 'estados.id', '=', 'oportunidades.estado_id')
            ->orderBy('oportunidades_en_tablero.id')
            ->get(['oportunidades_en_tablero.oportunidad_id', 'estados.codigo']);

        foreach ($filas as $fila) {
            $lugar = self::lugarDe((string) $fila->getAttribute('codigo'));

            if ($lugar !== null && count($porLugar[$lugar->value]) < self::MAXIMO) {
                $porLugar[$lugar->value][] = (int) $fila->getAttribute('oportunidad_id');
            }
        }

        return $porLugar;
    }

    /** @return list<int> todas las puestas por la persona, para su lista */
    public function ids(User $quien): array
    {
        return array_merge(...array_values($this->puestas($quien)));
    }

    /** @throws OportunidadRechazada */
    public function poner(int $oportunidadId, User $quien): void
    {
        DB::transaction(function () use ($oportunidadId, $quien): void {
            // Una persona a la vez: dos pestañas que marcan a la vez no pasan el tope.
            User::query()->whereKey($quien->id)->lockForUpdate()->first();
            $oportunidad = Oportunidad::query()->with('estado')->find($oportunidadId) ?? throw OportunidadRechazada::conMotivo('La oportunidad no existe.');
            $lugar = self::lugarDe($oportunidad->estado->codigo) ?? throw OportunidadRechazada::conMotivo("{$oportunidad->numero} está anulada: no va al tablero.");

            if (OportunidadEnTablero::query()->where('user_id', $quien->id)->where('oportunidad_id', $oportunidadId)->exists()) {
                return;
            }

            if (count($this->puestas($quien)[$lugar->value]) >= self::MAXIMO) {
                throw OportunidadRechazada::conMotivo('En '.$lugar->etiqueta().' ya tienes '.self::MAXIMO.' en el tablero: quita una para poner otra.');
            }

            OportunidadEnTablero::query()->create(['user_id' => $quien->id, 'oportunidad_id' => $oportunidadId]);
        });
    }

    public function quitar(int $oportunidadId, User $quien): void
    {
        OportunidadEnTablero::query()->where('user_id', $quien->id)->where('oportunidad_id', $oportunidadId)->delete();
    }

    public function quitarTodas(User $quien): void
    {
        OportunidadEnTablero::query()->where('user_id', $quien->id)->delete();
    }

    public static function lugarDe(string $codigoEstado): ?LugarFaja
    {
        return match ($codigoEstado) {
            'ganada' => LugarFaja::Ganadas,
            'perdida' => LugarFaja::Perdidas,
            default => LugarFaja::tryFrom($codigoEstado),
        };
    }

    /** @return list<int> los estados de un lugar, para contar */
    public static function estadosDe(LugarFaja $lugar): array
    {
        return Estado::query()->where('documento_type', 'oportunidad')->where('codigo', $lugar->estado())->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
    }
}
