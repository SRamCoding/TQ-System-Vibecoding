# Plan: presupuesto de la empresa por sede y periodo (2.13)

- **Estado:** por empezar (alcance decidido; pasos por planear antes de construir)
- **Responsable:** Sebastián (área 1, Presupuesto y costo, ADR 0020)
- **Entrega:** por definir al empezar (`entrega/presupuesto-empresa`)
- **Módulo(s):** `Erp/Presupuestos` (lo nuevo); núcleo (imputación del gasto directo, que suma la sede; sedes de Techquk;
  aprobaciones, alertas, plantillas)
- **Fase:** etapa 2, área 1, tarea 2.13 (la 2.12 es la copia al ganar, rama `plan/presupuesto-copia-al-ganar`; no depende del corte de reparto: el área 1 sigue en paralelo)
- **Creado:** 2026-11-16 · **Actualizado:** 2026-11-16

## Objetivo

Además del presupuesto de cada proyecto, Techquk presupuesta lo que gasta la empresa fuera de los proyectos (oficina,
gastos generales de empresa) por **sede y por mes**, y ese presupuesto controla el
gasto directo igual que el árbol de partidas controla el de un proyecto: saldo antes de gastar, comprometido,
ejecutado, alertas y aprobación del exceso.

## Por qué

Hoy el gasto directo solo se registra con su centro de costo (selector de imputación, 2.2): no tiene tope ni avisa
cuando se pasa. Decisión del desarrollador (2026-11-16), a partir de la descripción de un módulo de presupuestos de
empresa que trajo para comparar: «no es un pendiente, sí se hace así».

## Alcance

Tomado de esa descripción, solo lo que encaja con lo que ya hay y con la etapa 2:

1. **Estructura.** Un presupuesto de empresa por ejercicio (año), con una línea por sede de Techquk (las sedes de su
   ficha en Personas y empresas, plan 1.3b) y montos por mes (el año es la suma). Dentro de una sede, la línea se puede
   abrir en rubros de gasto (alquiler, servicios, movilidad…) si hace falta; el centro de costo no es dimensión del
   presupuesto (D2). Moneda: soles en la etapa 2, como el resto (plan 2.1, D7).
2. **Formulación.** Crearlo desde cero, copiar el del año anterior o partir del gasto real del año anterior con un
   porcentaje de ajuste (por línea o general); importarlo desde Excel con revisión de errores antes de guardar; una nota
   por línea para el supuesto («incluye 5 % por inflación»). La copia usa las plantillas del núcleo (2.11).
3. **Aprobación y versiones.** Como el presupuesto de proyecto: estados y transiciones como datos, aprobación por las
   reglas configurables del núcleo, congelado al aprobarse. La versión aprobada es la **base**; las revisiones del año
   (por ejemplo, un ajuste a mitad de año) son versiones nuevas que se comparan con la base, sin borrarla. Un mes cerrado
   ya no se edita.
4. **Control al gastar (lo central).** El selector de imputación muestra, para el gasto directo, el saldo de su sede en
   el mes antes de pedir, igual que el de una partida. El monto pasa por **presupuestado → comprometido (orden
   aprobada) → ejecutado (recepción o rendición)**, con las mismas reglas que una partida: el exceso exige motivo y
   aprobación. **Alertas configurables** por umbral de consumo (de partida, 75 %, 90 % y 100 %), idempotentes como las
   del núcleo.
5. **Traspasos.** Mover saldo presupuestado de una sede o de un mes a otro, con motivo, aprobación e
   historial. Distinto de la reclasificación (2.8), que mueve un gasto ya hecho.
6. **Comparativo.** Por sede y mes: presupuestado, comprometido, ejecutado, variación en soles y en
   porcentaje, con la desviación desfavorable marcada; su tarjeta en el tablero de Gerencia.

## Fuera de esta tarea (va en su etapa)

- **Devengado y pagado** (factura y pago), y la proyección de liquidez: etapa 3, con comprobantes, cuentas por pagar,
  tesorería y flujo de caja.
