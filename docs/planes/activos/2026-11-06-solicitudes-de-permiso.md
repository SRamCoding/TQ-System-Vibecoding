# Plan: Solicitudes de permiso

- **Estado:** **en espera** (decisión del desarrollador, 2026-11-15): decisiones D1 a D13 aprobadas como las recomienda
  este plan; la construcción se deja para después del reparto. Ver «En espera» abajo
- **Responsable:** Sebastián (núcleo; modo actual de AGENTS.md)
- **Rama:** `nucleo-solicitudes-permiso` (sale de `main`)
- **Módulo(s):** Nucleo (`Filament/AccionFilament`, `RegistroAcciones/Accion` y `EjecutorAcciones`, `Estados/` y
  `TransicionesService`, `EstadosService::barraDeEstados`, `SeguridadService`, `Alertas/`, `Notificaciones/`, Roles y
  permisos)
- **Fase:** F1 (decisiones) → F2 (construcción)
- **Creado:** 2026-11-06 · **Actualizado:** 2026-11-15

## En espera (decisión del desarrollador, 2026-11-15)

Punto 4 del corte (`docs/planes/completados/corte-reparto.md`): «o se hace ahora, o queda en espera con su punto de extensión
anotado». El desarrollador decidió que **las decisiones quedan** y **se construye después**:

- **Aprobadas, tal como las recomienda este plan:** D1 (a, por defecto `Trabajo`, con la lista de administración), D2
  (a), D3 (a: **el botón aparece bloqueado**, gris, con candado y el texto de lo que falta; al presionarlo, «Te falta un
  permiso» y «Solicitar permiso»; el desarrollador lo pidió así), D4 (a; Cuentas y Oportunidades pasan a una acción de
  «propias»), D5 (a), D6 (a), D7, D8, D9, D10 (se acepta el mismo riesgo que hoy), D11, D12 y D13. Preguntas abiertas:
  1 sí, 2 bloqueado y presionable, 3 sí, 4 sí, 5 como D10, 6 `administrador` hasta la grilla de roles real
  (pendiente), 7 no en esta tarea (solo roles globales).
- **El ADR de D13 será el 0023** (el 0022 es el de plantillas).
- **Punto de extensión, para que nadie arme el suyo mientras tanto:** todo botón de una acción registrada sale de
  `AccionFilament::desde()`, que hoy lo oculta sin permiso (`->visible()`); el rechazo del servidor es
  `AccionRechazada::sinPermiso` en `EjecutorAcciones::autorizar()` y `TransicionRechazada::sinPermiso`. Cuando se
  construya, cambia ahí y lo reciben todas las pantallas. **Regla hasta entonces:** ninguna pantalla ni área arma su
  propio botón de «pedir permiso», ni su propio aviso de permiso faltante; si una lo necesita, se adelanta esta tarea.
- **Al retomarla:** traer `main`, revisar que la lista de acciones de administración (D1) siga completa (las áreas
  habrán sumado acciones) y que «propias» se haya usado igual (D4).

## Objetivo

Quien no tiene el permiso de una acción de trabajo ve el botón bloqueado, sabe exactamente qué permiso le falta (con su
nombre legible, y la regla completa si es de «propias») y lo pide con un clic; quien administra Roles y permisos recibe
el aviso y lo resuelve en una pantalla, en un clic, dándolo por rol (nunca a una persona suelta) con las acciones de
roles que ya existen. El servidor rechaza con un error propio del núcleo que dice qué permisos faltaron. Con permiso,
todo queda igual que hoy. Todo vive en la pieza común: ninguna pantalla lo programa por su cuenta.

## Contexto que leí

Pedido del desarrollador (2026-11-06; mandato completo, partes 1 y 2). Lo que hay hoy en `main` (d7fad95):

- **`AccionFilament::desde()`** (`app/Modules/Nucleo/Filament/AccionFilament.php`) oculta el botón con
  `->visible(fn () => permiso === null || can(permiso))`. AGENTS.md ya avisa que si una pantalla llama `->visible()`
  reemplaza esa comprobación, y que para ocultar según la fila se usa `->hidden()`. Atrapa `ErrorDeNegocio` y lo
  muestra como aviso rojo (`Notification`). Hay 86 llamadas a `AccionFilament::desde` en `app/` y 64
  `assertActionHidden` en 14 archivos de pruebas.
- **`Accion`** (`RegistroAcciones/Accion.php`) declara **un solo** permiso (`permiso(): ?string`) y
  `permisoPorAmbito()` (solo `AccionTransicion`). Hay 84 acciones. Algunas exigen un segundo permiso dentro de
  `ejecutar()` o del servicio según los datos: `RegistrarParty` (`PERMISO_ROLES` si trae roles),
  `AprobacionesService` (`PERMISO_AUTOAPROBAR`), `IaService::sinPermisoParaResolver`.
