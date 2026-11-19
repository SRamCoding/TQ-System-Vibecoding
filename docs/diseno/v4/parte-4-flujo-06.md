# v4 · Flujo 6 · El cierre del proyecto

> Extracto de la parte 4 de la especificación v4. Fichas de sus documentos: `parte-6-fichas-documentos.md`. Escenarios de prueba: `parte-8-criterios-aceptacion.md`.

```text
Flujo 6 · El cierre del proyecto
El proyecto no termina con la última factura. Termina cuando no queda nada abierto con el cliente, con
los proveedores ni con el banco, y cuando lo aprendido quedó guardado para el siguiente presupuesto.
|  1  |  Se revisa presupuesto contra real
|  Jefatura de Proyectos · Robert
|  Es la pantalla que responde la pregunta que originó todo el proyecto del ERP. Cada monto se
|  puede abrir hasta los documentos que lo forman.
|  Presupuesto vs real · PRY-2026-012  |  Por partida  |  Por insumo  |  Exportar
|  Partida  |  Presupuestado  |  Comprometido  |  Ejecutado  |  Disponible  |  %
|  PC-01.01 Cámaras y conectores  |  5,830.00  |  5,780.00  |  5,780.00  |  50.00  |  99 %
|  PC-01.02 Mano de obra cámaras  |  600.00  |  0.00  |  0.00  |  600.00  |  0%
|  PC-02.01 Cable  |  1,512.00  |  1,560.00  |  1,248.00  |  −48.00  |  103 %
|  PC-02.02 Postes y cimentación  |  3,880.00  |  3,400.00  |  3,400.00  |  480.00  |  88 %
|  PC-02.03 Mano de obra  |  1,380.00  |  0.00  |  0.00  |  1,380.00  |  0%
|  PC-03 Centro de monitoreo  |  4,548.00  |  0.00  |  0.00  |  4,548.00  |  0%
|  PC-04 Gastos generales  |  1,775.00  |  960.00  |  960.00  |  815.00  |  54 %
|  PC-99 No presupuestados  |  0.00  |  0.00  |  0.00  |  0.00  |  —
|  Total  |  19,525.00  |  11,700.00  |  11,388.00  |  7,825.00  |  60 %
|  Disponible = presupuestado − comprometido. Cada monto abre los documentos que lo forman. Situación al 17 set, antes de cerrar el
|  tareo de la semana 37.
Presupuesto contra real. La pantalla que responde la pregunta que originó el proyecto.
|  2  |  Se verifica que no quede nada abierto
|  Jefatura de Proyectos
|  El cierre es una lista de verificación: el botón se activa cuando no quedan órdenes abiertas,
|  material asignado sin despachar, tareo sin cerrar, facturas retenidas ni conformidades
|  pendientes.
|  Cerrar proyecto · PRY-2026-012  |  3 pendientes  |  Cerrar proyecto
|  Condición  |  Estado
|  Conformidad final registrada  |  ✓
|  Órdenes abiertas  |  1 · OC-0142 recibida parcial
|  Material asignado sin despachar  |  ✓ ninguno
|  Tareo sin cerrar  |  semana 39
|  Facturas de proveedor retenidas  |  1
|  Comprobante de venta emitido y aceptado  |  ✓
|  AL CERRAR
|  Se congela el presupuesto contra real, se calcula el margen real y se habilita la devolución de la garantía al terminar su
|  plazo.
Cierre de proyecto como lista de verificación. El botón se activa cuando no queda nada abierto.
|  3  |  Se calcula el margen real y se aprende
|  Gerencia de Operaciones · Preventa y Costeo
|  Al cerrar se congela el presupuesto contra real y se calcula el margen real. Las diferencias por
|  insumo (el cable se desperdició más de lo previsto, un proveedor subió precios) quedan
|  disponibles para Preventa como «histórico de proyectos similares» al armar el siguiente
|  presupuesto.
|  4  |  Se devuelve la garantía
|  Tesorería
|  Al terminar el plazo que la condiciona, la carta fianza se libera y se registra su devolución. Hasta
|  entonces sigue en el tablero de garantías.
|  CUANDO LAS COSAS NO SALEN COMO SE ESPERA
|  Garantía técnica vigente después del cierre. El proyecto se cierra, pero sus series quedan
|  en garantía y los reclamos se atienden en el flujo 8.
|  Costos que llegan tarde (una factura de transporte un mes después). Se registran como
|  ajuste posterior al cierre, con aprobación, y recalculan el margen real con marca de ajuste.
```
