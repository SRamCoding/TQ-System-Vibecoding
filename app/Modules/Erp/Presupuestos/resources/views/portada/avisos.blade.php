{{--
    Portada de Presupuestos (plan 2.10, D1), como la de Cuentas: lo que hay que revisar, sobre la tabla. «Ver cuáles»
    pone el filtro «Revisar» en el sitio, sin recargar; «Ver todos» lo quita. Recibe `$avisos` y `$filtro`.
--}}
@php
    $textos = [
        'sin-producto' => [1 => 'presupuesto en curso con materiales sin producto', 0 => 'presupuestos en curso con materiales sin producto', 'mas' => 'Sin el enlace no se compara lo presupuestado con lo comprado. Se completa en «Insumos».'],
    ];
@endphp
<div class="tq-presupuestos-avisos">
    <style>
        .tq-presupuestos-avisos { display: flex; flex-direction: column; gap: 0.5rem; }
        .tq-presupuestos-aviso { display: flex; flex-wrap: wrap; align-items: center; gap: 0.25rem 0.75rem; padding: 0.625rem 0.875rem; border-radius: 0.625rem; font-size: 0.875rem; border: 1px solid; color: #1E3A8A; background: #EFF6FF; border-color: #BFDBFE; }
        .dark .tq-presupuestos-aviso { color: #BFDBFE; background: rgb(30 58 138 / 0.25); border-color: rgb(191 219 254 / 0.3); }
        .tq-presupuestos-aviso button { font-weight: 600; text-decoration: underline; text-underline-offset: 2px; }
    </style>

    @foreach ($avisos as $clave => $cantidad)
        <div class="tq-presupuestos-aviso" role="status" data-aviso="{{ $clave }}">
            <span><strong>{{ $cantidad }} {{ $textos[$clave][$cantidad === 1 ? 1 : 0] }}</strong>. {{ $textos[$clave]['mas'] }}</span>
            @if ($filtro === $clave)
                <button type="button" wire:click="verAviso(null)">Ver todos</button>
            @else
                <button type="button" wire:click="verAviso('{{ $clave }}')">Ver cuáles</button>
            @endif
        </div>
    @endforeach
</div>
