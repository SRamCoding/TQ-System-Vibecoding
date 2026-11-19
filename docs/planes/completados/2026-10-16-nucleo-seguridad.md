# Plan: 1.4 · Seguridad: usuarios, organigrama, permisos por ámbito, campos sensibles y cruces de funciones

- **Estado:** completado
- **Módulo(s):** Nucleo (M10; toca M1 y M4)
- **Etapa:** 1 · tarea 1.4 · rama `nucleo-seguridad`
- **Creado:** 2026-10-16 · **Actualizado:** 2026-10-18

## Objetivo

Cada usuario es una persona (party), tiene un puesto en el organigrama y uno o varios roles, globales o acotados a
un ámbito. Ve la suma de sus roles, menos los campos sensibles que ninguno de ellos le concede. Esto vale para las
fichas y también para el historial, y se filtra en el servidor. Asignar roles que juntan dos permisos incompatibles
no se prohíbe, pero exige registrar un control compensatorio y avisa a Gerencia General. Todo se hace con acciones
registradas, que también corren sin pantalla.

## Contexto que leí

- `AGENTS.md`, `docs/planes/mapa-etapas.md` (fila 1.4), `docs/planes/plantilla.md`.
- `docs/diseno/v4/parte-7-transversal.md` l.1-44 (matriz L/E/A/P, restricciones de campo) y l.104 (alerta «Cruce
  de funciones → Gerencia General»).
- `docs/diseno/v4/parte-5-interfaz-roles.md` l.130-160: puesto ≠ rol, la grilla sale de los flujos, vista de
  conflictos (Lisset registra y aprueba el mismo pago → control compensatorio). La parte 7 dice «parte 3», pero la
  grilla está en la parte 5.
- `docs/diseno/v4/parte-1-modelo-mental.md` l.232-237 (glosario: rol y puesto, separación de funciones).
- `docs/diseno/v4/parte-8-criterios-aceptacion.md` l.85-86: «Se asigna a Lisset el rol de aprobar pagos además de
  registrarlos → la grilla marca el cruce y exige registrar un control compensatorio».
- DBML v2 l.491-536 (`users`, `unidades_organizativas`, `permisos_por_ambito`) y l.2616-2655 (`puestos`,
  `restricciones_campo`, `cruces_funciones`).
- Plan 1.3 cerrado: deuda de la 1.4 (l.34-36), D10 (historial y `CamposVisibles`), l.188-200.
- Código: `App\Models\User` (86 archivos lo usan), migración `2026_10_10_000002` (usuario de sistema, correo en
  minúsculas), `PermisosSeeder`, `CamposVisibles`/`TodosLosCamposVisibles`, `HistorialLegible`, `PartyResource`
  (cuentas bancarias por `parties.ver-cuentas-bancarias`).
- `docs/decisiones/pendientes.md`: **nada bloquea.** Lo cercano no es de esta tarea: «Cuentas bancarias nuevas»
  (1.7), «RRHH y planilla» (uso real de la planilla), «Aniversario» (1.4b).

## Deuda de tareas anteriores

| Deuda | Dónde | Por qué |
|---|---|---|
| `users.party_id`, en la misma migración que completa `users` (plan 1.3) | **1.4**, paso 2 | Es esta tarea |
| `User` a `Nucleo\Models`, con prueba de que nadie fuera del núcleo escribe sus modelos (plan 1.3) | **1.4**, paso 1 | Va con completar `users` |
| Morph map (`enforceMorphMap`, `User` incluido) (plan 1.3) | **1.4**, paso 1 | spatie guarda `model_type` y `auditoria_cambios` guarda `auditable_type` |
| `CamposVisibles` real; el historial oculta los campos que la persona no puede ver (plan 1.3, D10) | **1.4**, paso 5 | Es esta tarea |

## Decisiones

### Tomadas (2026-10-16, por el desarrollador)

- **D1 · Un cruce de funciones es un par de permisos, no de roles.** El DBML compara `rol_a`/`rol_b`. Así no se ve
  el caso del v4: Tesorería registra y aprueba el mismo pago dentro de un solo rol. Por eso hay un catálogo de
  **permisos incompatibles** y se revisa contra la **suma** de los permisos efectivos de la persona (sus roles
  globales y los acotados a un ámbito). El catálogo es un dato: se edita en pantalla y nace vacío, porque los
  permisos de pedir, aprobar, registrar pago o aprobar pago llegan con las etapas 2 y 3. Cada tarea que agregue un
  permiso de aprobar agrega también su par.
- **D2 · Un campo sensible está oculto salvo que un permiso lo muestre.** Así encaja con «ve la suma de sus roles».
  Si se olvida configurar algo, falla cerrado: el dato no se ve. Este enfoque reemplaza la tabla
  `restricciones_campo` del DBML (ver D6).
- **D3 · Roles de inicio: solo los técnicos.** No se inventa la grilla de negocio. Se carga el rol `administrador`
  (ver D8) y el mecanismo para que Gerencia arme la grilla real como dato. Se anota en pendientes: «Grilla de roles
  real (12 a 15 roles desde los flujos, asignados a las 22 personas)», con Gerencia.

