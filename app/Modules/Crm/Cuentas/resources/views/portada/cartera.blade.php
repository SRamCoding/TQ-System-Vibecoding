{{--
    Portada de Cuentas (plan 2.20, D11): la cartera, cuántas cuentas atiende cada ejecutivo. Un clic filtra la tabla por
    ese ejecutivo; otro clic en el mismo lo quita. Las oportunidades de cada uno se suman cuando exista CRM-02.
    Recibe `$cartera` (id, nombre, activo, cuentas) y `$filtro` (el ejecutivo puesto, o nulo).
--}}
@if ($cartera !== [])
    <x-filament::section heading="Cartera por ejecutivo" icon="heroicon-o-user-group" collapsible compact>
        <div class="tq-cartera">
            <style>
                .tq-cartera { display: flex; flex-wrap: wrap; gap: 0.5rem; }
                .tq-cartera button { display: inline-flex; gap: 0.375rem; align-items: center; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.875rem; border: 1px solid var(--gray-300); }
                .dark .tq-cartera button { border-color: var(--gray-600); }
                .tq-cartera button[aria-pressed="true"] { border-color: var(--primary-600); color: var(--primary-600); font-weight: 600; }
                .tq-cartera .tq-inactivo { color: #B45309; }
            </style>
            @foreach ($cartera as $ejecutivo)
                @php $puesto = (int) $filtro === $ejecutivo['id']; @endphp
                <button type="button" aria-pressed="{{ $puesto ? 'true' : 'false' }}" wire:click="verEjecutivo({{ $puesto ? 'null' : $ejecutivo['id'] }})">
                    <span>{{ $ejecutivo['nombre'] }}</span>
                    @unless ($ejecutivo['activo'])
                        <span class="tq-inactivo">(inactivo)</span>
                    @endunless
                    <strong>{{ $ejecutivo['cuentas'] }}</strong>
                </button>
            @endforeach
        </div>
    </x-filament::section>
@endif
