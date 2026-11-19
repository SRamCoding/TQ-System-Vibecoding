{{-- Invierno · Chullo: cae el chullo andino y se mueven sus pompones. --}}
<svg viewBox="0 0 64 64" aria-hidden="true">
    <style>
        .tq-corre .tq-ic-chullo { transform-origin: center top; animation: tq-ic-cae 0.9s cubic-bezier(0.3, 1.4, 0.5, 1) both; }
        .tq-corre .tq-ic-pompon { transform-origin: center top; animation: tq-ic-mece 0.3s 0.8s ease-in-out 4 alternate both; }
        @keyframes tq-ic-cae { from { transform: translateY(-24px); opacity: 0; } to { transform: none; opacity: 1; } }
        @keyframes tq-ic-mece { from { transform: rotate(-12deg); } to { transform: rotate(12deg); } }
    </style>
    <g class="tq-ic-chullo">
        <circle cx="32" cy="6" r="4" fill="#F2C14E"/>
        <path d="M12 38c0-18 8-30 20-30s20 12 20 30z" fill="#C0392B"/>
        <path d="M14 26h36M13 32h38" stroke="#F6F0F0" stroke-width="3"/>
        <path d="M18 29l3-3 3 3 3-3 3 3 3-3 3 3 3-3 3 3 3-3 3 3" stroke="#3D4772" stroke-width="1.6" fill="none"/>
        <path d="M12 38h40v4H12z" fill="#3D4772"/>
        <path d="M14 42v8M50 42v8" stroke="#C0392B" stroke-width="3"/>
    </g>
    <circle class="tq-ic-pompon" cx="14" cy="53" r="3.6" fill="#F2C14E"/>
    <circle class="tq-ic-pompon" cx="50" cy="53" r="3.6" fill="#F2C14E"/>
</svg>
