{{--
    La ventana para cambiar una pieza decorativa (plan 1.4i, D3): todas sus variantes, agrupadas por fecha o estación.
    Se abre con un clic sobre la pieza en la página (`data-tq-pieza`) o desde la barra. Recibe `$abierta` (PiezaDecorativa
    o null) y `$elegida` (el valor guardado, o null).
--}}
@php /** @var \App\Modules\Nucleo\Desarrollo\PiezaDecorativa|null $abierta */ @endphp
@if ($abierta !== null)
    <div class="tq-dev-ventana" role="dialog" aria-modal="true" aria-labelledby="tq-dev-ventana-titulo" x-data x-trap.noscroll="true" x-on:keydown.escape.window="$wire.cerrar()">
        <form wire:submit="aplicar" class="tq-dev-ventana-caja">
            <h2 id="tq-dev-ventana-titulo" class="tq-dev-ventana-titulo">{{ $abierta->nombre() }}</h2>
            <label for="tq-dev-opcion" class="tq-dev-ventana-ayuda">Elige cualquier variante, de cualquier fecha:</label>
            <select id="tq-dev-opcion" wire:model="opcion" size="10">
                @foreach ($abierta->opciones() as $grupo => $variantes)
                    <optgroup label="{{ $grupo }}">
                        @foreach ($variantes as $valor => $nombre)
                            <option value="{{ $valor }}">{{ $nombre }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
            <div class="tq-dev-ventana-botones">
                <button type="submit">Aplicar</button>
                @if ($elegida !== null)
                    <button type="button" wire:click="quitar">Quitar (volver a la de la fecha)</button>
                @endif
                <button type="button" wire:click="cerrar">Cancelar</button>
            </div>
        </form>
    </div>
@endif
