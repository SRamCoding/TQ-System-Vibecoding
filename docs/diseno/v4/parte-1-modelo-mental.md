# v4 · El modelo mental: cuatro ideas, costo y dinero, IA, caso de ejemplo y glosario

> Extracto de texto de `fuentes/Techquk-Sistema-CRM-ERP-v4.pdf` (21 set 2026), líneas 68 a 450. Las tablas del PDF aparecen con columnas separadas por « | ». Si algo no se entiende, consulta el PDF.

```text
|  El modelo mental
|  Si se entienden estas páginas, el resto del documento se lee solo.
Qué es este ERP y qué no es
Es el sistema con el que Techquk va a ejecutar lo que gana: sus contratos, sus proyectos, lo que
compra, lo que guarda, las horas de su gente, lo que factura, cobra y paga. Empieza donde termina el
CRM: cuando una oportunidad se gana, el CRM genera el contrato, y desde ahí todo ocurre en el ERP.
Los dos son un solo sistema con dos caras, que comparten el registro de personas y empresas y el
catálogo.
No es una copia de S10. Toma de S10 lo que Techquk ya usa y funciona —el presupuesto con análisis
de precios unitarios, las partidas de control, la tabla única de personas, las aprobaciones configurables
— y resuelve lo que hoy vive fuera de S10: el ciclo del dinero (facturar, cobrar, pagar), las horas de los
técnicos, las garantías y los vencimientos. Durante la transición conviven, y algunas cosas, como la
contabilidad, pueden seguir un tiempo en S10.
Varias fronteras ya están decididas. Es un sistema para una sola empresa. Incluye Recursos
Humanos con planilla. Los comprobantes de venta se siguen emitiendo en el facturador de
siempre y se suben al ERP, que los lee y los enlaza. Los asientos contables se generan solos por
plantillas y se exportan en Excel para el programa donde el contador lleva los libros oficiales. Y todo lo
configurable —umbrales, aprobadores, motivos, alertas, tipos de compra— se cambia desde
pantalla, sin programar.
Idea 0 · Un sistema con dos caras
La cara comercial (CRM) responde «¿vamos a ganar este negocio?» y la cara operativa (ERP) responde
«¿estamos cumpliendo y ganando dinero con lo que firmamos?». Comparten el registro de clientes, el
catálogo y el presupuesto, y se pasan el testigo en un solo momento: cuando una oportunidad se
gana. Las cuatro ideas siguientes son del ERP, pero el CRM las respeta: sus documentos también
recorren estados, y su IA también sugiere sin decidir.
Idea 1 · El proyecto es el eje
Casi todo lo que ocurre en el ERP pertenece a un proyecto, y todo proyecto nace de un contrato. El
contrato es la cara hacia el cliente: plazos, hitos, garantías, penalidades. El proyecto es la cara hacia
adentro: presupuesto, partidas, compras, personas, avance. Un contrato puede tener más de un
proyecto (por ejemplo, un frente de hardware y otro de software), pero un proyecto siempre tiene un
contrato, salvo los proyectos internos, que se marcan como tales.
Idea 2 · Todo costo termina en una partida de control o en
el gasto directo
Es la idea más importante del sistema y la que más faltaba en la propuesta de agosto. Cada proyecto
tiene un árbol de partidas de control: la forma en que Techquk decidió vigilar su costo, armada a
partir del presupuesto. Y cada línea de costo —de una orden, de un despacho de almacén, de una hora
de tareo, de una rendición de viáticos, de un gasto de importación— tiene que decir a dónde va: a una
partida de un proyecto, o al gasto directo de la empresa, con su centro de costo. No existe una
tercera opción ni una línea sin destino. Eso es lo que permite, al final, comparar lo presupuestado con
lo real sin armar una hoja de cálculo.
|  IMPUTAR A
|  SALDO DE LA PARTIDA
|  Proyecto › partida  |  Gasto directo  |  (con proyecto →  |  Presupuestado S/ 1,512.00
|  PC-99 · sin  |  Comprometido con esta línea S/ 1,560.00
|  proyecto → centro  |  Excede en S/ 48.00
|  de costo)  |  Se puede continuar, pero la línea se marca y exige motivo y
|  PROYECTO  |  aprobación.
|  PRY-2026-012 · Videovigilancia Plaza de Armas
|  SI FUERA GASTO DIRECTO
|  PARTIDA DE CONTROL
|  Centro de costo: Administración · Comercial · Operaciones
|  PC-02.01 · Cable › Cableado y postes  |  · Suministro · RRHH
|  Busca por código o nombre en todo el árbol y muestra la ruta
|  completa.
Selector de imputación. El saldo se calcula en el momento: quien pide ve si se pasa antes de pedir, no un mes después.
Con esto quedan claras tres clases de gasto que en la conversación diaria se mezclan:
|  Clase  |  Ejemplo  |  Dónde cae
|  Gasto general del  |  SCTR del personal, movilidad a  |  La partida de gastos generales del árbol (PC-04),
|  proyecto  |  la obra, comisión de su carta  |  presupuestada
|  fianza
|  Gasto general de la  |  Alquiler de la oficina, póliza  |  Gasto directo sin proyecto, con su centro de costo
|  empresa  |  del local, honorarios del
|  contador
|  Gasto del proyecto no  |  Un taxi imprevisto a la obra,  |  Gasto directo con proyecto: cae en la partida «No
|  presupuestado  |  dos cámaras que pidió el  |  presupuestados» (PC-99) de ese proyecto, con motivo
|  cliente  |  y aprobación
Decisión: el gasto directo puede estar enlazado a un proyecto o no. Es un solo concepto con un
campo de proyecto opcional. Si lleva proyecto, es exactamente la PC-99 de ese proyecto (con centro
de costo de referencia); si no, va al centro de costo de la empresa. Así la palabra que Techquk ya usa
sigue significando lo mismo, y el sistema decide dónde cae sin que nadie tenga que aprender una
clasificación nueva.
La tercera es la más valiosa. Cada proyecto nace con una partida PC-99 que empieza en cero y que
nadie puede borrar. Así ningún gasto del proyecto se esconde como gasto de la empresa (lo que haría
ver al proyecto más rentable de lo que es), y cuando el motivo es un cambio pedido por el cliente, el
sistema lo marca como posible adicional para que se pueda cobrar. AJUSTE SET 2026
El selector se construye una sola vez y aparece en todos los documentos que llevan costo.
|  AJUSTE SET 2026  |  En S10 cada línea de pedido ya lleva su partida de control; así controla hoy Techquk.
|  POR VALIDAR  |  Falta confirmar cómo agrupa S10 el gasto directo y cuáles son los centros de costo
reales.
Idea 3 · Todo es un documento que recorre estados y
produce efectos
Un requerimiento, una orden, una recepción, una factura: casi todo en el ERP es un documento con
una secuencia de estados. Lo que importa no es solo en qué estado está, sino qué efecto produce
cada cambio. Cuando se confirma una recepción, sube el stock; cuando se despacha a obra, el costo
pasa a la partida; cuando se registra una conformidad, se habilita facturar. Los efectos son lo que
conecta los módulos, y por eso la Parte 4 los describe uno por uno. Dos reglas valen para todos: un
documento aprobado no se edita (se anula con motivo o se corrige con otro documento), y nada se
borra.
|  OC-2026-0142 · Proveedor Andino S.A.C.  |  Aprobada  |  Anular  |  Enviar al proveedor
|  Borrador  |  Por aprobar  |  Aprobada  |  Enviada  |  Recibida parcial  |  Recibida  |  Cerrada
|  Las acciones de la cabecera dependen del estado: en «Aprobada» solo se puede enviar o anular con motivo.
La barra de estados es un componente único. Cada tipo de documento declara sus estados y transiciones como datos; la
pantalla no los programa.
Idea 4 · La inteligencia artificial sugiere, una persona
decide
A lo largo del diseño apareció un mismo patrón una y otra vez: leer un presupuesto que llega en otro
formato, leer una factura en PDF, leer las notas en papel de Tesorería, relacionar un pago con su
factura, detectar que un gasto podría cobrarse como adicional. En todos los casos la IA hace el trabajo
pesado de leer y proponer, y una persona revisa y confirma. Por eso no se construye como una función
suelta de cada módulo, sino como un mecanismo del núcleo, igual que las aprobaciones o la
auditoría. AJUSTE SET 2026
La regla para saber dónde usarla es simple. La IA sirve cuando la entrada es desordenada (una foto,
un PDF, un Excel ajeno) o cuando hace falta criterio para relacionar cosas. No se usa para calcular
ni para aplicar reglas: el costo unitario, la penalidad, el stock, el cruce de tres vías y los asientos
contables tienen una sola respuesta correcta y se programan de forma fija.
|  Proceso  |  Qué lee o relaciona  |  Quién confirma
|  Árbol de partidas desde  |  Presupuestos del cliente, Excel, cronogramas de MS Project  |  Robert
|  archivos
|  Comprobantes sin XML  |  PDF o foto de una factura  |  Contabilidad
|  Notas de Tesorería  |  Foto de la hoja de registro en papel  |  Lisset
|  Enlaces sugeridos  |  Un pago con su factura, un comprobante con su hito, un  |  Quien registra
|  cobro con su cuenta por cobrar
|  Posible adicional  |  Gastos no presupuestados cuyo motivo es un cambio  |  Administración de
|  pedido por el cliente  |  Contratos
|  Rendición de viáticos  |  Foto de boletas  |  Jefatura del técnico
|  Datos de un contrato  |  Hitos, plazos y penalidades del PDF del contrato  |  Administración de
|  FASE POSTERIOR  |  Contratos
|  Partida sugerida  |  La partida probable de una línea de requerimiento, según  |  Solicitante
|  el historial FASE POSTERIOR
Cada sugerencia se guarda igual, sea del proceso que sea: de dónde salió (el archivo o la foto, siempre
adjunta), qué propuso con la seguridad de cada dato, por qué lo propuso en una línea, y qué hizo la
persona. Con eso las correcciones se vuelven aprendizaje (lo que Robert corrige una vez no se vuelve
a preguntar), se puede medir cuánto acierta cada proceso, y siempre se sabe qué vino de la máquina
y qué decidió una persona.
|  Sugerencias de la IA · auditoría  |  Por proceso
|  Proceso  |  Sugerencias del mes Aceptadas sin cambios Corregidas Rechazadas Activo
|  Lectura de notas por foto  |  46  |  71 %  |  24 %  |  5 % sí
|  Asociar comprobante a hito  |  12  |  92 %  |  8%  |  0 % sí
|  Árbol desde archivos  |  3  |  40 %  |  60 %  |  0 % sí
|  Sugerir partida en requerimientos  |  —  |  —  |  —  |  — no
|  Cada sugerencia guarda de dónde salió, qué propuso con su seguridad por campo, por qué, y qué hizo la persona. Con esto se decide
|  dónde la IA ayuda y dónde hace perder tiempo.
El mecanismo común de IA medido por proceso. Se activa o desactiva por configuración.
Las reglas del mecanismo son cinco. La IA nunca guarda nada sola. Siempre existe el camino manual,
para que nadie quede bloqueado si falla. Cada proceso se activa o desactiva por configuración. Los
datos sensibles, como los exámenes médicos o los sueldos, nunca se envían a un servicio externo, y
Gerencia autoriza qué sí puede enviarse. Y aunque un proceso acierte casi siempre, todo lo que mueve
dinero conserva la aprobación humana: es lo que hace que la gente confíe en el sistema.
Cómo se mueven el costo y el dinero
El sistema mide cada partida con cuatro números, y conviene que todos —usuarios y programadores—
los entiendan igual, porque la mayoría de las discusiones sobre «cuánto llevamos gastado» vienen de
mezclarlos.
|  Número  |  Qué significa  |  Cuándo cambia
|  Presupuestado  |  Lo que se decidió gastar en  |  Al aprobar el árbol de partidas; después solo con una nueva
|  esa partida  |  versión aprobada
|  Comprometido  |  Lo que ya se decidió gastar  |  Al aprobar una orden de compra o servicio, al aprobar una
|  aunque todavía no haya  |  reserva de stock, al cerrar una semana de tareo
|  llegado
|  Ejecutado  |  Lo que ya se consumió de  |  Al despachar material a obra (o recibirlo directamente en
|  verdad  |  obra), al dar conformidad a un servicio, al cerrar el tareo, al
|  aprobar una rendición
|  Pagado  |  Lo que ya salió de la caja  |  Al aplicar un pago a la factura del proveedor. Es una medida
|  de caja, no de costo
De ahí salen dos cálculos: el disponible de una partida (presupuestado menos comprometido) y el
margen estimado del proyecto (venta sin IGV menos el mayor entre presupuestado y comprometido
de cada partida). El comprometido nunca puede ser menor que el ejecutado.
Del lado del ingreso ocurre algo parecido, en tres pasos: lo contratado (el monto del contrato y sus
adendas), lo facturado (comprobantes aceptados por SUNAT, que solo se emiten con conformidad o
valorización aprobada) y lo cobrado (cobranzas aplicadas). La diferencia entre facturado y cobrado es
la cuenta por cobrar, y la diferencia entre lo que entra y lo que sale, semana a semana, es el flujo de
caja.
|  EL COSTO
|  Presupuestado  |  Comprometido  |  Ejecutado  |  Pagado
|  árbol aprobado  |  ›  |  órdenes y reservas  |  ›  |  despacho, conformidad,  |  ›  |  pago aplicado
|  tareo
|  EL INGRESO
|  Contratado  |  Facturable  |  Facturado  |  Cobrado
|  contrato y adendas  |  ›  |  conformidad o  |  ›  |  aceptado por SUNAT  |  ›  |  cobranza aplicada
|  valorización
|  El margen real de un proyecto es lo cobrable menos lo ejecutado. La caja es lo cobrado menos lo pagado. Son preguntas
|  distintas, y el sistema responde las dos.
Los dos caminos que recorre cualquier proyecto. Cada flecha es un cambio de estado de algún documento.
El caso que se sigue en todo el documento
Para que los flujos se puedan comprobar con números, todo el documento sigue el mismo proyecto. Es
el mismo caso usado para explicar el análisis de precios unitarios.
|  Videovigilancia Plaza de Armas
|  Cliente  |  Contrato
|  Municipalidad Provincial de Chiclayo  |  CT-2026-018 · S/ 24,715.10 inc. IGV · 30 días
|  Alcance  |  Fechas
|  10 cámaras domo IP, 4 postes, 600 m de cable,  |  firma 1 set · conformidad 30 set · hoy es martes
|  NVR, instalación  |  17 set
|  Costo presupuestado  |  Utilidad presupuestada
|  S/ 19,525.00 (directo 17,750 + generales  |  S/ 1,420.00 · 6.8 % de la venta sin IGV
|  1,775)
|  Garantía  |  Penalidad
|  carta fianza de S/ 2,471.51 · comisión S/ 180  |  S/ 205.96 por día · tope S/ 2,471.51
Glosario
|  Término  |  Qué significa
|  Insumo  |  Todo lo que cuesta: material, mano de obra, equipo o subcontrato, con
|  su precio.
|  Partida  |  Una unidad de trabajo con su unidad de medida: «instalación de cámara,
|  unidad».
|  APU  |  Análisis de Precios Unitarios: la receta de cuántos insumos lleva una
|  unidad de partida. Da el costo unitario.
|  Rendimiento y desperdicio  |  Cuánto trabajo se hace por hora (0.05 h por metro de cable) y cuánto
|  material se pierde (5 %).
|  Metrado  |  Cuántas unidades de una partida lleva el proyecto.
|  Costo directo  |  Suma de metrado por costo unitario de todas las partidas.
|  Gastos generales  |  Lo que el proyecto necesita para existir y no pertenece a una partida:
|  SCTR, pólizas, movilidad, carta fianza.
|  Partida de control  |  Nodo del árbol por proyecto contra el que se carga cada costo real.
|  Gasto directo  |  En el lenguaje de Techquk: gasto que no pertenece a ningún proyecto.
|  Ojo: en presupuestos, «directo» suele significar lo contrario.
|  Imputación  |  El destino de costo de una línea: una partida de control o el gasto directo
|  con su centro de costo.
|  Presupuestado, comprometido,  |  Los cuatro números de cada partida; ver «Cómo se mueven el costo y el
|  ejecutado, pagado  |  dinero».
|  Carta fianza  |  Garantía bancaria exigida por la entidad. Tiene un costo (comisión) y una
|  vida propia (vencimiento, renovación, devolución).
|  Conformidad  |  Acta con la que el cliente acepta un entregable. Es requisito para
|  facturarlo.
|  Valorización  |  Cálculo del avance de un periodo para cobrarlo, en contratos que pagan
|  por avance.
|  Tareo  |  Registro de horas por trabajador, por día y por partida.
|  Cruce de tres vías  |  Comparar orden, recepción y factura del proveedor antes de pagar.
|  Prorrateo  |  Reparto de los gastos de una importación entre los ítems importados.
|  SCTR  |  Seguro Complementario de Trabajo de Riesgo, obligatorio para el
|  personal de obra.
|  SLA  |  Nivel de servicio comprometido: en cuánto tiempo se atiende un
|  reclamo.
|  Partida PC-99  |  La partida «No presupuestados» que todo proyecto tiene desde que se
|  aprueba su árbol. Empieza en cero.
|  Posible adicional  |  Gasto no presupuestado causado por un cambio pedido por el cliente,
|  que podría cobrarse con una adenda.
|  XML del comprobante  |  El archivo con los datos exactos de una factura electrónica. Acompaña
|  siempre al PDF.
|  Plantilla contable  |  La regla que dice en qué cuentas se registra cada tipo de operación. La
|  define el contador.
|  Rol y puesto  |  El puesto es el cargo del organigrama; el rol es un paquete de permisos
|  del sistema. Una persona puede tener varios roles.
|  Separación de funciones  |  Que la misma persona no pida y apruebe, ni registre y apruebe, la
|  misma operación.
|  Habilitación de campo  |  Lo que alguien necesita vigente para trabajar en obra: examen
|  ocupacional, SCTR, inducción y equipo de protección.
|  PLAME y T-Registro  |  La declaración mensual de planilla y el registro de trabajadores ante
|  SUNAT.
|  MVP  |  La primera versión que ya sirve para trabajar de verdad.
|  PARTE 2
```
