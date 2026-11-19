# v4 · Construcción, decisiones tomadas y lo que falta decidir

> Extracto de texto de `fuentes/Techquk-Sistema-CRM-ERP-v4.pdf` (21 set 2026), líneas 4456 a 4840. Las tablas del PDF aparecen con columnas separadas por « | ». Si algo no se entiende, consulta el PDF.

```text
|  Construcción y decisiones
|  En qué orden se construye, qué entra en cada entrega y qué falta decidir.
Orden de construcción
El documento 10 ordenaba por dependencias entre módulos. Este orden respeta esas dependencias,
pero agrupa por entregas que sirven solas: cada entrega le resuelve algo a alguien en Techquk, sin
esperar a la siguiente.
|  Entrega  |  Qué incluye  |  Qué resuelve
|  0 · Núcleo  |  Personas y roles con grilla y cruces, catálogos, usuarios y  |  Nada visible todavía, pero
|  permisos con restricción de campo, estados, numeración,  |  todo lo demás lo necesita
|  aprobaciones configurables desde pantalla, auditoría, alertas
|  personalizables, selector de imputación, centros de costo,
|  mecanismo común de IA, interfaz única con inicio por mosaicos
|  1 · Control de  |  Contratos y garantías (fianza o retención) · adelantos ·  |  La pregunta de fondo:
|  costos (MVP)  |  presupuesto con APU y fórmula polinómica · árbol de partidas con  |  cuánto cuesta cada
|  PC-99 · requerimientos, cuadro comparativo, órdenes con sus  |  proyecto frente a lo
|  doce tipos, subcontratos · gastos no presupuestados y posibles  |  presupuestado, partida
|  adicionales · recepción en almacén o en obra, despacho, series y  |  por partida, y ganar sin
|  guías · colaboradores, habilitación de campo, asignación y tareo ·  |  perder plazos
|  valorizaciones · presupuesto contra real · tableros · CRM completo
|  (cuentas, oportunidades, cotizaciones, licitaciones, actividades,
|  catálogo)
|  2 · Ciclo del  |  Comprobantes subidos por XML · cuentas por pagar con cruce de  |  Lo que hoy vive fuera de
|  dinero  |  tres vías · tablero de pagos · captura de notas por foto · flujo de  |  S10: cuánto nos deben,
|  caja · moneda extranjera · asientos por plantilla y exportación a  |  cuánto debemos, cuánta
|  Excel · servicios con vigencia · rendiciones · liquidación del  |  caja habrá, sin teclear dos
|  contrato · consorcios  |  veces
|  3 · RRHH  |  Demanda a 90 días · selección · contratos laborales · planilla con  |  La gente y su costo real
|  completo  |  boletas, PLAME y asientos · competencias y certificaciones ·  |  dentro del mismo sistema
|  salida
|  4·  |  Embarques, gastos, prorrateo, simulador, alerta de embarque en  |  Costo real de lo
|  Importaciones  |  riesgo  |  importado y riesgo de
|  plazo. POR VALIDAR
|  Puede adelantarse si
|  Gerencia lo prioriza
|  5 · Postventa y  |  Tickets, rastreo de serie para Help Desk, horas de desarrollo,  |  Lo que depende de
|  el resto  |  suscripciones, arrendamiento de equipos, campañas y leads,  |  validaciones o suma valor
|  analítica, importación asistida de partidas, MS Project  |  sobre lo anterior
Durante la entrega 1, facturación, pagos y contabilidad siguen donde están hoy. Como ya se decidió
que es una sola empresa, los programadores pueden empezar el núcleo en cuanto se elija el motor de
base de datos. El corte se hace por proyecto: los proyectos nuevos arrancan en el ERP. Con el núcleo,
los desarrolladores pueden empezar desde ya, porque casi no depende de lo que salga de las sesiones
con el personal.
Registro de decisiones tomadas
Cada decisión con su porqué, para que nadie la reabra sin saber por qué se tomó. Las fechas son de
las conversaciones de análisis; todas deben ratificarse con Gerencia en la primera reunión.
|  Decisión  |  Por qué
|  ERP propio desde cero, con S10 como referencia y no  |  Escribir en la base de S10 depende de
|  como base de datos  |  procedimientos sin contrato y del proveedor; el
|  paquete de agosto ya lo planteaba así
|  Una sola empresa  |  Simplifica el núcleo; es la pregunta que más movía
|  los cimientos
|  RRHH dentro del ERP, con planilla  |  Las horas y el costo de la gente son parte del costo
|  de cada proyecto
|  Umbrales, aprobadores, motivos y tipos configurables  |  La empresa cambia; no debe depender de un
|  desde pantalla  |  programador para ajustar reglas
|  El árbol de partidas lo diseñan Robert y el especialista en  |  Así trabajan hoy y cada proyecto llega con sus
|  cada proyecto; la propuesta desde el presupuesto es  |  convenciones
|  opcional
|  Tres clases de gasto: general del proyecto, general de la  |  Que ningún gasto del proyecto se esconda como
|  empresa y no presupuestado (PC-99)  |  gasto de la empresa, y detectar adicionales
|  cobrables
|  Los comprobantes de venta se emiten en el facturador  |  Emitir no es lo difícil; lo valioso es que el ERP sepa
|  actual y se suben por XML  |  qué se facturó y lo enlace
|  Las facturas de proveedores también se suben por XML  |  Datos exactos y cruce de tres vías sin digitar
|  Los asientos se generan por plantilla y se exportan al  |  Ahorra la digitación; los libros oficiales siguen
|  programa del contador  |  donde el contador trabaja
|  Tesorería con tablero de pagos por tarjetas, alertas  |  Respeta el criterio de Lisset y le quita el trabajo
|  propias y captura de notas por foto  |  manual
|  La IA como mecanismo del núcleo: sugiere, una persona  |  Automatiza lo tedioso sin perder control ni
|  confirma; nunca calcula ni aplica reglas  |  confianza
|  Interfaz única con barra lateral e inicio por mosaicos;  |  La gente cumple varios roles y los flujos cruzan
|  móvil por acciones  |  áreas
|  Roles separados de puestos; grilla construida desde los  |  No hace falta reordenar la empresa para empezar,
|  flujos, con separación de funciones y controles  |  pero sí proteger los controles
|  compensatorios
|  Entregas que sirven solas, empezando por el control de  |  Cada entrega le resuelve algo a alguien sin esperar
|  costos  |  a la siguiente
|  El gasto directo puede llevar proyecto o no; con proyecto  |  Una sola palabra, la que Techquk ya usa, con un
|  es la PC-99  |  solo campo opcional
|  Doce tipos de compra desde el inicio, configurables  |  Un inventario amplio evita que las cosas raras se
|  registren mal
|  El material va al almacén central o directo a la obra;  |  Así ocurre en Techquk; el almacén de obra es un
|  ambos con la misma pantalla  |  almacén más
|  Fórmula polinómica incluida, definida con el presupuesto  |  Es requisito en obras públicas con reajuste
|  y aplicada en cada valorización
|  Los asientos se exportan a Excel con formato fijo  |  Todos los programas contables importan desde
|  Excel; el contador no teclea
|  Flujo 10: adelantos, valorizaciones, liquidación,  |  Cubre los huecos de los contratos de obra pública
|  consorcios, retención, moneda y subcontratos, como un  |  sin construir siete módulos
|  mismo mecanismo en dos direcciones
|  Un solo documento y un solo prototipo para CRM y ERP  |  Es un solo sistema con dos caras
Qué falta decidir o confirmar
|  Tema  |  Qué hay que resolver  |  Con quién
|  Árbol de partidas  |  Quién responde por cada partida; cómo se ve un árbol real  |  Robert y especialistas
|  Centros de costo  |  Cuáles son los centros de costo reales de la empresa  |  Contabilidad
|  Obra pública  |  De los contratos de estos años, cuáles fueron obras, cuáles  |  Gerencia y
|  tuvieron adelanto, reajuste, consorcio o retención en lugar de  |  Administración de
|  fianza  |  Contratos
|  Normativa de obra  |  Deducciones de reajuste por adelantos, cuándo aplica la  |  Administración de
|  retención, plazos de liquidación, según el reglamento vigente de  |  Contratos
|  la Ley 32069
|  Presupuesto  |  Cuándo se usa APU completo y cuándo precio por ítem; fórmula  |  Ronal y Lisset
|  polinómica; umbral del camino corto
|  Tipos de compra  |  Cuáles ocurren y con qué frecuencia  |  Logística y compras
|  Almacén de obra  |  Cuánto material va directo a la obra  |  Almacén y
|  especialistas
|  RRHH y planilla  |  Quién hace hoy RRHH; con qué se calcula la planilla; quién tarea  |  Gerencia, RRHH y
|  en campo; cómo se contrata al eventual; si la diferencia real  |  contador
|  contra estándar se reparte a partidas
|  Aprobaciones  |  Valores iniciales de U1, U2, U3 (la pantalla ya permite  |  Gerencia
|  cambiarlos); quién autoriza excesos; desde qué monto el cuadro
|  es obligatorio
|  Tesorería  |  Dónde anota hoy pagos y cobros, cómo decide qué pagar, cómo  |  Lisset
|  se entera de vencimientos; ver una hoja real de sus notas y
|  diseñar el formato
|  Interfaz  |  Mosaicos o barra lateral como inicio, elegido con una tarea real  |  Lisset, Robert
|  Penalidades  |  Fórmula y factores según el reglamento vigente y las bases  |  Administración de
|  Contratos
|  Facturador actual  |  Qué programa es y si entrega el XML de cada comprobante  |  Contabilidad
|  Columnas del  |  Las columnas exactas que el programa del contador acepta al  |  Contador
|  Excel contable  |  importar
|  D6 · Desarrollo  |  Confirmar que solo las horas viven en el ERP  |  Jefatura de Desarrollo
|  D10 · Base de  |  SQL Server o PostgreSQL; el equipo conoce PostgreSQL y la  |  Equipo técnico
|  datos  |  búsqueda por similitud lo favorece
|  Prioridad de  |  Si va con la entrega 1 o después  |  Gerencia
|  importaciones
|  MS Project  |  Qué se espera derivar del cronograma; ver archivos reales  |  Ingeniero de proyectos
|  IA y datos  |  Qué información puede enviarse a un servicio externo  |  Gerencia
|  (presupuestos, fotos de notas, facturas) y qué nunca
|  Módulos  |  Si suscripciones y servicios gestionados tienen contratos reales  |  Gerencia y
|  especulativos  |  levantamiento
Inventario de pantallas
«Anatomía común» significa que la pantalla sigue la lista y la ficha genéricas descritas en el
documento del CRM y no necesita boceto propio.
|  Dónde aparece  |  Pantalla  |  Estado
|  Modelo mental  |  Selector de imputación  |  Boceto AJUSTE SET 2026
|  Modelo mental  |  Auditoría de sugerencias de IA  |  Boceto AJUSTE SET 2026
|  Modelo mental  |  Barra de estados del documento  |  Boceto genérico
|  CRM  |  Tablero del ejecutivo comercial  |  Boceto
|  CRM  |  Lista de cuentas  |  Boceto
|  CRM  |  Crear cuenta (dos estados)  |  Boceto
|  CRM  |  Ficha de cuenta  |  Boceto
|  CRM  |  Contactos con rotación  |  Boceto
|  CRM  |  Oportunidades · tablero  |  Boceto
|  CRM  |  Ficha de oportunidad  |  Boceto
|  CRM  |  Avance de etapa y cierre perdida  |  Boceto
|  CRM  |  Editor de cotización  |  Boceto
|  CRM  |  Licitación · cronograma y expediente  |  Boceto
|  CRM  |  Resultado de convocatoria  |  Boceto
|  CRM  |  Agenda y registro rápido  |  Boceto
|  CRM  |  Catálogo comercial  |  Boceto
|  CRM  |  Leads y conversión  |  Boceto
|  CRM  |  Tablero de Gerencia Comercial  |  Boceto
|  Frontera  |  Traspaso de oportunidad ganada a contrato  |  Boceto AJUSTE SET 2026
|  Flujo 1  |  Ficha de contrato con línea de tiempo  |  Boceto
|  Flujo 1  |  Garantías con costo  |  Boceto AJUSTE SET 2026
|  Flujo 1  |  Presupuesto con APU  |  Boceto AJUSTE SET 2026
|  Flujo 1  |  Árbol de partidas de control  |  Boceto AJUSTE SET 2026
|  Flujo 1  |  Importación asistida de partidas  |  Boceto FASE POSTERIOR
|  Flujo 1  |  Lista de contratos · lista de proyectos  |  Anatomía común del CRM
|  Flujo 2  |  Requerimiento con imputación por línea  |  Boceto
|  Flujo 2  |  Bandeja de aprobación única  |  Boceto
|  Flujo 2  |  Cuadro comparativo  |  Boceto
|  Flujo 2  |  Orden de compra o servicio  |  Boceto
|  Flujo 2  |  Seguimiento de embarques  |  Boceto
|  Flujo 2  |  Expediente de importación con prorrateo  |  Boceto
|  Flujo 2  |  Recepción contra orden con series  |  Boceto
|  Flujo 2  |  Despacho a obra con guía  |  Boceto
|  Flujo 2  |  Cuentas por pagar con cruce de tres vías  |  Boceto
|  Flujo 3  |  Tablero de asignación  |  Boceto
|  Flujo 3  |  Tareo semanal por partida  |  Boceto AJUSTE SET 2026
|  Flujo 3  |  Horas de desarrollo  |  Boceto
|  Flujo 3  |  Matriz de competencias y certificaciones  |  Anatomía común
|  Flujo 4  |  Simulador de penalidad  |  Boceto
|  Flujo 4  |  Bandeja por facturar  |  Boceto
|  Flujo 4  |  Estado de cuenta y antigüedad de deuda  |  Boceto
|  Flujo 4  |  Comprobantes observados  |  Boceto
|  Flujo 4  |  Adenda · conformidad · valorización  |  Anatomía común
|  Flujo 5  |  Tablero de pagos con tarjetas  |  Boceto AJUSTE SET 2026
|  Flujo 5  |  Alertas personalizables por rol  |  Boceto AJUSTE SET 2026
|  Flujo 5  |  Formato de papel para Tesorería  |  Boceto AJUSTE SET 2026
|  Flujo 5  |  Revisión de notas leídas por foto  |  Boceto AJUSTE SET 2026
|  Flujo 5  |  Flujo de caja proyectado  |  Boceto AJUSTE SET 2026
|  Flujo 5  |  Asiento generado por plantilla  |  Boceto AJUSTE SET 2026
|  Flujo 5  |  Configuración contable y exportación  |  Boceto
|  Flujo 6  |  Presupuesto contra real  |  Boceto
|  Flujo 6  |  Cierre de proyecto con lista de verificación  |  Boceto AJUSTE SET 2026
|  Flujo 7  |  Registro de gasto no presupuestado con motivo  |  Boceto AJUSTE SET 2026
|  Flujo 7  |  Servicios con vigencia  |  Boceto AJUSTE SET 2026
|  Flujo 7  |  Rendición de viáticos  |  Boceto AJUSTE SET 2026
|  Flujo 8  |  Bandeja de tickets por SLA  |  Boceto
|  Flujo 8  |  Rastreo de serie  |  Boceto
|  Flujo 8  |  Cartera de suscripciones  |  Boceto POR VALIDAR
|  Flujo 8  |  Ficha de ticket, visitas, informe de servicio  |  Anatomía común
|  Flujo 9  |  Demanda de personal a 90 días  |  Boceto AJUSTE SET 2026
|  Flujo 9  |  Ficha de colaborador con habilitación de campo  |  Boceto AJUSTE SET 2026
|  Flujo 9  |  Planilla del periodo  |  Boceto AJUSTE SET 2026
|  Flujo 9  |  Selección, contrato laboral, desvinculación  |  Anatomía común AJUSTE SET 2026
|  Flujo 10  |  Adelantos del contrato  |  Boceto AJUSTE SET 2026
|  Flujo 10  |  Fórmula polinómica  |  Boceto AJUSTE SET 2026
|  Flujo 10  |  Valorización con reajuste y amortización  |  Boceto AJUSTE SET 2026
|  Flujo 10  |  Subcontrato con valorizaciones  |  Boceto AJUSTE SET 2026
|  Flujo 10  |  Consorcio  |  Boceto AJUSTE SET 2026
|  Flujo 10  |  Garantía por retención y moneda  |  Boceto AJUSTE SET 2026
|  Flujo 10  |  Liquidación del contrato  |  Boceto AJUSTE SET 2026
|  Flujo 10  |  Equipos propios y arrendamiento  |  Boceto FASE POSTERIOR
|  Interfaz  |  Aplicación única con vista rápida  |  Boceto AJUSTE SET 2026
|  Interfaz  |  Inicio con mosaicos por rol  |  Boceto AJUSTE SET 2026
|  Interfaz  |  Versión móvil por acciones  |  Boceto AJUSTE SET 2026
|  Roles  |  Tablero de Jefatura de Proyectos  |  Boceto
|  Roles  |  Tablero de Almacén  |  Boceto
|  Roles  |  Tablero de Gerencia General  |  Boceto
|  Roles  |  Grilla de roles con conflictos  |  Boceto AJUSTE SET 2026
78 entradas en el inventario, 73 con boceto. Especificación funcional: si el modelo de datos distribuye las entidades de otra forma, manda el
modelo. Datos de ejemplo ficticios.
```
