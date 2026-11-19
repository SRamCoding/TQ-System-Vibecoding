# Plan: compose e imagen de producción (1.12b)

- **Estado:** terminado (2026-10-30)
- **Módulo(s):** infraestructura (sin módulo); núcleo solo en el límite de subida
- **Fase:** F1
- **Creado:** 2026-10-30 · **Actualizado:** 2026-10-30

## Objetivo

Que TI pueda levantar el sistema en su servidor con un solo compose de producción, siguiendo `despliegue.md`: la
aplicación (PHP-FPM y nginx), PostgreSQL 16, los dos workers y el programador, con reinicio automático, PHP 8.5.12 o
posterior, opcache y `pg_dump` 16. Comprobado de verdad levantándolo en desarrollo (nunca en producción).

## Contexto que leí

- `docs/planes/mapa-etapas.md`, fila 1.12b.
- `docs/operaciones/requisitos-servidor.md` (contenedores, PHP parcheado, `pg_dump`, salida a internet) y
  `despliegue.md` (primera instalación, actualizar, volver atrás).
- `docker-compose.yml` actual: solo PostgreSQL de desarrollo (PHP corre nativo en WSL).
- `docs/decisiones/pendientes.md`: **Acceso externo** (VPN o HTTPS, dominio y certificado: TI), **Dominio del
  sistema** y **Región del bucket S3** (Asistencia Legal). Ninguna bloquea: la imagen no los decide (ver D4 y D7).
- Parámetro `adjuntos.tamano_maximo_mb` (20 de fábrica, editable) y el tope de Livewire para subidas (12 MB por
  defecto, sin `config/livewire.php`): hoy un archivo de entre 12 y 20 MB se rechaza aunque el parámetro lo permita.

## Decisiones

- **D1. Una imagen, varios contenedores.** `docker/produccion/Dockerfile` en etapas: dependencias de Composer sin las
  de desarrollo, las pantallas compiladas con Vite y la imagen final de PHP-FPM. Con la misma imagen corren `app`
  (FPM), `cola` (`queue:work database --queue=default`), `cola-ia` (`queue:work ia --queue=ia --timeout=660`) y
  `programador` (`schedule:work`). `web` es nginx con los archivos públicos copiados de la misma construcción. Más
  `postgres:16`. Todos con `restart: unless-stopped`: vuelven solos tras un corte de energía o un reinicio.
- **D2. Dónde.** `docker/produccion/compose.yml`, más su `nginx.conf` y `php.ini`. El `docker-compose.yml` de la raíz
  sigue siendo el de desarrollo. `.dockerignore` deja fuera `.env`, `vendor`, `node_modules`, `storage`, `tests` y
  `docs`.
- **D3. Secretos.** Solo en el `.env` de TI, que el compose lee con `env_file`. Nunca entran a la imagen.
- **D4. Sin HTTPS en la imagen.** `web` escucha HTTP en el puerto `WEB_PUERTO` (80 de fábrica). El certificado, el
  dominio y si es VPN o HTTPS siguen pendientes con TI: los pone su proxy o se suma cuando lo decidan. Sin inventarlo.
- **D5. Versiones.**
  - PHP: etiqueta fija `php:8.5.N-fpm-bookworm`, con N ≥ 12. La construcción falla si la versión es menor (PHP2Shell).
  - `postgresql-client-16`, del repositorio oficial de PostgreSQL: el de Debian bookworm es el 15 y no puede respaldar
    un servidor 16.
  - Extensiones: las que el sistema usa hoy (pdo_pgsql, intl, bcmath, zip, gd o las que pida la imagen, opcache…).
  - opcache con `validate_timestamps=0`, `expose_php=Off`.
- **D6. Archivos.** Un volumen `storage` compartido por `app`, las colas y el programador: adjuntos, histórico,
  respaldos locales y registros. `web` no lo monta: los adjuntos solo se descargan por la ruta que revisa permisos.
- **D7. Migraciones a mano.** El compose no migra al arrancar. TI respalda y luego corre
  `docker compose run --rm app php artisan migrate --force`, como dice `despliegue.md`, que se actualiza con los
  comandos exactos.
- **D8. Límite de subida.** Un solo techo de 50 MB en nginx (`client_max_body_size`), PHP (`upload_max_filesize`,
  `post_max_size`) y Livewire (`config/livewire.php`, regla `max:51200`). El parámetro decide por debajo, y la
  pantalla de parámetros no acepta más de 50. Se arregla el caso de 12 a 20 MB.
