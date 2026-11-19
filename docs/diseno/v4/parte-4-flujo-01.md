# v4 · Flujo 1 · Del contrato al proyecto listo para ejecutar

> Extracto de la parte 4 de la especificación v4. Fichas de sus documentos: `parte-6-fichas-documentos.md`. Escenarios de prueba: `parte-8-criterios-aceptacion.md`.

```text
Flujo 1 · Del contrato al proyecto listo para ejecutar
Empieza cuando el CRM marca una oportunidad como ganada y termina cuando el proyecto tiene
presupuesto aprobado, árbol de partidas aprobado y garantía emitida. Ese es el momento en que
alguien puede pedir su primera compra. Es el flujo más corto y el más importante: si el presupuesto y
el árbol quedan mal, todo lo que se mida después se mide contra una referencia equivocada.
|  1  |  Se registra el contrato
|  Administración de Contratos · desde el CRM
|  Al ganar, el CRM genera el contrato con cliente, monto y condiciones de la cotización aceptada.
|  Administración de Contratos completa lo que la cotización no trae: número de contrato de la
|  entidad, fecha de firma, plazo, hitos, forma de pago y la fórmula de penalidad de las bases.
|  CT-2026-018 · Videovigilancia
|  Vigente  |  Registrar adenda  |  Registrar conformidad  |  Abrir proyecto
|  Plaza de Armas
|  Resumen  |  Hitos  |  Garantías  |  Adendas  |  Conformidades  |  Penalidades  |  Documentos  |  Historial
|  PLAZO
|  Firma  |  Entrega de  |  Instalación  |  Conformidad  |  Fin de garantía
|  1 set  |  equipos  |  25 set  |  30 set  |  30 set 2028  |  13 días
|  15 set ✓  |  restantes de 30
|  Entidad  |  Origen
|  Municipalidad Provincial de Chiclayo  |  AS-2026-022 · ganada el 25 ago (CRM)  |  PENALIDAD DIARIA
|  Monto contractual  |  Plazo  |  S/ 205.96
|  S/ 24,715.10 inc. IGV  |  30 días calendario  |  tope S/ 2,471.51 (10 %)
|  Forma de pago  |  Garantía técnica
|  100 % a la conformidad  |  24 meses
|  CARTA FIANZA FIEL CUMPLIMIENTO
|  S/ 2,471.51 · vence 30 oct
Ficha de contrato. A la derecha, los tres números que alguien tiene que mirar todos los días: días restantes, penalidad diaria y
garantía.
La penalidad diaria no se escribe: se calcula. En contratos públicos, con la fórmula habitual de 0.10 ×
monto ÷ (F × plazo), con F = 0.40 para plazos de hasta 60 días, da S/ 205.96 por día. POR VALIDAR La
fórmula y los factores deben verificarse contra el reglamento vigente de la Ley 32069 y las bases de
cada contrato; el sistema permite ajustarlos por contrato.
|  Qué queda registrado · contrato vigente con hitos y penalidad calculada · alertas programadas para cada
|  hito · un proyecto en estado «en preparación» enlazado
|  2  |  Se gestiona la carta fianza
|  Tesorería (Lisset)
|  La entidad exige una garantía de fiel cumplimiento del 10 %. Tesorería la tramita con el banco y
|  la registra con sus dos caras: el monto garantizado y su vencimiento, y la comisión del banco,
|  que entra como orden de servicio imputada a gastos generales del proyecto.
|  Garantías · CT-2026-018  |  + Registrar garantía
|  Garantía  |  Banco  |  Monto garantizado Vence  |  Costo (comisión) Orden de su costo  |  Estado
|  Fiel cumplimiento  |  Banco Norte  |  2,471.51 30 oct  |  180.00 OS-2026-0049 › PC-04  |  vigente
|  Cara 1 · la garantía. Monto, vencimiento, renovaciones  |  Cara 2 · el costo. La comisión del banco es un servicio y
|  y devolución. No es gasto.  |  se imputa, normalmente a gastos generales del proyecto.
|  Solicitada  |  Emitida  |  Vigente  |  Renovada  |  Liberada  |  Devuelta
Garantías del contrato, con sus dos caras en la misma fila.
|  AJUSTE SET 2026  |  La propuesta de agosto tenía la garantía pero no su costo, ni sus renovaciones, ni la
fecha de devolución.
|  Qué queda registrado · garantía vigente con alertas a 60, 30 y 15 días · orden de servicio OS-2026-0049 por
|  S/ 180 imputada a PC-04
|  3  |  Se trae el presupuesto
|  Preventa y Costeo (Ronal y Lisset)
|  El presupuesto ya existe: se armó antes de ofertar, con análisis de precios unitarios, y la
|  oportunidad del CRM lo tenía enlazado. Al ganarse no se copia: el mismo presupuesto pasa al
|  proyecto y queda congelado como versión aprobada.
|  Presupuesto PRE-2026-031 · Plaza de Armas  |  Aprobado  |  Nueva versión  |  Exportar
|  Ítem  |  Descripción  |  Und Metrado  |  C. unit.  |  Parcial  |  APU · 02.01 TENDIDO DE CABLE · POR M
|  Insumo  |  Cant. Precio Parcial
|  01  |  CÁMARAS  |  6,430.00
|  Cable UTP  |  1.05 m  |  2.40  |  2.52
|  01.01  |  Instalación de cámara domo  |  und  |  10  |  643.00  |  6,430.00  |  Cat6
|  IP
|  Técnico  |  0.05 h  |  18.00  |  0.90
|  02  |  CABLEADO Y POSTES  |  6,772.00
|  Ayudante  |  0.05 h  |  12.00  |  0.60
|  02.01  |  Tendido de cable UTP  |  m  |  600  |  4.02  |  2,412.00
|  Costo  |  4.02
|  02.02  |  Instalación de poste metálico  |  und  |  4 1,090.00  |  4,360.00  |  unitario
|  03  |  CENTRO DE MONITOREO  |  4,548.00  |  1.05 es el desperdicio (5 %); 0.05 h, el
|  rendimiento (20 m por hora).
|  03.01  |  NVR con almacenamiento  |  und  |  1 4,308.00  |  4,308.00
|  03.02  |  Pruebas y puesta en marcha  |  glb  |  1  |  240.00  |  240.00  |  LISTA DE INSUMOS
|  Cable 630 m · Técnico 80 h · Ayudante
|  Costo directo  |  17,750.00
|  74 h · … total 17,750.00 ✓
|  Gastos generales 10 %  |  1,775.00
|  Utilidad 8 %  |  1,420.00
|  IGV 18 %  |  3,770.10
|  TOTAL  |  24,715.10
Presupuesto con títulos, partidas y metrados; a la derecha el análisis de la partida seleccionada. La lista de insumos debe
sumar exactamente el costo directo.
|  AJUSTE SET 2026 En agosto el presupuesto era una lista plana de montos. Techquk presupuesta con
análisis de precios unitarios en S10 (44 presupuestos con miles de líneas). POR VALIDAR Confirmar en
qué contratos se usa el análisis completo y en cuáles basta el precio por ítem de la cotización
(suministro simple). Decisión: la fórmula polinómica de reajuste se incluye; se define con el
presupuesto y se aplica en cada valorización (flujo 10).
|  4  |  Se arma y aprueba el árbol de partidas de control
|  Robert con los especialistas · aprueba la Jefatura de Proyectos
|  El árbol lo diseñan Robert y el especialista para cada proyecto, según cómo quieren vigilar su
|  costo; en el ejemplo separaron material de mano de obra. Pueden partir de cero o pedirle al
|  sistema una propuesta desde el presupuesto y ajustarla. Cada partida recibe un responsable. Al
|  aprobarse, el sistema agrega la partida PC-99 de no presupuestados. La única regla fija: la suma
|  del árbol debe ser igual al costo presupuestado.
|  Partidas de control · PRY-
|  Proponer desde presupuesto (opcional)  |  Importar archivo  |  Aprobar árbol
|  2026-012
|  Origen en el  |  PROFUNDIDAD
|  Código  |  Partida  |  Responsable Presupuestado
|  presupuesto  |  Libre: una partida puede
|  PC-01  |  Cámaras  |  Esp. CCTV  |  6,430.00 título 01  |  tener hijas sin límite de
|  niveles.
|  PC-  |  Suministro de cámaras Esp. CCTV  |  5,830.00 01.01 · materiales
|  01.01  |  y conectores
|  REAGRUPAR
|  PC-  |  Mano de obra de  |  Esp. CCTV  |  600.00 01.01 · mano de obra  |  Lo diseñan Robert y el
|  01.02  |  instalación  |  especialista. En este
|  proyecto separaron
|  PC-02  |  Cableado y postes  |  Esp. redes  |  6,772.00 título 02
|  material de mano de obra.
|  PC-  |  Cable  |  Esp. redes  |  1,512.00 02.01 · materiales
|  02.01  |  CONTROL
|  PC-  |  Postes y cimentación  |  Esp. redes  |  3,880.00 02.02 · materiales  |  Si la suma no es igual al
|  02.02  |  costo presupuestado, no
|  se puede aprobar.
|  PC-  |  Mano de obra  |  Esp. redes  |  1,380.00 02.01 + 02.02 ·
|  02.03  |  mano de obra
|  PC-03  |  Centro de monitoreo  |  Esp. CCTV  |  4,548.00 título 03
|  PC-04  |  Gastos generales de  |  R. Díaz  |  1,775.00 gastos generales
|  obra
|  PC-99  |  No presupuestados  |  R. Díaz  |  0.00 creada por el sistema
|  automática
|  Total controlado  |  19,525.00 = costo
|  presupuestado ✓
Árbol de partidas de control. La columna de origen dice de qué parte del presupuesto salió cada monto.
Cuando el presupuesto llega en formatos distintos (un Excel del cliente, un cronograma de MS Project),
existe una variante asistida: el sistema lee los archivos, propone el árbol, indica de qué fila salió cada
partida y con qué certeza coincide con partidas existentes, y Robert decide solo lo dudoso.
|  FASE POSTERIOR  |  Requiere autorización de Gerencia para enviar presupuestos a un servicio externo.
|  Importar partidas · 2 archivos  |  propuesta generada  |  Aprobar árbol
|  Partida propuesta  |  Fila de origen  |  Coincide con  |  Certeza  |  Decisión
|  Suministro de cámaras  |  presupuesto_cliente.xlsx · fila 14  |  PC-01.01 existente  |  alta  |  se reutiliza
|  Tendido UTP exterior  |  cronograma.xml · tarea 2.3  |  PC-02.01 Cable  |  media  |  Es la misma  |  Es nueva
|  Obras civiles menores  |  presupuesto_cliente.xlsx · fila 31  |  —  |  baja  |  se crea nueva
|  Suma del árbol propuesto: S/ 19,525.00 · igual al presupuesto ✓ · cada «es la misma» se guarda como equivalencia.
Importación asistida. El sistema propone; Robert decide solo lo dudoso. Fusionar por error es peor que duplicar.
|  Qué queda registrado · presupuesto PRE-2026-031 aprobado y congelado · árbol de 9 partidas por S/
|  19,525.00, cada una con responsable, más la PC-99 en cero · proyecto PRY-2026-012 pasa a «en ejecución» ·
|  presupuestado de cada partida disponible para el selector de imputación
|  CUANDO LAS COSAS NO SALEN COMO SE ESPERA
|  El árbol no cuadra con el presupuesto. No se puede aprobar; la pantalla muestra la
|  diferencia y en qué rama está.
|  El alcance cambia a mitad del proyecto (adenda de adicional o reducción). Se crea una
|  nueva versión del presupuesto y del árbol; la anterior se conserva, y los costos ya cargados
|  siguen en sus partidas.
|  Contrato chico de suministro sin análisis de precios. El presupuesto se genera desde las
|  líneas de la cotización del CRM, con una partida por familia de producto. Es el «camino corto»
|  que el documento 10 pide para contratos menores. POR VALIDAR Definir el umbral.
|  No hay presupuesto previo (una prueba en S10 mostró partidas creadas directo en el
|  proyecto). El árbol se crea a mano, pero el presupuestado de cada partida debe cargarse antes
|  de aprobar.
```
