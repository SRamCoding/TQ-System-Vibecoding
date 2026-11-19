{{--
    La faja de vidrio (plan 2.21b, paso 3): el mundo en CSS 3D, la cámara, las cuatro zonas con sus luces, rótulos,
    viñetas y escáneres, y las cajas. Recibe de `FajaDeVidrio::datos()`: `$zonas`, `$colores`, `$mundo`, `$urlTabla` (la Lista) y `$urlTablero`.
    Comportamiento y medidas del prototipo aprobado (`docs/diseno/fuentes/prototipos/faja-vidrio-oportunidades.html`).
    Huecos marcados para los pasos que siguen: arrastre y acciones (4), tubería, canaleta y salas (5), despiece (6) y el
    hombrecito (7). Paso 4: arrastrar una caja ejecuta la acción real (`FajaOportunidades::abrirMovimiento` y
    `moverOportunidad`; el arrastre, en `faja/camara.blade.php`). Paso 5: la boca de la tubería con su brida y la cortina de
    PVC, la canaleta, el terminal de ganadas, el letrero de perdidas, un terminal por zona y las salas de ganadas y
    perdidas (`$salas`), cada una en su nivel del mundo; las tablas se abren con `verLugar` (`TablaDeLaFaja`). Paso 6:
    el despiece de la caja (`DespieceDeOportunidad`, vista `faja/despiece`) cubre el visor. El mundo es oscuro en los dos
    temas (D13); los controles, los diálogos y el panel de lectura siguen el tema.
--}}
@use('App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\FajaOportunidades')
@use('App\Modules\Crm\Oportunidades\Filament\DespieceDeOportunidad')
@php
    $anchoZona = $mundo['anchoZona'];
    $fin = $mundo['fin'];
    $config = [
        'anchoZona' => $anchoZona,
        'fin' => $fin,
        'urlTabla' => $urlTabla,
        'centros' => array_map(fn (array $zona): int => $zona['indice'] * $anchoZona + 290, $zonas),
        // Paso 4: el orden de las zonas, los destinos fuera de ellas y las líneas de saltar y retroceder (sin llamar a nada).
        'lugares' => array_column($zonas, 'lugar'),
        'ganada' => FajaOportunidades::GANADA,
        'perdida' => FajaOportunidades::PERDIDA,
        'textos' => [
            'salto' => FajaOportunidades::NO_SE_SALTA,
            'atras' => FajaOportunidades::NO_SE_RETROCEDE,
        ],
        // Paso 5: dónde está cada sala en el mundo.
        'salas' => array_map(fn (array $sala): array => ['x' => $sala['x'], 'y' => $sala['y']], $salas),
    ];
    $ganadas = $salas['ganadas'];
    $perdidas = $salas['perdidas'];
    // Paso 5, piezas del mundo como el prototipo (`pipe`, `solid`, `stand`): la tubería (radio y altura del eje), los
    // sólidos con sus caras y las láminas de pie. Solo números y clases fijas: nada que escapar.
    $radio = 165;
    $eje = 172;
    $tubo = function (int|float $x0, int|float $largo, int|float $yc, int|float $zc, int|float $r, string $clase): string {
        $caras = 22;
        $ancho = round(2 * $r * sin(M_PI / $caras) + 2, 2);
        $html = '';
        for ($i = 0; $i < $caras; $i++) {
            $giro = round($i * 360 / $caras, 3);
            $luz = number_format(0.5 + 0.5 * cos(deg2rad($giro)), 2, '.', '');
            $html .= '<div class="tq-faja-abs '.$clase.'" style="left: '.$x0.'px; top: '.($yc - $ancho / 2).'px; width: '.$largo.'px; height: '.$ancho.'px; transform: translateZ('.$zc.'px) rotateX('.$giro.'deg) translateZ('.$r.'px); --tq-l: '.$luz.'"></div>';
        }

        return $html;
    };
    $solido = function (int|float $x, int|float $y, int|float $w, int|float $d, int|float $h, string $color, string $clase = '', int|float $z = 0): string {
        return '<div class="tq-faja-cubo tq-faja-solido '.$clase.'" style="left: '.$x.'px; top: '.$y.'px; width: '.$w.'px; height: '.$d.'px; --tq-z: '.$z.'px; --tq-c: '.$color.'">'
            .'<div class="tq-cara tq-cara-arriba" style="width: '.$w.'px; height: '.$d.'px; transform: translateZ('.$h.'px)"></div>'
            .'<div class="tq-cara tq-cara-frente" style="top: '.($d - $h).'px; width: '.$w.'px; height: '.$h.'px"></div>'
            .'<div class="tq-cara tq-cara-atras" style="top: '.(-$h).'px; width: '.$w.'px; height: '.$h.'px"></div>'
            .'<div class="tq-cara tq-cara-izq" style="left: '.(-$h).'px; width: '.$h.'px; height: '.$d.'px"></div>'
            .'<div class="tq-cara tq-cara-der" style="left: '.$w.'px; width: '.$h.'px; height: '.$d.'px"></div></div>';
    };
    $pared = function (int|float $x, int|float $y, int|float $giro, int|float $w, int|float $h, string $clase, string $dentro = '', int|float $z = 0): string {
        $alzada = $z !== 0 ? '; transform: translateZ('.$z.'px) rotateX(-90deg)' : '';

        return '<div class="tq-faja-abs" style="left: '.$x.'px; top: '.$y.'px; transform: rotateZ('.$giro.'deg)"><div class="tq-faja-pie '.$clase.'" style="left: '.(-$w / 2).'px; top: '.(-$h).'px; width: '.$w.'px; height: '.$h.'px'.$alzada.'">'.$dentro.'</div></div>';
    };
    // La cortina: 10 tiras que llenan el círculo de la boca, colgadas de su borde de arriba (prototipo, `curtainStrips`).
    $tiras = [];
    $anchoTira = 2 * $radio / 10;
    for ($i = 0; $i < 10; $i++) {
        $u = -$radio + $anchoTira * ($i + 0.5);
        $cuerda = sqrt($radio * $radio - $u * $u) - 4;
        $tiras[] = ['left' => round($radio + $u - $anchoTira / 2 - 2, 2), 'top' => round($radio - $cuerda, 2), 'ancho' => $anchoTira + 4, 'alto' => round(2 * $cuerda, 2)];
    }
