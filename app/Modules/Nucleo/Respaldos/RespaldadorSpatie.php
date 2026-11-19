<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Respaldos;

use Illuminate\Support\Facades\Artisan;
use Spatie\Backup\BackupDestination\BackupDestination;

/**
 * El respaldo con `spatie/laravel-backup` (plan 1.12, D3; `config/backup.php`). Sin sus avisos: los da el núcleo.
 * Necesita `pg_dump` igual o más nuevo que el servidor de PostgreSQL (imagen de producción, 1.12b).
 */
final readonly class RespaldadorSpatie implements Respaldador
{
    public function disco(): string
    {
        return (string) config('backup.backup.destination.disks.0');
    }

    public function respaldar(): ArchivoRespaldo
    {
        if (blank(config('backup.backup.password'))) {
            throw RespaldoFallido::sinClave();
        }

        if (Artisan::call('backup:run', ['--disable-notifications' => true, '--only-to-disk' => $this->disco()]) !== 0) {
            throw RespaldoFallido::alRespaldar();
        }

        $ultimo = BackupDestination::create($this->disco(), (string) config('backup.backup.name'))->newestBackup()
            ?? throw RespaldoFallido::sinArchivo($this->disco());

        return new ArchivoRespaldo(basename($ultimo->path()), (int) $ultimo->sizeInBytes());
    }

    public function limpiar(): void
    {
        Artisan::call('backup:clean', ['--disable-notifications' => true]);
    }
}
