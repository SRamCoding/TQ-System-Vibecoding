# Plan: entorno según la estación (1.4s)

- **Estado:** terminado
- **Módulo(s):** Nucleo (interfaz)
- **Fase:** F1
- **Creado:** 2026-10-28 · **Actualizado:** 2026-10-28

## Objetivo

Lo que está fuera de las cajas acompaña a la estación de la 1.4r, de forma sutil y en todas las pantallas. El fondo de
la página y la barra superior toman un tinte leve del color de la estación, con la misma claridad que hoy, así que el
contraste del texto no cambia. Un solo interruptor, «Apariencia según la estación», prende o apaga las cajas y el
entorno juntos.

## Contexto que leí

- Pedido del desarrollador (2026-10-28): «cambiar los colores del entorno, todo lo afuera de las cajas, pero no quiero
  un cambio brusco, quiero algo bien sutil». Vio tres propuestas en una vista previa (A velo, B velo y barra, C luz) y
  eligió la **B**, con las recomendaciones que la acompañaban: todas las pantallas, mezcla de 15 días entre estaciones,
  un solo interruptor y la barra lateral sin fondo propio.
- `docs/convenciones/interfaz.md`: «Identidad visual», «Reglas», «Barras» (barra superior perla en claro, gris con el
  matiz del azul noche en oscuro) y «Saludo y ambientación de la fecha» («Tres toques, nada más»; nunca cambiar
  botones ni estados).
- Plan 1.4r (`docs/planes/completados/2026-10-28-interfaz-cajas-estacion.md`): estación por fecha con la hora
  decorativa, selector del modo desarrollo, preferencia apagable.
- Filament: `.fi-body` pinta el fondo con `--gray-50` (oscuro: `--gray-950`); la barra lateral de escritorio es
  transparente, así que sigue al fondo sola.
- Nada de `pendientes.md` afecta.

## Decisiones

- **D1. Qué cambia:** el fondo de la página (`.fi-body`) y la barra superior (`--tq-barra-superior`). Nada más: botones,
  estados, texto, tarjetas, tablas, la barra lateral y la franja de la ambientación quedan como están.
- **D2. Misma claridad que hoy.** Fondo claro: L 0,985 (el `--gray-50`); oscuro: L 0,141 (el `--gray-950`). Barra clara:
  L 0,938 (la perla); oscura: L 0,30. Solo cambian el matiz y una croma de 0,008 a 0,018 (0,03 en la barra oscura, la de
  hoy es 0,045), recortada a la que la pantalla puede mostrar. Matices: verano 85 (arena), otoño 65 (ámbar), invierno 235 (hielo), primavera 150 (verde tierno).
- **D3. Mezcla de 15 días:** desde 15 días antes de un cambio de estación hasta 15 días después, el tinte pasa de una
  estación a la otra poco a poco (en oklab, para que el paso no dé la vuelta por otros tonos). El día del cambio va
  mitad y mitad. Las cajas siguen cambiando ese día, como en la 1.4r. Con una estación elegida en el modo desarrollo,
  sin mezcla: la estación pura.
- **D4. Un solo interruptor:** la preferencia de la 1.4r pasa a ser `apariencia.estacion`, «Apariencia según la
  estación», y `EstacionDeLasCajas` a `EstacionDeLaApariencia`. La 1.4r no está fusionada: no hay valores guardados
  que migrar.
- **D5. Todas las pantallas del panel:** el tinte va en la vista de navegación, que está en todas. Si estuviera solo en
  el inicio, el fondo cambiaría de golpe al entrar a un módulo.
- **D6. Regla «Tres toques»:** pasa a tener un cuarto, el tinte de la estación; la prohibición de cambiar botones y
  estados sigue igual.

## Pasos

- [x] 1. Un solo interruptor (D4): renombrar la preferencia y la clase; pruebas que la usan.
- [x] 2. `EntornoEstacion`: tinte del día con la mezcla (D2, D3) y su CSS en la vista de navegación (D1, D5).
  Pruebas: estación pura a mitad de estación, mitad y mitad el día del cambio, sin mezcla a 16 días, mezcla simétrica,
  la elección del modo desarrollo sin mezcla, preferencia apagada sin tinte, claridad igual a la de hoy y contraste
  del texto gris igual al de hoy, todo dentro de sRGB, y la página trae el estilo.
- [x] 3. Documentos: `interfaz.md` (cuarto toque, sección del inicio y de barras), mapa de etapas. Capturas.
- [x] 4. `composer check` en verde; plan a completados; PR.

## Criterio de aceptación

- `EntornoEstacionTest` y `CajasEstacionTest` en verde.
- En las cuatro estaciones, el fondo y la barra superior tienen otro tono y la misma claridad; entre estaciones, el
  cambio se reparte en un mes.

## Bitácora

- 2026-10-28 — Plan escrito con la propuesta B aprobada. Rama `interfaz-entorno-estacion`, apilada sobre
  `interfaz-cajas-estacion` (1.4r, sin fusionar) para no sacar la app de su rama.
- 2026-10-28 — Paso 2. La prueba de gama encontró que, con la claridad del fondo claro (0,985), el ámbar y el hielo solo
  admiten 0,008 de croma (la vista previa usaba 0,012 y 0,010, que el navegador recortaba): se bajaron y `colores()`
  recorta cualquier mezcla con `Oklch::cromaMaxima`. `BarrasNavegacionTest` revisa la perla con la preferencia apagada.
- 2026-10-28 — Paso 3, con capturas de la app real. En oscuro, la barra de verano y otoño se veía café y el fondo cálido:
  más que «bien sutil». Se bajó la croma oscura (fondo 0,010–0,012, barra 0,03) y el otoño pasó de matiz 55 a 65 (en
  claro se veía salmón, no ámbar).
- 2026-10-28 — Cierre. `composer check`: pint y phpstan sin errores, 42 migraciones bien, pest 1904/1904 (9177
  aserciones). Sin revisión de arquitectura: no hay cambios de estructura (sin migraciones, módulos ni efectos). Al ver
  las capturas, el desarrollador pidió un modo oscuro menos negro y cajas más livianas: es la 1.4t
  (`2026-10-28-interfaz-oscuro-atenuado.md`), apilada sobre esta rama.
