<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Desarrollo;

use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * El modo desarrollo (plan 1.4i): simular la fecha de lo decorativo y elegir variantes de las piezas decorativas, solo
 * en el equipo del desarrollador. Único lugar que decide si existe; todo lo del modo pregunta aquí.
 *
 * D7, en producción no hay forma de activarlo: hacen falta **las tres** llaves a la vez (`APP_ENV=local`,
 * `APP_DEBUG=true` y `TECHQUK_MODO_DESARROLLO=true`); sin ellas, lo guardado en la sesión se ignora. Con
 * `APP_ENV=production` y la variable puesta, se ignora y se registra un aviso crítico. No escribe en las tablas del
 * sistema: vive en la sesión y se pierde al cerrarla.
 */
final class ModoDesarrollo
{
    private const string SESION = 'nucleo.modo_desarrollo';

    /** Marca en el contenedor: el aviso sale una vez por petición (o por comando), no en cada pregunta. */
    private const string AVISADO = 'nucleo.modo_desarrollo.avisado';

    public static function disponible(): bool
    {
        $pedido = config('nucleo.modo_desarrollo') === true;

        if ($pedido && app()->environment('production')) {
            if (! app()->bound(self::AVISADO)) {
                app()->instance(self::AVISADO, true);
                Log::critical('TECHQUK_MODO_DESARROLLO está en verdadero en producción: se ignora. Quítala del .env del servidor.');
            }

            return false;
        }

        return $pedido && app()->environment('local') && config('app.debug') === true;
    }

    public static function activo(): bool
    {
        return self::disponible() && session()->get(self::SESION.'.activo') === true;
    }

    public static function prender(): void
    {
        abort_unless(self::disponible(), 404);
        session()->put(self::SESION.'.activo', true);
    }

    /** Apagar también olvida la fecha simulada y las variantes elegidas: se vuelve a ver lo de cualquier persona. */
    public static function apagar(): void
    {
        session()->forget(self::SESION);
    }

    public static function simular(?CarbonImmutable $momento): void
    {
        abort_unless(self::activo(), 404);

        if ($momento === null) {
            session()->forget(self::SESION.'.fecha');

            return;
        }

        session()->put(self::SESION.'.fecha', $momento->setTimezone(Calendario::ZONA)->format('Y-m-d\TH:i'));
    }

    public static function fechaSimulada(): ?CarbonImmutable
    {
        $guardada = self::activo() ? session()->get(self::SESION.'.fecha') : null;

        if (! is_string($guardada)) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d\TH:i', $guardada, Calendario::ZONA) ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * La hora de lo decorativo (D2): el saludo, la frase, el dibujo, la franja, el logo de fiesta, el agradecimiento y el
     * gran momento. Sin el modo, la real. Lo del negocio nunca la usa: sigue con `Calendario::ahora()`.
     */
    public static function ahoraDecorativo(): CarbonImmutable
    {
        return self::fechaSimulada() ?? Calendario::ahora();
    }

    /** La opción elegida para una pieza decorativa (D3), o null para la de la fecha. */
    public static function eleccion(string $pieza): ?string
    {
        $elegida = self::activo() ? session()->get(self::SESION.'.piezas.'.$pieza) : null;

        return is_string($elegida) ? $elegida : null;
    }

    public static function elegir(string $pieza, ?string $opcion): void
    {
        abort_unless(self::activo(), 404);

        if ($opcion === null) {
            session()->forget(self::SESION.'.piezas.'.$pieza);

            return;
        }

        session()->put(self::SESION.'.piezas.'.$pieza, $opcion);
    }
}
