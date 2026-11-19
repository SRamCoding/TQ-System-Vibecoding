{{--
    El comportamiento del despiece (plan 2.21b, paso 6; pedido B3, D12, D14), como el prototipo (`openBox`,
    `layoutSlabs`, `pickTab`, `closeBox`): escucha `tq-faja-abrir` y lo cancela, pide la caja a `DespieceDeOportunidad`
    y la vista nace desde la caja (vuela desde su lugar en la pantalla, `--fx` y `--fy`) mientras las capas se separan.
    Tocar una capa (o Enter y Espacio con el foco en ella) la asoma y su contenido sale en el panel. Esc y «Volver»
    cierran y el foco vuelve a la caja. Los botones del panel cierran y piden a la faja el mismo camino que soltar la
    caja (`tq-faja-mover`: diálogo de la acción, escáner y acción real). Con «reducir animaciones», sin vuelo ni
    separación animada.
--}}
<script>
    window.tqDespiece = () => ({
        abierta: false,
        id: null,
        origen: null,
        cargando: false,

        sinMovimiento() {
            return window.matchMedia('(prefers-reduced-motion: reduce)').matches || document.documentElement.classList.contains('tq-sin-animaciones');
        },

        // `tq-faja-abrir` ({ id, url }): tocar una caja, Enter o una fila de una tabla. Se cancela aquí, antes de esperar.
        async abrir(evento) {
            const id = Number(evento.detail?.id);
            if (! id) return;
            evento.preventDefault();
            if (this.cargando) return;
            this.cargando = true;
            const caja = document.querySelector(`[data-faja] [data-caja="${id}"]`);
            const r = caja?.getBoundingClientRect();
            this.origen = r && r.width > 0 && r.height > 0 ? { x: r.left + r.width / 2, y: r.top + r.height / 2 } : null;
            try {
                await this.$wire.abrir(id);
            } catch (error) {
                return;
            } finally {
                this.cargando = false;
            }
            this.id = id;
            this.abierta = true;
            await this.$nextTick();
            this.nacer();
            this.$root.querySelector('[data-panel]')?.focus({ preventScroll: true });
        },

        // La vista nace desde la caja y la caja se separa en capas (prototipo, `openBox` y `layoutSlabs`).
        nacer() {
            this.ajustar();
            const escena = this.$root.querySelector('[data-escena]');
            const pila = this.$root.querySelector('[data-pila-despiece]');
            if (! escena || ! pila) return;
            if (this.sinMovimiento()) {
                escena.classList.remove('desde');
                pila.classList.remove('juntas');

                return;
            }
            const s = escena.getBoundingClientRect();
            escena.style.setProperty('--fx', this.origen ? `${this.origen.x - (s.left + s.width / 2)}px` : '0px');
            escena.style.setProperty('--fy', this.origen ? `${this.origen.y - (s.top + s.height / 2)}px` : '0px');
            void escena.offsetWidth;
            requestAnimationFrame(() => {
                escena.classList.remove('desde');
                requestAnimationFrame(() => pila.classList.remove('juntas'));
            });
        },

        // La pila cabe en la escena (prototipo: escala hasta 1, con 620 px de lado).
        ajustar() {
            const escena = this.$root.querySelector('[data-escena]');
            if (! escena) return;
            const escala = Math.min(1, escena.clientWidth / 620, escena.clientHeight / 620);
            escena.style.setProperty('--escala', String(Math.max(0.3, escala)));
        },

        elegir(clave) {
            if (this.$wire.capa === clave) return;
            this.$wire.set('capa', clave);
        },

        esc(evento) {
            if (! this.abierta || evento.defaultPrevented) return;
            if (evento.target instanceof Element && evento.target.closest('.fi-modal')) return;
            evento.preventDefault();
            this.cerrar();
        },

        // Vuelve a la faja; el foco, a la caja (o a la faja si la caja no está dibujada: en una pila o en otra sala).
        cerrar(enfocar = true) {
            if (! this.abierta) return;
            const id = this.id;
            this.abierta = false;
            this.id = null;
            this.$wire.cerrar();
            // Paso 7: el hombrecito vuelve a mirar si hay algo que ordenar.
            window.dispatchEvent(new CustomEvent('tq-faja-despiece-cerrado'));
            // Paso 8: sin volver a la caja (Pasar, Ganar o Perder), el foco queda en la faja y no se pierde mientras se
            // abre el diálogo de la acción; al cerrarlo, Filament lo devuelve ahí.
            const caja = enfocar ? document.querySelector(`[data-faja] [data-caja="${id}"]`) : null;
            (caja ?? document.querySelector('[data-faja]'))?.focus({ preventScroll: true });
        },

        // Pasar, ganar o perder sin arrastrar (D14): la faja hace lo mismo que al soltar la caja en ese destino.
        mover(datos) {
            this.cerrar(false);
            window.dispatchEvent(new CustomEvent('tq-faja-mover', { detail: datos }));
        },
    });
</script>
