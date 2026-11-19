# Plan: 1.5 · Estados y transiciones con efectos

- **Estado:** terminado
- **Módulo(s):** Nucleo (M3; usa M4 y M10)
- **Etapa:** 1 · tarea 1.5 · rama `nucleo-estados-transiciones`
- **Creado:** 2026-10-20 · **Actualizado:** 2026-10-20

## Objetivo

Cualquier módulo puede declarar los estados y transiciones de un tipo de documento y sus efectos, y cambiar de
estado solo por el servicio de transiciones del núcleo. Ese servicio bloquea la fila, valida que la transición
exista desde el estado actual, revisa el permiso, exige el motivo cuando corresponde, escribe `historial_estados`
y ejecuta los efectos en la misma transacción. Anular exige motivo y revierte los efectos. Un documento en un
estado no editable no se puede modificar. Todo se prueba con un documento de prueba, sin pantalla.

## Contexto que leí

- `AGENTS.md`, `docs/planes/mapa-etapas.md` (fila 1.5), `docs/planes/plantilla.md`, `docs/planes/README.md`.
- `ARCHITECTURE.md` l.59-93 (carpeta `Efectos/`, «tres mecanismos que todo módulo usa»).
- `docs/convenciones/codigo.md` §«Servicios» (bloqueo optimista, errores de la base), §«Acciones registradas»
  (una clase, varias acciones), §«Estados y efectos», §«Eventos y jobs».
- `docs/convenciones/base-de-datos.md` l.64: estados con FK a `estados`, nunca `enum` ni texto libre.
- `.claude/skills/nuevo-documento/SKILL.md`: lo que esta tarea debe dejar listo para la etapa 2.
- `docs/diseno/v4/parte-1-modelo-mental.md` l.85-98 (idea 3: aprobado no se edita, nada se borra; la barra de
  estados es un componente único y la pantalla no programa los estados).
- `docs/diseno/v4/parte-6-fichas-documentos.md` l.1-60 y l.105-160 (contrato, garantía, orden, recepción):
  - «Anular, en todos los documentos, exige motivo y revierte los efectos de la última transición».
  - Transiciones hacia el mismo estado («Vigente → Registrar adenda → Vigente», «Renovar»).
  - Transiciones del sistema («— → Generar desde CRM → Borrador», quién: «Sistema»).
  - Una acción con dos destinos según los datos («Recepción → Recibida parcial / Recibida»).
  - «Por aprobar → Aprobar → Aprobada · Según matriz» (lo resuelve M2, tarea 1.7).
- `docs/diseno/v4/parte-7-transversal.md` l.155-160 (nada se borra; lo anulado se conserva con su motivo).
- DBML v2 l.231-269 (`estados`, `transiciones_estado`, `historial_estados`) y l.212-229 (`aprobaciones`).
- `docs/analisis/bloques/comun.md` l.36 (M3 «propuesto»).
- Código: `SeguridadService::puede()` (permiso con ámbito), `RegistroAcciones` y `Acciones/Catalogos/` (una clase,
  varias acciones), `ParametrosService::cambiar()` (bloqueo optimista), `UsuarioSistema`, morph map en
  `NucleoServiceProvider`, `tests/Modules/Nucleo/Fixtures/ModeloAuditado.php` (tabla creada en la prueba).
- `docs/decisiones/pendientes.md`: **nada bloquea.** Lo cercano: «Aprobaciones» (valores U1-U3, tarea 1.7) y
  «Grilla de roles real» (quién ejecuta cada transición es un permiso, y los permisos se asignan después).

## Deuda de tareas anteriores

Ninguna dirigida a la 1.5.

## Decisiones

### Tomadas (2026-10-20, por el desarrollador)

- **D1 · Dónde se declara la máquina de estados de un documento.** **Aprobada 2026-10-20, con tres ajustes.**
  Cada módulo escribe una clase de definición por tipo de documento y la registra en un `RegistroDocumentos` del
  núcleo, como hoy registra acciones y parámetros. Un sincronizador idempotente copia estados y transiciones a
  `estados` y `transiciones_estado` (corre en el seeder y en cada despliegue). Lo que sale de la definición se
  desactiva (`es_activo = false`), nunca se borra: hay documentos que lo apuntan.
  *Alternativas descartadas:* seeders sueltos por módulo (la máquina queda repartida y nadie revisa que cuadre);
  crear estados o transiciones por pantalla (los efectos son código: un estado nuevo sin su efecto rompe el flujo).
  Ajustes:
  1. **Qué es código y qué es dato editable.** La separación queda así:

     | Qué | Dónde vive | Quién lo cambia |
     |---|---|---|
     | Estados, transiciones (desde, hacia, si exigen motivo o aprobación) y efectos | Definición en código | El desarrollador |
     | Nombre visible y color de cada estado | `estados.nombre`, `estados.color` | Una persona, desde pantalla, con acción registrada `nucleo.estados.cambiar-presentacion` (campos `nombre`, `color`, `version` de control) |
     | Quién ejecuta cada transición | Permisos de la 1.4 (`transiciones_estado.permiso`, asignado a roles) y aprobaciones de la 1.7 (`requiere_aprobacion`) | Quien administra roles y reglas de aprobación |

     La definición trae el nombre y el color **iniciales**: el sincronizador los escribe al crear el estado y
     nunca los vuelve a pisar, para no deshacer lo editado en pantalla. Si el color se elige de la paleta de
     `ColoresMarca` o es libre se decide con la pantalla (D10).
  2. **No se desactiva un estado con documentos vivos en él.** Si un estado que salió de la definición todavía
     tiene documentos, el sincronizador falla sin cambiar nada, con un mensaje como «No se puede quitar el estado
     «Por aprobar» de orden_compra: 3 documentos siguen en él». Cada definición sabe qué modelo usa, así que el
     conteo es por tipo de documento. Con prueba.
  3. **El código no cambia nunca.** `estados.codigo`, `transiciones_estado.codigo` y `documento_type` no se
     editan: el modelo rechaza el cambio y la acción de pantalla solo acepta nombre y color. Cambiar el código en
     la definición no renombra: es quitar un estado y crear otro (y el ajuste 2 lo frena si tiene documentos).
     Renombrar es solo cambiar el nombre visible. Pruebas: cambiar el código de un estado o de una transición
     falla; cambiar el nombre desde la acción conserva el código y el id.

- **D2 · Columnas que se agregan al DBML.** **Aprobada 2026-10-20, con dos agregados** (al final de la lista).
  - `transiciones_estado`: `codigo` (la acción: `aprobar`), `nombre` («Enviar a aprobación»), `orden`, y
    `documento_type` con FK compuesta a `estados(id, documento_type)` en `desde` y `hacia`: la base impide una
    transición entre estados de dos documentos distintos. Únicos: `(documento_type, desde_estado_id, codigo)`.
  - `estados`: únicos `(documento_type, codigo)` y un solo `es_inicial` por documento (índice único parcial).
    `documento_type` es el alias del morph map (`orden_compra`), no el nombre de la clase. `version` y auditoría
    (`created_*`, `updated_*`), porque nombre y color se editan por pantalla (D1).
  - `historial_estados`: `transicion_id` (nulo solo al crear el documento, cuando `desde` también es nulo; CHECK),
    índice `(documentable_type, documentable_id, id)` para leer la bitácora en orden.
  - `permiso` queda nulo solo en las transiciones del sistema (ver D5).
  - **Agregado 1 · El motivo lo exige la base.** `transiciones_estado.requiere_motivo` (ya está en el DBML) y
    `historial_estados.motivo`. Sin triggers (prohibidos en `base-de-datos.md`), con copia y FK compuesta:
    - `historial_estados.requiere_motivo`, copiado de la transición, con FK `(transicion_id, requiere_motivo)` →
      `transiciones_estado(id, requiere_motivo)`, y CHECK `requiere_motivo = (motivo_id IS NOT NULL)`. La copia
      no puede mentir: la FK la obliga a coincidir con la transición. *(El motivo pasó de texto libre a catálogo en
      D9: el CHECK original `btrim(motivo) <> ''` se reemplaza por este y por los de D9.)*
    - **Anular siempre exige motivo (regla 6):** `transiciones_estado.hacia_es_cancelacion`, copiado del estado de
      destino con la FK de `hacia` extendida a `estados(id, documento_type, es_cancelacion)`, y CHECK
      `NOT hacia_es_cancelacion OR requiere_motivo`. La definición también lo valida, con mensaje claro, antes de
      llegar a la base.
    - Consecuencia: las FK no cascadean, así que **cambiar `requiere_motivo` de una transición que ya tiene
      historial, o `es_cancelacion` de un estado con transiciones, hace fallar al sincronizador** con un mensaje
      claro, igual que el ajuste 2 de D1. El historial queda fiel a la regla con la que se escribió.
  - **Agregado 2 · `historial_estados` es solo de inserción**, como `auditoria_cambios`: el modelo lanza error
    al actualizar y al borrar. Prueba de las dos cosas. (`efectos_aplicados` sí se actualiza una vez, al marcar
    `revertido_por_historial_id`: D3.)

