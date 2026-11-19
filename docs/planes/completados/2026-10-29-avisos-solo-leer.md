# Plan: avisos solo para leer y la página «Mis avisos» (1.4y)

- **Estado:** terminado
- **Módulo(s):** Nucleo (M8, avisos)
- **Fase:** F1
- **Creado:** 2026-10-29 · **Actualizado:** 2026-10-29

## Objetivo

Que nadie borre un aviso; que cada aviso se marque como leído, uno por uno o todos; y que exista una página con todos
los avisos de la persona, donde también se encuentran los leídos.

## Contexto que leí

- Pedido del desarrollador del 2026-10-29: en la campana, solo marcar como leído (que desaparezca), uno por uno y uno
  para todos; un lugar con todos los avisos al que se llegue para ver los leídos.
- Respuestas del desarrollador:
  - la campana muestra solo los no leídos, con «Marcar como leído» en cada aviso y «Marcar todos como leídos», sin
    «Borrar» ni la ✕;
  - «Mis avisos» muestra todos, con un filtro, y abre en los leídos;
  - desde «Mis avisos» se puede volver a marcar un aviso como no leído;
  - los leídos se guardan 365 días, como parámetro; los no leídos nunca se borran;
  - se llega desde el pie de la campana y desde el menú de la foto;
  - los obligatorios se marcan como leídos igual que los demás.
- Plan 1.8 (`completados/2026-10-26-nucleo-adjuntos-notificaciones.md`): `Notificacion` extiende la notificación de
  base de datos de Laravel, sobre la tabla `notificaciones`, con id entero; la crea solo `NotificacionesService`; la
  campana es la de Filament (`databaseNotifications`, cada 30 s).
- Filament (`Filament\Notifications\Livewire\DatabaseNotifications`):
  - trae «Marcar todo como leído» y «Borrar» (que borra todos) arriba, y una ✕ por aviso;
  - **sus métodos de un solo aviso** (`markNotificationAsRead`, `removeNotification`) **solo aceptan ids UUID**: con
    nuestros ids enteros no hacen nada, y por eso hoy la ✕ no hace nada;
  - el panel acepta un componente propio (`databaseNotifications(livewireComponent: …)`).
- `docs/convenciones/interfaz.md`, «Archivos y campana».

## Decisiones

- **D1. Campana propia:** `CampanaAvisos` extiende el componente de Filament.
  - Muestra solo los no leídos.
  - Sin «Borrar» ni la ✕: los métodos de borrar no hacen nada, aunque se los llame desde el navegador.
  - Cada aviso tiene «Marcar como leído»; arriba, «Marcar todos como leídos».
  - Al pie, «Ver todos mis avisos», aunque no haya no leídos.
  - Abrir un aviso lo sigue marcando como leído, como hoy.
- **D2. Acciones registradas,** sin permiso (son los avisos propios de cada persona):
  - `nucleo.marcar-aviso-leido`;
  - `nucleo.marcar-avisos-leidos` (todos los suyos no leídos);
  - `nucleo.marcar-aviso-no-leido`.
  - Solo sobre avisos de quien la ejecuta: uno ajeno se rechaza igual que uno que no existe, sin decir de quién es.
- **D3. Página «Mis avisos»:**
  - Una tabla con todos los avisos de la persona: título, texto, fecha y si está leído.
  - Filtro «Leídos / No leídos / Todos», que abre en «Leídos».
  - En cada fila, «Abrir» (si el aviso tiene enlace) y «Marcar como leído» o «Marcar como no leído».
  - Nadie borra avisos.
  - Se llega desde el pie de la campana y desde el menú de la foto, con un ícono que no use otra cosa.
- **D4. Plazo de los leídos:**
  - Parámetro `avisos.dias_leidos`: 365 de fábrica, de 30 a 3650.
  - Una tarea diaria del programador **archiva** (D6) y saca de la base los leídos más viejos que el plazo, contado
    desde que se leyeron.
  - Los no leídos nunca salen de la base.
- **D5. Obligatorios:** se marcan como leídos igual que los demás. «Obligatorio» solo quiere decir que el aviso
  siempre llega.

