# Plan: asistente de configuración inicial (1.13)

- **Estado:** terminado (2026-10-30)
- **Módulo(s):** núcleo (M5 catálogos, M9 parámetros, M10 seguridad)
- **Fase:** F1
- **Creado:** 2026-10-30 · **Actualizado:** 2026-10-30

## Objetivo

Que el administrador deje el sistema listo para Techquk siguiendo una lista guiada en Configuración. Cada paso dice
cómo está y abre la pantalla de siempre en un panel deslizante, sin recargar. El asistente no repite formularios ni
reglas: todo lo que se cambia pasa por las acciones registradas que ya existen.

## Contexto que leí

- `docs/planes/mapa-etapas.md`, fila 1.13: datos de la empresa, organigrama, roles, monedas, parámetros y fecha de
  fundación, con las acciones que ya existen, sin lógica propia, solo para la empresa única (ADR 0003). Va después de
  la 1.7, para configurar las aprobaciones.
- ADR 0003 y el plan de la 1.2: no hay tabla `empresas`; sus datos irían «como parámetros cuando un documento los
  pida». Ninguno los pide todavía.
- Acciones registradas por área: Organigrama, Seguridad (roles, permisos, usuarios), Catálogos, Parámetros,
  Aprobaciones, Alertas e IA.
- `docs/convenciones/interfaz.md`, «Patrones de pantalla» (panel deslizante, cajas, íconos únicos), y cómo abre su
  panel `Catalogos.php`.
- `docs/decisiones/pendientes.md`: ningún pendiente bloquea.

## Decisiones del desarrollador (2026-10-30)

- **Datos de la empresa, ahora.** El asistente tiene ese paso (ver D2 para el cómo).
- **Lista guiada**, no un formulario con «Siguiente»: cada paso abre su pantalla de siempre en un panel deslizante.
- **Solo quien puede configurar, en Configuración.** Mientras falten pasos, el inicio del administrador muestra un
  aviso que lleva al asistente.

## Decisiones

- **D1. Los pasos.** En este orden, cada uno con el ícono de la pantalla que abre (la pantalla y su caja comparten
  ícono: no es repetir):
  1. Datos de la empresa.
  2. Fecha de fundación (`interfaz.aniversario_techquk`).
  3. Organigrama.
  4. Roles y permisos.
  5. Usuarios.
  6. Monedas y catálogos.
  7. Parámetros (IGV, moneda local, horario…).
  8. Aprobaciones.
  9. Alertas.
  10. Inteligencia artificial, **opcional**: la usa solo si Gerencia la autoriza.
- **D2. Datos de la empresa: Techquk como ficha de Personas y empresas.** *(Recomendado; confirmar.)* En vez de
  parámetros sueltos, un parámetro `empresa.ficha` dice cuál es la ficha de Techquk. La ficha ya existe y tiene todo
  lo demás con sus reglas: razón social, nombre comercial, RUC con su dígito verificador y la consulta a SUNAT,
  dirección fiscal y sedes, contactos y **cuentas bancarias**, que las facturas necesitarán. El paso abre esa ficha y
  la elige.
  - Si prefieres parámetros sueltos: razón social, nombre comercial, RUC, dirección fiscal, teléfono, correo y web.
    Sin cuentas bancarias, y el RUC sin la consulta a SUNAT.
  - **El logo queda para después.** Un parámetro no guarda archivos. Lo pedirá el primer documento impreso y se
    enchufa como un adjunto de la ficha.
- **D3. Cómo está cada paso.** Cada paso muestra un resumen calculado («2 unidades», «1 usuario activo», «sin
  fecha»). Pero «listo» lo marca el administrador con «Marcar como listo», que se puede deshacer. Hay pasos sin un
  «hecho» medible: las monedas o las alertas ya traen valores de fábrica. Una tabla `configuracion_pasos` (paso,
  quién, cuándo) y dos acciones registradas, `MarcarPasoListo` y `ReabrirPaso`. Es la única lógica propia, y solo
  guarda el avance.
- **D4. El panel.** Al presionar un paso, su pantalla de siempre se abre dentro del panel deslizante como componente
  de Livewire. Sus botones, tablas y acciones son los mismos, y la URL sigue el panel. **Primero se comprueba que
  funcione** (paso 1). Si alguna pantalla no se deja meter en el panel, ese paso abre la pantalla normal con un botón
  «Volver al asistente», y te aviso cuál.
- **D5. Registro de pasos.** `RegistroPasosConfiguracion`, como el de comprobaciones de salud: el núcleo declara los
  suyos, y cada módulo nuevo (compras, proyectos…) suma los suyos sin tocar el asistente.
- **D6. Aviso en el inicio.** Mientras falten pasos obligatorios, quien tiene el permiso ve en su inicio un aviso con
  «Seguir configurando». Al completarlos, desaparece.
- **D7. Permiso.** `configuracion.inicial`, que nace con el administrador.

## Pasos

