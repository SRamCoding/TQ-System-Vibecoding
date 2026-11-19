{{--
    El Resumen de la ficha del proyecto (plan 2.61, D5): ¿cuánto estamos ganando?, ¿vamos a tiempo?, ¿cuánto nos han
    pagado? y ¿qué necesita atención hoy?, más la actividad reciente. Un dato con desglose es un botón que abre el panel
    deslizante con sus filas (sin ir al servidor: ya vienen). Colores de Filament por nombre (D7).
--}}
@php
    $color = fn (?string $t): string => match ($t) { 'danger' => 'var(--danger-600)', 'warning' => 'var(--warning-600)', 'success' => 'var(--success-600)', default => 'inherit' };
@endphp
<div class="tq-resumen">
    <style>
        .tq-resumen { display: grid; grid-template-columns: repeat(auto-fit, minmax(20rem, 1fr)); gap: 1rem; }
        .tq-resumen-lineas { display: flex; flex-direction: column; }
        .tq-resumen-linea { display: flex; align-items: baseline; gap: 0.75rem; padding: 0.4rem 0; border-bottom: 1px solid var(--gray-100); font-size: 0.875rem; text-align: start; width: 100%; }
        .dark .tq-resumen-linea { border-bottom-color: var(--gray-800); }
        .tq-resumen-linea:last-child { border-bottom: 0; }
        .tq-resumen-linea .tq-valor { margin-inline-start: auto; font-weight: 600; font-variant-numeric: tabular-nums; }
        button.tq-resumen-linea:hover .tq-valor, button.tq-resumen-linea:focus-visible .tq-valor { text-decoration: underline; text-underline-offset: 3px; }
        .tq-resumen-nota { font-size: 0.75rem; color: var(--gray-500); text-align: end; margin-top: -0.2rem; padding-bottom: 0.3rem; }
        .tq-resumen-titulo { font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--gray-500); margin: 0.6rem 0 0.2rem; }
        .tq-resumen-titulo:first-child { margin-top: 0; }
        .tq-resumen-aviso { display: flex; gap: 0.5rem; align-items: flex-start; padding: 0.35rem 0; font-size: 0.875rem; }
        .tq-resumen-vacio { font-size: 0.875rem; color: var(--gray-500); }
        .tq-resumen-desglose { display: flex; flex-direction: column; font-size: 0.875rem; }
        .tq-resumen-desglose li { display: flex; gap: 0.75rem; padding: 0.45rem 0; border-bottom: 1px solid var(--gray-100); }
        .dark .tq-resumen-desglose li { border-bottom-color: var(--gray-800); }
        .tq-resumen-desglose .tq-valor { margin-inline-start: auto; font-variant-numeric: tabular-nums; }
    </style>

    @foreach ($secciones as $seccion)
        <x-filament::section :heading="$seccion->pregunta()" compact data-seccion="{{ $seccion->value }}">
            @forelse ($tarjetas[$seccion->value] as $t => $tarjeta)
                <p class="tq-resumen-titulo">{{ $tarjeta->titulo }}</p>
                <div class="tq-resumen-lineas">
                    @foreach ($tarjeta->lineas as $l => $linea)
                        @if ($linea->desglose !== [])
                            <button type="button" class="tq-resumen-linea" x-on:click="$dispatch('open-modal', { id: 'tq-desglose-{{ $seccion->value }}-{{ $t }}-{{ $l }}' })">
                                <span>{{ $linea->etiqueta }}</span><span class="tq-valor" style="color: {{ $color($linea->tono) }}">{{ $linea->valor }}</span>
                            </button>
                            <x-filament::modal id="tq-desglose-{{ $seccion->value }}-{{ $t }}-{{ $l }}" slide-over width="md" :heading="$linea->etiqueta" :description="$linea->valor">
                                <ul class="tq-resumen-desglose">
                                    @foreach ($linea->desglose as $fila)
                                        <li>
                                            @if ($fila->url !== null)
                                                <a href="{{ $fila->url }}" wire:navigate class="text-primary-600 dark:text-primary-400">{{ $fila->etiqueta }}</a>
                                            @else
                                                <span>{{ $fila->etiqueta }}</span>
                                            @endif
                                            <span class="tq-valor">{{ $fila->valor }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </x-filament::modal>
                        @else
                            <div class="tq-resumen-linea"><span>{{ $linea->etiqueta }}</span><span class="tq-valor" style="color: {{ $color($linea->tono) }}">{{ $linea->valor }}</span></div>
                        @endif
                        @if ($linea->nota !== null)
                            <p class="tq-resumen-nota">{{ $linea->nota }}</p>
                        @endif
                    @endforeach
                </div>
                @foreach ($tarjeta->avisos as $aviso)
                    <div class="tq-resumen-aviso">
                        <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-5 w-5 flex-none" style="color: {{ $color($aviso->tono) }}" />
                        @if ($aviso->url !== null)
                            <a href="{{ $aviso->url }}" wire:navigate>{{ $aviso->texto }}</a>
                        @else
                            <span>{{ $aviso->texto }}</span>
                        @endif
                    </div>
                @endforeach
            @empty
                <p class="tq-resumen-vacio">{{ $seccion->sinTarjetas() }}</p>
            @endforelse
        </x-filament::section>
    @endforeach

    <x-filament::section heading="Actividad reciente" compact>
        @forelse ($actividad as $a)
            <div class="tq-resumen-linea"><span><b>{{ $a['quien'] }}</b> · {{ $a['accion'] }}</span><span class="tq-valor" style="font-weight: 400; color: var(--gray-500)">{{ $a['cuando']->diffForHumans() }}</span></div>
        @empty
            <p class="tq-resumen-vacio">Sin actividad todavía.</p>
        @endforelse
    </x-filament::section>
</div>