- **D6. Archivo histórico** (decisión del desarrollador, 2026-10-29: «que se almacene como histórico todo»). Nada de lo
  que se limpia se pierde; es un mecanismo del núcleo que usará toda limpieza:
  - Antes de sacar filas de la base, se escriben en un archivo comprimido y cifrado, por tabla y por mes:
    `historico/<tabla>/<AAAA-MM>/<fecha y hora>-<id único>.jsonl.gz.cifrado`.
  - Cifrado con la clave de los respaldos (`BACKUP_ARCHIVE_PASSWORD`). Sin clave no se archiva, y entonces no se
    borra nada.
  - Disco `historico` (`HISTORICO_DISCO`): hoy en el servidor; S3 cuando TI tenga el bucket, solo cambiando el
    `.env`.
  - **El archivo nunca se borra** ni se sobrescribe: cada corrida escribe uno nuevo.
  - Se borra de la base solo lo que quedó escrito y comprobado (se vuelve a leer, se descifra y se cuentan las filas).
    Si algo falla, no se borra nada y avisa al administrador (`nucleo.historico-fallido`).
  - Para leerlo, `php artisan nucleo:leer-historico <archivo>` (para TI), que lo descifra.
  - Las limpiezas de la 1.12 (corridas, latidos, Pulse, huérfanos) pasan a usarlo al retomarla.
  - Pendiente con Asistencia Legal (Ley 29733): cuánto tiempo se pueden guardar los datos de personas en el histórico.

## Pasos

- [x] 1. Archivo histórico (D6): disco, cifrado, escritura comprobada, aviso si falla y `nucleo:leer-historico`;
  pruebas.
- [x] 2. Acciones y servicio (leer, todos, no leído; solo los suyos) y la limpieza de los leídos con su parámetro,
  archivando; pruebas.
- [x] 3. Campana propia (D1) y sus pruebas.
- [x] 4. Página «Mis avisos» (D3), enlaces y sus pruebas.
- [x] 5. Prueba en la app, documentos (`interfaz.md`), revisión de arquitectura, `composer check` y cierre.

## Criterio de aceptación

- En la campana no se puede borrar ningún aviso; marcar uno lo saca de la campana y aparece en «Mis avisos» (Leídos).
- «Marcar todos» deja la campana vacía y apagada.
- Desde «Mis avisos», «Marcar como no leído» lo devuelve a la campana.
- Nadie marca ni ve los avisos de otra persona.
- La limpieza saca de la base solo los leídos de más de `avisos.dias_leidos` días, y antes los deja en el archivo
  histórico, cifrado y comprobado.
- Si el archivo histórico no se puede escribir, no se borra nada.

## Bitácora

- 2026-10-29 — Plan escrito con las respuestas del desarrollador. Rama `avisos-solo-leer`, apilada sobre
  `interfaz-aplicaciones-principales` (1.4x, terminada y sin fusionar): las dos tocan la campana en `interfaz.md`.
- 2026-10-29 — El desarrollador pidió que todo lo que se limpia quede como histórico: D6, el archivo histórico, se
  construye aquí (los avisos son la primera limpieza) y la 1.12 lo usará al retomarla. Pasos renumerados.
- 2026-10-29 — Paso 1. `Nucleo/Historico`: `CifradoHistorico` (AES-256-GCM con una clave derivada de
  `BACKUP_ARCHIVE_PASSWORD`; marca `TQH1`; un archivo alterado no se descifra), `ArchivoHistoricoService::archivar()`
  (por lotes de 1000 ordenados por id: escribe, vuelve a leer y compara los ids, y recién entonces borra con la misma
  condición; nombre único, nunca sobrescribe) y `leer()`; `HistoricoFallido`. Disco `historico` y
  `nucleo.historico.disco` (`HISTORICO_DISCO`). Aviso `nucleo.historico-fallido` (uno al día por tabla). Comando
  `nucleo:leer-historico`. `ArchivoHistoricoTest` (6): archiva y borra solo lo que cumple, cifrado y sin texto legible;
  lotes y nunca sobrescribe; sin clave ni disco no borra nada; otra clave o archivo alterado no se lee; TI lo lee.
