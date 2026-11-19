# 0012 — Tipo de cambio automático desde SUNAT, con 4 días de margen

- **Estado:** aceptada
- **Fecha:** 2026-10-10
- **Decide:** desarrollador (el margen de días conviene confirmarlo con el contador)

## Contexto

El v4 (parte 7, integraciones) deja la carga del tipo de cambio «manual o automática» como POR VALIDAR,
y `pendientes.md` tenía abierta la regla para los días sin publicación. La tarea 1.2 construyó la carga
manual con `tipo_cambio.dias_tolerancia = 0` (opción A, sin TC del día es error) y dejó preparado el
origen `sunat`.

Fuentes verificadas el 2026-10-10:
- SUNAT publica en `https://www.sunat.gob.pe/a/txt/tipoCambio.txt` solo el TC del día
  (`10/10/2026|3.45|3.457|`), incluidos sábados. Es el TC que SUNAT usa para efectos tributarios.
- El BCRP tiene una API con historial (cierre del sistema bancario SBS, solo días hábiles), pero no es
  lo que SUNAT publica como TC del día y no está confirmado que se correspondan.

## Decisión

El tipo de cambio del dólar se trae **automáticamente de SUNAT** (tarea programada varias veces al día,
origen `sunat`) y, si falta el de una fecha, se usa el último registrado **hasta 4 días antes**
(`tipo_cambio.dias_tolerancia = 4`). El registro manual queda como **respaldo** con permiso: para cuando
la fuente falle varios días o haya que corregir un valor. La sincronización nunca pisa un valor manual.

## Consecuencias

- El servidor necesita salida HTTPS a `www.sunat.gob.pe` y el programador (`schedule:run`) activo.
- SUNAT solo da el día en curso: un día en que el servidor no consultó no se recupera desde la fuente;
  lo cubre el margen de 4 días y, si hiciera falta, el registro manual.
- Si el contador fija otro margen, se cambia el parámetro, sin desplegar.
- Si SUNAT cambia la dirección o el formato, la sincronización falla con un mensaje claro (y avisará por
  alertas cuando exista la tarea 1.10); la dirección está en `config/nucleo.php`.

## Alternativas descartadas

- **BCRP como fuente o respaldo automático:** tiene historial, pero mezclaría el cierre SBS con el TC
  publicado por SUNAT sin confirmar que sean equivalentes.
- **Solo automático, sin registro manual:** si SUNAT falla más de 4 días, los documentos en dólares de
  esos días no se podrían convertir.
- **Margen 0 (opción A de la tarea 1.2):** con carga automática, cualquier corte breve bloquearía las
  conversiones.
