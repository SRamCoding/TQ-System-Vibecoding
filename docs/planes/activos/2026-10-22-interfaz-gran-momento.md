# Plan: 1.4d · Interfaz: ambientación de pantalla completa y gran momento de las fechas

- **Estado:** **en pausa tras el paso 5** (2026-10-23)
- **Módulo(s):** Nucleo (calendario laboral, ambientación, gran momento) y panel `app`
- **Etapa:** 1 · tarea 1.4d · rama `interfaz-gran-momento` (desde `main`, 3a3b9c2)
- **Creado:** 2026-10-22 · **Actualizado:** 2026-10-22
- **Meta:** idealmente lista el 10 de diciembre de 2026 (Navidad empieza el 15). Deseable, no obligatoria: el
  desarrollador aclaró el 2026-10-23 que todavía no se sabe si el sistema estará en uso este año.

## Objetivo

En las fechas con logo propio, el sistema acompaña sin estorbar: una ambientación de pantalla completa de 10 segundos
que no tapa ni bloquea, y una vez al año por persona un «gran momento» de 30 segundos con su escena y un versículo, que
nadie se pierde por no entrar el día justo (dentro de un plazo por fecha). En esta tarea, todo el mecanismo para las
cinco fechas, los feriados y las variantes de Navidad y Año Nuevo; Semana Santa, Fiestas Patrias y aniversario, en la
1.4d2.

## Contexto que leí

- `docs/planes/mapa-etapas.md` (fila 1.4d con las decisiones del 2026-10-19, 10-21 y 10-22; orden de trabajo).
- `docs/convenciones/interfaz.md` («Saludo y ambientación de la fecha», «Preferencias»), `codigo.md`
  («Preferencias por usuario»), `base-de-datos.md`, `docs/decisiones/pendientes.md` (aniversario sin fecha).
- Código: `Saludo/CalendarioFestivo` (ventanas, Domingo de Resurrección), `Saludo/Ambientacion`,
  `RegistroAmbientaciones` y `VarianteAmbientacion`, `database/data/frases-saludo.php` (versículos en español por
  fecha: Navidad 3, Año Nuevo 3, Semana Santa 3, Fiestas Patrias 2, aniversario 2), `Tiempo/Calendario`,
  `Filament/Apariencia` (reducir animaciones, 1.4g).
- No hay calendario de feriados: hoy solo se distinguen fines de semana.
- Pendientes que afectan: la fecha del aniversario (Gerencia); sin ella no hay gran momento de aniversario. No bloquea.

## Decisiones (aprobadas por el desarrollador, 2026-10-22)

- **D1 · Alcance y orden.** La 1.4d trae todo el mecanismo para las cinco fechas (plazos, feriados, «Lo vi», variantes
  por año, versículo, versión quieta, ambientación de pantalla completa, vista previa, preferencias) y los dibujos de
  **Navidad y Año Nuevo**: **dos variantes por fecha**, con el mecanismo abierto a más. Semana Santa, Fiestas Patrias y
  aniversario van en la **1.4d2** (fila nueva del mapa), antes de Semana Santa de 2027. Meta: todo listo el 10 de
  diciembre de 2026. Los feriados entran en este tramo: en 2026 el 25 de diciembre y en 2027 el 1 de enero caen
  viernes y son feriados (primer día hábil: lunes 28 y lunes 4).

- **D2 · Feriados y día hábil.** Catálogo editable `feriados` (en Configuración, como los demás): una fila por fecha
  concreta (fecha única, nombre, **tipo**). Tipos: **feriado nacional** (obliga al sector privado: nunca hábil), **día
  no laborable** decretado por el gobierno (hábil salvo que la empresa lo dé: sí/no «Techquk no trabaja», en no por
  defecto) y **día libre de la empresa** (nunca hábil). La base garantiza que un feriado nacional o un día libre de la
  empresa cuenten siempre como no hábiles. Día hábil = lunes a viernes y no marcado como no trabajado:
  **`CalendarioLaboral::esHabil()`** y **`CalendarioLaboral::primerHabilDesde()`**, aparte de `CalendarioFestivo`,
  para reusar en plazos, tareo o planilla. Sin feriados cargados para un año, solo cuentan los fines de semana. El
  seeder carga los feriados nacionales de 2026 y 2027. En `pendientes.md`: que Contabilidad o RRHH confirmen la lista
  con gob.pe, digan qué días no laborables da la empresa y carguen cada diciembre los del año siguiente.

