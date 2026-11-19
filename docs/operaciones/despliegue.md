# Despliegue

> Borrador a completar en la etapa 0 y probar antes del primer despliegue con datos reales.

## Primera instalación

1. TI crea una **deploy key de solo lectura** en el repositorio (ADR 0005) y la instala en el servidor.
2. Clonar el repositorio en el servidor.
3. Crear `.env` de producción a partir de `.env.example` (solo TI conoce sus valores). **Comprobar** que diga
   `APP_ENV=production` y `APP_DEBUG=false`, y que **no** tenga `TECHQUK_MODO_DESARROLLO` (el modo desarrollo, plan
   1.4i: con esas tres cosas no hay forma de activarlo; si la variable apareciera, el sistema la ignora y deja un aviso
   crítico en el registro).
4. Construir y levantar los contenedores de producción (plan 1.12b; `docker/produccion/`), desde la raíz del
   repositorio. El `.env` del paso 3 va en la raíz: el compose lo lee para los contenedores y para la base.
   ```bash
   docker compose --env-file .env -f docker/produccion/compose.yml up -d --build
   docker compose --env-file .env -f docker/produccion/compose.yml ps   # app, web y postgres «healthy»
   ```
   Son seis: `app` (PHP-FPM), `web` (nginx, en el puerto `WEB_PUERTO`, 80 de fábrica), `cola`, `cola-ia`, `programador`
   y `postgres`. Las colas y el programador se reinician solos hasta que el paso 5 crea las tablas: es normal.
   **Solo HTTP:** el certificado y el dominio siguen pendientes con TI (`docs/decisiones/pendientes.md`, «Acceso
   externo»); van en su proxy delante de `web`, o se suman a la imagen cuando se decidan.
5. Ejecutar migraciones (crean el usuario de sistema) y `php artisan db:seed --force` (datos maestros del
   núcleo: catálogos, parámetros, permisos y ubigeos). En producción no crea usuarios de prueba. Cada comando de
   `artisan` de esta guía corre dentro del contenedor `app`:
   ```bash
   docker compose --env-file .env -f docker/produccion/compose.yml exec app php artisan migrate --force
   docker compose --env-file .env -f docker/produccion/compose.yml exec app php artisan db:seed --force
   ```
6. Registrar las dos cuentas de inicio, de dos personas distintas: `php artisan seguridad:primer-administrador` y
   `php artisan seguridad:gerente-general` (piden los datos y la clave en pantalla; con `exec app`, como en el paso 5). Sin gerente general activo el
   sistema no arranca: muestra «Falta registrar al gerente general» (plan 1.10, D2).
7. Verificar la página de salud del sistema y el latido del programador.

## Actualizar a una versión nueva

1. Desarrollo entrega la versión (etiqueta en el repositorio) con sus notas: cambios, migraciones nuevas
   y cómo volver atrás.
2. TI toma un respaldo de la base antes de actualizar.
3. Descargar la etiqueta y, **en este orden** (el código nuevo nunca corre contra la base vieja: las colas procesarían
   trabajos con el esquema de antes):
   ```bash
   C="docker compose --env-file .env -f docker/produccion/compose.yml"
   $C build                                                     # la imagen nueva; lo que corre sigue igual
   $C exec app php artisan down                                 # aviso de mantenimiento para todos
   $C run --rm app php artisan migrate --force                  # con la imagen nueva
   $C run --rm app php artisan db:seed --class='App\Modules\Nucleo\database\seeders\NucleoSeeder' --force
   $C up -d                                                     # los seis contenedores con la versión nueva
   $C exec app php artisan up
   ```
   La semilla crea parámetros, permisos y elementos de catálogo nuevos; no pisa lo editado. `up -d` reinicia los dos
   workers (el de siempre y el de la IA) y el programador; este espera hasta 30 minutos a que termine un respaldo en
   curso.
4. Verificar la página de salud y que el `.env` siga sin `TECHQUK_MODO_DESARROLLO` (ver «Primera instalación», paso 3).

**Un cambio en el `.env`** (una clave, el correo, el bucket) se aplica con `$C up -d`, que recrea los contenedores: un
`restart` no lo toma, porque el `.env` se lee al crearlos y la configuración queda en caché.

**Si TI pone un proxy delante de `web`** (para HTTPS, «Primera instalación», paso 4), hay que avisar a Desarrollo
**antes** de abrirlo: Laravel tiene que confiar en ese proxy (`trustProxies`). Sin eso ve la IP del proxy y `http`: el
límite de intentos de ingreso lo compartiría toda la empresa, la auditoría guardaría la IP del proxy y las subidas de
archivos fallarían.

**Al actualizar PostgreSQL** (no la aplicación): el CHECK `party_sedes_nombre_comparable_check` usa `unaccent`, que
depende de su diccionario. Si la versión nueva cambiara cómo quita los acentos, las sedes ya guardadas no se
revisan solas, pero editar una sede con acentos podría fallar. Después de actualizar, revisar que ninguna fila
incumpla: `SELECT id FROM party_sedes WHERE nombre_comparable <> lower(unaccent(nombre));` debe salir vacío; si no,
avisar a Desarrollo antes de abrir el sistema (plan 1.3b).

## Volver atrás

Volver a la etiqueta anterior. Si la versión nueva tenía migraciones, restaurar el respaldo tomado en el
paso 2 (`restauracion.md`). Desde el primer despliegue con datos reales existe `.claude/PRODUCCION`
(ADR 0010).

## Ubigeos

La lista de distritos se actualiza desde la aplicación (Configuración → Catálogos → Ubigeos →
«Buscar actualización» / «Aplicar actualización», permiso `ubigeos.actualizar`): descarga del portal de
datos abiertos y escribe la base. Respeta lo hecho a mano: no desactiva los distritos agregados a mano
(`origen = manual`) ni reactiva los desactivados; los seeders tampoco pisan lo editado en la pantalla. El servidor necesita salida HTTPS a `www.datosabiertos.gob.pe`
(dirección en `config/nucleo.php` o `UBIGEOS_URL`). Desarrollo refresca el CSV del repositorio con
`php artisan nucleo:actualizar-csv-ubigeos`.

## Revisión diaria de alertas (plan 1.10)

El programador corre `nucleo:revisar-alertas` a las 07:00 (hora de Lima): cierra los cruces de funciones de roles
acotados vencidos y avisa los cruces sin control. Va con `withoutOverlapping()->onOneServer()`, que necesitan una caché
con bloqueos compartidos entre servidores (`CACHE_STORE=database`, el de fábrica, o redis; nunca `file` ni `array`).
Repetirla a mano no repite ninguna alerta.

## Tipo de cambio automático (ADR 0012)

El programador corre `nucleo:sincronizar-tipo-cambio` cada 30 minutos de 07:00 a 19:00 (hora de Lima; plan 1.10):
trae el TC del dólar que publica SUNAT, que no avisa cuando publica. Si a las 19:00 todavía falta el de hoy, llega la
alerta «Tipo de cambio que no llegó» al administrador. Requiere el programador activo (`schedule:run` cada minuto) y salida HTTPS
a `www.sunat.gob.pe` (dirección en `config/nucleo.php` o `TIPO_CAMBIO_SUNAT_URL`). Si falla, queda una
advertencia «Tipo de cambio: no se pudo sincronizar» en el registro y el margen de 4 días cubre la
espera; en Configuración → Tipos de cambio están «Traer de SUNAT ahora» y «Registrar a mano».
