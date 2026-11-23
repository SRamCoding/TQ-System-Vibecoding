# Registro de decisiones

Toda decisión que tome Sebastián en este repositorio, en orden, con un número que no se repite (`MV-001`, `MV-002`…).
Es el origen que citarán los planes, los ADR y los registros del repo original: por eso **nunca se borra ni se
reescribe una entrada**. Si Sebastián cambia de idea, se agrega una decisión nueva y la anterior pasa a «reemplazada
por MV-0NN». Solo cambia el estado.

Las fichas dicen qué decisiones están vigentes para cada funcionalidad; este registro dice cómo se llegó ahí.

## Cómo se anota

Una entrada por decisión, la más nueva abajo:

```markdown
### MV-0NN · AAAA-MM-DD · <título corto>

- **Ficha:** `fichas/<ficha>.md` (o «general»)
- **Dijo Sebastián:** sus palabras, citadas cortas, o lo que eligió entre las opciones que le dio el agente
- **Decisión:** qué queda decidido, en una o dos frases
- **Alcance:** ficha · cruza módulos · negocio
- **Estado:** vigente · reemplazada por MV-0NN · descartada
- **Commit:** `<hash>` donde se construyó
```

**Alcance** le dice al repo original dónde va cada una:

- **ficha:** se queda dentro de una funcionalidad (cómo se ve, cómo se navega, qué campo pide). Allá va a la sección
  «Decisiones» del plan de la tarea.
- **cruza módulos:** toca la estructura, una costura entre áreas, el núcleo o un mecanismo compartido. Allá se vuelve
  un ADR.
- **negocio:** dice cómo trabaja Techquk (una regla, un monto, quién aprueba). Sebastián la decidió, pero la empresa
  no la validó: allá entra al análisis y al registro de validaciones como «por validar» con la persona que
  corresponda.

Lo que el agente eligió solo **no va aquí**: va a «Supuestos sin confirmar» de su ficha. Si Sebastián después lo
confirma, recién entonces se vuelve una decisión con su número.

## Decisiones

### MV-001 · 2026-11-19 · La cartera de demostración son los nueve proyectos del plan viejo

- **Ficha:** `fichas/t0-datos-demo.md`
- **Dijo Sebastián:** eligió «Los 9 del plan» entre reutilizarlos, reutilizarlos con fechas recientes o una cartera
  más chica.
- **Decisión:** la demo usa la cartera P1–P9 del plan `2026-11-16-datos-desarrollo-cartera.md` tal cual: sus montos,
  partidas, márgenes, fechas históricas 2024–2026 y estados (cinco cerrados, tres en ejecución, uno en preparación).
- **Alcance:** ficha
- **Estado:** vigente
- **Commit:** el de «T0: datos de demostración»

### MV-002 · 2026-11-19 · El hospital de la demo es inventado

- **Ficha:** `fichas/t0-datos-demo.md`
- **Dijo Sebastián:** «Inventarlo», en vez de mantener el nombre real que permitía la excepción D2 del plan viejo.
- **Decisión:** P6 y P9 son del «Hospital Regional de Valle Norte», inventado. En los datos de demo no queda ningún
  nombre real; la excepción D2 del plan viejo no se trae.
- **Alcance:** ficha
- **Estado:** vigente
- **Commit:** el de «T0: datos de demostración»

### MV-003 · 2026-11-19 · Gastos de demostración como origen provisional del costo

- **Ficha:** `fichas/t0-datos-demo.md`
- **Dijo Sebastián:** eligió «Gasto de demostración» entre eso, usar solo la PC-99 o no tener gastos hasta el tramo 3.
- **Decisión:** mientras no existan órdenes, tareo ni rendiciones, el costo de los proyectos de demo entra por un
  documento mínimo «gasto de demostración», imputado a una partida de control (o a la PC-99) con sus montos
  comprometido, ejecutado y pagado. Desaparece cuando lleguen las órdenes de compra (tramo 3).
- **Alcance:** ficha
- **Estado:** vigente
- **Commit:** el de «T0: datos de demostración»

### MV-004 · 2026-11-19 · Embudo abierto de la demo

- **Ficha:** `fichas/t0-datos-demo.md`
- **Dijo Sebastián:** eligió «~15 abiertas + 3 perdidas».
- **Decisión:** además de las nueve ganadas, la demo trae unas quince oportunidades abiertas repartidas en todas las
  etapas y tres perdidas, con cotizaciones en distintos estados, sobre unas quince cuentas públicas y privadas.
- **Alcance:** ficha
- **Estado:** vigente
- **Commit:** el de «T0: datos de demostración»

### MV-005 · 2026-11-19 · El tramo 1 empieza por el puente al ERP

- **Ficha:** `fichas/t1-puente-erp.md`
- **Dijo Sebastián:** eligió «Puente al ERP» entre empezar por él, por licitaciones o por la agenda.
- **Decisión:** el orden del tramo 1 es: puente al ERP (ganar → proyecto con presupuesto copiado y comparación),
  después licitaciones, agenda, catálogo y leads.
- **Alcance:** ficha
- **Estado:** vigente
- **Commit:** el de «T1: puente al ERP»

### MV-006 · 2026-11-19 · El proyecto nace solo al ganar

- **Ficha:** `fichas/t1-puente-erp.md`
- **Dijo Sebastián:** «Solo, al ganar», en vez de un botón «Crear proyecto» aparte.
- **Decisión:** ganar la oportunidad, crear su proyecto y copiar lo cotizado como su presupuesto ocurren en un solo
  paso; si la copia falla, no se gana (confirma el ADR 0023 del repo original).
- **Alcance:** cruza módulos
- **Estado:** vigente
- **Commit:** el de «T1: puente al ERP»

### MV-007 · 2026-11-19 · Numeración por dueño desde ahora

- **Ficha:** `fichas/t1-puente-erp.md`
- **Dijo Sebastián:** «Sí, ahora», aunque haya que reconstruir la base.
- **Decisión:** costeos, cotizaciones y presupuestos se numeran por su dueño como dice el ADR 0023:
  «OP-2026-0007 · COT-01 v1», «OP-2026-0007 · PRE-01 v1», «PRY-2026-0013 · PRE-01 v1»; sin dueño, «PRE-2026-0031 v1».
- **Alcance:** cruza módulos
- **Estado:** vigente
- **Commit:** el de «T1: puente al ERP»

### MV-008 · 2026-11-19 · La comparación ofertado contra presupuesto va en su propia pestaña

- **Ficha:** `fichas/t1-puente-erp.md`
- **Dijo Sebastián:** eligió «Pestaña propia» entre eso, columnas dentro del presupuesto o un panel deslizante.
- **Decisión:** la ficha del proyecto tiene una pestaña «Ofertado vs. presupuesto», partida por partida, junto a la del
  presupuesto; el tablero muestra la diferencia total y lleva a esa pestaña.
- **Alcance:** ficha
- **Estado:** vigente
- **Commit:** el de «T1: puente al ERP»

### MV-009 · 2026-11-19 · Hasta terminar el sistema, cada ficha se da por aprobada

- **Ficha:** general
- **Dijo Sebastián:** «al final que termines con todo el sistema allí empezaremos a hacer cambios, por ahora asume que
  sí me gusta».
- **Decisión:** el agente construye la ruta completa sin detenerse a pedir aprobación: cada ficha pasa a «aprobada»
  con su etiqueta al terminarla. Lo que normalmente se preguntaría se resuelve con la opción recomendada y queda en
  «Supuestos sin confirmar» de su ficha. Los cambios de Sebastián llegan al final, con fichas en versión nueva.
- **Alcance:** ficha (forma de trabajo de este repositorio; no es una decisión del producto)
- **Estado:** vigente
- **Commit:** el de «Fichas t0-datos-demo v2 y t1-puente-erp aprobadas»

### MV-010 · 2026-11-19 · El modo «Sistema» sigue el cielo de la sede

- **Ficha:** `fichas/transicion-cielo.md` (se abre al construirla)
- **Dijo Sebastián:** «en modo sistema quiero que exista una transición pues viendo el clima de la ubicación y también
  el transicionamiento de día a noche»; para la ubicación eligió «sede» (en vez de la del navegador).
- **Decisión:** en el modo de apariencia «Sistema», la pantalla deja de copiar al sistema operativo y sigue el cielo
  de la sede de la empresa: claro u oscuro según la salida y puesta del sol de ese lugar y fecha, con una transición
  gradual al amanecer y al atardecer, y el clima la matiza (nublado o lluvia más apagado y atardecer más temprano).
  «Claro» y «Oscuro» siguen fijos.
- **Alcance:** cruza módulos (interfaz del núcleo)
- **Estado:** reemplazada por MV-012
- **Commit:** se anota al construirla

### MV-011 · 2026-11-19 · La transición del cielo va después del tramo 3

- **Ficha:** general
- **Dijo Sebastián:** «hazlo después del tramo 3».
- **Decisión:** el orden sigue: tramo 3 (flujo 2) y, al terminarlo, la transición del cielo (MV-010); después, el tramo 4.
- **Alcance:** ficha
- **Estado:** vigente
- **Commit:** —

### MV-012 · 2026-11-19 · La ubicación del navegador manda si ya está permitida

