{{--
    El logo de Techquk junto a su nombre, en la barra superior y en el ingreso (plan 1.4b, D9). Se construye en la primera
    pantalla tras el ingreso, al actualizar la página, al cambiar entre modo claro y oscuro, y al pasar el mouse (o
    tocarlo); al ir de una pantalla a otra queda quieto, ya construido (plan del logo, D1): un bloque blanco se rompe en escombros en tres tandas (arriba,
    abajo a la izquierda, abajo a la derecha), las piezas se colorean (el bloque es negro en modo claro, para que se
    note la fragmentación sobre la barra blanca, y blanco en modo oscuro) y cada una se ilumina desde adentro con su color.
    En cinco fechas aparece además su detalle (gorro, escudo, Biblia, estrella). Con «reducir movimiento», quieto. Recibe `$paleta`, `$escombros` (LogoTechquk) y
    `$animar` (la primera pantalla tras el ingreso) y `$espera` (la pantalla de ingreso: las piezas en su lugar, tenues,
    laten a ratos, cada vez distinto, sin construirse nunca; con el mouse, se ve su cuadrícula y forcejean; D2 y D3). La pieza blanca lleva un borde fino en modo
    claro: sobre la barra blanca no se vería.
--}}
@php
    $radio = $paleta['intensa'] ? 11 : 3.5;
    $piezas = \App\Modules\Nucleo\Saludo\LogoTechquk::PIEZAS;
    $centros = \App\Modules\Nucleo\Saludo\LogoTechquk::CENTROS;
    $luz = fn (string $color): string => strtoupper($color) === '#FFFFFF' ? 'var(--tq-luz-blanca)' : $color;
    // La clase que cambia su tono en modo claro, solo para los colores de siempre de la marca.
    $tono = fn (string $color): string => match (strtoupper($color)) {
        strtoupper(\App\Modules\Nucleo\Navegacion\ColoresMarca::TURQUESA) => 'turquesa',
        strtoupper(\App\Modules\Nucleo\Navegacion\ColoresMarca::MORADO) => 'morado',
        default => '',
    };
