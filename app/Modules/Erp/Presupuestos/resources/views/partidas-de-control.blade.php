{{--
    La pestaña «Partidas de control» del proyecto (plan 2.10; 2.9b; 2.11, D9) con el panel común de plantillas, que se
    desliza desde la derecha encima de la tabla, como el APU: guardar el árbol como plantilla y administrarlas. Usar una
    va por «Armar el árbol» → «Desde una plantilla», así que el panel no ofrece «Usar».
--}}
<div class="tq-catalogos">
    @include('nucleo::filament.partials.cajas-con-panel')

    <div @class(['tq-catalogos-detras' => $this->plantillasAbiertas])>
        {{ $this->table }}
    </div>

    @if ($this->plantillasAbiertas)
        @include('nucleo::filament.plantillas.panel', [
            'tipo' => \App\Modules\Erp\Presupuestos\Plantillas\PlantillaArbol::CLAVE,
            'documentoId' => $this->arbolParaPlantillas(),
            'ofrecerUsar' => false,
            'alCerrar' => 'cerrarPlantillas',
        ])
    @endif

    <x-filament-actions::modals />
</div>
