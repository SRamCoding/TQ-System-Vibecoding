# Ficha · Datos de demostración

- **Estado:** en curso
- **Versión:** v1
- **Tramo de la ruta:** T0
- **Aprobada:** —
- **Fuentes del diseño:** `docs/vibe/ruta-mvp.md` (tramo 0); plan viejo
  `docs/planes/activos/2026-11-16-datos-desarrollo-cartera.md` (la cartera de nueve proyectos y su recorrido).

## Qué hace

Al reconstruir la base de desarrollo, la deja llena con una cartera inventada y creíble: dieciséis cuentas públicas y
privadas, veintiocho oportunidades en todas las etapas, diecinueve cotizaciones en cinco estados y nueve proyectos
(cinco cerrados, tres en ejecución, uno en preparación), cada uno con su presupuesto aprobado, su árbol de partidas y
sus gastos. El tablero de cada proyecto responde con montos que cuadran: cuánto se vendió, cuánto se presupuestó,
cuánto se comprometió, ejecutó y pagó, y el margen previsto y proyectado. Sirve para que Sebastián (y quien pruebe el
sistema) vea cada pantalla con datos sin cargar nada a mano.

## Decisiones de Sebastián

- **MV-001** (ficha) · La cartera son los nueve proyectos del plan viejo tal cual: montos, partidas, márgenes, fechas
  2024–2026 y estados.
- **MV-002** (ficha) · El hospital es inventado («Hospital Regional de Valle Norte»): ningún nombre real en la demo.
  Contradice la D2 del plan viejo, que usaba el nombre real.
- **MV-003** (ficha) · El costo entra por un «gasto de demostración» imputado a una partida (o a la PC-99) hasta que
  existan las órdenes de compra (tramo 3).
- **MV-004** (ficha) · Embudo de unas quince abiertas en todas las etapas y tres perdidas, con cotizaciones en
  distintos estados.

## Pantallas y navegación

No agrega pantallas: llena las existentes. Para recorrerlo (con `php artisan serve --port=8001`):

- `/` inicio · `/cuentas` y `/cuentas/{id}` · `/oportunidades` (portada) y `/oportunidades/faja` · `/cotizaciones`
- `/presupuestos` · `/proyectos` (portada) y `/proyectos/{id}` (ficha con tablero: tarjeta «Costo del proyecto»)
- `/mis-aprobaciones` entrando como el gerente de desarrollo (`gerencia.dev@techquk.test`): una cotización por firmar.

El único cambio visible fuera de los datos: la tarjeta «Costo del proyecto» ahora muestra **Vendido, Margen previsto y
Margen proyectado** (antes no aparecían porque no había de dónde sacar la venta).

## Operaciones

Ninguna nueva para el usuario. El llenado corre solo en local con `php artisan migrate:fresh --seed` (o
`php artisan db:seed`, que no hace nada si la cartera ya está).

Para el repo original, la única operación nueva es interna y provisional:

| Operación | Campos | Valida | Qué produce |
|---|---|---|---|
| Registrar gasto de demostración | partida de control (obligatoria), descripción (texto ≤ 200), proveedor (texto ≤ 150), fecha, comprometido, ejecutado y pagado (decimales ≥ 0), motivo y comentario (obligatorios si la partida es la PC-99) | lo mismo que cualquier imputación: partida activa de un árbol aprobado, ejecutado ≤ comprometido | un documento `GD-AAAA-NNNN` y su imputación con los tres montos; el saldo de la partida y el tablero se mueven |

## Estados

No agrega estados. Recorre los existentes:

- **Oportunidad:** prospecto → calificada → propuesta enviada → negociación → ganada; o → perdida.
- **Cotización:** borrador → (por aprobar → aprobada, firmada por el gerente si el margen está bajo el 22 %) → emitida
  → aceptada; o emitida → rechazada.
- **Presupuesto:** borrador → ofertado (al emitir la cotización) → aprobado (al traerlo al proyecto).
- **Árbol:** borrador → aprobado (crea la PC-99 y pone el proyecto en ejecución).
- **Proyecto:** en preparación → en ejecución → cerrado.

## Datos

- Tabla nueva `gastos_demo` (provisional, MV-003): `numero` (texto 20, único, `GD-AAAA-NNNN`), `proyecto_id`,
  `partida_control_id`, `descripcion` (texto 200), `proveedor` (texto 150), `fecha` (fecha), `monto` (decimal 14,2),
  `created_by`. Alias polimórfico `gasto_demo`. No está en el DBML: desaparece con las órdenes de compra.
- En la base de desarrollo, además de la cartera: la unidad «Gerencia General» con el puesto «Gerente general» ocupado
  por el gerente de desarrollo, y la regla de aprobación «Cotización bajo el margen mínimo» (cotización · aprobar ·
  condición margen bajo el mínimo · firma esa unidad).
- Tres jefes de proyecto inventados: Rosa Quispe Herrera, Diego Salazar Bravo y Andrea Vílchez Campos (DNI
  `0000001x`, correo `@techquk.test`, clave de desarrollo de la fábrica de usuarios).
- RUC inventados con dígito verificador válido: públicos `2069910001`–`08` + dígito, privados `2069920001`–`08` +
  dígito. Contactos decisores con correo `@cliente-demo.test`.

## Reglas y números

**Lo vendido** de un proyecto (mientras no exista Contratos) es el subtotal sin IGV de su presupuesto vigente (costo
directo + gastos generales + utilidad), si ese presupuesto es de una oportunidad con cotización aceptada. **Margen
previsto** = vendido − presupuestado; **margen proyectado** = vendido − proyección al cierre (cálculo que ya existía).

La cartera (soles; «presupuestado» es el del árbol: costo directo + gastos generales):