- [x] 1. Prueba: meter Organigrama, Usuarios y Parámetros en un panel y comprobar que crean y editan bien.
- [x] 2. `configuracion_pasos`, `MarcarPasoListo`, `ReabrirPaso`, el permiso y sus pruebas.
- [x] 3. `RegistroPasosConfiguracion` y los 10 pasos del núcleo con sus resúmenes.
- [x] 4. Datos de la empresa según D2 (parámetro `empresa.ficha` y su validador, o los parámetros sueltos).
- [x] 5. La pantalla: lista guiada, avance, panel deslizante, y la URL que abre un paso directo.
- [x] 6. Aviso en el inicio.
- [x] 7. Capturas para que las revises (íconos, panel, transiciones).
- [x] 8. Documentos, revisión de arquitectura y `composer check`.

## Preparado para lo diferido

- **Logo de la empresa:** un adjunto de la ficha de Techquk (tipo de adjunto «Logo»), cuando lo pida el primer
  documento impreso. El paso 1 ya lleva a esa ficha.
- **Pasos de otros módulos:** se registran en `RegistroPasosConfiguracion` desde su provider.

## Criterio de aceptación

- Un administrador recorre los 9 pasos sin salir del asistente, y lo que cambia queda en las pantallas de siempre.
- Marcar y reabrir quedan registrados (quién y cuándo) y se pueden hacer sin la interfaz.
- Sin el permiso, ni la página ni el aviso.

## Bitácora

