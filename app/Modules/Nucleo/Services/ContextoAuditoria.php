<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Services;

use App\Modules\Nucleo\Excepciones\AutorDesconocido;
use App\Modules\Nucleo\Models\User;
use Closure;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Http\Request;

/**
 * Quién hace el cambio que se está guardando (M4) y desde dónde. Orden:
 * actor fijado por el ejecutor de acciones › usuario autenticado › usuario de sistema en consola
 * y cola › el que proponga el modelo › excepción. Nunca se guarda un cambio sin autor.
 */
final class ContextoAuditoria
{
    /** @var list<int> */
    private array $actores = [];

    /**
     * @param  Closure(): ?Request  $peticion
     */
    public function __construct(
        private readonly Auth $auth,
        private readonly UsuarioSistema $sistema,
        private readonly bool $enConsola,
        private readonly Closure $peticion,
    ) {}

    /**
     * @param  ?int  $porDefecto  autor que propone el modelo cuando no hay otro (ver ConAuditoria)
     */
    public function idActor(?int $porDefecto = null): int
    {
        if ($this->actores !== []) {
            return $this->actores[array_key_last($this->actores)];
        }

        $autenticado = $this->auth->guard()->id();

        if ($autenticado !== null) {
            return (int) $autenticado;
        }

        if ($this->enConsola) {
            return $this->sistema->id();
        }

        return $porDefecto ?? throw AutorDesconocido::crear();
    }

    /**
     * Ejecuta $tarea a nombre de $usuario, sin importar quién tenga la sesión.
     *
     * @template T
     *
     * @param  Closure(): T  $tarea
     * @return T
     */
    public function comoUsuario(User $usuario, Closure $tarea): mixed
    {
        $this->actores[] = (int) $usuario->getKey();

        try {
            return $tarea();
        } finally {
            array_pop($this->actores);
        }
    }

    /** IP de la petición web; en consola y cola no hay. */
    public function ip(): ?string
    {
        return $this->enConsola ? null : ($this->peticion)()?->ip();
    }

    /** Navegador o cliente de la petición web, recortado a 255. */
    public function agente(): ?string
    {
        $agente = $this->enConsola ? null : ($this->peticion)()?->userAgent();

        return $agente === null ? null : mb_substr($agente, 0, 255);
    }
}
