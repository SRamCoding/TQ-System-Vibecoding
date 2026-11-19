# Plan: saludo personalizado y animación de la fecha (tarea 1.4b)

- **Estado:** completado
- **Módulo(s):** Nucleo (interfaz)
- **Fase:** F1
- **Creado:** 2026-10-18 · **Actualizado:** 2026-10-19

## Objetivo

La página de inicio deja de decir «Inicio» y saluda a la persona por su nombre con una frase corta que depende del
momento del día, el día, su área y la fecha especial, elegida por reglas a partir de frases guardadas como datos.
Junto al saludo, una animación pequeña (SVG/CSS) de la fecha especial o de la temporada.

## Contexto que leí

- `docs/planes/mapa-etapas.md`, fila 1.4b (decidido el 2026-10-16).
- `docs/decisiones/pendientes.md`: «Aniversario de Techquk» (no bloquea: sin fecha no hay animación) e «IA y
  datos» (bloquea activar IA: solo se deja el punto de extensión).
- `Filament/Pages/Inicio.php`, `Navegacion/AreaModulo` (Núcleo, CRM, ERP), `ParametrosService`
  (`parametros.valor` es NOT NULL), ADR 0013 (catálogos editables por pantalla), ADR 0016 (campos sensibles).
- Memoria del desarrollador: sin Halloween, sin santos, nada contrario al cristianismo.

## Decisiones

### Tomadas (2026-10-18 y 19, por el desarrollador; D1, D2, D5 y D8 con sus ajustes)

- **D1 · Frases como catálogo editable, en tres idiomas y de fuentes variadas** (ajustada por el desarrollador el
  2026-10-18). El saludo tiene dos partes: el **saludo** con el nombre («Buenos días, {nombre}», «Bonjour,
  {nombre}», «Good evening, {nombre}») y debajo una **frase** que puede ser propia o una cita (autor conocido,
  refrán, versículo de la Biblia), con su fuente. Tabla `frases_saludo`: `tipo` (saludo | frase), `texto`, `idioma`
  (es | en | fr), `fuente` (opcional: «Proverbios 16:3», «Antoine de Saint-Exupéry», «Refrán»), y condiciones
  opcionales `momento` (mañana 5–11:59, tarde 12–18:59, noche 19–4:59, hora de Lima), `dia_semana`, `area` (CRM/ERP)
  y `ocasion`; `es_activa`, `version`. Sin traducción en pantalla. Se edita por la pantalla de catálogos (ADR 0013).
  El seeder trae unas 40 frases: solo citas de dominio público y de atribución segura, ninguna contraria al
  cristianismo.
- **D1a · Categorías** (desarrollador, 2026-10-18). Cada frase tiene categoría: propias, autores, refranes,
  versículos (`categoria` en `frases_saludo`). Cada categoría se activa o desactiva **para toda la empresa** (catálogo
  `categorias_frase`, por pantalla) y **por usuario** en «Mi perfil». Una frase sale solo si su categoría está activa
  en los dos niveles. Si el usuario apaga todas, queda solo el saludo, sin frase.
- **D1b · Idioma por usuario** (desarrollador, 2026-10-18). Por defecto, **solo español**. Cada persona puede activar
  inglés y francés en «Mi perfil». Con alguno activo, la proporción la da el parámetro general
  `interfaz.saludo_porcentaje_espanol` (70) y el resto se reparte entre los idiomas que activó. Saludo y frase salen
  en el mismo idioma; si no hay frase en ese idioma que cumpla las condiciones, se usa el español.
- **D1c · Versículos con texto exacto y versión** (desarrollador, 2026-10-18). La fuente lleva la referencia y la
  versión: «Proverbios 16:3 (RVR1960)». Versiones: RVR1960 en español (cita breve, permitida por la Sociedad Bíblica),
  KJV en inglés y Louis Segond 1910 en francés (dominio público). Cada texto se coteja contra esa versión antes de
  entrar al seeder, y la lista queda en la bitácora para que el desarrollador la revise.
- **Preferencias por usuario:** tabla `preferencias_saludo` (una fila por usuario: `ingles`, `frances`) y
  `categorias_frase_usuario` (las categorías que la persona apagó). Sin fila, valen los valores por defecto.
