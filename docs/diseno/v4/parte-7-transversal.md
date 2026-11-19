# v4 · Lo transversal: permisos, aprobaciones, alertas, IA, integraciones y migración

> Extracto de texto de `fuentes/Techquk-Sistema-CRM-ERP-v4.pdf` (21 set 2026), líneas 3917 a 4272. Las tablas del PDF aparecen con columnas separadas por « | ». Si algo no se entiende, consulta el PDF.

```text
|  Lo transversal
|  Lo que no pertenece a un flujo porque aparece en todos. Se construye una sola vez, en el núcleo.
Permisos
La matriz sale del documento 10 (L lectura · E escritura · A aprueba · P propio ámbito · — sin acceso),
agrupada por gerencia del organigrama propuesto, que también debe revisarse con los roles reales.
|  Ger.
|  Área  |  Comercial  |  Operaciones  |  Suministro  |  Administración  |  RRHH
|  General
|  Contratos y  |  L  |  L  |  E  |  L  |  L · Tesorería E en  |  —
|  garantías  |  garantías
|  Proyectos,  |  L  |  L · Preventa E  |  E  |  L  |  L  |  L
|  presupuesto y  |  en presupuesto
|  partidas
|  Compras  |  A  |  —  |  pide  |  E  |  L  |  —
|  Importaciones  |  L  |  —  |  L  |  E  |  L  |  —
|  Almacén  |  L  |  —  |  L · recibe en  |  E  |  —  |  —
|  obra
|  Personal,  |  L  |  —  |  E/P  |  —  |  L  |  E
|  asignación y tareo
|  Facturación y  |  L  |  —  |  L  |  —  |  E  |  —
|  cobranzas
|  Pagos, caja y  |  L  |  —  |  —  |  L  |  E  |  —
|  contabilidad
|  Servicios y  |  L  |  L  |  E  |  —  |  L  |  —
|  suscripciones
|  Planilla y contratos  |  L  |  —  |  —  |  —  |  L  |  E
|  laborales
|  Habilitación de  |  L  |  —  |  L  |  —  |  —  |  E
|  campo (solo
|  resultado)
|  Configuración  |  A  |  —  |  —  |  —  |  E  |  —
|  Restricciones de campo, no de módulo
|  Auxiliares de contabilidad y asistentes de administración pueden abrir un proyecto o una orden,
|  pero no ven costos ni márgenes: esas columnas no aparecen. Lo mismo vale para sueldos (solo
|  RRHH, Gerencia y contabilidad) y para el detalle de exámenes médicos (solo RRHH): son datos
|  sensibles según la ley de protección de datos personales. Conviene evaluar lo mismo para
|  almacén con los precios de compra. Se construye en el núcleo desde el inicio; agregarlo después
|  obliga a revisar cada formulario. La construcción de la grilla de roles y los controles
|  compensatorios están en la parte 3.
Matriz de aprobaciones
Decisión tomada: las reglas viven como datos y se cambian desde una pantalla de configuración, sin
programar, igual que en S10. Agregar un umbral, cambiar un monto o un aprobador es trabajo de un
administrador. Los montos se escriben aquí como U1, U2 y U3 porque sus valores iniciales los fija
Gerencia.  |  POR VALIDAR
|  Documento  |  Condición  |  Aprueba
|  Requerimiento  |  Hasta U1  |  Jefatura de Proyectos
|  Requerimiento u orden  |  Sobre U1  |  Gerencia de Operaciones o de Suministro
|  Orden  |  Sobre U2  |  Gerencia General
|  Cualquier línea que exceda su  |  Siempre  |  Jefatura de Proyectos, con motivo; sobre U1 también
|  partida  |  Gerencia de Operaciones
|  Árbol de partidas o nueva versión  |  Siempre  |  Jefatura de Proyectos
|  Adenda  |  Siempre  |  Gerencia de Operaciones; con impacto económico,
|  Gerencia General
|  Pago  |  Hasta U3  |  Gerencia de Administración
|  Pago  |  Sobre U3  |  Gerencia General
|  Factura retenida liberada  |  Siempre  |  Gerencia de Administración
|  Ajuste de inventario o de tareo  |  Siempre  |  Jefatura del área
|  Gasto no presupuestado (PC-99)  |  Siempre, cualquier  |  Jefatura de Proyectos; sobre U1 también Gerencia de
|  monto  |  Operaciones
|  Habilitación de campo autorizada  |  Siempre  |  Gerencia de Operaciones, con motivo
|  sin requisito
|  Cierre de planilla  |  Siempre  |  RRHH con visto bueno del contador
Catálogo único de alertas y vencimientos
Hoy estas alertas estarían repartidas en cinco módulos. Reunidas, son un solo mecanismo del núcleo
con un solo lugar de configuración. Cada persona ajusta la anticipación y el canal de sus alertas dentro
de lo que su rol permite; las obligatorias se pueden adelantar, pero no apagar.
|  Alerta  |  Se dispara  |  A quién
|  Hito de contrato  |  7 y 2 días antes; el mismo día;  |  Jefatura de Proyectos, Adm.
|  vencido  |  Contratos
|  Carta fianza por vencer  |  60, 30 y 15 días antes  |  Tesorería, Adm. Contratos, Legal
|  Servicio con vigencia por vencer (SCTR,  |  30 días antes  |  Responsable del servicio y del
|  pólizas, alquileres)  |  proyecto
|  Partida excedida o al 90 %  |  Al aprobar la línea que la  |  Responsable de la partida,
|  excede  |  Jefatura de Proyectos
|  Embarque en riesgo  |  Cuando la llegada estimada  |  Compras, Jefatura de Proyectos,
|  supera un hito  |  Adm. Contratos
|  Orden vencida sin recibir  |  Al día siguiente de la entrega  |  Compras, solicitante
|  pactada
|  Stock bajo mínimo  |  Al bajar del mínimo  |  Almacén, Compras
|  Tareo sin cerrar  |  Lunes siguiente a la semana  |  Jefatura de Proyectos
|  Certificación de personal por vencer  |  90 días antes  |  RRHH, persona
|  Sobreasignación  |  Al asignar más de 100 % en  |  Coordinación de Recursos
|  una semana
|  SLA de ticket por vencer  |  Al 75 % del tiempo  |  Help Desk, jefatura
|  Cuenta por cobrar vencida  |  30, 60 y 90 días  |  Tesorería, Gerencia de
|  Administración
|  Comprobante observado por SUNAT  |  Al recibir la respuesta  |  Administración
|  Exportación contable fallida  |  Al fallar  |  Contabilidad
|  Gasto no presupuestado registrado  |  Al enviarse  |  Jefatura de Proyectos
|  Posible adicional detectado  |  Al aprobarse el gasto  |  Administración de Contratos
|  Caja proyectada bajo el mínimo  |  Al recalcular el flujo  |  Tesorería, Gerencia de
|  Administración
|  Hoja de notas por revisar  |  Al subir la foto  |  Tesorería
|  Habilitación de campo por vencer  |  15 días antes; bloqueo el día  |  RRHH, jefatura, la persona
|  del vencimiento
|  Contrato laboral por vencer  |  30 días antes  |  RRHH, jefatura
|  Brecha de personal  |  Al recalcular la demanda a 90  |  Gerencia de RRHH
|  días
|  Cruce de funciones  |  Al asignar roles que se cruzan  |  Gerencia General
Catálogo único de automatizaciones
Todas las automatizaciones del sistema, en un solo lugar. Las de regla fija tienen una sola respuesta
correcta y se prueban con números. Las de IA siguen el principio de la idea 4: sugieren y una persona
confirma.
|  Dónde  |  Qué se automatiza  |  Tipo
|  CRM · cuentas y  |  Coincidencias por RUC, nombre y correo; precarga  |  Regla fija
|  leads  |  desde el padrón de SUNAT
|  CRM · licitaciones  |  Lista de documentos, cronograma y valor referencial  |  IA
|  leídos de las bases
|  CRM · cotización  |  Margen, umbrales y aprobación requerida;  |  Regla fija
|  vencimiento automático
|  CRM · precio de  |  Rango sugerido desde el histórico de adjudicaciones  |  Cálculo sobre datos propios,
|  oferta  |  de la entidad  |  mostrado como referencia
|  Frontera  |  Contrato generado con todo lo cotizado; hitos y  |  Regla fija + IA
|  condiciones leídos del PDF del contrato
|  Contratos  |  Penalidad diaria, plazo vigente con adendas, alertas  |  Regla fija
|  de hitos y garantías
|  Presupuesto  |  Costo unitario desde el APU, lista de insumos, totales;  |  Regla fija
|  factor de reajuste con índices del mes
|  Partidas  |  Propuesta de árbol desde el presupuesto; unificación  |  Regla fija + IA
|  de archivos de formatos distintos con coincidencias
|  por certeza
|  Compras  |  Verificación de stock, saldo de partida, exceso, cuadro  |  Regla fija (+ IA en la partida
|  comparativo obligatorio por monto; partida sugerida  |  sugerida FASE POSTERIOR )
|  por historial
|  Importaciones  |  Prorrateo, costo puesto, cruce del embarque con los  |  Regla fija
|  hitos del contrato
|  Almacén  |  Stock, series, guía de remisión generada desde el  |  Regla fija
|  despacho
|  Tareo y planilla  |  Sobreasignación, bloqueo por habilitación vencida,  |  Regla fija
|  costo estándar, cálculo de planilla, PLAME
|  Facturación  |  Lectura del XML, validación ante SUNAT, cuenta por  |  Regla fija + IA
|  cobrar, retención o detracción; hito sugerido; lectura
|  por visión si solo hay PDF
|  Cuentas por pagar  |  Cruce de tres vías, retención de facturas que no  |  Regla fija + IA
|  coinciden; enlace sugerido pago–factura
|  Tesorería  |  Ubicación inicial de tarjetas por vencimiento, totales y  |  Regla fija + IA
|  caja restante, flujo de caja, escenarios; lectura de la
|  hoja de notas por foto
|  Contabilidad  |  Asientos por plantilla, diferencia de cambio,  |  Regla fija
|  exportación a Excel
|  Valorizaciones y  |  Avance × precio, reajuste, amortizaciones, retención,  |  Regla fija
|  liquidación  |  neto; liquidación armada sola
|  No presupuestados  |  Margen recalculado al aprobarse; posible adicional  |  Regla fija (la marca) + IA
|  cuando el motivo es cambio del cliente  |  (redacción sugerida del adicional
|  FASE POSTERIOR )
|  Postventa  |  Orden por SLA, rastreo por serie, oportunidad de  |  Regla fija
|  vuelta al CRM fuera de garantía
|  Alertas  |  Todo el catálogo de vencimientos  |  Regla fija
Numeración, auditoría y reglas generales
Cada tipo de documento tiene su prefijo y su correlativo anual (CT, PRE, REQ, OC, OS, ING, DES, HOR,
GAR, AD), generado por el mecanismo de numeración del núcleo. Los comprobantes electrónicos y las
guías usan la serie y el correlativo que exige SUNAT. Toda ficha tiene una pestaña de historial
alimentada por la auditoría del núcleo: quién cambió qué, cuándo y cuál era el valor anterior. Nada se
borra: lo anulado, rechazado o vencido se conserva con su motivo.
Integraciones
|  Con qué  |  Para qué  |  Estado
|  Facturador actual de  |  Los comprobantes de venta se emiten ahí;  |  Decidido · falta saber qué programa es
|  Techquk  |  el ERP recibe su XML  |  POR VALIDAR
|  SUNAT · validación de  |  Verificar al subir que cada comprobante  |  Decidido
|  comprobantes  |  de venta o de proveedor es válido
|  SUNAT · guías de  |  Emitir la guía al despachar fuera del local  |  Por confirmar si se emiten desde el ERP o
|  remisión  |  desde el facturador POR VALIDAR
|  SUNAT · PLAME y T-  |  Archivo de declaración de planilla y  |  Decidido (planilla dentro)
|  Registro  |  registro de trabajadores
|  Programa del  |  Exportar asientos generados por plantilla  |  Decidido (Excel) · confirmar columnas que
|  contador  |  en un Excel de formato fijo que él importa  |  su programa acepta POR VALIDAR
|  Índices oficiales de  |  Cargar cada mes los índices para la  |  Carga manual mensual; automatizable
|  precios  |  fórmula polinómica  |  después FASE POSTERIOR
|  Tipo de cambio oficial  |  Registrar el TC de cada documento en  |  Carga diaria manual o automática
|  moneda extranjera  |  POR VALIDAR
|  Padrón de SUNAT  |  Precargar razón social y dirección al crear  |  Consulta pública
|  (RUC)  |  cuentas y proveedores
|  S10 durante la  |  Consultar histórico en solo lectura; nunca  |  Decidido
|  transición  |  escribir en su base
|  Bancos  |  Registro manual de cobros y pagos;  |  Primera versión manual
|  conciliación por archivo
|  MS Project  |  Importar hitos y estructura desde XML  |  POR VALIDAR  |  ver un archivo real primero
|  (formato MSPDI) o con la librería MPXJ
|  Modelo de lenguaje  |  Lectura de archivos y fotos, enlaces  |  Decidido como principio · requiere
|  (mecanismo de IA)  |  sugeridos, posibles adicionales (parte 1,  |  autorización de Gerencia sobre qué datos
|  idea 4)  |  se envían POR VALIDAR
|  CRM  |  Contrato generado al ganar; presupuesto  |  Mismo sistema
|  enlazado; ticket fuera de garantía vuelve
|  como oportunidad
Migración de datos
El documento 10 no la menciona, y es donde suelen fallar los proyectos de ERP. La regla general: se
migra lo que está vivo, y el histórico se deja consultable en S10 en solo lectura. El corte conviene
hacerlo por proyecto: los proyectos nuevos arrancan en el ERP y los que están por cerrar terminan en
S10, para que nadie trabaje un mismo proyecto en dos sistemas.
|  Qué  |  Desde dónde  |  Cuidado especial
|  Personas y empresas (clientes,  |  S10 (tabla de  |  Duplicados por RUC; roles de cada
|  proveedores, trabajadores)  |  identificadores) y Excel  |  persona
|  Catálogo de productos e insumos  |  S10 y Excel  |  S10 trae catálogos genéricos: migrar solo
|  lo que Techquk usa
|  Proyectos en curso con presupuesto  |  S10 Presupuestos y Gerencia  |  Cargar lo ya comprometido y ejecutado
|  y árbol de partidas  |  de Proyectos  |  como saldo inicial por partida
|  Stock inicial con series  |  Conteo físico  |  Hacer toma de inventario el día del corte
|  Cuentas por cobrar y por pagar  |  Contabilidad y Excel  |  Solo documentos con saldo
|  abiertas
|  Garantías y servicios con vigencia  |  Excel o memoria de las  |  Es la migración que más valor tiene y la
|  vigentes  |  personas  |  que nadie tiene en un sistema
|  Contratos vigentes con hitos  |  Carpetas de contratos  |  Plazo vigente con adendas
|  Personal, contratos laborales y  |  RRHH, Excel y carpetas del  |  Solo resultados de exámenes fuera de
|  habilitaciones  |  personal  |  RRHH; SCTR por proyecto
|  Saldos de planilla (vacaciones, CTS  |  Programa o Excel de planilla  |  Validar con el contador antes del primer
|  acumulada)  |  actual  |  cálculo
|  PARTE 8
```
