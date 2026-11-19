<?php

declare(strict_types=1);

namespace App\Modules\Erp\Contratos\Resumen;

use App\Modules\Erp\Contratos\Filament\Resources\Contratos\ContratoResource;
use App\Modules\Erp\Contratos\Filament\Resources\Contratos\Pages\VerContrato;
use App\Modules\Erp\Contratos\Models\Contrato;
use App\Modules\Erp\Contratos\Services\ContratosService;
use App\Modules\Erp\Proyectos\Resumen\AvisoResumen;
use App\Modules\Erp\Proyectos\Resumen\ContenidoTarjeta;
use App\Modules\Erp\Proyectos\Resumen\LineaResumen;
use App\Modules\Erp\Proyectos\Resumen\SeccionResumen;
use App\Modules\Erp\Proyectos\Resumen\TarjetaResumen;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;

/** MVP rápido (t2-contratos): «El contrato» en el tablero del proyecto: plazo, penalidad diaria y garantía. */
final class TarjetaContrato implements TarjetaResumen
{
    public function clave(): string
    {
        return 'contrato';
    }

    public function seccion(): SeccionResumen
    {
        return SeccionResumen::Plazo;
    }

    public function orden(): int
    {
        return 5;
    }

    public function exigeCostos(): bool
    {
        return false;
    }

    public function contenido(int $proyectoId, User $usuario): ?ContenidoTarjeta
    {
        $c = Contrato::query()->where('proyecto_id', $proyectoId)->where('estado', '!=', 'anulado')->first();

        if ($c === null || ! $usuario->can(ContratosService::PERMISO_VER)) {
            return null;
        }

        $s = app(ContratosService::class);
        $url = VerContrato::getUrl(['record' => $c]);
        $g = $c->garantias()->whereIn('estado', ['vigente', 'renovada'])->orderBy('vencimiento')->first();
        $avisos = [];

        if ($c->estado === 'borrador') {
            $avisos[] = new AvisoResumen("El contrato {$c->codigo} está en borrador: falta completarlo y activarlo.", 'warning', $url);
        }

        if ($g !== null && (int) CarbonImmutable::parse(Calendario::hoy())->diffInDays($g->vencimiento, false) <= 30) {
            $avisos[] = new AvisoResumen('La carta fianza vence el '.$g->vencimiento->format('d/m/Y').': renovarla.', 'danger', $url);
        }

        $aplicada = $s->penalidadAplicada($c);

        return new ContenidoTarjeta("Contrato {$c->codigo}", array_values(array_filter([
            new LineaResumen('Plazo', ContratoResource::dias($c), str_starts_with(ContratoResource::dias($c), 'vencido') ? 'danger' : null),
            new LineaResumen('Penalidad diaria', ContratoResource::soles($s->penalidadDiaria($c)), null, [], 'tope '.ContratoResource::soles($s->tope($c))),
            bccomp($aplicada, '0', 2) > 0 ? new LineaResumen('Penalidad aplicada', ContratoResource::soles($aplicada), 'danger') : null,
            new LineaResumen('Garantía', $g === null ? 'Sin garantía vigente' : ContratoResource::soles($g->monto).' · vence '.$g->vencimiento->format('d/m/Y')),
        ])), $avisos);
    }
}
