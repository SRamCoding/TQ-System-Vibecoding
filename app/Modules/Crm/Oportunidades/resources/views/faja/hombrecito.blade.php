{{--
    El hombrecito de la faja (plan 2.21b, paso 7; pedido B4, D11, D12), como el prototipo (`manHTML`, `walk`, `goTo`,
    `orderStage`, `sleep`, `checkMess`): uno solo y decorativo. Su cuerpo son cubos con rodillas, codos, cuello, manos,
    zapatos y cara simple; usa el uniforme de su zona (explorador, inspector, mensajero, ejecutivo) y se lo cambia con un
    destello al llegar. Si la zona que miras tiene cajas desordenadas, va enseguida (camina o corre) y las ordena; tocar
    una caja ordenada la desordena; si entras a una zona ordenada, no va. La primera vez en la sesión, todo aparece
    desordenado. Al terminar duerme echado contra el costado de una caja o la lámina de un escáner, con una de tres poses
    de piernas y «zzz». Empuja la caja al escáner cuando se ejecuta la acción real (`tq-faja-empujar`).

    Es solo pantalla (D11): el desorden y su lugar viven en `sessionStorage` (con try/catch), nunca en la base. No
    intercepta nada (`pointer-events: none`, `aria-hidden`). Con «reducir animaciones» (D12) no camina ni se anima: los
    cambios son instantáneos y duerme quieto. Con la pestaña oculta o la faja fuera de la vista (`tq-faja-pausa`) se detiene. Solo transforms: ninguna lectura de
    medidas por cuadro (las cajas se leen de su estilo en línea).

    Escucha, en el visor: `tq-faja-quieta`, `tq-faja-empujar`, `tq-faja-desordenar` (soltar en la misma zona),
    `tq-faja-tocar`, `tq-faja-devuelta` y `tq-faja-redibujada` (de `faja/camara`), y `tq-faja-despiece-cerrado` en la
    ventana.
--}}
<style>
    .tq-faja .tq-hombre-hueco, .tq-faja .tq-hombre-hueco * { pointer-events: none; }
    .tq-hombre { position: absolute; left: 0; top: 0; transform-origin: 0 0;
        --tq-hombre-piel: #D8A887; --tq-hombre-piel-oscura: #C28F6E; --tq-hombre-pelo: #2B1E16; --tq-hombre-zapato: #1E1B1A;
        --tq-hombre-cinturon: #2A2622; --tq-hombre-ojo: #FBF8F2; --tq-hombre-pupila: #2A1B12; --tq-hombre-ceja: #3A271B;
        --tq-hombre-boca: #8A4A3A; --tq-hombre-mejilla: rgba(220, 110, 100, .35); }
    .tq-hombre-piv { position: absolute; transition: transform .5s cubic-bezier(.3, .7, .3, 1); }
    .tq-hombre-torso, .tq-hombre-cabeza, .tq-hombre-sombrero { left: 0; top: 0; width: 0; height: 0; }
    .tq-hombre-sombrero { transform-origin: 11px 0 176px; }
    .tq-hombre .tq-faja-solido.redondo > .tq-cara { border-radius: 3px; }
    .tq-hombre-sombra { position: absolute; left: -30px; top: -22px; width: 60px; height: 44px; border-radius: 50%; background: radial-gradient(rgba(0, 0, 0, .55), transparent 70%); }

    /* Caminar, correr y empujar */
    .tq-hombre.camina .tq-hombre-muslo.a, .tq-hombre.camina .tq-hombre-brazo.b { animation: tq-hombre-paso-1 .34s ease-in-out infinite alternate; }
    .tq-hombre.camina .tq-hombre-muslo.b, .tq-hombre.camina .tq-hombre-brazo.a { animation: tq-hombre-paso-2 .34s ease-in-out infinite alternate; }
    .tq-hombre.camina .tq-hombre-pierna { animation: tq-hombre-rodilla .34s ease-in-out infinite alternate; }
    .tq-hombre.camina .tq-hombre-antebrazo { transform: rotateY(-28deg); }
    .tq-hombre.corre .tq-hombre-muslo, .tq-hombre.corre .tq-hombre-brazo, .tq-hombre.corre .tq-hombre-pierna { animation-duration: .19s !important; }
    .tq-hombre.corre .tq-hombre-torso { transform: rotateY(10deg); }
    @keyframes tq-hombre-paso-1 { from { transform: rotateY(26deg); } to { transform: rotateY(-26deg); } }
    @keyframes tq-hombre-paso-2 { from { transform: rotateY(-26deg); } to { transform: rotateY(26deg); } }
    @keyframes tq-hombre-rodilla { from { transform: rotateY(4deg); } to { transform: rotateY(30deg); } }
    .tq-hombre.empuja .tq-hombre-brazo { animation: none !important; transform: rotateY(-74deg); }
    .tq-hombre.empuja .tq-hombre-antebrazo { transform: rotateY(-10deg); }
    .tq-hombre.empuja .tq-hombre-torso { transform: rotateY(14deg); }

    /* Dormir: echado contra una superficie, la barbilla caída, un brazo sobre la barriga, la gorra sobre los ojos; las
       piernas, según la pose de esta vez (variables que pone el script). */
    .tq-hombre.duerme .tq-hombre-muslo.a { transform: rotateY(var(--tq-muslo-a, -90deg)) rotateX(var(--tq-abre-a, -4deg)); }
    .tq-hombre.duerme .tq-hombre-muslo.b { transform: rotateY(var(--tq-muslo-b, -90deg)) rotateX(var(--tq-abre-b, 6deg)); }
    .tq-hombre.duerme .tq-hombre-pierna.a { transform: rotateY(var(--tq-rodilla-a, 4deg)); }
    .tq-hombre.duerme .tq-hombre-pierna.b { transform: rotateY(var(--tq-rodilla-b, 2deg)); }
    .tq-hombre.duerme .tq-hombre-torso { transform: rotateY(var(--tq-recuesta, -68deg)); }
    .tq-hombre.duerme .tq-hombre-cabeza { transform: rotateX(var(--tq-ladea, 12deg)) rotateY(var(--tq-cabecea, 22deg)); }
    .tq-hombre.duerme .tq-hombre-sombrero { transform: rotateY(34deg) translateZ(-5px); }
    .tq-hombre.duerme .tq-hombre-brazo.a { transform: rotateY(-42deg) rotateX(-14deg); }
    .tq-hombre.duerme .tq-hombre-antebrazo.a { transform: rotateY(-88deg); }
    .tq-hombre.duerme .tq-hombre-brazo.b { transform: rotateY(var(--tq-brazo-b, -20deg)) rotateX(16deg); }
    .tq-hombre.duerme .tq-hombre-antebrazo.b { transform: rotateY(var(--tq-antebrazo-b, -18deg)); }

    /* La cara, en el costado de la cabeza que mira hacia adelante */
    .tq-hombre-cara { position: absolute; left: 0; top: 0; width: 20px; height: 26px; transform-origin: 0 0; transform: translateX(26px) rotate(90deg); }
    .tq-hombre-cara i { position: absolute; display: block; }
    .tq-hombre-cara .ojo { top: 10px; width: 6px; height: 4px; border-radius: 50%; background: var(--tq-hombre-ojo); }
    .tq-hombre-cara .ojo::after { content: ""; position: absolute; left: 2px; top: .5px; width: 2.6px; height: 3px; border-radius: 50%; background: var(--tq-hombre-pupila); }
    .tq-hombre-cara .ceja { top: 7.5px; width: 6px; height: 1.4px; border-radius: 1px; background: var(--tq-hombre-ceja); }
    .tq-hombre-cara .mejilla { top: 15px; width: 4px; height: 3px; border-radius: 50%; background: var(--tq-hombre-mejilla); }
    .tq-hombre-cara .boca { left: 7px; right: 7px; top: 19.5px; height: 1.6px; border-radius: 0 0 3px 3px; background: var(--tq-hombre-boca); }
    .tq-hombre-cara .ojo.i, .tq-hombre-cara .ceja.i { left: 3px; } .tq-hombre-cara .ojo.d, .tq-hombre-cara .ceja.d { right: 3px; }
    .tq-hombre-cara .mejilla.i { left: 2px; } .tq-hombre-cara .mejilla.d { right: 2px; }
    .tq-hombre.duerme .tq-hombre-cara .ojo { height: 1.4px; top: 11.5px; background: var(--tq-hombre-ceja); }
    .tq-hombre.duerme .tq-hombre-cara .ojo::after { display: none; }
    .tq-hombre.duerme .tq-hombre-cara .boca { left: 8.5px; right: 8.5px; height: 2.6px; border-radius: 50%; }

    /* «zzz» y el destello del cambio de uniforme: miran a la cámara */
    .tq-hombre-zzz { --tq-alto: 38px; font-weight: 800; color: #D9D3FF; font-size: 16px; line-height: 1; white-space: nowrap; }
    .tq-hombre-zzz i { display: inline-block; font-style: normal; opacity: 0; animation: tq-hombre-z 2.4s ease-in-out infinite; }
    .tq-hombre-zzz i:nth-child(2) { animation-delay: .6s; font-size: 20px; }
    .tq-hombre-zzz i:nth-child(3) { animation-delay: 1.2s; font-size: 25px; }
    @keyframes tq-hombre-z { 0% { opacity: 0; transform: translateY(8px); } 30% { opacity: 1; } 100% { opacity: 0; transform: translateY(-22px); } }
    .tq-hombre-destello { --tq-alto: 45px; }
    .tq-hombre-destello i { display: block; width: 70px; height: 70px; border-radius: 50%; border: 3px solid rgba(220, 215, 255, .9); opacity: 0; }
    .tq-hombre-destello.on i { animation: tq-hombre-destello .5s ease-out forwards; }
    @keyframes tq-hombre-destello { from { transform: scale(.2); opacity: 1; } to { transform: scale(1.6); opacity: 0; } }

    /* Reducir animaciones (D12): no camina ni se anima; duerme quieto, con el «zzz» fijo y sin destello. */
    @media (prefers-reduced-motion: reduce) {
        .tq-faja .tq-hombre-piv { transition: none !important; }
        .tq-faja .tq-hombre *, .tq-faja .tq-hombre-zzz i { animation: none !important; }
        .tq-faja .tq-hombre-zzz i { opacity: 1; }
        .tq-faja .tq-hombre-destello { display: none; }
    }
    html.tq-sin-animaciones .tq-faja .tq-hombre-piv { transition: none !important; }
    html.tq-sin-animaciones .tq-faja .tq-hombre *, html.tq-sin-animaciones .tq-faja .tq-hombre-zzz i { animation: none !important; }
    html.tq-sin-animaciones .tq-faja .tq-hombre-zzz i { opacity: 1; }
    html.tq-sin-animaciones .tq-faja .tq-hombre-destello { display: none; }
</style>
<script>
    window.tqHombrecito = (config) => {
        const CLAVE = 'tq-faja-hombrecito';
        // Los uniformes por zona (pedido B4), en un solo lugar. Los colores, como el prototipo (`ROLES`).
        const UNIFORMES = [
            { nombre: 'explorador', chaleco: '#5F6E34', pantalon: '#4A4032', sombrero: 'ala', colorSombrero: '#8A6A3E', extra: 'binoculares' },
            { nombre: 'inspector', chaleco: '#2F82AD', pantalon: '#2F3140', sombrero: 'casco', colorSombrero: '#F2F2EE', extra: 'tablilla' },
            { nombre: 'mensajero', chaleco: '#B0423A', pantalon: '#2F3140', sombrero: 'gorra', colorSombrero: '#B0423A', extra: 'bolso' },
            { nombre: 'ejecutivo', chaleco: '#232A5C', pantalon: '#232A5C', sombrero: null, colorSombrero: null, extra: 'corbata' },
        ];
        const ANCHO = config.anchoZona;
        const ULTIMA = config.lugares.length - 1;
        const CARRIL = 30;
        const azar = (a, b) => a + Math.random() * (b - a);
        const grados = (y, x) => Math.atan2(y, x) * 180 / Math.PI;
        const suave = (k) => (k < 0.5 ? 4 * k * k * k : 1 - Math.pow(-2 * k + 2, 3) / 2);
        // La lámina del escáner de cada zona (prototipo, `SCX`).
        const escaner = (z) => (z + 1) * ANCHO - 20;

        // Estado fuera de Alpine (nada reactivo que vigilar en cada cuadro).
        const h = {
            visor: null, faja: null, el: null, zzz: null, destello: null,
            x: 60, y: 340, cara: 0, zona: 0, dormido: false, pose: {}, uniforme: null,
            cajas: {}, turno: 0, ocupado: false, empujando: null,
            raf: null, finAnimar: null, reloj: null, finEspera: null, relojRevisar: null, relojDestello: null, quitar: [],
        };

        const sinMovimiento = () => h.faja.sinMovimiento();

        // ——— sessionStorage (D11): puede no estar (navegación privada, almacenamiento bloqueado) ———
        const leer = () => {
            try {
                const texto = window.sessionStorage.getItem(CLAVE);

                return texto ? JSON.parse(texto) : null;
            } catch (error) {
                return null;
            }
        };
        const guardar = () => {
            try {
                window.sessionStorage.setItem(CLAVE, JSON.stringify({ cajas: h.cajas, hombre: { x: h.x, y: h.y, cara: h.cara, zona: h.zona, pose: h.pose } }));
            } catch (error) {
                // Sin almacenamiento, el hombrecito sigue igual en esta página.
            }
        };

        // ——— El cuerpo (prototipo, `manHTML`; medidas antes de escalar a la mitad; mira hacia +x) ———
        const solido = (x, y, w, d, alto, z, color, redondo = false, cara = '') =>
            `<div class="tq-faja-cubo tq-faja-solido${redondo ? ' redondo' : ''}" style="left: ${x}px; top: ${y}px; width: ${w}px; height: ${d}px; --tq-z: ${z}px; --tq-c: ${color}">`
            + `<div class="tq-cara tq-cara-arriba" style="width: ${w}px; height: ${d}px; transform: translateZ(${alto}px)"></div>`
            + `<div class="tq-cara tq-cara-frente" style="top: ${d - alto}px; width: ${w}px; height: ${alto}px"></div>`
            + `<div class="tq-cara tq-cara-atras" style="top: ${-alto}px; width: ${w}px; height: ${alto}px"></div>`
            + `<div class="tq-cara tq-cara-izq" style="left: ${-alto}px; width: ${alto}px; height: ${d}px"></div>`
            + `<div class="tq-cara tq-cara-der" style="left: ${w}px; width: ${alto}px; height: ${d}px">${cara}</div></div>`;
        // Una articulación: gira desde su punto de arriba (rodilla, codo, cadera, hombro).
        const articulacion = (clase, x, y, w, d, z, alto, dentro) =>
            `<div class="tq-hombre-piv ${clase}" style="left: ${x}px; top: ${y}px; width: ${w}px; height: ${d}px; transform-origin: ${w / 2}px ${d / 2}px ${z + alto}px">${dentro}</div>`;

        const cuerpo = (u) => {
            const piel = 'var(--tq-hombre-piel)';
            const pielOscura = 'var(--tq-hombre-piel-oscura)';
            const pelo = 'var(--tq-hombre-pelo)';
            const pierna = (lado) => articulacion(`tq-hombre-muslo ${lado}`, -7, lado === 'a' ? -15 : 2, 14, 14, 48, 38,
                solido(0, 0, 14, 14, 38, 48, u.pantalon, true)
                + articulacion(`tq-hombre-pierna ${lado}`, 0, 0, 14, 14, 9, 39,
                    solido(1, 1, 12, 12, 39, 9, u.pantalon, true) + solido(-1, 0, 23, 14, 9, 0, 'var(--tq-hombre-zapato)', true)));
            const brazo = (lado) => articulacion(`tq-hombre-brazo ${lado}`, -5, lado === 'a' ? -30 : 20, 10, 10, 114, 28,
                solido(0, 0, 10, 10, 28, 114, u.chaleco, true)
                + articulacion(`tq-hombre-antebrazo ${lado}`, 0, 0, 10, 10, 90, 24,
                    solido(0.5, 0.5, 9, 9, 24, 90, u.chaleco, true) + solido(0.5, 0.5, 9, 9, 11, 79, piel, true)
                    + (u.extra === 'tablilla' && lado === 'b' ? solido(8, -4, 2, 16, 20, 74, '#E8E4D8') : '')));
            const cara = '<div class="tq-hombre-cara"><i class="ceja i"></i><i class="ceja d"></i><i class="ojo i"></i><i class="ojo d"></i>'
                + '<i class="mejilla i"></i><i class="mejilla d"></i><i class="boca"></i></div>';

            let torso = solido(-10, -16, 20, 32, 22, 92, u.chaleco, true) + solido(-11, -19, 22, 38, 28, 114, u.chaleco, true)
                + solido(-10.5, -16.5, 21, 33, 3, 92, 'var(--tq-hombre-cinturon)') + solido(-4, -4, 8, 8, 9, 142, piel) + brazo('a') + brazo('b');
            if (u.extra === 'corbata') torso += solido(11, -2.5, 1.5, 5, 30, 106, '#8C7BFF') + solido(10.6, -8, 1.2, 16, 8, 134, '#ECE8F4');
            if (u.extra === 'bolso') torso += solido(-13, 19, 20, 7, 22, 96, '#5A3A22') + solido(-1, -19, 2, 38, 2, 138, '#5A3A22');
            if (u.extra === 'binoculares') torso += solido(11, -7, 6, 14, 8, 122, '#222') + solido(-1, -19, 2, 38, 1.5, 140, '#3A2A1A');

            // Cabeza: cara, nariz, orejas y pelo.
            let cabeza = solido(-11, -10, 22, 20, 26, 150, piel, true, cara) + solido(10.5, -2, 4, 4, 7, 156, pielOscura, true)
                + solido(-3, -11.5, 6, 2, 8, 157, pielOscura) + solido(-3, 9.5, 6, 2, 8, 157, pielOscura)
                + solido(-12, -11, 24, 22, 6, 174, pelo, true) + solido(-12, -11, 8, 22, 20, 156, pelo) + solido(7, -11, 5, 22, 5, 170, pelo)
                + solido(-6, -11, 6, 2, 10, 164, pelo) + solido(-6, 9, 6, 2, 10, 164, pelo);
            const c = u.colorSombrero;
            let sombrero = '';
            if (u.sombrero === 'ala') sombrero = solido(-21, -20, 42, 40, 3, 176, c) + solido(-12, -11, 24, 22, 11, 178, c);
            if (u.sombrero === 'casco') sombrero = solido(-13, -12, 26, 24, 11, 173, c, true) + solido(12, -12, 7, 24, 3, 173, c);
            if (u.sombrero === 'gorra') sombrero = solido(-12, -11, 24, 22, 8, 175, c, true) + solido(11, -9, 13, 18, 2.5, 175, c);
            if (sombrero) cabeza += `<div class="tq-hombre-piv tq-hombre-sombrero">${sombrero}</div>`;
            torso += `<div class="tq-hombre-piv tq-hombre-cabeza" style="transform-origin: 0 0 150px">${cabeza}</div>`;

            return '<div class="tq-hombre-sombra"></div>' + pierna('a') + pierna('b') + solido(-11, -17, 22, 34, 12, 80, u.pantalon, true)
                + `<div class="tq-hombre-piv tq-hombre-torso" style="transform-origin: 0 0 92px">${torso}</div>`;
        };

        // ——— Dibujar (solo transforms) ———
        const pintarHombre = () => {
            h.el.style.transform = `translate3d(${h.x.toFixed(1)}px, ${h.y.toFixed(1)}px, 0) rotateZ(${h.cara.toFixed(1)}deg) scale3d(.5, .5, .5) translateZ(${h.dormido ? -82 : 0}px)`;
        };

        const vestir = (zona, conDestello = true) => {
            if (h.uniforme === zona) return;
            h.uniforme = zona;
            h.el.innerHTML = cuerpo(UNIFORMES[zona] ?? UNIFORMES[0]);
            if (! conDestello || sinMovimiento()) return;
            h.destello.style.left = `${h.x}px`;
            h.destello.style.top = `${h.y}px`;
            h.destello.classList.remove('on');
            void h.destello.offsetWidth;
            h.destello.classList.add('on');
            clearTimeout(h.relojDestello);
            h.relojDestello = setTimeout(() => h.destello.classList.remove('on'), 520);
        };

        const ponerZzz = () => {
            const a = h.cara * Math.PI / 180;
            h.zzz.style.left = `${h.x - Math.cos(a) * 34}px`;
            h.zzz.style.top = `${h.y - Math.sin(a) * 34}px`;
            h.zzz.hidden = ! h.dormido;
        };

        // ——— Las cajas: su lugar viene del estilo en línea que dibujó el servidor; el desorden, de `h.cajas` ———
        const datosDeCaja = (el) => {
            const sitio = el.closest('[data-sitio]');
            const desplazo = el.closest('[data-desplazo]');
            if (! sitio || ! desplazo) return null;

            return { el, desplazo, id: el.dataset.caja, lugar: el.dataset.lugar, x0: parseFloat(sitio.style.left), y0: parseFloat(sitio.style.top),
                ancho: parseFloat(el.style.width) || 170, fondo: parseFloat(el.style.height) || 120 };
        };
        const cajas = (lugar = null) => [...h.visor.querySelectorAll('[data-capa="cajas"] [data-caja]')]
            .filter((el) => lugar === null || el.dataset.lugar === lugar)
            .map(datosDeCaja)
            .filter((c) => c !== null && config.lugares.includes(c.lugar));
        // La caja que la faja tiene en la mano (arrastre, diálogo o camino al escáner) no se toca.
        const enUso = (id) => String(h.faja.ocupada?.id ?? '') === String(id) || String(h.faja.arrastre?.caja?.id ?? '') === String(id);
        const desordenada = (c) => ! h.cajas[c.id]?.ordenada;

        const desordenar = (c) => {
            const k = c.ancho / 170;
            h.cajas[c.id] = { lugar: c.lugar, dx: azar(-36, 36) * k, dy: azar(-24, 24) * k, giro: azar(-18, 18) };

            return h.cajas[c.id];
        };

        // `conDatos`: deja el desorden en `data-*` para que la faja lo lea al tomar la caja (`tomarCaja`).
        const pintarCaja = (c, s, conDatos = true) => {
            if (s.ordenada) {
                c.desplazo.style.transform = '';
                delete c.desplazo.dataset.dx;
                delete c.desplazo.dataset.dy;
                delete c.desplazo.dataset.giro;

                return;
            }
            c.desplazo.style.transform = `translate3d(${s.dx.toFixed(1)}px, ${s.dy.toFixed(1)}px, 0) rotateZ(${s.giro.toFixed(1)}deg)`;
            if (! conDatos) return;
            c.desplazo.dataset.dx = s.dx.toFixed(1);
            c.desplazo.dataset.dy = s.dy.toFixed(1);
            c.desplazo.dataset.giro = s.giro.toFixed(1);
        };

        // Pone cada caja como la dejó la sesión. Una caja nueva o que cambió de zona llega desordenada; la primera vez, todas.
        const aplicar = () => {
            const vistas = new Set();
            for (const c of cajas()) {
                vistas.add(c.id);
                if (enUso(c.id)) continue;
                let s = h.cajas[c.id];
                if (! s || s.lugar !== c.lugar) s = desordenar(c);
                pintarCaja(c, s);
            }
            for (const id of Object.keys(h.cajas)) if (! vistas.has(id)) delete h.cajas[id];
            guardar();
        };

        // ——— Animaciones que se pueden cortar: cada secuencia tiene su turno; `interrumpir` pasa al siguiente ———
        const animar = (duracion, cuadro, turno, conSuavizado = false) => new Promise((listo) => {
            if (turno !== h.turno) { listo(false); return }
            if (sinMovimiento() || document.hidden || duracion <= 0) { cuadro(1); listo(true); return }
            const inicio = performance.now();
            h.finAnimar = listo;
            const paso = (ahora) => {
                if (turno !== h.turno) return;
                const k = Math.min(1, (ahora - inicio) / duracion);
                cuadro(conSuavizado ? suave(k) : k);
                if (k < 1) { h.raf = requestAnimationFrame(paso); return }
                h.finAnimar = null;
                listo(true);
            };
            h.raf = requestAnimationFrame(paso);
        });

        const esperar = (ms, turno) => new Promise((listo) => {
            if (turno !== h.turno) { listo(false); return }
            if (sinMovimiento() || document.hidden) { listo(true); return }
            h.finEspera = listo;
            h.reloj = setTimeout(() => { h.finEspera = null; listo(true) }, ms);
        });

        const interrumpir = () => {
            h.turno++;
            cancelAnimationFrame(h.raf);
            clearTimeout(h.reloj);
            const finAnimar = h.finAnimar;
            const finEspera = h.finEspera;
            h.finAnimar = null;
            h.finEspera = null;
            if (finAnimar) finAnimar(false);
            if (finEspera) finEspera(false);
            // La caja que estaba ordenando queda donde iba, con su desorden de ahora.
            if (h.empujando) pintarCaja(h.empujando.c, h.empujando.s);
            h.empujando = null;
            h.el?.classList.remove('camina', 'corre', 'empuja');
            h.ocupado = false;
            guardar();
        };

        const correr = (secuencia) => {
            h.ocupado = true;
            const turno = ++h.turno;

            return secuencia(turno).catch(() => false).finally(() => {
                if (turno !== h.turno) return;
                h.ocupado = false;
                h.el.classList.remove('camina', 'corre', 'empuja');
                guardar();
                revisarLuego();
            });
        };

        // ——— Lo que hace ———
        // Camina (o corre, si es lejos) en línea recta; `alMover(dx, dy)` arrastra algo con él (la caja que empuja).
        const caminar = async (x, y, turno, velocidad = 1, alMover = null) => {
            const ax = h.x;
            const ay = h.y;
            const distancia = Math.hypot(x - ax, y - ay);
            if (distancia < 2) return turno === h.turno;
            const corre = distancia > 520;
            h.cara = grados(y - ay, x - ax);
            h.el.classList.add('camina');
            h.el.classList.toggle('corre', corre);
            const ok = await animar(Math.max(140, distancia / (0.55 * velocidad * (corre ? 2.6 : 1))), (k) => {
                h.x = ax + (x - ax) * k;
                h.y = ay + (y - ay) * k;
                pintarHombre();
                if (alMover) alMover((x - ax) * k, (y - ay) * k);
            }, turno);
            if (turno === h.turno) h.el.classList.remove('camina', 'corre');

            return ok;
        };

        const despertar = (turno) => {
            if (! h.dormido) return Promise.resolve(turno === h.turno);
            h.dormido = false;
            h.el.classList.remove('duerme');
            pintarHombre();
            ponerZzz();

            return esperar(450, turno);
        };

        // Hasta la zona por el carril de atrás (no cruza las láminas de los escáneres); se cambia de ropa al llegar.
        const irAZona = async (zona, turno) => {
            if (h.zona !== zona) {
                if (! await caminar(h.x, CARRIL, turno, 1.6)) return false;
                if (! await caminar(zona * ANCHO + (zona > h.zona ? 60 : 520), CARRIL, turno, 1.8)) return false;
                h.zona = zona;
            }
            vestir(zona);

            return true;
        };

        // Cada caja desordenada, a su lugar: se pone detrás de ella y la empuja (prototipo, `orderStage`).
        const ordenar = async (zona, turno) => {
            for (const c of cajas(config.lugares[zona])) {
                if (! desordenada(c) || enUso(c.id)) continue;
                const s = h.cajas[c.id] ?? desordenar(c);
                const largo = Math.hypot(s.dx, s.dy);
                if (largo >= 1) {
                    const ux = -s.dx / largo;
                    const uy = -s.dy / largo;
                    const detras = Math.abs(ux) * c.ancho / 2 + Math.abs(uy) * c.fondo / 2 + 20;
                    const cx = c.x0 + s.dx;
                    const cy = c.y0 + s.dy;
                    if (! await caminar(cx - ux * detras, cy - uy * detras, turno, 1.9)) return false;
                    if (enUso(c.id)) continue;
                    h.cara = grados(uy, ux);
                    h.el.classList.add('empuja', 'camina');
                    h.empujando = { c, s };
                    const antes = { dx: s.dx, dy: s.dy, giro: s.giro, x: h.x, y: h.y };
                    const ok = await animar(280, (k) => {
                        s.dx = antes.dx * (1 - k);
                        s.dy = antes.dy * (1 - k);
                        s.giro = antes.giro * (1 - k);
                        h.x = antes.x - antes.dx * k;
                        h.y = antes.y - antes.dy * k;
                        pintarCaja(c, s, false);
                        pintarHombre();
                    }, turno, true);
                    if (! ok) return false;
                    h.empujando = null;
                    h.el.classList.remove('empuja', 'camina');
                }
                h.cajas[c.id] = { lugar: c.lugar, ordenada: true };
                pintarCaja(c, h.cajas[c.id]);
            }

            return turno === h.turno;
        };

        // ¿Hay piso libre aquí, dentro de la zona? (prototipo, `freeAt`).
        const libre = (x, y, zona, obstaculos) => {
            if (y < 72 || y > 372 || x < zona * ANCHO + 20 || x > (zona === ULTIMA ? config.fin - 70 : escaner(zona) - 12)) return false;

            return ! obstaculos.some((o) => Math.abs(x - o.x) < o.ancho / 2 + 8 && Math.abs(y - o.y) < o.fondo / 2 + 8);
        };

        // Dónde recostarse: de espaldas al costado de una caja o a la lámina de un escáner (prototipo, `wallSpots`).
        const sitiosParaDormir = (zona) => {
            const obstaculos = cajas(config.lugares[zona]).map((c) => {
                const s = h.cajas[c.id];

                return { x: c.x0 + (s && ! s.ordenada ? s.dx : 0), y: c.y0 + (s && ! s.ordenada ? s.dy : 0), ancho: c.ancho, fondo: c.fondo };
            });
            h.visor.querySelectorAll('[data-capa="cajas"] [data-pila]').forEach((el) => {
                if (el.dataset.pila !== config.lugares[zona]) return;
                const sitio = el.parentElement;
                obstaculos.push({ x: parseFloat(sitio.style.left), y: parseFloat(sitio.style.top), ancho: parseFloat(el.style.width), fondo: parseFloat(el.style.height) });
            });
            const sitios = [];
            const D = 38;
            const probar = (x, y, cara) => {
                const a = cara * Math.PI / 180;
                for (let k = 0; k <= 72; k += 12) if (! libre(x + Math.cos(a) * k, y + Math.sin(a) * k, zona, obstaculos)) return;
                if (libre(x - Math.cos(a) * 20, y - Math.sin(a) * 20, zona, obstaculos)) sitios.push([x, y, cara]);
            };
            obstaculos.forEach((o) => {
                for (const dy of [-30, 0, 30]) { probar(o.x - o.ancho / 2 - D, o.y + dy, 180); probar(o.x + o.ancho / 2 + D, o.y + dy, 0) }
                for (const dx of [-50, 0, 50]) probar(o.x + dx, o.y + o.fondo / 2 + D, 90);
            });
            for (const y of [120, 180, 240, 300]) probar(escaner(zona) - D, y, 180);
            if (zona > 0) for (const y of [120, 180, 240, 300]) probar(escaner(zona - 1) + D, y, 0);

            return sitios;
        };

        // Las piernas varían cada vez: estiradas, una rodilla arriba o los tobillos cruzados.
        const elegirPose = () => {
            const v = (a, b) => Math.round(azar(a, b));
            const pose = { '--tq-recuesta': v(-74, -64), '--tq-cabecea': v(16, 30), '--tq-brazo-b': v(-34, -12), '--tq-antebrazo-b': v(-24, -4),
                '--tq-ladea': (Math.random() < 0.5 ? -1 : 1) * v(8, 20) };
            const piernas = [
                { '--tq-muslo-a': v(-92, -86), '--tq-muslo-b': v(-92, -86), '--tq-rodilla-a': v(0, 8), '--tq-rodilla-b': v(0, 6), '--tq-abre-a': v(-8, -2), '--tq-abre-b': v(4, 10) },
                { '--tq-muslo-a': v(-132, -120), '--tq-rodilla-a': v(96, 110), '--tq-muslo-b': v(-92, -86), '--tq-rodilla-b': v(0, 6), '--tq-abre-a': v(-6, 0), '--tq-abre-b': v(4, 10) },
                { '--tq-muslo-a': v(-92, -86), '--tq-muslo-b': v(-90, -84), '--tq-rodilla-a': v(0, 6), '--tq-rodilla-b': v(0, 4), '--tq-abre-a': v(2, 6), '--tq-abre-b': v(-12, -8) },
            ];

            return { ...pose, ...piernas[Math.floor(Math.random() * piernas.length)] };
        };

        const acostarse = (cara, pose) => {
            h.cara = cara;
            h.pose = pose;
            h.dormido = true;
            Object.entries(pose).forEach(([variable, valor]) => h.el.style.setProperty(variable, `${valor}deg`));
            h.el.classList.add('duerme');
            pintarHombre();
            ponerZzz();
        };

        const sitioParaDormir = () => {
            const sitios = sitiosParaDormir(h.zona);

            return sitios.length ? sitios[Math.floor(Math.random() * sitios.length)] : [h.zona * ANCHO + 60, 340, 0];
        };

        const dormir = async (turno) => {
            const [x, y, cara] = sitioParaDormir();
            if (! await caminar(x, y, turno, 1.4)) return false;
            acostarse(cara, elegirPose());

            return true;
        };

        // Lo que la faja necesita quieto: sin sala, sin caja en la mano, sin despiece y con la pestaña a la vista.
        const puede = () => h.faja.sala === null && h.faja.ocupada === null && ! h.faja.arrastre?.caja
            && ! h.visor.querySelector('[data-despiece][data-abierto]') && ! document.hidden && ! h.faja.pausada;

        // Si la zona que se mira tiene cajas desordenadas, va enseguida; si está ordenada, no va (prototipo, `checkMess`).
        const revisar = () => {
            if (h.ocupado || ! puede()) return;
            const zona = h.faja.zonaAqui;
            const hayDesorden = zona !== null && zona !== undefined && cajas(config.lugares[zona]).some((c) => desordenada(c) && ! enUso(c.id));
            if (hayDesorden) {
                correr(async (turno) => (await despertar(turno)) && (await irAZona(zona, turno)) && (await ordenar(zona, turno)) && dormir(turno));
            } else if (! h.dormido) {
                correr((turno) => dormir(turno));
            }
        };
        const revisarLuego = (ms = 400) => {
            clearTimeout(h.relojRevisar);
            h.relojRevisar = setTimeout(revisar, ms);
        };

        // El empuje al escáner es la animación de la acción real (D11): la faja espera esta promesa.
        const empujar = async (detalle, turno) => {
            const el = h.visor.querySelector(`[data-caja="${detalle.id}"]`);
            const ancho = el ? parseFloat(el.style.width) || 170 : 170;
            const zona = Math.max(0, Math.min(ULTIMA, Math.floor(detalle.desde.x / ANCHO)));
            const detras = ancho / 2 + 20;
            if (! await despertar(turno) || ! await irAZona(zona, turno)) return;
            if (! await caminar(detalle.desde.x - detras, detalle.desde.y, turno, 1.9)) return;
            h.el.classList.add('empuja');
            await caminar(detalle.hasta.x - detras, detalle.hasta.y, turno, 1.2, (dx, dy) => detalle.mover(detalle.desde.x + dx, detalle.desde.y + dy));
            h.el.classList.remove('empuja');
        };

        return {
            init() {
                h.visor = this.$el.closest('[data-faja]');
                h.faja = window.Alpine?.$data(h.visor) ?? this.$data;
                h.el = this.$el.querySelector('[data-hombre]');
                h.zzz = this.$el.querySelector('[data-hombre-zzz]');
                h.destello = this.$el.querySelector('[data-hombre-destello]');
                if (! h.visor || ! h.el) return;

                const guardado = leer();
                h.cajas = guardado && typeof guardado.cajas === 'object' && guardado.cajas !== null ? guardado.cajas : {};
                const hombre = guardado?.hombre;
                if (hombre && Number.isFinite(hombre.x) && Number.isFinite(hombre.y)) {
                    h.x = hombre.x;
                    h.y = hombre.y;
                    h.zona = Math.max(0, Math.min(ULTIMA, Number(hombre.zona) || 0));
                }
                vestir(h.zona, false);
                aplicar();
                // Aparece dormido: donde lo dejó la sesión, o junto a algo en la primera zona.
                if (hombre && Number.isFinite(hombre.cara) && hombre.pose && typeof hombre.pose === 'object') {
                    acostarse(hombre.cara, hombre.pose);
                } else {
                    const [x, y, cara] = sitioParaDormir();
                    h.x = x;
                    h.y = y;
                    acostarse(cara, elegirPose());
                }
                guardar();

                const escuchar = (destino, nombre, oyente, opciones) => {
                    destino.addEventListener(nombre, oyente, opciones);
                    h.quitar.push(() => destino.removeEventListener(nombre, oyente, opciones));
                };
                escuchar(h.visor, 'tq-faja-quieta', () => revisar());
                escuchar(h.visor, 'tq-faja-empujar', (evento) => {
                    // Con «reducir animaciones», la caja va sola y al instante; él sigue durmiendo.
                    // Sin la caja dibujada (en una pila «+N más»), tampoco: no hay qué empujar a la vista.
                    if (sinMovimiento() || document.hidden || ! h.visor.querySelector(`[data-capa="cajas"] [data-caja="${evento.detail.id}"]`)) return;
                    evento.preventDefault();
                    interrumpir();
                    evento.detail.esperas.push(correr((turno) => empujar(evento.detail, turno)));
                });
                // Soltar una caja en su misma zona: queda donde cayó, desordenada, y él viene a ordenarla.
                escuchar(h.visor, 'tq-faja-desordenar', (evento) => {
                    const el = h.visor.querySelector(`[data-caja="${evento.detail.id}"]`);
                    const c = el ? datosDeCaja(el) : null;
                    if (! c) return;
                    evento.preventDefault();
                    h.cajas[c.id] = { lugar: c.lugar, dx: Number(evento.detail.dx) || 0, dy: Number(evento.detail.dy) || 0, giro: Number(evento.detail.giro) || 0 };
                    pintarCaja(c, h.cajas[c.id]);
                    guardar();
                    revisarLuego();
                });
                // Tocar una caja ordenada la desordena (el despiece se abre encima).
                escuchar(h.visor, 'tq-faja-tocar', (evento) => {
                    const el = h.visor.querySelector(`[data-caja="${evento.detail.id}"]`);
                    const c = el ? datosDeCaja(el) : null;
                    if (! c || ! config.lugares.includes(c.lugar) || desordenada(c) || enUso(c.id)) return;
                    pintarCaja(c, desordenar(c));
                    guardar();
                });
                // La caja volvió a su lugar (soltar en otra zona sin pasar, diálogo cancelado o acción rechazada).
                escuchar(h.visor, 'tq-faja-devuelta', (evento) => {
                    const el = h.visor.querySelector(`[data-caja="${evento.detail.id}"]`);
                    if (el && config.lugares.includes(el.dataset.lugar)) h.cajas[el.dataset.caja] = { lugar: el.dataset.lugar, ordenada: true };
                    guardar();
                    revisarLuego();
                });
                escuchar(h.visor, 'tq-faja-redibujada', () => { aplicar(); revisarLuego() });
                escuchar(window, 'tq-faja-despiece-cerrado', () => revisarLuego());
                // Una caja tomada con el puntero lo detiene (no se empuja lo que se arrastra).
                escuchar(h.visor, 'pointerdown', (evento) => {
                    if (h.ocupado && h.faja.ocupada === null && evento.target instanceof Element && evento.target.closest('[data-caja]')) interrumpir();
                }, true);
                // Con la pestaña oculta se detiene; al volver, revisa.
                escuchar(document, 'visibilitychange', () => { if (document.hidden) { if (h.ocupado) interrumpir() } else revisarLuego() });
                // Paso 8: con la faja fuera de la vista (la página desplazada), igual: se corta (un empuje al escáner cortado
                // deja la caja en el escáner, como con la pestaña oculta) y al volver revisa.
                escuchar(h.visor, 'tq-faja-pausa', (evento) => {
                    if (! evento.detail?.pausada) revisarLuego();
                    else if (h.ocupado) interrumpir();
                });
            },

            destroy() {
                interrumpir();
                clearTimeout(h.relojRevisar);
                clearTimeout(h.relojDestello);
                h.quitar.forEach((quitar) => quitar());
                h.quitar = [];
            },
        };
    };
</script>