@endphp
<style>
    /* La luz de una pieza blanca: en modo claro, blanco lavanda para que se distinga del fondo y brille igual que las de
       color (pedido del desarrollador); en modo oscuro, blanco. */
    .tq-logo { display: inline-flex; align-items: center; gap: 0.6rem; height: 100%; --tq-logo-alto: 1.5rem; --tq-logo-nombre-alto: 1rem; --tq-logo-borde: {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::AZUL_NOCHE }}59; --tq-luz-blanca: #A9AEDB; --tq-logo-bloque: #111111; }
    .dark .tq-logo { --tq-logo-borde: transparent; --tq-luz-blanca: #FFFFFF; --tq-logo-bloque: #FFFFFF; }
    /* Modo claro (pedido del desarrollador, 2026-10-26): la pieza celeste, más turquesa, y la morada azulada, más morada.
       Solo con los colores de siempre: las fechas con su paleta (Navidad, Fiestas Patrias…) no cambian. En modo oscuro,
       los de la marca. */
    .tq-logo .tq-lg-turquesa { fill: {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::TURQUESA_LOGO_CLARO }}; stroke: {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::TURQUESA_LOGO_CLARO }}; --brillo: {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::TURQUESA_LOGO_CLARO }}; }
    .tq-logo .tq-lg-morado { fill: {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::MORADO_CLARO }}; stroke: {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::MORADO_CLARO }}; --brillo: {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::MORADO_CLARO }}; }
    .tq-logo .tq-lg-luz-turquesa { stop-color: {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::TURQUESA_LOGO_CLARO }} !important; }
    .tq-logo .tq-lg-luz-morado { stop-color: {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::MORADO_CLARO }} !important; }
    .dark .tq-logo .tq-lg-turquesa { fill: {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::TURQUESA }}; stroke: {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::TURQUESA }}; --brillo: {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::TURQUESA }}; }
    .dark .tq-logo .tq-lg-morado { fill: {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::MORADO }}; stroke: {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::MORADO }}; --brillo: {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::MORADO }}; }
    .dark .tq-logo .tq-lg-luz-turquesa { stop-color: {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::TURQUESA }} !important; }
    .dark .tq-logo .tq-lg-luz-morado { stop-color: {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::MORADO }} !important; }
    /* El ícono achatado al 85 % de su alto (plan 1.4ae, D1, pedido del desarrollador, 2026-11-16): mismo ancho que si
       fuera de `--tq-logo-alto` / 0.85, con el dibujo estirado a esa caja (`preserveAspectRatio="none"`). */
    .tq-logo svg { flex: none; height: var(--tq-logo-alto); width: calc(var(--tq-logo-alto) * 122 / 119 / 0.85); overflow: visible; }
    .tq-logo svg [class] { transform-box: fill-box; }
    /* El nombre en la letra de la marca, Audiowide (licencia OFL, servida desde public/fonts, sin pedir nada a otro
       sitio): vuelve el 2026-11-16 (plan 1.4ae, D2), «ya es marca de la empresa». Desde el 2026-11-17 (plan 1.4af,
       pedido del desarrollador) el ícono va más grande y el nombre más chico, cada uno con su número: el alto de las
       mayúsculas es `--tq-logo-nombre-alto` y ya no coincide con el del ícono («no importa si no están alineados»); el
       nombre queda centrado a lo alto del ícono. Con las medidas de Audiowide (unidades de 2048): mayúscula 1434
       (0.7002 em); ascendente 2027 y descendente 584, así que con interlineado 1 la parte alta de la mayúscula queda a
       0.1521 em del borde de su línea: el texto sube eso y la caja del nombre mide justo sus mayúsculas. */
    @font-face { font-family: 'Audiowide'; src: url('{{ asset('fonts/audiowide/audiowide-latin.woff2') }}') format('woff2'); font-display: swap; }
    .tq-logo-nombre { display: inline-block; height: var(--tq-logo-nombre-alto); font-family: 'Audiowide', ui-sans-serif, sans-serif; font-weight: 400; font-size: calc(var(--tq-logo-nombre-alto) / 0.7002); line-height: 1; text-transform: uppercase; letter-spacing: 0.04em; white-space: nowrap; }
    .tq-logo-nombre > span { display: block; position: relative; top: -0.1521em; }
    .tq-lg-esc { opacity: 0; transform-origin: center; }
    /* Quieto, el logo es el de la animación terminada: cada pieza con su luz en reposo (en el aniversario, D10, más
       intensa). Así no cambia entre una pantalla que lo animó y otra que no. */
    .tq-lg-luz { opacity: var(--reposo); transform-origin: center; }
    .tq-lg-pieza { filter: drop-shadow(0 0 calc(var(--radio) * 1px) var(--brillo)); }
    .tq-lg-detalle { transform-origin: center; }
    .tq-corre .tq-lg-bloque { animation: tq-lg-tiembla 1.4s ease-in-out both; }
    .tq-corre .tq-lg-esc { animation: tq-lg-cae 0.85s ease-in both; }
    /* Las dos en una sola declaración: dos reglas con «animation» se pisan y solo quedaba el brillo. */
    .tq-corre .tq-lg-pieza { animation: tq-lg-pinta 0.5s 1.6s ease-out both, tq-lg-brilla 1.1s 1.95s ease-out both; }
    .dark .tq-corre .tq-lg-pieza { animation-name: tq-lg-pinta-oscuro, tq-lg-brilla; }
    .tq-corre .tq-lg-luz { animation: tq-lg-emerge 1.1s 1.9s ease-out both; }
    .tq-corre .tq-lg-detalle { animation: tq-lg-aparece 0.6s 2.1s cubic-bezier(0.3, 1.5, 0.5, 1) both; }
    @keyframes tq-lg-tiembla {
        0%, 20%, 50%, 78%, 100% { transform: none; }
        23%, 53%, 81% { transform: translate(0.8px, -0.6px); }
        26%, 56%, 84% { transform: translate(-0.8px, 0.6px); }
    }
    @keyframes tq-lg-cae {
        from { opacity: 1; transform: none; }
        18% { opacity: 1; transform: translate(calc(var(--dx) * 0.15px), -3px) rotate(calc(var(--giro) * 0.1deg)); }
        to { opacity: 0; transform: translate(calc(var(--dx) * 1px), calc(var(--dy) * 1px)) rotate(calc(var(--giro) * 1deg)) scale(0.6); }
    }
    /* Una por modo: el navegador no toma la variable del bloque dentro de @keyframes. */
    @keyframes tq-lg-pinta { from { fill: #111111; stroke: #111111; } }
    @keyframes tq-lg-pinta-oscuro { from { fill: #FFFFFF; stroke: #FFFFFF; } }
    @keyframes tq-lg-brilla {
        from { filter: none; }
        55% { filter: drop-shadow(0 0 calc(var(--radio) * 2.2px) var(--brillo)); }
        to { filter: drop-shadow(0 0 calc(var(--radio) * 1px) var(--brillo)); }
    }
    @keyframes tq-lg-emerge {
        from { opacity: 0; transform: scale(0.2); }
        60% { opacity: 0.95; transform: scale(1.15); }
        to { opacity: var(--reposo); transform: scale(1); }
    }
    @keyframes tq-lg-aparece { from { opacity: 0; transform: scale(0) translateY(-6px); } to { opacity: 1; transform: none; } }
    /* La espera del ingreso (D2, D3): sin escombros ni luz, las piezas tenues laten (crecen y se achican) a ratos, cada
       vez distinto, mientras la pestaña está a la vista; con el mouse se ve la cuadrícula de los escombros y forcejean
       como si quisieran salir (pedido del desarrollador, 2026-10-28). El movimiento lo arma `tqLogoEspera`, abajo, al
       azar: así se ve natural. Solo si se permite el movimiento: si no, el logo normal y quieto (D5). */
    @media (prefers-reduced-motion: no-preference) {
        html:not(.tq-sin-animaciones) .tq-espera .tq-lg-pieza,
        html:not(.tq-sin-animaciones) .tq-espera .tq-lg-detalle { opacity: 0.45; filter: none; }
        html:not(.tq-sin-animaciones) .tq-espera .tq-lg-luz { opacity: 0; }
        html:not(.tq-sin-animaciones) .tq-espera:hover .tq-lg-esc { opacity: 1; fill: transparent !important; stroke: var(--tq-logo-cuadricula) !important; stroke-width: 0.6; transition: opacity 0.25s; }
    }
    .tq-espera .tq-lg-bloque { transform-origin: center; }
    .tq-logo { --tq-logo-cuadricula: {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::AZUL_NOCHE }}8C; }
    .dark .tq-logo { --tq-logo-cuadricula: #FFFFFF8C; }
    @media (prefers-reduced-motion: reduce) {
        .tq-logo svg [class] { animation: none !important; }
    }
    /* La preferencia «reducir animaciones» (plan 1.4g, D5), aunque el equipo no lo pida. */
    html.tq-sin-animaciones .tq-logo svg [class] { animation: none !important; }
</style>
@if ($espera ?? false)
    <script>
        // La espera del ingreso: cada latido y cada forcejeo se arman al azar (cuántos golpes, qué tan fuerte, cuánto
        // descansa), para que nunca se repita igual. Con la pestaña oculta no se programa nada; al volver, sigue.
        document.addEventListener('alpine:init', () => {
            Alpine.data('tqLogoEspera', () => ({
                encima: false,
                reloj: null,
                animaciones: [],
                init() {
                    this.siguiente(700);
                    document.addEventListener('visibilitychange', () => document.hidden ? this.detener() : this.siguiente(500));
                },
                quieto() {
                    return matchMedia('(prefers-reduced-motion: reduce)').matches || document.documentElement.classList.contains('tq-sin-animaciones');
                },
                azar(min, max) { return min + Math.random() * (max - min) },
                detener() {
                    clearTimeout(this.reloj);
                    this.animaciones.forEach((animacion) => animacion.cancel());
                    this.animaciones = [];
                },
                entrar() { this.encima = true; this.siguiente(0) },
                salir() { this.encima = false; this.siguiente(this.azar(600, 1400)) },
                siguiente(espera) {
                    this.detener();
                    if (document.hidden) return;
                    this.reloj = setTimeout(() => this.quieto() ? this.siguiente(2000) : (this.encima ? this.forcejear() : this.latir()), espera);
                },
                animar(elemento, cuadros, duracion, alTerminar) {
                    const animacion = elemento.animate(cuadros, { duration: duracion, easing: 'ease-in-out' });
                    this.animaciones.push(animacion);
                    if (alTerminar) animacion.finished.then(alTerminar, () => {});
                },
                // Late: de dos a seis golpes, cada uno de distinta fuerza y a distinto ritmo; luego descansa de 2,5 a 6 s.
                latir() {
                    const golpes = Math.round(this.azar(2, 6));
                    const fuerza = this.azar(0.06, 0.16);
                    const cuadros = [{ transform: 'none' }];
                    for (let i = 0; i < golpes; i++) {
                        const golpe = fuerza * this.azar(0.55, 1.15);
                        cuadros.push({ transform: `scale(${1 + golpe})` }, { transform: `scale(${1 - golpe * this.azar(0.4, 0.8)})` });
                    }
                    cuadros.push({ transform: 'none' });
                    this.animar(this.$el.querySelector('.tq-lg-bloque'), cuadros, golpes * this.azar(120, 240), () => this.siguiente(this.azar(2500, 6000)));
                },
                // Forcejea: empujones desde adentro hacia un lado distinto cada vez, y cada pieza se separa del centro a su
                // ritmo, como si algo quisiera romper el bloque. Pausas cortas e irregulares mientras siga el mouse.
                forcejear() {
                    const empujones = Math.round(this.azar(2, 5));
                    const cuadros = [{ transform: 'none' }];
                    for (let i = 0; i < empujones; i++) {
                        const angulo = this.azar(0, Math.PI * 2);
                        const distancia = this.azar(1.5, 4.5);
                        cuadros.push(
                            { transform: `translate(${Math.cos(angulo) * distancia}px, ${Math.sin(angulo) * distancia}px) rotate(${this.azar(-4, 4)}deg) scale(${1 + this.azar(0.08, 0.22)})` },
                            { transform: `scale(${1 - this.azar(0.02, 0.08)})` },
                        );
                    }
                    cuadros.push({ transform: 'none' });
                    const duracion = empujones * this.azar(140, 260);
                    this.$el.querySelectorAll('.tq-lg-pieza').forEach((pieza) => {
                        const caja = pieza.getBBox();
                        const dx = caja.x + caja.width / 2 - 61;
                        const dy = caja.y + caja.height / 2 - 59.5;
                        const largo = Math.hypot(dx, dy) || 1;
                        const empuje = this.azar(1, 3.5);
                        this.animar(pieza, [
                            { transform: 'none' },
                            { transform: `translate(${dx / largo * empuje}px, ${dy / largo * empuje}px)`, offset: this.azar(0.2, 0.6) },
                            { transform: 'none' },
                        ], duracion * this.azar(0.6, 1));
                    });
                    this.animar(this.$el.querySelector('.tq-lg-bloque'), cuadros, duracion, () => this.siguiente(this.azar(120, 700)));
                },
            }));
        });
    </script>
@endif
<span
    @class(['tq-logo', 'tq-espera' => $espera ?? false])
    data-logo="{{ $paleta['intensa'] ? 'intenso' : 'normal' }}"
    {{-- Se construye en la primera pantalla tras el ingreso (`data-animar`, lo marca el servidor) y al actualizar la
         página (lo dice el navegador), no al ir de una pantalla a otra; además, en cada cambio de modo claro u oscuro
         (Filament avisa con `theme-changed`) y al pasar el mouse o tocarlo (plan del logo, D1). El selector de modo de
         Filament también avisa `theme-changed` al cargar cada página, con el tema que ya había: por eso el logo
         recuerda el suyo y solo corre si el aviso trae otro. --}}
    @if ($espera ?? false)
        {{-- En el ingreso, la espera nunca termina: ni al cargar, ni al cambiar de modo, ni con el mouse (D2, D3). Solo se
             detiene con la pestaña oculta. --}}
        x-data="tqLogoEspera"
        x-on:mouseenter="entrar()"
        x-on:mouseleave="salir()"
    @else
    @if ($animar ?? false) data-animar="1" @endif
    x-data="{
        tema: (() => { try { return localStorage.getItem('theme') } catch (e) { return null } })() ?? @js(filament()->getDefaultThemeMode()->value),
        correr() { $el.classList.remove('tq-corre'); void $el.offsetWidth; $el.classList.add('tq-corre') },
        alCargar() { return $el.dataset.animar === '1' || performance.getEntriesByType('navigation')[0]?.type === 'reload' },
    }"
    x-init="if (alCargar()) correr()"
    x-on:theme-changed.window="if ($event.detail !== tema) { tema = $event.detail; correr() }"
    x-on:mouseenter="correr()"
    x-on:touchstart.passive="correr()"
    @endif
>
    {{-- En modo desarrollo, un clic sobre el ícono (no sobre el nombre, que sigue llevando al inicio) deja cambiarlo
         (plan 1.4i, D3; pedido del desarrollador, 2026-10-23). --}}
    <svg @if (\App\Modules\Nucleo\Desarrollo\ModoDesarrollo::activo()) data-tq-pieza="{{ \App\Modules\Nucleo\Desarrollo\Piezas\LogoDeFiesta::CLAVE }}" @endif viewBox="0 0 122 119" preserveAspectRatio="none" role="img" aria-label="Techquk" style="--radio: {{ $radio }}; --reposo: {{ $paleta['intensa'] ? 1 : 0.35 }}">
        <defs>
            @foreach ($centros as $nombre => $centro)
                <radialGradient id="tq-logo-luz-{{ $nombre }}{{ $sufijo ?? '' }}">
                    <stop offset="0" @if ($tono($paleta[$nombre]) !== '') class="tq-lg-luz-{{ $tono($paleta[$nombre]) }}" @endif style="stop-color: {{ $luz($paleta[$nombre]) }}" stop-opacity="0.85"/>
                    <stop offset="1" @if ($tono($paleta[$nombre]) !== '') class="tq-lg-luz-{{ $tono($paleta[$nombre]) }}" @endif style="stop-color: {{ $luz($paleta[$nombre]) }}" stop-opacity="0"/>
                </radialGradient>
            @endforeach
        </defs>
        {{-- Cada pieza se ilumina desde adentro con su propio color. --}}
        @foreach ($centros as $nombre => [$cx, $cy])
            <circle class="tq-lg-luz" cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $paleta['intensa'] ? 62 : 34 }}" fill="url(#tq-logo-luz-{{ $nombre }}{{ $sufijo ?? '' }})"/>
        @endforeach
        <g class="tq-lg-bloque">
            @foreach ($piezas as $nombre => $puntos)
                @php $color = $paleta[$nombre]; @endphp
                <polygon
                    class="tq-lg-pieza @if ($tono($color) !== '') tq-lg-{{ $tono($color) }} @endif"
                    points="{{ $puntos }}"
                    fill="{{ $color }}"
                    stroke="{{ strtoupper($color) === '#FFFFFF' ? 'var(--tq-logo-borde)' : $color }}"
                    stroke-width="2"
                    stroke-linejoin="round"
                    style="--brillo: {{ $luz($color) }}"
                />
            @endforeach
            @foreach ($escombros as $e)
                <rect
                    class="tq-lg-esc"
                    x="{{ $e['x'] }}" y="{{ $e['y'] }}" width="{{ $e['lado'] + 0.6 }}" height="{{ $e['lado'] + 0.6 }}"
                    stroke-width="0.4"
                    style="fill: var(--tq-logo-bloque); stroke: var(--tq-logo-borde); --dx: {{ $e['dx'] }}; --dy: {{ $e['dy'] }}; --giro: {{ $e['giro'] }}; animation-delay: {{ 0.35 + $e['zona'] * 0.4 + $e['retraso'] }}s"
                />
            @endforeach
        </g>
        @if ($paleta['detalle'] !== null)
            <g class="tq-lg-detalle" data-detalle="{{ $paleta['detalle'] }}">
                @include('nucleo::filament.logo-detalle-'.$paleta['detalle'])
            </g>
        @endif
    </svg>
    <span class="tq-logo-nombre"><span>TechQuk</span></span>
</span>
