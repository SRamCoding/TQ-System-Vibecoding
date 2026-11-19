# Plan: Adjuntos y notificaciones (M7, M8)

- **Estado:** terminado
- **Módulo(s):** Nucleo
- **Fase:** F1
- **Creado:** 2026-10-26 · **Actualizado:** 2026-10-26

## Objetivo

Cualquier registro puede llevar archivos adjuntos, guardados fuera de la base y descargables solo por quien puede ver
el registro; en un documento no editable se agregan pero no se reemplazan ni se quitan. Los módulos avisan a las
personas por un único servicio, en el sistema (la campana) y por correo, respetando lo que cada persona eligió.

## Contexto que leí

- `docs/planes/mapa-etapas.md` fila 1.8; plan 1.5 D8 (adjuntos en documentos no editables).
- `docs/diseno/techquk_modelo.dbml` M7 (`tipos_adjunto`, `adjuntos`: ruta, mime, tamaño, hash, disco, `es_sensible`)
  y M8 (`plantillas_notificacion`, `notificaciones`).
- `ARCHITECTURE.md`: archivos en el disco de Laravel, en la base solo la ruta, respaldo a S3.
- `docs/convenciones/ia.md`: los adjuntos sensibles nunca salen a un servicio externo. `base-de-datos.md`: nunca
  binarios en la base. `codigo.md`: lo externo (correo) va después del commit, al menos una vez, nunca perdido.
- `docs/operaciones/`: respaldos de adjuntos con la base; correo saliente con SPF y DKIM.
- v4 parte 7: cada persona ajusta el canal de sus avisos; los obligatorios no se apagan.
- Pendientes: ninguno bloquea.

## Decisiones

Aprobadas por el desarrollador el 2026-10-26: D1, D2 y D6 elegidas entre opciones; D3 a D5 y D7 a D9 aprobadas juntas.

- **D1 · Dónde viven los archivos.** En el disco privado de la aplicación (nunca en una carpeta pública), con un
  nombre interno que no es el original. Se descargan solo por una ruta que revisa que la persona pueda ver el registro.
  El respaldo a S3 lo hace el job de respaldos (`docs/operaciones/respaldos.md`). Cambiar a S3 como disco es
  configuración, no código.
- **D2 · Quitar un adjunto.** En un registro editable, quitar deja rastro: el adjunto queda marcado como quitado, con
  quién y cuándo, y el archivo se conserva. En uno no editable no se quita ni se reemplaza; solo se agrega (plan 1.5,
  D8).
- **D3 · Qué se acepta.** Tamaño máximo y extensiones permitidas como parámetros (20 MB; pdf, jpg, jpeg, png, webp,
  xlsx, docx, xml, zip), y se revisa el contenido real del archivo, no solo su extensión. El mismo archivo dos veces en
  el mismo registro se rechaza (por su huella).
- **D4 · Tipos de adjunto.** Catálogo editable por documento (como «Cotización» u «Orden firmada»), con su tamaño y
  extensiones si difieren, si es sensible y si es obligatorio. «Obligatorio» se exige con una condición de transición
  que el módulo pone donde corresponda (`TieneAdjuntosObligatorios`).
- **D5 · Sensibles.** Un adjunto de un tipo sensible (exámenes médicos, sueldos) solo lo ve y descarga quien tiene el
  permiso del grupo sensible correspondiente, y nunca sale a un servicio externo.
- **D6 · Canales de aviso.** En el sistema (la campana de la barra superior) y por correo. Otros canales (WhatsApp)
  después, sin tocar a quien avisa: cada canal es una pieza.
- **D7 · Qué se avisa y a quién.** Cada módulo declara sus avisos en código (clave, título, texto, enlace, si es
  obligatorio) y los manda por `NotificacionesService`. El correo va por cola después del commit, una sola vez por
  clave. Las plantillas editables y la anticipación (DBML `plantillas_notificacion`) llegan con las alertas (1.10).
- **D8 · Lo que elige cada persona.** En «Mis preferencias», por grupo de avisos: correo sí o no. La campana siempre.
  Los obligatorios no se apagan.
- **D9 · Dónde se ve primero.** El componente de adjuntos es reutilizable; se estrena en la ficha de una persona o
  empresa (pestaña «Archivos»: ficha RUC, contratos, certificados). La campana se activa para todos.

## Pasos

- [x] 1. Plan aprobado (D1 a D9).
- [x] 2. Adjuntos: tablas, servicio (agregar, quitar, reemplazar, descargar), reglas de editable, tipos, sensibles.
- [x] 3. Componente de adjuntos y pestaña «Archivos» en la ficha de una party; ruta de descarga protegida.
- [x] 4. Notificaciones: servicio, avisos declarados, campana, correo por cola idempotente, preferencias.
- [x] 5. Documentos, `revisor-arquitectura`, `composer check`, `/cerrar-tarea` y PR.

