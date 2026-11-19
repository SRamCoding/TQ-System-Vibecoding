<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Requisitos;

use InvalidArgumentException;

/**
 * Lo que cada etapa exige (plan 2.21, D9), con los requisitos de todos los módulos. Se acumulan: al avanzar se revisan
 * todos los exigidos hasta la etapa destino, no solo los de esa transición (como el panel «Avanzar» del v4). Nunca se
 * revisan hacia atrás: una oportunidad que deja de cumplir uno no retrocede sola; solo no puede avanzar.
 */
final class RequisitosDeEtapa
{
    /** El orden del embudo hacia adelante: `ganada` está después de la última etapa abierta. */
    public const array ORDEN = ['prospecto', 'calificada', 'propuesta_enviada', 'negociacion', 'ganada'];

    /** @var list<RequisitoDeEtapa> */
    private array $requisitos = [];

    public function registrar(RequisitoDeEtapa $requisito): void
    {
        if (! in_array($requisito->exigidoAlEntrarEn(), array_slice(self::ORDEN, 1), true)) {
            throw new InvalidArgumentException("El requisito «{$requisito->texto()}» se exige desde una etapa que no existe: «{$requisito->exigidoAlEntrarEn()}».");
        }

        $this->requisitos[] = $requisito;
    }

    /**
     * Los que se exigen para llegar a la etapa destino, en el orden del embudo, cada uno con si se cumple: lo que muestra
     * el panel «Avanzar».
     *
     * Plan 2.21d, D8: cada uno trae también su clase (`requisito`) y, si falta y se puede cumplir ahí mismo, su forma.
     *
     * @return list<array{texto: string, cumple: bool, requisito: class-string<RequisitoDeEtapa>, forma: ?FormaDeCumplir}>
     */
    public function revisar(int $oportunidadId, string $destino): array
    {
        $hasta = array_search($destino, self::ORDEN, true);

        if ($hasta === false) {
            return [];
        }

        $exigidos = array_filter(
            $this->requisitos,
            fn (RequisitoDeEtapa $r): bool => array_search($r->exigidoAlEntrarEn(), self::ORDEN, true) <= $hasta
                && (! $r instanceof RequisitoApagable || $r->activo()),
        );
        usort($exigidos, fn (RequisitoDeEtapa $a, RequisitoDeEtapa $b): int => array_search($a->exigidoAlEntrarEn(), self::ORDEN, true) <=> array_search($b->exigidoAlEntrarEn(), self::ORDEN, true));

        return array_map(function (RequisitoDeEtapa $r) use ($oportunidadId): array {
            $cumple = $r->cumple($oportunidadId);

            return [
                'texto' => $r->texto(),
                'cumple' => $cumple,
                'requisito' => $r::class,
                'forma' => ! $cumple && $r instanceof SeCumpleEnLaCompuerta ? $r->formaDeCumplir($oportunidadId) : null,
            ];
        }, $exigidos);
    }

    /** El requisito registrado de esa clase, para cumplirlo desde la compuerta; null si no está o no se cumple ahí. */
    public function paraCumplir(string $clase): ?SeCumpleEnLaCompuerta
    {
        foreach ($this->requisitos as $requisito) {
            if ($requisito::class === $clase && $requisito instanceof SeCumpleEnLaCompuerta) {
                return $requisito;
            }
        }

        return null;
    }

    /** @return list<string> el texto de los que faltan */
    public function faltantes(int $oportunidadId, string $destino): array
    {
        return array_values(array_map(
            fn (array $r): string => $r['texto'],
            array_filter($this->revisar($oportunidadId, $destino), fn (array $r): bool => ! $r['cumple']),
        ));
    }
}