- **D3 · Día clave, día de mostrarse y plazo.** Día clave: Navidad 25 dic; Año Nuevo 1 ene; Semana Santa, Domingo de
  Resurrección; Fiestas Patrias 28 jul; aniversario, el parámetro `interfaz.aniversario_techquk`. Se muestra desde el
  día clave si es hábil o desde `primerHabilDesde()`. **Plazo: un parámetro por fecha, en días después del día clave**
  (editable en Configuración, de 0 a 30): Navidad 6, Año Nuevo 6, Semana Santa 7, Fiestas Patrias 7, aniversario 7.
  El plazo no se mueve con los feriados. Si el primer día hábil cae después del plazo, ese año no hay gran momento.

- **D4 · «Lo vi».** Tabla `momentos_vistos`: `user_id` (FK con índice), **`gran_momento_id`** (FK con índice a
  `grandes_momentos`), `auditoria()` (su `created_at` es el momento de la marca: no hay `visto_at`), único
  `(user_id, gran_momento_id)`. No va a la bitácora. Acción registrada **`nucleo.marcar-momento-visto`** (campo: el id
  del gran momento), sin permiso; **no recibe `user_id`: siempre es quien inició sesión** (con prueba). Solo acepta el
  gran momento vigente para esa persona; idempotente; **devuelve si fue la primera marca**. Cuenta como visto al
  empezar con la pestaña visible; si la marca falla, vuelve a salir en la siguiente entrada dentro del plazo.
  **Selección:** de los grandes momentos vigentes (entre su día de mostrarse y su plazo) se toma **solo el más
  reciente**; si ya lo vio, no sale nada, aunque quede uno más antiguo sin ver.

- **D5 · Cuándo y cómo aparece el gran momento.** En la primera página que se abra mientras haya uno vigente sin ver
  (recién entrando o con la sesión ya abierta); nunca en el ingreso. Capa a pantalla completa de **30 segundos** con
  **su propia escena y su fondo**; mientras dura, la ambientación de la ventana en curso se pausa y se esconde (el 28 de
  diciembre, el de Navidad lleva su escena navideña, no los fuegos de Año Nuevo). «Cerrar» visible desde el primer
  segundo, Esc o los 30 segundos; mientras está abierto, lo de abajo no recibe clics. Diálogo (`role="dialog"`,
  `aria-modal`) con su nombre; **el foco queda atrapado dentro**; al cerrar vuelve a donde estaba y la ambientación se
  reanuda. El versículo, en texto. Con animaciones reducidas, la misma escena **quieta**, con el versículo, «Cerrar» y
  30 segundos, y cuenta como visto. En el celular, igual. Sin sonido.
  **Orden al volverse visible la pestaña:** primero se marca y **solo se empieza si el servidor dice que fue la primera
  marca**; si ya estaba visto (otra pestaña), la capa se quita sin mostrarse.
  **WCAG 2.3.1:** nada parpadea más de tres veces por segundo ni da destellos grandes.