- **El rechazo de hoy no es `AuthorizationException`:** `EjecutorAcciones::autorizar()` lanza
  `AccionRechazada::sinPermiso($nombre)` («No tienes permiso para «X».»), que es `ErrorDeNegocio`;
  `TransicionesService::revisarQuien()` lanza `TransicionRechazada::sinPermiso`. `AuthorizationException` solo sale en
  pantallas cuando no hay usuario con sesión (AccionFilament, RolesPermisos, Alertas, MisDatos…). Y una acción oculta
  que se intenta llamar por Livewire no llega al servidor: Filament no la monta. Ver «Contradicciones».
  16 pruebas en 11 archivos esperan el texto «No tienes permiso»; 39 `toThrow(AccionRechazada::class)`.
- **Nombres legibles:** `RegistroPermisos` (plan 2.20a, N8) y `PermisosLegibles::etiqueta()/grupo()`.
- **«Propias»:** `SeguridadService::puedeSobrePropio($usuario, $todas, $propias, $responsableId)` y
  `filtrarPropios()` (ADR 0021). En la rama `crm-oportunidades` (Cuentas, 2.20) el patrón es **dos acciones**
  (`crm.cuentas.editar` y `crm.cuentas.editar-propias`) y la pantalla oculta una u otra fila por fila con `->hidden()`;
  el servicio revisa con `puedeSobrePropio`. El plan 2.21 (Oportunidades, D10.5, aprobada) pone «propias» en las
  transiciones con una `CondicionTransicion` `SobreLoPropio` del módulo CRM.
- **Transiciones:** `TransicionesService::salidasDisponibles()` **filtra** las que la persona no puede (permiso global
  o en algún ámbito del documento, y condiciones); `EstadosService::barraDeEstados()` arma `BarraDeEstados` con
  `SalidaDeEstado` (sin dato de permiso faltante). `CondicionTransicion::revisar()` devuelve solo un texto. En `main`
  **no hay todavía componente de pantalla** de la barra («llega con el primer documento real»): lo construye la 2.21.
- **Seguridad** (`SeguridadService`): `asignarRol` (`nucleo.seguridad.asignar-rol`, permiso `usuarios.gestionar`),
  `asignarRolEnAmbito`, `cambiarPermisosDeRol` (`nucleo.seguridad.cambiar-permisos-de-rol`, permiso
  `roles.gestionar`, **reemplaza** la lista entera). Reglas que la resolución hereda: nadie cambia sus propios roles
  (`bloquearAjeno`), nadie cambia los permisos de un rol que tiene (`rolPropio`), el rol `administrador` no se toca.
  **Pares incompatibles:** no se bloquean en seco; `revisarCruces()` lanza `CruceSinControl` y la pantalla Roles y
  permisos pide ahí mismo el **control compensatorio** (texto, responsable, vigencia) y reintenta.
- **Roles y permisos** (`Filament/Pages/RolesPermisos.php`, permiso `roles.gestionar`): cajas por rol, panel que se
  desliza, cantidad de personas por rol; maneja `CruceSinControl` con el formulario del control.
- **Alertas** (`DefinicionAlerta`, `RegistroAlertas`, `AlertasService::emitir`): roles por defecto configurables en
  Configuración → Alertas, hecho único en la base (`alerta_emitidas.clave`), `destinatarioPropio` para una persona
  puntual. **Avisos** (`RegistroAvisos`, `NotificacionesService::avisar`), grupos `control` y `sistema`.
- **Estados como datos:** `base-de-datos.md` («Estados: FK a `estados`, nunca enum ni texto libre»), con el precedente
  del núcleo `rondas_aprobacion.estado` (enum con CHECK e índice único parcial `WHERE estado = 'abierta'`, plan 1.7).
- Convenciones leídas: `codigo.md` §Acciones registradas, §Estados y efectos, §Filament (permisos solo por roles,
  nombres, «Lo propio y lo de todos»); `interfaz.md` §Reglas, §Patrones de pantalla, §Archivos y campana;
  `base-de-datos.md` §Integridad y concurrencia. ADR 0021. v4 parte 7 §Permisos: no dice nada de solicitudes de acceso.
- **Pendientes** (`docs/decisiones/pendientes.md`) que tocan esta tarea, sin bloquearla: «Grilla de roles real» (qué
  roles reales reciben la alerta; hasta entonces, `administrador`) y «Dos cambios a la vez sobre un rol» (la opción B
  hereda ese riesgo; ver D10).

## Decisiones

Todas son **propuestas** de la sesión que escribió el plan (2026-11-06). Las aprueba o cambia Sebastián antes de
construir. Como cambia la pieza común que usan las cuatro áreas, el paso 1 deja un ADR.

