{{--
    Una tarjeta de «Mien» (plan 1.4l, D3): título y lo que dibuja su módulo; si falló, el aviso. La vista del módulo se
    dibuja aquí, dentro del ciclo de Livewire (así puede usar `$this`); si falla al dibujarse, el error va al registro.
--}}
@php
    /** @var (\Closure(): string)|null $dibujar */
    try {
        $cuerpo = $dibujar === null ? null : $dibujar();
    } catch (\Throwable $error) {
        report($error);
        $cuerpo = null;
    }
@endphp
<section class="fi-section tq-mien-tarjeta" data-tq-tarjeta>
    <h2 class="tq-mien-tarjeta-titulo">{{ $titulo }}</h2>
    @if ($cuerpo === null)
        <p class="tq-mien-tarjeta-fallida">No se pudo cargar. Vuelve a intentarlo más tarde.</p>
    @else
        {!! $cuerpo !!}
    @endif
</section>
