# Plan: página de salud, respaldos, huérfanos y prueba de carga (1.12)

- **Estado:** completado (2026-10-30)
- **Módulo(s):** Nucleo y operaciones
- **Fase:** F1
- **Creado:** 2026-10-29 · **Actualizado:** 2026-10-29

## Objetivo

Que Desarrollo y TI sepan, desde una sola pantalla, si el sistema está sano: programador, cola, respaldos,
archivos huérfanos e IA. Que existan los respaldos de verdad, que los huérfanos de adjuntos se limpien sin riesgo
y que haya un script de carga listo para correrlo en un servidor de prueba.

## Contexto que leí

- `docs/operaciones/monitoreo.md`: cuatro capas; la página «Salud del sistema» muestra la última ejecución del
  programador y de cada alerta, jobs fallidos y en cola, último respaldo y última prueba de restauración,
  exportaciones contables fallidas (no existen todavía) y el uso de cada proceso de IA frente a su tope. Latido del
  programador hacia Healthchecks. Errores y rendimiento con Laravel Pulse.
- `docs/operaciones/respaldos.md`: base y adjuntos con `spatie/laravel-backup`, cifrados; frecuencia como
  parámetro (semanal en desarrollo, diaria antes de datos reales); el job solo escribe en el bucket; aviso si falla.
  `restauracion.md`: la prueba de restauración es condición antes de cargar datos reales.
- `spatie/laravel-backup` está instalado pero sin programar ni configurar (`config/backup.php` de fábrica). No hay
  `pg_dump` en el entorno de desarrollo: PostgreSQL corre en Docker.
- Programador: `NucleoServiceProvider::programarTareas()` (tipo de cambio y `RevisarAlertas` a las 07:00).
  Parámetros: `DefinicionParametro` y `ParametrosService`. Alertas: `DefinicionAlerta` y `AlertasService::emitir`.
- Plan 1.8: un módulo que adjunta dentro de su transacción y la deshace deja el archivo en el disco `adjuntos`.
- Estimación de capacidad del 2026-10-29: 200 a 400 personas a la vez con 4 núcleos, sin carga real medida.
- `laravel/pulse` 1.8.1 (MIT, de Laravel): admite Laravel 13 y Livewire 4.
- Respuestas del desarrollador del 2026-10-29 sobre respaldos, prueba de carga y huérfanos: son las decisiones
  D3 a D5.

## Decisiones

- **D1. Registro de ejecuciones:** tabla `salud_ejecuciones` (tarea, inicio, fin, resultado, datos `jsonb` sin datos
  sensibles). Ahí anotan su corrida el latido del programador, los respaldos y la revisión de huérfanos. La página
  la lee: lo que importa queda en la base, no en la caché.
- **D2. Página extensible:** la página arma sus recuadros desde un registro de comprobaciones de salud
  (`RegistroSalud`). El núcleo trae las suyas; cada módulo registrará las propias, como las exportaciones contables
  de la etapa 4, sin tocar la página. Permiso propio, `nucleo.ver-salud`, que nace con el administrador.
- **D3. Respaldos** (desarrollador):
  - Base y adjuntos con `spatie/laravel-backup`, cifrados con una clave del `.env` (`BACKUP_ARCHIVE_PASSWORD`).
  - Disco de destino por configuración (`RESPALDOS_DISCO`): local por ahora; S3 sin tocar código.
  - Frecuencia como parámetro (`respaldos.frecuencia`: semanal o diaria; semanal de fábrica). El programador lo
    revisa a diario y el comando decide si ya toca.
  - Si un respaldo falla, aviso al responsable. Si el último es más viejo que la frecuencia más un margen, otra
    alerta.
  - En la página: fecha, tamaño y si falló. Con destino local, **amarillo**: «Respaldo solo local: no protege ante
    pérdida del servidor». Verde solo con destino externo y un respaldo reciente.
  - La limpieza de respaldos viejos (`backup:clean`) corre solo con destino local. En S3 queda apagada, porque el
    job solo escribe: la retención la hace TI con las reglas de ciclo de vida del bucket.
  - TI guarda la clave de cifrado fuera del servidor (`respaldos.md`, `responsabilidades.md`).
  - La prueba de restauración no entra en esta tarea. La página muestra su fecha desde un parámetro que se edita
    al hacerla (`respaldos.ultima_prueba_restauracion`): vacío, «sin probar». Muestra también quién la registró,
  según la auditoría del parámetro (pedido del desarrollador).