- **D1 · Dos clases de acción, declaradas por la acción.** *Propuesta.*
  - *Problema:* unas acciones sin permiso deben verse bloqueadas (trabajo) y otras seguir ocultas (administración).
  - *Opciones:* (a) enum `ClaseAccion { Trabajo, Administracion }` devuelto por `Accion::clase()`, por defecto
    `Trabajo`; (b) el mismo enum con defecto `Administracion` (todo sigue como hoy y cada acción de trabajo lo pide);
    (c) método abstracto, sin defecto (las 84 acciones lo declaran); (d) lo decide la pantalla al dibujarla.
  - *Recomendación:* (a). Las acciones de trabajo son la mayoría y crecen con cada módulo de negocio; las de
    administración son pocas y conocidas. Las del núcleo que son de administración lo declaran en su clase base o en
    su clase: `AccionSeguridad` y las de `Seguridad/`, `Alertas/ConfigurarAlerta`, `CambiarParametro*`,
    `Catalogos/*`, `Aplicaciones/*` (administración), `ConfiguracionInicial/*`, `Ia/*` de configuración,
    `Organigrama/*`, `SincronizarTipoCambio`, `ActualizarUbigeos`. Una prueba en `tests/Arch` fija esa lista: toda acción
    de esas carpetas es `Administracion`, y toda `AccionTransicion` es `Trabajo`. Las acciones sin permiso
    (`permiso() === null`: «Mis datos», preferencias) no cambian.
  - *Descartado:* (b), porque las áreas se olvidarían y el pedido quedaría sin efecto; (c), mucho cambio mecánico sin
    ganancia; (d), contradice el mandato («la acción, no la pantalla»). Equivocarse hacia «trabajo» no abre nada (el
    servidor sigue rechazando): solo muestra un botón bloqueado que no debía verse.

- **D2 · Lo que exige una acción: `requisitos()` en lugar de un solo permiso.** *Propuesta.*
  - *Problema:* hoy `permiso()` es uno; el mandato pide listar todos los que faltan, y «propias» es una regla, no un
    permiso.
  - *Opciones:* (a) `Accion::permisosAdicionales(): list<string>` junto a `permiso()`, y `sobreLoPropio(): ?SobreLoPropio`;
    (b) cambiar `permiso()` por `permisos(): list<string>` en las 84 acciones; (c) dejar que cada servicio arme la
    lista al rechazar.
  - *Recomendación:* (a). `permiso()` sigue siendo el principal (lo usa `PermisosSeeder`, las pruebas y los nombres de
    ADR 0021); `permisosAdicionales()` (por defecto `[]`) suma los que siempre exige. Los que dependen de los datos
    (`RegistrarParty` con roles) no se pueden saber antes de llenar el formulario: los rechaza el servicio con la misma
    excepción de D5, y el botón no los anticipa. Una pieza nueva del núcleo, `RequisitosDeAccion`
    (`faltantes(Accion, User, ?array $datos, ?Model $registro): list<FaltaDePermiso>`), es la **única** que calcula lo
    que falta; la usan `AccionFilament`, `EjecutorAcciones` y la barra.
  - *Descartado:* (b), cambio masivo y rompe `PermisosSeeder`; (c), repetiría la regla en cada servicio.

- **D3 · Cómo se ve una acción de trabajo sin permiso.** *Propuesta.*
  - *Problema:* un botón realmente deshabilitado en Filament no recibe clic, así que no puede llevar «Solicitar
    permiso»; y el texto debe verse al pasar el mouse.
  - *Opciones:* (a) **bloqueado, no deshabilitado**: el mismo botón en gris (`color('gray')`), con candado
    (`heroicon-o-lock-closed`) y el texto en `tooltip()`; al presionarlo abre una ventana «Te falta un permiso» con la
    lista, el comentario opcional y «Solicitar permiso» (la acción de D7); (b) `->disabled()` + `->tooltip()` y, al
    lado, un botón pequeño «Solicitar permiso»; (c) deshabilitado sin más y la solicitud desde «Mis datos».
  - *Recomendación:* (a): un solo botón, sirve en tablas y grupos de acciones, y en celular (donde no hay mouse) el
    clic muestra lo mismo que el tooltip. Se comprueba en el paso 2 con una prueba corta que Filament 5 muestra el
    tooltip y abre la ventana del botón bloqueado. AccionFilament lo resuelve dentro de `desde()`: si faltan permisos,
    cambia etiqueta de la ventana, esquema y `action()` por los de la solicitud; si no, todo igual que hoy.
    Las de administración siguen con `->visible()` como hoy.
  - *Regla nueva para las pantallas* (va a `interfaz.md` y a AGENTS.md): sobre una acción de `AccionFilament`, la
    pantalla no llama `->visible()`, `->disabled()`, `->action()` ni `->tooltip()`: solo `->hidden()` según la fila.
    Para el resto, parámetros de `desde()`.
  - *Descartado:* (b), dos botones por fila; (c), la persona perdería el contexto (qué acción, qué registro).

