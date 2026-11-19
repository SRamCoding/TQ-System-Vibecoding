<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Aprobaciones;

use App\Modules\Nucleo\Enums\FirmanteAprobacion;
use App\Modules\Nucleo\Models\FirmaAprobacion;
use App\Modules\Nucleo\Models\RondaAprobacion;
use App\Modules\Nucleo\Models\UnidadOrganizativa;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\OrganigramaService;

/**
 * Firma la jefatura de la unidad del paso; si está vacante, la de la unidad de arriba, y así hasta la raíz (plan 1.7,
 * D1; plan 1.4, D5). Quien pidió la aprobación no firma su propio paso: también sube (D6).
 */
final readonly class JefaturaQueSube implements ResolutorDeFirmante
{
    public function __construct(private OrganigramaService $organigrama) {}

    public function firmante(FirmaAprobacion $firma, RondaAprobacion $ronda): ?User
    {
        return $this->subir($this->unidadDelPaso($firma, $ronda), $ronda->solicitante_user_id);
    }

    public function titular(FirmaAprobacion $firma, RondaAprobacion $ronda): ?User
    {
        return $this->subir($this->unidadDelPaso($firma, $ronda), null);
    }

    public function descripcion(FirmaAprobacion $firma, RondaAprobacion $ronda): string
    {
        $unidad = $this->unidadDelPaso($firma, $ronda);
        $nombre = $unidad === null ? null : UnidadOrganizativa::query()->whereKey($unidad)->value('nombre');

        return $nombre === null ? 'la jefatura del documento' : "la jefatura de {$nombre}";
    }

    private function unidadDelPaso(FirmaAprobacion $firma, RondaAprobacion $ronda): ?int
    {
        return $firma->firmante === FirmanteAprobacion::UnidadFija ? $firma->unidad_organizativa_id : $ronda->unidad_organizativa_id;
    }

    private function subir(?int $unidad, ?int $salvo): ?User
    {
        $visitadas = [];

        while ($unidad !== null && ! isset($visitadas[$unidad])) {
            $visitadas[$unidad] = true;
            $jefe = $this->organigrama->jefeDe($unidad);

            if ($jefe !== null && $jefe->id !== $salvo) {
                return $jefe;
            }

            $superior = UnidadOrganizativa::query()->whereKey($unidad)->value('parent_id');
            $unidad = $superior === null ? null : (int) $superior;
        }

        return null;
    }
}
