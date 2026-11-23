{{--
    MV-076 (observación 7.3): la pestaña «Licitación» de la oportunidad como el lienzo «A · Ruta por pasos»
    (`docs/vibe/pedidos/lienzos/postulacion/Main.dc.html`; la oferta, `Oferta.dc.html`). Cabecera con tipo, etapa y
    nomenclatura, objeto, entidad y valor referencial, la cuenta regresiva a la presentación (coral a menos de 5 días) y
    «Listo para presentar N %». A la izquierda, los ocho pasos (check turquesa si está hecho, número azul resaltado si es
    el actual, gris si falta); a la derecha, el contenido del paso elegido. Recibe lo de `LicitacionDeOportunidad::render()`.
--}}
@php
    use App\Modules\Crm\Licitaciones\Models\DocumentoLicitacion;
    use App\Modules\Crm\Licitaciones\Services\PostulacionService as P;
    $soles = fn ($m) => $m === null ? '—' : 'S/ '.number_format((float) $m, 2);
    $iniciales = fn (?string $n) => $n === null ? '' : mb_strtoupper(collect(preg_split('/\s+/', trim($n)))->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode(''));
    $colorDoc = ['firmado' => 'verde', 'listo' => 'verde', 'en_revision' => 'ambar', 'falta' => 'coral', 'no_aplica' => 'gris'];
    $urgente = $diasPresentacion !== null && $diasPresentacion < P::DIAS_URGENTE;
    $actual = collect($ruta['pasos'])->firstWhere('numero', $paso);