- **D4 · Qué falta y cómo se dice, también para «propias».** *Propuesta.*
  - *Problema:* calcular los permisos que faltan con varios permisos, ámbitos y «propias», y explicar la regla de
    «propias» sin datos sensibles.
  - *Opciones para «propias»:* (a) la acción declara `sobreLoPropio(): ?SobreLoPropio` (valor del núcleo:
    `permisoTodas`, `permisoPropias`, `responsable` = texto «el ejecutivo de esta cuenta», `verbo` = «editarla», y un
    closure que da el `user_id` responsable desde el registro); la pantalla dibuja **un** botón; (b) seguir con dos
    acciones (todas / propias) y que la pantalla elija; (c) texto escrito a mano por cada pantalla.
  - *Recomendación:* (a), con `RequisitosDeAccion` usando `puedeSobrePropio` (la única regla, ADR 0021). El texto:
    «Solo el ejecutivo de esta cuenta, o quien tenga «Editar todas las cuentas», puede editarla.» Lo que se pide: si la
    persona **es** la responsable y le falta `propias`, se pide `propias`; si no lo es, se pide `todas` (ser la
    responsable no se pide: es otra acción, «Reasignar»). **Nunca** el nombre de la persona responsable ni datos del
    registro: solo el rol del responsable, que declara la acción. Con dos acciones que ya existen (Cuentas), la de
    propias pasa a ser la única y declara la pareja; ver «Contradicciones».
  - *Para transiciones:* falta el permiso si no lo tiene global ni en ningún ámbito de `ambitoDePermisos()` (basta
    uno, como hoy). La condición «propias» de la 2.21 pasa a ser una pieza del núcleo, `CondicionSobreLoPropio`
    (implementa una interfaz nueva `CondicionDePermiso extends CondicionTransicion`, con `faltantes()` además de
    `revisar()`), para que la barra y la excepción sepan que es de permisos y no de negocio. Las demás condiciones
    siguen ocultando la salida como hoy.
  - *Descartado:* (b), la pantalla no puede explicar la regla completa sin repetirla; (c), se desviaría pantalla por
    pantalla.

- **D5 · Error propio del núcleo: `PermisoFaltante`.** *Propuesta.*
  - *Problema:* el rechazo debe decir qué permisos faltaron (clave y nombre legible), en pantallas, pruebas y la API
    futura.
  - *Opciones:* (a) `PermisoFaltante extends ErrorDeNegocio` con `accion` (nombre), `faltantes: list<FaltaDePermiso>`
    (`clave`, `nombre`, `regla` opcional) y `paraRespuesta(): array`; reemplaza `AccionRechazada::sinPermiso` y
    `TransicionRechazada::sinPermiso` (y `IaRechazada::sinPermisoParaResolver`); (b) agregar los datos a
    `AccionRechazada`; (c) usar `AuthorizationException` con datos.
  - *Recomendación:* (a). Mensaje: «No tienes permiso para «Editar cuenta»: te falta «Editar todas las cuentas».»
    (empieza igual que hoy, así las pruebas que buscan «No tienes permiso» solo cambian la clase). `AccionFilament` ya
    atrapa `ErrorDeNegocio`: para `PermisoFaltante`, el aviso rojo lleva el botón «Solicitar permiso». La API futura la
    traduce a 403 con `paraRespuesta()` (`mensaje`, `permisos: [{clave, nombre}]`): se deja listo el método, no la
    ruta (tocar rutas hace correr todas las pruebas; no hay API todavía). Nunca lleva datos del registro.
  - *Pruebas que hoy esperan el rechazo:* se cambian en el mismo paso a `PermisoFaltante` (son 16 en 11 archivos con
    el texto y las de `AccionRechazada::class` que prueban permiso; las de usuario inactivo o de sistema siguen con
    `AccionRechazada`).
  - *Descartado:* (b), mezcla «sin permiso» con «usuario inactivo»; (c), Laravel la convierte en 403 genérico y
    Filament no la muestra como aviso.

