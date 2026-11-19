# Plan: Alertas idempotentes (M13)

- **Estado:** terminado
- **Módulo(s):** Nucleo
- **Fase:** F1
- **Creado:** 2026-10-26 · **Actualizado:** 2026-10-27

## Objetivo

El núcleo tiene un solo mecanismo de alertas (el «catálogo único» del v4): cada tipo de alerta se declara una vez, se
configura en un solo lugar y se emite una sola vez por hecho aunque el job se repita. Con él salen las cuatro alertas
que dejaron preparadas las tareas anteriores: cruces sin control, efecto externo fallido, correo de aviso que no salió y
autoaprobación por revisar; y la revisión diaria cierra los cruces de roles acotados vencidos.

## Contexto que leí

- `docs/planes/mapa-etapas.md` fila 1.10.
- `docs/convenciones/codigo.md` «Eventos y jobs» (clave única en `alertas_emitidas.clave`, `withoutOverlapping()`,
  jobs fallidos avisan al responsable) y «Adjuntos y avisos» (todo aviso por `NotificacionesService::avisar()`).
- DBML M13 (`alerta_tipos`, `alerta_preferencias`, `alertas_emitidas`) y M8 (`plantillas_notificacion`).
- v4 parte 7, «Catálogo único de alertas y vencimientos»: un solo mecanismo y un solo lugar de configuración; cada
  persona ajusta anticipación y canal dentro de lo que su rol permite; las obligatorias se adelantan, no se apagan.
- Plan 1.4 D9 (aviso diario de cruces sin control; cierre de cruces de roles acotados vencidos), plan 1.5
  (`EjecutarEfectoExterno::failed`), plan 1.7 D6 (autoaprobaciones por revisar), plan 1.8 D6 a D8 (avisos, grupos,
  correo por grupo; plantillas y anticipación diferidas aquí).
- Pendientes: «Servicio de correo saliente» y «Contacto de alertas» (TI) no bloquean: los destinatarios son datos
  (D2) y en desarrollo el correo queda en el registro.

## Decisiones

Aprobadas por el desarrollador el 2026-10-26: D2, D3 (resumen de cruces), D5 y D6 elegidas tras conversarlas; D1, D3, D4, D7 y D8
(íconos, vistos en imagen) aprobadas juntas.

- **D1 · Alerta = aviso que el sistema manda solo.** Cada tipo de alerta lo declara su módulo en código
  (`RegistroAlertas`: clave, grupo de avisos, si es obligatoria, textos por defecto y marcadores) y su parte editable
  vive en `alerta_tipos`. Cada hecho se registra una sola vez en `alertas_emitidas` (clave única: tipo + entidad +
  umbral + fecha) y llega a las personas por `NotificacionesService::avisar()`. Desviación del DBML: `alertas_emitidas`
  es una fila por hecho, no por persona (por persona ya está `notificaciones`).
- **D2 · A quién.** **Aprobada 2026-10-26, con el rol de Gerencia General.**
  - Cada tipo tiene sus roles destinatarios, editables en Configuración → Alertas: se agrega cualquier rol (aunque en
    teoría no le toque) y se quita. **Siempre al menos uno**: no se guarda una alerta sin roles. Le llega a toda
    persona activa con alguno de ellos; algunas suman una persona puntual (quien hizo el cambio de estado).
  - Recibir una alerta es independiente de los permisos: la grilla (1.4c) dice qué puede hacer un rol; esta lista, de
    qué se entera.
  - **Rol de fábrica `gerencia_general`** («Gerencia General»), sin permisos (los decide la grilla, 1.4c). El
    administrador y el gerente general son personas distintas.
    - Se registra al instalar, junto con el primer administrador; **sin gerente general activo el sistema no arranca**
      (la pantalla solo dice «Falta registrar al gerente general»).
    - Nunca se queda sin uno: no se desactiva al último gerente general activo ni se le quita el rol (como el último
      administrador, plan 1.4 D8). Para cambiarlo, primero se asigna al nuevo.
  - Por defecto: cruces sin control y autoaprobaciones → Gerencia General (control interno, v4); efecto externo
    fallido y correo que no salió → Administrador (técnicas). Los roles de negocio por defecto llegan con la 1.4c.