- **D2 · Cómo se elige.** Entre las frases activas cuyas condiciones se cumplen, gana la más específica
  (ocasión > día > área > momento). Si empatan, una al azar **fija para la persona durante ese momento del día**
  (no cambia al recargar). Ajustes del desarrollador (2026-10-18):
  - La semilla del azar es persona + fecha + momento: dos personas no ven por fuerza la misma frase a la vez.
  - No repite la frase que esa persona vio en el mismo momento del día anterior, si hay otra disponible. No se
    guarda nada: se recalcula la elección de ayer con la misma regla y se excluye (si alguien editó el catálogo
    entretanto, puede fallar una vez; no importa).
  - El idioma se decide primero (D1b) y el respaldo sin condiciones («Hola, {nombre}», «Hello, {nombre}», «Bonjour,
    {nombre}») sale en ese idioma. El respaldo está en código además de en el catálogo: si alguien desactiva todas
    las frases, el saludo no se cae.
- **D3 · Área de la persona.** Es CRM o ERP según las cajas de módulos que puede ver (`AreaModulo`). Si ve las
  dos o ninguna, no hay área y valen las frases sin área. Hoy solo existe el Núcleo: todas aplicarán sin área
  hasta que lleguen los módulos. **Aprobada** (2026-10-18). Mejora posible, anotada por el desarrollador: si muchas
  personas quedan sin área por ver cajas de los dos lados, tomar el área de la unidad organizativa de su puesto
  (1.4) y dejar la deducción por módulos como respaldo. Para eso, el área se calcula en una sola pieza
  (`AreaDeUsuario`), que es lo único que habría que cambiar.
- **D4 · Punto de extensión para IA.** Interfaz `GeneradorSaludo` con una sola implementación,
  `SaludoPorReglas`. Una futura `SaludoConIa` se enchufa en el contenedor; no se activa (pendiente «IA y datos»).
  **Aprobada** (2026-10-18). Para cuando se active, anotado por el desarrollador:
  1. Va por el mecanismo de IA del núcleo (1.11), con interruptor, tope de gasto y prompt versionado.
  2. La IA solo genera frases **propias**; las citas de autores, refranes y versículos salen siempre del catálogo
     verificado.
  3. Solo envía nombre de pila, momento del día y fecha especial. Nada más de la persona ni de la empresa.
  Si la IA falla o tarda, sale el saludo por reglas.
- **D5 · Ocasiones.** En código (enum `Ocasion`), porque cada una tiene su animación:
  Año Nuevo (1 ene), Semana Santa (Domingo de Ramos a Domingo de Resurrección, fecha calculada), Día de la Madre
  (2.º domingo de mayo, Perú), Día del Padre (3.er domingo de junio), Fiestas Patrias (28–29 jul), Navidad
  (24–25 dic), cumpleaños y aniversario. Si coinciden: cumpleaños > aniversario > la fecha del calendario.
  Los demás días, la temporada del hemisferio sur (verano 21 dic, otoño 20 mar, invierno 21 jun, primavera 22 sep).
  **Ajuste del desarrollador (2026-10-18):** casi todas caen en domingo o feriado, cuando nadie entra; cada una tiene
  una ventana de días en que también se muestra (**aprobadas** el 2026-10-19):

  | Ocasión | Ventana |
  |---|---|
  | Año Nuevo | 26 dic – 2 ene (el 2 es el primer día de vuelta al trabajo) |
  | Semana Santa | Domingo de Ramos – Domingo de Resurrección (ya incluye lunes a miércoles hábiles) |
  | Día de la Madre | Lunes a domingo de la semana que termina el 2.º domingo de mayo |
  | Día del Padre | Lunes a domingo de la semana que termina el 3.er domingo de junio |
  | Fiestas Patrias | 21 – 29 jul |
  | Navidad | 15 – 25 dic |
  | Cumpleaños | El día; si cae sábado o domingo, también el viernes anterior y el lunes siguiente |
  | Aniversario | Igual que el cumpleaños |

  Las ventanas del calendario no se pisan entre sí. Si el cumpleaños o el aniversario caen dentro de una ventana,
  ganan ellos.
