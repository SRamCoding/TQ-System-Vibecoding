# Plan: dibujos de la fecha por piezas, diarios y con la luz de la hora (1.4e2)

- **Estado:** en curso (decisiones aprobadas 2026-10-23)
- **Módulo(s):** Nucleo
- **Fase:** F1
- **Creado:** 2026-10-23 · **Actualizado:** 2026-10-23

## Objetivo

El dibujo junto al saludo cambia cada día (el mismo para todos, nunca igual dos días seguidos) y se ve con la luz de
la hora: el mismo dibujo con cielo de mañana, de tarde o de noche. Cada dibujo se arma con piezas (fondo, motivo y
acompañante), así hay cientos de combinaciones por estación sin dibujar cientos de escenas. Antes de hacer todas las
piezas, una muestra de primavera para aprobar el estilo.

## Contexto que leí

- `docs/planes/mapa-etapas.md`, fila 1.4e2 (decidido por el desarrollador el 2026-10-20 y separado de la 1.4e).
- `Saludo/Ambientacion` (elige la variante del día), `RegistroAmbientaciones` (cinco variantes por tema, una por
  año), `VarianteAmbientacion` (vista SVG y colores de la franja), vistas `filament/ambientacion/*` (64×64, animación
  dentro de `.tq-corre`, quietas con animaciones reducidas), `docs/convenciones/interfaz.md` («Saludo y ambientación»),
  el modo desarrollo (fecha simulada y cambiar en el sitio).
- Memoria: contenido respetuoso del cristianismo (sin Halloween ni santos).

## Decisiones

- **D1 · Muestra: primavera** (desarrollador, 2026-10-23), la estación de ahora en Chiclayo.
- **D2 · Estilo: el de ahora, más trabajado** (desarrollador, 2026-10-23): plano y limpio, con fondo de cielo,
  sombras suaves y piezas que se combinan. SVG en línea, sin imágenes ni librerías.
- **D3 · Tamaño: 80 px** junto al saludo (64 px en pantalla angosta), en vez de 56.
- **D4 · El mismo dibujo para todos** ese día (desarrollador, 2026-10-23).
- **Cómo se arma** (fila del mapa): por estación, motivos (unos 20), acompañantes (unos 6) y fondos (unos 5); reglas
  que descartan combinaciones absurdas (por ejemplo, una abeja sin flor). El día elige la combinación con un contador
  continuo de días (no se reinicia al cambiar de año) y un paso que recorre todas antes de repetir, sin repetir la de
  ayer. La luz sale del momento del día del saludo (mañana, tarde, noche; la madrugada es noche), con la hora
  decorativa (`ModoDesarrollo::ahoraDecorativo()`).
- **D5 · El clima de donde está cada computadora** (desarrollador, 2026-10-23): el dibujo trae capas de clima
  (despejado, nublado, lluvia, llovizna, niebla, tormenta) y el navegador prende la que corresponde. El navegador pide
  permiso de ubicación una vez; si se niega, usa la ubicación de respaldo (parámetro, Chiclayo). **La ubicación no va a
  nuestro servidor ni se guarda**: el navegador consulta directo a Open-Meteo (gratuito, sin clave) con la posición
  **redondeada a un decimal (unos 10 km)** y recuerda el resultado 30 minutos en ese navegador. Sin internet o si
  falla, el dibujo se ve sin clima. Se apaga con un parámetro. Anotado en `pendientes.md` para que Gerencia y TI lo
  sepan. El modo desarrollo deja elegir el clima a mano.
- **Mientras una estación o fecha no tenga piezas**, sigue con sus variantes de hoy (`RegistroAmbientaciones`): no se
  pierde nada mientras se completan.

## Pasos

- [x] 1. Plan aprobado (D1 a D4).
- [x] 2. Mecanismo: registro de piezas por tema, reglas de combinación, elección diaria, luz de la hora, capas de
      clima con su consulta desde el navegador (D5), tamaño; y una **muestra de primavera** (pocas piezas), con el clima
      a elegir en el modo desarrollo.
- [x] 3. Aprobación del estilo por el desarrollador (capturas de mañana, tarde y noche).
- [x] 4. Primavera completa (motivos, acompañantes, fondos).
- [x] 5. Las otras tres estaciones.
- [x] 6. Las fechas especiales por piezas.
- [x] 7. Documentos: `interfaz.md`, mapa, `cambios.md`.
- [x] 8. Revisión a la vista, `revisor-arquitectura`, `composer check`, `/cerrar-tarea` y PR.

## Criterio de aceptación

- Dos días seguidos nunca traen la misma combinación; recorre todas antes de repetir; el 31 de diciembre y el 1 de
  enero siguen el mismo contador.
- El mismo día, todas las personas ven la misma combinación; a las 9:00, 15:00 y 21:00, el mismo dibujo con su luz.
- Ninguna combinación prohibida sale nunca (prueba sobre todas las combinaciones).
- Con animaciones reducidas, quieto; una estación sin piezas sigue con sus variantes.

