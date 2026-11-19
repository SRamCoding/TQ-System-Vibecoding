# Plan: saludo y frase según la hora y el horario de la empresa (1.4e)

- **Estado:** completado (2026-10-23)
- **Módulo(s):** Nucleo
- **Fase:** F1
- **Creado:** 2026-10-23 · **Actualizado:** 2026-10-23

## Objetivo

El saludo del inicio se siente natural: en horario, variado (casual o propio de la hora), nuevo en cada sesión y
estable mientras se navega; fuera de horario, uno especial que nota con naturalidad la hora, sin exagerar ni halagar.
La frase de abajo cambia una vez al día en horario; fuera de horario, 70 % una frase tranquila y 30 % la del día. Los
dibujos por piezas con luz de mañana, tarde y noche van aparte (1.4e2).

## Contexto que leí

- `docs/planes/mapa-etapas.md`, fila 1.4e (ampliada por el desarrollador el 2026-10-23).
- `Saludo/SaludoPorReglas` (hoy: saludo y frase por persona, día y momento; no repite la de ayer en el mismo momento;
  idioma por día y momento; días de versículo; la fecha especial manda), `Enums/MomentoDia` (mañana 5–12, tarde
  12–19, noche), modelo `FraseSaludo` (tipo, momento, día de la semana, área, ocasión), `Tiempo/CalendarioLaboral`.
- `docs/convenciones/interfaz.md`, «Saludo y ambientación de la fecha»; memoria: contenido respetuoso del cristianismo.

## Decisiones

- **D1 · Dos entregas** (desarrollador, 2026-10-23): esta, saludo y frase; la **1.4e2**, los dibujos por piezas, que
  cambian cada día y se ven con la luz de la hora (**el mismo dibujo** con cielo de mañana, tarde o noche), empezando
  por una estación de muestra.
- **D2 · Horario de la empresa**, parámetros editables: de 8:00 a 19:00, de lunes a viernes. **Fuera de horario:**
  antes de la hora de inicio, desde la de fin, sábados, domingos y feriados (los días que Techquk no trabaja, de
  `CalendarioLaboral`).
- **D3 · En horario, el saludo** mezcla casuales («Hola, Ana») y propios de la hora («Buenos días, Ana»; solo en su
  momento), **mitad y mitad** (parámetro editable). Se elige **al iniciar sesión** y se queda mientras se navega; otro
  si la sesión cambia de momento (mañana → tarde) o entra o sale del horario. La fecha especial sigue mandando.
- **D4 · La frase** en horario, **una por día** (ya no por momento). **Fuera de horario**, **70 % una frase especial
  tranquila y 30 % la del día** (parámetro editable). Los días de versículo siguen en horario.
- **D5 · Los textos especiales los propone el agente y los revisa el desarrollador** antes de guardarlos: unos 20
  saludos y 15 frases para fuera de horario, naturales, sin exagerar emociones ni halagar; algunos versículos
  apropiados (descanso, paz). Quedan en el catálogo de frases, editables.
- **Marca de fuera de horario en las frases:** una columna nueva `fuera_de_horario` en `frases_saludo` (texto, nula =
  frase de horario normal), con CHECK de sus valores: `temprano` (antes de la hora de inicio), `noche` (desde la de
  fin), `fin_de_semana`, `feriado` o `cualquiera` (sirve en las cuatro). Así «Qué temprano» no sale de noche. La
  identidad de la sesión: una semilla que se crea al iniciar sesión.

## Pasos

- [x] 1. Plan aprobado (D1 a D5) y mapa (1.4e y 1.4e2).
- [x] 2. Textos propuestos para fuera de horario (y los casuales que falten), revisados por el desarrollador.
- [x] 3. Horario: parámetros, `HorarioEmpresa` (en horario o no, con los feriados), columna `fuera_de_horario`.
- [x] 4. Elección: semilla de sesión, franja (mañana, tarde, noche, fuera), mezcla casual/de la hora, frase diaria y
      la de fuera de horario; pruebas con los números.
- [x] 5. Carga de los textos aprobados en el seeder de frases.
- [x] 6. Documentos: `interfaz.md`, mapa, `cambios.md`.
- [x] 7. Revisión a la vista, `revisor-arquitectura`, `composer check`, `/cerrar-tarea` y PR.

## Criterio de aceptación