- **D3 · Un efecto: dos métodos y su registro.** **Aprobada 2026-10-20, con tres ajustes.** Interfaz `Efecto` con
  `aplicar()` y `revertir()`, los dos obligatorios. Un efecto que no se deshace (un aviso ya enviado) lo dice en
  `revertir()` (por ejemplo, avisa que se anuló), no lo omite. Cada efecto aplicado se guarda en una tabla nueva
  `efectos_aplicados` (`historial_estado_id`, clave del efecto, orden, `revertido_por_historial_id`): al anular se
  sabe qué se aplicó de verdad, aunque la definición haya cambiado después. Prueba: toda clave guardada o declarada
  existe en el registro de efectos. Ajustes:
  1. **El efecto guarda lo que hizo y se revierte con eso.** `aplicar()` devuelve los datos de lo que hizo (ids,
     montos, partidas) y se guardan en `efectos_aplicados.datos` (`jsonb`, NOT NULL, `{}` si no hay nada).
     `revertir()` recibe esos datos y no recalcula con los de hoy: si el comprometido fue 1,248.00 en la partida
     X, se libera 1,248.00 en la partida X aunque el precio o la imputación hayan cambiado. Los montos van como
     texto decimal (nunca `float`, regla 4). Prueba: cambiar los datos de origen entre aplicar y anular no cambia lo
     que se revierte. `datos` no se muestra en pantallas ni registros tal cual (regla 10).
  2. **Orden inverso.** Al anular, se revierte del último efecto aplicado al primero: por cambio de estado del más
     reciente al más antiguo y, dentro de uno, por `orden` descendente. Prueba con tres efectos que anotan su orden.
  3. **Internos en la transacción; externos después, por cola.** Dos tipos:
     - `Efecto` (interno: stock, imputación, otro documento): corre dentro de la transacción del cambio de estado.
       Si uno falla, no cambia nada: ni estado, ni historial, ni los otros efectos.
     - `EfectoExterno` (correo, aviso, servicio externo): la transacción solo guarda su fila en
       `efectos_aplicados` con `ejecutado_at` nulo; un job en cola, despachado después del commit, lo ejecuta y
       marca `ejecutado_at`. Es idempotente (si `ejecutado_at` ya tiene valor, no repite) y sus reintentos no
       duplican el aviso. Si falla, el estado no se deshace: el job queda en `failed_jobs` y avisa al responsable.
       Su `revertir()` sigue la misma regla al anular.
     Pruebas: un efecto interno que falla deja el documento como estaba; un externo no se ejecuta si la
     transacción se revierte, sí se ejecuta después del commit, y un reintento no lo repite.
     **Ajustado 2026-10-20 (desarrollador, al revisar la arquitectura en el paso 10):** el job no envía dentro de
     una transacción (`codigo.md`: nada externo en una transacción). Una transacción corta reserva la fila
     (`reservado_hasta`, con plazo); el envío corre sin transacción, con el id del efecto como clave de
     idempotencia; otra transacción corta confirma o libera la reserva si falló. Si el worker muere, la reserva vence
     y otro intento lo retoma: **al menos una vez**, puede repetirse pero nunca perderse. Con correo SMTP la clave
     (un Message-ID estable) solo reduce los duplicados. El plazo de la reserva es mayor que el `timeout` del job y
     menor que el `retry_after` de la cola. Un efecto reservado sin confirmar cuenta como salido: se revierte al
     anular e impide «Reabrir». `revertir()` sigue el mismo patrón (`reversion_reservada_hasta`). Pruebas: reserva
     vigente no la toma otro intento; reserva vencida sí; si el envío falla, la reserva se libera y el reintento lo
     ejecuta; reservado sin confirmar impide «Reabrir»; el envío corre sin transacción abierta.

- **D4 · Qué revierte «Anular».** **Aprobada 2026-10-20, con un agregado. Interpretación del v4, escrita en
  `docs/convenciones/codigo.md` §Estados y efectos.** El v4 dice «revierte los efectos de la última transición».
  Tomado al pie de la letra, anular una orden «Enviada» solo revertiría el aviso al proveedor, y el comprometido que
  puso «Aprobar» se quedaría en la partida. Decidido: **anular revierte todos los efectos que siguen vivos, en orden
  inverso** (D3, ajuste 2). En un documento que acaba de pasar un solo paso, es lo mismo que dice el v4. Anular va a
  un estado `es_cancelacion`, final, con motivo obligatorio (D2), y solo desde los estados que la definición diga.
  Un efecto ya revertido (`revertido_por_historial_id` lleno) no se revierte otra vez.
  - **Agregado · No se anula con documentos posteriores vivos.** Un documento no se anula si otro documento vivo
    depende de él (una orden con una recepción confirmada o una factura). El mecanismo da una interfaz
    `RevisionAntesDeAnular` que devuelve la lista de documentos que lo impiden (tipo, número o id, estado):
    - La definición del documento declara las suyas.
    - Como el documento que depende suele estar en otro módulo (la recepción es de Almacén, la orden de Compras),
      **ese otro módulo también puede registrar una revisión para el tipo ajeno** en `RegistroDocumentos`. Así la
      dependencia va en el sentido correcto: Compras no necesita conocer Almacén.
    - El servicio corre todas las revisiones **dentro del bloqueo** de la fila; si alguna devuelve algo, falla con
      un `ErrorDeNegocio` que lista los documentos: «No se puede anular la orden: la usan ING-2026-0031
      (Confirmada) y la factura F001-123 (Registrada). Anúlalos primero». Mientras no exista numeración (1.6), la
      revisión da lo que tenga (el id).
    - Para que no haya carrera, quien crea un documento que depende de otro bloquea la fila del origen
      (`lockForUpdate`) al crearlo: crear la recepción y anular la orden quedan en fila.
    - Pruebas: con un dependiente vivo, anular falla y el mensaje lo nombra; con el dependiente anulado, se puede;
      una revisión registrada por otro módulo también bloquea.

- **D5 · Transacción, concurrencia y transiciones del sistema.** **Aprobada 2026-10-20, con dos agregados y el
  patrón «Reabrir» documentado** (al final). El servicio abre la transacción, bloquea la fila
  (`lockForUpdate`), compara la `version` que leyó la persona, valida la transición **dentro** del bloqueo, cambia
  el estado, escribe el historial y corre los efectos internos, todo junto; los externos van por cola después del
  commit (D3, ajuste 3). Se permiten
  transiciones al mismo estado («Renovar»). Si una acción tiene dos destinos según los datos («Recibida parcial» o
  «Recibida»), son dos transiciones y el servicio del módulo elige cuál pedir. Las transiciones con `permiso` nulo
  son del sistema: solo las pide código del módulo, a nombre del usuario de sistema, nunca una acción de usuario
  (prueba). La creación pasa por el servicio: estado inicial y primera fila del historial sin `desde` ni
  transición. Concurrencia: dos pedidos con la misma versión, el segundo falla con `VersionDesactualizada`; la
  prueba con procesos en paralelo es de la 1.6 y la 1.7.
  - **Agregado 1 · Cada estado dice si se editan los datos, y nada edita en uno que no lo permite (regla 6).**
    `estados.es_editable` (ya en el DBML) lo declara la definición. El rechazo vive en el modelo (trait
    `TieneEstados`, D8), no en la pantalla: falla igual desde Filament, una acción, un servicio, un job o
    `tinker`. Pruebas: editar un documento en estado no editable falla por las tres vías (servicio, acción,
    modelo directo); en uno editable, pasa.
  - **Agregado 2 · En las transiciones del sistema se guarda quién las originó.** `historial_estados.originado_por_user_id`
    (FK a `users`, con índice): la persona cuya acción disparó la transición del sistema (quien aprobó la cotización
    que generó el contrato). Nulo si no la disparó una persona (un proceso programado, un vencimiento). El servicio
    lo recibe al pedir una transición del sistema (`originadoPor`). Prueba: el historial guarda al sistema como
    `user_id` y a la persona como `originado_por_user_id`.
  - **Patrón «Reabrir» (solo documentado; no se construye en la 1.5).** Escrito en `docs/convenciones/codigo.md`
    §Estados y efectos para que cada documento de la etapa 2 lo considere: transición desde un estado aprobado a uno
    editable, con permiso y motivo; revierte los efectos vivos aplicados desde que salió de ese estado (libera el
    comprometido) y obliga a aprobar otra vez; solo si nada salió al exterior y no hay documentos posteriores vivos;
    si no, se anula y se crea otro. Cada definición decide si lo ofrece y desde dónde. En pantalla, «Reabrir» y
    «Anular» van en el menú «Más», con confirmación.

