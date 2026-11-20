{{--
    MV-052: el globo de Mien, pegado a la estrella de la barra lateral, a su derecha, con una puntita que la señala. Clic
    en la estrella lo abre; clic afuera, Esc u otro clic en la estrella lo cierran; Ctrl + clic (o la rueda) abre Mien en
    otra pestaña. Cada pendiente lleva a su lugar y, al saltar, el globo se cierra. Los grupos de más de cinco se
    despliegan con «Ver todos»; ninguno se omite.
--}}
<div
    x-data="{
        abierto: false,
        x: 0, y: 0, punta: 24,
        abrir(rect) {
            const alto = Math.min(window.innerHeight - 24, 520)
            this.x = rect.right + 12
            this.y = Math.max(12, Math.min(rect.top + rect.height / 2 - 32, window.innerHeight - alto - 12))
            this.punta = rect.top + rect.height / 2 - this.y
            this.abierto = true
            document.documentElement.classList.add('tq-globo-mien-abierto')
            $wire.cargar().then(() => $wire.$refresh())
        },
        cerrar() { this.abierto = false; document.documentElement.classList.remove('tq-globo-mien-abierto') },
    }"
    x-on:tq-globo-mien.window="abierto ? cerrar() : abrir($event.detail.rect)"
    x-on:keydown.escape.window="abierto && cerrar()"
    x-on:livewire:navigate.window="abierto && cerrar()"
>
    <style>
        .tq-globo { position: fixed; z-index: 45; width: 22rem; max-height: min(520px, calc(100vh - 24px)); display: flex; flex-direction: column; border-radius: 0.9rem; background: white; box-shadow: 0 16px 40px rgb(0 0 0 / 0.18), 0 0 0 1px var(--gray-200); }
        .dark .tq-globo { background: var(--gray-900); box-shadow: 0 16px 40px rgb(0 0 0 / 0.5), 0 0 0 1px var(--gray-700); }
        .tq-globo-punta { position: absolute; left: -7px; width: 14px; height: 14px; transform: rotate(45deg); background: inherit; box-shadow: -1px 1px 0 var(--gray-200); }
        .dark .tq-globo-punta { box-shadow: -1px 1px 0 var(--gray-700); }
        .tq-globo-cabeza { padding: 0.85rem 1rem 0.5rem; border-bottom: 1px solid var(--gray-100); }
        .dark .tq-globo-cabeza { border-bottom-color: var(--gray-800); }
        .tq-globo-titulo { font-weight: 800; color: {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::DORADO }}; }
        .dark .tq-globo-titulo { color: {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::DORADO_OSCURO }}; }
        .tq-globo-cuerpo { overflow-y: auto; padding: 0.5rem 1rem 0.9rem; scrollbar-width: thin; }
        .tq-globo-grupo { margin-top: 0.6rem; }
        .tq-globo-grupo-titulo { display: flex; justify-content: space-between; font-size: 0.7rem; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; color: var(--gray-500); }
        .tq-globo-item { display: block; padding: 0.3rem 0.4rem; margin-inline: -0.4rem; border-radius: 0.4rem; font-size: 0.85rem; line-height: 1.25; }
        .tq-globo-item:hover, .tq-globo-item:focus-visible { background: color-mix(in oklab, var(--primary-600) 8%, transparent); }
        .tq-globo-item small { display: block; color: var(--gray-500); font-size: 0.75rem; }
        .tq-globo-item::before { content: ''; display: inline-block; width: 0.45rem; height: 0.45rem; margin-right: 0.4rem; border-radius: 9999px; background: var(--gray-400); vertical-align: middle; }
        .tq-globo-item[data-tono="danger"]::before { background: var(--danger-500); }
        .tq-globo-item[data-tono="warning"]::before { background: var(--warning-500); }
        .tq-globo-item[data-tono="info"]::before { background: var(--info-500); }
        .tq-globo-item[data-tono="success"]::before { background: var(--success-500); }
        .tq-globo-mas { font-size: 0.8rem; color: var(--primary-600); text-decoration: underline; }
        .dark .tq-globo-mas { color: var(--primary-400); }
        html.tq-globo-mien-abierto .fi-main-sidebar [data-modulo="mien"] .fi-sidebar-item-btn { background: color-mix(in oklab, var(--dorado-400) 22%, transparent); }
    </style>
    <div class="tq-globo" x-show="abierto" x-cloak x-on:click.outside="if (! $event.target.closest('[data-modulo=mien]')) cerrar()"
        x-bind:style="`left: ${x}px; top: ${y}px`" role="dialog" aria-label="Mien resumido"
        x-transition:enter="transition ease-out duration-150 motion-reduce:transition-none" x-transition:enter-start="opacity-0 -translate-x-1" x-transition:leave="transition ease-in duration-100 motion-reduce:transition-none" x-transition:leave-end="opacity-0">
        <span class="tq-globo-punta" x-bind:style="`top: ${punta - 7}px`" aria-hidden="true"></span>
        <div class="tq-globo-cabeza">
            <div class="tq-globo-titulo">Mien</div>
            <div class="text-sm text-gray-500">{{ $resumen['resumen'] ?? 'Cargando…' }}</div>
        </div>
        <div class="tq-globo-cuerpo" x-on:click="if ($event.target.closest('a')) cerrar()">
            @if ($resumen !== null && $grupos === [])
                <p class="mt-2 text-sm text-gray-500">Nada pendiente. Todo al día.</p>
            @endif
            @foreach ($grupos as $g)
                <section class="tq-globo-grupo" x-data="{ todos: false }">
                    <div class="tq-globo-grupo-titulo"><span>{{ $g['titulo'] }}</span><span>{{ count($g['items']) }}</span></div>
                    @foreach ($g['items'] as $n => $i)
                        <a href="{{ $i['url'] }}" wire:navigate class="tq-globo-item" @if ($i['tono']) data-tono="{{ $i['tono'] }}" @endif @if ($n >= 5) x-show="todos" x-cloak @endif>
                            {{ $i['texto'] }}@if ($i['nota'])<small>{{ $i['nota'] }}</small>@endif
                        </a>
                    @endforeach
                    @if (count($g['items']) > 5)
                        <button type="button" class="tq-globo-mas" x-show="! todos" x-on:click="todos = true">Ver los {{ count($g['items']) }}</button>
                    @endif
                </section>
            @endforeach
        </div>
        <div class="border-t border-gray-100 px-4 py-2 text-right dark:border-gray-800">
            <a href="/mien" wire:navigate class="tq-globo-mas" x-on:click="cerrar()">Abrir Mien completo</a>
        </div>
    </div>
</div>
