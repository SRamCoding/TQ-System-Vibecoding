<?php

declare(strict_types=1);

return [

    /*
     * Lista oficial de distritos del INEI (Plataforma Nacional de Datos Abiertos). Si el portal mueve
     * el archivo, se cambia aquí o con UBIGEOS_URL; `nucleo:actualizar-csv-ubigeos` avisa si la descarga
     * falla o si el formato cambió.
     */
    'ubigeos' => [
        'url' => env('UBIGEOS_URL', 'https://www.datosabiertos.gob.pe/sites/default/files/UBIGEODISTRITOS.XLSX'),
        'csv' => app_path('Modules/Nucleo/database/data/ubigeos.csv'),
        // Una descarga con menos distritos se rechaza: probablemente llegó cortada.
        'minimo_distritos' => 1800,
    ],

    /*
     * Salud del sistema (plan 1.12, D7): la URL de Healthchecks para el latido del programador. La configura TI; vacía,
     * el latido solo se anota en la base.
     */
    'salud' => [
        'latido_url' => env('SALUD_LATIDO_URL', ''),
    ],

    /*
     * Tipo de cambio automático (ADR 0012). SUNAT publica el TC del día en este archivo de texto.
     * `horas`: cuándo se consulta (hora de Lima); repetir no duplica y nunca pisa un valor manual. SUNAT no avisa
     * cuando publica: plan 1.10, cada 30 minutos de 07:00 a 19:00, para que entre a más tardar media hora después y un
     * día no se pierda (SUNAT solo da el del día). La última hora es la del aviso si todavía falta.
     */
    'tipo_cambio' => [
        'url_sunat' => env('TIPO_CAMBIO_SUNAT_URL', 'https://www.sunat.gob.pe/a/txt/tipoCambio.txt'),
        'horas' => array_map(fn (int $minuto): string => sprintf('%02d:%02d', intdiv($minuto, 60), $minuto % 60), range(7 * 60, 19 * 60, 30)),
    ],

    /*
     * Modo desarrollo (plan 1.4i, D7): una de sus tres llaves, junto con APP_ENV=local y APP_DEBUG=true. Solo la pone el
     * desarrollador en su .env; el servidor nunca la tiene. Con APP_ENV=production se ignora y se avisa.
     */
    // Solo «true» la prende: cualquier otro valor (no, off, 0, vacío) la deja apagada.
    'modo_desarrollo' => env('TECHQUK_MODO_DESARROLLO') === true,

    // Plan 1.10, D2: sin gerente general activo el panel no arranca. Fijo, sin variable de entorno: solo las pruebas lo
    // apagan (tests/Pest.php), para no tener que crear un gerente en cada una.
    'exigir_gerente_general' => true,

    /*
     * Usuario para iniciar sesión en desarrollo: lo crea DatabaseSeeder solo con APP_ENV=local, con un DNI inventado
     * (00000000). Cada desarrollador pone su nombre y correo en su .env; sin ellos, dev@techquk.test.
     */
    'usuario_desarrollo' => [
        // Vacío cuenta como ausente: las pruebas los vacían en phpunit.xml para no depender del .env de nadie.
        'nombres' => env('DEV_USER_NOMBRES') ?: 'Usuario',
        'apellidos' => env('DEV_USER_APELLIDOS') ?: 'de Desarrollo',
        'email' => env('DEV_USER_EMAIL') ?: 'dev@techquk.test',
        'dni' => '00000000',
    ],

    /*
     * Archivo histórico (plan 1.4y, D6): el disco donde quedan, cifradas, las filas que salen de la base al limpiar.
     */
    'historico' => [
        'disco' => env('HISTORICO_DISCO', 'historico'),
    ],

    /*
     * Plan 1.11, D5: las claves de los proveedores de IA. Cada proveedor guarda solo el nombre de su variable, siempre
     * `IA_CLAVE_…` (CHECK en la base): aquí se leen todas las que existan, y ninguna otra. TI las pone en el servidor;
     * nunca van al repositorio, a la base ni a los registros. Cambiar el valor de una por la clave de otra cuenta exige
     * avisar a Gerencia (ia.md).
     */
    'ia' => [
        'claves' => array_filter(
            [...getenv(), ...$_SERVER, ...$_ENV],
            fn (mixed $valor, int|string $nombre): bool => is_string($nombre) && preg_match('/^IA_CLAVE_[A-Z0-9_]+$/', $nombre) === 1 && is_string($valor) && $valor !== '',
            ARRAY_FILTER_USE_BOTH,
        ),
        // Plan 1.11c: la conexión de la cola de la IA (config/queue.php, `ia`); en las pruebas, `sync`.
        'cola' => env('IA_COLA_CONEXION', 'ia'),
    ],

];
