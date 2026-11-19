# 0022 — Plantillas personalizables: un mecanismo del núcleo que cada módulo usa

- **Estado:** aceptada (2026-11-12, desarrollador)
- **Fecha:** 2026-11-12
- **Decide:** desarrollador (modo actual de AGENTS.md: lo que cruza áreas lo decide Sebastián, con la IA proponiendo
  opciones). Nace de la conversación del 2026-11-12, al cerrar la 2.10 y antes de planificar la 2.9b.

## Contexto

Techquk repite tipos de obra (videovigilancia, cableado estructurado, redes) y cada vez arma desde cero lo mismo: los
títulos y partidas del presupuesto, los análisis de precios de las partidas de siempre, el árbol de partidas de
control, los textos de una cotización, los materiales de un pedido. Hoy eso se copia de un archivo viejo (con el
riesgo de arrastrar datos de otro cliente) o se reescribe. S10 tiene partidas y análisis «tipo», y quien presupuesta los
extraña cuando no están. Partir de algo que ya funcionó y ajustar solo lo distinto ahorra horas y, además, ordena el
costo igual en todas las obras de un tipo, que es lo que permite compararlas (la pregunta del sistema: cuánto se gana
en cada proyecto).

La necesidad aparece en varias áreas a la vez (ADR 0020):

| # | Plantilla | Área | Módulo | Cuándo |
|---|---|---|---|---|
| 1 | Árbol de partidas de control | 1 | `Erp/Presupuestos` | La primera, pegada a la 2.9b |
| 2 | Presupuesto (títulos y partidas con unidad y rendimiento) | 1 | `Erp/Presupuestos` | Después de la 2.9b |
| 3 | APU tipo de una partida | 1 | `Erp/Presupuestos` | Con la 2 |
| 4 | Cuadrilla tipo (mano de obra y equipos que van juntos) | 1 | `Erp/Presupuestos` | Con la 3 |
| 5 | Textos de la cotización (alcance, condiciones, garantía, validez) | 2 | `Crm/Cotizaciones` | Con su módulo |
| 6 | Requisitos por etapa de la oportunidad, por tipo | 2 | `Crm/Oportunidades` | Cuando lo decida su dueño |
| 7 | Contrato (hitos de pago, penalidad, carta fianza) | 3 | `Erp/Contratos` | Con su módulo |
| 8 | Pedido recurrente («kit de instalación por cámara») | 3 | `Erp/Compras` | Con su módulo |
| 9 | Proyecto completo (las anteriores juntas) | varias | — | Al final, cuando existan las piezas |

Si cada módulo inventa su versión, las nueve se comportan distinto (unas copian, otras enlazan; unas tienen versión,
otras no) y quien las usa tiene que aprender nueve maneras. Por eso se decide antes de la primera.

Dos cosas del diseño tocan el nombre y el alcance:

- El v4 ya usa **«plantilla contable»** (parte 1 y parte 6): la regla que dice en qué cuentas va cada operación, de la
  etapa 3. Es otra cosa: no se copia en un documento, se aplica cada vez.
- `docs/decisiones/pendientes.md`, fila «Tipologías de proyecto», menciona **plantillas de fase** ligadas a las
  tipologías T1 a T6, que no están definidas. Esta decisión no las define ni depende de ellas (regla 12).

## Decisión

Las plantillas son **un mecanismo del núcleo** (`Nucleo/Plantillas`), como los estados o las acciones: el núcleo
guarda, versiona, da permisos y muestra; **cada módulo registra sus tipos de plantilla** y dice qué se captura y cómo se
aplica. Reglas comunes:

1. **Aplicar es copiar, nunca enlazar.** Al usar una plantilla, el documento recibe una copia y queda solo. Cambiar la
   plantilla después no toca nada de lo ya creado (además, lo aprobado no se edita: regla 6).
2. **Aplicar arma una propuesta editable**, no el documento directo: la persona ve lo que va a crearse, quita, agrega y
   cambia, y recién entonces confirma por la acción normal del módulo (regla 7). Es el mismo paso que la 2.9b usa para
   proponer el árbol desde el presupuesto: «desde el presupuesto», «desde una plantilla» y «desde cero» son tres
   orígenes de una misma propuesta.
