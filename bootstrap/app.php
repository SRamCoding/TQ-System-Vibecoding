<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Regla 10 (plan 1.3b): un QueryException trae el SQL con sus valores y el DETAIL de PostgreSQL (nombres,
        // correos, números de cuenta). Se registra solo el SQLSTATE, la conexión y dónde ocurrió.
        $exceptions->report(function (QueryException $e): false {
            Log::error('Error de base de datos', [
                'sqlstate' => $e->errorInfo[0] ?? $e->getCode(),
                'conexion' => $e->getConnectionName(),
                // El primer punto del código propio por donde pasó (el archivo del error siempre es de Laravel).
                'donde' => collect($e->getTrace())->map(fn (array $f): string => ($f['file'] ?? '').':'.($f['line'] ?? ''))
                    ->first(fn (string $lugar): bool => str_starts_with($lugar, app_path().DIRECTORY_SEPARATOR)),
            ]);

            return false;
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
