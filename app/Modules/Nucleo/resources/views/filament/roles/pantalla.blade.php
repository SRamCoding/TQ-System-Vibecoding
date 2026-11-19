{{--
    Roles y permisos (plan 1.4c, D2): las cajas de los roles y, debajo, la grilla de solo lectura, con un buscador que
    filtra las dos sin acentos ni mayúsculas. Con un rol abierto, sus permisos en un panel que se desliza desde la derecha
    **encima** (el mismo de Catálogos y Alertas: `partials.cajas-con-panel`). Sin recargar; cada caja sigue siendo un enlace
    (`?rol=…`). La fila de encabezado y la columna de los permisos quedan fijas al desplazarse.
--}}
@php
    /** @var list<array{id: int, nombre: string, permisos: int, personas: int}> $roles */
    /** @var list<array{nombre: string, permisos: array<int, string>}> $grupos */
    /** @var array<string, bool> $marcados */
    /** @var \Spatie\Permission\Models\Role|null $abierto */
    /** @var string|null $bloqueo */
    $normalizar = fn (string $texto): string => \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii($texto));
    $clicAbrir = fn (int $id): string => 'if ($event.button === 0 && ! ($event.ctrlKey || $event.metaKey || $event.shiftKey || $event.altKey)) { $event.preventDefault(); $wire.abrir('.$id.') }';
    $cantidad = fn (int $n, string $uno, string $varios): string => $n === 1 ? "1 {$uno}" : "{$n} {$varios}";