- **D6 · Ambientación de pantalla completa durante la ventana.** Navidad (15-25 dic): nieve suave; Año Nuevo (26 dic-2
  ene): fuegos. **Dura 10 segundos** (los dos últimos, desvaneciéndose) y sale en la primera página que se abra cuando
  ya pasaron **X minutos** desde la última vez (hora guardada en la sesión); **nunca a mitad de una página**; sin bucle.
  X es el parámetro **`interfaz.ambientacion_minutos_entre`** (editable, de 5 a 1440, 15 por defecto). No recibe clics
  (`pointer-events: none`), queda por debajo de las ventanas de Filament y del gran momento, liviana (pocas partículas,
  transparentes; menos en el celular). Fuegos: estallidos pequeños de color, nunca un destello blanco ni de pantalla
  completa, separados al menos medio segundo, en la franja de arriba (WCAG 2.3.1). SVG y CSS en la vista, sin
  librerías; 30 a 40 partículas armadas en el servidor. No sale: con la ambientación apagada (empresa o persona), **con
  animaciones reducidas**, en el ingreso, ni con el gran momento abierto. Semana Santa (solemne en Jueves y Viernes
  Santo), Fiestas Patrias y aniversario: en la 1.4d2.
  **Excepción en `interfaz.md`** a «nada encima del contenido»: no tapa, no bloquea, dura 10 segundos y se repite como
  máximo cada X minutos, solo al abrir una página.

- **D7 · Variantes y versículo, elegidos y guardados por año.**
  - **`RegistroGranMomento`** (como `RegistroAmbientaciones`): cada variante, una vista con su escena de 30 segundos
    (SVG y CSS en línea) y una línea con la fecha, la clave y el título del diálogo. Agregar una variante es una vista y
    una línea.
  - **Tabla `grandes_momentos`:** ocasión, año (el del día clave), clave de la variante y versículo (FK con índice a
    `frases_saludo`, nula si no hubo), `auditoria()`, único `(ocasion, anio)`. Se elige la primera vez que hace falta
    ese año y queda fija: el mismo para todas las personas («insert … on conflict do nothing»).
  - **Elección:** rotación por año (año + desfase de la fecha, sobre las variantes registradas o los versículos
    activos) **saltando la variante y el versículo guardados el año anterior**; sin fila del año anterior, la rotación
    tal cual. Si la variante guardada **del año en curso** ya no existe en el registro, se elige otra y se actualiza la
    fila (con bloqueo); si es la del año anterior, no hay nada que saltar. **Prueba:** al sumar una variante o cambiar
    los versículos activos, no se repite la del año anterior.
  - **Versículo:** los versículos en español de esa fecha (categoría versículos, activos, por código), ya cotejados con
    RVR1960; en español aunque la persona tenga otros idiomas en su saludo. Sin versículos activos, solo el título.
  - **Tono:** Navidad, el nacimiento: una variante **en silueta** (establo, estrella y figuras sin rostro, a contraluz) y
    otra con la estrella de Belén sobre un pueblo dormido. Año Nuevo, gratitud y esperanza: fuegos sobre un horizonte
    de ciudad y un amanecer con el calendario que cambia; sin destellos. Nada contrario al cristianismo, sin santos.
  - **Semana Santa (1.4d2):** el gran momento es alegre, de la Resurrección; la 1.4d2 decide cómo elegir solo los
    versículos de la Resurrección.

- **D8 · Vista previa.** Página en Configuración, «Vista previa de fechas», **solo en desarrollo** (ambiente `local`; en
  producción no aparece ni se abre por URL: cambio del desarrollador, 2026-10-22, porque las variantes son código y no
  se cargan desde la pantalla), para quien tiene `parametros.editar`:
  elige fecha, variante y año; ve la versión animada y la quieta del gran momento y la ambientación de pantalla
  completa de esa fecha; muestra el versículo que tocaría ese año. No marca «Lo vi» ni guarda nada. **Orden:** primero
  la vista previa con la variante de Navidad en silueta, que se entrega al desarrollador; mientras la revisa, se sigue
  con el mecanismo; **solo las otras tres variantes esperan su aprobación**.

- **D9 · Ambientación apagada.** Quien apagó «Ambientación de la fecha» (o la empresa, `interfaz.ambientacion_activa`)
  no ve la ambientación de pantalla completa **ni el gran momento**. La ayuda de esa preferencia en «Mis preferencias»
  dice que apagarla también quita el gran momento. Con «reducir animaciones», el gran momento sí sale, quieto.

## Pasos

Un commit al cerrar cada paso, con push. Cada paso deja `composer check` en verde.

