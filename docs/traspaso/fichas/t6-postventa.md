# Ficha · Postventa: series, tickets y suscripciones (flujo 8)

- **Estado:** aprobada (MV-009)
- **Versión:** v1
- **Tramo de la ruta:** fuera de la ruta original (MV-015)
- **Aprobada:** 2026-11-20 · etiqueta `aprobado/t6-postventa-v1`
- **Fuentes del diseño:** `docs/diseno/v4/parte-4-flujo-08.md`; `parte-8-criterios-aceptacion.md` (F8: la serie DS8K…4415).

## Qué hace

Después de la entrega llegan los reclamos, y el flujo empieza siempre por identificar el equipo: su **número de serie**
dice a quién se compró, cuándo entró al almacén, a qué proyecto se despachó, dónde se instaló y hasta cuándo tiene
garantía (la de Techquk y la del fabricante). Los **tickets** se ordenan por el vencimiento de su nivel de servicio (8,
24 o 72 horas según la prioridad), no por su creación. En garantía del fabricante, se gestiona con él; fuera de
garantía, no se atiende sin cotización aceptada y vuelve al CRM como oportunidad. Cada **visita técnica** registra
horas y materiales. Las **suscripciones** que Techquk vende (monitoreo, respaldo en nube) muestran su margen por mes y
su disponibilidad contra la meta.

## Decisiones de Sebastián

- **MV-015** (ficha) · Entra al MVP. **MV-009** (general) · Aprobada sin revisión.

## Pantallas y navegación

- **Inicio:** cajas «Postventa» y «Suscripciones» (ERP).
- **Postventa · tickets** `/postventa`: ticket (prioridad), cliente, equipo (reclamo debajo), garantía (vigente, del
  fabricante, vencida), «SLA vence en» (rojo a menos de 4 h o vencido) y estado. Filtro Ver: Abiertos, Míos, Por vencer
  SLA, Con fabricante, Cerrados. En cada fila: Visita, Estado y, fuera de garantía, **Cotizar** (abre la oportunidad
  nueva). Arriba: **Rastrear serie** y **Nuevo ticket** (serie recomendada, cliente, equipo si no hay serie, reclamo,
  prioridad).
- **Rastreo de serie** `/rastreo-serie?serie=…`: buscador (serie completa o su final) y la vida del equipo en cinco
  columnas: Compra (orden y proveedor), Ingreso (fecha y ubicación), Despacho (fecha y proyecto), Instalada (dónde),
  Garantía (estado y fechas).
- **Cartera de suscripciones** `/suscripciones`: suscripción, cliente, cobro y costo mensual con su margen,
  disponibilidad real contra la meta (rojo si no la cumple), renovación.

## Operaciones

| Operación | Campos | Valida | Qué produce |
|---|---|---|---|
| (servicio) Ingresar series | producto, series, orden, fecha, ubicación | serie única | series «en almacén» |
| (servicio) Instalar | serie, proyecto, fecha, lugar, meses de garantía (24) y del fabricante | la serie existe | «instalada», con su garantía desde la instalación |
| Nuevo ticket | serie, cliente, equipo, reclamo, prioridad (alta 8 h, media 24 h, baja 72 h) | — | `TK-NNNN` con su garantía calculada; abierto, o «por cotizar» si la garantía venció |
| Visita | fecha, técnico, horas, materiales, qué se hizo | — | visita; el ticket pasa a «en atención» |
| Estado | estado, solución | — | — |
| Cotizar | monto estimado | fuera de garantía | oportunidad «Reparación fuera de garantía · TK-…» en prospecto, enlazada al ticket |

## Datos

Tablas nuevas `series`, `tickets_postventa`, `visitas_tecnicas` y `suscripciones`. Permisos `erp.postventa.ver` y
`.atender`.

## Reglas y números

- Garantía hoy: vigente si no venció la de Techquk; «del fabricante» si venció la de Techquk pero no la del fabricante;
  si no, vencida.
- Demo (v4 F8): **DS8K7731024415** · compra de la Plaza de Armas · ingreso 12/09/2026 en Central A-03 · despacho
  30/09/2026 a PRY-2026-0003 · instalada en el poste 3 · **garantía hasta el 30/09/2028**. 5 tickets: el switch del
  hospital (vigente, alta), la PTZ de Valle Norte (vencida, por cotizar), el NVR de la clínica (con el fabricante), la
  cámara del poste 3 (en atención, con una visita de 2.5 h) y uno resuelto de la UGEL. 3 suscripciones (el monitoreo
  de la red del GORE no cumple su disponibilidad y se renueva el 31/12/2026).

## Supuestos sin confirmar

- Las series se cargan por servicio (la recepción de una orden todavía no las pide; el almacén no exige elegirlas al
  despachar).
- Las horas y materiales de la visita no se imputan como costo; las suscripciones no generan facturas.
- La garantía de Techquk es de 24 meses desde la instalación por omisión.

## Atajos tomados

- Estados como texto, sin acciones registradas; consultas directas en las pantallas.

## Dónde está en el código

`app/Modules/Erp/Postventa/` (provider, migración, `Services/PostventaService.php`, `Filament/Pages/` con Tickets,
Rastreo y Suscripciones, vista `rastreo`); datos en `DemoSeeder::postventa()`.
