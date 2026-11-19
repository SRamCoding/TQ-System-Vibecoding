<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Services;

use App\Modules\Nucleo\Enums\IconoAplicacion;
use App\Modules\Nucleo\Models\Adjunto;
use App\Modules\Nucleo\Models\Aplicacion;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\ArchivoSubido;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * El ícono que sube administración para una aplicación de Techquk (plan 1.4z, D9), para que sumar una con su logo no
 * necesite código. Es un adjunto de la aplicación, del tipo `icono_aplicacion` (PNG o WEBP, hasta 1 MB: los SVG están
 * prohibidos en todo adjunto). Lo revisa y lo guarda AdjuntosService; aquí, que la aplicación pase a usarlo.
 */
final readonly class IconosAplicacionService
{
    public const string TIPO = 'icono_aplicacion';

    public function __construct(private AdjuntosService $adjuntos) {}

    /** Sube (o reemplaza) el ícono y la aplicación pasa a «Propio». */
    public function subir(int $aplicacionId, ArchivoSubido $archivo, User $usuario): Adjunto
    {
        $aplicacion = Aplicacion::query()->findOrFail($aplicacionId);
        $anterior = $aplicacion->icono_adjunto_id === null ? null : Adjunto::query()->whereKey($aplicacion->icono_adjunto_id)->whereNull('quitado_at')->first();

        // El archivo se escribe fuera de toda transacción (lo hace AdjuntosService); después, la aplicación lo apunta.
        $adjunto = $anterior === null
            ? $this->adjuntos->agregar($aplicacion->getMorphClass(), $aplicacion->id, $archivo, self::TIPO, $usuario)
            : $this->adjuntos->reemplazar($anterior->id, $archivo, $usuario);

        DB::transaction(function () use ($aplicacionId, $adjunto): void {
            $aplicacion = Aplicacion::query()->lockForUpdate()->findOrFail($aplicacionId);
            $aplicacion->icono = IconoAplicacion::Propio;
            $aplicacion->icono_adjunto_id = $adjunto->id;
            $aplicacion->version++;
            $aplicacion->save();
        });

        return $adjunto;
    }

    /**
     * Las imágenes de los íconos subidos, como `data:`, por id de adjunto: la pantalla las incrusta como las demás.
     *
     * @param  list<int>  $adjuntos
     * @return array<int, string>
     */
    public function imagenes(array $adjuntos): array
    {
        if ($adjuntos === []) {
            return [];
        }

        // Un ícono quitado o sin su archivo no se muestra: el panel cae al genérico (la inicial). Cada adjunto es inmutable
        // (reemplazar crea otro), así que su imagen se guarda en caché por id.
        return Adjunto::query()->whereKey($adjuntos)->whereNull('quitado_at')->get()
            ->mapWithKeys(fn (Adjunto $adjunto): array => [$adjunto->id => Cache::remember(
                'icono-aplicacion:'.$adjunto->id,
                now()->addDay(),
                fn (): ?string => ($contenido = Storage::disk($adjunto->disco)->get($adjunto->ruta)) === null ? null : 'data:'.$adjunto->mime.';base64,'.base64_encode($contenido),
            )])
            ->filter()
            ->all();
    }
}
