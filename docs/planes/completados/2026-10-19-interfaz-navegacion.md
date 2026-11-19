# Plan: 1.3d · Interfaz: navegación (cambio de modo sin recargar, barra superior y barra lateral ajustable)

- **Estado:** terminado
- **Módulo(s):** Nucleo (navegación, preferencias) y panel `app`
- **Etapa:** 1 · tarea 1.3d · rama `interfaz-navegacion`
- **Creado:** 2026-10-19 · **Actualizado:** 2026-10-19

## Objetivo

1. ~~Cambio de modo sin recargar~~: descartado por el desarrollador (D1).
2. La barra superior (del logo al avatar) tiene fondo propio: perla en modo claro, un tono que contrasta en modo
   oscuro, los dos desde `ColoresMarca`.
3. En modo paneles, el borde de la barra lateral se arrastra para cambiar su ancho, hasta dejar solo los íconos. El
   ancho se recuerda por persona.

## Contexto que leí

- `docs/planes/mapa-etapas.md` (fila 1.3d), plan terminado de la 1.3c, `docs/convenciones/interfaz.md` («Cambio de
  modo»), ADR 0010 y 0017.
- Código: `AppPanelProvider` (hoy `->navigation()` depende del modo, así que en modo modular Filament no dibuja la
  barra lateral, y la acción de cambio termina con `redirect` al inicio), `Inicio::getWidgets()` (elige un widget
  según el modo), `CambiarModoNavegacion`, `PreferenciasUsuarioService::cambiarModo()`, `ColoresMarca`, la vista
  `transicion-modos` y el logo (en modo oscuro tiene piezas azul noche y bloque blanco).
- Filament 5.8: `layout/index.blade.php` dibuja la barra lateral solo si `hasNavigation()`; la barra lateral usa
  `--sidebar-width` y, si es plegable en escritorio, `--collapsed-sidebar-width` y `$store.sidebar` (solo íconos).
- `docs/decisiones/pendientes.md`: nada afecta.

## Decisiones (aprobadas por el desarrollador, 2026-10-19)

- **D1 (descartada). Cambio de modo sin recargar.** Pedía tener siempre en la página la barra lateral y los dos
  inicios, y que el modo fuera una marca en `<html>`. El desarrollador prefirió dejar el cambio de modo como está:
  **el punto 1 sale de la 1.3d** (sigue recargando y yendo al inicio; la animación de la 1.3c no se toca).
- **D4. Color de la barra superior.** `ColoresMarca::PERLA` para modo claro y `ColoresMarca::barraSuperiorOscura()`
  para modo oscuro: un gris azulado (matiz del azul noche) más claro que el fondo, no el azul noche puro, porque las
  piezas azul noche del logo desaparecerían sobre él. Valores exactos tras ver la vista previa.
- **D5. Ancho de la barra lateral por persona, en la base.** Columna nueva en `users`: `ancho_barra_lateral`
  (`smallint NOT NULL DEFAULT 320`, en píxeles; `CHECK`: `0` o entre los límites de D6). Se guarda con una acción registrada
  `nucleo.cambiar-ancho-barra-lateral` (sin permiso: es su preferencia; la última escritura gana, sin versión, como
  el modo). Se guarda al soltar el borde, no mientras se arrastra.
- **D6. Límites y modo íconos.** Ancho normal entre 200 y 480 px. Si se suelta por debajo de 140 px, queda en modo
  íconos (el plegado de Filament, `sidebarCollapsibleOnDesktop`, ~72 px) y se guarda como `0`. Desde íconos, arrastrar
  hacia afuera la vuelve a abrir. **Cambio pedido al revisar las capturas:** sin botón de plegar; la flecha de Filament se esconde y la barra se pliega solo arrastrando. Solo en pantallas anchas (≥ 1024 px);
  en el celular la barra sigue siendo la que se abre encima.
- **D7. Una sola vista de navegación.** El arrastre y los estilos nuevos viven en una vista
  `nucleo::filament.navegacion` (junto a `transicion-modos`), puesta por el proveedor del panel. Sin paquetes
  JavaScript nuevos: Alpine, que ya trae Filament.

## Pasos

- [x] 1. Plan, rama y aprobación de decisiones.
- [x] 2. Barra superior con fondo propio (D4). Vista previa en claro y oscuro.
- [x] 3. Migración, acción y servicio del ancho (D5, D6). Pruebas de límites y de la acción.
- [x] 4. Borde arrastrable (D6, D7). Vista previa.
- [x] 5. `docs/convenciones/interfaz.md` al día; capturas para revisión del desarrollador.
- [x] 6. Revisión de `revisor-arquitectura` y `composer check` en verde.

