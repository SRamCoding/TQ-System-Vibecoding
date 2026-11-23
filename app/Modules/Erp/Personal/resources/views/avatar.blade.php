{{--
    Observación 6.7 (MV-067): la cara de un colaborador en círculo. Con foto, su foto; sin foto, el avatar por defecto de
    hombre o de mujer: un busto ilustrado en tonos neutros que salen de los colores del ambiente (`--tq-superficie` y
    `--tq-texto-suave`), así funciona en claro y en oscuro. Recibe `$a` (de `PersonalService::avatar()`) y `$tam` en px.
--}}
@php($tam = $tam ?? 40)
<span class="tq-avatar" style="width: {{ $tam }}px; height: {{ $tam }}px" role="img" aria-label="{{ $a['nombre'] }}">
    @once
        <style>
            .tq-avatar { --tq-av-base: var(--tq-superficie, #fff); --tq-av-tinta: var(--tq-texto-suave, #7A8088);
                display: inline-flex; flex-shrink: 0; border-radius: 9999px; overflow: hidden; background: color-mix(in oklab, var(--tq-av-tinta) 16%, var(--tq-av-base));
                box-shadow: 0 0 0 1px color-mix(in oklab, var(--tq-av-tinta) 25%, transparent); }
            .dark .tq-avatar { --tq-av-base: var(--tq-superficie, #33302B); --tq-av-tinta: var(--tq-texto-suave, #A8ADB4); }
            .tq-avatar img { width: 100%; height: 100%; object-fit: cover; }
            .tq-avatar svg { width: 100%; height: 100%; }
            .tq-avatar .piel { fill: color-mix(in oklab, var(--tq-av-tinta) 34%, var(--tq-av-base)); }
            .tq-avatar .pelo { fill: color-mix(in oklab, var(--tq-av-tinta) 88%, #000 12%); }
            .tq-avatar .ropa { fill: color-mix(in oklab, var(--tq-av-tinta) 62%, var(--tq-av-base)); }
            .tq-avatar .sombra { fill: color-mix(in oklab, var(--tq-av-tinta) 48%, var(--tq-av-base)); }
        </style>
    @endonce
    @if ($a['foto'] !== null)
        <img src="{{ $a['foto'] }}" alt="" loading="lazy" draggable="false">
    @elseif ($a['sexo'] === 'mujer')
        <svg viewBox="0 0 64 64" aria-hidden="true">
            <path class="pelo" d="M18.5 31C16.5 16.5 24.5 11 32 11s15.5 5.5 13.5 20c0 8 2.5 13.5-1 18H19.5c-3.5-4.5-1-10-1-18Z"/>
            <path class="ropa" d="M12 64c0-12.5 9-19 20-19s20 6.5 20 19Z"/>
            <path class="sombra" d="M27 37h10v7.5c0 2.8-2.2 4.5-5 4.5s-5-1.7-5-4.5Z"/>
            <ellipse class="piel" cx="32" cy="27.5" rx="10.5" ry="12.2"/>
            <path class="pelo" d="M21.3 25.5C21.5 17.5 26.5 14 32 14s10.8 3.3 10.7 11.5c-2.8-4.8-7-6.3-12.2-5-3.8 1-6.7 2.8-9.2 5Z"/>
        </svg>
    @else
        <svg viewBox="0 0 64 64" aria-hidden="true">
            <path class="ropa" d="M9 64c0-13.5 10-20 23-20s23 6.5 23 20Z"/>
            <path class="sombra" d="M26.5 36h11v8c0 3-2.5 5-5.5 5s-5.5-2-5.5-5Z"/>
            <ellipse class="piel" cx="32" cy="27" rx="11" ry="12.6"/>
            <path class="pelo" d="M20.6 26.5C19.8 16 25.8 12 32 12s12.4 4 11.5 14.5c-1.4-4.6-5-7.2-11.5-7.2s-10 2.6-11.4 7.2Z"/>
        </svg>
    @endif
</span>
