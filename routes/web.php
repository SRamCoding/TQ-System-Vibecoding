<?php

declare(strict_types=1);
use App\Modules\Crm\Campanias\Models\Campania;
use App\Modules\Crm\Campanias\Services\CampaniasService;
use App\Modules\Crm\Campanias\Services\LeadsService;
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

// Observación 7.6 (ficha t1-campanias): el formulario público de cada campaña (sin sesión). Atajo del MVP: contra envíos
// repetidos, solo un límite por dirección (5 por 10 minutos) y un campo trampa.
Route::middleware(['web', 'throttle:5,10'])->match(['get', 'post'], 'c/{token}', function (Request $r, string $token) {
    $campania = Campania::query()->where('token', $token)->where('es_activa', true)->firstOrFail();
    $enviado = false;

    if ($r->isMethod('post')) {
        $d = $r->validate(['nombre' => 'required|string|max:150', 'organizacion' => 'nullable|string|max:200', 'telefono' => 'nullable|string|max:30',
            'correo' => 'nullable|email|max:150', 'necesita' => 'nullable|string|max:1000', 'sitio' => 'nullable|max:0']);
        app(CampaniasService::class)->recibirFormulario($token, $d['nombre'], $d['organizacion'] ?? null, $d['telefono'] ?? null, $d['correo'] ?? null, $d['necesita'] ?? null);
        $enviado = true;
    }

    return view('campanias::formulario', ['campania' => $campania, 'enviado' => $enviado]);
})->name('campania.formulario');

// Observación 9.3 (ficha t1-campanias): la entrada de leads desde la landing de Techquk (docs/vibe/conectar-landing.md).
// Sin sesión ni CSRF (llega de otro sitio): la protegen la clave compartida, la casilla trampa oculta y el límite de
// envíos por minuto y dirección. Lo rechazado no crea nada. Punto de extensión: los formularios de anuncios de Meta y
// TikTok pueden llegar aquí con su `utm_source`.
Route::post('entrada/leads', function (Request $r) {
    $clave = (string) config('services.entrada_leads.clave');
    $enviada = (string) ($r->header('X-Techquk-Clave') ?? $r->input('clave', ''));
    $responder = fn (int $codigo, string $mensaje) => $r->expectsJson() || ! $r->filled('volver')
        ? response()->json(['ok' => $codigo === 201, 'mensaje' => $mensaje], $codigo)
        : redirect()->away($r->input('volver').(str_contains((string) $r->input('volver'), '?') ? '&' : '?').($codigo === 201 ? 'enviado=1' : 'error=1'));

    if ($clave === '' || ! hash_equals($clave, $enviada)) {
        return $responder(403, 'Clave incorrecta.');
    }

    // La casilla trampa: oculta para las personas; un robot la llena.
    if (trim((string) $r->input('sitio_web', '')) !== '') {
        return $responder(422, 'No se pudo registrar.');
    }

    $v = validator($r->all(), [
        'nombre' => 'required|string|max:150', 'organizacion' => 'nullable|string|max:200', 'telefono' => 'nullable|string|max:30',
        'correo' => 'nullable|email|max:150', 'mensaje' => 'nullable|string|max:2000', 'cargo' => 'nullable|string|max:120', 'ruc' => 'nullable|digits:11',
        'volver' => 'nullable|url|max:500',
    ]);

    if ($v->fails() || (! $r->filled('telefono') && ! $r->filled('correo'))) {
        return $responder(422, $v->fails() ? $v->errors()->first() : 'Falta un teléfono o un correo.');
    }

    $lead = app(LeadsService::class)->recibirDeLaWeb($v->validated(), $r->input('utm_source'), $r->input('campania') ?? $r->input('utm_campaign'));

    return $responder(201, 'Gracias, te contactaremos pronto. (lead '.$lead->id.')');
})->middleware('throttle:entrada-leads')->name('entrada.leads');

// Observación 9.3: una página de prueba que envía un formulario de ejemplo a la entrada, como lo haría la landing.
Route::middleware(['web'])->get('entrada/prueba', fn (Request $r) => $r->user() === null ? redirect('/login') : view('campanias::entrada-prueba', [
    'clave' => (string) config('services.entrada_leads.clave'), 'enviado' => $r->boolean('enviado'), 'error' => $r->boolean('error'),
]))->name('entrada.prueba');