### Propuestas (por aprobar)

- **D4 · `users` completa, en una sola migración.** **Aprobada 2026-10-16, con dos ajustes.**
  - `party_id` FK a `parties`, con índice. Se exige con `CHECK (es_sistema OR party_id IS NOT NULL)`: el usuario
    de sistema no es una persona.
  - **Ajuste 1 · Una persona, una cuenta, por clave de persona (1.3b), no por `party_id`.** Si fuera por
    `party_id`, Juan podría tener un usuario en su ficha DNI y otro en su ficha RUC 10.
    - `users` copia la clave de su persona (`persona_tipo_id`, `persona_numero`), igual que
      `party_cuentas_bancarias`.
    - La FK compuesta `(party_id, persona_tipo_id, persona_numero)` apunta a la restricción `parties_id_clave_persona_unique`,
      que ya existe: la copia no puede desviarse de la ficha.
    - `UNIQUE (persona_tipo_id, persona_numero)`.
    - El usuario de sistema deja las tres columnas en nulo: un CHECK exige las tres juntas o ninguna.
  - **Además, el usuario apunta a una ficha de documento personal (DNI, CE o pasaporte), nunca a un RUC.** Lo valida
    el servicio con su prueba: un CHECK no puede leer el código del tipo en otra tabla. La unicidad sí la garantiza
    la base.
  - `puesto_id` FK a `puestos`, nulo mientras el organigrama real no esté cargado.
  - **No se agrega `users.unidad_organizativa_id`:** la unidad sale del puesto (`puestos.unidad_organizativa_id
    NOT NULL`). Si estuviera en los dos lados, habría dos verdades que pueden contradecirse.
  - `ultimo_acceso timestamptz` nulo, lo llena un listener del evento `Login`.
  - `name` se queda (Filament y el framework lo usan) y se copia de la persona.
  - **Ajuste 2 · Cuando se edita el nombre de la persona, `users.name` se actualiza en la misma transacción.**
    `PartiesService` lo hace al editar la party; las dos tablas son del núcleo. Prueba: editar el nombre de la
    persona cambia el `name` del usuario; si la edición falla, no cambia ninguno de los dos.
  - El cumpleaños **no** va aquí: lo agrega la 1.4b en su propia migración.
- **D5 · Organigrama.** **Aprobada 2026-10-16, con el jefe como puesto.**
  - `unidades_organizativas` sigue el DBML. Le agrego: código **único**, CHECK de `tipo`, y ninguna unidad puede ser
    su propio ancestro. Esto último lo verifica el servicio con bloqueo de fila: no se puede expresar como CHECK.
  - **El jefe es un puesto, no una persona.** `jefe_puesto_id` reemplaza al `jefe_user_id` del DBML. Así, cuando
    cambia quien ocupa el puesto, la jefatura se actualiza sola y una vacante queda a la vista.
    - Es nulo mientras no esté definido.
    - La FK es compuesta, `(jefe_puesto_id, id)` → `puestos (id, unidad_organizativa_id)`: el puesto de jefatura es
      de la misma unidad.
    - Un puesto de jefatura tiene como máximo un ocupante activo. Lo revisa el servicio, con bloqueo de la fila del
      puesto, al asignar un puesto a un usuario y al designar la jefatura de una unidad.
    - `jefeDe(unidad): ?User` devuelve el ocupante, o nulo si el puesto está vacante.
    - Los encargos temporales son delegaciones de la 1.7.
  - `puestos` sigue el DBML y le agrego **único** `(unidad_organizativa_id, nombre)`.
  - Tablas vacías: el organigrama real es dato pendiente, como «el organigrama propuesto debe revisarse» del v4.
- **D6 · Campos sensibles, declarados en el modelo.** **Aprobada 2026-10-16, con dos agregados:**
  - **Prueba de arquitectura contra el olvido.**
    - Para cada modelo de `app/Modules`, lee las columnas reales de su tabla en la base, no las migraciones.
    - Falla si una columna con nombre sospechoso no está en `camposSensibles()` ni en `camposNoSensibles()`.
    - Nombres sospechosos: `costo`, `precio`, `margen`, `sueldo`, `remuneracion`, `salario`, `cci`, `cuenta`,
      `medic`, `examen`, `utilidad`. La lista vive en un solo lugar.
    - `camposNoSensibles(): array<string, string>` exige un motivo por columna.
    - Un olvido rompe `composer check`.
  - **Los valores calculados heredan el grupo.** Un margen, un total o una cifra de reporte o de tablero calculada
    a partir de campos sensibles pertenece al mismo grupo y se oculta igual: en accessors, columnas de tabla,
    exportaciones y tarjetas de tablero. Se escribe en `codigo.md` y en el ADR 0016.
  - Cada modelo declara qué columnas son sensibles y de qué grupo: `camposSensibles(): array<string,
    GrupoSensible>`.
  - `GrupoSensible` es un enum: `costos`, `margen`, `remuneracion`, `detalle_medico`, `precio_compra` (por evaluar
    en el v4) y `datos_bancarios`.
  - Cada grupo tiene su permiso `campos.ver-<grupo>`.
  - La sensibilidad vive en el código, junto a la columna. Así nadie la puede apagar desde una pantalla y no hace
    falta la tabla `restricciones_campo`. Lo configurable es qué rol tiene cada permiso.
  - `solo_lectura` del DBML no se construye: editar ya lo controla el permiso de cada acción.
  - **Primer uso real:** `PartyCuentaBancaria` (número y CCI → `datos_bancarios`). Reemplaza a
    `parties.ver-cuentas-bancarias`, que hoy se revisa a mano en la ficha. Con eso, la prueba del historial usa un
    modelo de verdad.
  - Va con un ADR (0016): se aparta del DBML.
