{{--
    La pantalla de un paso abierta dentro de otra (plan 1.13b, D6), con los datos de `MuestraPasoAbierto`: la usan el panel
    deslizante de Configuración inicial y la ventana de bienvenida, cada una con su marco (título, botones, cerrar). Un
    enlace a otra pantalla del sistema se abre aquí mismo, y también una redirección que venga de adentro; con Ctrl,
    Shift, el botón del medio, hacia otro sitio o una descarga, el enlace de siempre.
--}}
@php
    /** @var \App\Modules\Nucleo\ConfiguracionInicial\DefinicionPaso $abierto */
    /** @var array{clase: class-string, parametros: array<string, mixed>} $pantalla */
@endphp
<div
    class="tq-paso-abierto"
    data-paso-abierto="{{ $abierto->clave }}"
    x-data="{
        {{-- Una pantalla de adentro que redirige al guardar (una ficha nueva lleva a su ficha) sacaba de la página entera:
             si la redirección viene de adentro de un paso abierto y va a otra pantalla del sistema, se abre aquí. Se
             engancha una vez por página; sin paso abierto, no hace nada. --}}
        init() {
            if (window.tqAsistenteRedirecciones) return
            window.tqAsistenteRedirecciones = true
            window.Livewire.interceptRequest(({ request, onRedirect }) => {
                onRedirect(({ url, preventDefault }) => {
                    const paso = [...document.querySelectorAll('[data-paso-abierto]')].find((abierto) => [...request.messages].some((mensaje) => abierto.contains(mensaje.component?.el)))
                    if (! paso) return
                    const destino = new URL(url, location.href)
                    if (destino.origin !== location.origin) return
                    preventDefault()
                    const anfitrion = window.Livewire.find(paso.closest('[wire\\:id]').getAttribute('wire:id'))
                    anfitrion.abrirEnPanel(destino.pathname + destino.search).then((abierta) => { if (! abierta) location.href = url })
                })
            })
        },
        {{-- Se captura antes que la navegación de Filament. --}}
        enlace(evento) {
            const enlace = evento.target.closest('a[href]')
            if (! enlace || enlace.hasAttribute('data-tq-abre-aqui') || evento.button !== 0 || evento.ctrlKey || evento.metaKey || evento.shiftKey || evento.altKey || enlace.target === '_blank' || enlace.hasAttribute('download')) return
            const destino = new URL(enlace.href, location.href)
            if (destino.origin !== location.origin) return
            evento.preventDefault()
            evento.stopImmediatePropagation()
            $wire.abrirEnPanel(destino.pathname + destino.search).then((abierta) => { if (! abierta) location.href = enlace.href })
        },
    }"
    x-on:click.capture="enlace($event)"
>
    <style>
        .tq-asistente-empresa { display: grid; gap: 0.5rem; margin-bottom: 1.25rem; }
        .tq-asistente-empresa-titulo { font-size: 0.875rem; font-weight: 600; }
        .tq-asistente-hoja { padding: 1rem 1.25rem; border: 1px solid var(--gray-200); border-radius: 0.5rem; background: #FFFFFF; box-shadow: 0 1px 3px rgb(0 0 0 / 0.08); }
        .dark .tq-asistente-hoja { border-color: var(--gray-600); }
        .tq-asistente-faltantes { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem; }
        .tq-asistente-faltantes .tq-asistente-descripcion { display: block; font-size: 0.8125rem; color: var(--gray-500); }
        /* La pantalla de adentro sin su título, migas ni menú lateral: los pone el marco. */
        .tq-paso-abierto .fi-breadcrumbs, .tq-paso-abierto .fi-header-heading, .tq-paso-abierto .fi-header-subheading,
        .tq-paso-abierto .fi-page-sub-navigation-sidebar, .tq-paso-abierto .fi-page-sub-navigation-select,
        .tq-paso-abierto .fi-page-sub-navigation-dropdown { display: none; }
        .tq-paso-abierto .fi-page-has-sub-navigation .fi-page-main { display: block; }
        .tq-paso-abierto .fi-header { justify-content: flex-end; }
        /* Sin su título, el encabezado de la pantalla solo existe si trae botones; y sin el espacio de arriba. */
        .tq-paso-abierto .fi-header:not(:has(.fi-ac)) { display: none; }
        .tq-paso-abierto .fi-page-header-main-ctn { padding-block: 0; row-gap: 1rem; }
    </style>
    @if ($empresa !== null)
        {{-- Plan 1.15, D4: cómo verán los documentos a Techquk y lo que les falta. No impide marcar el paso. --}}
        <section class="tq-asistente-empresa" data-tq-vista-previa-empresa>
            <p class="tq-asistente-empresa-titulo">Así salen sus datos en los documentos</p>
            <div class="tq-asistente-hoja">
                @include('nucleo::empresa.encabezado', ['empresa' => $empresa, 'logo' => $logoEmpresa])
            </div>
            @if (($faltantes = $empresa->faltantes()) !== [])
                <div class="tq-asistente-faltantes" data-tq-faltantes>
                    @foreach ($faltantes as $faltante)
                        <x-filament::badge color="warning" icon="heroicon-m-exclamation-triangle">{{ $faltante }}</x-filament::badge>
                    @endforeach
                    <span class="tq-asistente-descripcion">Se completan en la ficha, aquí abajo; el logo, en «Archivos», con el tipo «Logo».</span>
                </div>
            @endif
        </section>
    @endif
    @if ($permitida)
        @livewire($pantalla['clase'], $pantalla['parametros'], key('pantalla-'.md5($abierto->clave.json_encode($pantalla))))
    @else
        <p class="tq-catalogos-nada" data-tq-sin-permiso>No tienes permiso para ver esta pantalla. Pídelo a quien administra los roles.</p>
    @endif
</div>
