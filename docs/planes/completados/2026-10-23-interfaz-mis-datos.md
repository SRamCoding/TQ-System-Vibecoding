# Plan: 1.4h · Interfaz: «Mis datos»

- **Estado:** completado (2026-10-23)
- **Módulo(s):** Nucleo (cuenta, ficha de la persona, contraseña) y panel `app`
- **Etapa:** 1 · tarea 1.4h · rama `interfaz-mis-datos` (desde `main`, f155ff5)
- **Creado:** 2026-10-23 · **Actualizado:** 2026-10-23

## Objetivo

Cada persona ve sus datos de cuenta en una pantalla propia, «Mis datos», y cambia ahí lo suyo: su cumpleaños, su
teléfono y su contraseña (esta, con un código que llega a su correo). «Mi cumpleaños» sale del menú de la foto. Lo que
define su identidad y su acceso (nombre, documento, correo, puesto) se ve pero no se cambia desde aquí.

## Contexto que leí

- `docs/planes/mapa-etapas.md` (fila 1.4h y el orden del 2026-10-23: 1.4h, 1.4l, 1.4i, luego 1.6 a 1.13).
- `docs/planes/completados/2026-10-22-interfaz-preferencias-usuario.md` (D1: el cumpleaños no es preferencia; el
  estilo de filas de «Mis preferencias»).
- Código: `User` (nombre copiado de la ficha, correo, puesto, último acceso, cumpleaños, `version`), `Party`
  (`telefono` texto libre de 30, `version`), `PartiesService::editar()` (bloqueo optimista), acciones
  `nucleo.registrar-mi-cumpleanos` y `nucleo.seguridad.registrar-cumpleanos-usuario`, `PreferenciasUsuarioService::
  registrarCumpleanos()`, el menú del avatar en `AppPanelProvider` («Mi cumpleaños»), `->passwordReset()` de Filament.
- `docs/operaciones/requisitos-servidor.md`: el correo saliente es un requisito del servidor, pero el servicio y el
  remitente no están definidos. `.env.example`: `MAIL_MAILER=log`, `CACHE_STORE=database`.
- Pendientes: ninguno bloquea. Nuevo: «Servicio de correo saliente» (D3).

## Decisiones (aprobadas por el desarrollador, 2026-10-23)

- **D1 · Qué trae «Mis datos».** Se ven, sin poder cambiarse: nombre completo y documento (de su ficha), correo de
  acceso, puesto y unidad, último acceso. Se cambian: **cumpleaños**, **teléfono** y **contraseña**. El **directorio
  del equipo** va en una tarea aparte (abre decisiones de privacidad: qué ven los demás de cada uno). Los datos
  laborales de RRHH (contrato, cuenta de sueldo, salud) no van aquí: son de la etapa 4.

- **D2 · El teléfono.** Un solo teléfono, el de la ficha (`parties.telefono`): no se crea otro en la cuenta. Acción
  propia **`nucleo.registrar-mi-telefono`**, sin permiso: cambia solo el teléfono de la ficha de quien la ejecuta, con
  la misma regla que la ficha (hasta 30 caracteres) y por el servicio de personas. Bloqueo optimista con la versión de
  la ficha leída al abrir. Queda en la bitácora de la ficha. Sin ficha (usuario de sistema), el campo no aparece. La
  regla de «solo números, espacios, +, guiones y paréntesis» queda para cuando el directorio muestre teléfonos.

- **D3 · «Cambiar mi contraseña», con código por correo.**
  - Se pide un **código de 6 dígitos** que llega al **correo de acceso** (la pantalla lo muestra en parte oculto); se
    escribe el código y la nueva contraseña dos veces. **No se pide la contraseña actual.**
  - El código **vale 10 minutos y una sola vez**; se guarda cifrado (nunca en texto) y caduca solo; **5 intentos**
    equivocados lo anulan; **«Reenviar código»** como mucho una vez por minuto; pedir uno nuevo anula el anterior.
  - **La contraseña:** al menos **8 caracteres, una mayúscula, un número y un carácter especial**. La regla se define una
    vez en el sistema (`Password::defaults()`) y vale también para «¿Olvidaste tu contraseña?» y la primera contraseña
    de una cuenta nueva.
  - Al cambiarla, **se cierran las sesiones en otros equipos**; la de este sigue.
  - La contraseña nunca va a la bitácora ni a los registros; queda anotado que se cambió y cuándo.
  - Acciones registradas, sin permiso: `nucleo.pedir-codigo-clave` y `nucleo.cambiar-mi-clave`.
  - **Pendiente nuevo:** el servicio de correo saliente de producción (proveedor o SMTP, remitente, SPF y DKIM), para
    TI, antes de producción. En desarrollo el correo queda en `storage/logs`; en las pruebas se simula.

