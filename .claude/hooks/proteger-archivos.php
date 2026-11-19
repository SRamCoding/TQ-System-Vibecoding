<?php

declare(strict_types=1);

/**
 * PreToolUse: bloquea ediciones que rompen reglas del proyecto.
 *  - Archivos .env (salvo .env.example): siempre.
 *  - Migraciones que ya existen en origin/main: SOLO desde el primer despliegue con datos reales,
 *    es decir, cuando existe el archivo .claude/PRODUCCION (ADR 0010).
 *
 * Exit 2 = bloquea la herramienta y muestra stderr a Claude.
 */
$entrada = json_decode((string) stream_get_contents(STDIN), true) ?? [];
$ruta = $entrada['tool_input']['file_path'] ?? '';
if ($ruta === '') {
    exit(0);
}

$raiz = getenv('CLAUDE_PROJECT_DIR') ?: ($entrada['cwd'] ?? getcwd());
$normalizar = static fn (string $p): string => rtrim(str_replace('\\', '/', $p), '/');
$ruta = $normalizar($ruta);
$raiz = $normalizar((string) $raiz);
$relativa = str_starts_with(strtolower($ruta), strtolower($raiz) . '/') ? substr($ruta, strlen($raiz) + 1) : $ruta;

$nombre = basename($relativa);
if (str_starts_with($nombre, '.env') && $nombre !== '.env.example') {
    fwrite(STDERR, "Bloqueado: no se editan archivos .env. Si falta una variable, agrégala a .env.example y avisa al usuario.\n");
    exit(2);
}

if ($relativa === '.claude/PRODUCCION') {
    fwrite(STDERR, "Bloqueado: .claude/PRODUCCION lo crea una persona el día del primer despliegue con datos reales (ADR 0010).\n");
    exit(2);
}

$enProduccion = is_file($raiz . '/.claude/PRODUCCION');
if ($enProduccion && (str_contains($relativa, '/database/migrations/') || str_starts_with($relativa, 'database/migrations/'))) {
    $comando = sprintf('git -C %s cat-file -e %s 2>&1', escapeshellarg($raiz), escapeshellarg('origin/main:' . $relativa));
    exec($comando, $salida, $codigo);
    if ($codigo === 0) {
        fwrite(STDERR, "Bloqueado: {$relativa} ya está en origin/main y el sistema tiene datos reales. Crea una migración nueva que haga el cambio (ver /nueva-migracion).\n");
        exit(2);
    }
}

exit(0);