- **D4. Archivos huérfanos de adjuntos** (desarrollador):
  - Una tarea semanal busca los archivos del disco de adjuntos sin fila en `adjuntos` (comparando ruta y disco) y
    con más de 24 horas, y los **mueve** a `cuarentena/` en el mismo disco, sin borrarlos.
  - La misma tarea borra lo que lleva más de N días en cuarentena (`adjuntos.dias_cuarentena`, 30 de fábrica).
  - Si los huérfanos de una corrida pasan un tope (`adjuntos.tope_huerfanos`, 50 de fábrica), no mueve nada y deja
    una alerta: es más probable un error o una restauración que huérfanos de verdad.
  - El comando tiene `--simular`: lista lo que haría sin mover ni borrar.
  - En la página: los movidos y borrados en la última corrida, cuánto ocupa la cuarentena y la fecha.
  - `restauracion.md`: al restaurar, pausar la tarea; cómo devolver un archivo desde la cuarentena.
- **D5. Prueba de carga** (desarrollador):
  - Script en PHP, `scripts/prueba-carga.php`: simula N personas con sesión iniciada recorriendo pantallas, con
    N por parámetro. Se prueba contra el local solo con pocas personas, y esos tiempos no son resultado.
  - Además, por cada pantalla del recorrido: consultas SQL y tiempo de base, en local y con una persona. Ese
    resultado va a la bitácora, con las pantallas que se salgan de lo razonable y una propuesta para cada una. Un
    arreglo grande no se hace sin preguntar.
  - La corrida real con 50, 100 y 200 personas la hace TI en un servidor de prueba igual al de producción.
    `docs/operaciones/prueba-de-carga.md` explica cómo correrla y cómo leer los números.
- **D6. Pulse** (corregido por el desarrollador el 2026-10-29: con su configuración de fábrica **sí** guarda datos
  personales). `laravel/pulse` muestra errores, pedidos lentos, consultas lentas y colas en `/pulse`, solo con
  `nucleo.ver-salud` y enlazado desde la página. Sus tablas van en la misma base.
  - **Uso por persona apagado:** sin los registradores de pedidos y jobs por usuario ni su tarjeta, y sin resolver
    usuarios. Así no guarda nombre ni correo, ni pide avatares a Gravatar (eso manda el hash del correo afuera).
  - **Excepciones:** solo la clase y el lugar, nunca el mensaje (regla 10). Se revisa en el código de Pulse; si
    guardara el mensaje, se filtra.
  - **Pedidos lentos:** la ruta sin la cadena de consulta, para que una búsqueda por nombre no quede guardada.
  - **Servidores apagado:** su tarjeta necesita `pulse:check` corriendo siempre, y la capa del servidor es de TI
    (`monitoreo.md`). Si un día se enciende, va en `requisitos-servidor.md` y en la 1.12b.
  - **Limpieza:** Pulse borra lo que pasa de 7 días (`trim.keep`), para que sus tablas no crezcan sin límite.
  - **Prueba:** después de pedidos, jobs y excepciones con datos de una persona inventada, las tablas de Pulse no
    tienen nombres, correos ni cadenas de consulta.
  - **Compatibilidad**, comprobada antes de agregarla (2026-10-29): `composer require laravel/pulse:^1.8 --dry-run`
    resuelve la 1.8.1 sin cambiar nada de lo instalado (Livewire sigue en 4.4.8; Laravel 13 y Filament 5 sin
    cambios). Suma `laravel/sentinel` y `doctrine/sql-formatter`. `composer audit` sin advertencias.
- **D7. Latido:** el programador anota su latido cada 5 minutos en `salud_ejecuciones` y, si existe
  `SALUD_LATIDO_URL` (Healthchecks, la configura TI), además lo envía ahí.

## Pasos

- [x] 1. `salud_ejecuciones`, el latido y el registro de comprobaciones de salud, con pruebas.
- [x] 2. Respaldos (D3): configuración, comando, alertas, documentos y pruebas sin escribir afuera.
- [x] 3. Huérfanos (D4): comando con cuarentena, tope y `--simular`, programado; documentos y pruebas.
- [x] 4. Pulse (D6), con su permiso.
- [x] 5. Página «Salud del sistema» (D2) con sus recuadros y pruebas.
- [x] 6. Prueba de carga (D5): script, medición por pantalla en la bitácora y `prueba-de-carga.md`.
- [ ] 7. Revisión de arquitectura y `composer check` en verde.

