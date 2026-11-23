{{--
    Observación 7.3: el paquete para el SEACE. Índice y una página foliada por documento, en el orden de las bases;
    las que necesitan firma, marcadas. Atajo del MVP: los archivos subidos no se incrustan (se adjuntan aparte).
--}}
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><title>Paquete {{ $l->nomenclatura }}</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1F2328; }
    .folio { position: fixed; top: -10px; right: 0; font-size: 10px; color: #5F5B53; }
    h1 { font-size: 16px; margin: 0 0 4px; } h2 { font-size: 14px; margin: 0 0 8px; }
    table { width: 100%; border-collapse: collapse; margin-top: 12px; }
    td, th { border-bottom: 1px solid #E5E0D6; padding: 6px 4px; text-align: left; }
    .firma { color: #B07A00; font-weight: bold; }
    .pagina { page-break-before: always; }
    .caja { border: 1px dashed #B07A00; padding: 10px; margin-top: 40px; }
</style></head>
<body>
    <div class="folio">Folio 1</div>
    <h1>{{ $l->nomenclatura }} · {{ $entidad }}</h1>
    <div>{{ $l->objeto }}</div>
    <h2 style="margin-top: 18px">Índice</h2>
    <table>
        <tr><th>Folio</th><th>Documento</th><th>Firma</th></tr>
        @foreach ($indice as $i)
            <tr><td>{{ $i['folio'] }}</td><td>{{ $i['nombre'] }}</td><td class="firma">{{ $i['firma'] ? 'Firmar' : '' }}</td></tr>
        @endforeach
    </table>
    @foreach ($indice as $i)
        <div class="pagina">
            <div class="folio">Folio {{ $i['folio'] }}</div>
            <h2>{{ $i['nombre'] }}</h2>
            <div>{{ $l->nomenclatura }} · Techquk S.A.C.</div>
            @if ($i['responsable'])<div>Responsable: {{ $i['responsable'] }}</div>@endif
            @if ($i['archivo'])<div>Archivo adjunto: {{ basename($i['archivo']) }}</div>@endif
            @if ($i['firma'])<div class="caja firma">Esta página necesita firma del representante legal.</div>@endif
        </div>
    @endforeach
</body></html>