- **D7 · Permisos acotados a un ámbito.** **Aprobada 2026-10-16, con tres precisiones:**
  - **Los cruces suman lo global y lo acotado.** La detección de D1 usa los permisos efectivos de la persona: sus
    roles globales y todos sus roles acotados vigentes o futuros. Un cruce no puede esconderse dándolo solo en un
    ámbito. Se revisa también al asignar un rol en un ámbito (D9).
  - **`ambitosDonde($usuario, $permiso, $tipoAmbito)`** devuelve los ids donde la persona tiene ese permiso hoy, o
    «todos» si lo tiene por un rol global. Sirve para que las listas se filtren en la base con un `whereIn`, y no
    registro por registro con `puede()`.
  - **El día `hasta` es vigente, en hora de Lima**, igual que los roles de party de la 1.3: vigente si
    `desde <= hoy` (o `desde` vacío) y `hasta >= hoy` (o `hasta` vacío). CHECK `hasta >= desde`.
    - Pruebas en el borde, con el reloj fijo:
      - el día `hasta` a las 23:59 de Lima, vigente;
      - el día siguiente a las 00:00 de Lima, no vigente (en UTC todavía es el mismo día);
      - antes de `desde`, no vigente.
    - El «hoy en Lima» hoy está copiado tres veces (`PartiesService`, `PartyResource`, `ContactosDeParty`). Pasa a una
      sola pieza del núcleo, que esta tarea también usa.
  - `permisos_por_ambito` asigna **un rol** (FK a `roles`, no `varchar`) a un usuario dentro de un ámbito
    polimórfico, con `desde`/`hasta`. `user_id` y `rol_id` son NOT NULL.
  - El DBML trae `rol` y `permiso` sueltos. Yo uso el rol porque es la unidad que se asigna en la grilla.
  - La consulta es `SeguridadService::puede($usuario, $permiso, ?$ambito)`: sí, si lo da un rol global o un rol
    acotado a ese ámbito y vigente hoy.
  - Hoy el único ámbito que existe es `unidad_organizativa`, así que la prueba lo usa. Los ámbitos `proyecto`,
    `contrato` y `almacen` se suman al morph map cuando nazcan; queda anotado en «Preparado».
  - La herencia entre ámbitos (un contrato incluye sus proyectos) queda para cuando existan esos modelos.
  - Los permisos van **solo por roles**. Ninguna acción da permisos directos a un usuario (spatie lo permite, pero
    rompería la grilla).
- **D8 · Administrador.** **Aprobada 2026-10-16, con tres agregados:**
  - **Nadie se cambia a sí mismo.** Nadie se asigna ni se quita roles, ni acotados ni globales, ni cambia los
    permisos de un rol que él mismo tiene. Si un administrador necesita un rol de negocio, se lo asigna otro
    administrador. Con pruebas.
  - **Todo cambio de roles emite un evento:** `RolAsignado`, `RolQuitado`, `PermisosDeRolCambiados`, con quién,
    a quién, qué rol, el ámbito y los permisos antes y después. El aviso a Gerencia lo conectan la 1.8/1.10.
  - **Prueba de que con `APP_ENV=production` el seeder no crea el usuario de desarrollo.**
  - Consecuencias:
    - Los permisos del rol `administrador` se fijan en el seeder y la grilla los muestra bloqueados. Cambiarlos
      exigiría un administrador sin ese rol, que no existe.
    - El primer administrador lo crea `php artisan seguridad:primer-administrador`, a nombre del usuario de
      sistema. El comando se niega si ya hay un administrador activo.
  - El rol `administrador` tiene solo los permisos de configuración y seguridad: usuarios, roles, organigrama,
    catálogos y parámetros. No tiene los de negocio, para no nacer en conflicto con todo el catálogo de
    incompatibles.
  - No hay `Gate::before` de superusuario: se saltaría los campos sensibles y los cruces.
  - El usuario de desarrollo (solo en `local`) sigue recibiendo todo.
  - La base nunca se queda sin un usuario activo que pueda gestionar usuarios. Lo verifica el servicio con bloqueo
    de fila.
