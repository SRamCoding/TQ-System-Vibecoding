<?php

declare(strict_types=1);

namespace Tests\Modules\Nucleo\Fixtures;

use App\Modules\Nucleo\Estados\EfectoExterno;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Efecto externo de prueba: en vez de enviar un aviso, lo anota. Guarda el nivel de transacción y la clave de
 * idempotencia con que lo llamaron, y puede fallar las veces que la prueba diga.
 */
final class AvisoDePrueba implements EfectoExterno
{
    public static ?int $nivelDeTransaccion = null;

    public static ?string $ultimaClave = null;

    public static int $fallar = 0;

    public function preparar(Model $documento): array
    {
        return ['nombre' => (string) $documento->getAttribute('nombre')];
    }

    public function ejecutar(Model $documento, array $datos, string $claveIdempotencia): void
    {
        self::$nivelDeTransaccion = DB::transactionLevel();
        self::$ultimaClave = $claveIdempotencia;

        if (self::$fallar > 0) {
            self::$fallar--;

            throw new RuntimeException('El servicio de avisos no respondió.');
        }

        DB::table('anotaciones_prueba')->insert(['texto' => "aviso {$datos['nombre']}"]);
    }

    public function revertir(Model $documento, array $datos, string $claveIdempotencia): void
    {
        self::$nivelDeTransaccion = DB::transactionLevel();
        self::$ultimaClave = $claveIdempotencia;
        DB::table('anotaciones_prueba')->insert(['texto' => "aviso de anulación {$datos['nombre']}"]);
    }
}
