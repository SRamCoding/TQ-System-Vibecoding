# Responsabilidades entre TI y desarrollo

El servidor es de Techquk y lo administra TI (ADR 0004). La mayoría de las fallas de operación ocurren
en la frontera entre ambos: por eso queda escrito.

| Responsabilidad | Dueño | Lo que entrega al otro lado |
|---|---|---|
| Sistema operativo, Docker, parches, firewall, red, UPS, espacio en disco | TI | Aviso previo antes de reinicios |
| Acceso externo (VPN o HTTPS), dominio, certificado | TI | Puertos y dominios disponibles |
| Imagen de la aplicación: PHP, extensiones, worker, programador | Desarrollo | `docker-compose.yml` de producción y `despliegue.md` |
| Ejecutar despliegues y migraciones en producción | TI | — |
| Paquete de cada versión y cómo volver atrás | Desarrollo | Notas de versión |
| Qué se respalda y cómo se restaura | Desarrollo | `respaldos.md`, `restauracion.md` |
| Bucket S3, credenciales de solo escritura, retención en la infraestructura | TI | Credenciales para el job de respaldo |
| Prueba de restauración en otra máquina | Ambos | Informe antes de cargar datos reales |
| Monitoreo del servidor | TI | — |
| Señales de salud de la aplicación (latido, jobs fallidos, respaldos) | Desarrollo las emite, TI las recibe | Contacto de TI para alertas (pendiente) |
| `.env` y secretos de producción (incluida la clave de IA) | TI | Desarrollo nunca los ve |
| Clave de cifrado de los respaldos (`BACKUP_ARCHIVE_PASSWORD`): generarla y guardarla **fuera del servidor**; sin ella los respaldos no se abren | TI | — |
| URL del latido en Healthchecks (`SALUD_LATIDO_URL`) y a quién avisa | TI | — |
