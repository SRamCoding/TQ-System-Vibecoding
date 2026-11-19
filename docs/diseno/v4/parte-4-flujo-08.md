# v4 · Flujo 8 · La postventa

> Extracto de la parte 4 de la especificación v4. Fichas de sus documentos: `parte-6-fichas-documentos.md`. Escenarios de prueba: `parte-8-criterios-aceptacion.md`.

```text
Flujo 8 · La postventa
Después de la entrega llegan los reclamos. El flujo empieza siempre por identificar el equipo, y aquí
rinde el registro de series de almacén, que S10 no tiene: en segundos se sabe a quién se compró,
cuándo se instaló y si sigue en garantía.
|  Tickets  |  Buscar por número, cliente o serie…  |  + Nuevo ticket
|  Abiertos  |  Míos  |  Por vencer SLA  |  Con fabricante  |  Cerrados
|  Ticket  |  Cliente  |  Equipo  |  Garantía  |  SLA vence en
|  TK-0418  |  Hospital Regional  |  Switch · serie SW…0917  |  vigente  |  1 h 40 min
|  TK-0421  |  Muni. de Pimentel  |  Cámara PTZ · serie PT…2231  |  vencida  |  6h
|  TK-0415  |  EPSEL S.A.  |  NVR · serie NV…1180  |  con fabricante  |  en espera
Bandeja de tickets ordenada por vencimiento de SLA, no por fecha de creación.
|  Rastreo · serie DS8K…4415
|  Compra  |  Ingreso  |  Despacho  |  Instalada  |  Garantía
|  OC-0138  |  12 set  |  16 set  |  Poste 3  |  hasta 30 set 2028
|  Prov. Andino  |  Central A-03  |  PRY-012  |  Plaza de Armas
La vida completa de un equipo. Es lo primero que abre Help Desk cuando llega un reclamo.
Los tickets se ordenan por vencimiento de su nivel de servicio, no por fecha de creación. Un ticket en
garantía del fabricante se gestiona con él; uno fuera de garantía no se atiende sin cotización aceptada,
y vuelve al CRM como oportunidad. Toda visita técnica registra horas y materiales con su imputación.
Las suscripciones son los servicios recurrentes que Techquk vende (monitoreo, respaldo en nube),
con margen medido por periodo porque el costo también corre cada mes. POR VALIDAR Este tipo de
servicio salió del portafolio público de la empresa, no de contratos verificados. Si hoy no hay
suscripciones reales, se deja para una fase posterior.
|  Cartera de suscripciones  |  1 por renovar en 60 días
|  Suscripción  |  Cliente  |  Cobro mensual  |  Costo mensual Margen  |  Disponibilidad
|  Monitoreo CCTV 24/7  |  EPSEL S.A.  |  4,800.00  |  3,100.00 35 %  |  99.6 % / 99.5 %
|  Respaldo en nube  |  Hospital Regional  |  1,900.00  |  1,650.00 13 %  |  100 % / 99.9 %
Suscripciones que Techquk vende: el margen se mide por periodo, porque el costo también corre cada mes.
```