- **D9 · Registro de cruces.** **Aprobada 2026-10-16, con tres ajustes: cierre automático, pendientes de control
  y responsable.**
  - `incompatibilidades_permisos` guarda pares de permisos: `permiso_a_id < permiso_b_id` con CHECK (así el par no
    se repite al revés), es **único**, y cada par lleva su motivo.
  - **`cruces_funciones` es el cruce de una persona**, y existe mientras ella tenga los dos permisos.
    - Columnas: `user_id`, `incompatibilidad_id`, `desde`, `hasta`.
    - Hay un único parcial abierto por `(user_id, incompatibilidad_id)`.
    - **Se cierra solo** (`hasta` = hoy en Lima) cuando la persona pierde uno de los dos permisos: se le quita el
      rol, vence su rol acotado, el rol pierde el permiso o se borra el par. Nunca se borra la fila.
    - `aprobado_por` es nulo; lo llenará la 1.7 si Gerencia aprueba cada cruce.
  - **`controles_cruce` guarda el control compensatorio**, con su propia vigencia.
    - Columnas: `cruce_id`, `control` (texto, NOT NULL), `responsable_user_id` (NOT NULL, FK a `users`),
      `vigente_hasta` (nulo = sin vencimiento).
    - Un único parcial vigente por cruce. Renovar el control cierra el anterior; la historia queda.
    - **El responsable no puede ser la persona del cruce.** La FK compuesta copia `user_id` del cruce y un CHECK
      exige `responsable_user_id <> user_id`.
  - **Estado del cruce, calculado:** *controlado* si tiene un control vigente hoy; *pendiente de control* si no.
    `SeguridadService::crucesPendientes()` los lista y la grilla los muestra.
  - **Cuándo se revisa**, en la misma transacción que el cambio:
    - asignar un rol a una persona, asignar un rol en un ámbito o **agregarle un permiso a un rol** (este último
      revisa a todas las personas que lo tienen). Exigen el control (texto y responsable) de cada cruce nuevo; si
      falta, no se guarda;
    - **agregar un par al catálogo no se bloquea.** Los cruces que ya existían nacen *pendientes de control*,
      visibles y con aviso a Gerencia hasta que se registre su control.
  - **Eventos:** `CruceFuncionesRegistrado` (nuevo, con o sin control), `CruceFuncionesCerrado` y
    `ControlCruceRegistrado`.
  - **Avisos:** un control vencido con los permisos todavía en pie pasa solo a *pendiente*. El aviso diario a
    Gerencia de «cruces sin control» es una alerta de la 1.10 (idempotente): usa `crucesPendientes()`. Lo mismo el
    cierre por vencimiento de un rol acotado, porque no hay un cambio que lo dispare. La 1.4 cierra en el momento
    los cruces que dispara una acción; los que vencen solos se cierran cuando el job de la 1.10 los revisa. Mientras
    tanto, `crucesPendientes()` ya no cuenta como cruce a quien no tiene los dos permisos vigentes hoy.
- **D10 · Pantallas.** **Aprobada 2026-10-16. La grilla pasa a la 1.4c.**
  - **En la 1.4:**
    - **Usuarios:** crear con su persona (solo DNI, CE o pasaporte), correo y puesto; asignar y quitar roles
      globales o acotados con sus fechas. Si un rol crea un cruce, el formulario pide ahí mismo el control y el
      responsable. Desactivar. Último acceso en la lista.
    - **Organigrama:** unidades en árbol, cada una con su puesto de jefatura y su ocupante, o «vacante»; puestos
      dentro de cada unidad.
  - **En la 1.4c (solo pantalla):** grilla de roles × permisos con los campos sensibles, vista de conflictos
    (controlados y pendientes, con su control, responsable y vencimiento) y catálogo de permisos incompatibles.
  - **Condición de la 1.4c:** la 1.4 deja construidas y probadas, sin pantalla, todas las acciones y servicios de
    roles y permisos: crear o editar un rol, cambiar los permisos de un rol (con la revisión de cruces a todas las
    personas que lo tienen), registrar o quitar un par incompatible (con sus pendientes), registrar o renovar el
    control de un cruce y consultar los cruces. La 1.4c no agrega lógica.
  - Toda la lógica está en servicios y acciones; Filament solo llama. La versión de control se captura al abrir;
    los selectores incluyen el valor actual aunque esté inactivo.

## Pasos

Un commit al cerrar cada paso.

- [x] 1. `User` a `App\Modules\Nucleo\Models\User` (config `auth`, factory, 86 usos). Morph map con
  `enforceMorphMap` para todo modelo auditado, `User` y los de spatie. Prueba de arquitectura: todo modelo con
  `ConAuditoria` está en el mapa, y fuera del núcleo nadie escribe modelos del núcleo. Migración de datos que
  pasa los `model_type`/`auditable_type` guardados con el nombre de clase completo a su alias (base de
  desarrollo).
- [x] 2. Migraciones: `unidades_organizativas`, `puestos`, completar `users` (D4), `permisos_por_ambito` (D7),
  `incompatibilidades_permisos`, `cruces_funciones` y `controles_cruce` (D9). Modelos y factories.
- [x] 3. `OrganigramaService` y sus acciones (unidad y puesto: crear, editar, desactivar), sin ciclos. Pruebas.
- [x] 4. `SeguridadService`: permisos efectivos (globales y por ámbito), `puede()`, detección de cruces. Acciones:
  registrar usuario, asignar o quitar rol, asignar rol en ámbito, cambiar los permisos de un rol, registrar un par
  incompatible, desactivar usuario (nunca el último administrador). Prueba de aceptación de la parte 8 con
  permisos de prueba `pagos.registrar` × `pagos.aprobar`. `ultimo_acceso`.