- **D4 · La pantalla.** `/mis-datos`, para toda persona con sesión, desde el menú de la foto (no va en la barra de
  módulos), con el estilo de filas de «Mis preferencias»:
  - **Mi cuenta:** solo lectura, con la nota «Si algo está mal, pídelo a quien administra los usuarios».
  - **Mis datos de contacto:** cumpleaños (día y mes) y teléfono, que **la propia persona cambia**, con un botón
    **«Guardar»** (son datos con versión: se guardan juntos al terminar; si alguien los cambió mientras tanto, el aviso
    de siempre). Quien gestiona usuarios también puede cambiar el cumpleaños desde la ficha del usuario, como hoy; los
    dos caminos usan el mismo servicio y la misma versión.
  - **Seguridad:** «Cambiar mi contraseña» (D3).
  - **Menú de la foto:** sale «Mi cumpleaños»; entra «Mis datos», antes de «Mis preferencias».

- **D5 · Las contraseñas actuales que no cumplen la regla dejan de servir** (pedido del desarrollador, 2026-10-23).
  Una contraseña guardada está cifrada: solo se puede comprobar cuando la persona la escribe. **Al iniciar sesión**, si
  la que escribió no cumple la regla de D3, la cuenta queda marcada (`users.debe_cambiar_clave`). Con la marca, toda
  página lleva a **«Cambia tu contraseña»**, donde solo se puede poner la nueva (dos veces) o cerrar sesión; no se pide
  el código por correo, porque acaba de demostrar que conoce la suya (así nadie queda bloqueado si el correo de
  producción todavía no funciona). Al cambiarla, se quita la marca, se cierran las otras sesiones y entra normal.
  Acción registrada `nucleo.renovar-mi-clave`. Vale también para la cuenta de desarrollo (su clave de prueba no cumple).

## Pasos

Un commit al cerrar cada paso, con push. Cada paso deja `composer check` en verde.

- [x] 1. **Plan aprobado** (D1-D4).
- [x] 2. **Contraseña:** regla única (`Password::defaults()`), código por correo (guardado cifrado en la caché, 10 min,
      5 intentos, reenvío por minuto), notificación por correo, acciones `nucleo.pedir-codigo-clave`,
      `nucleo.cambiar-mi-clave` y `nucleo.renovar-mi-clave`, cierre de las otras sesiones; la marca al iniciar sesión
      con una clave que no cumple y la pantalla obligatoria «Cambia tu contraseña» (D5). Pruebas.
- [x] 3. **Teléfono:** acción `nucleo.registrar-mi-telefono` por el servicio de personas, con versión. Pruebas.
- [x] 4. **Pantalla «Mis datos»** y el menú de la foto.
- [x] 5. **Documentos:** `interfaz.md`, `codigo.md` si hace falta, `pendientes.md` (correo saliente), mapa,
      `cambios.md`.
- [x] 6. Revisión a la vista, `revisor-arquitectura`, `composer check` y `/cerrar-tarea`.

## Criterio de aceptación

- **Contraseña:** una contraseña sin mayúscula, sin número, sin carácter especial o de menos de 8 se rechaza, también
  al restablecerla; el código llega al correo de la persona y no a otro; un código vencido, usado, anulado por
  intentos o reemplazado no sirve; 5 intentos equivocados lo anulan; el reenvío respeta el minuto; la contraseña nunca
  aparece en la bitácora ni en los registros; al cambiarla, la sesión de este equipo sigue y las demás se cierran.