- [x] 1. **Plan aprobado** (D1-D9) y mapa (fila 1.4d al día, fila 1.4d2 nueva).
- [x] 2. **Vista previa de Navidad:** `RegistroGranMomento`, la variante del nacimiento en silueta, la versión quieta y la
      página «Vista previa de fechas». Se entrega al desarrollador.
- [x] 3. **Calendario laboral:** catálogo `feriados` (tipos, «Techquk no trabaja», CHECK), seeder 2026-2027,
      `CalendarioLaboral`, pendientes.
- [x] 4. **Gran momento, datos y reglas:** `grandes_momentos`, `momentos_vistos`, parámetros de plazo, elección por año
      con salto del anterior, selección del vigente más reciente, acción `nucleo.marcar-momento-visto`.
- [x] 5. **Gran momento en pantalla:** la capa (marca primero, foco atrapado, Esc, 30 s, quieta con animaciones
      reducidas, pausa de la ambientación), D9 y la ayuda de la preferencia.
- [ ] 6. **Ambientación de pantalla completa:** parámetro de minutos, marca en la sesión, nieve y fuegos.
- [ ] 7. **Las otras tres variantes** (Navidad estrella, Año Nuevo fuegos y amanecer), tras la aprobación del estilo.
- [ ] 8. **Documentos:** `interfaz.md` (excepción, gran momento, feriados), `codigo.md` si hace falta, DBML, mapa,
      `cambios.md`, `pendientes.md`.
- [ ] 9. Revisión a la vista, `revisor-arquitectura`, `composer check` y `/cerrar-tarea`.

## Criterio de aceptación

- **Calendario laboral:** el 25 de diciembre de 2026 (feriado, viernes) da como primer día hábil el lunes 28; el 1 de
  enero de 2027, el lunes 4; un día no laborable sin marcar es hábil y marcado no; la base rechaza un feriado nacional
  o un día libre de la empresa marcado como trabajado; sin feriados cargados, solo cuentan los fines de semana.
- **Selección:** Navidad 2026 se muestra del 28 al 31; el 1 de enero no; si se juntan dos vigentes, solo el más
  reciente; visto el más reciente, no sale nada; pasado el plazo, nada.
- **Elección por año:** misma variante y versículo para todas las personas; al sumar una variante o cambiar los
  versículos activos, no se repite la del año anterior; una variante guardada que ya no existe no rompe.
- **«Lo vi»:** a nombre de quien inició sesión; solo el vigente; idempotente; devuelve si fue la primera marca.
- **Pantalla:** la capa sale solo con uno pendiente; con la ambientación apagada, no; con animaciones reducidas, quieta;
  la ambientación de pantalla completa respeta los X minutos, no sale con animaciones reducidas y no recibe clics;
  ninguna animación de la tarea pasa de tres destellos por segundo (revisión a la vista y prueba de las reglas CSS).
- **A la vista:** escritorio, celular, modo oscuro, reducir animaciones, lector de pantalla con el diálogo.

## Bitácora

- 2026-10-22 — Tarea planeada en conversación con el desarrollador. Rama `interfaz-gran-momento` desde `main`
  (3a3b9c2). Se trajeron con `cherry-pick` las dos reglas de `AGENTS.md` de la 1.4g que quedaron fuera del PR #25 (se
  fusionó antes de su último commit). D1 a D9 aprobadas una por una, con sus ajustes. Siguiente: paso 2, la vista
  previa de Navidad en silueta.
