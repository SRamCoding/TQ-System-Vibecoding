# v4 · La interfaz, los roles y el celular

> Extracto de texto de `fuentes/Techquk-Sistema-CRM-ERP-v4.pdf` (21 set 2026), líneas 2772 a 3068. Las tablas del PDF aparecen con columnas separadas por « | ». Si algo no se entiende, consulta el PDF.

```text
|  La interfaz y los roles
|  Una sola aplicación que a cada persona se le siente pequeña y a su medida.
Por qué una sola aplicación y no un lanzador tipo Okta
Se evaluó una interfaz tipo Okta: una pantalla de mosaicos donde cada área es una aplicación
separada. Tiene ventajas reales, porque cada área se siente simple y enfocada. Pero en Techquk
saldría más caro que útil, por tres razones. La gente cumple varios roles (Lisset presupuesta y lleva la
tesorería; Robert arma partidas y mira compras), y con aplicaciones separadas saltaría todo el día de
una a otra para hacer una sola tarea. Los flujos cruzan áreas todo el tiempo: una partida excedida lleva
a la orden, la orden a la recepción, la recepción a la factura, y cada salto entre aplicaciones rompe el
hilo. Y el buscador, las alertas y la bandeja de aprobaciones solo sirven si juntan todo.
La decisión es una propuesta intermedia: una sola aplicación con barra lateral, cuya pantalla de
inicio se ve como mosaicos.
La aplicación única
|  Techquk  |  Buscar OC-2026-0142, una serie, un RUC… Ctrl K  |  Alertas 3 RB  |  + Crear
|  Inicio  |  Inicio · control de partidas  |  Vista rápida
|  PC-04 · Hospital Regional
|  Mis bandejas 5  |  ÁRBOLES POR  |  PARTIDAS  |  NO  |  Presupuestado 9,200.00
|  APROBAR  |  EXCEDIDAS  |  PRESUPUESTADOS  |  Comprometido 10,856.00
|  OPERACIONES
|  Proyectos  |  2  |  3  |  S/ 45  |  LO QUE LO FORMÓ
|  OS-0061 SCTR · 1,240
|  Contratos  |  OS-0063 Movilidad · 2,100
|  Partida  |  %
|  Partidas  |  Ver todo ›
|  PC-02.01 Cable · Plaza de Armas  |  103 %
|  SUMINISTRO
|  PC-04 Generales · Hospital Regional  |  118 %
|  Compras
|  PC-99 No presupuestados · Plaza de Armas  |  1 gasto
|  Almacén
|  FINANZAS
|  Tesorería
|  Facturas
|  PERSONAS
|  RRHH y tareo
|  COMERCIAL
|  CRM
La aplicación única: barra lateral por áreas (solo lo que el rol permite), barra superior común, tablero al centro y vista rápida a
la derecha para revisar sin perder el lugar.
Tiene cuatro piezas. La barra lateral agrupa por áreas y muestra solo lo que el rol de cada persona
permite: el almacenero ve cuatro opciones, no treinta. La barra superior tiene tres herramientas que
se usan todo el tiempo: el buscador global (se escribe un número de orden, una serie o un RUC y se va
directo), el botón de crear y la campana de alertas. Al centro está el tablero de inicio del rol. Y a la
derecha, la vista rápida: al tocar algo, se abre su detalle sin abandonar la pantalla en la que se
estaba. Filament, el framework del equipo, trae de fábrica casi todo esto; el tablero de pagos con
tarjetas arrastrables y la pantalla de revisión de lo que lee la IA se construyen a medida sobre esa
base.
El inicio con mosaicos
|  Techquk  |  Buscar factura, proveedor, proyecto… Ctrl K  |  Alertas 2 LS  |  + Crear
|  ▦  |  Buenos días, Lisset
|  Sábado 19 de setiembre · 6 cosas pendientes hoy
|  ✉
|  $  |  Tesorería  |  Facturas  |  Garantías
|  4 pagos esta semana  |  3 por subir · 1 retenida  |  5 cartas fianza vigentes
|  ▤  |  Caja al límite el martes  |  1 observada  |  1 vence en 28 días
|  ∑
|  Presupuestos  |  Capturar notas  |  Proyectos
|  ▣
|  2 en armado  |  Foto de tu hoja  |  Solo lectura
|  ◉  |  LP-2026-014  |  1 hoja por revisar  |  6 en ejecución
|  CONTINUAR DONDE TE QUEDASTE
|  Tablero de pagos · semana 38  |  PRE-2026-034 · v2  |  E001-000347 retenida
La propuesta intermedia: el inicio se ve como mosaicos, pero todo vive en la misma aplicación. La barra lateral queda
encogida a íconos y el buscador y la campana son uno solo.
Lo que toma de Okta son los mosaicos como puerta de entrada, uno por cada área del rol, pero cada
mosaico dice qué espera ahí («4 pagos esta semana, caja al límite el martes»). Lo que conserva de la
aplicación única: la barra lateral encogida a íconos para saltar entre áreas sin volver al inicio, un solo
buscador y una sola campana, y «continuar donde te quedaste». POR VALIDAR Llevar esta versión y la
de barra lateral a las sesiones, y que Lisset y Robert elijan haciendo una tarea real.
El celular
|  Hola, J. Chero
|  Plaza de Armas
|  Registrar  |  Recibir en
|  tareo  |  obra
|  En el celular no va la barra lateral completa. Van botones grandes, uno por acción, y cada
|  persona ve solo los suyos. Aquí sí tiene sentido la lógica de mosaicos de Okta: no de
|  Rendir  |  Foto de  |  aplicaciones, sino de acciones.
|  viáticos  |  avance
|  Mis  |  Visita
|  aprobaciones  |  técnica
La versión móvil: seis acciones que se hacen en la calle o en la obra.
Los tableros de inicio
Cada tablero responde pocas preguntas concretas. La regla viene del CRM: lo que el sistema le pide a
alguien tiene que devolverle algo a él, no solo a su jefe. Si registrar el tareo solo sirve para que lo
supervisen, se va a registrar lo mínimo; si además le avisa qué partida se está desbordando, lo va a
usar.
|  Buenos días, R. Díaz  |  Martes 17 de setiembre
|  MIS PROYECTOS ACTIVOS  |  HITOS ESTA SEMANA  |  PARTIDAS EXCEDIDAS  |  PENALIDAD EN RIESGO
|  6  |  4  |  3  |  S/ 0
|  S/ 1 184 000 en contratos  |  1 vencido  |  en 2 proyectos  |  ningún contrato atrasado
|  ESTA SEMANA  |  DÓNDE SE VA EL COSTO  |  ESPERAN ALGO DE MÍ
|  Vencido ayer · Acta de entrega  |  Plaza de Armas · PC-02.01 Cable ·  |  2 requerimientos por aprobar
|  parcial · Hospital Regional  |  103 %
|  Tareo de la semana 37 sin cerrar
|  Jue 19 · Instalación de postes · Plaza  |  Hospital Regional · PC-04 Gastos
|  de Armas  |  generales · 118 %
|  1 partida sin responsable
|  Sáb 21 · Embarque EMB-031 · Data  |  EMB-031 llega 3 días después del
|  center  |  hito
Tablero de la Jefatura de Proyectos: qué vence, dónde se va el costo y qué espera alguien de mí.
|  Almacén · hoy  |  Martes 17 de setiembre
|  POR RECIBIR HOY  |  DESPACHOS PENDIENTES  |  BAJO MÍNIMO  |  DIFERENCIAS DE
|  INVENTARIO
|  3  |  2  |  4
|  órdenes  |  1 con series por elegir  |  productos  |  0
|  OC-2026-0144 · 3 switches · Prov. Andino · 10:00  |  REQ-2026-0311 · 4 cimentaciones para PRY-012 ·
|  despachar antes del 19
|  EMB-030 · sale de aduana · llega jueves
Tablero de Almacén. Almacén no piensa en módulos: piensa en qué entra y qué sale hoy.
|  Gerencia General  |  Setiembre 2026
|  CONTRATOS EN  |  MARGEN PONDERADO  |  PENALIDAD EN RIESGO  |  POR COBRAR +60 DÍAS
|  EJECUCIÓN
|  11.2 %  |  S/ 8 240  |  S/ 69 500
|  S/ 1 294 715  |  objetivo 12 %  |  1 contrato con hito vencido  |  2 entidades
|  8 contratos
|  MARGEN POR PROYECTO  |  CAJA  |  ESPERAN MI FIRMA
|  Ampliación CCTV  |  Punto más bajo: S/ 64,500 en la  |  Adenda CT-2026-011 · vence en 2
|  24.1 %  |  semana 39  |  días
|  Data center  |  9.8 %
|  Plaza de Armas  |  6.6 %  |  Con 30 días de atraso del Estado: S/  |  Gasto directo del mes: 94 % de lo
|  −12,300 en la semana 42  |  previsto
Tablero de Gerencia General: si se gana dinero con lo firmado, dónde puede perderse, y qué espera su firma.
Puestos, roles y la grilla
Para definir qué ve cada persona no hace falta ordenar primero toda la empresa. Hay que separar dos
cosas que suelen confundirse. El puesto es el cargo del organigrama. El rol es un paquete de
permisos del sistema: «puede registrar pagos», «puede ver márgenes», «puede aprobar hasta cierto
monto». Una persona puede tener varios roles y un rol lo pueden tener varias personas. Lisset no
necesita un puesto que diga «tesorera y presupuestista»: tiene dos roles, y el sistema le muestra la
suma. Si mañana otra persona asume la tesorería, se mueve ese rol y nada más. Por eso los 33 roles
del organigrama de agosto, pensados para una empresa ideal, no se copian al sistema.
La grilla se construye desde los flujos. En cada paso de cada flujo ya está escrito quién actúa. De ahí
sale la lista de acciones, que se agrupan en unos 12 a 15 roles, que se asignan a las 22 personas
reales. La mayor parte sigue la realidad tal como es, porque los roles son datos y se ajustan cuando la
empresa cambie. Pero en dos lugares conviene acercarse a lo ideal desde el primer día: la separación
de funciones (nadie pide y aprueba lo suyo, ni registra y aprueba el mismo pago) y los datos
sensibles (sueldos, exámenes médicos, costos y márgenes). Cuando en una empresa pequeña no
haya otra persona que pueda aprobar, no se prohíbe: se agrega un control compensatorio, y la
grilla muestra el cruce para que se decida a conciencia.
|  Grilla de roles · vista de conflictos  |  1 conflicto con control compensatorio
|  Acción  |  Tesorería  |  Preventa  |  Control de partidas  |  Jefatura de Proyectos  |  Compras
|  Armar presupuesto  |  ✓
|  Aprobar árbol de partidas  |  ✓
|  Emitir orden de compra  |  ✓
|  Aprobar orden  |  ✓
|  Registrar pago  |  ✓
|  Aprobar pago hasta U3  |  ✓ conflicto
|  PERSONAS Y ROLES  |  Lisset registra y aprueba el mismo pago. Control
|  Lisset: Tesorería + Preventa  |  compensatorio: Gerencia recibe cada lunes la lista de esos
|  Robert: Control de partidas  |  pagos.
|  R. Díaz: Jefatura de Proyectos
La grilla sale de los flujos: acciones agrupadas en roles, roles asignados a personas. El sistema detecta los cruces para que se
decidan a conciencia.
Qué ve y qué hace cada rol
|  Rol  |  Su inicio responde  |  Sus bandejas  |  En el celular
|  Gerencia General  |  ¿Ganamos dinero con lo firmado?  |  Aprobaciones sobre umbral,  |  Aprobar
|  ¿Dónde podemos perderlo?  |  controles compensatorios
|  Jefatura de  |  ¿Qué vence? ¿Dónde se va el  |  Requerimientos, no  |  Aprobar, ver
|  Proyectos  |  costo?  |  presupuestados, tareo por  |  partidas
|  cerrar
|  Especialista de  |  ¿Qué pedí y cuándo llega?  |  Mis requerimientos,  |  Pedir, recibir en
|  proyecto  |  recepciones en obra  |  obra, foto de avance
|  Control de partidas  |  ¿Qué árboles faltan? ¿Qué  |  Árboles por aprobar,  |  —
|  (Robert)  |  partidas se exceden?  |  sugerencias de importación
|  Preventa (Ronal,  |  ¿Qué presupuestos están en  |  Presupuestos por revisar,  |  —
|  Lisset)  |  armado?  |  lecciones de proyectos
|  cerrados
|  Administración de  |  ¿Cuántos días le quedan a cada  |  Conformidades, adendas,  |  Registrar
|  Contratos  |  contrato? ¿Qué penalidad está en  |  posibles adicionales  |  conformidad
|  riesgo?
|  Compras e  |  ¿Qué hay que comprar? ¿Qué  |  Requerimientos aprobados,  |  —
|  Importaciones  |  embarque está en riesgo?  |  gastos sin prorratear
|  Almacén  |  ¿Qué entra y qué sale hoy?  |  Recepciones esperadas,  |  Recibir con series,
|  despachos pendientes  |  despachar
|  Tesorería (Lisset)  |  ¿Cuánta caja tendremos? ¿Qué  |  Tablero de pagos, notas por  |  Foto de notas
|  cobro está atrasado?  |  revisar, garantías por vencer
|  Contabilidad  |  ¿Qué falta registrar o exportar?  |  Facturas por subir o retenidas,  |  —
|  exportaciones fallidas
|  RRHH  |  ¿Qué brecha de personal viene?  |  Contratos por vencer, planilla  |  —
|  ¿Qué habilitación vence?  |  del mes, salidas
|  Jefe de cuadrilla y  |  ¿Quién trabajó hoy y en qué?  |  Tareo, fondos por rendir  |  Tareo, rendir viáticos
|  técnico
|  Help Desk  |  ¿Qué ticket vence primero?  |  Tickets por SLA  |  Registrar visita
|  PARTE 6
```
