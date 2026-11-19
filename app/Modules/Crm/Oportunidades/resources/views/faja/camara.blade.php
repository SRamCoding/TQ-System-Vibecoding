{{--
    La cámara y la navegación de la faja de vidrio (plan 2.21b, paso 3), como el prototipo: el mundo se mueve con
    `translateZ(-dist) rotateX(pitch) rotateZ(yaw) translate3d(-tx, -ty, 0)`. Sin rotación libre ni flechas arriba y
    abajo. Con «reducir animaciones» (D12), sin vuelos ni inclinación: los cambios son instantáneos.

    Ganchos para los pasos que siguen:
    - `tq-faja-abrir` ({ id, url }, cancelable): tocar una caja o Enter. El despiece (paso 6, `faja/despiece`) lo
      cancela y abre la caja en capas; si nadie lo cancelara, se abriría la ficha (VerOportunidad).
    - `tq-faja-abrir-lugar` ({ lugar }, cancelable): la pila «+N más». La faja misma lo cancela y abre la ventana con
      la tabla de ese lugar (paso 5, `verLugar`); si nadie lo cancelara, la Tabla.
    - `tq-faja-abrir-fila` ({ id, url }, de `TablaDeLaFaja`): tocar una fila de una tabla. La faja lo pasa a
      `tq-faja-abrir`; el despiece lo cancela y se cierra la ventana (sin despiece, se abriría la ficha).
    - `tq-faja-mover` ({ id, desde, hacia, version }, del despiece; D14): pasar, ganar o perder sin arrastrar. La faja
      hace lo mismo que al soltar la caja en ese destino (`moverSinArrastre`); si la caja no está dibujada (en una pila),
      lo mismo sin verla moverse.
    - `tq-faja-quieta` ({ zona }): la cámara se detuvo; el hombrecito (paso 7) decide si va a ordenar.
    - `tq-faja-empujar` ({ id, desde, hasta, mover(x, y), esperas }, cancelable): la caja va al escáner. El hombrecito
      (paso 7) lo cancela, empuja la caja con `mover` y deja en `esperas` la promesa de su animación; mientras nadie lo
      cancele, la caja se desliza sola.
    - Paso 7, para el hombrecito (`faja/hombrecito`): `tq-faja-tocar` ({ id }, tocar una caja: la desordena),
      `tq-faja-desordenar` ({ id, dx, dy, giro }, cancelable: soltar en la misma zona; si nadie lo cancela, la caja
      vuelve a su lugar), `tq-faja-devuelta` ({ id }, la caja volvió a su lugar) y `tq-faja-redibujada` (tras
      recargar). El desorden de una caja lo deja el hombrecito en `data-dx`, `data-dy` y `data-giro` de su
      `data-desplazo`; al tomarla, la faja parte de ahí.

    Paso 4, arrastre y acciones (D3–D6, D12): la caja se levanta, sigue al cursor proyectado al piso (`alPiso`) y la
    cámara avanza sola cerca de los bordes. La versión se toma al agarrarla (D4). Al soltar: en su zona vuelve a su
    lugar; saltar o retroceder se explica en una línea sin llamar a nada; la zona siguiente, el final (desde
    Negociación) o la canaleta llaman a `abrirMovimiento`, que abre el diálogo de la acción. Lo llenado vuelve con
    `tq-oportunidad-dialogo-listo`; la caja va al escáner y ahí `moverOportunidad` ejecuta la acción real: rojo y vuelve con el
    mensaje, o verde y cruza. Con «reducir animaciones», todo es instantáneo.

    Paso 5 (pedido B1 y B5; D12): la cortina de PVC de la boca de la tubería se mece sola (resorte amortiguado con un
    poco de ruido, como el prototipo) y se abre cuando pasa una caja: ganar entra por ella. Las salas son otros niveles
    del mundo: `entrarGanadas` vuela hasta la boca y entra por la tubería; `entrarPerdidas` baja hacia la canaleta;
    `volverAFaja` regresa. Con «reducir animaciones», sin vuelos ni física: todo es instantáneo y la cortina, quieta.

    Paso 8 (pedido B7): lo que se repite se detiene cuando la faja no se ve (`IntersectionObserver`) o la pestaña está
    oculta (`visibilitychange`): la cortina deja de pedir cuadros, el visor lleva `en-pausa` (las animaciones de CSS se
    congelan) y avisa `tq-faja-pausa` ({ pausada }) al hombrecito; al volver, sigue. La cortina tampoco corre en una sala
    ni con «reducir animaciones». Teclado: con el foco en algo del mundo que quedó fuera de la vista (Tab), la cámara
    va hasta ahí; el visor nunca se desplaza solo (el navegador intenta mostrar lo enfocado moviendo el scroll). Si al
    entrar o salir de una sala se oculta lo que tenía el foco, el foco vuelve al visor.
--}}
<script>
    window.tqFaja = (config) => ({
        PERSPECTIVA: 1200,
        // Bajo esta escala la vista es «lejana»: se ven las viñetas y no el grabado.
        ESCALA_LEJOS: 0.5,
        UMBRAL_ARRASTRE_PX: 6,
        cam: { tx: 1300, ty: 230, yaw: 0, pitch: 50, dist: 2200 },
        inclinacion: { x: 0, y: 0 },
        lejos: true,
        zonaAqui: null,
        mensaje: null,
        // { id, x0, y0, x, y, movido, objetivo, caja }. `caja` es la que se toma, con su versión (D4).
        arrastre: null,
        // La caja que está en un diálogo o en camino al escáner: mientras tanto no se toma otra.
        ocupada: null,
        // El destino al que apunta la caja levantada: 'perdida', 'ganada' o null (para resaltar la canaleta o el final).
        apunta: null,
        apuntaEscaner: null,
        bordeVuelo: null,
        vuelo: null,
        finVuelo: null,
        // Paso 5: la sala en la que se está ('ganadas', 'perdidas' o null), el fundido al cambiar y la cortina.
        sala: null,
        viajando: false,
        oscuro: false,
        cortina: { tiras: [], a: [], v: [], quieta: false },
        fisica: null,
        movimiento: null,
        reloj: null,
        relojMensaje: null,
        ruedaAcumulada: 0,
        ruedaBloqueadaHasta: 0,
        transformacion: '',
        quitar: [],
        // Paso 8: si la faja está a la vista (en la ventana y con la pestaña visible). Fuera de la vista, en pausa.
        enVista: true,
        pausada: false,

        init() {
            this.movimiento = window.matchMedia('(prefers-reduced-motion: reduce)');
            this.cam = this.vistaGeneral();
            this.aplicarCamara();
            const teclado = (evento) => this.teclado(evento);
            const medida = () => this.aplicarCamara();
            const rueda = (evento) => this.rueda(evento);
            document.addEventListener('keydown', teclado);
            window.addEventListener('resize', medida);
            this.$root.addEventListener('wheel', rueda, { passive: false });
            this.quitar = [
                () => document.removeEventListener('keydown', teclado),
                () => window.removeEventListener('resize', medida),
                () => this.$root.removeEventListener('wheel', rueda),
            ];
            // Paso 8: pausa fuera de la vista o con la pestaña oculta; la cortina sigue la preferencia si cambia.
            const pestana = () => this.revisarPausa();
            const preferencia = () => this.moverCortinaSiToca();
            document.addEventListener('visibilitychange', pestana);
            this.movimiento.addEventListener?.('change', preferencia);
            this.quitar.push(() => document.removeEventListener('visibilitychange', pestana), () => this.movimiento.removeEventListener?.('change', preferencia));
            if ('IntersectionObserver' in window) {
                const observador = new IntersectionObserver((entradas) => {
                    this.enVista = entradas[entradas.length - 1].isIntersecting;
                    this.revisarPausa();
                });
                observador.observe(this.$root);
                this.quitar.push(() => observador.disconnect());
            }
            this.iniciarCortina();
        },

        destroy() {
            this.quitar.forEach((quitar) => quitar());
            this.pararVuelo();
            this.pararCortina();
            clearTimeout(this.reloj);
            clearTimeout(this.relojMensaje);
            cancelAnimationFrame(this.bordeVuelo);
        },

        sinMovimiento() {
            return (this.movimiento ?? window.matchMedia('(prefers-reduced-motion: reduce)')).matches || document.documentElement.classList.contains('tq-sin-animaciones');
        },

        // Paso 8: en pausa, nada se repite; al volver, la cortina sigue y el hombrecito vuelve a mirar.
        revisarPausa() {
            const pausada = ! this.enVista || document.hidden;
            if (pausada === this.pausada) return;
            this.pausada = pausada;
            this.moverCortinaSiToca();
            this.$root.dispatchEvent(new CustomEvent('tq-faja-pausa', { detail: { pausada }, bubbles: true }));
        },

        escala(dist = this.cam.dist) { return this.PERSPECTIVA / (this.PERSPECTIVA + dist) },
        distanciaPara(escala) { return this.PERSPECTIVA / escala - this.PERSPECTIVA },
        limites() {
            if (this.sala === null) return [[60, config.fin + 500], [-60, 560]];
            const o = config.salas[this.sala];

            return [[o.x + 200, o.x + 900], [o.y + 100, o.y + 620]];
        },

        vistaGeneral() {
            const ancho = this.$root.getBoundingClientRect().width || 1200;

            return { tx: 1300, ty: 230, yaw: 0, pitch: 50, dist: this.distanciaPara(Math.min(0.42, ancho * 0.92 / 3000)) };
        },

        camaraDeZona(indice) {
            return { tx: config.centros[indice], ty: 230, yaw: 0, pitch: 56, dist: this.distanciaPara(0.95) };
        },

        camaraDeSala() {
            const o = config.salas[this.sala];

            return { tx: o.x + 500, ty: o.y + 320, yaw: 0, pitch: 52, dist: this.distanciaPara(0.72) };
        },

        aplicarCamara() {
            const [limX, limY] = this.limites();
            const c = this.cam;
            c.tx = Math.max(limX[0], Math.min(limX[1], c.tx));
            c.ty = Math.max(limY[0], Math.min(limY[1], c.ty));
            c.dist = Math.max(this.distanciaPara(2.2), Math.min(this.distanciaPara(0.18), c.dist));
            const quieto = this.sinMovimiento();
            const yaw = c.yaw + (quieto ? 0 : this.inclinacion.x * 2.5);
            const pitch = c.pitch + (quieto ? 0 : this.inclinacion.y * -3);
            const escala = this.escala();
            const mundo = this.$refs.mundo;
            this.transformacion = `translateZ(${-c.dist}px) rotateX(${pitch}deg) rotateZ(${yaw}deg) translate3d(${-c.tx}px, ${-c.ty}px, 0)`;
            mundo.style.transform = this.transformacion;
            mundo.style.setProperty('--tq-yaw', `${yaw}deg`);
            mundo.style.setProperty('--tq-pitch', `${pitch}deg`);
            // Las viñetas guardan su tamaño en pantalla: se escalan al revés que el mundo.
            mundo.style.setProperty('--tq-inv', (1 / escala).toFixed(3));
            this.lejos = this.sala === null && escala < this.ESCALA_LEJOS;
            this.zonaAqui = this.zonaActual();
            clearTimeout(this.reloj);
            this.reloj = setTimeout(() => this.$root.dispatchEvent(new CustomEvent('tq-faja-quieta', { detail: { zona: this.zonaAqui }, bubbles: true })), 300);
        },

        zonaActual() {
            if (this.sala !== null || this.lejos || this.cam.tx >= config.fin) return null;

            return Math.max(0, Math.min(config.centros.length - 1, Math.floor(this.cam.tx / config.anchoZona)));
        },

        // Un vuelo de cámara; la promesa se cumple al llegar o si otro vuelo (o la persona) lo interrumpe.
        volarA(destino, duracion = 850) {
            this.pararVuelo();
            const desde = { ...this.cam };
            const hasta = { ...desde, ...destino };

            if (this.sinMovimiento() || duracion <= 0) {
                Object.assign(this.cam, hasta);
                this.aplicarCamara();

                return Promise.resolve();
            }

            const suave = (k) => (k < 0.5 ? 4 * k * k * k : 1 - Math.pow(-2 * k + 2, 3) / 2);
            const inicio = performance.now();

            return new Promise((listo) => {
                this.finVuelo = listo;
                const paso = (ahora) => {
                    const k = suave(Math.min(1, (ahora - inicio) / duracion));
                    for (const clave of Object.keys(hasta)) this.cam[clave] = desde[clave] + (hasta[clave] - desde[clave]) * k;
                    this.aplicarCamara();
                    if (k < 1) { this.vuelo = requestAnimationFrame(paso); return }
                    this.vuelo = null;
                    this.finVuelo = null;
                    listo();
                };
                this.vuelo = requestAnimationFrame(paso);
            });
        },

        pararVuelo() {
            cancelAnimationFrame(this.vuelo);
            this.vuelo = null;
            const fin = this.finVuelo;
            this.finVuelo = null;
            if (fin) fin();
        },

        irAZona(indice) { this.volarA(this.camaraDeZona(indice)) },

        acercar(factor) { this.volarA({ dist: this.distanciaPara(this.escala() * factor) }, 350) },

        // A la zona anterior o siguiente sin cambiar el zoom.
        saltarZona(direccion) {
            if (this.sala !== null) return;
            const centros = config.centros;
            let actual = 0;
            let mejor = Infinity;
            centros.forEach((x, j) => {
                const distancia = Math.abs(x - this.cam.tx);
                if (distancia < mejor) { mejor = distancia; actual = j }
            });
            const siguiente = Math.max(0, Math.min(centros.length - 1, actual + direccion));
            if (siguiente === actual && mejor < 40) return;
            this.volarA({ tx: centros[siguiente], ty: 230, yaw: 0 }, 450);
        },

        desplazar(dx, dy) {
            const escala = this.escala();
            const coseno = Math.max(Math.cos(this.cam.pitch * Math.PI / 180), 0.3);
            this.cam.tx -= dx / escala;
            this.cam.ty -= dy / escala / coseno;
        },

        // De la pantalla al piso (z = 0), para el arrastre del paso 4 (prototipo, `toFloor`).
        alPiso(cx, cy) {
            const r = this.$root.getBoundingClientRect();
            const u = cx - (r.left + r.width / 2);
            const v = cy - (r.top + r.height / 2);
            const M = new DOMMatrix(this.transformacion);
            const P = this.PERSPECTIVA;
            const a1 = P * M.m11 + u * M.m13, b1 = P * M.m21 + u * M.m23, c1 = u * P - u * M.m43 - P * M.m41;
            const a2 = P * M.m12 + v * M.m13, b2 = P * M.m22 + v * M.m23, c2 = v * P - v * M.m43 - P * M.m42;
            const det = a1 * b2 - a2 * b1;

            return { x: (c1 * b2 - c2 * b1) / det, y: (a1 * c2 - a2 * c1) / det };
        },

        presionar(evento) {
            if (evento.target.closest('.tq-faja-ui') || (evento.pointerType === 'mouse' && evento.button !== 0)) return;
            this.arrastre = { id: evento.pointerId, x0: evento.clientX, y0: evento.clientY, x: evento.clientX, y: evento.clientY, movido: false, objetivo: evento.target, caja: this.tomarCaja(evento.target) };
        },

        // La caja que se puede arrastrar bajo el puntero, con su versión de ahora (D4); nula si no hay o si otra está en curso.
        tomarCaja(objetivo) {
            const el = objetivo.closest('[data-caja]');
            if (! el || this.ocupada !== null) return null;
            const indice = config.lugares.indexOf(el.dataset.lugar);
            const sitio = el.closest('[data-sitio]');
            const desplazo = el.closest('[data-desplazo]');
            if (indice < 0 || ! sitio || ! desplazo) return null;
            const x = parseFloat(sitio.style.left);
            const y = parseFloat(sitio.style.top);
            // Paso 7: si el hombrecito la dejó desordenada, se parte de ahí (sin salto al levantarla).
            const dx = Number(desplazo.dataset.dx ?? 0) || 0;
            const dy = Number(desplazo.dataset.dy ?? 0) || 0;
            const giro = Number(desplazo.dataset.giro ?? 0) || 0;

            return { el, desplazo, id: Number(el.dataset.caja), lugar: el.dataset.lugar, indice, version: Number(el.dataset.version),
                ancho: el.offsetWidth, origen: { x, y }, x: x + dx, y: y + dy, z: 0, tumbo: 0, giro, desfase: null, hacia: null, listo: false };
        },

        mover(evento) {
            const r = this.$root.getBoundingClientRect();
            if (! this.sinMovimiento()) this.inclinacion = { x: (evento.clientX - r.left) / r.width - 0.5, y: (evento.clientY - r.top) / r.height - 0.5 };
            const a = this.arrastre;

            if (a !== null && a.id === evento.pointerId) {
                const dx = evento.clientX - a.x;
                const dy = evento.clientY - a.y;
                a.x = evento.clientX;
                a.y = evento.clientY;

                if (! a.movido && Math.hypot(evento.clientX - a.x0, evento.clientY - a.y0) > this.UMBRAL_ARRASTRE_PX) {
                    a.movido = true;
                    this.pararVuelo();
                    this.$root.setPointerCapture(evento.pointerId);
                    if (a.caja) this.levantar(a.caja, evento.clientX, evento.clientY);
                }

                // Con una caja, la caja sigue al cursor proyectado al piso; sin caja, se desplaza la vista.
                if (a.movido && a.caja) this.arrastrarCaja(a.caja, evento.clientX, evento.clientY);
                else if (a.movido) this.desplazar(dx, dy);
            }

            if (this.vuelo === null) this.aplicarCamara();
        },

        soltar(evento) {
            const a = this.arrastre;
            if (a === null || a.id !== evento.pointerId) return;
            this.arrastre = null;

            if (a.caja && a.movido) {
                cancelAnimationFrame(this.bordeVuelo);
                this.bordeVuelo = null;
                if (evento.type === 'pointercancel') { this.apuntar(a.caja, null); this.volver(a.caja) } else this.soltarCaja(a.caja);

                return;
            }

            if (a.movido || evento.type === 'pointercancel') return;
            const caja = a.objetivo.closest('[data-caja]');
            const pila = a.objetivo.closest('[data-pila]');
            const ir = a.objetivo.closest('[data-ir]');
            const terminal = a.objetivo.closest('[data-terminal]');
            const vineta = a.objetivo.closest('[data-vineta]');
            if (caja) {
                // Paso 7: tocarla la desordena (el hombrecito); luego se abre.
                this.$root.dispatchEvent(new CustomEvent('tq-faja-tocar', { detail: { id: Number(caja.dataset.caja) }, bubbles: true }));
                this.abrirCaja(Number(caja.dataset.caja));
            }
            else if (pila) this.abrirLugar(pila.dataset.pila);
            else if (ir) ir.dataset.ir === 'ganadas' ? this.entrarGanadas() : this.entrarPerdidas();
            else if (terminal) this.verLugar(terminal.dataset.terminal);
            else if (vineta) this.irAZona(Number(vineta.dataset.zona));
        },

        rueda(evento) {
            evento.preventDefault();

            if (evento.ctrlKey || evento.metaKey) {
                if (this.sala !== null) return;
                const ahora = performance.now();
                this.ruedaAcumulada += evento.deltaY;
                if (ahora > this.ruedaBloqueadaHasta && Math.abs(this.ruedaAcumulada) > 40) {
                    this.saltarZona(this.ruedaAcumulada > 0 ? 1 : -1);
                    this.ruedaAcumulada = 0;
                    this.ruedaBloqueadaHasta = ahora + 420;
                }

                return;
            }

            // Hacia abajo aleja; hacia arriba acerca.
            this.pararVuelo();
            this.cam.dist = this.distanciaPara(this.escala() * Math.exp(-evento.deltaY * 0.0016));
            this.aplicarCamara();
        },

        teclado(evento) {
            if (evento.defaultPrevented || evento.ctrlKey || evento.metaKey || evento.altKey) return;
            const destino = evento.target;
            if (destino instanceof Element && destino.closest('input, textarea, select, [contenteditable], .fi-modal, [role="dialog"]')) return;
            if (! this.$root.isConnected || this.$root.offsetParent === null) return;
            // Con el despiece abierto, las teclas son suyas (Esc lo cierra).
            if (this.$root.querySelector('[data-despiece][data-abierto]')) return;
            const tecla = evento.key;
            if (tecla === 'Escape' && this.sala !== null) { this.volverAFaja(); evento.preventDefault(); return }
            if (tecla === 'ArrowRight' || tecla === 'd' || tecla === 'D') this.saltarZona(1);
            else if (tecla === 'ArrowLeft' || tecla === 'a' || tecla === 'A') this.saltarZona(-1);
            else return;
            evento.preventDefault();
        },

        // Devuelve si alguien (el despiece, paso 6) tomó la apertura.
        abrirCaja(id, url = null) {
            const caja = this.$root.querySelector(`[data-caja="${id}"]`);
            url = url ?? caja?.dataset.url ?? null;
            const abrir = new CustomEvent('tq-faja-abrir', { detail: { id, url }, bubbles: true, cancelable: true });
            this.$root.dispatchEvent(abrir);
            if (! abrir.defaultPrevented && url) window.location.assign(url);

            return abrir.defaultPrevented;
        },

        // ——— Paso 5: tablas, salas y cortina ———

        // La ventana con la tabla de un lugar (zona o sala): `FajaOportunidades::verLugarAction`.
        verLugar(lugar) {
            if (! config.lugares.includes(lugar) && ! Object.hasOwn(config.salas, lugar)) return;
            this.$wire.mountAction('verLugar', { lugar });
        },

        // Una fila de la tabla: se abre su caja como si se la tocara (`tq-faja-abrir`).
        abrirFila(detalle) {
            const id = Number(detalle?.id);
            if (! id) return;
            if (this.abrirCaja(id, detalle.url ?? null)) this.$wire.unmountAction();
        },

        async entrarGanadas() {
            if (this.sala === 'ganadas' || this.viajando || this.ocupada !== null) return;
            this.viajando = true;
            try {
                // Hasta la boca de la tubería, que abre la cortina, y adentro (prototipo, `enterWin`).
                if (this.sala === null && ! this.sinMovimiento()) {
                    await this.volarA({ tx: config.fin - 170, ty: 220, pitch: 66, yaw: -34, dist: this.distanciaPara(1.5) }, 900);
                    this.golpearCortina(0.5, 6);
                    await this.volarA({ tx: config.fin + 90, dist: this.distanciaPara(2.2) }, 380);
                }
                const o = config.salas.ganadas;
                await this.fundido(() => this.ponerSala('ganadas', { tx: o.x + 120, ty: o.y + 330, pitch: 60, yaw: -22, dist: this.distanciaPara(1.6) }));
                await this.volarA(this.camaraDeSala(), 900);
            } finally {
                this.viajando = false;
            }
        },

        async entrarPerdidas() {
            if (this.sala === 'perdidas' || this.viajando || this.ocupada !== null) return;
            this.viajando = true;
            try {
                // Hacia la canaleta, casi a ras del piso, y abajo (prototipo, `enterLost`).
                if (this.sala === null && ! this.sinMovimiento()) {
                    await this.volarA({ tx: Math.max(300, Math.min(config.fin - 200, this.cam.tx)), ty: 420, pitch: 6, yaw: 0, dist: this.distanciaPara(1.8) }, 900);
                }
                const o = config.salas.perdidas;
                await this.fundido(() => this.ponerSala('perdidas', { tx: o.x + 500, ty: o.y + 320, pitch: 4, yaw: 0, dist: this.distanciaPara(1.9) }));
                await this.volarA(this.camaraDeSala(), 1100);
            } finally {
                this.viajando = false;
            }
        },

        async volverAFaja() {
            const desde = this.sala;
            if (desde === null || this.viajando) return;
            this.viajando = true;
            try {
                await this.fundido(() => this.ponerSala(null, desde === 'ganadas'
                    ? { tx: config.fin - 60, ty: 230, pitch: 60, yaw: -26, dist: this.distanciaPara(1.4) }
                    : { tx: 1200, ty: 400, pitch: 8, yaw: 0, dist: this.distanciaPara(1.6) }));
                await this.volarA(this.camaraDeZona(desde === 'ganadas' ? config.lugares.length - 1 : 1), 900);
            } finally {
                this.viajando = false;
            }
        },

        ponerSala(sala, camara) {
            this.pararVuelo();
            this.sala = sala;
            this.inclinacion = { x: 0, y: 0 };
            Object.assign(this.cam, camara);
            this.aplicarCamara();
            this.moverCortinaSiToca();
            // Paso 8: si lo enfocado se ocultó con el cambio de nivel (el botón o el terminal que se usó), el foco vuelve al visor.
            this.$nextTick(() => {
                const activo = document.activeElement;
                if (activo instanceof HTMLElement && activo !== this.$root && this.$root.contains(activo) && activo.getClientRects().length === 0) this.$root.focus({ preventScroll: true });
            });
        },

        // Paso 8: la viñeta con el teclado. De cerca las viñetas se ocultan, así que el foco pasa a la primera caja de la
        // zona (o a su pila, o al visor si está vacía).
        async irDesdeVineta(indice) {
            await this.volarA(this.camaraDeZona(indice));
            const lugar = config.lugares[indice];
            const primero = this.$root.querySelector(`[data-capa="cajas"] [data-caja][data-lugar="${lugar}"], [data-capa="cajas"] [data-pila='${lugar}']`);
            (primero ?? this.$root).focus({ preventScroll: true });
        },

        // Paso 8, teclado: lo que recibe el foco con Tab y quedó fuera de la vista trae la cámara hasta ahí. El visor no se
        // desplaza: el navegador movería su scroll para mostrar lo enfocado y descuadraría el mundo.
        alEnfocar(evento) {
            this.$root.scrollTop = 0;
            this.$root.scrollLeft = 0;
            const destino = evento.target;
            if (! (destino instanceof Element) || this.sala !== null || this.lejos || this.arrastre !== null) return;
            if (typeof destino.matches === 'function' && ! destino.matches(':focus-visible')) return;
            const sitio = destino.closest('[data-sitio]') ?? destino.closest('[data-pila]')?.parentElement ?? destino.closest('.tq-faja-cartel');
            if (! sitio || ! sitio.closest('.tq-faja-nivel-faja')) return;
            const x = parseFloat(sitio.style.left);
            if (! Number.isFinite(x)) return;
            const medio = (this.$root.getBoundingClientRect().width || 1200) / 2 / this.escala();
            if (Math.abs(x - this.cam.tx) > medio * 0.75) this.volarA({ tx: x, ty: 230, yaw: 0 }, 450);
        },

        // Pantalla negra mientras se cambia de nivel; sin animaciones, el cambio es directo.
        async fundido(cambio) {
            if (this.sinMovimiento()) { cambio(); return }
            this.oscuro = true;
            await this.esperar(300);
            cambio();
            await this.esperar(60);
            this.oscuro = false;
        },

        // La cortina de PVC (prototipo, `physics`): cada tira es un resorte amortiguado con un poco de ruido.
        // Paso 8: el bucle solo corre cuando la cortina se ve y se mueve (sin sala, sin pausa, sin «reducir animaciones»).
        iniciarCortina() {
            const tiras = [...this.$root.querySelectorAll('[data-tira]')];
            this.cortina = { tiras, a: tiras.map(() => 0), v: tiras.map(() => 0), quieta: false };
            this.moverCortinaSiToca();
        },

        moverCortinaSiToca() {
            const c = this.cortina;
            if (this.sinMovimiento()) {
                this.pararCortina();
                if (! c.quieta) { c.tiras.forEach((t, i) => { c.a[i] = 0; c.v[i] = 0; t.style.transform = '' }); c.quieta = true }

                return;
            }
            c.quieta = false;
            if (this.pausada || this.sala !== null || c.tiras.length === 0) { this.pararCortina(); return }
            if (this.fisica !== null) return;
            let antes = performance.now();
            const paso = (ahora) => {
                // La preferencia de la persona (`tq-sin-animaciones`) no avisa al cambiar: se mira en cada cuadro.
                if (this.sinMovimiento()) { this.fisica = null; this.moverCortinaSiToca(); return }
                const dt = Math.min(0.04, Math.max(0, (ahora - antes) / 1000));
                antes = ahora;
                this.moverCortina(dt);
                this.fisica = requestAnimationFrame(paso);
            };
            this.fisica = requestAnimationFrame(paso);
        },

        pararCortina() {
            cancelAnimationFrame(this.fisica);
            this.fisica = null;
        },

        moverCortina(dt) {
            const c = this.cortina;
            c.tiras.forEach((t, i) => {
                c.v[i] += (-34 * c.a[i] - 2.6 * c.v[i] + (Math.random() - 0.5)) * dt;
                c.a[i] = Math.max(-1.1, Math.min(1.1, c.a[i] + c.v[i] * dt));
                t.style.transform = `rotateX(${(c.a[i] * 57).toFixed(2)}deg) rotateY(${(c.a[i] * 8 * (i % 2 ? 1 : -1)).toFixed(2)}deg)`;
            });
        },

        // Un empujón a la cortina: `posicion` de 0 a 1 a lo ancho, `fuerza` como el prototipo (`hitCurtain`).
        golpearCortina(posicion, fuerza) {
            if (this.sinMovimiento()) return;
            this.moverCortinaSiToca();
            const c = this.cortina;
            const n = c.tiras.length;
            c.tiras.forEach((_, i) => {
                const u = n > 1 ? i / (n - 1) : 0.5;
                c.v[i] += fuerza * Math.exp(-Math.pow((u - posicion) / 0.26, 2)) * (1 + (Math.random() - 0.5) * 0.5);
            });
        },

        // Ganar (paso 4): la caja entra por la cortina y desaparece en la tubería (prototipo, `push` a la tubería).
        async entrarPorLaCortina(caja) {
            this.golpearCortina(0.5, 2);
            await this.deslizar(caja, config.fin - caja.ancho / 2 - 30, 220, 450);
            this.golpearCortina(0.5, 7);
            await this.animar(700, (k) => {
                this.posicionar(caja, config.fin - caja.ancho / 2 - 30 + k * (caja.ancho / 2 + 430), 220);
                caja.desplazo.style.opacity = String(k < 0.6 ? 1 : 1 - (k - 0.6) / 0.4);
            }, false);
            this.golpearCortina(0.5, 3);
            caja.desplazo.style.opacity = '0';
        },

        abrirLugar(lugar) {
            const abrir = new CustomEvent('tq-faja-abrir-lugar', { detail: { lugar }, bubbles: true, cancelable: true });
            this.$root.dispatchEvent(abrir);
            if (! abrir.defaultPrevented) window.location.assign(config.urlTabla);
        },

        // Paso 4: `estado` es 'aim', 'ok', 'no' o null.
        marcarEscaner(indice, estado) {
            const escaner = this.$root.querySelector(`[data-escaner="${indice}"]`);
            if (! escaner) return;
            escaner.classList.remove('aim', 'ok', 'no');
            if (estado) { void escaner.offsetWidth; escaner.classList.add(estado) }
        },

        avisar(texto) {
            this.mensaje = texto;
            clearTimeout(this.relojMensaje);
            this.relojMensaje = setTimeout(() => this.mensaje = null, 3800);
        },

        // ——— Paso 4: arrastre y acciones ———

        levantar(caja, cx, cy) {
            const piso = this.alPiso(cx, cy);
            caja.desfase = { x: caja.x - piso.x, y: caja.y - piso.y };
            caja.desplazo.classList.add('levantada');
            caja.z = 30;
            this.pintar(caja);
        },

        arrastrarCaja(caja, cx, cy) {
            const piso = this.alPiso(cx, cy);
            this.posicionar(caja, piso.x + caja.desfase.x, Math.max(70, Math.min(470, piso.y + caja.desfase.y)));
            this.apuntar(caja, this.destino(caja));
            // Cerca de los bordes, la cámara avanza sola mientras el puntero siga ahí.
            const r = this.$root.getBoundingClientRect();
            const ex = (cx - r.left) / r.width;
            const direccion = ex < 0.08 ? -1 : (ex > 0.92 ? 1 : 0);
            cancelAnimationFrame(this.bordeVuelo);
            this.bordeVuelo = null;
            if (direccion === 0) return;
            const paso = () => {
                if (this.arrastre === null || this.arrastre.caja !== caja) return;
                this.cam.tx += direccion * 9 / this.escala();
                this.aplicarCamara();
                const p = this.alPiso(this.arrastre.x, this.arrastre.y);
                this.posicionar(caja, p.x + caja.desfase.x, Math.max(70, Math.min(470, p.y + caja.desfase.y)));
                this.apuntar(caja, this.destino(caja));
                this.bordeVuelo = requestAnimationFrame(paso);
            };
            this.bordeVuelo = requestAnimationFrame(paso);
        },

        posicionar(caja, x, y) {
            caja.x = x;
            caja.y = y;
            this.pintar(caja);
        },

        // La caja se mueve desde su lugar (el que dibujó el servidor) con un transform en `data-desplazo`.
        pintar(caja) {
            caja.desplazo.style.transform = `translate3d(${caja.x - caja.origen.x}px, ${caja.y - caja.origen.y}px, ${caja.z}px) rotateZ(${caja.giro ?? 0}deg) rotateX(${caja.tumbo}deg)`;
        },

        // Adónde caería la caja (prototipo, `dropTarget`): la canaleta delante de la faja, el final o una zona.
        destino(caja) {
            if (caja.y > 388 && caja.x < config.fin) return { tipo: 'perder', lugar: config.perdida };
            const zona = caja.x >= config.fin - 80 ? config.lugares.length : Math.max(0, Math.min(config.lugares.length - 1, Math.floor(caja.x / config.anchoZona)));
            const lugar = zona === config.lugares.length ? config.ganada : config.lugares[zona];
            if (zona === caja.indice) return { tipo: 'misma', lugar };
            if (zona === caja.indice + 1) return { tipo: 'siguiente', lugar };

            return { tipo: zona > caja.indice ? 'salto' : 'atras', lugar };
        },

        // El escáner de la zona se ilumina al apuntar a la siguiente; la canaleta y el final, al apuntarles.
        apuntar(caja, destino) {
            const escaner = destino !== null && destino.tipo === 'siguiente' ? caja.indice : null;
            if (escaner !== this.apuntaEscaner) {
                if (this.apuntaEscaner !== null) this.marcarEscaner(this.apuntaEscaner, null);
                if (escaner !== null) this.marcarEscaner(escaner, 'aim');
                this.apuntaEscaner = escaner;
            }
            this.apunta = destino === null ? null : (destino.tipo === 'perder' ? config.perdida : (destino.tipo === 'siguiente' && destino.lugar === config.ganada ? config.ganada : null));
        },

        async soltarCaja(caja) {
            const destino = this.destino(caja);
            this.apuntar(caja, null);
            caja.z = 0;
            this.pintar(caja);

            if (destino.tipo === 'misma') return this.dejarEnSuZona(caja);
            if (destino.tipo === 'salto' || destino.tipo === 'atras') {
                this.avisar(config.textos[destino.tipo]);

                return this.volver(caja);
            }

            this.ocupada = caja;
            caja.hacia = destino.lugar;
            if (destino.tipo === 'perder') this.posicionar(caja, caja.x, 360);

            return this.pedirDialogo(caja);
        },

        // Antes de animar, el diálogo de la acción (D5). El servidor elige la acción y revisa la versión (D4).
        async pedirDialogo(caja) {
            this.ocupada = caja;
            let respuesta;
            try {
                respuesta = await this.$wire.abrirMovimiento(caja.id, caja.lugar, caja.hacia, caja.version);
            } catch (error) {
                respuesta = { ok: false, conflicto: false, mensaje: 'No se pudo abrir el diálogo. Vuelve a intentarlo.' };
            }
            if (respuesta.ok) return;
            this.avisar(respuesta.mensaje);
            this.ocupada = null;
            if (respuesta.conflicto) this.recargar();
            else this.volver(caja);
        },

        // D14, desde el despiece: el mismo camino que soltar la caja en `hacia`, con la versión leída al abrirlo. Si la
        // caja no está dibujada (en una pila «+N más»), una caja sin dibujo: el diálogo y la acción son los mismos.
        moverSinArrastre(detalle) {
            const id = Number(detalle?.id);
            if (! id || this.ocupada !== null || ! config.lugares.includes(detalle.desde)) return;
            const el = this.$root.querySelector(`[data-caja="${id}"]`);
            const caja = (el ? this.tomarCaja(el) : null) ?? {
                el: null, desplazo: document.createElement('div'), id, lugar: detalle.desde, indice: config.lugares.indexOf(detalle.desde),
                version: 0, ancho: 0, origen: { x: 0, y: 0 }, x: 0, y: 0, z: 0, tumbo: 0, giro: 0, desfase: null, hacia: null, listo: false,
            };
            caja.version = Number(detalle.version);
            caja.hacia = detalle.hacia;

            return this.pedirDialogo(caja);
        },

        // Lo llenado en el diálogo (`FajaOportunidades::dialogo`): la caja va al escáner y se ejecuta la acción.
        dialogoListo(detalle) {
            const caja = this.ocupada;
            if (caja === null || caja.listo || Number(detalle.oportunidad) !== caja.id) return;
            caja.listo = true;
            this.ejecutar(caja, detalle.datos ?? {});
        },

        // El diálogo se cerró sin confirmar (Cancelar, Esc o la ×): la caja vuelve a su lugar.
        dialogoCerrado(detalle) {
            const caja = this.ocupada;
            if (caja === null || caja.listo || ! String(detalle?.id ?? '').startsWith(`fi-${this.$wire.$id}-action-`)) return;
            setTimeout(() => {
                if (this.ocupada !== caja || caja.listo) return;
                this.ocupada = null;
                this.volver(caja);
            }, 250);
        },

        async ejecutar(caja, datos) {
            const perder = caja.hacia === config.perdida;
            const escaner = caja.indice;
            const xEscaner = escaner * config.anchoZona + config.anchoZona - 20;

            try {
                if (! perder) await this.empujar(caja, xEscaner - caja.ancho / 2 - 30, 220);
                let r;
                try {
                    r = await this.$wire.moverOportunidad(caja.id, caja.lugar, caja.hacia, caja.version, datos);
                } catch (error) {
                    r = { ok: false, conflicto: false, mensaje: 'No se pudo guardar. Vuelve a intentarlo.' };
                }

                if (! r.ok) {
                    if (! perder) this.marcarEscaner(escaner, 'no');
                    this.avisar(r.mensaje);
                    await this.esperar(800);
                    if (! perder) this.marcarEscaner(escaner, null);
                    if (r.conflicto) this.recargar();
                    else await this.volver(caja);

                    return;
                }

                if (perder) {
                    await this.caer(caja);
                } else {
                    this.marcarEscaner(escaner, 'ok');
                    if (caja.hacia === config.ganada) {
                        await this.entrarPorLaCortina(caja);
                    } else {
                        await this.deslizar(caja, xEscaner + caja.ancho / 2 + 60, 220, 650);
                    }
                    this.marcarEscaner(escaner, null);
                }
                this.avisar(r.mensaje);
                await this.recargar();
                if (! perder && caja.hacia !== config.ganada && ! this.lejos) this.irAZona(escaner + 1);
            } finally {
                this.ocupada = null;
            }
        },

        // El empuje hasta el escáner: gancho para el hombrecito (paso 7); sin él, la caja se desliza sola.
        async empujar(caja, x, y) {
            const evento = new CustomEvent('tq-faja-empujar', {
                detail: { id: caja.id, desde: { x: caja.x, y: caja.y }, hasta: { x, y }, mover: (nx, ny) => this.posicionar(caja, nx, ny), esperas: [] },
                bubbles: true,
                cancelable: true,
            });
            this.$root.dispatchEvent(evento);
            if (evento.defaultPrevented) {
                await Promise.all(evento.detail.esperas);
                this.posicionar(caja, x, y);

                return;
            }
            await this.deslizar(caja, x, y, 650);
        },

        // Paso 7: soltada en su misma zona, queda donde cayó (dentro de la zona), un poco girada y desordenada; el
        // hombrecito viene a ordenarla (prototipo, `drop` y `clampIn`). Si nadie la toma, vuelve a su lugar.
        dejarEnSuZona(caja) {
            const x0 = caja.indice * config.anchoZona;
            const tope = caja.indice === config.lugares.length - 1 ? 380 : 470;
            caja.desplazo.classList.remove('levantada');
            caja.giro = (Math.random() - 0.5) * 24;
            this.posicionar(caja, Math.max(x0 + 100, Math.min(x0 + tope, caja.x)), Math.max(110, Math.min(330, caja.y)));
            const evento = new CustomEvent('tq-faja-desordenar', {
                detail: { id: caja.id, dx: caja.x - caja.origen.x, dy: caja.y - caja.origen.y, giro: caja.giro },
                bubbles: true,
                cancelable: true,
            });
            this.$root.dispatchEvent(evento);
            if (! evento.defaultPrevented) return this.volver(caja);
        },

        // La caja vuelve a su lugar en la zona, derecha; el hombrecito la da por ordenada (`tq-faja-devuelta`).
        async volver(caja) {
            caja.desplazo.classList.remove('levantada');
            caja.z = 0;
            const desde = { x: caja.x, y: caja.y, giro: caja.giro ?? 0 };
            await this.animar(350, (k) => {
                caja.giro = desde.giro * (1 - k);
                this.posicionar(caja, desde.x + (caja.origen.x - desde.x) * k, desde.y + (caja.origen.y - desde.y) * k);
            });
            caja.desplazo.style.transform = '';
            this.olvidarDesorden(caja.desplazo);
            this.$root.dispatchEvent(new CustomEvent('tq-faja-devuelta', { detail: { id: caja.id }, bubbles: true }));
        },

        olvidarDesorden(desplazo) {
            delete desplazo.dataset.dx;
            delete desplazo.dataset.dy;
            delete desplazo.dataset.giro;
        },

        // Perdida: se desliza al borde, se inclina y cae a la canaleta (prototipo, `lose`).
        async caer(caja) {
            const y0 = caja.y;
            await this.animar(400, (k) => this.posicionar(caja, caja.x, y0 + (420 - y0) * k));
            await this.animar(300, (k) => { caja.tumbo = -55 * k; caja.z = -20 * k; this.pintar(caja) });
            await this.animar(560, (k) => {
                caja.tumbo = -55 - 120 * k;
                caja.z = -20 - 620 * k * k;
                this.pintar(caja);
                caja.desplazo.style.opacity = String(1 - k * k);
            }, false);
            caja.desplazo.style.opacity = '0';
        },

        deslizar(caja, x, y, duracion) {
            const desde = { x: caja.x, y: caja.y };

            return this.animar(duracion, (k) => this.posicionar(caja, desde.x + (x - desde.x) * k, desde.y + (y - desde.y) * k));
        },

        // Una animación de `duracion` ms con suavizado; con «reducir animaciones», instantánea (D12).
        animar(duracion, cuadro, suavizar = true) {
            return new Promise((listo) => {
                if (this.sinMovimiento() || duracion <= 0) { cuadro(1); listo(); return }
                const suave = (k) => (k < 0.5 ? 4 * k * k * k : 1 - Math.pow(-2 * k + 2, 3) / 2);
                const inicio = performance.now();
                const paso = (ahora) => {
                    const k = Math.min(1, (ahora - inicio) / duracion);
                    cuadro(suavizar ? suave(k) : k);
                    if (k < 1) requestAnimationFrame(paso); else listo();
                };
                requestAnimationFrame(paso);
            });
        },

        esperar(ms) {
            return new Promise((listo) => setTimeout(listo, this.sinMovimiento() ? 0 : ms));
        },

        // Los datos nuevos del servidor: las cajas vuelven a sus lugares, con sus zonas y viñetas al día (D4), y la fila
        // de cifras de arriba también (`MuestraLasVistas::actualizarCifras`, el mismo camino del tablero; 2.21c).
        async recargar() {
            await this.$wire.actualizarCifras();
            this.$root.querySelectorAll('[data-desplazo]').forEach((el) => {
                el.style.transform = '';
                el.style.opacity = '';
                el.classList.remove('levantada');
                this.olvidarDesorden(el);
            });
            // Paso 7: el hombrecito vuelve a poner el desorden de la sesión (una caja que cambió de zona llega desordenada).
            this.$root.dispatchEvent(new CustomEvent('tq-faja-redibujada', { bubbles: true }));
        },
    });
</script>
