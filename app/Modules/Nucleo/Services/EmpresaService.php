<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Services;

use App\Modules\Nucleo\Enums\IconoRed;
use App\Modules\Nucleo\Enums\TipoPersona;
use App\Modules\Nucleo\Models\Adjunto;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\RedSocial;
use App\Modules\Nucleo\Models\TipoAdjunto;
use App\Modules\Nucleo\Parties\DatosEmpresa;
use App\Modules\Nucleo\Parties\ResumenRedSocial;
use App\Modules\Nucleo\Parties\ValidadorFichaEmpresa;
use Illuminate\Support\Facades\Storage;

/**
 * Los datos de Techquk (plan 1.13, D2; ADR 0003): los de su ficha en Personas y empresas, la que dice `empresa.ficha`.
 * Cotizaciones y documentos los piden a `datos()` y nunca leen la ficha directamente (plan 1.15, D1).
 */
final readonly class EmpresaService
{
    public const string PARAMETRO = 'empresa.ficha';

    /** El tipo de adjunto del logo de una ficha (plan 1.15, D2): el de la ficha de Techquk sale en los documentos. */
    public const string TIPO_LOGO = 'logo';

    /**
     * La ficha de Techquk, o null si todavía no se eligió. La elegida no se desactiva ni se borra (plan 1.15, D3). Los
     * parámetros se piden aquí y no en el constructor (AGENTS.md: dependencia de un solo método).
     */
    public function ficha(): ?Party
    {
        return ValidadorFichaEmpresa::ficha(app(ParametrosService::class)->texto(self::PARAMETRO));
    }

    /**
     * Si esa party es la ficha elegida (D3), con el parámetro leído de la base: quien desactiva o borra la llama con la
     * fila de la party bloqueada, y quien la elige la lee con bloqueo compartido (`ValidadorFichaEmpresa`). Así, de dos
     * a la vez, el segundo ve lo que hizo el primero.
     */
    public function esLaFicha(Party $party): bool
    {
        // Jurídica, como en ValidadorFichaEmpresa: el número solo no es único entre tipos de documento.
        return $party->tipo === TipoPersona::Juridica && app(ParametrosService::class)->textoReciente(self::PARAMETRO) === $party->numero_documento;
    }

    /** Lo que imprime un documento (D1), o null si todavía no hay ficha elegida. */
    public function datos(): ?DatosEmpresa
    {
        $ficha = $this->ficha();

        if ($ficha === null) {
            return null;
        }

        $sitiosWeb = RedSocial::query()->where('icono', IconoRed::SitioWeb)->pluck('id')->all();
        [$webs, $redes] = collect(app(RedesSocialesService::class)->redesQueVe($ficha->id, null, null))
            ->partition(fn (ResumenRedSocial $red): bool => in_array($red->redSocialId, $sitiosWeb, true));

        return new DatosEmpresa(
            partyId: $ficha->id,
            razonSocial: $ficha->razon_social,
            nombreComercial: $ficha->nombre_comercial,
            ruc: $ficha->numero_documento,
            direccionFiscal: $ficha->direccion_fiscal === null ? null : $ficha->direccion_fiscal.($ficha->ubigeo === null ? '' : ', '.$ficha->ubigeo->etiqueta()),
            telefono: $ficha->telefono,
            correo: $ficha->email,
            sitioWeb: $webs->first()?->enlace,
            redes: $redes->values()->all(),
            logo: $this->logo($ficha),
        );
    }

    /**
     * El logo como `data:`, para incrustarlo en la pantalla y en el PDF (D4), o null si no hay o falta su archivo. Sin
     * caché, a diferencia de los íconos: un logo de hasta 2 MB serían casi 3 MB por fila en la caché de la base, y leer
     * el archivo del disco local es barato.
     */
    public function imagenDelLogo(DatosEmpresa $datos): ?string
    {
        $logo = $datos->logo;
        $contenido = $logo === null ? null : Storage::disk($logo->disco)->get($logo->ruta);

        return $logo === null || $contenido === null ? null : 'data:'.$logo->mime.';base64,'.base64_encode($contenido);
    }

    /** El logo vigente más reciente de la ficha (D2): subir otro lo reemplaza. */
    private function logo(Party $ficha): ?Adjunto
    {
        return Adjunto::query()
            ->where('adjuntable_type', $ficha->getMorphClass())
            ->where('adjuntable_id', $ficha->id)
            ->whereNull('quitado_at')
            ->whereIn('tipo_adjunto_id', TipoAdjunto::query()->where('codigo', self::TIPO_LOGO)->select('id'))
            ->orderByDesc('id')
            ->first();
    }
}
