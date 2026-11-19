{{--
    La barra de carpetas de Cotizaciones (`interfaz.md`, «Documentos con versiones: árbol de carpetas»): Cuenta ›
    Oportunidad › Cotización › Versión, la versión más reciente primero, cada una con su estado. La carpeta de una
    oportunidad trae también sus presupuestos (el precio y el costo detrás, en el mismo lugar). Presionar una carpeta
    recorta la tabla sin recargar; una versión abre su ficha. Recibe `$arbol`, `$carpeta` y `$soloMias`.
--}}
@php
    use App\Modules\Crm\Cotizaciones\Filament\Resources\Cotizaciones\Pages\VerCotizacion;
    $abierta = fn (string $clave): bool => $carpeta === $clave;
    $rutaPresupuesto = \Illuminate\Support\Facades\Route::has('filament.app.resources.presupuestos.view')
        ? fn (int $id): string => route('filament.app.resources.presupuestos.view', ['record' => $id])
        : null;
@endphp
<aside class="tq-carpetas" aria-label="Carpetas" data-tq-carpetas>
    <style>
        .tq-cotizaciones-portada { display: grid; gap: 1rem; grid-template-columns: minmax(0, 1fr); }
        @media (min-width: 1024px) { .tq-cotizaciones-portada { grid-template-columns: 18rem minmax(0, 1fr); align-items: start; } }
        .tq-cotizaciones-principal { display: flex; flex-direction: column; gap: 1rem; min-width: 0; }
        .tq-carpetas { font-size: 0.875rem; border: 1px solid rgb(0 0 0 / 0.08); border-radius: 0.75rem; padding: 0.75rem; background: white; }
        .dark .tq-carpetas { background: rgb(255 255 255 / 0.03); border-color: rgb(255 255 255 / 0.1); }
        .tq-carpetas ul { list-style: none; margin: 0; padding-left: 0.75rem; }
        .tq-carpetas > ul { padding-left: 0; }
        .tq-carpetas li { margin: 0.125rem 0; }
        .tq-carpetas button, .tq-carpetas a { display: inline-flex; gap: 0.375rem; align-items: baseline; text-align: left; border-radius: 0.375rem; padding: 0.125rem 0.375rem; }
        .tq-carpetas button:hover, .tq-carpetas a:hover { background: rgb(0 0 0 / 0.05); }
        .dark .tq-carpetas button:hover, .dark .tq-carpetas a:hover { background: rgb(255 255 255 / 0.08); }
        .tq-carpetas [aria-current="true"] { font-weight: 600; background: rgb(var(--primary-500) / 0.12); }
        .tq-carpetas-sub { color: rgb(107 114 128); font-size: 0.8125rem; }
        .dark .tq-carpetas-sub { color: rgb(156 163 175); }
        .tq-carpetas-encabezado { display: flex; justify-content: space-between; gap: 0.5rem; align-items: center; margin-bottom: 0.5rem; }
    </style>

    <div class="tq-carpetas-encabezado">
        <p><strong>Carpetas</strong> <span class="tq-carpetas-sub">Cuenta › Oportunidad › Cotización › Versión</span></p>
    </div>
    <p class="mb-2">
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" class="fi-checkbox-input" wire:click="cambiarSoloMias" @checked($soloMias) data-tq-solo-mias>
            Solo mías
        </label>
    </p>

    @if ($carpeta !== null)
        <p class="mb-2"><button type="button" wire:click="verCarpeta(null)" class="fi-link">Ver todas las carpetas</button></p>
    @endif

    @if ($arbol['cuentas'] === [])
        <p class="tq-carpetas-sub">Sin cotizaciones con estos filtros.</p>
    @endif

    <ul>
        @foreach ($arbol['cuentas'] as $cuenta)
            <li data-carpeta="cuenta:{{ $cuenta['id'] }}">
                <button type="button" wire:click="verCarpeta('cuenta:{{ $cuenta['id'] }}')" aria-current="{{ $abierta('cuenta:'.$cuenta['id']) ? 'true' : 'false' }}">
                    {{ $cuenta['etiqueta'] }} <span class="tq-carpetas-sub">{{ count($cuenta['oportunidades']) }}</span>
                </button>
                <ul>
                    @foreach ($cuenta['oportunidades'] as $op)
                        <li data-carpeta="oportunidad:{{ $op['id'] }}">
                            <button type="button" wire:click="verCarpeta('oportunidad:{{ $op['id'] }}')" aria-current="{{ $abierta('oportunidad:'.$op['id']) ? 'true' : 'false' }}">
                                {{ $op['etiqueta'] }}
                            </button>
                            <ul>
                                @foreach ($op['numeros'] as $numero)
                                    <li data-carpeta="numero:{{ $numero['numero'] }}">
                                        <button type="button" wire:click="verCarpeta('numero:{{ $numero['numero'] }}')" aria-current="{{ $abierta('numero:'.$numero['numero']) ? 'true' : 'false' }}" class="font-mono">
                                            {{ $numero['numero'] }} <span class="tq-carpetas-sub">{{ count($numero['versiones']) }}</span>
                                        </button>
                                        <ul>
                                            @foreach ($numero['versiones'] as $v)
                                                <li data-version="{{ $v['id'] }}">
                                                    <a href="{{ VerCotizacion::getUrl(['record' => $v['id']]) }}" wire:navigate>
                                                        versión {{ $v['version'] }} <span class="tq-carpetas-sub">· {{ mb_strtolower($v['estadoNombre']) }}{{ $v['aceptada'] ? ' · la aceptada' : '' }}</span>
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </li>
                                @endforeach
                                @foreach ($op['presupuestos'] as $p)
                                    <li data-presupuesto="{{ $p['id'] }}">
                                        @if ($rutaPresupuesto !== null)
                                            <a href="{{ $rutaPresupuesto($p['id']) }}">Presupuesto {{ $p['etiqueta'] }} <span class="tq-carpetas-sub">· {{ mb_strtolower($p['estado']) }}</span></a>
                                        @else
                                            <span>Presupuesto {{ $p['etiqueta'] }} <span class="tq-carpetas-sub">· {{ mb_strtolower($p['estado']) }}</span></span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </li>
                    @endforeach
                </ul>
            </li>
        @endforeach
    </ul>

    @if ($arbol['recortadas'])
        <p class="tq-carpetas-sub mt-2">Hay más cotizaciones: usa el buscador o una pestaña para ver sus carpetas.</p>
    @endif
</aside>