| Proyecto | Estado | Total cotizado con IGV | Vendido | Presupuestado | Comprometido | PC-99 | Margen previsto | Margen proyectado | Semáforo |
|---|---|---:|---:|---:|---:|---:|---:|---:|---|
| P1 PRY-2024-0001 | cerrado | 2,504,985.40 | 2,122,868.98 | 1,978,945.66 | 1,959,103.65 | 38,500.00 | 143,923.32 | 105,423.32 | verde |
| P2 PRY-2024-0002 | cerrado | 212,132.14 | 179,773.00 | 167,585.00 | 163,089.00 | 0.00 | 12,188.00 | 12,188.00 | verde |
| P3 PRY-2024-0003 | cerrado | 139,585.39 | 118,292.70 | 111,898.50 | 109,048.72 | 0.00 | 6,394.20 | 6,394.20 | verde |
| P4 PRY-2025-0001 | cerrado | 107,793.00 | 91,350.00 | 69,300.00 | 67,406.00 | 0.00 | 22,050.00 | 22,050.00 | verde |
| P5 PRY-2025-0002 | en ejecución | 65,018.00 | 55,100.00 | 41,800.00 | 38,619.40 | 0.00 | 13,300.00 | 13,300.00 | verde |
| P6 PRY-2025-0003 | en ejecución | 3,181,593.74 | 2,696,265.88 | 2,513,468.19 | 2,137,031.61 | 53,850.00 | 182,797.69 | 128,947.69 | ámbar |
| P7 PRY-2025-0004 | cerrado | 227,665.66 | 192,937.00 | 146,366.00 | 151,902.20 | 9,800.00 | 46,571.00 | 36,771.00 | rojo |
| P8 PRY-2026-0001 | en ejecución | 1,180,755.20 | 1,000,640.00 | 932,800.00 | 503,893.50 | 0.00 | 67,840.00 | 67,840.00 | verde |
| P9 PRY-2026-0002 | en preparación | 1,799,418.08 | — (árbol en borrador) | | | | | | |

- Las nueve oportunidades ganadas suman **S/ 9,418,946.61**, el total del plan viejo.
- Embudo: 4 prospecto, 5 calificadas, 4 propuesta enviada, 3 negociación, 9 ganadas, 3 perdidas.
- Cotizaciones: 9 aceptadas, 7 emitidas, 1 borrador, 1 por aprobar, 1 rechazada. Las de obra pública (margen 6.8 %)
  pasan por la firma del gerente: 8 firmas en total y 1 pendiente («Ampliación del centro de monitoreo»).
- Gastos: 104 gastos de demostración. Cada hoja en dos (60 % al 25 % del plazo y 40 % al 70 %). Cerrados: comprometido
  = ejecutado = pagado, entre 93 % y 100 % de lo presupuestado. En ejecución: por avance (P5 0.85, P6 0.78, P8 0.55);
  del segundo gasto, ejecutado el 60 % y pagado el 30 %.

## Supuestos sin confirmar

- **La venta sale del presupuesto vigente** cuando su oportunidad tiene cotización aceptada (subtotal sin IGV). Con
  Contratos, sale del contrato.
- **Nombres sin santos:** «Santa Rosa del Valle» del plan viejo se cambió por «Los Laureles» (P3) por la regla de no
  usar santos; el proyecto P3 se llama «… Municipalidad de Los Laureles».
- **La aprobación por margen** se arma como en el plan viejo (D5): unidad «Gerencia General», puesto ocupado por el
  gerente de desarrollo y una regla que firma esa unidad. Solo en la base de desarrollo.
- **El reparto de los gastos** (60/40, variaciones, avances, lo ejecutado y pagado de los proyectos en curso) y los
  gastos de la PC-99 (P1 imprevisto de obra 38,500.00; P6 precio mayor 18,450.00 y cambio del cliente 35,400.00; P7
  cambio del cliente 9,800.00) son inventados para que el tablero tenga los tres colores del semáforo.
- **El embudo abierto** (nombres, montos, fechas, líneas de cotización) es inventado; los privados con margen cerca del
  30 % y los públicos de la regla con 8 %.
- Los contactos decisores, proveedores de los gastos y cargos son inventados.

## Atajos tomados

- **Gasto de demostración** (`gastos_demo`): documento sin estados, sin permisos, sin pantalla y sin numeración del
  núcleo (el número sale del id). Solo lo usa el seeder. En el repo original no existe: el costo entra por órdenes,
  tareo y rendiciones.
- **Venta del proyecto desde el presupuesto** (`VentaDesdeLaCotizacion`): sustituye la de Contratos con una regla
  provisional; vive en Presupuestos y consulta la cotización aceptada por la interfaz del CRM.
- **Contrato provisional:** cada proyecto nace por `crearDesdeContrato` con un id de contrato inventado (900001…900009)
  sin fila, como ya permitía la columna sin llave foránea.
- **El seeder** busca catálogos y motivos con consultas directas a la base y crea a los jefes con las fábricas de
  modelos; todo lo demás va por los servicios de cada módulo, con su numeración, historial y auditoría.

## Dónde está en el código

- `database/seeders/Demo/CarteraDemo.php` (los datos) y `database/seeders/Demo/DemoSeeder.php` (el recorrido);
  llamado desde `database/seeders/DatabaseSeeder.php` solo en local.
- `app/Modules/Erp/Presupuestos/Models/GastoDemo.php`, `Services/GastosDemoService.php` y la migración
  `database/migrations/2026_11_19_000001_crear_gastos_demo.php` del módulo.
- `app/Modules/Erp/Presupuestos/Services/VentaDesdeLaCotizacion.php`, enlazada en `PresupuestosServiceProvider`.