## Criterio de aceptación

- Un documento no editable acepta un adjunto nuevo y rechaza quitar o reemplazar uno que ya tenía.
- Un archivo con extensión `.pdf` que no es un PDF se rechaza; uno de 21 MB también.
- Quien no puede ver el registro no descarga su adjunto; un sensible sin su permiso, tampoco.
- Un aviso con correo apagado llega solo a la campana; uno obligatorio llega igual; un reintento no duplica el correo.

## Bitácora

- 2026-10-26 — Paso 1. Plan aprobado: archivos en disco privado del servidor (respaldo a S3), quitar deja rastro,
  campana y correo (WhatsApp preparado para después).
- 2026-10-26 — Pasos 2 y 3. Íconos elegidos por el desarrollador: clip (catálogo «Tipos de adjunto», grupo nuevo
  «Documentos y archivos»), carpeta abierta (pestaña «Archivos») y campana (avisos). Tablas `tipos_adjunto` y
  `adjuntos` (desviaciones del DBML en la migración: `grupo_sensible` en vez de solo `es_sensible`, nulos que toman los
  parámetros, quitar con rastro, reemplazo enlazado, huella única entre los vigentes de un registro). Disco `adjuntos`
  privado (`config/filesystems.php`). Parámetros `adjuntos.tamano_maximo_mb` (20) y `adjuntos.extensiones`. Un registro
  que lleva adjuntos implementa `ConAdjuntos` (quién ve y quién cambia); `Party` y el documento de prueba lo hacen.
  `AdjuntosService` revisa extensión, contenido real (`finfo`), tamaño y huella; nunca acepta php, exe, js, html, svg…
  aunque un parámetro los permita; si la base rechaza, borra el archivo escrito. Campo nuevo de acción
  `Campo::archivo` → `ArchivoSubido` (desde la pantalla, el archivo subido; sin interfaz, ruta y nombre). Descarga solo
  por `filament.app.adjuntos.descargar` (con sesión, revisa permisos y sensibles; siempre como descarga, `nosniff`).
  Condición `TieneAdjuntosObligatorios`. Componente reutilizable `ArchivosDeRegistro`, estrenado en la ficha de la
  party. `AdjuntosTest` (10).
- 2026-10-26 — Paso 4. Tabla `notificaciones` con la forma de las de Laravel (desviaciones del DBML en la migración):
  `User::notifications()` apunta a ella (`Notificacion extends DatabaseNotification`, llave entera) y la campana de
  Filament (`databaseNotifications()`, cada 30 s) la lee sin otra tabla. `RegistroAvisos` (grupos y avisos que
  declaran los módulos; cada grupo suma `avisos.correo_<grupo>` en «Mis preferencias», encendida). `NotificacionesService::avisar`
  inserta con `insertOrIgnore` sobre la clave única por persona (un reintento no repite) y, si toca correo, despacha
  `EnviarCorreoDeAviso` después del commit, que bloquea la fila y no manda dos veces (Message-ID estable). Sistema e
  inactivos no reciben. `NotificacionesTest` (7). Documentos: `codigo.md` («Adjuntos y avisos»), `interfaz.md`
  («Archivos y campana»), `comun.md` M7 y M8, Común 0.3.13, mapa.
- 2026-10-26 — Revisión de `revisor-arquitectura`: tres bloqueantes atendidos. (1) El correo salía dentro de una
  transacción: ahora sigue el patrón de los efectos externos (reserva `correo_reservado_hasta` en una transacción
  corta, envío fuera, confirmación o liberación en otra; `failed()` lo registra por id; el aviso al responsable, 1.10).
  (2) Desde la pantalla se podía cambiar el archivo por una ruta del servidor: `AccionFilament` exige un archivo subido
  en los campos de archivo, y `AdjuntosService` solo toma rutas de las carpetas temporales de subida (nunca del disco de
  adjuntos). (3) Carrera con una transición: quitar y reemplazar bloquean la fila del registro y revisan si se edita
  dentro del bloqueo. Observaciones atendidas: quitar o reemplazar un sensible exige poder verlo; uno quitado se sigue
  descargando a propósito (D2, probado); la unicidad de los avisos incluye su tipo; regla de no llevar datos sensibles
  en avisos (`codigo.md`); si la escritura falla a mitad no queda el archivo; más extensiones prohibidas; el nombre se
  guarda limpio; la pestaña ya no consulta por fila; las pruebas borran sus temporales y prueban el rollback del aviso
  y la reserva del correo. Queda anotado: si un módulo llama dentro de su propia transacción y la deshace, el archivo
  queda en el disco; lo limpiará la revisión de huérfanos de la página de salud (1.12).
- 2026-10-26 — Paso 5. `composer check` en verde (1628 pruebas, 12,4 min). Plan terminado. Sigue la 1.9 (imputación y
  centros de costo).
