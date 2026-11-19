<?php

declare(strict_types=1);
use App\Modules\Nucleo\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/*
 * Prueba de carga (plan 1.12, D5). Dos modos:
 *
 *   php scripts/prueba-carga.php carga --base=https://servidor-de-prueba --correo=… --usuarios=50 [--duracion=120] [--pausa=10]
 *     La clave se pide sin mostrarla, o se toma de PRUEBA_CARGA_CLAVE: nunca como opción, que queda en el historial de la
 *     terminal y en la lista de procesos.
 *     Simula N personas con sesión iniciada recorriendo pantallas (una sola sesión que comparten todas: el ingreso
 *     corta los intentos seguidos desde una misma dirección, y 50 ingresos de golpe fallaban), cada una con una pausa al azar entre pantallas (de la
 *     mitad al doble de --pausa, en segundos), durante --duracion segundos. Mide cada pedido y muestra, por pantalla,
 *     cuántos, errores y los tiempos (mediana, p95, máximo), y los pedidos por segundo del total. Los errores salen por
 *     código: un 403 es una pantalla sin permiso para esa persona, no una falla del servidor. La corrida de verdad
 *     (50, 100 y 200) la hace TI en un servidor de prueba igual al de producción (docs/operaciones/prueba-de-carga.md).
 *     Contra el servidor de desarrollo los tiempos no valen: sin opcache y con un solo proceso.
 *
 *   php scripts/prueba-carga.php consultas [--correo=…]
 *     Dentro de la app, con una persona (de fábrica, el usuario de desarrollo): por pantalla, cuántas consultas hace y
 *     cuánto tiempo de base gasta. Eso sí vale para producción: una consulta repetida o un índice que falta pesan igual
 *     con o sin opcache. Marca las que pasan de 40 consultas o 100 ms de base. No escribe nada (deshace todo). Solo en
 *     local (APP_ENV=local): entra como la persona sin su clave, y lo que sale de la base (registros) no se deshace.
 *
 * Nunca contra producción: los agentes no la tocan, y la carga la decide TI.
 */

const PANTALLAS = [
    '/', '/mien', '/personas', '/seguridad/usuarios', '/seguridad/organigrama', '/seguridad/roles', '/configuracion', '/configuracion/catalogos',
    '/configuracion/parametros', '/configuracion/alertas', '/configuracion/salud', '/mis-avisos', '/mis-datos', '/mis-preferencias',
];

const LIMITE_CONSULTAS = 40;
const LIMITE_MS_BASE = 100.0;

/** @return array<string, string> */
function opciones(array $argv): array
{
    $opciones = [];

    foreach (array_slice($argv, 2) as $argumento) {
        if (preg_match('/^--([a-z-]+)=(.*)$/', $argumento, $m) === 1) {
            $opciones[$m[1]] = $m[2];
        }
    }

    return $opciones;
}

function pedirClave(): string
{
    $clave = getenv('PRUEBA_CARGA_CLAVE');

    if (is_string($clave) && $clave !== '') {
        return $clave;
    }

    fwrite(STDOUT, 'Clave: ');
    shell_exec('stty -echo');
    $clave = trim((string) fgets(STDIN));
    shell_exec('stty echo');
    fwrite(STDOUT, "\n");

    return $clave;
}

function salir(string $mensaje): never
{
    fwrite(STDERR, $mensaje."\n");
    exit(1);
}

/** @param list<float> $valores */
function percentil(array $valores, float $p): float
{
    sort($valores);

    return $valores === [] ? 0.0 : $valores[(int) min(count($valores) - 1, floor($p * (count($valores) - 1)))];
}

// --- Modo carga: contra un servidor, con curl. ---

