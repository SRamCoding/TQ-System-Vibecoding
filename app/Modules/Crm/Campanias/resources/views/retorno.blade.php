{{--
    Observación 9.11: el retorno, calculado al mirar. Real (de lo ganado) en verde si es 1× o más, coral si menos;
    esperado (de lo abierto) en gris con «esperado»; los dos a la vez si hay ganadas y abiertas; sin gasto, «sin gasto».
    Al pasar el puntero, cómo leerlo y el detalle de lo esperado. Recibe `$r` (real), `$e` (esperado), `$ganadas`,
    `$detalle` (lista de lo esperado), `$gastado` y `$tamano` (px).
--}}
@php
    $tamano ??= 20;
    $detalle ??= [];
    $x = fn (?string $v) => $v === null ? '0×' : rtrim(rtrim(number_format((float) $v, 1), '0'), '.').'×';
    $sinGasto = bccomp((string) $gastado, '0', 2) <= 0;
    $hayAbiertas = $detalle !== [] || ($abiertas ?? 0) > 0;
    $ayuda = [];
    if (! $sinGasto && $ganadas > 0 && $r !== null) { $ayuda[] = 'Por cada S/ 1 invertido volvieron S/ '.number_format((float) $r, 2).' de ganancia (margen de lo ganado ÷ lo gastado).'; }
    if (! $sinGasto && $e !== null) { $ayuda[] = 'Esperado: de lo que sigue abierto se espera S/ '.number_format((float) $e, 2).' de ganancia por cada S/ 1 (margen previsto × probabilidad de su etapa).'; }
    foreach ($detalle as $d) { $ayuda[] = '· '.$d['nombre'].' — '.$d['etapa'].' '.$d['probabilidad'].' % × margen S/ '.number_format((float) $d['margen'], 0).($d['supuesto'] ? ' (sin cotización: monto × margen mínimo)' : '').' = S/ '.number_format((float) $d['aporta'], 0); }
    $verde = 'var(--c-verde, var(--success-600))'; $coral = 'var(--c-coral, var(--danger-600))'; $gris = 'var(--c-suave, var(--gray-500))';
@endphp
<span title="{{ implode("\n", $ayuda) }}" style="font-weight: 700; font-size: {{ $tamano }}px; font-variant-numeric: tabular-nums; white-space: nowrap; cursor: help">
    @if ($sinGasto)
        <span style="color: {{ $gris }}; font-size: {{ max(12, $tamano - 6) }}px">sin gasto</span>
    @elseif ($ganadas > 0)
        <span style="color: {{ (float) $r >= 1 ? $verde : $coral }}">{{ $x($r) }}</span>
        @if ($hayAbiertas && $e !== null)<span style="color: {{ $gris }}; font-size: {{ max(12, $tamano - 7) }}px; font-weight: 600; {{ ($apilado ?? false) ? 'display: block' : '' }}">{{ ($apilado ?? false) ? '' : ' · ' }}+ {{ $x($e) }} esperado</span>@endif
    @elseif ($hayAbiertas && $e !== null)
        <span style="color: {{ $gris }}">{{ $x($e) }} <span style="font-size: {{ max(11, $tamano - 8) }}px; font-weight: 600">esperado</span></span>
    @else
        <span style="color: {{ $coral }}">0×</span>
    @endif
</span>
