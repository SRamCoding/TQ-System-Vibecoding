<?php

declare(strict_types=1);

use App\Modules\Nucleo\Adjuntos\ValidadorTamanoAdjunto;

/*
 * La imagen de producción (plan 1.12b): lo que, si se desarregla en `docker/produccion/`, no lo nota ninguna otra prueba.
 */

function archivoDeProduccion(string $nombre): string
{
    return (string) file_get_contents(dirname(__DIR__, 2).'/docker/produccion/'.$nombre);
}

test('el techo de subida es el mismo en nginx, PHP y Livewire', function () {
    $techo = ValidadorTamanoAdjunto::TECHO_MB;
    preg_match('/client_max_body_size\s+(\d+)M;/', archivoDeProduccion('nginx.conf'), $nginx);
    preg_match('/^upload_max_filesize\s*=\s*(\d+)M/m', archivoDeProduccion('php.ini'), $archivo);
    preg_match('/^post_max_size\s*=\s*(\d+)M/m', archivoDeProduccion('php.ini'), $formulario);

    expect((int) $archivo[1])->toBe($techo)
        ->and((int) $formulario[1])->toBeGreaterThan($techo)
        // nginx mide el pedido entero, como post_max_size: un archivo del techo más el resto del formulario.
        ->and((int) $nginx[1])->toBe((int) $formulario[1])
        ->and((require dirname(__DIR__, 2).'/config/livewire.php')['temporary_file_upload']['rules'])->toContain('max:'.($techo * 1024));
});

test('el compose fija producción, no baja imágenes de un registro y no le pasa los secretos a la base', function () {
    $compose = archivoDeProduccion('compose.yml');
    $base = substr($compose, (int) strpos($compose, "\n    postgres:"));

    expect($compose)->toContain('APP_ENV: production')
        ->toContain('APP_DEBUG: "false"')
        ->toContain('pull_policy: never')
        ->and(substr_count($compose, 'pull_policy: build'))->toBe(2)
        ->and($base)->not->toContain('env_file');
});

test('las dos colas corren con los comandos de requisitos-servidor.md y nadie migra al arrancar', function () {
    $compose = archivoDeProduccion('compose.yml');

    expect($compose)->toContain('"queue:work", "database", "--queue=default"')
        ->toContain('"queue:work", "ia", "--queue=ia", "--timeout=660"')
        ->toContain('"schedule:work"')
        ->and($compose.archivoDeProduccion('arrancar.sh').archivoDeProduccion('Dockerfile'))->not->toContain('migrate');
});

test('la imagen exige PHP 8.5.12 o posterior y pg_dump 16', function () {
    $dockerfile = archivoDeProduccion('Dockerfile');

    expect($dockerfile)->toContain('version_compare(PHP_VERSION, "8.5.12", ">=")')
        ->toContain('postgresql-client-16')
        ->toMatch('/^FROM php:8\.5\.(1[2-9]|[2-9]\d)-fpm/m');
});