- 2026-10-30 — Plan escrito tras las tres decisiones del desarrollador. Rama `asistente-configuracion` desde `main`
  con la 1.12b (PR #65).
- 2026-10-30 — El desarrollador aprobó el plan («sí») con la recomendación de D2: Techquk como ficha de Personas y
  empresas.
- 2026-10-30 — Paso 1, **funciona**. `ListarUnidades`, `ListarUsuarios` y `ListarParametros` se meten en el panel con
  `@livewire(...)`: sin la barra lateral, con su botón de encabezado («Crear unidad», «Registrar usuario») que abre su
  ventana de siempre, la tabla con sus filas, la URL con `?paso=` y sin errores en la consola. Dentro del panel se
  esconden con CSS el título, la ruta y el menú de la sección de la pantalla (repetían y quitaban ancho). Pendiente:
  presionar una fila lleva a la ficha de esa fila (`VerUsuario`, por ejemplo), fuera del asistente.
- 2026-10-30 — El desarrollador eligió **mantener la ficha dentro del panel**. Todo enlace presionado dentro del
  panel a una pantalla de este panel de Filament (la ficha de una fila, sus pestañas) se abre ahí mismo
  (`abrirEnPanel`, `?ficha=`), con una flecha para volver al paso. Con Ctrl, Shift o el botón del medio, o hacia otro
  sitio o una descarga, el enlace de siempre. La pantalla revisa sus permisos al montarse, como siempre.
  `pantallaDe()` resuelve la ruta con el router (las pantallas de Livewire se registran como `Clase@__invoke`) y
  rechaza lo que no sea una página de `filament.app.` o el propio asistente. Probado con el navegador: ficha de usuario,
  su pestaña Roles y volver. Pruebas en `ConfiguracionInicialTest`.
- 2026-10-30 — Pasos 2 y 3.
  - `configuracion_pasos` (paso único con CHECK de clave, `es_listo`, auditoría; historial con `RegistraCambios`),
    `ConfiguracionInicialService` (marcar, reabrir, listos, faltan; `createOrFirst` y candado), `MarcarPasoListo` y
    `ReabrirPaso` con el permiso `configuracion.inicial` (del administrador), alias `configuracion_paso`.
  - `RegistroPasosConfiguracion` y `DefinicionPaso` (la pantalla puede ser una clase con parámetros o una función que la
    elige). **9 pasos, no 10:** no hay pantalla de roles y permisos (su grilla es la 1.4c; las acciones existen, pero hoy
    los roles solo se asignan desde la ficha del usuario). La 1.4c suma su paso después de Usuarios.
  - Íconos: los de cada pantalla; fundación con `heroicon-o-gift` (la torta ya es «Cumpleaños» en Usuarios).
  - **Tropiezo:** al sumar los `use` al provider repetí seis que ya estaban, y la app se cayó (error fatal) hasta
    quitarlos, unos segundos. Pruebas en `ConfiguracionInicialTest` (11).
- 2026-10-30 — Paso 4. Parámetro `empresa.ficha` (texto): el **RUC** de la ficha de Techquk, legible en la tabla de
  parámetros y sin depender de un id; «sin ficha» mientras no se elija (un parámetro de texto no puede quedar vacío).
  `ValidadorFichaEmpresa` exige una empresa activa con ese RUC y guarda solo los dígitos. `EmpresaService::ficha()`
  la devuelve: la 1.15 hará que los documentos tomen de ahí los datos (acordado con el desarrollador: la 1.13 solo la
  elige). El paso abre la lista de Personas y empresas sin ficha elegida, y la ficha de Techquk con ella; su resumen,
  «Sin elegir» o la razón social y el RUC. Cómo se elige desde el asistente va con la pantalla (paso 5), con la acción
  `CambiarParametro` que ya existe.
- 2026-10-30 — Paso 5. La pantalla de verdad: avance («N de 8 listos», con barra), los 9 pasos con número, ícono,
  descripción, resumen y estado («Listo» + «Reabrir», o «Marcar como listo»; «Opcional» en la IA), y el panel con el
  ícono y el título del paso, «Marcar como listo»/«Reabrir» y, en el de la empresa, «Elegir la ficha de Techquk»
  (`CambiarParametro`, versión tomada al abrir la ventana). Primera en Configuración (orden 0), ícono
  `heroicon-o-rocket-launch` (no se usa en otro lado), solo con `configuracion.inicial`.
  - **Tropiezos:** (1) los botones dentro del enlace del paso lo seguían: el enlace es solo el ícono y el texto. (2) La
    clase `block` no existe en los estilos del panel: CSS propio. (3) El selector de la ficha rechazaba la elegida:
    PHP vuelve entero una clave de solo dígitos (el RUC); la clave es «RUC 20…» y el validador guarda los dígitos.
    (4) En `Livewire::test`, ejecutar una acción con el panel de la empresa abierto (`ListarParties` adentro) da
    «mountedActions on null»; en el navegador funciona (marcado y reabierto con ese panel abierto, sin errores). Las
    pruebas de acciones van sin el panel abierto.
  - Recorrido con el navegador: marcar y reabrir, los 9 paneles, la ventana de la ficha; sin errores en la consola.
    Pruebas en `ConfiguracionInicialTest` (15).
- 2026-10-30 — Paso 6. Widget `AvisoConfiguracionInicial`, antes de las cajas del inicio: «Faltan N pasos de la
  configuración inicial», sus títulos y «Seguir configurando». Solo con el permiso y mientras falte algún obligatorio
  (la IA no cuenta). Comparte el cohete con la página (la pantalla y lo que lleva a ella). Pruebas: el aviso y las
  del inicio (97 en verde).
- 2026-10-30 — Paso 7. El desarrollador lo vio: «por ahora está bien»; una revisión visual más adelante (íconos, modo
  oscuro, transiciones). Documentos: `interfaz.md` (pantallas de siempre dentro del panel), mapa (la 1.15 sabe que la
  1.13 ya elige la ficha; la 1.4c suma su paso «Roles y permisos»).
- 2026-10-30 — Paso 8. `composer check` en verde (2264 de 2264, pint, phpstan, 53 migraciones). **Revisión de
  arquitectura:** sin bloqueantes; atendido:
  - El panel revisa el permiso de la pantalla de destino (`puedeAbrir`: `Resource::canAccess()`, que el `canAccess()`
    de la pantalla no mira, y `canView`/`canEdit`/`canCreate` con el registro); sin permiso, el enlace sigue su camino.
    Las secciones (`Cluster`) no se abren en el panel. **Además**, un paso cuya pantalla la persona no puede ver ya no
    la monta (se caía): el panel dice «No tienes permiso para ver esta pantalla».
  - **Redirecciones dentro del panel:** una pantalla del panel que redirige al guardar (registrar una ficha lleva a su
    ficha) sacaba de la página. `Livewire.interceptRequest` → `onRedirect`: si viene de un componente del panel y va a
    una pantalla del sistema, se abre en el panel. Probado en el navegador: registrar una ficha desde el paso de la
    empresa abrió su ficha en el panel (la ficha de prueba, de nombre inventado, se borró luego con `nucleo.parties.borrar`).
  - Menos consultas: `listos` se lee una vez y pasa a `faltan()`; la pantalla de una ficha se resuelve una vez. El
    widget del inicio hace 2 consultas por carga para quien tiene el permiso (aceptado: chicas y solo esa persona).
  - `EmpresaService` sin dependencias en el constructor (AGENTS.md).
  - Marcar y reabrir: se busca primero con candado y solo se crea al marcar (sin errores de unicidad en el registro
    de Postgres); reabrir uno nunca marcado no deja fila ni historial.
  - El paso de la empresa con el ícono de su pantalla (`tq-apreton-manos`), no el de «Mis datos».
  - Pruebas nuevas: sin permiso de la pantalla de destino, la sección, reabrir sin marcar, bloqueo optimista de
    `elegirFicha` (otro cambia el parámetro con la ventana abierta), RUC sin ficha, botón oculto sin
    `parametros.editar` (20 en verde).
  - Anotado para la 1.15: si se desactiva la ficha elegida, el resumen dice «Sin elegir» y nada avisa.
  - Lo que va después de «?» en un enlace del panel se pierde: dicho en el docblock de `abrirEnPanel`.
- 2026-10-30 — Cierre. Después de `composer check` cambiaron la página, su vista, el servicio, `EmpresaService` y el
  ícono de un paso; se corrieron solas `ConfiguracionInicialTest` (20) y `tests/Arch`, y el recorrido completo con el
  navegador (los 9 paneles, marcar y reabrir, la ficha dentro del panel con su pestaña, volver), sin errores. La
  revisión visual (íconos, modo oscuro, transiciones) queda para más adelante, a pedido del desarrollador.
