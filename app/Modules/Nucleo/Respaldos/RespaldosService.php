<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Respaldos;

use App\Modules\Nucleo\Alertas\AlertasDelNucleo;
use App\Modules\Nucleo\Enums\ResultadoEjecucion;
use App\Modules\Nucleo\Models\SaludEjecucion;
use App\Modules\Nucleo\Services\AlertasService;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Services\SaludService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Los respaldos (plan 1.12, D3; `docs/operaciones/respaldos.md`). El programador llama a `respaldarSiToca()` cada
 * noche y el parámetro `respaldos.frecuencia` decide si ya toca. Cada corrida queda en `salud_ejecuciones`; si falla,
 * avisa. La limpieza de respaldos viejos corre solo con destino local: en S3 el job solo escribe y la retención la
 * hace TI con las reglas del bucket.
 */
final readonly class RespaldosService
{
    public const string TAREA = 'nucleo.respaldo';

    /** Un día más que la frecuencia antes de dar el respaldo por vencido: una noche fallida no alarma de inmediato. */
    public const int DIAS_MARGEN = 1;

    public function __construct(
        private Respaldador $respaldador,
        private SaludService $salud,
        private ParametrosService $parametros,
    ) {}

    /** @return bool si respaldó (false: todavía no tocaba) */
    public function respaldarSiToca(AlertasService $alertas, bool $forzar = false): bool
    {
        $ultimo = $this->ultimoCorrecto();
        // Medio día de holgura: la corrida de cada noche no se salta por unos minutos de diferencia.
        $toca = $ultimo === null || $ultimo->iniciada_at->lte(Calendario::ahora()->subDays($this->frecuencia()->dias())->addHours(12));

        if (! $toca && ! $forzar) {
            return false;
        }

        $this->respaldar($alertas);

        return true;
    }

    /** Las alertas llegan como parámetro (AGENTS.md: dependencia de un solo método; no cierra ciclos en el contenedor). */
    public function respaldar(AlertasService $alertas): ArchivoRespaldo
    {
        try {
            $datos = $this->salud->registrar(self::TAREA, function (): array {
                $archivo = $this->respaldador->respaldar();

                if ($this->destinoEsLocal()) {
                    $this->respaldador->limpiar();
                }

                return ['archivo' => $archivo->nombre, 'bytes' => $archivo->bytes, 'disco' => $this->respaldador->disco()];
            });

            return new ArchivoRespaldo($datos['archivo'], $datos['bytes']);
        } catch (Throwable $error) {
            $alertas->emitir(AlertasDelNucleo::RESPALDO_FALLIDO, Calendario::ahora()->toDateString(), ['disco' => $this->respaldador->disco()]);

            throw $error;
        }
    }

    /** La revisión diaria (`RespaldosVencidos`): el último respaldo correcto es más viejo que la frecuencia y el margen. */
    public function estaVencido(): bool
    {
        $ultimo = $this->ultimoCorrecto();

        return $ultimo === null || $ultimo->iniciada_at->lt(Calendario::ahora()->subDays($this->frecuencia()->dias() + self::DIAS_MARGEN));
    }

    public function ultimoCorrecto(): ?SaludEjecucion
    {
        return $this->salud->ultima(self::TAREA, ResultadoEjecucion::Correcta);
    }

    public function ultimaCorrida(): ?SaludEjecucion
    {
        return $this->salud->ultima(self::TAREA);
    }

    public function frecuencia(): FrecuenciaRespaldo
    {
        return FrecuenciaRespaldo::from($this->parametros->texto('respaldos.frecuencia'));
    }

    public function disco(): string
    {
        return $this->respaldador->disco();
    }

    /** Un respaldo en el mismo servidor no lo protege si el servidor se pierde (D3): nunca en verde. */
    public function destinoEsLocal(): bool
    {
        return config('filesystems.disks.'.$this->respaldador->disco().'.driver') === 'local';
    }

    public function ultimaPruebaRestauracion(): ?CarbonImmutable
    {
        return ValidadorPruebaRestauracion::fecha($this->parametros->texto('respaldos.ultima_prueba_restauracion'));
    }
}
