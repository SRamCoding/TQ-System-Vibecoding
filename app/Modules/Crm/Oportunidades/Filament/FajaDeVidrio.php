<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Filament;

use App\Modules\Crm\Oportunidades\Enums\EtapaAbierta;
use App\Modules\Crm\Oportunidades\Enums\LugarFaja;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\ListarOportunidades;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\TableroOportunidades;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\VerOportunidad;
use App\Modules\Crm\Oportunidades\Services\FilaFaja;
use App\Modules\Crm\Oportunidades\Services\LimiteCajasFaja;
use App\Modules\Crm\Oportunidades\Services\OportunidadesService;
use App\Modules\Crm\Oportunidades\Services\PortadaOportunidades;
use App\Modules\Crm\Oportunidades\Services\ProximaAccionDeOportunidades;
use App\Modules\Crm\Oportunidades\Services\TotalLugarFaja;
use App\Modules\Nucleo\Models\Moneda;
use App\Modules\Nucleo\Navegacion\ColoresMarca;
use App\Modules\Nucleo\Services\ParametrosService;

/**
 * Lo que dibuja la faja de vidrio (plan 2.21b, paso 3): arma los datos de la vista desde `OportunidadesService`
 * (`totalesDeLaFaja` y `tablaDeLaFaja`) y `LimiteCajasFaja`, sin decidir reglas. Aquí solo hay medidas de pantalla: el
 * lugar de cada caja en su zona o sala y su alto por monto. Paso 5: las salas de ganadas y perdidas (las mismas reglas
 * de `LimiteCajasFaja`, contando desde las más recientes por `LugarFaja::ordenDeLasCajas()`), los terminales y los
 * textos «N · S/ total» de `totalesDeLaFaja`.
 */
final class FajaDeVidrio
{
    /** Ancho de una zona en el mundo, en px (como el prototipo). */
    public const int ANCHO_ZONA = 600;

    /** Dónde termina la faja: la boca de la tubería (paso 5). */
    public const int FIN = 2420;

    /** El monto (en soles) de una caja de alto «completo»: alto = 90 + √(monto / REFERENCIA) · 120 (prototipo, `hOf`). */
    public const int MONTO_REFERENCIA = 410000;

    /** Dónde están las salas en el mundo (como el prototipo): lejos de la faja, cada una a la vista solo cuando se entra. */
    public const array SALAS = [
        'ganadas' => ['x' => 5200, 'y' => 0],
        'perdidas' => ['x' => 5200, 'y' => 1700],
    ];

    /**
     * Tope del factor √(monto / REFERENCIA): una oportunidad enorme no tapa la zona (desvío del prototipo, sin tope).
     * `float` solo para dibujar (píxeles), nunca para dinero.
     */
    public const float TOPE_FACTOR_ALTO = 1.5;

    /**
     * Las medidas de una caja y la cuadrícula de lugares por modo (D8). Completas: 3 × 2 cajas de 170 × 120; con código:
     * 4 × 3 de 120 × 84 (la pila «+N más» ocupa el último lugar). Centros relativos al borde izquierdo de la zona y al
     * piso; la faja va de y = 60 a y = 380 y el escáner está en x = 580 de cada zona.
     */
    private const array CUADRICULAS = [
        'completas' => ['ancho' => 170, 'fondo' => 120, 'escalaAlto' => 1.0, 'columnas' => [110, 290, 470], 'filas' => [145, 295]],
        'codigo' => ['ancho' => 120, 'fondo' => 84, 'escalaAlto' => 0.75, 'columnas' => [85, 222, 359, 496], 'filas' => [118, 220, 322]],
    ];

    /**
     * Las cuadrículas de las salas (D8, «igual en las salas»): completas, 3 × 2 sobre pallets; con código, 4 × 3. Centros
     * relativos al rincón de la sala; el piso marcado va de x = 100 a 1020 y de y = 130 a 560.
     */
    private const array CUADRICULAS_SALA = [
        'completas' => ['ancho' => 170, 'fondo' => 120, 'escalaAlto' => 1.0, 'columnas' => [330, 560, 790], 'filas' => [260, 450]],
        'codigo' => ['ancho' => 120, 'fondo' => 84, 'escalaAlto' => 0.75, 'columnas' => [255, 435, 615, 795], 'filas' => [225, 365, 505]],
    ];

    public function __construct(
        private readonly OportunidadesService $oportunidades,
        private readonly PortadaOportunidades $portada,
    ) {}

