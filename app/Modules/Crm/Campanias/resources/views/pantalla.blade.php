{{--
    MV-079 (observación 7.6): la caja Campañas como los lienzos `campanias/Main.dc.html` (campañas con su retorno) y
    `Clientes.dc.html` (Mis clientes). Chips arriba: Campañas · Mis clientes (el Radar del PAC espera decisión; el
    calendario, más adelante). Recibe `$retorno`, `$grupos` y `$puede` de `ListarCampanias`.
--}}
@php
    $soles = fn ($m) => 'S/ '.number_format((float) $m, 2);
    $veces = fn (?string $r) => $r === null ? '—' : rtrim(rtrim(number_format((float) $r, 1), '0'), '.').'×';
@endphp
<div class="tq-camp">
    <style>
        .tq-camp { --c-sup: var(--tq-superficie, #fff); --c-borde: var(--tq-borde, #E5E0D6); --c-linea: color-mix(in oklch, #EFEBE3 calc(100% - var(--tq-tinte-leve, 0%)), var(--tq-tinte, #EFEBE3)); --c-suave: var(--tq-texto-suave, #5F5B53); --c-gris-fondo: color-mix(in oklch, #F1EEE7 calc(100% - var(--tq-tinte-leve, 0%)), var(--tq-tinte, #F1EEE7));
            --c-turquesa: color-mix(in oklch, #0E8FAE calc(100% - var(--tq-tinte-leve, 0%)), var(--tq-tinte, #0E8FAE)); --c-azul: color-mix(in oklch, #24389C calc(100% - var(--tq-tinte-leve, 0%)), var(--tq-tinte, #24389C)); --c-azul-fondo: color-mix(in oklch, #E8ECFB calc(100% - var(--tq-tinte-leve, 0%)), var(--tq-tinte, #E8ECFB)); --c-azul-borde: color-mix(in oklch, #C6D0F5 calc(100% - var(--tq-tinte-leve, 0%)), var(--tq-tinte, #C6D0F5)); --c-verde: color-mix(in oklch, #2F7D4F calc(100% - var(--tq-tinte-leve, 0%)), var(--tq-tinte, #2F7D4F)); --c-coral: color-mix(in oklch, #D9534A calc(100% - var(--tq-tinte-leve, 0%)), var(--tq-tinte, #D9534A)); --c-pista: color-mix(in oklch, #EFEBE3 calc(100% - var(--tq-tinte-leve, 0%)), var(--tq-tinte, #EFEBE3));
            display: flex; flex-direction: column; gap: 16px; }
        .dark .tq-camp { --c-sup: var(--tq-superficie, #2B2925); --c-borde: var(--tq-borde, #45413A); --c-linea: color-mix(in oklch, #3A3731 calc(100% - var(--tq-tinte-leve, 0%)), var(--tq-tinte, #3A3731)); --c-suave: var(--tq-texto-suave, #A8ADB4); --c-gris-fondo: color-mix(in oklch, #3A3731 calc(100% - var(--tq-tinte-leve, 0%)), var(--tq-tinte, #3A3731));
            --c-turquesa: color-mix(in oklch, #4DCFEA calc(100% - var(--tq-tinte-leve, 0%)), var(--tq-tinte, #4DCFEA)); --c-azul: color-mix(in oklch, #9DB0FF calc(100% - var(--tq-tinte-leve, 0%)), var(--tq-tinte, #9DB0FF)); --c-azul-fondo: color-mix(in oklch, #2A3150 calc(100% - var(--tq-tinte-leve, 0%)), var(--tq-tinte, #2A3150)); --c-azul-borde: color-mix(in oklch, #3E4A7A calc(100% - var(--tq-tinte-leve, 0%)), var(--tq-tinte, #3E4A7A)); --c-verde: color-mix(in oklch, #8FD3A5 calc(100% - var(--tq-tinte-leve, 0%)), var(--tq-tinte, #8FD3A5)); --c-coral: color-mix(in oklch, #FF8A75 calc(100% - var(--tq-tinte-leve, 0%)), var(--tq-tinte, #FF8A75)); --c-pista: color-mix(in oklch, #3A3731 calc(100% - var(--tq-tinte-leve, 0%)), var(--tq-tinte, #3A3731)); }
        .tq-camp-card { background: var(--c-sup); border: 1px solid var(--c-borde); border-radius: 14px; }
        .tq-camp-chips { display: flex; gap: 8px; flex-wrap: wrap; }
        .tq-camp-chip { display: inline-flex; align-items: center; gap: 6px; height: 30px; padding: 0 12px; border-radius: 999px; border: 1px solid var(--c-borde); background: var(--c-sup); font-size: 13px; }
        .tq-camp-chip .n { font-size: 11px; font-weight: 600; color: var(--c-suave); background: var(--c-gris-fondo); border-radius: 999px; padding: 1px 7px; }
        .tq-camp-chip[aria-pressed="true"] { background: var(--c-azul-fondo); border-color: var(--c-azul-borde); color: var(--c-azul); font-weight: 600; }
        .tq-camp-cifras { display: grid; grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr)); gap: 12px; }
        .tq-camp-cifra { padding: 14px 16px; }
        .tq-camp-cifra .t { font-size: 12px; color: var(--c-suave); }
        .tq-camp-cifra .v { font-size: 22px; font-weight: 700; font-variant-numeric: tabular-nums; }
        .tq-camp-cifra .d { font-size: 12px; color: var(--c-suave); }
        .tq-camp-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(21rem, 1fr)); gap: 14px; }
        .tq-camp-tarjeta { padding: 16px 18px; display: flex; flex-direction: column; gap: 12px; }
        .tq-camp-tag { font-size: 11px; font-weight: 600; border-radius: 6px; padding: 2px 8px; background: var(--c-gris-fondo); color: var(--c-suave); }
        .tq-camp-embudo { display: grid; grid-template-columns: 6.5rem 1fr 2.5rem; gap: 6px 10px; align-items: center; font-size: 12px; color: var(--c-suave); }
        .tq-camp-embudo .barra { height: 10px; border-radius: 99px; background: var(--c-pista); }
        .tq-camp-embudo .barra span { display: block; height: 10px; border-radius: 99px; background: var(--c-turquesa); }
        .tq-camp-fila { display: flex; justify-content: space-between; font-size: 13px; font-variant-numeric: tabular-nums; }
        .tq-camp-fila span:first-child { color: var(--c-suave); }
        .tq-camp-acciones { display: flex; gap: 8px; flex-wrap: wrap; border-top: 1px solid var(--c-linea); padding-top: 12px; }
        .tq-camp-grupo { padding: 18px; display: flex; flex-direction: column; gap: 10px; }
        .tq-camp-lista { padding: 6px 0; }
        .tq-camp-lista label { display: flex; align-items: center; gap: 12px; padding: 10px 16px; border-top: 1px solid var(--c-linea); font-size: 14px; }
    </style>

    <div class="tq-camp-chips">
        <button type="button" class="tq-camp-chip" aria-pressed="{{ $this->seccion === 'campanias' ? 'true' : 'false' }}" wire:click="$set('seccion', 'campanias')">Campañas</button>
        <button type="button" class="tq-camp-chip" aria-pressed="{{ $this->seccion === 'canales' ? 'true' : 'false' }}" wire:click="$set('seccion', 'canales')">Canales</button>
        <button type="button" class="tq-camp-chip" aria-pressed="{{ $this->seccion === 'clientes' ? 'true' : 'false' }}" wire:click="$set('seccion', 'clientes')">Mis clientes <span class="n">{{ count(array_filter($grupos, fn ($g) => $g['clientes'] !== [])) }}</span></button>
    </div>

    @if ($this->seccion === 'campanias' && $retorno !== null)
        @php $c = $retorno['cifras']; @endphp
        <section class="tq-camp-cifras" aria-label="Resumen del año">
            <div class="tq-camp-card tq-camp-cifra"><div class="t">Invertido en {{ $c['anio'] }}</div><div class="v">{{ $soles($c['gastado']) }}</div><div class="d">de {{ $soles($c['presupuesto']) }} presupuestado</div></div>
            <div class="tq-camp-card tq-camp-cifra"><div class="t">Contactos</div><div class="v">{{ $c['contactos'] }}</div><div class="d">{{ $c['porContacto'] === null ? '—' : $soles($c['porContacto']).' por contacto' }}</div></div>
            <div class="tq-camp-card tq-camp-cifra"><div class="t">Oportunidades</div><div class="v">{{ $c['oportunidades'] }}</div><div class="d">{{ $c['tasa'] }} % de los contactos</div></div>
            <div class="tq-camp-card tq-camp-cifra"><div class="t">Ganado</div><div class="v">{{ $soles($c['ganado']) }}</div><div class="d">{{ $c['proyectos'] }} {{ $c['proyectos'] === 1 ? 'proyecto' : 'proyectos' }}</div></div>
            <div class="tq-camp-card tq-camp-cifra"><div class="t">Retorno</div><div class="v" style="color: {{ $c['retorno'] !== null && (float) $c['retorno'] >= 1 ? 'var(--c-verde)' : 'var(--c-coral)' }}">{{ $veces($c['retorno']) }}</div><div class="d">{{ $soles($c['margen']) }} de margen por {{ $soles($c['gastado']) }}</div></div>
        </section>

        <div class="tq-camp-grid">
            @foreach ($retorno['tarjetas'] as $t)
                @php $max = max(1, $t['contactos']); @endphp
                <article class="tq-camp-card tq-camp-tarjeta" wire:key="camp-{{ $t['id'] }}">
                    <div style="display: flex; align-items: center; gap: 8px">
                        <div style="flex: 1; min-width: 0"><div style="font-weight: 700">{{ $t['nombre'] }}</div><span class="tq-camp-tag">{{ $t['tipo'] }}</span></div>
                        <div style="text-align: right"><div style="font-size: 11px; color: var(--c-suave)">Retorno</div>
                            <div style="font-size: 20px; font-weight: 700; color: {{ $t['retorno'] === null ? 'var(--c-suave)' : ((float) $t['retorno'] >= 1 ? 'var(--c-verde)' : 'var(--c-coral)') }}">{{ $veces($t['retorno']) }}</div></div>
                    </div>
                    <div class="tq-camp-embudo">
                        @foreach ([['Contactos', $t['contactos']], ['Oportunidades', $t['oportunidades']], ['Ganadas', $t['ganadas']]] as [$n, $v])
                            <span>{{ $n }}</span><span class="barra"><span style="width: {{ max($v > 0 ? 3 : 0, round($v * 100 / $max)) }}%"></span></span><b style="text-align: right; color: var(--tq-texto, inherit)">{{ $v }}</b>
                        @endforeach
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 4px">
                        <div class="tq-camp-fila"><span>Gastado / presupuesto</span><span @if ($t['sePaso']) style="color: var(--c-coral); font-weight: 600" @endif>{{ $soles($t['gastado']) }} / {{ $soles($t['presupuesto']) }}</span></div>
                        <div class="tq-camp-fila"><span>Ganado</span><span>{{ $soles($t['ganado']) }}</span></div>
                        <div class="tq-camp-fila"><span>Margen de lo ganado{{ $t['margenPrevisto'] ? ' (previsto)' : '' }}</span><span>{{ $soles($t['margen']) }}</span></div>
                        @if ($t['sinAtender'] > 0)<div class="tq-camp-fila"><span style="color: var(--c-coral)">{{ $t['sinAtender'] }} sin atender hace 3 días</span><span></span></div>@endif
                        @if ($t['destinatarios'] > 0)<div class="tq-camp-fila"><span>Clientes</span><span>{{ $t['destinatarios'] }}</span></div>@endif
                    </div>
                    <div class="tq-camp-acciones">
                        <x-filament::button size="sm" color="gray" wire:click="sobre('formulario', {{ $t['id'] }})">Formulario y QR</x-filament::button>
                        @if ($puede)<x-filament::button size="sm" color="gray" wire:click="sobre('registrarGasto', {{ $t['id'] }})">Registrar gasto</x-filament::button>@endif
                        <x-filament::button size="sm" color="gray" tag="a" :href="\App\Modules\Crm\Campanias\Filament\Resources\Campanias\Pages\VerCampania::getUrl(['record' => $t['id']])">Ver contactos</x-filament::button>
                    </div>
                </article>
            @endforeach
        </div>
    @elseif ($this->seccion === 'canales' && $canales !== null)
        @include('campanias::canales', ['canales' => $canales, 'soles' => $soles, 'veces' => $veces])
    @else
        <div class="tq-camp-grid" style="grid-template-columns: repeat(auto-fill, minmax(19rem, 1fr))">
            @foreach ($grupos as $clave => $g)
                <article class="tq-camp-card tq-camp-grupo" wire:key="grupo-{{ $clave }}" @if ($this->grupo === $clave) style="border-color: var(--c-azul-borde); box-shadow: inset 0 0 0 1px var(--c-azul-borde)" @endif>
                    <div style="font-weight: 700; font-size: 16px">{{ $g['titulo'] }}</div>
                    <div style="font-size: 13px; color: var(--c-suave)">{{ $g['oferta'] }}</div>
                    <div style="font-size: 14px">{{ count($g['clientes']) }} {{ count($g['clientes']) === 1 ? 'cliente' : 'clientes' }} · {{ $soles($g['estimado']) }} estimado</div>
                    <div style="display: flex; gap: 8px; margin-top: auto">
                        <x-filament::button size="sm" color="gray" wire:click="verLista('{{ $clave }}')" :disabled="$g['clientes'] === []">{{ $this->grupo === $clave ? 'Ocultar la lista' : 'Ver la lista' }}</x-filament::button>
                        @if ($puede)<x-filament::button size="sm" wire:click="crearDesde('{{ $clave }}')" :disabled="$g['clientes'] === []">Crear campaña</x-filament::button>@endif
                    </div>
                </article>
            @endforeach
        </div>
        @if ($this->grupo !== null && isset($grupos[$this->grupo]))
            <section class="tq-camp-card tq-camp-lista">
                <div style="padding: 8px 16px 10px; font-weight: 700">{{ $grupos[$this->grupo]['titulo'] }}</div>
                @foreach ($grupos[$this->grupo]['clientes'] as $cl)
                    <label wire:key="cl-{{ $this->grupo }}-{{ $cl['cuenta'] }}">
                        <x-filament::input.checkbox wire:model.live="incluidos.{{ $cl['cuenta'] }}" />
                        <span style="flex: 1; min-width: 0"><b>{{ $cl['cliente'] }}</b><span style="display: block; font-size: 12px; color: var(--c-suave)">{{ $cl['proyecto'] }}</span></span>
                        <span style="font-size: 13px; color: {{ str_starts_with($cl['dato'], 'vence') && (int) filter_var($cl['dato'], FILTER_SANITIZE_NUMBER_INT) < 30 ? 'var(--c-coral)' : 'var(--c-suave)' }}">{{ $cl['dato'] }}</span>
                    </label>
                @endforeach
            </section>
        @endif
    @endif
</div>
