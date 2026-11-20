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
    #tq-asis .zzz { display: none; position: absolute; top: -14px; right: -4px; font: 600 12px ui-sans-serif; color: #b88a00; }
    #tq-asis.dormido .zzz { display: block; }
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
    <span class="zzz">z z z</span>
    <svg viewBox="0 0 64 72" aria-hidden="true">
        <path d="M10 46 Q2 40 4 32" stroke="#e0a800" stroke-width="4" fill="none" stroke-linecap="round" class="brazo-i"/>
        <path d="M54 46 Q62 40 60 32" stroke="#e0a800" stroke-width="4" fill="none" stroke-linecap="round" class="brazo-d"/>
        <ellipse cx="32" cy="40" rx="24" ry="26" fill="#f5c518"/>
        <ellipse cx="32" cy="34" rx="18" ry="15" fill="#ffe27a" opacity=".55"/>
        <g class="ojos" fill="#3b2f00" font-family="ui-sans-serif" font-weight="700" font-size="15" text-anchor="middle">
            <text class="ojo-i" x="24" y="40">n</text><text class="boca" x="32" y="42" font-size="11">_</text><text class="ojo-d" x="40" y="40">n</text>
        </g>
        <path d="M32 6 l2.4 4.9 5.4.8-3.9 3.8.9 5.4-4.8-2.6-4.8 2.6.9-5.4-3.9-3.8 5.4-.8z" fill="#ffd84a" stroke="#e0a800" stroke-width=".8"/>
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
    const frases = @js(\App\Modules\Nucleo\Services\AsistenteService::frases())
    const csrf = @js(csrf_token()), rutaChat = @js(route('asistente.chat')), rutaTicket = @js(route('asistente.ticket'))
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
        if (ruta && Math.random() > 0.2) {
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
        el.querySelector('.ojo-i').textContent = el.classList.contains('dormido') ? '–' : 'n'
        el.querySelector('.ojo-d').textContent = el.classList.contains('dormido') ? '–' : 'n'
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
        else if (modo() !== 'normal' || el.classList.contains('dormido')) { guardar('modo', null); ultimaActividad = Date.now(); aplicarModo(); decir('¡Aquí estoy!') }
        else abrirChat()
        inicio = null
    })

    // D3: curioso, sigue al mouse con los ojos; D2: si escribe, al lado contrario.
    addEventListener('mousemove', (e) => {
        ultimaActividad = Date.now()
        if (el.classList.contains('dormido') && modo() !== 'descansar') aplicarModo()
        const r = el.getBoundingClientRect(), dx = Math.max(-1.5, Math.min(1.5, (e.clientX - r.left - 32) / 200)), dy = Math.max(-1, Math.min(1, (e.clientY - r.top - 36) / 200))
        el.querySelector('.ojos').setAttribute('transform', `translate(${dx} ${dy})`)
    }, { passive: true })
    addEventListener('keydown', (e) => {
        if (chat.contains(e.target)) return
        ultimaActividad = Date.now(); ultimaEscritura = Date.now(); cerrarGlobo()
        if (! reducido() && e.target.getBoundingClientRect) { const r = e.target.getBoundingClientRect(); if (Math.abs(r.left - el.getBoundingClientRect().left) < 300) ir(r.left > innerWidth / 2 ? 24 : innerWidth - 110, el.getBoundingClientRect().top) }
    })

    const ciclo = () => { aplicarModo(); if (! el.classList.contains('dormido')) meta(); setTimeout(ciclo, 7000 + Math.random() * 9000) }
    setTimeout(ciclo, 1200)
    setInterval(iniciativa, 20000)
    document.addEventListener('livewire:navigated', () => setTimeout(meta, 600))
    window.tqAsistente = { decir, abrirChat, meta, iniciativa }
})()
</script>
@endif
