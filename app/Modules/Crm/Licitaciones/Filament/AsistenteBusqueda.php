<?php

declare(strict_types=1);

namespace App\Modules\Crm\Licitaciones\Filament;

use App\Modules\Crm\Licitaciones\Services\AsistenteBusquedaSeace;
use App\Modules\Crm\Licitaciones\Services\FiltroConvocatorias;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

/**
 * Observación 9.6: el asistente de configuración inicial de la búsqueda del SEACE, en una ventana al medio (fondo
 * desenfocado, MV-086) con pasos y barra de avance; se puede volver atrás sin perder lo elegido. Solo guarda quien
 * tiene `crm.convocatorias.configurar-busqueda`.
 */
final class AsistenteBusqueda extends Component
{
    public const array PASOS = [1 => '¿Qué vende la empresa?', 2 => '¿Dónde trabaja?', 3 => '¿Desde qué monto?', 4 => 'Vista previa', 5 => 'Guardar'];

    public int $paso = 1;

    /** @var list<array<string, mixed>> */
    public array $rubros = [];

    public string $umbral = '200000';

    /** Una sola elección de regiones para todas las líneas (vacía = todo el Perú). */
    public bool $mismasRegiones = true;

    /** @var list<string> */
    public array $regionesTodas = [];

    /** @var array<string, string> */
    public array $nueva = [];

    public string $otroRubro = '';

    #[On('tq-abrir-asistente-seace')]
    public function abrir(bool $desdeCero = false): void
    {
        $e = app(AsistenteBusquedaSeace::class)->estadoInicial($desdeCero);
        $this->rubros = $e['rubros'];
        $this->umbral = $e['umbral'];
        $marcados = array_values(array_filter($this->rubros, fn (array $r): bool => $r['marcado']));
        $distintas = array_unique(array_map(fn (array $r): string => json_encode($r['regiones']), $marcados));
        $this->mismasRegiones = count($distintas) <= 1;
        $this->regionesTodas = $marcados === [] ? [] : $marcados[0]['regiones'];
        $this->paso = 1;
        $this->dispatch('open-modal', id: 'tq-asistente-seace');
    }

    public function ir(int $paso): void
    {
        $this->paso = max(1, min(5, $paso));

        if ($this->mismasRegiones) {
            foreach ($this->rubros as $i => $_) {
                $this->rubros[$i]['regiones'] = $this->regionesTodas;
            }
        }
    }

    public function marcar(int $i): void
    {
        $this->rubros[$i]['marcado'] = ! $this->rubros[$i]['marcado'];
    }

    public function agregarPalabra(int $i, string $tipo): void
    {
        $p = mb_strtolower(trim((string) ($this->nueva["{$i}-{$tipo}"] ?? '')));

        if ($p !== '' && in_array($tipo, ['palabras', 'negativas'], true) && ! in_array($p, $this->rubros[$i][$tipo], true)) {
            $this->rubros[$i][$tipo][] = $p;
        }

        $this->nueva["{$i}-{$tipo}"] = '';
    }

    public function quitarPalabra(int $i, string $tipo, int $j): void
    {
        array_splice($this->rubros[$i][$tipo], $j, 1);
    }

    public function agregarOtro(): void
    {
        $nombre = trim($this->otroRubro);

        if ($nombre === '') {
            return;
        }

        app(AsistenteBusquedaSeace::class)->agregarRubro($nombre, [mb_strtolower($nombre)]);
        $this->rubros[] = ['rubro' => $nombre, 'nombre' => $nombre, 'id' => null, 'marcado' => true, 'palabras' => [mb_strtolower($nombre)], 'negativas' => [], 'regiones' => $this->regionesTodas, 'minimo' => '20000', 'excluidas' => []];
        $this->otroRubro = '';
    }

    public function region(?int $i, string $region): void
    {
        if ($i === null) {
            $this->regionesTodas = in_array($region, $this->regionesTodas, true) ? array_values(array_diff($this->regionesTodas, [$region])) : [...$this->regionesTodas, $region];

            return;
        }

        $actual = $this->rubros[$i]['regiones'];
        $this->rubros[$i]['regiones'] = in_array($region, $actual, true) ? array_values(array_diff($actual, [$region])) : [...$actual, $region];
    }

    public function todoElPeru(?int $i): void
    {
        $i === null ? $this->regionesTodas = [] : $this->rubros[$i]['regiones'] = [];
    }

    /** Una sugerencia aceptada agrega la palabra a su rubro (y lo marca); descartada, se olvida. */
    public function sugerencia(int $id, bool $aceptar): void
    {
        $s = collect(app(AsistenteBusquedaSeace::class)->sugerencias())->firstWhere('id', $id);
        app(AsistenteBusquedaSeace::class)->resolverSugerencia($id, $aceptar);

        if (! $aceptar || $s === null) {
            return;
        }

        foreach ($this->rubros as $i => $r) {
            if (FiltroConvocatorias::normal($r['rubro']) === FiltroConvocatorias::normal($s['rubro']) || FiltroConvocatorias::normal($r['nombre']) === FiltroConvocatorias::normal($s['rubro'])) {
                $this->rubros[$i]['marcado'] = true;
                $this->rubros[$i]['palabras'] = array_values(array_unique([...$r['palabras'], $s['palabra']]));

                return;
            }
        }

        $this->rubros[] = ['rubro' => $s['rubro'], 'nombre' => $s['rubro'], 'id' => null, 'marcado' => true, 'palabras' => [$s['palabra']], 'negativas' => [], 'regiones' => $this->regionesTodas, 'minimo' => '20000', 'excluidas' => []];
    }

    public function sugerirConIa(): void
    {
        try {
            $n = app(AsistenteBusquedaSeace::class)->sugerirConIa($this->rubros);
            Notification::make()->success()->title("La IA sugirió {$n} palabras: confírmalas una por una.")->send();
        } catch (Throwable $e) {
            Notification::make()->warning()->title($e->getMessage())->send();
        }
    }

    public function guardar(): void
    {
        if (! (auth()->user()?->can(AsistenteBusquedaSeace::PERMISO_CONFIGURAR) ?? false)) {
            Notification::make()->danger()->title('Solo quien tiene el permiso «Configurar la búsqueda» puede cambiarla.')->send();

            return;
        }

        $this->ir(5);

        try {
            app(AsistenteBusquedaSeace::class)->guardar($this->rubros, $this->umbral);
            $this->dispatch('close-modal', id: 'tq-asistente-seace');
            $this->dispatch('tq-busqueda-guardada');
            Notification::make()->success()->title('Búsqueda guardada: la bandeja se volvió a filtrar.')->send();
        } catch (Throwable $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
        }
    }

    public function render(): View
    {
        $s = app(AsistenteBusquedaSeace::class);

        return view('licitaciones::bandeja.asistente', [
            'pasos' => self::PASOS, 'regiones' => FiltroConvocatorias::REGIONES,
            'previa' => $this->paso >= 4 ? $s->vistaPrevia($this->rubros) : null,
            'sugerencias' => $this->paso === 1 ? $s->sugerencias() : [], 'ia' => $s->iaDisponible(),
            'puede' => auth()->user()?->can(AsistenteBusquedaSeace::PERMISO_CONFIGURAR) ?? false,
        ]);
    }
}
