<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Alertas;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\AlertasService;

/**
 * Configura un tipo de alerta (plan 1.10, D7): a qué roles llega, si está activa, sus días de anticipación y sus textos,
 * que conservan siempre sus datos. Las reglas están en `AlertasService::configurar()`.
 */
final class ConfigurarAlerta extends Accion
{
    public const string PERMISO = 'alertas.configurar';

    public function clave(): string
    {
        return 'nucleo.alertas.configurar';
    }

    public function nombre(): string
    {
        return 'Configurar una alerta';
    }

    public function campos(): array
    {
        return [
            Campo::texto('alerta', 'Alerta', 80),
            Campo::listaDeIds('roles', 'Quiénes la reciben', 'roles', 'name'),
            Campo::booleano('es_activa', 'Activa'),
            Campo::texto('anticipacion_dias', 'Días de anticipación', 40)->opcional(),
            Campo::texto('asunto', 'Asunto', 200),
            Campo::texto('texto', 'Texto', 2000),
            Campo::entero('version', 'Versión')->conReglas('min:1')->deControl(),
        ];
    }

    public function permiso(): string
    {
        return self::PERMISO;
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        app(AlertasService::class)->configurar(
            $datos['alerta'],
            $datos['roles'],
            $datos['es_activa'],
            $datos['anticipacion_dias'],
            $datos['asunto'],
            $datos['texto'],
            $datos['version'],
        );

        return null;
    }
}