## Criterio de aceptación

- `tests/Modules/Nucleo/TransicionModosTest.php` sigue verde (la barra lateral nueva no rompe los nombres de la 1.3c).
- Pruebas nuevas de la acción y los límites del ancho.
- El ancho elegido se ve igual al entrar desde otro navegador con la misma cuenta.

## Bitácora

- 2026-10-19 — Rama `interfaz-navegacion` desde `main` (9ed67ac). Plan escrito; decisiones en revisión.
- 2026-10-19 — D1 descartada: el cambio de modo queda como está; el punto 1 sale de la tarea. D4–D7 aprobadas tal como se propusieron.
- 2026-10-19 — Paso 3 (antes que el 2: el arrastre depende de él). Migración nueva `2026_10_19_000003` con
  `Schema::table` (no se editó la de `users`: así no hay que reconstruir la base de desarrollo). Límites en
  `Navegacion\BarraLateral`; el servicio rechaza con `PreferenciaRechazada` y la base con su `CHECK`. El ancho no va a la
  bitácora (`camposSinBitacora`). DBML: columna anotada en `users`.
- 2026-10-19 — Pasos 2 y 4 en un solo commit: comparten la vista `nucleo::filament.navegacion`. Desviación de D7: el
  borde es un componente Livewire propio (`Filament\BordeBarraLateral`, puesto en `SIDEBAR_START`) con su vista
  `borde-barra-lateral`, porque guardar al soltar necesita una petición; `navegacion` lleva los estilos y el estado
  inicial. El estado plegado lo guarda Filament en `localStorage` (`_x_isOpen`, `_x_isOpenDesktop`): la vista lo
  sobrescribe en el `<head>` con lo que dice la base, antes de que Alpine lo lea. `sidebarCollapsibleOnDesktop()`
  agrega la flecha de plegar a la izquierda del logo. Probado en el navegador (Playwright, usuario inventado en la base
  de desarrollo): arrastrar a 240, plegar soltando a 100, sigue plegada al recargar, abrir arrastrando a 400 y otro
  navegador con la misma cuenta entra con 400. Perla `#EEEAE2`; oscuro `oklch(0.30 0.045 264.466)`.
- 2026-10-19 — Paso 5. `interfaz.md`: sección «Barras» y `fi-topbar` anotada como segunda excepción a «nunca clases
  internas de Filament». Vista previa con capturas publicada para el desarrollador
  (https://claude.ai/artifact/KcuMjHzcpyDtbpzBr4TCVZ); revisión de arquitectura pedida.
- 2026-10-19 — Revisión de `revisor-arquitectura`: sin bloqueantes. Atendido: (1) los detalles internos de Filament de
  los que depende la barra plegada, anotados en `interfaz.md` para revisarlos al actualizar Filament; (3) el borde ya
  no guarda al cargar la página, solo al soltar o al usar el botón de plegar (no hay registro de acciones ejecutadas
  ni bitácora del ancho, así que no hay volumen que cuidar); (4) prueba que compara el `CHECK` de la base con
  `BarraLateral`. Pendiente: (2) confirmar con el desarrollador el cambio de D7 (componente Livewire aparte).
- 2026-10-19 — El desarrollador aprueba D7 con el componente aparte, siempre que guardar el ancho pase por una acción
  registrada con mínimo y máximo validados en el servidor. Ya es así: `BordeBarraLateral::guardar()` solo llama a
  `EjecutorAcciones` con `nucleo.cambiar-ancho-barra-lateral`; el servicio rechaza lo que no sea 0 o 200–480
  (`PreferenciaRechazada`) y la base lo vuelve a impedir con su `CHECK`.
- 2026-10-19 — El desarrollador aprueba los colores (perla `#EEEAE2`, oscuro `oklch(0.30 0.045 264.466)`). No quiere
  plegar la barra con un clic: se esconde la flecha de Filament (`fi-topbar-collapse-sidebar-btn-ctn`, tercera
  excepción anotada en `interfaz.md`). El botón del celular es otro y sigue.
- 2026-10-19 — Cierre. `composer check` en verde: pint y phpstan sin errores, Pest 1142 pruebas / 5160 aserciones.
  Usuario inventado de las capturas borrado de la base de desarrollo. Qué sigue: nada de esta tarea; si algún día se
  quiere el cambio de modo sin recargar (D1), la idea descartada queda descrita arriba.
