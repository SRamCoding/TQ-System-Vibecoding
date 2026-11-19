{{--
    Un monto corto con el exacto al lado (plan 2.21c, D16): se ve «S/ 27k»; el `title` (al pasar el mouse) y el lector de
    pantalla dan «S/ 27,000.00». Uso: <x-oportunidades::monto :corto="$x['total']" :completo="$x['totalCompleto']" />.
    Los textos ya vienen armados (`MontoCorto::de` y `PanelesOportunidad::dinero`).
--}}
@props(['corto', 'completo'])
@once
    <style>
        .tq-monto-leer { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
    </style>
@endonce
<span {{ $attributes->class(['tq-monto']) }} title="{{ $completo }}"><span aria-hidden="true">{{ $corto }}</span><span class="tq-monto-leer">{{ $completo }}</span></span>
