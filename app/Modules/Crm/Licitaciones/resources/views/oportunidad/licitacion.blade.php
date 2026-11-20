{{--
    MV-023: la licitación dentro de la oportunidad. Arriba, su estado y las acciones de ese estado; debajo, las seis
    partes: bases, cronograma, expediente, consultas, propuesta y resultado. Cronograma, expediente y consultas se editan
    en la ficha completa de la licitación (enlace).
--}}
@php
    $soles = fn ($m) => $m === null ? '—' : 'S/ '.number_format((float) $m, 2);
    $proximo = $l->hitos->where('es_cumplido', false)->sortBy('fecha')->first();
@endphp
<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="text-sm">
            <span class="font-mono font-semibold">{{ $l->codigo }}</span> · {{ $l->estado->etiqueta() }}
            @if ($proximo && ! $cerrada)
                @php $dias = (int) $hoy->diffInDays($proximo->fecha, false); @endphp
                · <span @class(['text-danger-600 dark:text-danger-400' => $dias < 0, 'text-warning-600 dark:text-warning-400' => $dias >= 0 && $dias <= 3])>{{ $proximo->etapa }} {{ $dias >= 0 ? "en {$dias} días" : 'vencida hace '.abs($dias).' días' }}</span>
            @endif
            @if ($l->responsable) · responsable: {{ $l->responsable->name }} @endif
        </div>
        <div class="flex flex-wrap gap-2">
            {{ $this->participarAction }} {{ $this->noParticiparAction }} {{ $this->confirmarSugerenciasAction }} {{ $this->presentarAction }} {{ $this->resultadoAction }}
            <x-filament::button tag="a" :href="$urlFicha" color="gray" size="sm" icon="heroicon-o-pencil-square">Editar cronograma, expediente y consultas</x-filament::button>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-filament::section heading="Bases" compact>
            <dl class="grid grid-cols-2 gap-2 text-sm">
                <dt class="text-gray-500">Procedimiento</dt><dd>{{ $l->tipo_procedimiento?->etiqueta() ?? $l->tipo_procedimiento }}</dd>
                <dt class="text-gray-500">Objeto</dt><dd>{{ $l->objeto }}</dd>
                <dt class="text-gray-500">Valor referencial</dt><dd class="font-mono">{{ $soles($l->valor_referencial) }}</dd>
                <dt class="text-gray-500">Convocatoria</dt><dd>{{ $l->fecha_convocatoria?->format('d/m/Y') }}</dd>
                <dt class="text-gray-500">Bases</dt><dd>@if ($l->url_bases)<a href="{{ $l->url_bases }}" target="_blank" class="text-primary-600 underline">Abrir las bases</a>@else — @endif</dd>
            </dl>
        </x-filament::section>

        <x-filament::section heading="Cronograma" compact>
            <ul class="text-sm divide-y divide-gray-100 dark:divide-white/5">
                @forelse ($l->hitos->sortBy('fecha') as $h)
                    <li class="flex justify-between py-1"><span @class(['line-through text-gray-400' => $h->es_cumplido])>{{ $h->etapa }}</span><span @class(['text-danger-600' => ! $h->es_cumplido && $h->fecha->lt($hoy)])>{{ $h->fecha->format('d/m/Y') }}</span></li>
                @empty
                    <li class="py-1 text-gray-500">Sin cronograma.</li>
                @endforelse
            </ul>
        </x-filament::section>

        <x-filament::section heading="Expediente" compact>
            @php $faltan = $l->documentos->filter(fn ($d) => ! $d->es_listo || ! $d->es_confirmado)->count(); @endphp
            <p class="mb-2 text-sm text-gray-500">{{ $l->documentos->count() }} documentos · {{ $faltan === 0 ? 'completo' : "faltan {$faltan}" }}</p>
            <ul class="text-sm divide-y divide-gray-100 dark:divide-white/5">
                @foreach ($l->documentos->sortBy('orden') as $d)
                    <li class="flex justify-between py-1"><span>{{ $d->nombre }}@if (! $d->es_confirmado) <span class="text-gray-400">(sugerido)</span>@endif</span><span>{{ $d->es_listo ? '✓ listo' : 'pendiente' }}</span></li>
                @endforeach
            </ul>
        </x-filament::section>

        <x-filament::section heading="Consultas" compact>
            <ul class="text-sm divide-y divide-gray-100 dark:divide-white/5">
                @forelse ($l->consultas as $c)
                    <li class="py-1">{{ $c->pregunta }}<br><span class="text-gray-500">{{ $c->respuesta ?? 'Sin respuesta todavía' }}</span></li>
                @empty
                    <li class="py-1 text-gray-500">Sin consultas.</li>
                @endforelse
            </ul>
        </x-filament::section>

        <x-filament::section heading="Propuesta" compact>
            <p class="text-sm">Nuestra oferta: <span class="font-mono">{{ $soles($l->monto_ofertado) }}</span>
                @if ($l->monto_ofertado && $l->valor_referencial) · {{ number_format((float) $l->monto_ofertado / (float) $l->valor_referencial * 100, 1) }} % del referencial @endif</p>
            @if ($historico['minimo'] !== null)
                <p class="mt-1 text-sm text-gray-500">Esta entidad adjudica entre el {{ $historico['minimo'] }} % y el {{ $historico['maximo'] }} % del referencial.</p>
            @endif
        </x-filament::section>

        <x-filament::section heading="Resultado" compact>
            @if ($l->participantes->isEmpty())
                <p class="text-sm text-gray-500">Todavía sin resultado. Al registrarlo, la oportunidad se cierra: ganada con la buena pro, perdida si la ganó otro o quedó desierta.</p>
            @else
                <ul class="text-sm divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($l->participantes->sortBy('puesto') as $p)
                        <li class="flex justify-between py-1"><span @class(['font-semibold' => $p->es_techquk])>{{ $p->puesto ? $p->puesto.'.º ' : '' }}{{ $p->nombre }}</span><span class="font-mono">{{ $soles($p->oferta) }}</span></li>
                    @endforeach
                </ul>
            @endif
        </x-filament::section>
    </div>
    <x-filament-actions::modals />
</div>
