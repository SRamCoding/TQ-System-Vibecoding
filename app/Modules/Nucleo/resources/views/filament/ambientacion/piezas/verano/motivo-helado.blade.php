{{-- Verano · motivo · Helado: un cono con dos bolas. --}}
<style>
    .tq-corre .tq-pz-bola { animation: tq-pz-bl 0.7s cubic-bezier(0.3, 1.5, 0.5, 1) both; transform-box: fill-box; transform-origin: center bottom; }
    @keyframes tq-pz-bl { from { transform: translateY(-10px) scale(0.6); opacity: 0; } to { transform: none; opacity: 1; } }
</style>
<path d="M33 50l7 20 7-20z" fill="#E0A96D"/>
<path d="M35 54l9 4M36 58l7 3M38 62l4 2" stroke="#C48A4E" stroke-width="0.8"/>
<circle class="tq-pz-bola" cx="40" cy="47" r="7" fill="#F2A7C3"/>
<circle class="tq-pz-bola" style="animation-delay: 0.2s" cx="40" cy="38" r="6" fill="#FFF3D6"/>
<circle cx="42" cy="34" r="1.4" fill="#E8414F"/>
