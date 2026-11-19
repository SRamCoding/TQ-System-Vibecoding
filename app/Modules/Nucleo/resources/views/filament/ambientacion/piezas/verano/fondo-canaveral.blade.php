{{-- Verano · fondo · Cañaveral: campo de caña verde del valle. --}}
<rect x="0" y="62" width="80" height="18" fill="#9CC873"/>
@foreach ([2, 7, 12, 17, 60, 65, 70, 75] as $i => $x)
    <path d="M{{ $x }} 66v-{{ 10 + ($i % 3) * 2 }}" stroke="#6E9F45" stroke-width="1.4" stroke-linecap="round"/>
    <path d="M{{ $x }} {{ 58 - ($i % 3) * 2 }}c-3-1-4-3-4-5M{{ $x }} {{ 60 - ($i % 3) * 2 }}c3-1 4-3 4-5" stroke="#86B85A" stroke-width="1" fill="none" stroke-linecap="round"/>
@endforeach