- [x] 5. `GrupoSensible`, `camposSensibles()` y `CamposVisiblesPorPermiso`, que reemplaza a
  `TodosLosCamposVisibles`. `PartyCuentaBancaria` pasa a `datos_bancarios`. El historial oculta número y CCI a
  quien no tiene `campos.ver-datos_bancarios` (se prueba el componente hijo, no la ficha). Prueba de arquitectura
  de nombres sospechosos y `camposNoSensibles()` con motivo. ADR 0016, que incluye la regla de los valores
  calculados.
- [x] 6. Pantallas de usuarios y organigrama (D10), con su versión de control al abrir y sus selectores con el
  valor actual. Pruebas de las acciones de roles, permisos, pares incompatibles y controles, todas sin pantalla
  (condición de la 1.4c).
- [x] 7. `PermisosSeeder` (permisos `campos.*` y rol `administrador`). Pendiente nuevo «Grilla de roles real».
  Documentos: `codigo.md` l.125 (permisos solo por roles; campos sensibles; los valores calculados heredan el
  grupo), DBML con nota de lo cambiado, mapa.
- [x] 8. Revisión de `revisor-arquitectura`. `composer check` en verde.

## Criterio de aceptación

- Asignar a una persona un rol que suma `pagos.registrar` y `pagos.aprobar` sin control compensatorio falla. Con el
  control, guarda: el cruce queda registrado y se emite `CruceFuncionesRegistrado` (parte 8, fila «Roles»).
- Agregar un permiso a un rol que crea un cruce para dos personas exige un control para cada una.
- Quitar el rol cierra el cruce en la misma transacción.
- Un par nuevo en el catálogo deja los cruces que ya existían como *pendientes de control* y emite su evento.
- Un control vencido deja el cruce *pendiente*.
- El responsable del control no puede ser la persona del cruce.
- Un rol acotado a una unidad organizativa da el permiso en esa unidad y no en otra, y nunca fuera de su vigencia:
  el día `hasta` es vigente en hora de Lima.
- Un cruce armado con un permiso global y otro acotado se detecta igual.
- `ambitosDonde()` devuelve solo las unidades vigentes del usuario, o «todas» si el permiso es global.
- El historial de una party no muestra número ni CCI de una cuenta a quien no tiene `campos.ver-datos_bancarios`:
  el dato no llega al navegador.
- Se rechazan cuatro casos:
  - un usuario no-sistema sin party;
  - un usuario sobre una ficha RUC (10 o 20);
  - un segundo usuario de la misma persona, aunque sea en su otra ficha;
  - una unidad dentro de su propio descendiente.
- Editar el nombre de una persona actualiza el `name` de su usuario en la misma transacción.
- No se puede desactivar al último administrador activo, ni quitarle el rol, ni quitarle al rol el permiso de
  gestionar usuarios.
- Un administrador no puede asignarse ni quitarse un rol, ni cambiar los permisos de un rol que tiene.
- Asignar, quitar o cambiar los permisos de un rol emite su evento.
- Con `APP_ENV=production`, el seeder no crea el usuario de desarrollo.
- `seguridad:primer-administrador` crea el primero y se niega si ya existe uno activo.
- Una columna `costo_unitario` agregada a un modelo sin declararla hace fallar la prueba de arquitectura.

## Preparado para lo diferido

- Nuevos ámbitos (`proyecto`, `contrato`, `almacen`): se agregan al morph map y al CHECK
  `permisos_por_ambito_ambito_type_check` (una migración de su módulo); `puede()` ya los acepta.
- La aprobación de un cruce por Gerencia (1.7): `cruces_funciones.aprobado_por` ya existe.
- Para la 1.7: si la jefatura de una unidad está vacante, la aprobación sube al jefe de la unidad de arriba.
  `jefeDe()` y el `parent_id` del organigrama bastan para recorrerlo.
- El aviso a Gerencia General (1.8/1.10): basta un listener de `CruceFuncionesRegistrado`, `RolAsignado`,
  `RolQuitado` y `PermisosDeRolCambiados`.
- Un campo sensible nuevo: una línea en `camposSensibles()` del modelo.

## Bitácora

- 2026-10-16 — Revisados parte 7, parte 5 (la grilla está ahí, no en la 3), parte 8 y pendientes: nada bloquea.
  D1-D3 decididas por el desarrollador.
- 2026-10-16 — Revisadas una a una; aprobadas D4-D10 con ajustes:
  - D4: unicidad por clave de persona; solo DNI, CE o pasaporte; `name` sincronizado.
  - D5: el jefe es un puesto.
  - D6: prueba de arquitectura de nombres sospechosos; los valores calculados heredan el grupo.
  - D7: los cruces suman lo global y lo acotado; `ambitosDonde()`; el día `hasta` es vigente en Lima.
  - D8: nadie se cambia a sí mismo; eventos de cambio de roles; prueba en producción.
  - D9: cierre automático, pendientes de control, responsable distinto de la persona.
  - D10: la grilla pasa a la 1.4c.
  - Descartado: un cuestionario de gustos para personalizar el saludo (no va en la 1.4b).
  - Al mapa: la vacante sube de nivel (1.7), el aviso de cruces sin control (1.10) y la fila 1.4c.
