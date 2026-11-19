{{--
    Portada de Personas y empresas (plan 1.4m, D4): los últimos cambios en las fichas, de la bitácora: quién, qué hizo,
    a qué ficha y cuándo, con enlace a la ficha si sigue existiendo. Recibe `$cambios`.
--}}
@php /** @var list<array{quien: string, accion: string, ficha: string, url: ?string, cuando: \Carbon\CarbonImmutable}> $cambios */ @endphp
<x-filament::section heading="Últimos cambios" icon="heroicon-o-clock" collapsible compact>
    @if ($cambios === [])
        <p class="text-sm text-gray-500">Todavía no hay cambios.</p>
    @else
        <ul class="tq-ultimos-cambios">
            <style>
                .tq-ultimos-cambios { display: flex; flex-direction: column; gap: 0.375rem; font-size: 0.875rem; }
                .tq-ultimos-cambios li { display: flex; flex-wrap: wrap; gap: 0.25rem 0.5rem; }
                .tq-ultimos-cambios .tq-cuando { color: var(--gray-500); }
                .tq-ultimos-cambios a { font-weight: 600; color: var(--primary-600); }
                .dark .tq-ultimos-cambios a { color: var(--primary-400); }
            </style>
            @foreach ($cambios as $cambio)
                <li>
                    <span>{{ $cambio['quien'] }} {{ \Illuminate\Support\Str::lower($cambio['accion']) }}</span>
                    @if ($cambio['url'] !== null)
                        <a href="{{ $cambio['url'] }}">{{ $cambio['ficha'] }}</a>
                    @else
                        <span>{{ $cambio['ficha'] }}</span>
                    @endif
                    <span class="tq-cuando" title="{{ $cambio['cuando']->format('d/m/Y H:i') }}">· {{ $cambio['cuando']->locale('es')->diffForHumans() }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</x-filament::section>
