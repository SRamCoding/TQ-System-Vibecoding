<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Filament;

use App\Modules\Crm\Oportunidades\Enums\EtapaAbierta;
use App\Modules\Crm\Oportunidades\OportunidadesServiceProvider;
use App\Modules\Crm\Oportunidades\Requisitos\RequisitosDeEtapa;
use App\Modules\Crm\Oportunidades\Services\ContratosDeOportunidad;
use App\Modules\Crm\Oportunidades\Services\FilaFaja;
use App\Modules\Crm\Oportunidades\Services\OportunidadesService;
use App\Modules\Crm\Oportunidades\Services\PortadaOportunidades;
use App\Modules\Nucleo\Models\User;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * El despiece de una caja de la faja (plan 2.21b, paso 6; D10, D14; pedido B3): la caja separada en capas, una por
 * pestaña de la ficha (`CapasDelDespiece`), y el panel de lectura a la derecha con la capa elegida, los requisitos del
 * escáner siguiente y los botones Pasar, Ganar y Perder. Los botones no ejecutan nada aquí: la faja abre el mismo
 * diálogo que al arrastrar (`MueveOportunidades::abrirMovimiento`) y la acción real corre al llegar al escáner (D14). Lo
 * ve quien ve oportunidades, como la ficha; cada botón, solo si su acción lo aceptaría por permiso (también «propias»).
 * Los datos salen de `OportunidadesService::filaDeLaFaja`, los mismos de las cajas y las tablas; las anuladas, no.
 */
final class DespieceDeOportunidad extends Component
{
    /** La caja abierta; nula, cerrado. Solo cambia por `abrir` y `cerrar`. */
    #[Locked]
    public ?int $oportunidadId = null;

    /** La capa que se asoma y se lee en el panel. */
    public string $capa = 'resumen';

    public function boot(): void
    {
        abort_unless(self::usuario()?->can(OportunidadesServiceProvider::PERMISO_VER) ?? false, 403);
    }

    public function abrir(int $oportunidadId): void
    {
        // Que esté en la faja: si otro la anuló o no existe, la faja recibe el 404 y no abre nada.
        abort_if(app(OportunidadesService::class)->versionEnLaFaja($oportunidadId) === null, 404);
        $this->oportunidadId = $oportunidadId;
        $this->capa = 'resumen';
    }

    public function cerrar(): void
    {
        $this->oportunidadId = null;
        $this->capa = 'resumen';
    }

    public function render(): View
    {
        if ($this->oportunidadId === null) {
            return view('oportunidades::faja.despiece', ['abierta' => null]);
        }

        $capas = app(CapasDelDespiece::class)->para(self::usuario());

        if (! in_array($this->capa, array_column($capas, 'clave'), true)) {
            $this->capa = 'resumen';
        }

        return view('oportunidades::faja.despiece', ['abierta' => $this->datos($this->oportunidadId), 'capas' => $capas]);
    }

    /**
     * Lo que muestran la cabecera, la capa Resumen y el pie del panel (escáner siguiente o cierre).
     *
     * @return array<string, mixed>
     */
    private function datos(int $oportunidadId): array
    {
        $fila = app(OportunidadesService::class)->filaDeLaFaja($oportunidadId);
        $registro = TablaDeLaFaja::registro($fila);
        $etapa = EtapaAbierta::tryFrom($fila->etapa);
        $portada = app(PortadaOportunidades::class);
        $nombreEtapa = $etapa?->etiqueta() ?? match ($fila->etapa) {
            'ganada' => 'Ganada',
            'perdida' => 'Perdida',
            default => ucfirst($fila->etapa),
        };
        $probabilidad = $etapa === null ? null : $portada->probabilidad($etapa);
        $dias = $fila->diasEnEtapa === 1 ? '1 día' : "{$fila->diasEnEtapa} días";

        return [
            'id' => $fila->id,
            'numero' => $fila->numero,
            'nombre' => $fila->nombre,
            'cuenta' => $fila->cuenta,
            'ejecutivo' => $fila->ejecutivo,
            // D16 de la 2.21c: corto en la tapa de la capa Resumen; D17: completo en la cabecera de la ventana.
            'monto' => MontoCorto::de($fila->monto, $fila->simbolo),
            'montoCompleto' => $registro['monto'],
            'etapa' => $fila->etapa,
            'nombreEtapa' => $nombreEtapa,
            'pastilla' => $probabilidad === null ? $nombreEtapa : "{$nombreEtapa} · {$probabilidad} %",
            'version' => $fila->version,
            'abierta' => $etapa !== null,
            'atascada' => $fila->atascada,
            'resumen' => array_filter([
                ['Número', $fila->numero],
                ['Oportunidad', $fila->nombre],
                ['Cuenta', $fila->cuenta],
                ['Monto', $registro['monto'].' · '.$fila->moneda],
                ['Ejecutivo', $fila->ejecutivo],
                ['Etapa', $nombreEtapa],
                ['Probabilidad', $probabilidad === null ? '—' : "{$probabilidad} %"],
                ['Cierre estimado', $fila->cierreEstimado->format('d/m/Y')],
                // La última: quitarla deja la lista sin huecos.
                $etapa === null ? null : ['Días en la etapa', $dias.($fila->atascada ? ' · atascada' : '')],
            ]),
            'escaner' => $etapa === null ? null : self::escaner($fila, $etapa, $portada),
            'cierre' => $etapa === null ? self::cierre($fila, $registro) : null,
        ];
    }

