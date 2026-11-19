# v4 · Flujo 7 · Gastos generales, no presupuestados y servicios

> Extracto de la parte 4 de la especificación v4. Fichas de sus documentos: `parte-6-fichas-documentos.md`. Escenarios de prueba: `parte-8-criterios-aceptacion.md`.

```text
Flujo 7 · Gastos generales, no presupuestados y servicios
con vigencia
Corre en paralelo todo el tiempo y ordena tres clases de gasto que en la conversación diaria se
mezclan. Los gastos generales del proyecto (el SCTR de su personal, la movilidad a la obra, la
comisión de su carta fianza) ya salen presupuestados y van a la partida PC-04, como cualquier otra.
Los gastos generales de la empresa (el alquiler, la póliza del local, el contador) no pertenecen a
ningún proyecto: son el gasto directo y van a un centro de costo. Y los gastos del proyecto que no
estaban presupuestados van a la partida PC-99. Esta tercera clase es la que hace al sistema
realmente útil.
|  1  |  Se registra un gasto no presupuestado
|  Especialista o jefe de proyecto · aprueba la Jefatura de Proyectos
|  Surge algo que no estaba en el presupuesto. Se registra contra la PC-99 con un motivo de una
|  lista cerrada (imprevisto en obra, error de metrado, cambio pedido por el cliente, precio mayor al
|  presupuestado, reposición por pérdida o daño, otro) y un comentario obligatorio. Siempre pasa
|  por aprobación. Al aprobarse, se ve como sobrecosto en el presupuesto contra real y baja el
|  margen estimado ese mismo día, no al cierre.
|  Gasto no  |  Otro caso: cambio pedido
|  posible adicional
|  presupuestado · PRY-  |  Enviar a aprobación  |  por el cliente
|  2026-012
|  Concepto  |  Monto
|  CONCEPTO  |  2 cámaras más en la  |  S/ 1,286.00
|  esquina norte
|  Taxi para traer herramienta olvidada
|  MONTO  |  Aviso enviado a Administración de Contratos: este gasto
|  S/ 45.00  |  podría cobrarse con una adenda de adicional.
|  MOTIVO · LISTA CERRADA
|  Si el adicional se aprueba, el gasto pasa a una partida
|  Imprevisto en obra  |  nueva con su presupuesto y deja de ser sobrecosto.
|  COMENTARIO · OBLIGATORIO
|  La camioneta estaba en el Hospital Regional
|  Margen estimado: S/ 1,372 → S/ 1,327 al aprobarse
La partida PC-99 existe en todo proyecto desde que se aprueba su árbol. Todo lo que cae ahí pide motivo, pasa por aprobación
y se ve como sobrecosto.
Hay dos detalles de diseño que marcan la diferencia. Primero, el sistema distingue el exceso de
partida (la partida existía pero se gastó más, como el cable) del no presupuestado (el gasto no
tenía partida). Separarlos permite saber si el problema está en los precios o en lo que se olvidó
presupuestar. Segundo, cuando el motivo es cambio pedido por el cliente, el sistema lo marca
como posible adicional y avisa a Administración de Contratos. Si se aprueba la adenda de adicional, el
gasto se reclasifica a una partida nueva con su presupuesto y deja de ser sobrecosto: se convirtió en
ingreso. Con el tiempo, el sistema muestra qué se olvida presupuestar con más frecuencia, y esa
información vuelve a Preventa. AJUSTE SET 2026
|  2  |  Se controlan los servicios con vigencia
|  Responsable de cada servicio
|  Pueden ser de un proyecto (el SCTR de su personal) o de la empresa (el alquiler, las licencias).
|  Cada uno genera sus órdenes periódicas y avisa antes de vencer. El SCTR se enlaza al proyecto:
|  si el contrato se amplía, el sistema recuerda ampliar la cobertura.
|  Servicios con vigencia  |  Buscar por proveedor o concepto…  |  + Registrar
|  Vigentes  |  Vencen en 30 días  |  Vencidos
|  Concepto  |  Proveedor  |  Imputación  |  Pago  |  Vence
|  SCTR · personal de obra Plaza de  |  Aseguradora PRY-012 › PC-04  |  único · 180.00  |  30 set 13 días
|  Armas
|  Póliza multirriesgo de oficina  |  Broker  |  Gasto directo ›  |  anual  |  15 oct
|  Administración  |  28 días
|  Alquiler de local  |  Inmobiliaria  |  Gasto directo ›  |  mensual ·  |  31 dic
|  Administración  |  2,800.00
|  Licencias S10 (7 módulos)  |  S10  |  Gasto directo ›  |  anual  |  jul 2027
|  Administración
Servicios con vigencia: contratos del lado del proveedor. Cada fila genera sus órdenes periódicas y avisa antes de vencer.
|  3  |  Se rinden los viáticos de campo
|  Técnico · aprueba su jefatura
|  Se entregan fondos, el técnico rinde desde el celular con foto de cada boleta (leída por la IA y
|  confirmada por él), se aprueba, y cada gasto se imputa.
|  Rendición · Técnico J. Chero  |  por revisar  |  Aprobar
|  Gasto  |  Monto Imputación
|  Pasaje Chiclayo–Lambayeque · boleto  |  12.00 PRY-012 › PC-04
|  Almuerzo · boleta  |  15.00 PRY-012 › PC-04
|  Entregado S/ 50.00 · rendido S/ 27.00 · saldo a devolver S/ 23.00
Rendición de viáticos, registrada desde el celular con foto del comprobante.
|  CUANDO LAS COSAS NO SALEN COMO SE ESPERA
|  Gasto directo que en realidad era de un proyecto. Se reclasifica con un ajuste aprobado
|  que lo mueve del centro de costo a la partida.
|  Gasto no presupuestado que en realidad cabía en una partida existente. Se reclasifica
|  a esa partida con aprobación, y el motivo queda en el historial.
|  Rendición con saldo a favor del técnico. Se registra como reembolso pendiente en cuentas
|  por pagar.
|  Decisión: el gasto directo lleva un campo de proyecto opcional. Con proyecto, es la PC-99 de
|  ese proyecto; sin proyecto, el centro de costo de la empresa. Ambos casos se manejan con la
|  misma pantalla.
```
