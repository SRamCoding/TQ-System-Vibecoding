{{-- Inicio (plan 1.13, D6): lo que falta de la configuración inicial, con un enlace al asistente. --}}
@php($faltan = $this->faltan())
<x-filament-widgets::widget>
    <div class="fi-section tq-aviso-configuracion" data-tq-aviso-configuracion>
        <style>
            .tq-aviso-configuracion { display: flex; align-items: center; gap: 1rem; padding: 0.875rem 1.125rem; border-left: 4px solid var(--warning-500); }
            .tq-aviso-configuracion-icono { flex: none; color: var(--warning-500); }
            .tq-aviso-configuracion-texto { flex: 1; min-width: 0; font-size: 0.875rem; }
            .tq-aviso-configuracion-titulo { display: block; font-weight: 700; }
            .tq-aviso-configuracion-detalle { display: block; color: var(--gray-500); }
            @media (max-width: 640px) { .tq-aviso-configuracion { flex-wrap: wrap; } }
        </style>
        <x-filament::icon icon="heroicon-o-rocket-launch" class="tq-aviso-configuracion-icono h-6 w-6" />
        <span class="tq-aviso-configuracion-texto">
            <span class="tq-aviso-configuracion-titulo">
                {{ count($faltan) === 1 ? 'Falta 1 paso de la configuración inicial' : 'Faltan '.count($faltan).' pasos de la configuración inicial' }}
            </span>
            <span class="tq-aviso-configuracion-detalle">{{ implode(' · ', $faltan) }}</span>
        </span>
        <x-filament::button tag="a" :href="\App\Modules\Nucleo\Filament\Pages\ConfiguracionInicial::getUrl()" size="sm" icon="heroicon-m-arrow-right" icon-position="after">
            Seguir configurando
        </x-filament::button>
    </div>
</x-filament-widgets::widget>
