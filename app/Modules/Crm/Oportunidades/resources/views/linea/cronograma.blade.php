{{--
    MV-060: la Línea de tiempo como el «Cronograma refinado» del lienzo (`docs/vibe/pedidos/lienzos/linea-de-tiempo/`):
    una fila por oportunidad con su gema, nombre, cliente y monto; a la derecha, un tramo redondeado por cada etapa que
    recorrió (más oscuro al avanzar), la línea punteada desde hoy hasta el rombo dorado del cierre esperado (coral y
    «venció» si ya pasó) y la línea «Hoy». Arriba, los filtros en chips y «Ordenar por». En «Por urgencia», un separador
    con la cuenta de vencidas. Al cambiar el orden o un filtro, las filas se deslizan a su nuevo lugar (escalonadas) y las
    que salen se recogen (`data-tq-fila` dentro de `data-tq-escalonar`, del script de animación de filas). Recibe lo de
    `DibujoDelCronograma::datos()`, `$personas` y `$fichaAbierta`.
--}}
@php
    use App\Modules\Crm\Oportunidades\Filament\DibujoDelCronograma as D;
    $etapas = ['prospecto' => ['Prospecto', 'en_bruto'], 'calificada' => ['Calificada', 'desbastada'], 'propuesta_enviada' => ['Propuesta enviada', 'tallada'], 'negociacion' => ['Negociación', 'pulida']];
    $hayFiltros = $this->buscar !== '' || $this->vencimiento !== null || $this->etapa !== null || $this->responsable !== 'mias';
    $separadorPuesto = false;