- 2026-10-16 — Paso 1 hecho:
  - `User` y `UserFactory` al núcleo (`#[UseFactory]`); se quita `Database\Factories` del autoload.
  - Morph map obligatorio en `NucleoServiceProvider` (alias en snake: `party_cuenta_bancaria`, `user`; spatie:
    `role`, `permission`). Los modelos de prueba se registran al crear su tabla.
  - La prueba exige alias para **todo** modelo de `app/Modules/*/Models`, no solo los auditados: con el mapa
    obligatorio, cualquiera sin alias falla al guardarse como polimórfico.
  - La prueba de «nadie fuera del núcleo escribe sus modelos» es estática: detecta `new Modelo`, los estáticos que
    escriben y las cadenas `Modelo::…->update()`. No ve `->save()` sobre una instancia leída (anotado en la prueba).
  - Migración `2026_10_16_000003` pasa los nombres de clase guardados a alias, aplicada en desarrollo.
  - `composer check` en verde (846 pruebas).
- 2026-10-16 — Paso 2 hecho (migraciones `2026_10_16_000004` a `000007`, seis modelos con factory, alias en el
  mapa y bitácora campo por campo). Desviaciones, con su motivo:
  - `version` en `unidades_organizativas`, `puestos`, `users`, `permisos_por_ambito` e `incompatibilidades_permisos`:
    se editan en pantalla (convención de bloqueo optimista).
  - Nombre único sin mayúsculas: entre unidades hermanas (también en la raíz, `NULLS NOT DISTINCT`) y entre los
    puestos de una unidad.
  - **Un par incompatible no se borra, se desactiva** (`es_activa`): los cruces cerrados lo siguen apuntando y nunca
    se borran. En D9, «se borra el par» se lee «se desactiva el par».
  - `controles_cruce.es_actual`: el índice parcial de «un control vigente por cruce» no puede usar la fecha de hoy.
    Vigente = actual y (`vigente_hasta` vacío o ≥ hoy en Lima).
  - `permisos_por_ambito`: CHECK de los tipos de ámbito conocidos (hoy `unidad_organizativa`) y EXCLUDE para que el
    mismo rol en el mismo ámbito no se cruce en el tiempo. Que el `ambito_id` exista lo verifica el servicio.
  - `completar_users` falla si hay usuarios sin persona: no inventa una. `UserFactory` crea la persona del usuario;
    cinco pruebas que contaban todas las parties ahora miran solo las suyas.
  - Pruebas de integridad en `SeguridadIntegridadTest` (18). `composer check` en verde (864 pruebas).
  - **Base de desarrollo:** el usuario `dev@techquk.test` no tiene persona, así que `migrate` se detiene en `000005`.
    Hay que reconstruirla (`migrate:fresh --seed`) con permiso del desarrollador.
- 2026-10-16 — Seeder: el usuario de desarrollo toma nombre, apellidos y correo de `DEV_USER_*` (vía
  `config/nucleo.php`), con persona de DNI inventado `00000000`; `phpunit.xml` los vacía en las pruebas. Base de
  desarrollo reconstruida con `migrate:fresh --seed`, con permiso del desarrollador.
- 2026-10-18 — Paso 3 hecho:
  - `OrganigramaService` y nueve acciones `nucleo.organigrama.*` con el permiso `organigrama.gestionar`: crear,
    editar, desactivar y activar unidad; designar jefatura; crear, editar, desactivar y activar puesto.
  - **Además del plan:** activar unidad y puesto, para que desactivar por error no sea un camino sin vuelta.
  - **Ciclos:** mover una unidad toma un candado de todo el árbol (`pg_advisory_xact_lock`), no solo de la fila.
    Con candados de fila, dos movimientos cruzados (A bajo B y B bajo A) pasarían la revisión a la vez.
  - Lo activo no cuelga de lo desactivado: no se desactiva una unidad con unidades o puestos activos, ni un puesto
    con ocupantes activos o que sea jefatura. Un puesto no cambia de unidad; solo se renombra.
  - `bloquearParaOcupar()` queda listo para que el paso 4 revise la jefatura al asignar un puesto a un usuario.
  - Pruebas: `OrganigramaServiceTest` (11) y `AccionesOrganigramaTest` (5). `composer check` en verde (884 pruebas).