- **Moneda extranjera** y diferencia de cambio: etapa 3.
- **Planilla como inductor** (sueldos, cargas y contrataciones proyectados): etapa 4, RRHH.
- **Proyección continua (meses cerrados reales + meses futuros proyectados), escenarios y tableros ejecutivos**:
  etapa 5, analítica. Esta tarea deja los datos por mes listos para eso.
- **IA que detecta anomalías y sugiere por estacionalidad**: después de tener un año de datos; con la regla «la IA
  sugiere y una persona confirma».
- **Hilos de comentarios por línea**: si se hacen, en el núcleo para todos los documentos, no solo aquí.

## Decisiones

- **D1.** (desarrollador, 2026-11-16) Se hace: presupuesto de empresa por periodo (por centro de costo, cambiado a sede por D2), que controla el
  gasto directo.

- **D2.** (desarrollador, 2026-11-16) Se presupuesta **por sede**, no por centro de costo: «no lo manejan por centro».
  Consecuencia: hoy el gasto directo se imputa solo a un centro de costo (selector de imputación, 2.2); para que este
  presupuesto lo controle, **cada línea de gasto directo lleva además su sede**. El centro de costo se queda en la
  imputación como está (lo usan los asientos y los centros del v4), pero no se presupuesta.
- **D3.** (desarrollador, 2026-11-16, responde la P1) La sede del gasto directo es **siempre obligatoria**, también con
  una sola sede: no se llena sola ni se oculta. Un gasto de toda la empresa (un seguro anual, por ejemplo) va a una
  línea **«Empresa»**, que se presupuesta y se controla igual que una sede, sin repartirse entre ellas.
- **D4.** (desarrollador, 2026-11-16, responde la P2) Un gasto directo en una sede y un mes **sin presupuesto** no
  se carga a nada: **hay que crear un presupuesto genérico de ese mes** para esa sede, y el gasto sigue cuando existe.
  El selector ofrece crearlo ahí mismo, en borrador, y pasa por la misma aprobación que cualquier presupuesto de la
  empresa (propuesta confirmada por el desarrollador, 2026-11-16).
- **D5.** (desarrollador, 2026-11-16, responde la P3) El núcleo pide el saldo por un enchufe propio que llena
  `Erp/Presupuestos`, como `VentaDelProyecto`; el gasto directo guarda su sede. Escrito en el ADR 0025.
- **D6.** (desarrollador, 2026-11-16, responde la P4) Los gastos generales de un proyecto se quedan en su árbol; al
  presupuesto de la sede va solo lo que no es de ningún proyecto, para que nada se cuente dos veces. Alguien de oficina
  que trabaja para un proyecto sigue siendo gasto de la sede, salvo que se impute explícitamente al proyecto (como las
  horas del tareo).

## Pasos

Se escriben al empezar la tarea, con la plantilla. Ya no queda nada por decidir del alcance (D1 a D6); el ADR 0025
va antes de tocar la imputación.

## Preparado para lo diferido

Los montos por mes y las versiones (base y revisiones) quedan listos para que la etapa 3 sume devengado y pagado, y la
etapa 5 la proyección continua y los escenarios, sin rehacer la tabla.

## Criterio de aceptación

Por definir con los pasos; como mínimo, un escenario con números exactos: presupuesto de una sede en un mes,
una orden que compromete, una recepción que ejecuta, el exceso con motivo y aprobación, la alerta al 90 % y un traspaso.

## Bitácora

- 2026-11-16 — El desarrollador comparó el módulo con la descripción de un presupuesto de empresa. El de proyecto ya
  cubre la aprobación, las versiones y el control al gastar; lo que faltaba era presupuestar el gasto directo por centro
  de costo y periodo. Decidió hacerlo (D1), por sede y no por centro de costo (D2), con la sede siempre obligatoria y una línea
  «Empresa» para lo que es de todas (D3).