3. **Nacen de lo real: «Guardar como plantilla».** Desde un documento bien hecho (un presupuesto, un árbol) se guarda
   su estructura como plantilla. También se pueden crear y editar desde su pantalla, pero el camino principal es este.
4. **El contenido es del tipo; el núcleo no lo interpreta.** Cada tipo decide qué guarda: el árbol, solo estructura
   (códigos, nombres y jerarquía, sin montos: el costo sale del presupuesto aprobado, D5 de la 2.10); el presupuesto
   con APU (segundo tipo) sí lleva rendimientos, cantidades y montos. Lo que nunca lleva ninguna: clientes ni
   personas (el responsable se elige al aplicar). Si un tipo guarda montos, van como texto decimal, nunca `float`
   (regla 4), y el tipo declara su grupo sensible (ADR 0016): sin ese permiso, la plantilla se ve en la lista pero su
   contenido no se muestra ni se envía al navegador, y no se puede aplicar.
5. **Personales y de la empresa, nada más** (pregunta 1). Cada quien guarda las suyas (solo las ve y usa esa persona);
   las de la empresa las ve todo el que puede crear ese documento. No se comparte con una persona ni con un equipo en
   particular: para que otros la usen, se publica. Publicar una personal crea una de la empresa; la personal sigue
   siendo de su dueño.
5b. **Publicar pide el permiso de su tipo** (pregunta 2): cada tipo declara el suyo, con su área (ADR 0021); los tipos
   del área 1 comparten `erp.presupuestos.plantillas.publicar`. Quien publica presupuestos no publica contratos.
6. **Con versión, y siempre se aplica la última** (pregunta 3). Editar una plantilla crea su versión siguiente; el
   selector ofrece solo la vigente y las anteriores quedan como historia, así que no hace falta avisar de una versión
   más nueva. El documento creado guarda de qué versión salió en una columna `plantilla_version_id` (llave foránea con
   índice a `plantilla_versiones`; nula solo si el documento no vino de una plantilla) y se muestra («Árbol creado
   desde Videovigilancia v3»). Esa columna es el punto de extensión para mostrar después qué cambió entre versiones.
7. **Se desactivan, no se borran.** Una plantilla vieja deja de ofrecerse; lo creado con ella sigue diciendo de dónde
   salió.
8. **El contenido lo valida su módulo.** El núcleo guarda el contenido de cada versión en `jsonb` con el número de
   formato de su tipo; el módulo lo valida al guardar y lo traduce al aplicar. Si el formato del tipo cambia, el
   módulo sabe leer los anteriores (o los convierte con una migración de datos). Las plantillas no son datos de los que
   dependa un cálculo: lo que importa queda en el documento creado, con sus tablas y restricciones de siempre.
9. **Nombre:** en pantallas y código, «plantilla» a secas dentro de su módulo («Usar plantilla», «Guardar como
   plantilla»); el glosario las distingue de la «plantilla contable» de la etapa 3, que no usa este mecanismo.

### Qué pone el núcleo y qué pone cada módulo

- **Núcleo:** las tablas (`plantillas`: tipo, nombre, descripción, de la empresa o de una persona, activa;
  `plantilla_versiones`: número, formato, contenido, quién y cuándo, y de qué documento se guardó), el registro de
  tipos (`RegistroPlantillas`, como `RegistroDocumentos`), un `PlantillasService` para los demás módulos (listar las
  que una persona puede usar, guardar, publicar, versionar, desactivar), las acciones registradas de esas operaciones,
  y las piezas de pantalla comunes: el selector «Usar plantilla» y el panel deslizante de «Mis plantillas» y «De la
  empresa» (`docs/convenciones/interfaz.md`).
