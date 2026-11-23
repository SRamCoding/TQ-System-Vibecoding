{{-- La barra apilada del dinero de un subcontrato: pagado · aprobado por pagar · por aprobar · por ejecutar, sobre su monto. --}}
@php
    $C = \App\Modules\Erp\Valorizaciones\Filament\Pages\Subcontratos::class;
    $partes = [
        ['Pagado', $e['pagado'], 'var(--tq-sc-pagado)'],
        ['Aprobado por pagar', $e['aprobado'], 'var(--tq-sc-erp)'],
        ['Por aprobar', $e['por_aprobar'], 'var(--tq-sc-poraprobar)'],
        ['Por ejecutar', $e['por_ejecutar'], 'var(--tq-sc-resto)'],
    ];
    $ancho = fn (string $m): string => bccomp($e['monto'], '0', 2) === 0 ? '0' : bcdiv(bcmul($m, '100', 4), $e['monto'], 2);
@endphp
<div class="tq-sc-barra" @if ($fija ?? false) data-fija @endif tabindex="-1">
    <div class="tq-sc-barra-pista" role="img" aria-label="{{ collect($partes)->map(fn ($p) => $p[0].' '.$C::soles($p[1]))->implode(', ') }}">
        @foreach (array_slice($partes, 0, 3) as [$nombre, $m, $color])
            @if (bccomp($m, '0', 2) > 0)
                <span style="width: {{ $ancho($m) }}%; background: {{ $color }}" title="{{ $nombre }}: {{ $C::soles($m) }}"></span>
            @endif
        @endforeach
    </div>
    <div class="tq-sc-leyenda" aria-hidden="true">
        @foreach ($partes as [$nombre, $m, $color])
            <span><i class="tq-sc-punto" style="background: {{ $color }}"></i>{{ $nombre }}</span><span class="tq-sc-num" style="text-align: right; font-weight: 600">{{ $C::soles($m) }}</span>
        @endforeach
    </div>
</div>