- **D6 · Cumpleaños.** En `users`: `cumpleanos_dia` y `cumpleanos_mes` (sin año), los dos o ninguno, CHECK de
  fecha válida (29 de febrero se celebra el 28 en años no bisiestos). Lo registra la propia persona en «Mi perfil»
  y quien tiene `usuarios.gestionar`. No es un grupo sensible de ADR 0016, pero no entra en la bitácora ni en
  mensajes; solo lo ven la persona y quien tiene `usuarios.ver`. **Aprobada** el 2026-10-19.
- **D7 · Aniversario.** Parámetro de texto `interfaz.aniversario_techquk` con `MM-DD`, vacío de inicio (vacío = sin
  animación). Cuando Gerencia decida, se llena por la pantalla de parámetros, sin código.
  Se valida al guardar: un día y mes que existan, o vacío. **Aprobada** el 2026-10-19.
- **D8 · Ambientación según la fecha** (ampliada por el desarrollador el 2026-10-19: que se note sin acaparar ni
  saturar, y que cambie año tras año). **Aprobada** el 2026-10-19:
  - **Tres toques, nada más:**
    1. En el inicio, la animación junto al saludo (≈64 px).
    2. En todas las pantallas, una franja fina (3–4 px) bajo la barra superior con los colores o el motivo de la
       fecha.
    3. Un ícono pequeño y quieto junto al logo.
  - **Nunca:** nada encima del contenido, nada de confeti sobre formularios ni tablas, y no cambian los colores de
    botones ni de estados.
  - **Movimiento:** la animación corre una vez al entrar (unos segundos) y queda quieta; ningún bucle. **Vuelve a
    correr al pasar el mouse por encima** (de la animación del inicio y del ícono junto al logo; en el celular, al
    tocarla), pedido del desarrollador el 2026-10-19. Con «reducir movimiento», quieta siempre, también al pasar el
    mouse.
  - **Cambia cada año:** cada ocasión y temporada tiene varias variantes de estilo (por ejemplo, Navidad: estrella y
    luces / adornos geométricos / pesebre en trazo). La variante sale del año, con un desfase por ocasión para que
    no cambien todas a la vez. Es la misma para todos ese año. Se empieza con 3 variantes por ocasión; agregar una
    es agregar un archivo.
  - **Interruptor:** parámetro general `interfaz.ambientacion_activa` y, por persona, apagarla en «Mi perfil».
  - **Colores:** cada variante declara su paleta en un solo lugar (el registro de ambientaciones), con clases `tq-`.
    Es una excepción a «colores solo en el proveedor del panel», que se anota en `docs/convenciones/interfaz.md`.
    Contraste revisado en modo claro y oscuro.
  - SVG en línea con CSS, sin librerías ni imágenes externas.

## Pasos

- [x] 1. Base: `categorias_frase`, `frases_saludo`, `preferencias_saludo`, `categorias_frase_usuario` y cumpleaños en
  `users`; modelos, factories, catálogos editables (frases y categorías) y parámetros
  (`interfaz.saludo_porcentaje_espanol`, `interfaz.ambientacion_activa`, `interfaz.aniversario_techquk`).
- [x] 2. Seeder de frases (~40, en tres idiomas y cuatro categorías; versículos cotejados con su versión y listados
  en la bitácora).
- [x] 3. `Ocasion` y `CalendarioFestivo`: Semana Santa calculada, ventanas, temporadas, cumpleaños y aniversario.
- [x] 4. `GeneradorSaludo` + `SaludoPorReglas` + `AreaDeUsuario`: idioma, categorías, especificidad, azar por
  persona, sin repetir la de ayer, respaldo en código.
- [x] 5. Acciones: registrar cumpleaños (propio y de otro) y preferencias del saludo; «Mi perfil» y ficha del usuario.
- [x] 6. Ambientación: registro de variantes por año, animación del inicio, franja y ícono junto al logo, repetición
  al pasar el mouse, «reducir movimiento», interruptores. Inicio con el saludo en vez de «Inicio».
  `docs/convenciones/interfaz.md` al día.
- [x] 7. Revisión de `revisor-arquitectura`, `composer check` en verde y cierre.

