{{--
    Plan 1.4f, D10: en el celular no hay barra lateral; para cambiar de módulo se vuelve al inicio con esta casa, a la
    izquierda del logo. Solo en el celular (`tq-solo-celular`, corte de BarraLateral) y nunca en el propio inicio. El
    área táctil mide 44 × 44 px aunque el ícono sea más chico; sin tooltip en el celular, el `aria-label` lo explica.
--}}
<style>
    .tq-ir-inicio { display: inline-flex; align-items: center; justify-content: center; inline-size: 44px; block-size: 44px; flex-shrink: 0; margin-inline-end: 0.25rem; border-radius: 0.5rem; color: var(--gray-500); }
    .tq-ir-inicio:hover, .tq-ir-inicio:focus-visible { color: var(--primary-600); background-color: var(--gray-50); }
    .dark .tq-ir-inicio { color: var(--gray-400); }
    .dark .tq-ir-inicio:hover, .dark .tq-ir-inicio:focus-visible { color: var(--primary-400); background-color: var(--gray-800); }
    .tq-ir-inicio-icono { inline-size: 1.5rem; block-size: 1.5rem; }
</style>
<a href="{{ $url }}" class="tq-ir-inicio tq-solo-celular" aria-label="Ir al inicio">
    <x-filament::icon icon="heroicon-o-home" class="tq-ir-inicio-icono" />
</a>
