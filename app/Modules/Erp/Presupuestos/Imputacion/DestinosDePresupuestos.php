<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Imputacion;

use App\Modules\Erp\Presupuestos\Services\AvisoDeGastosNoPresupuestados;
use App\Modules\Erp\Presupuestos\Services\AvisoDeSaldos;
use App\Modules\Erp\Presupuestos\Services\PartidasService;
use App\Modules\Erp\Proyectos\Services\ProyectoRechazado;
use App\Modules\Erp\Proyectos\Services\ProyectosService;
use App\Modules\Nucleo\Excepciones\ImputacionRechazada;
use App\Modules\Nucleo\Imputacion\CentroDeCosto;
use App\Modules\Nucleo\Imputacion\DestinoImputacion;
use App\Modules\Nucleo\Imputacion\EleccionImputacion;
use App\Modules\Nucleo\Imputacion\FuenteDeDestinos;
use App\Modules\Nucleo\Imputacion\OpcionDestino;
use App\Modules\Nucleo\Imputacion\SaldoConLinea;
use App\Modules\Nucleo\Imputacion\TipoImputacion;
use App\Modules\Nucleo\Models\Motivo;

/**
 * Lo que el selector de imputación del núcleo pide de proyectos y partidas (plan 2.2, D1), solo sobre las interfaces
 * de C1 y C2: nunca modelos ni tablas de Proyectos o Presupuestos. Así funciona igual con las versiones en memoria
 * (pruebas y la página de modo desarrollo). Del núcleo usa su catálogo de motivos y sus centros de costo; la alerta de
 * saldos la emite `AvisoDeSaldos`.
 */
final readonly class DestinosDePresupuestos implements FuenteDeDestinos
{
    public function __construct(
        private PartidasService $partidas,
        private ProyectosService $proyectos,
    ) {}

    public function proyectos(string $texto = ''): array
    {
        return array_map(
            fn ($p): OpcionDestino => new OpcionDestino($p->id, "{$p->codigo} · {$p->nombre}"),
            $this->proyectos->activos($texto),
        );
    }

    public function partidas(int $proyectoId, string $texto = ''): array
    {
        return array_map(
            fn ($p): OpcionDestino => new OpcionDestino($p->id, $p->ruta),
            $this->partidas->buscar($proyectoId, $texto),
        );
    }

    public function etiquetaProyecto(int $proyectoId): ?string
    {
        try {
            $p = $this->proyectos->resumen($proyectoId);
        } catch (ProyectoRechazado) {
            return null;
        }

        return "{$p->codigo} · {$p->nombre}".($p->aceptaCostos ? '' : ' ('.mb_strtolower($p->estadoNombre).')');
    }

    public function etiquetaPartida(int $partidaId): ?string
    {
        $p = $this->partidas->resumen($partidaId);

        return $p === null ? null : $p->ruta.($p->esActiva ? '' : ' (desactivada)');
    }

    public function esNoPresupuestada(int $partidaId): bool
    {
        return $this->partidas->resumen($partidaId)->esNoPresupuestada ?? false;
    }

    public function saldoConLineas(int $partidaId, string $lineasEnSoles, bool $bloquear = false): SaldoConLinea
    {
        $saldo = $this->partidas->saldo($partidaId, $bloquear);
        $resumen = $this->partidas->resumen($partidaId);

        return new SaldoConLinea(
            partidaId: $partidaId,
            partida: $resumen === null ? "#{$partidaId}" : "{$resumen->codigo} {$resumen->nombre}",
            presupuestado: $saldo->presupuestado,
            comprometido: $saldo->comprometido,
            lineas: bcadd($lineasEnSoles, '0', 2),
        );
    }

    public function destino(EleccionImputacion $eleccion): DestinoImputacion
    {
        return match ($eleccion->tipo) {
            TipoImputacion::CentroDeCosto => new CentroDeCosto(
                $eleccion->centroCostoId ?? throw ImputacionRechazada::conMotivo('Elige el centro de costo.'),
            ),
            TipoImputacion::Partida => $this->destinoDePartida($eleccion),
            TipoImputacion::NoPresupuestado => $this->destinoNoPresupuestado($eleccion),
        };
    }

    public function motivosDeExceso(): array
    {
        return $this->motivos(DestinoPartida::FAMILIA_EXCESO);
    }

    public function motivosNoPresupuestados(): array
    {
        return $this->motivos(DestinoPartida::FAMILIA_NO_PRESUPUESTADO);
    }

    /** Plan 2.6: encendida. */
    public function admiteGastoConProyecto(): bool
    {
        return true;
    }

    public function avisarSaldos(array $saldos): void
    {
        app(AvisoDeSaldos::class)->avisar($saldos, $this->partidas, $this->proyectos);
    }

    public function noPresupuestadosEnviados(array $lineas): void
    {
        app(AvisoDeGastosNoPresupuestados::class)->alEnviar($lineas, $this->partidas, $this->proyectos);
    }

    public function noPresupuestadosAprobados(array $lineas): void
    {
        app(AvisoDeGastosNoPresupuestados::class)->alAprobar($lineas, $this->partidas, $this->proyectos);
    }

    /** La PC-99 del proyecto elegido, con su motivo y comentario obligatorios (los revisa `DestinoPartida`). */
    private function destinoNoPresupuestado(EleccionImputacion $eleccion): DestinoImputacion
    {
        $proyectoId = $eleccion->proyectoId ?? throw ImputacionRechazada::conMotivo('Elige el proyecto.');
        $pc99 = $this->partidas->noPresupuestada($proyectoId)
            ?? throw ImputacionRechazada::conMotivo('Este proyecto todavía no tiene su árbol de partidas aprobado: no hay PC-99 a la que cargar el gasto.');

        return $this->partidas->destino($pc99->id, $eleccion->motivoId, $eleccion->comentario, $eleccion->centroCostoId);
    }

    /**
     * Del catálogo de motivos del núcleo: no es dato de C1 ni de C2.
     *
     * @return list<OpcionDestino>
     */
    private function motivos(string $familia): array
    {
        return Motivo::query()
            ->where('es_activo', true)
            ->whereHas('tipoMotivo', fn ($query) => $query->where('codigo', $familia))
            ->orderBy('es_otro')
            ->orderBy('nombre')
            ->get()
            ->map(fn (Motivo $motivo): OpcionDestino => new OpcionDestino($motivo->id, $motivo->nombre))
            ->values()
            ->all();
    }

    private function destinoDePartida(EleccionImputacion $eleccion): DestinoImputacion
    {
        $partidaId = $eleccion->partidaId ?? throw ImputacionRechazada::conMotivo('Elige la partida de control.');
        $resumen = $this->partidas->resumen($partidaId) ?? throw ImputacionRechazada::conMotivo('Esa partida no existe.');

        if ($resumen->esNoPresupuestada) {
            throw ImputacionRechazada::conMotivo('La PC-99 se elige como «Gasto directo con proyecto».');
        }

        if ($eleccion->proyectoId !== null && $eleccion->proyectoId !== $resumen->proyectoId) {
            throw ImputacionRechazada::conMotivo("La partida {$resumen->codigo} no es de ese proyecto.");
        }

        return $this->partidas->destino($partidaId, $eleccion->motivoId, $eleccion->comentario);
    }
}
