{{-- La tarjeta «Mis aprobaciones» de «Mien» (plan 2.22, P6). --}}
<div data-tq-mis-aprobaciones>
    @if ($cuantas === 0)
        <p class="text-sm text-gray-500 dark:text-gray-400">No tienes nada por firmar.</p>
    @else
        <p class="text-sm">
            <span class="text-2xl font-semibold">{{ $cuantas }}</span>
            {{ $cuantas === 1 ? 'documento espera tu firma.' : 'documentos esperan tu firma.' }}
        </p>
    @endif
    <p class="mt-2 text-sm"><a href="{{ $enlace }}" wire:navigate class="fi-link">Ver mis aprobaciones</a></p>
</div>