## Criterio de aceptación

- Respaldos: con destino local, la página no muestra verde; un fallo emite el aviso; un respaldo vencido dispara la
  alerta.
- Huérfanos: un archivo con fila nunca se mueve; un huérfano de menos de 24 horas no se mueve; lo que pasó los días
  de cuarentena se borra; el tope detiene la corrida; `--simular` no cambia nada.
- Ninguna prueba escribe un respaldo en un servicio externo.

## Bitácora

- 2026-10-29 — Plan escrito con las respuestas del desarrollador sobre respaldos, prueba de carga y huérfanos. Rama
  `pagina-salud` desde `main` (ya con la 1.11c). En el mapa: la fila 1.12 actualizada y la 1.12b nueva (compose e
  imagen de producción).
- 2026-10-29 — Correcciones del desarrollador antes del paso 1: Pulse guarda datos personales con su configuración
  de fábrica (D6 reescrita), la prueba de restauración con quién la registró, y `postgresql-client` 16 o mayor en la
  1.12b.
- 2026-10-29 — Paso 1. Migración `2026_10_29_000001_crear_salud_ejecuciones` (tabla técnica, fuera del DBML), con
  CHECK de tarea, resultado, término coherente y `datos` siempre objeto (atrapó un error real: un arreglo vacío de
  PHP se guardaba como `[]`; el modelo ahora guarda siempre un objeto). `SaludService` (`registrar`, `ultima`,
  `olvidarAntesDe`): una corrida fallida guarda solo la clase del error. `RegistroSalud` y `ComprobacionSalud` en
  `Nucleo/Salud`. `nucleo:latido` cada 5 minutos, con Healthchecks si existe `SALUD_LATIDO_URL` y una semana de
  historia; recuadro «Programador» (falla tras 15 minutos sin latido). `SaludTest`, 14 pruebas.
- 2026-10-29 — Paso 2. `Nucleo/Respaldos`: `Respaldador` (interfaz; `RespaldadorSpatie` en producción, uno falso en
  las pruebas: ninguna escribe un respaldo), `RespaldosService` (`respaldarSiToca`, vencido con un día de margen,
  destino local), `RespaldosVencidos` (fuente de la revisión diaria), `nucleo:respaldar {--forzar}` a las 02:00.
  Sin `BACKUP_ARCHIVE_PASSWORD` no respalda (nunca sin cifrar). Alertas `nucleo.respaldo-fallido` y
  `nucleo.respaldo-vencido`. Parámetros `respaldos.frecuencia`, `respaldos.ultima_prueba_restauracion` (con quién
  la registró, por `updated_by` del parámetro) y `salud.dias_historial` (400). Recuadro «Respaldos»: amarillo con
  destino local, verde solo externo y reciente. `config/backup.php`: nombre `techquk`, solo adjuntos y `pgsql`, disco
  `RESPALDOS_DISCO`, sin avisos de la librería. Discos `respaldos` y `respaldos_s3` en `config/filesystems.php`.
  **Registro de corridas acotado** (pedido del desarrollador): el latido limpia de paso una semana de latidos y
  `salud.dias_historial` de lo demás, sin tocar lo que sigue en curso. Documentos: `respaldos.md`,
  `responsabilidades.md` (clave fuera del servidor, URL de Healthchecks), `requisitos-servidor.md` (Healthchecks
  en la salida a internet y `pg_dump`), `.env.example`. `RespaldosTest` (13) y `SaludTest`; en `AlertasTest`, dos
  pruebas cuentan solo su alerta (la revisión diaria ahora también revisa respaldos). **Abierto:** para usar S3 falta
  el paquete `league/flysystem-aws-s3-v3`; se agrega cuando TI tenga el bucket (o en la 1.12b).
- 2026-10-29 — Notas del desarrollador sobre lo abierto del paso 2: `league/flysystem-aws-s3-v3` va en la 1.12b
  (mapa y `respaldos.md`); en `respaldos.md`, revisar al pasar a S3 que cada respaldo copia todos los adjuntos (la
  alternativa: bucket como disco de adjuntos con versionado). Sin cambios de código.
