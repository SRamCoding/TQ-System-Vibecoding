<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Historico;

/**
 * El cifrado del archivo histórico (plan 1.4y, D6): AES-256-GCM, con una clave derivada de la de los respaldos
 * (`BACKUP_ARCHIVE_PASSWORD`, que TI guarda fuera del servidor) con PBKDF2-SHA256 y una sal propia de cada archivo
 * (revisión de arquitectura: un solo hash no frenaba la fuerza bruta ante una contraseña débil). El archivo empieza con
 * `TQH2` y sigue la sal (16 bytes), el vector inicial (12), la etiqueta de autenticación (16) y el contenido cifrado.
 * Con la etiqueta, un archivo alterado no se descifra.
 *
 * Si TI cambia la contraseña, los archivos anteriores siguen cifrados con la vieja: se leen con
 * `nucleo:leer-historico --otra-clave` (`docs/operaciones/respaldos.md`).
 */
final class CifradoHistorico
{
    private const string MARCA = 'TQH2';

    private const int ITERACIONES = 600_000;

    public function cifrar(string $contenido): string
    {
        $sal = random_bytes(16);
        $iv = random_bytes(12);
        $etiqueta = '';
        $cifrado = openssl_encrypt($contenido, 'aes-256-gcm', $this->clave($sal), OPENSSL_RAW_DATA, $iv, $etiqueta);

        if ($cifrado === false) {
            throw HistoricoFallido::ilegible();
        }

        return self::MARCA.$sal.$iv.$etiqueta.$cifrado;
    }

    /** @param ?string $contrasena otra que la actual (la de antes de un cambio); nula, la de `BACKUP_ARCHIVE_PASSWORD` */
    public function descifrar(string $archivo, ?string $contrasena = null): string
    {
        if (! str_starts_with($archivo, self::MARCA) || strlen($archivo) < 48) {
            throw HistoricoFallido::ilegible();
        }

        $contenido = openssl_decrypt(substr($archivo, 48), 'aes-256-gcm', $this->clave(substr($archivo, 4, 16), $contrasena), OPENSSL_RAW_DATA, substr($archivo, 20, 12), substr($archivo, 32, 16));

        return $contenido === false ? throw HistoricoFallido::ilegible() : $contenido;
    }

    private function clave(string $sal, ?string $contrasena = null): string
    {
        $contrasena ??= (string) config('backup.backup.password');

        return $contrasena === '' ? throw HistoricoFallido::sinClave() : hash_pbkdf2('sha256', $contrasena, $sal, self::ITERACIONES, 32, true);
    }
}
