{{-- Observación 7.6: el enlace público del formulario, su QR para imprimir y el enlace de WhatsApp. --}}
<div style="display: flex; flex-wrap: wrap; gap: 20px; align-items: center" x-data="{ copiado: '' }">
    <img src="{{ $qr }}" alt="QR del formulario" style="width: 180px; height: 180px; background: #fff; border-radius: 10px; padding: 6px">
    <div style="flex: 1; min-width: 14rem; display: flex; flex-direction: column; gap: 10px; font-size: 14px">
        <div><div style="font-size: 12px; color: var(--gray-500)">Formulario</div><a href="{{ $url }}" target="_blank" style="color: var(--primary-600); word-break: break-all">{{ $url }}</a></div>
        <div><div style="font-size: 12px; color: var(--gray-500)">WhatsApp</div><a href="{{ $whatsapp }}" target="_blank" style="color: var(--primary-600); word-break: break-all">{{ $whatsapp }}</a></div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap">
            <x-filament::button size="sm" color="gray" x-on:click="navigator.clipboard.writeText({{ \Illuminate\Support\Js::from($url) }}); copiado = 'form'"><span x-text="copiado === 'form' ? 'Copiado' : 'Copiar enlace'"></span></x-filament::button>
            <x-filament::button size="sm" color="gray" x-on:click="navigator.clipboard.writeText({{ \Illuminate\Support\Js::from($whatsapp) }}); copiado = 'wa'"><span x-text="copiado === 'wa' ? 'Copiado' : 'Copiar WhatsApp'"></span></x-filament::button>
            <x-filament::button size="sm" color="gray" tag="a" :href="$qr" download="qr-campania.svg">Descargar QR</x-filament::button>
        </div>
    </div>
</div>
