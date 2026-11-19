<?php

declare(strict_types=1);

/**
 * PostToolUse: formatea con Pint el archivo PHP recién editado.
 * Si Pint falla (normalmente por un error de sintaxis), se lo muestra a Claude (exit 2).
 */
$entrada = json_decode((string) stream_get_contents(STDIN), true) ?? [];
$ruta = $entrada['tool_input']['file_path'] ?? '';

if ($ruta === '' || ! str_ends_with($ruta, '.php') || ! is_file($ruta)) {
    exit(0);
}

$raiz = getenv('CLAUDE_PROJECT_DIR') ?: ($entrada['cwd'] ?? getcwd());
$pint = $raiz . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'pint';
if (! is_file($pint)) {
    exit(0); // Laravel aún no instalado
}

$comando = sprintf('php %s %s --quiet 2>&1', escapeshellarg($pint), escapeshellarg($ruta));
exec($comando, $salida, $codigo);

if ($codigo !== 0) {
    fwrite(STDERR, "Pint no pudo formatear {$ruta}:\n" . implode("\n", $salida) . "\n");
    exit(2);
}

exit(0);
