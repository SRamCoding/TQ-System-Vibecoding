<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Services;

use App\Modules\Nucleo\Alertas\AlertasDelNucleo;
use App\Modules\Nucleo\Estados\EfectoExterno;
use App\Modules\Nucleo\Estados\RegistroDocumentos;
use App\Modules\Nucleo\Models\EfectoAplicado;
use App\Modules\Nucleo\Models\HistorialEstado;
use App\Modules\Nucleo\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

/**
 * Corre los efectos externos de los cambios de estado, y sus reversiones al anular, fuera de toda transacción (plan
 * 1.5, D3; `codigo.md`: nada externo dentro de una transacción). Cada intento:
 * 1. en una transacción corta, bloquea la fila del efecto y la reserva por PLAZO_RESERVA segundos, si nadie la tiene
 *    reservada y no se hizo ya;
 * 2. sin transacción, envía, con el id del efecto como clave de idempotencia;
 * 3. en otra transacción corta, confirma (y quita la reserva) o, si el envío falló, libera la reserva para que el
 *    reintento lo haga.
 * Si el worker muere a mitad, la reserva vence y otro intento lo retoma: puede repetirse, pero nunca perderse (al
 * menos una vez). Un efecto reservado y no confirmado cuenta como salido al exterior: se revierte al anular e impide
 * «Reabrir».
 */
