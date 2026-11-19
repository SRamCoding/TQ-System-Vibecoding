# Plan: el clima real ajusta los efectos de la estación (1.4u)

- **Estado:** terminado
- **Módulo(s):** Nucleo (interfaz)
- **Fase:** F1
- **Creado:** 2026-10-28 · **Actualizado:** 2026-10-28

## Objetivo

La estación la sigue decidiendo el calendario; el clima del lugar de la computadora ajusta sus efectos en las cajas del
inicio y la barra superior: sol solo de día y despejado (más fuerte con calor), gotas en la barra con lluvia, bruma con
niebla y un poco de escarcha con frío. Sin saltos y sin que la ubicación ni el clima pasen por el servidor.

## Contexto que leí

- Pedido del desarrollador (2026-10-28): «¿y si manejamos primavera, verano, otoño, invierno en función de las
  temperaturas detectadas por la ubicación de la laptop?». Se le explicó por qué no conviene como reemplazo (en
  Chiclayo casi siempre sería verano; salto al cargar; la regla de privacidad del clima; incoherencia con el dibujo) y
  aceptó el enfoque propuesto: el calendario decide la estación y el clima real ajusta los efectos.
- Plan 1.4e2 (D5) y `docs/convenciones/interfaz.md`, «Saludo y ambientación de la fecha»: el navegador consulta
  Open-Meteo con la posición redondeada a un decimal, recuerda el clima 30 minutos en `localStorage` (`tq-clima`); nada
  pasa por el servidor ni se guarda; `interfaz.clima_activo` lo prende; respaldo `interfaz.clima_ubicacion_respaldo`;
  el modo desarrollo fuerza el clima (`ClimaDelDibujo`). Hoy solo lo usa el dibujo del saludo, en el inicio.
- Planes 1.4r, 1.4s y 1.4t: reflejo de sol en cajas (primavera y verano, claro) y barra superior; escarcha de invierno
  (`AdornosDeEstacion::hielo`); hojas de otoño en la barra.
- Nada de `pendientes.md` afecta.

## Decisiones

- **D1. Un solo consultor del clima, en todas las pantallas.** El script del dibujo pasa a una vista del encabezado
  (`nucleo::filament.clima`), que pone el clima en `<html>` (`data-tq-clima`, y `data-tq-temperatura` con calor o
  frío) antes de dibujar, desde lo recordado, y consulta Open-Meteo solo si hace falta. El dibujo lo toma de ahí. Misma
  privacidad que hoy: posición a un decimal, nada al servidor, 30 minutos en el navegador. Se agrega la temperatura a la
  consulta (`temperature_2m`).
- **D2. Día o noche, del servidor:** la luz de la hora del dibujo (`RegistroPiezasDibujo::luz`), con la hora decorativa
  (sigue la fecha simulada del modo desarrollo). De noche, sin sol. Lo pone la vista de navegación (`data-tq-noche`),
  así vale aunque el clima esté apagado.
- **D3. Qué ajusta el clima** (solo donde hay estación, con «Apariencia según la estación» prendida):
  - **Sol** (reflejos de las cajas y de la barra): entero con despejado de día; más fuerte con calor (30 °C o más);
    nada con nublado, niebla, llovizna, lluvia, tormenta o de noche. Se apaga y se prende con un fundido (1,2 s), no de
    golpe.
  - **Lluvia** (llovizna, lluvia, tormenta): gotas quietas en la barra superior, como sobre un vidrio. Sin animación.
  - **Niebla:** una bruma clara sobre la barra.
  - **Frío** (15 °C o menos): un poco de escarcha en las cajas, en cualquier estación (la de invierno ya la tiene).
- **D4. Sin clima** (apagado, sin respuesta o sin permiso y sin respaldo): todo como hoy, con sol.
- **D5. Modo desarrollo:** el clima elegido (`ClimaDelDibujo`, ahora «Clima») vale para todo; nueva pieza
  «Temperatura» (calor, templado, frío).

## Pasos

- [x] 1. Consultor del clima en el encabezado (D1, D2, D4, D5), el dibujo lo usa; pruebas.
- [x] 2. Efectos del clima en cajas y barra (D3); pruebas.
- [x] 3. Documentos y capturas.
- [x] 4. `composer check` en verde; plan a completados; PR.

## Criterio de aceptación

- `ClimaEntornoTest` en verde; las pruebas del dibujo con clima siguen pasando.
- En la app: con nublado o de noche, sin reflejo; con lluvia, gotas en la barra; con frío, escarcha en las cajas.

## Bitácora

- 2026-10-28 — Plan escrito. Rama `interfaz-clima-estacion`, apilada sobre `interfaz-oscuro-atenuado` (1.4t).
- 2026-10-28 — Paso 2. `--tq-sol` (registrada con `@property` para que el cambio sea un fundido) multiplica la opacidad
  de todos los reflejos. Gotas y bruma en `.fi-topbar::after`, detrás del contenido. **Error del paso 1, corregido aquí:**
  la vista del clima leía los parámetros adentro; sin ellos (pruebas sin sembrar) lanzaba dentro de la vista, y Laravel
  limpia la pila de componentes al fallar una vista: las cajas del inicio se rompían después («Undefined array key 0»).
  Ahora el proveedor pide los datos antes de dibujar y la vista solo los recibe. En la app real los parámetros existen:
  no llegó a verse.
- 2026-10-28 — Paso 3. En las capturas, las gotas formaban una cuadrícula: cuatro capas con medidas que no se repiten
  juntas (89, 131, 173 y 61 px). El desarrollador fusionó el PR de la 1.4r mientras tanto.
- 2026-10-28 — Cierre. `composer check`: pint y phpstan sin errores, 42 migraciones bien, pest 1932/1932 (9815
  aserciones). Sin revisión de arquitectura: sin migraciones, módulos ni efectos entre módulos (un gancho más del
  encabezado y una pieza del modo desarrollo).
