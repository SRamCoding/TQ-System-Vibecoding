# Ruta al MVP

El MVP es el sistema completo en versión base: lo que describe el v4 (ADR 0006), visto funcionando de punta a punta
con datos de demostración. Esta ruta reemplaza al mapa de etapas del repo original para este repositorio: el mapa
(`docs/planes/mapa-etapas.md`) queda como referencia de lo que se pensó para cada parte.

El orden sigue el camino del dinero en un proyecto real de Techquk: se consigue el cliente, se gana el contrato, se
arma el proyecto, se compra y se trabaja, se cobra y se paga, y al final se cierra y se ve cuánto se ganó. Cada tramo
deja algo que Sebastián puede recorrer con clics. Sebastián puede cambiar el orden cuando quiera: basta con decirlo.

Cada tramo produce una o varias fichas en `docs/traspaso/fichas/` (por ejemplo, `t1-licitaciones.md`). Un tramo está
terminado cuando se ve como dice abajo **y** sus fichas están aprobadas: eso es lo que el repo original se lleva.

## Punto de partida (lo que ya está, 19 de noviembre de 2026)

El **núcleo** está completo: personas y empresas con contactos, sedes y cuentas bancarias; usuarios, puestos y
permisos; catálogos y parámetros; estados y transiciones con efectos; numeración; aprobaciones configurables;
auditoría e historial; motivos; alertas; registro de acciones; el mecanismo de IA; plantillas; documentos impresos;
y toda la interfaz (inicio con cajas por estación, saludo, «Mien», «Mis datos», preferencias, panel de aplicaciones,
modo desarrollo).

Del **CRM** están Cuentas, Oportunidades (portada con fotos, tablero, ficha con recorrido) y Cotizaciones (con su
PDF). Del catálogo existe el esqueleto de lectura.

Del **ERP** están Proyectos (portada con fotos y ficha con tablero de control), el presupuesto con APU, el árbol de
partidas de control propuesto desde el presupuesto, el selector de imputación y los gastos no presupuestados
(PC-99).

Quedaron a medio camino, sin urgencia: el «gran momento» de las fechas especiales (1.4d, 1.4d2, 1.4k), el asistente
de ayuda con IA (1.14) y las solicitudes de permiso (2.0b). Van al final, si Sebastián los quiere.

## Tramo 0 · Datos de demostración

Sin datos no se ve nada, así que va primero. Una cartera creíble: unas quince cuentas (entidades públicas y
privadas), oportunidades en todas las etapas, cotizaciones, y nueve proyectos en estados distintos con presupuesto,
árbol de partidas y gastos. La idea ya estaba pensada en `docs/planes/activos/2026-11-16-datos-desarrollo-cartera.md`.
Cada tramo siguiente agrega sus propios datos encima de estos.

**Se ve listo cuando** al entrar, cada caja del inicio abre algo lleno, y el tablero de un proyecto muestra montos
que tienen sentido.

## Tramo 1 · Cerrar lo comercial

Licitaciones (CRM-04) con carga manual, públicas y privadas; agenda y actividades (CRM-05); catálogo comercial
completo con precios y carga masiva (CRM-06); campañas y leads con su conversión (CRM-07). Y el puente al ERP: al
ganar una oportunidad, su costeo se copia como presupuesto del proyecto y se compara lo ofertado contra lo
presupuestado (2.12, plan en `docs/planes/activos/2026-11-16-presupuesto-copia-al-ganar.md`). La lectura automática
del OECE queda fuera de este tramo.

**Se ve listo cuando** se puede seguir un cliente desde el lead hasta la oportunidad ganada y ver aparecer su
proyecto con presupuesto.

Fuentes: `docs/diseno/v4/parte-2-crm.md`, `docs/analisis/bloques/crm-pantallas.md`.

## Tramo 2 · Del contrato al proyecto listo (flujo 1)

Contratos con hitos, garantías (cartas fianza), penalidad calculada y adendas; la fórmula polinómica del presupuesto
(2.14); el presupuesto de la empresa por sede y periodo que controla el gasto directo (2.13, plan en
`docs/planes/activos/2026-11-16-presupuesto-empresa.md`).

**Se ve listo cuando** el escenario del flujo 1 se recorre en pantalla y da sus números (margen 1,327.00; penalidad
205.96, en `parte-8-criterios-aceptacion.md`).

## Tramo 3 · De la necesidad al costo (flujo 2)

Requerimientos, cuadro comparativo de proveedores, órdenes de compra y de servicio (los doce tipos), almacén central
y de obra, recepción, despacho y series; reclasificación de imputaciones (2.8). Cada costo cae en su partida y el
saldo del proyecto se mueve.

**Se ve listo cuando** un requerimiento termina en material recibido en obra y el tablero del proyecto muestra el
costo en su partida (cable 1,248.00).

## Tramo 4 · La gente y el avance (flujos 3 y 10, parte de obra)

Colaboradores, habilitación de campo, asignación a proyectos y tareo (horas que se vuelven costo); valorizaciones y
subcontratos.

**Se ve listo cuando** las horas de una semana aparecen como costo en el proyecto y una valorización se arma desde
el avance.

## Tramo 5 · El ciclo del dinero (flujos 4, 5, 6 y 10)

Comprobantes por XML con validación SUNAT; cuentas por pagar con cruce de tres vías; el tablero de pagos donde
Tesorería ordena arrastrando tarjetas; notas por foto con IA; flujo de caja; moneda extranjera; plantillas de
asientos y exportación a Excel para el contador; servicios con vigencia; rendiciones y fondos por proyecto (caja
chica con incrementos); conciliación bancaria, detracciones y retenciones; liquidación, consorcios y cierre de
proyecto. Lo anotado para esta etapa está en `docs/planes/mapa-etapas.md`, «Etapa 3 · anotado antes de abrirla».

**Se ve listo cuando** un proyecto se cierra y su tablero responde la pregunta del sistema: cuánto se ganó de verdad.

## Tramo 6 · Recursos humanos (flujo 9)

Demanda de personal a 90 días, selección, contratos laborales, planilla con boletas, PLAME y asientos, diferencia
real contra estándar, competencias y salida.

**Se ve listo cuando** una planilla de demostración se genera completa con sus boletas.

## Tramo 7 · Importaciones, postventa y analítica (flujos 2 y 8)

Embarques con sus gastos y prorrateo, alerta de embarque en riesgo; tickets con SLA, garantías por serie y
suscripciones; tableros de analítica para Gerencia.

**Se ve listo cuando** Gerencia tiene su tablero con la cartera completa y un cliente puede abrir un ticket sobre un
equipo que se le vendió.

## Tramo 8 · Pulido

La mejora visual de conjunto (a cargo de Sebastián), lo que quedó a medias de la interfaz y el asistente de ayuda.