- **D6 · Permisos con ámbito.** **Aprobada 2026-10-20, con dos ajustes.** El permiso de la transición se revisa
  con `SeguridadService::puede(usuario, permiso, ámbito)`. El nombre del permiso sigue la regla de siempre
  (`modulo.documento.accion`), lo crea `PermisosSeeder` a partir de las definiciones, y una prueba revisa que toda
  transición de persona tenga un permiso existente. Quién la ejecuta sale de qué roles tienen el permiso (D1).
  Ajustes:
  1. **Varios ámbitos, basta uno.** `ambitoDePermisos()` del trait `TieneEstados` devuelve una lista de ámbitos
     (el proyecto y la unidad, por ejemplo); la persona puede si tiene el permiso global o en **cualquiera** de
     ellos. Hoy todos devuelven `[]` (solo cuenta el permiso global). Prueba con un documento de prueba de dos
     ámbitos: el permiso en el segundo basta; en un tercer ámbito ajeno, no.
  2. **Condiciones adicionales por transición.** Interfaz `CondicionTransicion` (`revisar(documento, usuario,
     transicion)` → nulo si pasa, o el mensaje en español si no). La definición las declara por transición; el
     servicio las corre **dentro del bloqueo, después del permiso**, y si una falla lanza un `ErrorDeNegocio` con su
     mensaje. Hoy ninguna transición real tiene. Prueba con una condición de ejemplo («el monto no supera 1,000.00»):
     pasa debajo, falla encima con su mensaje, y no se escribe historial ni efectos.
     **Para la 1.7:** ahí van la **autoaprobación** (no se prohíbe: se permite con permiso especial, tope de monto,
     motivo y revisión posterior) y los **límites de monto**. Regla anotada en la fila 1.7 del mapa, porque no
     estaba escrita en ningún documento.

- **D7 · Transiciones que requieren aprobación (preparar la 1.7).** **Aprobada 2026-10-20, con dos notas.** Si
  `requiere_aprobacion` es verdadero, ninguna persona la ejecuta directamente: la ejecuta el mecanismo de
  aprobaciones cuando se completa la última firma obligatoria, como transición del sistema con
  `originado_por_user_id` = quien dio esa firma (D5). Hoy queda una interfaz `AprobacionDeTransicion` con una
  implementación que la rechaza con un mensaje claro («Este paso necesita aprobaciones, que todavía no están
  configuradas»). La 1.7 solo reemplaza la implementación en el contenedor. No se ofrece como acción de persona (D9)
  ni como botón (D10). Las condiciones de D6 corren igual al ejecutarla. Pruebas: una persona con el permiso no la
  ejecuta; con una implementación de prueba que diga «completa», el mecanismo la ejecuta y guarda al originador.
  - **Nota para la 1.7 (anotada en su fila del mapa): las firmas se atan a la versión firmada.** Cada firma guarda
    la `version` del documento que se firmó. Si el documento se reabre o vuelve a un estado editable, todas las
    firmas anteriores quedan anuladas y la aprobación empieza de cero.
  - **Sin permiso (decidido 2026-10-20, al cerrar el paso 3).** Una transición con `requiere_aprobacion` no tiene
    permiso, como las del sistema: quién firma vive solo en las reglas de aprobación de la 1.7 (v4 parte 7, matriz
    de aprobaciones como datos). Un permiso por transición sería una segunda configuración que debe coincidir con las
    reglas, y un administrador lo asignaría creyendo que da poder de aprobar. Pedir la aprobación es otra transición
    («Enviar a aprobación») con su propio permiso. La base lo exige (CHECK `NOT requiere_aprobacion OR permiso IS
    NULL`) y la definición rechaza marcarla además como del sistema. `TransicionEstado::esDelSistema()` distingue
    las dos: sin permiso y sin aprobación. La prueba de D6 (paso 6) distingue transiciones del sistema y con
    aprobación, y `barraDeEstados()` (paso 7) reconoce las que esperan aprobación por `requiere_aprobacion`, no por
    el permiso. Si la 1.7 necesita un filtro general de quién puede firmar, será un permiso del mecanismo de
    aprobaciones (nota en su fila del mapa).
  - **Convención: «Por aprobar» no es editable** (escrita en `codigo.md` §Estados y efectos, con D5). Para que no
    dependa de acordarse, la definición lo valida: **el estado de origen de una transición con
    `requiere_aprobacion` debe tener `es_editable = false`**, o la definición no se registra (mensaje claro).
    Prueba.

- **D8 · Cómo se bloquea la edición (el cómo de D5, agregado 1).** **Aprobada 2026-10-20, con tres ajustes.**
  - Trait `TieneEstados` en la cabecera: en un estado `es_editable = false`, guardar cualquier cambio falla, salvo
    las columnas del mecanismo (`estado_id`, `version`, `updated_*`) y las que el modelo declare en
    `camposDerivados()` (la cantidad recibida de una orden). `esEditable()` para que el servicio avise antes.
  - Trait `ParteDeDocumento` en las tablas hijas (líneas): cada una dice su documento, y crear, cambiar o borrar una
    línea falla si el documento no es editable; sus derivados se declaran igual. Prueba con un documento y una línea
    de prueba, por las tres operaciones.
  - El rechazo es un `ErrorDeNegocio`. Un `UPDATE` en SQL a mano se saltaría el modelo (los triggers están
    prohibidos): una prueba de `tests/Arch` revisa que los módulos no usen `DB::table()->update()` sobre tablas de
    documentos con estado; queda lista para la etapa 2.
  Ajustes:
  1. **Los derivados solo cambian dentro de un efecto.** El servicio de transiciones corre cada efecto dentro de un
     contexto «ejecutando efecto» (`ContextoEfectos::durante($efecto, $tarea)`, mismo patrón que
     `ContextoAuditoria::comoUsuario()` de la 1.1, y se restaura aunque el efecto falle). Fuera de ese contexto, el
     trait rechaza cambiar un campo derivado **en cualquier estado**, editable o no. Pruebas: el efecto cambia el
     derivado en un estado no editable; el mismo cambio fuera de un efecto falla; tras un efecto que lanza error, el
     contexto queda cerrado.
  2. **Nota para la 1.8 (anotada en su fila del mapa):** en un documento no editable se pueden agregar adjuntos, pero
     no reemplazar ni borrar los que ya tiene. `esEditable()` es lo que la 1.8 consulta.
  3. **El mensaje solo ofrece las salidas reales.** Dice el estado y menciona solo las transiciones de salida que esa
     definición tiene desde ese estado **y** que esa persona puede ejecutar ahora (permiso con ámbito y condiciones,
     D6), como «anular» o «reabrir»; si no tiene ninguna, no menciona ninguna: «La orden está en «Aprobada» y no se
     puede modificar.» El mensaje sale de las mismas reglas que la barra de estados (D10), no de un texto fijo.
     Pruebas: con permiso de anular, el mensaje lo nombra; sin ningún permiso, no nombra salidas.

