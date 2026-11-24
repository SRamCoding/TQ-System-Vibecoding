{{--
    Observación 6.11 (ficha t2-contratos): los contratos en tarjetas que avisan antes. Arriba, el selector de vista
    (Contratos · Calendario de vencimientos) y las cinco cifras; luego los chips, el buscador, el cliente y el orden; las
    tarjetas en dos columnas (una en pantallas angostas). Clic: resumen en panel deslizante; doble clic: la ficha.
--}}
@php
    $d = $this->datos();
    $soles = fn ($m): string => 'S/ '.number_format((float) $m, 2);
    $porCompletar = $d['agrupar'] ? array_values(array_filter($d['tarjetas'], fn (array $t): bool => $t['estado'] === 'borrador')) : [];
    $resto = $d['agrupar'] ? array_values(array_filter($d['tarjetas'], fn (array $t): bool => $t['estado'] !== 'borrador')) : $d['tarjetas'];
@endphp
<x-filament-panels::page>
    <div class="tq-ct">
        <style>
            .tq-ct { --tq-ct-sup: var(--tq-superficie, #fff); --tq-ct-elev: var(--tq-superficie-elevada, #fff); --tq-ct-borde: var(--tq-borde, var(--gray-200)); --tq-ct-suave: var(--tq-texto-suave, var(--gray-500));
                --tq-ct-acento: var(--tq-franja-misionales, #0790A8); --tq-ct-coral: color-mix(in oklch, #C2532F calc(100% - var(--tq-tinte-leve, 0%)), var(--tq-tinte, #C2532F)); --tq-ct-ambar: var(--warning-500); --tq-ct-verde: var(--success-500);
                display: flex; flex-direction: column; gap: 1rem; }
            .dark .tq-ct { --tq-ct-sup: var(--tq-superficie, #33302B); --tq-ct-elev: var(--tq-superficie-elevada, #3A3731); --tq-ct-borde: var(--tq-borde, #45413A); --tq-ct-suave: var(--tq-texto-suave, #A8ADB4);
                --tq-ct-acento: var(--tq-franja-misionales, #7FE9FF); --tq-ct-coral: color-mix(in oklch, #F2A58C calc(100% - var(--tq-tinte-leve, 0%)), var(--tq-tinte, #F2A58C)); }
            .tq-ct-coral { color: var(--tq-ct-coral); font-weight: 600; }
            .tq-ct-suave { color: var(--tq-ct-suave); }
            .tq-ct-cifras { display: grid; grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr)); gap: 0.75rem; }
            .tq-ct-cifra { padding: 0.75rem 1rem; border-radius: 0.75rem; background: var(--tq-ct-sup); border: 1px solid var(--tq-ct-borde); }
            .tq-ct-cifra-titulo { font-size: 0.8rem; font-weight: 600; color: var(--tq-ct-suave); }
            .tq-ct-cifra-valor { font-size: 1.375rem; font-weight: 600; line-height: 1.875rem; font-variant-numeric: tabular-nums; }
            .tq-ct-franja { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.75rem 1.25rem; padding: 0.75rem 1rem; border-radius: 0.75rem; background: var(--tq-ct-sup); border: 1px solid var(--tq-ct-borde); }
            .tq-ct-chips, .tq-ct-derecha { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem; }
            .tq-ct-chip { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.7rem; border-radius: 9999px; font-size: 0.8rem; font-weight: 600; border: 1px dashed var(--gray-400); color: var(--gray-600); }
            .dark .tq-ct-chip { border-color: var(--gray-500); color: var(--gray-300); }
            .tq-ct-chip[aria-pressed="true"] { border-style: solid; border-color: var(--primary-600); background: var(--primary-600); color: white; }
            .dark .tq-ct-chip[aria-pressed="true"] { border-color: var(--primary-400); background: var(--primary-400); color: var(--gray-950); }
            .tq-ct-chip-n { font-variant-numeric: tabular-nums; opacity: .8; }
            .tq-ct-limpiar { font-size: 0.8rem; color: var(--primary-600); text-decoration: underline; }
            .dark .tq-ct-limpiar { color: var(--primary-400); }
            .tq-ct-grupo { font-size: 0.85rem; font-weight: 700; letter-spacing: .02em; color: var(--tq-ct-suave); margin-top: 0.25rem; }
            .tq-ct-rejilla { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
            @media (max-width: 900px) { .tq-ct-rejilla { grid-template-columns: minmax(0, 1fr); } }
            .tq-ct-tarjeta { position: relative; display: flex; flex-direction: column; gap: 0.75rem; padding: 1rem 1.1rem 0.9rem 1.25rem; border-radius: 0.9rem; background: var(--tq-ct-elev); border: 1px solid var(--tq-ct-borde); cursor: pointer; text-align: left; transition: box-shadow .15s ease, transform .15s ease; overflow: hidden; }
            .tq-ct-tarjeta::before { content: ''; position: absolute; inset: 0 auto 0 0; width: 4px; background: var(--tq-ct-acento); }
            .tq-ct-tarjeta[data-alerta="coral"]::before { background: var(--tq-ct-coral); }
            .tq-ct-tarjeta[data-alerta="ambar"]::before { background: var(--tq-ct-ambar); }
            .tq-ct-tarjeta[data-alerta="gris"]::before { background: var(--gray-400); }
            .tq-ct-tarjeta:hover { box-shadow: 0 6px 18px rgb(0 0 0 / .08); }
            .tq-ct-tarjeta:focus-visible { outline: 2px solid var(--primary-600); outline-offset: 2px; }
            .tq-ct-cabeza { display: flex; justify-content: space-between; gap: 1rem; }
            .tq-ct-cliente { font-size: 0.78rem; font-weight: 600; color: var(--tq-ct-suave); }
            .tq-ct-objeto { font-weight: 600; line-height: 1.3; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
            .tq-ct-codigo { font-family: ui-monospace, monospace; font-size: 0.8rem; margin-top: 0.15rem; }
            .tq-ct-monto { font-family: ui-monospace, monospace; font-weight: 700; white-space: nowrap; text-align: right; }
            .tq-ct-barras { display: flex; flex-direction: column; gap: 0.4rem; }
            .tq-ct-barra-fila { display: grid; grid-template-columns: 3.6rem minmax(0, 1fr); column-gap: 0.6rem; row-gap: 0.1rem; align-items: center; font-size: 0.78rem; }
            .tq-ct-barra-nombre { color: var(--tq-ct-suave); font-weight: 600; grid-row: span 2; }
            .tq-ct-barra { position: relative; height: 0.45rem; border-radius: 9999px; background: color-mix(in oklab, var(--tq-ct-suave) 22%, transparent); overflow: hidden; }
            .tq-ct-barra-lleno { position: absolute; inset: 0 auto 0 0; border-radius: 9999px; transition: width .3s ease; }
            .tq-ct-barra-lleno[data-tono="plazo"] { background: var(--tq-ct-acento); }
            .tq-ct-barra-lleno[data-tono="avance"] { background: var(--tq-ct-verde); }
            .tq-ct-barra-lleno[data-tono="coral"] { background: var(--tq-ct-coral); }
            .tq-ct-barra-lleno[data-tono="gris"] { background: var(--gray-400); }
            .tq-ct-barra-texto { font-variant-numeric: tabular-nums; }
            .tq-ct-hitos { position: relative; height: 1.6rem; margin: 0.9rem 0.4rem 0; }
            .tq-ct-hitos-linea { position: absolute; left: 0; right: 0; top: 0.8rem; height: 2px; background: var(--tq-ct-borde); }
            .tq-ct-hito { position: absolute; top: 0.5rem; width: 0.65rem; height: 0.65rem; margin-left: -0.325rem; border-radius: 9999px; border: 2px solid var(--tq-ct-acento); background: var(--tq-ct-elev); }
            .tq-ct-hito[data-estado="cumplido"] { background: var(--tq-ct-acento); }
            .tq-ct-hito[data-estado="vencido"] { border-color: var(--tq-ct-coral); background: var(--tq-ct-coral); }
            .tq-ct-hoy { position: absolute; top: 0; bottom: 0; width: 0; border-left: 2px dashed var(--tq-ct-suave); }
            .tq-ct-hoy span { position: absolute; top: -0.95rem; left: 0; transform: translateX(-50%); font-size: 0.65rem; font-weight: 700; color: var(--tq-ct-suave); }
            .tq-ct-hitos-pie { display: flex; justify-content: space-between; gap: 0.5rem; font-size: 0.68rem; color: var(--tq-ct-suave); }
            .tq-ct-hecho { color: inherit; }
            .tq-ct-pie { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.4rem 1rem; font-size: 0.78rem; padding-top: 0.6rem; border-top: 1px solid var(--tq-ct-borde); }
            .tq-ct-semaforo { display: inline-flex; align-items: center; gap: 0.4rem; }
            .tq-ct-luz { width: 0.6rem; height: 0.6rem; border-radius: 9999px; background: var(--gray-400); }
            .tq-ct-luz[data-tono="verde"] { background: var(--tq-ct-verde); }
            .tq-ct-luz[data-tono="ambar"] { background: var(--tq-ct-ambar); }
            .tq-ct-luz[data-tono="coral"] { background: var(--tq-ct-coral); }
            .tq-ct-vacio { padding: 2.5rem 1rem; text-align: center; color: var(--tq-ct-suave); border: 1px dashed var(--tq-ct-borde); border-radius: 0.9rem; }
            .tq-ct-calendario { display: flex; gap: 0.75rem; overflow-x: auto; padding-bottom: 0.5rem; }
            .tq-ct-columna { flex: 0 0 15rem; display: flex; flex-direction: column; gap: 0.5rem; padding: 0.75rem; border-radius: 0.9rem; background: var(--tq-ct-sup); border: 1px solid var(--tq-ct-borde); }
            .tq-ct-columna[data-columna="vencidos"] { border-color: color-mix(in oklab, var(--tq-ct-coral) 55%, var(--tq-ct-borde)); }
            .tq-ct-columna-titulo { display: flex; justify-content: space-between; font-size: 0.85rem; font-weight: 700; }
            .tq-ct-evento { display: flex; gap: 0.5rem; align-items: flex-start; width: 100%; padding: 0.5rem 0.6rem; border-radius: 0.6rem; background: var(--tq-ct-elev); border: 1px solid var(--tq-ct-borde); text-align: left; font-size: 0.78rem; }
            .tq-ct-evento:hover, .tq-ct-evento:focus-visible { border-color: var(--primary-600); }
            .tq-ct-evento[data-tipo="fianza"] svg { color: var(--tq-ct-ambar); }
            .tq-ct-evento[data-tipo="fin"] svg { color: var(--tq-ct-acento); }
            .tq-ct-columna[data-columna="vencidos"] .tq-ct-evento svg { color: var(--tq-ct-coral); }
            @media (prefers-reduced-motion: reduce) { .tq-ct-tarjeta, .tq-ct-barra-lleno { transition: none; } }
            html.tq-sin-animaciones .tq-ct-tarjeta, html.tq-sin-animaciones .tq-ct-barra-lleno { transition: none; }
        </style>

        <x-filament::tabs label="Vistas de Contratos" contained>
            <x-filament::tabs.item :active="$this->vista === 'contratos'" wire:click="poner('vista', 'contratos')" icon="heroicon-o-squares-2x2">Contratos</x-filament::tabs.item>
            <x-filament::tabs.item :active="$this->vista === 'calendario'" wire:click="poner('vista', 'calendario')" icon="heroicon-o-calendar-days">Calendario de vencimientos</x-filament::tabs.item>
        </x-filament::tabs>

        <section class="tq-ct-cifras" aria-label="Resumen de contratos">
            <div class="tq-ct-cifra" data-cifra="vigentes"><div class="tq-ct-cifra-titulo">Contratos vigentes</div><div class="tq-ct-cifra-valor">{{ $d['cifras']['vigentes'] }}</div></div>
            <div class="tq-ct-cifra" data-cifra="monto"><div class="tq-ct-cifra-titulo">Monto vigente</div><div class="tq-ct-cifra-valor">{{ $soles($d['cifras']['monto']) }}</div></div>
            <div class="tq-ct-cifra" data-cifra="riesgo"><div class="tq-ct-cifra-titulo">Penalidad en riesgo</div><div @class(['tq-ct-cifra-valor', 'tq-ct-coral' => bccomp($d['cifras']['riesgo'], '0', 2) > 0])>{{ $soles($d['cifras']['riesgo']) }}</div></div>
            <div class="tq-ct-cifra" data-cifra="fianzas"><div class="tq-ct-cifra-titulo">Cartas fianza a 30 días</div><div @class(['tq-ct-cifra-valor', 'tq-ct-coral' => (int) $d['cifras']['fianzas'] > 0])>{{ $d['cifras']['fianzas'] }}</div></div>
            <div class="tq-ct-cifra" data-cifra="garantizado"><div class="tq-ct-cifra-titulo">Garantizado con bancos</div><div class="tq-ct-cifra-valor">{{ $soles($d['cifras']['garantizado']) }}</div></div>
        </section>

        @if ($this->vista === 'calendario')
            <div class="tq-ct-calendario" aria-label="Calendario de vencimientos">
                @foreach ($d['calendario'] as $col)
                    <section class="tq-ct-columna" data-columna="{{ $col['clave'] }}" aria-label="{{ $col['titulo'] }}">
                        <div class="tq-ct-columna-titulo"><span>{{ $col['titulo'] }}</span><span class="tq-ct-suave">{{ count($col['items']) }}</span></div>
                        @forelse ($col['items'] as $e)
                            <button type="button" class="tq-ct-evento" data-tipo="{{ $e['tipo'] }}" wire:key="evento-{{ $e['tipo'] }}-{{ $e['contrato_id'] }}-{{ $loop->index }}"
                                wire:click="mountAction('resumen', { contrato: {{ $e['contrato_id'] }} })">
                                <x-filament::icon :icon="$e['tipo'] === 'fianza' ? 'heroicon-o-shield-check' : 'heroicon-o-flag'" class="h-4 w-4 shrink-0" style="margin-top: 0.1rem" />
                                <span style="display: flex; flex-direction: column; gap: 0.1rem; min-width: 0">
                                    <span><span class="tq-ct-codigo">{{ $e['codigo'] }}</span> · {{ $e['texto'] }}</span>
                                    <span class="tq-ct-suave">{{ $e['cuando'] }} · <span style="font-family: ui-monospace, monospace">{{ $soles($e['monto']) }}</span></span>
                                </span>
                            </button>
                        @empty
                            <span class="tq-ct-suave" style="font-size: 0.78rem">Nada vence</span>
                        @endforelse
                    </section>
                @endforeach
            </div>
        @else
            <div class="tq-ct-franja">
                <div class="tq-ct-chips" role="group" aria-label="Filtros">
                    @foreach (\App\Modules\Erp\Contratos\Services\TableroContratos::FILTROS as $clave => $nombre)
                        <button type="button" class="tq-ct-chip" aria-pressed="{{ $this->filtro === $clave ? 'true' : 'false' }}" wire:click="poner('filtro', '{{ $clave }}')" data-filtro="{{ $clave }}">
                            {{ $nombre }} <span class="tq-ct-chip-n">{{ $d['cuentas'][$clave] }}</span>
                        </button>
                    @endforeach
                    @if ($d['hayFiltros'])
                        <button type="button" class="tq-ct-limpiar" wire:click="limpiar">Limpiar filtros</button>
                    @endif
                </div>
                <div class="tq-ct-derecha">
                    <x-filament::input.wrapper prefix-icon="heroicon-o-magnifying-glass" style="min-width: 14rem">
                        <x-filament::input type="search" wire:model.live.debounce.300ms="buscar" placeholder="Código, n.° de la entidad, objeto o cliente" aria-label="Buscar contratos" />
                    </x-filament::input.wrapper>
                    <x-filament::input.wrapper style="max-width: 15rem">
                        <x-filament::input.select wire:change="poner('cliente', $event.target.value)" aria-label="Cliente">
                            <option value="">Todos los clientes</option>
                            @foreach ($d['clientes'] as $id => $nombre)
                                <option value="{{ $id }}" @selected($this->cliente === (int) $id)>{{ $nombre }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                    <x-filament::input.wrapper prefix="Orden">
                        <x-filament::input.select wire:change="poner('orden', $event.target.value)" aria-label="Orden">
                            @foreach (\App\Modules\Erp\Contratos\Services\TableroContratos::ORDENES as $clave => $nombre)
                                <option value="{{ $clave }}" @selected($this->orden === $clave)>{{ $nombre }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>
            </div>

            @if ($d['tarjetas'] === [])
                <div class="tq-ct-vacio">Ningún contrato con estos filtros.</div>
            @endif

            @if ($porCompletar !== [])
                <div class="tq-ct-grupo">Por completar · {{ count($porCompletar) }}</div>
                <div class="tq-ct-rejilla">
                    @foreach ($porCompletar as $t)
                        @include('contratos::tarjeta', ['t' => $t, 'gestiona' => $d['gestiona']])
                    @endforeach
                </div>
                @if ($resto !== [])
                    <div class="tq-ct-grupo">En curso y culminados · {{ count($resto) }}</div>
                @endif
            @endif

            @if ($resto !== [])
                <div class="tq-ct-rejilla">
                    @foreach ($resto as $t)
                        @include('contratos::tarjeta', ['t' => $t, 'gestiona' => $d['gestiona']])
                    @endforeach
                </div>
            @endif
        @endif
    </div>
</x-filament-panels::page>
