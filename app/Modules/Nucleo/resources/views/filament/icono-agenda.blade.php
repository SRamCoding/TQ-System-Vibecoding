{{--
    MV-055: el ícono de la agenda estilo aplicación, «la hoja del día»: hoja blanca con banda turquesa, el día de la
    semana abreviado arriba, el número del día grande y un relojito dorado. Muestra siempre hoy, en la hora de Lima, y
    se actualiza solo al cambiar el día. No toma el color de franja, tema ni estación. En modo claro, un borde fino gris
    cálido. Recibe `$tamano` (px).
--}}
@php
    $hoy = \Carbon\CarbonImmutable::parse(\App\Modules\Nucleo\Tiempo\Calendario::hoy(), 'America/Lima');
    $dias = ['LUN', 'MAR', 'MIÉ', 'JUE', 'VIE', 'SÁB', 'DOM'];
    $largos = ['lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];
    $id = 'tqag'.substr(md5(uniqid('', true)), 0, 5);
@endphp
<svg class="tq-icono-agenda" width="{{ $tamano ?? 32 }}" height="{{ $tamano ?? 32 }}" viewBox="0 0 48 48" role="img"
    aria-label="Agenda: {{ $largos[$hoy->dayOfWeekIso - 1] }} {{ $hoy->day }}"
    x-data="{
        dia: @js($dias[$hoy->dayOfWeekIso - 1]), numero: @js((string) $hoy->day),
        hoy() {
            const p = Object.fromEntries(new Intl.DateTimeFormat('en-US', { timeZone: 'America/Lima', weekday: 'short', day: 'numeric' }).formatToParts(new Date()).map((x) => [x.type, x.value]))
            this.dia = { Mon: 'LUN', Tue: 'MAR', Wed: 'MIÉ', Thu: 'JUE', Fri: 'VIE', Sat: 'SÁB', Sun: 'DOM' }[p.weekday] ?? this.dia
            this.numero = p.day ?? this.numero
        },
    }" x-init="setInterval(() => hoy(), 60000)">
    <defs>
        <linearGradient id="{{ $id }}" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#00B8D9"/><stop offset="1" stop-color="#00DAFF"/></linearGradient>
    </defs>
    <rect class="tq-icono-agenda-hoja" x="3" y="3" width="42" height="42" rx="10" fill="#FFFFFF"/>
    <path d="M3 13a10 10 0 0 1 10-10h22a10 10 0 0 1 10 10v2H3z" fill="url(#{{ $id }})"/>
    <text x="24" y="12.6" text-anchor="middle" font-family="Inter, sans-serif" font-size="7" font-weight="800" fill="#FFFFFF" letter-spacing=".6" x-text="dia">{{ $dias[$hoy->dayOfWeekIso - 1] }}</text>
    <text x="24" y="36" text-anchor="middle" font-family="Inter, sans-serif" font-size="20" font-weight="800" fill="#1F2328" x-text="numero">{{ $hoy->day }}</text>
    <circle cx="38" cy="38" r="7.5" fill="#F2C14E" stroke="#FFFFFF" stroke-width="1.6"/>
    <path d="M38 34.6V38l2.2 1.4" stroke="#5A3E00" stroke-width="1.5" stroke-linecap="round" fill="none"/>
</svg>