- **D9. S3.** `composer require league/flysystem-aws-s3-v3`. El disco `respaldos_s3` ya existe y la región sigue
  pendiente: el paquete no la decide.

## Pasos

- [x] 1. Límite de subida: `config/livewire.php`, tope de 50 en el parámetro y pruebas (un adjunto de 15 MB entra; el
  parámetro no acepta 51).
- [x] 2. `league/flysystem-aws-s3-v3` y una prueba de que el disco `respaldos_s3` se arma (sin llamar a S3).
- [x] 3. Dockerfile, `.dockerignore`, `php.ini` y `nginx.conf`. La construcción comprueba PHP ≥ 8.5.12 y
  `pg_dump` 16.
- [x] 4. `docker/produccion/compose.yml` con los 6 servicios, el volumen y los `healthcheck` (`/up` en web, que pasa por
  FPM; en app, que FPM esté bien configurado y escuche; `pg_isready` para la base).
- [x] 5. Levantarlo en desarrollo con un `.env` de prueba **fuera del repositorio** (scratchpad) y una base propia:
  migrar y sembrar, y comprobar el ingreso, la página de salud (latido anotado por el programador), un trabajo de cada
  cola, `nucleo:respaldar --forzar` con `pg_dump` dentro de la imagen y opcache encendido.
- [x] 6. Prueba de carga corta contra ese entorno (`scripts/prueba-carga.php carga`, 50 personas). La de verdad (50,
  100 y 200 en un servidor igual al de producción) sigue siendo de TI.
- [x] 7. Documentos: `despliegue.md` (comandos exactos), `requisitos-servidor.md` (puerto, volumen, límite de subida),
  `prueba-de-carga.md` (resultado local).
- [x] 8. Revisión de arquitectura y `composer check` en verde.

## Criterio de aceptación

- `docker compose -f docker/produccion/compose.yml up -d --build` deja los 6 servicios sanos. La app responde, inicia
  sesión y la página de salud muestra el latido.
- Dentro de la imagen, `php -v` ≥ 8.5.12, `pg_dump --version` 16 y opcache activo.
- Un respaldo forzado dentro del contenedor crea su archivo cifrado.
- Un adjunto de 15 MB se acepta (prueba en `AdjuntosTest`).

## Bitácora