- **D9 · Acciones registradas y motivos de catálogo.** **Aprobada 2026-10-20, con cinco ajustes.** Una clase
  `AccionTransicion` con una instancia por transición de persona, registrada sola desde las definiciones: clave
  `modulo.documento.codigo`, el permiso de la transición, y los campos id del documento, `version` (de control,
  tomada al abrir) y el motivo (si lo exige). Ninguna para transiciones del sistema ni con aprobación (prueba). La
  transición que necesita más datos (registrar una adenda) se marca «acción propia» en la definición y tiene su
  acción en su módulo, que llama al servicio. `nucleo.estados.cambiar-presentacion` (D1) con permiso
  `nucleo.estados.editar-presentacion`. Ajustes:
  1. **El servicio revisa todo, no la acción.** Permiso con ámbito, condiciones (D6), bloqueo y versión los revisa
     `TransicionesService`, que recibe al usuario. La acción solo traduce campos. Así una acción propia no puede
     saltárselos. Prueba: una acción propia de ejemplo, con su propio permiso pero sin el de la transición, falla.
  2. **El motivo es de catálogo, no texto libre.**
     - Cada transición que exige motivo tiene **su propia lista**: su `tipo_motivo` (familia), declarado en la
       definición y creado por el sincronizador con código igual a la clave de la transición
       (`compras.orden.anular`). Los de anular una orden no son los de rechazar una valorización.
     - Los motivos de cada lista se crean, renombran y desactivan desde pantalla (acciones `nucleo.motivos.agregar`,
       `…cambiar`, `…desactivar`, permiso `nucleo.motivos.editar`); nunca se borran si se usaron (la FK decide,
       ADR 0013). Las familias no se crean por pantalla: las declara el código.
     - **«Otro» siempre existe**: el sincronizador lo crea en cada familia (`motivos.es_otro`, uno por familia con
       índice único parcial) y no se desactiva. Elegirlo obliga a escribir el detalle, con un mínimo de caracteres
       en el parámetro `motivos.minimo_detalle_otro` (10). La base exige que no esté en blanco; el mínimo lo revisa
       el servicio, porque un CHECK no lee parámetros.
     - Con cualquier motivo se puede agregar un **comentario** opcional.
     - `historial_estados` guarda `motivo_id`, `motivo_detalle` (solo con «Otro») y `comentario`, con integridad en
       la base, sin triggers:
       - `tipo_motivo_id` copiado, con FK `(transicion_id, tipo_motivo_id)` → `transiciones_estado(id,
         tipo_motivo_id)` y FK `(motivo_id, tipo_motivo_id, motivo_es_otro)` → `motivos(id, tipo_motivo_id,
         es_otro)`: el motivo elegido es de la lista de esa transición.
       - CHECK `requiere_motivo = (motivo_id IS NOT NULL)` (D2) y CHECK `motivo_es_otro = (motivo_detalle IS NOT
         NULL)` con `btrim(motivo_detalle) <> ''`.
       - En `transiciones_estado`, CHECK `requiere_motivo = (tipo_motivo_id IS NOT NULL)`.
     - Pruebas: un motivo de otra lista falla en la base; «Otro» sin detalle o con menos del mínimo falla; un motivo
       desactivado no se ofrece pero el historial viejo lo sigue mostrando; «Otro» no se desactiva.
  3. **Motivos adelantados de la 1.9 a la 1.5.** `tipos_motivo` y `motivos` (DBML l.2503-2529) se construyen aquí.
     El mapa ya dice: la 1.5 incluye los motivos; la 1.9 queda con imputación y centros de costo.
     `motivos.es_cambio_cliente` (posible adicional) se crea ahora porque está en la tabla, aunque solo lo usen los
     gastos no presupuestados de la etapa 2. **Convivencia con `motivos_perdida` del CRM: propuesta por aprobar**
     (ver abajo, D9b).
  4. **Regla general** escrita en `docs/convenciones/base-de-datos.md`: lo que se analiza se elige de un catálogo
     o lista fija; texto libre solo como comentario, detalle o detalle de «Otro»; catálogo oficial si existe; lista
     fija si el sistema decide según el valor, catálogo editable si solo describe.
  5. **Notas «PASA A CATÁLOGO» en el DBML** en los 19 campos indicados (9 de motivos, 4 de catálogo oficial, 6 de
     otros catálogos), más tres respondidas después: `contrato_adendas.motivo` (causales de ampliación de la Ley
     32069 y su reglamento), `penalidades.motivo` (junto a `tipo_penalidad_id`) y `aprobaciones.motivo` (1.7: de
     catálogo, con comentario obligatorio). `motivo_cese` anota verificar en la etapa 4 la tabla del T-Registro.
     No se construyen ahora.

- **D9b · Cómo convive `motivos` con `motivos_perdida` del CRM.** **Aprobada 2026-10-20, con dos ajustes:** no se
  edita `docs/diseno/08-…` (análisis de agosto: queda como historia); el cambio se anota en
  `docs/analisis/cambios.md` (CRM 0.4.1 y Común 0.3.2) y en `docs/diseno/00-Reconciliacion-con-v4.md` §4. La lista
  inicial de motivos de pérdida queda en `docs/analisis/bloques/crm.md` como punto a validar con el área comercial.
  DBML: `motivos_perdida` REEMPLAZADA, `oportunidades.motivo_perdida_id` → `motivos`, nota de `motivos` al día.
  El DBML tiene
  `motivos_perdida` (l.688, lista cerrada: precio, plazo, requisito, competencia, sin presupuesto) y anota en
  `motivos` «convive con motivos_perdida hasta unificar»; `tipos_motivo` ya prevé la familia `perdida_oportunidad`.
  Como ninguna de las dos tablas existe todavía, no hay nada que migrar. Propuesta: **unificar desde ya en el
  diseño: `motivos_perdida` no se crea**; la pérdida de una oportunidad es la familia `perdida_oportunidad` de
  `motivos`, y `oportunidades.motivo_perdida_id` apunta a `motivos.id` con la misma FK compuesta de familia, así
  la base impide poner un motivo de anulación como motivo de pérdida. Si la pérdida se registra como transición
  del CRM (Abierta → Perdida), la familia sale sola de su definición; si no, el módulo CRM la declara en
  `RegistroDocumentos`. Se marca `motivos_perdida` como REEMPLAZADA en el DBML y se ajusta la nota de `motivos`.
  Los cinco motivos iniciales los siembra el CRM al construirse (etapa 2), no la 1.5.

- **D10 · Pantallas.** **Aprobada 2026-10-20, con dos agregados** (al final). Corrección al escribirla: los tonos se
  ligan a los colores de Filament (`gray`, `info`, `warning`, `success`, `danger`…), no a `ColoresMarca`, porque
  `interfaz.md` ya dice que los colores de estado siguen el significado de Filament.
  Desde D1 y D9 hay dos cosas que se configuran desde pantalla; la barra de estados, en cambio,
  no tiene todavía ningún documento donde dibujarse (el primero es de la etapa 2). Propuesta:
  - **Se construyen ahora, en «Configuración»:**
    - **Estados de documentos:** lista por tipo de documento (estado, código, nombre, color, si es editable,
      inicial, final, de cancelación); solo se editan nombre y color, con `nucleo.estados.cambiar-presentacion`.
    - **Motivos:** lista por familia (la transición a la que pertenece), con agregar, renombrar y desactivar;
      «Otro» aparece sin la opción de desactivar.
    - Hasta la etapa 2 las dos estarán vacías en el sistema real; se prueban con el documento de prueba.
  - **El color se elige de una lista de tonos con nombre, no se escribe:** neutro, en curso, por aprobar, aprobado,
    alerta, cerrado, anulado, cada uno ligado a un color de Filament (`gray`, `info`, `warning`, `success`,
    `danger`), que ya tiene su versión clara y oscura. Así se ven bien en los dos temas y es coherente con la regla
    de D9 (se elige, no se escribe). `estados.color` guarda el código del tono
    (lista fija con CHECK, porque la pantalla decide cómo pintarlo).
  - **Se dejan para el primer documento real (etapa 2):** el componente de la barra de estados, el menú «Más» con
    «Anular» y «Reabrir» con confirmación (D5), y la pestaña de historial de estados en la ficha. En la 1.5 queda el
    servicio que los alimenta: `barraDeEstados(documento, usuario)` (estados en orden, el actual, las transiciones
    que esa persona puede ejecutar ahora y las que esperan aprobación) y `historialDe(documento)` (con motivo,
    detalle, comentario y originador). El mensaje de D8 usa el mismo servicio.
  - **Agregado 1 · El color nunca va solo:** escrito en `docs/convenciones/interfaz.md` §Reglas. La pantalla de
    estados de la 1.5 muestra el tono junto al nombre; prueba de que la columna lleva los dos.
  - **Agregado 2 · Consulta de los «Otro» para promoverlos a la lista.** `MotivosService::detallesDeOtro(familia,
    desde?)`: por familia, los detalles escritos en «Otro», agrupados por texto normalizado (minúsculas, sin
    tildes con `unaccent`, sin espacios repetidos), con su frecuencia y la fecha del último uso, del más repetido al
    menos. Solo lectura. Lee de `historial_estados`; cuando otros módulos guarden motivos (la oportunidad perdida),
    se suman por una interfaz `FuenteDeMotivos` que cada uno registra, sin que el núcleo lea sus tablas. Pruebas:
    «Precio alto», «precio  ALTO» y «precio álto» cuentan como uno (3); familias separadas no se mezclan; el filtro
    por fecha funciona. La pantalla espera a la etapa 2.

