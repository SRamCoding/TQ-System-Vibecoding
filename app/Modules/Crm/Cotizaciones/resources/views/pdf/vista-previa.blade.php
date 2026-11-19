{{--
    La vista previa del PDF en la ficha (D20): el PDF va como base64 y Alpine lo convierte en un `blob:` para el visor del
    navegador (un `data:` de PDF no se abre en un iframe en todos). El guardado al emitir se puede descargar; el borrador,
    no: no es lo que se envía.
--}}
@php
    /** @var string $base64 */
    /** @var ?string $descarga */
@endphp
<div class="space-y-3" data-tq-vista-previa-pdf>
    <p class="text-sm text-gray-600 dark:text-gray-300">
        @if ($descarga !== null)
            El PDF que se guardó al emitir: es lo que recibió el cliente y no cambia.
            <a href="{{ $descarga }}" class="font-medium text-primary-600 underline dark:text-primary-400">Descargar</a>
        @else
            Así saldría el PDF con los datos de ahora. Se guarda al emitir.
        @endif
    </p>
    <div
        x-data="{ url: null }"
        x-init="
            const bytes = Uint8Array.from(atob(@js($base64)), (c) => c.charCodeAt(0));
            url = URL.createObjectURL(new Blob([bytes], { type: 'application/pdf' }));
        "
        x-on:remove="url && URL.revokeObjectURL(url)"
    >
        <iframe x-bind:src="url" title="Vista previa del PDF" class="h-[75vh] w-full rounded-lg border border-gray-200 bg-white dark:border-white/10"></iframe>
    </div>
</div>
