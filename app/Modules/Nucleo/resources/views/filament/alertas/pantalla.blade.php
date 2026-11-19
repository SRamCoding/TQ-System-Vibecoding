{{--
    Configuración → Alertas (plan 1.10, D7). Las cajas de las alertas, agrupadas por su grupo de avisos, con un buscador
    que las filtra sin acentos ni mayúsculas; con una abierta, su configuración en un panel que se desliza desde la
    derecha **encima** de las cajas (el mismo de Catálogos: `partials.cajas-con-panel`). Sin recargar: abrir y cerrar
    pasan por Livewire; cada caja sigue siendo un enlace (`?alerta=…`). Una alerta cuyos roles no tienen a nadie activo
    va marcada en rojo: hoy le llega al administrador (D2). Los botones «+ dato» insertan su marcador donde estaba el
    cursor, en el asunto o en el texto.
--}}
@php
    /** @var list<array{nombre: string, alertas: list<\App\Modules\Nucleo\Alertas\DefinicionAlerta>}> $grupos */
    /** @var \App\Modules\Nucleo\Alertas\DefinicionAlerta|null $abierta */
    /** @var list<string> $sinNadie */
    $normalizar = fn (string $texto): string => \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii($texto));
    $sinNadieTexto = 'Nadie tiene los roles de esta alerta: por ahora le llega al administrador.';
@endphp
<div class="tq-catalogos">
    @include('nucleo::filament.partials.cajas-con-panel')
    <style>
        .tq-alerta-panel { width: min(100%, 40rem); }
        .tq-alerta-sin-nadie { border-top-color: var(--danger-600) !important; }
        .tq-alerta-sin-nadie-marca { font-size: 0.75rem; font-weight: 600; color: var(--danger-600); }
        .dark .tq-alerta-sin-nadie-marca { color: var(--danger-400); }
        .tq-alerta-aviso { margin-bottom: 1rem; padding: 0.75rem 1rem; border-radius: 0.5rem; font-size: 0.875rem; color: var(--danger-700); background: var(--danger-50); }
        .dark .tq-alerta-aviso { color: var(--danger-300); background: rgb(127 29 29 / 0.25); }
        .tq-alerta-descripcion { margin-bottom: 1rem; font-size: 0.875rem; color: var(--gray-600); }
        .dark .tq-alerta-descripcion { color: var(--gray-400); }
    </style>
    <script>
        // El último campo de texto de la alerta que tuvo el foco, y cómo insertarle un dato donde estaba el cursor.
        document.addEventListener('focusin', (e) => { if (e.target.matches?.('[data-tq-texto-alerta]')) window.tqCampoAlerta = e.target })
        window.tqInsertarDatoAlerta = (marcador) => {
            const campo = window.tqCampoAlerta?.isConnected ? window.tqCampoAlerta : document.querySelector('textarea[data-tq-texto-alerta]')
            if (! campo) return
            campo.setRangeText(marcador, campo.selectionStart ?? campo.value.length, campo.selectionEnd ?? campo.value.length, 'end')
            campo.dispatchEvent(new Event('input', { bubbles: true }))
            campo.focus()
        }
    </script>

    <div @class(['tq-catalogos-detras' => $abierta !== null]) x-data="{ q: '', normalizar: (t) => t.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim() }">
        <x-filament::input.wrapper class="tq-catalogos-buscar" prefix-icon="heroicon-o-magnifying-glass">
            <x-filament::input type="search" x-model="q" placeholder="Buscar alerta…" aria-label="Buscar alerta" />
        </x-filament::input.wrapper>

        @foreach ($grupos as $grupo)
            <section class="tq-catalogos-grupo" x-show="[...$el.querySelectorAll('[data-buscar]')].some((c) => c.dataset.buscar.includes(normalizar(q)))">
                <h2 class="tq-catalogos-grupo-titulo">{{ $grupo['nombre'] }}</h2>
                <div class="tq-catalogos-cajas">
                    @foreach ($grupo['alertas'] as $alerta)
                        @php($vacia = in_array($alerta->clave, $sinNadie, true))
                        <a
                            href="{{ \App\Modules\Nucleo\Filament\Pages\Alertas::getUrl(['alerta' => $alerta->clave]) }}"
                            @class(['fi-section tq-caja-catalogo tq-caja-sub', 'tq-alerta-sin-nadie' => $vacia])
                            data-alerta="{{ $alerta->clave }}"
                            data-tq-abre-aqui
                            data-buscar="{{ $normalizar($alerta->nombre.' '.$alerta->descripcion) }}"
                            x-show="$el.dataset.buscar.includes(normalizar(q))"
                            x-on:click="if ($event.button === 0 && ! ($event.ctrlKey || $event.metaKey || $event.shiftKey || $event.altKey)) { $event.preventDefault(); $wire.abrir(@js($alerta->clave)) }"
                            x-tooltip="{ content: @js($vacia ? $sinNadieTexto : $alerta->descripcion), theme: $store.theme }"
                            aria-description="{{ $vacia ? $sinNadieTexto : $alerta->descripcion }}"
                        >
                            <x-filament::icon :icon="$alerta->icono" class="tq-caja-catalogo-icono tq-caja-sub-icono" />
                            <span class="tq-caja-catalogo-nombre">{{ $alerta->nombre }}</span>
                            @if ($vacia)
                                <span class="tq-alerta-sin-nadie-marca">Sin nadie</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach

        <p class="tq-catalogos-nada" role="status" x-cloak x-show="! [...$root.querySelectorAll('[data-buscar]')].some((c) => c.dataset.buscar.includes(normalizar(q)))">
            Ninguna alerta coincide.
        </p>
    </div>

    @if ($abierta !== null)
        <section
            class="tq-catalogo-panel tq-alerta-panel"
            wire:key="panel-{{ $abierta->clave }}"
            aria-labelledby="tq-catalogo-panel-titulo"
            data-alerta-abierta="{{ $abierta->clave }}"
            x-data="{
                saliendo: false,
                cerrar() {
                    if (this.saliendo) return
                    this.saliendo = true
                    const quieto = window.matchMedia('(prefers-reduced-motion: reduce)').matches || document.documentElement.classList.contains('tq-sin-animaciones')
                    setTimeout(() => $wire.cerrar(), quieto ? 0 : 190)
                },
            }"
            x-bind:class="{ 'tq-saliendo': saliendo }"
            x-init="$nextTick(() => $el.querySelector('#tq-catalogo-panel-titulo')?.focus())"
            x-on:keydown.escape.window="if (! $event.defaultPrevented && ! $event.target.closest('input, textarea, select, [contenteditable]') && ! [...document.querySelectorAll('.fi-modal-open, [aria-modal=true], .fi-dropdown-panel')].some((capa) => capa.getClientRects().length)) cerrar()"
        >
            <header class="tq-catalogo-panel-encabezado">
                <h2 id="tq-catalogo-panel-titulo" class="tq-catalogo-panel-titulo" tabindex="-1">
                    <x-filament::icon :icon="$abierta->icono" class="h-6 w-6" />
                    {{ $abierta->nombre }}
                </h2>
                <x-filament::icon-button icon="heroicon-o-x-mark" color="gray" label="Cerrar y volver a las alertas" x-on:click="cerrar()" />
            </header>
            <p class="tq-alerta-descripcion">{{ $abierta->descripcion }}</p>
            @if (in_array($abierta->clave, $sinNadie, true))
                <p class="tq-alerta-aviso" role="status">{{ $sinNadieTexto }} Asígnale uno de sus roles a alguien en Usuarios y organigrama.</p>
            @endif
            {{ $this->form }}
        </section>
    @endif
</div>
