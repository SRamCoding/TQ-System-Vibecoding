{{-- Día de la Madre · fondo · Jardín con flores a los lados. --}}
<rect x="0" y="62" width="80" height="18" fill="#9CC873"/>
@foreach ([[6, '#F29BBF'], [12, '#F6C944'], [66, '#B9A3EC'], [73, '#F29BBF']] as [$x, $c])
    <path d="M{{ $x }} 66v-5" stroke="#5E9E57" stroke-width="0.8"/><circle cx="{{ $x }}" cy="60" r="2" fill="{{ $c }}"/>
@endforeach
