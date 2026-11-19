# Restauración

> Un respaldo que nunca se restauró es una hipótesis. Esta prueba se hace **antes de cargar datos reales**
> y luego periódicamente.

1. En una máquina distinta del servidor de producción, levantar los contenedores con la misma versión.
2. Descargar del bucket el último volcado de la base y los archivos adjuntos.
3. Descifrar y restaurar la base; copiar los adjuntos al disco de la aplicación.
4. Verificar: la aplicación abre, la página de salud está en verde, y un documento reciente con adjuntos
   se ve completo.
5. Registrar en el informe: fecha, versión, tiempo total de restauración y problemas encontrados.

## Archivos huérfanos de adjuntos durante una restauración (plan 1.12)

Cada domingo a las 04:00, el sistema pasa a cuarentena los archivos del disco de adjuntos que no tienen registro en la
base. Con una base restaurada a medias, archivos buenos parecerían huérfanos. Por eso:

1. **Antes de restaurar**, apagar el parámetro `adjuntos.revision_huerfanos_activa` (Configuración → Parámetros). La
   página de salud lo muestra en amarillo mientras esté apagado, y en rojo si pasa de `adjuntos.dias_pausa_huerfanos`
   días (14 de fábrica): una pausa olvidada no queda así para siempre.
2. Restaurar la base y los adjuntos.
3. Revisar lo que haría, sin cambiar nada: `php artisan nucleo:revisar-huerfanos --simular`. Si lista archivos que
   deberían tener registro, la restauración no está completa.
4. **Al terminar**, volver a encender el parámetro.

Aunque la revisión corriera por error, tiene dos frenos: si encuentra más huérfanos que `adjuntos.tope_huerfanos` (50
de fábrica) no mueve ninguno y avisa; y nada se borra de inmediato, solo después de `adjuntos.dias_cuarentena` días
(30 de fábrica).

**Devolver un archivo desde la cuarentena:** en el disco de adjuntos, cada archivo queda en
`cuarentena/AAAA-MM-DD/<su ruta original>` (la fecha es la del día en que entró). Para devolverlo, se mueve de vuelta a
`<su ruta original>`, quitando el prefijo `cuarentena/AAAA-MM-DD/`. La fila de `adjuntos` que lo nombra tiene que existir:
si no, la siguiente revisión lo vuelve a poner en cuarentena.

## Archivo histórico (plan 1.4y)

Al restaurar, se copia también la carpeta del histórico (`storage/app/private/historico`, o el bucket si está en S3).
Para comprobarlo, leer un archivo con `php artisan nucleo:leer-historico <archivo>`; si se cifró con una contraseña
anterior, `--otra-clave`.
