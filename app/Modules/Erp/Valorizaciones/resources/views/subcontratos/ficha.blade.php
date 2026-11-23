{{--
    Observaciones 6, punto 12: la ficha de un subcontrato. Su resumen; sus valorizaciones como línea de pasos (periodo,
    avance, neto y estado, y la retención liberada al final); su alcance por partida con lo ejecutado contra lo comprometido.
--}}
@php
    $C = \App\Modules\Erp\Valorizaciones\Filament\Pages\Subcontratos::class;
    $e = $this->estado();
    $estados = ['por_aprobar' => 'Por aprobar', 'aprobada' => 'Aprobada · por pagar', 'pagada' => 'Pagada'];
@endphp
<x-filament-panels::page>
    <div class="tq-sc" style="display: flex; flex-direction: column; gap: 18px">
        @include('valorizaciones::subcontratos.estilos')

        <div class="tq-sc-caja">@include('valorizaciones::subcontratos.resumen', ['e' => $e])</div>

        <div class="tq-sc-grilla">
            <section class="tq-sc-caja" aria-label="Valorizaciones">
                <h3>Valorizaciones</h3>
                <div class="tq-sc-pasos">
                    @forelse ($e['valorizaciones'] as $v)
                        <div class="tq-sc-paso" data-estado="{{ $v['estado'] }}" wire:key="paso-{{ $v['id'] }}">
                            <span class="tq-sc-paso-num">{{ $v['numero'] }}</span>
                            <div style="display: flex; flex-direction: column; gap: 1px; font-size: 13px">
                                <b>{{ $v['periodo'] }} · avance {{ $C::pct($v['avance_pct']) }}</b>
                                <span class="tq-sc-suave tq-sc-num">{{ $C::soles($v['monto']) }} − {{ $C::soles($v['amortizacion']) }} − {{ $C::soles($v['retencion']) }}</span>
                                <span class="tq-sc-suave">{{ $estados[$v['estado']] ?? $v['estado'] }}</span>
                            </div>
                            <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 6px">
                                <b class="tq-sc-num">{{ $C::soles($v['neto']) }}</b>
                                @if ($v['estado'] === 'por_aprobar' && $C::puedeGestionar())
                                    <x-filament::button size="xs" color="success" wire:click="mountAction('aprobar', { id: {{ $v['id'] }} })">Aprobar</x-filament::button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="tq-sc-suave" style="font-size: 13px">Todavía sin valorizaciones.</p>
                    @endforelse
                    @if ($e['terminado'])
                        <div class="tq-sc-paso" data-estado="retencion">
                            <span class="tq-sc-paso-num"><x-filament::icon icon="heroicon-m-lock-open" class="h-4 w-4" /></span>
                            <div style="display: flex; flex-direction: column; gap: 1px; font-size: 13px">
                                <b>Retención liberada · conformidad del {{ \Carbon\CarbonImmutable::parse($e['conformidad_fecha'])->format('d/m/Y') }}</b>
                                <span class="tq-sc-suave">{{ $e['retencion_pagada'] ? 'Devuelta' : 'Por pagar' }}</span>
                            </div>
                            <b class="tq-sc-num">{{ $C::soles($e['retencion_liberada']) }}</b>
                        </div>
                    @endif
                </div>
            </section>

            <section class="tq-sc-caja" aria-label="Alcance por partida">
                <h3>Alcance por partida</h3>
                @foreach ($e['alcance'] as $a)
                    @php $pct = bccomp($a['comprometido'], '0', 2) === 0 ? '0' : bcdiv(bcmul($a['ejecutado'], '100', 4), $a['comprometido'], 2); @endphp
                    <div class="tq-sc-partida">
                        <span><b>{{ $a['codigo'] }}</b> {{ $a['nombre'] }}</span>
                        <span class="tq-sc-num" style="font-weight: 600">{{ $C::soles($a['ejecutado']) }} <span class="tq-sc-suave">de {{ $C::soles($a['comprometido']) }}</span></span>
                        <div class="tq-sc-barra-pista" role="img" aria-label="Ejecutado {{ $C::pct($pct) }} de lo comprometido">
                            <span style="width: {{ min(100, (float) $pct) }}%; background: var(--tq-sc-erp)"></span>
                        </div>
                    </div>
                @endforeach
            </section>
        </div>
    </div>
</x-filament-panels::page>
