{{--
    El resumen de un documento en el panel de «Mis aprobaciones» (plan 2.22, P6). Los datos ya llegan filtrados por los
    permisos de la persona: aquí no se decide qué se ve.
--}}
<dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm" data-tq-resumen-aprobacion>
    <dt class="text-gray-500 dark:text-gray-400">Firma</dt>
    <dd>{{ $fila['paso'] }}</dd>
    <dt class="text-gray-500 dark:text-gray-400">Lo pide</dt>
    <dd>{{ $fila['solicitante'] }} · {{ $fila['pedida'] }}</dd>
    <dt class="text-gray-500 dark:text-gray-400">Monto (S/)</dt>
    <dd class="font-mono">{{ $fila['monto'] }}</dd>
    @foreach ($fila['datos'] as $dato)
        <dt class="text-gray-500 dark:text-gray-400">{{ $dato['etiqueta'] }}</dt>
        <dd>{{ $dato['valor'] }}</dd>
    @endforeach
</dl>
@if ($fila['enlace'] !== null)
    <p class="mt-3 text-sm"><a href="{{ $fila['enlace'] }}" class="fi-link" target="_blank">Abrir el documento completo</a></p>
@endif