- 2026-10-22 — Paso 2 hecho: la vista previa, entregada al desarrollador para aprobar el estilo. Piezas:
  `Saludo/VarianteGranMomento` (ocasión, clave, nombre, título, vista), `Saludo/RegistroGranMomento` (singleton; clave
  repetida es un error; `variante()` devuelve nula si ya no existe), `Saludo/RotacionAnual::elegir()` (año + desfase
  de la semilla, saltando lo del año anterior: la base de D7), `Saludo/EscenaGranMomento` y
  `Services/GranMomentoService` (por ahora `versiculos()` y `previa()`, sin guardar nada). Vistas
  `gran-momento/marco` (el diálogo de 30 s: escena arriba y el texto en su franja de abajo, del color del suelo, para
  no tapar las figuras; foco atrapado con `x-trap`, Esc, «Cerrar», cierre a los 30 s, evento
  `tq-gran-momento-cerrado`; quieto con `prefers-reduced-motion` o `tq-sin-animaciones`) y
  `gran-momento/navidad-establo` (el nacimiento en silueta: noche que se aclara hacia el horizonte, estrella de Belén
  con su haz, el establo a contraluz, José con su cayado, María de rodillas, el pesebre, dos ovejas en la colina; las
  estrellas titilan cada 3 a 5 segundos, sin destellos). Página `Filament/Pages/VistaPreviaFechas` en Configuración
  (`parametros.editar`): fecha, variante, año, «Ver animado» y «Ver quieto». Revisada la escena como imagen (cairosvg
  en el borrador; sin navegador en el entorno): se suavizó el haz y se movieron las ovejas a la colina lejana.
  Pruebas: `GranMomentoPreviaTest` (7). `composer check` en verde (1362). Siguiente: paso 3 (calendario laboral),
  mientras el desarrollador revisa el estilo.
- 2026-10-22 — El desarrollador instaló las bibliotecas que le faltaban al Chromium de Playwright (`libnss3` y otras):
  ya se pueden sacar capturas de la página real (`headless_shell --screenshot`, con `--virtual-time-budget` para ver
  la animación en un segundo dado). Con ellas, dos correcciones de la vista previa: la estrella quedaba cortada en
  escritorio (la escena, anclada abajo, en una franja ancha): ahora va centrada (`xMidYMid slice`), con la estrella
  más abajo y el establo más arriba, todo lo importante entre y = 165 y y = 750; y el título y el versículo se achican
  también con la altura de la pantalla, para que en un portátil de 768 px la escena conserve su lugar. Revisado en
  1366 × 768, 1600 × 900, 1920 × 1080 y celular 390 × 844, y la animación a los 3, 6, 9 y 13 segundos.

- 2026-10-22 — Conversación con el desarrollador sobre la vista previa: las variantes son código (vista y línea en el
  registro), como los dibujos de la fecha; subir animaciones desde la pantalla se descartó (seguridad, reglas de
  WCAG y de «reducir animaciones» sin poder probarse). La vista previa queda **solo en desarrollo**
  (`app()->environment('local')` en `canAccess()`; con prueba de que fuera de local da 403 y no sale en el menú). Nace
  la **1.4i «Modo desarrollo»** (fila nueva en el mapa), después de la 1.4d: interruptor en el menú del avatar solo en
  local, fecha simulada, cambiar en el sitio las piezas decorativas entre las variantes que ya existen; absorbe esta
  vista previa. «Ver como otra persona» queda fuera: los roles reales aún no están definidos (`pendientes.md`).
- 2026-10-22 — Paso 3 hecho. Enum `TipoFeriado` (feriado nacional, día no laborable, día libre de la empresa;
  `nuncaSeTrabaja()`). Migración `2026_10_22_000002_crear_feriados`: el código del catálogo es la fecha (CHECK de
  formato y de fecha válida), `tipo` con CHECK, `techquk_no_trabaja` con CHECK (siempre verdadero salvo en un día no
  laborable), `nombre` no vacío, `version`, `auditoria()`. Modelo `Feriado` (catálogo con `EsCatalogo`, alias
  `feriado`) y su factory. Caso `Catalogo::Feriados` («feriados y días no laborables»): se agrega, edita, desactiva,
  activa y borra en Configuración → Catálogos con `catalogos.editar`, como todo catálogo; `ReglasFeriado` dice antes,
  con un mensaje claro, lo que rechazaría la base. `FeriadosSeeder` (en `NucleoSeeder`) siembra los feriados nacionales
  de 2026 y 2027 desde `database/data/feriados.php`, con Jueves y Viernes Santo calculados; carga un año solo si todavía no tiene
  ningún feriado (lo que el administrador borre o cambie no vuelve). `Tiempo/CalendarioLaboral` (`scoped`):
  `esHabil()` y `primerHabilDesde()`. DBML y `pendientes.md` al día.
  El desarrollador pidió que los días sean configurables (ya lo son: catálogo editable) y preguntó por sus efectos: hoy
  solo deciden desde qué día sale el gran momento; el plazo no se mueve y nada más los lee todavía.
  Pruebas: `CalendarioLaboralTest` (12). `composer check` en verde (1381). Siguiente: paso 4.