## Criterio de aceptación

- Pruebas del calendario: Semana Santa de 2025, 2026 y 2027; Día de la Madre y del Padre de varios años; cumpleaños
  del 29 de febrero; prioridad cuando coinciden.
- Pruebas de la elección: gana la más específica, es estable al recargar, respaldo cuando nada aplica, frase
  inactiva nunca sale.
- Acción del cumpleaños probada sin pantalla, con sus rechazos (fecha inválida, solo uno de los dos campos).
- En pantalla (lo revisa el desarrollador): el saludo reemplaza «Inicio» y la animación se ve junto a él.

## Bitácora

- 2026-10-18 — Plan escrito; rama `interfaz-saludo`.
- 2026-10-19 — D1–D8 aprobadas una por una, con los ajustes del desarrollador anotados en cada una.
- 2026-10-19 — Paso 1. Migración `2026_10_19_000001_crear_saludo_y_cumpleanos` (tablas nuevas, no estaban en el
  DBML: se agregaron allí con su nota). Frases y categorías son dos pestañas más del catálogo (`Catalogo`); los campos
  que se pueden vaciar llevan `present`. Desvíos:
  - **Aniversario «sin fecha» en vez de vacío (D7):** un parámetro de texto no admite vacío (`TipoParametro::interpretar`);
    `ValidadorAniversario` acepta `MM-DD` o «sin fecha» (también normaliza «-», «--», «ninguna»).
  - Las frases tienen `codigo` (lo exige el catálogo): sirve al seeder para no duplicar.
  - `dia_semana` es un entero 1–7 (ISO, 1 lunes) y no una opción, porque el formulario envía texto y la regla de enum
    con enteros no lo acepta igual.
  - Las cuatro categorías las siembra `CatalogosSeeder`; ninguna es «del sistema», así que todas se pueden apagar.
  - `texto` va primero entre los campos de las frases (la prueba general de catálogos edita el primero como texto).
  - `composer check` en verde (981 pruebas).