    /**
     * @return array{
     *     zonas: list<array<string, mixed>>,
     *     salas: array{ganadas: array<string, mixed>, perdidas: array<string, mixed>},
     *     colores: array<string, string>,
     *     mundo: array{anchoZona: int, fin: int},
     *     urlTabla: string,
     *     urlTablero: string,
     * }
     */
    public function datos(): array
    {
        $totales = $this->oportunidades->totalesDeLaFaja();
        $local = $this->simboloLocal();
        $zonas = [];

        foreach (EtapaAbierta::cases() as $indice => $etapa) {
            $lugar = LugarFaja::deEtapa($etapa);
            $zonas[] = [
                'indice' => $indice,
                'probabilidad' => $this->portada->probabilidad($etapa),
                ...$this->lugar($lugar, $totales[$lugar->value], $local, $indice * self::ANCHO_ZONA, 0, self::CUADRICULAS),
            ];
        }

        $salas = [];

        foreach ([LugarFaja::Ganadas, LugarFaja::Perdidas] as $lugar) {
            $origen = self::SALAS[$lugar->value];
            $salas[$lugar->value] = [...$origen, ...$this->lugar($lugar, $totales[$lugar->value], $local, $origen['x'], $origen['y'], self::CUADRICULAS_SALA)];
        }

        return [
            'zonas' => $zonas,
            'salas' => $salas,
            'colores' => self::colores(),
            'mundo' => ['anchoZona' => self::ANCHO_ZONA, 'fin' => self::FIN],
            'urlTabla' => ListarOportunidades::getUrl(),
            'urlTablero' => TableroOportunidades::getUrl(),
        ];
    }

    /**
     * «N · S/ total» de un lugar, como lo dicen su terminal, su letrero y el título de su tabla (pedido B5). `$corto`:
     * el monto en notación corta (plan 2.21c, D16), para el terminal; el título de la tabla lo lleva exacto.
     */
    public function textoDelTotal(TotalLugarFaja $total, ?string $simboloLocal = null, bool $corto = false): string
    {
        $simbolo = $simboloLocal ?? $this->simboloLocal();

        return $total->cantidad.' · '.($corto ? MontoCorto::de($total->totalEnSoles, $simbolo) : PanelesOportunidad::dinero($total->totalEnSoles, $simbolo));
    }

    /**
     * Si hay próxima acción que mostrar (D5), en un solo lugar: hasta CRM-05 no existe, y ni el punto ámbar de la caja ni
     * la columna de las tablas se muestran (una columna llena de «—» no dice nada, como la D11 de Cuentas). CRM-05 la
     * llena en `FilaFaja::proximaAccion` y cambia esta respuesta.
     */
    public static function conProximaAccion(): bool
    {
        return app(ProximaAccionDeOportunidades::class)->existe();
    }

    /**
     * Un lugar de la faja (zona o sala) con sus cajas según el límite (D8): las que se dibujan, en el orden de
     * `LugarFaja::ordenDeLasCajas()`, y la pila «+N más» en el último lugar de la cuadrícula.
     *
     * @param  array<string, array{ancho: int, fondo: int, escalaAlto: float, columnas: list<int>, filas: list<int>}>  $cuadriculas
     * @return array<string, mixed>
     */
    private function lugar(LugarFaja $lugar, TotalLugarFaja $total, ?string $local, int $origenX, int $origenY, array $cuadriculas): array
    {
        $limite = LimiteCajasFaja::para($total->cantidad);
        $modo = $limite->modo->value;
        $filas = [];

        if ($limite->dibujadas > 0) {
            [$orden, $direccion] = $lugar->ordenDeLasCajas();
            $filas = $this->oportunidades->tablaDeLaFaja($lugar, null, $orden, $direccion, 1, $limite->dibujadas)->filas;
        }

        $cuadricula = $cuadriculas[$modo === 'completas' ? 'completas' : 'codigo'];
        $cajas = [];

        foreach ($filas as $k => $fila) {
            $cajas[] = $this->caja($fila, $cuadricula, $origenX, $origenY, $k, $lugar === LugarFaja::Perdidas);
        }

        $pila = null;

        if ($limite->enPila > 0) {
            $k = LimiteCajasFaja::HASTA_CODIGO - 1;
            $pila = [
                'cantidad' => $limite->enPila,
                'x' => $origenX + $cuadricula['columnas'][$k % count($cuadricula['columnas'])],
                'y' => $origenY + $cuadricula['filas'][intdiv($k, count($cuadricula['columnas']))],
                'ancho' => $cuadricula['ancho'],
                'fondo' => $cuadricula['fondo'],
            ];
        }

        return [
            'lugar' => $lugar->value,
            'nombre' => $lugar->etiqueta(),
            'cantidad' => $total->cantidad,
            // D16: corto a la vista; el exacto en el `title` y en lo que lee el lector de pantalla.
            'total' => MontoCorto::de($total->totalEnSoles, $local),
            'totalCompleto' => PanelesOportunidad::dinero($total->totalEnSoles, $local),
            'terminal' => $this->textoDelTotal($total, $local, corto: true),
            'terminalCompleto' => $this->textoDelTotal($total, $local),
            'sinTipoCambio' => $total->sinTipoCambio,
            'modo' => $modo,
            'cajas' => $cajas,
            'pila' => $pila,
        ];
    }