/** @return array{0: resource|CurlHandle, 1: string} */
function sesion(string $base, string $correo, string $clave): array
{
    $galleta = tempnam(sys_get_temp_dir(), 'carga');
    $pedir = function (string $metodo, string $url, ?string $cuerpo = null, array $cabeceras = []) use ($galleta): string {
        $c = curl_init($url);
        curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $galleta, CURLOPT_COOKIEFILE => $galleta, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 30, CURLOPT_CUSTOMREQUEST => $metodo, CURLOPT_HTTPHEADER => $cabeceras]);

        if ($cuerpo !== null) {
            curl_setopt($c, CURLOPT_POSTFIELDS, $cuerpo);
        }

        return (string) curl_exec($c);
    };

    // El ingreso es un componente de Livewire: su estado está en la página; se le mandan los datos y se llama a autenticar.
    $pagina = $pedir('GET', $base.'/login');
    preg_match('/name="csrf-token" content="([^"]+)"/', $pagina, $token);
    preg_match('/data-update-uri="([^"]+)"/', $pagina, $actualizar);
    preg_match('/wire:snapshot="([^"]*&quot;email&quot;[^"]*)"/', $pagina, $estado);

    if (! isset($token[1], $actualizar[1], $estado[1])) {
        salir('No se encontró el formulario de ingreso en '.$base.'/login.');
    }

    $pedir('POST', $actualizar[1], (string) json_encode([
        '_token' => $token[1],
        'components' => [[
            'snapshot' => html_entity_decode($estado[1]),
            'updates' => ['data.email' => $correo, 'data.password' => $clave],
            'calls' => [['path' => '', 'method' => 'authenticate', 'params' => []]],
        ]],
    ]), ['Content-Type: application/json', 'X-Livewire: 1', 'X-CSRF-TOKEN: '.$token[1]]);

    // Estricto: con la sesión, el inicio responde 200 y dibuja la barra del panel (sin sesión, lleva al ingreso).
    $inicio = curl_init($base.'/');
    curl_setopt_array($inicio, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => $galleta, CURLOPT_TIMEOUT => 30]);
    $pagina = (string) curl_exec($inicio);

    if (curl_getinfo($inicio, CURLINFO_HTTP_CODE) !== 200 || ! str_contains($pagina, 'fi-topbar')) {
        salir('No se pudo iniciar sesión con '.$correo.': revisa el correo y la clave.');
    }

    return [curl_init(), $galleta];
}

function carga(array $opciones): void
{
    $base = rtrim($opciones['base'] ?? '', '/');
    $correo = $opciones['correo'] ?? '';
    $clave = isset($opciones['clave']) ? salir('La clave no va como opción: queda en el historial. Usa PRUEBA_CARGA_CLAVE o escríbela cuando la pida.') : '';
    $usuarios = max(1, (int) ($opciones['usuarios'] ?? 10));
    $duracion = max(10, (int) ($opciones['duracion'] ?? 120));
    $pausa = max(0.0, (float) ($opciones['pausa'] ?? 10));

    if ($base === '' || $correo === '') {
        salir('Faltan --base o --correo.');
    }

    $clave = pedirClave();

    if ($clave === '') {
        salir('Falta la clave.');
    }

    echo "Iniciando sesión…\n";
    [, $galleta] = sesion($base, $correo, $clave);
    $personas = [];

    for ($i = 0; $i < $usuarios; $i++) {
        $personas[] = ['galleta' => $galleta, 'proximo' => microtime(true) + mt_rand(0, (int) ($pausa * 1000)) / 1000];
    }

    echo "Recorriendo pantallas durante {$duracion} s (pausa media {$pausa} s)…\n";
    $multi = curl_multi_init();
    $enCurso = [];
    $tiempos = [];
    $errores = [];
    $codigos = [];
    $fin = microtime(true) + $duracion;

    while (microtime(true) < $fin || $enCurso !== []) {
        foreach ($personas as $i => $persona) {
            if (! isset($enCurso[$i]) && $persona['proximo'] <= microtime(true) && microtime(true) < $fin) {
                $pantalla = PANTALLAS[array_rand(PANTALLAS)];
                $c = curl_init($base.$pantalla);
                curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => $persona['galleta'], CURLOPT_TIMEOUT => 60]);
                curl_multi_add_handle($multi, $c);
                $enCurso[$i] = ['c' => $c, 'pantalla' => $pantalla, 'inicio' => microtime(true)];
            }
        }

        curl_multi_exec($multi, $activos);
        curl_multi_select($multi, 0.05);

        while (($hecho = curl_multi_info_read($multi)) !== false) {
            foreach ($enCurso as $i => $pedido) {
                if ($pedido['c'] === $hecho['handle']) {
                    $ms = (microtime(true) - $pedido['inicio']) * 1000;
                    $codigo = (int) curl_getinfo($pedido['c'], CURLINFO_HTTP_CODE);
                    $tiempos[$pedido['pantalla']][] = $ms;

                    if ($codigo !== 200) {
                        $errores[$pedido['pantalla']] = ($errores[$pedido['pantalla']] ?? 0) + 1;
                        $codigos[$codigo] = ($codigos[$codigo] ?? 0) + 1;
                    }

                    curl_multi_remove_handle($multi, $pedido['c']);
                    unset($enCurso[$i]);
                    $personas[$i]['proximo'] = microtime(true) + $pausa * (0.5 + mt_rand(0, 1500) / 1000);
                }
            }
        }
    }

    $total = array_sum(array_map('count', $tiempos));
    printf("\n%-28s %7s %7s %10s %10s %10s\n", 'Pantalla', 'Pedidos', 'Errores', 'Mediana', 'p95', 'Máximo');

    foreach ($tiempos as $pantalla => $lista) {
        printf("%-28s %7d %7d %8.0f ms %8.0f ms %8.0f ms\n", $pantalla, count($lista), $errores[$pantalla] ?? 0, percentil($lista, 0.5), percentil($lista, 0.95), max($lista));
    }

    $todos = array_merge(...array_values($tiempos ?: [[]]));
    printf("\n%d personas · %d pedidos en %d s (%.1f por segundo) · errores: %d · p95 general: %.0f ms\n", $usuarios, $total, $duracion, $total / $duracion, array_sum($errores), percentil($todos, 0.95));

    ksort($codigos);
    echo $codigos === [] ? '' : 'Errores por código: '.implode(', ', array_map(fn (int $codigo, int $cantidad): string => "{$codigo} × {$cantidad}", array_keys($codigos), $codigos))." (403: sin permiso para esa pantalla)\n";
    @unlink($galleta);
}