final readonly class EfectosExternosService
{
    /** Mayor que el timeout de los jobs (60) y menor que `retry_after` de la cola (90): ver la prueba que lo revisa. */
    public const PLAZO_RESERVA = 75;

    public function __construct(
        private RegistroDocumentos $registro,
        private ContextoEfectos $efectos,
        private AlertasService $alertas,
    ) {}

    /**
     * Ejecuta el efecto. Falso si otro intento lo tiene reservado: el job vuelve a la cola. Un efecto ya ejecutado o
     * anulado antes de salir no se envía.
     */
    public function ejecutar(int $efectoAplicadoId): bool
    {
        return $this->correr(
            $efectoAplicadoId,
            'reservado_hasta',
            fn (EfectoAplicado $aplicado): ?bool => $aplicado->ejecutado_at !== null || $aplicado->revertido_por_historial_id !== null ? true : null,
            fn (EfectoExterno $efecto, Model $documento, EfectoAplicado $aplicado) => $efecto->ejecutar($documento, $aplicado->datos, "efecto-{$aplicado->id}"),
            'ejecutado_at',
        );
    }

    /**
     * Revierte un efecto anulado que salió o pudo salir: ejecutado, o con una reserva vencida sin confirmar. Si su
     * envío sigue en curso (reserva vigente), espera. Falso si hay que reintentar más tarde.
     */
    public function revertir(int $efectoAplicadoId): bool
    {
        return $this->correr(
            $efectoAplicadoId,
            'reversion_reservada_hasta',
            function (EfectoAplicado $aplicado): ?bool {
                if ($aplicado->revertido_por_historial_id === null || $aplicado->reversion_ejecutada_at !== null) {
                    return true;
                }

                if ($aplicado->ejecutado_at === null) {
                    // Nunca salió (o falló y se liberó): nada que deshacer. Con reserva vigente, su envío sigue en curso.
                    return $aplicado->reservado_hasta === null ? true : ($aplicado->reservado_hasta->isFuture() ? false : null);
                }

                return null;
            },
            fn (EfectoExterno $efecto, Model $documento, EfectoAplicado $aplicado) => $efecto->revertir($documento, $aplicado->datos, "efecto-{$aplicado->id}-reversion"),
            'reversion_ejecutada_at',
        );
    }

    /**
     * @param  Closure(EfectoAplicado): ?bool  $yaResuelto  verdadero si no hay nada que hacer, falso si hay que esperar,
     *                                                      nulo si toca hacerlo
     * @param  Closure(EfectoExterno, Model, EfectoAplicado): void  $enviar
     */
    private function correr(int $id, string $reserva, Closure $yaResuelto, Closure $enviar, string $hecho): bool
    {
        $tomado = DB::transaction(function () use ($id, $reserva, $yaResuelto): EfectoAplicado|bool {
            $aplicado = EfectoAplicado::query()->lockForUpdate()->find($id);

            if ($aplicado === null) {
                return true;
            }

            $resuelto = $yaResuelto($aplicado);

            if ($resuelto !== null) {
                return $resuelto;
            }

            if ($aplicado->getAttribute($reserva)?->isFuture()) {
                return false;
            }

            // Una reserva vencida es de un intento que murió: se retoma.
            $aplicado->setAttribute($reserva, now()->addSeconds(self::PLAZO_RESERVA));
            $aplicado->save();

            return $aplicado;
        });

        if (! $tomado instanceof EfectoAplicado) {
            return $tomado;
        }

        $efecto = $this->efectoDe($tomado);
        $documento = $tomado->historial()->firstOrFail()->documentable ?? throw new LogicException('El documento del efecto no existe.');

        try {
            $this->efectos->durante($efecto, fn () => $enviar($efecto, $documento, $tomado));
        } catch (Throwable $error) {
            $this->cerrar($id, fn (EfectoAplicado $aplicado) => $aplicado->setAttribute($reserva, null));

            throw $error;
        }

        $this->cerrar($id, function (EfectoAplicado $aplicado) use ($reserva, $hecho): void {
            $aplicado->setAttribute($hecho, now());
            $aplicado->setAttribute($reserva, null);
        });

        return true;
    }

    /**
     * El efecto agotó sus intentos (lo llama el `failed()` de su job): avisa a quienes reciben la alerta y a quien hizo
     * el cambio de estado que lo disparó, o el que lo anuló si fallaba la reversión (plan 1.10, D3). Una vez por efecto
     * y por sentido. Sin datos del efecto: el documento y el paso.
     */
    public function avisarQueFallo(int $efectoAplicadoId, bool $alRevertir): void
    {
        $aplicado = EfectoAplicado::query()->find($efectoAplicadoId);

        if ($aplicado === null) {
            return;
        }

        $historial = $aplicado->historial()->with(['transicion', 'hacia'])->firstOrFail();
        $cambio = $alRevertir && $aplicado->revertido_por_historial_id !== null
            ? HistorialEstado::query()->with(['transicion', 'hacia'])->findOrFail($aplicado->revertido_por_historial_id)
            : $historial;
        $documento = $historial->documentable;
        $definicion = $this->registro->definicionDe($historial->documentable_type);
        $nombre = $definicion === null ? 'el documento' : $definicion->nombre();
        $identificador = $documento instanceof Model && $definicion !== null ? $definicion->identificador($documento) : '#'.$historial->documentable_id;
        $persona = User::query()->find($cambio->user_id);

        $this->alertas->emitir(
            AlertasDelNucleo::EFECTO_FALLIDO,
            "efecto:{$aplicado->id}".($alRevertir ? ':reversion' : ''),
            [
                'documento' => "{$nombre} {$identificador}",
                'paso' => ($alRevertir ? 'revertir ' : '').($cambio->transicion->nombre ?? $cambio->hacia->nombre ?? 'el cambio de estado'),
            ],
            $documento instanceof Model ? $documento : null,
            ademas: $persona === null ? [] : [$persona],
        );
    }

    /**
     * @param  Closure(EfectoAplicado): void  $cambio
     */
    private function cerrar(int $id, Closure $cambio): void
    {
        DB::transaction(function () use ($id, $cambio): void {
            $aplicado = EfectoAplicado::query()->lockForUpdate()->findOrFail($id);
            $cambio($aplicado);
            $aplicado->save();
        });
    }

    private function efectoDe(EfectoAplicado $aplicado): EfectoExterno
    {
        $clase = $this->registro->claseDeEfecto($aplicado->clave) ?? throw new LogicException("El efecto «{$aplicado->clave}» no está registrado.");
        $efecto = app($clase);

        return $efecto instanceof EfectoExterno ? $efecto : throw new LogicException("El efecto «{$aplicado->clave}» ya no es externo.");
    }
}