## Pasos

Un commit al cerrar cada paso. Cada paso deja `composer check` en verde.

- [x] 1. **Tablas.** Migración `crear_estados_transiciones_y_motivos`: `tipos_motivo`, `motivos` (con `es_otro`),
      `estados` (con tono, `version` y auditoría), `transiciones_estado` (con `codigo`, `nombre`, `orden`,
      `tipo_motivo_id`, `hacia_es_cancelacion`), `historial_estados` (con `transicion_id`, `requiere_motivo`,
      `tipo_motivo_id`, `motivo_id`, `motivo_es_otro`, `motivo_detalle`, `comentario`, `originado_por_user_id`) y
      `efectos_aplicados` (con `datos`, `ejecutado_at`, `revertido_por_historial_id`). Todas las FK compuestas, CHECK
      e índices únicos parciales de D2, D3 y D9. Parámetro `motivos.minimo_detalle_otro` (10). DBML al día. Pruebas de
      integridad contra la base (cada CHECK y FK compuesta intenta romperse).
- [x] 2. **Modelos y registro.** Modelos de las cinco tablas, `historial_estados` solo de inserción, códigos
      inmutables; morph map. `RegistroDocumentos`, la clase de definición y su validación (anular con motivo, «Por
      aprobar» no editable, un inicial, claves de efecto existentes). Sincronizador idempotente con sus fallas claras
      (estado con documentos, `requiere_motivo` o `es_cancelacion` con historial) y creación de familias con «Otro».
- [x] 3. **Servicio de transiciones.** Documento y línea de prueba en `tests/Modules/Nucleo/Fixtures` (tablas
      creadas en la prueba). `TransicionesService`: crear con estado inicial, transicionar, transición del sistema
      con originador. Bloqueo, versión, permiso con varios ámbitos, condiciones, motivo con «Otro», aprobación
      (`AprobacionDeTransicion` provisional).
- [x] 4. **Efectos.** `Efecto` y `EfectoExterno`, `ContextoEfectos`, `efectos_aplicados` con datos, job idempotente
      después del commit. Anular: efectos vivos en orden inverso con sus datos, `RevisionAntesDeAnular` (también la
      registrada por otro módulo).
- [x] 5. **Bloqueo de edición.** Traits `TieneEstados` y `ParteDeDocumento`, campos derivados solo dentro de un
      efecto, mensaje con las salidas reales. Prueba de `tests/Arch` contra `DB::table()->update()`.
- [x] 6. **Acciones y permisos.** `AccionTransicion` por transición de persona, acción propia de ejemplo,
      `nucleo.estados.cambiar-presentacion`, acciones de motivos; permisos desde las definiciones en `PermisosSeeder`.
- [x] 7. **Lecturas.** `barraDeEstados()`, `historialDe()` y `MotivosService::detallesDeOtro()` con `FuenteDeMotivos`.
- [x] 8. **Pantallas.** «Estados de documentos» y «Motivos» en Configuración, con el tono junto al nombre.
- [x] 9. **Documentos.** `codigo.md` §Estados y efectos con lo construido (nombres reales de clases), skill
      `/nuevo-documento` (definición, efectos con datos, revisiones antes de anular, Reabrir, motivos), ARCHITECTURE
      si cambia, `comun.md` (M3 construido; motivos de M11 construidos) y `cambios.md` si sube la versión.
- [x] 10. Revisión con `revisor-arquitectura`; `composer check` en verde; `/cerrar-tarea`.

## Criterio de aceptación

Todas con el documento de prueba, sin pantalla salvo las del paso 8:

- **Base (paso 1):** una transición entre estados de dos documentos falla; dos estados iniciales en un documento
  fallan; un historial sin motivo en una transición que lo exige falla; un motivo de otra familia falla; «Otro» sin
  detalle falla; una transición a cancelación sin motivo falla; un historial sin `desde` con transición falla.
- **Definición y sincronizador (paso 2):** correrlo dos veces no cambia nada; no pisa nombre ni color editados;
  quitar un estado con documentos falla con «N documentos de tipo X siguen en él»; cambiar un código falla; renombrar
  conserva código e id; «Por aprobar» editable o anular sin motivo no se registran; `historial_estados` no se
  actualiza ni se borra.
- **Transiciones (paso 3):** la creación deja el estado inicial y su primera fila; dos pedidos con la misma versión,
  el segundo falla con `VersionDesactualizada`; sin permiso falla; el permiso en un segundo ámbito basta y en uno
  ajeno no; la condición de ejemplo (1,000.00) pasa debajo y falla encima sin escribir nada; una persona no ejecuta
  una transición del sistema ni una con aprobación; el sistema guarda al originador; «Otro» con menos del mínimo
  falla.
- **Efectos (paso 4):** un efecto interno que falla deja el documento como estaba; un externo no corre si la
  transacción se revierte, corre después del commit y un reintento no lo repite; anular revierte tres efectos en
  orden inverso con los datos guardados aunque el origen haya cambiado; un efecto revertido no se revierte dos
  veces; con un dependiente vivo, anular falla y el mensaje lo nombra; anulado el dependiente, se puede.
- **Edición (paso 5):** cabecera y líneas no se cambian, agregan ni borran en un estado no editable, por servicio,
  acción y modelo; un derivado cambia dentro de un efecto y falla fuera; el contexto se cierra tras un error; el
  mensaje nombra «anular» solo a quien puede.
- **Acciones (paso 6):** `nucleo.prueba.enviar` por `EjecutorAcciones` cambia el estado y deja registro; una acción
  propia sin el permiso de la transición falla; toda transición de persona tiene acción y permiso; ninguna del
  sistema o con aprobación tiene acción.
- **Lecturas (paso 7):** la barra da los estados en orden y solo las salidas que la persona puede usar; «Precio
  alto», «precio  ALTO» y «precio álto» cuentan 3 como uno, sin mezclar familias.
- **Pantallas (paso 8):** solo se editan nombre y color; el tono se muestra con el nombre; «Otro» no tiene
  desactivar.

## Preparado para lo diferido

