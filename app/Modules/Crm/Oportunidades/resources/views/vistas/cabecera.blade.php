{{--
    Arriba de las cuatro vistas de Oportunidades (plan 2.21c, D1): las pestañas de vista (enlaces, con `aria-current` en
    la activa; las de Filament, en claro y oscuro) y la fila de cifras: embudo abierto, ponderado, cierran este mes,
    atascadas y cierre estimado vencido. Solo abiertas: las anuladas y las cerradas no cuentan. Recibe `$pestanas` y
    `$cifras` de `VistasDeOportunidades`.
--}}
<div class="tq-vistas" data-vistas>
    <style>
        .tq-vistas { display: flex; flex-direction: column; gap: 0.75rem; }
        .tq-vistas-cifras { display: grid; grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr)); gap: 0.75rem; }
        .tq-vistas-cifra-valor { font-size: 1.375rem; font-weight: 600; line-height: 1.875rem; font-variant-numeric: tabular-nums; }
        .tq-vistas-cifra-alerta { color: var(--warning-700); }
        .dark .tq-vistas-cifra-alerta { color: var(--warning-400); }
        .tq-vistas-cifra-detalle { font-size: 0.8125rem; color: var(--gray-500); font-variant-numeric: tabular-nums; }
        .tq-vistas-cifra-nota { font-size: 0.75rem; color: var(--gray-500); margin-top: 0.25rem; }
        .dark .tq-vistas-cifra-detalle, .dark .tq-vistas-cifra-nota { color: var(--gray-400); }
        /* En el celular las pestañas se desplazan dentro de su caja, nunca la página; con Tab, el foco se ve (paso 10). */
        .tq-vistas-pestanas { max-width: 100%; overflow-x: auto; }
        .tq-vistas-pestanas .fi-tabs-item:focus-visible { outline: 2px solid var(--primary-600); outline-offset: -2px; }
        .dark .tq-vistas-pestanas .fi-tabs-item:focus-visible { outline-color: var(--primary-400); }
    </style>

    <x-filament::tabs label="Vistas de Oportunidades" contained class="tq-vistas-pestanas">
        @foreach ($pestanas as $pestana)
            <x-filament::tabs.item tag="a" :href="$pestana['url']" :active="$pestana['activa']" data-vista="{{ $pestana['clave'] }}">
                {{ $pestana['etiqueta'] }}
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>

    <section class="tq-vistas-cifras" aria-label="Resumen del embudo">
        @foreach ($cifras as $clave => $cifra)
            <x-filament::section compact data-cifra="{{ $clave }}">
                <x-slot name="heading">{{ $cifra['titulo'] }}</x-slot>
                {{-- D16: los montos, cortos a la vista y exactos en el `title` y para el lector de pantalla. --}}
                <div @class(['tq-vistas-cifra-valor', 'tq-vistas-cifra-alerta' => $cifra['alerta']])>
                    @if ($cifra['valorCompleto'] !== null)
                        <x-oportunidades::monto :corto="$cifra['valor']" :completo="$cifra['valorCompleto']" />
                    @else
                        {{ $cifra['valor'] }}
                    @endif
                </div>
                <div class="tq-vistas-cifra-detalle">
                    @isset($cifra['detalleCompleto'])
                        <x-oportunidades::monto :corto="$cifra['detalle']" :completo="$cifra['detalleCompleto']" />
                    @else
                        {{ $cifra['detalle'] }}
                    @endisset
                </div>
                @foreach ($cifra['notas'] as $nota)
                    <div class="tq-vistas-cifra-nota">{{ $nota }}</div>
                @endforeach
            </x-filament::section>
        @endforeach
    </section>
</div>
