# Política de respaldos

| Qué | Frecuencia | Dónde | Cómo |
|---|---|---|---|
| Base de datos (volcado completo) | **Semanal** durante el desarrollo; **diario** antes de cargar datos reales | Bucket S3 (fuera del servidor) | Job programado de Laravel (`spatie/laravel-backup`) |
| Archivos adjuntos | Igual que la base | Bucket S3 | El mismo job, o el bucket como disco de Laravel con versionado |
| Copia de largo plazo | Mensual | Bucket S3 | Retención escalonada: diarios unas semanas, semanales unos meses, mensuales un año o más |

Condiciones:

- La frecuencia es un **parámetro de configuración**, no está escrita en el código.
- El job solo puede **escribir** en el bucket, no borrar. Bucket con versionado u Object Lock.
- Los volcados van **cifrados** (contienen datos de clientes y colaboradores).
- Si el respaldo falla, avisa al responsable (ver `monitoreo.md`).
- **Antes de cargar datos reales:** pasar a frecuencia diaria y aprobar una restauración en otra
  máquina (`restauracion.md`). Es criterio de salida de esa etapa.
- La región del bucket se confirma con Asistencia Legal (transferencia de datos personales, Ley 29733).
- Los plazos legales de conservación de comprobantes se confirman con Contabilidad.

## Cómo está hecho (plan 1.12)

- **Comando:** `php artisan nucleo:respaldar`. El programador lo corre cada noche a las 02:00 (Lima) y el parámetro
  `respaldos.frecuencia` (`semanal` de fábrica, o `diaria`) decide si ya toca. `--forzar` respalda igual: para TI,
  antes de una actualización.
- **Qué:** la base (`pg_dump`) y la carpeta de adjuntos, incluida su cuarentena, en un zip cifrado (AES-256) con
  `BACKUP_ARCHIVE_PASSWORD`. Sin esa clave **no hay respaldo**: el comando falla y avisa, nunca sale uno sin cifrar.
  La imagen necesita `postgresql-client` 16 o mayor (igual o más nuevo que el servidor).
- **Clave de cifrado:** la genera TI y la guarda **fuera del servidor** (por ejemplo, en el gestor de contraseñas de
  TI). Si se pierde con el servidor, los respaldos no se pueden abrir.
- **Destino:** `RESPALDOS_DISCO`. De fábrica, `respaldos`, una carpeta del mismo servidor
  (`storage/app/private/respaldos`): la página de salud lo muestra en **amarillo** («Respaldo solo local: no protege
  ante pérdida del servidor»). Con el bucket de TI: `RESPALDOS_DISCO=respaldos_s3` y las variables `RESPALDOS_S3_*`,
  sin tocar código (el paquete `league/flysystem-aws-s3-v3` ya está, desde la 1.12b). La región del bucket sigue
  pendiente con Asistencia Legal (datos personales fuera del país).
- **Limpieza de respaldos viejos:** solo con destino local (retención de la librería: 7 días todos, luego diarios,
  semanales, mensuales y anuales; nunca borra el más nuevo). **En S3 queda apagada**, porque el job solo puede escribir:
  la retención escalonada la hace TI con las reglas de ciclo de vida del bucket.
- **Avisos** (al administrador, en la campana y por correo): `nucleo.respaldo-fallido`, una vez al día si un respaldo
  falla; `nucleo.respaldo-vencido`, en la revisión diaria de las 07:00, si el último respaldo correcto es más viejo
  que la frecuencia más un día.
- **Página de salud:** fecha, tamaño y destino del último respaldo, si falló, y la última prueba de restauración
  (parámetro `respaldos.ultima_prueba_restauracion`, con quién lo registró). Verde solo con destino externo y un
  respaldo reciente.
- **Historial:** cada corrida queda en `salud_ejecuciones` durante `salud.dias_historial` días (400 de fábrica).
- **La prueba de restauración** (`restauracion.md`) no la hace el sistema: sigue siendo condición antes de cargar
  datos reales. Al aprobarla, se anota su fecha en el parámetro.

## Al pasar a S3, revisar

- Hoy **cada respaldo copia todos los adjuntos completos**. Mientras sean pocos no importa; cuando crezcan, cada
  respaldo repetiría gigas. La alternativa que ya prevé la tabla de arriba: el bucket como disco de los adjuntos, con
  versionado, y el respaldo solo de la base. Decidirlo al pasar a S3 (anotado el 2026-10-29; no se cambia antes).

## Archivo histórico (plan 1.4y)

- Lo que las limpiezas sacan de la base (los avisos leídos hace más de `avisos.dias_leidos` días, las corridas viejas de
  la página de salud, las mediciones de Pulse de más de 7 días) y los archivos que vencen en la cuarentena de huérfanos
  (se leen con `nucleo:leer-historico <archivo> --guardar=<ruta>`) quedan antes en
  el **archivo histórico**: el disco `historico` (`storage/app/private/historico`; a S3 con `HISTORICO_DISCO`), cifrado y
  **sin plazo**. Esas filas ya no están en la base: **el histórico tiene que estar dentro de los respaldos** mientras siga
  en el servidor: el respaldo incluye su carpeta, junto con la de los adjuntos (plan 1.12, `config/backup.php`).
- **`BACKUP_ARCHIVE_PASSWORD` cifra también el histórico.** Si TI la cambia, los archivos anteriores siguen cifrados con
  la vieja: TI guarda también las anteriores, fuera del servidor, y los lee con
  `php artisan nucleo:leer-historico <archivo> --otra-clave`.
- `--guardar=<ruta>` deja el archivo **descifrado** donde diga TI: guardarlo fuera de carpetas compartidas y borrarlo al
  terminar.
- **Si un respaldo falla**, spatie/laravel-backup escribe en el registro de errores la línea
  `[backup] Backup failed because: …` con el mensaje crudo de `pg_dump`, que puede traer el usuario y el servidor de la
  base (nunca la contraseña, que va por variable de entorno). La alerta y la página de salud solo dicen que falló; el
  detalle está ahí. Por eso el registro de errores solo lo lee TI.
