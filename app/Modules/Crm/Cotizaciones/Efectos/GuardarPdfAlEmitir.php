<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Efectos;

use App\Modules\Crm\Cotizaciones\Models\Cotizacion;
use App\Modules\Crm\Cotizaciones\Services\PdfCotizacion;
use App\Modules\Nucleo\Estados\Efecto;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\ArchivoSubido;
use App\Modules\Nucleo\Services\AdjuntosService;
use App\Modules\Nucleo\Services\ContextoAuditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Al emitir (D20, plan 2.22c), el PDF de la versión se guarda como su adjunto y queda en `adjunto_pdf_id`: lo que se envió
 * al cliente queda igual para siempre, aunque después cambien los datos de Techquk o del cliente. Corre después de
 * congelar (necesita la fecha de emisión y el vencimiento). La emitida ya no es editable, así que el adjunto no se quita.
 */
final readonly class GuardarPdfAlEmitir implements Efecto
{
    public function aplicar(Model $documento): array
    {
        if (! $documento instanceof Cotizacion) {
            return [];
        }

        $pdf = app(PdfCotizacion::class);
        // En la carpeta temporal del sistema: es de las que acepta AdjuntosService.
        $ruta = sys_get_temp_dir().'/cotizacion-'.Str::random(24).'.pdf';

        try {
            file_put_contents($ruta, $pdf->contenido($documento->id));
            $quien = User::query()->findOrFail(app(ContextoAuditoria::class)->idActor());
            $adjunto = app(AdjuntosService::class)->agregar(
                $documento->getMorphClass(), $documento->id,
                new ArchivoSubido($ruta, $pdf->nombreArchivo("{$documento->numero} v{$documento->numero_version}")),
                null, $quien,
            );
        } finally {
            @unlink($ruta);
        }

        $documento->adjunto_pdf_id = $adjunto->id;
        $documento->save();

        return ['adjunto_pdf_id' => $adjunto->id];
    }

    public function revertir(Model $documento, array $datos): void
    {
        // Una emitida no se anula (D5): el PDF es lo que salió al cliente. Nada que deshacer.
    }
}
