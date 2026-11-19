<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Filament;

use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\CompetidoresDeOportunidad;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\EtapasDeOportunidad;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\HistorialDeOportunidad;
use App\Modules\Crm\Oportunidades\Pestanas\PestanaOportunidad;
use App\Modules\Crm\Oportunidades\Pestanas\RegistroPestanasOportunidad;
use App\Modules\Nucleo\Models\User;

/**
 * Las capas del despiece de una caja (plan 2.21b, D10; pedido B3): una por pestaña de la ficha. Resumen, Competidores,
 * Historial de etapas e Historial son de Oportunidades; las demás las suman otros módulos con
 * `RegistroPestanasOportunidad`. Las que todavía no tienen módulo (`EN_ESPERA`) salen vacías con su texto de espera, nunca
 * con datos inventados; cuando su módulo registra la pestaña con esa clave, su capa toma el lugar de la vacía. Quien no
 * tiene el permiso de una pestaña registrada no ve su capa (ni la vacía).
 */
final class CapasDelDespiece
{
    /**
     * Las pestañas que esperan su módulo, en un solo lugar: clave de la pestaña (la que registrará el módulo), título y
     * texto de espera.
     */
    public const array EN_ESPERA = [
        'presupuesto' => ['titulo' => 'Presupuesto', 'espera' => 'El presupuesto de esta oportunidad se verá aquí cuando llegue su módulo.'],
        'actividades' => ['titulo' => 'Actividades', 'espera' => 'Las actividades de esta oportunidad se verán aquí cuando llegue su módulo (CRM-05).'],
        'documentos' => ['titulo' => 'Documentos', 'espera' => 'Los documentos de esta oportunidad se verán aquí cuando llegue su módulo.'],
    ];

    /** El orden de las capas, de arriba abajo, como el prototipo; las demás registradas van antes del Historial. */
    public const array ORDEN = ['resumen', 'cotizaciones', 'presupuesto', 'actividades', 'competidores', 'documentos', 'etapas'];

    /** Las claves que son de Oportunidades: una pestaña registrada con una de ellas no suma otra capa. */
    private const array PROPIAS = ['resumen', 'competidores', 'etapas', 'historial'];

    public function __construct(private readonly RegistroPestanasOportunidad $registro) {}

    /**
     * Las capas que ve la persona, en orden. `tipo`: `resumen` (los datos de la oportunidad), `componente` (un componente
     * de Livewire que recibe la oportunidad en `parametro`) o `espera` (vacía, con su texto).
     *
     * @return list<array{clave: string, titulo: string, tipo: string, componente: ?string, parametro: string, espera: ?string}>
     */
    public function para(?User $usuario): array
    {
        $visibles = [];

        foreach ($this->registro->pestanasPara($usuario) as $pestana) {
            $visibles[$pestana->clave] = $pestana;
        }

        $capas = [];

        foreach (self::ORDEN as $clave) {
            $capa = match ($clave) {
                'resumen' => self::capa('resumen', 'Resumen', 'resumen'),
                'competidores' => self::capa('competidores', 'Competidores', 'componente', CompetidoresDeOportunidad::class),
                'etapas' => self::capa('etapas', 'Historial de etapas', 'componente', EtapasDeOportunidad::class),
                default => match (true) {
                    isset($visibles[$clave]) => self::registrada($visibles[$clave]),
                    $this->registro->registrada($clave) => null,
                    // Una clave del orden cuyo módulo ya llegó (sin texto de espera) no deja capa vacía.
                    ! isset(self::EN_ESPERA[$clave]) => null,
                    default => self::capa($clave, self::EN_ESPERA[$clave]['titulo'], 'espera', espera: self::EN_ESPERA[$clave]['espera']),
                },
            };

            if ($capa !== null) {
                $capas[] = $capa;
            }
        }

        foreach ($visibles as $clave => $pestana) {
            if (! in_array($clave, self::ORDEN, true) && ! in_array($clave, self::PROPIAS, true)) {
                $capas[] = self::registrada($pestana);
            }
        }

        $capas[] = self::capa('historial', 'Historial', 'componente', HistorialDeOportunidad::class, 'registroId');

        return $capas;
    }

    /** @return array{clave: string, titulo: string, tipo: string, componente: ?string, parametro: string, espera: ?string} */
    private static function registrada(PestanaOportunidad $pestana): array
    {
        return self::capa($pestana->clave, $pestana->titulo, 'componente', $pestana->nombreLivewire());
    }

    /** @return array{clave: string, titulo: string, tipo: string, componente: ?string, parametro: string, espera: ?string} */
    private static function capa(string $clave, string $titulo, string $tipo, ?string $componente = null, string $parametro = 'oportunidadId', ?string $espera = null): array
    {
        return ['clave' => $clave, 'titulo' => $titulo, 'tipo' => $tipo, 'componente' => $componente, 'parametro' => $parametro, 'espera' => $espera];
    }
}