- 2026-10-30 — Plan escrito. Rama `compose-produccion` desde `main` con la 1.12 (PR #64), el mismo código que la
  carpeta: la app no cambió.
- 2026-10-30 — Paso 1. `ValidadorTamanoAdjunto` (1 a `TECHO_MB` = 50) en `adjuntos.tamano_maximo_mb`;
  `config/livewire.php` solo con `temporary_file_upload` (Livewire une la clave entera), regla `max:51200` y sin SVG en
  las vistas previas (los adjuntos lo prohíben). Pruebas: el parámetro rechaza 0 y 51, y un PDF de 15 MB entra.
- 2026-10-30 — Paso 2. `league/flysystem-aws-s3-v3` ^3.35 (con el SDK de AWS); prueba de que `respaldos_s3` se arma
  sin llamar a S3. La región sigue pendiente (Asistencia Legal): la pone TI en `RESPALDOS_S3_REGION`.
- 2026-10-30 — Pasos 3 a 6.
  - **Imagen:** `php:8.5.12-fpm-bookworm` (la oficial trae justo 8.5.12; la construcción falla con menos);
    `postgresql-client-16` de PGDG (16.15); extensiones pdo_pgsql, pgsql, intl, bcmath, zip, pcntl y gmp, comprobadas
    con `composer check-platform-reqs`; Composer 2.10.3 (la 2.11.0 salió el día anterior); nginx 1.28.3. **Sin Node:**
    la app no usa Vite (Filament trae sus archivos y los publica `filament:assets`). Cambio respecto de D1.
  - **Arranque:** `arrancar` corre `php artisan optimize` con el .env de TI antes de cada proceso; no migra.
  - **Tropiezo:** sin `ENV_ARCHIVO`, el compose lee el `.env` de la raíz (el de desarrollo, en esta carpeta); las colas
    fallaron por autenticación y no tocaron nada. Para la prueba, `ENV_ARCHIVO` apunta al archivo del scratchpad. En el
    servidor de TI, el `.env` de la raíz es el correcto (`despliegue.md`).
  - **Comprobado** (proyecto `techquk-prueba`, puerto 8090, base propia): migrar y sembrar dentro de la imagen; las dos
    cuentas de inicio con nombres inventados; `/up`, el ingreso y la página de salud responden 200; `.env` da 403 y
    `composer.json` 404; `X-Powered-By` no sale; opcache encendido con `validate_timestamps=0`; un trabajo real
    (`EnviarCorreoDeAviso`) en la cola de siempre y la de IA tomando lo suyo; `nucleo:respaldar --forzar` hizo el zip
    cifrado con `pg_dump` 16; el latido se anotó a las 16:05; los reinicios automáticos (8 antes de migrar, sin
    tablas) se recuperaron solos.
  - **Prueba de carga corta** (50 personas, 90 s, pausa 5 s): 705 pedidos, p95 198 ms, sin errores del servidor (302 de
    `/configuracion`, que lleva a su primera pantalla, y 403 de Personas, sin permiso para ese administrador). **El
    script fallaba:** iniciaba 50 sesiones de golpe, el ingreso las cortaba y no lo notaba (666 «errores» en la primera
    corrida). Ahora una sola sesión compartida, el ingreso comprobado con el inicio en 200, y los errores por código.
- 2026-10-30 — Paso 7. `despliegue.md`: los comandos exactos con `--env-file .env -f docker/produccion/compose.yml`
  (sin `--env-file`, compose buscaría el .env junto al archivo y falla avisando qué falta); `artisan` dentro de `app`;
  solo HTTP. `requisitos-servidor.md`: puerto, los dos volúmenes y el techo de 50 MB. `prueba-de-carga.md`: una sesión
  compartida, los códigos y la primera medición (orienta; la de verdad sigue siendo de TI).
- 2026-10-30 — Paso 8. `composer check` en verde (2244 de 2244, pint, phpstan, 52 migraciones). **Revisión de
  arquitectura:** sin bloqueantes; todo lo demás atendido:
  - Vistas compiladas por contenedor (`VIEW_COMPILED_PATH` en `bootstrap/cache/views`): en el volumen compartido, el
    `optimize` de una cola que se reiniciaba (cada hora, por `--max-time`) borraba las de todos.
  - nginx a 52M, igual que `post_max_size` (mide el pedido entero): un archivo de casi 50 MB recibía 413.
  - `pull_policy: never` en la imagen de la aplicación y `build` en app y web: un `docker compose pull` no trae una
    imagen ajena con nuestro nombre.
  - `postgres` sin `env_file`: solo sus tres variables, no los secretos de la aplicación.
  - Actualizar en orden: `build`, `down`, migrar y sembrar con `run --rm` (imagen nueva), `up -d`, `up`. Antes, el código
    nuevo corría contra la base vieja.
  - `trustProxies`: aviso en `despliegue.md` (con proxy, avisar a Desarrollo antes de abrir). No se configura: el
    proxy sigue pendiente con TI.
  - `stop_grace_period`: `cola` 90 s (sus trabajos tienen 60 s de `$timeout`); `programador` 30 min (un respaldo
    cortado dejaba su candado 24 h). Sin `--tries=3` (los trabajos fijan el suyo).
  - Código de root; `www-data` solo escribe en `storage` y `bootstrap/cache` (comprobado: no puede tocar `app/`).
  - `APP_ENV=production` y `APP_DEBUG=false` fijos en el compose.
  - La prueba del adjunto de 15 MB renombrada (va directo al servicio: no prueba Livewire). Nueva
    `tests/Arch/ImagenProduccionTest.php`: el techo igual en nginx, PHP y Livewire; producción fija, sin bajar imágenes ni
    secretos en la base; los comandos de las colas y nadie migra; PHP ≥ 8.5.12 y `pg_dump` 16. Comprobado que falla con
    nginx en 50M.
  - Menores: `**/.env*` y `storage/framework/testing` fuera de la imagen; archivos ocultos denegados antes que los
    estáticos (`/css/.oculto.js` da 403); sin cabeceras de compilación, `curl` ni `unzip` en la imagen final, con
    `apt-get upgrade`; `WEB_IP` para escuchar solo en el servidor; `.env` cambiado exige `up -d`.
  - Imagen reconstruida y comprobada otra vez (20 personas, p95 163 ms, solo 302 y 403 esperados).
- 2026-10-30 — Cierre. Después de `composer check` solo cambiaron archivos de Docker, documentos y dos pruebas, que
  se corrieron solas (`ImagenProduccionTest`, `AdjuntosTest`). La prueba de carga de 50, 100 y 200 personas en un
  servidor igual al de producción sigue siendo de TI (`prueba-de-carga.md`); aquí quedó la medición orientativa.
  Entorno de prueba `techquk-prueba` apagado y borrado con sus volúmenes.