@endphp
<div class="tq-catalogos">
    @include('nucleo::filament.partials.cajas-con-panel')
    <style>
        .tq-roles-panel { width: min(100%, 44rem); }
        .tq-roles-panel-acciones { display: flex; align-items: center; gap: 0.5rem; }
        .tq-roles-aviso { margin-bottom: 1rem; padding: 0.75rem 1rem; border-radius: 0.5rem; font-size: 0.875rem; color: var(--gray-700); background: var(--gray-100); }
        .dark .tq-roles-aviso { color: var(--gray-200); background: var(--gray-800); }
        .tq-roles-cruces { margin-bottom: 0.5rem; padding-left: 1.1rem; list-style: disc; font-size: 0.875rem; }
        .tq-roles-cajas { grid-template-columns: repeat(auto-fill, 11rem); grid-auto-rows: 7.5rem; }
        .tq-roles-dato { font-size: 0.75rem; color: var(--gray-500); }
        .dark .tq-roles-dato { color: var(--gray-400); }
        .tq-roles-grilla-marco { max-height: 70vh; overflow: auto; border-radius: 0.75rem; box-shadow: 0 0 0 1px var(--gray-200); }
        .dark .tq-roles-grilla-marco { box-shadow: 0 0 0 1px var(--gray-700); }
        .tq-roles-grilla { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.875rem; background: var(--color-white, #fff); }
        .dark .tq-roles-grilla { background: var(--gray-900); }
        .tq-roles-grilla th, .tq-roles-grilla td { padding: 0.45rem 0.75rem; border-bottom: 1px solid var(--gray-100); }
        .dark .tq-roles-grilla th, .dark .tq-roles-grilla td { border-bottom-color: var(--gray-800); }
        .tq-roles-grilla thead th { position: sticky; top: 0; z-index: 2; min-width: 7rem; font-weight: 600; text-align: center; background: var(--gray-50); }
        .dark .tq-roles-grilla thead th { background: var(--gray-800); }
        .tq-roles-grilla tbody th { position: sticky; left: 0; z-index: 1; min-width: 17rem; font-weight: 400; text-align: left; background: var(--color-white, #fff); }
        .dark .tq-roles-grilla tbody th { background: var(--gray-900); }
        .tq-roles-grilla thead th:first-child { left: 0; z-index: 3; text-align: left; }
        .tq-roles-grilla tbody th.tq-roles-grilla-grupo { font-weight: 700; color: var(--primary-600); background: var(--gray-50); }
        .dark .tq-roles-grilla tbody th.tq-roles-grilla-grupo { color: var(--primary-400); background: var(--gray-800); }
        .tq-roles-grilla-fila-grupo td { background: var(--gray-50); }
        .dark .tq-roles-grilla-fila-grupo td { background: var(--gray-800); }
        .tq-roles-grilla td { text-align: center; }
        .tq-roles-grilla td .fi-icon { display: inline-block; width: 1.1rem; height: 1.1rem; color: var(--primary-600); }
        .dark .tq-roles-grilla td .fi-icon { color: var(--primary-400); }
        .tq-roles-grilla a:hover { text-decoration: underline; }
        .tq-roles-solo-lector { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); white-space: nowrap; }
    </style>

    <div @class(['tq-catalogos-detras' => $abierto !== null]) x-data="{ q: '', normalizar: (t) => t.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim() }">
        <x-filament::input.wrapper class="tq-catalogos-buscar" prefix-icon="heroicon-o-magnifying-glass">
            <x-filament::input type="search" x-model="q" placeholder="Buscar rol o permiso…" aria-label="Buscar rol o permiso" />
        </x-filament::input.wrapper>

        <section class="tq-catalogos-grupo">
            <h2 class="tq-catalogos-grupo-titulo">Roles</h2>
            <div class="tq-catalogos-cajas tq-roles-cajas">
                @foreach ($roles as $rol)
                    <a
                        href="{{ \App\Modules\Nucleo\Filament\Pages\RolesPermisos::getUrl(['rol' => $rol['id']]) }}"
                        class="fi-section tq-caja-catalogo tq-caja-sub"
                        data-rol="{{ $rol['id'] }}"
                        data-tq-abre-aqui
                        data-buscar-rol="{{ $normalizar($rol['nombre']) }}"
                        x-show="! normalizar(q) || $el.dataset.buscarRol.includes(normalizar(q)) || ! [...$root.querySelectorAll('[data-buscar-rol]')].some((c) => c.dataset.buscarRol.includes(normalizar(q)))"
                        x-on:click="{{ $clicAbrir($rol['id']) }}"
                    >
                        <x-filament::icon icon="heroicon-o-shield-check" class="tq-caja-catalogo-icono tq-caja-sub-icono" />
                        <span class="tq-caja-catalogo-nombre">{{ $rol['nombre'] }}</span>
                        <span class="tq-roles-dato">{{ $cantidad($rol['permisos'], 'permiso', 'permisos') }} · {{ $cantidad($rol['personas'], 'persona', 'personas') }}</span>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="tq-catalogos-grupo">
            <h2 class="tq-catalogos-grupo-titulo">Quién puede qué</h2>
            <div class="tq-roles-grilla-marco" tabindex="0" role="region" aria-label="Grilla de roles y permisos">
                <table class="tq-roles-grilla" data-tq-grilla>
                    <thead>
                        <tr>
                            <th scope="col">Permiso</th>
                            @foreach ($roles as $rol)
                                <th scope="col"><a href="{{ \App\Modules\Nucleo\Filament\Pages\RolesPermisos::getUrl(['rol' => $rol['id']]) }}" data-tq-abre-aqui x-on:click="{{ $clicAbrir($rol['id']) }}">{{ $rol['nombre'] }}</a></th>
                            @endforeach
                        </tr>
                    </thead>
                    @foreach ($grupos as $grupo)
                        <tbody x-show="[...$el.querySelectorAll('[data-buscar]')].some((f) => f.dataset.buscar.includes(normalizar(q)))">
                            <tr class="tq-roles-grilla-fila-grupo">
                                <th scope="rowgroup" class="tq-roles-grilla-grupo">{{ $grupo['nombre'] }}</th>
                                <td colspan="{{ count($roles) }}"></td>
                            </tr>
                            @foreach ($grupo['permisos'] as $permisoId => $etiqueta)
                                <tr data-buscar="{{ $normalizar($grupo['nombre'].' '.$etiqueta) }}" x-show="$el.dataset.buscar.includes(normalizar(q))">
                                    <th scope="row">{{ $etiqueta }}</th>
                                    @foreach ($roles as $rol)
                                        <td>
                                            @isset ($marcados[$rol['id'].':'.$permisoId])
                                                <x-filament::icon icon="heroicon-m-check" />
                                                <span class="tq-roles-solo-lector" data-tq-tiene="{{ $rol['id'] }}:{{ $permisoId }}">Sí</span>
                                            @endisset
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    @endforeach
                </table>
            </div>
            <p class="tq-catalogos-nada" role="status" x-cloak x-show="q && ! [...$root.querySelectorAll('[data-buscar]')].some((c) => c.dataset.buscar.includes(normalizar(q)))">
                Ningún permiso coincide.
            </p>
        </section>
    </div>

    @if ($abierto !== null)
        <section
            class="tq-catalogo-panel tq-roles-panel"
            wire:key="panel-rol-{{ $abierto->getKey() }}"
            aria-labelledby="tq-catalogo-panel-titulo"
            data-rol-abierto="{{ $abierto->getKey() }}"
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
                    <x-filament::icon icon="heroicon-o-shield-check" class="h-6 w-6" />
                    {{ \Illuminate\Support\Str::ucfirst($abierto->name) }}
                </h2>
                <div class="tq-roles-panel-acciones">
                    {{ $this->renombrarAction }}
                    <x-filament::icon-button icon="heroicon-o-x-mark" color="gray" label="Cerrar y volver a los roles" x-on:click="cerrar()" />
                </div>
            </header>
            @if ($bloqueo !== null)
                <p class="tq-roles-aviso" role="status" data-tq-rol-bloqueado>{{ $bloqueo }}</p>
            @endif
            {{ $this->form }}
        </section>
    @endif
</div>
