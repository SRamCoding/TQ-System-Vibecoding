<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Filament;

use App\Modules\Crm\Oportunidades\Enums\EtapaAbierta;
use App\Modules\Crm\Oportunidades\Estados\DefinicionOportunidad;
use App\Modules\Crm\Oportunidades\Services\OportunidadesService;
use App\Modules\Crm\Oportunidades\Services\PortadaOportunidades;
use App\Modules\Nucleo\Excepciones\ErrorDeNegocio;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Support\Enums\Width;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Renderless;

/**
 * Mover una oportunidad por el embudo desde una pantalla (plan 2.21b, D3–D5; plan 2.21c, D4 y D5): el único camino del
 * arrastre de la faja, el arrastre del tablero de gemas y los botones Pasar, Ganar y Perder del despiece y de la ficha
 * del tablero. Solo conecta la pantalla con la acción registrada; las reglas están en la acción y su servicio.
 *
 * 1. `abrirMovimiento` elige la acción (`MovimientoDeOportunidad::accion`), revisa la versión tomada, el permiso y
 *    «propias» y, en la rueda de tallado (`$exigirRequisitos`), lo que falta de la etapa destino; si todo está, abre el
 *    diálogo de la acción (`calificar`, `enviarPropuesta`, `negociar`, `ganar` o `perder`, con sus campos).
 * 2. El diálogo no ejecuta: devuelve lo llenado con `tq-oportunidad-dialogo-listo` ({ oportunidad, datos }).
 * 3. La pantalla anima y llama a `moverOportunidad`, que ejecuta la acción por `EjecutorAcciones` con la versión tomada
 *    al agarrar la tarjeta o al abrir la ficha (D4 de la 2.21b): nunca la leída al abrir el diálogo.
 */
trait MueveOportunidades
{
    use CumpleRequisitos;

    /**
     * Soltar una tarjeta o pulsar un botón (D3 y D5 de la 2.21b): si el movimiento es una transición, abre el diálogo de
     * su acción. Saltar, retroceder o ganar desde donde no se puede se explica sin llamar a nada. Si otro la movió desde que
     * se tomó, se avisa (`conflicto`). Con `$exigirRequisitos` (la rueda de tallado, D4 de la 2.21c), si falta algo de la
     * etapa destino no abre nada y devuelve los textos en `faltan`.
     *
     * @return array{ok: bool, conflicto: bool, mensaje: ?string, faltan?: list<string>}
     */
    public function abrirMovimiento(int $oportunidadId, string $desde, string $hacia, int $version, bool $exigirRequisitos = false): array
    {
        $accion = MovimientoDeOportunidad::accion($desde, $hacia);

        if (! is_array($accion)) {
            return self::resultadoDelMovimiento(false, $accion);
        }

        // Anulada o movida por otro desde que se tomó: conflicto, la pantalla se redibuja.
        if (app(OportunidadesService::class)->versionEnLaFaja($oportunidadId) !== $version) {
            return self::resultadoDelMovimiento(false, VersionDesactualizada::de('la oportunidad')->getMessage(), conflicto: true);
        }

        // Si la acción la va a rechazar por permiso, el diálogo no se abre; el mensaje es el mismo que dará.
        $rechazo = MovimientoDeOportunidad::rechazo($oportunidadId, $accion['codigo']);

        if ($rechazo !== null) {
            return self::resultadoDelMovimiento(false, $rechazo);
        }

        if ($exigirRequisitos) {
            $faltan = MovimientoDeOportunidad::faltantes($oportunidadId, $hacia);

            if ($faltan !== []) {
                return [...self::resultadoDelMovimiento(false, MovimientoDeOportunidad::textoDeFaltantes($hacia, $faltan)), 'faltan' => $faltan];
            }
        }

        $this->mountAction($accion['dialogo'], ['oportunidad' => $oportunidadId, 'version' => $version, 'desde' => $desde, 'hacia' => $hacia]);

        return self::resultadoDelMovimiento(true, null);
    }

