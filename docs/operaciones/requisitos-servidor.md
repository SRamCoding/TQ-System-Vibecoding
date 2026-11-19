# Requisitos del servidor

- **Sistema:** Linux con Docker y Docker Compose.
- **Contenedores:** aplicación (PHP-FPM + servidor web), PostgreSQL 16, **dos workers de la cola** y programador:
  - el de siempre (correos de avisos, tipo de cambio): `php artisan queue:work database --queue=default`;
  - el de la IA (plan 1.11c), aparte para que una sugerencia que tarda minutos no detenga lo demás:
    `php artisan queue:work ia --queue=ia --timeout=660`.
  Todos con política de reinicio automático, para que vuelvan solos tras un corte de energía.
- **PHP 8.5 parcheado:** la versión oficial 8.5.12 o posterior, o el paquete de la distribución que incluya el parche
  (en Ubuntu, el registro de cambios del paquete nombra las CVE corregidas). Corrige «PHP2Shell» (CVE-2026-109618 y
  CVE-2026-109619, octubre de 2026): ejecución de código sin iniciar sesión, ya explotada. **Los avisos de seguridad de
  PHP se aplican en cuanto salen**, reiniciando todos los procesos de PHP (FPM, worker y programador). Si un parche
  tarda, se sigue la mitigación que publique PHP.
- **Recursos:** con decenas de usuarios, un servidor modesto basta. Lo que más crece son los archivos
  adjuntos (actas, fotos, comprobantes): reservar disco para ellos.
- **Salida a internet:** validación de comprobantes ante SUNAT, consulta de RUC, correo saliente, S3 para
  respaldos, **Healthchecks** (la URL del latido del programador que configure TI en `SALUD_LATIDO_URL`, plan 1.12)
  y, si Gerencia lo autoriza, el servicio de IA.
- **`pg_dump`** (`postgresql-client` 16 o mayor, igual o más nuevo que el servidor de PostgreSQL) en la imagen de la
  aplicación: lo usan los respaldos (plan 1.12). La imagen de producción (`docker/produccion/`, 1.12b) trae el 16 del
  repositorio oficial de PostgreSQL y PHP 8.5.12; la construcción falla si alguno no está.
- **Extensión GD de PHP, con JPG y WEBP** (plan 2.22c): los PDF (la cotización, con dompdf) la usan para dibujar el
  logo de Techquk; sin ella, emitir falla. La imagen de producción la trae y su construcción lo comprueba; en una
  máquina de desarrollo, el paquete `php8.5-gd`. `composer.json` la pide (`ext-gd`): `composer install` avisa si falta.
- **Puerto y archivos:** `web` escucha en `WEB_PUERTO` (80 de fábrica), solo HTTP, en todas las interfaces; con
  `WEB_IP=127.0.0.1`, solo en el servidor (si el proxy de TI corre ahí, nadie lo salta). Dos volúmenes de Docker:
  `storage` (adjuntos, histórico, respaldos locales y registros, compartido por la aplicación, las colas y el
  programador) y `postgres-datos`. **Ambos van en el disco que más crece**: reservar espacio ahí.
- **Archivos subidos:** hasta 50 MB por archivo (el techo de nginx, PHP y Livewire). El parámetro
  `adjuntos.tamano_maximo_mb` decide por debajo; subir el techo es un cambio de código y una imagen nueva.
- **Clima del inicio** (plan 1.4e2): los **navegadores** consultan `api.open-meteo.com` (no el servidor). Si la red de la
  empresa lo bloquea, el dibujo se ve sin clima; se apaga con el parámetro `interfaz.clima_activo`.
- **Entrada:** acceso para técnicos en obra por VPN o HTTPS (a definir con TI).
- **Correo saliente** con SPF y DKIM configurados: las alertas viajan por correo.
- **Hora del servidor** sincronizada (NTP). La aplicación usa la zona `America/Lima`.
- **UPS** con apagado ordenado, para no corromper la base ante un corte.
