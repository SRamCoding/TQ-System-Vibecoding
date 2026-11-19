<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Services;

use App\Modules\Nucleo\Excepciones\PreferenciaRechazada;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Models\PreferenciaUsuario;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Preferencias\DefinicionPreferencia;
use App\Modules\Nucleo\Preferencias\FuenteDeLista;
use App\Modules\Nucleo\Preferencias\GrupoPreferencia;
use App\Modules\Nucleo\Preferencias\RegistroPreferencias;
use App\Modules\Nucleo\Preferencias\TipoPreferencia;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Preferencias de interfaz de cada usuario (plan 1.4g): único lugar que las lee y las escribe, desde el registro
 * (`RegistroPreferencias`). Además, el cumpleaños (plan 1.4b, D6; no es preferencia: pasa a «Mis datos» en la 1.4h).
 * Lo invocan sus acciones; ninguna mueve dinero ni permisos. El saludo no se configura (plan 1.4aa, D5).
 */
final class PreferenciasUsuarioService
{
    /** @var array<int, array<string, string>> las filas de `preferencias_usuario` leídas en esta petición, por persona */
    private array $guardadas = [];

    public function __construct(private readonly RegistroPreferencias $registro) {}

    /**
     * El valor de una preferencia para la persona, en su tipo (plan 1.4g, D2): lo que guardó o, sin fila, el defecto.
     * Un valor guardado que ya no vale (cambiaron las opciones o perdió el permiso de un módulo) cuenta como el defecto.
     * Una lista la entrega su módulo.
     *
     * @return bool|int|string|list<string>
     */
    public function valor(User $usuario, string $clave): bool|int|string|array
    {
        $definicion = $this->registro->definicion($clave) ?? throw new LogicException("La preferencia «{$clave}» no está declarada.");

        if ($definicion->tipo === TipoPreferencia::Lista) {
            return $this->fuente($definicion)->valores($usuario);
        }

        $guardado = $this->guardadasDe($usuario)[$clave] ?? null;
        $valor = $guardado === null ? null : $definicion->validar($guardado, $usuario);

        return $valor ?? $definicion->defecto ?? throw new LogicException("La preferencia «{$clave}» no tiene defecto.");
    }

    /** Una preferencia sí/no, en su tipo. */
    public function siNo(User $usuario, string $clave): bool
    {
        $valor = $this->valor($usuario, $clave);

        return is_bool($valor) ? $valor : throw new LogicException("La preferencia «{$clave}» no es sí/no.");
    }

    /** Una preferencia numérica, en su tipo. */
    public function numero(User $usuario, string $clave): int
    {
        $valor = $this->valor($usuario, $clave);

        return is_int($valor) ? $valor : throw new LogicException("La preferencia «{$clave}» no es un número.");
    }

    /** Una preferencia de opción, en su tipo. */
    public function opcion(User $usuario, string $clave): string
    {
        $valor = $this->valor($usuario, $clave);

        return is_string($valor) ? $valor : throw new LogicException("La preferencia «{$clave}» no es una opción.");
    }