- 2026-10-22 — Pedidos del desarrollador durante el paso 4 (antes de empezarlo):
  (1) **Grandes momentos no editables ni elegibles:** son un regalo con factor sorpresa. Coincide con lo que hay
  (variantes en código, elegidas por año, vista previa solo en local); nada que cambiar.
  (2) **Feriados:** el seeder carga un año solo si todavía no tiene ningún feriado; lo que el administrador borre o
  cambie no vuelve (prueba en `CalendarioLaboralTest`).
  (3) **Saludo en inglés y francés, prendidos por defecto** (`saludo.ingles` y `saludo.frances` con defecto `true`,
  ajuste a la 1.4g). Las pruebas del saludo que suponían solo español lo dicen ahora explícitamente
  (`personaDelSaludo()` parte de solo español).
  (4) **«Mis preferencias» rediseñada** (ajuste a la 1.4g): cada preferencia en su fila, nombre a la izquierda y control
  a la derecha (`inlineLabel`); las opciones con pocos valores fijos, como botones agrupados con la elegida resaltada
  (`ToggleButtons::grouped()`), en vez de botones de opción sueltos; las que dependen de la persona (pantalla al
  entrar), en un desplegable nativo; etiquetas cortas («Automático» en vez de «Según el sistema») para que quepan en un
  celular de 360 px. Revisada con capturas de Chromium en escritorio claro y oscuro y en celular. `interfaz.md` al día.
  `composer check` en verde (1382). Siguiente: paso 4.
- 2026-10-23 — Paso 4 hecho. Migración `2026_10_23_000001_crear_grandes_momentos`: `grandes_momentos` (ocasión con
  CHECK de las cinco fechas, año con CHECK, variante con CHECK de formato, versículo FK nula con índice, único
  `(ocasion, anio)`) y `momentos_vistos` (`user_id` y `gran_momento_id` con índice, único). Modelos `GranMomento` y
  `MomentoVisto` (alias en el morph map). Parámetros `interfaz.gran_momento_plazo_<fecha>` (6, 6, 7, 7, 7; de 0 a 30,
  `ValidadorPlazoGranMomento`). `GranMomentoService`: `diaClave()` (aniversario desde `interfaz.aniversario_techquk`,
  nulo sin fecha; 29 de febrero → 28), `ventana()` (desde el primer día hábil hasta el plazo; nula si se cruzan),
  `vigente()` (el de día clave más reciente, solo fechas con variantes; mira el año en curso y el anterior),
  `pendientePara()` (nada con la ambientación apagada, D9, o si ya lo vio; un versículo desactivado después ya no se
  muestra), `marcarVisto()` (solo el vigente; `insertOrIgnore`; devuelve si fue la primera) y la elección guardada por
  año (`RotacionAnual` saltando lo del año anterior; «insert … on conflict do nothing»; una variante guardada que ya
  no existe se reemplaza con bloqueo). `Ambientacion::estaActiva()` pasó a pública para D9. Acción
  `nucleo.marcar-momento-visto` (campo `gran_momento`, devuelve `primera`). Excepción `GranMomentoRechazado`. DBML.
  Pruebas: `GranMomentoTest` (15). `ConfiguracionPanelTest` busca `igv_porcentaje` con el buscador (los plazos nuevos lo
  pasaron a la segunda página de la lista).
  Fuera del plan, pedido del desarrollador: **ícono de la pestaña**, la T de Techquk en tres dimensiones
  (`public/favicon.svg`, con `favicon.ico` de respaldo y `apple-touch-icon.png`; `->favicon()` en el panel; antes
  `favicon.ico` estaba vacío). Revisado a 16 y 32 px en pestañas claras y oscuras.
  `composer check` en verde (1398). Siguiente: paso 5. Pendiente: la decisión del desarrollador sobre el realismo de
  las escenas (ilustración trabajada en SVG o imágenes y video).
