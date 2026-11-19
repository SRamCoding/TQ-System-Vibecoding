{{--
    Una aplicación de la cuadrícula del panel (plan 1.4p; plan 1.4x, D2). Se arrastra sobre uno de los 3 lugares de «Tus
    3 principales» o sobre otra de su sección, desde cualquier parte de la caja y sin espera (plan 1.4aa, D1: Pointer
    Events; si no se movió, es un clic y la abre); en modo edición, la de abajo tiene «Poner arriba» (al 3.er lugar, arriba a la izquierda), la de Techquk
    «Ocultar» (arriba a la derecha) y la propia «Cambiar» (abajo a la izquierda) y «Quitar». Ningún botón tapa el ícono.
    Recibe `$aplicacion`, `$principal` (si está arriba) e `$icono`.
--}}
@php($origen = $aplicacion->origen->value)
<div
    class="tq-apps-app"
    x-show="coincide(@js($aplicacion->nombre))"
    data-tq-arrastra
    x-on:pointerdown="presionar($event, @js($origen), {{ $aplicacion->id }}, @js($principal ? 'principales' : $origen))"
    x-on:dragstart.prevent
    @unless ($principal)
        {{-- Plan 1.4z, D10: recibe otra de su sección y cambian de lugar. --}}
        data-tq-destino="{{ $origen }}"
        data-tq-valor="{{ $aplicacion->id }}"
        x-bind:class="sobre === @js($origen.'-'.$aplicacion->id) && 'tq-apps-sobre'"
    @endunless
    wire:key="{{ $principal ? 'principal' : $origen }}-{{ $aplicacion->id }}"
    data-tq-app="{{ $origen }}-{{ $aplicacion->id }}"
>
    {{-- Plan 1.4z, D6: al programa de escritorio si la persona lo eligió (sin pestaña nueva: el programa se abre solo). --}}
    <a href="{{ $aplicacion->destino() }}" @unless ($aplicacion->enEscritorio) target="_blank" @endunless rel="noopener noreferrer" class="tq-apps-enlace" draggable="false" x-on:click="$wire.abrir(@js($origen), {{ $aplicacion->id }})" aria-label="{{ $aplicacion->nombre }} {{ $aplicacion->enEscritorio ? '(se abre en el programa de escritorio)' : '(se abre en otra pestaña)' }}">
        {{ $icono($aplicacion) }}
        <span>{{ $aplicacion->nombre }}</span>
    </a>
    @if ($aplicacion->escritorio !== null)
        {{-- Plan 1.4z, D6: abrirla en el escritorio o en la web, en modo edición (abajo a la derecha). --}}
        <button type="button" style="right: 0.1rem; top: auto; bottom: 1.5rem" x-show="editando" x-cloak x-on:click="error = await $wire.abrirEn(@js($origen), {{ $aplicacion->id }}, {{ $aplicacion->enEscritorio ? 'false' : 'true' }})" aria-label="{{ $aplicacion->enEscritorio ? 'Abrir '.$aplicacion->nombre.' en la web' : 'Abrir '.$aplicacion->nombre.' en el escritorio' }}" title="{{ $aplicacion->enEscritorio ? 'Abrir en la web' : 'Abrir en el escritorio' }}" data-tq-abrir-en="{{ $aplicacion->enEscritorio ? 'web' : 'escritorio' }}">
            <x-filament::icon :icon="$aplicacion->enEscritorio ? 'heroicon-m-globe-alt' : 'heroicon-m-computer-desktop'" class="h-3 w-3" />
        </button>
    @endif
    @unless ($principal)
        <button type="button" style="left: 0.1rem" x-show="editando" x-cloak x-on:click="ponerArriba(@js($origen), {{ $aplicacion->id }}, 3)" aria-label="Poner {{ $aplicacion->nombre }} entre tus 3 principales" title="Poner arriba">
            <x-filament::icon icon="heroicon-m-arrow-up" class="h-3 w-3" />
        </button>
    @endunless
    @if ($origen === 'techquk')
        <button type="button" style="right: 0.1rem" x-show="editando" x-cloak x-on:click="error = await $wire.ocultar({{ $aplicacion->id }})" aria-label="Ocultar {{ $aplicacion->nombre }}" title="Ocultar">
            <x-filament::icon icon="heroicon-m-eye-slash" class="h-3 w-3" />
        </button>
    @else
        <button type="button" style="left: 0.1rem; top: auto; bottom: 1.5rem" x-show="editando" x-cloak x-on:click="editar(@js(['id' => $aplicacion->id, 'nombre' => $aplicacion->nombre, 'enlace' => $aplicacion->enlace, 'icono' => $aplicacion->icono->value, 'escritorio' => $aplicacion->escritorio?->value ?? '', 'version' => $aplicacion->version]))" aria-label="Cambiar {{ $aplicacion->nombre }}" title="Cambiar">
            <x-filament::icon icon="heroicon-m-pencil" class="h-3 w-3" />
        </button>
        <button type="button" style="right: 0.1rem" x-show="editando" x-cloak x-on:click="quitar({{ $aplicacion->id }}, {{ $aplicacion->version }}, @js($aplicacion->nombre))" aria-label="Quitar {{ $aplicacion->nombre }}" title="Quitar">
            <x-filament::icon icon="heroicon-m-x-mark" class="h-3 w-3" />
        </button>
    @endif
</div>
