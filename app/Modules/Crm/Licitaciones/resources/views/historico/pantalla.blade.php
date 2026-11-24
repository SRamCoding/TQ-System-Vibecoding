{{-- Observación 8.12: el ensayo, el avance de la carga con pausar y reanudar, y la verificación. --}}
@php
    $a = $avance;
    $meses = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Set', 'Oct', 'Nov', 'Dic'];
    $mb = fn ($b) => number_format($b / 1048576, 1).' MB';
    $ensayo = collect($a['ensayo']);
@endphp
<div style="display: flex; flex-direction: column; gap: 16px" @if ($a['estado'] === 'en_curso') wire:poll.5s @endif>
    <x-filament::section compact>
        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 14px">
            <div style="flex: 1; min-width: 16rem">
                <div style="font-weight: 700; font-size: 16px">
                    @switch($a['estado'])
                        @case('en_curso') Importando histórico {{ $this->anio }} · {{ $a['porcentaje'] }} % @break
                        @case('pausada') Histórico {{ $this->anio }} en pausa · {{ $a['porcentaje'] }} % @break
                        @case('hecha') Histórico {{ $this->anio }} importado @break
                        @case('error') La importación de {{ $this->anio }} se detuvo por un error · {{ $a['porcentaje'] }} % @break
                        @default Histórico {{ $this->anio }} sin importar
                    @endswitch
                </div>
                <div style="font-size: 13px; color: var(--tq-texto-suave, var(--gray-500))">Solo las regiones de tus líneas de negocio: {{ implode(', ', array_map(fn ($r) => \App\Modules\Crm\Licitaciones\Services\FiltroConvocatorias::nombreRegion($r), $regiones)) ?: 'todas' }} · {{ number_format($a['guardados']) }} procesos guardados</div>
                <div style="height: 8px; border-radius: 99px; background: var(--tq-borde, var(--gray-200)); margin-top: 8px"><div style="height: 8px; border-radius: 99px; width: {{ $a['porcentaje'] }}%; background: var(--primary-600)"></div></div>
            </div>
            @if ($a['estado'] === 'en_curso')
                <x-filament::button color="gray" wire:click="pausar" icon="heroicon-o-pause">Pausar</x-filament::button>
            @elseif ($a['estado'] !== 'hecha')
                <x-filament::button wire:click="reanudar" icon="heroicon-o-play" :disabled="$ensayo->isEmpty()">{{ $a['estado'] === 'sin_empezar' ? 'Importar' : 'Reanudar' }}</x-filament::button>
            @else
                <x-filament::button color="gray" wire:click="verificar" icon="heroicon-o-check-badge">Verificar</x-filament::button>
            @endif
        </div>
        @if ($ensayo->isEmpty())
            <p style="margin-top: 10px; font-size: 13px">Primero el ensayo: <code>php artisan seace:historico {{ $this->anio }} --ensayo</code> (solo cuenta, no guarda).</p>
        @endif
    </x-filament::section>

    @if ($ensayo->isNotEmpty())
        <x-filament::section heading="Ensayo (sin guardar)" compact>
            <div style="display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 4px 12px; font-size: 13px; font-variant-numeric: tabular-nums">
                <b>Mes</b><b style="text-align: right">Procesos</b><b style="text-align: right">En tus regiones</b><b style="text-align: right">Ya estaban</b><b style="text-align: right">Peso</b><b style="text-align: right">Avance</b>
                @foreach ($ensayo as $m)
                    @php $c = collect($a['meses'])->firstWhere('mes', $m->mes); @endphp
                    <span>{{ $meses[$m->mes] }} {{ $this->anio }}</span><span style="text-align: right">{{ number_format($m->procesos) }}</span><span style="text-align: right">{{ number_format($m->en_regiones) }}</span>
                    <span style="text-align: right">{{ number_format($m->ya_estaban) }}</span><span style="text-align: right">{{ $mb($m->bytes) }}</span>
                    <span style="text-align: right">{{ $c === null ? '—' : ['hecha' => 'hecho', 'en_curso' => 'en curso', 'pausada' => 'en pausa', 'error' => 'error', 'pendiente' => 'pendiente'][$c->estado] ?? $c->estado }}</span>
                @endforeach
                <b>Total</b><b style="text-align: right">{{ number_format($ensayo->sum('procesos')) }}</b><b style="text-align: right">{{ number_format($ensayo->sum('en_regiones')) }}</b><b style="text-align: right">{{ number_format($ensayo->sum('ya_estaban')) }}</b><b style="text-align: right">{{ $mb($ensayo->sum('bytes')) }}</b><span></span>
            </div>
        </x-filament::section>
    @endif

    @if ($this->verificacion)
        <x-filament::section heading="Verificación" compact>
            <p style="font-size: 14px">OECE (en tus regiones): <b>{{ number_format($this->verificacion['oece']) }}</b> · guardados: <b>{{ number_format($this->verificacion['guardados']) }}</b></p>
            <ul style="font-size: 13px; margin-top: 6px">
                @foreach ($this->verificacion['muestra'] as $m)
                    <li>{{ $m['ok'] ? '✓' : '✗' }} <a href="https://contratacionesabiertas.oece.gob.pe/proceso/{{ $m['ocid'] }}" target="_blank" style="text-decoration: underline">{{ $m['ocid'] }}</a> · {{ $m['detalle'] }}</li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif
</div>
