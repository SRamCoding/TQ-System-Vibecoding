# v4 · Los criterios de aceptación: escenarios con números exactos

> Extracto de texto de `fuentes/Techquk-Sistema-CRM-ERP-v4.pdf` (21 set 2026), líneas 4273 a 4455. Las tablas del PDF aparecen con columnas separadas por « | ». Si algo no se entiende, consulta el PDF.

```text
|  Los criterios de aceptación
|  Con esto un programador sabe cuándo terminó, y quien prueba sabe exactamente qué revisar.
Cada escenario usa el caso de la Plaza de Armas y se puede reproducir paso a paso. Si el resultado no
es exactamente el indicado, la funcionalidad no está terminada.
|  Flujo  |  Cuando  |  Entonces
|  F1  |  Se aprueba el presupuesto PRE-2026-031  |  Costo directo 17,750.00; gastos generales
|  1,775.00; utilidad 1,420.00; IGV 3,770.10; total
|  24,715.10; la lista de insumos suma 17,750.00
|  F1  |  Se intenta aprobar un árbol cuyas hojas suman  |  No se aprueba; muestra diferencia de 100.00 y la
|  19,425.00  |  rama donde está
|  F1  |  Se aprueba el árbol de 9 partidas  |  Proyecto pasa a «en ejecución»; presupuestado
|  total 19,525.00
|  F1  |  Se activa el contrato CT-2026-018  |  Penalidad diaria 205.96; tope 2,471.51; alertas de
|  hitos programadas
|  F1  |  Se registra la carta fianza con comisión de  |  Garantía vigente con alertas a 60, 30 y 15 días;
|  180.00  |  orden de servicio por 180.00 imputada a PC-04
|  F2  |  Se aprueba OC-2026-0142 por 650 m a 2.40  |  Comprometido de PC-02.01 = 1,560.00; disponible
|  = −48.00; la aprobación exigió motivo
|  F2  |  Se envía un requerimiento con una línea sin  |  No se puede enviar; la línea queda marcada
|  imputación
|  F2  |  Se aprueba REQ-2026-0311  |  Cimentación reservada de stock (comprometido
|  PC-02.02 +480.00); grúa y conectores pasan a
|  Compras; la vista previa de PC-04 mostró 91 %
|  F2  |  Se confirma en obra la recepción de 520 m de  |  Orden en «recibida parcial»; ejecutado de PC-02.01
|  OC-2026-0142  |  = 1,248.00; comprometido sigue en 1,560.00
|  F2  |  Llega la factura del proveedor por 650 m  |  Queda retenida por diferencia de 130 m entre
|  recepción y factura
|  F2  |  Se confirma la recepción de OC-2026-0138 con  |  No se puede confirmar; falta una serie
|  9 series escaneadas de 10
|  F2  |  Se despachan 10 cámaras y 20 conectores a  |  Stock baja en 10 y 20; ejecutado de PC-01.01 =
|  PRY-012  |  5,780.00; se genera guía de remisión
|  F2  |  Se aprueba el prorrateo de EMB-029 por valor  |  Factor 15.15 %; costo unitario puesto: switch
|  FOB  |  10,336.07, NVR 13,781.43, cámara 516.80; total
|  79,243.20
|  F3  |  Se cierra el tareo de la semana 37 (68 h)  |  Ejecutado PC-02.03 = 648.00; PC-01.02 = 432.00
|  (72 %); la semana ya no se puede editar
|  F3  |  Se asigna a una persona 60 % a dos proyectos  |  Se marca sobreasignación de 120 %
|  en la misma semana
|  F4  |  Se simula un atraso de 7 días en CT-2026-018  |  Penalidad 1,441.72; margen estimado 1,372.00
|  pasa a −69.72; no se registra nada
|  F4  |  Se intenta emitir el comprobante de CT-2026-  |  El botón está deshabilitado
|  018 sin conformidad final
|  F4  |  Se aplica una cobranza de 30,555.00 a F001-  |  Saldo del comprobante 0.00; retención de 945.00
|  000868  |  registrada aparte
|  F5  |  Se exporta una compra de un tipo sin cuenta  |  El lote queda «fallido» con el motivo; las demás
|  configurada  |  operaciones del lote se exportan
|  F6  |  Se intenta cerrar PRY-012 con OC-2026-0142  |  No se puede cerrar; la lista muestra ambas
|  abierta y tareo sin cerrar  |  condiciones
|  F7  |  Se registra un SCTR con vencimiento en 30 días  |  Aparece en «vencen en 30 días» y se envía la
|  alerta al responsable y al jefe de proyecto
|  F7  |  Se aprueba una rendición de 27.00 sobre un  |  PC-04 ejecutado +27.00; saldo a devolver 23.00
|  fondo de 50.00  |  registrado
|  F8  |  Se busca la serie DS8K…4415  |  Muestra orden OC-2026-0138, ingreso 12 set,
|  despacho 16 set a PRY-012 y garantía hasta el 30
|  set de 2028
|  F1  |  Se aprueba el árbol de PRY-012  |  Existe la partida PC-99 con presupuestado 0.00; no
|  se puede eliminar
|  F7  |  Se registra un taxi de 45.00 en PC-99 sin  |  No se puede enviar; el comentario es obligatorio
|  comentario
|  F7  |  Se aprueba el taxi de 45.00 con motivo  |  Comprometido de PC-99 = 45.00; margen
|  «imprevisto en obra»  |  estimado pasa de 1,372.00 a 1,327.00
|  F7  |  Se aprueba un gasto de 1,286.00 con motivo  |  Se marca posible adicional y llega una alerta a
|  «cambio pedido por el cliente»  |  Administración de Contratos
|  F2  |  Se sube el XML de E001-000331  |  Datos leídos sin intervención; validado ante SUNAT;
|  cruce correcto; asiento: 60 = 5,780.00 y 40 =
|  1,040.40 al debe, 42 = 6,820.40 al haber
|  F4  |  Se sube el XML de F001-000912  |  Se sugiere asociarlo a CT-2026-018 · Conformidad
|  final; no se asocia hasta que la persona confirma
|  F5  |  Lisset arrastra la tarjeta del Proveedor Asia a  |  La columna suma 25,420.40 y muestra caja
|  «próxima semana»  |  restante 53,831.60; el orden se conserva al volver
|  a entrar
|  F5  |  Se sube la foto de la hoja 14  |  Tres filas propuestas; la fila 2 marca monto en
|  ámbar y proyecto en rojo; no se guarda nada hasta
|  confirmar
|  F9  |  Se intenta tarear a L. Vera con el SCTR vencido  |  El tareo se bloquea; se ofrece autorizar con motivo
|  a quien tiene ese permiso
|  F9  |  Se cierra la planilla de setiembre  |  Se generan boletas, archivo PLAME y asientos; J.
|  Chero: neto 2,088.00, costo empresa 2,616.00
|  (ejemplo simplificado)
|  Roles  |  Se asigna a Lisset el rol de aprobar pagos  |  La grilla marca el cruce y exige registrar un control
|  además de registrarlos  |  compensatorio
|  F10  |  Se aprueba la valorización 3 de CT-2026-030  |  Reajuste 2,106.04; amortización directa 14,230.00;
|  (avance 142,300.00, factor 1.0148, adelanto  |  neto 108,776.04; el comprobante aparece en la
|  directo 10 %, adelanto de materiales según  |  bandeja por facturar por ese neto
|  consumo 21,400.00)
|  F10  |  Se cargan los índices de setiembre para la  |  Factor 1.0148 (0.320×621.10/612.40 +
|  fórmula de PRE-2026-040  |  0.285×411.60/398.20 + 0.190×509.80/512.00 +
|  0.205×186.90/184.30)
|  F10  |  Se intenta aprobar una fórmula cuyos  |  No se aprueba; muestra la diferencia
|  coeficientes suman 0.995
|  F10  |  Se registra un adelanto directo de 89,000.00 y  |  Amortizado 50,000.00; por amortizar 39,000.00; la
|  se aprueban valorizaciones por 500,000.00 en  |  garantía del adelanto no se libera
|  total
|  F10  |  Se aprueba la valorización 2 del subcontrato  |  Neto a pagar 21,000.00; ejecutado en las partidas
|  SC-2026-007 (28,000.00, adelanto 20 %,  |  del alcance +28,000.00; retención acumulada
|  retención 5 %)  |  2,625.00
|  F10  |  Se arma la liquidación de CT-2026-030 con los  |  Saldo a favor 51,020.00; no se puede presentar si
|  datos del ejemplo  |  hay una valorización en borrador
|  F10  |  Se registra OC-2026-0151 por USD 6,000 a TC  |  Equivalente 22,440.00; diferencia de cambio 72.00
|  3.740 y se paga a 3.752  |  con su propio asiento
|  F10  |  Se enlaza CT-2025-044 al Consorcio Vial Norte  |  Cada valorización registra el 40 % como ingreso de
|  con Techquk al 40 % en modo participación  |  Techquk
|  F10  |  Se configura CT-2026-035 con garantía por  |  Cobrable 45,000.00; retenido acumulado
|  retención del 10 % y se cobra una valorización  |  +5,000.00 visible aparte
|  de 50,000.00
|  CRM  |  Se intenta crear una cuenta con el RUC  |  Se bloquea y ofrece abrir la existente
|  20176543210 ya registrado
|  CRM  |  Se arrastra una oportunidad de «calificada» a  |  El modal no permite avanzar; muestra el requisito
|  «propuesta enviada» sin cotización emitida  |  faltante
|  CRM  |  Se solicita aprobación de COT-2026-0184 v2  |  Va a la bandeja de Gerencia Comercial; no se
|  con margen 19.4 % y mínimo 22 %  |  puede enviar al cliente sin aprobación registrada
|  CRM  |  Se marca LP-2026-014 como presentada con 3  |  No se puede; lista los dos faltantes
|  de 5 documentos
|  Frontera  |  Se marca ganada la oportunidad Plaza de  |  Se genera CT-2026-018 con cliente, monto
|  Armas con COT-2026-0190 aceptada  |  24,715.10, condiciones y PRE-2026-031 enlazado;
|  el proyecto queda «en preparación»
|  PARTE 9
```
