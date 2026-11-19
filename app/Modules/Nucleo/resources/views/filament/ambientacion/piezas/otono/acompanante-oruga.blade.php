{{-- Otoño · acompañante · Oruga que avanza. --}}
<style>
    .tq-corre .tq-pz-oruga circle { animation: tq-pz-or 0.6s ease-in-out 3 alternate both; }
    @keyframes tq-pz-or { from { transform: none; } to { transform: translateY(-1.4px); } }
</style>
<g class="tq-pz-oruga">
    @foreach ([10, 13, 16, 19, 22] as $i => $x)
        <circle cx="{{ $x }}" cy="72" r="2" fill="#8FB35A" style="animation-delay: {{ $i * 0.08 }}s"/>
    @endforeach
    <circle cx="24.5" cy="71" r="2.3" fill="#7FA34C"/>
    <circle cx="25.2" cy="70.4" r="0.5" fill="#2E2E2E"/>
</g>