- **Cada módulo:** una `DefinicionPlantilla` por tipo (clave `<area>.<modulo>.<cosa>`, nombre, formato y su
  validación, grupo sensible si guarda montos, `capturar()` desde un documento y `proponer()` hacia la propuesta
  editable), su permiso de publicar, la columna `plantilla_version_id` en su documento y los botones en su pantalla.
  El contrato no asume nada de ningún tipo: el árbol es el primero, y el presupuesto con APU entra después sin
  cambiar el núcleo (pregunta 4). Ningún módulo lee las tablas del núcleo directo: va por `PlantillasService` (regla 2).

### Por dónde empezar

1. **La 2.9b deja la propuesta del árbol lista para recibir un origen más** (punto de extensión, «Preparado para lo
   diferido»), sin construir plantillas todavía.
2. **La tarea 2.11** construye el mecanismo del núcleo con su primer tipo, el árbol de partidas de control (el más
   simple: estructura sin montos). Plan: `docs/planes/completados/2026-11-12-plantillas.md`.
3. **Una tarea del área 1** suma presupuesto, APU tipo y cuadrillas.
4. Las demás áreas suman sus tipos cuando construyan su módulo; el proyecto completo, al final.

## Consecuencias

- **Más fácil:** cada área agrega una plantilla registrando un tipo, sin rehacer guardado, versiones, permisos ni
  pantallas; quien las usa aprende una sola manera; se puede preguntar qué plantillas se usan y cuáles no.
- **Más difícil:** el núcleo crece con un mecanismo más, y cada tipo tiene que cuidar su formato a lo largo del
  tiempo (validarlo y saber leer los anteriores).
- **Prohibido:** que un documento quede enlazado a su plantilla (que cambie si la plantilla cambia); guardar en una
  plantilla precios, montos, clientes, personas o campos sensibles; aplicar una plantilla sin pasar por la propuesta
  editable y la acción del módulo.

## Alternativas descartadas

- **Cada módulo hace las suyas, con sus tablas.** Más integridad en la base para cada tipo, pero nueve maneras de
  guardar, versionar y mostrar, y nueve pantallas que aprender. Lo común (personales y de la empresa, versiones,
  publicar) es justo lo que más se repetiría.
- **Copiar un documento existente («duplicar este presupuesto»).** Útil y barato, pero arrastra montos, cliente y
  datos del caso, y no deja una referencia ordenada por tipo de obra. Puede convivir como atajo; no reemplaza a las
  plantillas.
- **Plantilla enlazada (el documento sigue a su plantilla).** Rompe la regla 6 (lo aprobado no se edita) y hace que un
  cambio de una persona altere el trabajo de otras sin que lo noten.
- **Tablas con columnas fijas en el núcleo para todos los tipos.** El núcleo tendría que conocer presupuestos,
  contratos y pedidos: rompe los límites entre módulos (ARCHITECTURE.md).
- **Esperar a las tipologías de proyecto.** Atar las plantillas a T1–T6 haría depender todo de un pendiente sin dueño;
  si las tipologías se definen, una plantilla podrá llevar su tipología como dato más, sin cambiar este mecanismo.

## Preguntas respondidas (desarrollador, 2026-11-12)

1. **Compartir:** solo personales y de la empresa; nada de compartir con una persona o un equipo.
2. **Publicar:** un permiso por tipo, con su área.
3. **Versiones:** siempre se aplica la última; aplicar copia, y el documento guarda la versión de la que salió.
4. **Primer tipo:** el árbol de partidas; el contrato del núcleo no asume nada del árbol, y el presupuesto con APU
   (que lleva montos, rendimientos y análisis) es el segundo.

Y, en el plan de la 2.11, decididas el mismo día: el tipo que guarda montos declara su grupo sensible (D5); la
plantilla de árbol guarda códigos, nombres y jerarquía, sin montos ni responsables mientras el organigrama esté en
espera (D6); y se guarda desde un árbol en cualquier estado y se aplica si el proyecto no tiene árbol o tiene un
borrador sin partidas (la misma regla y la misma comprobación que «Proponer desde el presupuesto» de la 2.9b), con un
rechazo que explica por qué y qué hacer si el borrador ya tiene partidas, y en pantalla es una opción del panel «Armar
el árbol» de la 2.9b, no un botón propio (D7).
