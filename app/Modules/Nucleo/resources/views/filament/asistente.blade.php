{{--
    MVP rápido (ficha op-asistente, plan 1.14): el asistente de ayuda, la carita «n_n» dorada. Tres capas (D1): el cuerpo y
    la iniciativa corren aquí, sin IA ni costo; la conversación va al servidor (IA si está configurada). Se mueve con
    intención (D2): al entrar, junto a lo que se mira primero; si la persona escribe, al lado contrario; si no pasa
    nada, otra meta cada 7 a 16 s; se puede arrastrar. Estados (D3): tranquilo, curioso, hablando, pensando, contento y
    dormido (20 min sin actividad). Habla poco (D4): no si escribiste hace menos de 90 s, máximo 3 por hora, 10 min
    entre una y otra, «Ahora no» calla 30 min. Clic derecho: Descansar o Callar por 1 h, 2 h, 4 h o hasta mañana (D7).
    Un clic: lo devuelve a lo normal o abre el chat. Con movimiento reducido, quieto en la esquina (D8).
--}}
@php
    $u = filament()->auth()->user();
@endphp
@if ($u instanceof \App\Modules\Nucleo\Models\User)
<style>
    #tq-asis { position: fixed; z-index: 2147482000; width: 64px; height: 72px; left: calc(100vw - 96px); top: calc(100vh - 110px); transition: left 1.6s cubic-bezier(.4,.1,.3,1), top 1.6s cubic-bezier(.4,.1,.3,1); cursor: pointer; touch-action: none; }
    #tq-asis svg { width: 100%; height: 100%; overflow: visible; animation: tq-flota 3.2s ease-in-out infinite; filter: drop-shadow(0 4px 6px rgb(0 0 0 / .18)); }
    #tq-asis.dormido svg { animation: tq-respira 4s ease-in-out infinite; }
    /* MV-064: la «Gota de miel». Cada gesto es una cara; pasa de una a otra con un fundido corto. */
    #tq-asis .cara { opacity: 0; transition: opacity 260ms ease; }
    #tq-asis:not(.dormido):not(.cara-saluda):not(.cara-habla):not(.cara-escucha) .cara-tranquilo,
    #tq-asis.cara-saluda:not(.dormido) .cara-saluda, #tq-asis.cara-habla:not(.dormido) .cara-habla,
    #tq-asis.cara-escucha:not(.dormido) .cara-escucha, #tq-asis.dormido .cara-duerme { opacity: 1; }
    #tq-asis .mano-saluda { opacity: 0; transition: opacity 260ms ease; }
    #tq-asis.cara-saluda:not(.dormido) .mano-saluda { opacity: 1; }
    #tq-asis.cara-saluda:not(.dormido) .brazo-d { opacity: 0; }
    #tq-asis .ojos, #tq-asis .parpado, #tq-asis .saluda, #tq-asis .habla, #tq-asis .estrella, #tq-asis .z { transform-box: fill-box; transform-origin: center; }
    #tq-asis .ojos { transition: transform 300ms ease-out; }
    #tq-asis .parpado { transition: transform 90ms ease-in; }
    #tq-asis.parpadea .parpado { transform: scaleY(.08); }
    #tq-asis .saluda { transform-origin: 0% 100%; animation: tq-asis-saluda 1.6s ease-in-out infinite; }
    @keyframes tq-asis-saluda { 0%, 100% { transform: rotate(0); } 25% { transform: rotate(-12deg); } 50% { transform: rotate(4deg); } 75% { transform: rotate(-10deg); } }
    #tq-asis .habla { animation: tq-asis-habla 1.1s ease-in-out infinite; }
    @keyframes tq-asis-habla { 0%, 100% { transform: scaleY(.35); } 15% { transform: scaleY(1); } 30% { transform: scaleY(.5); } 42% { transform: scaleY(.95); } 58% { transform: scaleY(.3); } 72% { transform: scaleY(.8); } 86% { transform: scaleY(.45); } }
    #tq-asis .estrella { animation: tq-asis-estrella 5s ease-in-out infinite; }
    @keyframes tq-asis-estrella { 0%, 100% { transform: rotate(0); } 50% { transform: rotate(8deg); } }
    #tq-asis .z { animation: tq-asis-z 2.6s ease-out infinite; }
    #tq-asis .z.b { animation-delay: 1.3s; }
    @keyframes tq-asis-z { 0% { opacity: 0; transform: translate(0, 4px); } 40% { opacity: 1; } 100% { opacity: 0; transform: translate(4px, -8px); } }
    /* El ámbar: facetas que aparecen con el trabajo seguido. */
    #tq-asis .facetas { opacity: calc(var(--tq-ambar, 0) * .55); }
    html.tq-sin-animaciones #tq-asis *, html.tq-sin-animaciones #tq-asis .cara { animation: none !important; transition: none !important; }
    @media (prefers-reduced-motion: reduce) { #tq-asis * { animation: none !important; transition: none !important; } }
    @keyframes tq-flota { 0%, 100% { transform: translateY(0) rotate(-2deg); } 50% { transform: translateY(-6px) rotate(2deg); } }
    @keyframes tq-respira { 0%, 100% { transform: scale(1); } 50% { transform: scale(.96); } }
    html.tq-sin-animaciones #tq-asis, html.tq-sin-animaciones #tq-asis svg { transition: none; animation: none; }
    @media (prefers-reduced-motion: reduce) { #tq-asis, #tq-asis svg { transition: none; animation: none; } }
    .tq-asis-globo { position: fixed; z-index: 2147482001; max-width: 280px; padding: 10px 12px; border-radius: 14px; font-size: 13px; line-height: 1.35; background: #fffbea; color: #3b2f00; box-shadow: 0 6px 20px rgb(0 0 0 / .18); }
    html.dark .tq-asis-globo { background: #3a3215; color: #fff4c2; }
    .tq-asis-globo button { margin: 6px 6px 0 0; padding: 2px 10px; border-radius: 999px; font-size: 12px; background: #f5c518; color: #3b2f00; }
    #tq-asis-chat { position: fixed; z-index: 2147482002; right: 16px; bottom: 16px; width: min(360px, calc(100vw - 32px)); height: 440px; display: none; flex-direction: column; border-radius: 16px; overflow: hidden; background: white; box-shadow: 0 12px 40px rgb(0 0 0 / .25); }
    html.dark #tq-asis-chat { background: rgb(24 24 27); color: white; }
    #tq-asis-chat .msgs { flex: 1; overflow-y: auto; padding: 12px; font-size: 13px; display: flex; flex-direction: column; gap: 8px; }
    #tq-asis-chat .yo { align-self: flex-end; background: #f5c518; color: #3b2f00; padding: 6px 10px; border-radius: 12px 12px 2px 12px; max-width: 85%; }
    #tq-asis-chat .el { align-self: flex-start; background: rgb(244 244 245); padding: 6px 10px; border-radius: 12px 12px 12px 2px; max-width: 85%; }
    html.dark #tq-asis-chat .el { background: rgb(39 39 42); }
    #tq-asis-menu { position: fixed; z-index: 2147482003; display: none; min-width: 190px; padding: 6px; border-radius: 10px; background: white; box-shadow: 0 8px 24px rgb(0 0 0 / .2); font-size: 13px; }
    html.dark #tq-asis-menu { background: rgb(39 39 42); color: white; }
    #tq-asis-menu button { display: block; width: 100%; text-align: left; padding: 5px 8px; border-radius: 6px; }
    #tq-asis-menu button:hover { background: rgb(0 0 0 / .06); }
</style>
<div id="tq-asis" title="Asistente · clic: conversar · clic derecho: descansar o callar" role="button" aria-label="Asistente de ayuda">
    <svg viewBox="0 0 120 130" aria-hidden="true" style="--tq-a-rasgo: color-mix(in oklch, #2E1F00 calc((1 - var(--tq-ambar, 0)) * 100%), #3D1600)">
        {{-- MV-064: la «Gota de miel» (lienzo A): esfera dorada con luz arriba a la izquierda, brillo, sombra en el piso, ojos
             con punto de luz, mejillas y la estrellita. Sus colores van de miel a ámbar (lienzo C) con `--tq-ambar` (0 a 1). --}}
        <defs>
            <radialGradient id="tq-asis-cuerpo" cx="38%" cy="30%" r="75%"><stop offset="0" style="stop-color: color-mix(in oklch, #FFF1B0 calc((1 - var(--tq-ambar, 0)) * 100%), #FFE7A0)"/><stop offset=".35" style="stop-color: color-mix(in oklch, #FFD23F calc((1 - var(--tq-ambar, 0)) * 100%), #FFAE1A)"/><stop offset=".8" style="stop-color: color-mix(in oklch, #E8A800 calc((1 - var(--tq-ambar, 0)) * 100%), #E06A00)"/><stop offset="1" style="stop-color: color-mix(in oklch, #B97F00 calc((1 - var(--tq-ambar, 0)) * 100%), #8E3A00)"/></radialGradient>
            <radialGradient id="tq-asis-brazo" cx="50%" cy="30%" r="70%"><stop offset="0" style="stop-color: color-mix(in oklch, #FFD84A calc((1 - var(--tq-ambar, 0)) * 100%), #FFC93C)"/><stop offset="1" style="stop-color: color-mix(in oklch, #C98E00 calc((1 - var(--tq-ambar, 0)) * 100%), #B86A00)"/></radialGradient>
            <radialGradient id="tq-asis-sombra" cx="50%" cy="50%" r="50%"><stop offset="0" stop-color="#3B2A00" stop-opacity=".35"/><stop offset="1" stop-color="#3B2A00" stop-opacity="0"/></radialGradient>
            <radialGradient id="tq-asis-estrella" cx="50%" cy="40%" r="60%"><stop offset="0" style="stop-color: color-mix(in oklch, #FFE680 calc((1 - var(--tq-ambar, 0)) * 100%), #FFFBE6)"/><stop offset=".6" style="stop-color: color-mix(in oklch, #F7C21A calc((1 - var(--tq-ambar, 0)) * 100%), #FFD54F)"/><stop offset="1" style="stop-color: color-mix(in oklch, #D49500 calc((1 - var(--tq-ambar, 0)) * 100%), #E89B00)"/></radialGradient>
        </defs>
        <ellipse cx="60" cy="124" rx="34" ry="5" fill="url(#tq-asis-sombra)"/>
        <path class="brazo-i" d="M20 86 Q8 78 11 66" stroke="url(#tq-asis-brazo)" stroke-width="7" fill="none" stroke-linecap="round"/>
        <path class="brazo-d" d="M100 86 Q112 78 109 66" stroke="url(#tq-asis-brazo)" stroke-width="7" fill="none" stroke-linecap="round"/>
        <path class="mano-saluda saluda" d="M100 86 Q114 70 110 54" stroke="url(#tq-asis-brazo)" stroke-width="7" fill="none" stroke-linecap="round"/>
        <ellipse cx="60" cy="76" rx="44" ry="46" fill="url(#tq-asis-cuerpo)"/>
        <g class="facetas" stroke="#FFF1B8" stroke-width="1.2" fill="none"><path d="M30 60 L48 44 L72 44 L90 60 M48 44 L44 70 M72 44 L76 70 M30 60 L44 70 L76 70 L90 60"/></g>
        <ellipse cx="44" cy="50" rx="15" ry="9" fill="#FFFFFF" opacity=".55" transform="rotate(-25 44 50)"/>
        <ellipse cx="38" cy="46" rx="4" ry="2.5" fill="#FFFFFF" opacity=".9" transform="rotate(-25 38 46)"/>
        <path d="M24 96 Q60 130 96 96" stroke="#B97F00" stroke-width="2" fill="none" opacity=".25"/>
        <g class="estrella"><path d="M60 6 l3.6 7.4 8.1 1.2-5.9 5.7 1.4 8.1-7.2-3.9-7.2 3.9 1.4-8.1-5.9-5.7 8.1-1.2z" fill="url(#tq-asis-estrella)" stroke="#C98E00" stroke-width=".8" stroke-linejoin="round"/></g>
        <g class="cara cara-tranquilo"><g class="ojos"><path d="M38 73 Q45 61 52 73 M68 73 Q75 61 82 73" style="stroke: var(--tq-a-rasgo)" stroke-width="4.2" fill="none" stroke-linecap="round"/></g><ellipse cx="34" cy="82" rx="6" ry="3.6" fill="#FF8A7A" opacity=".35"/><ellipse cx="86" cy="82" rx="6" ry="3.6" fill="#FF8A7A" opacity=".35"/><path d="M54 84 Q60 89 66 84" style="stroke: var(--tq-a-rasgo)" stroke-width="2.6" fill="none" stroke-linecap="round"/></g>
        <g class="cara cara-saluda"><g class="ojos"><path d="M38 73 Q45 61 52 73 M68 73 Q75 61 82 73" style="stroke: var(--tq-a-rasgo)" stroke-width="4.2" fill="none" stroke-linecap="round"/></g><ellipse cx="34" cy="82" rx="6" ry="3.6" fill="#FF8A7A" opacity=".35"/><ellipse cx="86" cy="82" rx="6" ry="3.6" fill="#FF8A7A" opacity=".35"/><path d="M50 82 Q60 97 70 82 Q60 86 50 82Z" style="fill: var(--tq-a-rasgo)"/><path d="M55 89 Q60 93 65 89 Q60 90.5 55 89Z" fill="#E8604F"/></g>
        <g class="cara cara-habla"><path d="M38 61 Q45 56 52 59 M68 59 Q75 56 82 61" style="stroke: var(--tq-a-rasgo)" stroke-width="2.4" fill="none" stroke-linecap="round" opacity=".8"/><g class="ojos"><g class="parpado"><ellipse cx="45" cy="70" rx="5.4" ry="7.2" style="fill: var(--tq-a-rasgo)"/><ellipse cx="43.2" cy="66.8" rx="2" ry="2.4" fill="#FFFFFF"/><circle cx="47" cy="73.5" r=".9" fill="#FFFFFF" opacity=".7"/><ellipse cx="75" cy="70" rx="5.4" ry="7.2" style="fill: var(--tq-a-rasgo)"/><ellipse cx="73.2" cy="66.8" rx="2" ry="2.4" fill="#FFFFFF"/><circle cx="77" cy="73.5" r=".9" fill="#FFFFFF" opacity=".7"/></g></g><ellipse cx="34" cy="82" rx="6" ry="3.6" fill="#FF8A7A" opacity=".35"/><ellipse cx="86" cy="82" rx="6" ry="3.6" fill="#FF8A7A" opacity=".35"/><ellipse class="habla" cx="60" cy="86" rx="5.5" ry="4" style="fill: var(--tq-a-rasgo)"/><ellipse class="habla" cx="60" cy="88" rx="3" ry="1.6" fill="#E8604F"/></g>
        <g class="cara cara-escucha"><path d="M38 57 Q45 52 52 55 M68 55 Q75 52 82 57" style="stroke: var(--tq-a-rasgo)" stroke-width="2.4" fill="none" stroke-linecap="round" opacity=".8"/><g class="ojos"><g class="parpado"><ellipse cx="45" cy="70" rx="5.4" ry="7.2" style="fill: var(--tq-a-rasgo)"/><ellipse cx="43.2" cy="66.8" rx="2" ry="2.4" fill="#FFFFFF"/><circle cx="47" cy="73.5" r=".9" fill="#FFFFFF" opacity=".7"/><ellipse cx="75" cy="70" rx="5.4" ry="7.2" style="fill: var(--tq-a-rasgo)"/><ellipse cx="73.2" cy="66.8" rx="2" ry="2.4" fill="#FFFFFF"/><circle cx="77" cy="73.5" r=".9" fill="#FFFFFF" opacity=".7"/></g></g><ellipse cx="34" cy="82" rx="6" ry="3.6" fill="#FF8A7A" opacity=".35"/><ellipse cx="86" cy="82" rx="6" ry="3.6" fill="#FF8A7A" opacity=".35"/><ellipse cx="60" cy="86" rx="3.2" ry="3.8" style="fill: var(--tq-a-rasgo)"/></g>
        <g class="cara cara-duerme"><path d="M38 71 Q45 76 52 71 M68 71 Q75 76 82 71" style="stroke: var(--tq-a-rasgo)" stroke-width="3.4" fill="none" stroke-linecap="round"/><ellipse cx="34" cy="82" rx="6" ry="3.6" fill="#FF8A7A" opacity=".35"/><ellipse cx="86" cy="82" rx="6" ry="3.6" fill="#FF8A7A" opacity=".35"/><path d="M56 85 Q60 87 64 85" style="stroke: var(--tq-a-rasgo)" stroke-width="2.2" fill="none" stroke-linecap="round"/><g fill="#8A6A1F" font-family="Inter, sans-serif" font-weight="700"><text class="z" x="96" y="40" font-size="12">z</text><text class="z b" x="104" y="28" font-size="9">z</text></g></g>
    </svg>
</div>
<div id="tq-asis-menu" role="menu"></div>
<div id="tq-asis-chat" role="dialog" aria-label="Asistente de ayuda">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;background:#f5c518;color:#3b2f00;font-weight:600">
        <span>n_n · Ayuda del sistema</span><button type="button" data-cerrar aria-label="Cerrar">✕</button>
    </div>
    <div class="msgs"><div class="el">Hola. Pregúntame cómo hacer algo en el sistema, o cuéntame si algo falla y abro un ticket.</div></div>
    <form style="display:flex;gap:6px;padding:10px;border-top:1px solid rgb(0 0 0 / .08)">
        <input name="q" autocomplete="off" placeholder="¿Cómo registro una factura?" style="flex:1;border-radius:10px;padding:6px 10px;border:1px solid rgb(0 0 0 / .15);background:transparent;font-size:13px">
        <button style="padding:6px 12px;border-radius:10px;background:#f5c518;color:#3b2f00;font-size:13px">Enviar</button>
    </form>
</div>
<script>
(() => {
    if (window.tqAsistente) return
    const el = document.getElementById('tq-asis'), menu = document.getElementById('tq-asis-menu'), chat = document.getElementById('tq-asis-chat')
    const frases = @js(\App\Modules\Nucleo\Services\AsistenteService::frases());
    const csrf = @js(csrf_token()), rutaChat = @js(route('asistente.chat')), rutaTicket = @js(route('asistente.ticket'));
    const leer = (k, d) => { try { return JSON.parse(localStorage.getItem('tq-asis-' + k)) ?? d } catch (e) { return d } }
    const guardar = (k, v) => { try { localStorage.setItem('tq-asis-' + k, JSON.stringify(v)) } catch (e) {} }
    const reducido = () => matchMedia('(prefers-reduced-motion: reduce)').matches || document.documentElement.classList.contains('tq-sin-animaciones')
    let ultimaActividad = Date.now(), ultimaEscritura = 0, globo = null, arrastrando = false, conversacion = []

    const modo = () => { const m = leer('modo', null); return m && m.hasta > Date.now() ? m.tipo : 'normal' }
    const ir = (x, y) => {
        const w = innerWidth - 80, h = innerHeight - 90
        el.style.left = Math.max(8, Math.min(w, x + (Math.random() * 30 - 15))) + 'px'
        el.style.top = Math.max(60, Math.min(h, y + (Math.random() * 20 - 10))) + 'px'
    }
    const esquina = () => ir(innerWidth - 96, innerHeight - 110)
    const junto = (n) => { const r = n.getBoundingClientRect(); ir(r.right + 12 < innerWidth - 90 ? r.right + 12 : r.left - 80, r.top - 10) }
    const meta = () => {
        if (arrastrando || reducido() || modo() === 'descansar' || chat.style.display === 'flex') return esquina()
        const candidatos = [...document.querySelectorAll('main h1, .fi-header-heading, .fi-ta-search-field input, main .fi-section, main .fi-wi')].filter((n) => n.getBoundingClientRect().height > 0)
        if (candidatos.length && Math.random() < 0.6) return junto(candidatos[Math.floor(Math.random() * candidatos.length)])
        ir(Math.random() < 0.5 ? 24 : innerWidth - 110, 120 + Math.random() * (innerHeight - 260))
    }

    const cerrarGlobo = () => { globo?.remove(); globo = null }

    // MV-064: los gestos (tranquilo, saluda, habla, escucha, duerme) y el parpadeo a ratos, nunca a ritmo fijo.
    let relojCara = null
    const cara = (nombre, ms = 0) => {
        clearTimeout(relojCara)
        el.classList.remove('cara-saluda', 'cara-habla', 'cara-escucha')
        if (nombre !== 'tranquilo') el.classList.add('cara-' + nombre)
        if (ms) relojCara = setTimeout(() => cara(document.activeElement && chat.contains(document.activeElement) ? 'escucha' : 'tranquilo'), ms)
    }
    const parpadear = () => {
        if (! reducido()) { el.classList.add('parpadea'); setTimeout(() => el.classList.remove('parpadea'), 130) }
        // A veces un doble parpadeo; el próximo, entre 2,5 y 7 s.
        if (Math.random() < 0.18) setTimeout(() => { el.classList.add('parpadea'); setTimeout(() => el.classList.remove('parpadea'), 120) }, 260)
        setTimeout(parpadear, 2500 + Math.random() * 4500)
    }
    setTimeout(parpadear, 1800)
    chat.addEventListener('focusin', () => cara('escucha'))
    chat.addEventListener('focusout', () => cara('tranquilo'))

    // MV-064: con 2 h de actividad sin pausa, la miel se va haciendo ámbar en 30 min; 15 min sin actividad (o con la
    // pestaña oculta) la devuelven a miel. Se guarda en el navegador para que siga igual al navegar o recargar.
    const DOS_HORAS = 2 * 3600000, MEDIA_HORA = 30 * 60000, PAUSA = 15 * 60000
    const trabajo = () => { const t = leer('trabajo', null); return t && Date.now() - t.ultima < PAUSA ? t : { inicio: Date.now(), ultima: Date.now() } }
    const anotarActividad = () => { const t = trabajo(); t.ultima = Date.now(); guardar('trabajo', t) }
    const ambar = () => { const t = trabajo(); return Math.max(0, Math.min(1, (Date.now() - t.inicio - DOS_HORAS) / MEDIA_HORA)) }
    const pintarAmbar = () => el.style.setProperty('--tq-ambar', ambar().toFixed(3))
    let ocultaDesde = null
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) { ocultaDesde = Date.now(); return }
        if (ocultaDesde && Date.now() - ocultaDesde >= PAUSA) guardar('trabajo', null)
        ocultaDesde = null
        pintarAmbar()
    })
    ;['pointermove', 'keydown', 'pointerdown'].forEach((t) => addEventListener(t, () => { if (! document.hidden) anotarActividad() }, { passive: true }))
    setInterval(pintarAmbar, 30000)
    pintarAmbar()
    const decir = (texto, botones = []) => {
        cerrarGlobo()
        globo = document.createElement('div')
        globo.className = 'tq-asis-globo'
        globo.textContent = texto
        botones.concat(['Ahora no']).forEach((b) => {
            const btn = document.createElement('button')
            btn.textContent = b
            btn.onclick = () => {
                if (b === 'Ahora no') guardar('silencio', Date.now() + 30 * 60000)
                cerrarGlobo()
                if (frases.respuestas[b]) setTimeout(() => decir(frases.respuestas[b]), 300)
            }
            globo.appendChild(document.createElement('br'))
            globo.appendChild(btn)
        })
        const r = el.getBoundingClientRect()
        globo.style.left = Math.max(8, Math.min(innerWidth - 300, r.left - 200)) + 'px'
        globo.style.top = Math.max(60, r.top - 90) + 'px'
        document.body.appendChild(globo)
        cara('habla', Math.min(6000, 1200 + texto.length * 55))
        setTimeout(() => globo?.textContent.startsWith(texto) && cerrarGlobo(), 14000)
    }

    // D4: cuándo habla.
    const puedeHablar = () => {
        if (reducido() || modo() !== 'normal' || chat.style.display === 'flex' || globo || el.classList.contains('dormido')) return false
        if (Date.now() - ultimaEscritura < 90000 || leer('silencio', 0) > Date.now()) return false
        const hora = leer('dichas', []).filter((t) => t > Date.now() - 3600000)
        return hora.length < 3 && (hora.at(-1) ?? 0) < Date.now() - 600000
    }
    const iniciativa = () => {
        if (! puedeHablar()) return
        const ruta = Object.keys(frases.pantallas).find((p) => location.pathname.startsWith(p))
        const vistas = leer('vistas-' + location.pathname, [])
        let texto, botones = []
        if (ambar() >= 1 && Math.random() < 0.5) {
            // MV-064: con el ámbar completo, a veces invita a una pausa (respetando cuándo habla).
            texto = 'Llevas más de dos horas seguidas. ¿Una pausa de cinco minutos?'
            botones = ['Ahora voy']
        } else if (ruta && Math.random() > 0.2) {
            texto = frases.pantallas[ruta].find((f) => ! vistas.includes(f))
        } else {
            [texto, botones] = frases.vida[Math.floor(Math.random() * frases.vida.length)]
        }
        if (! texto) return
        guardar('vistas-' + location.pathname, vistas.concat(texto))
        guardar('dichas', leer('dichas', []).concat(Date.now()))
        decir(texto, botones)
    }

    const abrirChat = () => { chat.style.display = 'flex'; esquina(); chat.querySelector('input').focus() }
    chat.querySelector('[data-cerrar]').onclick = () => { chat.style.display = 'none' }
    const agregar = (clase, texto) => { const d = document.createElement('div'); d.className = clase; d.textContent = texto; chat.querySelector('.msgs').appendChild(d); d.scrollIntoView(); return d }
    chat.querySelector('form').onsubmit = async (e) => {
        e.preventDefault()
        const input = chat.querySelector('input'), q = input.value.trim()
        if (! q) return
        input.value = ''
        agregar('yo', q)
        conversacion.push('Persona: ' + q)
        const pensando = agregar('el', '…')
        try {
            const r = await fetch(rutaChat, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' }, body: JSON.stringify({ pregunta: q, pantalla: location.pathname }) }).then((x) => x.json())
            pensando.textContent = r.texto
            conversacion.push('Asistente: ' + r.texto)
            if (r.ticket) {
                const b = document.createElement('button')
                b.textContent = 'Abrir ticket'
                b.style.cssText = 'margin-top:6px;padding:2px 10px;border-radius:999px;background:#f5c518;color:#3b2f00;font-size:12px;display:block'
                b.onclick = async () => {
                    b.disabled = true
                    const t = await fetch(rutaTicket, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' }, body: JSON.stringify({ resumen: r.ticket.resumen, pantalla: location.pathname, conversacion: conversacion.join('\n') }) }).then((x) => x.json())
                    agregar('el', 'Listo: abrí el ticket ' + t.codigo + '. Soporte lo revisa y te avisa.')
                }
                pensando.appendChild(b)
            }
        } catch (err) { pensando.textContent = 'No pude responder ahora. Intenta en un momento.' }
    }

    // D7: clic derecho, descansar o callar.
    const opciones = [['1 h', 1], ['2 h', 2], ['4 h', 4], ['hasta mañana (8:00)', 0]]
    el.addEventListener('contextmenu', (e) => {
        e.preventDefault()
        menu.innerHTML = ''
        ;['descansar', 'callar'].forEach((tipo) => opciones.forEach(([et, h]) => {
            const b = document.createElement('button')
            b.textContent = (tipo === 'descansar' ? 'Descansar ' : 'Callar ') + et
            b.onclick = () => {
                const manana = new Date(); manana.setDate(manana.getDate() + 1); manana.setHours(8, 0, 0, 0)
                guardar('modo', { tipo, hasta: h ? Date.now() + h * 3600000 : manana.getTime() })
                menu.style.display = 'none'; cerrarGlobo(); aplicarModo()
            }
            menu.appendChild(b)
        }))
        menu.style.left = Math.min(innerWidth - 200, e.clientX) + 'px'
        menu.style.top = Math.max(8, e.clientY - 220) + 'px'
        menu.style.display = 'block'
    })
    document.addEventListener('click', (e) => { if (! menu.contains(e.target)) menu.style.display = 'none' })

    const aplicarModo = () => {
        el.classList.toggle('dormido', modo() === 'descansar' || Date.now() - ultimaActividad > 20 * 60000)
        if (modo() === 'descansar') esquina()
    }

    // Un clic: lo devuelve a lo normal; si ya estaba normal, abre el chat. Se puede arrastrar.
    let inicio = null
    el.addEventListener('pointerdown', (e) => { if (e.button === 0) inicio = { x: e.clientX, y: e.clientY, t: Date.now() } })
    addEventListener('pointermove', (e) => {
        if (! inicio) return
        if (Math.abs(e.clientX - inicio.x) + Math.abs(e.clientY - inicio.y) > 6) {
            arrastrando = true; el.style.transition = 'none'; el.style.left = (e.clientX - 32) + 'px'; el.style.top = (e.clientY - 36) + 'px'
        }
    })
    addEventListener('pointerup', () => {
        if (! inicio) return
        if (arrastrando) { el.style.transition = ''; setTimeout(() => { arrastrando = false }, 60000) }
        else if (modo() !== 'normal' || el.classList.contains('dormido')) { guardar('modo', null); ultimaActividad = Date.now(); aplicarModo(); decir('¡Aquí estoy!'); cara('saluda', 2200) }
        else abrirChat()
        inicio = null
    })

    // D3: curioso, sigue al mouse con los ojos; D2: si escribe, al lado contrario.
    addEventListener('mousemove', (e) => {
        ultimaActividad = Date.now()
        if (el.classList.contains('dormido') && modo() !== 'descansar') aplicarModo()
        const r = el.getBoundingClientRect(), dx = Math.max(-1.5, Math.min(1.5, (e.clientX - r.left - 32) / 200)), dy = Math.max(-1, Math.min(1, (e.clientY - r.top - 36) / 200))
        // MV-064: los ojos siguen el puntero (en la escala de la carita, unos px).
        el.querySelectorAll('.ojos').forEach((o) => { o.style.transform = `translate(${dx * 2.2}px, ${dy * 1.6}px)` })
    }, { passive: true })
    addEventListener('keydown', (e) => {
        if (chat.contains(e.target)) return
        ultimaActividad = Date.now(); ultimaEscritura = Date.now(); cerrarGlobo()
        if (! reducido() && e.target.getBoundingClientRect) { const r = e.target.getBoundingClientRect(); if (Math.abs(r.left - el.getBoundingClientRect().left) < 300) ir(r.left > innerWidth / 2 ? 24 : innerWidth - 110, el.getBoundingClientRect().top) }
    })

    // MV-063: una frase ahora mismo, sin las reglas de cuándo habla, para probarlo: `tqAsistente.probarFrase()` en la consola.
    const probarFrase = () => {
        const ruta = Object.keys(frases.pantallas).find((p) => location.pathname.startsWith(p))
        if (ruta) return decir(frases.pantallas[ruta][Math.floor(Math.random() * frases.pantallas[ruta].length)])
        const [texto, botones] = frases.vida[Math.floor(Math.random() * frases.vida.length)]
        decir(texto, botones)
    }

    // Al aparecer, saluda un momento.
    setTimeout(() => cara('saluda', 2200), 900)

    const ciclo = () => { aplicarModo(); if (! el.classList.contains('dormido')) meta(); setTimeout(ciclo, 7000 + Math.random() * 9000) }
    setTimeout(ciclo, 1200)
    setInterval(iniciativa, 20000)
    document.addEventListener('livewire:navigated', () => setTimeout(meta, 600))
    window.tqAsistente = { decir, abrirChat, meta, iniciativa, probarFrase, cara, ambar }
})()
</script>
@endif
