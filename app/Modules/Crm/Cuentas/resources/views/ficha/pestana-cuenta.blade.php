{{-- MV-022: una pestaña de la cuenta dentro de la ficha de la persona o empresa. --}}
<div>
    @if ($cuenta === null || $pestana === null)
        <p class="text-sm text-gray-500">Esta empresa no es cuenta del CRM: créala desde «Cuenta comercial».</p>
    @else
        @livewire($pestana->nombreLivewire(), ['cuentaId' => $cuenta->id], key('ficha-cuenta-'.$pestana->clave.'-'.$cuenta->id))
    @endif
</div>
