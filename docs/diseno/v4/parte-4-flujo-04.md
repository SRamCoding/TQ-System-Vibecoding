# v4 · Flujo 4 · De la conformidad al cobro

> Extracto de la parte 4 de la especificación v4. Fichas de sus documentos: `parte-6-fichas-documentos.md`. Escenarios de prueba: `parte-8-criterios-aceptacion.md`.

```text
Flujo 4 · De la conformidad al cobro
Es la primera mitad del ciclo del dinero, y hoy vive fuera de S10: su configuración de facturación
electrónica está vacía y la tesorería casi no tiene registros. Para una empresa que le vende al Estado,
que paga tarde, aquí el ERP propio aporta desde el primer mes.
|  1  |  Se vigilan los hitos y el plazo
|  Administración de Contratos
|  Cada hito tiene su fecha comprometida y sus alertas. Cuando algo amenaza el plazo —un
|  embarque tarde, una zona de trabajo que la entidad no libera— el simulador responde cuánto
|  costaría el atraso, y si la causa no es atribuible a Techquk, se tramita una adenda de ampliación
|  antes del vencimiento.
|  Simular atraso · CT-2026-018
|  SI LA CONFORMIDAD SE ATRASA
|  PENALIDAD  |  CONTRA EL MARGEN
|  7 días  |  El margen estimado de S/ 1,372 se
|  S/ 1,441.72
|  El simulador no registra nada: responde una
|  vuelve una pérdida de S/ 69.72
|  7 × 205.96 · 58 % del tope
|  pregunta.
Simulador de penalidad. Convierte «¿vale la pena pagar horas extra?» en una comparación de números.
|  2  |  Se registra la conformidad
|  Jefatura de Proyectos · Administración de Contratos
|  El cliente firma el acta; se registra contra el hito con el documento adjunto. Ese registro es lo que
|  habilita facturar. En contratos que pagan por avance, el equivalente es la valorización aprobada.
|  3  |  Se factura en el facturador de siempre y se sube
|  Administración
|  La bandeja de lo facturable muestra lo que ya tiene conformidad o valorización y todavía no
|  tiene comprobante. El comprobante se emite en el programa que Techquk ya usa, y se sube al
|  ERP: el XML trae los datos exactos, el sistema valida el comprobante ante SUNAT y sugiere a qué
|  hito pertenece. La persona confirma. Así el ERP no reemplaza al facturador, pero sabe todo lo
|  que se facturó y lo enlaza a su contrato.
|  Por facturar  |  2 habilitados
|  Origen  |  Cliente  |  Qué lo habilita  |  Monto
|  CT-2026-015 · Hito 1  |  EPSEL S.A.  |  Conformidad 12 set  |  87,000.00  |  Subir comprobante
|  CT-2026-011 · Valorización 3  |  Hospital Regional  |  Aprobada 10 set  |  142,300.00  |  Subir comprobante
|  CT-2026-018 · Conformidad final Muni. Prov. de Chiclayo pendiente de conformidad  |  24,715.10  |  Subir comprobante
Bandeja de lo facturable. El comprobante se emite en el facturador de siempre y se sube aquí; lo que no tiene conformidad
aparece, pero no se puede asociar.
|  Subir comprobante de venta  |  XML leído  |  Confirmar
|  ARCHIVOS  |  LO QUE LEYÓ EL SISTEMA
|  F001-000912.xml ✓ leído  |  Dato  |  Valor
|  F001-000912.pdf adjunto  |  Cliente  |  Municipalidad Provincial de Chiclayo · RUC
|  20…
|  Validado ante SUNAT: comprobante aceptado  |  Fecha de emisión  |  2 oct 2026
|  Si solo hay PDF o foto, se lee por visión y cada dato  |  Base · IGV · Total  |  20,945.00 · 3,770.10 · 24,715.10
|  leído con poca seguridad se marca para revisar.  |  Retención esperada (3  |  741.45
|  %)
|  Se asocia a  |  CT-2026-018 · Conformidad final sugerido
El comprobante se emite en el facturador de siempre y se sube aquí. El XML trae los datos exactos; el sistema solo sugiere a
qué hito pertenece y la persona confirma.
|  Qué queda registrado · comprobante enlazado al hito · cuenta por cobrar por el total · retención o
|  detracción registrada aparte, si aplica · asiento de venta generado por plantilla
|  4  |  Se cobra
|  Tesorería
|  El seguimiento de la deuda se hace por antigüedad. Cuando llega el depósito, se aplica a uno o
|  varios comprobantes; lo no aplicado queda como saldo a favor visible.
|  Estado de cuenta · Muni. Prov. de Chiclayo  |  Antigüedad de deuda
|  Comprobante Emisión  |  Total Retención  |  Cobrado  |  Saldo  |  0–30  |  253,273
|  F001-000868  |  28 ago  |  31,500.00  |  945.00  |  0.00 30,555.00  |  31–60  |  96,400
|  61–90  |  41,000
|  F001-000845  |  10 jul  |  18,200.00  |  546.00 17,654.00  |  0.00
|  +90  |  28,500
Estado de cuenta por cliente y antigüedad de toda la deuda. Ejemplo: la entidad retiene el 3 % del IGV.
|  CUANDO LAS COSAS NO SALEN COMO SE ESPERA
|  SUNAT rechaza el comprobante o la validación falla al subirlo. Queda en la bandeja de
|  observados con el motivo; se corrige en el facturador y se vuelve a subir, o se emite nota de
|  crédito.
|  Se sube un comprobante que no corresponde a ningún hito facturable. El sistema no lo
|  asocia solo: queda pendiente de asociar, y si el hito no tiene conformidad, lo avisa.
|  Cobro parcial o con descuento de penalidad. La entidad paga menos: la diferencia se
|  registra como penalidad aplicada, enlazada al contrato, y alimenta el margen real.
|  La entidad no libera la zona o demora la conformidad. Adenda de ampliación con su
|  resolución; hasta que la resolución esté cargada, la penalidad se calcula sobre el plazo original.
|  Retención y detracción. POR VALIDAR Confirmar con contabilidad qué operaciones llevan una
|  u otra. El ejemplo usa retención del 3 % del IGV.
|  Comprobantes observados  |  2
|  Comprobante  |  Motivo que devolvió el operador
|  F001-000913  |  RUC del adquiriente no válido  |  Corregir y reenviar
|  NC01-000021  |  Referencia a comprobante inexistente  |  Corregir
Un comprobante rechazado es una venta que para SUNAT no existe. Esta bandeja no es opcional.
Decisión tomada: los comprobantes se siguen emitiendo fuera del ERP y se suben. POR VALIDAR
Falta saber con qué programa se factura hoy, para confirmar que entrega el XML de cada comprobante
(todos los facturadores electrónicos deberían hacerlo).
```
