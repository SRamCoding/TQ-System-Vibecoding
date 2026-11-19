<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Filament;

use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\FajaOportunidades;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\LineaDeTiempoOportunidades;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\ListarOportunidades;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\TableroOportunidades;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\TarjetasOportunidades;
use App\Modules\Crm\Oportunidades\Services\EmbudoEnSoles;
use App\Modules\Crm\Oportunidades\Services\TableroDeGemas;
use App\Modules\Nucleo\Models\Moneda;
use App\Modules\Nucleo\Services\ParametrosService;

/**
 * Lo común de las vistas de Oportunidades (plan 2.21c, D1; 2.21d, D11): las pestañas (Tarjetas · Tablero, por defecto ·
 * Línea de tiempo · Lista · Faja 3D) y la fila de cifras de arriba, de `TableroDeGemas::cifras()`. Solo da forma al texto: las cuentas son
 * del servicio. La dibuja la vista `oportunidades::vistas.cabecera`.
 */
final class VistasDeOportunidades
{
    public const string TARJETAS = 'tarjetas';

    public const string TABLERO = 'tablero';

    public const string LINEA_DE_TIEMPO = 'linea-de-tiempo';

    public const string LISTA = 'lista';

    public const string FAJA = 'faja';

    /**
     * Las pestañas, en su orden, con la activa marcada.
     *
     * @return list<array{clave: string, etiqueta: string, url: string, activa: bool}>
     */
    public static function pestanas(string $activa): array
    {
        $vistas = [
            // Plan 2.21d, D11: Tarjetas, Tablero y Línea de tiempo; la Lista y la Faja 3D siguen como vistas extra.
            self::TARJETAS => ['Tarjetas', TarjetasOportunidades::class],
            self::TABLERO => ['Tablero', TableroOportunidades::class],
            self::LINEA_DE_TIEMPO => ['Línea de tiempo', LineaDeTiempoOportunidades::class],
            self::LISTA => ['Lista', ListarOportunidades::class],
            self::FAJA => ['Faja 3D', FajaOportunidades::class],
        ];
        $pestanas = [];

        foreach ($vistas as $clave => [$etiqueta, $pagina]) {
            $pestanas[] = ['clave' => $clave, 'etiqueta' => $etiqueta, 'url' => $pagina::getUrl(), 'activa' => $clave === $activa];
        }

        return $pestanas;
    }

    /**
     * La fila de cifras (D1): embudo abierto, ponderado, cierran este mes, atascadas y cierre estimado vencido. Lo de
     * otra moneda va convertido al T.C. de compra y se dice cuánto; lo que no tiene T.C. queda aparte (D8 de la 2.21).
     *
     * @return array<string, array{titulo: string, valor: string, valorCompleto: ?string, detalle: string, detalleCompleto?: string, notas: list<string>, alerta: bool}>
     */
    public static function cifras(): array
    {
        $cifras = app(TableroDeGemas::class)->cifras();
        $local = self::simboloLocal();
        $notas = self::notas($cifras->embudo);

        return [
            'embudo' => [
                'titulo' => 'Embudo abierto',
                'valor' => MontoCorto::de($cifras->embudo->total, $local),
                'valorCompleto' => PanelesOportunidad::dinero($cifras->embudo->total, $local),
                'detalle' => self::cantidad($cifras->embudo->cantidad),
                'notas' => $notas,
                'alerta' => false,
            ],
            'ponderado' => [
                'titulo' => 'Ponderado',
                'valor' => MontoCorto::de($cifras->embudo->ponderado, $local),
                'valorCompleto' => PanelesOportunidad::dinero($cifras->embudo->ponderado, $local),
                'detalle' => 'Cada monto por la probabilidad de su etapa',
                'notas' => $notas,
                'alerta' => false,
            ],
            'este-mes' => [
                'titulo' => 'Cierran este mes',
                'valor' => MontoCorto::de($cifras->esteMes->total, $local),
                'valorCompleto' => PanelesOportunidad::dinero($cifras->esteMes->total, $local),
                'detalle' => self::cantidad($cifras->esteMes->cantidad),
                'notas' => self::notas($cifras->esteMes),
                'alerta' => false,
            ],
            'atascadas' => [
                'titulo' => 'Atascadas',
                'valor' => (string) $cifras->atascadas->cantidad,
                'valorCompleto' => null,
                'detalle' => MontoCorto::de($cifras->atascadas->total, $local).' · pasan los días máximos de su etapa',
                'detalleCompleto' => PanelesOportunidad::dinero($cifras->atascadas->total, $local).' · pasan los días máximos de su etapa',
                'notas' => [],
                'alerta' => $cifras->atascadas->cantidad > 0,
            ],
            'vencidas' => [
                'titulo' => 'Cierre estimado vencido',
                'valor' => (string) $cifras->vencidas->cantidad,
                'valorCompleto' => null,
                'detalle' => MontoCorto::de($cifras->vencidas->total, $local).' · siguen abiertas',
                'detalleCompleto' => PanelesOportunidad::dinero($cifras->vencidas->total, $local).' · siguen abiertas',
                'notas' => [],
                'alerta' => $cifras->vencidas->cantidad > 0,
            ],
        ];
    }

    public static function simboloLocal(): ?string
    {
        $simbolo = Moneda::query()->where('codigo', app(ParametrosService::class)->monedaLocal())->value('simbolo');

        return is_string($simbolo) ? $simbolo : null;
    }

    private static function cantidad(int $cantidad): string
    {
        return $cantidad === 1 ? '1 oportunidad abierta' : "{$cantidad} oportunidades abiertas";
    }

    /**
     * «Incluye US$ 1,000.00 a T.C. 3.700» por cada moneda convertida, y aparte lo que no tiene T.C.
     *
     * @return list<string>
     */
    private static function notas(EmbudoEnSoles $embudo): array
    {
        $simbolo = fn (string $codigo): ?string => Moneda::query()->where('codigo', $codigo)->value('simbolo');
        $notas = [];

        foreach ($embudo->convertido as $moneda => $dato) {
            $notas[] = 'Incluye '.PanelesOportunidad::dinero($dato['monto'], $simbolo($moneda))." a T.C. {$dato['tipoCambio']}";
        }

        foreach ($embudo->sinTipoCambio as $moneda => $dato) {
            $notas[] = 'Aparte, sin T.C.: '.PanelesOportunidad::dinero($dato['monto'], $simbolo($moneda))
                .($dato['cantidad'] === 1 ? ' (1 oportunidad)' : " ({$dato['cantidad']} oportunidades)");
        }

        return $notas;
    }
}
