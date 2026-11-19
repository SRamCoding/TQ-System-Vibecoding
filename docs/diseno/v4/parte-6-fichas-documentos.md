# v4 · Las fichas de documentos: campos, estados, transiciones y efectos

> Extracto de texto de `fuentes/Techquk-Sistema-CRM-ERP-v4.pdf` (21 set 2026), líneas 3069 a 3916. Las tablas del PDF aparecen con columnas separadas por « | ». Si algo no se entiende, consulta el PDF.

```text
|  Las fichas de documentos
|  La referencia para programar: qué guarda cada documento, por qué estados pasa y qué produce
|  cada cambio.
Cada ficha tiene tres partes. Los campos clave, que no son todos los campos sino los que tienen
alguna regla. La tabla de transiciones, que es lo más importante: desde qué estado, con qué acción,
a qué estado, quién puede hacerlo, y qué efectos produce en otros documentos o en los números de
las partidas. Y las validaciones que bloquean una acción. Anular, en todos los documentos, exige
motivo y revierte los efectos de la última transición; no se repite en cada tabla.
|  Contrato
|  La cara hacia el cliente de lo que se ganó.
|  Numeración: CT-AAAA-NNNN
|  CAMPOS CLAVE
|  Campo  |  Nota
|  Origen  |  Oportunidad ganada o convocatoria adjudicada del CRM; manual solo con
|  motivo
|  Monto, plazo, forma de  |  Heredados de la cotización aceptada; editables hasta activar
|  pago
|  Hitos  |  Fecha comprometida, entregable, si habilita facturación
|  Fórmula de penalidad  |  Por defecto la de las bases; ajustable con permiso
|  Plazo vigente  |  Calculado: plazo original más adendas aprobadas con resolución
|  ESTADOS Y TRANSICIONES
|  Desde  |  Acción  |  Hacia  |  Quién  |  Efectos
|  —  |  Generar desde CRM  |  Borrador  |  Sistema  |  Crea el proyecto en «en preparación»
|  Borrador  |  Activar  |  Vigente  |  Adm.  |  Programa alertas de hitos; empieza a
|  Contratos  |  correr el plazo
|  Vigente  |  Registrar adenda  |  Vigente  |  Adm.  |  Recalcula plazo vigente y penalidad
|  aprobada  |  Contratos
|  Vigente  |  Conformidad final  |  Por  |  Adm.  |  Habilita facturación final; inicia plazo de
|  liquidar  |  Contratos  |  devolución de garantías
|  Por  |  Liquidar  |  Cerrado  |  Adm.  |  Congela penalidades aplicadas
|  liquidar  |  Contratos
|  VALIDACIONES
|  No se activa sin al menos un hito y la forma de pago
|  No se cierra con hitos pendientes ni garantías vigentes sin decisión
|  Garantía (carta fianza)  |  AJUSTE SET 2026
|  El compromiso bancario y su costo.
|  Numeración: GAR-AAAA-NNNN
|  CAMPOS CLAVE
|  Campo  |  Nota
|  Tipo  |  Fiel cumplimiento, adelanto, seriedad de oferta
|  Monto garantizado, banco, vencimiento  |  Obligatorios
|  Orden de su costo  |  Orden de servicio de tipo «garantía financiera», con imputación
|  Condición de devolución  |  Qué conformidad la libera
|  ESTADOS Y TRANSICIONES
|  Desde  |  Acción  |  Hacia  |  Quién  |  Efectos
|  Solicitada  |  Registrar  |  Vigente  |  Tesorería  |  Programa alertas a 60, 30 y 15 días
|  emisión
|  Vigente  |  Renovar  |  Vigente  |  Tesorería  |  Nuevo vencimiento; historial de renovaciones;
|  nueva orden de costo si hay comisión
|  Vigente  |  Liberar  |  Liberada  |  Adm.  |  Requiere la conformidad que la condiciona
|  Contratos
|  Liberada  |  Registrar  |  Devuelta  |  Tesorería  |  Cierra alertas
|  devolución
|  VALIDACIONES
|  Toda garantía registra su costo, aunque sea cero
|  No se libera sin la conformidad que la condiciona
|  Presupuesto  |  AJUSTE SET 2026
|  El cálculo de cuánto cuesta y cuánto se cobra, con análisis de precios unitarios.
|  Numeración: PRE-AAAA-NNNN · versión
|  CAMPOS CLAVE
|  Campo  |  Nota
|  Títulos y partidas  |  Árbol con metrado por partida
|  APU por partida  |  Insumos con cantidad, desperdicio o rendimiento y precio
|  Gastos generales y utilidad  |  Porcentaje o monto
|  Oportunidad y proyecto  |  Enlace, sin copia
|  ESTADOS Y TRANSICIONES
|  Desde  |  Acción  |  Hacia  |  Quién  |  Efectos
|  Borrador  |  Enviar a  |  En revisión  |  Preventa  |  —
|  revisión
|  En  |  Aprobar  |  Aprobado  |  Gerencia  |  Queda disponible para la oferta y, al ganar,
|  revisión  |  Comercial  |  para el proyecto
|  Aprobado  |  Nueva  |  Borrador  |  Preventa  |  La versión anterior queda congelada
|  versión  |  (v+1)
|  VALIDACIONES
|  La lista de insumos consolidada suma exactamente el costo directo
|  Una versión aprobada no se edita
|  Árbol de partidas de control  |  AJUSTE SET 2026
|  Cómo se vigila el costo de un proyecto.
|  Numeración: PC-NN.NN por proyecto
|  CAMPOS CLAVE
|  Campo  |  Nota
|  Padre  |  Profundidad libre; lo diseñan Robert y el especialista
|  Presupuestado  |  Solo en hojas; los padres suman
|  Origen  |  Referencia opcional a la partida o insumo del presupuesto
|  Responsable  |  Una persona por partida
|  PC-99  |  Creada por el sistema al aprobar; no se borra; presupuestado cero
|  ESTADOS Y TRANSICIONES
|  Desde  |  Acción  |  Hacia  |  Quién  |  Efectos
|  Borrador  |  Proponer desde  |  Borrador  |  Robert  |  Propone un nodo por título y partida
|  presupuesto
|  (opcional)
|  Borrador  |  Aprobar  |  Aprobado  |  Jefatura de  |  Crea la PC-99; pasa el proyecto a «en
|  Proyectos  |  ejecución»; habilita el selector de
|  imputación
|  Aprobado  |  Nueva versión  |  Borrador  |  Robert  |  Las partidas con costos cargados se
|  (v+1)  |  conservan
|  VALIDACIONES
|  La suma de las hojas es igual al costo presupuestado
|  Una partida con costos no se elimina ni se mueve de rama sin dejar rastro
|  Requerimiento  |  AJUSTE SET 2026
|  Lo que el proyecto necesita.
|  Numeración: REQ-AAAA-NNNN
|  CAMPOS CLAVE
|  Campo  |  Nota
|  Líneas  |  Ítem, cantidad, tipo de compra, imputación, fecha necesaria
|  Atención  |  Por stock (reserva) o por compra
|  ESTADOS Y TRANSICIONES
|  Desde  |  Acción  |  Hacia  |  Quién  |  Efectos
|  Borrador  |  Enviar  |  Por aprobar  |  Solicitante  |  Revisa stock y saldo de cada partida
|  Por  |  Aprobar  |  Aprobado  |  Según matriz  |  Las líneas con stock quedan reservadas
|  aprobar  |  (comprometido); las demás pasan a Compras
|  Aprobado  |  Atender  |  Atendido parcial  |  Compras,  |  Se cierra cuando todas sus líneas tienen orden
|  / atendido  |  Almacén  |  o despacho
|  VALIDACIONES
|  Toda línea tiene tipo de compra e imputación
|  Una línea que excede su partida exige motivo
|  Orden de compra o de servicio
|  El compromiso con un proveedor.
|  Numeración: OC-AAAA-NNNN · OS-AAAA-NNNN
|  CAMPOS CLAVE
|  Campo  |  Nota
|  Proveedor  |  De la tabla única de personas
|  Líneas  |  Heredadas del requerimiento o del cuadro comparativo, con imputación
|  Lugar de entrega  |  Almacén central o almacén de obra
|  Cuadro comparativo  |  Obligatorio sobre el umbral que se defina
|  ESTADOS Y TRANSICIONES
|  Desde  |  Acción  |  Hacia  |  Quién  |  Efectos
|  Borrador  |  Enviar a aprobación  |  Por aprobar  |  Compras  |  —
|  Por aprobar  |  Aprobar  |  Aprobada  |  Según matriz  |  Comprometido en cada
|  partida
|  Aprobada  |  Enviar  |  Enviada  |  Compras  |  Aviso al proveedor
|  Enviada  |  Recepción o  |  Recibida parcial /  |  Almacén o  |  Ver ficha de recepción
|  conformidad  |  recibida  |  solicitante
|  Recibida  |  Cerrar saldo  |  Cerrada  |  Compras  |  Libera el comprometido no
|  parcial  |  recibido
|  VALIDACIONES
|  No se aprueba sin imputación en todas las líneas
|  Una orden aprobada no se edita
|  Recepción (ingreso)
|  Lo que efectivamente llegó.
|  Numeración: ING-AAAA-NNNN
|  CAMPOS CLAVE
|  Campo  |  Nota
|  Orden de origen  |  Obligatoria, salvo devolución o ajuste
|  Almacén  |  Central o de obra
|  Series  |  Obligatorias para ítems seriados
|  Costo unitario  |  El de la orden; en importaciones, provisional hasta el prorrateo
|  ESTADOS Y TRANSICIONES
|  Desde  |  Acción  |  Hacia  |  Quién  |  Efectos
|  Borrador  |  Confirmar  |  Confirmada  |  Almacén o  |  Almacén central: sube stock. Almacén de obra:
|  especialista  |  ejecutado en la partida. Actualiza la orden.
|  Habilita el cruce con la factura
|  VALIDACIONES
|  No se recibe más de lo ordenado sin aprobación
|  Un ítem seriado no se confirma sin todas sus series
|  Despacho (salida a obra)
|  El material que sale del almacén hacia un proyecto.
|  Numeración: DES-AAAA-NNNN
|  CAMPOS CLAVE
|  Campo  |  Nota
|  Imputación  |  Partida de control del proyecto destino
|  Series  |  Se eligen las que salen
|  Guía de remisión  |  Se genera si sale del local
|  ESTADOS Y TRANSICIONES
|  Desde  |  Acción  |  Hacia  |  Quién  |  Efectos
|  Borrador  |  Despachar  |  Despachado  |  Almacén  |  Baja stock; ejecutado en la partida al costo
|  del almacén; genera la guía
|  Despachado  |  Registrar devolución  |  Despachado  |  Almacén  |  Sube stock; revierte ejecutado
|  de obra
|  VALIDACIONES
|  No se despacha sin stock suficiente
|  No se despacha un ítem seriado sin elegir series
|  Parte de horas (tareo y desarrollo)  |  AJUSTE SET 2026
|  Las horas de una persona en una semana.
|  Numeración: HOR-AAAA-SS-persona
|  CAMPOS CLAVE
|  Campo  |  Nota
|  Líneas  |  Día, horas, imputación
|  Costo hora  |  De la tabla de categorías vigente a la fecha
|  ESTADOS Y TRANSICIONES
|  Desde  |  Acción  |  Hacia  |  Quién  |  Efectos
|  Abierto  |  Enviar  |  Enviado  |  Jefe de cuadrilla o  |  —
|  desarrollador
|  Enviado  |  Cerrar  |  Cerrado  |  Jefatura de Proyectos o de  |  Comprometido y ejecutado en
|  semana  |  Desarrollo  |  cada partida
|  Cerrado  |  Ajustar  |  Cerrado  |  Jefatura  |  Ajuste con historial; reclasifica entre
|  partidas
|  VALIDACIONES
|  No se registran horas de obra a una persona sin SCTR vigente para el proyecto
|  Más de 12 horas en un día requiere motivo
|  Factura de proveedor  |  AJUSTE SET 2026
|  Lo que el proveedor cobra, subida con su XML.
|  Numeración: Serie y número del proveedor
|  CAMPOS CLAVE
|  Campo  |  Nota
|  XML  |  Fuente de los datos; si solo hay PDF, lectura por visión con revisión
|  Validación SUNAT  |  Al subir
|  Orden  |  Obligatoria, salvo aprobación con motivo
|  Cruce  |  Orden · recepción o conformidad · factura
|  ESTADOS Y TRANSICIONES
|  Desde  |  Acción  |  Hacia  |  Quién  |  Efectos
|  —  |  Subir XML  |  Registrada  |  Contabilidad  |  Valida ante SUNAT; asocia a la
|  orden; genera asiento por plantilla
|  Registrada  |  Cruzar  |  Lista para pago /  |  Sistema  |  Si coincide, aparece como tarjeta en
|  retenida  |  el tablero de pagos
|  Retenida  |  Liberar  |  Lista para pago  |  Según  |  Queda marcada para revisión
|  matriz
|  Lista para  |  Programar y  |  Pagada  |  Tesorería  |  Pagado en la partida; asiento de
|  pago  |  aplicar pago  |  pago
|  VALIDACIONES
|  No se registra dos veces la misma serie y número del mismo proveedor
|  Un comprobante no válido ante SUNAT no se registra
|  Comprobante de venta y cobranza  |  AJUSTE SET 2026
|  Lo que Techquk factura (en su facturador) y cobra.
|  Numeración: Serie y correlativo del facturador
|  CAMPOS CLAVE
|  Campo  |  Nota
|  XML  |  Subido desde el facturador; fuente de los datos
|  Hito  |  Sugerido por el sistema, confirmado por la persona
|  Retención o detracción  |  Registrada aparte
|  ESTADOS Y TRANSICIONES
|  Desde  |  Acción  |  Hacia  |  Quién  |  Efectos
|  —  |  Subir XML  |  Registrado  |  Administración  |  Valida ante SUNAT; cuenta por cobrar;
|  asiento de venta
|  Registrado  |  Asociar a hito  |  Asociado  |  Administración  |  El hito queda facturado
|  Asociado  |  Aplicar  |  Cobrado parcial /  |  Tesorería  |  Baja la cuenta por cobrar; saldo a
|  cobranza  |  cobrado  |  favor si sobra
|  VALIDACIONES
|  No se asocia a un hito sin conformidad o valorización
|  Un comprobante válido solo se corrige con nota de crédito o débito
|  Gasto no presupuestado  |  AJUSTE SET 2026
|  Un gasto del proyecto que no estaba en su presupuesto. Es una línea de orden, rendición o
|  despacho imputada a la PC-99.
|  Numeración: La del documento que lo origina
|  CAMPOS CLAVE
|  Campo  |  Nota
|  Motivo  |  Lista cerrada configurable
|  Comentario  |  Obligatorio
|  Posible adicional  |  Automático si el motivo es cambio pedido por el cliente
|  ESTADOS Y TRANSICIONES
|  Desde  |  Acción  |  Hacia  |  Quién  |  Efectos
|  Borrador  |  Enviar  |  Por aprobar  |  Solicitante  |  Muestra el nuevo margen estimado
|  Por  |  Aprobar  |  Aprobado  |  Jefatura de  |  Comprometido en PC-99; baja el margen
|  aprobar  |  Proyectos  |  estimado; alerta si es posible adicional
|  Aprobado  |  Reclasificar  |  Reclasificado  |  Jefatura de  |  Mueve el costo a otra partida (existente o
|  Proyectos  |  creada por una adenda)
|  VALIDACIONES
|  No se aprueba sin motivo y comentario
|  Siempre requiere aprobación, aunque el monto sea menor al umbral
|  Sugerencia de IA  |  AJUSTE SET 2026
|  Lo que propone el mecanismo común de IA en cualquier proceso.
|  Numeración: SUG-AAAA-NNNNNN
|  CAMPOS CLAVE
|  Campo  |  Nota
|  Proceso  |  Cuál de los procesos activados la generó
|  Origen  |  Archivo o foto, siempre adjunto
|  Propuesta  |  Datos propuestos con su seguridad por campo
|  Explicación  |  Por qué lo propuso, en una línea
|  ESTADOS Y TRANSICIONES
|  Desde  |  Acción  |  Hacia  |  Quién  |  Efectos
|  —  |  Generar  |  Por  |  Sistema  |  Aparece en la bandeja de quien confirma
|  revisar
|  Por  |  Aceptar / corregir /  |  Resuelta  |  Persona  |  Crea o actualiza el documento real; guarda la
|  revisar  |  rechazar  |  corrección como aprendizaje
|  VALIDACIONES
|  Nunca crea un documento sin confirmación
|  No procesa datos marcados como sensibles
|  Asiento generado  |  AJUSTE SET 2026
|  El registro contable de una operación, armado por plantilla.
|  Numeración: AS-AAAA-MM-NNNN
|  CAMPOS CLAVE
|  Campo  |  Nota
|  Documento de origen  |  La operación que lo generó
|  Plantilla  |  Definida por el contador
|  Líneas  |  Cuenta, debe, haber, proyecto o centro de costo
|  ESTADOS Y TRANSICIONES
|  Desde  |  Acción  |  Hacia  |  Quién  |  Efectos
|  —  |  Documento llega a su  |  Generado  |  Sistema  |  —
|  estado
|  Generado  |  Exportar  |  Exportado /  |  Contabilidad  |  Archivo en el formato del programa
|  fallido  |  del contador
|  Fallido  |  Configurar plantilla y  |  Exportado  |  Contabilidad  |  —
|  reintentar
|  VALIDACIONES
|  Debe y haber siempre suman lo mismo
|  Una operación sin plantilla no genera asiento: queda en fallidos
|  Colaborador, contrato laboral y habilitación  |  AJUSTE SET 2026
|  La persona, su vínculo con la empresa y lo que necesita para trabajar en obra.
|  Numeración: COL-NNNN · CTL-AAAA-NNNN
|  CAMPOS CLAVE
|  Campo  |  Nota
|  Contrato  |  Modalidad, vigencia, remuneración, régimen de pensiones; eventual enlazado al
|  proyecto
|  Habilitación  |  Examen ocupacional (solo resultado fuera de RRHH), SCTR por proyecto, inducción,
|  equipo de protección
|  Roles del  |  Asignados aparte del puesto
|  sistema
|  ESTADOS Y TRANSICIONES
|  Desde  |  Acción  |  Hacia  |  Quién  |  Efectos
|  Candidato  |  Contratar  |  Activo  |  RRHH  |  Crea usuario si corresponde; alerta
|  si falta habilitación
|  Activo  |  Vence un requisito de  |  No  |  Sistema  |  Bloquea asignación a obra y tareo
|  habilitación  |  habilitado
|  No  |  Regularizar o autorizar  |  Activo  |  RRHH o  |  Desbloquea
|  habilitado  |  con motivo  |  Gerencia
|  Activo  |  Iniciar salida  |  En salida  |  RRHH  |  Lista de traspasos; revoca accesos
|  al terminar
|  En salida  |  Liquidar  |  Cesado  |  RRHH  |  Liquidación en planilla; asiento
|  VALIDACIONES
|  No se tarea en obra a quien no está habilitado para ese proyecto
|  El detalle médico solo lo ve RRHH
|  Planilla del periodo  |  AJUSTE SET 2026
|  El cálculo mensual de remuneraciones.
|  Numeración: PLA-AAAA-MM
|  CAMPOS CLAVE
|  Campo  |  Nota
|  Conceptos  |  Tablas configurables con vigencia (tasas, topes, conceptos)
|  Insumos  |  Contratos, asistencias, tareo, licencias
|  ESTADOS Y TRANSICIONES
|  Desde  |  Acción  |  Hacia  |  Quién  |  Efectos
|  Abierta  |  Calcular  |  Calculada  |  RRHH  |  —
|  Calculada  |  Cerrar  |  Cerrada  |  RRHH · visto bueno  |  Boletas; archivo PLAME; asientos; diferencia
|  del contador  |  real contra estándar por proyecto
|  VALIDACIONES
|  No se cierra con tareo de la quincena sin cerrar
|  Una planilla cerrada se corrige con una planilla de ajuste
|  Adelanto (del cliente a Techquk, o de Techquk al subcontratista)
|  AJUSTE SET 2026
|  Dinero recibido o entregado antes del avance, que se descuenta después.
|  Numeración: ADL-AAAA-NNNN
|  CAMPOS CLAVE
|  Campo  |  Nota
|  Tipo  |  Directo o de materiales
|  Monto y %  |  Sobre el contrato
|  Garantía  |  Enlace a la garantía que lo respalda
|  Amortizado  |  Calculado desde las valorizaciones
|  ESTADOS Y TRANSICIONES
|  Desde  |  Acción  |  Hacia  |  Quién  |  Efectos
|  Registrado  |  Recibir o entregar  |  Vigente  |  Tesorería  |  Entra o sale de la caja; asiento de anticipo
|  Vigente  |  Valorización  |  Vigente  |  Sistema  |  Descuenta la parte proporcional; reduce el por
|  aprobada  |  amortizar
|  Vigente  |  Saldo cero  |  Amortizado  |  Sistema  |  Permite reducir o liberar la garantía
|  VALIDACIONES
|  La suma de amortizaciones nunca supera el monto del adelanto
|  Valorización  |  AJUSTE SET 2026
|  El cálculo del avance de un periodo para cobrarlo (del cliente) o pagarlo (al subcontratista).
|  Numeración: VAL-contrato-NN
|  CAMPOS CLAVE
|  Campo  |  Nota
|  Avance  |  Metrado ejecutado por partida × precio contractual
|  Reajuste  |  Factor de la fórmula polinómica con los índices del periodo
|  Amortizaciones  |  De cada adelanto vigente
|  Retención y penalidades  |  Según el contrato
|  Neto  |  Calculado
|  ESTADOS Y TRANSICIONES
|  Desde  |  Acción  |  Hacia  |  Quién  |  Efectos
|  Borrador  |  Enviar  |  Por  |  Jefatura de Proyectos  |  —
|  aprobar
|  Por  |  Aprobar  |  Aprobada  |  Adm. Contratos  |  Cliente: habilita comprobante por el
|  aprobar  |  (cliente) o Jefatura  |  neto. Subcontrato: ejecutado en
|  (subcontrato)  |  partidas y cuenta por pagar
|  Aprobada  |  Observada por  |  Borrador  |  Adm. Contratos  |  Registra la observación
|  la entidad
|  VALIDACIONES
|  No se aprueba una valorización de un periodo con la anterior en borrador
|  Una valorización aprobada no se edita: se corrige en la siguiente
|  Fórmula polinómica  |  AJUSTE SET 2026
|  La regla de reajuste de precios de un contrato de obra.
|  Numeración: Parte del presupuesto
|  CAMPOS CLAVE
|  Campo  |  Nota
|  Monomios  |  Coeficiente e índice base de cada uno; suman 1
|  Índices del mes  |  Cargados mensualmente desde la fuente oficial
|  Deducciones  |  Por adelantos, según configuración del contrato
|  ESTADOS Y TRANSICIONES
|  Desde  |  Acción  |  Hacia  |  Quién  |  Efectos
|  Borrador  |  Aprobar con el presupuesto  |  Vigente  |  Preventa  |  Disponible para las valorizaciones
|  Vigente  |  Cargar índices del mes  |  Vigente  |  Administración  |  Recalcula el factor del periodo
|  VALIDACIONES
|  Los coeficientes suman exactamente 1
|  No se valoriza un periodo sin sus índices cargados
|  Liquidación del contrato  |  AJUSTE SET 2026
|  El cierre económico hacia la entidad o el subcontratista.
|  Numeración: LIQ-contrato
|  CAMPOS CLAVE
|  Campo  |  Nota
|  Conceptos  |  Contratado, adicionales, reducciones, reajustes, penalidades, adelantos, retenciones, cobrado
|  Plazos  |  Para presentar, para que la entidad se pronuncie
|  ESTADOS Y TRANSICIONES
|  Desde  |  Acción  |  Hacia  |  Quién  |  Efectos
|  Borrador  |  Presentar  |  Presentada  |  Adm.  |  Inicia el plazo de la entidad
|  Contratos
|  Presentada  |  Observación  |  Observada  |  Adm.  |  Vuelve a borrador con la observación
|  Contratos
|  Presentada  |  Aprobación  |  Aprobada  |  Adm.  |  —
|  Contratos
|  Aprobada  |  Consentimiento  |  Consentida  |  Adm.  |  Habilita devolución de garantías; permite
|  Contratos  |  cerrar el proyecto
|  VALIDACIONES
|  No se presenta con valorizaciones pendientes de aprobar
|  Consorcio  |  AJUSTE SET 2026
|  La agrupación con otras empresas para un contrato.
|  Numeración: CSR-AAAA-NNN
|  CAMPOS CLAVE
|  Campo  |  Nota
|  Integrantes y %  |  Suman 100
|  Operador  |  Quién factura a la entidad
|  Modo  |  Solo participación de Techquk, o todo el contrato con porcentajes
|  ESTADOS Y TRANSICIONES
|  Desde  |  Acción  |  Hacia  |  Quién  |  Efectos
|  Registrado  |  Enlazar  |  Activo  |  Adm.  |  Las valorizaciones y costos se reparten según el
|  contrato  |  Contratos  |  modo
|  Activo  |  Liquidar  |  Cerrado  |  Adm.  |  Reparto final de resultados
|  Contratos
|  VALIDACIONES
|  Los porcentajes suman exactamente 100
|  Subcontrato  |  AJUSTE SET 2026
|  Un tipo de compra con alcance, valorizaciones, adelantos, retención y liquidación.
|  Numeración: SC-AAAA-NNN
|  CAMPOS CLAVE
|  Campo  |  Nota
|  Alcance  |  Asignado a partidas de control
|  Retención  |  % de garantía sobre cada valorización
|  Adelantos  |  Los que Techquk entrega
|  ESTADOS Y TRANSICIONES
|  Desde  |  Acción  |  Hacia  |  Quién  |  Efectos
|  Borrador  |  Aprobar  |  Vigente  |  Según matriz  |  Comprometido en las partidas del alcance
|  Vigente  |  Valorización  |  Vigente  |  Jefatura de  |  Ejecutado en partidas; cuenta por pagar
|  aprobada  |  Proyectos  |  por el neto
|  Vigente  |  Liquidar  |  Liquidado  |  Jefatura de  |  Devuelve la retención; cierra el
|  Proyectos  |  compromiso
|  VALIDACIONES
|  No se liquida con valorizaciones pendientes
Siguen la misma forma, con fichas más cortas en la especificación detallada: adenda, conformidad,
valorización, cuadro comparativo, expediente de importación y prorrateo, guía de remisión, servicio
con vigencia, rendición de viáticos, pago, ticket y suscripción.
|  PARTE 7
```
