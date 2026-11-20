<?php

declare(strict_types=1);

namespace App\Modules\Erp\Finanzas\Filament\Pages;

use App\Modules\Erp\Finanzas\Models\Comprobante;
use App\Modules\Erp\Finanzas\Services\FinanzasService;
use App\Modules\Nucleo\Tiempo\Calendario;
use BackedEnum;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * MVP rápido (t5-finanzas): el tablero de pagos de Tesorería, en tres columnas: por programar, programado y pagado
 * (últimos 15 días). Cada tarjeta se mueve con sus botones (en el v4, arrastrando).
 */
final class TableroPagos extends Base
{
    protected static ?string $slug = 'tablero-pagos';

    protected static ?string $title = 'Tablero de pagos';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-view-columns';

    protected string $view = 'finanzas::tablero-pagos';

    /** @return array<string, array{titulo: string, total: string, tarjetas: Collection}> */
    public function columnas(): array
    {
        $col = fn (string $titulo, $q): array => ['titulo' => $titulo, 'tarjetas' => $q->get(), 'total' => self::s((clone $q)->sum('total'))];

        return [
            'por_programar' => $col('Por programar', Comprobante::query()->where('clase', 'compra')->where('estado', 'por_programar')->orderBy('vencimiento')),
            'programado' => $col('Programado', Comprobante::query()->where('clase', 'compra')->where('estado', 'programado')->orderBy('pago_programado')),
            'pagado' => $col('Pagado · últimos 15 días', Comprobante::query()->where('clase', 'compra')->where('estado', 'pagado')->where('updated_at', '>=', now()->subDays(15))->orderByDesc('updated_at')),
        ];
    }

    public function programar(int $id): void
    {
        $this->hacer(fn () => app(FinanzasService::class)->programar($id, CarbonImmutable::parse(Calendario::hoy())->next(CarbonImmutable::FRIDAY)), 'Programado para el viernes.');
    }

    public function pagar(int $id): void
    {
        $this->hacer(fn () => app(FinanzasService::class)->pagar($id, CarbonImmutable::parse(Calendario::hoy())), 'Pagado: sale del banco y pasa a pagado en sus partidas.');
    }
}
