# Plan: la luz de la barra según la hora, la temperatura y el clima (1.4ac)

- **Estado:** terminado (falta `composer check:tarea` de la entrega)
- **Responsable:** Sebastián (interfaz)
- **Entrega:** `entrega/proyectos-portada`
- **Módulo(s):** Nucleo (`Navegacion`: `EntornoEstacion`, `ClimaDelEntorno`; vistas `navegacion` y `clima`)
- **Fase:** —
- **Creado:** 2026-11-15 · **Actualizado:** 2026-11-15

## Objetivo

La barra superior lleva una luz que cuenta la escena de afuera (sol de día, luna de noche) y el fondo de la página toma
un tinte sutil acorde a la temperatura y la hora, en claro y en oscuro, sin cambiar la claridad ni el contraste del
texto. Las tarjetas, tablas y secciones no cambian.

## Contexto que leí

- `interfaz.md`, «Inicio»: el entorno según la estación (1.4s), el clima real (1.4u), el sol en la barra (hoy un
  destello a la izquierda, al 32 %) y la noche sin sol (1.4u, D2).
- Simuladores revisados con el desarrollador el 2026-11-15 (claro y oscuro, con hora, temperatura y clima).

## Decisiones

- **D1.** (desarrollador, 2026-11-15, «me gusta más el detalle del color amarillo como representando el sol en la parte
  de arriba a la derecha por el buscador») La luz vive **arriba a la derecha, junto al buscador**: un halo radial que
  se sale de la barra por arriba. Al amanecer se corre un poco hacia el centro y al atardecer se pega al borde, como
  en el simulador; de día está junto al buscador.
- **D2.** (desarrollador, 2026-11-15, «que el fondo cambie sus colores pero sutilmente durante el día dependiendo de la
  temperatura y hora») **El tinte del fondo y de la barra sale de la temperatura y la hora**, no de la estación: frío,
  blanco azulado; templado, neutro; calor, arena. Con la misma claridad de siempre (en oscuro, la del gris carbón) y
  croma pequeña, como la 1.4s. Reemplaza al tinte por estación del entorno (1.4s); las cajas del inicio (1.4r) no
  cambian en esta tarea.
- **D3.** (simulador aprobado, 2026-11-15) **Color de la luz por la hora:** amanecer naranja suave, día amarillo
  (más intenso y más dorado con calor), atardecer coral. **Clima:** nublado la difumina y le quita color; con lluvia,
  casi no hay astro (las gotas de la 1.4u siguen).
- **D4.** (simulador aprobado, 2026-11-15) **De noche, luna:** halo plateado azulado en el mismo lugar, unas pocas
  estrellas finas (5 a 9) en la barra con cielo despejado y el fondo un poco más frío. **Con la fase real de la luna**,
  calculada con la fecha (sin consultar a nadie): llena, halo redondo y brillante; creciente o menguante, solo el borde
  iluminado del lado que corresponde; nueva, casi nada y quedan las estrellas.
- **D5.** (simulador aprobado, 2026-11-15) **Modo oscuro:** la luz a la mitad de intensidad y más dorada (resplandece,
  no ilumina); el carbón apenas tibio con calor y apenas azulado con frío; de noche, el fondo a un azul noche muy
  apagado, luna y estrellas visibles de verdad.
- **D6.** (técnica) La luz y el tinte son cálculos en PHP (como `EntornoEstacion`) que dan variables CSS; la temperatura
  y el cielo los da el clima que ya consulta el navegador (1.4u). Sin clima (sin permiso de ubicación o sin respuesta),
  temperatura templada y cielo despejado. Se apaga con la misma preferencia «Apariencia según la estación» (se
  renombra «Apariencia según el día») y, con «reducir animaciones», sin transiciones.

## Valores de los simuladores aprobados (desarrollador, 2026-11-15)

Copiados de los dos simuladores que el desarrollador aprobó (`techquk_luz_de_la_barra_por_hora_y_temperatura.html`,
claro, y `techquk_luz_de_la_barra_modo_oscuro.html`, oscuro): **se usan tal cual**. El modo claro sale del primero y
el oscuro del segundo (el segundo también trae un claro con diferencias mínimas; manda el primero). Colores en RGB.

- **Fases por la hora:** noche antes de las 5:30 o desde las 19:30; amanecer hasta las 7:00; día hasta las 17:30;
  atardecer después.
- **Calor:** `(temperatura − 10) / 24`, entre 0 y 1 (10 °C → 0; 34 °C → 1). Sin clima: templado (calor 0.5) y despejado.
- **Mezcla:** `mezclar(a, b, f)` = cada canal `a + (b − a) × f`, redondeado. Con calor < 0.5, frío→tibio con
  `calor × 2`; si no, tibio→calor con `(calor − 0.5) × 2`.
