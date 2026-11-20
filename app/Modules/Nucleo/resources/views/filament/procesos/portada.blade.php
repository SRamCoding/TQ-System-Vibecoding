{{--
    Portada de un proceso (MV-020): sus cajas internas, del tamaño de las del inicio, con el color de su franja. La
    descripción va en el tooltip; el clic abre la pantalla en la misma pestaña.
--}}
@php /** @var array{proceso: \App\Modules\Nucleo\Navegacion\Proceso, caja: \App\Modules\Nucleo\Navegacion\CajaModulo, internas: list<\App\Modules\Nucleo\Navegacion\CajaModulo>} $datos */ @endphp
<div class="tq-portada-proceso" data-area="{{ $datos['caja']->area->value }}">
    @include('nucleo::filament.partials.cajas-con-panel')
    <style>
        /* MV-033: el color de la franja, como en el inicio. */
        .tq-portada-proceso[data-area="personal"] { --tq-area-color: var(--dorado-600); }
        .tq-portada-proceso[data-area="nucleo"] { --tq-area-color: {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::AZUL_NOCHE }}; }
        .tq-portada-proceso[data-area="crm"] { --tq-area-color: var(--morado-700); }
        .tq-portada-proceso[data-area="erp"] { --tq-area-color: {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::TURQUESA_TEXTO }}; }
        .tq-portada-proceso[data-area="plataforma"] { --tq-area-color: {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::GRIS_PLATAFORMA }}; }
        .dark .tq-portada-proceso[data-area="personal"] { --tq-area-color: var(--dorado-400); }
        .dark .tq-portada-proceso[data-area="nucleo"] { --tq-area-color: {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::AZUL_NOCHE_OSCURO }}; }
        .dark .tq-portada-proceso[data-area="crm"] { --tq-area-color: {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::LILA_OSCURO }}; }
        .dark .tq-portada-proceso[data-area="erp"] { --tq-area-color: {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::TURQUESA }}; }
        .dark .tq-portada-proceso[data-area="plataforma"] { --tq-area-color: {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::GRIS_PLATAFORMA_OSCURO }}; }
        .tq-portada-proceso .tq-caja-catalogo { border-top-color: var(--tq-area-color); }
        .tq-portada-proceso .tq-caja-catalogo:hover { --tw-ring-color: var(--tq-area-color); }
        .tq-portada-proceso .tq-caja-catalogo-icono { color: var(--tq-area-color); }
        .tq-portada-franja { margin-bottom: 1rem; font-size: 0.8rem; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; color: var(--tq-area-color); }
    </style>
    <p class="tq-portada-franja">{{ $datos['proceso']->franja()->etiqueta() }}</p>
    <div class="tq-catalogos-cajas">
        @foreach ($datos['internas'] as $interna)
            <a
                href="{{ $interna->url }}"
                class="fi-section tq-caja-catalogo tq-caja-sub"
                data-modulo="{{ $interna->clave }}"
                x-data
                @if ($interna->descripcion !== null) x-tooltip="{ content: @js($interna->descripcion), theme: $store.theme }" @endif
            >
                <x-filament::icon :icon="$interna->icono" class="tq-caja-catalogo-icono tq-caja-sub-icono" />
                <span class="tq-caja-catalogo-nombre">{{ $interna->nombre }}</span>
            </a>
        @endforeach
    </div>
</div>