- **Teléfono:** cambia solo el de la ficha de quien lo ejecuta; con la versión vieja, el aviso; queda en la bitácora de
  la ficha; sin ficha, no hay campo.
- **Clave que no cumple (D5):** al entrar con ella, la cuenta queda marcada y toda página lleva a «Cambia tu
  contraseña»; con una que cumple, nada; al renovarla se quita la marca y se entra normal.
- **Pantalla:** «Mi cuenta» sin campos editables; «Guardar» cambia cumpleaños y teléfono; el menú de la foto ya no
  tiene «Mi cumpleaños» y sí «Mis datos».

## Bitácora

- 2026-10-23 — Tarea planeada en conversación con el desarrollador. Rama `interfaz-mis-datos` desde `main` (f155ff5,
  con la 1.4d en pausa tras su paso 5 fusionada). D1 a D4 aprobadas una por una. Siguiente: paso 2.
- 2026-10-23 — D5 agregada a pedido del desarrollador: las contraseñas que no cumplen la regla dejan de servir; se
  detecta al iniciar sesión y obliga a renovarla (sin código por correo en ese caso).
- 2026-10-23 — Pasos 2 y 3 hechos (un solo commit: se tocan en el proveedor del Núcleo).
  **Contraseña:** `ClaveService` (regla única `regla()`, registrada con `Password::defaults()`, así vale también en
  «¿Olvidaste tu contraseña?»; `faltas()` con mensajes en español, nuevos en `lang/es/validation.php`;
  `pedirCodigo()`, `cambiarConCodigo()`, `renovar()`, `marcarSiNoCumple()`). El código: 6 dígitos con `random_int`,
  cifrado en la caché con su vencimiento (un intento fallido no le da más vida), 5 intentos, reenvío por minuto, el
  nuevo anula el anterior. Notificación `Notificaciones/CodigoCambioClave` (correo). Migración
  `2026_10_23_000002_agregar_cambio_de_clave_a_users` (`debe_cambiar_clave`, `clave_cambiada_at`). Acciones
  `nucleo.pedir-codigo-clave`, `nucleo.cambiar-mi-clave`, `nucleo.renovar-mi-clave` (la regla la revisa el servicio,
  no el campo). D5: `Listeners/RevisarClaveAlEntrar` (en `Attempting` anota en la petición si la escrita cumple, nunca
  la contraseña; en `Login` marca la cuenta), `Http/ExigirClaveRenovada` (en `authMiddleware`: toda página lleva a
  `RenovarClave`; de las peticiones de Livewire solo pasan las de esa pantalla, por el nombre del componente en el
  snapshot), página `Filament/Pages/RenovarClave` (actualiza el resumen de la contraseña en la sesión: esta sigue, las
  otras se cierran con `AuthenticateSession`). El factory usa `UserFactory::CLAVE` (`Clave-de-prueba1`).
  Encontrado al revisar: `SeguridadService::establecerClave()` (el comando del primer administrador) solo exigía 12
  caracteres; ahora además la regla.
  **Teléfono:** acción `nucleo.registrar-mi-telefono` (teléfono `present` hasta 30, versión de control), por
  `PartiesService::editar()` sobre la ficha de quien la ejecuta; `PartyRechazada::cuentaSinFicha()`.
  El correo repetido al crear una cuenta ya tenía su mensaje claro («Ya hay una cuenta con el correo…», con prueba);
  el desarrollador puede pedir que salga junto al campo.
  Pruebas: `ClaveTest` (21), `MiTelefonoTest` (6); `PrimerAdministradorTest` y `SeguridadServiceTest` con claves que
  cumplen. `composer check` en verde (1431). Desde ahora el agente abre el PR y avisa; el desarrollador fusiona.
  Siguiente: paso 4 (la pantalla).