    private function simboloLocal(): ?string
    {
        $simbolo = Moneda::query()->where('codigo', app(ParametrosService::class)->monedaLocal())->value('simbolo');

        return is_string($simbolo) ? $simbolo : null;
    }

    /** El alto de una caja por su monto en soles (prototipo, `hOf`), con tope. */
    public static function alto(?string $montoEnSoles): int
    {
        // `float` solo para el alto en píxeles del dibujo; el monto de verdad sigue en texto decimal (nunca dinero en float).
        $monto = max(0.0, (float) ($montoEnSoles ?? '0'));

        return (int) round(90 + min(sqrt($monto / self::MONTO_REFERENCIA), self::TOPE_FACTOR_ALTO) * 120);
    }

    /**
     * Los colores del mundo (D13), en un solo lugar: el mundo es oscuro en los dos temas. El brillo del vidrio es el
     * morado de la marca.
     *
     * @return array<string, string>
     */
    public static function colores(): array
    {
        [$r, $g, $b] = sscanf(ColoresMarca::MORADO, '#%02x%02x%02x');

        return [
            '--tq-faja-noche' => '#0B0A1A',
            '--tq-faja-cielo' => '#251F58',
            '--tq-faja-brillo' => "{$r},{$g},{$b}",
            '--tq-faja-tinta' => '#EDEBFF',
            '--tq-faja-tinta-suave' => '#A9A4D6',
            '--tq-faja-ambar' => '#F0A63A',
            '--tq-faja-rojo' => '#F06A5E',
            '--tq-faja-verde' => '#4FC28A',
            '--tq-faja-cafe' => '201,140,70',
            '--tq-faja-banda' => '#16142C',
            '--tq-faja-banda-linea' => '#1D1A3A',
            '--tq-faja-borde' => '#3A3462',
            '--tq-faja-acero' => '#2A2E33',
            // Paso 5: la tubería, sus soportes, los terminales y las salas.
            '--tq-faja-acero-medio' => '#9AA2AA',
            '--tq-faja-acero-claro' => '#C9D0D6',
            '--tq-faja-soporte' => '#3A3F46',
            '--tq-faja-madera' => '#9C7A4E',
            '--tq-faja-concreto' => '#6C6A66',
            '--tq-faja-escalon' => '#3A352E',
            '--tq-faja-ganada' => '46,125,90',
            '--tq-faja-perdida' => '142,58,51',
        ];
    }

    /**
     * @param  array{ancho: int, fondo: int, escalaAlto: float, columnas: list<int>, filas: list<int>}  $cuadricula
     * @return array<string, mixed>
     */
    private function caja(FilaFaja $fila, array $cuadricula, int $origenX, int $origenY, int $k, bool $caida): array
    {
        $columnas = count($cuadricula['columnas']);

        return [
            'id' => $fila->id,
            'numero' => $fila->numero,
            'nombre' => $fila->nombre,
            'cuenta' => $fila->cuenta,
            'monto' => MontoCorto::de($fila->monto, $fila->simbolo),
            'montoCompleto' => PanelesOportunidad::dinero($fila->monto, $fila->simbolo),
            'atascada' => $fila->atascada,
            'avisoProximaAccion' => self::avisoDeProximaAccion($fila),
            'version' => $fila->version,
            'url' => VerOportunidad::getUrl(['record' => $fila->id]),
            'x' => $origenX + $cuadricula['columnas'][$k % $columnas],
            'y' => $origenY + $cuadricula['filas'][intdiv($k, $columnas)],
            // Las perdidas quedan caídas en el sótano: un giro y una inclinación fijos por caja (sin azar, para que no
            // salten al redibujar).
            'giro' => $caida ? ($fila->id * 47) % 57 - 28 : 0,
            'tumbo' => $caida ? ($fila->id * 31) % 21 - 10 : 0,
            'ancho' => $cuadricula['ancho'],
            'fondo' => $cuadricula['fondo'],
            'alto' => (int) round(self::alto($fila->montoEnSoles) * $cuadricula['escalaAlto']),
        ];
    }

    /**
     * El punto ámbar que late (pedido B1): sin próxima acción o vencida. Hasta CRM-05 la próxima acción no existe (D5,
     * `conProximaAccion`) y no hay punto; CRM-05 llena `FilaFaja::proximaAccion` y completa la regla de «vencida».
     */
    private static function avisoDeProximaAccion(FilaFaja $fila): bool
    {
        return self::conProximaAccion() && ($fila->proximaAccion === null || str_starts_with($fila->proximaAccion, 'Vencida'));
    }
}