    /**
     * Ejecuta la acción real con lo que pidió el diálogo y la versión tomada (D4 de la 2.21b). Si falla, el mensaje de la
     * acción; si otro la movió, `conflicto`. Sin redibujar: la pantalla anima primero y después pide los datos nuevos.
     *
     * @param  array<string, mixed>  $datos
     * @return array{ok: bool, conflicto: bool, mensaje: ?string, version?: int, etapa?: string}
     */
    #[Renderless]
    public function moverOportunidad(int $oportunidadId, string $desde, string $hacia, int $version, array $datos = []): array
    {
        $accion = MovimientoDeOportunidad::accion($desde, $hacia);

        if (! is_array($accion)) {
            return self::resultadoDelMovimiento(false, $accion);
        }

        $usuario = Filament::auth()->user();

        if (! $usuario instanceof User) {
            throw new AuthorizationException;
        }

        try {
            app(EjecutorAcciones::class)->ejecutar(
                DefinicionOportunidad::CLAVE.".{$accion['codigo']}",
                [...$datos, 'oportunidad_id' => $oportunidadId, 'version' => $version],
                $usuario,
            );
        } catch (VersionDesactualizada $e) {
            return self::resultadoDelMovimiento(false, $e->getMessage(), conflicto: true);
        } catch (ValidationException $e) {
            return self::resultadoDelMovimiento(false, (string) collect($e->errors())->flatten()->first());
        } catch (ErrorDeNegocio $e) {
            return self::resultadoDelMovimiento(false, $e->getMessage());
        }

        $oportunidad = app(OportunidadesService::class)->resumen($oportunidadId);
        $etapa = $oportunidad->etapa;
        $abierta = EtapaAbierta::tryFrom($etapa);
        $mensaje = match (true) {
            $abierta !== null => "«{$oportunidad->nombre}» pasó a {$abierta->etiqueta()} · ".app(PortadaOportunidades::class)->probabilidad($abierta).' %.',
            $etapa === MovimientoDeOportunidad::GANADA => "«{$oportunidad->nombre}» ganada.",
            default => "«{$oportunidad->nombre}» cayó a perdidas.",
        };

        return [...self::resultadoDelMovimiento(true, $mensaje), 'version' => $oportunidad->version, 'etapa' => $etapa];
    }

    // Los diálogos del movimiento (D5 de la 2.21b): los paneles de la ficha, más chicos, que devuelven lo llenado a la
    // pantalla en lugar de ejecutar. `abrirMovimiento` los abre con {oportunidad, version}.
    public function calificarAction(): Action
    {
        return $this->dialogoDelMovimiento(PanelesOportunidad::avanzar('prospecto', self::oportunidadDelMovimiento(...), fn () => null));
    }

    public function enviarPropuestaAction(): Action
    {
        return $this->dialogoDelMovimiento(PanelesOportunidad::avanzar('calificada', self::oportunidadDelMovimiento(...), fn () => null));
    }

    public function negociarAction(): Action
    {
        return $this->dialogoDelMovimiento(PanelesOportunidad::avanzar('propuesta_enviada', self::oportunidadDelMovimiento(...), fn () => null));
    }

    public function ganarAction(): Action
    {
        return $this->dialogoDelMovimiento(PanelesOportunidad::ganar(self::oportunidadDelMovimiento(...), fn () => null));
    }

    public function perderAction(): Action
    {
        return $this->dialogoDelMovimiento(PanelesOportunidad::perder(self::oportunidadDelMovimiento(...), fn () => null));
    }

    /** @param  array<string, mixed>  $arguments */
    private static function oportunidadDelMovimiento(array $arguments): int
    {
        return (int) ($arguments['oportunidad'] ?? 0);
    }

    /** Un diálogo chico (no panel deslizante) que, al confirmar, devuelve lo llenado a la pantalla sin ejecutar. */
    private function dialogoDelMovimiento(Action $panel): Action
    {
        return $panel->slideOver(false)->modalWidth(Width::Large)
            ->action(function (array $data, array $arguments): void {
                // La versión que vale es la tomada al agarrar la tarjeta o al abrir la ficha (D4), no la leída aquí.
                unset($data['version']);

                // Plan 2.21d, D8 (revisión de arquitectura): un archivo no puede ir y volver por el navegador. Con la
                // certificación, el avance se ejecuta aquí mismo con la versión tomada, en una sola transacción con el
                // archivo, y el navegador solo termina (`resultado`). Sin archivo, el camino de siempre.
                $certificacion = $data['certificacion_presupuestal'] ?? null;
                $certificacion = is_array($certificacion) ? (array_values($certificacion)[0] ?? null) : $certificacion;
                unset($data['certificacion_presupuestal']);

                if ($certificacion instanceof UploadedFile && isset($arguments['desde'], $arguments['hacia'], $arguments['version'])) {
                    $resultado = $this->moverOportunidad(self::oportunidadDelMovimiento($arguments), (string) $arguments['desde'], (string) $arguments['hacia'], (int) $arguments['version'], [...$data, 'certificacion_presupuestal' => $certificacion]);
                    $this->dispatch('tq-oportunidad-dialogo-listo', oportunidad: self::oportunidadDelMovimiento($arguments), datos: $data, resultado: $resultado);

                    return;
                }

                $this->dispatch('tq-oportunidad-dialogo-listo', oportunidad: self::oportunidadDelMovimiento($arguments), datos: $data);
            });
    }

    /** @return array{ok: bool, conflicto: bool, mensaje: ?string} */
    private static function resultadoDelMovimiento(bool $ok, ?string $mensaje, bool $conflicto = false): array
    {
        return ['ok' => $ok, 'conflicto' => $conflicto, 'mensaje' => $mensaje];
    }
}