- **Ficha:** `fichas/transicion-cielo.md` (se abre al construirla)
- **Dijo Sebastián:** «ahora si ya tiene la ubicación permitida pues esa manda».
- **Decisión:** igual que MV-010 (en «Sistema», la pantalla sigue el cielo: sol, transición gradual y clima), pero la
  ubicación es la del navegador si la persona ya dio el permiso; si no lo dio, la de la sede de la empresa. El sistema
  no pide el permiso solo para esto.
- **Alcance:** cruza módulos (interfaz del núcleo)
- **Estado:** vigente
- **Commit:** se anota al construirla

### MV-013 · 2026-11-20 · El cielo cambia como en la realidad, sin saltos

- **Ficha:** `fichas/t3b-transicion-cielo.md`
- **Dijo Sebastián:** «algo que sea similar a lo que pasa en realidad, el sol se va escondiendo y el ambiente es como
  de un color y luego más oscuro poco a poco hasta que en una hora en particular ya está oscuro».
- **Decisión:** en el tema «Sistema», el ambiente sigue la altura del sol de forma continua: luz dorada mientras baja,
  naranja y rosado al ocultarse, la hora azul y luego oscuro, sin un cambio de tema perceptible; al amanecer, al revés.
  Precisa MV-012 (la ubicación sigue igual).
- **Alcance:** ficha
- **Estado:** vigente
- **Commit:** el de «Transición del cielo»

### MV-014 · 2026-11-20 · Todo lo pendiente entra al MVP

- **Ficha:** general
- **Dijo Sebastián:** «todas de una vez», sobre las partes simuladas (XML, SUNAT, notas por foto con IA), lo que faltaba
  del tramo 5 (moneda extranjera, servicios con vigencia, liquidación, consorcios) y los opcionales (gran momento,
  asistente de ayuda con IA, solicitudes de permiso).
- **Decisión:** se construyen todas antes de cerrar el MVP. La validación con SUNAT queda lista para activarse con las
  credenciales de la empresa; mientras, simulada.
- **Alcance:** ficha
- **Estado:** vigente
- **Commit:** los de «Cierre del tramo 5» y «Opcionales»

### MV-015 · 2026-11-20 · Lo que el v4 tenía y la ruta dejó fuera también entra

- **Ficha:** general
- **Dijo Sebastián:** «sí, sigue con todo de una vez», sobre la bandeja del SEACE y lo planeado que faltaba: flujo 8
  (postventa, con series de almacén), flujo 9 (las personas, con planilla), tableros de inicio por rol y el cierre del
  proyecto (flujo 6).
- **Decisión:** se construyen antes de cerrar el MVP, en ese orden.
- **Alcance:** ficha
- **Estado:** vigente
- **Commit:** los de «Postventa», «Personas», «Tableros» y «Cierre»

### MV-016 · 2026-11-20 · El sistema se organiza por procesos: cuatro franjas y 11 cajas

- **Ficha:** `fichas/r1-inicio-procesos.md`
- **Dijo Sebastián:** (conversado el 19 y 20 de noviembre) «Dejan de existir las áreas Núcleo, CRM y ERP. Hay cuatro
  franjas, en plural: Estratégicos, Misionales, De Apoyo y Plataforma. 11 cajas en total. Al abrir cada caja aparecen
  sus cajas internas […], que son las pantallas que ya existen».
- **Decisión:** el inicio y la barra lateral muestran procesos, no módulos. Estratégicos: Techquk (¿Cuánto se ganó?,
  Presupuesto de la empresa, Lo aprendido, Reglas del negocio). Misionales: Conseguir el negocio (Oportunidades,
  Convocatorias del SEACE, Campañas), Gerencia de proyectos (Proyectos, Contratos, Personal asignado, Tareo,
  Subcontratos), Abastecer (Requerimientos, Órdenes, Almacén), Facturación y cobranza (Valorizaciones, Facturas
  emitidas, Cobranzas), Postventa (Tickets y series, Suscripciones). De Apoyo: Presupuestar y cotizar (Presupuestos,
  Cotizaciones), Finanzas (Facturas de proveedor, Tablero de pagos, Flujo de caja y conciliación, Rendiciones,
  Servicios con vigencia, Para el contador), Personas (Fichas laborales, Demanda y selección, Planilla), Directorio y
  catálogos (Personas y empresas, Catálogo de productos, Listas, Organigrama). Plataforma: Sistema (Usuarios,
  Configuración, Solicitudes de permiso, Tickets de ayuda). Reglas del negocio (aprobaciones y parámetros sensibles) y
  Listas (catálogos y motivos) salen de Configuración. Los nombres de cajas y franjas son exactamente esos.
- **Alcance:** cruza módulos
- **Estado:** vigente
- **Commit:** el de «Ronda 1 · paso 1: el inicio por procesos»

### MV-017 · 2026-11-20 · Mien va arriba, a lo ancho, y absorbe «Mi tablero»

- **Ficha:** `fichas/r1-inicio-procesos.md`
- **Dijo Sebastián:** «Mien va arriba de todo, a lo ancho, y absorbe "Mi tablero" (el tablero por rol)».
- **Decisión:** en el inicio, Mien es una caja ancha encima de las franjas. El tablero por rol deja de ser una caja y
  se ve dentro de Mien, debajo de sus tarjetas, con las mismas pestañas por rol.
- **Alcance:** cruza módulos
- **Estado:** vigente
- **Commit:** el de «Ronda 1 · paso 1: el inicio por procesos»

### MV-018 · 2026-11-20 · Las franjas se reparten solas en dos columnas

- **Ficha:** `fichas/r1-inicio-procesos.md`
- **Dijo Sebastián:** «Las franjas se reparten en dos columnas de alto parecido. Cada franja va junta, con su título.
  El reparto se calcula solo según las cajas que ve cada persona (filtradas por su rol). Si una franja queda vacía para
  alguien, desaparece con su título. En el celular, una columna».
- **Decisión:** lo dicho, para cada persona según sus permisos.
- **Alcance:** ficha
- **Estado:** vigente
- **Commit:** el de «Ronda 1 · paso 1: el inicio por procesos»

### MV-019 · 2026-11-20 · Los colores siguen como están; los nuevos se consultan

- **Ficha:** `fichas/r1-inicio-procesos.md`
- **Dijo Sebastián:** «Los colores siguen por ahora como están. Que cada franja se distinga es lo que importa;
  pregúntame antes de elegir colores nuevos».
- **Decisión:** no se crea ningún color. Cada franja usa uno de los colores de siempre y se distingue de las otras.
  Los colores propios de cada franja quedan pendientes de Sebastián.
- **Alcance:** ficha
- **Estado:** reemplazada por MV-033
- **Commit:** el de «Ronda 1 · paso 1: el inicio por procesos»

### MV-020 · 2026-11-20 · Cada proceso abre una portada con sus cajas; dentro, pestañas y miga

- **Ficha:** `fichas/r1-inicio-procesos.md`
- **Dijo Sebastián:** eligió «Portada con cajas» (y no entrar directo con pestañas, como Configuración) y «Pestañas +
  miga» (y no solo la miga), entre las opciones que le dio el agente.
- **Decisión:** la caja de un proceso abre su portada, con sus cajas internas del tamaño de las del inicio. Dentro de
  una pantalla interna, arriba, la miga «Proceso › Pantalla» para volver a la portada y una fila de pestañas con las
  pantallas hermanas del proceso. La barra lateral muestra las cajas de proceso.
- **Alcance:** cruza módulos
- **Estado:** reemplazada por MV-035
- **Commit:** el de «Ronda 1 · paso 1: el inicio por procesos»

### MV-021 · 2026-11-20 · Comprobantes se parte entre Facturación y cobranza y Finanzas

- **Ficha:** `fichas/t5-finanzas.md`
- **Dijo Sebastián:** «Las facturas que emite Techquk van a Facturación y cobranza ("Facturas emitidas", junto a
  "Cobranzas"). Las facturas de proveedor, con su cruce de tres vías, van a Finanzas ("Facturas de proveedor")».
- **Decisión:** ya no hay una pantalla de comprobantes: las ventas se ven en Facturas emitidas y Cobranzas; las compras,
  con el cruce de tres vías, en Facturas de proveedor.
- **Alcance:** cruza módulos
- **Estado:** vigente
- **Commit:** el de «Ronda 1 · paso 2: partir Comprobantes»

### MV-022 · 2026-11-20 · Cuentas vive dentro de Personas y empresas

- **Ficha:** `fichas/r1-personas-empresas.md`
- **Dijo Sebastián:** «Desaparece la caja Cuentas. Lo que tenía su ficha (quién atiende, contactos, actividad,
  oportunidades abiertas, histórico de precios de la entidad) se ve en la ficha de la persona o empresa. Al entrar a
  Personas y empresas hay dos cajas internas: "Clientes y entidades" y "Proveedores y colaboradores". Una empresa con
  los dos papeles es un solo registro que aparece en las dos. La coherencia de datos se trabaja después».
- **Decisión:** lo dicho. La coherencia de datos entre los dos papeles queda para después.
- **Alcance:** cruza módulos
- **Estado:** vigente
- **Commit:** el de «Ronda 1 · paso 3: Cuentas dentro de Personas y empresas»

### MV-023 · 2026-11-20 · La licitación es una sección de la oportunidad

- **Ficha:** `fichas/t1-licitaciones.md`
- **Dijo Sebastián:** «Licitaciones deja de ser caja. La licitación es una sección de la ficha de la oportunidad cuando
  el cliente es una entidad pública: bases, cronograma, expediente, consultas, propuesta y resultado. El resultado
  cierra la oportunidad. La lista de licitaciones con sus plazos es una pestaña o filtro en la portada de
  Oportunidades. La bandeja del SEACE sigue como caja interna ("Convocatorias del SEACE")».
