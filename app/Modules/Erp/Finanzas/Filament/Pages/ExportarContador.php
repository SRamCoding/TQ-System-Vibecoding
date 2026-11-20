<?php

declare(strict_types=1);

namespace App\Modules\Erp\Finanzas\Filament\Pages;

use App\Modules\Nucleo\Tiempo\Calendario;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * MVP rápido (ficha t5-finanzas v2): lo que el contador necesita cada mes, en CSV para Excel: registro de ventas,
 * registro de compras y movimientos de caja y bancos, con la cuenta contable sugerida por plantilla (atajo: plantilla
 * fija en el código, no configurable).
 */
final class ExportarContador extends Base
{
    protected static ?string $slug = 'exportar-contador';

    protected static ?string $title = 'Para el contador';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected string $view = 'finanzas::exportar';

    /** Plantilla de asientos: cuenta del PCGE por libro. */
    public const array CUENTAS = ['venta' => ['12121 Facturas por cobrar', '40111 IGV', '70321 Ventas de servicios'], 'compra' => ['60xx / 63xx Compras y servicios', '40111 IGV', '42121 Facturas por pagar'], 'caja' => ['10411 Cuentas corrientes', '1013 Caja']];

    protected function getHeaderActions(): array
    {
        $meses = collect(range(0, 17))->mapWithKeys(fn (int $i): array => [($m = Calendario::ahora()->subMonths($i))->format('Y-m') => $m->translatedFormat('F Y')])->all();

        return collect(['ventas' => 'Registro de ventas', 'compras' => 'Registro de compras', 'caja' => 'Caja y bancos'])->map(fn (string $nombre, string $libro) => Action::make("exportar_{$libro}")
            ->label($nombre)->icon('heroicon-o-arrow-down-tray')->color($libro === 'ventas' ? 'primary' : 'gray')
            ->fillForm(fn (): array => ['mes' => Calendario::ahora()->subMonth()->format('Y-m')])
            ->schema([Select::make('mes')->label('Mes')->options($meses)->required()])
            ->action(fn (array $data): StreamedResponse => $this->descargar($libro, $data['mes'])))->values()->all();
    }

    private function descargar(string $libro, string $mes): StreamedResponse
    {
        [$y, $m] = array_map('intval', explode('-', $mes));
        [$cabecera, $filas] = match ($libro) {
            'caja' => [['Fecha', 'Cuenta', 'Tipo', 'Concepto', 'Monto', 'Conciliado', 'Cuenta contable'],
                DB::table('movimientos_caja')->whereYear('fecha', $y)->whereMonth('fecha', $m)->orderBy('fecha')->get()
                    ->map(fn ($r): array => [$r->fecha, $r->cuenta, $r->tipo, $r->concepto, $r->monto, $r->conciliado ? 'sí' : 'no', $r->cuenta === 'Caja' ? self::CUENTAS['caja'][1] : self::CUENTAS['caja'][0]])->all()],
            default => [['Emisión', 'Vencimiento', 'Comprobante', 'RUC', 'Razón social', 'Proyecto', 'Base imponible', 'IGV', 'Total', 'Detracción', 'Estado', 'Cuentas'],
                DB::table('comprobantes as c')->leftJoin('proyectos as p', 'p.id', '=', 'c.proyecto_id')->where('c.clase', $libro === 'ventas' ? 'venta' : 'compra')
                    ->whereYear('c.emision', $y)->whereMonth('c.emision', $m)->orderBy('c.emision')->get(['c.*', 'p.codigo as pry'])
                    ->map(fn ($r): array => [$r->emision, $r->vencimiento, $r->serie_numero, $r->ruc, $r->razon_social, $r->pry, $r->subtotal, $r->igv, $r->total, $r->detraccion, $r->estado,
                        implode(' / ', self::CUENTAS[$libro === 'ventas' ? 'venta' : 'compra'])])->all()],
        };

        return response()->streamDownload(function () use ($cabecera, $filas): void {
            $f = fopen('php://output', 'w');
            fwrite($f, "\xEF\xBB\xBF");
            fputcsv($f, $cabecera, ';');

            foreach ($filas as $fila) {
                fputcsv($f, $fila, ';');
            }

            fclose($f);
        }, "techquk-{$libro}-{$mes}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
