<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Respaldos;

/**
 * Quien hace el respaldo de verdad (plan 1.12, D3), detrás de su interfaz: en producción, `spatie/laravel-backup`; en
 * las pruebas, uno falso, para que ninguna escriba un respaldo en ningún lado.
 */
interface Respaldador
{
    /** El disco de destino de los respaldos (`RESPALDOS_DISCO`). */
    public function disco(): string;

    /** Hace el respaldo cifrado de la base y los adjuntos y devuelve el archivo que dejó. */
    public function respaldar(): ArchivoRespaldo;

    /** Borra los respaldos viejos según la retención de la librería. Solo se llama con destino local (D3). */
    public function limpiar(): void;
}
