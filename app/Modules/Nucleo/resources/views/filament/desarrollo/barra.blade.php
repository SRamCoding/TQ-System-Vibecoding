{{--
    La barra del modo desarrollo (plan 1.4i, D5): fija al pie, delgada, sin tapar el contenido (la página reserva su
    alto). Solo se dibuja con el modo prendido (D7).
--}}
@php
    /** @var \Carbon\CarbonImmutable|null $simulada */
    /** @var list<\App\Modules\Nucleo\Desarrollo\PiezaDecorativa> $piezas */
@endphp
{{-- Un clic sobre una pieza marcada en la página abre su ventana (y no hace lo de siempre, como ir al inicio). --}}
<div
    class="tq-dev"
    role="region"
    aria-label="Modo desarrollo"
    x-data
    {{-- La página reserva el alto real de la barra (en pantallas angostas ocupa dos o tres líneas). --}}
    x-init="new ResizeObserver(() => document.body.style.paddingBottom = $el.offsetHeight + 'px').observe($el); document.addEventListener('click', (e) => { const p = e.target.closest('[data-tq-pieza]'); if (p) { e.preventDefault(); e.stopPropagation(); $wire.abrir(p.dataset.tqPieza) } }, true)"
>
    <style>
        .tq-dev { position: fixed; inset-inline: 0; bottom: 0; z-index: 40; display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem 1rem; min-height: 2.75rem; padding: 0.375rem 1rem; font-size: 0.8125rem; color: #fff; background: #3f2a00; border-top: 2px solid {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::DORADO_OSCURO }}; }
        .tq-dev-titulo { font-weight: 700; letter-spacing: 0.02em; }
        .tq-dev input { padding: 0.125rem 0.375rem; border-radius: 0.25rem; color: #111; background: #fff; color-scheme: light; }
        .tq-dev button, .tq-dev a { padding: 0.125rem 0.5rem; border-radius: 0.25rem; text-decoration: underline; text-underline-offset: 2px; }
        .tq-dev button:hover, .tq-dev a:hover { background: rgb(255 255 255 / 0.12); }
        .tq-dev-estaciones { display: inline-flex; flex-wrap: wrap; align-items: center; gap: 0.25rem; }
        .tq-dev .tq-dev-elegida { color: #3f2a00; background: {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::DORADO_OSCURO }}; text-decoration: none; }
        .tq-dev-simulada { color: {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::DORADO_OSCURO }}; }
        [data-tq-pieza] { cursor: pointer; outline: 1px dashed {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::DORADO_OSCURO }}; outline-offset: 3px; border-radius: 4px; }
        .tq-dev-ventana { position: fixed; inset: 0; z-index: 60; display: grid; place-items: center; background: rgb(0 0 0 / 0.45); }
        .tq-dev-ventana-caja { display: flex; flex-direction: column; gap: 0.75rem; width: min(28rem, calc(100vw - 2rem)); padding: 1.25rem; border-radius: 0.75rem; color: #111; background: #fff; box-shadow: 0 10px 30px rgb(0 0 0 / 0.3); }
        .dark .tq-dev-ventana-caja { color: #f4f4f5; background: #18181b; }
        .tq-dev-ventana-titulo { font-size: 1rem; font-weight: 700; }
        .tq-dev-ventana-ayuda { font-size: 0.875rem; color: #52525b; }
        .dark .tq-dev-ventana-ayuda { color: #a1a1aa; }
        .tq-dev-ventana select { width: 100%; padding: 0.25rem; border: 1px solid #d4d4d8; border-radius: 0.375rem; color: inherit; background: transparent; }
        .tq-dev-ventana-botones { display: flex; flex-wrap: wrap; gap: 0.5rem; }
        .tq-dev-ventana-botones button { padding: 0.25rem 0.75rem; border: 1px solid #d4d4d8; border-radius: 0.375rem; text-decoration: none; }
        .tq-dev-ventana-botones button[type="submit"] { color: #fff; background: #01006F; border-color: #01006F; }
    </style>
    <span class="tq-dev-titulo">⚙ Modo desarrollo</span>
    <form wire:submit="simular" class="flex items-center gap-2">
        <label for="tq-dev-fecha">Fecha simulada</label>
        <input id="tq-dev-fecha" type="datetime-local" wire:model="fecha" />
        <button type="submit">Aplicar</button>
    </form>
    @if ($simulada !== null)
        <span class="tq-dev-simulada">Se ve como el {{ $simulada->translatedFormat('j \d\e F \d\e Y, H:i') }}</span>
        <button type="button" wire:click="volverAHoy">Volver a hoy</button>
    @endif
    <span>Cambiar:</span>
    @foreach ($piezas as $pieza)
        <button type="button" wire:click="abrir(@js($pieza->clave()))">{{ $pieza->nombre() }}</button>
    @endforeach
    {{-- Plan 1.4r, D6: la estación de las cajas del inicio, con un clic. --}}
    <span class="tq-dev-estaciones" role="group" aria-label="Estación de las cajas">
        <span>Estación:</span>
        @foreach ($estaciones as $valor => $nombre)
            <button type="button" wire:click="elegirEstacion(@js($valor))" @if ($estacionElegida === $valor) aria-pressed="true" class="tq-dev-elegida" @endif>{{ $nombre }}</button>
        @endforeach
        <button type="button" wire:click="elegirEstacion(null)" @if ($estacionElegida === null) aria-pressed="true" class="tq-dev-elegida" @endif>Por fecha</button>
    </span>
    <a href="{{ $vistaPrevia }}">Vista previa</a>
    <button type="button" wire:click="apagar">Apagar</button>
    @include('nucleo::filament.desarrollo.selector', ['abierta' => $abierta, 'elegida' => $elegida])
</div>