- **Decisión:** pestaña «Licitación» en la oportunidad con las seis partes y sus acciones; el resultado la cierra
  (ganada con la buena pro, perdida si la ganó otro o quedó desierta). «Licitaciones» es una pestaña de la portada de
  Oportunidades.
- **Alcance:** cruza módulos
- **Estado:** reemplazada en parte por MV-074 (la pestaña «Licitaciones» de la portada; la sección de la oportunidad sigue)
- **Commit:** el de «Ronda 1 · paso 4: licitaciones y leads dentro de la oportunidad»

### MV-024 · 2026-11-20 · Los leads son la primera etapa del embudo; Campañas muestra resultados

- **Ficha:** `fichas/t1-campanias.md`
- **Dijo Sebastián:** «Los leads son la primera etapa del embudo ("por calificar"). Campañas se queda como caja interna
  pequeña: la lista de campañas con sus resultados (leads, conversión, monto, costo por lead)».
- **Decisión:** «Por calificar» es la primera pestaña de la portada de Oportunidades; la caja Campañas abre la lista de
  campañas con leads, convertidos y tasa, monto generado y costo por lead.
- **Alcance:** cruza módulos
- **Estado:** reemplazada en parte por MV-074 (la pestaña «Por calificar»; Campañas con sus resultados sigue)
- **Commit:** el de «Ronda 1 · paso 4: licitaciones y leads dentro de la oportunidad»

### MV-025 · 2026-11-20 · La agenda es una herramienta del muelle

- **Ficha:** `fichas/t1-agenda.md`
- **Dijo Sebastián:** «La agenda sale del inicio y es una herramienta del muelle, con un ícono de calendario con reloj,
  arriba del de Recortes. Al presionarlo aparecen en el centro dos recuadros pegados: el grande (calendario) muestra
  primero el año completo y enseguida, solo, una animación de acercamiento hasta el mes actual. El día de hoy tiene una
  flecha arriba que lo señala, y los días tienen colores según sus actividades (vencidas, por vencer, programadas,
  hechas); el angosto, pegado al lado (descripciones): al presionar un día, muestra sus actividades con quién, de qué
  cliente u oportunidad y su estado. Mien muestra además una tarjeta "Mi agenda" con lo de hoy, lo vencido y lo que
  está por vencer».
- **Decisión:** lo dicho; la caja Agenda deja el inicio.
- **Alcance:** cruza módulos
- **Estado:** vigente
- **Commit:** el de «Ronda 1 · paso 5: la agenda en el muelle»

### MV-026 · 2026-11-20 · En la agenda: botón «Año» y se registra y marca desde las descripciones

- **Ficha:** `fichas/t1-agenda.md`
- **Dijo Sebastián:** eligió «Botón "Año"» (y no el clic en el nombre del mes ni la rueda) y «Registrar y marcar hecha»
  (y no solo marcar ni solo ver), entre las opciones que le dio el agente.
- **Decisión:** un botón «Año» junto al nombre del mes hace la animación al revés y, en el año, un clic en un mes se
  acerca a él. En el recuadro de descripciones cada actividad pendiente tiene «Marcar hecha», y abajo «Registrar
  actividad» para ese día (tipo, cliente, oportunidad opcional, asunto y hora).
- **Alcance:** ficha
- **Estado:** vigente
- **Commit:** el de «Ronda 1 · paso 5: la agenda en el muelle»

### MV-027 · 2026-11-20 · La cotización del proveedor se llama «oferta de proveedor» (OFP-001)

- **Ficha:** `fichas/t3-compras.md`
- **Dijo Sebastián:** «Lo que hoy se llama "cotización del proveedor" pasa a llamarse "oferta de proveedor", con su
  propia numeración (OFP-001). La palabra "cotización" queda solo para lo que Techquk le ofrece a sus clientes».
- **Decisión:** lo dicho, en pantallas, documentos y datos.
- **Alcance:** cruza módulos
- **Estado:** vigente
- **Commit:** el de «Ronda 1 · paso 6: ofertas de proveedor y herramientas»

### MV-028 · 2026-11-20 · Control de herramientas propias en Abastecer

- **Ficha:** `fichas/t3-compras.md`
- **Dijo Sebastián:** «Nuevo, dentro de Abastecer, junto al almacén: el control de herramientas propias (taladros,
  escaleras, camioneta, equipos de medición): qué herramienta está en qué obra, quién la tiene y cuándo debe volver.
  Por ahora solo se controla dónde está; si su uso se carga como costo al proyecto queda pendiente».
- **Decisión:** caja «Herramientas» en Abastecer que solo controla dónde está cada una. **Pendiente de Sebastián:** si
  su uso cuesta al proyecto.
- **Alcance:** negocio
- **Estado:** vigente
- **Commit:** el de «Ronda 1 · paso 6: ofertas de proveedor y herramientas»

### MV-029 · 2026-11-20 · «Ejecutar proyecto» y los nombres exactos de cajas y franjas

- **Ficha:** `fichas/r1-inicio-procesos.md`
- **Dijo Sebastián:** «"Ejecutar la obra" pasa a ser "Ejecutar proyecto" donde aparezca. Revisa que los nombres de las
  cajas y los títulos de las franjas digan exactamente lo de arriba». Y después: «la franja se llama "Plataforma" y su
  única caja se llama "Sistema" (igual que la franja Estratégicos tiene una sola caja, "Techquk"). Las cuatro franjas
  llevan título».
- **Decisión:** el nombre del paso es «Ejecutar proyecto», nunca «Ejecutar la obra». Las cajas, sus cajas internas y
  los títulos de las franjas dicen exactamente lo de MV-016; cada pantalla se titula como su caja.
- **Alcance:** cruza módulos
- **Estado:** vigente
- **Commit:** el de «Ronda 1 · paso 7: nombres»

### MV-030 · 2026-11-20 · Ícono propio de «Conseguir el negocio»: la montaña con la bandera

- **Ficha:** `fichas/r1-inicio-procesos.md`
- **Dijo Sebastián:** (`docs/vibe/pedidos/icono-conseguir-el-negocio.md`) «una montaña de dos picos con una bandera
  clavada en la cima del pico principal: ganar el negocio es llegar a la cima. Solo el dibujo, sin ningún texto».
- **Decisión:** la caja usa un ícono propio de línea, de 24 × 24, trazo 1,9 y un solo color (el de su franja): el pico
  principal a la izquierda, el más bajo detrás a la derecha, una línea de nieve en zigzag en cada uno, el mástil en la
  cima y un banderín con muesca en «V». Va en el inicio, la barra lateral y la portada. Referencia en
  `docs/vibe/referencias/icono-conseguir-el-negocio.png`.
- **Alcance:** ficha
- **Estado:** vigente
- **Commit:** el de «Pedido 1: ícono de Conseguir el negocio»

### MV-031 · 2026-11-20 · Ícono propio de «Postventa»: el rompecabezas armado y la flecha que sigue

- **Ficha:** `fichas/r1-inicio-procesos.md`
- **Dijo Sebastián:** (`docs/vibe/pedidos/icono-postventa.md`) «un rompecabezas de cuatro piezas encajadas, con una
  flecha que sale de la pieza de abajo a la derecha y sigue ondulando hacia la derecha. La idea: el proyecto ya está
  armado y completo, pero la relación con el cliente continúa. Solo el dibujo, sin ningún texto».
- **Decisión:** ícono propio de línea, 24 × 24, trazo 1,9, un solo color: el cuadrado de 2 × 2 piezas (unos 14 × 14 a
  la izquierda) con una lengüeta por borde interior, y la flecha ondulada con punta abierta saliendo de la pieza de abajo
  a la derecha. Va en el inicio, la barra lateral y la portada. Referencia en
  `docs/vibe/referencias/icono-postventa.png`.
- **Alcance:** ficha
- **Estado:** vigente
- **Commit:** el de «Pedido 2: ícono de Postventa»

### MV-032 · 2026-11-20 · El inicio centrado: la ventana del saludo y la estrella de Mien