- **D3 · Las alertas de hoy.**
  - Cruces sin control: **un solo resumen** diario a las 07:00 (Lima), solo si hay alguno, con enlace a la lista
    (aprobado 2026-10-26). Obligatoria.
  - Efecto externo fallido: al agotar sus intentos, uno por efecto, a quien hizo el cambio de estado y a los roles.
    Obligatoria. Sin datos del efecto: documento y transición.
  - Correo de aviso que no salió: como máximo uno por día, solo en la campana (si el correo falla, avisar por correo
    también fallaría).
  - Autoaprobación por revisar: en el momento, una por firma, con enlace a la pestaña de Configuración → Aprobaciones.
- **D4 · Revisión diaria.** Un job a las 07:00 (Lima), `withoutOverlapping()` y en un solo servidor: primero cierra los
  cruces de roles acotados vencidos (`SeguridadService`), luego pide a cada fuente registrada sus hechos. Una fuente
  que falla no frena a las otras (queda en el registro, solo con ids).
- **D5 · Textos editables.** **Aprobada 2026-10-26; forma elegida el 2026-10-27: opción B** (cuadro de texto con los
  datos entre llaves, insertados con su botón; el servicio no guarda sin ellos), no piezas imborrables. Cada tipo de alerta trae su asunto y su texto por defecto (en
  código) con sus datos: piezas que el sistema llena siempre con el valor real (cruces: cantidad; efecto fallido:
  documento y paso; correo que no salió: cantidad y fecha; autoaprobación: documento y quién se autoaprobó). En
  Configuración → Alertas se cambian las palabras alrededor, nunca los datos: son piezas que no se borran, y si falta
  una no se guarda («Falta el documento»). «Volver al texto por defecto» lo recupera. Nunca datos sensibles (regla
  10). Solo alertas: los avisos puntuales de la 1.8 conservan su texto en código. Desviación: una sola tabla
  (`alerta_tipos`) en vez de `alerta_tipos` + `plantillas_notificacion`.
- **D6 · Anticipación.** **Aprobada 2026-10-26.** El mecanismo queda hecho y probado (días por tipo, «60,30,15»; el umbral entra en la clave),
  pero ninguna alerta del núcleo la usa: la estrenan contratos y cartas fianza. El ajuste por persona
  (`alerta_preferencias`) se difiere hasta esa primera alerta; el canal por correo ya se elige por grupo (1.8).
- **D7 · Pantalla.** **Aprobada 2026-10-26.** Configuración → Alertas: cajas con buscador y panel deslizante desde la
  derecha para editar roles, textos, anticipación y si está activa (las obligatorias no se apagan). Por acciones registradas `nucleo.alertas.*` con
  versión; permiso `alertas.configurar`.
- **D8 · Íconos nuevos.** Configuración → Alertas `heroicon-o-bell-alert`; cruces sin control
  `heroicon-o-shield-exclamation`; efecto fallido `heroicon-o-bolt-slash`; correo que no salió `heroicon-o-envelope`;
  autoaprobación por revisar `heroicon-o-document-magnifying-glass`; tipo de cambio que no llegó `heroicon-o-signal-slash`
  (elegido el 2026-10-27 entre dos).

## Preparado para lo diferido

- Anticipación por persona: la anticipación se pide siempre a un solo método (`AlertasService::anticipacionPara()`), que
  hoy devuelve la del tipo; `alerta_preferencias` solo lo completa.
- Alertas de otros módulos (hitos, cartas fianza, stock): registran su fuente y su tipo en su provider, sin tocar el
  núcleo.
- Otros canales (WhatsApp): siguen entrando por `NotificacionesService` (1.8, D6).

## Pasos