- 2026-10-29 — Paso 2. Acciones `nucleo.marcar-aviso-leido`, `nucleo.marcar-avisos-leidos` y `nucleo.marcar-aviso-no-leido`
  (sin permiso; `NotificacionesService::marcarLeido/marcarTodosLeidos/marcarNoLeido`, solo sobre los suyos: uno ajeno o
  inexistente da el mismo `AvisoRechazado`). Parámetro `avisos.dias_leidos` (365, de 30 a 3650). Comando
  `nucleo:archivar-avisos`, cada día a las 03:30, que pasa al histórico los leídos viejos.
  **Ciclo en el contenedor (la app no arrancaba; lo diagnosticó el desarrollador):** la primera versión puso
  `ArchivoHistoricoService` en el constructor de `NotificacionesService`, y el histórico avisa por `AlertasService`, que
  avisa por `NotificacionesService`: `NotificacionesService → ArchivoHistoricoService → AlertasService →
  NotificacionesService`. Laravel no avisa un ciclo así: arma sin fin hasta agotar la memoria (`artisan --version`
  colgado). Arreglo (propuesta del desarrollador): la limpieza sale de `NotificacionesService` (es mantenimiento, no
  avisar) y va en `ArchivarAvisos`, que recibe `ParametrosService` y `ArchivoHistoricoService` en `handle()`. En el
  `boot()`, `$acciones->registrar()` como el resto del bloque (y el comentario del panel de aplicaciones vuelve junto a su
  `foreach`). **`tests/Arch/ContenedorSinCiclosTest`**: arma con reflexión el grafo de constructores de `app/` y falla
  mostrando la cadena; comprobado metiendo un ciclo a propósito (lo encontró) y quitándolo. `AvisosLeerTest` (9).
- 2026-10-29 — `AGENTS.md`: una dependencia de un solo método va en ese método, no en el constructor (aprobada por el
  desarrollador).
- 2026-10-29 — Pasos 3 y 4. `Filament/CampanaAvisos` extiende la campana **del panel** (`Filament\Livewire\…`; la
  primera versión extendía la del paquete de avisos, que no trae el botón, y la campana desapareció: se vio al probar
  en la app). Solo no leídos; `removeNotification` y `clearNotifications` no hacen nada; «Borrar» oculto; la ✕
  escondida en su vista (`campana-avisos.blade.php`, copia de la de Filament); marcar uno (también «Abrir»), todos y no
  leído por las acciones registradas, aceptando los ids enteros que Filament descartaba; «Marcar como leído» en cada
  aviso; «Ver todos mis avisos» al pie, también vacía. Página `MisAvisos` (`mis-avisos`): tabla de los suyos, filtro
  que abre en «Leídos», «Abrir» (si guardó enlace), «Marcar como leído» y «Marcar como no leído»; en el menú de la foto
  (`heroicon-o-inbox-stack`). Probado en la app del desarrollador (navegador sin pantalla): los tres casos, sin ✕ ni
  «Borrar», vacía, «Mis avisos» y su vuelta a no leído, sin errores; los avisos de prueba se borraron después.
  `CampanaYMisAvisosTest` (8). `interfaz.md`. Visto de paso, fuera de esta tarea: en una tabla ancha el muelle de
  aplicaciones tapa el final de la última columna (flota encima por diseño, 1.4p).
- 2026-10-29 — `composer check` (antes de los ajustes de la revisión): pint y phpstan sin errores, 44 migraciones bien,
  pest 2135/2135.
- 2026-10-29 — Revisión de arquitectura: sin bloqueantes. Atendido: clave del histórico derivada con PBKDF2-SHA256
  (600 000 vueltas) y sal por archivo (marca `TQH2`); `nucleo:leer-historico --otra-clave` para archivos cifrados antes de
  un cambio de `BACKUP_ARCHIVE_PASSWORD` (pide la contraseña sin dejarla en el historial); `respaldos.md` y
  `restauracion.md`: el histórico debe estar en los respaldos y esa contraseña también lo cifra; fila 1.12 del mapa:
  sumar la carpeta del histórico al acotar el respaldo; `ArchivarAvisos` muestra el mensaje solo de `HistoricoFallido`
  (el de otro error puede traer SQL); si avisar falla no tapa el error original; «Mis avisos» muestra los mismos que la
  campana (`data->format = filament`) y atrapa `ValidationException`; índice parcial `notificaciones_leidas_index`
  para la limpieza (migración `2026_10_29_000003`); pruebas de fallas a medias (un lote que falla tras otros, una fila
  que deja de cumplir la condición, una relectura distinta, un borrado que falla) con un disco con gancho y un trigger;
  límites de `ContenedorSinCiclosTest` anotados (no sigue interfaces ligadas ni abstractas). Escrito sin control en
  tiempo de ejecución: `archivar()` va fuera de toda transacción (las pruebas corren dentro de una), la clave única de un
  aviso protege mientras está en la base (por eso lleva fecha o versión), `updated_by` no cambia al marcar leído, y
  dónde iría el plazo del histórico si Asistencia Legal fija uno. 44 pruebas tocadas en verde.
- 2026-10-29 — Cierre. `composer check`: pint y phpstan sin errores, 45 migraciones bien, pest 2140/2140 (10567
  aserciones). Siguiente: la 1.4z (ajustes del panel), apilada encima; luego retomar la 1.12 en su paso 5 (sus
  limpiezas al histórico).
