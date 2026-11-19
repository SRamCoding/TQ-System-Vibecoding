{{--
    Portada de Cuentas (plan 2.20, D11): lo que hay que revisar, sobre la tabla. «Ver cuáles» pone el filtro «Revisar»
    en el sitio, sin recargar; «Ver todas» lo quita. Solo salen los avisos con alguna cuenta, y nunca el de un dato que
    todavía nadie aporta. Recibe `$avisos` (clave => cantidad) y `$filtro` (el aviso puesto, o nulo).
--}}
@php
    $textos = [
        'sin-actividad' => [1 => 'cuenta sin actividad reciente', 0 => 'cuentas sin actividad reciente', 'tono' => 'ambar', 'mas' => 'Sin contacto en los últimos días del parámetro «días sin actividad».'],
        'ejecutivo-inactivo' => [1 => 'cuenta con ejecutivo inactivo', 0 => 'cuentas con ejecutivo inactivo', 'tono' => 'ambar', 'mas' => 'Su ejecutivo ya no está activo: reasígnalas.'],
        'ficha-inactiva' => [1 => 'cuenta con su ficha inactiva', 0 => 'cuentas con su ficha inactiva', 'tono' => 'azul', 'mas' => 'La empresa está inactiva en Personas y empresas: no reciben trabajo nuevo.'],
        'sin-rubro' => [1 => 'cuenta sin rubro', 0 => 'cuentas sin rubro', 'tono' => 'azul', 'mas' => 'Elige su rubro en la ficha de la cuenta.'],
    ];
@endphp
<div class="tq-cuentas-avisos">
    <style>
        .tq-cuentas-avisos { display: flex; flex-direction: column; gap: 0.5rem; }
        .tq-cuentas-aviso { display: flex; flex-wrap: wrap; align-items: center; gap: 0.25rem 0.75rem; padding: 0.625rem 0.875rem; border-radius: 0.625rem; font-size: 0.875rem; border: 1px solid; }
        .tq-cuentas-aviso-ambar { color: #92400E; background: #FFFBEB; border-color: #FCD34D; }
        .dark .tq-cuentas-aviso-ambar { color: #FCD34D; background: rgb(146 64 14 / 0.18); border-color: rgb(252 211 77 / 0.35); }
        .tq-cuentas-aviso-azul { color: #1E3A8A; background: #EFF6FF; border-color: #BFDBFE; }
        .dark .tq-cuentas-aviso-azul { color: #BFDBFE; background: rgb(30 58 138 / 0.25); border-color: rgb(191 219 254 / 0.3); }
        .tq-cuentas-aviso button { font-weight: 600; text-decoration: underline; text-underline-offset: 2px; }
    </style>

    @foreach ($avisos as $clave => $cantidad)
        <div class="tq-cuentas-aviso tq-cuentas-aviso-{{ $textos[$clave]['tono'] }}" role="status" data-aviso="{{ $clave }}">
            <span><strong>{{ $cantidad }} {{ $textos[$clave][$cantidad === 1 ? 1 : 0] }}</strong>. {{ $textos[$clave]['mas'] }}</span>
            @if ($filtro === $clave)
                <button type="button" wire:click="verAviso(null)">Ver todas</button>
            @else
                <button type="button" wire:click="verAviso('{{ $clave }}')">Ver cuáles</button>
            @endif
        </div>
    @endforeach
</div>
