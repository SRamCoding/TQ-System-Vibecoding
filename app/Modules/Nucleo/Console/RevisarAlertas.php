<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Console;

use App\Modules\Nucleo\Services\AlertasService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Illuminate\Console\Command;

/**
 * La revisión diaria de alertas (plan 1.10, D4): corre las fuentes que registran los módulos. La programa el núcleo
 * a las 07:00 de Lima; repetirla no repite ninguna alerta (cada hecho se emite una vez). Firma el usuario de sistema.
 */
final class RevisarAlertas extends Command
{
    protected $signature = 'nucleo:revisar-alertas';

    protected $description = 'Revisa las alertas del día (cruces sin control y las que registren los módulos)';

    public function handle(AlertasService $alertas): int
    {
        $alertas->revisarDelDia(Calendario::ahora()->startOfDay());
        $this->info('Alertas revisadas.');

        return self::SUCCESS;
    }
}