- **D6 · Tablas de solicitudes.** *Propuesta.*
  - *Problema:* guardar la solicitud con su origen, sin repetidas y con su resolución.
  - *Opciones de estado:* (a) enum con CHECK (`pendiente`, `concedida`, `rechazada`, `retirada`, `sin_efecto`), como
    `rondas_aprobacion` del núcleo; (b) documento con estados del núcleo (`DefinicionDocumento`, FK a `estados`).
  - *Recomendación:* (a). Es un registro de proceso del núcleo, no un documento de negocio: el sistema toma decisiones
    según el estado y la unicidad de la pendiente se garantiza con un índice único parcial
    `WHERE estado = 'pendiente'`, que con un `estado_id` no se puede escribir (el id cambia entre bases). Se anota
    como excepción a «Estados: FK a `estados`» con el precedente de la 1.7. La bitácora sale de las acciones (D7) y del
    historial (`RegistraCambios`).
  - *Columnas* (`solicitudes_permiso`): `id`; `user_id` (quien pide, FK `users`, índice); `estado` (varchar con
    CHECK); `permisos_clave` (text: las claves de los permisos ordenadas y unidas por coma, para la unicidad);
    `accion_clave` (varchar 150); `accion_nombre` (varchar 200, el nombre al pedir); `pantalla_titulo` (varchar 200);
    `pantalla_ruta` (varchar 500: **solo una ruta interna**, empieza con `/` y no con `//`, validada en el servicio;
    es el enlace del aviso de respuesta); `registro_type` / `registro_id` (nullable, índice compuesto); `comentario`
    (text nullable, hasta 1000); `resuelta_por` (FK `users`, nullable, índice); `resuelta_en` (timestamptz nullable);
    `resolucion` (varchar con CHECK: `rol_asignado`, `permisos_agregados`, `rechazada`; nullable); `rol_id` (FK
    `roles`, nullable, índice); `motivo_id` (FK `motivos`, nullable, índice) y `motivo_detalle` (text nullable);
    `version` (integer, CHECK ≥ 1); `auditoria()`. CHECK de coherencia: pendiente ⇔ sin resolución; concedida ⇒
    `rol_id`; rechazada ⇒ `motivo_id`.
  - *Detalle* (`solicitud_permiso_permisos`): `solicitud_id` (FK, índice), `permission_id` (FK `permissions`, índice),
    único `(solicitud_id, permission_id)`. Así la pantalla lee los permisos por id y un permiso borrado no deja texto
    huérfano.
  - *Unicidad:* `UNIQUE (user_id, permisos_clave) WHERE estado = 'pendiente'`; el servicio atrapa la violación y
    responde «Ya pediste estos permisos el <fecha>; está pendiente.» Si una pendiente ya **cubre** todos los permisos
    pedidos (pidió más), también avisa (lo revisa el servicio con candado por persona; la base solo garantiza el caso
    igual).
  - *Descartado:* (b) por la unicidad; guardar los permisos solo en `jsonb` (sin FK).

- **D7 · Acciones de la solicitud.** *Propuesta.*
  - `nucleo.solicitudes-permiso.solicitar` (sin permiso: cualquiera con sesión; `Trabajo`): campos `accion_clave`,
    `pantalla_titulo`, `pantalla_ruta`, `registro_type`, `registro_id` (de control, los pone AccionFilament) y
    `comentario`. **Los permisos no los manda quien pide:** el servicio los vuelve a calcular con
    `RequisitosDeAccion` para esa acción y ese registro, así nadie pide permisos que la acción no exige. Si ya no le
    falta nada, avisa «Ya tienes lo necesario» y no crea nada.
  - `nucleo.solicitudes-permiso.retirar` (quien la pidió, mientras está pendiente; opcional en la pantalla de la
    persona, ver «Preparado para lo diferido»).
  - `nucleo.solicitudes-permiso.resolver` (D9).
  - Un `SolicitudesPermisoService` en el núcleo con `solicitar()`, `resolver()`, `rechazar()`, `retirar()`,
    `pendientes()`; las acciones solo lo llaman.

- **D8 · Avisos.** *Propuesta.*
  - *Al pedir:* alerta nueva del núcleo `nucleo.solicitud-permiso` («Solicitud de permiso», grupo de avisos nuevo
    `accesos` «Accesos y permisos»), roles por defecto `[administrador]`, configurables en Configuración → Alertas como
    las demás; hecho `solicitud:<id>` (único en la base). Datos: `persona`, `permisos` (nombres legibles unidos),
    `origen` («Editar cuenta» en «Cuentas»). Enlace a la pantalla de resolución (D11). No es obligatoria.
  - *Al resolver:* aviso `nucleo.solicitud-permiso-respondida` a la persona (con `NotificacionesService::avisar`, no
    alerta: es una persona puntual), «Te dieron «Editar todas las cuentas»» o «No te dieron «…»: <motivo>», con enlace a
    `pantalla_ruta`.
  - *Descartado:* avisar a todos los que tienen `roles.gestionar` sin configuración: el mandato pide destinatarios
    configurables como las alertas.

