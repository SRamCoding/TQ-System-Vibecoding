{{-- Otoño · fondo · Huerto: surcos de tierra recién cosechados. --}}
<rect x="0" y="60" width="80" height="20" fill="#A47A4E"/>
@foreach ([64, 69, 74, 79] as $y)
    <path d="M0 {{ $y }}h80" stroke="#8A6143" stroke-width="1.2"/>
@endforeach
<path d="M0 60h80" stroke="#8FA05A" stroke-width="2"/>
