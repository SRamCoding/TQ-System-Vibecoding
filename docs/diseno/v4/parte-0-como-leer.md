# v4 · Cómo leer este documento

> Extracto de texto de `fuentes/Techquk-Sistema-CRM-ERP-v4.pdf` (21 set 2026), líneas 1 a 67. Las tablas del PDF aparecen con columnas separadas por « | ». Si algo no se entiende, consulta el PDF.

```text
TECHQUK S.A .C. · ANÁLISIS FUNCIONAL
El sistema de Techquk: CRM
y ERP
Cómo funciona y cómo se construye, de la convocatoria al cierre del
contrato
Especificación funcional unificada · Versión 4 · 21 de setiembre de 2026 · Reemplaza a «El CRM de Techquk» y a
«El ERP de Techquk v3»
|  Cómo leer este documento
|  Es un solo sistema con dos caras: la comercial (CRM), que se ocupa de ganar, y la operativa (ERP),
|  que se ocupa de cumplir y de ganar dinero con lo firmado. No se entiende módulo por módulo,
|  porque lo importante pasa entre los módulos: una convocatoria se vuelve oportunidad, la
|  oportunidad contrato, el contrato proyecto, el requerimiento orden, la orden costo de una partida.
|  Por eso va de lo general a lo detallado y sigue el orden en que ocurren las cosas. Cada lector
|  puede quedarse en su parte:
|  Parte  |  Qué contiene  |  Para quién
|  1 · El modelo  |  Las cuatro ideas que sostienen el sistema, cómo se mueven el costo y  |  Todos
|  mental  |  el dinero, dónde entra la IA, el caso de ejemplo y el glosario
|  2 · La cara  |  El recorrido de la convocatoria al contrato, pantalla por pantalla, con  |  Comercial y
|  comercial (CRM)  |  las lecturas asistidas por IA  |  todos
|  3 · La frontera  |  El momento en que se gana y el CRM le entrega al ERP  |  Todos
|  4 · Los flujos del  |  Diez recorridos de punta a punta, incluidos los contratos de obra  |  Todos
|  ERP  |  pública, con las pantallas en el momento en que se usan y lo que pasa
|  cuando algo sale mal
|  5 · La interfaz y los  |  Cómo se ve el sistema, qué ve cada persona, la grilla de roles y el  |  Usuarios, diseño
|  roles  |  celular  |  y Gerencia
|  6 · Las fichas de  |  Campos, estados, transiciones y efectos de cada documento  |  Desarrollo
|  documentos
|  7 · Lo transversal  |  Permisos, aprobaciones, alertas, catálogo de IA, integraciones y  |  Desarrollo y
|  migración  |  Gerencia
|  8 · Los criterios de  |  Escenarios de prueba con números exactos  |  Desarrollo y
|  aceptación  |  pruebas
|  9 · Construcción y  |  Entregas, registro de decisiones y lo que falta decidir  |  Gerencia y
|  decisiones  |  equipo
Las marcas que aparecen en el texto: AJUSTE SET 2026 algo que la propuesta de agosto (documento
10) no tenía y la revisión de setiembre mostró necesario · POR VALIDAR una suposición que hay que
confirmar con el personal antes de construir · FASE POSTERIOR algo que el modelo debe soportar desde
ya, pero se construye después.
Los bocetos muestran estructura y comportamiento, no diseño gráfico final. Los datos de ejemplo son ficticios.
|  PARTE 1
```
