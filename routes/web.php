<?php

declare(strict_types=1);
use App\Modules\Nucleo\Navegacion\Proceso;
use App\Modules\Nucleo\Services\AsistenteService;
use App\Modules\Nucleo\Services\NavegacionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// La interfaz es el panel de Filament en «/» (App\Providers\Filament\AppPanelProvider).

// MVP rápido (ficha op-asistente): la conversación del asistente de ayuda y la confirmación de su ticket.
Route::middleware(['web', 'auth'])->prefix('asistente')->group(function (): void {
    Route::post('chat', function (Request $r) {
        $datos = $r->validate(['pregunta' => 'required|string|max:500', 'pantalla' => 'required|string|max:200']);

        return response()->json(app(AsistenteService::class)->responder($r->user(), $datos['pregunta'], $datos['pantalla']));
    })->name('asistente.chat');
    Route::post('ticket', function (Request $r) {
        $datos = $r->validate(['resumen' => 'required|string|max:300', 'pantalla' => 'required|string|max:200', 'conversacion' => 'nullable|string|max:5000']);

        return response()->json(['codigo' => app(AsistenteService::class)->abrirTicket($r->user(), $datos['resumen'], $datos['pantalla'], (string) ($datos['conversacion'] ?? ''))]);
    })->name('asistente.ticket');
});

// MV-021: Comprobantes se partió; los enlaces viejos van a las facturas emitidas.
Route::middleware(['web'])->get('comprobantes', fn () => redirect('/facturas-emitidas'));

// MV-035: el orden de las cajas de un proceso, por persona (lista vacía: el orden original).
Route::middleware(['web', 'auth'])->post('procesos/{proceso}/orden', function (Request $r, string $proceso) {
    app(NavegacionService::class)->ordenar($r->user(), Proceso::from($proceso), (array) $r->input('claves', []));

    return response()->noContent();
});
