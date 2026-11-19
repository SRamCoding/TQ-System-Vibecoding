{{--
    Pestaña «Cuenta comercial» en la ficha de Personas y empresas (plan 2.20, D13). Recibe `$cuenta` (ResumenCuenta o
    nulo), `$tipo` («Pública · nivel» o «Privada»), `$motivo` (si está inactiva), `$urlCuenta`, `$urlCrear` y
    `$fichaActiva`.
--}}
<div class="tq-cuenta-comercial">
    @if ($cuenta !== null)
        <x-filament::section heading="Es cuenta del CRM" icon="heroicon-o-building-storefront" compact>
            <dl class="grid gap-3 sm:grid-cols-2 text-sm">
                <div><dt class="text-gray-500">Ejecutivo</dt><dd>{{ $cuenta->ejecutivoNombre }}</dd></div>
                <div><dt class="text-gray-500">Cliente</dt><dd>{{ $cuenta->clienteDesde === null ? 'Prospecto' : 'Cliente desde '.$cuenta->clienteDesde->format('d/m/Y') }}</dd></div>
                <div><dt class="text-gray-500">Tipo</dt><dd>{{ $tipo }}</dd></div>
                @if ($cuenta->motivoNoUsable !== null)
                    <div><dt class="text-gray-500">Estado</dt><dd>{{ $cuenta->motivoNoUsable->etiqueta() }}{{ $motivo === null ? '' : ' · '.$motivo }}</dd></div>
                @endif
            </dl>
            <div class="mt-4">
                <x-filament::button tag="a" :href="$urlCuenta" icon="heroicon-o-arrow-top-right-on-square">Abrir la cuenta</x-filament::button>
            </div>
        </x-filament::section>
    @else
        <x-filament::section heading="Esta empresa no es cuenta del CRM" icon="heroicon-o-building-storefront" compact>
            @if ($urlCrear !== null)
                <x-filament::button tag="a" :href="$urlCrear" icon="heroicon-o-plus">Crear cuenta</x-filament::button>
            @elseif (! $fichaActiva)
                <p class="text-sm text-gray-500">La ficha está inactiva: reactívala antes de crear su cuenta.</p>
            @endif
        </x-filament::section>
    @endif
</div>
