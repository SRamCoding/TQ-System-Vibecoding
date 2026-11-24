{{-- Observación 9.6: el asistente de configuración inicial de la búsqueda del SEACE (ventana al medio, pasos). --}}
@php
    $nombreRegion = fn ($r) => \App\Modules\Crm\Licitaciones\Services\FiltroConvocatorias::nombreRegion($r);
    $marcados = array_filter($rubros, fn ($r) => $r['marcado']);
@endphp
<div>
    <style>
        .tq-as { display: flex; flex-direction: column; gap: 14px; }
        .tq-as-barra { height: 6px; border-radius: 99px; background: color-mix(in oklab, var(--gray-400) 25%, transparent); overflow: hidden; }
        .tq-as-barra > span { display: block; height: 100%; background: var(--primary-500); border-radius: 99px; transition: width .25s ease; }
        .tq-as-pasos { display: flex; gap: 6px; flex-wrap: wrap; font-size: 12px; }
        .tq-as-pasos button { padding: 2px 8px; border-radius: 99px; color: var(--gray-500); }
        .tq-as-pasos button[aria-current="step"] { background: color-mix(in oklab, var(--primary-500) 14%, transparent); color: var(--primary-700); font-weight: 600; }
        .dark .tq-as-pasos button[aria-current="step"] { color: var(--primary-300); }
        .tq-as-rubros { display: grid; grid-template-columns: repeat(auto-fill, minmax(12rem, 1fr)); gap: 8px; }
        .tq-as-rubro { border: 1px solid var(--tq-borde, var(--gray-200)); border-radius: 12px; padding: 10px 12px; text-align: start; background: var(--tq-superficie, white); }
        .tq-as-rubro[aria-pressed="true"] { border-color: var(--primary-500); box-shadow: inset 0 0 0 1px var(--primary-500); background: color-mix(in oklab, var(--primary-500) 7%, var(--tq-superficie, white)); }
        .tq-as-caja { border: 1px solid var(--tq-borde, var(--gray-200)); border-radius: 12px; padding: 10px 12px; display: flex; flex-direction: column; gap: 6px; }
        .tq-as-pal { display: inline-flex; align-items: center; gap: 4px; font-size: 12px; padding: 2px 8px; border-radius: 999px; background: color-mix(in oklab, var(--gray-400) 18%, transparent); }
        .tq-as-pal.neg { background: color-mix(in oklab, #D9534A 14%, transparent); color: color-mix(in oklch, #B0423A calc(100% - var(--tq-tinte-leve, 0%)), var(--tq-tinte, #B0423A)); }
        .tq-as-in { font-size: 12px; border: 1px solid var(--tq-borde, var(--gray-300)); border-radius: 999px; padding: 2px 10px; width: 9rem; background: transparent; color: inherit; }
        .tq-as-reg { font-size: 12px; padding: 3px 9px; border-radius: 999px; border: 1px solid var(--tq-borde, var(--gray-300)); }
        .tq-as-reg[aria-pressed="true"] { background: color-mix(in oklab, var(--primary-500) 14%, transparent); border-color: var(--primary-500); font-weight: 600; }
        .tq-as-marca { background: color-mix(in oklab, #E7B416 35%, transparent); border-radius: 3px; padding: 0 2px; }
    </style>
    <x-filament::modal id="tq-asistente-seace" width="4xl" heading="Configura qué convocatorias le interesan a Techquk" :close-by-clicking-away="false">
        <div class="tq-as">
            <div class="tq-as-barra"><span style="width: {{ $paso * 20 }}%"></span></div>
            <div class="tq-as-pasos">
                @foreach ($pasos as $n => $t)
                    <button type="button" wire:click="ir({{ $n }})" @if ($paso === $n) aria-current="step" @endif>{{ $n }}. {{ $t }}</button>
                @endforeach
            </div>

            @if ($paso === 1)
                <p style="font-size: 14px">Marca los rubros: cada uno será una línea de negocio, con sus palabras clave y negativas sugeridas (quita o agrega las que quieras).</p>
                <div class="tq-as-rubros">
                    @foreach ($rubros as $i => $r)
                        <button type="button" class="tq-as-rubro" aria-pressed="{{ $r['marcado'] ? 'true' : 'false' }}" wire:click="marcar({{ $i }})" wire:key="ru-{{ $i }}">
                            <div style="font-weight: 600; font-size: 14px">{{ $r['marcado'] ? '✓ ' : '' }}{{ $r['nombre'] }}</div>
                            <div style="font-size: 11px; color: var(--gray-500)">{{ count($r['palabras']) }} palabras · {{ count($r['negativas']) }} negativas</div>
                        </button>
                    @endforeach
                    <div class="tq-as-rubro" style="display: flex; gap: 6px; align-items: center">
                        <input class="tq-as-in" style="flex: 1; width: auto" wire:model="otroRubro" wire:keydown.enter.prevent="agregarOtro" placeholder="+ Otro rubro">
                        <x-filament::link tag="button" size="sm" wire:click="agregarOtro">Agregar</x-filament::link>
                    </div>
                </div>
                @foreach ($rubros as $i => $r)
                    @if ($r['marcado'])
                        <div class="tq-as-caja" wire:key="pal-{{ $i }}">
                            <strong style="font-size: 13px">{{ $r['nombre'] }}</strong>
                            @foreach (['palabras' => 'Palabras clave', 'negativas' => 'Palabras negativas'] as $tipo => $titulo)
                                <div style="display: flex; flex-wrap: wrap; gap: 5px; align-items: center">
                                    <span style="font-size: 11px; color: var(--gray-500); width: 7.5rem">{{ $titulo }}</span>
                                    @foreach ($r[$tipo] as $j => $p)
                                        <span class="tq-as-pal {{ $tipo === 'negativas' ? 'neg' : '' }}">{{ $p }}<button type="button" wire:click="quitarPalabra({{ $i }}, '{{ $tipo }}', {{ $j }})" aria-label="Quitar {{ $p }}">×</button></span>
                                    @endforeach
                                    <input class="tq-as-in" wire:model="nueva.{{ $i }}-{{ $tipo }}" wire:keydown.enter.prevent="agregarPalabra({{ $i }}, '{{ $tipo }}')" placeholder="+ agregar">
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endforeach
                <div class="tq-as-caja" style="background: color-mix(in oklab, var(--primary-500) 5%, transparent)">
                    <div style="display: flex; align-items: center; gap: 8px"><strong style="font-size: 13px; flex: 1">Sugerencias (de los proyectos que ganaste)</strong>
                        <x-filament::link tag="button" size="sm" icon="heroicon-o-sparkles" wire:click="sugerirConIa">{{ $ia ? 'Sugerir palabras' : 'Sugerir palabras (la IA no está configurada)' }}</x-filament::link></div>
                    @forelse ($sugerencias as $s)
                        <div style="display: flex; gap: 8px; align-items: center; font-size: 13px" wire:key="sug-{{ $s['id'] }}">
                            <span style="flex: 1">{{ $s['motivo'] }} <span style="color: var(--gray-500)">→ «{{ $s['palabra'] }}» en {{ $s['rubro'] }}</span></span>
                            <span style="font-size: 11px; color: var(--gray-500)">sugerido</span>
                            <x-filament::button size="xs" color="gray" wire:click="sugerencia({{ $s['id'] }}, false)">No</x-filament::button>
                            <x-filament::button size="xs" wire:click="sugerencia({{ $s['id'] }}, true)">Agregar</x-filament::button>
                        </div>
                    @empty
                        <div style="font-size: 12px; color: var(--gray-500)">Sin sugerencias por confirmar.</div>
                    @endforelse
                </div>
            @elseif ($paso === 2)
                <label style="display: flex; gap: 8px; align-items: center; font-size: 14px"><input type="checkbox" wire:model.live="mismasRegiones"> La misma elección para todas las líneas</label>
                @foreach ($mismasRegiones ? [null] : array_keys($marcados) as $i)
                    @php $elegidas = $i === null ? $regionesTodas : $rubros[$i]['regiones']; @endphp
                    <div class="tq-as-caja" wire:key="reg-{{ $i ?? 'todas' }}">
                        <strong style="font-size: 13px">{{ $i === null ? 'Todas las líneas' : $rubros[$i]['nombre'] }}</strong>
                        <div style="display: flex; flex-wrap: wrap; gap: 5px">
                            <button type="button" class="tq-as-reg" aria-pressed="{{ $elegidas === [] ? 'true' : 'false' }}" wire:click="todoElPeru({{ $i ?? 'null' }})">Todo el Perú</button>
                            @foreach ($regiones as $reg)
                                <button type="button" class="tq-as-reg" aria-pressed="{{ in_array($reg, $elegidas, true) ? 'true' : 'false' }}" wire:click="region({{ $i ?? 'null' }}, '{{ $reg }}')">{{ $nombreRegion($reg) }}</button>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            @elseif ($paso === 3)
                <p style="font-size: 14px">Desde qué valor referencial entra una convocatoria a cada línea, y desde cuánto se marca como prioritaria.</p>
                @foreach ($marcados as $i => $r)
                    <div style="display: flex; gap: 10px; align-items: center" wire:key="min-{{ $i }}">
                        <span style="flex: 1; font-size: 14px; font-weight: 600">{{ $r['nombre'] }}</span>
                        <span style="font-size: 13px; color: var(--gray-500)">Mínimo S/</span>
                        <x-filament::input.wrapper style="width: 10rem"><x-filament::input type="number" min="0" step="1000" wire:model="rubros.{{ $i }}.minimo" /></x-filament::input.wrapper>
                    </div>
                @endforeach
                <div style="display: flex; gap: 10px; align-items: center; border-top: 1px solid var(--tq-borde, var(--gray-200)); padding-top: 10px">
                    <span style="flex: 1; font-size: 14px; font-weight: 600">Prioritaria desde</span>
                    <span style="font-size: 13px; color: var(--gray-500)">S/</span>
                    <x-filament::input.wrapper style="width: 10rem"><x-filament::input type="number" min="0" step="10000" wire:model="umbral" /></x-filament::input.wrapper>
                </div>
            @else
                @if ($previa !== null)
                    <div style="font-size: 15px">Con esta configuración, en el último mes habrían entrado <strong>{{ $previa['total'] }} {{ $previa['total'] === 1 ? 'convocatoria' : 'convocatorias' }}</strong> <span style="font-size: 12px; color: var(--gray-500)">(de {{ $previa['leidas'] }} leídas del OECE)</span>.</div>
                    <div style="display: flex; gap: 8px; flex-wrap: wrap">
                        @foreach ($previa['porLinea'] as $linea => $n)<span class="tq-as-pal">{{ $linea }}: <strong>{{ $n }}</strong></span>@endforeach
                    </div>
                    @if ($previa['ayuda'])<div style="font-size: 13px; padding: 8px 12px; border-radius: 10px; background: color-mix(in oklab, var(--warning-500) 13%, transparent)">{{ $previa['ayuda'] }}</div>@endif
                    @foreach ($previa['ejemplos'] as $e)
                        @php
                            $obj = e($e['objeto']);
                            foreach ($e['coincide'] as $p) { $obj = preg_replace('/('.preg_quote(e($p), '/').')/iu', '<mark class="tq-as-marca">$1</mark>', $obj); }
                        @endphp
                        <div class="tq-as-caja" style="gap: 2px"><div style="font-size: 13px">{!! $obj !!}</div><div style="font-size: 12px; color: var(--gray-500)">{{ $e['entidad'] }} · {{ $e['linea'] }}</div></div>
                    @endforeach
                @endif
                @if ($paso === 5 && ! $puede)
                    <div style="font-size: 13px; color: var(--gray-500)">Solo lectura: la búsqueda la cambia quien tiene el permiso «Configurar la búsqueda de convocatorias del SEACE».</div>
                @endif
            @endif
        </div>
        <x-slot name="footerActions">
            @if ($paso > 1)<x-filament::button color="gray" wire:click="ir({{ $paso - 1 }})">Atrás</x-filament::button>@endif
            @if ($paso < 5)
                <x-filament::button wire:click="ir({{ $paso + 1 }})">Siguiente</x-filament::button>
            @elseif ($puede)
                <x-filament::button icon="heroicon-o-check" wire:click="guardar">Guardar y filtrar la bandeja</x-filament::button>
            @endif
        </x-slot>
    </x-filament::modal>
</div>
