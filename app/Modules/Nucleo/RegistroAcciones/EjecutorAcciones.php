<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\RegistroAcciones;

use App\Modules\Nucleo\Excepciones\AccionRechazada;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\ContextoAuditoria;
use Illuminate\Contracts\Validation\Factory as Validador;
use Illuminate\Validation\ValidationException;

/**
 * Única puerta para ejecutar una acción, venga de Filament, de una prueba o de una API futura:
 * revisa quién la pide, su permiso y sus campos, y la ejecuta a su nombre (M4).
 */
final class EjecutorAcciones
{
    public function __construct(
        private readonly RegistroAcciones $registro,
        private readonly ContextoAuditoria $contexto,
        private readonly Validador $validador,
    ) {}

    /**
     * @param  Accion|string  $accion  la acción o su clave registrada
     * @param  array<string, mixed>  $datos  lo que llega del formulario o de la petición
     *
     * @throws AccionRechazada
     * @throws ValidationException
     */
    public function ejecutar(Accion|string $accion, array $datos, User $usuario): mixed
    {
        $accion = is_string($accion) ? $this->registro->obtener($accion) : $accion;

        $this->autorizar($accion, $usuario);
        $validados = $this->validar($accion, $datos);

        return $this->contexto->comoUsuario($usuario, fn (): mixed => $accion->ejecutar($validados, $usuario));
    }

    private function autorizar(Accion $accion, User $usuario): void
    {
        if ($usuario->es_sistema) {
            throw AccionRechazada::usuarioDeSistema();
        }

        // Se relee de la base: un usuario dado de baja después de cargarse (API futura) no pasa.
        $activo = User::query()->whereKey($usuario->getKey())->value('es_activo');

        if ($activo !== true) {
            throw AccionRechazada::usuarioInactivo();
        }

        $permiso = $accion->permiso();

        // Con ámbito, lo revisa el servicio, que conoce el registro (plan 1.5, D6).
        if ($permiso !== null && ! $accion->permisoPorAmbito() && ! $usuario->can($permiso)) {
            throw AccionRechazada::sinPermiso($accion->nombre());
        }
    }

    /**
     * Solo pasan los campos declarados, convertidos a su tipo.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function validar(Accion $accion, array $datos): array
    {
        $reglas = [];
        $etiquetas = [];

        foreach ($accion->campos() as $campo) {
            $reglas[$campo->nombre] = $campo->reglasDeValidacion();
            $etiquetas[$campo->nombre] = $campo->etiqueta;

            if ($campo->reglasDeElementos() !== null) {
                $reglas["{$campo->nombre}.*"] = $campo->reglasDeElementos();
                $etiquetas["{$campo->nombre}.*"] = $campo->etiqueta;
            }
        }

        $validados = $this->validador->make($datos, $reglas, [], $etiquetas)->validate();
        $convertidos = [];

        foreach ($accion->campos() as $campo) {
            $convertidos[$campo->nombre] = $campo->convertir($validados[$campo->nombre] ?? null);
        }

        return $convertidos;
    }
}