- 2026-10-23 — El desarrollador fusionó el PR #32 (pasos 1 a 3) por su cuenta y probó la renovación obligatoria: bien.
- 2026-10-23 — Paso 4 hecho. `Filament/Pages/MisDatos` (`/mis-datos`, toda persona con sesión, fuera de la barra):
  «Mi cuenta» con `TextEntry` (solo lectura), «Mis datos de contacto» (día, mes por nombre y teléfono, con las
  versiones de la cuenta y de la ficha en campos ocultos leídos al abrir; «Guardar» ejecuta
  `nucleo.registrar-mi-cumpleanos` y `nucleo.registrar-mi-telefono`; si uno falla, avisa, conserva lo escrito y relee
  las versiones) y «Seguridad» («Cambiar mi contraseña»: ventana con «Enviar código» en el pie, el código y la nueva dos
  veces; un rechazo deja la ventana abierta). `nucleo.registrar-mi-cumpleanos` acepta, opcional, la versión de la
  cuenta (sin ella, como antes). Menú de la foto: sale «Mi cumpleaños», entra «Mis datos» antes de «Mis preferencias»;
  salen `accionMiCumpleanos()` y `avisoGuardado()` del proveedor del panel. Revisada con capturas en escritorio y
  celular. Pruebas: `MisDatosTest` (7); `AccionesSaludoTest` espera «Mis datos» en el menú.
  Paso 5 hecho. `interfaz.md` («Mis datos y contraseña»), `codigo.md` («Contraseñas»), `pendientes.md` (correo
  saliente), `cambios.md` (Común 0.3.5; README y `comun.md` al día). `composer check` en verde (1438). Siguiente: paso 6.
- 2026-10-23 — Paso 6, revisión de `revisor-arquitectura` (4 «debería», 7 menores, nada bloqueante) atendida:
  `ExigirClaveRenovada` es persistente (`authMiddleware(..., isPersistent: true)`) y revisa los componentes de Livewire
  antes que la ruta (Livewire la reaplica con la ruta de la página de origen: dejaba pasar la barra superior y la
  búsqueda); `ClaveService` pide y verifica el código bajo un candado por persona (dos pestañas ya no se saltan los 5
  intentos ni reviven un código usado) y lo borra si el correo falla; `#[\SensitiveParameter]` en todo parámetro con
  contraseña o código; `PasswordReset` y entrar con una que cumple quitan la marca (`ClaveService::alRestablecer()` y
  `revisarAlEntrar()`, que reemplaza a `marcarSiNoCumple()`, que no se usaba); `establecerClave` también quita la marca;
  al cambiarla se renueva `remember_token`; `seguirEnEsteEquipo` pasó a `Http/SesionDeClave`. Pruebas nuevas: el
  bloqueo es persistente, rechaza otros componentes aunque vengan de «Cambia tu contraseña», esta sesión sigue y otra
  con la contraseña vieja sale al ingreso, restablecer o entrar con una que cumple quita la marca. Queda tal cual
  (documentado en `MisDatos::guardar`): cumpleaños y teléfono se guardan por separado; si fallara entre los dos, avisa.
  `codigo.md` («Contraseñas»: middleware persistente, candado, `SensitiveParameter`) y `pendientes.md` (nunca
  `MAIL_MAILER=log` en producción).
- 2026-10-23 — Fuera del plan, pedido del desarrollador durante el cierre: letras. TECHQUK en mayúsculas y letra de
  consola de píxeles (Press Start 2P, OFL, en `public/fonts/press-start-2p/` con su licencia); saludo, frase (en
  cursiva) y nombres de módulos en Times New Roman. `Inicio::getSubheading()` devuelve la frase envuelta en
  `.tq-frase` (escapada). `interfaz.md` («Identidad visual», «Letras»). Además, el desarrollador pidió la 1.4e (dibujo
  diario) antes de los mecanismos del núcleo: el mapa la pone tras la 1.4i.
- 2026-10-23 — Paso 6 hecho. `composer check` en verde (1441 pruebas). Plan cerrado; pasa a `completados/`. Sigue la
  1.4l («Mien»).
- 2026-10-23 — El desarrollador vio las letras y pidió dejar el saludo, la frase y los módulos como antes: se quita Times
  New Roman y la cursiva; queda solo TECHQUK en Press Start 2P.
  La frase sí queda en cursiva (con la letra de siempre).
