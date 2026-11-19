{{--
    Plan 1.4g, D4: el selector de tema de Filament avisa con `theme-changed` (también al cargar, con el tema que ya
    tenía). Solo se guarda si es distinto del que dice la base, para no escribir en cada página.
--}}
<div
    x-data="{ tema: @js($tema) }"
    x-on:theme-changed.window="if ($event.detail && $event.detail !== tema) { tema = $event.detail; $wire.guardar($event.detail) }"
    {{-- Un cambio desde «Mis preferencias» ya está guardado: solo se anota, para no volver a guardarlo. --}}
    x-on:{{ \App\Modules\Nucleo\Filament\Apariencia::EVENTO }}.window="if ($event.detail?.tema) { tema = $event.detail.tema }"
    hidden
></div>
