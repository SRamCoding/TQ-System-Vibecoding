# v4 · Flujo 5 · El dinero que sale y la contabilidad

> Extracto de la parte 4 de la especificación v4. Fichas de sus documentos: `parte-6-fichas-documentos.md`. Escenarios de prueba: `parte-8-criterios-aceptacion.md`.

```text
Flujo 5 · El dinero que sale y la contabilidad
La segunda mitad del ciclo del dinero, y el flujo donde más cambió el diseño en esta versión. Hoy el
módulo de S10 donde se registran pagos y cobros está vacío en Techquk: Lisset lleva la tesorería en
otro lado, con su propio criterio y sus notas en papel. La idea no es quitarle ese criterio, sino darle los
números a la vista y quitarle el trabajo de pasar todo a mano. Y del lado de contabilidad, que el
contador reciba los asientos ya hechos en lugar de escribirlos.
|  1  |  Se ordenan los pagos en el tablero
|  Tesorería (Lisset)
|  Cada factura que pasó el cruce de tres vías aparece como una tarjeta en el tablero de pagos, en
|  la columna que le toca por vencimiento. Lisset las arrastra según su criterio: adelanta una, deja
|  otra para la semana siguiente, pone en espera lo que prefiere conversar. Cada columna muestra
|  su total y cuánta caja queda después de pagarla, así que al mover una tarjeta ve en el momento
|  si el dinero alcanza. Las retenidas aparecen aparte y no suman a la caja.
|  Tablero de pagos · Lisset  |  Caja disponible hoy S/ 84,000.00  |  Programar esta semana
|  ESTA SEMANA  |  PRÓXIMA SEMANA  |  DESPUÉS  |  EN ESPERA
|  S/ 4,748.00  |  S/ 25,420.40  |  S/ 4,675.00  |  S/ 1,840.80
|  queda S/ 79,252.00  |  queda S/ 53,831.60  |  no suma a la caja
|  Flete EMB-030 ·
|  Alquiler de local ·  |  Proveedor Andino ·  |  4,675.00  |  Proveedor Andino ·
|  2,800.00  |  6,820.40  |  vence 30 oct  |  1,840.80
|  servicio con vigencia ·  |  E001-000331 · vence  |  retenida: facturó 650
|  vence 30 set  |  12 oct  |  m, se recibieron 520
|  Transportes  |  Proveedor Asia ·
|  Lambayeque · 708.00  |  18,600.00
|  F001-002231 · vence 25  |  saldo EMB-031 ·
|  set  |  arrastrada por Lisset
|  SCTR Hospital Regional ·
|  1,240.00
|  OS-2026-0061
|  El sistema ubica cada tarjeta según su vencimiento; Lisset las arrastra según su criterio y ese orden se respeta. Cada columna muestra
|  su total y cuánta caja queda después de pagarla.
Tablero de pagos con tarjetas. La intuición de Lisset sigue decidiendo, pero ahora con los números a la vista al mover cada
tarjeta.
|  AJUSTE SET 2026  |  Más adelante, con el historial de cómo ella ordena, el sistema podría sugerirle un
orden inicial. Primero manual.
|  2  |  Se reciben las alertas propias del rol
|  Tesorería
|  Lisset se entera por el ERP, no por memoria: cartas fianza por vencer, facturas próximas a
|  vencer, cobros atrasados, caja proyectada bajo un mínimo. Cada persona ajusta sus alertas
|  dentro de lo que su rol permite, y las obligatorias se pueden adelantar pero no apagar.
|  Mis alertas · Tesorería  |  Guardar
|  Alerta  |  Anticipación por defecto  |  La mía  |  Dónde me llega
|  Carta fianza por vencer  |  60, 30 y 15 días  |  45 y 15 días  |  campana y correo
|  Factura de proveedor por vencer  |  7 días  |  5 días  |  campana
|  Cuenta por cobrar vencida  |  30, 60 y 90 días  |  igual  |  campana y correo
|  Caja proyectada bajo S/ 50,000  |  —  |  activada  |  campana y correo
|  Cada persona ajusta sus propias alertas dentro de lo que su rol permite. Las alertas obligatorias (como la de carta fianza) se pueden
|  adelantar, pero no apagar.
Alertas personalizables por rol.
|  3  |  Se capturan las notas en papel
|  Tesorería
|  Lisset sigue anotando en papel, pero en una hoja con formato fijo diseñada con ella. Le toma una
|  foto; el sistema lee la hoja, propone las filas en el formato del ERP y marca en ámbar lo que leyó
|  con poca seguridad y en rojo lo que falta. Además sugiere enlaces: este pago parece
|  corresponder a tal factura, este cobro a tal comprobante. Ella revisa con la foto al lado, corrige y
|  confirma.
|  Formato de papel · Registro de movimientos  |  Hoja N.° ____ · Fecha ___/___/______
|  Entra /  |  Proveedor o  |  Proyecto o  |  Monto  |  N.° de
|  N.° Día  |  Concepto  |  Medio
|  Sale  |  cliente  |  «Empresa»  |  S/  |  documento
|  1  |  ☐E☐S  |  ☐ Transf ☐ Efect ☐
|  Cheque
|  2  |  ☐E☐S  |  ☐ Transf ☐ Efect ☐
|  Cheque
|  …
|  12
|  Casillas para marcar en lugar de palabras, número de hoja y de fila para que nada se suba dos veces, y unas 12 filas por hoja con
|  espacio amplio. Borrador inicial: se ajusta con Lisset a partir de lo que ya anota.
El formato de papel es lo que más pesa en que la lectura por foto funcione. Un talonario simple resuelve más que cualquier
ajuste técnico.
|  Revisar hoja 14 · leída por foto  |  2 datos por revisar  |  Confirmar filas
|  LA FOTO  |  LO QUE PROPONE EL SISTEMA
|  Fila Tipo  |  A quién  |  Imputación  |  Monto Se enlaza con
|  Hoja 14 · 18/09/2026
|  1 · 18 · S · Transp. Lambayeque ·  |  1  |  Pago  |  Transportes PRY-012 ›  |  708.00  |  F001-002231
|  movilidad obra · Plaza Armas · 708.00
|  Lambayeque PC-04
|  · T · F001-2231  |  2  |  Pago  |  Ferretería El  |  falta  |  136.50 ó 186.50  |  boleta B002-
|  2 · 18 · S · Ferret. El Norte · cinta  |  Norte  |  proyecto  |  004411
|  y precintos · ______ · 1?6.50 · E ·
|  3  |  Cobro Muni. Prov.  |  Empresa  |  30,555.00  |  F001-000868
|  B002-4411
|  de Chiclayo
|  3 · 18 · E · Muni Chiclayo · cobro
|  En ámbar lo que se leyó con poca seguridad; en rojo lo que falta. Lo que dice «se
|  fact · Empresa · 30,555.00 · T · F001-  |  enlaza con» es una sugerencia que Lisset acepta o rechaza. Nada se guarda sin su
|  868  |  confirmación y la foto queda adjunta.
Captura de notas por foto. Lisset sigue escribiendo en papel; el sistema ordena los datos y ella revisa.
La calidad de la lectura depende, de mayor a menor peso, del formato del papel, de la letra (sobre
todo de los números), de la foto (buena luz, hoja plana y completa) y de subir una hoja por foto. Los
montos se confirman siempre, porque un 1 leído como 7 en un pago es un problema serio. Esta
captura sirve también como puente: cuando el sistema ya tenga las facturas, los pagos y las alertas,
gran parte de lo que hoy se anota dejará de ser necesario. POR VALIDAR Ver una página real de sus
anotaciones y diseñar el formato con ella.
|  4  |  Se paga y se proyecta la caja
|  Tesorería · aprueba según monto
|  Al programar la semana, cada pago se aplica a facturas concretas y el costo pasa a pagado. El
|  flujo de caja se arma solo con lo facturado, lo por facturar, lo programado y los fijos, y un
|  segundo escenario simula el atraso típico de las entidades públicas.
|  Flujo de caja · próximas semanas  |  Cobros a fecha  |  +30 días de atraso del Estado
|  Sem 38  |  Sem 39  |  Sem 40  |  Sem 41  |  Sem 42
|  Cobros esperados  |  87,000  |  0  |  142,300  |  23,974  |  0
|  Pagos programados  |  −38,400  |  −22,100  |  −61,800  |  −9,700  |  −48,000
|  Planilla y fijos  |  0  |  −46,000  |  0  |  0  |  −46,000
|  Saldo (inicial 84,000)  |  132,600  |  64,500  |  145,000  |  159,274  |  65,274
Flujo de caja proyectado. Los cobros salen de lo facturado y lo por facturar; los pagos, de cuentas por pagar y servicios con
vigencia.
|  5  |  Los asientos se generan solos
|  Contabilidad
|  Cada tipo de operación tiene una plantilla contable, definida una vez por el contador. Cuando un
|  documento llega a su estado (la factura del proveedor queda registrada, un pago se aplica, un
|  comprobante de venta se sube, la planilla se cierra), el sistema toma la plantilla, le pone los
|  montos y genera el asiento. No interviene la IA: es una regla fija.
|  Asiento generado · AS-2026-09-0412  |  listo para exportar
|  Origen  |  Plantilla
|  Factura E001-000331 registrada · OC-2026-0138  |  Compra de mercadería afecta a IGV
|  Glosa  |  Proyecto
|  Fact. E001-000331 Proveedor Andino, OC-2026-0138  |  PRY-2026-012
|  Cuenta  |  Debe  |  Haber
|  60 Compras  |  5,780.00
|  40 IGV por acreditar  |  1,040.40
|  42 Proveedores  |  6,820.40
|  Totales  |  6,820.40  |  6,820.40
|  Ilustrativo: las cuentas reales de cada plantilla las define el contador. El asiento se exporta en el formato de importación del programa
|  donde él lleva los libros oficiales.
Asiento generado por plantilla. No interviene la IA: es una regla fija, porque tiene una sola respuesta correcta.
|  6  |  Se exportan al programa del contador
|  Contabilidad
|  Decisión: los asientos se exportan en lotes a un archivo Excel con un formato fijo (fecha,
|  número, glosa, cuenta, debe, haber, proyecto o centro de costo, documento de origen), que el
|  contador importa en su programa. Él revisa, corrige si algo no le cuadra e importa, sin teclear. Lo
|  que falla, por ejemplo una operación sin plantilla configurada, queda en una bandeja con su
|  motivo.
|  Configuración contable  |  Exportación contable  |  1 fallida
|  Operación  |  Debe  |  Haber  |  Lote  |  Contenido  |  Estado
|  Compra de  |  60 Compras  |  42 Por pagar  |  EXP-0912  |  46 asientos de setiembre  |  enviado
|  mercadería
|  EXP-0913  |  Compra sin cuenta configurada  |  fallido
|  Consumo a proyecto  |  Costo del  |  20
|  proyecto  |  Mercaderías
|  Venta con  |  12 Por cobrar  |  70 Ventas
|  conformidad
|  Ilustrativo: el plan real lo define contabilidad.
Qué cuenta corresponde a cada operación, y qué se envió a contabilidad, qué falló y por qué.
|  CUANDO LAS COSAS NO SALEN COMO SE ESPERA
|  Operación sin plantilla configurada. No se exporta en silencio: queda en la bandeja de
|  fallidos hasta que el contador la configure.
|  Pago urgente de una factura retenida. Se libera con aprobación y motivo; queda marcada
|  para revisión.
|  La foto no se puede leer. Se registra a mano en el mismo formulario; la foto queda adjunta
|  igual.
|  Lisset registra y aprueba el mismo pago. Es un cruce de funciones; se permite con el
|  control compensatorio definido en la grilla de roles (parte 3).
|  POR VALIDAR  Las columnas exactas que su programa acepta al importar desde Excel,
|  para ajustar el formato fijo si hace falta.
```
