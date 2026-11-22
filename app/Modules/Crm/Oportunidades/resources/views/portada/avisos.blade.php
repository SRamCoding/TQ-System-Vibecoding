{{--
    Portada de Oportunidades (plan 2.21, D11.1): lo que hay que revisar, sobre la tabla, con el patrón de Cuentas. «Ver
    cuáles» pone el filtro «Revisar» en el sitio, sin recargar; «Ver todas» lo quita. Solo salen los avisos con alguna
    oportunidad. Recibe `$avisos` (clave => cantidad) y `$filtro` (el aviso puesto, o nulo).
--}}
@php
    $textos = [
        'estancadas' => [1 => 'oportunidad estancada', 0 => 'oportunidades estancadas', 'mas' => 'Llevan en su etapa más días que el máximo del parámetro.'],
        'ejecutivo-inactivo' => [1 => 'oportunidad abierta con ejecutivo inactivo', 0 => 'oportunidades abiertas con ejecutivo inactivo', 'mas' => 'Su ejecutivo ya no está activo: reasígnalas.'],
    ];
@endphp
<div class="tq-oportunidades-avisos">
    <style>
        .tq-oportunidades-avisos { display: flex; flex-direction: column; gap: 0.5rem; }
        .tq-oportunidades-aviso { display: flex; flex-wrap: wrap; align-items: center; gap: 0.25rem 0.75rem; padding: 0.625rem 0.875rem; border-radius: 0.625rem; font-size: 0.875rem; border: 1px solid; color: var(--warning-800); background: color-mix(in oklch, var(--tq-superficie) 85%, var(--warning-500)); border-color: color-mix(in oklch, var(--tq-borde) 50%, var(--warning-500)); }
        .dark .tq-oportunidades-aviso { color: color-mix(in oklch, var(--tq-borde) 50%, var(--warning-500)); background: color-mix(in oklch, var(--tq-superficie) 82%, var(--warning-500)); border-color: color-mix(in oklch, var(--tq-borde) 60%, var(--warning-400)); }
        .tq-oportunidades-aviso button { font-weight: 600; text-decoration: underline; text-underline-offset: 2px; }
    </style>

    @foreach ($avisos as $clave => $cantidad)
        <div class="tq-oportunidades-aviso" role="status" data-aviso="{{ $clave }}">
            <span><strong>{{ $cantidad }} {{ $textos[$clave][$cantidad === 1 ? 1 : 0] }}</strong>. {{ $textos[$clave]['mas'] }}</span>
            @if ($filtro === $clave)
                <button type="button" wire:click="verAviso(null)">Ver todas</button>
            @else
                <button type="button" wire:click="verAviso('{{ $clave }}')">Ver cuáles</button>
            @endif
        </div>
    @endforeach
</div>