- **D9 · Resolver: A, B o C, con las acciones de roles que ya existen.** *Propuesta.*
  - Una sola acción `nucleo.solicitudes-permiso.resolver` (campos: `solicitud_id`, `version` de control, `opcion`
    A/B/C, `rol_id`, `motivo_id`, `motivo_detalle`, y los del control compensatorio de `AccionSeguridad`). El servicio,
    en **una transacción** y con candado sobre la solicitud:
    - **A · darle un rol que ya tiene todos los permisos pedidos:** ejecuta `nucleo.seguridad.asignar-rol` **por
      `EjecutorAcciones`** a nombre de quien resuelve (exige `usuarios.gestionar`, deja su bitácora, revisa cruces).
    - **B · agregar los permisos a uno de sus roles globales:** lee los permisos actuales del rol y ejecuta
      `nucleo.seguridad.cambiar-permisos-de-rol` con actuales + pedidos (exige `roles.gestionar`). Antes de confirmar,
      la pantalla advierte: «Este rol lo tienen otras N personas: también recibirán estos permisos.» (N = titulares
      del rol, globales y acotados, menos la persona; `SeguridadService` gana un método público de solo lectura para
      contarlos).
    - **C · rechazar con motivo:** motivo elegido de una lista suelta del núcleo `nucleo.solicitud_permiso_rechazo`
      con su «Otro» y detalle (la regla «lo que se analiza se elige»); exige `roles.gestionar`.
    - Después de A o B, el servicio comprueba con `RequisitosDeAccion` que a la persona **ya no le falta nada**; si
      falta (por ejemplo, la acción necesitaba un ámbito), revierte y lo dice. Marca la solicitud `concedida`, guarda
      rol y resolución, y cierra como `sin_efecto` las otras pendientes de la persona que quedaron cubiertas (cada una
      con su aviso).
  - *Par incompatible:* A y B ya pasan por `revisarCruces()`; si lanza `CruceSinControl`, la pantalla muestra el
    mismo formulario de control compensatorio que Roles y permisos (texto, responsable, vigencia) y reintenta, con la
    misma regla de «solo vale para lo que se mostró». Nada se concede sin control.
  - *Lo que ya rechaza el servicio de seguridad* se muestra tal cual y bloquea esa opción: quien resuelve no se
    resuelve a sí mismo (`bloquearAjeno`), no cambia un rol que tiene (`rolPropio`), el rol `administrador` no se toca.
    En la pantalla, esas opciones salen bloqueadas con su motivo (la misma regla de D3, aplicada a sí misma).
  - *Descartado:* dar el permiso directo a la persona (prohibido: solo por roles); crear un rol nuevo desde la
    solicitud (queda para Roles y permisos); lógica de roles nueva en el servicio de solicitudes.

- **D10 · Riesgo heredado en B.** *Propuesta (pregunta para el desarrollador).* `cambiar-permisos-de-rol` reemplaza la
  lista entera y el rol no lleva `version`: dos cambios a la vez pueden pisarse (pendiente «Dos cambios a la vez sobre
  un rol»). B lee y escribe en la misma transacción, pero la lectura queda fuera del candado de `SeguridadService`.
  Recomendación: aceptar el mismo riesgo que hoy tiene la pantalla y no inventar una salida aquí (regla 12); si se
  decide el pendiente, B lo hereda solo.

- **D11 · Pantalla para resolver y lista en Roles y permisos.** *Propuesta.*
  - En Roles y permisos, una **pestaña de texto** «Solicitudes» con su cantidad (patrón de portada): tabla con
    pendientes primero y filtro por estado (pendientes / resueltas). Al presionar una, se abre el **panel deslizante**
    (sin recargar, URL `?solicitud=<id>`, que es el enlace del aviso): quién, cuándo, comentario, origen (acción,
    pantalla, registro como tipo y número, sin datos), los permisos con nombre y grupo, sus roles actuales, los roles
    que **ya tienen todos** los permisos pedidos con su cantidad de personas, y las tres opciones.
  - Acceso: `roles.gestionar` o `usuarios.gestionar` (cada opción exige el suyo; D9).
  - *Descartado:* una pantalla aparte fuera de Roles y permisos (el mandato la pone ahí).

- **D12 · Qué queda para la mejora visual (Sebastián, al final).** Colores y forma del botón bloqueado, el candado,
  la ventana «Te falta un permiso», el diseño del panel de resolución y de la pestaña Solicitudes. Esta tarea usa los
  componentes de Filament y los patrones que ya hay, sin estilos nuevos.

- **D13 · ADR.** *Propuesta.* Cambia la pieza común que usan las cuatro áreas (cómo se declara una acción, el error de
  permiso y la condición de «propias» de las transiciones): va a un ADR (0022), que decide Sebastián.

## Contradicciones y riesgos

- **El punto de partida del mandato no es exacto:** el servidor ya no lanza `AuthorizationException` en el ejecutor
  ni en las transiciones, sino `AccionRechazada` / `TransicionRechazada` (mensaje «No tienes permiso para «X»», sin
  decir qué permiso). Una acción oculta que se llama por Livewire tampoco llega al servidor: Filament la ignora sin
  mensaje. Lo que falta sigue siendo verdad (no se sabe qué permiso ni a quién pedirlo); cambia qué se reemplaza.
- **«Propias» con dos acciones (Cuentas, 2.20, rama `crm-oportunidades`)** choca con «un botón con la regla
  completa»: con dos botones, la persona sin ninguno de los dos vería «te falta Editar todas las cuentas» sin la
  regla. D4 propone una sola acción que declara la pareja; Cuentas y Oportunidades se ajustan (las dos son de
  Sebastián). Si la 2.20 o la 2.21 se fusionan antes, el ajuste va en esta tarea; si van después, lo hacen ellas.
