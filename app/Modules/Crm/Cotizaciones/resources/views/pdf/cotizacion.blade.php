{{--
    El PDF de una cotización (D20, plan 2.22c), dibujado con dompdf: CSS sencillo (tablas, sin flex ni variables), papel
    blanco con la tinta de `ColoresMarca`. Viene del resumen de C3, que no trae costo ni margen (D15). El encabezado de
    Techquk es el del núcleo (`nucleo::empresa.encabezado`); si todavía no se eligió la ficha de la empresa, no se dibuja.
--}}
@use('App\Modules\Nucleo\Navegacion\ColoresMarca')
@php
    /** @var \App\Modules\Crm\Cotizaciones\Services\ResumenCotizacion $cotizacion */
    $monto = fn (string $valor): string => number_format((float) $valor, 2);
    $fecha = fn (?string $dia): ?string => $dia === null ? null : \Carbon\CarbonImmutable::parse($dia)->format('d/m/Y');
    $condiciones = array_filter([
        'Plazo de entrega' => $cotizacion->plazo,
        'Forma de pago' => $cotizacion->formaPago,
        'Garantía' => $cotizacion->garantia,
        'Lugar de entrega' => $cotizacion->lugarEntrega,
    ]);
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $cotizacion->identificador }}</title>
    <style>
        @page { margin: 1.6cm 1.5cm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9.5pt; color: {{ ColoresMarca::TINTA }}; }
        h1 { margin: 0; font-size: 15pt; color: {{ ColoresMarca::AZUL_NOCHE }}; }
        .suave { color: {{ ColoresMarca::TINTA_SUAVE }}; }
        .derecha { text-align: right; }
        .borrador { margin-bottom: 10pt; padding: 6pt 8pt; border: 1pt solid #b45309; color: #b45309; font-weight: bold; }
        .titulo { width: 100%; margin: 14pt 0 10pt; border-collapse: collapse; }
        .titulo td { vertical-align: top; padding: 0; }
        .lineas { width: 100%; border-collapse: collapse; margin-top: 6pt; }
        .lineas th { background: {{ ColoresMarca::AZUL_NOCHE }}; color: #ffffff; font-weight: bold; padding: 4pt 5pt; text-align: left; }
        .lineas th.derecha { text-align: right; }
        .lineas td { padding: 4pt 5pt; border-bottom: 0.5pt solid #d4d4d8; vertical-align: top; }
        .totales { width: 45%; margin: 8pt 0 0 auto; border-collapse: collapse; }
        .totales td { padding: 2pt 5pt; }
        .totales .total td { font-weight: bold; font-size: 11pt; border-top: 1pt solid {{ ColoresMarca::TINTA }}; }
        h2 { margin: 14pt 0 4pt; font-size: 10.5pt; color: {{ ColoresMarca::AZUL_NOCHE }}; }
        .datos { border-collapse: collapse; }
        .datos td { padding: 1.5pt 10pt 1.5pt 0; vertical-align: top; }
    </style>
</head>
<body>
    @if ($esBorrador)
        <div class="borrador">Borrador: vista previa. No es la cotización emitida ni se envía al cliente.</div>
    @endif

    @if ($empresa !== null)
        @include('nucleo::empresa.encabezado', ['empresa' => $empresa, 'logo' => $logo])
    @endif

    <table class="titulo">
        <tr>
            <td>
                <h1>Cotización {{ $cotizacion->identificador }}</h1>
                <p style="margin: 4pt 0 0;"><strong>Cliente:</strong> {{ $cliente }}</p>
                @if ($atencion !== null)
                    <p style="margin: 2pt 0 0;"><strong>Atención:</strong> {{ $atencion }}</p>
                @endif
            </td>
            <td class="derecha">
                <p style="margin: 0;"><strong>Emisión:</strong> {{ $fecha($cotizacion->fechaEmision) ?? '—' }}</p>
                <p style="margin: 2pt 0 0;"><strong>Válida hasta:</strong> {{ $fecha($cotizacion->fechaVencimiento) ?? '—' }}</p>
                <p style="margin: 2pt 0 0;"><strong>Moneda:</strong> {{ $cotizacion->moneda }}</p>
            </td>
        </tr>
    </table>

    <table class="lineas">
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th>Descripción</th>
                <th style="width: 8%;">Unidad</th>
                <th class="derecha" style="width: 10%;">Cantidad</th>
                <th class="derecha" style="width: 13%;">P.U.</th>
                <th class="derecha" style="width: 8%;">Dscto.</th>
                <th class="derecha" style="width: 14%;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($cotizacion->lineas as $i => $linea)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $linea['descripcion'] }}</td>
                    <td>{{ $linea['unidad'] }}</td>
                    <td class="derecha">{{ rtrim(rtrim($linea['cantidad'], '0'), '.') }}</td>
                    <td class="derecha">{{ $monto($linea['precio_unitario']) }}</td>
                    <td class="derecha">{{ (float) $linea['descuento_porcentaje'] == 0.0 ? '' : rtrim(rtrim($linea['descuento_porcentaje'], '0'), '.').' %' }}</td>
                    <td class="derecha">{{ $monto($linea['subtotal']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totales">
        <tr><td>Subtotal</td><td class="derecha">{{ $cotizacion->moneda }} {{ $monto($cotizacion->subtotal) }}</td></tr>
        <tr><td>IGV</td><td class="derecha">{{ $cotizacion->moneda }} {{ $monto($cotizacion->igv) }}</td></tr>
        <tr class="total"><td>Total</td><td class="derecha">{{ $cotizacion->moneda }} {{ $monto($cotizacion->total) }}</td></tr>
    </table>

    @if ($condiciones !== [])
        <h2>Condiciones comerciales</h2>
        <table class="datos">
            @foreach ($condiciones as $etiqueta => $valor)
                <tr><td class="suave">{{ $etiqueta }}</td><td>{{ $valor }}</td></tr>
            @endforeach
        </table>
    @endif

    @if ($cuentaBancaria !== null)
        <h2>Cuenta para el pago</h2>
        <table class="datos">
            <tr><td class="suave">Banco</td><td>{{ $cuentaBancaria->etiqueta }}</td></tr>
            <tr><td class="suave">Cuenta {{ $cuentaBancaria->codigoMoneda }}</td><td>{{ $cuentaBancaria->numeroCuenta }}</td></tr>
            @if ($cuentaBancaria->cci !== null)
                <tr><td class="suave">CCI</td><td>{{ $cuentaBancaria->cci }}</td></tr>
            @endif
        </table>
    @endif
</body>
</html>