- 2026-10-23 — El desarrollador mandó su propia imagen para el ícono de la pestaña (la T y la Q de Techquk en tres
  dimensiones, con luz azul): reemplaza la T que hice. Recortada al cuadrado con esquinas redondeadas, sobre su fondo
  oscuro: `public/favicon.png` (192 px), `favicon.ico` (16, 32, 48) y `apple-touch-icon.png` (180); sale
  `favicon.svg`. Revisada a 16 y 32 px en pestañas claras y oscuras.
- 2026-10-23 — Conversación sobre el orden: se sigue el orden del mapa (esta tarea, luego 1.4i, 1.6, 1.7…). La meta del
  10 de diciembre pasa a deseable. El realismo de las escenas queda para una tarea aparte, la **1.4k** (fila nueva en
  el mapa, sin fecha); aquí se sigue con el estilo actual. «Mi cumpleaños» sigue en el menú hasta la 1.4h.
- 2026-10-23 — Para la 1.4k, el desarrollador eligió estilo anime inspirado en Dragon Ball Z, también en las figuras y
  con rostro, sobrio y acorde al momento (anotado en el mapa). En esta tarea sigue la silueta.
- 2026-10-23 — Paso 5 hecho. `Filament/GranMomentoEnPantalla` (Livewire, `#[Locked]` el id): lo monta el proveedor del
  panel en `BODY_END` solo si `pendientePara()` da uno (con `rescue`: es decoración). Su vista
  `gran-momento/en-pantalla` espera a que la pestaña se vea (`visibilitychange`), llama a `marcar()` (`#[Renderless]`:
  no vuelve a dibujar y el diálogo conserva su estado), que ejecuta `nucleo.marcar-momento-visto`; si fue la primera
  marca envía `tq-gran-momento-empezar`, si no, se quita. El marco gana `$esperar` (no se muestra hasta ese evento) y
  avisa `tq-gran-momento-abierto` y `tq-gran-momento-cerrado` (para la ambientación del paso 6); Esc solo con el
  diálogo abierto; `x-trap` devuelve el foco al cerrar. `GranMomentoService::escena(id)` arma la escena de uno ya
  elegido. La ayuda de `saludo.ambientacion` dice que apagarla también quita el gran momento (D9).
  Pruebas: `GranMomentoPantallaTest` (5), con la fecha fijada (`setTestNow`). Lo que depende de Livewire y Alpine en el
  navegador (empezar al volverse visible, Esc, el foco) queda para la revisión a la vista: hará falta simular la fecha
  (lo trae la 1.4i). `composer check` en verde (1403). Siguiente: paso 6 (ambientación de pantalla completa).
- 2026-10-23 — **En pausa tras el paso 5**, a pedido del desarrollador: primero «Mis datos» (1.4h), «Mien» (1.4l, nueva)
  y el modo desarrollo (1.4i); luego los mecanismos del núcleo (1.6 a 1.13); y recién después se retoma esta tarea. Lo
  hecho (pasos 1 a 5) se fusiona: funciona solo y no molesta (el gran momento sale con la escena del nacimiento; la
  vista previa, solo en local). **Para retomar:** paso 6 (ambientación de pantalla completa: parámetro
  `interfaz.ambientacion_minutos_entre`, marca en la sesión, nieve y fuegos, pausa con `tq-gran-momento-abierto` y
  `tq-gran-momento-cerrado`), paso 7 (las otras tres variantes; el estilo de la silueta sigue sin aprobar), paso 8
  (documentos: la excepción de `interfaz.md`, el gran momento y los feriados; `cambios.md`) y paso 9 (cierre). El
  realismo de las escenas va en la 1.4k. `composer check` en verde en fec5c74 (1403).
