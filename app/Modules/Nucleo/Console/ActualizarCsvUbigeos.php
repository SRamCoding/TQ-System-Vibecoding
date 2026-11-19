<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Console;

use App\Modules\Nucleo\Excepciones\PadronUbigeosNoDisponible;
use App\Modules\Nucleo\Ubigeos\DistritoUbigeo;
use App\Modules\Nucleo\Ubigeos\PadronUbigeos;
use Illuminate\Console\Command;

/**
 * Descarga la lista de distritos del INEI, muestra qué cambió frente al CSV del repositorio y solo lo
 * reemplaza si alguien confirma. No toca la base: después se corre el seeder de catálogos.
 */
final class ActualizarCsvUbigeos extends Command
{
    protected $signature = 'nucleo:actualizar-csv-ubigeos
        {--aplicar : Reemplaza el CSV sin preguntar}
        {--url= : Otra dirección de descarga (por defecto, config/nucleo.php)}';

    protected $description = 'Descarga los ubigeos del INEI, muestra las diferencias y actualiza el CSV del núcleo';

    private const int MAXIMO_LISTADO = 20;

    public function handle(PadronUbigeos $padron): int
    {
        $url = (string) ($this->option('url') ?: config('nucleo.ubigeos.url'));
        $this->line("Descargando {$url}");

        try {
            $descargados = $padron->descargar($url);
        } catch (PadronUbigeosNoDisponible $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $diferencia = $padron->comparar($padron->leerGuardado(), $descargados);
        $this->info(sprintf('Descargados: %d distritos.', count($descargados)));

        if (! $diferencia->hayCambios()) {
            $this->info('Sin cambios: el CSV ya está al día.');

            return self::SUCCESS;
        }

        $this->listar('Nuevos', array_map(fn (DistritoUbigeo $d): string => $d->descripcion(), $diferencia->nuevos));
        $this->listar('Renombrados', array_map(
            fn (array $par): string => "{$par['antes']->descripcion()}  →  {$par['ahora']->departamento} / {$par['ahora']->provincia} / {$par['ahora']->distrito}",
            $diferencia->renombrados,
        ));
        $this->listar('Ya no están (se desactivarán, no se borran)', array_map(fn (DistritoUbigeo $d): string => $d->descripcion(), $diferencia->retirados));

        if (! $this->option('aplicar') && ! $this->confirm('¿Reemplazar el CSV con la lista descargada?', false)) {
            $this->line('No se cambió nada.');

            return self::SUCCESS;
        }

        $padron->guardar($descargados, ['url' => $url, 'descargado' => now()->toDateString(), 'codigos' => 'INEI']);
        $this->info("CSV actualizado: {$padron->rutaCsv()}");
        $this->line('Siguiente: revisar el cambio en git y correr «php artisan db:seed --class=\\App\\Modules\\Nucleo\\database\\seeders\\CatalogosSeeder».');

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $lineas
     */
    private function listar(string $titulo, array $lineas): void
    {
        $this->newLine();
        $this->line(sprintf('<comment>%s: %d</comment>', $titulo, count($lineas)));

        foreach (array_slice($lineas, 0, self::MAXIMO_LISTADO) as $linea) {
            $this->line("  {$linea}");
        }

        if (count($lineas) > self::MAXIMO_LISTADO) {
            $this->line(sprintf('  … y %d más', count($lineas) - self::MAXIMO_LISTADO));
        }
    }
}
