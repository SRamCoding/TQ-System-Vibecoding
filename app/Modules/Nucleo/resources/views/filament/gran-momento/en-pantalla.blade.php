{{--
    El gran momento pendiente (plan 1.4d, D5): espera a que la pestaña se vea; entonces marca «Lo vi» y solo lo empieza
    si el servidor dice que fue la primera marca. Si no, se quita sin mostrarse.
--}}
<div
    x-data="{
        hecho: false,
        intentar() {
            if (this.hecho || document.visibilityState !== 'visible') return
            this.hecho = true
            this.$wire.marcar().then((primera) => primera ? this.$dispatch('tq-gran-momento-empezar') : this.$el.remove())
        },
    }"
    x-init="document.addEventListener('visibilitychange', () => intentar()); intentar()"
    data-tq-gran-momento-pendiente
>
    @if ($escena !== null)
        @include('nucleo::filament.gran-momento.marco', ['escena' => $escena, 'animado' => true, 'esperar' => true])
    @endif
</div>
