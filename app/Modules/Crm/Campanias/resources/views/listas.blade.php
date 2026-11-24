{{-- Observación 9.10: las listas de una campaña (contactos, oportunidades, ganadas, gastos), en los atajos y en la ficha. --}}
@php $soles = fn ($m) => $m === null ? '—' : 'S/ '.number_format((float) $m, 2); @endphp
<div class="tq-camp-lista-tabla" style="overflow-x: auto">
    <style>
        .tq-camp-lista-tabla table { width: 100%; font-size: 13px; border-collapse: collapse; font-variant-numeric: tabular-nums; }
        .tq-camp-lista-tabla th { text-align: start; font-size: 12px; font-weight: 600; color: var(--gray-500); padding: 6px 8px; border-bottom: 1px solid var(--tq-borde, var(--gray-200)); white-space: nowrap; }
        .tq-camp-lista-tabla td { padding: 8px; border-bottom: 1px solid var(--tq-borde, var(--gray-100)); vertical-align: top; }
        .tq-camp-lista-tabla a { color: var(--primary-600); }
        .tq-camp-lista-tabla a:hover { text-decoration: underline; }
        .tq-camp-pill { font-size: 11px; font-weight: 600; border-radius: 6px; padding: 1px 7px; white-space: nowrap; }
    </style>
    @if ($tipo === 'contactos')
        <table>
            <thead><tr><th>Nombre</th><th>Organización</th><th>Teléfono</th><th>Correo</th><th>Nuevo o ya registrado</th><th>Fecha</th><th>Quedó</th></tr></thead>
            <tbody>
                @forelse ($filas as $c)
                    <tr>
                        <td>@if ($c['url'])<a href="{{ $c['url'] }}" wire:navigate>{{ $c['nombre'] }}</a>@else{{ $c['nombre'] }}@endif</td>
                        <td>{{ $c['organizacion'] ?? '—' }}</td><td>{{ $c['telefono'] ?? '—' }}</td><td>{{ $c['email'] ?? '—' }}</td>
                        <td>@if ($c['nuevo'])<span class="tq-camp-pill" style="background: color-mix(in oklab, var(--primary-500) 14%, transparent)">nuevo</span>@else<span class="tq-camp-pill" style="background: color-mix(in oklab, var(--gray-400) 20%, transparent)" title="{{ $c['como'] }}">ya registrado</span><div style="font-size: 11px; color: var(--gray-500)">{{ $c['como'] }}</div>@endif</td>
                        <td>{{ $c['fecha'] }}</td><td>{{ $c['quedo'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" style="color: var(--gray-500)">Todavía no llegó nadie.</td></tr>
                @endforelse
            </tbody>
        </table>
    @elseif ($tipo === 'oportunidades' || $tipo === 'ganadas')
        <table>
            <thead><tr><th>Oportunidad</th><th>Etapa</th><th style="text-align: end">{{ $tipo === 'ganadas' ? 'Ganado' : 'Monto' }}</th><th style="text-align: end">Margen</th><th>Responsable</th></tr></thead>
            <tbody>
                @forelse ($filas as $o)
                    <tr>
                        <td><a href="{{ $o['url'] }}" wire:navigate>{{ $o['nombre'] }}</a></td>
                        <td style="white-space: nowrap"><span style="display: inline-flex; gap: 5px; align-items: center"><x-oportunidades::gema :talla="\App\Modules\Crm\Oportunidades\Enums\TallaDeGema::desdeEtapa($o['codigo'])->value" :tamano="16" decorativa />{{ $o['etapa'] }}</span></td>
                        <td style="text-align: end">{{ $soles($o['monto']) }}</td>
                        <td style="text-align: end">{{ $soles($o['margen']) }}@if ($o['margen'] !== null && $o['margenPrevisto'])<div style="font-size: 11px; color: var(--gray-500)">previsto</div>@endif</td>
                        <td>{{ $o['responsable'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="color: var(--gray-500)">{{ $tipo === 'ganadas' ? 'Todavía ninguna ganada.' : 'Todavía ninguna oportunidad.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    @elseif ($tipo === 'gastos')
        <table>
            <thead><tr><th>Fecha</th><th>Concepto</th><th>Imputación</th><th>Comprobante</th><th style="text-align: end">Monto</th></tr></thead>
            <tbody>
                @forelse ($filas['gastos'] as $g)
                    <tr><td>{{ $g['fecha'] }}</td><td>{{ $g['concepto'] }}<div style="font-size: 11px; color: var(--gray-500)">{{ $g['numero'] }} · {{ $g['proveedor'] }}</div></td><td style="font-size: 12px">{{ $g['imputacion'] }}</td>
                        <td>@if ($g['comprobante'])<a href="{{ $g['comprobante'] }}" target="_blank" rel="noopener">Ver</a>@else — @endif</td><td style="text-align: end">{{ $soles($g['monto']) }}</td></tr>
                @empty
                    <tr><td colspan="5" style="color: var(--gray-500)">Sin gastos registrados.</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr><td colspan="4" style="text-align: end; font-weight: 600">Gastado</td><td style="text-align: end; font-weight: 700">{{ $soles($filas['total']) }}</td></tr>
                <tr><td colspan="4" style="text-align: end; color: var(--gray-500)">Presupuesto</td><td style="text-align: end">{{ $soles($filas['presupuesto']) }}</td></tr>
                <tr><td colspan="4" style="text-align: end; font-weight: 600">{{ $filas['sePaso'] ? 'Se pasó' : 'Queda' }}</td>
                    <td style="text-align: end; font-weight: 700; color: {{ $filas['sePaso'] ? 'var(--danger-600)' : 'inherit' }}">{{ $soles(ltrim($filas['queda'], '-')) }}</td></tr>
            </tfoot>
        </table>
    @endif
</div>
