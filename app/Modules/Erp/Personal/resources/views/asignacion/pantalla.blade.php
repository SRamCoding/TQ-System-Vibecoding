{{--
    Observación 6.7 (MV-067): Personal asignado en tarjetas de dos columnas (una en pantallas angostas), una por persona
    activa: arriba su foto o avatar, nombre, «categoría · vínculo» y el SCTR (coral si está vencido o no tiene); debajo sus
    cuatro semanas como barritas de ocupación (gris en 0 %, normal hasta 100 %, coral «sobreasignado» si pasa). Clic: el
    detalle en el panel deslizante.
--}}
@php($tarjetas = $this->tarjetas())
<x-filament-panels::page>
    <div class="tq-pa">
        <style>
            .tq-pa { --tq-pa-elev: var(--tq-superficie-elevada, #fff); --tq-pa-borde: var(--tq-borde, var(--gray-200)); --tq-pa-suave: var(--tq-texto-suave, var(--gray-500));
                --tq-pa-acento: var(--tq-franja-misionales, #0790A8); --tq-pa-coral: color-mix(in oklch, #C2532F calc(100% - var(--tq-tinte-leve, 0%)), var(--tq-tinte, #C2532F)); display: flex; flex-direction: column; gap: 1rem; }
            .dark .tq-pa { --tq-pa-elev: var(--tq-superficie-elevada, #3A3731); --tq-pa-borde: var(--tq-borde, #45413A); --tq-pa-suave: var(--tq-texto-suave, #A8ADB4);
                --tq-pa-acento: var(--tq-franja-misionales, #7FE9FF); --tq-pa-coral: color-mix(in oklch, #F2A58C calc(100% - var(--tq-tinte-leve, 0%)), var(--tq-tinte, #F2A58C)); }
            .tq-pa-buscar { display: flex; justify-content: flex-end; }
            .tq-pa-rejilla { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
            @media (max-width: 900px) { .tq-pa-rejilla { grid-template-columns: minmax(0, 1fr); } }
            .tq-pa-tarjeta { display: flex; flex-direction: column; gap: 0.9rem; padding: 1rem 1.15rem; border-radius: 0.9rem; background: var(--tq-pa-elev); border: 1px solid var(--tq-pa-borde);
                cursor: pointer; text-align: left; transition: box-shadow .15s ease; }
            .tq-pa-tarjeta:hover { box-shadow: 0 6px 18px rgb(0 0 0 / .08); }
            .tq-pa-tarjeta:focus-visible { outline: 2px solid var(--primary-600); outline-offset: 2px; }
            .tq-pa-cabeza { display: flex; align-items: center; gap: 0.85rem; }
            .tq-pa-nombre { font-weight: 600; line-height: 1.25; }
            .tq-pa-sub { font-size: 0.8rem; color: var(--tq-pa-suave); }
            .tq-pa-sctr { margin-left: auto; align-self: flex-start; white-space: nowrap; font-size: 0.75rem; font-weight: 600; padding: 0.15rem 0.6rem; border-radius: 9999px;
                border: 1px solid var(--tq-pa-borde); color: var(--tq-pa-suave); }
            .tq-pa-sctr[data-mal] { border-color: var(--tq-pa-coral); color: var(--tq-pa-coral); background: color-mix(in oklab, var(--tq-pa-coral) 10%, transparent); }
            .tq-pa-semanas { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 0.75rem; }
            @media (max-width: 520px) { .tq-pa-semanas { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
            .tq-pa-semana { display: flex; flex-direction: column; gap: 0.3rem; font-size: 0.75rem; }
            .tq-pa-semana-nombre { color: var(--tq-pa-suave); font-weight: 600; white-space: nowrap; }
            .tq-pa-barra { position: relative; height: 0.45rem; border-radius: 9999px; background: color-mix(in oklab, var(--tq-pa-suave) 22%, transparent); overflow: hidden; }
            .tq-pa-lleno { position: absolute; inset: 0 auto 0 0; border-radius: 9999px; background: var(--tq-pa-acento); transition: width .3s ease; }
            .tq-pa-semana[data-tono="libre"] .tq-pa-lleno { background: var(--gray-400); }
            .tq-pa-semana[data-tono="sobre"] .tq-pa-lleno { background: var(--tq-pa-coral); }
            .tq-pa-pct { font-variant-numeric: tabular-nums; font-weight: 600; }
            .tq-pa-semana[data-tono="libre"] .tq-pa-pct { color: var(--tq-pa-suave); font-weight: 500; }
            .tq-pa-semana[data-tono="sobre"] .tq-pa-pct { color: var(--tq-pa-coral); }
            .tq-pa-vacio { padding: 2rem; text-align: center; color: var(--tq-pa-suave); }
            @media (prefers-reduced-motion: reduce) { .tq-pa-tarjeta, .tq-pa-lleno { transition: none; } }
        </style>

        <div class="tq-pa-buscar">
            <x-filament::input.wrapper prefix-icon="heroicon-o-magnifying-glass" style="min-width: 16rem">
                <x-filament::input type="search" wire:model.live.debounce.300ms="buscar" placeholder="Buscar por nombre" aria-label="Buscar personas" />
            </x-filament::input.wrapper>
        </div>

        @if ($tarjetas === [])
            <div class="tq-pa-vacio">Nadie con ese nombre.</div>
        @endif

        <div class="tq-pa-rejilla">
            @foreach ($tarjetas as $t)
                <article class="tq-pa-tarjeta" data-tq-fila="persona-{{ $t['id'] }}" wire:key="persona-{{ $t['id'] }}" role="button" tabindex="0" aria-label="{{ $t['nombres'] }}"
                    wire:click="mountAction('detalle', { colaborador: {{ $t['id'] }} })" x-on:keydown.enter.prevent="$wire.mountAction('detalle', { colaborador: {{ $t['id'] }} })">
                    <div class="tq-pa-cabeza">
                        @include('personal::avatar', ['a' => $t['avatar'], 'tam' => 48])
                        <div style="min-width: 0">
                            <div class="tq-pa-nombre">{{ $t['nombres'] }}</div>
                            <div class="tq-pa-sub">{{ $t['categoria'] }} · {{ $t['vinculo'] }}</div>
                            <div class="tq-pa-sub">{{ $t['jornada'] }}</div>
                        </div>
                        <span class="tq-pa-sctr" @if ($t['sctr_mal']) data-mal @endif>{{ $t['sctr'] }}</span>
                    </div>
                    <div class="tq-pa-semanas">
                        @foreach ($t['semanas'] as $s)
                            <div class="tq-pa-semana" data-tono="{{ $s['tono'] }}">
                                <span class="tq-pa-semana-nombre">{{ $s['etiqueta'] }}</span>
                                <span class="tq-pa-barra"><span class="tq-pa-lleno" style="width: {{ min(100, $s['total']) }}%"></span></span>
                                <span><span class="tq-pa-pct">{{ $s['total'] }} %</span>@if ($s['tono'] === 'sobre') <span class="tq-pa-pct">· sobreasignado</span>@endif</span>
                            </div>
                        @endforeach
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
