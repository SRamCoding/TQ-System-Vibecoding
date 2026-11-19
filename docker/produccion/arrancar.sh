#!/bin/sh
# Antes de cada proceso (FPM, colas, programador): las cachés de configuración, rutas, vistas y eventos de Laravel se
# arman con el .env de TI, que llega al arrancar (nunca está en la imagen). No migra: eso lo hace TI a mano
# (docs/operaciones/despliegue.md).
set -e
php artisan optimize --ansi
exec "$@"
