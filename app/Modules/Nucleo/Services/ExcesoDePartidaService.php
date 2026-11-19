<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Services;

use App\Modules\Nucleo\Excepciones\ExcesoDePartidaRechazado;
use App\Modules\Nucleo\Imputacion\FuenteDeDestinos;
use App\Modules\Nucleo\Imputacion\LineaDeCosto;
use App\Modules\Nucleo\Imputacion\LineaNoPresupuestada;
use App\Modules\Nucleo\Imputacion\SaldoConLinea;
use App\Modules\Nucleo\Models\Imputacion;
use Closure;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * La revisión del exceso de partida de un documento con costo (plan 2.2, D3 y D4; costura C12 del ADR 0020). Suma las
 * líneas del documento por partida sobre lo ya comprometido, marca todas las de una partida que se excede y desmarca
 * las que ya no; si una marcada no tiene motivo y comentario, rechaza. Cada documento la llama al enviar (`alEnviar`)
 * y al aprobar (`alAprobar`), **antes** de subir el comprometido de sus líneas y en la misma transacción. La PC-99 y
 * el gasto directo no se revisan aquí; las líneas a la PC-99 solo se avisan, después del commit (plan 2.6, D2 y D6):
 * así los documentos no agregan nada para el gasto no presupuestado.
 */
final readonly class ExcesoDePartidaService
{
    public function __construct(
        private FuenteDeDestinos $fuente,
        private ImputacionService $imputaciones,
    ) {}

    /**
     * El documento la llama dentro de la transacción de su transición «enviar»: así, si el envío se frena después (sin
     * regla para el exceso o la PC-99), el aviso de las líneas a la PC-99 se descarta con lo demás.
     *
     * @param  list<LineaDeCosto>  $lineas
     * @return list<SaldoConLinea> el saldo de cada partida revisada, con las líneas
     *
     * @throws ExcesoDePartidaRechazado
     */
    public function alEnviar(array $lineas): array
    {
        return DB::transaction(function () use ($lineas): array {
            [$saldos, $noPresupuestadas] = $this->revisar($lineas, false);
            $this->despues(fn () => $this->fuente->noPresupuestadosEnviados($noPresupuestadas), $noPresupuestadas);

            return $saldos;
        });
    }

    /**
     * Con las partidas bloqueadas, siempre en el mismo orden (id ascendente), para que dos aprobaciones a la vez no
     * vean las dos el saldo completo ni se traben entre sí. Después del commit, avisa de las partidas que llegaron al
     * umbral o se excedieron (D6).
     *
     * @param  list<LineaDeCosto>  $lineas
     * @return list<SaldoConLinea>
     *
     * @throws ExcesoDePartidaRechazado
     */
    public function alAprobar(array $lineas): array
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('La revisión al aprobar va en la transacción que sube el comprometido: fuera de ella, el bloqueo no sirve.');
        }

        [$saldos, $noPresupuestadas] = $this->revisar($lineas, true);
        // La aprobación ya quedó: si el aviso falla, se registra y no se le muestra a la persona como si no hubiera quedado.
        DB::afterCommit(fn () => rescue(fn () => $this->fuente->avisarSaldos($saldos)));
        $this->despues(fn () => $this->fuente->noPresupuestadosAprobados($noPresupuestadas), $noPresupuestadas);

        return $saldos;
    }

    /**
     * Después del commit, y solo si hay líneas a la PC-99. Si el aviso falla, se registra: el documento ya quedó.
     *
     * @param  list<LineaNoPresupuestada>  $noPresupuestadas
     */
    private function despues(Closure $aviso, array $noPresupuestadas): void
    {
        if ($noPresupuestadas !== []) {
            DB::afterCommit(fn () => rescue($aviso));
        }
    }

    /**
     * @param  list<LineaDeCosto>  $lineas
     * @return array{0: list<SaldoConLinea>, 1: list<LineaNoPresupuestada>} el saldo de cada partida revisada y las líneas
     *                                                                      que van a la PC-99, para avisar de ellas
     */
    private function revisar(array $lineas, bool $bloquear): array
    {
        /** @var array<int, list<array{linea: LineaDeCosto, imputacion: Imputacion, soles: string}>> $porPartida */
        $porPartida = [];
        $noPresupuestadas = [];

        foreach ($lineas as $linea) {
            $imputacion = $this->imputaciones->de($linea->origen) ?? throw ExcesoDePartidaRechazado::sinDestino();
            $partidaId = $imputacion->partida_control_id;
            $soles = bcmul($linea->monto, (string) $imputacion->tipo_cambio, 6);

            if ($partidaId === null) {
                continue;
            }

            if ($this->fuente->esNoPresupuestada($partidaId)) {
                $noPresupuestadas[] = new LineaNoPresupuestada($imputacion->id, $partidaId, (int) $imputacion->proyecto_id, $imputacion->motivo_id, self::redondear($soles));

                continue;
            }

            $porPartida[$partidaId][] = ['linea' => $linea, 'imputacion' => $imputacion, 'soles' => $soles];
        }

        ksort($porPartida);
        $saldos = [];
        $faltan = [];
        $nuevos = [];

        foreach ($porPartida as $partidaId => $delDocumento) {
            $suma = array_reduce($delDocumento, fn (string $suma, array $l): string => bcadd($suma, $l['soles'], 6), '0');
            $saldo = $this->fuente->saldoConLineas($partidaId, self::redondear($suma), $bloquear);
            $saldos[] = $saldo;

            foreach ($delDocumento as $l) {
                if (! $saldo->excede()) {
                    $this->imputaciones->marcarExceso($l['linea']->origen, false);

                    continue;
                }

                if ($l['imputacion']->motivo_id === null || trim((string) $l['imputacion']->comentario) === '') {
                    $faltan[$partidaId] = "la partida {$saldo->partida} se excedería en S/ {$saldo->excedeEn()}";

                    continue;
                }

                // Un exceso que aparece recién al aprobar no se aprueba aquí aunque tenga motivo: su ronda se abrió sin
                // la regla del exceso (D5: nunca pasa sin su regla). Quien firma observa y, al reenviar, la ronda la trae.
                if ($bloquear && ! $l['imputacion']->excede_partida) {
                    $nuevos[$partidaId] = "la partida {$saldo->partida} se excedería en S/ {$saldo->excedeEn()}";

                    continue;
                }

                $this->imputaciones->marcarExceso($l['linea']->origen, true);
            }
        }

        if ($faltan !== []) {
            throw ExcesoDePartidaRechazado::sinMotivo(array_values($faltan));
        }

        if ($nuevos !== []) {
            throw ExcesoDePartidaRechazado::nuevoAlAprobar(array_values($nuevos));
        }

        return [$saldos, $noPresupuestadas];
    }

    /** Al céntimo, la mitad hacia arriba (como `ROUND` de PostgreSQL con números positivos). */
    private static function redondear(string $monto): string
    {
        return bcadd($monto, bccomp($monto, '0', 6) >= 0 ? '0.005' : '-0.005', 2);
    }
}