- **Ficha:** `fichas/r1-inicio-procesos.md`
- **Dijo Sebastián:** (`docs/vibe/pedidos/inicio-ventana-y-mien.md`, propuesta aprobada en el lienzo
  https://claude.ai/artifact/QvZvCuBrPV7VLCCcf7ZraW) «Todo lo de arriba del inicio va centrado: la ventana con la
  animación del momento del día, el saludo grande, la frase, Mien sin caja». «La animación del saludo ya no va
  suelta: se ve a través de una ventana». «Mien deja de ser una caja. Es una estrella dorada grande, centrada».
- **Decisión:** de arriba abajo y centrados: la ventana (168 × 180, arco de medio punto, alféizar, dos travesaños y un
  parante, marco de madera) con la escena del cielo según la hora y el clima; el saludo en 40 px; la frase en cursiva
  gris (620 px como máximo); la estrella de Mien (112 px, halo que late, tres destellos, número de pendientes, «Mien»
  y su resumen; pálida y sin halo cuando todo está al día); debajo, las franjas. En la barra lateral, Mien sigue igual.
- **Alcance:** ficha
- **Estado:** vigente
- **Commit:** el de «Pedido 3: inicio centrado, ventana, estrella de Mien y colores por franja»

### MV-033 · 2026-11-20 · Los colores de las franjas

- **Ficha:** `fichas/r1-inicio-procesos.md`
- **Dijo Sebastián:** (punto 4 de `inicio-ventana-y-mien.md`) «reemplaza los colores por área (CRM morado, ERP
  turquesa, Núcleo azul noche). Cada franja queda en un escalón de claridad distinto, para que se distingan aunque
  alguien no perciba bien los colores, y el oro queda solo para Mien». Y en la ronda: «donde te dije "los colores
  siguen por ahora como están" ya no aplica».
- **Decisión:** Misionales turquesa (`#0FC2C4`, texto `#00788A`; oscuro `#00DAFF`), Estratégicos azul noche
  (`#01006F`; oscuro `#6F9BFF`), De Apoyo morado (`#B35FD0`; oscuro `#C3A6FF`), Plataforma gris neutro (`#6E7781`;
  oscuro `#9DA5AE`) y Mien oro (`#936600`; oscuro `#F2C14E`). Todo lo que tomaba el color del área lo toma de su
  franja. Reemplaza MV-019.
- **Alcance:** cruza módulos
- **Estado:** vigente
- **Commit:** el de «Pedido 3: inicio centrado, ventana, estrella de Mien y colores por franja»

### MV-034 · 2026-11-20 · Todas las cajas con la altura de un nombre de dos líneas

- **Ficha:** `fichas/r1-inicio-procesos.md`
- **Dijo Sebastián:** (`docs/vibe/pedidos/altura-de-cajas.md`) «Un poco más de altura en las cajas, para que entre un
  nombre de dos líneas con aire. Todas las cajas de una misma pantalla con la misma altura. El nombre, como máximo en
  dos líneas. Lo mismo en las cajas internas».
- **Decisión:** las cajas del inicio y las internas miden 9 × 7,5 rem (antes 6,5 de alto), todas iguales; el nombre
  ocupa como máximo dos líneas, sin achicar la letra.
- **Alcance:** ficha
- **Estado:** vigente
- **Commit:** el de «Pedido 4: altura pareja de las cajas»

### MV-035 · 2026-11-20 · La portada de cada proceso: frase, cajas centradas como navegación y contenido debajo

- **Ficha:** `fichas/r1-inicio-procesos.md`
- **Dijo Sebastián:** (`docs/vibe/pedidos/portada-de-procesos.md`) «Arriba, centrado, una frase representativa del
  proceso, en el color de su franja. Debajo, solo las cajas internas, juntas como un solo cuerpo, centrado: el conjunto
  de cajas es la barra de navegación del proceso. Al presionar una, no se va a otra página: su contenido aparece debajo
  de las cajas. Al entrar se abre siempre la caja del medio. Las cajas se pueden cambiar de posición; el orden queda
  guardado para cada persona. Una opción "Volver al orden original"». Y, entre las opciones que le dio el agente: que en
  las fichas de detalle la barra «sí, sigue arriba».
- **Decisión:** las 11 cajas de proceso siguen este patrón; reemplaza la portada con cajas y las pestañas con miga
  (MV-020). Al entrar, la caja del medio en el orden de la persona (de 3 la 2.ª; de 4 la 2.ª; de 6 la 3.ª); desde un
  enlace, la de ese enlace. En Conseguir el negocio el orden original es Convocatorias del SEACE, Oportunidades,
  Campañas. Las frases se cambian en Configuración sin tocar el código.
- **Alcance:** cruza módulos
- **Estado:** vigente
- **Commit:** el de «Pedido 5: la portada de cada proceso»

### MV-036 · 2026-11-20 · La frase debajo de las cajas, sutil; reordenar como en el muelle

- **Ficha:** `fichas/r1-inicio-procesos.md`
- **Dijo Sebastián:** «que la frase esté abajo de las cajas y que sea más sutil, esto implica más pequeño y menos
  intensidad de color, y quiero que cuando intento cambiar el orden las cajas, pues sea más natural como lo fue en el
  muelle por ejemplo».
- **Decisión:** la frase va debajo de la barra de cajas, chica (15 px), en cursiva y con el color de su franja apagado
  hacia gris. Reordenar se siente como el muelle: una copia de la caja sigue al puntero (mouse o dedo) y las demás se
  corren en vivo a su nuevo lugar; al soltar, la copia se desliza a su sitio. Precisa MV-035.
- **Alcance:** ficha
- **Estado:** vigente
- **Commit:** el de «La frase debajo y sutil; reordenar como en el muelle»

### MV-037 · 2026-11-20 · Cambiar de pantalla sin recargar toda la página

- **Ficha:** `fichas/r1-inicio-procesos.md`
- **Dijo Sebastián:** (`docs/vibe/pedidos/observaciones-de-navegacion.md`, punto 1) «Que al cambiar de opción solo
  cambie el contenido, sin recargar la página entera. La barra lateral, el encabezado, el muelle y el resto del marco se
  quedan quietos. Vale para toda la navegación del sistema. La dirección se actualiza igual y el botón "atrás" funciona.
  Mientras carga, una señal discreta».
- **Decisión:** toda la navegación cambia solo el contenido; lo que debe seguir vivo (muelle, agenda, asistente,
  cielo, ambientación) se conserva entre pantallas; mientras carga, una barra fina arriba.
- **Alcance:** cruza módulos
- **Estado:** vigente
- **Commit:** el de «Observación 1: cambiar de pantalla sin recargar»

### MV-038 · 2026-11-20 · Ícono propio de «Tickets de ayuda»: el ticket con audífonos de soporte

- **Ficha:** `fichas/r1-inicio-procesos.md`
- **Dijo Sebastián:** (observaciones, punto 2) «La caja Tickets de ayuda lleva un ícono propio: un ticket con unos
  audífonos de soporte».
- **Decisión:** ícono de línea, 24 × 24, trazo 1,9, un solo color: el boleto ancho (casi todo el lienzo) con una muesca
  en cada lado, los audífonos con micrófono a la izquierda y dos líneas de texto a la derecha. En su caja, la barra y su
  pantalla. Referencia en `docs/vibe/referencias/icono-tickets-de-ayuda.png`.
- **Alcance:** ficha
- **Estado:** vigente
- **Commit:** el de «Observación 2: ícono de Tickets de ayuda»

### MV-039 · 2026-11-20 · Sin textos explicativos en las pantallas

- **Ficha:** `fichas/r1-inicio-procesos.md`
- **Dijo Sebastián:** (observaciones, punto 3) «Quitar siempre esos textos, en todas las pantallas: el subtítulo o
  descripción de la cabecera de cada pantalla. Si una explicación hace falta de verdad, va en la ayuda (el asistente
  "n_n") o en un ícono de información que se abre al pasar el mouse, nunca como texto fijo. No quites las etiquetas de
  los campos ni los mensajes de error o de confirmación».
- **Decisión:** ninguna pantalla, ficha, panel ni formulario lleva texto que explique cómo funciona. Se quedan las
  etiquetas, los mensajes de error y de confirmación y los datos (cantidades, montos, estados).
- **Alcance:** cruza módulos
- **Estado:** vigente
- **Commit:** el de «Observación 3: sin textos explicativos»

### MV-040 · 2026-11-20 · Filtros de Tickets de ayuda y el estado «Sin atender»

- **Ficha:** `fichas/op-asistente.md`
- **Dijo Sebastián:** (observaciones, punto 4; propuesta en el lienzo https://claude.ai/artifact/MuDxcC3mfv47874foW8KFk)
  «En una franja fija arriba de la lista, siempre a la vista: el estado como pestañas con contador, Pendientes (se abre
  al entrar), Sin atender, En curso, Resueltos, Todos. El estado "abierto" pasa a llamarse "Sin atender" en todo el
  sistema. Buscador. Filtros en botones pequeños: proceso, quién lo abrió, fecha, esperando más de… Se recuerdan los
  filtros de cada persona».
- **Decisión:** lo dicho; los pendientes se ordenan del más antiguo al más nuevo y los resueltos al revés. Prioridad y
  quién lo atiende quedan fuera por ahora.
- **Alcance:** ficha
- **Estado:** vigente
- **Commit:** el de «Observación 4: filtros de Tickets de ayuda»

### MV-041 · 2026-11-20 · Las filas se mueven al filtrar u ordenar

- **Ficha:** `fichas/r1-inicio-procesos.md`
- **Dijo Sebastián:** (observaciones, punto 5) «Cada vez que se presiona un filtro (una pestaña, un filtro en botón, el
  buscador, el orden), la animación es la propia de una tabla: las filas se mueven. Las que siguen se deslizan, las que
  salen se desvanecen y se recogen hacia arriba, las que entran aparecen desde un poco más abajo, una tras otra (unos
  40 ms). Unos 300 ms. Vale para todas las tablas y listas».
- **Decisión:** lo dicho, en toda tabla y lista que se filtra u ordena, y en los tableros de tarjetas; sin animación con
  «reducir movimiento» o «reducir animaciones».
- **Alcance:** cruza módulos
- **Estado:** vigente
- **Commit:** el de «Observación 5: las filas se mueven al filtrar»

### MV-042 · 2026-11-20 · Un solo azul de resaltado; la franja dice dónde estás

- **Ficha:** `fichas/r1-inicio-procesos.md`
- **Dijo Sebastián:** (observaciones, punto 6) «El azul con el que hoy se resalta lo elegido me gusta y se queda, y es el
  mismo para todo el sistema. Lo usan: la opción elegida en las barras laterales internas, los enlaces de acción, el
  foco de los campos y del buscador, las pestañas y los filtros activos. La excepción es la barra lateral principal: esa
  sigue con el color de cada franja. El color de la franja dice dónde estás; el azul dice qué elegiste». Y, entre las
  opciones que le dio el agente: la caja elegida de la barra de un proceso sigue con el color de su franja.
- **Decisión:** el resaltado de lo elegido es siempre el color principal (azul noche en claro, `#6F9BFF` en oscuro).
  Con el color de su franja quedan la barra lateral principal, las cajas (también la elegida de cada proceso), la frase
  de cada proceso y los íconos de las cajas.
- **Alcance:** cruza módulos
- **Estado:** vigente
- **Commit:** el de «Observación 6: un solo azul de resaltado»

### MV-043 · 2026-11-20 · La carga se ve como energía que recorre la franja bajo la barra de arriba

- **Ficha:** `fichas/r1-inicio-procesos.md`
- **Dijo Sebastián:** «para dar la sensación de carga utiliza la franja de abajo del ícono de Techquk» (eligió la
  franja fina bajo la barra superior, entre las opciones que le dio el agente) y «haz una sensación de que comienza a
  pasar una carga de energía por esa franja».
- **Decisión:** al cambiar de pantalla, la franja bajo la barra de arriba se carga de izquierda a derecha con un brillo
  azul y chispas que la recorren; al llegar la pantalla, se completa, destella y se apaga. Reemplaza la barra de
  progreso de Livewire. Precisa MV-037.
- **Alcance:** ficha
- **Estado:** vigente
- **Commit:** el de «La carga como energía en la franja de arriba»

### MV-044 · 2026-11-20 · La barra de cajas del proceso queda quieta; solo se desliza la marca de la elegida

- **Ficha:** `fichas/r1-inicio-procesos.md`
- **Dijo Sebastián:** (`docs/vibe/pedidos/observaciones-2.md`, punto 1) «Que la barra de cajas se quede quieta y lo
  único que cambie sea: la marca de la elegida se desliza de la caja anterior a la nueva, en unos 250 ms; el contenido
  de abajo se reemplaza sin recargar, con un cambio suave y corto. Al usar "atrás", la marca se desliza igual». Y la
  aclaración: «la caja elegida de la barra de cada proceso se marca con el color de su franja (no con el azul), y tiene
  que distinguirse bien de las demás aunque sean del mismo color».
- **Decisión:** lo dicho, en las barras de los once procesos; la marca va en el color de la franja, más marcada (fondo,
  borde fino y línea de abajo) y la caja elegida con su nombre en negrita.
- **Alcance:** cruza módulos
- **Estado:** vigente
- **Commit:** el de «Observación 2.1: la barra de cajas quieta, la marca se desliza»

### MV-045 · 2026-11-20 · En las barras laterales internas, la marca de la opción elegida se desliza en vertical

- **Ficha:** `fichas/r1-inicio-procesos.md`
- **Dijo Sebastián:** (observaciones-2, punto 2) «El mismo comportamiento en las barras laterales internas de cada
  módulo (Listas, Configuración…): la barra se queda quieta, conserva su desplazamiento; la marca de la opción elegida se
  desliza en vertical de la anterior a la nueva, en el azul de resaltado, en unos 250 ms; solo cambia el contenido de la
  derecha, sin recargar».
- **Decisión:** lo dicho, en todas las barras laterales internas; con «reducir movimiento», la marca salta.
- **Alcance:** cruza módulos
- **Estado:** vigente
- **Commit:** el de «Observación 2.2: la marca de las barras laterales internas se desliza»

### MV-046 · 2026-11-20 · Sin ruta ni título grande; el nombre del módulo bajo la frase, con la letra del logo

- **Ficha:** `fichas/r1-inicio-procesos.md`
- **Dijo Sebastián:** (observaciones-2, punto 3) «Quitar la ruta en todas las pantallas. Debajo de la frase del
  proceso, el nombre del módulo (por ejemplo, DIRECTORIO Y CATÁLOGOS), centrado, con la letra del logo de TECHQUK:
  Audiowide, en mayúsculas. Quitar también el título grande de la pantalla interna. Escritorio 22 px, interlineado 1,2,
  espaciado 0,06 em; celular 18 px; una sola línea; unos 6 px debajo de la frase y unos 28 px antes del contenido».
- **Decisión:** lo dicho; el orden queda barra de cajas, frase, nombre del módulo y contenido. En el color de texto de
  su franja (4,5:1 o más).
- **Alcance:** cruza módulos
- **Estado:** vigente
- **Commit:** el de «Observación 2.3: sin ruta; el nombre del módulo con la letra del logo»

### MV-047 · 2026-11-20 · Sin el haz de luz al cambiar dentro de un mismo módulo

- **Ficha:** `fichas/r1-inicio-procesos.md`
- **Dijo Sebastián:** (`docs/vibe/pedidos/observaciones-3.md`, punto 1) «Que en esos cambios no aparezca el haz de luz:
  la marca de la opción se desliza y el contenido de la derecha cambia, nada más. El haz sigue para lo demás: cambiar
  de módulo, abrir una caja desde el inicio, ir desde una tarjeta de Mien. Si un cambio interno tarda más de un segundo,
  una señal discreta dentro del área del contenido». Y, a la pregunta de si tampoco al cambiar entre las cajas de la
  barra de un proceso: «No, tampoco ahí».
- **Decisión:** los cambios dentro de un módulo (cajas de la barra de un proceso y opciones de una barra lateral
  interna) no encienden la franja; si tardan más de un segundo, el contenido se atenúa y una línea fina recorre su borde.
  Precisa MV-043.
- **Alcance:** cruza módulos
- **Estado:** vigente
- **Commit:** el de «Observación 3.1: sin el haz dentro de un módulo»

### MV-048 · 2026-11-20 · Sin título grande también en las pantallas con barra lateral interna

- **Ficha:** `fichas/r1-inicio-procesos.md`
- **Dijo Sebastián:** (observaciones-3, punto 2) «Quitarlo ahí y en todas las pantallas con barra lateral interna
  (Configuración, Listas y las demás): debajo del nombre del módulo van directo la barra lateral y el contenido. Revisa
  todas las pantallas internas de los 11 procesos».
- **Decisión:** completa MV-046: las opciones de una barra lateral interna tampoco llevan título grande.
- **Alcance:** cruza módulos
- **Estado:** vigente
- **Commit:** el de «Observación 3.2: sin título en las pantallas con barra lateral interna»

### MV-049 · 2026-11-20 · Nadie elige qué módulos ver: cada persona ve lo que su rol permite

- **Ficha:** `fichas/r1-inicio-procesos.md`
- **Dijo Sebastián:** (`docs/vibe/pedidos/observaciones-4.md`, punto 1) «Que no exista ninguna opción para que la
  persona elija qué módulos o cajas ver. Cada persona ve siempre y solo lo que su rol le permite. Se queda el cambio de
  orden de las cajas dentro de la barra de cada proceso. Lo que ve cada rol lo define quien administra, en Sistema».
- **Decisión:** se quitan «Elegir módulos» (menú del avatar y al prender la barra) y la preferencia «Módulos de la
  barra», y se borra lo que habían guardado. Reemplaza lo que el repo original decidió en el plan 1.4f (D4) y 1.4g
  sobre ocultar módulos.
- **Alcance:** cruza módulos
- **Estado:** vigente
- **Commit:** el de «Observación 4.1: sin elegir qué módulos ver»

### MV-050 · 2026-11-20 · La barra lateral solo se expande o se comprime

- **Ficha:** `fichas/r1-inicio-procesos.md`
- **Dijo Sebastián:** (observaciones-4, punto 2) «Que no exista la opción de cambiar el ancho de la barra lateral. Solo
  dos estados: expandida, con los nombres, a un ancho fijo; comprimida, solo los íconos. Se cambia con un botón, con
  una transición corta, y el sistema recuerda el último estado de cada persona».
- **Decisión:** lo dicho; reemplaza el borde arrastrable del repo original (plan 1.3d, D6).
- **Alcance:** cruza módulos
- **Estado:** vigente
- **Commit:** el de «Observación 4.2: la barra lateral solo se expande o se comprime»

### MV-051 · 2026-11-20 · Las barras laterales siempre entran, sin barra de desplazamiento

- **Ficha:** `fichas/r1-inicio-procesos.md`
- **Dijo Sebastián:** (observaciones-4, punto 3) «Que entren siempre, ajustándose al alto: cada elemento y el espacio
  entre ellos con un máximo y un mínimo; primero se quita el aire, después se achica el ícono, nunca por debajo de unos
  28 px. Respaldo para el caso extremo: se puede desplazar sin que se dibuje la barra de desplazamiento, con un degradado
  suave que avisa que hay más. Vale para la principal y todas las internas. Con teclado, el elemento enfocado siempre se
  trae a la vista».
- **Decisión:** lo dicho.
- **Alcance:** cruza módulos
- **Estado:** vigente
- **Commit:** el de «Observación 4.3: las barras laterales siempre entran»

### MV-052 · 2026-11-20 · La estrella de Mien en la barra lateral abre un globo con Mien resumido

- **Ficha:** `fichas/r1-inicio-procesos.md`
- **Dijo Sebastián:** (observaciones-4, punto 4) «Al presionar la estrella de la barra lateral principal, no se va a la
  página de Mien: se abre un globo pegado a la estrella con Mien resumido. No se puede olvidar ningún pendiente. Clic
  afuera, Esc u otro clic en la estrella lo cierran. Ctrl + clic abre Mien completo en una pestaña nueva. Desde el globo
  solo se mira y se salta: cada pendiente es un enlace al lugar donde se resuelve; no se aprueba ni se rechaza desde
  ahí».
- **Decisión:** lo dicho.
- **Alcance:** ficha
- **Estado:** vigente
- **Commit:** el de «Observación 4.4: el globo de Mien en la barra lateral»

### MV-053 · 2026-11-20 · La caja Techquk lleva el logo de Techquk con su nombre

- **Ficha:** `fichas/r1-inicio-procesos.md`
- **Dijo Sebastián:** (observaciones-4, punto 5) «La caja Techquk usa como ícono el logo completo de Techquk: el símbolo
  y su nombre TECHQUK. En el color de la franja Estratégicos, en un solo color. El nombre en Audiowide, el símbolo
  achatado al 85 %. Sale del mismo lugar que el logo del encabezado. Dentro de la caja, el logo con su nombre reemplaza
  al texto "Techquk". En la barra lateral: expandida, el símbolo y TECHQUK; comprimida, solo el símbolo».
- **Decisión:** lo dicho.
- **Alcance:** ficha
- **Estado:** vigente
- **Commit:** el de «Observación 4.5: el logo de Techquk en su caja»

### MV-054 · 2026-11-20 · Ícono propio de «Gerencia de proyectos»: reloj, moneda y barras

- **Ficha:** `fichas/r1-inicio-procesos.md`
- **Dijo Sebastián:** (observaciones-4, punto 6) «Un reloj, una moneda con el signo de dólar y unas barras que suben: el
  plazo, el costo y el avance. Un solo color; la moneda puede ir rellena con el "$" calado».
- **Decisión:** ícono de línea, 24 × 24, trazo 1,9, un solo color: el reloj abierto abajo con dos agujas, la moneda
  rellena con el «$» calado abajo a la izquierda y tres barras que suben abajo a la derecha. Referencia en
  `docs/vibe/referencias/icono-gerencia-de-proyectos.png`.
- **Alcance:** ficha
- **Estado:** vigente
- **Commit:** el de «Observación 4.6: ícono de Gerencia de proyectos»

### MV-055 · 2026-11-21 · El ícono de la agenda, estilo aplicación: la hoja del día

- **Ficha:** `fichas/t1-agenda.md`
- **Dijo Sebastián:** (`docs/vibe/pedidos/observaciones-5.md`, punto 1; opción A del lienzo
  https://claude.ai/artifact/HjBp8UWVJRA7wrserWntJL) «Un ícono estilo aplicación, a todo color: la hoja del día. Banda
  turquesa con el día de la semana abreviado, el número del día grande y un relojito dorado. Cambia solo cada día, en la
  hora de Lima, en español. En modo claro, un borde fino gris cálido. 32 px en el muelle. No toma el color de ninguna
  franja ni del tema».
- **Decisión:** lo dicho.
- **Alcance:** ficha
- **Estado:** vigente
- **Commit:** el de «Observación 5.1: la hoja del día de la agenda»

### MV-056 · 2026-11-22 · El logo de la caja Techquk, en el azul de Estratégicos

- **Ficha:** `fichas/r1-inicio-procesos.md`
- **Dijo Sebastián:** (observaciones-5, punto 2) «Que vaya en el azul de la franja Estratégicos: `#6F9BFF` en modo
  oscuro y azul noche en modo claro. El símbolo y el nombre, los dos en ese azul. Solo en la caja y en la barra lateral
  principal. Al pasar el mouse o elegida, más intenso, sin volverse blanco ni gris».
- **Decisión:** lo dicho; precisa MV-053.
- **Alcance:** ficha
- **Estado:** vigente
- **Commit:** el de «Observación 5.2: el logo de Techquk en azul»

### MV-057 · 2026-11-22 · Oportunidades: «Mis oportunidades» por defecto, «Embudo de oportunidades» y sin Lista ni Faja 3D

- **Ficha:** `fichas/r1-oportunidades-vistas.md`
- **Dijo Sebastián:** (observaciones-5, punto 3) «La vista Tarjetas pasa a llamarse "Mis oportunidades" y es siempre la
  que se abre por defecto. La vista Tablero pasa a llamarse "Embudo de oportunidades". Se eliminan las vistas "Lista" y
  "Faja 3D": sus pestañas, pantallas, rutas y el código que solo usaban ellas. Los enlaces que llevaban a esas vistas
  pasan a "Mis oportunidades". Quedan tres vistas: Mis oportunidades · Embudo de oportunidades · Línea de tiempo».
- **Decisión:** lo dicho. Reemplaza la D1 y la D11 de los planes 2.21c y 2.21d del repo original (Tablero de entrada;
  Lista y Faja 3D como vistas extra).
- **Alcance:** ficha
- **Estado:** vigente
- **Commit:** el de «Observación 5.3: Mis oportunidades y el Embudo»

### MV-058 · 2026-11-22 · El Embudo de oportunidades: el embudo clásico con sus gemas

- **Ficha:** `fichas/r1-oportunidades-vistas.md`
- **Dijo Sebastián:** (observaciones-5, punto 4; tablero «A refinada · el embudo con sus gemas», copia en
  `docs/vibe/pedidos/lienzos/embudo/Refinada.dc.html` y `Gema.dc.html`) «Cuatro cifras; a la izquierda, el embudo
  clásico con cuatro franjas que se angostan, cada una con su gema, nombre, talla y probabilidad, monto y cantidad, y
  entre franjas "61 % avanza"; debajo, Ganadas y Perdidas del año; a la derecha, el panel de la etapa elegida con su
  lista. Las gemas, con dibujo profesional, cada una más trabajada que la anterior; solo cambia su dibujo y se usan
  también donde ya aparecen».
- **Decisión:** lo dicho; reemplaza la botonera del Tablero (plan 2.21d, D3 a D6) y el dibujo de las gemas del plan 2.21c
  (D3).
- **Alcance:** ficha
- **Estado:** vigente
- **Commit:** el de «Observación 5.4: el Embudo con sus gemas»

### MV-059 · 2026-11-22 · Todos los colores siguen al ambiente

- **Ficha:** `fichas/r1-inicio-procesos.md`
- **Dijo Sebastián:** (observaciones-5, punto 5; columna «Propuesta» y componente «Escena» del lienzo, copia en
  `docs/vibe/pedidos/lienzos/ambiente/`) «Que todos los colores de todos los componentes se adapten al ambiente. Las
  superficies nacen del fondo con `color-mix(in oklch, …)`; los colores de las franjas mantienen su identidad pero se
  mezclan con un 12 a 14 % del tinte del ambiente, también el azul de resaltado; vidrio en lo que flota. No cambian los
  íconos de aplicación del muelle ni el logo del encabezado. Los colores de estado toman un toque leve y siguen
  distinguiéndose. Busca cada color escrito a mano. El contraste tiene que seguir cumpliendo en todos los ambientes».
- **Decisión:** lo dicho.
- **Alcance:** cruza módulos
- **Estado:** vigente
- **Commit:** el de «Observación 5.5: los colores siguen al ambiente»

### MV-060 · 2026-11-22 · La Línea de tiempo: el cronograma refinado, con orden y filtros

- **Ficha:** `fichas/r1-oportunidades-vistas.md`
- **Dijo Sebastián:** (observaciones-5, punto 6; propuesta A «Cronograma refinado», copia en
  `docs/vibe/pedidos/lienzos/linea-de-tiempo/Main.dc.html`) «Una fila por oportunidad con su gema, nombre, cliente y
  monto; tramos redondeados por cada etapa, línea punteada desde hoy hasta un rombo dorado, coral y "venció" si ya
  pasó; línea "Hoy". Orden por defecto "Por urgencia" (vencidas, por vencer, a igual día mayor monto, sin fecha al
  final; sin ganadas ni perdidas) con un separador "3 vencidas"; otros órdenes; filtros en chips (buscador,
  vencimiento, etapa con su gema y Cerradas, responsable con Mías por defecto, rango del eje); las filas se deslizan al
  reordenarse».
- **Decisión:** lo dicho; reemplaza el estilo de la vista del plan 2.21d (D11).
- **Alcance:** ficha
- **Estado:** vigente
- **Commit:** el de «Observación 5.6: la Línea de tiempo con orden y filtros»

### MV-061 · 2026-11-22 · El Embudo muestra sus cifras una sola vez

- **Ficha:** `fichas/r1-oportunidades-vistas.md`
- **Dijo Sebastián:** (`docs/vibe/pedidos/observaciones-6.md`, punto 1) «Aparecen dos filas de cifras. Se queda solo la
  fila de arriba, la de cinco; la de abajo se borra. Revisa que las otras vistas no tengan la misma duplicación».
- **Decisión:** el Embudo usa solo la fila de cinco cifras de la cabecera de las vistas; las otras dos vistas no
  duplicaban.
- **Alcance:** ficha
- **Estado:** vigente
- **Commit:** el de «Observación 6.1: las cifras del Embudo, una vez»

### MV-062 · 2026-11-22 · El muelle se puede mover y se pega al borde

- **Ficha:** `fichas/r1-inicio-procesos.md`
- **Dijo Sebastián:** (observaciones-6, punto 2) «El muelle se puede arrastrar a otro lugar de la pantalla. Se agarra
  desde cualquier parte que no sea un ícono; al soltarlo se pega al borde más cercano; arriba y abajo se pone
  horizontal; nunca tapa la barra lateral ni el encabezado; recuerda dónde lo dejó cada persona en ese navegador; doble
  clic en la zona de agarre lo devuelve a su lugar. El asistente se queda donde aparece hoy; el muelle, por defecto, un
  poco más arriba para que nunca se superpongan».
- **Decisión:** lo dicho.
- **Alcance:** ficha
- **Estado:** vigente
- **Commit:** el de «Observación 6.2: el muelle se mueve»

### MV-063 · 2026-11-22 · El asistente vuelve a funcionar

- **Ficha:** `fichas/op-asistente.md`
- **Dijo Sebastián:** (observaciones-6, punto 3) «Hoy el personaje aparece, pero nada de su comportamiento funciona.
  Revísalo, arréglalo y comprueba cada punto: clic → chat; paseo con intención; globos (deja una forma de forzar una
  frase sin esperar las reglas); clic derecho → menú; y que siga funcionando después de navegar sin recargar. El
  comportamiento esperado es el de su ficha, que no cambia».
- **Decisión:** arreglado (la causa está en la ficha); se puede forzar una frase con `tqAsistente.probarFrase()`.
- **Alcance:** ficha
- **Estado:** vigente
- **Commit:** el de «Observación 6.3: el asistente vuelve a funcionar»

### MV-064 · 2026-11-22 · La carita del asistente: «Gota de miel», que se vuelve ámbar con el trabajo seguido

- **Ficha:** `fichas/op-asistente.md`
- **Dijo Sebastián:** (observaciones-6, punto 4; lienzos `docs/vibe/pedidos/lienzos/asistente/A.dc.html` y `C.dc.html`)
  «Más realista: volumen, luz y sombra, ojos con brillo, cejas y boca, y que cambie sus facciones de forma natural:
  parpadea a ratos, los ojos siguen el puntero, sonríe al saludar, mueve la boca al hablar, levanta las cejas al
  escuchar, entrecierra los ojos al dormir; cada cambio pasa suave. Diseño "A · Gota de miel". Después de 2 horas de
  actividad sin pausa, la miel se va oscureciendo hacia el ámbar de la propuesta C en unos 30 minutos; 15 minutos de
  ausencia la devuelven a miel; el cambio es continuo; con el ámbar completo, a veces invita a una pausa».
- **Decisión:** lo dicho.
- **Alcance:** ficha
- **Estado:** vigente
- **Commit:** el de «Observación 6.4: la Gota de miel»

### MV-065 · 2026-11-22 · Doble clic en la estrella de Mien de la barra lateral abre la página de Mien

- **Ficha:** `fichas/r1-inicio-procesos.md`
- **Dijo Sebastián:** (observaciones-6, punto 5) «Un clic sigue abriendo el globo y Ctrl + clic Mien en otra pestaña.
  Doble clic lleva a la página de Mien, en la misma pestaña y sin recargar. El primer clic del doble clic no debe dejar el
  globo abierto ni hacerlo parpadear».
- **Decisión:** lo dicho; precisa MV-052.
- **Alcance:** ficha
- **Estado:** vigente
- **Commit:** el de «Observación 6.5: doble clic en la estrella de Mien»

### MV-066 · 2026-11-23 · «Leer ahora del OECE» con la API real de contrataciones abiertas, leída por páginas

- **Ficha:** `fichas/t1-licitaciones.md`
- **Dijo Sebastián:** (observaciones-6, punto 6) «La dirección vigente de la API … es
  `https://contratacionesabiertas.oece.gob.pe/api/v1/releasesAfter`. La API no acepta `since`. Se pide lo más reciente
  primero y se siguen las páginas (`links.next`) mientras los releases sigan siendo más nuevos que la fecha "desde" (por
  defecto, 3 días atrás), con un tope de páginas (por ejemplo 20). Al terminar, el aviso dice cuántas leyó, cuántas
  entraron y cuántas quedaron fuera por el filtro».
- **Decisión:** lo dicho. Se precisó al probarla: `releasesAfter` con `order=desc` no ordena por fecha (devuelve releases
  de 2024), así que se lee `/releases` del mismo servicio, que sí entrega lo más nuevo primero (20 por página); si la
  dirección configurada es `releasesAfter`, se cambia sola. Reemplaza el supuesto «dirección no confirmada» de la v2.
- **Alcance:** ficha
- **Estado:** vigente
- **Commit:** el de «Observación 6.6: Leer ahora del OECE con la API real»

### MV-067 · 2026-11-23 · «Personal asignado» en tarjetas, con foto de perfil o avatar por defecto

- **Ficha:** `fichas/t4-personal-tareo.md`
- **Dijo Sebastián:** (observaciones-6, punto 7) «Pasa a tarjetas en dos columnas (una en pantallas angostas), una por
  persona activa: arriba la foto de perfil en círculo, el nombre, "categoría · vínculo" y la habilitación SCTR como
  etiqueta (en coral si está vencida o no tiene); debajo sus 4 semanas como barritas de ocupación … gris si está en 0 %,
  en el color normal hasta 100 %, en coral con "sobreasignado" si pasa del 100 %. Arriba, el buscador por nombre … Un
  clic abre el panel deslizante con el detalle. El colaborador gana sexo (obligatorio) y foto (opcional, recortada
  cuadrada). Sin foto, un avatar por defecto de hombre y de mujer, ilustrados, en tonos neutros que sigan al ambiente.
  Nunca fotos de personas reales. El mismo avatar o foto donde aparezca esa persona».
- **Decisión:** lo dicho.
- **Alcance:** ficha (el sexo y la foto cruzan a Personas)
- **Estado:** vigente
- **Commit:** el de «Observación 6.7: Personal asignado en tarjetas con foto»

### MV-068 · 2026-11-23 · Gerencia de proyectos con Proyectos al centro, y sin «Volver al orden original»

- **Ficha:** `fichas/r1-inicio-procesos.md`
- **Dijo Sebastián:** (observaciones-6, punto 8) «Nuevo orden por defecto de las cajas de Gerencia de proyectos:
  Contratos · Subcontratos · Proyectos · Personal asignado · Tareo. Proyectos queda en el medio y es la que se abre al
  entrar; a la izquierda lo contractual y a la derecha lo de las personas. Cambia el orden original del sistema … borra el
  orden guardado de este proceso en la base de demo. Se quita el botón "Volver al orden original" en todos los procesos
  (y lo que solo él usa). Reordenar arrastrando sigue igual y se sigue guardando por persona».
- **Decisión:** lo dicho; precisa MV-035.
- **Alcance:** cruza módulos
- **Estado:** vigente
- **Commit:** el de «Observación 6.8: Proyectos al centro y sin volver al orden original»

### MV-069 · 2026-11-23 · Tareo: la jornada de cada persona y el tareo que se rellena solo

- **Ficha:** `fichas/t4-personal-tareo.md`
- **Dijo Sebastián:** (observaciones-6, punto 9) «Al registrar o editar un colaborador se define su jornada: qué días
  trabaja y cuántas horas cada día. Viene propuesta según el vínculo (planilla: Lu a Vi 8 h y Sá 4 h = 48 h, la jornada
  máxima legal; eventual: vacía) … Al abrir una semana nueva de un proyecto, el sistema ya trae una línea por cada persona
  asignada a ese proyecto esa semana, con las horas de su jornada multiplicadas por su dedicación, repartidas por igual
  en sus días, en la partida donde tareó la semana anterior … Los feriados van en 0. A quien tiene el SCTR vencido no se
  le rellena: aparece en coral. Las líneas rellenadas se ven más suaves, con la marca "de la jornada", hasta que alguien
  las toca o se cierra la semana. Otros atajos: Copiar la semana anterior, Rellenar con la jornada, Llenar la fila».
- **Decisión:** lo dicho. Quedan por confirmar (en la ficha): la dedicación en el relleno, la partida de la semana
  anterior y las 48 h de planilla (los días que dio suman 44 h).
- **Alcance:** ficha (la jornada es un dato nuevo del colaborador)
- **Estado:** vigente
- **Commit:** el de «Observaciones 6.9 y 6.10: el tareo nuevo»

### MV-070 · 2026-11-23 · Tareo: la vista nueva

- **Ficha:** `fichas/t4-personal-tareo.md`
- **Dijo Sebastián:** (observaciones-6, punto 10; «aprobó todas estas mejoras») «1. Cuadrícula tipo hoja de cálculo …
  se escribe directo en la celda (Tab y Enter avanzan; Esc deshace la celda) y se guarda sola al salir … El formulario
  "Agregar horas" se va. 2. Foto o avatar de cada persona. 3. Tira de semanas del mes … el proyecto como chip. 4. Cuatro
  cifras arriba … 5. Avisos dentro de la cuadrícula: feriados, horas extra, "¿faltó?", SCTR vencido. 6. Ver por persona
  / por partida. 7. La persona en todos sus proyectos ("44 de 48 h"). 8. Cerrar con resumen. 9. Asistencia rápida desde
  el celular: Vino / Faltó, con un selector Semana / Hoy».
- **Decisión:** lo dicho.
- **Alcance:** ficha
- **Estado:** vigente
- **Commit:** el de «Observaciones 6.9 y 6.10: el tareo nuevo»

### MV-071 · 2026-11-23 · Contratos: de tabla a tarjetas que avisan antes

- **Ficha:** `fichas/t2-contratos.md`
- **Dijo Sebastián:** (observaciones-6, punto 11; «aprobada completa») «Tarjetas en dos columnas, una por contrato …
  barra doble de plazo contra avance … hitos en miniatura … penalidad diaria y semáforo de la carta fianza. Orden por
  urgencia (borradores arriba como "Por completar" con "Completar y activar"; vencidos; carta fianza a 30 días; días
  restantes; culminados) y otros órdenes. Cinco cifras arriba … Filtros en chips … Segunda vista, "Calendario de
  vencimientos" … Clic: panel deslizante con el resumen y Aplicar penalidad, Registrar adenda y Registrar garantía;
  doble clic: la ficha completa. Avisos en Mien».
- **Decisión:** lo dicho.
- **Alcance:** ficha (los avisos tocan Mien)
- **Estado:** vigente
- **Commit:** el de «Observación 6.11: Contratos en tarjetas que avisan antes»

### MV-072 · 2026-11-23 · Subcontratos: una tarjeta por subcontrato y la alarma de pagar de más

- **Ficha:** `fichas/t4-valorizaciones.md`
- **Dijo Sebastián:** (observaciones-6, punto 12; «aprobada completa») «Tarjetas en dos columnas, una por subcontrato …
  barra apilada del dinero: pagado · aprobado por pagar · por aprobar · por ejecutar … adelanto por amortizar y retención
  acumulada. Bandeja "Por aprobar" con su cuenta y el botón Aprobar. Alarma "le pagas más avance del que cobras" si el
  subcontratista va más de 10 puntos adelante … Cinco cifras arriba. Liberar retención (operación nueva) con el 100 % y
  su conformidad, registrada como acción con su permiso. Ficha del subcontrato (doble clic; un clic, el panel). Orden
  por urgencia y filtros en chips. Avisos en Mien».
- **Decisión:** lo dicho.
- **Alcance:** ficha (la alarma lee Valorizaciones y los avisos tocan Mien)
- **Estado:** vigente
- **Commit:** el de «Observación 6.12: Subcontratos en tarjetas y la alarma de pagar de más»

### MV-073 · 2026-11-23 · El modo oscuro se activa al elegirlo y «Sistema» sigue al cielo en todo

- **Ficha:** `fichas/t3b-transicion-cielo.md`
- **Dijo Sebastián:** (observaciones-7, punto 7) «se puede pulsar "oscuro", pero la app no cambia a modo oscuro … Con
  "Sistema", la app se ve oscura a las 2:30 p. m. en Chiclayo … Haz que en "Sistema" mande el cielo en todos los
  lugares que deciden la clase dark, también después de navegar sin recargar».
- **Decisión:** lo dicho; confirma MV-012 y MV-013.
- **Alcance:** ficha
- **Estado:** vigente
- **Commit:** el de «Observación 7.7: el modo oscuro se activa y "Sistema" sigue al cielo»

### MV-074 · 2026-11-23 · Oportunidades en una sola lista: «Por calificar» es la primera etapa y hay filtro TIPO

- **Ficha:** `fichas/r1-oportunidades-vistas.md` (toca `t1-campanias.md` y `t1-licitaciones.md`)
- **Dijo Sebastián:** (observaciones-7, punto 1) «Se quitan [las pestañas Por calificar · Oportunidades · Licitaciones]:
  todo va en la lista de oportunidades y se separa con los filtros del costado. "Por calificar" es la primera etapa del
  embudo, antes de Prospecto … Elige una gema para esta etapa, anterior a "en bruto" … En "Por calificar" caen los leads
  (Calificar / Descartar) y las licitaciones por decidir (Participar / No participar) … Filtro nuevo "TIPO": Privada ·
  Licitación pública … Plazos de las licitaciones en la tarjeta … en coral a menos de 5 días. El orden por urgencia las
  considera … Los contadores de las pestañas pasan a ser los números junto a cada filtro … Convocatorias del SEACE sigue
  como caja aparte; Campañas también».
- **Decisión:** lo dicho. Reemplaza la parte de MV-023 que hacía de «Licitaciones» una pestaña y la de MV-024 que hacía
  de «Por calificar» una pestaña; `/leads` y `/licitaciones` llevan a Oportunidades con su filtro puesto.
- **Alcance:** cruza módulos (Oportunidades, Campañas y leads, Licitaciones)
- **Estado:** vigente
- **Commit:** el de «Observación 7.1: Oportunidades en una sola lista»

### MV-075 · 2026-11-23 · Sin espacio vacío bajo el nombre del proceso

- **Ficha:** `fichas/r1-inicio-procesos.md`
- **Dijo Sebastián:** (observaciones-7, punto 2) «Entre "¡Vamos por esas oportunidades!" / "CONSEGUIR EL NEGOCIO" y el
  contenido de la caja queda un espacio vacío grande. El contenido empieza justo debajo del nombre del proceso, con un
  respiro normal (como entre las demás secciones). Revisa que no pase lo mismo en las portadas de los otros procesos».
- **Decisión:** lo dicho, en todas las portadas de proceso.
- **Alcance:** ficha
- **Estado:** vigente
- **Commit:** el de «Observación 7.2: sin espacio vacío bajo el nombre del proceso»

### MV-076 · 2026-11-23 · La postulación se gestiona desde el sistema; el SEACE solo para el envío

- **Ficha:** `fichas/t1-licitaciones.md`
- **Dijo Sebastián:** (observaciones-7, punto 3) «quiero que se haga toda la gestión de postulación y demás desde nuestro
  sistema» … «todo el trabajo en el sistema y en el SEACE solo el envío, hecho por una persona. No se automatiza el
  portal con un robot ni se guardan credenciales del SEACE en el sistema». Diseño elegido: «A · Ruta por pasos», con la
  oferta económica desde el presupuesto.
- **Decisión:** lo dicho: los 8 pasos en la pestaña Licitación (participar, consultas, bases integradas, expediente,
  oferta, paquete, presentar, subsanación y resultado) y sus avisos en Mien.
- **Alcance:** negocio (cómo Techquk postula a licitaciones)
- **Estado:** vigente
- **Commit:** el de «Observación 7.3: la postulación por pasos»

### MV-077 · 2026-11-23 · Convocatorias del SEACE: guardar todo lo liviano, leer solo lo nuevo y reaccionar por eventos

- **Ficha:** `fichas/t1-licitaciones.md`
- **Dijo Sebastián:** (observaciones-7, punto 4) «Pasa a guardarse todo lo que publica el OECE, pero solo sus datos
  livianos … La bandeja muestra solo lo que coincide … Leer solo lo nuevo (lectura incremental) … En segundo plano cada
  hora … Al entrar … si la última lectura tiene más de 15 minutos, lanza otra por detrás y lo nuevo aparece solo …
  "Leer ahora" sigue … Si el OECE no responde, la pantalla sigue con lo guardado … Cada convocatoria nueva o actualizada
  dispara un evento … Filtrar · Priorizar · Avisar en Mien».
- **Decisión:** lo dicho.
- **Alcance:** ficha
- **Estado:** vigente
- **Commit:** el de «Observación 7.4: el SEACE se guarda entero y se lee solo lo nuevo»

### MV-078 · 2026-11-23 · Convocatorias del SEACE: la bandeja con detalle

- **Ficha:** `fichas/t1-licitaciones.md`
- **Dijo Sebastián:** (observaciones-7, punto 5) diseño elegido «B · Bandeja con detalle»: «Reemplaza la tabla de hoy.
  Funciona como un correo: decidir rápido, una por una» — buscador con «Buscar en todo el SEACE», chips de líneas de
  negocio y de «ver», lista compacta con ↑ ↓, detalle con cronograma, ganadores anteriores, responsable y etiquetas, y
  «Registrar como oportunidad»; configuración con «Líneas de negocio … cada una con sus palabras clave y sus palabras
  negativas … y su valor mínimo. Reemplaza el parámetro único de palabras clave», «¿Por qué no entró?», fuentes y el
  umbral de prioritaria.
- **Decisión:** lo dicho.
- **Alcance:** ficha
- **Estado:** vigente
- **Commit:** el de «Observación 7.5: la bandeja del SEACE con detalle»

### MV-079 · 2026-11-23 · Campañas con su retorno, Mis clientes y el Radar del PAC en espera

- **Ficha:** `fichas/t1-campanias.md`
- **Dijo Sebastián:** (observaciones-7, punto 6) «La caja Campañas se divide en secciones con chips arriba: Campañas · Mis
  clientes · Radar del PAC (el calendario del año queda para más adelante)». Campañas: cinco cifras, tarjeta con mini
  embudo, gasto real con el selector de imputación, margen de lo ganado (real o previsto), retorno, formulario y QR,
  WhatsApp, aviso en Mien. Mis clientes: tres grupos, «Ver la lista», «Crear campaña» con seguimiento «día 1 correo,
  día 3 WhatsApp, día 7 llamada, editable … cada paso se vuelve una tarea en Mien». Radar del PAC: «antes de
  construirlo, averigua si los datos abiertos del OECE … entregan los Planes Anuales … Detente y avísame».
- **Decisión:** lo dicho; el Radar del PAC espera la respuesta de Sebastián a la investigación.
- **Alcance:** cruza módulos (Campañas, Oportunidades, Proyectos, Postventa, Finanzas por el gasto, Mien)
- **Estado:** vigente
- **Commit:** el de «Observación 7.6: Campañas con su retorno y Mis clientes»