- 2026-10-29 — Paso 3. `HuerfanosAdjuntosService`: busca archivos sin fila en `adjuntos` (mismo disco y ruta, en
  lotes de 500) con más de 24 horas, los mueve a `cuarentena/AAAA-MM-DD/<ruta>` (la fecha dice cuándo vence; para
  devolverlo, se quita el prefijo) y borra los de más de `adjuntos.dias_cuarentena` días. Si pasan
  `adjuntos.tope_huerfanos`, no mueve ninguno y avisa (`nucleo.huerfanos-demasiados`). `nucleo:revisar-huerfanos
  {--simular}` cada domingo a las 04:00 (después del respaldo de las 02:00); `--simular` no mueve, no borra, no avisa
  ni anota la corrida. En el registro de corridas, solo cantidades y bytes (nunca las rutas). Se pausa con
  `adjuntos.revision_huerfanos_activa` (agregado: lo pide `restauracion.md` y hacía falta un interruptor). Recuadro
  «Archivos huérfanos»: falla si se detuvo, si falló o si pasan 15 días sin correr; amarillo si está pausada.
  `restauracion.md`: pausar antes de restaurar y cómo devolver un archivo. `HuerfanosAdjuntosTest` (9).
- 2026-10-29 — Agregado del desarrollador al paso 3: una pausa de más de `adjuntos.dias_pausa_huerfanos` días (14 de
  fábrica, 1 a 90) pasa de amarillo a rojo. Desde cuándo: el `updated_at` del parámetro. Con su prueba.
- 2026-10-29 — Paso 4. `laravel/pulse` 1.8.1 instalado (con `laravel/sentinel` y `doctrine/sql-formatter`; Livewire
  sigue en 4.4.8; `composer audit` sin advertencias). Revisado en su código: errores guardan clase y lugar (sin
  mensaje); pedidos lentos, el patrón de la ruta (sin cadena de consulta); consultas, el SQL sin valores; llamadas
  externas, la URL entera (la del latido lleva un código secreto) → agrupadas al dominio. Uso por persona, servidores y
  caché apagados, y su tablero publicado sin esas tarjetas. `PulseSinPersonas` reemplaza a su `ResolvesUsers`: Pulse
  nunca sabe quién hizo un pedido. `trim.keep` fijo en 7 días. Puerta `viewPulse` con el permiso **`salud.ver`**
  (cambio de nombre respecto de D2: sigue la forma de los demás permisos de pantalla, como `usuarios.ver`), en
  `PermisosSeeder::DE_LECTURA` y para el administrador. Migración de Pulse en `database/migrations` (tablas del
  paquete). `PulseTest` (3): enciende Pulse con su configuración real (en `phpunit.xml` está apagado) y revisa que no
  quede nombre, correo, mensaje, cadena de consulta ni código del latido, y que sí quede lo que debe. `monitoreo.md`.
- 2026-10-30 — Retomada tras la 1.4x, 1.4y y 1.4z. `main` traído: conflictos resueltos conservando las dos partes
  (alertas, comandos y tareas programadas, parámetros, discos `respaldos`, `respaldos_s3` e `historico`, documentos de
  respaldos y restauración, mapa). El respaldo ahora incluye la carpeta del **archivo histórico** (sus filas ya no están
  en la base). Dos íconos de alerta chocaban con los de `main` (la regla: uno distinto por alerta, lo vigila
  `PantallaAlertasTest`): «Respaldo vencido» pasa a `heroicon-o-calendar-date-range` y «Demasiados archivos huérfanos» a
  `heroicon-o-document-duplicate`. Pendiente al seguir: las limpiezas de esta tarea (corridas, latidos, Pulse, cuarentena)
  pasan por el archivo histórico, y el paso 5 (la página).
