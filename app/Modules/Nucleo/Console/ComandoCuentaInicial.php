<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Console;

use App\Modules\Nucleo\Excepciones\ErrorDeNegocio;
use App\Modules\Nucleo\Excepciones\SeguridadRechazada;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Parties\DatosParty;
use App\Modules\Nucleo\Services\SeguridadService;
use App\Modules\Nucleo\Services\UsuarioSistema;
use Illuminate\Console\Command;

/**
 * Lo común de las cuentas que se registran al instalar (primer administrador, plan 1.4 D8; gerente general, plan 1.10
 * D2): pide los datos de la persona, la clave en pantalla y oculta (nunca como opción, para que no quede en el
 * historial de la terminal) y registra la cuenta a nombre del usuario de sistema. Se niega si ya hay una activa.
 */
abstract class ComandoCuentaInicial extends Command
{
    /** Las opciones que comparten: el `$signature` de cada comando las agrega después de su nombre. */
    protected const string OPCIONES = '
        {--tipo=1 : Código SUNAT del documento: 1 DNI, 4 carné de extranjería, 7 pasaporte}
        {--documento= : Número del documento}
        {--nombres= : Nombres de la persona}
        {--apellidos= : Apellidos de la persona}
        {--email= : Correo con el que inicia sesión}';

    /** Revisión rápida para no pedir la clave en vano; la que vale la repite el servicio, bajo el candado. */
    abstract protected function yaHay(SeguridadService $seguridad): ?SeguridadRechazada;

    abstract protected function registrar(SeguridadService $seguridad, DatosParty $persona, string $email, string $clave, User $sistema): int;

    /** «Administrador creado», «Gerente general registrado»: lo que se dice al terminar, antes del correo. */
    abstract protected function hecho(): string;

    public function handle(SeguridadService $seguridad, UsuarioSistema $sistema): int
    {
        $yaHay = $this->yaHay($seguridad);

        if ($yaHay !== null) {
            $this->error($yaHay->getMessage());

            return self::FAILURE;
        }

        $tipo = (string) $this->option('tipo');
        $documento = $this->dato('documento', 'Número de documento');
        $email = $this->dato('email', 'Correo');
        $clave = (string) $this->secret('Clave (al menos 12 caracteres, con mayúscula, minúscula, número y carácter especial)');

        if ($clave !== (string) $this->secret('Repite la clave')) {
            $this->error('Las claves no coinciden.');

            return self::FAILURE;
        }

        try {
            $usuarioId = $this->registrar(
                $seguridad,
                new DatosParty($tipo, $documento, nombres: $this->dato('nombres', 'Nombres'), apellidos: $this->dato('apellidos', 'Apellidos')),
                $email,
                $clave,
                User::query()->findOrFail($sistema->id()),
            );
        } catch (ErrorDeNegocio $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info($this->hecho().': '.User::query()->whereKey($usuarioId)->value('email').'.');

        return self::SUCCESS;
    }

    private function dato(string $opcion, string $pregunta): string
    {
        $valor = trim((string) $this->option($opcion));

        return $valor !== '' ? $valor : trim((string) $this->ask($pregunta));
    }
}