- **La barra de estados no tiene componente de pantalla en `main`**: la construye la 2.21 (Oportunidades). Esta tarea
  agrega a `SalidaDeEstado` lo que falta (`faltantes`) y devuelve también las salidas bloqueadas por permiso; el
  componente que dibuje la 2.21 (o el que deje esta tarea si llega primero) las muestra bloqueadas. Coordinar el orden
  de fusión. La condición `SobreLoPropio` de la 2.21 pasa al núcleo (D4).
- **Cambia `salidasDisponibles()`**: hoy también lo usa el mensaje de edición bloqueada (`BloqueoEdicion`). Ese mensaje
  debe seguir ofreciendo solo las salidas que la persona puede usar.
- **Pruebas que cambian:** las que esperan el botón oculto sin permiso (`assertActionHidden`, 64 en 14 archivos, no
  todas por permiso) y las que esperan `AccionRechazada`/`TransicionRechazada` por permiso. Se ajustan en el paso que
  cambia el comportamiento; las de administración siguen igual.
- **Rendimiento:** una tabla con «propias» calcula fila por fila. `SeguridadService::puede` consulta la base cada vez;
  se guarda en memoria por petición lo que la persona tiene (sus permisos globales) para no consultar por fila.
- **Quién recibe y quién puede:** la alerta llega por rol (`administrador`), pero A exige `usuarios.gestionar` y B y C
  `roles.gestionar`. Si un rol real recibe la alerta sin uno de los dos, verá esa opción bloqueada (D3). Se decide con
  la «Grilla de roles real».
- **Enlace de origen:** `pantalla_ruta` se usa en un aviso; si se aceptara una URL externa sería una redirección
  abierta. Solo rutas internas (D6).
- **Pantallas enteras sin permiso** (`canAccess()` → 403) quedan fuera: el mandato habla de acciones. Ver «Preparado
  para lo diferido».

## Preguntas abiertas

1. ¿Se aprueba el valor por defecto `Trabajo` (D1) y la lista de acciones de administración del núcleo?
2. ¿«Bloqueado y presionable» (D3a) cumple «deshabilitado» del pedido, o se quiere el botón realmente deshabilitado con
   la solicitud en otro lugar?
3. ¿Se unifican las dos acciones de «propias» de Cuentas en una (D4), y se mueve `SobreLoPropio` de la 2.21 al núcleo?
4. Estado de la solicitud como enum con CHECK (D6a), como excepción con el precedente de `rondas_aprobacion`: ¿de acuerdo?
5. Riesgo de dos cambios a la vez sobre un rol en la opción B (D10, pendiente «Dos cambios a la vez sobre un rol»).
6. Qué roles reales reciben la alerta (pendiente «Grilla de roles real», Gerencia). Hasta entonces, `administrador`.
7. ¿Se permite elegir en A un rol **acotado a un ámbito** (`asignar-rol-en-ambito`) cuando la solicitud viene de un
   documento con ámbito? Propuesta: no en esta tarea (solo roles globales), y queda el punto de extensión.

## Pasos

Un paso = un commit, con su bitácora. En cada paso, solo las pruebas de lo tocado, pint y phpstan.

- [ ] 1. ADR 0022 (D13) con las decisiones aprobadas; fila en el mapa al día.
- [ ] 2. Prueba corta de Filament 5: un botón bloqueado (gris, candado, tooltip) que abre una ventana; si no
      funciona, volver a D3 antes de seguir.
- [ ] 3. `ClaseAccion`, `Accion::clase()`, `permisosAdicionales()`, `SobreLoPropio` y `sobreLoPropio()`;
      `FaltaDePermiso`, `RequisitosDeAccion`; las acciones de administración del núcleo lo declaran; prueba de
      `tests/Arch`. Memoria por petición de los permisos globales en `SeguridadService`.
- [ ] 4. `PermisoFaltante` y su uso en `EjecutorAcciones`, `TransicionesService` e `IaService`; se quitan
      `AccionRechazada::sinPermiso` y `TransicionRechazada::sinPermiso`; pruebas ajustadas.
- [ ] 5. `CondicionDePermiso` y `CondicionSobreLoPropio` en el núcleo; `SalidaDeEstado::faltantes`;
      `barraDeEstados()` devuelve también las salidas bloqueadas por permiso; `BloqueoEdicion` sigue igual.
- [ ] 6. Migración con `/nueva-migracion`: `solicitudes_permiso` y `solicitud_permiso_permisos` (D6), modelo y
      `RegistraCambios`; lista de motivos `nucleo.solicitud_permiso_rechazo`.
- [ ] 7. `SolicitudesPermisoService::solicitar()` y la acción `solicitar` con `/nueva-accion`; alerta
      `nucleo.solicitud-permiso` y grupo de avisos `accesos`.
- [ ] 8. `AccionFilament`: botón bloqueado de trabajo con tooltip, ventana y solicitud; aviso con «Solicitar permiso»
      al atrapar `PermisoFaltante`; administración sin cambios.
- [ ] 9. Resolver (D9): acción `resolver` y `rechazar` sobre las de roles por `EjecutorAcciones`, en una transacción,
      con comprobación final y cierre de las cubiertas; aviso de respuesta.
