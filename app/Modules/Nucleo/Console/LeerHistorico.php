<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Console;

use App\Modules\Nucleo\Historico\ArchivoHistoricoService;
use Illuminate\Console\Command;

/**
 * Para TI (plan 1.4y, D6): muestra descifradas las filas de un archivo del histórico, una por línea en JSON. La ruta es
 * la del disco `historico` (por ejemplo, `notificaciones/2027-11/20271101-030000-….jsonl.gz.cifrado`). Necesita la
 * clave de los respaldos; si cambió, `--otra-clave` pide la anterior sin que quede en el historial de la terminal.
 */
final class LeerHistorico extends Command
{
    protected $signature = 'nucleo:leer-historico {archivo : Ruta dentro del disco del histórico}
        {--otra-clave : Pide la contraseña con que se cifró, si BACKUP_ARCHIVE_PASSWORD cambió después}
        {--guardar= : Para un archivo entero (los huérfanos vencidos): lo guarda descifrado en esta ruta}';

    protected $description = 'Muestra descifradas las filas de un archivo del histórico';

    public function handle(ArchivoHistoricoService $historico): int
    {
        $contrasena = $this->option('otra-clave') ? (string) $this->secret('Contraseña con que se cifró') : null;

        // Plan 1.12: un archivo entero (no filas) se guarda descifrado donde diga TI.
        if (is_string($this->option('guardar')) && $this->option('guardar') !== '') {
            if (file_put_contents($this->option('guardar'), $historico->leerArchivo((string) $this->argument('archivo'), $contrasena)) === false) {
                $this->error('No se pudo guardar en '.$this->option('guardar').'.');

                return self::FAILURE;
            }

            $this->info('Guardado en '.$this->option('guardar').' (descifrado: bórralo cuando termines).');

            return self::SUCCESS;
        }

        foreach ($historico->leer((string) $this->argument('archivo'), $contrasena) as $fila) {
            $this->line((string) json_encode($fila, JSON_UNESCAPED_UNICODE));
        }

        return self::SUCCESS;
    }
}
