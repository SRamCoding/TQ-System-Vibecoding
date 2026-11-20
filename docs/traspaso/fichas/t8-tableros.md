# Ficha · Tableros de inicio por rol

- **Estado:** aprobada (MV-009)
- **Versión:** v1
- **Tramo de la ruta:** fuera de la ruta (MV-015)
- **Aprobada (v1):** 2026-11-20 · etiqueta `aprobado/t8-tableros-v1`
- **Fuentes del diseño:** `docs/diseno/v4/parte-5-interfaz-roles.md` («Los tableros de inicio»: Jefatura de
  Proyectos, Almacén y Gerencia General; Tesorería en el inicio con mosaicos).

## Qué hace

Cada rol tiene un tablero que responde pocas preguntas: cuatro números arriba y tres listas debajo, con «qué vence»,
«dónde se va el dinero» y «qué espera algo de mí». La regla del v4: lo que el sistema le pide a alguien le devuelve
algo a él, no solo a su jefe. Cada fila lleva a la pantalla donde se resuelve. Los números salen de los mismos cálculos
que las pantallas de cada módulo (dinero del proyecto, contratos, flujo de caja).

## Decisiones de Sebastián

- **MV-015** (general) · Lo que el v4 tenía y la ruta dejó fuera también entra; tableros por rol.
- **MV-009** (general) · Aprobada sin revisión; los cambios vienen al final.

## Pantallas y navegación

- **Inicio:** caja «Mi tablero» en el área ERP.
- **Mi tablero** `/mi-tablero?rol=…`: subtítulo con el rol y la fecha; pestañas con los roles que la persona puede ver
  (si es uno solo, no hay pestañas); cuatro tarjetas (etiqueta, número en color si hay alerta, nota) y tres secciones
  con listas cuyas filas son enlaces (en rojo lo vencido, en ámbar lo que está al límite).
  - **Jefatura de Proyectos:** mis proyectos activos (con lo que suman sus contratos), hitos en 14 días (cuántos
    vencidos), partidas al límite (90 % o más comprometido), penalidad en riesgo. Listas: Qué vence (hitos del
    contrato), Dónde se va el costo (partidas al límite con su %), Esperan algo de mí (tareos sin cerrar,
    requerimientos por aprobar, partidas sin responsable, cartas fianza que vencen en 30 días).
  - **Almacén:** por recibir (órdenes de compra con saldo, atrasadas y con saldo de más de 30 días aparte), recibido
    hoy, despachos pendientes (requerimientos aprobados), inventario valorizado y series en almacén. Listas: Qué entra,
    Qué sale, Últimos movimientos.
  - **Tesorería:** pagos de esta semana, facturas por programar (vencidas y con diferencia), caja (y movimientos sin
    conciliar), cartas fianza vigentes. Listas: Qué pago, Qué cobro, La caja de los próximos meses.
  - **Comercial:** oportunidades abiertas (monto en juego), ganado en el año, hitos de licitaciones en 14 días (y
    convocatorias nuevas del SEACE), oportunidades sin contacto en 30 días. Listas: Qué cierra pronto, Licitaciones
    que vencen, Mi agenda de la semana.
  - **Gerencia General:** contratos en ejecución, margen ponderado, penalidad en riesgo, por cobrar a más de 60 días.
    Listas: Margen por proyecto (de menor a mayor), Caja por mes, Esperan mi firma (cotizaciones por aprobar, órdenes
    y requerimientos por aprobar, contratos pasados de plazo).

## Operaciones

Ninguna: solo lectura. Cada fila lleva a la pantalla del módulo.

## Datos

Sin tablas nuevas. Qué pestañas ve cada quien sale de los permisos de ver que ya existen: Jefatura con
`erp.contratos.ver`, Almacén con `erp.compras.ver`, Tesorería y Gerencia con `erp.finanzas.ver`, Comercial con
`crm.licitaciones.ver`.

## Reglas y números

- **Partida al límite:** comprometido ≥ 90 % de lo presupuestado; en rojo si pasó el 100 %.
- **Penalidad en riesgo:** por cada contrato vigente pasado de su fin vigente, días de atraso × penalidad diaria (sin
  tope; el tope se aplica al registrar la penalidad, ficha `t2-contratos`).
- **Margen ponderado:** suma de márgenes proyectados (o previstos) ÷ suma de lo vendido, de los proyectos en ejecución.
  Demo: 5.5 % (hospital 4.0 %, Plaza de Armas 6.5 %, GORE 8.4 %); en ámbar bajo 10 %.
- **Caja:** el flujo de `t5-finanzas` (3 meses en Tesorería, 4 en Gerencia); en rojo el mes con neto negativo (demo:
  diciembre −4,955.87).
- Demo: Jefatura ve 4 proyectos, 2 hitos de la Plaza de Armas, 6 partidas al límite, el tareo sin cerrar y la carta
  fianza de P8 que vence el 12/12; Almacén, la OC del cable por recibir y los dos requerimientos por despachar.

## Supuestos sin confirmar

- Los roles del tablero se deducen de los permisos de ver (no hay un rol «tablero» por persona); la persona de
  desarrollo ve las cinco pestañas.
- Jefatura muestra todos los proyectos en ejecución, no solo los del jefe que entra (el servicio acepta el filtro por
  jefe; con la persona de desarrollo saldría vacío). Igual Comercial con el ejecutivo.
- «Continuar donde te quedaste», el inicio con mosaicos y la versión del celular quedan fuera.
- Las órdenes con saldo de hace más de 30 días se cuentan aparte para no tapar lo que llega esta semana.

## Atajos tomados

- El servicio consulta tablas de otros módulos directamente (`DB`) en vez de pedirles un servicio a cada uno; solo
  dinero del proyecto, contratos y flujo usan su servicio.
- Se recalcula todo en cada visita, sin caché.
- «Mi tablero» no reemplaza el inicio: es una caja más.

## Dónde está en el código

`app/Modules/Erp/Tableros/` (`Services/TablerosService.php`, `Filament/Pages/MiTablero.php`,
`resources/views/tablero.blade.php`, provider registrado en `bootstrap/providers.php`).
