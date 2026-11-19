# Plan: 1.4r · Estilo de las cajas del inicio según la estación

- **Estado:** completado
- **Módulo(s):** Nucleo (interfaz del inicio)
- **Fase:** F1
- **Etapa:** 1 · tarea 1.4r · rama `interfaz-cajas-estacion` (desde `main`, 05d4c76). Adelantada antes de la 1.11, que
  quedó en pausa tras su paso 3 en `nucleo-ia`.
- **Creado:** 2026-10-28 · **Actualizado:** 2026-10-28

## Objetivo

Con la estación cambia el estilo completo de cada caja del inicio (fondo, degradado, franja, ícono y su ficha), siempre
con el matiz de su área. El nombre se lee (AA) en las cuatro estaciones y los dos temas, y una caja nueva hereda el
estilo sin declarar nada.

## Contexto que leí

- Pedido del desarrollador (2026-10-28), con los valores de la vista previa aprobada en otra conversación (el enlace no
  se puede leer desde aquí: se tomaron los del pedido).
- `Temporada::deLaFecha()` (hemisferio sur; verano desde el 21 dic, otoño desde el 20 mar, invierno desde el 21 jun,
  primavera desde el 22 sep), `ModoDesarrollo::ahoraDecorativo()` y sus piezas (1.4i), `ColoresMarca`, las vistas
  `widgets/caja-modulo` y `widgets/cajas-modulos` (1.4f, 1.4q) y las preferencias de Apariencia (1.4g).
- `docs/convenciones/interfaz.md`, «Inicio». Nada en `pendientes.md` lo bloquea.

## Decisiones

D1, D5 y D6 vinieron tomadas en el pedido; D2 a D4 y D7 a D9 las propuse y el desarrollador las aprobó el 2026-10-28
(«que se mantengan los estilos, pero más importante es que se vea el texto»).

- **D1 · Solo el inicio:** la barra lateral no toma el estilo de la estación.
- **D2 · Solo la estación:** las fechas especiales (Navidad, Año Nuevo, Semana Santa, Fiestas Patrias, aniversario) no
  cambian nada de las cajas. Sigue la hora de lo decorativo (`ModoDesarrollo::ahoraDecorativo()`, no
  `Calendario::ahora()` como decía el pedido: es la que cambia con la fecha simulada; sin el modo, son la misma).
- **D3 · Colores que la pantalla puede mostrar.** Un matiz por área, calculado del color de la marca en `ColoresMarca`
  (`matizDeArea`: dorado 78,4°, azul noche 264,5°, morado 283°, turquesa 215,9°). Claridad y croma por estación y tema
  en `EstiloEstacion` (los de la vista previa). La croma se recorta a la máxima que sRGB muestra con esa claridad y ese
  matiz: el dorado y el turquesa no llegan a 0,20 (el navegador la recortaba y el tono cambiaba). Las variables se arman
  por área (`--tq-e-*` en `.tq-area`), así que una caja nueva las hereda.
- **D4 · El azul del Núcleo sigue siendo azul noche:** croma × 0,65 y tonos medios (claridad 0,35–0,85) 0,08 más
  oscuros. Sin el ajuste, oklch(.62 .20 264) era azul rey.
- **D5 · Preferencia apagable** «Cajas del inicio según la estación» (Apariencia, `apariencia.estacion_cajas`),
  prendida por defecto; apagada, las cajas como antes.
- **D6 · Modo desarrollo:** la barra tiene «Estación: Primavera · Verano · Otoño · Invierno · Por fecha», un clic cada
  una, solo en la sesión. También es una pieza decorativa más (`estacion-inicio`).
- **D7 · Las cajas miden lo mismo:** la ficha del ícono es de 2,75 rem (no 48 px) y, con ficha, menos aire arriba y
  abajo, para que un nombre de dos líneas («Personas y empresas») quepa en los 6,5 rem de siempre.
- **D8 · Contraste del nombre.** Se revisa contra el fondo en los puntos del degradado donde cae el nombre (30 %, 50 %,
  70 % y 90 %). Con D3 y D4, el verano claro da entre 4,7:1 (turquesa) y 7:1 (Núcleo); las demás, más de 9:1. El
  brillo blanco del verano llega hasta la mitad de la caja y no pasa detrás del nombre.
- **D9 · El degradado del ícono** (otoño, invierno) usa un `linearGradient` con id propio por caja
  (`tq-trazo-<clave>`), en coordenadas del ícono (24 × 24): un trazo recto también lo toma.
- **Lo no especificado en la vista previa** (propio): el texto de otoño e invierno y su color en oscuro; el ícono de
  invierno va del color del área (0,45) a casi blanco (0,86) en claro y al revés en oscuro; otoño lleva franja sólida
  del color del ícono; la estrella de Mien, dorada con brillo en las cuatro.

## Pasos

- [x] 1. `Oklch`, `ColoresMarca::matizDeArea`, `EstiloEstacion`, `EstacionDeLasCajas`, la pieza `EstacionDelInicio` y la
      preferencia.
- [x] 2. Vistas: contenedor `tq-inicio-cajas` con `data-tq-estacion`, estilos por estación, degradado del trazo y marca
      de agua por caja; barra del modo desarrollo.
- [x] 3. Pruebas (`CajasEstacionTest`, ajuste de `CajasModulosTest`) y capturas de las cuatro estaciones en los dos temas.
- [x] 4. Mapa, `interfaz.md`, `composer check`, plan a completados y PR.

## Criterio de aceptación

- `CajasEstacionTest`: la estación por fecha con los bordes; una fecha especial no cambia nada; la preferencia apagada lo
  quita; una caja nueva de cualquier área lo hereda; ids únicos; AA en las 32 combinaciones; el matiz no cambia; ningún
  color fuera de pantalla; el Núcleo no pasa de croma 0,14; el modo desarrollo cambia la estación sin escribir en la base.
- La 1.4q (`CajasPreguntasTest`), el tamaño de las cajas y «reducir animaciones» siguen igual.

## Bitácora

- 2026-10-28 — Hecho de una vez a pedido del desarrollador. Las capturas se sacaron con el Chromium de Playwright sobre el
  inicio real (dibujado por la app como el usuario de desarrollo, dentro de una transacción que se deshace), recortando
  la zona de las cajas: la página completa queda oculta hasta que Livewire responde, y fuera de una sesión de navegador
  no responde. Primera ronda: los nombres de dos líneas se cortaban con la ficha (D7) y el Núcleo de verano tiraba a azul
  rey (D4); corregido y vuelto a revisar.
- 2026-10-28 — Primera corrida completa: falló `PreferenciasNuevasTest` (lista las preferencias de Apariencia y no traía
  la nueva); actualizado. Segunda corrida: pint, phpstan, migraciones y 1884 pruebas en verde.
