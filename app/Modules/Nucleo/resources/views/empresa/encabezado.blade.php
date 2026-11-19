{{--
    El encabezado de Techquk en un documento (plan 1.15, D4): logo, nombres, RUC, dirección y contacto, tomados de
    `EmpresaService::datos()`. Lo incluyen los documentos impresos (etapa 2) y la vista previa del asistente. Es papel:
    fondo blanco y texto oscuro también en modo oscuro, con la tinta y el azul de la marca de `ColoresMarca`; sin
    variables de Filament, que un PDF no tiene. Lo que falta no se dibuja (el asistente lo avisa aparte).
--}}
@php
    /** @var \App\Modules\Nucleo\Parties\DatosEmpresa $empresa */
    /** @var ?string $logo la imagen como `data:` (`EmpresaService::imagenDelLogo()`) */
    $sinEsquema = fn (string $enlace): string => preg_replace('#^https?://(www\.)?#i', '', rtrim($enlace, '/'));
    $contacto = array_filter([$empresa->telefono, $empresa->correo, $empresa->sitioWeb === null ? null : $sinEsquema($empresa->sitioWeb)]);
@endphp
<div class="tq-encabezado-empresa" data-tq-encabezado-empresa>
    <style>
        .tq-encabezado-empresa { display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem 1.25rem; color: {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::TINTA }}; font-size: 0.8125rem; line-height: 1.4; }
        .tq-encabezado-empresa-logo { flex: none; max-width: 9rem; max-height: 4.5rem; object-fit: contain; }
        .tq-encabezado-empresa-datos { flex: 1 1 14rem; min-width: 0; }
        .tq-encabezado-empresa-nombre { margin: 0; font-size: 1.125rem; font-weight: 700; color: {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::AZUL_NOCHE }}; }
        .tq-encabezado-empresa-linea { margin: 0; overflow-wrap: anywhere; }
        .tq-encabezado-empresa-suave { color: {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::TINTA_SUAVE }}; }
    </style>
    @if ($logo !== null)
        <img class="tq-encabezado-empresa-logo" src="{{ $logo }}" alt="Logo de {{ $empresa->nombreComercial ?? $empresa->razonSocial }}">
    @endif
    <div class="tq-encabezado-empresa-datos">
        <p class="tq-encabezado-empresa-nombre">{{ $empresa->nombreComercial ?? $empresa->razonSocial }}</p>
        @if ($empresa->nombreComercial !== null)
            <p class="tq-encabezado-empresa-linea">{{ $empresa->razonSocial }}</p>
        @endif
        <p class="tq-encabezado-empresa-linea"><strong>RUC {{ $empresa->ruc }}</strong></p>
        @if ($empresa->direccionFiscal !== null)
            <p class="tq-encabezado-empresa-linea">{{ $empresa->direccionFiscal }}</p>
        @endif
        @if ($contacto !== [])
            <p class="tq-encabezado-empresa-linea">{{ implode(' · ', $contacto) }}</p>
        @endif
        @if ($empresa->redes !== [])
            <p class="tq-encabezado-empresa-linea tq-encabezado-empresa-suave">
                {{ implode(' · ', array_map(fn ($red): string => $red->red.': '.$sinEsquema($red->enlace), $empresa->redes)) }}
            </p>
        @endif
    </div>
</div>