    /**
     * Cambia una preferencia de la persona (la acción general, D3). Solo claves registradas, solo valores que el
     * registro acepta; una lista se cambia con su propia acción. Si el valor es el defecto, se borra la fila: solo se
     * guarda lo que la persona cambió. La última escritura gana, sin versión.
     *
     * @throws PreferenciaRechazada
     */
    public function cambiar(User $usuario, string $clave, mixed $entrada): void
    {
        $definicion = $this->registro->definicion($clave) ?? throw PreferenciaRechazada::claveDesconocida();

        if ($definicion->tipo === TipoPreferencia::Lista) {
            throw PreferenciaRechazada::seCambiaConSuAccion($definicion->etiqueta);
        }

        $valor = $definicion->validar($entrada, $usuario) ?? throw PreferenciaRechazada::valorInvalido($definicion->etiqueta);

        DB::transaction(function () use ($usuario, $clave, $definicion, $valor): void {
            if ($valor === $definicion->defecto) {
                PreferenciaUsuario::query()->where('user_id', $usuario->id)->where('clave', $clave)->delete();

                return;
            }

            // «insert … on conflict do nothing» y después se bloquea: dos primeros guardados a la vez no chocan.
            $texto = $definicion->tipo->aTexto($valor);
            PreferenciaUsuario::query()->insertOrIgnore(['user_id' => $usuario->id, 'clave' => $clave, 'valor' => $texto, 'created_by' => $usuario->id, 'created_at' => now()]);
            $fila = PreferenciaUsuario::query()->where('user_id', $usuario->id)->where('clave', $clave)->lockForUpdate()->firstOrFail();

            if ($fila->valor !== $texto) {
                $fila->valor = $texto;
                $fila->save();
            }
        });

        unset($this->guardadas[$usuario->id]);
    }

    /**
     * Vuelve un grupo a como viene (D6): borra lo que la persona cambió en él y vacía sus listas. No toca otros grupos.
     */
    public function restablecer(User $usuario, GrupoPreferencia $grupo): void
    {
        $definiciones = $this->registro->delGrupo($grupo);

        DB::transaction(function () use ($usuario, $definiciones): void {
            PreferenciaUsuario::query()->where('user_id', $usuario->id)
                ->whereIn('clave', array_map(fn (DefinicionPreferencia $definicion): string => $definicion->clave(), $definiciones))
                ->delete();

            foreach ($definiciones as $definicion) {
                if ($definicion->tipo === TipoPreferencia::Lista) {
                    $this->fuente($definicion)->restablecer($usuario);
                }
            }
        });

        unset($this->guardadas[$usuario->id]);
    }

    /** @return array<string, string> */
    private function guardadasDe(User $usuario): array
    {
        return $this->guardadas[$usuario->id] ??= PreferenciaUsuario::query()->where('user_id', $usuario->id)->pluck('valor', 'clave')->all();
    }

    /** El módulo dueño de una preferencia de lista. */
    public function fuente(DefinicionPreferencia $definicion): FuenteDeLista
    {
        $fuente = app($definicion->fuente ?? throw new LogicException("La lista «{$definicion->clave()}» no dice su fuente."));

        return $fuente instanceof FuenteDeLista ? $fuente : throw new LogicException("La fuente de «{$definicion->clave()}» no es una FuenteDeLista.");
    }

    /**
     * Día y mes, sin año, o los dos nulos para borrarlo. La propia persona lo cambia sin versión (es su dato); quien
     * gestiona usuarios lo cambia desde la ficha con la versión que leyó al abrirla. Sube la versión de la cuenta y no
     * entra en la bitácora (D6).
     *
     * @throws PreferenciaRechazada|VersionDesactualizada
     */
    public function registrarCumpleanos(int $usuarioId, ?int $dia, ?int $mes, ?int $versionLeida = null): void
    {
        if (($dia === null) !== ($mes === null)) {
            throw PreferenciaRechazada::cumpleanosIncompleto();
        }

        // 2024 es bisiesto: el 29 de febrero vale.
        if ($dia !== null && $mes !== null && ! checkdate($mes, $dia, 2024)) {
            throw PreferenciaRechazada::cumpleanosInvalido();
        }

        DB::transaction(function () use ($usuarioId, $dia, $mes, $versionLeida): void {
            $usuario = User::query()->lockForUpdate()->find($usuarioId) ?? throw PreferenciaRechazada::usuarioNoExiste();

            if ($versionLeida !== null && $usuario->version !== $versionLeida) {
                throw VersionDesactualizada::de("la cuenta de {$usuario->name}");
            }

            $usuario->forceFill(['cumpleanos_dia' => $dia, 'cumpleanos_mes' => $mes]);

            if ($usuario->isDirty()) {
                $usuario->version++;
                $usuario->save();
            }
        });
    }
}
