{{-- Plan 1.4c: quiénes juntarían permisos incompatibles con el cambio que se quiso guardar. --}}
<ul class="tq-roles-cruces" data-tq-cruces-frenados>
    @foreach ($cruces as $cruce)
        <li><strong>{{ $cruce['usuario'] }}</strong>: {{ $cruce['permisos'] }}</li>
    @endforeach
</ul>