- 2026-10-18 — Paso 4 hecho:
  - `SeguridadService`: `puede()`, `ambitosDonde()` (devuelve `Ambitos`, con `filtrar()` para un `whereIn`),
    `crucesPendientes()`, `estaControlado()`, usuarios, roles globales y por ámbito, permisos de un rol, pares
    incompatibles y controles. 14 acciones `nucleo.seguridad.*`.
  - Dos permisos: `usuarios.gestionar` (cuentas y roles de una persona) y `roles.gestionar` (roles, sus permisos,
    pares y controles). El rol `administrador` los tendrá en el paso 7.
  - **Un solo candado para toda escritura de seguridad** (`pg_advisory_xact_lock`), no candados de fila: dos cambios
    simultáneos podrían, cada uno por su lado, no ver el cruce o el último administrador que juntos producen. Son
    cambios raros: serializarlos no cuesta.
  - Un cambio que abre varios cruces usa **un mismo control** (texto, responsable, vencimiento) y deja una fila por
    cruce. Si falta, `CruceSinControl` lista los cruces que lo piden, para que el formulario lo pida ahí mismo.
  - Los permisos directos de spatie **se cuentan** en `puede()`, en los cruces y en «último administrador», para no
    esconder nada. Ninguna acción los da; el usuario de desarrollo los tiene hasta el paso 7.
  - Quitar un rol acotado: su último día pasa a ser ayer; si todavía no empezaba, se borra.
  - Registrar un par desactivado lo reactiva. Agregados al plan: activar usuario y cambiar puesto (con la regla de la
    jefatura de una sola persona, también al reactivar una cuenta).
  - «Hoy en Lima» es una sola pieza: `Tiempo\Calendario`, siempre en `America/Lima` aunque cambie `APP_TIMEZONE`.
    Reemplaza las copias de `PartiesService`, `ContactosPartyService`, `Party`, `PartyResource`, `ContactosDeParty`
    y `RolesDeParty`.
  - Campo nuevo `Campo::listaDeIds()` (tipo `ListaIds`) para los permisos de un rol: una lista de una tabla que no es
    catálogo.
  - `PartiesService::editar` actualiza el `name` de la cuenta en la misma transacción. `ultimo_acceso` lo anota un
    oyente de `Login`.
  - **Para el paso 6:** la cuenta nace con clave aleatoria y la persona pone la suya con «olvidé mi clave», pero el
    panel todavía no tiene `->passwordReset()`. Hay que activarlo con las pantallas de usuarios.
  - Pruebas: `SeguridadServiceTest` (20, con el escenario de la parte 8) y `AccionesSeguridadTest` (5).
    `composer check` en verde (909 pruebas).
- 2026-10-18 — Paso 5 hecho:
  - `GrupoSensible` (seis grupos, permiso `campos.ver-<grupo>`), la interfaz `DeclaraCamposSensibles`
    (`camposSensibles()` y `camposNoSensibles()` con motivo) y `CamposVisiblesPorPermiso`, que reemplaza a
    `TodosLosCamposVisibles`. `CamposVisibles` ahora dice qué campos se **ocultan** (`ocultos()`), no cuáles se ven: así
    un modelo que no declara nada se ve entero, sin tener que listar todas sus columnas.
  - `PartyCuentaBancaria`: número y CCI son `datos_bancarios`; `campos.ver-datos_bancarios` reemplaza a
    `parties.ver-cuentas-bancarias` en la pestaña, su componente y el historial. `PermisosSeeder` crea los seis
    `campos.*`; el permiso viejo ya no se crea (en la base de desarrollo queda una fila huérfana, inofensiva).
  - **Se mantiene la decisión de la 1.3b:** sin el permiso, las cuentas ni se leen para el historial de la ficha.
    El filtro por campo es una segunda barrera, probada con el modelo real en `HistorialLegible`. Por la misma razón,
    la cuenta se nombra en la bitácora «Cuenta BCP en PEN», sin número: el número solo sale en las filas de sus campos
    y en el resumen de creación.
  - Revisor de nombres sospechosos **por palabra**, no por subcadena: «dire**cci**on» y «detra**cci**on» no son un CCI.
    `tipos_documento.afecta_cuenta_corriente` se declaró no sensible, con su motivo.
  - ADR 0016 y `codigo.md`: permisos solo por roles, campos sensibles y la regla de los valores calculados.
  - Pruebas: `CamposSensiblesTest` (5, incluido `costo_unitario` sin declarar) y la segunda barrera en
    `HistorialFichaPartyTest`. `composer check` en verde (915 pruebas).
- 2026-10-18 — Paso 6 hecho:
  - Clúster «Usuarios y organigrama» (`/seguridad`, con su caja del modo modular). Permisos de lectura nuevos:
    `usuarios.ver` y `organigrama.ver`.
  - **Usuarios:** lista sin la cuenta de sistema, con puesto y unidad, roles globales, activo y último acceso
    («Nunca»). «Registrar usuario» busca solo personas con DNI, CE o pasaporte, activas y sin cuenta. En cada fila y
    en la ficha: cambiar puesto, desactivar y activar. Pestañas «Roles» y «Roles por unidad» (vigente, programado o
    terminado).
  - **El control compensatorio se pide en el mismo formulario:** «Asignar rol» lleva los campos de control y
    responsable. Si el rol abre un cruce y faltan, el aviso dice cuál y el formulario queda abierto para completarlo.
  - **Organigrama:** unidades en árbol (sangría por nivel, hermanas por `orden`), con su puesto de jefatura y quién
    lo ocupa, «Vacante» o «Sin definir». Ficha de la unidad con la pestaña «Puestos» (ocupantes y cuál es la
    jefatura).
  - La versión se captura al abrir cada formulario. Los selectores de puesto y de unidad ofrecen también el valor
    actual aunque esté desactivado. Para ocultar acciones según la fila, `->hidden()`.
  - El panel tiene «¿Olvidaste tu contraseña?» (`->passwordReset()`). En desarrollo el correo va al registro
    (`MAIL_MAILER=log`); en producción depende del correo que se configure.
  - Pruebas: `SeguridadPanelTest` (12). Las acciones de roles, permisos, pares y controles ya estaban probadas sin
    pantalla en el paso 4 (condición de la 1.4c). `composer check` en verde (927 pruebas).
