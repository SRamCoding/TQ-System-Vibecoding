{{--
    Configuración → IA (plan 1.11, D11). Las cajas de los procesos (hoy ninguno: lo dice) y de los proveedores, con un
    buscador que las filtra sin acentos ni mayúsculas; con una abierta, su panel se desliza desde la derecha **encima**
    de las cajas (el mismo de Catálogos y Alertas: `partials.cajas-con-panel`). Sin recargar: abrir y cerrar pasan por
    Livewire; cada caja sigue siendo un enlace (`?abierto=…`). El estado de cada caja va con su nombre, nunca solo con
    un color (interfaz.md).
--}}
@php
    /** @var list<array{codigo: string, nombre: string, descripcion: string, encendido: bool, autorizado: bool, gasto: string, tope: string}> $procesos */
    /** @var \Illuminate\Support\Collection<int, \App\Modules\Nucleo\Models\IaProveedor> $proveedores */
    /** @var \App\Modules\Nucleo\Models\IaProceso|null $procesoAbierto */
    /** @var array<string, mixed>|null $detalle */
    /** @var \App\Modules\Nucleo\Models\IaProveedor|null $proveedorAbierto */
    /** @var bool $nuevoProveedor */
    /** @var list<array{modelo: \App\Modules\Nucleo\Models\IaModelo, precio: ?string}> $modelos */
    $pagina = \App\Modules\Nucleo\Filament\Pages\InteligenciaArtificial::class;
    $normalizar = fn (string $texto): string => \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii($texto));
    $dolares = fn (string $monto): string => number_format((float) $monto, 2, '.', ',');
    $abierto = $procesoAbierto !== null || $proveedorAbierto !== null || $nuevoProveedor;
