<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Services;

use App\Modules\Crm\Cotizaciones\Models\Cotizacion;
use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Parties\ResumenContacto;
use App\Modules\Nucleo\Services\AdjuntosService;
use App\Modules\Nucleo\Services\EmpresaService;
use App\Modules\Nucleo\Services\PartiesService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * El documento impreso de una cotización (D20, plan 2.22c): el encabezado de Techquk del núcleo, el cliente, las líneas,
 * los totales, las condiciones y la cuenta bancaria en la moneda de la cotización. Sale del resumen de C3
 * (`CotizacionesService::resumen`), que no trae costo ni margen: el PDF no los puede mostrar (D15). Al emitir se guarda
 * como adjunto (`GuardarPdfAlEmitir`); antes, la ficha lo dibuja al vuelo como vista previa, marcado como borrador.
 */
final readonly class PdfCotizacion
{
    /** El PDF como bytes. */
    public function contenido(int $cotizacionId): string
    {
        // Solo las letras usadas de la fuente (sin esto, ~850 KB por PDF) y nada de fuera: el HTML trae el logo incrustado.
        return Pdf::setOption(['isFontSubsettingEnabled' => true, 'isRemoteEnabled' => false])
            ->loadHTML($this->html($cotizacionId))->setPaper('a4')->output();
    }

    /** Lo que dibuja el PDF, como HTML: las pruebas leen aquí lo que dice (el PDF va comprimido). */
    public function html(int $cotizacionId): string
    {
        $cotizacion = app(CotizacionesService::class)->resumen($cotizacionId);
        $cuenta = app(CuentasService::class)->resumen($cotizacion->cuentaId);
        $empresa = app(EmpresaService::class)->datos();
        $parties = app(PartiesService::class);
        $contactoId = Cotizacion::query()->whereKey($cotizacionId)->value('contacto_id');
        $contactoId = $contactoId === null ? null : (int) $contactoId;
        $atencion = $contactoId === null ? null : collect($parties->contactosVigentes($cuenta->partyId))
            ->first(fn (ResumenContacto $c): bool => $c->id === $contactoId)?->etiqueta;

        return view('cotizaciones::pdf.cotizacion', [
            'cotizacion' => $cotizacion,
            'cliente' => $cuenta->etiqueta,
            'atencion' => $atencion,
            'empresa' => $empresa,
            'logo' => $empresa === null ? null : app(EmpresaService::class)->imagenDelLogo($empresa),
            'cuentaBancaria' => $empresa === null ? null : $parties->cuentaPrincipal($empresa->partyId, $cotizacion->moneda),
            'esBorrador' => $cotizacion->fechaEmision === null,
        ])->render();
    }

    /**
     * Lo que muestra la ficha (D20): el PDF guardado al emitir, si lo hay (con el permiso de ver sus adjuntos), o el de
     * ahora, marcado como borrador. Nunca se guarda desde aquí.
     *
     * @return array{contenido: string, adjuntoId: ?int}
     */
    public function paraVer(int $cotizacionId, User $usuario): array
    {
        $adjuntoId = Cotizacion::query()->whereKey($cotizacionId)->value('adjunto_pdf_id');

        if ($adjuntoId === null) {
            return ['contenido' => $this->contenido($cotizacionId), 'adjuntoId' => null];
        }

        $adjunto = app(AdjuntosService::class)->paraDescargar((int) $adjuntoId, $usuario);

        return ['contenido' => (string) Storage::disk($adjunto->disco)->get($adjunto->ruta), 'adjuntoId' => $adjunto->id];
    }

    /** «COT-2026-0012 v2.pdf». */
    public function nombreArchivo(string $identificador): string
    {
        return "{$identificador}.pdf";
    }
}
