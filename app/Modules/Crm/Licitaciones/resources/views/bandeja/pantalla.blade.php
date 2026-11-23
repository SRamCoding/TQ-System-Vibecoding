{{--
    MV-078 (observación 7.5): la bandeja de Convocatorias del SEACE como el lienzo «B · Bandeja con detalle»
    (`docs/vibe/pedidos/lienzos/convocatorias/Main.dc.html`; la configuración, `Configuracion.dc.html`). Recibe lo de
    `BandejaConvocatorias::datos()`. Observación 7.4: `wire:init` lee lo nuevo por detrás; las filas nuevas entran con la
    animación de las tablas (`data-tq-escalonar`).
--}}
@php
    use App\Modules\Crm\Licitaciones\Services\BandejaSeace as B;
    $soles = fn ($m, $moneda = 'PEN') => $m === null ? 'Sin valor publicado' : ($moneda === 'PEN' ? 'S/ ' : $moneda.' ').number_format((float) $m, 2);
    $iniciales = fn (?string $n) => $n === null ? '' : mb_strtoupper(collect(preg_split('/\s+/', trim($n)))->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode(''));
    // Resalta lo que coincidió (sin importar tildes ni mayúsculas).
    $marcar = function (string $texto, array $palabras): string {
        $palabras = array_values(array_filter($palabras));
        if ($palabras === []) {
            return e($texto);
        }
        // Una sola expresión con las más largas primero: «cámaras» gana a «cámara» y no se parten.
        usort($palabras, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        $patron = implode('|', array_map(fn ($p) => strtr(preg_quote($p, '/'), ['a' => '[aá]', 'á' => '[aá]', 'e' => '[eé]', 'é' => '[eé]', 'i' => '[ií]', 'í' => '[ií]', 'o' => '[oó]', 'ó' => '[oó]', 'u' => '[uú]', 'ú' => '[uú]']), $palabras));
        $partes = preg_split('/('.$patron.')/iu', $texto, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$texto];

        return implode('', array_map(fn ($parte, $i) => $i % 2 === 1 ? '<mark>'.e($parte).'</mark>' : e($parte), $partes, array_keys($partes)));
    };
    $dias = fn (?int $d) => $d === null ? 'Sin fecha' : ($d < 0 ? 'Venció' : ($d === 0 ? 'Hoy' : ($d === 1 ? '1 día' : $d.' días')));
    $d = $detalle;
@endphp
<div class="tq-band" wire:init="leerAlEntrar"
    x-data
    x-on:keydown.window="if (['INPUT', 'TEXTAREA', 'SELECT'].includes($event.target.tagName) || document.querySelector('.fi-modal-open')) return;
        if ($event.key === 'ArrowDown') { $event.preventDefault(); $wire.mover(1, @js($ids)) }
        if ($event.key === 'ArrowUp') { $event.preventDefault(); $wire.mover(-1, @js($ids)) }">
    <style>
        .tq-band { --b-sup: var(--tq-superficie, #fff); --b-borde: var(--tq-borde, #E5E0D6); --b-linea: #EFEBE3; --b-suave: var(--tq-texto-suave, #5F5B53); --b-fondo-suave: #F7F5F0;
            --b-turquesa: #0E8FAE; --b-azul: #24389C; --b-azul-fondo: #E8ECFB; --b-azul-borde: #C6D0F5; --b-coral: #D9534A; --b-coral-fondo: #FBE9E7; --b-gris-fondo: #F1EEE7;
            --b-celeste-fondo: #E3F5F9; --b-mark: #FFF0B3; --b-oro: #E0A800;
            display: flex; flex-direction: column; gap: 14px; }
        .dark .tq-band { --b-sup: var(--tq-superficie, #2B2925); --b-borde: var(--tq-borde, #45413A); --b-linea: #3A3731; --b-suave: var(--tq-texto-suave, #A8ADB4); --b-fondo-suave: #2F2C28;
            --b-turquesa: #4DCFEA; --b-azul: #9DB0FF; --b-azul-fondo: #2A3150; --b-azul-borde: #3E4A7A; --b-coral: #FF8A75; --b-coral-fondo: #4A2A26; --b-gris-fondo: #3A3731;
            --b-celeste-fondo: #1F3A42; --b-mark: #5C4B12; --b-oro: #F2C14E; }
        .tq-band mark { background: var(--b-mark); color: inherit; border-radius: 3px; padding: 0 2px; }
        .tq-band-barra { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
        .tq-band-buscar { flex: 1 1 24rem; display: flex; align-items: center; gap: 8px; height: 40px; border: 1px solid var(--b-borde); border-radius: 10px; background: var(--b-sup); padding: 0 12px; }
        .tq-band-buscar input { flex: 1; border: 0; background: transparent; font-size: 14px; outline: none; min-width: 0; }
        .tq-band-interruptor { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: var(--b-suave); cursor: pointer; white-space: nowrap; }
        .tq-band-interruptor span.pista { width: 28px; height: 16px; border-radius: 999px; background: #D9D4C9; position: relative; display: inline-block; transition: background .15s; }
        .tq-band-interruptor span.pista::after { content: ''; position: absolute; left: 2px; top: 2px; width: 12px; height: 12px; border-radius: 50%; background: #fff; transition: left .15s; }
        .tq-band-interruptor[aria-checked="true"] span.pista { background: var(--b-turquesa); }
        .tq-band-interruptor[aria-checked="true"] span.pista::after { left: 14px; }
        .tq-band-leido { font-size: 12px; color: var(--b-suave); }
        .tq-band-btn { height: 32px; padding: 0 14px; border-radius: 9px; border: 1px solid var(--b-borde); background: var(--b-sup); font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; }
        .tq-band-btn.p { background: var(--b-turquesa); border-color: var(--b-turquesa); color: #fff; }
        .tq-band-chips { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .tq-band-chip { display: inline-flex; align-items: center; gap: 6px; height: 30px; padding: 0 12px; border-radius: 999px; border: 1px solid var(--b-borde); background: var(--b-sup); font-size: 13px; }
        .tq-band-chip .n { font-size: 11px; font-weight: 600; color: var(--b-suave); background: var(--b-gris-fondo); border-radius: 999px; padding: 1px 7px; }
        .tq-band-chip[aria-pressed="true"] { background: var(--b-azul-fondo); border-color: var(--b-azul-borde); color: var(--b-azul); font-weight: 600; }
        .tq-band-chip[aria-pressed="true"] .n { background: var(--b-sup); color: var(--b-azul); }
        .tq-band-cuerpo { display: grid; grid-template-columns: 430px minmax(0, 1fr); gap: 16px; align-items: start; }
        @media (max-width: 1023px) { .tq-band-cuerpo { grid-template-columns: minmax(0, 1fr); } }
        .tq-band-lista { background: var(--b-sup); border: 1px solid var(--b-borde); border-radius: 14px; overflow: hidden; max-height: calc(100vh - 8rem); display: flex; flex-direction: column; position: sticky; top: 5rem; }
        .tq-band-lista-cab { padding: 10px 14px; font-size: 12px; color: var(--b-suave); border-bottom: 1px solid var(--b-linea); display: flex; align-items: center; gap: 8px; }
        .tq-band-lista-cab select { border: 0; background: transparent; font-size: 12px; color: var(--b-suave); padding: 0 1.5rem 0 0; }
        .tq-band-filas { overflow-y: auto; }
        .tq-band-fila { display: flex; gap: 10px; padding: 12px 14px; border-bottom: 1px solid var(--b-linea); width: 100%; text-align: start; transition: background-color .3s ease; }
        .tq-band-fila:hover { background: color-mix(in oklab, var(--b-azul) 5%, transparent); }
        .tq-band-fila[aria-current="true"] { background: var(--b-azul-fondo); box-shadow: inset 3px 0 0 var(--b-azul); }
        .tq-band-fila[data-fuera] { opacity: .62; }
        .tq-band-punto { width: 8px; height: 8px; border-radius: 50%; background: var(--b-turquesa); flex: none; margin-top: 5px; }
        .tq-band-ent { font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .tq-band-obj { font-size: 13px; line-height: 1.3; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .tq-band-pie { display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--b-suave); }
        .tq-band-dias { margin-left: auto; font-size: 12px; font-weight: 600; white-space: nowrap; color: var(--b-suave); }
        .tq-band-dias[data-urgente] { color: var(--b-coral); }
        .tq-band-av { width: 24px; height: 24px; border-radius: 50%; background: #D7E4F7; color: #24389C; font: 700 10px Inter, sans-serif; display: inline-flex; align-items: center; justify-content: center; flex: none; }
        .tq-band-tag { font-size: 11px; font-weight: 600; border-radius: 6px; padding: 2px 7px; white-space: nowrap; }
        .tq-band-det { background: var(--b-sup); border: 1px solid var(--b-borde); border-radius: 14px; padding: 22px 26px; display: flex; flex-direction: column; gap: 16px; min-height: 36rem; animation: tq-band-entra .22s ease both; }
        @keyframes tq-band-entra { from { opacity: 0; transform: translateX(6px); } to { opacity: 1; transform: none; } }
        @media (prefers-reduced-motion: reduce) { .tq-band-det { animation: none; } }
        html.tq-sin-animaciones .tq-band-det { animation: none; }
        .tq-band-sub { font-size: 12px; color: var(--b-suave); }
        .tq-band-crono { display: flex; position: relative; }
        .tq-band-crono-linea { position: absolute; left: 8%; right: 8%; top: 6px; height: 2px; }
        .tq-band-hito { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 6px; position: relative; text-align: center; }
        .tq-band-hito .p { width: 14px; height: 14px; border-radius: 50%; border: 2px solid var(--b-turquesa); background: var(--b-sup); z-index: 1; }
        .tq-band-hito[data-pasado] .p { background: var(--b-turquesa); }
        .tq-band-hito[data-clave] .p { border-color: var(--b-coral); }
        .tq-band-hito[data-clave] .nombre { color: var(--b-coral); }
        .tq-band-gan { display: grid; grid-template-columns: 2.2fr 1.4fr 1fr .8fr; font-size: 13px; border: 1px solid var(--b-linea); border-radius: 10px; overflow: hidden; }
        .tq-band-gan > div { padding: 8px 12px; border-top: 1px solid var(--b-linea); }
        .tq-band-gan > div:nth-child(4n+3), .tq-band-gan > div:nth-child(4n) { white-space: nowrap; }
        .tq-band-gan > div.cab { background: var(--b-fondo-suave); color: var(--b-suave); font-weight: 600; border-top: 0; }
        .tq-band-acciones { margin-top: auto; display: flex; flex-wrap: wrap; gap: 8px; border-top: 1px solid var(--b-linea); padding-top: 14px; align-items: center; }
        .tq-band-conf h4 { font-size: 15px; font-weight: 700; }
        .tq-band-palabra { display: inline-flex; align-items: center; gap: 4px; font-size: 12px; padding: 3px 8px; border-radius: 999px; background: var(--b-gris-fondo); }
        .tq-band-palabra.neg { background: var(--b-coral-fondo); color: var(--b-coral); }
        .tq-band-palabra button { opacity: .7; }
        .tq-band-agregar { font-size: 12px; border: 1px solid var(--b-borde); border-radius: 999px; padding: 3px 10px; background: var(--b-sup); color: var(--tq-texto, inherit); width: 9rem; }
        .tq-band-agregar::placeholder { color: var(--b-suave); opacity: 1; }
        /* Observación 8.7: cada línea en su caja con borde visible y el cuadrito de su color; montos como campos. */
        .tq-band-linea { border: 1px solid var(--tq-borde, var(--b-borde)); border-radius: 12px; padding: 12px 14px; display: flex; flex-direction: column; gap: 8px; background: var(--b-sup); }
        .tq-band-cuadrito { width: 12px; height: 12px; border-radius: 3px; flex: none; }
        .tq-band-monto { width: 10rem; border: 1px solid var(--tq-borde, var(--b-borde)); border-radius: 8px; padding: 4px 8px; background: var(--b-sup); color: var(--tq-texto, inherit); font-variant-numeric: tabular-nums; text-align: right; }
        .tq-band-fuente-off { color: var(--b-suave); }
        /* Observación 8.9: filtros rápidos con buscador y chips de región. */
        .tq-band-rapido { position: relative; }
        .tq-band-rapido > button { font-size: 12px; color: var(--b-suave); display: inline-flex; gap: 4px; align-items: center; }
        .tq-band-rapido > button[data-activo] { color: var(--b-azul); font-weight: 600; }
        .tq-band-pop { position: absolute; z-index: 30; top: calc(100% + 6px); left: 0; width: 17rem; max-height: 20rem; overflow: auto; padding: 8px; border-radius: 10px; background: var(--tq-superficie-elevada, #fff); border: 1px solid var(--tq-borde, var(--b-borde)); box-shadow: 0 12px 30px -8px rgb(0 0 0 / .25); }
        .tq-band-pop input { width: 100%; margin-bottom: 6px; padding: 4px 8px; font-size: 12px; border: 1px solid var(--tq-borde, var(--b-borde)); border-radius: 6px; background: var(--b-sup); color: inherit; }
        .tq-band-pop button { display: flex; width: 100%; justify-content: space-between; gap: 8px; padding: 5px 6px; border-radius: 6px; font-size: 13px; text-align: start; }
        .tq-band-pop button:hover, .tq-band-pop button[aria-pressed="true"] { background: var(--b-azul-fondo); color: var(--b-azul); }
        .tq-band-region { font-size: 11px; padding: 2px 8px; border-radius: 999px; border: 1px solid var(--tq-borde, var(--b-borde)); background: var(--b-sup); }
        .tq-band-region[aria-pressed="true"] { background: var(--b-azul-fondo); border-color: var(--b-azul-borde); color: var(--b-azul); font-weight: 600; }
        .tq-band-pronto { font-size: 11px; font-weight: 600; border-radius: 6px; padding: 1px 7px; background: var(--b-gris-fondo); color: var(--b-suave); }
    </style>

    {{-- Arriba: buscador con «Buscar en todo el SEACE», «Leído hace…», Leer ahora y Búsqueda. --}}
    <div class="tq-band-barra">
        <label class="tq-band-buscar">
            <x-filament::icon icon="heroicon-o-magnifying-glass" class="h-4 w-4" style="color: var(--b-suave)" />
            <input type="search" wire:model.live.debounce.400ms="buscar" placeholder="Buscar por objeto, entidad o nomenclatura…" aria-label="Buscar convocatorias">
            <button type="button" class="tq-band-interruptor" role="switch" aria-checked="{{ $this->todo ? 'true' : 'false' }}" wire:click="$toggle('todo')"><span class="pista"></span>Buscar en todo el SEACE</button>
        </label>
        <span class="tq-band-leido" title="{{ $lectura['ok']?->format('d/m/Y H:i') }}">
            {{ $lectura['ok'] === null ? 'Sin lecturas todavía' : 'Leído '.$lectura['ok']->locale('es')->diffForHumans() }}
        </span>
        @if ($puede)
            <button type="button" class="tq-band-btn" wire:click="leerAhora" wire:loading.attr="disabled" wire:target="leerAhora"><x-filament::icon icon="heroicon-o-arrow-path" class="h-4 w-4" wire:loading.class="animate-spin" wire:target="leerAhora" />Leer ahora</button>
        @endif
        <button type="button" class="tq-band-btn" wire:click="abrirBusqueda"><x-filament::icon icon="heroicon-o-adjustments-horizontal" class="h-4 w-4" />Búsqueda</button>
    </div>
    @if ($lectura['fallo'] !== null)
        <div class="tq-band-sub" style="color: var(--b-coral)">El OECE no respondió a las {{ $lectura['falloEn']?->format('H:i') }}: se muestra lo guardado.</div>
    @endif

    {{-- Chips: líneas de negocio y «ver», con su cantidad. --}}
    <div class="tq-band-chips">
        @foreach ($lineas as $l)
            <button type="button" class="tq-band-chip" aria-pressed="{{ $this->linea === $l['id'] ? 'true' : 'false' }}" wire:click="ponerLinea({{ $l['id'] ?? 'null' }})">{{ $l['nombre'] }} <span class="n">{{ $l['n'] }}</span></button>
        @endforeach
        <span style="width: 16px"></span>
        @foreach (B::VER as $clave => $texto)
            <button type="button" class="tq-band-chip" aria-pressed="{{ $this->ver === $clave ? 'true' : 'false' }}" wire:click="ponerVer('{{ $clave }}')">{{ $texto }} <span class="n">{{ $ver[$clave] }}</span></button>
        @endforeach
    </div>

    <div class="tq-band-cuerpo">
        {{-- La lista compacta. --}}
        <div class="tq-band-lista">
            <div class="tq-band-lista-cab">
                {{ count($filas) }} {{ count($filas) === 1 ? 'convocatoria' : 'convocatorias' }}
                {{-- Observación 8.9: Región y Entidad, con buscador y la cantidad de cada opción. --}}
                @foreach (['region' => ['Región', collect($regiones)->mapWithKeys(fn ($n, $k) => [$k => ['nombre' => \App\Modules\Crm\Licitaciones\Services\FiltroConvocatorias::nombreRegion($k ?: null), 'n' => $n]])->all()], 'entidad' => ['Entidad', $entidades]] as $campo => [$titulo, $opciones])
                    <span class="tq-band-rapido" x-data="{ abierto: false, q: '' }" x-on:click.outside="abierto = false" @if ($loop->first) style="margin-left: auto" @endif>
                        <button type="button" x-on:click="abierto = ! abierto" @if ($this->{$campo}) data-activo @endif>
                            {{ $this->{$campo} ? \Illuminate\Support\Str::limit($opciones[$this->{$campo}]['nombre'] ?? $titulo, 18) : $titulo }} ▾
                        </button>
                        <div class="tq-band-pop" x-show="abierto" x-cloak x-transition.opacity>
                            <input type="search" x-model="q" placeholder="Buscar {{ mb_strtolower($titulo) }}…" aria-label="Buscar {{ mb_strtolower($titulo) }}">
                            <button type="button" aria-pressed="{{ $this->{$campo} ? 'false' : 'true' }}" wire:click="$set('{{ $campo }}', null)" x-on:click="abierto = false"><span>Todas</span></button>
                            @foreach ($opciones as $clave => $op)
                                <button type="button" aria-pressed="{{ (string) $this->{$campo} === (string) $clave ? 'true' : 'false' }}" wire:click="$set('{{ $campo }}', @js((string) $clave))" x-on:click="abierto = false"
                                    x-show="q === '' || {{ \Illuminate\Support\Js::from(mb_strtolower($op['nombre'])) }}.includes(q.toLowerCase())">
                                    <span>{{ $op['nombre'] }}</span><span style="color: var(--b-suave)">{{ $op['n'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    </span>
                @endforeach
                <select wire:model.live="orden" aria-label="Orden">
                    @foreach (B::ORDENES as $clave => $texto)<option value="{{ $clave }}">{{ $texto }}</option>@endforeach
                </select>
            </div>
            <div class="tq-band-filas" data-tq-escalonar>
                @forelse ($filas as $f)
                    <button type="button" class="tq-band-fila" wire:key="conv-{{ $f['id'] }}" data-tq-fila="conv-{{ $f['id'] }}" wire:click="elegir({{ $f['id'] }})"
                        aria-current="{{ $d !== null && $d['id'] === $f['id'] ? 'true' : 'false' }}" @if (! $f['enBandeja']) data-fuera @endif>
                        @if ($f['nueva'] && $f['estado'] === 'nueva')<span class="tq-band-punto" title="Nueva"></span>@else<span style="width: 8px; flex: none"></span>@endif
                        <span style="flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 4px">
                            <span style="display: flex; align-items: center; gap: 6px">
                                @if ($f['prioritaria'])<x-filament::icon icon="heroicon-s-star" class="h-3.5 w-3.5" style="color: var(--b-oro); flex: none" />@endif
                                <span class="tq-band-ent" style="font-weight: {{ $f['nueva'] && $f['estado'] === 'nueva' ? 700 : 500 }}">{{ $f['entidad'] }}</span>
                                <span class="tq-band-dias" @if ($f['urgente']) data-urgente @endif>{{ $dias($f['dias']) }}</span>
                            </span>
                            <span class="tq-band-obj" style="font-weight: {{ $f['nueva'] && $f['estado'] === 'nueva' ? 700 : 500 }}">{!! $marcar($f['objeto'], $f['coincide']) !!}</span>
                            <span class="tq-band-pie">
                                <span>{{ $soles($f['valor'], $f['moneda']) }}</span>@if ($f['linea'])·<span>{{ $f['linea'] }}</span>@endif
                                @if (! $f['enBandeja'])·<span>fuera de tu bandeja</span>@endif
                                @if ($f['estado'] !== 'nueva')·<span>{{ $f['estado'] === 'registrada' ? 'registrada' : 'descartada' }}</span>@endif
                                <span style="margin-left: auto">@if ($f['responsable'])<span class="tq-band-av" title="{{ $f['responsable'] }}">{{ $iniciales($f['responsable']) }}</span>@endif</span>
                            </span>
                        </span>
                    </button>
                @empty
                    <div class="tq-band-sub" style="padding: 18px">Nada por aquí con estos filtros.</div>
                @endforelse
            </div>
        </div>

        {{-- El detalle de la elegida. --}}
        @if ($d !== null)
            <div class="tq-band-det" wire:key="det-{{ $d['id'] }}">
                <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap">
                    @if ($d['prioritaria'])<x-filament::icon icon="heroicon-s-star" class="h-4 w-4" style="color: var(--b-oro)" title="{{ $d['motivoPrioridad'] }}" />@endif
                    <span class="tq-band-tag" style="background: var(--b-celeste-fondo); color: var(--b-turquesa)">{{ $d['tipo'] }}</span>
                    @if ($d['estado'] === 'nueva' && $d['nueva'])<span class="tq-band-tag" style="background: var(--b-turquesa); color: #fff">Nueva</span>@endif
                    @if ($d['estado'] === 'registrada')<span class="tq-band-tag" style="background: var(--b-azul-fondo); color: var(--b-azul)">Registrada</span>@endif
                    @if ($d['estado'] === 'descartada')<span class="tq-band-tag" style="background: var(--b-gris-fondo); color: var(--b-suave)">Descartada · {{ $d['motivoDescarte'] }}</span>@endif
                    @if ($d['esCliente'])<span class="tq-band-tag" style="background: var(--b-azul-fondo); color: var(--b-azul)">Ya es cliente</span>@endif
                    @if ($d['linea'])<span class="tq-band-tag" style="background: var(--b-gris-fondo); color: var(--b-suave)">{{ $d['linea'] }}</span>@endif
                    @if (! $d['enBandeja'])<span class="tq-band-tag" style="background: var(--b-gris-fondo); color: var(--b-suave)">Fuera de tu bandeja · {{ $d['fueraPor'] }}</span>@endif
                    @if ($d['dias'] !== null)
                        <span style="margin-left: auto"><span class="tq-band-tag" style="background: {{ $d['urgente'] || $d['dias'] < 0 ? 'var(--b-coral-fondo)' : 'var(--b-gris-fondo)' }}; color: {{ $d['urgente'] || $d['dias'] < 0 ? 'var(--b-coral)' : 'var(--b-suave)' }}">{{ $d['dias'] < 0 ? 'Presentación vencida' : ($d['dias'] === 0 ? 'Presentación hoy' : 'Presentación en '.$dias($d['dias'])) }}</span></span>
                    @endif
                </div>
                <div style="font-size: 20px; font-weight: 700; line-height: 1.3">{!! $marcar($d['objeto'], $d['coincide']) !!}</div>
                <div style="font-size: 14px; color: var(--b-suave)">{{ $d['entidad'] }}{{ $d['region'] ? ' · '.$d['region'] : '' }} · <span style="font-family: ui-monospace, monospace; font-size: 13px">{{ $d['nomenclatura'] }}</span></div>
                <div style="display: flex; flex-wrap: wrap; gap: 28px">
                    <div><div class="tq-band-sub">Valor referencial</div><div style="font-size: 24px; font-weight: 700">{{ $soles($d['valor'], $d['moneda']) }}</div></div>
                    <div><div class="tq-band-sub">Coincide con</div><div style="display: flex; gap: 6px; margin-top: 6px; flex-wrap: wrap">@forelse ($d['coincide'] as $p)<mark style="font-size: 13px; padding: 2px 6px">{{ $p }}</mark>@empty<span class="tq-band-sub">—</span>@endforelse</div></div>
                    <div><div class="tq-band-sub">Con esta entidad</div><div style="font-size: 14px; margin-top: 6px">{{ $d['conEntidad'] ?? '—' }}</div></div>
                </div>

                <div>
                    <div style="font-size: 13px; font-weight: 600; margin-bottom: 12px">Cronograma</div>
                    <div class="tq-band-crono">
                        <div class="tq-band-crono-linea" style="background: linear-gradient(90deg, var(--b-turquesa) {{ $d['avance'] }}%, #D9D4C9 {{ $d['avance'] }}%)"></div>
                        @foreach ($d['cronograma'] as $h)
                            <div class="tq-band-hito" @if ($h['pasado']) data-pasado @endif @if ($h['clave']) data-clave @endif>
                                <span class="p"></span><span class="nombre" style="font-size: 12px; font-weight: 600">{{ $h['nombre'] }}</span>
                                <span class="tq-band-sub">{{ $h['fecha'] ? str_replace('.', '', $h['fecha']->locale('es')->translatedFormat('d M')) : '—' }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div>
                    <div style="font-size: 13px; font-weight: 600; margin-bottom: 8px">Documentos del proceso</div>
                    @forelse ($d['documentos'] as $doc)
                        <a href="{{ $doc['url'] }}" target="_blank" rel="noopener" style="display: inline-flex; align-items: center; gap: 6px; margin: 0 14px 6px 0; font-size: 13px; color: var(--b-azul)">
                            <x-filament::icon icon="heroicon-o-document-arrow-down" class="h-4 w-4" />{{ $doc['titulo'] }}{{ $doc['formato'] ? ' · '.$doc['formato'] : '' }}
                        </a>
                    @empty
                        <div class="tq-band-sub">El OECE aún no publica documentos de este proceso.</div>
                    @endforelse
                </div>

                <div>
                    <div style="font-size: 13px; font-weight: 600; margin-bottom: 8px">Ganadores anteriores en esta entidad</div>
                    @if ($d['ganadores'] === [])
                        <div class="tq-band-sub">Sin procesos anteriores guardados.</div>
                    @else
                        <div class="tq-band-gan">
                            <div class="cab">Objeto</div><div class="cab">Ganador</div><div class="cab" style="text-align: right">Adjudicado</div><div class="cab" style="text-align: right">Descuento</div>
                            @foreach ($d['ganadores'] as $g)
                                <div>{{ \Illuminate\Support\Str::limit($g['objeto'], 60) }}{{ $g['anio'] ? ' ('.$g['anio'].')' : '' }}</div>
                                <div @if ($g['techquk']) style="color: var(--b-azul); font-weight: 600" @endif>{{ $g['techquk'] ? 'Techquk' : $g['ganador'] }}</div>
                                <div style="text-align: right">{{ $g['monto'] === null ? '—' : 'S/ '.number_format((float) $g['monto'], 2) }}</div>
                                <div style="text-align: right">{{ $g['descuento'] === null ? '—' : $g['descuento'].' %' }}</div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap">
                    <span class="tq-band-sub" style="font-size: 13px">Responsable</span>
                    @if ($d['responsable'])<span class="tq-band-av">{{ $iniciales($d['responsable']) }}</span><span style="font-size: 13px">{{ $d['responsable'] }}</span>@else<span class="tq-band-sub">Sin asignar</span>@endif
                    <span class="tq-band-sub" style="font-size: 13px; margin-left: 12px">Etiquetas</span>
                    @foreach ($d['etiquetas'] as $e)
                        <span class="tq-band-tag" style="background: var(--b-gris-fondo); color: var(--b-suave)">{{ $e }} @if ($puede)<button type="button" wire:click="quitarEtiqueta(@js($e))" aria-label="Quitar {{ $e }}">×</button>@endif</span>
                    @endforeach
                    @if ($puede)
                        <input type="text" class="tq-band-agregar" placeholder="+ etiqueta" wire:model="nuevaEtiqueta" wire:keydown.enter="agregarEtiqueta" aria-label="Agregar etiqueta">
                    @endif
                </div>

                <div class="tq-band-acciones">
                    {{-- Observación 8.10: el OECE tiene página por proceso; el buscador del SEACE no, así que se copia la nomenclatura. --}}
                    <a class="tq-band-btn" href="{{ $d['urlOece'] }}" target="_blank" rel="noopener">Ver en el OECE</a>
                    <a class="tq-band-btn" href="{{ $d['urlSeace'] }}" target="_blank" rel="noopener" x-on:click="window.tqCopiarNomenclatura({{ \Illuminate\Support\Js::from($d['nomenclatura']) }})">Buscar en el SEACE</a>
                    @if ($d['estado'] === 'nueva') {{ $this->descartarAction }} @endif
                    <span style="flex: 1"></span>
                    @if ($d['estado'] !== 'descartada') {{ $this->asignarAction }} @endif
                    @if ($d['estado'] === 'nueva')
                        {{ $this->registrarAction }}
                    @elseif ($d['licitacionId'])
                        <a class="tq-band-btn" href="/licitaciones/{{ $d['licitacionId'] }}" wire:navigate>Ver la licitación</a>
                    @endif
                </div>
            </div>
        @else
            <div class="tq-band-det" style="align-items: center; justify-content: center; color: var(--b-suave)">Elige una convocatoria de la lista.</div>
        @endif
    </div>

    {{-- Configuración de la búsqueda (panel deslizante). --}}
    <x-filament::modal id="tq-busqueda-seace" slide-over width="3xl" heading="Configuración de la búsqueda">
        <div class="tq-band-conf" style="display: flex; flex-direction: column; gap: 18px">
            <section style="display: flex; flex-direction: column; gap: 12px">
                <h4>Líneas de negocio</h4>
                @php $colores = ['#0E8FAE', '#3E63DD', '#8A3FA8', '#C2532F', '#2F7D4F', '#B07A00']; @endphp
                @foreach ($this->lineas as $i => $l)
                    <div class="tq-band-linea" wire:key="linea-{{ $i }}">
                        <div style="display: flex; gap: 8px; align-items: center">
                            <span class="tq-band-cuadrito" style="background: {{ $colores[$i % count($colores)] }}" aria-hidden="true"></span>
                            <input type="text" wire:model="lineas.{{ $i }}.nombre" placeholder="Nombre de la línea" style="font-weight: 700; border: 0; background: transparent; font-size: 15px; flex: 1; color: var(--tq-texto, inherit)">
                            <span style="font-size: 13px">desde</span>
                            <input type="text" inputmode="decimal" class="tq-band-monto" aria-label="Valor mínimo de {{ $l['nombre'] }}" x-data="{ foco: false }"
                                x-bind:value="foco ? ($wire.get('lineas.{{ $i }}.minimo') ?? '') : 'S/ ' + Number($wire.get('lineas.{{ $i }}.minimo') || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"
                                x-on:focus="foco = true" x-on:blur="foco = false; $wire.set('lineas.{{ $i }}.minimo', ($event.target.value || '0').replace(/[^0-9.]/g, ''), false)">
                            @if ($puede)<button type="button" wire:click="quitarLinea({{ $i }})" class="tq-band-sub" aria-label="Quitar la línea">×</button>@endif
                        </div>
                        @foreach (['palabras' => 'PALABRAS CLAVE', 'negativas' => 'PALABRAS NEGATIVAS'] as $tipo => $titulo)
                            <div style="display: flex; flex-wrap: wrap; gap: 6px; align-items: center">
                                <span style="width: 9.5rem; font-size: 11px; font-weight: 700; letter-spacing: .04em">{{ $titulo }}</span>
                                @foreach ($l[$tipo] as $j => $p)
                                    <span @class(['tq-band-palabra', 'neg' => $tipo === 'negativas'])>{{ $p }}@if ($puede)<button type="button" wire:click="quitarPalabra({{ $i }}, '{{ $tipo }}', {{ $j }})" aria-label="Quitar {{ $p }}">×</button>@endif</span>
                                @endforeach
                                @if ($puede)<input type="text" class="tq-band-agregar" placeholder="+ agregar" wire:model="nuevaPalabra.{{ $i }}-{{ $tipo }}" wire:keydown.enter="agregarPalabra({{ $i }}, '{{ $tipo }}')">@endif
                            </div>
                        @endforeach
                        {{-- Observación 8.9: regiones (ninguna marcada = todas) y entidades que nunca entran. --}}
                        <div style="display: flex; flex-wrap: wrap; gap: 5px; align-items: center">
                            <span style="width: 9.5rem; font-size: 11px; font-weight: 700; letter-spacing: .04em">REGIONES</span>
                            <button type="button" class="tq-band-region" aria-pressed="{{ ($l['regiones'] ?? []) === [] ? 'true' : 'false' }}" wire:click="alternarRegion({{ $i }}, '*')">Todas</button>
                            @foreach ($regionesPeru as $r)
                                <button type="button" class="tq-band-region" aria-pressed="{{ in_array($r, $l['regiones'] ?? [], true) ? 'true' : 'false' }}" wire:click="alternarRegion({{ $i }}, @js($r))">{{ \App\Modules\Crm\Licitaciones\Services\FiltroConvocatorias::nombreRegion($r) }}</button>
                            @endforeach
                        </div>
                        <div style="display: flex; flex-wrap: wrap; gap: 6px; align-items: center">
                            <span style="width: 9.5rem; font-size: 11px; font-weight: 700; letter-spacing: .04em">ENTIDADES EXCLUIDAS</span>
                            @foreach ($l['excluidas'] ?? [] as $e)
                                <span class="tq-band-palabra neg">{{ $e['nombre'] }}@if ($puede)<button type="button" wire:click="incluirEntidad({{ $i }}, @js($e['ruc']))" aria-label="Quitar {{ $e['nombre'] }}">×</button>@endif</span>
                            @endforeach
                            @if ($puede)
                                <span style="position: relative">
                                    <input type="search" class="tq-band-agregar" style="width: 14rem" placeholder="+ excluir entidad (buscar)" wire:model.live.debounce.300ms="buscarEntidad.{{ $i }}">
                                    @if (! empty($sugerenciasEntidad[$i]))
                                        <span class="tq-band-pop" style="display: block">
                                            @foreach ($sugerenciasEntidad[$i] as $ruc => $nombre)
                                                <button type="button" wire:click="excluirEntidad({{ $i }}, @js((string) $ruc))"><span>{{ $nombre }}</span></button>
                                            @endforeach
                                        </span>
                                    @endif
                                </span>
                            @endif
                        </div>
                    </div>
                @endforeach
                @if ($puede)<div><button type="button" class="tq-band-btn" wire:click="nuevaLinea">+ Nueva línea de negocio</button></div>@endif
            </section>

            <section style="display: flex; flex-direction: column; gap: 8px">
                <h4>¿Por qué no entró?</h4>
                <x-filament::input.wrapper prefix-icon="heroicon-o-magnifying-glass">
                    <x-filament::input type="text" wire:model.live.debounce.500ms="porQue" placeholder="Nomenclatura o código OCDS, por ejemplo AS-SM-9-2026-MPVN-1" />
                </x-filament::input.wrapper>
                @if ($this->porQue !== '')
                    @if ($porQueResultado === null)
                        <div class="tq-band-sub">No está entre lo guardado del OECE: puede que aún no se haya leído (prueba «Leer ahora») o que la fuente no la publique.</div>
                    @else
                        @php $ev = $porQueResultado['evaluacion']; @endphp
                        <div style="border: 1px solid var(--b-linea); border-radius: 10px; padding: 12px 14px; display: flex; flex-direction: column; gap: 6px">
                            <div style="font-family: ui-monospace, monospace; font-size: 12px">{{ $porQueResultado['nomenclatura'] }}</div>
                            <div style="font-weight: 600">{{ $porQueResultado['objeto'] }}</div>
                            <div class="tq-band-sub">{{ $porQueResultado['entidad'] }} · {{ $soles($porQueResultado['valor']) }}</div>
                            <div style="font-size: 14px">
                                @if ($ev['entra'])
                                    Entra a la bandeja: coincidió con <mark>{{ implode(', ', $ev['coincide']) }}</mark>.
                                @elseif ($porQueResultado['enBandeja'])
                                    No pasa el filtro ({{ $ev['motivo'] }}), pero alguien la trajo a la bandeja.
                                @else
                                    @php
                                        $conNeg = collect($ev['detalle'])->first(fn ($x) => $x['coincide'] !== [] && $x['negativas'] !== []);
                                        $conRegion = collect($ev['detalle'])->first(fn ($x) => $x['coincide'] !== [] && $x['negativas'] === [] && ($x['fueraRegion'] ?? false));
                                        $conExcluida = collect($ev['detalle'])->first(fn ($x) => $x['coincide'] !== [] && $x['negativas'] === [] && ($x['excluida'] ?? false));
                                        $nr = fn ($r) => \App\Modules\Crm\Licitaciones\Services\FiltroConvocatorias::nombreRegion($r);
                                    @endphp
                                    @if (! $conNeg && $conExcluida)
                                        Coincidió con <mark>{{ implode(', ', $conExcluida['coincide']) }}</mark> ({{ $conExcluida['linea'] }}), pero la entidad está entre las excluidas de esa línea.
                                    @elseif (! $conNeg && $conRegion)
                                        Coincidió con <mark>{{ implode(', ', $conRegion['coincide']) }}</mark> ({{ $conRegion['linea'] }}), pero es de {{ $nr($conRegion['departamento']) }} y esa línea solo busca en {{ implode(', ', array_map($nr, $conRegion['regiones'])) }}.
                                    @elseif ($conNeg)
                                        Coincidió con <mark>{{ implode(', ', $conNeg['coincide']) }}</mark> ({{ $conNeg['linea'] }}), pero la descartó la palabra negativa <b style="color: var(--b-coral)">{{ $conNeg['negativas'][0] }}</b>.
                                    @else
                                        {{ $ev['motivo'] }}.
                                    @endif
                                @endif
                            </div>
                            @if (! $porQueResultado['enBandeja'] && $puede)
                                <div style="display: flex; gap: 8px; flex-wrap: wrap">
                                    <button type="button" class="tq-band-btn p" wire:click="traerIgual({{ $porQueResultado['id'] }})">Traer igual a la bandeja</button>
                                    @isset($conNeg)
                                        @if ($conNeg)<button type="button" class="tq-band-btn" wire:click="quitarNegativa(@js($conNeg['linea']), @js($conNeg['negativas'][0]))">Quitar la palabra negativa</button>
                                        @elseif ($conExcluida)<button type="button" class="tq-band-btn" wire:click="quitarExcluida(@js($conExcluida['linea']), @js((string) ($porQueResultado['ruc'] ?? '')))">Quitar la entidad de las excluidas</button>
                                        @elseif ($conRegion && $conRegion['departamento'])<button type="button" class="tq-band-btn" wire:click="agregarRegion(@js($conRegion['linea']), @js($conRegion['departamento']))">Agregar {{ $nr($conRegion['departamento']) }} a la línea</button>
                                        @endif
                                    @endisset
                                </div>
                            @endif
                        </div>
                    @endif
                @endif
            </section>

            <section style="display: flex; flex-direction: column; gap: 8px">
                <h4>Fuentes</h4>
                <label style="display: flex; gap: 8px; align-items: center"><input type="checkbox" checked disabled> Procedimientos del SEACE (datos abiertos del OECE)</label>
                <label class="tq-band-fuente-off" style="display: flex; gap: 8px; align-items: center"><input type="checkbox" disabled> Contratos menores <span class="tq-band-pronto">más adelante</span></label>
                <label class="tq-band-fuente-off" style="display: flex; gap: 8px; align-items: center"><input type="checkbox" disabled> Plan Anual de Contrataciones <span class="tq-band-pronto">más adelante</span></label>
                <div>{{ $this->importarAction }}</div>
                <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-top: 6px">
                    <span>Prioritaria desde</span>
                    <input type="text" inputmode="decimal" class="tq-band-monto" aria-label="Valor desde el que es prioritaria" x-data="{ foco: false }" @disabled(! $puede)
                        x-bind:value="foco ? ($wire.get('umbral') ?? '') : 'S/ ' + Number($wire.get('umbral') || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"
                        x-on:focus="foco = true" x-on:blur="foco = false; $wire.set('umbral', ($event.target.value || '0').replace(/[^0-9.]/g, ''), false)">
                    <span>o si la entidad ya es cliente</span>
                </div>
            </section>
        </div>
        @if ($puede)
            <x-slot name="footerActions">
                <x-filament::button wire:click="guardarBusqueda">Guardar y volver a filtrar</x-filament::button>
                <x-filament::button color="gray" x-on:click="close">Cancelar</x-filament::button>
            </x-slot>
        @endif
    </x-filament::modal>
</div>
