{{--
    La bienvenida de la configuración inicial (plan 1.13b, D3–D5): una ventana grande al centro, encima de la pantalla
    atenuada; en el celular, a pantalla completa. Arriba, el título, «Paso N de M · Título» y un punto por paso (verde si
    está listo; cada uno lleva a su paso); en medio, la pantalla de siempre del paso (`paso-abierto`, común con el panel
    de Configuración inicial); abajo, «Seguir después desde Configuración», «Anterior», «Saltar» y «Listo, siguiente».
    Después del último paso, el cierre: lo hecho y lo que falta. Es un diálogo: el foco queda atrapado dentro; la ✕ o Esc
    la cierran. Quieta con «reducir movimiento» o la preferencia «reducir animaciones».
--}}
@php
    /** @var bool $cerrada */
@endphp
<div>
    @unless ($cerrada)
        @php
            /** @var list<array{definicion: \App\Modules\Nucleo\ConfiguracionInicial\DefinicionPaso, listo: bool}> $pasos */
            /** @var list<\App\Modules\Nucleo\ConfiguracionInicial\DefinicionPaso> $faltan */
            /** @var ?\App\Modules\Nucleo\ConfiguracionInicial\DefinicionPaso $abierto */
            $total = count($pasos);
        @endphp
        <style>
            .tq-bienvenida-fondo { position: fixed; inset: 0; z-index: 45; display: flex; align-items: center; justify-content: center; padding: 2rem 1rem; background: rgb(3 7 18 / 0.55); animation: tq-bienvenida-aparece 200ms ease-out both; }
            .tq-bienvenida { display: flex; flex-direction: column; width: min(100%, 72rem); min-width: 0; height: min(100%, 52rem); overflow: hidden; border-radius: 1rem; background: var(--color-white, #fff); box-shadow: 0 24px 64px rgb(0 0 0 / 0.3); animation: tq-bienvenida-entra 220ms ease-out both; }
            .dark .tq-bienvenida { background: var(--gray-900); box-shadow: 0 24px 64px rgb(0 0 0 / 0.6); }
            .tq-bienvenida-encabezado { display: grid; gap: 0.75rem; padding: 1.25rem 1.5rem 1rem; border-bottom: 1px solid var(--gray-200); }
            .dark .tq-bienvenida-encabezado, .dark .tq-bienvenida-pie { border-color: var(--gray-700); }
            .tq-bienvenida-arriba { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; }
            .tq-bienvenida-titulo { min-width: 0; font-size: 1.375rem; font-weight: 700; line-height: 1.3; }
            .tq-bienvenida-titulo:focus { outline: none; }
            .tq-bienvenida-paso { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.5rem 1rem; }
            .tq-bienvenida-cual { display: flex; align-items: center; gap: 0.5rem; font-weight: 600; color: var(--gray-700); }
            .dark .tq-bienvenida-cual { color: var(--gray-200); }
            .tq-bienvenida-cual .fi-icon { color: var(--primary-600); }
            .dark .tq-bienvenida-cual .fi-icon { color: #FFFFFF; }
            .tq-bienvenida-herramientas { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem; }
            .tq-bienvenida-puntos { display: flex; flex-wrap: wrap; gap: 0.5rem; margin: 0; padding: 0; list-style: none; }
            .tq-bienvenida-punto { display: block; width: 0.875rem; height: 0.875rem; padding: 0; border: 2px solid var(--gray-400); border-radius: 9999px; background: transparent; cursor: pointer; transition: transform 150ms ease-out; }
            .tq-bienvenida-punto:hover, .tq-bienvenida-punto:focus-visible { transform: scale(1.25); }
            .tq-bienvenida-punto:focus-visible { outline: 2px solid var(--primary-600); outline-offset: 2px; }
            .tq-bienvenida-punto[data-listo="1"] { border-color: var(--success-600); background: var(--success-600); }
            .tq-bienvenida-punto[aria-current="step"] { box-shadow: 0 0 0 3px var(--primary-200); border-color: var(--primary-600); }
            .dark .tq-bienvenida-punto[aria-current="step"] { box-shadow: 0 0 0 3px rgb(255 255 255 / 0.35); border-color: #FFFFFF; }
            .tq-bienvenida-cuerpo { flex: 1; min-height: 0; overflow-y: auto; padding: 1.25rem 1.5rem; }
            .tq-bienvenida-pie { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.75rem; padding: 0.875rem 1.5rem; border-top: 1px solid var(--gray-200); }
            .tq-bienvenida-avanzar { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem; margin-left: auto; }
            .tq-bienvenida-cierre { display: grid; gap: 1.5rem; max-width: 44rem; }
            .tq-bienvenida-cierre h3 { margin-bottom: 0.5rem; font-weight: 700; }
            .tq-bienvenida-lista { display: grid; gap: 0.5rem; margin: 0; padding: 0; list-style: none; }
            .tq-bienvenida-lista li { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
            .tq-bienvenida-lista span { display: flex; align-items: center; gap: 0.5rem; }
            .tq-bienvenida-hecho .fi-icon { color: var(--success-600); }
            .tq-bienvenida-cuerpo .tq-catalogos-nada { font-size: 0.875rem; color: var(--gray-500); }
            @keyframes tq-bienvenida-aparece { from { opacity: 0; } }
            @keyframes tq-bienvenida-entra { from { opacity: 0; transform: translateY(12px) scale(0.98); } }
            @media (max-width: 640px) {
                .tq-bienvenida-fondo { padding: 0; }
                .tq-bienvenida { width: 100%; height: 100%; border-radius: 0; }
                .tq-bienvenida-encabezado, .tq-bienvenida-cuerpo, .tq-bienvenida-pie { padding-inline: 1rem; }
                .tq-bienvenida-titulo { font-size: 1.125rem; }
            }
            @media (prefers-reduced-motion: reduce) { .tq-bienvenida-fondo, .tq-bienvenida { animation: none; } .tq-bienvenida-punto { transition: none; } }
            html.tq-sin-animaciones .tq-bienvenida-fondo, html.tq-sin-animaciones .tq-bienvenida { animation: none; }
            html.tq-sin-animaciones .tq-bienvenida-punto { transition: none; }
        </style>

        <div
            class="tq-bienvenida-fondo"
            data-tq-bienvenida
            x-data="{ abierta: true, cerrar() { this.abierta = false; $wire.cerrar() } }"
            x-show="abierta"
            x-trap.noscroll="abierta"
            x-on:keydown.escape.window="if (abierta && ! $event.defaultPrevented && ! $el.querySelector('.tq-catalogo-panel') && ! $event.target.closest('input, textarea, select, [contenteditable]') && ! [...document.querySelectorAll('.fi-modal-open, .fi-dropdown-panel')].some((capa) => capa.getClientRects().length)) cerrar()"
        >
            <section class="tq-bienvenida" role="dialog" aria-modal="true" aria-labelledby="tq-bienvenida-titulo">
                <header class="tq-bienvenida-encabezado">
                    <div class="tq-bienvenida-arriba">
                        {{-- El foco va al título al abrir y en cada paso: el lector de pantalla dice dónde está. --}}
                        <h2 id="tq-bienvenida-titulo" class="tq-bienvenida-titulo" tabindex="-1" wire:key="titulo-{{ $paso ?? 'cierre' }}" x-init="$nextTick(() => $el.focus())">Te damos la bienvenida. Configuremos Techquk</h2>
                        <x-filament::icon-button icon="heroicon-o-x-mark" color="gray" label="Cerrar hasta el próximo ingreso" x-on:click="cerrar()" />
                    </div>
                    <div class="tq-bienvenida-paso">
                        <p class="tq-bienvenida-cual" data-tq-paso-actual>
                            @if ($abierto !== null)
                                @if ($enFicha)
                                    <x-filament::icon-button icon="heroicon-o-arrow-left" color="gray" label="Volver a {{ $abierto->titulo }}" wire:click="volver" data-volver />
                                @endif
                                <x-filament::icon :icon="$abierto->icono" class="h-5 w-5" />
                                Paso {{ $numero }} de {{ $total }} · {{ $abierto->titulo }}
                            @else
                                <x-filament::icon icon="heroicon-o-flag" class="h-5 w-5" />
                                {{ $faltan === [] ? 'Todo listo' : 'Lo que falta' }}
                            @endif
                        </p>
                        @if ($abierto !== null)
                            <div class="tq-bienvenida-herramientas">
                                @if ($abierto->clave === 'nucleo.empresa')
                                    {{ $this->elegirFichaAction }}
                                @endif
                                @if ($abiertoListo)
                                    <x-filament::badge color="success" icon="heroicon-m-check-circle">Listo</x-filament::badge>
                                    {{ ($this->reabrirAction)(['paso' => $abierto->clave]) }}
                                @elseif (! $abierto->obligatorio)
                                    <x-filament::badge color="gray">Opcional</x-filament::badge>
                                @endif
                            </div>
                        @endif
                    </div>
                    <ol class="tq-bienvenida-puntos" aria-label="Pasos">
                        @foreach ($pasos as $i => ['definicion' => $definicion, 'listo' => $listo])
                            <li>
                                <button
                                    type="button"
                                    class="tq-bienvenida-punto"
                                    data-listo="{{ $listo ? 1 : 0 }}"
                                    data-tq-punto="{{ $definicion->clave }}"
                                    @if ($definicion->clave === $paso) aria-current="step" @endif
                                    aria-label="Paso {{ $i + 1 }}: {{ $definicion->titulo }}{{ $listo ? ', listo' : '' }}"
                                    title="{{ $definicion->titulo }}{{ $listo ? ' · listo' : '' }}"
                                    wire:click="ir(@js($definicion->clave))"
                                ></button>
                            </li>
                        @endforeach
                    </ol>
                </header>

                <div class="tq-bienvenida-cuerpo">
                    @if ($abierto !== null && $pantalla !== null)
                        <div wire:key="bienvenida-{{ $abierto->clave }}">
                            @include('nucleo::filament.configuracion-inicial.paso-abierto')
                        </div>
                    @else
                        {{-- D5: lo hecho y lo que falta; cada pendiente lleva a su paso. --}}
                        <div class="tq-bienvenida-cierre" data-tq-bienvenida-cierre>
                            <p>{{ $faltan === [] ? 'Los pasos obligatorios están listos. Lo que quieras cambiar después está en Configuración → Configuración inicial.' : 'Lo que falta queda en Configuración → Configuración inicial; esta ventana vuelve en tu próximo ingreso mientras falte algo.' }}</p>
                            @if ($faltan !== [])
                                <section>
                                    <h3>Falta</h3>
                                    <ul class="tq-bienvenida-lista" data-tq-faltan>
                                        @foreach ($faltan as $pendiente)
                                            <li>
                                                <span><x-filament::icon :icon="$pendiente->icono" class="h-5 w-5" />{{ $pendiente->titulo }}</span>
                                                <x-filament::button size="sm" color="gray" wire:click="ir(@js($pendiente->clave))">Ir</x-filament::button>
                                            </li>
                                        @endforeach
                                    </ul>
                                </section>
                            @endif
                            @if (($hechos = array_filter($pasos, fn (array $fila): bool => $fila['listo'])) !== [])
                                <section>
                                    <h3>Hecho</h3>
                                    <ul class="tq-bienvenida-lista tq-bienvenida-hecho" data-tq-hechos>
                                        @foreach ($hechos as ['definicion' => $hecho])
                                            <li><span><x-filament::icon icon="heroicon-m-check-circle" class="h-5 w-5" />{{ $hecho->titulo }}</span></li>
                                        @endforeach
                                    </ul>
                                </section>
                            @endif
                        </div>
                    @endif
                </div>

                <footer class="tq-bienvenida-pie">
                    @if ($faltan !== [])
                        {{ $this->seguirDespuesAction }}
                    @endif
                    <div class="tq-bienvenida-avanzar">
                        @if ($numero !== 1)
                            <x-filament::button color="gray" icon="heroicon-m-arrow-left" wire:click="anterior">Anterior</x-filament::button>
                        @endif
                        @if ($abierto === null)
                            <x-filament::button wire:click="irAlInicio" data-tq-ir-al-inicio>Ir al inicio</x-filament::button>
                        @elseif ($abiertoListo)
                            <x-filament::button icon="heroicon-m-arrow-right" icon-position="after" wire:click="siguiente">Siguiente</x-filament::button>
                        @else
                            <x-filament::button color="gray" wire:click="siguiente">Saltar</x-filament::button>
                            {{ $this->listoSiguienteAction }}
                        @endif
                    </div>
                </footer>
            </section>
            {{-- Dentro de la capa: el foco atrapado debe poder entrar a la ventana de una acción. --}}
            <x-filament-actions::modals />
        </div>
    @endunless
</div>