- [ ] 10. Roles y permisos: pestaña «Solicitudes» y panel de resolución (D11), con la advertencia de B y el control
      compensatorio.
- [ ] 11. Reglas escritas: `docs/convenciones/interfaz.md` §«Acciones sin permiso» (bloqueado, tooltip, solicitud;
      administración oculta; la pantalla solo usa `->hidden()`), `codigo.md` §Acciones registradas (`clase()`,
      `permisosAdicionales()`, `sobreLoPropio()`, `PermisoFaltante`), y la lección en AGENTS.md.
- [ ] 12. `composer check` en verde (una vez, en segundo plano) y revisión del subagente `revisor-arquitectura`.

## Preparado para lo diferido

- **API futura:** `PermisoFaltante::paraRespuesta()` deja el cuerpo del 403; la ruta lo usa cuando exista.
- **Rol acotado en la opción A** (pregunta 7): `resolver()` recibe la opción como enum; agregar «A en ámbito» es otro
  caso que llama `asignar-rol-en-ambito`.
- **Pantallas enteras sin permiso:** `RequisitosDeAccion` y la acción `solicitar` aceptan una pantalla sin acción
  (`accion_clave` nula sería el cambio); la página 403 podría ofrecer «Solicitar permiso» después.
- **Mis solicitudes:** la acción `retirar` y `SolicitudesPermisoService::deLaPersona()` quedan listos; la lista en
  «Mis datos» o «Mien» se agrega cuando se pida.
- **«Propias» como ámbito** (ADR 0021): si algún día existe, solo cambian `puedeSobrePropio` y `RequisitosDeAccion`.

## Criterio de aceptación

Pruebas (en `tests/Modules/Nucleo/`, cada archivo pasa solo):

- `AccionesSinPermisoTest`
  - sin permiso, una acción de trabajo se ve bloqueada, con el tooltip «Te falta «<nombre legible>»» y la ventana con
    «Solicitar permiso»;
  - una acción de administración sin permiso sigue oculta;
  - una acción de «propias» sobre un registro ajeno muestra la regla completa («Solo el ejecutivo de esta cuenta, o
    quien tenga «Editar todas las cuentas», puede editarla.»), sin nombres de personas;
  - con permiso, la acción se ve y se ejecuta igual que hoy (mismo formulario, mismo resultado).
- `PermisoFaltanteTest`: el ejecutor, una transición y un servicio rechazan con `PermisoFaltante`, que lleva clave y
  nombre legible de cada permiso que faltó y nada del registro; `paraRespuesta()` da ese cuerpo.
- `BarraDeEstadosPermisosTest`: una transición sin permiso sale bloqueada con su falta; una que falla una condición de
  negocio sigue sin salir; con permiso, igual que hoy.
- `SolicitudesPermisoTest`
  - una acción con dos permisos faltantes crea **una** solicitud con los dos;
  - una segunda solicitud igual mientras la primera está pendiente avisa y no crea otra (y la base lo impide aunque
    se salte el servicio);
  - la alerta llega a quienes tienen el rol configurado, con los permisos, la persona y el origen, y enlaza al panel.
- `ResolverSolicitudPermisoTest`
  - A: la persona recibe un rol que ya tiene los dos permisos; queda concedida; queda la bitácora de `asignar-rol`;
  - B: los dos permisos se agregan juntos a su rol, y la pantalla advierte «otras N personas» con N correcto;
  - C: rechazada con motivo de la lista (y «Otro» con detalle);
  - un par incompatible frena A o B y pide el control compensatorio, como en Roles y permisos; con el control, pasa;
  - la persona recibe en la campana la respuesta (concedido, o rechazado con el motivo) con enlace a la pantalla de
    origen;
  - Roles y permisos lista las pendientes y las resueltas.

Visible para el usuario: el botón bloqueado con candado y su texto al pasar el mouse; «Solicitar permiso»; el aviso en
la campana; la pestaña «Solicitudes» en Roles y permisos con su panel.

## Bitácora

- 2026-11-06 — Plan escrito desde el pedido del desarrollador, con el código de `main` (d7fad95). Hallazgos: el
  rechazo de hoy ya es `ErrorDeNegocio` (no `AuthorizationException`); «propias» en Cuentas son dos acciones; la barra
  de estados no tiene componente en `main` (lo trae la 2.21); los pares incompatibles piden control compensatorio, no
  bloquean en seco. Decisiones D1 a D13 propuestas; ninguna aprobada. Sigue: que Sebastián revise D1 a D13 y las
  preguntas abiertas.
- 2026-11-15 — **En espera** por decisión del desarrollador: aprueba D1 a D13 como las recomienda el plan (D3: el botón
  aparece bloqueado) y deja la construcción para después del reparto. Con esto se cumple el punto 4 del corte. Ver
  «En espera» arriba (punto de extensión y regla hasta entonces).
