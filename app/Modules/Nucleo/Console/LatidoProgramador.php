<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Console;

use App\Modules\Nucleo\Historico\ArchivoHistoricoService;
use App\Modules\Nucleo\Services\SaludService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * El latido del programador (plan 1.12, D7), cada 5 minutos: anota su corrida para la página de salud y, si TI
 * configuró `SALUD_LATIDO_URL` (Healthchecks), la avisa ahí. Si el latido deja de llegar, Healthchecks avisa a TI.
 * De paso limpia el registro de corridas: una semana de latidos y `salud.dias_historial` de lo demás, que pasan antes
 * al archivo histórico.
 */
final class LatidoProgramador extends Command
{
    public const string TAREA = 'nucleo.latido';

    /** Los latidos son muchos (288 al día): solo una semana. Las demás corridas, `salud.dias_historial`. */
    public const int DIAS_HISTORIAL = 7;

    protected $signature = 'nucleo:latido';

    protected $description = 'Anota que el programador está vivo y avisa a Healthchecks si está configurado';

    public function handle(SaludService $salud, ArchivoHistoricoService $historico): int
    {
        $url = (string) config('nucleo.salud.latido_url');

        $salud->registrar(self::TAREA, function () use ($url): array {
            if ($url === '') {
                return ['enviado' => false];
            }

            try {
                Http::timeout(5)->get($url)->throw();

                return ['enviado' => true];
            } catch (Throwable $error) {
                // Un Healthchecks caído no detiene el latido: él mismo avisará que no llegó.
                Log::warning('No se pudo enviar el latido a Healthchecks.', ['error' => $error::class]);

                return ['enviado' => false];
            }
        });

        // La limpieza no detiene el latido: si el histórico falla (sin clave, sin disco), ya avisó él y aquí solo se anota.
        try {
            $salud->cerrarColgadas();
            $salud->olvidarViejas(self::TAREA, self::DIAS_HISTORIAL, $historico);
        } catch (Throwable $error) {
            Log::warning('No se pudo archivar el registro de corridas; se reintenta en el próximo latido.', ['error' => $error::class]);
        }

        return self::SUCCESS;
    }
}