- 2026-10-30 — Limpiezas al archivo histórico (decisión del desarrollador del 2026-10-29). Corridas de la página de salud:
  `olvidarViejas()` archiva (el histórico llega como parámetro: regla de `AGENTS.md`); el latido se anota igual si el
  histórico falla (sin clave), y lo reintenta en el próximo. Cuarentena de huérfanos: lo vencido pasa entero al histórico
  (`ArchivoHistoricoService::archivarArchivo`, comprimido, cifrado y comprobado) y recién entonces sale del disco;
  `nucleo:leer-historico --guardar` lo devuelve descifrado. Pulse: sin borrado al azar (`ingest.trim.lottery` en 0);
  `nucleo:archivar-pulse` (03:45) archiva `pulse_entries` y `pulse_values` de más de 7 días y después recorta los
  resúmenes (se calculan de las mediciones). `monitoreo.md` y `respaldos.md`. Pruebas: `ArchivarPulseTest` (4), y las de
  salud, respaldos y huérfanos con el histórico (72 en verde). El commit del código salió sin estos documentos: la
  edición falló y el commit iba en una línea aparte, no encadenado con `&&` (la regla de `AGENTS.md`); se completa aquí.
- 2026-10-30 — Paso 5. `Filament/Pages/SaludDelSistema` (Configuración → Salud del sistema, `/configuracion/salud`,
  solo con `salud.ver`): un recuadro por comprobación de `RegistroSalud`, con su estado escrito («Bien», «Atender»,
  «Falla», «Sin datos») y un borde de color; arriba, el enlace a Pulse. Las comprobaciones se revisan antes de dibujar
  la vista; una que falla al revisarse se muestra como falla, sin tumbar la pantalla ni mostrar su mensaje. Nuevas:
  `ComprobacionCola` (en espera por cola y desde cuándo; falla si uno espera más de 15 minutos o falló en 24 horas;
  atender si falló en la semana) y `ComprobacionIa` (gasto del mes frente al tope por proceso encendido; atender desde
  el 80 %, falla al tope). Probado en la app del desarrollador: muestra su entorno tal cual (sin programador corriendo,
  el respaldo de ayer sin clave, un trabajo esperando porque no corre el worker). `PantallaSaludTest` (6).
- 2026-10-30 — Paso 6. `scripts/prueba-carga.php`, dos modos. `carga`: inicia sesión como el navegador (el formulario
  es un componente de Livewire: su estado sale de la página, se le mandan los datos y se llama a `authenticate`) y
  recorre al azar 13 pantallas con N personas a la vez (`curl_multi`), con pausa al azar; por pantalla, pedidos, errores,
  mediana, p95 y máximo. Probado contra el local con 2 personas: sesión iniciada, 0 errores (esos tiempos no cuentan).
  `consultas`: dentro de la app, con una persona, consultas y tiempo de base por pantalla, deshaciendo todo.
  `docs/operaciones/prueba-de-carga.md`: cómo la corre TI en el servidor de prueba (50, 100 y 200) y cómo leerla.
  **Resultado de `consultas` (2026-10-30, base de desarrollo):**

  | Pantalla | Consultas | Base |
  |---|---|---|
  | / | 17 | 19,0 ms |
  | /mien | 15 | 13,2 ms |
  | /personas | 39 | 35,5 ms |
  | /seguridad/usuarios | 18 | 17,3 ms |
  | /seguridad/organigrama | 16 | 16,3 ms |
  | /configuracion/catalogos | 15 | 15,4 ms |
  | /configuracion/parametros | 17 | 14,9 ms |
  | **/configuracion/alertas** | **51** | 37,6 ms |
  | /configuracion/salud | 23 | 22,4 ms |
  | /mis-avisos | 16 | 17,7 ms |
  | /mis-datos | 24 | 23,0 ms |
  | /mis-preferencias | 19 | 18,2 ms |

  **Marcada: Alertas (51 consultas).** Es N+1: por cada alerta (12) busca su tipo, sus roles y cuenta a quién le llega,
  por separado (36 consultas), y crecerá con cada módulo que sume alertas. Propuesta: cargar los tipos con sus roles de
  una vez y contar a quién llega cada alerta en una sola consulta agrupada (quedaría en unas 18). **A consultar con el
  desarrollador antes de arreglarlo.** Menor, en todas las pantallas: `modulos_ocultos` de la persona se lee 5 veces por
  pedido (se puede guardar la primera). /personas está cerca del límite (39): vigilar cuando crezca.
