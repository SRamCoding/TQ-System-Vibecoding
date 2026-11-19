<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/*
 * Regla 10 (plan 1.3b): un error imprevisto de la base no deja datos personales ni bancarios en los registros. El
 * QueryException trae el SQL con sus valores; se registra solo el SQLSTATE, la conexión y dónde ocurrió.
 */

test('un QueryException se registra sin el SQL, sus valores ni el detalle de PostgreSQL', function () {
    Log::spy();
    $error = new QueryException('pgsql', 'insert into party_contactos (nombres, email) values (?, ?)', ['Luis', 'luis@ejemplo.pe'], new PDOException('SQLSTATE[23514]: Check violation: DETAIL: Failing row contains (Luis, luis@ejemplo.pe).'));

    report($error);

    Log::shouldHaveReceived('error')->once()->withArgs(function (string $mensaje, array $contexto): bool {
        $todo = $mensaje.json_encode($contexto);

        return $mensaje === 'Error de base de datos'
            && ! str_contains($todo, 'Luis')
            && ! str_contains($todo, 'luis@ejemplo.pe')
            && ! str_contains($todo, 'insert into')
            && $contexto['conexion'] === 'pgsql';
    });
});