- [x] 1. Plan y decisiones aprobadas.
- [x] 2. Rol de fábrica Gerencia General: se registra al instalar, sin él el sistema no arranca, nunca se queda sin uno.
- [x] 3. Mecanismo: tablas, `RegistroAlertas`, `AlertasService`, textos con datos obligatorios, revisión diaria
  programada, cierre de cruces vencidos.
- [x] 4. Las cuatro alertas conectadas.
- [x] 5. Configuración → Alertas (acciones y pantalla).
- [x] 6. `codigo.md`, mapa, revisor-arquitectura, `composer check` en verde.

## Criterio de aceptación

- Repetir la revisión diaria o reintentar un job no repite ninguna alerta ni su correo.
- Cada una de las cuatro alertas llega a quien corresponde, con el texto editado si lo hay.
- Un cruce de un rol acotado vencido queda cerrado tras la revisión diaria.
- Sin gerente general activo el panel no deja trabajar; no se desactiva ni se le quita el rol al último.
- Un texto editado sin uno de sus datos no se guarda; ninguna alerta queda sin roles.

## Bitácora

- 2026-10-26 — Rama `nucleo-alertas` desde main (con la 1.9 y la interfaz fusionadas). Contexto leído; decisiones
  propuestas.
- 2026-10-26 — Decisiones conversadas y aprobadas. El desarrollador pidió el rol Gerencia General obligatorio desde la
  instalación (el administrador no siempre es el gerente) y que los textos editables conserven siempre los datos
  reales. Gestión completa de roles (deshabilitar, eliminar, grilla, roles por defecto acordados): tarea 1.4c, después.
- 2026-10-27 — Paso 2. Rol `gerencia general` en `SeguridadSeeder` (sin permisos; el seeder no se los toca).
  `seguridad:gerente-general` comparte con `seguridad:primer-administrador` la base `ComandoCuentaInicial`. El candado
  de `SeguridadService` (`cuidaTitulares`) impide quedarse sin gerente activo; no se renombra ni se da en un ámbito.
  `ExigirGerenteGeneral` (persistente, después de `ExigirClaveRenovada`) lleva a `FaltaGerenteGeneral`; la
  comprobación de Livewire de ambos va en `PeticionLivewire`. Crear un gerente en cada prueba descuadraba 29 conteos
  ajenos: el bloqueo se apaga en las pruebas con `nucleo.exigir_gerente_general` (fijo en `true`, sin variable de
  entorno) y `GerenteGeneralTest` lo enciende. En local, `DatabaseSeeder` crea un gerente inventado (DNI 00000001);
  sembrada la base de desarrollo. `despliegue.md`: el paso de las dos cuentas de inicio.
- 2026-10-27 — Paso 3. Migración `crear_alertas` (`alerta_tipos`, `alerta_tipo_roles`, `alertas_emitidas`; desviaciones
  en su encabezado). `DefinicionAlerta` + `RegistroAlertas` (cada alerta suma su aviso en `RegistroAvisos`, misma
  clave), `TextoAlerta` (marcadores, faltantes, desconocidos), `FuenteAlertas`, `AlertasService` (`emitir` con
  `createOrFirst` sobre la clave única; `destinatarios` con respaldo en el administrador; `anticipacion`, punto de
  enchufe de D6; `revisarDelDia`; `sincronizar`, que corre `AlertasSeeder`). `DefinicionAviso::conCorreo` para la de
  solo campana. `nucleo:revisar-alertas` a las 07:00 de Lima, `withoutOverlapping()->onOneServer()`. Fuente
  `CrucesSinControl`: `SeguridadService::cerrarCrucesVencidos()` y el resumen del día. Grupos de avisos nuevos:
  «Control interno» y «Fallas del sistema» (suman su preferencia de correo).
  - La alerta de cruces va sin enlace: la vista de cruces llega con la 1.4c, que lo agrega en `CrucesSinControl`.
  - Correo que no salió: un solo dato, la **fecha**; la cantidad no se conoce cuando falla el primero (se avisa en ese
    momento). Se lo cuento al desarrollador.
