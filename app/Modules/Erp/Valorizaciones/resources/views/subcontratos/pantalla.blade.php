{{--
    Observaciones 6, punto 12: Subcontratos. Cinco cifras, la franja de filtros (chips, buscador y proyecto), la bandeja
    «Por aprobar» (si hay pendientes) y una tarjeta por subcontrato en dos columnas, en orden por urgencia.
--}}
@php
    $C = \App\Modules\Erp\Valorizaciones\Filament\Pages\Subcontratos::class;
    $d = $this->datos();
    $cuenta = [
        'por_aprobar' => count(array_filter($d['todos'], fn ($e) => $e['pendientes'] !== [])),
        'por_pagar' => count(array_filter($d['todos'], fn ($e) => bccomp($e['por_pagar'], '0', 2) > 0)),
        'con_aviso' => count(array_filter($d['todos'], fn ($e) => $e['alarma'])),
        'terminados' => count(array_filter($d['todos'], fn ($e) => $e['terminado'])),
    ];
    $cf = $d['cifras'];
@endphp
<x-filament-panels::page>
    <div class="tq-sc" style="display: flex; flex-direction: column; gap: 18px">
        @include('valorizaciones::subcontratos.estilos')

        <div class="tq-sc-cifras">
            <div class="tq-sc-cifra"><b class="tq-sc-num">{{ $cf['vigentes'] }}</b><span>Subcontratos vigentes</span></div>
            <div class="tq-sc-cifra"><b class="tq-sc-num">{{ $C::soles($cf['comprometido']) }}</b><span>Comprometido</span></div>
            <div class="tq-sc-cifra"><b class="tq-sc-num">{{ $cf['por_aprobar_cantidad'] }} · {{ $C::soles($cf['por_aprobar']) }}</b><span>Por aprobar</span></div>
            <div class="tq-sc-cifra"><b class="tq-sc-num">{{ $C::soles($cf['por_pagar']) }}</b><span>Por pagar</span></div>
            <div class="tq-sc-cifra"><b class="tq-sc-num">{{ $C::soles($cf['retenciones']) }}</b><span>Retenciones por devolver</span></div>
        </div>

        <div class="tq-sc-franja">
            @foreach ($C::FILTROS as $clave => $texto)
                <button type="button" class="tq-sc-chip" aria-pressed="{{ $this->filtro === $clave ? 'true' : 'false' }}" wire:click="poner('{{ $clave }}')">{{ $texto }} <small>{{ $cuenta[$clave] }}</small></button>
            @endforeach
            <span style="flex: 1"></span>
            <x-filament::input.wrapper prefix-icon="heroicon-o-magnifying-glass" style="min-width: 15rem">
                <x-filament::input type="search" wire:model.live.debounce.300ms="buscar" placeholder="Subcontratista, objeto o código" aria-label="Buscar subcontratos" />
            </x-filament::input.wrapper>
            <x-filament::input.wrapper style="max-width: 22rem">
                <x-filament::input.select wire:model.live="proyecto" aria-label="Proyecto">
                    <option value="">Todos los proyectos</option>
                    @foreach ($d['proyectos'] as $id => $t)
                        <option value="{{ $id }}">{{ \Illuminate\Support\Str::limit($t, 60) }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
            @if ($this->filtro !== null || $this->buscar !== '' || $this->proyecto !== null)
                <button type="button" class="tq-sc-limpiar" wire:click="limpiar">Limpiar filtros</button>
            @endif
        </div>

        @if ($d['bandeja'] !== [])
            <section class="tq-sc-bandeja" aria-label="Por aprobar">
                <h3>Por aprobar · {{ count($d['bandeja']) }}</h3>
                @foreach ($d['bandeja'] as $v)
                    @php $e = $v['sc']; @endphp
                    <div class="tq-sc-pend" wire:key="pend-{{ $v['id'] }}">
                        <span class="tq-sc-avatar" aria-hidden="true">{{ $e['iniciales'] }}</span>
                        <div style="min-width: 0; display: flex; flex-direction: column; gap: 1px">
                            <span class="tq-sc-corta" style="font-weight: 700; font-size: 14px">{{ $e['subcontratista'] }}</span>
                            <span class="tq-sc-suave" style="font-size: 12px">{{ $e['codigo'] }} · val. {{ $v['numero'] }} · {{ $v['periodo'] }} · avance {{ $C::pct($v['avance_pct']) }}@if ($v['dias'] > 0) · espera {{ $v['dias'] }} {{ $v['dias'] === 1 ? 'día' : 'días' }}@endif</span>
                        </div>
                        <div class="tq-sc-cuenta tq-sc-num">
                            <span title="Monto">{{ $C::soles($v['monto']) }}</span> <span class="tq-sc-suave">−</span> <span title="Amortización del adelanto">{{ $C::soles($v['amortizacion']) }}</span>
                            <span class="tq-sc-suave">−</span> <span title="Retención de garantía">{{ $C::soles($v['retencion']) }}</span> <span class="tq-sc-suave">=</span> <b title="Neto a pagar">{{ $C::soles($v['neto']) }}</b>
                        </div>
                        <div style="display: flex; align-items: center; gap: 12px; justify-content: flex-end; flex-wrap: wrap">
                            @include('valorizaciones::subcontratos.aviso', ['e' => $e])
                            @if ($C::puedeGestionar())
                                <x-filament::button size="sm" color="success" icon="heroicon-o-check" wire:click="mountAction('aprobar', { id: {{ $v['id'] }} })">Aprobar</x-filament::button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </section>
        @endif

        <div class="tq-sc-grilla">
            @forelse ($d['tarjetas'] as $e)
                @include('valorizaciones::subcontratos.tarjeta', ['e' => $e])
            @empty
                <p class="tq-sc-suave" style="font-size: 14px">Ningún subcontrato con estos filtros.</p>
            @endforelse
        </div>
    </div>
</x-filament-panels::page>
