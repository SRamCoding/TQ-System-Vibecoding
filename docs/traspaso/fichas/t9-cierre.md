# Ficha · Cierre del proyecto (flujo 6)

- **Estado:** aprobada (MV-009)
- **Versión:** v1
- **Tramo de la ruta:** fuera de la ruta (MV-015)
- **Aprobada (v1):** 2026-11-20 · etiqueta `aprobado/t9-cierre-v1`
- **Fuentes del diseño:** `docs/diseno/v4/parte-4-flujo-06.md`; `parte-8-criterios-aceptacion.md` (F6: se intenta
  cerrar con una OC abierta y el tareo sin cerrar → no se puede y la lista muestra ambas).

## Qué hace

El proyecto no termina con la última factura: termina cuando no queda nada abierto con el cliente, los proveedores ni
el banco, y cuando lo aprendido quedó guardado. El cierre es una lista de verificación; el botón se activa cuando nada
bloquea. Al cerrar se congela el presupuesto contra real por partida y el margen real; se escribe lo aprendido, que
queda para Preventa como histórico; y la carta fianza, cuando el banco la libera, se registra como devuelta.

## Decisiones de Sebastián

- **MV-015** (general) · Lo que el v4 tenía y la ruta dejó fuera también entra; cierre del proyecto.
- **MV-009** (general) · Aprobada sin revisión; los cambios vienen al final.

## Pantallas y navegación

- **Proyecto → pestaña «Cierre»** (`/proyectos/{id}?pestana=cierre::tab`), la última:
  - «Cerrar proyecto · N pendientes» con la tabla Condición / Estado (✓ en verde lo resuelto; en rojo lo que bloquea;
    en ámbar los avisos) y el botón **Cerrar proyecto** (desactivado si algo bloquea; pide un comentario).
  - Ya cerrado: «Lo que quedó congelado» (fecha, vendido, facturado, costo real, margen real y %; por partida:
    presupuestado, real y diferencia, en rojo si se pasó), «Lo aprendido» con **Escribir lo aprendido**, y
    «Garantías» con **Registrar devolución** en las vigentes, renovadas o liberadas.
- **Inicio:** caja «Lo aprendido» → `/lo-aprendido`: proyecto (enlace a su pestaña Cierre), fecha de cierre, margen
  real y %, «Dónde se pasó» (las tres partidas con más sobrecosto) y lo aprendido.
- El «Cerrar» del menú «Más» de la ficha pasa por la misma verificación.

## Operaciones

| Operación | Campos | Valida | Qué produce |
|---|---|---|---|
| Cerrar proyecto | comentario (≤ 300) | en ejecución; ninguna verificación que bloquea pendiente (mensaje con todas las que faltan) | proyecto cerrado; al confirmarse, el cierre congelado |
| Escribir lo aprendido | texto | hay cierre | lecciones del cierre |
| Registrar devolución | — | proyecto cerrado | garantía «devuelta» |

## Verificaciones

| Condición | Bloquea | Pendiente cuando |
|---|---|---|
| Órdenes abiertas | sí | órdenes del proyecto en borrador, aprobada o recibida parcial |
| Material asignado sin despachar | sí | reservas del proyecto menos salidas > 0 |
| Tareo sin cerrar | sí | semanas de tareo abiertas |
| Facturas de proveedor retenidas | sí | facturas de compra sin pagar retenidas o con diferencia en el cruce |
| Valorizaciones en borrador | sí | valorizaciones del contrato en borrador |
| Conformidad final registrada | aviso | el último hito del contrato sin fecha real |
| Comprobante de venta emitido | aviso | ninguna factura de venta |

## Datos

Tabla nueva `proyecto_cierres` (proyecto único, fecha, vendido, facturado, presupuestado, costo, margen real, margen %,
partidas congeladas con presupuestado, real y diferencia, lecciones). Permiso nuevo `erp.cierre.gestionar`. Cada
módulo registra sus verificaciones en una lista que Proyectos consulta como condición de la transición «cerrar».

## Reglas y números

- **Margen real** = vendido (del contrato, con penalidades; si no, lo facturado) − costo ejecutado; % sobre lo vendido.
- F6 en la demo: Plaza de Armas (PRY-2026-0003) no se puede cerrar: «Órdenes abiertas (OC-2026-0028 recibida
  parcial); Material asignado sin despachar (4 unidades reservadas); Tareo sin cerrar (semana del 16/11)».
- Cerrados de la demo: P1 videovigilancia vendido 1,872,370.44, costo 1,978,015.94, margen **−105,645.50 (−5.6 %)**
  (la penalidad); colegio 16,153.67 (8.9 %); computadoras 8,895.02 (7.5 %); videoconferencia 23,944.00 (26.2 %);
  telemetría 41,034.80 (21.2 %). Lecciones escritas en P1, colegio y telemetría; la carta fianza del colegio está
  liberada esperando que se registre su devolución.

## Supuestos sin confirmar

- La conformidad final y la factura de venta se avisan pero no bloquean (en la demo los cerrados no tenían su
  facturación cargada en el momento del cierre).
- «Material sin despachar» se mide con las reservas del proyecto, porque aún no hay despachos de almacén.
- El cierre congelado se puede rehacer (la demo lo rehace después de cargar la facturación); los costos que llegan
  tarde como «ajuste posterior con aprobación» quedan fuera.
- La lista «Por insumo» del presupuesto contra real queda fuera: se congela por partida.
- El contrato no se culmina solo al cerrar (la demo lo culmina aparte, como antes).

## Atajos tomados

- Las verificaciones consultan tablas de otros módulos directamente desde el módulo Cierre.
- El cierre congelado se guarda como JSON en una columna.
- El seeder recongela los cerrados y cambia el estado de la garantía directo en la tabla.

## Dónde está en el código

`app/Modules/Erp/Cierre/` (`Services/CierreService.php`, `Filament/CierreDelProyecto.php` y su vista `pestana`,
`Filament/Pages/LoAprendido.php`, migración `proyecto_cierres`); en Proyectos, `Condiciones/VerificacionesCierre.php`
y `Condiciones/ProyectoSinPendientes.php` en la transición «cerrar»; datos en `DemoSeeder::cierres()`.