- 2026-10-27 — Paso 4. `EfectosExternosService::avisarQueFallo()` desde el `failed()` de `EjecutarEfectoExterno` **y de
  `RevertirEfectoExterno`** (la reversión al anular también quedaba solo en el registro): documento (nombre e
  identificador de su definición) y paso (la transición; al revertir, «revertir <la que anuló>»), a los roles y a quien
  hizo el cambio (o lo anuló); una vez por efecto y sentido. `EnviarCorreoDeAviso::failed()` emite la del correo con
  la fecha del día. `AprobacionesService::autoaprobar()` emite la suya en la misma transacción, con enlace a la pestaña
  `?tab=autoaprobaciones`. Un aviso que falla dentro de un `failed()` queda en el registro (solo ids y la clase del
  error) y no tapa el fallo original.
- 2026-10-27 — Decisiones del desarrollador tras el paso 4: el correo que no salió se avisa en el momento, con la fecha
  y por la campana (como estaba). **Alerta nueva, aprobada:** tipo de cambio que no llegó (pendiente que la 1.0 dejó
  anotado en `SincronizarTipoCambio`): si SUNAT falla y no hay tipo de cambio de hoy, al administrador, una vez al día,
  con enlace a Configuración → Tipos de cambio. **Pruebas:** `check:tarea` corría las 1.687 en cada paso (la rama tocó
  `tests/Pest.php`); el desarrollador pidió correrlo al terminar cada bloque, no en cada paso.
- 2026-10-27 — Paso 5. Acción `nucleo.alertas.configurar` (permiso `alertas.configurar`, de fábrica en el rol
  administrador) y `AlertasService::configurar()` (al menos un rol, obligatoria no se apaga, anticipación solo donde
  la hay, datos completos y ninguno inventado; un texto igual al de fábrica se guarda vacío; versión). Pantalla
  `Alertas` (Configuración, ícono `heroicon-o-bell-alert`): cajas por grupo de avisos con buscador y el panel de
  Catálogos, cuyos estilos pasaron a `partials/cajas-con-panel`; «Sin nadie» en rojo si sus roles no tienen a nadie
  activo; botones «+ dato» que insertan el marcador en el cursor; «Volver al texto por defecto». Capturas: no se
  pudieron sacar sin una sesión en la app; se le pide al desarrollador que la mire.
- 2026-10-27 — Revisión de arquitectura: sin bloqueantes; D1 a D8 cumplidas. Atendido: comentarios descolocados
  (`SeguridadService`, `config/nucleo.php`); nota en `codigo.md` de por qué la alerta de autoaprobación va sin `try`;
  una alerta nace con el administrador si sus roles de fábrica no existen (nunca sin roles); `configurar()` rechaza un
  rol que no existe; `despliegue.md` pide caché con bloqueos compartidos para `onOneServer()`. Queda para la 1.4c:
  eliminar un rol debe rechazar `gerencia general` y `administrador`; y el comando de inicio solo crea cuentas nuevas.
- 2026-10-27 — Tipo de cambio, decidido por el desarrollador (opción A): SUNAT no tiene webhook ni aviso, así que se
  consulta cada 30 minutos de 07:00 a 19:00 (antes, 08:30, 12:00 y 18:00), y la alerta sale solo si falla el último
  intento del día y todavía no hay tipo de cambio de hoy. Corregido lo que se le dijo: un día sin TC no bloquea (vale
  el de hasta 4 días antes, ADR 0012).
- 2026-10-27 — Cierre. Prueba de la acción sin interfaz. `pendientes.md` («Grilla de roles real»): ya existe
  `gerencia general`; con la grilla, los roles por defecto de las alertas de negocio. Error propio: corrí un archivo de
  pruebas mientras corría `composer check` y chocaron en la base de pruebas (lo advierte `CLAUDE.md`); se relanzó la
  corrida limpia. Sigue: 1.4c (grilla de roles; enlace del resumen de cruces y rechazo a eliminar los roles de
  fábrica), 1.11.