- 2026-10-18 — Paso 7 hecho:
  - `SeguridadSeeder` (en todo ambiente, después de `PermisosSeeder`): el rol `administrador` con permisos **fijos**
    que se vuelven a poner en cada despliegue. Tiene usuarios, roles, organigrama, catálogos, parámetros y ubigeos.
    **No tiene** los que mueven dinero (`*-sensibles`, tipos de cambio), ni `campos.*`, ni personas y empresas: son
    del negocio. Consecuencia: para darle cuenta a alguien, su persona la registra antes quien tenga `parties.crear`.
  - Usuario de desarrollo: ahora por roles (`administrador` y `desarrollo`, este último solo en local y con todo),
    sin permisos directos.
  - `seguridad:primer-administrador`: persona (o la que ya exista con ese documento), cuenta y rol, a nombre del
    usuario de sistema y en una sola transacción. **La clave se pide en pantalla, oculta**: el correo de producción
    todavía no está configurado y el primer administrador no podría usar «¿Olvidaste tu contraseña?». Mínimo 12
    caracteres (`SeguridadService::establecerClave`, que usa solo este comando). Se niega si ya hay un administrador
    activo.
  - Pendientes nuevos: «Grilla de roles real» y «Organigrama real», ambos con Gerencia y sin bloquear nada.
  - DBML: notas de lo que no se crea o se reemplazó (`users.unidad_organizativa_id`, `jefe_user_id`, `rol`/`permiso`
    de `permisos_por_ambito`, `restricciones_campo`, `cruces_funciones`). Mapa: fila 1.4 sin `restricciones_campo`.
  - Pruebas: `PrimerAdministradorTest` (6) y, en `UsuarioDesarrolloSeederTest`, producción y permisos fijos del
    administrador. `composer check` en verde (934 pruebas).
- 2026-10-18 — Revisión de `revisor-arquitectura` (paso 8). Corregido:
  - **Nombres según la convención** (manda sobre el DBML): `users.ultimo_acceso_at` y `cruces_funciones.aprobado_por_user_id`.
    Migraciones editadas: la base de desarrollo se reconstruye.
  - **Caché de spatie:** además de borrarla durante el cambio, se borra al confirmar (`DB::afterCommit`). Si no, otra
    petición podía volver a llenarla con los permisos viejos por 24 h.
  - **Bitácora de lo global:** asignar o quitar un rol global queda en la bitácora de la cuenta (roles antes y
    después); crear, renombrar o cambiar los permisos de un rol, en la del rol; `User` tiene `RegistraCambios`
    (nombre, correo, persona, puesto, activa; nunca la clave). Todo en la misma transacción que el cambio.
  - **Un campo omitido falla en vez de borrar** (regla `present`, con su mensaje): los permisos de un rol, el puesto
    al cambiarlo, la jefatura al designarla y, al editar una unidad, de quién depende y su orden.
  - **«Nadie se cambia a sí mismo» alcanza también** (decidido por el desarrollador): cambiarse el puesto, desactivar
    el par de un cruce propio y registrar el control del propio cruce.
  - **Persona y cuenta** (decidido por el desarrollador): no se desactiva una persona con cuenta activa (primero la
    cuenta), ni se activa una cuenta de una persona desactivada. Las dos operaciones bloquean primero la persona.
  - El primer administrador se crea en `SeguridadService::registrarPrimerAdministrador()`, bajo el candado: dos
    corridas a la vez no crean dos. El comando quedó delgado.
  - El ámbito se bloquea al asignar un rol en él.
- Descartado: «la unicidad del correo distingue mayúsculas». Ya existe `users_email_minusculas_unique` sobre
  `lower(email)` (migración `2026_10_10_000002`).
- Para la 1.4c (grilla): `crucesPendientes()` calcula los permisos por cruce y la lista del organigrama llama a
  `jefeDe()` por fila. Con 22 personas no pesa; si la grilla lo usa en volumen, precargar.
- `composer check` en verde (941 pruebas).
- 2026-10-18 — Tarea cerrada. Sigue la 1.4b (saludo personalizado y animación de la fecha; el cumpleaños va en
  `users`). La 1.4c espera la grilla real de Gerencia.
- 2026-10-18 — Al cerrar: `composer check` tarda ~385 s y Composer cortaba a los 300 s, dejando una corrida de
  pruebas suelta. Ahora `composer.json` tiene `"process-timeout": 0` (vale para todos, incluido el CI) y `CLAUDE.md`
  pide revisar con `pgrep -af "artisan test"` que no haya otra corrida antes de lanzarlo. **Si `composer check` pasa
  de unos 10 minutos, evaluar correr las pruebas en paralelo con una base por proceso.**