    /**
     * El escáner siguiente: lo que exige (de `RequisitosDeEtapa`, con si se cumple) y los botones que la persona puede
     * usar, cada uno con su destino en la faja.
     *
     * @return array{titulo: string, requisitos: list<array{texto: string, cumple: bool}>, botones: list<array{hacia: string, etiqueta: string, tono: string}>}
     */
    private static function escaner(FilaFaja $fila, EtapaAbierta $etapa, PortadaOportunidades $portada): array
    {
        $siguiente = $etapa->siguiente();
        $numero = array_search($etapa, EtapaAbierta::cases(), true) + 1;
        $candidatos = [];

        if ($siguiente !== null) {
            $candidatos[] = ['hacia' => $siguiente->value, 'etiqueta' => "Pasar a {$siguiente->etiqueta()}", 'tono' => 'primario'];
        } else {
            $candidatos[] = ['hacia' => MovimientoDeOportunidad::GANADA, 'etiqueta' => 'Ganar · por la tubería', 'tono' => 'primario'];
        }

        $candidatos[] = ['hacia' => MovimientoDeOportunidad::PERDIDA, 'etiqueta' => 'Perder · a la canaleta', 'tono' => 'peligro'];

        return [
            'titulo' => $siguiente === null
                ? "Escáner {$numero} · hacia la tubería de ganadas"
                : "Escáner {$numero} · hacia {$siguiente->etiqueta()} · {$portada->probabilidad($siguiente)} %",
            'requisitos' => app(RequisitosDeEtapa::class)->revisar($fila->id, $siguiente->value ?? MovimientoDeOportunidad::GANADA),
            'botones' => array_values(array_filter($candidatos, fn (array $boton): bool => MovimientoDeOportunidad::puede($fila->id, $etapa->value, $boton['hacia']))),
        ];
    }

    /**
     * Lo que se sabe del cierre de una ganada o una perdida, como sus tablas (paso 5). También la ficha del tablero.
     *
     * @param  array<string, mixed>  $registro
     * @return list<array{0: string, 1: string}>
     */
    public static function cierre(FilaFaja $fila, array $registro): array
    {
        $fecha = $fila->fechaCierre?->format('d/m/Y') ?? '—';

        if ($fila->etapa === 'ganada') {
            return array_filter([
                ['Ganada el', $fecha],
                app(ContratosDeOportunidad::class)->tieneDatos() ? ['Contrato', $fila->contrato ?? '—'] : null,
            ]);
        }

        if ($fila->etapa === 'perdida') {
            return [
                ['Perdida el', $fecha],
                ['Motivo', $fila->motivo ?? '—'],
                ['Ganador', is_string($registro['ganador']) ? $registro['ganador'] : '—'],
                ['Precio', is_string($registro['precio']) ? $registro['precio'] : '—'],
            ];
        }

        return [['Estado', ucfirst($fila->etapa)]];
    }

    private static function usuario(): ?User
    {
        $usuario = Filament::auth()->user();

        return $usuario instanceof User ? $usuario : null;
    }
}