@endphp
<div class="tq-linea" data-cronograma>
    <style>
        .tq-linea { --tq-l-izq: 330px; --tq-l-sup: var(--tq-superficie, #fff); --tq-l-borde: var(--tq-borde, var(--gray-200)); --tq-l-suave: var(--tq-texto-suave, var(--gray-500)); --tq-l-cab: var(--tq-superficie-elevada, var(--gray-50));
            --tq-l-acento: #0790A8; --tq-l-coral: #C2532F; --tq-l-oro: #F2C14E; --tq-l-oro-texto: #9A7000; --tq-l-hoy: rgba(31, 35, 40, .55); --tq-l-hoy-fondo: #1F2328; --tq-l-hoy-texto: #FFFFFF;
            display: flex; flex-direction: column; gap: 14px; margin-top: 0.5rem; }
        .dark .tq-linea { --tq-l-sup: var(--tq-superficie, #33302B); --tq-l-borde: var(--tq-borde, #45413A); --tq-l-suave: var(--tq-texto-suave, #A8ADB4); --tq-l-cab: var(--tq-superficie-elevada, #2F2C28);
            --tq-l-acento: #7FE9FF; --tq-l-coral: #F2A58C; --tq-l-oro-texto: #E8C46A; --tq-l-hoy: rgba(236, 237, 239, .6); --tq-l-hoy-fondo: #ECEDEF; --tq-l-hoy-texto: #1E1F22; }
        .tq-linea-barra { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
        .tq-linea-chip { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; border: 1px solid var(--tq-l-borde); background: var(--tq-l-sup); color: inherit; cursor: pointer; }
        .tq-linea-chip[aria-pressed="true"] { background: var(--primary-600); border-color: var(--primary-600); color: #fff; }
        .dark .tq-linea-chip[aria-pressed="true"] { background: var(--primary-400); border-color: var(--primary-400); color: var(--gray-950); }
        .tq-linea-sep { width: 1px; height: 20px; background: var(--tq-l-borde); margin: 0 4px; }
        .tq-linea-sel { padding: 4px 8px; border-radius: 8px; font-size: 12px; font-weight: 600; border: 1px solid var(--tq-l-borde); background: var(--tq-l-sup); color: inherit; }
        .tq-linea-leyenda { display: flex; flex-wrap: wrap; gap: 16px; font-size: 12px; color: var(--tq-l-suave); }
        .tq-linea-leyenda > span { display: inline-flex; align-items: center; gap: 6px; }
        .tq-linea-tabla { position: relative; border-radius: 16px; background: var(--tq-l-sup); border: 1px solid var(--tq-l-borde); overflow: hidden; }
        .tq-linea-cab { display: grid; grid-template-columns: var(--tq-l-izq) 1fr; border-bottom: 1px solid var(--tq-l-borde); background: var(--tq-l-cab); }
        @media (max-width: 900px) { .tq-linea { --tq-l-izq: 200px; } }
        .tq-linea-mes { position: absolute; top: 0; bottom: 0; padding: 12px 10px; font-size: 12px; font-weight: 600; color: var(--tq-l-suave); border-left: 1px solid var(--tq-l-borde); white-space: nowrap; }
        .tq-linea-fila { display: grid; grid-template-columns: var(--tq-l-izq) 1fr; align-items: center; width: 100%; border-bottom: 1px solid var(--tq-l-borde); min-height: 66px; text-align: left; background: transparent; }
        .tq-linea-fila:hover, .tq-linea-fila:focus-visible { background: color-mix(in oklab, var(--primary-600) 5%, transparent); }
        .tq-linea-corta { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .tq-linea-pista { position: relative; height: 66px; overflow: hidden; }
        .tq-linea-tramo { position: absolute; top: 26px; height: 14px; border-radius: 7px; }
        .tq-linea-falta { position: absolute; top: 32px; height: 0; border-top: 2px dashed var(--tq-l-acento); opacity: .7; }
        .tq-linea-rombo { position: absolute; top: 27px; width: 12px; height: 12px; margin-left: -6px; transform: rotate(45deg); background: var(--tq-l-oro); box-shadow: 0 0 0 3px var(--tq-l-sup); }
        .tq-linea-rombo[data-vencido] { background: var(--tq-l-coral); }
        .tq-linea-hito { position: absolute; top: 44px; width: 80px; margin-left: -40px; text-align: center; font-size: 11px; font-weight: 600; color: var(--tq-l-oro-texto); }
        .tq-linea-hito[data-vencido] { color: var(--tq-l-coral); }
        .tq-linea-hoy { position: absolute; top: 40px; bottom: 0; width: 2px; background: var(--tq-l-hoy); pointer-events: none; z-index: 2; }
        .tq-linea-hoy-texto { position: absolute; top: 44px; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; background: var(--tq-l-hoy-fondo); color: var(--tq-l-hoy-texto); pointer-events: none; z-index: 3; white-space: nowrap; }
        .tq-linea-separador { display: flex; align-items: center; gap: 10px; padding: 6px 18px; font-size: 12px; font-weight: 700; color: var(--tq-l-coral); border-bottom: 1px solid var(--tq-l-borde); }
        .tq-linea-separador::after { content: ''; flex: 1; height: 1px; background: color-mix(in oklab, var(--tq-l-coral) 40%, transparent); }
    </style>

    {{-- Filtros (como los de Tickets de ayuda, sin textos explicativos) y «Ordenar por». --}}
    <div class="tq-linea-barra">
        <x-filament::input.wrapper prefix-icon="heroicon-o-magnifying-glass" style="min-width: 15rem">
            <x-filament::input type="search" wire:model.live.debounce.300ms="buscar" placeholder="Buscar oportunidad o cliente" aria-label="Buscar oportunidad o cliente" />
        </x-filament::input.wrapper>
        <span class="tq-linea-sep" aria-hidden="true"></span>
        @foreach (D::VENCIMIENTOS as $clave => $texto)
            <button type="button" class="tq-linea-chip" aria-pressed="{{ $this->vencimiento === $clave ? 'true' : 'false' }}" wire:click="poner('vencimiento', '{{ $clave }}')">{{ $texto }}</button>
        @endforeach
        <span class="tq-linea-sep" aria-hidden="true"></span>
        @foreach ($etapas as $clave => [$texto, $talla])
            <button type="button" class="tq-linea-chip" aria-pressed="{{ $this->etapa === $clave ? 'true' : 'false' }}" wire:click="poner('etapa', '{{ $clave }}')"><x-oportunidades::gema :talla="$talla" :tamano="16" decorativa />{{ $texto }}</button>
        @endforeach
        <button type="button" class="tq-linea-chip" aria-pressed="{{ $this->etapa === 'cerradas' ? 'true' : 'false' }}" wire:click="poner('etapa', 'cerradas')">Cerradas</button>
        <span class="tq-linea-sep" aria-hidden="true"></span>
        <button type="button" class="tq-linea-chip" aria-pressed="{{ $this->responsable === 'mias' ? 'true' : 'false' }}" wire:click="poner('responsable', 'mias')">Mías</button>
        <button type="button" class="tq-linea-chip" aria-pressed="{{ $this->responsable === 'todas' ? 'true' : 'false' }}" wire:click="poner('responsable', 'todas')">Todas</button>
        <select class="tq-linea-sel" aria-label="Responsable" wire:change="poner('responsable', $event.target.value || 'todas')">
            <option value="">Una persona…</option>
            @foreach ($personas as $id => $nombre)
                <option value="{{ $id }}" @selected((string) $id === $this->responsable)>{{ $nombre }}</option>
            @endforeach
        </select>
        <span class="tq-linea-sep" aria-hidden="true"></span>
        @foreach (D::RANGOS as $clave => [$texto])
            <button type="button" class="tq-linea-chip" aria-pressed="{{ $this->rango === $clave ? 'true' : 'false' }}" wire:click="poner('rango', '{{ $clave }}')">{{ $texto }}</button>
        @endforeach
        <span class="tq-linea-sep" aria-hidden="true"></span>
        <label class="tq-linea-chip" style="cursor: default">Ordenar por
            <select class="tq-linea-sel" style="border: 0; padding: 0 4px" wire:change="poner('orden', $event.target.value)">
                @foreach (D::ORDENES as $clave => $texto)
                    <option value="{{ $clave }}" @selected($this->orden === $clave)>{{ $texto }}</option>
                @endforeach
            </select>
        </label>
    </div>

    <div class="tq-linea-leyenda" aria-hidden="true">
        <span><span style="width: 18px; height: 8px; border-radius: 4px; background: linear-gradient(90deg, #A6F2FF, #1FCDEB)"></span>etapas recorridas</span>
        <span><span style="width: 18px; height: 0; border-top: 2px dashed var(--tq-l-acento)"></span>lo que falta hasta el cierre</span>
        <span><span style="width: 10px; height: 10px; transform: rotate(45deg); background: var(--tq-l-oro)"></span>cierre esperado</span>
        <span><span style="width: 18px; height: 0; border-top: 2px dashed var(--tq-l-coral)"></span>cierre vencido</span>
    </div>

    <div class="tq-linea-tabla">
        <div class="tq-linea-cab">
            <span style="padding: 12px 18px; font-size: 12px; font-weight: 600; letter-spacing: .06em; color: var(--tq-l-suave)">OPORTUNIDAD</span>
            <div style="position: relative; height: 40px">
                @foreach ($meses as $m)
                    <span class="tq-linea-mes" style="left: {{ $m['x'] }}%">{{ $m['texto'] }}</span>
                @endforeach
            </div>
        </div>

        <div data-tq-escalonar>
            @forelse ($filas as $f)
                @if ($vencidas > 0 && ! $f['vencida'] && ! $separadorPuesto)
                    {{-- Donde termina lo atrasado: un separador fino con la cuenta de vencidas. --}}
                    @php $separadorPuesto = true; @endphp
                    <div class="tq-linea-separador" data-tq-fila="sep-vencidas">↑ {{ $vencidas === 1 ? '1 vencida' : $vencidas.' vencidas' }}</div>
                @endif
                <button type="button" class="tq-linea-fila" wire:key="linea-{{ $f['id'] }}" data-tq-fila="linea-{{ $f['id'] }}" wire:click="abrirFicha({{ $f['id'] }})">
                    <span style="display: flex; align-items: center; gap: 12px; padding: 10px 18px; min-width: 0">
                        <x-oportunidades::gema :talla="$f['talla']" :tamano="26" decorativa />
                        <span style="display: flex; flex-direction: column; gap: 2px; min-width: 0">
                            <span class="tq-linea-corta" style="font-size: 14px; font-weight: 600">{{ $f['nombre'] }}</span>
                            <span class="tq-linea-corta" style="font-size: 12px; color: var(--tq-l-suave)">{{ $f['cliente'] }} · <b style="font-weight: 600; color: var(--tq-texto, inherit)">{{ $f['monto'] }}</b>
                                @if ($f['vencida'])<b style="font-weight: 600; color: var(--tq-l-coral)"> · venció hace {{ $f['diasVencida'] === 1 ? '1 día' : $f['diasVencida'].' días' }}</b>@endif
                                @if ($f['cierre'] === null)<span style="opacity: .8"> · sin fecha</span>@endif
                            </span>
                        </span>
                    </span>
                    <span class="tq-linea-pista">
                        @foreach ($f['tramos'] as $t)
                            <span class="tq-linea-tramo" style="left: {{ $t['x'] }}%; width: calc({{ $t['ancho'] }}% - 2px); background: {{ $t['color'] }}" title="{{ $t['titulo'] }}"></span>
                        @endforeach
                        @if ($f['falta'])
                            <span class="tq-linea-falta" style="left: {{ $f['falta']['x'] }}%; width: {{ $f['falta']['ancho'] }}%"></span>
                        @endif
                        @if ($f['hito'] && $f['hito']['visible'])
                            <span class="tq-linea-rombo" style="left: {{ $f['hito']['x'] }}%" @if ($f['vencida']) data-vencido @endif title="{{ $f['hito']['titulo'] }}"></span>
                            <span class="tq-linea-hito" style="left: {{ $f['hito']['x'] }}%" @if ($f['vencida']) data-vencido @endif>{{ $f['hito']['texto'] }}</span>
                        @endif
                    </span>
                </button>
            @empty
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 18px; font-size: 14px; color: var(--tq-l-suave)">
                    <span>Ninguna oportunidad coincide.</span>
                    <x-filament::button size="sm" color="gray" wire:click="quitarFiltros">Quitar filtros</x-filament::button>
                </div>
            @endforelse
        </div>

        {{-- La línea «Hoy» (siempre visible) atraviesa todas las filas. --}}
        <span class="tq-linea-hoy" style="left: calc(var(--tq-l-izq) + (100% - var(--tq-l-izq)) * {{ $hoy / 100 }})"></span>
        <span class="tq-linea-hoy-texto" style="left: calc(var(--tq-l-izq) + (100% - var(--tq-l-izq)) * {{ $hoy / 100 }} + 6px)">{{ $textoHoy }}</span>
    </div>
</div>