@endphp
<div class="tq-faja">
    <style>
        /* Colores, una sola vez (D13 de la 2.21b; D10 de la 2.21c): los del mundo 3D, iguales en los dos temas (oscuro
           siempre); los de todo lo demás (controles, ayudas, avisos, etiquetas de lugar, barra de etapas, marco y panel del
           despiece), con las variables del panel según el tema que la persona eligió en Filament. */
        .tq-faja {
            @foreach ($colores as $variable => $valor)
                {{ $variable }}: {{ $valor }};
            @endforeach
            --tq-faja-ui-fondo: #fff;
            --tq-faja-ui-marco: var(--gray-50);
            --tq-faja-ui-tinta: var(--gray-950);
            --tq-faja-ui-suave: var(--gray-500);
            --tq-faja-ui-linea: var(--gray-200);
            --tq-faja-ui-acento: var(--morado-600);
            --tq-faja-ui-sobre-acento: #fff;
            --tq-faja-ui-verde: var(--success-600);
            --tq-faja-ui-rojo: var(--danger-600);
            --tq-faja-ui-ambar: var(--warning-700);
            --tq-faja-ui-sombra: 0 1px 2px rgb(9 9 11 / 0.05), 0 4px 12px rgb(9 9 11 / 0.08);
        }
        .dark .tq-faja {
            --tq-faja-ui-fondo: var(--gray-900);
            --tq-faja-ui-marco: var(--gray-950);
            --tq-faja-ui-tinta: #fff;
            --tq-faja-ui-suave: var(--gray-400);
            --tq-faja-ui-linea: rgb(255 255 255 / 0.10);
            --tq-faja-ui-acento: var(--morado-400);
            --tq-faja-ui-sobre-acento: var(--gray-950);
            --tq-faja-ui-verde: var(--success-400);
            --tq-faja-ui-rojo: var(--danger-400);
            --tq-faja-ui-ambar: var(--warning-400);
            --tq-faja-ui-sombra: 0 4px 12px rgb(0 0 0 / 0.35);
        }
        /* El mundo 3D es oscuro en los dos temas: sus piezas usan solo los colores del mundo. */
        .tq-faja-mundo, .tq-despiece-escena { color-scheme: dark; }
        .tq-faja [x-cloak] { display: none !important; }

        /* El aviso del celular (D12 de la 2.21c): el Tablero es la vista por defecto; la faja queda como opción. */
        .tq-faja-celular { display: none; margin-bottom: 0.75rem; font-size: 0.875rem; color: var(--tq-faja-ui-suave); }
        .tq-faja-celular a { color: var(--tq-faja-ui-acento); font-weight: 600; text-decoration: underline; }
        @media (max-width: 767px) { .tq-faja-celular { display: block; } }

        /* El visor */
        .tq-faja-visor { position: relative; height: calc(100dvh - 13rem); min-height: 460px; border-radius: 16px; overflow: hidden;
            border: 1px solid var(--tq-faja-ui-linea); background: radial-gradient(ellipse at 50% 115%, var(--tq-faja-cielo), var(--tq-faja-noche) 62%);
            perspective: 1200px; touch-action: none; user-select: none; -webkit-user-select: none; cursor: grab; outline: none; }
        .tq-faja-visor.arrastrando { cursor: grabbing; }
        .tq-faja-visor:focus-visible { outline: 2px solid var(--tq-faja-ui-acento); outline-offset: 2px; }
        .tq-faja-mundo { position: absolute; left: 50%; top: 50%; width: 0; height: 0; transform-origin: 0 0; }
        .tq-faja-mundo, .tq-faja-mundo * { transform-style: preserve-3d; }
        .tq-faja-abs { position: absolute; }

        /* El piso, la faja y la canaleta (la canaleta es solo imagen hasta el paso 5) */
        .tq-faja-piso { background: linear-gradient(rgba(var(--tq-faja-brillo), .09) 1px, transparent 1px) 0 0 / 50px 50px,
            linear-gradient(90deg, rgba(var(--tq-faja-brillo), .09) 1px, transparent 1px) 0 0 / 50px 50px, var(--tq-faja-noche); }
        /* Paso 8: la banda corre con un transform (lo mueve la tarjeta de video), no con background-position (repintaba
           toda la banda en cada cuadro). Las líneas van en un ::before más ancho que se desliza un periodo de 28 px × 10. */
        .tq-faja-banda { overflow: hidden; background: var(--tq-faja-banda);
            border-top: 4px solid var(--tq-faja-borde); border-bottom: 4px solid var(--tq-faja-borde); }
        .tq-faja-banda::before { content: ""; position: absolute; top: 0; bottom: 0; left: -280px; right: 0;
            background: repeating-linear-gradient(90deg, var(--tq-faja-banda) 0 26px, var(--tq-faja-banda-linea) 26px 28px); animation: tq-faja-banda 10s linear infinite; }
        @keyframes tq-faja-banda { to { transform: translateX(280px); } }
        .tq-faja-canaleta { background: linear-gradient(#000, #14110E); }
        .tq-faja-canaleta-pared { background: linear-gradient(180deg, #2A2620, #050404); }
        .tq-faja-luz { border-radius: 40px; pointer-events: none;
            background: radial-gradient(ellipse at 50% 55%, rgba(var(--tq-faja-brillo), var(--tq-faja-alfa)), transparent 70%); }
        .tq-faja-rotulo { font-weight: 800; font-size: 36px; color: var(--tq-faja-tinta); opacity: .9; white-space: nowrap; pointer-events: none; }
        .tq-faja-rotulo span { font-family: ui-monospace, Consolas, monospace; font-weight: 500; font-size: 20px; color: var(--tq-faja-tinta-suave); margin-left: 10px; }

        /* Una lámina de pie (escáner, tope del final): un plano vertical sobre el piso */
        .tq-faja-pie { position: absolute; transform-origin: 50% 100%; transform: rotateX(-90deg); }
        .tq-faja-poste { background: var(--tq-faja-borde); }
        .tq-faja-escaner .tq-faja-lamina { overflow: hidden; transition: background .25s;
            background: linear-gradient(0deg, rgba(var(--tq-faja-cafe), .62), rgba(var(--tq-faja-cafe), .10));
            border-left: 2px solid rgba(240, 190, 120, .8); border-right: 2px solid rgba(240, 190, 120, .8); }
        /* Paso 8: el barrido baja con un transform (antes `top`, que recalculaba y repintaba en cada cuadro) y su brillo es
           un degradado (antes una sombra con desenfoque). La raya va al centro de una franja de 24 px. */
        .tq-faja-escaner .tq-faja-lamina::after { content: ""; position: absolute; left: 0; right: 0; top: -12px; height: 100%;
            background: linear-gradient(180deg, transparent, rgba(255, 200, 120, .4) 7px, rgba(255, 220, 170, .9) 10.5px 13.5px, rgba(255, 200, 120, .4) 17px, transparent) 0 0 / 100% 24px no-repeat;
            animation: tq-faja-barrido 2.4s ease-in-out infinite; }
        @keyframes tq-faja-barrido { 0%, 100% { transform: translateY(10%); } 50% { transform: translateY(88%); } }
        /* Paso 4: al apuntar (aim), al pasar (ok) y al rechazar (no). */
        .tq-faja-escaner.aim .tq-faja-lamina { background: linear-gradient(0deg, rgba(255, 200, 120, .85), rgba(255, 200, 120, .2)); }
        .tq-faja-escaner.ok .tq-faja-lamina { background: linear-gradient(0deg, rgba(60, 210, 120, .75), rgba(60, 210, 120, .15)); animation: tq-faja-vibra .09s linear 7; }
        .tq-faja-escaner.no .tq-faja-lamina { background: linear-gradient(0deg, rgba(240, 80, 70, .8), rgba(240, 80, 70, .18)); animation: tq-faja-vibra .07s linear 10; }
        @keyframes tq-faja-vibra { 25% { translate: -7px 0; } 75% { translate: 7px 0; } }
        .tq-faja-fin { background: radial-gradient(circle, #000 30%, #15171A 70%, var(--tq-faja-acero)); border-radius: 50%; }

        /* Paso 5: la tubería de acero (22 caras alrededor del eje), sus bridas, sus soportes y la cortina de PVC */
        .tq-faja-tubo { transform-origin: 0 50%; background: linear-gradient(90deg, rgba(0, 0, 0, .35), transparent 6%),
            repeating-linear-gradient(90deg, transparent 0 170px, rgba(0, 0, 0, .28) 170px 176px, rgba(255, 255, 255, .18) 176px 178px),
            linear-gradient(0deg, color-mix(in oklab, var(--tq-faja-acero-medio) calc(var(--tq-l) * 100%), var(--tq-faja-acero)), color-mix(in oklab, var(--tq-faja-acero-claro) calc(var(--tq-l) * 100%), var(--tq-faja-acero))); }
        .tq-faja-brida { transform-origin: 0 50%; transition: background .2s;
            background: linear-gradient(0deg, color-mix(in oklab, #6E767E calc(var(--tq-l) * 100%), #1E2124), color-mix(in oklab, #A9B0B7 calc(var(--tq-l) * 100%), #1E2124)); }
        .tq-faja-visor.apunta-fin .tq-faja-boca .tq-faja-brida { background: var(--tq-faja-verde); }
        .tq-faja-solido > .tq-cara { background: var(--tq-c); }
        .tq-faja-solido > .tq-cara-arriba { background: color-mix(in oklab, var(--tq-c), #fff 14%); }
        .tq-faja-solido > .tq-cara-izq, .tq-faja-solido > .tq-cara-der { background: color-mix(in oklab, var(--tq-c), #000 12%); }
        .tq-faja-solido > .tq-cara-atras { background: color-mix(in oklab, var(--tq-c), #000 22%); }
        .tq-faja-cortina { overflow: visible; }
        .tq-faja-tira { position: absolute; transform-origin: 50% 0; border-radius: 0 0 3px 3px;
            background: linear-gradient(180deg, transparent 82%, rgba(90, 80, 60, .22)),
                repeating-linear-gradient(90deg, transparent 0 7px, rgba(255, 255, 255, .16) 7px 8px, transparent 8px 13px),
                linear-gradient(90deg, rgba(170, 215, 220, .20), rgba(225, 248, 250, .46) 38%, rgba(255, 255, 255, .62) 46%, rgba(190, 225, 230, .26) 62%, rgba(150, 195, 205, .18));
            border-left: 1px solid rgba(255, 255, 255, .45); border-right: 1px solid rgba(120, 160, 170, .35); }

        /* La canaleta: brillo rojo al apuntarle, labio de acero detrás y franjas de aviso delante */
        .tq-faja-canaleta-brillo { background: linear-gradient(90deg, transparent, rgba(240, 106, 94, .35), transparent); opacity: 0; transition: opacity .2s; }
        .tq-faja-visor.apunta-canaleta .tq-faja-canaleta-brillo { opacity: 1; }
        .tq-faja-labio { background: linear-gradient(#5A6168, var(--tq-faja-acero)); }
        .tq-faja-labio-aviso { background: repeating-linear-gradient(135deg, var(--tq-faja-ambar) 0 14px, #1A1714 14px 28px); opacity: .75; }

        /* Los terminales y el letrero (miran a la cámara; se van en la vista general, donde están las viñetas) */
        .tq-faja-ir { display: block; cursor: pointer; white-space: nowrap; border-radius: 12px; padding: 8px 13px; font-weight: 600; font-size: 16px; color: #fff;
            box-shadow: 0 8px 24px rgba(0, 0, 0, .5); transition: opacity .3s, visibility .3s; outline: none;
            background: linear-gradient(180deg, rgba(var(--tq-faja-brillo), .85), rgba(var(--tq-faja-brillo), .55)); border: 3px solid rgba(220, 215, 255, .8); }
        .tq-faja-ir small { display: block; font-family: ui-monospace, Consolas, monospace; font-size: 12px; opacity: .85; font-weight: 500; }
        .tq-faja-ir.ganadas { background: linear-gradient(180deg, rgb(var(--tq-faja-ganada)), color-mix(in oklab, rgb(var(--tq-faja-ganada)), #000 25%)); border-color: #7BE0A6; }
        .tq-faja-ir.perdidas { background: linear-gradient(180deg, rgb(var(--tq-faja-perdida)), color-mix(in oklab, rgb(var(--tq-faja-perdida)), #000 25%)); border-color: #F2A79F; }
        .tq-faja-ir:hover, .tq-faja-ir:focus-visible { border-color: #fff; }
        .tq-faja-ir:focus-visible { outline: 3px solid var(--tq-faja-tinta); outline-offset: 3px; }
        /* Oculto también para el teclado (`visibility`): Tab no se detiene en lo que no se ve. */
        .tq-faja-visor.lejos .tq-faja-ir { opacity: 0; visibility: hidden; pointer-events: none; }
        .tq-faja-vineta.ganadas b { border-color: var(--tq-faja-verde); }
        .tq-faja-vineta.perdidas b { border-color: var(--tq-faja-rojo); }

        /* Las salas: cada una es un nivel del mundo que solo se ve al entrar */
        .tq-faja-visor.en-sala .tq-faja-nivel-faja, .tq-faja-visor:not(.en-ganadas) [data-capa="ganadas"], .tq-faja-visor:not(.en-perdidas) [data-capa="perdidas"] { display: none; }
        .tq-faja-visor.en-perdidas { background: radial-gradient(ellipse at 50% 40%, #1E1A16, #070605 70%); }
        .tq-faja-concreto { background: radial-gradient(ellipse at 50% 40%, rgba(255, 255, 255, .10), transparent 60%), linear-gradient(var(--tq-faja-concreto), color-mix(in oklab, var(--tq-faja-concreto), #000 15%)); }
        .tq-faja-concreto.oscuro { background: radial-gradient(ellipse at 50% 45%, rgba(255, 220, 160, .16), transparent 45%), linear-gradient(#2E2B27, #1D1B18); }
        .tq-faja-epoxi { border: 6px solid #C9A227; border-radius: 4px; }
        .tq-faja-bloque { background: repeating-linear-gradient(0deg, rgba(0, 0, 0, .28) 0 2px, transparent 2px 38px), repeating-linear-gradient(90deg, rgba(0, 0, 0, .22) 0 2px, transparent 2px 76px), linear-gradient(180deg, #8E897F, #6F6A61); }
        .tq-faja-bloque.oscuro { background: repeating-linear-gradient(0deg, rgba(0, 0, 0, .35) 0 2px, transparent 2px 38px), repeating-linear-gradient(90deg, rgba(0, 0, 0, .3) 0 2px, transparent 2px 76px), linear-gradient(180deg, #4A453E, #2A2620); }
        .tq-faja-letrero { background: linear-gradient(180deg, #2B2F35, #1C1F23); color: #E8ECEF; font-weight: 800; font-size: 30px; letter-spacing: .12em; display: grid; place-items: center;
            border: 4px solid #8D949B; text-shadow: 0 1px 0 #000; white-space: nowrap; }
        .tq-faja-foco { border-radius: 50%; pointer-events: none; background: radial-gradient(circle, #FFF4D6, #FFC56B 40%, transparent 70%); }
        .tq-faja-caja.tq-faja-ganada > .tq-cara { background: linear-gradient(180deg, rgba(79, 194, 138, .30), rgba(79, 194, 138, .07)); border-color: rgba(190, 255, 220, .6); }
        .tq-faja-caja.tq-faja-perdida > .tq-cara { background: linear-gradient(180deg, rgba(240, 106, 94, .30), rgba(240, 106, 94, .06)); border-color: rgba(255, 190, 180, .6); }
        .tq-faja-caja.tq-faja-ganada > .tq-cara-rotulo, .tq-faja-caja.tq-faja-perdida > .tq-cara-rotulo { background: none; border: 0; }

        /* El fundido al entrar o salir de una sala */
        .tq-faja-fundido { position: absolute; inset: 0; z-index: 4; background: #000; opacity: 0; pointer-events: none; transition: opacity .28s; }
        .tq-faja-fundido.on { opacity: 1; }

        /* Las caras de un cubo (como el prototipo): +z hacia arriba, el frente mira a la cámara */
        .tq-faja-cubo { position: absolute; transform: translateZ(var(--tq-z, 0px)) rotateZ(var(--tq-giro, 0deg)) rotateX(var(--tq-tumbo, 0deg)); }
        .tq-faja-cubo > .tq-cara { position: absolute; overflow: hidden; transform-style: flat; }
        .tq-faja-cubo > .tq-cara * { transform-style: flat; }
        .tq-cara-arriba { left: 0; top: 0; }
        .tq-cara-frente { left: 0; transform-origin: 50% 100%; transform: rotateX(-90deg); }
        .tq-cara-atras { left: 0; transform-origin: 50% 100%; transform: rotateX(-90deg) rotateY(180deg); }
        .tq-cara-izq { top: 0; transform-origin: 100% 50%; transform: rotateY(90deg); }
        .tq-cara-der { top: 0; transform-origin: 0 50%; transform: rotateY(-90deg); }

        /* Las cajas de vidrio */
        .tq-faja-caja { cursor: pointer; outline: none; }
        .tq-faja-caja > .tq-cara { background: linear-gradient(180deg, rgba(var(--tq-faja-brillo), .32), rgba(var(--tq-faja-brillo), .07)); border: 1px solid rgba(220, 215, 255, .55); }
        .tq-faja-caja > .tq-cara-arriba { background: linear-gradient(135deg, rgba(220, 215, 255, .32), rgba(var(--tq-faja-brillo), .12)); }
        .tq-faja-caja:hover > .tq-cara, .tq-faja-caja:focus-visible > .tq-cara { border-color: #fff; }
        .tq-faja-caja:focus-visible > .tq-cara-arriba { box-shadow: inset 0 0 0 3px #fff; }
        /* Paso 4: la caja levantada, el destino al que apunta (canaleta o final) */
        .levantada > .tq-faja-caja > .tq-cara { border-color: #fff; }
        .levantada > .tq-faja-caja > .tq-cara-arriba { box-shadow: 0 0 26px rgba(var(--tq-faja-brillo), .9); }
        /* Paso 8: `will-change` solo en la caja que se mueve, mientras se mueve. */
        .levantada { will-change: transform; }
        .tq-faja-visor.apunta-canaleta .tq-faja-canaleta { box-shadow: inset 0 0 0 3px var(--tq-faja-rojo), inset 0 0 40px rgba(240, 106, 94, .55); }
        .tq-faja-visor.apunta-fin .tq-faja-fin { box-shadow: 0 0 0 6px var(--tq-faja-verde), 0 0 40px var(--tq-faja-verde); }
        /* El grabado va solo en la cara visible, sin texto espejado por detrás */
        .tq-faja-caja > .tq-cara-rotulo { background: none; border: 0; overflow: visible; backface-visibility: hidden; -webkit-backface-visibility: hidden; }
        /* Paso 8: el brillo con desenfoque solo en las cajas completas (hasta 6 por zona); con muchas, solo el código y la
           sombra dura, que no se desenfoca. */
        .tq-faja-grabado { position: absolute; left: 12px; right: 12px; top: 12px; color: var(--tq-faja-tinta); transition: opacity .3s;
            text-shadow: 0 1px 0 rgba(0, 0, 0, .6); }
        .tq-faja-modo-completa .tq-faja-grabado { text-shadow: 0 1px 0 rgba(0, 0, 0, .6), 0 0 12px rgba(var(--tq-faja-brillo), .8); }
        .tq-faja-grabado b { display: block; font-weight: 800; font-size: 15px; line-height: 1.1; }
        .tq-faja-grabado span { display: block; font-family: ui-monospace, Consolas, monospace; font-size: 12px; opacity: .85; margin-top: 3px;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .tq-faja-modo-codigo .tq-faja-grabado { left: 6px; right: 6px; top: 8px; }
        .tq-faja-modo-codigo .tq-faja-grabado b { font-family: ui-monospace, Consolas, monospace; font-size: 12px; }
        .tq-faja-visor.lejos .tq-faja-grabado { opacity: 0; }
        .tq-faja-grieta { position: absolute; inset: 0; width: 100%; height: 100%; }
        .tq-faja-grieta polyline { fill: none; stroke: var(--tq-faja-ambar); }
        /* CRM-05 llena la próxima acción (D5); hasta entonces no se dibuja el punto. */
        .tq-faja-pulso { position: absolute; right: 10px; top: 10px; width: 12px; height: 12px; border-radius: 50%; background: var(--tq-faja-ambar);
            box-shadow: 0 0 12px var(--tq-faja-ambar); animation: tq-faja-pulso 1.6s ease-in-out infinite; }
        @keyframes tq-faja-pulso { 50% { opacity: .35; } }

        /* La pila «+N más» (D8): abre la tabla de la zona (paso 5) */
        .tq-faja-pila { cursor: pointer; outline: none; }
        .tq-faja-pila > .tq-cara { background: repeating-linear-gradient(0deg, rgba(var(--tq-faja-brillo), .40) 0 14px, rgba(var(--tq-faja-brillo), .12) 14px 18px);
            border: 1px solid rgba(220, 215, 255, .55); }
        .tq-faja-pila > .tq-cara-arriba { background: rgba(var(--tq-faja-brillo), .45); display: grid; place-items: center; color: #fff; font-weight: 800; font-size: 20px; }
        .tq-faja-pila:hover > .tq-cara, .tq-faja-pila:focus-visible > .tq-cara { border-color: #fff; }
        .tq-faja-pila:focus-visible > .tq-cara-arriba { box-shadow: inset 0 0 0 3px #fff; }

        /* Lo que mira siempre a la cámara: las viñetas (solo el número; mismo tamaño en pantalla; se van de cerca) */
        .tq-faja-cartel { position: absolute; transform-origin: 0 0;
            transform: translate3d(0, 0, var(--tq-alto, 80px)) rotateZ(calc(var(--tq-yaw) * -1)) rotateX(calc(var(--tq-pitch) * -1)) translate(-50%, -100%); }
        .tq-faja-vineta { --tq-alto: 330px; cursor: pointer; transition: opacity .3s, visibility .3s; outline: none;
            transform: translate3d(0, 0, var(--tq-alto)) rotateZ(calc(var(--tq-yaw) * -1)) rotateX(calc(var(--tq-pitch) * -1)) scale(var(--tq-inv, 1)) translate(-50%, -100%); }
        .tq-faja-vineta b { display: grid; place-items: center; min-width: 42px; height: 42px; padding: 0 10px; border-radius: 21px; background: rgba(20, 18, 43, .86);
            color: #fff; border: 2px solid rgba(220, 215, 255, .75); font-family: ui-monospace, Consolas, monospace; font-size: 19px; box-shadow: 0 6px 18px rgba(0, 0, 0, .4); }
        .tq-faja-vineta::after { content: ""; position: absolute; left: 50%; top: 42px; width: 2px; height: 16px; margin-left: -1px; background: rgba(220, 215, 255, .6); }
        .tq-faja-vineta:hover b, .tq-faja-vineta:focus-visible b { border-color: #fff; }
        .tq-faja-vineta:focus-visible b { outline: 3px solid var(--tq-faja-tinta); outline-offset: 3px; }
        .tq-faja-visor.cerca .tq-faja-vineta { opacity: 0; visibility: hidden; pointer-events: none; }

        /* Los controles: siguen el tema */
        .tq-faja-ui { position: absolute; z-index: 5; }
        .tq-faja-controles { top: 12px; left: 12px; right: 12px; display: flex; flex-wrap: wrap; gap: 6px; align-items: center; pointer-events: none; }
        .tq-faja-controles > * { pointer-events: auto; }
        .tq-faja-boton { background: var(--tq-faja-ui-fondo); color: var(--tq-faja-ui-tinta); border: 1px solid var(--tq-faja-ui-linea); border-radius: 10px;
            padding: 7px 11px; font-weight: 600; min-width: 36px; box-shadow: var(--tq-faja-ui-sombra); }
        .tq-faja-boton:focus-visible { outline: 2px solid var(--tq-faja-ui-acento); outline-offset: 2px; }
        .tq-faja-boton[aria-pressed="true"] { background: var(--tq-faja-ui-acento); border-color: var(--tq-faja-ui-acento); color: var(--tq-faja-ui-sobre-acento); }
        .tq-faja-boton.ganadas { border-color: var(--tq-faja-ui-verde); }
        .tq-faja-boton.perdidas { border-color: var(--tq-faja-ui-rojo); }
        .tq-faja-lugar { top: 62px; right: 12px; background: var(--tq-faja-ui-fondo); color: var(--tq-faja-ui-tinta); border: 1px solid var(--tq-faja-ui-linea); box-shadow: var(--tq-faja-ui-sombra); border-radius: 10px; padding: 8px 12px; font-size: 13px; max-width: 280px; }
        .tq-faja-lugar b { display: block; font-size: 17px; }
        .tq-faja-mini .tq-faja-volver { grid-column: 1 / -1; }
        .tq-faja-pista { font-size: 12px; color: var(--tq-faja-ui-suave); background: var(--tq-faja-ui-fondo); border: 1px solid var(--tq-faja-ui-linea); border-radius: 8px; padding: 6px 9px; }
        .tq-faja-espacio { flex: 1; }
        .tq-faja-mensaje { left: 50%; bottom: 78px; transform: translateX(-50%); background: var(--tq-faja-ui-fondo); color: var(--tq-faja-ui-tinta); border: 1px solid var(--tq-faja-ui-linea); box-shadow: var(--tq-faja-ui-sombra);
            border-radius: 12px; padding: 8px 14px; max-width: min(560px, calc(100% - 24px)); font-size: 13px; text-align: center; }
        .tq-faja-mini { left: 12px; right: 12px; bottom: 12px; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 4px;
            background: var(--tq-faja-ui-fondo); border: 1px solid var(--tq-faja-ui-linea); border-radius: 12px; padding: 5px; box-shadow: var(--tq-faja-ui-sombra); }
        .tq-faja-mini button:focus-visible { outline: 2px solid var(--tq-faja-ui-acento); outline-offset: -2px; }
        .tq-faja-mini button { border: 0; background: transparent; border-radius: 8px; padding: 6px 8px; text-align: left; min-width: 0; color: var(--tq-faja-ui-tinta); }
        .tq-faja-mini b { display: block; font-size: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .tq-faja-mini span { font-family: ui-monospace, Consolas, monospace; font-size: 11px; color: var(--tq-faja-ui-suave); white-space: nowrap; }
        .tq-faja-mini button.aqui { background: color-mix(in oklab, var(--tq-faja-ui-acento) 16%, transparent); }
        .tq-faja-mini button.aqui b { color: var(--tq-faja-ui-acento); }
        @media (max-width: 767px) {
            .tq-faja-pista, .tq-faja-lugar { display: none; }
            .tq-faja-mini { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .tq-faja-mini span { display: none; }
        }


        /* Paso 6: el despiece de la caja (prototipo, `.detail`, `.slab`, `.d-read`). La escena es del mundo (oscura); el
           marco, la cabecera y el panel de lectura siguen el tema (D10 de la 2.21c). */
        .tq-faja .tq-despiece { inset: 0; z-index: 10; display: flex; flex-direction: column; cursor: auto; touch-action: auto;
            background: var(--tq-faja-ui-marco); color: var(--tq-faja-ui-tinta); }
        .tq-despiece-cabeza { display: flex; gap: 12px; align-items: start; justify-content: space-between; padding: 14px 16px 0; flex-wrap: wrap; }
        .tq-despiece-cabeza h2 { font-size: 22px; font-weight: 800; margin: 0; line-height: 1.1; }
        .tq-despiece-sub { color: var(--tq-faja-ui-suave); font-size: 13px; margin-top: 3px; }
        .tq-despiece-lbl { font-size: 10.5px; letter-spacing: .08em; text-transform: uppercase; font-weight: 600; color: var(--tq-faja-ui-suave); }
        .tq-despiece-pastilla { font-size: 12px; font-weight: 600; border-radius: 99px; padding: 3px 10px; background: color-mix(in oklab, var(--tq-faja-ui-acento) 14%, transparent); color: var(--tq-faja-ui-acento); white-space: nowrap; }
        .tq-despiece-atascada { color: var(--tq-faja-ui-ambar); font-weight: 600; }
        .tq-despiece-cuerpo { flex: 1; min-height: 0; display: flex; gap: 12px; padding: 8px 16px 16px; }
        .tq-despiece-escena { position: relative; flex: 1; min-width: 0; overflow: hidden; border-radius: 14px; perspective: 1400px; display: grid; place-items: center;
            background: radial-gradient(ellipse at 50% 115%, var(--tq-faja-cielo), var(--tq-faja-noche) 62%); color: var(--tq-faja-tinta);
            transition: transform .75s cubic-bezier(.3, .7, .2, 1), opacity .4s; }
        .tq-despiece-escena.desde { transform: translate(var(--fx, 0px), var(--fy, 0px)) scale(.2); opacity: .2; transition: none; }
        .tq-despiece-pila { position: relative; width: 380px; height: 260px; transform-style: preserve-3d; --hueco: 62px;
            transform: scale(var(--escala, 1)) rotateX(58deg) rotateZ(-34deg) translateZ(-150px); }
        .tq-despiece-pila.juntas { --hueco: 24px; }
        .tq-despiece-capa { position: absolute; left: 0; top: 0; width: 380px; height: 260px; cursor: pointer; outline: none; transform-style: preserve-3d; --asoma: 0px;
            transform: translateZ(calc((var(--n) - 1 - var(--i)) * var(--hueco))) translate(var(--asoma), calc(-1 * var(--asoma)));
            transition: transform .7s cubic-bezier(.4, 0, .2, 1); }
        .tq-despiece-capa:hover, .tq-despiece-capa:focus-visible { --asoma: 28px; }
        .tq-despiece-capa.on { --asoma: 60px; }
        .tq-despiece-cara { position: absolute; overflow: hidden; }
        .tq-despiece-arriba { left: 0; top: 0; width: 380px; height: 260px; transform: translateZ(24px); padding: 14px 16px; color: var(--tq-faja-tinta);
            background: color-mix(in oklab, var(--tq-faja-banda) 94%, transparent); border: 1px solid rgba(220, 215, 255, .4); border-radius: 4px; }
        .tq-despiece-frente { left: 0; top: 236px; width: 380px; height: 24px; transform-origin: 50% 100%; transform: rotateX(-90deg); background: rgba(var(--tq-faja-brillo), .55); }
        .tq-despiece-der { left: 380px; top: 0; width: 24px; height: 260px; transform-origin: 0 50%; transform: rotateY(-90deg); background: rgba(var(--tq-faja-brillo), .4); }
        .tq-despiece-capa.on > .tq-despiece-arriba { border: 3px solid rgb(var(--tq-faja-brillo)); }
        .tq-despiece-capa:focus-visible > .tq-despiece-arriba { box-shadow: inset 0 0 0 3px #fff; }
        .tq-despiece-capa.espera > .tq-despiece-arriba { border-style: dashed; color: var(--tq-faja-tinta-suave); }
        .tq-despiece-capa.espera > .tq-despiece-frente, .tq-despiece-capa.espera > .tq-despiece-der { background: rgba(var(--tq-faja-brillo), .18); }
        .tq-despiece-sn { font-weight: 800; font-size: 22px; }
        .tq-despiece-ss { font-size: 13px; color: var(--tq-faja-tinta-suave); margin-top: 4px; }
        .tq-despiece-k { position: absolute; right: 14px; top: 12px; font-family: ui-monospace, Consolas, monospace; font-size: 13px; color: rgb(var(--tq-faja-brillo)); }
        .tq-despiece-panel { width: min(460px, 46%); flex: none; overflow: auto; display: flex; flex-direction: column; gap: 12px; padding: 16px; border-radius: 14px;
            background: var(--tq-faja-ui-fondo); color: var(--tq-faja-ui-tinta); border: 1px solid var(--tq-faja-ui-linea); user-select: text; -webkit-user-select: text; outline: none; }
        .tq-despiece-panel:focus-visible { outline: 2px solid var(--tq-faja-ui-acento); outline-offset: 2px; }
        .tq-despiece-panel h3 { margin: 0; font-size: 17px; font-weight: 800; }
        .tq-despiece-panel .tq-despiece-lbl { color: var(--tq-faja-ui-suave); }
        .tq-despiece-datos { display: flex; flex-direction: column; margin: 0; font-size: 13px; }
        .tq-despiece-datos > div { display: flex; justify-content: space-between; gap: 10px; border-bottom: 1px solid var(--tq-faja-ui-linea); padding: 5px 0; }
        .tq-despiece-datos dt { color: var(--tq-faja-ui-suave); }
        .tq-despiece-datos dd { margin: 0; text-align: right; font-variant-numeric: tabular-nums; }
        .tq-despiece-espera { margin: 0; padding: 14px; border: 1px dashed var(--tq-faja-ui-linea); border-radius: 10px; color: var(--tq-faja-ui-suave); font-size: 13px; }
        .tq-despiece-escaner { border-top: 1px solid var(--tq-faja-ui-linea); padding-top: 12px; display: flex; flex-direction: column; gap: 8px; }
        .tq-despiece-requisitos { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 5px; font-size: 13px; }
        .tq-despiece-requisitos li { display: flex; gap: 9px; align-items: center; border: 1px solid var(--tq-faja-ui-linea); border-radius: 9px; padding: 7px 9px; }
        .tq-despiece-marca { width: 18px; height: 18px; border-radius: 5px; border: 2px solid var(--tq-faja-ui-suave); display: grid; place-items: center; font-size: 12px; flex: none; }
        .tq-despiece-requisitos li.ok .tq-despiece-marca { background: var(--tq-faja-ui-verde); border-color: var(--tq-faja-ui-verde); color: var(--tq-faja-ui-sobre-acento); }
        .tq-despiece-estado { margin-left: auto; font-size: 11.5px; color: var(--tq-faja-ui-suave); }
        .tq-despiece-nota { margin: 0; font-size: 12px; color: var(--tq-faja-ui-suave); }
        .tq-despiece-accion { width: 100%; border-radius: 10px; padding: 9px 12px; font-weight: 600; border: 1px solid var(--tq-faja-ui-linea); background: transparent; color: var(--tq-faja-ui-tinta); }
        .tq-despiece-accion.primario { background: var(--tq-faja-ui-acento); border-color: var(--tq-faja-ui-acento); color: var(--tq-faja-ui-sobre-acento); }
        .tq-despiece-accion.peligro { color: var(--tq-faja-ui-rojo); }
        .tq-despiece-accion:focus-visible { outline: 2px solid var(--tq-faja-ui-acento); outline-offset: 2px; }
        @media (max-width: 767px) {
            .tq-despiece-cuerpo { flex-direction: column; }
            .tq-despiece-panel { width: auto; max-height: 52%; }
        }

        /* Paso 8: fuera de la vista o con la pestaña oculta, las animaciones de CSS se congelan (la faja pone `en-pausa`). */
        .tq-faja-visor.en-pausa *, .tq-faja-visor.en-pausa *::before, .tq-faja-visor.en-pausa *::after { animation-play-state: paused !important; }
        /* Contrato con el núcleo: la clase `tq-barra-hojas` de `barra-estacion` (lo vigila FajaTecladoYMedidaTest). */
        /* Paso 8: las hojas que caen en la barra en otoño (núcleo, `barra-estacion`) no se ven mientras está la faja. Con
           `visibility` y no `display`: su animación termina y el núcleo las quita (con `display: none` se quedarían). */
        html:has(.tq-faja-visor) .tq-barra-hojas { visibility: hidden; }

        /* Reducir animaciones (D12): la preferencia del sistema y la de la persona (`tq-sin-animaciones`). Paso 8: todo
           lo de la faja, sin excepciones (también lo que traen las pestañas del despiece). */
        @media (prefers-reduced-motion: reduce) {
            .tq-faja *, .tq-faja *::before, .tq-faja *::after { animation: none !important; transition: none !important; }
        }
        html.tq-sin-animaciones .tq-faja *, html.tq-sin-animaciones .tq-faja *::before, html.tq-sin-animaciones .tq-faja *::after { animation: none !important; transition: none !important; }
    </style>

    @include('oportunidades::faja.camara')
    @include('oportunidades::faja.despiece-script')
    @include('oportunidades::faja.hombrecito')

    <p class="tq-faja-celular">En el celular se lee mejor el <a href="{{ $urlTablero }}">Tablero de oportunidades</a>; la faja queda como opción.</p>

    {{-- `wire:ignore.self`: Livewire no toca las clases ni el estilo que pone la cámara al redibujar (solo el contenido). --}}
    <div
        class="tq-faja-visor lejos"
        wire:ignore.self
        x-data="tqFaja(@js($config))"
        x-bind:class="{ lejos: lejos, cerca: ! lejos, arrastrando: arrastre !== null && arrastre.movido, 'apunta-canaleta': apunta === 'perdida', 'apunta-fin': apunta === 'ganada', 'en-sala': sala !== null, 'en-ganadas': sala === 'ganadas', 'en-perdidas': sala === 'perdidas', 'en-pausa': pausada }"
        x-on:pointerdown="presionar($event)"
        x-on:pointermove="mover($event)"
        x-on:pointerup="soltar($event)"
        x-on:pointercancel="soltar($event)"
        x-on:pointerleave="inclinacion = { x: 0, y: 0 }; aplicarCamara()"
        x-on:tq-oportunidad-dialogo-listo.window="dialogoListo($event.detail)"
        x-on:modal-closed.window="dialogoCerrado($event.detail)"
        x-on:tq-faja-abrir-lugar="$event.preventDefault(); verLugar($event.detail.lugar)"
        x-on:tq-faja-abrir-fila.window="abrirFila($event.detail)"
        x-on:tq-faja-mover.window="moverSinArrastre($event.detail)"
        x-on:focusin="alEnfocar($event)"
        x-on:scroll="$el.scrollTop = 0; $el.scrollLeft = 0"
        tabindex="0"
        role="application"
        aria-label="Faja de oportunidades en 3D. A y D o las flechas izquierda y derecha cambian de zona; la rueda acerca y aleja; Tab recorre las cajas, las pilas y los terminales; Enter o Espacio abre lo que tiene el foco. Una caja abierta se pasa, se gana o se pierde con sus botones, sin arrastrar; Esc vuelve."
        data-faja
    >
        <div class="tq-faja-mundo" x-ref="mundo" wire:ignore.self>
            <div class="tq-faja-abs tq-faja-nivel-faja" data-capa="faja">
                {{-- El piso: atrás, delante de la canaleta y después del final --}}
                <div class="tq-faja-abs tq-faja-piso" style="left: -500px; top: -700px; width: {{ $fin + 500 }}px; height: 1095px"></div>
                <div class="tq-faja-abs tq-faja-piso" style="left: -500px; top: 445px; width: {{ $fin + 500 }}px; height: 700px"></div>
                <div class="tq-faja-abs tq-faja-piso" style="left: {{ $fin }}px; top: -700px; width: 1400px; height: 1845px"></div>

                {{-- La canaleta delante de la faja: soltar aquí pierde la oportunidad, que cae al fondo --}}
                <div class="tq-faja-abs" data-hueco="canaleta">
                    <div class="tq-faja-abs tq-faja-canaleta" style="left: -500px; top: 395px; width: {{ $fin + 500 }}px; height: 50px; transform: translateZ(-260px)"></div>
                    <div class="tq-faja-abs tq-faja-canaleta-brillo" style="left: -500px; top: 395px; width: {{ $fin + 500 }}px; height: 50px; transform: translateZ(-40px)"></div>
                    <div class="tq-faja-abs" style="left: {{ ($fin - 500) / 2 }}px; top: 395px"><div class="tq-faja-pie tq-faja-canaleta-pared" style="left: {{ -($fin + 500) / 2 }}px; top: -260px; width: {{ $fin + 500 }}px; height: 260px; transform: translateZ(-260px) rotateX(-90deg)"></div></div>
                    <div class="tq-faja-abs" style="left: {{ ($fin - 500) / 2 }}px; top: 445px; transform: rotateZ(180deg)"><div class="tq-faja-pie tq-faja-canaleta-pared" style="left: {{ -($fin + 500) / 2 }}px; top: -260px; width: {{ $fin + 500 }}px; height: 260px; transform: translateZ(-260px) rotateX(-90deg)"></div></div>
                    <div class="tq-faja-abs tq-faja-labio" style="left: -500px; top: 387px; width: {{ $fin + 500 }}px; height: 8px; transform: translateZ(1px)"></div>
                    <div class="tq-faja-abs tq-faja-labio-aviso" style="left: -500px; top: 445px; width: {{ $fin + 500 }}px; height: 10px; transform: translateZ(1px)"></div>
                </div>

                <div class="tq-faja-abs tq-faja-banda" style="left: -40px; top: 60px; width: {{ $fin + 40 }}px; height: 320px"></div>

                @foreach ($zonas as $zona)
                    @php($origen = $zona['indice'] * $anchoZona)
                    <div class="tq-faja-abs tq-faja-luz" style="left: {{ $origen + 10 }}px; top: 40px; width: 560px; height: 360px; --tq-faja-alfa: {{ round(0.10 + $zona['probabilidad'] / 100 * 0.45, 3) }}"></div>
                    <div class="tq-faja-abs tq-faja-rotulo" style="left: {{ $origen + 20 }}px; top: 455px" data-rotulo="{{ $zona['lugar'] }}">
                        {{ $zona['indice'] + 1 }} · {{ mb_strtoupper($zona['nombre']) }}<span title="{{ $zona['totalCompleto'] }}">{{ $zona['probabilidad'] }} % · {{ $zona['total'] }}</span>
                    </div>
                    {{-- El escáner a la salida de la zona: lámina café con barrido; clases aim, ok y no para el paso 4 --}}
                    <div class="tq-faja-abs tq-faja-escaner" style="left: {{ $origen + $anchoZona - 20 }}px; top: 220px; transform: rotateZ(90deg)" data-escaner="{{ $zona['indice'] }}">
                        <div class="tq-faja-pie tq-faja-lamina" style="left: -165px; top: -210px; width: 330px; height: 210px"></div>
                        <div class="tq-faja-pie tq-faja-poste" style="left: -181px; top: -232px; width: 16px; height: 232px"></div>
                        <div class="tq-faja-pie tq-faja-poste" style="left: 165px; top: -232px; width: 16px; height: 232px"></div>
                        <div class="tq-faja-pie tq-faja-poste" style="left: -181px; top: -16px; width: 362px; height: 16px; transform: translateZ(232px) rotateX(-90deg)"></div>
                    </div>
                    {{-- El terminal de la zona, en el piso (pedido B5): abre la ventana con su tabla --}}
                    {!! $solido($origen + 470, 545, 40, 26, 96, 'var(--tq-faja-soporte)') !!}
                    {!! $solido($origen + 462, 538, 56, 40, 8, 'var(--tq-faja-acero)') !!}
                    <div class="tq-faja-cartel" style="left: {{ $origen + 490 }}px; top: 558px; --tq-alto: 120px">
                        <span class="tq-faja-ir" data-terminal="{{ $zona['lugar'] }}" tabindex="0" role="button" x-on:keydown.enter.prevent="verLugar('{{ $zona['lugar'] }}')" x-on:keydown.space.prevent="verLugar('{{ $zona['lugar'] }}')"
                            aria-label="Ver las {{ $zona['cantidad'] }} de {{ $zona['nombre'] }} en una tabla">Ver las {{ $zona['cantidad'] }} de {{ $zona['nombre'] }}<small title="{{ $zona['terminalCompleto'] }}">{{ $zona['terminal'] }}</small></span>
                    </div>
                @endforeach

                {{-- El final de la faja: la boca de la tubería de acero con su brida, el fondo oscuro y la cortina de PVC --}}
                <div class="tq-faja-abs" data-hueco="tuberia">
                    {!! $tubo($fin, 760, 220, $eje, $radio, 'tq-faja-tubo') !!}
                    <div class="tq-faja-abs tq-faja-boca">{!! $tubo($fin - 14, 26, 220, $eje, $radio + 16, 'tq-faja-brida') !!}</div>
                    {!! $tubo($fin + 330, 26, 220, $eje, $radio + 12, 'tq-faja-brida') !!}
                    {!! $pared($fin + 150, 220, 90, 2 * $radio, 2 * $radio, 'tq-faja-fin', '', $eje - $radio) !!}
                    @foreach ([$fin + 90, $fin + 560] as $x)
                        {!! $solido($x, 220 - $radio - 10, 30, 2 * $radio + 20, $eje - $radio + 6, 'var(--tq-faja-soporte)') !!}
                        {!! $solido($x - 10, 220 - $radio - 30, 50, 2 * $radio + 60, 8, 'var(--tq-faja-acero)') !!}
                    @endforeach
                    {{-- La cortina: la física la mueve el navegador; Livewire no la toca al redibujar --}}
                    <div class="tq-faja-abs" style="left: {{ $fin - 2 }}px; top: 220px; transform: rotateZ(90deg)" wire:ignore>
                        <div class="tq-faja-pie tq-faja-cortina" style="left: {{ -$radio }}px; top: {{ -2 * $radio }}px; width: {{ 2 * $radio }}px; height: {{ 2 * $radio }}px; transform: translateZ({{ $eje - $radio }}px) rotateX(-90deg)" data-cortina>
                            @foreach ($tiras as $tira)
                                <i class="tq-faja-tira" data-tira style="left: {{ $tira['left'] }}px; top: {{ $tira['top'] }}px; width: {{ $tira['ancho'] }}px; height: {{ $tira['alto'] }}px"></i>
                            @endforeach
                        </div>
                    </div>
                    {{-- El terminal de las ganadas, junto a la tubería (pedido B5) --}}
                    {!! $solido($fin + 40, 470, 46, 30, 110, 'var(--tq-faja-soporte)') !!}
                    {!! $solido($fin + 30, 462, 66, 46, 8, 'var(--tq-faja-acero)') !!}
                    <div class="tq-faja-cartel" style="left: {{ $fin + 63 }}px; top: 485px; --tq-alto: 140px">
                        <span class="tq-faja-ir ganadas" data-ir="ganadas" data-terminal-sala="ganadas" tabindex="0" role="button" x-on:keydown.enter.prevent="entrarGanadas()" x-on:keydown.space.prevent="entrarGanadas()"
                            aria-label="Ver ganadas: {{ $ganadas['terminalCompleto'] }}">Ver ganadas<small title="{{ $ganadas['terminalCompleto'] }}">{{ $ganadas['terminal'] }}</small></span>
                    </div>
                </div>

                {{-- El letrero rojo sobre la canaleta (pedido B5): baja al sótano de las perdidas --}}
                <div class="tq-faja-cartel" style="left: {{ $fin / 2 }}px; top: 420px; --tq-alto: 60px">
                    <span class="tq-faja-ir perdidas" data-ir="perdidas" data-terminal-sala="perdidas" tabindex="0" role="button" x-on:keydown.enter.prevent="entrarPerdidas()" x-on:keydown.space.prevent="entrarPerdidas()"
                        aria-label="Ver perdidas: {{ $perdidas['terminalCompleto'] }}">↓ Ver perdidas<small title="{{ $perdidas['terminalCompleto'] }}">{{ $perdidas['terminal'] }}</small></span>
                </div>
            </div>

            <div class="tq-faja-abs tq-faja-nivel-faja" data-capa="vinetas">
                @foreach ($zonas as $zona)
                    <div class="tq-faja-cartel tq-faja-vineta" style="left: {{ $config['centros'][$zona['indice']] }}px; top: 230px"
                        data-vineta="{{ $zona['lugar'] }}" data-zona="{{ $zona['indice'] }}"
                        tabindex="0" role="button" aria-label="Ir a {{ $zona['nombre'] }}: {{ $zona['cantidad'] }} oportunidades"
                        x-on:keydown.enter.prevent="irDesdeVineta({{ $zona['indice'] }})" x-on:keydown.space.prevent="irDesdeVineta({{ $zona['indice'] }})"
                        title="{{ $zona['nombre'] }}: {{ $zona['cantidad'] }}"><b>{{ $zona['cantidad'] }}</b></div>
                @endforeach
                {{-- Las de ganadas y perdidas, también solo con el número; llevan a su sala --}}
                <div class="tq-faja-cartel tq-faja-vineta ganadas" style="left: {{ $fin + 380 }}px; top: 220px; --tq-alto: 440px"
                    data-vineta="ganadas" data-ir="ganadas" tabindex="0" role="button" aria-label="Entrar a ganadas: {{ $ganadas['cantidad'] }} oportunidades"
                    x-on:keydown.enter.prevent="entrarGanadas()" x-on:keydown.space.prevent="entrarGanadas()"
                    title="Ganadas: {{ $ganadas['cantidad'] }}"><b>{{ $ganadas['cantidad'] }}</b></div>
                <div class="tq-faja-cartel tq-faja-vineta perdidas" style="left: {{ $fin / 2 }}px; top: 420px; --tq-alto: 150px"
                    data-vineta="perdidas" data-ir="perdidas" tabindex="0" role="button" aria-label="Bajar a perdidas: {{ $perdidas['cantidad'] }} oportunidades"
                    x-on:keydown.enter.prevent="entrarPerdidas()" x-on:keydown.space.prevent="entrarPerdidas()"
                    title="Perdidas: {{ $perdidas['cantidad'] }}"><b>{{ $perdidas['cantidad'] }}</b></div>
            </div>

            <div class="tq-faja-abs tq-faja-nivel-faja" data-capa="cajas">
                @foreach ($zonas as $zona)
                    @foreach ($zona['cajas'] as $caja)
                        @include('oportunidades::faja.caja', ['caja' => $caja, 'lugar' => $zona['lugar'], 'modo' => $zona['modo']])
                    @endforeach

                    @if ($zona['pila'] !== null)
                        @include('oportunidades::faja.pila', ['pila' => $zona['pila'], 'lugar' => $zona['lugar'], 'nombre' => $zona['nombre']])
                    @endif
                @endforeach
            </div>

            {{-- La sala de ganadas (pedido B5): se entra por la tubería; las cajas, sobre pallets, las más recientes primero (D8) --}}
            @php($gx = $ganadas['x'])
            @php($gy = $ganadas['y'])
            <div class="tq-faja-abs" data-capa="ganadas">
                <div class="tq-faja-abs tq-faja-concreto" style="left: {{ $gx - 200 }}px; top: {{ $gy - 120 }}px; width: 1300px; height: 900px"></div>
                <div class="tq-faja-abs tq-faja-epoxi" style="left: {{ $gx + 100 }}px; top: {{ $gy + 130 }}px; width: 920px; height: 430px"></div>
                {!! $pared($gx + 450, $gy - 120, 180, 1300, 320, 'tq-faja-bloque') !!}
                {!! $pared($gx + 1100, $gy + 330, -90, 900, 320, 'tq-faja-bloque') !!}
                {!! $pared($gx - 200, $gy + 330, 90, 900, 320, 'tq-faja-bloque') !!}
                {!! $tubo($gx - 200, 150, $gy + 330, $eje, $radio, 'tq-faja-tubo') !!}
                {!! $tubo($gx - 60, 24, $gy + 330, $eje, $radio + 14, 'tq-faja-brida') !!}
                {!! $pared($gx - 40, $gy + 330, 90, 2 * $radio, 2 * $radio, 'tq-faja-fin', '', $eje - $radio) !!}
                {!! $pared($gx + 450, $gy - 116, 0, 300, 60, 'tq-faja-letrero', 'GANADAS', 200) !!}
                <div class="tq-faja-abs tq-faja-foco" style="left: {{ $gx + 250 }}px; top: {{ $gy + 80 }}px; width: 600px; height: 560px; transform: translateZ(1px); opacity: .22"></div>
                @foreach ($ganadas['cajas'] as $caja)
                    {!! $solido($caja['x'] - ($caja['ancho'] + 30) / 2, $caja['y'] - ($caja['fondo'] + 20) / 2, $caja['ancho'] + 30, $caja['fondo'] + 20, 14, 'var(--tq-faja-madera)') !!}
                    @include('oportunidades::faja.caja', ['caja' => $caja, 'lugar' => 'ganadas', 'modo' => $ganadas['modo'], 'tono' => 'ganada', 'z' => 14])
                @endforeach
                @if ($ganadas['pila'] !== null)
                    @php($pila = $ganadas['pila'])
                    {!! $solido($pila['x'] - ($pila['ancho'] + 30) / 2, $pila['y'] - ($pila['fondo'] + 20) / 2, $pila['ancho'] + 30, $pila['fondo'] + 20, 14, 'var(--tq-faja-madera)') !!}
                    @include('oportunidades::faja.pila', ['pila' => $pila, 'lugar' => 'ganadas', 'nombre' => 'Ganadas', 'z' => 14])
                @endif
            </div>

            {{-- El sótano de perdidas (pedido B5): se baja por la escalera; las cajas, caídas, las más recientes primero (D8) --}}
            @php($lx = $perdidas['x'])
            @php($ly = $perdidas['y'])
            <div class="tq-faja-abs" data-capa="perdidas">
                <div class="tq-faja-abs tq-faja-concreto oscuro" style="left: {{ $lx - 200 }}px; top: {{ $ly - 120 }}px; width: 1300px; height: 900px"></div>
                {!! $pared($lx + 450, $ly - 120, 180, 1300, 300, 'tq-faja-bloque oscuro') !!}
                {!! $pared($lx + 1100, $ly + 330, -90, 900, 300, 'tq-faja-bloque oscuro') !!}
                {!! $pared($lx - 200, $ly + 330, 90, 900, 300, 'tq-faja-bloque oscuro') !!}
                {!! $pared($lx + 450, $ly - 116, 0, 360, 56, 'tq-faja-letrero', 'PERDIDAS', 190) !!}
                @for ($escalon = 0; $escalon < 7; $escalon++)
                    {!! $solido($lx - 195, $ly - 110 + $escalon * 55, 150, 55, 42 * (7 - $escalon), 'var(--tq-faja-escalon)') !!}
                @endfor
                <div class="tq-faja-abs tq-faja-foco" style="left: {{ $lx + 230 }}px; top: {{ $ly + 100 }}px; width: 560px; height: 560px; transform: translateZ(1px); opacity: .5"></div>
                @foreach ($perdidas['cajas'] as $caja)
                    @include('oportunidades::faja.caja', ['caja' => $caja, 'lugar' => 'perdidas', 'modo' => $perdidas['modo'], 'tono' => 'perdida'])
                @endforeach
                @if ($perdidas['pila'] !== null)
                    @include('oportunidades::faja.pila', ['pila' => $perdidas['pila'], 'lugar' => 'perdidas', 'nombre' => 'Perdidas'])
                @endif
            </div>

            {{-- Paso 7: el hombrecito (`faja/hombrecito`). Decorativo: no toma el puntero ni lo lee el lector de pantalla;
                 su cuerpo lo arma el navegador y Livewire no lo toca al redibujar. --}}
            <div class="tq-faja-abs tq-faja-nivel-faja tq-hombre-hueco" data-hueco="hombrecito" aria-hidden="true" wire:ignore
                x-data="tqHombrecito(@js(['anchoZona' => $anchoZona, 'fin' => $fin, 'lugares' => $config['lugares']]))">
                <div class="tq-hombre" data-hombre></div>
                <div class="tq-faja-cartel tq-hombre-zzz" data-hombre-zzz hidden><i>z</i><i>z</i><i>Z</i></div>
                <div class="tq-faja-cartel tq-hombre-destello" data-hombre-destello><i></i></div>
            </div>
        </div>

        <div class="tq-faja-fundido" x-bind:class="{ on: oscuro }" aria-hidden="true"></div>

        <div class="tq-faja-ui tq-faja-controles">
            <button type="button" class="tq-faja-boton" x-show="sala === null" x-on:click="volarA(vistaGeneral())" x-bind:aria-pressed="lejos ? 'true' : 'false'">Vista general</button>
            <button type="button" class="tq-faja-boton" x-show="sala !== null" x-on:click="volverAFaja()" x-cloak data-boton="volver">← Volver a la faja</button>
            <button type="button" class="tq-faja-boton" x-on:click="acercar(1 / 1.35)" aria-label="Alejar">−</button>
            <button type="button" class="tq-faja-boton" x-on:click="acercar(1.35)" aria-label="Acercar">+</button>
            <span class="tq-faja-pista" x-show="sala === null">Rueda: acercar o alejar · Ctrl + rueda, A/D o ←/→: zona anterior o siguiente · arrastra el fondo para moverte</span>
            <span class="tq-faja-espacio"></span>
            <button type="button" class="tq-faja-boton" x-show="sala !== null" x-on:click="verLugar(sala)" x-cloak data-boton="tabla">Ver en tabla</button>
            <button type="button" class="tq-faja-boton ganadas" x-show="sala !== 'ganadas'" x-on:click="entrarGanadas()" data-boton="ganadas">Ver ganadas · {{ $ganadas['cantidad'] }}</button>
            <button type="button" class="tq-faja-boton perdidas" x-show="sala !== 'perdidas'" x-on:click="entrarPerdidas()" data-boton="perdidas">Ver perdidas · {{ $perdidas['cantidad'] }}</button>
        </div>

        <div class="tq-faja-ui tq-faja-lugar" role="status" x-show="sala !== null" x-cloak>
            <div x-show="sala === 'ganadas'"><b>Ganadas</b>{{ $ganadas['cantidad'] }} cajas · <x-oportunidades::monto :corto="$ganadas['total']" :completo="$ganadas['totalCompleto']" /></div>
            <div x-show="sala === 'perdidas'"><b>Sótano de perdidas</b>{{ $perdidas['cantidad'] }} cajas · <x-oportunidades::monto :corto="$perdidas['total']" :completo="$perdidas['totalCompleto']" /></div>
        </div>

        <div class="tq-faja-ui tq-faja-mensaje" role="status" x-show="mensaje !== null" x-text="mensaje" x-cloak></div>

        <nav class="tq-faja-ui tq-faja-mini" aria-label="Zonas de la faja">
            @foreach ($zonas as $zona)
                <button type="button" x-show="sala === null" x-on:click="irAZona({{ $zona['indice'] }})" x-bind:class="{ aqui: zonaAqui === {{ $zona['indice'] }} }" data-mini="{{ $zona['lugar'] }}">
                    <b>{{ $zona['indice'] + 1 }} · {{ $zona['nombre'] }}</b>
                    <span>{{ $zona['cantidad'] }} · <x-oportunidades::monto :corto="$zona['total']" :completo="$zona['totalCompleto']" /></span>
                </button>
            @endforeach
            <button type="button" class="tq-faja-volver" x-show="sala !== null" x-on:click="volverAFaja()" x-cloak>
                <b>← Volver a la faja</b>
                <span x-text="sala === 'ganadas' ? 'por la tubería' : 'por la escalera'"></span>
            </button>
        </nav>

        {{-- Paso 6: el despiece de la caja, encima del visor (escucha `tq-faja-abrir`). --}}
        @livewire(DespieceDeOportunidad::class, [], key('faja-despiece'))
    </div>
</div>