@endphp
<div class="tq-pos" data-licitacion="{{ $l->id }}">
    <style>
        .tq-pos { --pos-sup: var(--tq-superficie, #fff); --pos-borde: var(--tq-borde, #E5E0D6); --pos-suave: var(--tq-texto-suave, #5F5B53); --pos-linea: #EFEBE3; --pos-pie: #FBFAF7;
            --pos-turquesa: #0E8FAE; --pos-azul: #24389C; --pos-azul-fondo: #E8ECFB; --pos-coral: #D9534A; --pos-coral-fondo: #FBE9E7; --pos-verde: #2F7D4F; --pos-verde-fondo: #E5F3EA;
            --pos-ambar: #B07A00; --pos-ambar-fondo: #FFF4D6; --pos-gris-fondo: #F1EEE7; --pos-celeste-fondo: #E3F5F9;
            display: flex; flex-direction: column; gap: 16px; }
        .dark .tq-pos { --pos-sup: var(--tq-superficie, #2B2925); --pos-borde: var(--tq-borde, #45413A); --pos-suave: var(--tq-texto-suave, #A8ADB4); --pos-linea: #3A3731; --pos-pie: #2F2C28;
            --pos-turquesa: #4DCFEA; --pos-azul: #9DB0FF; --pos-azul-fondo: #2A3150; --pos-coral: #FF8A75; --pos-coral-fondo: #4A2A26; --pos-verde: #8FD3A5; --pos-verde-fondo: #243A2D;
            --pos-ambar: #F2C14E; --pos-ambar-fondo: #43381E; --pos-gris-fondo: #3A3731; --pos-celeste-fondo: #1F3A42; }
        .tq-pos-card { background: var(--pos-sup); border: 1px solid var(--pos-borde); border-radius: 14px; }
        .tq-pos-tag { font-size: 11px; font-weight: 600; border-radius: 6px; padding: 2px 8px; white-space: nowrap; }
        .tq-pos-tag[data-tono="verde"] { background: var(--pos-verde-fondo); color: var(--pos-verde); }
        .tq-pos-tag[data-tono="ambar"] { background: var(--pos-ambar-fondo); color: var(--pos-ambar); }
        .tq-pos-tag[data-tono="coral"] { background: var(--pos-coral-fondo); color: var(--pos-coral); }
        .tq-pos-tag[data-tono="gris"] { background: var(--pos-gris-fondo); color: var(--pos-suave); }
        .tq-pos-tag[data-tono="celeste"] { background: var(--pos-celeste-fondo); color: var(--pos-turquesa); }
        .tq-pos-av { width: 24px; height: 24px; border-radius: 50%; background: #D7E4F7; color: #24389C; font: 700 10px Inter, sans-serif; display: inline-flex; align-items: center; justify-content: center; flex: none; }
        .tq-pos-cab { padding: 16px 22px; display: flex; flex-wrap: wrap; align-items: center; gap: 18px; }
        .tq-pos-cuenta { text-align: center; padding: 8px 18px; border-radius: 12px; background: var(--pos-gris-fondo); color: var(--pos-suave); }
        .tq-pos-cuenta[data-urgente] { background: var(--pos-coral-fondo); color: var(--pos-coral); }
        .tq-pos-barra { height: 8px; background: var(--pos-linea); border-radius: 99px; margin-top: 6px; }
        .tq-pos-barra > span { display: block; height: 8px; background: var(--pos-turquesa); border-radius: 99px; }
        .tq-pos-cuerpo { display: grid; grid-template-columns: 330px minmax(0, 1fr); gap: 16px; align-items: start; }
        @media (max-width: 1023px) { .tq-pos-cuerpo { grid-template-columns: minmax(0, 1fr); } }
        .tq-pos-pasos { padding: 10px; display: flex; flex-direction: column; gap: 2px; }
        .tq-pos-paso { display: flex; gap: 12px; padding: 12px 14px; border-radius: 10px; text-align: start; width: 100%; cursor: pointer; }
        .tq-pos-paso:hover { background: color-mix(in oklab, var(--pos-azul) 6%, transparent); }
        .tq-pos-paso[aria-current="step"] { background: var(--pos-azul-fondo); }
        .tq-pos-punto { width: 26px; height: 26px; border-radius: 50%; flex: none; display: inline-flex; align-items: center; justify-content: center; box-sizing: border-box; font: 600 12px Inter, sans-serif; }
        .tq-pos-punto[data-estado="hecho"] { background: var(--pos-turquesa); }
        .tq-pos-punto[data-estado="actual"] { background: var(--pos-sup); border: 3px solid var(--pos-azul); color: var(--pos-azul); font-weight: 700; }
        .tq-pos-punto[data-estado="pendiente"] { background: var(--pos-sup); border: 2px solid #D9D4C9; color: var(--pos-suave); }
        .tq-pos-paso-nombre { font-size: 14px; font-weight: 600; }
        .tq-pos-paso[data-estado="actual"] .tq-pos-paso-nombre { color: var(--pos-azul); font-weight: 700; }
        .tq-pos-paso[data-estado="pendiente"] .tq-pos-paso-nombre { color: var(--pos-suave); }
        .tq-pos-paso-resumen { font-size: 12px; color: var(--pos-suave); margin-top: 2px; }
        .tq-pos-panel { display: flex; flex-direction: column; overflow: hidden; min-height: 32rem; }
        .tq-pos-panel-cab { padding: 18px 20px; display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
        .tq-pos-titulo { font-size: 18px; font-weight: 700; }
        .tq-pos-sub { font-size: 13px; color: var(--pos-suave); }
        .tq-pos-fila { display: flex; align-items: center; gap: 12px; padding: 11px 16px; border-top: 1px solid var(--pos-linea); }
        .tq-pos-fila button.tq-pos-nombre { flex: 1; text-align: start; font-size: 14px; }
        .tq-pos-pie { margin-top: auto; padding: 14px 20px; border-top: 1px solid var(--pos-borde); display: flex; flex-wrap: wrap; align-items: center; gap: 10px; background: var(--pos-pie); }
        .tq-pos-texto { padding: 0 20px 18px; font-size: 14px; display: flex; flex-direction: column; gap: 12px; }
        .tq-pos-tabla { display: grid; grid-template-columns: 3fr .6fr .6fr 1.2fr 1.3fr; padding: 10px 16px; border-top: 1px solid var(--pos-linea); font-size: 13px; font-variant-numeric: tabular-nums; }
        .tq-pos-tabla.tq-cab { background: var(--pos-pie); font-size: 12px; color: var(--pos-suave); font-weight: 600; border-top: 0; }
        .tq-pos-cifra { font-size: 12px; color: var(--pos-suave); }
        .tq-pos-cifra b { display: block; font-size: 18px; font-weight: 700; color: var(--tq-texto, inherit); }
        .tq-pos-bajar { display: flex; align-items: center; gap: 10px; font-size: 13px; }
        .tq-pos-bajar > span:first-child { width: 190px; }
        .tq-pos-bajar .tq-pos-pista { flex: 1; height: 10px; border-radius: 99px; background: var(--pos-linea); }
        .tq-pos-bajar .tq-pos-pista > span { display: block; height: 10px; border-radius: 99px; }
        .tq-pos-bajar [data-tono="verde"] { background: var(--pos-verde); color: var(--pos-verde); }
        .tq-pos-bajar [data-tono="ambar"] { background: var(--pos-ambar); color: var(--pos-ambar); }
        .tq-pos-bajar [data-tono="coral"] { background: var(--pos-coral); color: var(--pos-coral); }
        .tq-pos-bajar b[data-tono] { background: none; width: 120px; text-align: right; white-space: nowrap; }
        .tq-pos-consulta { border: 1px solid var(--pos-linea); border-radius: 10px; padding: 12px 14px; display: flex; flex-direction: column; gap: 6px; }
        .tq-pos-panel { animation: tq-pos-entra .22s ease both; }
        @keyframes tq-pos-entra { from { opacity: 0; transform: translateX(6px); } to { opacity: 1; transform: none; } }
        @media (prefers-reduced-motion: reduce) { .tq-pos-panel { animation: none; } }
        html.tq-sin-animaciones .tq-pos-panel { animation: none; }
    </style>

    {{-- Cabecera. --}}
    <div class="tq-pos-card tq-pos-cab">
        <div style="flex: 1; min-width: 18rem">
            <div style="display: flex; flex-wrap: wrap; gap: 6px; align-items: center">
                <span class="tq-pos-tag" data-tono="celeste">{{ $l->es_publica ? 'Licitación pública' : 'Licitación privada' }}</span>
                <span class="tq-pos-tag" data-tono="gris">{{ $etapaOportunidad }}</span>
                <span class="tq-pos-tag" data-tono="gris">{{ $l->estado->etiqueta() }}</span>
                <span style="font-family: ui-monospace, monospace; font-size: 12px; color: var(--pos-suave)">{{ $l->nomenclatura }}</span>
            </div>
            <div style="font-size: 20px; font-weight: 700; margin-top: 6px">{{ $l->objeto }}</div>
            <div class="tq-pos-sub">{{ $entidad }} · {{ $soles($l->valor_referencial) }} valor referencial</div>
        </div>
        @if ($presentacion !== null && ! $cerrada && $l->estado->value !== 'presentada')
            <div class="tq-pos-cuenta" @if ($urgente) data-urgente @endif>
                <div style="font-size: 12px; font-weight: 600">Presentación</div>
                <div style="font-size: 26px; font-weight: 700">{{ $diasPresentacion < 0 ? 'Venció' : ($diasPresentacion === 0 ? 'Hoy' : ($diasPresentacion === 1 ? '1 día' : $diasPresentacion.' días')) }}</div>
                <div style="font-size: 12px">{{ str_replace('.', '', $presentacion->locale('es')->translatedFormat('D d M')) }} · 23:59</div>
            </div>
        @endif
        <div style="width: 220px">
            <div style="display: flex; justify-content: space-between; font-size: 12px; color: var(--pos-suave)"><span>Listo para presentar</span><b style="color: var(--tq-texto, inherit)">{{ $docs['porcentaje'] }} %</b></div>
            <div class="tq-pos-barra"><span style="width: {{ $docs['porcentaje'] }}%"></span></div>
            <div style="font-size: 12px; color: var(--pos-suave); margin-top: 6px">{{ $docs['listos'] }} de {{ $docs['aplican'] }} documentos · {{ $l->monto_ofertado === null ? 'oferta por decidir' : 'oferta lista' }}</div>
        </div>
    </div>

    @if ($l->estado->value === 'registrada')
        <div class="tq-pos-card" style="padding: 14px 20px; display: flex; flex-wrap: wrap; align-items: center; gap: 10px">
            <span style="flex: 1">Por decidir: ¿participamos?</span>
            {{ $this->participarAction }} {{ $this->noParticiparAction }}
        </div>
    @endif

    <div class="tq-pos-cuerpo">
        {{-- Los ocho pasos. --}}
        <nav class="tq-pos-card tq-pos-pasos" aria-label="Pasos de la postulación">
            @foreach ($ruta['pasos'] as $p)
                <button type="button" class="tq-pos-paso" data-estado="{{ $p['estado'] }}" wire:click="elegirPaso({{ $p['numero'] }})" @if ($p['numero'] === $paso) aria-current="step" @endif>
                    <span class="tq-pos-punto" data-estado="{{ $p['estado'] }}">
                        @if ($p['estado'] === 'hecho')
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" aria-hidden="true"><path d="M5 12l5 5L20 7"/></svg>
                        @else
                            {{ $p['numero'] }}
                        @endif
                    </span>
                    <span><span class="tq-pos-paso-nombre" style="display: block">{{ $p['nombre'] }}</span><span class="tq-pos-paso-resumen" style="display: block">{{ $p['resumen'] }}</span></span>
                </button>
            @endforeach
            <a href="{{ $urlFicha }}" style="padding: 10px 14px; font-size: 12px; color: var(--pos-suave)">Cronograma completo y participantes →</a>
        </nav>

        {{-- El contenido del paso. --}}
        <section class="tq-pos-card tq-pos-panel" wire:key="paso-{{ $paso }}">
            @switch($paso)
                @case(1)
                    <div class="tq-pos-panel-cab"><div><div class="tq-pos-titulo">Participar en el SEACE</div><div class="tq-pos-sub">Regístrate como participante en el portal; aquí se anota con su constancia.</div></div></div>
                    <div class="tq-pos-texto">
                        @if ($l->registrado_seace_el)
                            <p>Registrado el <b>{{ $l->registrado_seace_el->format('d/m/Y') }}</b>{{ $l->constancia_registro ? ' · con constancia' : '' }}.</p>
                        @else
                            <p>Recuerda: el registro de participantes cierra antes de la presentación{{ $presentacion ? ' (hasta el '.$presentacion->subDay()->format('d/m/Y').')' : '' }}. Entra con tu certificado SEACE, usuario y clave; busca <b>{{ $l->nomenclatura }}</b>.</p>
                        @endif
                    </div>
                    <div class="tq-pos-pie"><span style="flex: 1"></span>
                        <x-filament::button tag="a" :href="$urlSeace" target="_blank" color="gray" icon="heroicon-o-arrow-top-right-on-square">Abrir el SEACE</x-filament::button>
                        @unless ($l->registrado_seace_el) {{ $this->registrarParticipacionAction }} @endunless
                    </div>
                    @break

                @case(2)
                    <div class="tq-pos-panel-cab"><div><div class="tq-pos-titulo">Consultas y observaciones</div><div class="tq-pos-sub">Se redactan aquí (la IA sugiere a partir de las bases; una persona confirma) y se copian al SEACE.</div></div>
                        <span style="flex: 1"></span>{{ $this->sugerirConsultasAction }} {{ $this->redactarConsultaAction }}</div>
                    <div class="tq-pos-texto">
                        @forelse ($l->consultas as $c)
                            <div class="tq-pos-consulta" wire:key="consulta-{{ $c->id }}">
                                <div style="display: flex; flex-wrap: wrap; gap: 6px; align-items: center">
                                    <span class="tq-pos-tag" data-tono="{{ $c->tipo === 'observacion' ? 'ambar' : 'celeste' }}">{{ $c->tipo === 'observacion' ? 'Observación' : 'Consulta' }}</span>
                                    <span class="tq-pos-tag" data-tono="{{ ['borrador' => 'gris', 'enviada' => 'celeste', 'absuelta' => 'verde'][$c->estado] ?? 'gris' }}">{{ \App\Modules\Crm\Licitaciones\Models\ConsultaLicitacion::ESTADOS[$c->estado] ?? $c->estado }}</span>
                                    @if ($c->enviada_el)<span class="tq-pos-sub">enviada el {{ $c->enviada_el->format('d/m') }}{{ $c->constancia ? ' · con constancia' : '' }}</span>@endif
                                    @if ($c->origen === 'ia')<span class="tq-pos-tag" data-tono="gris">Sugerida por IA</span>@endif
                                    @if ($c->numeral)<span class="tq-pos-sub">{{ $c->numeral }}</span>@endif
                                </div>
                                <div>{{ $c->pregunta }}</div>
                                @if ($c->respuesta)<div class="tq-pos-sub"><b>Absolución:</b> {{ $c->respuesta }}</div>@endif
                                @if ($puede && $c->estado !== 'absuelta')
                                    <div style="display: flex; gap: 8px; flex-wrap: wrap" x-data="{ copiado: false }">
                                        <x-filament::button size="xs" color="gray" icon="heroicon-o-clipboard" x-on:click="navigator.clipboard.writeText({{ \Illuminate\Support\Js::from($textoSeace($c)) }}); copiado = true; setTimeout(() => copiado = false, 2000)">
                                            <span x-text="copiado ? 'Copiado' : 'Copiar para el SEACE'"></span>
                                        </x-filament::button>
                                        @if ($c->estado === 'borrador')
                                            <x-filament::button size="xs" wire:click="sobre('enviarConsulta', {{ $c->id }})">Enviada</x-filament::button>
                                        @else
                                            <x-filament::button size="xs" color="gray" wire:click="sobre('absolverConsulta', {{ $c->id }})">Registrar absolución</x-filament::button>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @empty
                            <p class="tq-pos-sub">Sin consultas todavía. Pide a la IA que lea las bases o redacta una.</p>
                        @endforelse
                    </div>
                    @break

                @case(3)
                    <div class="tq-pos-panel-cab"><div><div class="tq-pos-titulo">Bases integradas</div><div class="tq-pos-sub">Con las consultas absueltas, la entidad publica las bases integradas: el expediente sigue su orden.</div></div></div>
                    <div class="tq-pos-texto">
                        @if ($l->bases_integradas_el)
                            <p>Descargadas el <b>{{ $l->bases_integradas_el->format('d/m/Y') }}</b>.@if ($l->url_bases) <a href="{{ $l->url_bases }}" target="_blank" style="color: var(--pos-turquesa); text-decoration: underline">Abrir las bases</a>@endif</p>
                        @else
                            <p>{{ $actual['resumen'] }}.</p>
                        @endif
                    </div>
                    <div class="tq-pos-pie"><span style="flex: 1"></span>
                        <x-filament::button tag="a" :href="$urlSeace" target="_blank" color="gray" icon="heroicon-o-arrow-top-right-on-square">Abrir el SEACE</x-filament::button>
                        @unless ($l->bases_integradas_el) {{ $this->basesIntegradasAction }} @endunless
                    </div>
                    @break

                @case(4)
                    <div class="tq-pos-panel-cab">
                        <div><div class="tq-pos-titulo">Expediente</div><div class="tq-pos-sub">En el orden de las bases integradas{{ $l->documentos->contains('origen', 'ia') ? ' · sugerido por IA' : '' }}{{ $l->responsable ? ' y confirmado por '.$l->responsable->name : '' }}</div></div>
                        <span style="flex: 1"></span>
                        @if ($l->documentos->contains('es_confirmado', false)) {{ $this->confirmarSugerenciasAction }} @endif
                        @if ($puede)<x-filament::button color="gray" wire:click="nuevoDocumento">+ Documento</x-filament::button>@endif
                    </div>
                    @foreach ($l->documentos as $d)
                        @php
                            $venceEn = $d->vence === null ? null : (int) $hoy->diffInDays($d->vence, false);
                            $etiqueta = DocumentoLicitacion::ESTADOS[$d->estado] ?? $d->estado;
                            if ($d->estado === 'falta' && $venceEn !== null) {
                                $etiqueta .= ' · '.($venceEn < 0 ? 'venció pedirla' : ($venceEn === 0 ? 'vence pedirla hoy' : "pedirla en {$venceEn} días"));
                            }
                        @endphp
                        <div class="tq-pos-fila" wire:key="doc-{{ $d->id }}">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="color: var(--pos-suave)" aria-hidden="true"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/></svg>
                            <button type="button" class="tq-pos-nombre" @if ($puede) wire:click="sobre('documento', {{ $d->id }})" @endif>
                                {{ $d->nombre }}@if ($d->requiere_firma)<span class="tq-pos-sub"> · necesita firma</span>@endif @if (! $d->es_confirmado)<span class="tq-pos-sub"> · por confirmar</span>@endif
                            </button>
                            @if ($d->responsable)<span class="tq-pos-av" title="{{ $d->responsable->name }}">{{ $iniciales($d->responsable->name) }}</span>@endif
                            <span class="tq-pos-tag" data-tono="{{ $colorDoc[$d->estado] ?? 'gris' }}">{{ $etiqueta }}</span>
                        </div>
                    @endforeach
                    <div class="tq-pos-pie">
                        @if ($docs['faltan'] > 0)
                            <span style="font-size: 13px; color: var(--pos-coral); font-weight: 600">Faltan {{ $docs['faltan'] - $docs['enRevision'] }} {{ $docs['faltan'] - $docs['enRevision'] === 1 ? 'documento' : 'documentos' }}{{ $docs['enRevision'] > 0 ? ' y '.$docs['enRevision'].' en revisión' : '' }}</span>
                        @else
                            <span style="font-size: 13px; color: var(--pos-verde); font-weight: 600">Expediente completo</span>
                        @endif
                        <span style="flex: 1"></span>
                        <x-filament::button color="gray" wire:click="elegirPaso(6)">Ver el paquete</x-filament::button>
                        <x-filament::button :disabled="$docs['faltan'] > 0 || ! $puede" wire:click="mountAction('armarPaquete')">Armar paquete para el SEACE</x-filament::button>
                    </div>
                    @break

                @case(5)
                    <div class="tq-pos-panel-cab"><div><div class="tq-pos-titulo">Oferta económica</div>
                        <div class="tq-pos-sub">{{ $oferta === null ? 'Esta oportunidad aún no tiene presupuesto: créalo en Presupuestar y cotizar y la oferta sale de él.' : 'Sale del presupuesto '.$oferta['presupuesto']->numero.' · cambia el precio y se recalcula el margen' }}</div></div></div>
                    @if ($oferta !== null)
                        <div style="display: flex; flex-direction: column; border-top: 1px solid var(--pos-linea)">
                            <div>
                                <div class="tq-pos-tabla tq-cab"><span>Partida</span><span style="text-align: right">Cant.</span><span style="text-align: center">Und.</span><span style="text-align: right">Costo unit.</span><span style="text-align: right">Costo</span></div>
                                @foreach ($oferta['partidas'] as $pa)
                                    <div class="tq-pos-tabla"><span>{{ $pa['descripcion'] }}</span><span style="text-align: right">{{ rtrim(rtrim(number_format((float) $pa['cantidad'], 2), '0'), '.') }}</span><span style="text-align: center">{{ $pa['unidad'] }}</span><span style="text-align: right">{{ number_format((float) $pa['unitario'], 2) }}</span><span style="text-align: right; font-weight: 600">{{ number_format((float) $pa['costo'], 2) }}</span></div>
                                @endforeach
                                <div style="padding: 14px 16px; border-top: 1px solid var(--pos-borde); display: flex; flex-wrap: wrap; gap: 28px; background: var(--pos-pie); align-items: flex-end">
                                    <div class="tq-pos-cifra">Costo<b>{{ $soles($oferta['costo']) }}</b></div>
                                    <div class="tq-pos-cifra">Precio ofertado (con IGV)
                                        <x-filament::input.wrapper prefix="S/" style="margin-top: 4px; max-width: 12rem"><x-filament::input type="text" inputmode="decimal" wire:model.live.debounce.400ms="precio" aria-label="Precio ofertado" :disabled="! $puede" /></x-filament::input.wrapper>
                                    </div>
                                    <div class="tq-pos-cifra">Margen<b style="color: {{ $margen !== null && (float) $margen >= 0 ? 'var(--pos-verde)' : 'var(--pos-coral)' }}">{{ $margen === null ? '—' : $margen.' %' }}</b></div>
                                    <div class="tq-pos-cifra">Sobre el valor referencial<b>{{ $sobreVr === null ? '—' : $sobreVr.' %' }}</b></div>
                                    @if ($puede)<x-filament::button wire:click="guardarOferta" icon="heroicon-o-check">Guardar oferta</x-filament::button>@endif
                                </div>
                            </div>
                            <div style="padding: 18px 20px; display: flex; flex-direction: column; gap: 12px; border-top: 1px solid var(--pos-borde)">
                                <div style="font-size: 18px; font-weight: 700">¿Cuánto bajar?</div>
                                <div class="tq-pos-sub">
                                    @if ($bajar['descuento'] !== null)
                                        Descuento promedio de los ganadores en esta entidad: <b style="color: var(--tq-texto, inherit)">{{ $bajar['descuento'] }} %</b> ({{ $bajar['procesos'] }} {{ $bajar['procesos'] === 1 ? 'proceso' : 'procesos' }})
                                    @else
                                        Sin procesos anteriores guardados de esta entidad.
                                    @endif
                                </div>
                                @foreach ($bajar['filas'] as $f)
                                    <div class="tq-pos-bajar"><span>{{ $f['etiqueta'] }}</span><span class="tq-pos-pista"><span data-tono="{{ $f['tono'] }}" style="width: {{ $f['ancho'] }}%"></span></span><b data-tono="{{ $f['tono'] }}">margen {{ $f['margen'] ?? '—' }} %</b></div>
                                @endforeach
                                <div class="tq-pos-sub" style="margin-top: auto; font-size: 12px">La IA sugiere; el precio lo decide una persona.</div>
                            </div>
                        </div>
                    @endif
                    @break

                @case(6)
                    <div class="tq-pos-panel-cab"><div><div class="tq-pos-titulo">Paquete para el SEACE</div><div class="tq-pos-sub">Un PDF único en el orden de las bases, foliado, con índice; marca las páginas que necesitan firma.</div></div></div>
                    <div class="tq-pos-texto">
                        <ol style="list-style: decimal; padding-inline-start: 1.25rem; display: flex; flex-direction: column; gap: 4px">
                            <li>Índice</li>
                            @foreach ($l->documentos->where('estado', '!=', 'no_aplica') as $d)
                                <li>{{ $d->nombre }}@if ($d->requiere_firma) <span class="tq-pos-tag" data-tono="ambar">firma</span>@endif @if (! in_array($d->estado, ['listo', 'firmado'], true)) <span class="tq-pos-tag" data-tono="coral">{{ DocumentoLicitacion::ESTADOS[$d->estado] }}</span>@endif</li>
                            @endforeach
                            <li>Oferta económica{{ $l->monto_ofertado ? ' · '.$soles($l->monto_ofertado) : '' }} <span class="tq-pos-tag" data-tono="ambar">firma</span></li>
                        </ol>
                        @if ($l->paquete_armado_at)<p>Armado el <b>{{ $l->paquete_armado_at->format('d/m/Y H:i') }}</b>.</p>@endif
                    </div>
                    <div class="tq-pos-pie">
                        @if ($docs['faltan'] > 0)<span style="font-size: 13px; color: var(--pos-coral); font-weight: 600">Se arma cuando el expediente esté completo</span>@endif
                        <span style="flex: 1"></span>
                        @if ($l->paquete_archivo)<x-filament::button color="gray" icon="heroicon-o-arrow-down-tray" wire:click="descargarPaquete">Descargar el paquete</x-filament::button>@endif
                        <x-filament::button :disabled="$docs['faltan'] > 0 || ! $puede" wire:click="mountAction('armarPaquete')">{{ $l->paquete_armado_at ? 'Volver a armar' : 'Armar paquete para el SEACE' }}</x-filament::button>
                    </div>
                    @break

                @case(7)
                    <div class="tq-pos-panel-cab"><div><div class="tq-pos-titulo">Presentar en el SEACE</div><div class="tq-pos-sub">Sube el paquete en el portal y vuelve a registrar la presentación con su constancia.</div></div></div>
                    <div class="tq-pos-texto">
                        @if ($l->presentada_at)
                            <p>Presentada el <b>{{ $l->presentada_at->format('d/m/Y H:i') }}</b> por {{ $soles($l->monto_ofertado) }}{{ $l->constancia_presentacion ? ' · con constancia' : '' }}.</p>
                        @elseif ($l->estado->value !== 'en_preparacion')
                            <p>Propuesta {{ $l->fecha_presentacion ? 'presentada el '.$l->fecha_presentacion->format('d/m/Y') : 'presentada' }}.</p>
                        @else
                            <p>{{ $actual['resumen'] }}. Entra con tu certificado SEACE, usuario y clave; el sistema no guarda tus credenciales.</p>
                        @endif
                    </div>
                    <div class="tq-pos-pie"><span style="flex: 1"></span>
                        <x-filament::button tag="a" :href="$urlSeace" target="_blank" color="gray" icon="heroicon-o-arrow-top-right-on-square">Abrir el SEACE</x-filament::button>
                        @if ($l->estado->value === 'en_preparacion') {{ $this->registrarPresentacionAction }} @endif
                    </div>
                    @break

                @default
                    <div class="tq-pos-panel-cab"><div><div class="tq-pos-titulo">Subsanación y resultado</div><div class="tq-pos-sub">Si la entidad pide subsanar, se registra con su plazo (va como aviso en Mien) y su respuesta.</div></div>
                        <span style="flex: 1"></span>@if ($l->estado->value === 'presentada') {{ $this->pedirSubsanacionAction }} @endif</div>
                    <div class="tq-pos-texto">
                        @foreach ($l->subsanaciones as $s)
                            @php $d = (int) $hoy->diffInDays($s->plazo, false); @endphp
                            <div class="tq-pos-consulta" wire:key="sub-{{ $s->id }}">
                                <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap">
                                    <span class="tq-pos-tag" data-tono="{{ $s->respondida_el ? 'verde' : 'coral' }}">{{ $s->respondida_el ? 'Respondida el '.$s->respondida_el->format('d/m') : 'Subsanar '.($d < 0 ? '· venció' : ($d === 0 ? 'hoy' : "en {$d} días")) }}</span>
                                    <span class="tq-pos-sub">pedida el {{ $s->pedida_el->format('d/m') }} · plazo {{ $s->plazo->format('d/m/Y') }}</span>
                                </div>
                                <div>{{ $s->pedido }}</div>
                                @if ($s->respuesta)<div class="tq-pos-sub"><b>Respuesta:</b> {{ $s->respuesta }}</div>@endif
                                @if ($puede && ! $s->respondida_el)<div><x-filament::button size="xs" wire:click="sobre('responderSubsanacion', {{ $s->id }})">Responder</x-filament::button></div>@endif
                            </div>
                        @endforeach
                        <p>{{ $actual['resumen'] }}.</p>
                        @if ($l->participantes->isNotEmpty())
                            <ul class="tq-pos-sub">
                                @foreach ($l->participantes as $pp)<li>{{ $pp->puesto ? $pp->puesto.'.º ' : '' }}{{ $pp->nombre }}{{ $pp->oferta ? ' · '.$soles($pp->oferta) : '' }}</li>@endforeach
                            </ul>
                        @endif
                    </div>
                    <div class="tq-pos-pie"><span style="flex: 1"></span>{{ $this->resultadoAction }}</div>
            @endswitch
        </section>
    </div>

    <x-filament-actions::modals />
</div>