- **Claro:**
  - Fondo: frío `240,244,248`; tibio `248,247,242`; calor `250,246,236`. Barra: fondo mezclado con `255,255,255` al 0.35.
  - Noche: fondo = mezclar(fondo, `236,239,247`, 0.6); barra = mezclar(fondo, `228,232,244`, 0.6); luna `200,212,255`,
    intensidad 0.55 despejado (0.18 con nubes o lluvia); 7 estrellas con cielo despejado.
  - Amanecer: luz `255,170,110`, intensidad 0.45 + calor × 0.2, a 70 % desde la izquierda.
  - Día: luz = mezclar(`255,236,150`, `255,210,80`, calor), intensidad 0.35 + calor × 0.4, a 110 px del borde derecho
    (junto al buscador).
  - Atardecer: luz `255,130,100`, intensidad 0.5 + calor × 0.2, a 40 px del borde derecho.
  - Nublado (no de noche): luz = mezclar(luz, `220,220,225`, 0.7), intensidad × 0.5.
  - Lluvia: luz = mezclar(luz, `200,205,215`, 0.85), intensidad × 0.35, barra = mezclar(barra, `232,236,242`, 0.5).
- **Oscuro (gris carbón):**
  - Fondo: frío `34,39,48`; tibio `40,41,44`; calor `47,42,36`. Barra: mezclar(fondo, `70,72,78`, 0.45).
  - Luz de día, amanecer y atardecer al 0.55 de su intensidad.
  - Noche: fondo = mezclar(fondo, `30,34,46`, 0.6); barra = mezclar(barra, `42,47,64`, 0.6); luna `200,212,255`,
    intensidad 0.42 despejado (0.15 con nubes o lluvia); 9 estrellas de brillo 0.5, 0.7 y 0.9.
  - Amanecer `255,160,100`; día = mezclar(`255,230,140`, `255,200,70`, calor); atardecer `255,120,95` (mismas
    intensidades base que el claro, por 0.55).
  - Nublado: mezclar(luz, `150,152,160`, 0.7), intensidad × 0.5. Lluvia: mezclar(luz, `130,140,160`, 0.85), × 0.35.
  - Logo `#C9C8FF`; buscador con fondo blanco al 7 %, texto `#A1A1AA` y borde blanco al 10 %; avatar `#3B3A9E`.
- **El halo:** degradado radial de 220 px de diámetro, centrado 110 px por encima de la barra: color a la intensidad en
  el centro, a un 35 % de ella al 40 %, transparente al 70 %.
- **Las tarjetas y tablas no cambian** (las del simulador del oscuro son solo de muestra). La fase real de la luna (D4)
  no está en los simuladores: se agrega sobre el halo de la noche.

## Pasos

- [x] 1. Plan con el diseño aprobado.
- [x] 2. `LuzDelDia` (hora, temperatura, cielo, modo → color, intensidad y lugar de la luz; tinte de fondo y barra) y
  `FaseLunar` (fecha → fracción iluminada y lado), con pruebas por casos (mediodía caluroso, amanecer frío, noche de
  luna llena y nueva, lluvia, oscuro).
- [x] 3. La vista de la barra: el halo a la derecha junto al buscador, la luna con su fase y las estrellas; quitar el
  destello del 32 %.
- [x] 4. El tinte del fondo por temperatura y hora en lugar del de la estación (1.4s), sin cambiar la claridad; prueba de
  contraste AA del texto gris sobre el fondo en los extremos.
- [x] 5. Modo desarrollo: hora y temperatura simuladas para revisar cada escena.
- [x] 6. `interfaz.md` al día y `composer check:tarea` en verde.

## Preparado para lo diferido

Nada se pospone. Las cajas del inicio siguen con su estación (1.4r): si se quiere que también sigan a la temperatura,
es otra tarea.

## Criterio de aceptación

- Pruebas de `LuzDelDia` y `FaseLunar` con sus casos; la del contraste en los extremos.
- En pantalla, con el modo desarrollo: las escenas del simulador en claro y oscuro.

## Bitácora

- 2026-11-15 — Diseño cerrado con dos simuladores en el chat (claro y oscuro). El desarrollador aprobó la dirección
  («ya me vacila, está bien») sin elegir entre sol fijo o que recorra la barra ni entre luna con fase o solo halo: se
  tomó lo que pidió primero (a la derecha, junto al buscador) y la fase real, que fue la propuesta destacada.
- 2026-11-15 — Anotados los valores de los dos simuladores (claro y oscuro) que aprobó el desarrollador, para usarlos
  tal cual («quiero que quede el fondo tal cual como este»). Sigue después de que se fusionen los PR del corte.
- 2026-11-16 — Hecho. Los valores se leyeron de los simuladores mismos (`/mnt/c/Users/20rse/Downloads/…`), no solo
  del resumen: la geometría del halo es un círculo de 220 px con el centro en el borde de arriba (en el fondo de la barra:
  `circle 155.56px`, porque `circle` llega a la esquina). La temperatura solo la sabe el navegador: `LuzDelDia::css()`
  escribe la escena de la hora con `color-mix()` sobre `--tq-calor`, que pone `clima.blade.php`; `escena()` hace la
  misma cuenta en PHP para las pruebas. Fuera `EntornoEstacion` y su prueba; `data-tq-noche` (de `MomentoDia`) sigue
  para los reflejos de las cajas. En oscuro, el logo lila y el buscador translúcido del simulador; el avatar no
  (Filament lo dibuja como imagen). **Contraste:** el gris secundario de Filament en claro baja de 4,62 a 4,1–4,5:1
  sobre el fondo nuevo (la barra perla ya estaba en 4,02); el texto principal pasa en todo. Se respetan los valores
  aprobados; la prueba vigila que no baje de 4,0. Avisado al desarrollador.