// --- Modo consultas: dentro de la app, con una persona. ---

function consultas(array $opciones): void
{
    $raiz = dirname(__DIR__);
    require $raiz.'/vendor/autoload.php';
    $app = require $raiz.'/bootstrap/app.php';
    $http = $app->make(Kernel::class);
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    // Revisión de arquitectura de la 1.12 (regla 11): entra como la persona sin su clave y corre contra la base del .env.
    if (! $app->environment('local')) {
        salir('El modo consultas solo corre en local (APP_ENV=local).');
    }

    config(['session.driver' => 'array']);

    $correo = $opciones['correo'] ?? (string) config('nucleo.usuario_desarrollo.email');
    $persona = User::query()->where('email', mb_strtolower($correo))->first() ?? salir("No existe el usuario {$correo}.");
    $db = DB::connection();
    $marcadas = 0;

    printf("%-28s %6s %10s %10s %10s\n", 'Pantalla', 'Estado', 'Consultas', 'Base', 'Total');

    foreach (PANTALLAS as $pantalla) {
        // Dos vueltas: la primera compila vistas y llena cachés; se mide la segunda. Todo se deshace.
        foreach ([1, 2] as $vuelta) {
            $db->beginTransaction();
            $consultas = 0;
            $msBase = 0.0;
            $db->listen(function ($consulta) use (&$consultas, &$msBase): void {
                $consultas++;
                $msBase += $consulta->time;
            });
            Auth::guard('web')->setUser($persona);
            $inicio = hrtime(true);
            $respuesta = $http->handle(Request::create('http://localhost'.$pantalla, 'GET'));
            $msTotal = (hrtime(true) - $inicio) / 1e6;
            $db->rollBack();
            $db->flushQueryLog();
            app('auth')->forgetGuards();
            $db->forgetRecordModificationState();
            $app['events']->forget(QueryExecuted::class);
        }

        $marca = $consultas > LIMITE_CONSULTAS || $msBase > LIMITE_MS_BASE ? '  ← revisar' : '';
        $marcadas += $marca === '' ? 0 : 1;
        printf("%-28s %6d %10d %7.1f ms %7.0f ms%s\n", $pantalla, $respuesta->getStatusCode(), $consultas, $msBase, $msTotal, $marca);
    }

    printf("\nLímites: %d consultas o %.0f ms de base por pantalla. Marcadas: %d.\n", LIMITE_CONSULTAS, LIMITE_MS_BASE, $marcadas);
}

match ($argv[1] ?? '') {
    'carga' => carga(opciones($argv)),
    'consultas' => consultas(opciones($argv)),
    default => salir("Uso:\n  php scripts/prueba-carga.php carga --base=URL --correo=… --usuarios=50 [--duracion=120] [--pausa=10]\n  php scripts/prueba-carga.php consultas [--correo=…]"),
};