@endphp
<div class="tq-catalogos">
    @include('nucleo::filament.partials.cajas-con-panel')
    <style>
        .tq-ia-panel { width: min(100%, 42rem); }
        .tq-ia-estado { font-size: 0.75rem; font-weight: 600; color: var(--gray-600); }
        .dark .tq-ia-estado { color: var(--gray-400); }
        .tq-ia-estado-bien { color: var(--success-700); }
        .dark .tq-ia-estado-bien { color: var(--success-400); }
        .tq-ia-estado-falta { color: var(--warning-700); }
        .dark .tq-ia-estado-falta { color: var(--warning-400); }
        .tq-ia-vacio { padding: 1rem 1.25rem; border-radius: 0.75rem; font-size: 0.875rem; color: var(--gray-600); background: var(--gray-50); }
        .dark .tq-ia-vacio { color: var(--gray-400); background: var(--gray-900); }
        .tq-ia-descripcion { margin-bottom: 1rem; font-size: 0.875rem; color: var(--gray-600); }
        .dark .tq-ia-descripcion { color: var(--gray-400); }
        .tq-ia-datos { display: grid; grid-template-columns: max-content 1fr; gap: 0.35rem 1rem; margin-bottom: 1.25rem; font-size: 0.875rem; }
        .tq-ia-datos dt { color: var(--gray-500); }
        .tq-ia-bloque { margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid var(--gray-200); }
        .dark .tq-ia-bloque { border-top-color: var(--gray-700); }
        .tq-ia-bloque h3 { margin-bottom: 0.75rem; font-size: 0.95rem; font-weight: 600; }
        .tq-ia-modelos { width: 100%; font-size: 0.875rem; border-collapse: collapse; }
        .tq-ia-modelos th { text-align: left; font-weight: 600; color: var(--gray-500); padding: 0.35rem 0.5rem; }
        .tq-ia-modelos td { padding: 0.45rem 0.5rem; border-top: 1px solid var(--gray-200); vertical-align: top; }
        .dark .tq-ia-modelos td { border-top-color: var(--gray-700); }
        .tq-ia-modelo-acciones { display: flex; flex-wrap: wrap; gap: 0.25rem 0.75rem; }
    </style>

    <div @class(['tq-catalogos-detras' => $abierto]) x-data="{ q: '', normalizar: (t) => t.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim() }">
        <x-filament::input.wrapper class="tq-catalogos-buscar" prefix-icon="heroicon-o-magnifying-glass">
            <x-filament::input type="search" x-model="q" placeholder="Buscar proceso o proveedor…" aria-label="Buscar proceso o proveedor" />
        </x-filament::input.wrapper>

        <section class="tq-catalogos-grupo">
            <h2 class="tq-catalogos-grupo-titulo">Procesos</h2>
            @if ($procesos === [])
                <p class="tq-ia-vacio" role="status">Todavía no hay procesos de IA. Llegan con los módulos que los usan (la lectura de bases, las partidas sugeridas…), apagados y sin tope hasta que se configuren aquí.</p>
            @else
                <div class="tq-catalogos-cajas">
                    @foreach ($procesos as $proceso)
                        <a
                            href="{{ $pagina::getUrl(['abierto' => 'proceso:'.$proceso['codigo']]) }}"
                            class="fi-section tq-caja-catalogo tq-caja-sub"
                            data-ia="proceso:{{ $proceso['codigo'] }}"
                            data-buscar="{{ $normalizar($proceso['nombre'].' '.$proceso['descripcion']) }}"
                            x-show="$el.dataset.buscar.includes(normalizar(q))"
                            x-on:click="if ($event.button === 0 && ! ($event.ctrlKey || $event.metaKey || $event.shiftKey || $event.altKey)) { $event.preventDefault(); $wire.abrir(@js('proceso:'.$proceso['codigo'])) }"
                            x-tooltip="{ content: @js($proceso['descripcion']), theme: $store.theme }"
                        >
                            <x-filament::icon icon="heroicon-o-sparkles" class="tq-caja-catalogo-icono tq-caja-sub-icono" />
                            <span class="tq-caja-catalogo-nombre">{{ $proceso['nombre'] }}</span>
                            <span @class(['tq-ia-estado', 'tq-ia-estado-bien' => $proceso['encendido'], 'tq-ia-estado-falta' => ! $proceso['autorizado']])>
                                {{ $proceso['encendido'] ? 'Encendido' : 'Apagado' }} · {{ $proceso['autorizado'] ? 'Autorizado' : 'Sin autorizar' }}
                            </span>
                            <span class="tq-ia-estado">{{ $dolares($proceso['gasto']) }} de {{ $dolares($proceso['tope']) }} USD este mes</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="tq-catalogos-grupo">
            <h2 class="tq-catalogos-grupo-titulo">Proveedores y modelos</h2>
            <div class="tq-catalogos-cajas">
                @foreach ($proveedores as $proveedor)
                    <a
                        href="{{ $pagina::getUrl(['abierto' => 'proveedor:'.$proveedor->id]) }}"
                        class="fi-section tq-caja-catalogo tq-caja-sub"
                        data-ia="proveedor:{{ $proveedor->id }}"
                        data-buscar="{{ $normalizar($proveedor->nombre.' '.$proveedor->codigo) }}"
                        x-show="$el.dataset.buscar.includes(normalizar(q))"
                        x-on:click="if ($event.button === 0 && ! ($event.ctrlKey || $event.metaKey || $event.shiftKey || $event.altKey)) { $event.preventDefault(); $wire.abrir(@js('proveedor:'.$proveedor->id)) }"
                    >
                        <x-filament::icon :icon="$proveedor->es_externo ? 'heroicon-o-cloud' : 'heroicon-o-server'" class="tq-caja-catalogo-icono tq-caja-sub-icono" />
                        <span class="tq-caja-catalogo-nombre">{{ $proveedor->nombre }}</span>
                        <span class="tq-ia-estado">
                            {{ $proveedor->es_externo ? 'Externo' : 'Propio' }} · {{ trans_choice('{0} sin modelos|{1} 1 modelo|[2,*] :count modelos', $proveedor->modelos_count) }}{{ $proveedor->es_activo ? '' : ' · Inactivo' }}
                        </span>
                    </a>
                @endforeach
                @if ($puedeConfigurar)
                    <a
                        href="{{ $pagina::getUrl(['abierto' => $pagina::NUEVO_PROVEEDOR]) }}"
                        class="fi-section tq-caja-catalogo tq-caja-sub"
                        data-ia="{{ $pagina::NUEVO_PROVEEDOR }}"
                        data-buscar="{{ $normalizar('nuevo proveedor agregar') }}"
                        x-show="$el.dataset.buscar.includes(normalizar(q))"
                        x-on:click="if ($event.button === 0 && ! ($event.ctrlKey || $event.metaKey || $event.shiftKey || $event.altKey)) { $event.preventDefault(); $wire.abrir(@js($pagina::NUEVO_PROVEEDOR)) }"
                    >
                        <x-filament::icon icon="heroicon-o-plus-circle" class="tq-caja-catalogo-icono tq-caja-sub-icono" />
                        <span class="tq-caja-catalogo-nombre">Agregar proveedor</span>
                    </a>
                @endif
            </div>
        </section>
    </div>

    @if ($abierto)
        <section
            class="tq-catalogo-panel tq-ia-panel"
            wire:key="panel-{{ $procesoAbierto?->codigo ?? $proveedorAbierto?->id ?? 'nuevo' }}"
            aria-labelledby="tq-catalogo-panel-titulo"
            x-data="{
                saliendo: false,
                cerrar() {
                    if (this.saliendo) return
                    this.saliendo = true
                    const quieto = window.matchMedia('(prefers-reduced-motion: reduce)').matches || document.documentElement.classList.contains('tq-sin-animaciones')
                    setTimeout(() => $wire.cerrar(), quieto ? 0 : 190)
                },
            }"
            x-bind:class="{ 'tq-saliendo': saliendo }"
            x-init="$nextTick(() => $el.querySelector('#tq-catalogo-panel-titulo')?.focus())"
            x-on:keydown.escape.window="if (! $event.defaultPrevented && ! $event.target.closest('input, textarea, select, [contenteditable]') && ! [...document.querySelectorAll('.fi-modal-open, [aria-modal=true], .fi-dropdown-panel')].some((capa) => capa.getClientRects().length)) cerrar()"
        >
            <header class="tq-catalogo-panel-encabezado">
                <h2 id="tq-catalogo-panel-titulo" class="tq-catalogo-panel-titulo" tabindex="-1">
                    <x-filament::icon :icon="$procesoAbierto !== null ? 'heroicon-o-sparkles' : 'heroicon-o-cpu-chip'" class="h-6 w-6" />
                    {{ $detalle['definicion']->nombre ?? $proveedorAbierto?->nombre ?? 'Nuevo proveedor' }}
                </h2>
                <x-filament::icon-button icon="heroicon-o-x-mark" color="gray" label="Cerrar y volver a las cajas" x-on:click="cerrar()" />
            </header>

            @if ($detalle !== null)
                @php
                    $definicion = $detalle['definicion'];
                    $envia = collect($definicion->camposEnviados)->map(fn (array $campos, string $origen): string => $origen.': '.implode(', ', $campos))->implode('; ');
                @endphp
                <p class="tq-ia-descripcion">{{ $definicion->descripcion }}</p>
                <dl class="tq-ia-datos">
                    <dt>Dueño</dt><dd>{{ $definicion->dueno }}</dd>
                    <dt>Qué envía</dt><dd>{{ $envia !== '' ? $envia : 'Ningún campo del registro' }}{{ $definicion->tiposAdjunto !== [] ? ' · archivos: '.implode(', ', $definicion->tiposAdjunto) : '' }}</dd>
                    <dt>Sale de la empresa</dt><dd>{{ $detalle['enviaFuera'] ? 'Sí' : 'No (o todavía sin modelo)' }}</dd>
                    <dt>Autorización</dt>
                    <dd @class(['tq-ia-estado-bien' => $detalle['vigente'] && $detalle['autorizadoPor'] !== null, 'tq-ia-estado-falta' => ! $detalle['vigente']])>
                        @if ($detalle['autorizadoPor'] !== null)
                            {{ $detalle['vigente'] ? 'Vigente' : 'Ya no vale: cambió lo que envía o adónde' }} · {{ $detalle['autorizadoPor'] }}, {{ $detalle['autorizadoAt']?->timezone('America/Lima')->format('d/m/Y') }}
                        @else
                            {{ $detalle['vigente'] ? 'No hace falta: no envía datos fuera' : 'Pendiente de Gerencia' }}
                        @endif
                    </dd>
                    <dt>Modelo</dt><dd>{{ $detalle['modelo'] ?? 'Ninguno' }}</dd>
                    <dt>Este mes</dt><dd>{{ $dolares($detalle['gasto']) }} de {{ $dolares($detalle['tope']) }} USD · {{ trans_choice('{0} ninguna llamada|{1} 1 llamada|[2,*] :count llamadas', $detalle['llamadas']) }}</dd>
                    <dt>Acierto</dt><dd>{{ $detalle['acierto'] === null ? 'Sin sugerencias resueltas' : $detalle['acierto'].' % aceptadas o corregidas, de '.$detalle['resueltas'] }}</dd>
                </dl>

                <div class="tq-ia-bloque">
                    <h3>Modelos y encendido</h3>
                    {{ $this->getSchema('procesoForm') }}
                </div>

                @if ($puedeAutorizar)
                    <div class="tq-ia-bloque">
                        <h3>Autorización de Gerencia</h3>
                        <p class="tq-ia-descripcion">Autoriza lo que el proceso envía hoy, a los proveedores externos de sus modelos de hoy. Si después cambia, la autorización cae y el proceso se apaga.</p>
                        {{ $this->getSchema('autorizacionForm') }}
                    </div>
                @endif
            @elseif ($proveedorAbierto !== null || $nuevoProveedor)
                {{ $this->getSchema('proveedorForm') }}

                @if ($proveedorAbierto !== null)
                    <div class="tq-ia-bloque">
                        <h3>Modelos</h3>
                        @if ($modelos === [])
                            <p class="tq-ia-descripcion">Todavía no tiene modelos.</p>
                        @else
                            <table class="tq-ia-modelos">
                                <thead><tr><th>Modelo</th><th>Precio vigente</th><th></th></tr></thead>
                                <tbody>
                                    @foreach ($modelos as $fila)
                                        @php($modelo = $fila['modelo'])
                                        <tr data-modelo="{{ $modelo->id }}">
                                            <td>
                                                {{ $modelo->nombre }} <span class="tq-ia-estado">{{ $modelo->codigo }}</span>
                                                <div class="tq-ia-estado">
                                                    {{ $modelo->es_activo ? 'Activo' : 'Inactivo' }}{{ $modelo->es_por_defecto ? ' · Por defecto' : '' }} · hasta {{ number_format($modelo->max_tokens_salida) }} tokens de salida
                                                </div>
                                            </td>
                                            <td @class(['tq-ia-estado-falta' => $fila['precio'] === null])>{{ $fila['precio'] ?? 'Sin precio: no se llama' }}</td>
                                            <td>
                                                @if ($puedeConfigurar)
                                                    <div class="tq-ia-modelo-acciones">
                                                        {{ ($this->nucleoIaEditarModeloAction)(['modelo' => $modelo->id]) }}
                                                        {{ ($this->nucleoIaAgregarPrecioAction)(['modelo' => $modelo->id]) }}
                                                        @if (! $modelo->es_por_defecto && $modelo->es_activo && $fila['precio'] !== null)
                                                            {{ ($this->nucleoIaMarcarModeloPorDefectoAction)(['modelo' => $modelo->id]) }}
                                                        @endif
                                                    </div>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                        @if ($puedeConfigurar)
                            <div class="tq-ia-bloque">{{ $this->nucleoIaCrearModeloAction }}</div>
                        @endif
                    </div>
                @endif
            @endif
        </section>
    @endif
</div>
