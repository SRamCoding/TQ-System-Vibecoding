<div class="space-y-2 text-sm">
    <p class="font-semibold">Techquk S.A.C. · Boleta de pago · {{ $periodo }}</p>
    <p>{{ $l->nombres }} · DNI {{ $l->dni }} · {{ strtoupper($l->regimen) }}</p>
    <table class="w-full">
        <tr><td>Remuneración</td><td class="text-right font-mono">{{ number_format((float) $l->remuneracion, 2) }}</td></tr>
        <tr><td>Aporte {{ strtoupper($l->regimen) }}</td><td class="text-right font-mono">−{{ number_format((float) $l->aporte, 2) }}</td></tr>
        <tr class="font-semibold"><td>Neto a pagar</td><td class="text-right font-mono">{{ number_format((float) $l->neto, 2) }}</td></tr>
        <tr class="text-gray-500"><td>Aporte del empleador · EsSalud 9 %</td><td class="text-right font-mono">{{ number_format((float) $l->essalud, 2) }}</td></tr>
    </table>
</div>
