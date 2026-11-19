# Glosario

Términos que aparecen en el código y en los documentos. Si uno falta y causó confusión, agrégalo.

## Las ideas del sistema

- **Proyecto como eje** — Casi todo pertenece a un proyecto, y todo proyecto nace de un contrato (salvo los internos).
- **Partida de control** — Nodo del árbol de un proyecto contra el que se carga cada costo real. El árbol se arma desde el presupuesto y la suma de sus hojas es igual al costo presupuestado.
- **PC-99** — La partida «No presupuestados». La crea el sistema al aprobar el árbol, empieza en cero y no se borra. Todo lo que cae ahí exige motivo y aprobación.
- **PC-04** — Gastos generales del proyecto (SCTR, movilidad, comisión de la carta fianza).
- **Gasto directo** — En el lenguaje de Techquk, gasto que **no** pertenece a ningún proyecto. Va a un centro de costo.
- **Imputación** — El destino de costo de una línea: una partida de control o el gasto directo con su centro de costo. Nunca una línea sin destino.
- **Presupuestado, comprometido, ejecutado, pagado** — Los cuatro números de cada partida: lo que se decidió gastar; lo ya ordenado o reservado; lo ya consumido; lo ya salido de caja.
- **Margen estimado** — Venta sin IGV menos el mayor entre presupuestado y comprometido, partida por partida.
- **Posible adicional** — Gasto no presupuestado causado por un cambio pedido por el cliente; podría cobrarse con una adenda.
- **Efecto** — Lo que produce una transición de estado en otros documentos o números (por ejemplo, despachar ejecuta el costo en la partida).
- **Acción registrada** — Una operación de usuario definida con sus campos y permiso, ejecutable sin interfaz.
- **Rol y puesto** — El puesto es el cargo del organigrama; el rol es un paquete de permisos. Una persona puede tener varios roles.
- **Restricción por campo** — Un permiso que oculta un dato (costos, sueldos, detalle médico), no un módulo entero.
- **Plantilla** — Un punto de partida guardado para armar un documento (un árbol de partidas, un presupuesto): al usarla se **copia** a una propuesta que se edita antes de crear el documento, y nunca queda enlazada. Es personal o de la empresa y tiene versiones; el documento dice de cuál salió («Videovigilancia v3»). ADR 0022.
- **Plantilla contable** — Otra cosa (etapa 3): la regla que dice en qué cuentas va cada tipo de operación al armar un asiento. No es una plantilla del ADR 0022: no se copia a un documento, se aplica cada vez.

## Presupuesto y obra

- **APU** — Análisis de Precios Unitarios: la receta de insumos de una unidad de partida.
- **Metrado** — Cuántas unidades de una partida lleva el proyecto.
- **Rendimiento y desperdicio** — Trabajo por hora y material que se pierde.
- **Fórmula polinómica** — Regla de reajuste de precios de un contrato de obra según índices oficiales.
- **Valorización** — Cálculo del avance de un periodo para cobrarlo (o pagarlo a un subcontratista).
- **Adelanto** — Dinero recibido o entregado antes del avance, que se descuenta en valorizaciones.
- **Liquidación** — Cierre económico del contrato con la entidad o con el subcontratista.
- **Consorcio** — Agrupación con otras empresas para un contrato; los porcentajes suman 100.

## Comercial (CRM)

- **Cuenta** — La ficha comercial de una empresa o persona: quién la atiende (su ejecutivo), de dónde llegó y si ya es cliente o todavía prospecto.
- **Oportunidad** — Un negocio en curso con una cuenta: cuánto podría valer, cuándo se espera cerrar y en qué etapa está.
- **Embudo** — Las oportunidades abiertas, ordenadas por etapa: Prospecto, Calificada, Propuesta enviada y Negociación. Terminan ganadas, perdidas o anuladas.
- **Etapa** — El estado de una oportunidad. Se avanza de una en una y cada etapa exige lo suyo (por ejemplo, para calificar: necesidad, presupuesto y decisor).
- **Probabilidad** — La da la etapa (10, 25, 50 y 75 %, configurables): nadie la edita en la oportunidad.
- **Ponderado** — Monto de la oportunidad por la probabilidad de su etapa. El ponderado del embudo es la suma, en soles.
- **Estancada** — Una oportunidad que lleva en su etapa más días que el máximo de esa etapa.
- **Competidor** — Otra empresa que compite por una oportunidad; si la gana, queda con su precio para el histórico.

## Compras, almacén y personas

- **Tipo de compra** — Uno de doce tipos configurables (bien para proyecto, importación, servicio puntual, subcontrato, servicio con vigencia, garantía financiera, viáticos, alquiler, honorarios, software, combustible y peajes, bien para la empresa).
- **Cuadro comparativo** — Comparación de cotizaciones de proveedores, obligatoria sobre un umbral.
- **Cruce de tres vías** — Comparar orden, recepción (o conformidad) y factura antes de pagar.
- **Prorrateo** — Reparto de los gastos de una importación entre los ítems importados.
- **Tareo** — Registro semanal de horas por persona, día y partida.
- **Habilitación de campo** — Lo que alguien necesita vigente para trabajar en obra: examen ocupacional, SCTR del proyecto, inducción y equipo de protección.
- **Costo hora estándar y real** — El estándar sale de la categoría del colaborador; el real, de la planilla.

## Contexto peruano

- **RUC** (11 dígitos), **DNI** (8), **CE** — Documentos de identidad tributaria y personal.
- **SUNAT** — Administración tributaria. Valida comprobantes y publica el tipo de cambio.
- **XML** — El comprobante electrónico en su formato oficial; de ahí se leen los datos.
- **Facturador actual** — El programa con el que Techquk emite hoy sus comprobantes; el ERP no emite.
- **Guía de remisión** — Documento que acompaña el traslado de bienes.
- **Detracción y retención** — Descuentos tributarios que se registran aparte del cobro o pago.
- **SCTR** — Seguro Complementario de Trabajo de Riesgo, obligatorio para trabajo en obra.
- **PLAME** — Declaración mensual de planilla ante SUNAT.
- **AFP / ONP** — Regímenes de pensiones.
- **Carta fianza** — Garantía bancaria exigida por la entidad; tiene costo, vencimiento y renovación.
- **Penalidad** — Descuento por día de atraso: coeficiente × monto ÷ (F × plazo), con tope.
- **Conformidad** — Acta con la que el cliente acepta un entregable; habilita facturar.
- **Ley 32069** — Ley General de Contrataciones Públicas; su reglamento rige penalidades y obras.
- **Ley 29733** — Ley de Protección de Datos Personales.
- **S10** — ERP de construcción que se reemplaza. Su histórico queda consultable en solo lectura.