| Diferido | Dónde se enchufa |
|---|---|
| Aprobaciones (1.7) | Reemplazar la implementación de `AprobacionDeTransicion` en el contenedor; ejecutar la transición como sistema con `originadoPor` = la última firma. Autoaprobación y límites de monto como `CondicionTransicion`. Firmas atadas a la `version` (fila 1.7 del mapa) |
| Adjuntos (1.8) | `esEditable()` decide si se reemplazan o borran; agregar siempre se puede (fila 1.8 del mapa) |
| Alertas (1.10) | Un `EfectoExterno` o un listener; los jobs ya son idempotentes por fila de `efectos_aplicados` |
| Numeración (1.6) | El mensaje de `RevisionAntesDeAnular` muestra el número cuando exista; hoy el id |
| Documentos de la etapa 2 | Definición + `registrar()` en el provider del módulo, `TieneEstados`/`ParteDeDocumento` en sus modelos, efectos en `Efectos/`, `ambitoDePermisos()` con su proyecto (y el ámbito `proyecto` en el morph map y el CHECK de la 1.4) |
| Barra de estados, menú «Más», historial en la ficha | Componente sobre `barraDeEstados()` e `historialDe()`, con el primer documento real |
| Reabrir | Transición declarada en la definición; el servicio ya revierte efectos vivos desde un punto del historial y ya sabe si hubo un `EfectoExterno` ejecutado |
| Motivos de otros módulos (pérdida de oportunidad) | Familia declarada en `RegistroDocumentos`; FK compuesta `(motivo_id, tipo_motivo_id)`; `FuenteDeMotivos` para la consulta de «Otro» |
| Pantalla de los «Otro» | Sobre `MotivosService::detallesDeOtro()` |
| Campos «PASA A CATÁLOGO» | Notas en el DBML y regla en `base-de-datos.md`; cada módulo los convierte al construirse |

## Bitácora