## Bitácora

- 2026-10-23 — Plan escrito con el desarrollador (D1 a D4).
- 2026-10-23 — D5 a pedido del desarrollador (el clima de cada computadora), con privacidad: consulta del navegador a
  Open-Meteo con la posición redondeada, sin pasar por el servidor; pendiente para Gerencia y TI; requisitos del
  servidor al día.
- 2026-10-25 — Paso 2. `Saludo/Piezas/` (`TipoPiezaDibujo`, `PiezaDibujo` con etiquetas y exigencias,
  `CombinacionDibujo`, `RegistroPiezasDibujo` con las combinaciones válidas y el dibujo del día por contador continuo y
  paso coprimo, `PiezasDeLaFecha`). `VarianteAmbientacion` suma `datos`; `Ambientacion::delTema()` arma el dibujo por
  piezas si el tema las tiene (si no, la variante de siempre) y la luz entra en su caché. Vista
  `filament/ambientacion/compuesto` (lienzo 80 × 80: cielo por luz, sol o luna y estrellas, cielo que se agrisa,
  capas de clima escondidas y el script del navegador con Open-Meteo, redondeo a un decimal, 30 minutos en
  `localStorage`, respaldo sin permiso) y nueve piezas de primavera (2 fondos, 4 motivos, 3 acompañantes: 30
  combinaciones). Parámetros `interfaz.clima_activo` y `clima_ubicacion_respaldo` (con `ValidadorCoordenadas`); pieza
  «Clima del dibujo» en el modo desarrollo; 80 px (64 en celular). `DibujosPiezasTest` (7). Muestra entregada al
  desarrollador (capturas de las tres luces y cinco climas).
- 2026-10-25 — Paso 3: el desarrollador aprobó el estilo («están bien»). Paso 4: primavera completa, 5 fondos (colinas,
  jardín, campo, laguna, sendero), 20 motivos y 6 acompañantes (abeja, mariquita y colibrí solo con flores): 640
  combinaciones, casi dos años sin repetir. Revisadas a la vista.
- 2026-10-26 — Paso 5. Verano (playa, dunas, piscina, malecón, cañaveral; caballito de totora, pelícano, raspadilla…:
  358), otoño (sin calabazas; árbol dorado, uvas, choclos, búho…: 560) e invierno de costa con un toque de sierra
  (chullo, emoliente, pingüino de Humboldt, llama…: 540). Los motivos también pueden exigir algo del fondo
  (`RegistroPiezasDibujo::combinaciones`: la tabla de surf, el mar). Ojo aprendido: una pieza animada no lleva su
  posición en el mismo grupo que la animación (la animación pisa el `transform`): se envuelve en un grupo exterior.
  Revisadas a la vista con una galería por estación. El desarrollador aprobó la lista de piezas de las fechas
  especiales (con las religiosas en silueta y respetuosas).
- 2026-10-26 — Pasos 6 y 7. Fechas especiales por piezas con la lista aprobada: Navidad (61 combinaciones; el
  nacimiento y el ángel en silueta y sin rostros; pastores y Reyes Magos solo en el pueblito), Semana Santa (20, sobria:
  la cruz vacía con lirios, palmas, Biblia, sepulcro vacío, amanecer; solo la paloma de acompañante), Año Nuevo (40),
  Fiestas Patrias (36), Día de la Madre (24), Día del Padre (24), cumpleaños (24) y aniversario (32, con la T del logo).
  Lo que sirve a varias fechas (sala, banderines, confeti, serpentinas, estrellitas, corazoncitos) va en
  `piezas/comunes`. Documentos: `interfaz.md`, mapa y Común 0.3.10.
- 2026-10-26 — Revisión de `revisor-arquitectura`: corregidos la prueba de «tema sin piezas» (ahora con un registro
  vacío), los seis `@keyframes` repetidos entre temas (con prueba de nombres únicos), la franja de las fechas (conserva
  sus colores; las estaciones siguen la luz) y el clima que se perdía si Livewire redibujaba el encabezado. La fila del
  clima en pendientes dice que viene encendido e informa a Gerencia y TI. **Queda para después:** el modo desarrollo
  todavía solo elige variantes de `RegistroAmbientaciones` (`DibujoDelSaludo`, `VistaPreviaFechas`); falta poder elegir
  una combinación de piezas. El punto de extensión está listo: `RegistroPiezasDibujo::combinaciones()` y la clave
  `piezas:<fondo>-<motivo>-<acompañante>` de la variante.
- 2026-10-26 — Paso 8. `composer check` en verde (1537 pruebas). La prueba de Año Nuevo «no cambia de variante entre
  diciembre y enero» ahora corre con un registro de piezas vacío: cubre la variante por año, que solo usan los temas
  sin piezas. Plan terminado.
