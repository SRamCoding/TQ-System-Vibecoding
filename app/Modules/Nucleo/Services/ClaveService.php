<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Services;

use App\Modules\Nucleo\Excepciones\ClaveRechazada;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Notificaciones\CodigoCambioClave;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use SensitiveParameter;
use Throwable;

/**
 * La contraseña de cada persona (plan 1.4h, D3 y D5). Una sola regla para todo el sistema; el cambio voluntario pide un
 * código que llega al correo de acceso; quien entró con una contraseña que no cumple la regla debe renovarla. La
 * contraseña nunca va a la bitácora ni a los registros; el código se guarda cifrado en la caché y caduca solo.
 */
final readonly class ClaveService
{
    public const int MINUTOS_CODIGO = 10;

    public const int INTENTOS = 5;

    public const int SEGUNDOS_REENVIO = 60;

    public function __construct(private Repository $cache) {}

    /** La regla de toda contraseña: al menos 8 caracteres, mayúscula y minúscula, un número y un carácter especial. */
    public static function regla(): Password
    {
        return Password::min(8)->mixedCase()->numbers()->symbols();
    }

    /**
     * Lo que le falta a una contraseña para cumplir la regla, en palabras; vacío si cumple.
     *
     * @return list<string>
     */
    public static function faltas(#[SensitiveParameter] string $clave): array
    {
        $validador = Validator::make(['clave' => $clave], ['clave' => [self::regla()]], [], ['clave' => 'contraseña']);

        return array_values($validador->errors()->get('clave'));
    }

    /**
     * Envía un código nuevo al correo de acceso (y anula el anterior). Devuelve el correo, en parte oculto, para
     * decirle a la persona dónde buscarlo.
     *
     * @throws ClaveRechazada si pidió otro hace menos de un minuto
     */
    public function pedirCodigo(User $usuario): string
    {
        $codigo = $this->conCandado($usuario, function () use ($usuario): string {
            $anterior = $this->cache->get($this->llave($usuario));

            if (is_array($anterior) && ($espera = (int) $anterior['pedido'] + self::SEGUNDOS_REENVIO - now()->getTimestamp()) > 0) {
                throw ClaveRechazada::esperaReenvio($espera);
            }

            $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $vence = now()->addMinutes(self::MINUTOS_CODIGO);
            $this->cache->put($this->llave($usuario), ['hash' => Hash::make($codigo), 'intentos' => 0, 'pedido' => now()->getTimestamp(), 'vence' => $vence->getTimestamp()], $vence);

            return $codigo;
        });

        try {
            $usuario->notify(new CodigoCambioClave($codigo, self::MINUTOS_CODIGO));
        } catch (Throwable $e) {
            // Un código que nadie recibió no debe quedar ni bloquear el reenvío.
            $this->cache->forget($this->llave($usuario));

            throw $e;
        }

        return self::correoOculto($usuario->email);
    }

    /**
     * El cambio voluntario (D3): con el código del correo, sin la contraseña actual. El código sirve una sola vez.
     *
     * @throws ClaveRechazada
     */
    public function cambiarConCodigo(User $usuario, #[SensitiveParameter] string $codigo, #[SensitiveParameter] string $clave, #[SensitiveParameter] string $repetida): void
    {
        $this->validarNueva($usuario, $clave, $repetida);
        $this->gastarCodigo($usuario, $codigo);
        $this->guardar($usuario, $clave);
    }

    /**
     * La renovación obligatoria (D5): sin código, porque la persona acaba de entrar con su contraseña. Solo si su
     * cuenta está marcada.
     *
     * @throws ClaveRechazada
     */
    public function renovar(User $usuario, #[SensitiveParameter] string $clave, #[SensitiveParameter] string $repetida): void
    {
        if (! $usuario->debe_cambiar_clave) {
            throw ClaveRechazada::noDebeRenovar();
        }

        $this->validarNueva($usuario, $clave, $repetida);
        $this->guardar($usuario, $clave);
    }

    /**
     * Al iniciar sesión (D5): si la contraseña escrita no cumple la regla, la cuenta queda marcada; si cumple (la
     * cambió por otro camino), la marca se quita.
     */
    public function revisarAlEntrar(User $usuario, bool $cumple): void
    {
        if ($usuario->debe_cambiar_clave === $cumple) {
            $usuario->forceFill(['debe_cambiar_clave' => ! $cumple])->save();
        }
    }

    /**
     * Restablecida por «¿Olvidaste tu contraseña?» (con la misma regla): ya cumple. Se quita la marca y se anota cuándo.
     */
    public function alRestablecer(User $usuario): void
    {
        $usuario->forceFill(['debe_cambiar_clave' => false, 'clave_cambiada_at' => now()])->save();
    }

    /** `se***@gmail.com`: suficiente para reconocerlo, sin mostrarlo entero. */
    public static function correoOculto(string $correo): string
    {
        [$nombre, $dominio] = array_pad(explode('@', $correo, 2), 2, '');

        return mb_substr($nombre, 0, 2).'***@'.$dominio;
    }

    /** @throws ClaveRechazada */
    private function validarNueva(User $usuario, #[SensitiveParameter] string $clave, #[SensitiveParameter] string $repetida): void
    {
        if ($clave !== $repetida) {
            throw ClaveRechazada::noCoincide();
        }

        $faltas = self::faltas($clave);

        if ($faltas !== []) {
            throw ClaveRechazada::noCumple($faltas);
        }

        if (Hash::check($clave, (string) $usuario->getAuthPassword())) {
            throw ClaveRechazada::igualALaActual();
        }
    }

    /** @throws ClaveRechazada */
    private function gastarCodigo(User $usuario, #[SensitiveParameter] string $codigo): void
    {
        $this->conCandado($usuario, fn () => $this->verificarCodigo($usuario, $codigo));
    }

    /**
     * Bajo el candado de la persona: dos pestañas a la vez no se saltan el límite de intentos ni reviven un código usado.
     *
     * @throws ClaveRechazada
     */
    private function verificarCodigo(User $usuario, #[SensitiveParameter] string $codigo): void
    {
        $guardado = $this->cache->get($this->llave($usuario));

        if (! is_array($guardado)) {
            throw ClaveRechazada::sinCodigo();
        }

        if (! Hash::check(trim($codigo), (string) $guardado['hash'])) {
            $intentos = (int) $guardado['intentos'] + 1;

            if ($intentos >= self::INTENTOS) {
                $this->cache->forget($this->llave($usuario));

                throw ClaveRechazada::codigoAnulado();
            }

            // Con su vencimiento de siempre: un intento fallido no le da más vida al código.
            $this->cache->put($this->llave($usuario), [...$guardado, 'intentos' => $intentos], now()->setTimestamp((int) $guardado['vence']));

            throw ClaveRechazada::codigoIncorrecto(self::INTENTOS - $intentos);
        }

        $this->cache->forget($this->llave($usuario));
    }

    /**
     * Guarda la nueva (cifrada por el modelo), quita la marca y anota cuándo. Las otras sesiones dejan de valer, y el
     * «recordarme» de otros equipos también (se renueva su token, como al restablecerla).
     */
    private function guardar(User $usuario, #[SensitiveParameter] string $clave): void
    {
        DB::transaction(function () use ($usuario, $clave): void {
            $usuario->forceFill(['password' => $clave, 'debe_cambiar_clave' => false, 'clave_cambiada_at' => now(), 'remember_token' => Str::random(60)])->save();
        });
    }

    /**
     * @template T
     *
     * @param  callable(): T  $trabajo
     * @return T
     */
    private function conCandado(User $usuario, callable $trabajo): mixed
    {
        return Cache::lock($this->llave($usuario).'.candado', 10)->block(5, $trabajo);
    }

    private function llave(User $usuario): string
    {
        return "nucleo.codigo-clave.{$usuario->id}";
    }
}