- 2026-10-30 — Arreglos de la prueba de carga (aprobados por el desarrollador). Alertas: `AlertasService::sinDestinatariosPropiosDe()`
  resuelve todas de una vez (tipos con sus roles y, en una consulta, qué roles tienen a alguien activo): de 51 a 13
  consultas; prueba de que da lo mismo que alerta por alerta y en 3 consultas como mucho. Módulos ocultos: `OcultosDelPedido`
  (scoped, sin otras dependencias) los lee una vez por pedido y `NavegacionService` lo olvida al guardar: cinco
  consultas menos en cada pantalla. **Tropiezo:** primero se hizo `scoped` todo `NavegacionService`, y 30 pruebas
  fallaron porque guardaba la lista de módulos y los permisos de antes de que la prueba los cambiara; el almacén chico no
  guarda nada más. Medición después: ninguna pantalla marcada (Alertas 13, Personas 34, el resto entre 10 y 19).
- 2026-10-30 — `composer check` se detuvo en las migraciones: el verificador (ampliado en `main` a `database/migrations`)
  encontró `char` en la migración publicada de Pulse, en sus ramas de MySQL/MariaDB (en PostgreSQL nunca corrían).
  Queda solo la rama de PostgreSQL (`uuid` calculado de `md5`), sin `char`. Sin cambio en la base. Se relanza la corrida
  completa con la revisión de arquitectura atendida.
- 2026-10-30 — Fuera de la 1.12, a pedido del desarrollador (Techquk usa otra pizarra; dirá cuál): Whiteboard sale del
  panel. Migración `2026_10_30_000002`: desactiva la aplicación (una usada no se borra), le quita su programa y saca
  `whiteboard` de la lista cerrada de programas de escritorio (Microsoft retiró su aplicación de escritorio el
  2026-10-16, y Whiteboard para cuentas personales). La semilla ya no lo crea; Excalidraw queda como pizarra.
- 2026-10-30 — **Revisión de arquitectura** (subagente `revisor-arquitectura`), todo atendido:
  - Bloqueantes: la revisión de huérfanos no toca `fotos-perfil/` (mismo disco, sin fila en `adjuntos`;
    `HuerfanosAdjuntosService::CARPETAS_DE_OTROS`, con su prueba); el error de una comprobación va al registro solo con
    su clase (regla 10).
  - `/pulse` con las exigencias del panel (sesión, clave renovada, gerente general). `nucleo:archivar-pulse` recorta los
    resúmenes con el mismo corte con que archiva, en la conexión de Pulse (antes `Storage::trim()` recalculaba el suyo
    y borraba sin archivar lo de en medio).
  - Corridas colgadas: el latido cierra como fallida la que sigue en curso más de 6 horas; huérfanos en curso se ve en
    amarillo, no «sin pendientes».
  - Candados (`Cache::lock`) en `nucleo:respaldar` y `nucleo:revisar-huerfanos`: una corrida a mano no choca con la
    programada. `AlertasService` pasa a parámetro de método en respaldos y huérfanos (AGENTS.md).
    `ComprobacionHuerfanos` sigue con `SaludService` en el constructor: la interfaz de `revisar()` no recibe parámetros
    y `SaludService` no depende de nada que cierre ciclo.
  - Las comprobaciones se registran con `callAfterResolving`, solo cuando alguien pide el registro; la página arma clave,
    título e ícono dentro del `try` (un ícono que no existe no la tumba).
  - Script de carga: `consultas` solo en local; la clave de `carga` nunca como opción (variable o se pide sin mostrarla).
  - Menores: salida de `nucleo:respaldar` sin el mensaje de un error ajeno; montos de IA con `bcmath`; `--guardar`
    revisa que se guardó y avisa que queda descifrado; ocultos olvidados después de la transacción; comentario de
    `config/nucleo.php` en su sitio; la cola sale «sin datos» si no es de base; íconos de los recuadros únicos en la app
    (señal, flechas en ciclo, gráfico, documento con menos). Documentado en `respaldos.md` lo que spatie escribe en el
    registro cuando un respaldo falla.
- 2026-10-30 — Cierre. `composer check`: pint, phpstan y migraciones en verde; 2239 de 2241 pruebas. Las dos que
  faltaban eran huecos de la tarea: `salud.ver` no estaba en la lista esperada de permisos de `CatalogosSeederTest`, y
  `SaludEjecucion` no tenía alias en el morph map (`salud_ejecucion`). Corregidos y pasados sus archivos. Plan a
  completados; sigue la 1.12b.
