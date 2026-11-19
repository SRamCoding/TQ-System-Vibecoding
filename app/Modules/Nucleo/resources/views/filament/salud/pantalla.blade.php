{{--
    Configuración → Salud del sistema (plan 1.12, D2). Un recuadro por comprobación, con su estado escrito (nunca solo
    un color: interfaz.md), una frase y sus datos. Arriba, el enlace a Pulse (errores, pedidos y consultas lentas).
--}}
@php
    /** @var list<array{clave: string, titulo: string, icono: string, resultado: \App\Modules\Nucleo\Salud\ResultadoComprobacion}> $recuadros */
    $etiquetas = ['bien' => 'Bien', 'aviso' => 'Atender', 'falla' => 'Falla', 'sin_datos' => 'Sin datos'];
@endphp
<div class="tq-salud">
    <style>
        .tq-salud-arriba { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.75rem; margin-bottom: 1.25rem; font-size: 0.875rem; color: var(--gray-600); }
        .dark .tq-salud-arriba { color: var(--gray-400); }
        .tq-salud-recuadros { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fill, minmax(18rem, 1fr)); }
        .tq-salud-recuadro { border-radius: 0.9rem; padding: 1rem 1.1rem; background: #FFFFFF; border: 1px solid var(--gray-200); border-left-width: 4px; }
        .dark .tq-salud-recuadro { background: var(--gray-900); border-color: var(--gray-700); }
        .tq-salud-recuadro[data-estado="bien"] { border-left-color: var(--success-600); }
        .tq-salud-recuadro[data-estado="aviso"] { border-left-color: var(--warning-500); }
        .tq-salud-recuadro[data-estado="falla"] { border-left-color: var(--danger-600); }
        .tq-salud-recuadro[data-estado="sin_datos"] { border-left-color: var(--gray-400); }
        .tq-salud-cabeza { display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.4rem; }
        .tq-salud-cabeza h2 { font-size: 0.95rem; font-weight: 700; flex: 1; }
        .tq-salud-estado { font-size: 0.72rem; font-weight: 700; padding: 0.1rem 0.5rem; border-radius: 9999px; }
        .tq-salud-recuadro[data-estado="bien"] .tq-salud-estado { color: var(--success-700); background: color-mix(in oklch, var(--success-600) 12%, transparent); }
        .tq-salud-recuadro[data-estado="aviso"] .tq-salud-estado { color: var(--warning-700); background: color-mix(in oklch, var(--warning-500) 14%, transparent); }
        .tq-salud-recuadro[data-estado="falla"] .tq-salud-estado { color: var(--danger-700); background: color-mix(in oklch, var(--danger-600) 12%, transparent); }
        .tq-salud-recuadro[data-estado="sin_datos"] .tq-salud-estado { color: var(--gray-600); background: var(--gray-100); }
        .dark .tq-salud-recuadro[data-estado="bien"] .tq-salud-estado { color: var(--success-400); }
        .dark .tq-salud-recuadro[data-estado="aviso"] .tq-salud-estado { color: var(--warning-400); }
        .dark .tq-salud-recuadro[data-estado="falla"] .tq-salud-estado { color: var(--danger-400); }
        .dark .tq-salud-recuadro[data-estado="sin_datos"] .tq-salud-estado { color: var(--gray-300); background: var(--gray-800); }
        .tq-salud-resumen { font-size: 0.875rem; margin-bottom: 0.6rem; }
        .tq-salud-datos { display: grid; grid-template-columns: max-content 1fr; gap: 0.25rem 0.8rem; font-size: 0.8rem; }
        .tq-salud-datos dt { color: var(--gray-500); }
    </style>

    <div class="tq-salud-arriba">
        <span>Cada recuadro se revisa al abrir la pantalla.</span>
        <x-filament::link :href="url(config('pulse.path', 'pulse'))" icon="heroicon-m-chart-bar" target="_blank" data-tq-salud="pulse">
            Ver Pulse: errores, pedidos y consultas lentas
        </x-filament::link>
    </div>

    <div class="tq-salud-recuadros">
        @foreach ($recuadros as ['clave' => $clave, 'titulo' => $titulo, 'icono' => $icono, 'resultado' => $resultado])
            <section class="tq-salud-recuadro" data-estado="{{ $resultado->estado->value }}" data-tq-salud="{{ $clave }}">
                <div class="tq-salud-cabeza">
                    <x-filament::icon :icon="$icono" class="h-5 w-5 text-gray-500" />
                    <h2>{{ $titulo }}</h2>
                    <span class="tq-salud-estado">{{ $etiquetas[$resultado->estado->value] }}</span>
                </div>
                <p class="tq-salud-resumen">{{ $resultado->resumen }}</p>
                @if ($resultado->detalle !== [])
                    <dl class="tq-salud-datos">
                        @foreach ($resultado->detalle as $etiqueta => $valor)
                            <dt>{{ $etiqueta }}</dt>
                            <dd>{{ $valor }}</dd>
                        @endforeach
                    </dl>
                @endif
            </section>
        @endforeach
    </div>
</div>
