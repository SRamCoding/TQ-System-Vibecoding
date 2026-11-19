# Ficha · Fórmula polinómica de reajuste (2.14)

- **Estado:** aprobada (MV-009)
- **Versión:** v1
- **Tramo de la ruta:** T2
- **Aprobada:** 2026-11-19 · etiqueta `aprobado/t2-formula-polinomica-v1`
- **Fuentes del diseño:** `docs/diseno/v4/parte-4-flujo-01.md` (paso 3: «la fórmula polinómica de reajuste se
  incluye; se define con el presupuesto y se aplica en cada valorización»); `parte-4-flujo-10.md` (paso 1, el ejemplo
  de la red de fibra).

## Qué hace

Cada presupuesto puede llevar su fórmula polinómica de reajuste: hasta ocho monomios (mano de obra, cable, equipos,
índice general…), cada uno con su índice unificado y su coeficiente, que suman 1.000. Con los índices oficiales de cada
mes, el sistema calcula el factor de reajuste K = Σ coeficiente × (índice del mes ÷ índice base) y el reajuste de una
valorización = monto × (K − 1). Es cálculo, no criterio. La fórmula viaja con el presupuesto: al ganar pasa a la copia
del proyecto y a sus versiones. Las valorizaciones (tramo 4) la aplican.

## Decisiones de Sebastián

- **MV-009** (general) · Aprobada sin revisión, como todas hasta terminar el sistema; los cambios vienen al final.

## Pantallas y navegación

- **Presupuesto** `/presupuestos/{id}` → pestaña nueva **«Fórmula polinómica»** (entre «Lista de insumos» e
  «Historial»): título, y debajo «Suma 1.000 ✓ · Factor de reajuste 2026-11 sobre 2026-05: K = 1.0187 (Σ coef × índice
  del mes ÷ índice base)». Tabla: monomio, índice unificado («47 · Mano de obra…»), coeficiente y su término.
  Botones: **Cambiar meses** (mes base y mes de la valorización), **Agregar monomio**; en cada fila, **Quitar**.

## Operaciones

| Operación | Campos | Valida | Qué produce |
|---|---|---|---|
| Agregar monomio | descripción (≤ 120), índice unificado (de los cargados), coeficiente (0.001 a 1, tres decimales) | presupuesto editable (borrador); hasta 8 monomios; la suma no pasa de 1.000 | monomio |
| Quitar monomio | — | presupuesto editable | — |
| Calcular factor | mes base, mes de la valorización | suma 1.000; índices de los dos meses | K a cuatro decimales y el término de cada monomio |

## Datos

Tablas nuevas `presupuesto_monomios` (presupuesto, descripción, código de índice, coeficiente, orden) e
`indices_unificados` (código, nombre, periodo AAAA-MM, valor; único por código y periodo). La copia al ganar y la
versión nueva copian los monomios.

## Reglas y números

- K = Σ coef × (índice del mes ÷ índice base), con seis decimales por término y redondeado a cuatro. Reajuste = monto ×
  (K − 1), a dos decimales.
- Demo P8 (PRY-2026-0001 · PRE-01 v2): mano de obra 0.320 (índice 47), cable de fibra 0.285 (06), equipos electrónicos
  0.190 (49), índice general 0.205 (39). Mayo → noviembre de 2026: **K = 1.0187**; sobre una valorización de 142,300.00,
  reajuste **2,661.01**.
- El ejemplo del v4 (flujo 10) dice K = 1.0148 con 621.10/612.40, 411.60/398.20, 509.80/512.00 y 186.90/184.30; con la
  fórmula escrita da **1.0162**. El repo original tiene que confirmar si se redondea cada término (a tres decimales da
  1.017) o si el ejemplo tiene un error.
- Demo: 5 índices de enero de 2025 a noviembre de 2026 (inventados, con su tendencia); fórmula en P6, P8 y P9.

## Supuestos sin confirmar

- Los índices se cargan a mano (en la demo, sembrados); no se leen del INEI.
- La fórmula se edita solo mientras el presupuesto está en borrador; una aprobada se cambia con versión nueva.
- Una sola fórmula por presupuesto (no por área geográfica ni por componente).

## Atajos tomados

- Sin acciones registradas ni historial de cambios de la fórmula; se escribe con consultas directas.
- No hay pantalla para cargar índices.

## Dónde está en el código

`app/Modules/Erp/Presupuestos/Services/FormulaPolinomicaService.php`, `Filament/FormulaDePresupuesto.php`, migración
`2026_11_19_500001_crear_formula_polinomica.php`, la copia en `ArmadoPresupuestoService::copiarContenido()`; datos en
`DemoSeeder::indicesUnificados()` y `formulaDe()`.