- 2026-10-19 — Paso 2. `FrasesSaludoSeeder` (en `NucleoSeeder`, después de los catálogos): 21 saludos (cada idioma
  y momento, lunes, viernes y fechas especiales) y 42 frases en las cuatro categorías. Solo crea lo que falta por
  `codigo`; una frase borrada desde la pantalla vuelve en el siguiente despliegue: para quitarla, se desactiva.
  **Versículos para que el desarrollador los revise** (cotejados en BibleGateway el 2026-10-19, con las mayúsculas de
  cada versión; la poesía empieza cada verso con mayúscula):
  - Proverbios 16:3 (RVR1960): «Encomienda a Jehová tus obras, Y tus pensamientos serán afirmados.»
  - Salmos 118:24 (RVR1960, mañana): «Este es el día que hizo Jehová; Nos gozaremos y alegraremos en él.»
  - Lamentaciones 3:22-23 (RVR1960, mañana): «Por la misericordia de Jehová no hemos sido consumidos, porque nunca
    decayeron sus misericordias. Nuevas son cada mañana; grande es tu fidelidad.»
  - Salmos 4:8 (RVR1960, noche): «En paz me acostaré, y asimismo dormiré; Porque solo tú, Jehová, me haces vivir
    confiado.»
  - Josué 1:9 (RVR1960, lunes): «Mira que te mando que te esfuerces y seas valiente; no temas ni desmayes, porque
    Jehová tu Dios estará contigo en dondequiera que vayas.»
  - Lucas 2:11 (RVR1960, Navidad): «que os ha nacido hoy, en la ciudad de David, un Salvador, que es CRISTO el Señor.»
  - Mateo 28:6 (RVR1960, Semana Santa): «No está aquí, pues ha resucitado, como dijo. Venid, ved el lugar donde fue
    puesto el Señor.»
  - Psalm 118:24 (KJV): «This is the day which the LORD hath made; we will rejoice and be glad in it.»
  - Proverbs 16:3 (KJV): «Commit thy works unto the LORD, and thy thoughts shall be established.»
  - Luke 2:11 (KJV, Navidad): «For unto you is born this day in the city of David a Saviour, which is Christ the Lord.»
  - Proverbes 16:3 (LSG): «Recommande à l'Éternel tes oeuvres, Et tes projets réussiront.»
  - Lamentations 3:23 (LSG, mañana): «Elles se renouvellent chaque matin. Oh! que ta fidélité est grande!»
  Descartados: Colosenses 3:23 (termina en «;», la frase sigue en el 24) y Juan 3:16 (no se pudo cotejar completo).
  Autores: Machado, Cervantes (Don Quijote), Vallejo («Los nueve monstruos», Poemas humanos), Franklin (Poor
  Richard's Almanack), Saint-Exupéry (Le Petit Prince) y La Fontaine (dos fábulas). Sin capítulo cuando no se pudo
  confirmar. `composer check` en verde (995 pruebas).
- 2026-10-19 — Paso 3. `Saludo/CalendarioFestivo` (ocasión del día con sus ventanas, Pascua por el algoritmo de
  Meeus/Jones/Butcher, enésimo domingo del mes, cumpleaños y aniversario con viernes y lunes si caen en fin de semana)
  y `Enums/Temporada`. Compara días civiles de Lima, no instantes: quien llama pasa la hora de Lima
  (`Calendario::ahora()`). Pruebas con Pascua de 2000 a 2038, Día de la Madre y del Padre de 2025 a 2027, el 29 de
  febrero y el cambio de año. `composer check` en verde (1052 pruebas).
- 2026-10-19 — Paso 4. `Saludo/GeneradorSaludo` (contrato, D4), `SaludoPorReglas` (enlazado en el provider),
  `AreaDeUsuario` (D3) y el resultado `Saludo`. Desvío de D2: **el azar entre empatadas es una rotación por día**
  (punto de partida y paso propios de persona y momento, paso sin divisores comunes con la cantidad), no un azar
  puro. Con azar puro, «recalcular la de ayer y excluirla» no garantiza nada: la de ayer también había excluido la
  de anteayer (la prueba lo encontró con dos frases). La rotación nunca repite dos días seguidos con la misma
  lista y pasa por todas antes de repetir; si la lista cambió, se compara con la de ayer y se pasa a la siguiente.
  El idioma sí es azar estable por persona, día y momento. El nombre de pila es la primera palabra de `nombres` de su
  persona. `composer check` en verde (1066 pruebas).
- 2026-10-19 — Paso 5. Acciones `nucleo.registrar-mi-cumpleanos` y `nucleo.cambiar-preferencias-saludo` (sin permiso:
  son de la propia persona) y `nucleo.seguridad.registrar-cumpleanos-usuario` (`usuarios.gestionar`, con la versión de
  la cuenta). Las reglas en `PreferenciasUsuarioService`; los mensajes de rechazo no repiten la fecha (D6). Día, mes y
  categorías apagadas con `present`. En pantalla: «Mi saludo» y «Mi cumpleaños» en el menú del usuario (no hay
  página de perfil: el menú ya tenía el cambio de modo), y en la ficha del usuario el dato «Cumpleaños» («28 de
  julio», sin año) y su botón. `composer check` en verde (1083 pruebas).
- 2026-10-19 — Paso 6, primera tanda (pedida por el desarrollador: revisar el estilo antes de dibujar todo):
  `RegistroAmbientaciones` (variante por año: año + desfase del tema; Año Nuevo y verano cuentan diciembre como del
  año que empieza), `Ambientacion` (fecha especial con dibujo; si no, la temporada; interruptores), el saludo como
  encabezado del inicio (`Inicio::getHeading()`), la franja (`TOPBAR_AFTER`), el ícono (`TOPBAR_LOGO_AFTER`) y las
  tres variantes de primavera (flor, brotes, mariposa). Repetir al pasar el mouse: Alpine quita y pone `.tq-corre`.
  **Decoración a prueba de fallos:** sin un parámetro, la ambientación tumbaba todas las pantallas (lo encontró
  `CajasModulosTest`, que no siembra parámetros); ahora va con `rescue()`: registra el error y sale sin adornos.
  `docs/convenciones/interfaz.md` tiene la sección «Saludo y ambientación de la fecha». Falta la segunda tanda: las
  variantes de las fechas especiales y de las otras tres temporadas. `composer check` en verde (1093 pruebas).
- 2026-10-19 — Ajustes pedidos por el desarrollador tras ver la primera tanda:
  - **D2, regla de elección:** solo la fecha especial y el día de la semana excluyen; el momento y el área se suman
    a las generales. Con la regla anterior, cuatro frases de la mañana tapaban a todas las generales cada mañana.
  - **Catálogo ampliado a 302** (48 saludos, 145 propias, 49 refranes, 22 citas de autores, 38 versículos), en
    `database/data/frases-saludo.php`, con sus reglas de contenido al inicio. Autores y versículos nuevos cotejados
    el 2026-10-19; descartadas por atribución dudosa una cita atribuida a Martí y una a Emerson. Saludos neutros en
    género. Una prueba impide códigos o textos repetidos (un código repetido se saltaría sin aviso).
  - **Animaciones:** 5 variantes por fecha y temporada (eran 3). Navidad ya tiene 3 (estrella, árbol, pesebre),
    mostradas al desarrollador en una página de vista previa.
- 2026-10-19 — Paso 6, segunda tanda: las 60 variantes (5 por cada fecha especial y temporada), registradas con sus
  colores; vista previa publicada para el desarrollador. **Error corregido:** la regla global
  `.tq-amb svg * { transform-box: fill-box }` cambiaba el centro de giro de las piezas dibujadas con
  `transform="rotate(… cx cy)"` y desarmaba esos dibujos (también la «Flor» de primavera de la primera tanda); ahora
  solo aplica a las piezas animadas (`[class]`), y ninguna pieza animada lleva a la vez `transform` en el atributo.
  Pedido nuevo del desarrollador, en espera del archivo del logo: logo junto a «TechQuk» con animación al pasar el
  mouse y un logo especial en pocas fechas (por acordar).
- 2026-10-19 — **D9 · Logo** (pedido del desarrollador, que entregó el logo: la T de tres piezas, sin el fondo). Va
  junto a «TechQuk» (`brandLogo`) y reemplaza al ícono de fecha que había al lado (quitado; la animación del saludo y
  la franja quedan). Al pasar el mouse se construye: bloque blanco que se rompe en escombros en tres tandas, piezas que
  se colorean y luz turquesa suave de adentro. Logo propio solo en cinco fechas: aniversario (luz más fuerte, cada
  pieza con su color), Navidad (rojo y blanco; falta elegir el detalle: gorro, estrella o bufanda), Fiestas Patrias
  (rojo, blanco, rojo), Año Nuevo (luz dorada y destellos) y Semana Santa (morado litúrgico, luz dorada). Colores del
  logo: turquesa `#00DAFF`, morado `#7C6EFF`, fondo `#01006F`; distintos de los aproximados de `interfaz.md` (pregunta
  abierta al desarrollador).
  **Rendimiento:** `composer check` subió a ~13 min. Causa: `FrasesSaludoSeeder` creaba 300 filas una por una en cada
  prueba que carga el núcleo (0,83 s cada vez). Ahora inserta en bloque solo las que faltan (0,025 s), sin bitácora de
  creación de esas filas sembradas. `composer check` en verde (1101 pruebas, 4 min 17 s).
- 2026-10-19 — D9, ajustes del desarrollador: cada pieza se ilumina con su propio color (también en el aniversario,
  que solo brilla más); Navidad con rojo, blanco, rojo y gorro (lo pidió el desarrollador aun con la nota de que Papá
  Noel viene de San Nicolás); Fiestas Patrias con rojo, blanco (arriba a la derecha), rojo (abajo) y el escudo del
  Perú simplificado colgando del pecho de la T; Semana Santa con una Biblia en el medio; Año Nuevo con una estrella en
  el medio (reemplaza los destellos). `composer check` en verde (1102 pruebas, 4 min 9 s).
- 2026-10-19 — **D10 · Aniversario** (pedido del desarrollador): aura del logo más fuerte y visible todo el día (no
  solo al pasar el mouse), y entre el nombre y el buscador un agradecimiento a Dios por los años, en cursiva (Great
  Vibes, OFL, guardada en `public/fonts/great-vibes/` para no depender de Google). Inventario de 12 agradecimientos
  (tercer tipo de frase, `agradecimiento`, editable en el catálogo; la base exige `{anios}` y 60 letras como máximo);
  cada año sale uno, el mismo para todos. **Cambio del parámetro:** `interfaz.aniversario_techquk` pasa a ser la fecha
  de fundación completa (`AAAA-MM-DD`), porque los años salen de ella. Migración nueva `2026_10_19_000002` (la
  columna `tipo` crece a 20: «agradecimiento» no cabía en 10; lo encontró la prueba). Oculto en pantallas angostas. `composer check` en verde (1114 pruebas).
- 2026-10-19 — Ajuste del desarrollador: en modo claro la pieza blanca no se veía iluminada (luz blanca sobre fondo
  blanco) y el logo quedaba iluminado solo arriba. La luz de toda pieza blanca usa ahora `--tq-luz-blanca`: blanco
  lavanda (`#A9AEDB`) en modo claro, blanco en modo oscuro. `composer check` en verde.
- 2026-10-19 — **Colores de marca** (el desarrollador aceptó usar los del logo): ADR 0017, `Nucleo\Navegacion\ColoresMarca`
  (único lugar donde se escriben), `morado` en vez de `malva`, pendiente «Identidad visual» tachado. La paleta de
  `primary` se arma a mano (`ColoresMarca::paletaPrincipal()`): `Color::hex()` solo toma el tono y el botón de ingreso
  salía celeste con texto oscuro; ahora el tono 600 es el azul noche exacto. Revisado en pantalla, claro y oscuro.
- 2026-10-19 — **Revisión de `revisor-arquitectura`**, atendida:
  - Error: el selector de varias categorías no ofrecía las desactivadas que la persona ya tenía apagadas, y «Mi
    saludo» no se habría podido guardar. `CatalogosService::opciones()` acepta la lista actual.
  - Error: colores de marca escritos en el logo y el agradecimiento: ahora salen de `ColoresMarca`.
  - Error: DBML de `frases_saludo.tipo` al día (`varchar(20)`, `agradecimiento`, CHECKs).
  - Una frase mal formada en el catálogo se rechaza con un mensaje claro (`ReglasFrase`), no con un error de la base.
  - La excepción de bitácora del seeder en bloque, escrita en `base-de-datos.md`; `frases-saludo.fuente.json`.
  - Fecha de ejemplo del aniversario neutra (`2010-05-15`): la anterior era una de las candidatas reales.
  - Logo y estilos con `rescue()`: si fallan, queda el nombre.
  - Preferencias leídas en un solo lugar (`PreferenciasUsuarioService::saludoDe()`, recordadas en la petición), y el
    primer guardado de «Mi saludo» ya no choca si llegan dos a la vez (`insertOrIgnore` y luego bloqueo).
  - Abierto, para el desarrollador: registrar el propio cumpleaños sube la versión de la cuenta (un gestor con la ficha
    abierta vería «otra persona cambió la cuenta»).
- 2026-10-19 — `composer check` en verde (1121 pruebas, 3 min 56 s). **Tarea cerrada.** Quedan dos preguntas abiertas al
  desarrollador, que no bloquean: si los botones en modo oscuro usan un azul noche más claro, y si registrar el propio
  cumpleaños debe subir la versión de la cuenta. Sigue la 1.4d: ambientación de pantalla completa en las cinco fechas
  con logo propio y su «gran momento» de 30 segundos (pedido del desarrollador).
- 2026-10-19 — Respuestas del desarrollador a las dos preguntas abiertas: en modo oscuro los botones usan un azul más
  luminoso (`ColoresMarca::principalModoOscuro()`, solo el tono 600 de `primary`); registrar el propio cumpleaños sigue
  subiendo la versión de la cuenta (pasa poco y no hace daño).
- 2026-10-19 — Pedido del desarrollador: en modo claro el bloque de la animación del logo es negro (se notaba poco la
  fragmentación sobre la barra blanca); en modo oscuro sigue blanco. Error corregido de paso: cada pieza tenía dos
  reglas `animation` (pintar y brillar) y la segunda anulaba a la primera, así que las piezas nunca partían del color
  del bloque; ahora van en una sola declaración.