- 2026-10-20 — Rama creada desde `main` (al día con `origin/main`, PR #19). Plan escrito; decisiones D1-D10 por
  aprobar, una por una.
- 2026-10-20 — D1 aprobada con tres ajustes: separación código / nombre y color editables / permisos y
  aprobaciones; el sincronizador no desactiva estados con documentos; el código nunca cambia. D2 suma `version` y
  auditoría a `estados` por el ajuste 1.
- 2026-10-20 — D2 aprobada con dos agregados: motivo exigido por la base (copia + FK compuesta + CHECK, porque
  los triggers están prohibidos) y anular siempre con motivo; `historial_estados` solo de inserción.
- 2026-10-20 — D3 aprobada con tres ajustes: `efectos_aplicados.datos` y `revertir()` con esos datos; orden
  inverso; internos en la transacción y externos por cola después del commit (`ejecutado_at`, idempotente). D5
  ajustada para remitir a D3.
- 2026-10-20 — D4 aprobada: anular revierte todos los efectos vivos en orden inverso (llevado a `codigo.md` como
  interpretación del v4). Agregado: no se anula con documentos posteriores vivos (`RevisionAntesDeAnular`, también
  registrable por el módulo dependiente).
- 2026-10-20 — D5 aprobada con dos agregados: `es_editable` rechaza toda edición venga de donde venga;
  `originado_por_user_id` en el historial de las transiciones del sistema. Patrón «Reabrir» escrito en `codigo.md`
  (no se construye ahora). D8 queda como el cómo del agregado 1.
- 2026-10-20 — D6 aprobada con dos ajustes: lista de ámbitos (basta uno) y `CondicionTransicion` dentro del
  bloqueo, vacías hoy. La regla de autoaprobación se anotó en la fila 1.7 del mapa.
- 2026-10-20 — D7 aprobada. Notas: firmas atadas a la versión (fila 1.7 del mapa); «Por aprobar» no editable
  (`codigo.md`), validado por la definición.
- 2026-10-20 — D8 aprobada con tres ajustes: derivados solo dentro de `ContextoEfectos`; adjuntos en no editables
  (fila 1.8 del mapa); el mensaje solo nombra las salidas que la persona puede usar. Suma `ParteDeDocumento` para
  líneas y la prueba de arquitectura contra `DB::table()->update()`.
- 2026-10-20 — D9 aprobada con cinco ajustes: el servicio revisa todo; motivos de catálogo por transición con
  «Otro» y comentario; motivos adelantados de la 1.9 (mapa al día); regla en `base-de-datos.md`; 19 notas «PASA A
  CATÁLOGO» en el DBML. D2 ajustada (CHECK por `motivo_id`). D9b (unificar `motivos_perdida`) por aprobar.
- 2026-10-20 — D9b aprobada: `motivos_perdida` reemplazada por la familia `perdida_oportunidad`. Anotado en
  `cambios.md` (CRM 0.4.1, Común 0.3.2), reconciliación §4 y `crm.md` (validar la lista con Comercial). El doc 08
  de agosto no se toca. Siguen sin respuesta: anotar `salidas_personal.motivo`, `contrato_adendas.motivo` y
  `penalidades.motivo`; catálogo oficial para `motivo_cese`; `aprobaciones.motivo` de catálogo (1.7).
- 2026-10-20 — D10 aprobada con dos agregados: color siempre con su nombre (`interfaz.md`) y consulta de los «Otro»
  por familia. Tonos ligados a colores de Filament (lo pide `interfaz.md`). Pasos, criterios y «Preparado para lo
  diferido» completos. Sigue: paso 1.
- 2026-10-20 — Respuestas a las tres preguntas: notas «PASA A CATÁLOGO» en `contrato_adendas.motivo` (Ley 32069) y
  `penalidades.motivo` (junto a `tipos_penalidad`); `motivo_cese`: verificar el T-Registro en la etapa 4; observar en
  la 1.7 con motivo de catálogo y comentario obligatorio (fila 1.7 del mapa). Después, `salidas_personal.motivo` también,
  ligado a la misma lista que `motivo_cese`.
- 2026-10-20 — Paso 1 hecho: migración `2026_10_20_000001_crear_estados_transiciones_y_motivos`, parámetro
  `motivos.minimo_detalle_otro` (10, `ValidadorMinimoDetalle`: de 1 a 200), DBML al día y
  `EstadosIntegridadTest` (19 pruebas). `composer check` en verde (1161). Desviaciones y agregados:
  - `tipos_motivo.codigo` mide 80, no 40: es la clave de la transición, como un permiso. Sin `version`: las
    familias no se editan por pantalla.
  - La FK `(transicion_id, requiere_motivo)` de D2 se amplió a `(transicion_id, desde_estado_id, hacia_estado_id,
    requiere_motivo)`: además impide un historial con un salto que su transición no hace.
  - Agregados: FK `(hacia_estado_id, documentable_type)` → `estados` (el historial es del mismo documento que su
    estado); CHECK de cancelación final y no editable; `estados.color` NOT NULL; nombres, detalle y comentario no en
    blanco; en `efectos_aplicados`, `es_externo` (D3 ajuste 3), interno siempre con `ejecutado_at`, `datos` siempre
    objeto y `revertido_por_historial_id` posterior a su fila.
  - `historial_estados` y `efectos_aplicados` sin `auditoria()`, como `auditoria_cambios`: son bitácora.
  - Las pruebas que esperan una falla la corren en un punto de guardado (`sinAbortar`) para revisar varias en una
    misma prueba.
- 2026-10-20 — Paso 2 hecho: modelos `TipoMotivo`, `Motivo`, `Estado`, `TransicionEstado`, `HistorialEstado` (solo
  inserción) y `EfectoAplicado` (solo se marca ejecutado o revertido, una vez), con alias en el morph map; trait
  `CodigoInmutable`; enum `TonoEstado` (tono → color de Filament); `Estados/`: `DefinicionDocumento`,
  `EstadoDefinido`, `TransicionDefinida`, `RegistroDocumentos` (valida al registrar), interfaz `Efecto`;
  `SincronizadorEstados` y `EstadosSeeder` (en `NucleoSeeder`). Fixtures `DocumentoPrueba` y `DefinicionPrueba`.
  Pruebas: `DefinicionDocumentoTest`, `SincronizadorEstadosTest`, `HistorialEstadosTest` (23). `composer check` en
  verde (1184). Decisiones de construcción:
  - Una transición con varios orígenes se declara una vez (`desde` como lista) y se guarda una fila por origen, con
    la misma lista de motivos (`clave.codigo`), llamada «Transición · documento».
  - Las claves de efecto se revisan en el sincronizador, no al registrar: el efecto puede registrarlo otro provider
    que arranca después.
  - Cambiar si un estado es de anulación falla si **alguna transición** llega a él (no solo con historial): la FK
    de `hacia` no cascadea, así que la base lo rechazaría igual; el mensaje lo explica antes.
  - ~~Las transiciones con aprobación conservan su permiso~~: corregido el mismo día, quedan sin permiso (D7, «Sin
    permiso»).
  - `Efecto` nace con `aplicar(documento): array` y `revertir(documento, datos)`; el paso 4 lo completa.
  - El estado actual de un documento vive en su columna `estado_id` (la cuenta el sincronizador al quitar un estado).
- 2026-10-20 — Paso 3 hecho: `TransicionesService` (`crear`, `transicionar`, `transicionarComoSistema`), interfaz
  `DocumentoConEstados` con el trait `TieneEstados` (por ahora solo `ambitoDePermisos()`; el paso 5 le suma el
  bloqueo de edición), `CondicionTransicion`, `AprobacionDeTransicion` con `SinAprobaciones` en el contenedor,
  `MotivoElegido` y `TransicionRechazada`. Fixtures: documento con dos ámbitos (unidades organizativas, el único tipo
  que hoy admite `permisos_por_ambito`), su línea, `MontoHastaMil` y `AprobacionesCompletas`.
  `TransicionesServiceTest` (15). `composer check` en verde (1200). Decisiones de construcción:
  - El sistema solo ejecuta transiciones sin permiso o con aprobación; una de persona, no (mensaje claro). Sin
    versión: actúa sobre el estado que encuentra bajo el bloqueo.
  - La creación no revisa permiso: la acción del módulo que crea ya revisa el suyo.
  - Todo corre con `ContextoAuditoria::comoUsuario()`: `updated_by` del documento es quien hizo la transición.
  - `estado_id` no va en `$fillable` del documento: solo lo pone el servicio.
  - Un motivo desactivado se rechaza al elegirlo; el detalle se guarda sin espacios al borde y se rechaza con un
    motivo que no es «Otro»; el comentario en blanco se guarda como nulo.
  - Los efectos se enchufan en el paso 4, después de escribir el historial y dentro de la misma transacción.
- 2026-10-20 — Decisión del desarrollador: las transiciones con aprobación quedan **sin permiso** (D7, «Sin
  permiso»). Hecho: `DefinicionDocumento::permiso()` las deja nulas, la definición rechaza aprobación más sistema,
  CHECK `transiciones_estado_aprobacion_check` (migración editada: la rama no está en `main`; una base de desarrollo
  que ya la corrió necesita reconstruirse), `esDelSistema()` las distingue y el servicio rechaza primero por
  aprobación. DBML y fila 1.7 del mapa al día. Pendiente en los pasos 6 y 7 lo que dice D7.
- 2026-10-20 — Pausa: llegaron dos tareas de interfaz (1.4f, un solo modo; y «Mis datos»). Orden propuesto al
  desarrollador: terminar la 1.5 (sigue el paso 4), cerrar la 1.4f y luego planear «Mis datos».
- 2026-10-20 — Paso 4 hecho: interfaz `EfectoExterno` (`preparar` en la transacción, `ejecutar` y `revertir` en
  cola), `ContextoEfectos` (singleton, `durante()` y `activo()`), jobs `EjecutarEfectoExterno` y
  `RevertirEfectoExterno` (`ShouldQueueAfterCommit`, bloquean su fila y no repiten), `RevisionAntesDeAnular` con
  `DocumentoQueImpide` (en la definición o por `RegistroDocumentos::revisionAntesDeAnular()` desde otro módulo) y
  `TransicionesService` que aplica los efectos tras el historial y, al anular, revisa dependientes dentro del
  bloqueo y revierte los vivos en orden inverso. Fixtures: efectos A, B y C que anotan en `anotaciones_prueba`,
  `EfectoQueFalla`, `AvisoDePrueba`, `DependientesDePrueba` (`origen_id` en el documento de prueba).
  `EfectosTest` (13) y dos casos en `EstadosIntegridadTest`. `composer check` en verde (1215). Desviaciones y
  agregados:
  - **Columna nueva `efectos_aplicados.reversion_ejecutada_at`** (migración editada: la rama no está en `main`) con
    CHECK `efectos_aplicados_reversion_check` (solo externo, ejecutado y revertido). Sin ella, revertir un externo
    por cola no podía ser idempotente, como pide D3 («su `revertir()` sigue la misma regla»). DBML al día.
  - Un externo que no llegó a ejecutarse se marca revertido y no se encola su reversión; su job de envío, al ver la
    marca, ya no lo envía. La anulación bloquea las filas de efectos vivos, así que espera a un envío en curso.
  - Las revisiones de anular corren antes de cambiar nada; la reversión, después de escribir el historial (la FK y
    el CHECK piden que la fila que revierte exista y sea posterior); luego, los efectos propios de la transición.
  - `datos` vacío se guarda como `{}` desde el modelo (la base exige objeto).
  - El sincronizador falla con una revisión registrada para un documento sin definición y con un efecto aplicado y
    vivo cuya clave ya nadie registra (no se podría anular): la parte «guardada» de la prueba de D3.
  - «Un efecto revertido no se revierte dos veces»: lo cubren la consulta (solo vivos), el modelo (la marca se pone
    una vez, con prueba) y el job de reversión (no repite, con prueba). Una segunda anulación no existe: el estado
    de anulación es final.
  - `revertirEfectosVivos()` ya recibe «después de qué fila del historial» para «Reabrir»; hoy nadie lo pasa.
  - Si un job externo agota sus intentos, registra solo el id (regla 10); el aviso al responsable llega con la 1.10.
- 2026-10-20 — Paso 5 hecho: `BloqueoEdicion` (servicio que usan los traits), `TieneEstados` (bloquea guardar y
  borrar la cabecera; `camposDerivados()`, `esEditable()`), `ParteDeDocumento` (crear, cambiar y borrar líneas;
  `documentoConEstados()`), `EdicionRechazada` y `TransicionesService::salidasDisponibles()` (de persona, con permiso
  en algún ámbito y condiciones cumplidas; la barra de la 7 la reutiliza). Prueba de `tests/Arch` contra escrituras
  que se saltan el modelo. Fixtures: línea con `ParteDeDocumento`, `total_recibido` derivado en la cabecera,
  `ServicioDocumentoPrueba`, acción `RenombrarDocumentoPrueba` y efecto `RecibirTodo`. `EdicionDocumentosTest` (13).
  `composer check` en verde (1230). Decisiones de construcción:
  - El estado se lee de la base al guardar, no del modelo: una instancia leída antes del cambio no se salta el
    bloqueo (con prueba).
  - Dentro de un efecto solo se libera lo derivado: lo demás sigue bloqueado en un estado no editable.
  - Las salidas del mensaje son las de quien guarda según `ContextoAuditoria` (actor de la acción o sesión); sin
    persona conocida, no nombra ninguna.
  - Agregado: `DefinicionDocumento::articulo()` («el» por omisión) y `nombreConArticulo()`. Los mensajes decían
    «el {nombre}» y «un {nombre}», que habría dado «el orden de compra». `noDisponible` pasa a «No se puede hacer
    «Enviar»: el documento de prueba está en «Enviado».».
  - La prueba de arquitectura busca los modelos con los traits en `app/Modules` y vigila `DB::table(...)` con
    escritura, `DB::update/statement(...)` con la tabla en el SQL y la actualización masiva de Eloquent
    (`Modelo::query()->where()->update()`, que no dispara eventos). No ve la masiva por relación
    (`$orden->lineas()->update()`). Hoy ningún módulo usa los traits; el detector se prueba con ejemplos y con los
    fixtures.
- 2026-10-20 — Decisión del desarrollador: el estado lo pone solo `TransicionesService`. `ContextoTransicion`
  (singleton, como `ContextoEfectos`) envuelve el guardado en `crear` y en cada transición. `TieneEstados` rechaza
  crear la cabecera o cambiar `estado_id` fuera de ese contexto, con un `LogicException` (es un error de
  programación). Así ningún cambio de estado se queda sin historial. Las dos pruebas del sincronizador que arman
  documentos a mano en «Enviado» ahora lo hacen dentro de `ContextoTransicion::durante()`. `composer check` en
  verde (1231).
- 2026-10-20 — Paso 6 hecho: `AccionTransicion` (una por transición de persona, registrada sola por
  `RegistroDocumentos::registrar()`; campos `documento_id`, `version` de control, `motivo_id` y `motivo_detalle` si
  exige motivo, `comentario` siempre opcional), `TransicionDefinida::accionPropia`, `PermisosSeeder` con los permisos
  de las definiciones, `EstadosService::cambiarPresentacion()` con `nucleo.estados.cambiar-presentacion`,
  `MotivosService` con `nucleo.motivos.agregar`, `…cambiar`, `…desactivar` y `…activar` (permiso
  `nucleo.motivos.editar`), y `MotivoRechazado`. Fixture: acción propia `RegistrarAdendaPrueba`.
  `AccionesEstadosTest` (12). `composer check` en verde (1243). Decisiones de construcción:
  - **`Accion::permisoPorAmbito()`**. `EjecutorAcciones` exigía el permiso global (`can()`): habría rechazado a quien
    tiene el permiso de la transición solo en un ámbito (D6). Con `true`, el ejecutor no lo exige y lo revisa el
    servicio con los ámbitos del documento (D9, ajuste 1). Sigue rechazando al sistema y a quien está inactivo. Lo
    usa `AccionTransicion`. Pendiente para la etapa 2: `AccionFilament` muestra la acción según `can()`; el botón
    de una transición saldrá de `barraDeEstados()` (paso 7), no de ahí.
  - La clave de la acción es la misma que el permiso (`nucleo.prueba.anular`) y el nombre, «Anular documento de
    prueba».
  - `accionPropia` en una transición del sistema o con aprobación no se registra. Su permiso igual se crea: lo
    revisa el servicio cuando la acción del módulo lo llama.
  - Agregado: `nucleo.motivos.activar`. Sin él, desactivar no tenía vuelta atrás.
  - El código de un motivo nuevo sale de su nombre (`Precio alto` → `precio_alto`, hasta 40). Dos motivos de una
    lista no pueden tener el mismo nombre sin contar mayúsculas, tildes ni espacios. El código no cambia al
    renombrar.
- 2026-10-20 — Paso 7 hecho: `EstadosService::barraDeEstados()` (`BarraDeEstados` con `EstadoEnBarra`,
  `SalidaDeEstado` y los nombres de lo que espera aprobación) e `historialDe()` (`FilaHistorialEstados`, del primer
  cambio al último); `MotivosService::detallesDeOtro(familia, desde?)` (`DetalleDeOtro`) con `FuenteDeMotivos`
  registrable en `RegistroDocumentos`. Fixture `FuenteDeMotivosPrueba`. `LecturasEstadosTest` (7). `composer check`
  en verde (1250). Decisiones de construcción:
  - Las salidas de la barra salen de `TransicionesService::salidasDisponibles()`, lo mismo que el mensaje de edición
    bloqueada. Cada salida lleva la clave de su `AccionTransicion`, o nula si es de acción propia (la ofrece su
    módulo). Lo que espera aprobación va aparte, por `requiere_aprobacion` (D7).
  - La barra muestra los estados activos en su orden, más el actual aunque estuviera desactivado.
  - Normalización de «Otro» en SQL: `regexp_replace(lower(unaccent(btrim(…))), '\s+', ' ', 'g')`, pública en
    `MotivosService::normalizacionSql()` para que las fuentes de otros módulos agrupen igual. Se juntan por clave:
    se suman las veces, se toma la fecha más reciente y el texto de esa última vez. Orden: más veces primero y, a
    igualdad, el uso más reciente.
- 2026-10-20 — Paso 8 hecho: `EstadoResource` («Estados de documentos») y `MotivoResource` («Motivos») en el clúster
  Configuración, solo lectura más sus acciones registradas. `PantallasEstadosTest` (8). `composer check` en verde
  (1258). Decisiones de construcción:
  - El estado se muestra como etiqueta pintada con su tono y, debajo, «Color: En curso»: el color nunca va solo
    (`interfaz.md`). La prueba revisa el color y el texto de la columna.
  - Solo los estados activos, por documento y en su orden, con filtro por documento. Motivos por lista, con «Otro»
    al final; desactivar y activar se alternan con confirmación, y «Otro» no tiene desactivar.
  - Las dos pantallas se ven con `catalogos.ver`, como el resto de Configuración. La descripción de la caja suma
    «estados y motivos».
  - Error repetible anotado en `AGENTS.md`: en los closures de Filament los parámetros van con el nombre que inyecta
    (`$query`). Con `$consulta`, la tabla recibía un `Builder` vacío.
- 2026-10-20 — Paso 9 hecho:
  - `codigo.md` §Estados y efectos reescrita con las clases reales: definición y sincronizador, servicio, acciones y
    `accionPropia`, efectos internos y externos, anular, revisiones, bloqueo de edición y derivados, motivos,
    lecturas y «Reabrir».
  - Skill `/nuevo-documento` rehecho sobre el mecanismo, con el documento de prueba como ejemplo.
  - `ARCHITECTURE.md`: el párrafo de documentos con estados dice que el módulo declara una definición y que el
    estado solo cambia por el servicio.
  - `comun.md`: M3 construido con pruebas (tarea 1.5); en M11, el catálogo de motivos adelantado y construido.
  - `mapa-etapas.md`: lo preparado para la 1.6 (concurrencia de transiciones, número en el mensaje de anular), la
    1.7 (cómo se enchufa) y la 1.10 (aviso cuando un efecto externo agota sus intentos).
  - `cambios.md` no cambia: el estado de un mecanismo lo da el desarrollo y no sube la versión del bloque
    (`versionado.md`, «El estado de cada pieza»).
- 2026-10-20 — Paso 10, revisión con `revisor-arquitectura`: 2 bloqueantes, 5 a corregir, 5 sugerencias. Corregido:
  - **Bloqueante 1 (envío externo dentro de una transacción):** el desarrollador eligió enviar fuera de toda
    transacción, con reserva que vence (D3 ajustada arriba). `EfectosExternosService` hace reservar → enviar →
    confirmar o liberar; los jobs solo lo llaman y vuelven a la cola si el efecto está reservado (`$timeout` 60,
    reserva 75 s, `retry_after` 90). Columnas `reservado_hasta` y `reversion_reservada_hasta` con su CHECK.
    `EfectoExterno::ejecutar()` y `revertir()` reciben la clave de idempotencia. `salioAlExterior()` para «Reabrir».
    El CHECK de reversión acepta un externo reservado sin confirmar (pudo salir). La prueba de «sin transacción»
    compara el nivel dentro del envío con el de fuera del job: `RefreshDatabase` envuelve la prueba en una.
  - **Bloqueante 2 (unicidad del nombre de un motivo solo en PHP):** `motivos.nombre_comparable` con CHECK
    (`regexp_replace(lower(unaccent(btrim(nombre))), '\s+', ' ', 'g')`, la misma normalización que los «Otro») e
    índice único por familia; el modelo lo pide a la base, como `party_sedes`. El servicio revisa antes con la misma
    columna, y un código que choca sin que choque el nombre lleva `_2`, `_3`.
  - La prueba de arquitectura de escrituras crudas no revisa `database/migrations` (el DDL es obligatorio ahí).
  - El estado se lee con bloqueo compartido al guardar (una línea agregada durante una aprobación espera). La prueba
    con procesos en paralelo queda para la 1.6, como la de transiciones.
  - **Un documento con estados no se borra nunca** (v4, parte 1, idea 3: nada se borra): se anula. Así no quedan
    efectos vivos ni historial sin documento. Antes se permitía en un estado editable.
  - `crear()` rechaza un documento ya guardado.
  - Prueba en `tests/Arch`: solo `AccionTransicion` declara `permisoPorAmbito()`. En `codigo.md`: el botón de una
    transición sale de `barraDeEstados()`, no de `AccionFilament`.
  - Sugerencias aplicadas: CHECK `historial_estados_otro_check` («Otro» siempre con motivo); `codigo.md` pide que una
    acción propia guarde y transicione en la misma transacción y que las condiciones solo lean. No aplicada: que
    `AccionTransicion` diga «no encontrado» antes que «sin permiso» (el servicio necesita el documento para revisar
    los ámbitos; riesgo menor).
- 2026-10-20 — Paso 10 hecho: revisión atendida, `composer check` en verde (1269 pruebas, 5654 aserciones), plan
  movido a `completados/`. Sigue (orden propuesto al desarrollador): cerrar la 1.4f y planear «Mis datos». Quedan
  para otras tareas: concurrencia real de transiciones y de líneas (1.6), aprobaciones (1.7), aviso de efectos
  externos fallidos (1.10), barra de estados en pantalla con el primer documento real (etapa 2).
