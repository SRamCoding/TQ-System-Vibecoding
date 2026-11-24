{{--
    Observación 8.7 (MV-086), para todo el sistema:
    - Paneles deslizantes y ventanas con fondo sólido (la superficie elevada del ambiente) y una sombra que los separa;
      el vidrio queda para lo pequeño que flota (muelle, globos, menús).
    - Los paneles que salen de un costado oscurecen la pantalla de atrás con una capa negra pareja (42 %; 58 % en
      oscuro). Las ventanas que salen al medio la desenfocan y la aclaran un poco, sin oscurecer.
    - MV-088: el asistente siempre encima de todo (única excepción): nunca se oscurece, desenfoca ni esconde; con un panel
      del costado abierto se corre al otro lado.
--}}
<style>
    .fi-modal-window {
        background-color: var(--tq-superficie-elevada, #fff) !important;
        -webkit-backdrop-filter: none !important; backdrop-filter: none !important;
        border: 1px solid var(--tq-borde, rgb(0 0 0 / .08)) !important;
        box-shadow: 0 28px 70px -18px rgb(0 0 0 / .45) !important;
        color: var(--tq-texto, inherit);
    }
    .dark .fi-modal-window { background-color: var(--tq-superficie-elevada, var(--gray-900)) !important; }
    .fi-modal-window .fi-modal-header, .fi-modal-window .fi-modal-footer { background-color: transparent !important; }
    .fi-modal.fi-modal-slide-over > .fi-modal-close-overlay { background-color: rgb(0 0 0 / .42) !important; -webkit-backdrop-filter: none; backdrop-filter: none; }
    .dark .fi-modal.fi-modal-slide-over > .fi-modal-close-overlay { background-color: rgb(0 0 0 / .58) !important; }
    .fi-modal:not(.fi-modal-slide-over) > .fi-modal-close-overlay { background-color: rgb(255 255 255 / .16) !important; -webkit-backdrop-filter: blur(7px); backdrop-filter: blur(7px); }
    .dark .fi-modal:not(.fi-modal-slide-over) > .fi-modal-close-overlay { background-color: rgb(255 255 255 / .05) !important; }
    html:has(.fi-modal.fi-modal-open.fi-modal-slide-over) #tq-asis { left: 24px !important; }
    /* MV-088: el asistente siempre encima de todo (la única excepción): ni oscurecido, ni desenfocado, ni escondido;
       queda incluso sobre el velo del cielo. Con un panel del costado solo se corre al otro lado. */
    #tq-asis { z-index: 2147483100 !important; }
    .tq-asis-globo { z-index: 2147483101 !important; }
    #tq-asis-chat { z-index: 2147483102 !important; }
    #tq-asis-menu { z-index: 2147483103 !important; }
</style>
<script>
    // Observación 8.10: el buscador del SEACE no tiene dirección por proceso; al abrirlo se copia la nomenclatura y se avisa.
    window.tqCopiarNomenclatura = (nomenclatura) => {
        try { navigator.clipboard.writeText(nomenclatura) } catch (e) {}
        try { new FilamentNotification().title('Nomenclatura copiada: pégala en el buscador del SEACE').body(nomenclatura).success().send() } catch (e) {}
    }
</script>
<script>
    // Observación 8.13 (MV-093): lo que es de la persona (tema, preferencias de apariencia, orden de cajas, grupos de
    // filtros abiertos) se aplica al instante en todas sus pestañas abiertas de este navegador, sin recargarlas. Se avisa
    // por `BroadcastChannel` (o el evento `storage` como respaldo); cada pestaña lo aplica con el evento que ya existe.
    // Lo que es de la pantalla (vista, filtros puestos, etapa abierta) no se copia.
    (() => {
        if (window.tqEntrePestanas) return
        const canal = 'BroadcastChannel' in window ? new BroadcastChannel('tq-persona') : null
        const recibir = (m) => {
            if (! m || ! m.tipo) return
            const detalle = { ...(m.datos || {}), remoto: true }
            if (m.tipo === 'apariencia') {
                if (detalle.tema) { try { localStorage.setItem('theme', detalle.tema) } catch (e) {} }
                window.dispatchEvent(new CustomEvent(@js(\App\Modules\Nucleo\Filament\Apariencia::EVENTO), { detail: detalle }))
            } else {
                window.dispatchEvent(new CustomEvent('tq-remoto-' + m.tipo, { detail: detalle }))
            }
        }
        canal?.addEventListener('message', (e) => recibir(e.data))
        window.addEventListener('storage', (e) => { if (! canal && e.key === 'tq-persona' && e.newValue) { try { recibir(JSON.parse(e.newValue)) } catch (er) {} } })
        window.tqEntrePestanas = {
            enviar(tipo, datos) {
                const m = { tipo, datos, t: Date.now() }
                if (canal) canal.postMessage(m); else { try { localStorage.setItem('tq-persona', JSON.stringify(m)) } catch (e) {} }
            },
        }
        // Un cambio de apariencia hecho aquí (Mis preferencias) se manda a las demás pestañas; los que llegan, no se reenvían.
        window.addEventListener(@js(\App\Modules\Nucleo\Filament\Apariencia::EVENTO), (e) => { if (! e.detail?.remoto) window.tqEntrePestanas.enviar('apariencia', e.detail || {}) })
        // El selector de tema de Filament: solo cuando de verdad cambia (también avisa al cargar cada página).
        let temaVisto = (() => { try { return localStorage.getItem('theme') } catch (e) { return null } })()
        window.addEventListener('theme-changed', (e) => {
            if (typeof e.detail !== 'string' || e.detail === temaVisto) return
            temaVisto = e.detail
            const clases = @js(\App\Modules\Nucleo\Filament\Apariencia::CLASES).filter((c) => document.documentElement.classList.contains(c))
            window.tqEntrePestanas.enviar('apariencia', { tema: e.detail, clases })
        })
        window.addEventListener(@js(\App\Modules\Nucleo\Filament\Apariencia::EVENTO), (e) => { if (e.detail?.tema) temaVisto = e.detail.tema })
        // Los grupos de filtros abiertos (Mis oportunidades): el servidor avisa al guardarlos.
        window.addEventListener('tq-filtros-abiertos-guardados', (e) => window.tqEntrePestanas.enviar('filtros-abiertos', { abiertos: e.detail?.abiertos ?? [] }))
    })()
</script>
