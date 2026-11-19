<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Imputacion;

use App\Modules\Nucleo\Excepciones\ImputacionRechazada;

/**
 * Lo que el selector de imputación necesita de los proyectos y sus partidas (plan 2.2, D1). El núcleo no sabe de
 * `Erp`: la implementa Presupuestos (`DestinosDePresupuestos`), sobre las interfaces de C1 y C2, y la registra en el
 * contenedor. Los centros de costo los da el núcleo.
 */
interface FuenteDeDestinos
{
    /**
     * Los proyectos que aceptan costos, por código o nombre.
     *
     * @return list<OpcionDestino>
     */
    public function proyectos(string $texto = ''): array;

    /**
     * Las partidas a las que se puede imputar en un proyecto, por código o nombre en todo el árbol, con su ruta.
     *
     * @return list<OpcionDestino>
     */
    public function partidas(int $proyectoId, string $texto = ''): array;

    /** Cómo se muestra un proyecto ya guardado, aunque ya no acepte costos. */
    public function etiquetaProyecto(int $proyectoId): ?string;

    /** Cómo se muestra una partida ya guardada, aunque ya no esté activa. */
    public function etiquetaPartida(int $partidaId): ?string;

    /** Si la partida es la PC-99 (el exceso no se revisa ahí: su motivo es el del gasto no presupuestado). */
    public function esNoPresupuestada(int $partidaId): bool;

    /**
     * El saldo de la partida con estas líneas, en soles. Con `$bloquear`, con la partida bloqueada (al aprobar, D3).
     *
     * @param  string  $lineasEnSoles  decimal como texto
     *
     * @throws ImputacionRechazada si la partida no existe
     */
    public function saldoConLineas(int $partidaId, string $lineasEnSoles, bool $bloquear = false): SaldoConLinea;

    /**
     * El destino de la elección, para `ImputacionService::destinar()`.
     *
     * @throws ImputacionRechazada si la elección no se puede imputar
     */
    public function destino(EleccionImputacion $eleccion): DestinoImputacion;

    /**
     * Los motivos activos de un exceso de partida (plan 2.2, D4), para el selector.
     *
     * @return list<OpcionDestino>
     */
    public function motivosDeExceso(): array;

    /**
     * Los motivos activos de un gasto no presupuestado (plan 2.6, D3), para el selector.
     *
     * @return list<OpcionDestino>
     */
    public function motivosNoPresupuestados(): array;

    /** Si «gasto directo con proyecto» (PC-99) está disponible (plan 2.2, D7; encendida en el plan 2.6). */
    public function admiteGastoConProyecto(): bool;

    /**
     * Después de aprobar líneas, avisa de las partidas que llegaron al umbral o se excedieron (D6).
     *
     * @param  list<SaldoConLinea>  $saldos  el de cada partida, con las líneas aprobadas
     */
    public function avisarSaldos(array $saldos): void;

    /**
     * Después de enviar un documento con líneas a la PC-99 (plan 2.6, D6): «Gasto no presupuestado registrado».
     *
     * @param  list<LineaNoPresupuestada>  $lineas
     */
    public function noPresupuestadosEnviados(array $lineas): void;

    /**
     * Después de aprobarlas (plan 2.6, D6): las que son un cambio pedido por el cliente avisan «Posible adicional
     * detectado» y disparan `PosibleAdicionalRegistrado` (C11).
     *
     * @param  list<LineaNoPresupuestada>  $lineas
     */
    public function noPresupuestadosAprobados(array $lineas): void;
}