- Un martes a las 10:00: saludo casual o de la mañana (nunca «Buenas noches»); el mismo en dos páginas de la misma
  sesión; otro en una sesión nueva (con semillas distintas); la frase es la misma toda la jornada y otra al día
  siguiente.
- A las 19:30, un sábado, un domingo y un feriado: saludo de fuera de horario; la frase, especial o la del día según
  el 70/30 (con semillas fijas, la proporción se comprueba sobre muchas sesiones).
- La sesión que pasa de 18:55 a 19:05 cambia a un saludo de fuera de horario; de 11:55 a 12:05, a uno de la tarde o
  casual.
- Navidad a las 21:00: manda el saludo de Navidad.
- Los parámetros cambian el horario y los porcentajes.

## Bitácora

- 2026-10-23 — Plan escrito con el desarrollador (D1 a D5).
- 2026-10-23 — Paso 2: el desarrollador aprobó la lista (20 saludos por situación, 8 frases y 5 versículos). Sin
  respuesta sobre inglés y francés: por ahora solo en español (sin especial en el idioma de la sesión, en español).
  Cuatro ya existían («El descanso también es parte del trabajo bien hecho» y Salmos 4:8, Mateo 11:28 y Eclesiastés
  3:1): no se duplican; los de noche existentes ya salen fuera de horario.
- 2026-10-23 — Pasos 3 a 6. Migración `2026_10_23_000003` (`frases_saludo.fuera_de_horario` con CHECK), enum
  `FueraDeHorario`, `Tiempo/HorarioEmpresa` y validadores de hora y días; cinco parámetros (`horario_inicio`,
  `horario_fin`, `horario_dias`, `saludo_porcentaje_de_la_hora`, `saludo_porcentaje_frase_especial`); campo «Solo
  fuera de horario» en el catálogo de frases. `Saludo/SemillaSesion` (nueva en `Login`) y `GeneradorSaludo::para(...,
  $sesion)`; `SaludoPorReglas` reescrito en sus reglas 4, 5 y 7 (saludo de sesión con mezcla, frase del día sin las de
  un momento, fuera de horario con su 70 %); el idioma ahora es de la sesión. Hallazgo: unas 18 frases «de la mañana»
  o «de la tarde» dejan de salir (cambiarían a mediodía); se pueden pasar a «cualquier hora» desde el catálogo; se le
  avisó al desarrollador. Pruebas: `SaludoHorarioTest` (10) y `SaludoPorReglasTest` ajustado (frase del día, idioma por
  sesión, el sábado de horario en la de versículos). Documentos: `interfaz.md`, `cambios.md` (Común 0.3.9).
- 2026-10-23 — Paso 7, revisión de `revisor-arquitectura`. Atendido: Éxodo 33:14 con su texto completo («Y él dijo:»)
  e Isaías 40:31 ya existía (no se duplica: quedan solo Éxodo 33:14 como versículo nuevo); la frase de una fecha
  especial manda también fuera de horario (Navidad en feriado); **el idioma vuelve a ser de la persona y el día** (por
  sesión, con inglés o francés la frase cambiaba en cada sesión: se había decidido sin preguntar); de madrugada (antes
  de las 5:00) es «noche»; `CalendarioLaboral::noTrabaja()` ve el feriado también en un sábado laborable; CHECK
  `frases_saludo_fuera_de_horario_solo_check` (ni agradecimiento, ni fecha especial, ni momento) con su mensaje en
  `ReglasFrase`; las especiales respetan área y día de la semana; los días laborables mal escritos se rechazan; se quitó
  «Todavía por aquí, {nombre}.», casi igual a uno que ya existía. Sin cambio, avisado: «Hoy es feriado» también sale en
  los días libres de la empresa, y un día entre semana quitado del horario saluda como fin de semana; no se valida que
  el inicio sea anterior al fin (lo dice la ayuda del parámetro).
- 2026-10-23 — Paso 7 hecho. `composer check` en verde (1525 pruebas). Plan cerrado; pasa a `completados/`. Sigue la
  1.4e2 (dibujos por piezas con la luz de la hora), empezando por una estación de muestra.
- 2026-10-23 — Después del cierre, pedido del desarrollador: los saludos de fuera de horario también en inglés (9) y
  francés (9, sin género), con 3 frases tranquilas en cada idioma. «Hoy es feriado» se queda como está (decisión suya).
