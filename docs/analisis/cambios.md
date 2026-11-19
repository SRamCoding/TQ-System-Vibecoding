# Registro de cambios del análisis

Cada versión de cada bloque, de la más reciente a la más antigua. Una línea por versión: qué cambió y
de dónde salió. El significado de los números está en [versionado](versionado.md); el relato
completo, en la [historia](historia.md).

Las versiones anteriores al 15 de octubre de 2026 se asignaron después, mirando qué etapa cubría cada
documento. Llevan la marca **(reconstruida)**.

## CRM

### 0.4.2 · 20 oct 2026 · Validación

- La fuente de convocatorias deja de ser una pregunta abierta: se propone traer convocatorias y bases
  automáticamente desde los datos abiertos del OECE, con las bases adjuntas en la misma convocatoria y el
  histórico de adjudicaciones desde el inicio, dentro del MVP (etapa 2) sin ser condición para cerrarla.
  Licitalab queda como complemento a evaluar. Pendiente nuevo «Fuente de convocatorias» para la Gerencia
  Comercial.
- Origen: [ADR 0018](../decisiones/0018-convocatorias-desde-datos-abiertos-oece.md), propuesta. Verificación
  del desarrollador el 2026-10-20 con el archivo de 2026 del OCDS del OECE y tres descargas de bases desde
  Chiclayo.

### 0.4.1 · 20 oct 2026 · Validación

- Los motivos de pérdida de una oportunidad pasan al catálogo único de motivos (familia
  `perdida_oportunidad`), con la opción «Otro» que obliga a escribir el detalle y un comentario opcional.
  La tabla `motivos_perdida` no se crea. La lista inicial (precio, plazo, requisito, competencia, sin
  presupuesto) queda como punto a validar con el área comercial.
- Origen: plan 1.5, decisión D9b (desarrollador, 2026-10-20).

### 0.4.0 · 15 oct 2026 · Validación

- Empieza la validación con Cristhian. Sigue Miguel, gerente comercial, que da la
  versión final. Registro en [validaciones](validaciones.md).
- El diseño de pantallas del 13 de octubre se guarda en el repositorio como fuente fechada
  (`docs/diseno/fuentes/crm-pantallas-2026-10-13/`) y se hace su [inventario](bloques/crm-pantallas.md).
- Origen: decisión de Sebastián de llevar el CRM primero.

### 0.3.1 · 13 oct 2026 · Especificación (reconstruida)

- Diseño de 63 pantallas y 5 componentes para los siete módulos CRM-01 a CRM-07, el inicio del
  ejecutivo comercial, el tablero de Gerencia Comercial y el traspaso a contrato.
- Origen: lienzo de diseño hecho en otra cuenta de Claude, creado el 13 oct 2026.

### 0.3.0 · 21 set 2026 · Especificación (reconstruida)

- La especificación unificada v4 describe el CRM en su parte 2: módulos CRM-01 a CRM-07, recorridos
  por rol y la frontera con el ERP.
- Origen: `docs/diseno/fuentes/Techquk-Sistema-CRM-ERP-v4.pdf` (21 set 2026); extracto en
  `docs/diseno/v4/parte-2-crm.md`.

### 0.2 · agosto 2026 · Propuesta (reconstruida)

- Documento «El CRM de Techquk».
- Origen: citado por la v4 como reemplazado. El archivo no está en el repositorio.

## ERP

### 0.3.1 · 9 oct 2026 · Especificación (reconstruida)

- Reconciliación con la v4: los documentos de agosto pasan a «versión 3.0» con lo superado marcado,
  se escribe `00-Reconciliacion-con-v4.md`, la v4 se parte en Markdown y el modelo de datos pasa a su
  versión 2 (210 tablas, PostgreSQL 16).
- Origen: commit `493d687` del 9 oct 2026.

### 0.3.0 · 21 set 2026 · Especificación (reconstruida)

- Especificación unificada v4: diez flujos con fichas de documentos, estados, efectos y criterios de
  aceptación con números; el proyecto como eje; árbol de partidas de control; cuatro números por
  partida; obra pública; planilla; IA que sugiere. Llegan también el prototipo 4 y el flujograma de
  gastos.
- Origen: `docs/diseno/fuentes/` (los tres archivos con fecha de la v4).

### 0.2.x · agosto a set 2026 · Propuesta (reconstruida)

- Documentos 01, 05, 06, 08, 09-B, 10, el recorrido central y un modelo de datos de 149 tablas.
  Después hubo un «El ERP de Techquk v3» que la v4 reemplazó; no se conserva.
- Origen: cabeceras de agosto en `docs/diseno/`; mención del v3 en `docs/diseno/v4/parte-0-como-leer.md`.
  El paquete original del 24 ago 2026 está en
  [el archivo de agosto](archivo/2026-08-24-analisis-funcional/README.md) desde el 15 oct 2026.

### 0.1 · agosto 2026 · Diagnóstico (reconstruida)

- Organigrama propuesto de cinco gerencias, levantamiento con Jotform, flujos BPMN y registro de
  brechas.
- Origen: paquete «AnalisisFuncional-TechQuk» del 24 ago 2026, que vivía en el proyecto de Claude.
  Desde el 15 oct 2026 está en [el archivo de agosto](archivo/2026-08-24-analisis-funcional/README.md).

## Común

### 0.3.14 · 26 oct 2026 · Especificación

- M11 Imputación: el mecanismo de «toda línea de costo tiene destino» queda construido. Cada línea guarda a dónde va y
  sus tres montos (comprometido, ejecutado, pagado), con la regla de que lo ejecutado nunca pasa lo comprometido. Hoy
  el destino es un centro de costo; las partidas de los proyectos se enchufan en la etapa 2. Centros de costo en
  Catálogos, con los cinco del v4 por confirmar con Contabilidad. El selector de imputación se dibuja con el primer
  documento con costo.
- Origen: plan 1.9, decisiones D1 a D3 (desarrollador, 2026-10-26).

### 0.3.13 · 26 oct 2026 · Especificación

- M7 Adjuntos construida: cualquier registro puede llevar archivos, guardados en un disco privado del servidor (respaldo
  a S3) y descargados solo con permiso; se revisa el contenido real, no solo la extensión; quitar deja rastro; en un
  documento que ya no se edita solo se agregan. Tipos de adjunto en Catálogos (obligatorios y sensibles). Se estrena
  en la ficha de personas y empresas (pestaña «Archivos»).
- M8 Notificaciones construida: los avisos llegan a la campana y, según «Mis preferencias» (por grupo; los obligatorios
  siempre), por correo, una sola vez. WhatsApp, después.
- Origen: plan 1.8, decisiones D1 a D9 (desarrollador, 2026-10-26).

### 0.3.12 · 26 oct 2026 · Especificación

- M2 Aprobaciones construida: las reglas de la matriz del v4 son datos de Configuración → Aprobaciones (documento, paso,
  rango de montos en soles y jefaturas que firman, en orden). Firma quien ocupa la jefatura; si está vacante, sube.
  Las reglas que calzan se acumulan. Observar exige motivo de catálogo y qué corregir, y devuelve el documento a
  editable. Las firmas valen para la versión que se pidió. Aprobar lo propio solo con permiso, bajo el tope de la regla
  y con motivo, y queda para revisión.
- Origen: plan 1.7, decisiones D1 a D9 (desarrollador, 2026-10-26).

### 0.3.11 · 26 oct 2026 · Especificación

- M6 Numeración construida: cada documento recibe su número al crearse (`REQ-2026-0001`), sin huecos ni duplicados
  aunque varias personas guarden a la vez; reinicia cada año (o cada mes, o nunca, según el documento) y puede llevar
  un contador por proyecto o contrato, listo para la etapa 2. Las series las declara cada módulo y se ven, con su
  último número, en Configuración → Numeración.
- Origen: plan 1.6, decisiones D1 a D5 (desarrollador, 2026-10-26).

### 0.3.10 · 26 oct 2026 · Especificación

- El dibujo de la fecha se arma por piezas (fondo, motivo y acompañante, con reglas que descartan lo absurdo) y cambia
  cada día, igual para todos: unas 2100 combinaciones entre las cuatro estaciones y otras 260 entre las fechas
  especiales. Toma la luz de la hora (mañana, tarde, noche) y, si se activa, el clima del lugar de cada computadora
  (sin guardarlo). Las fechas religiosas, en silueta y sin rostros; Semana Santa, sobria.
- Origen: plan 1.4e2, decisiones D1 a D5 (desarrollador, 2026-10-23 y 2026-10-26).

### 0.3.9 · 23 oct 2026 · Especificación

- Saludo natural según el horario de la empresa (editable, de 8:00 a 19:00 de lunes a viernes): en horario, variado y
  nuevo en cada sesión, mitad casual y mitad de la hora, con la frase una por día; fuera de horario (temprano, de
  noche, fines de semana y feriados), un saludo especial natural y, la mayoría de las veces, una frase tranquila.
- Los dibujos por piezas con la luz de la hora quedan para la 1.4e2.
- Origen: plan 1.4e, decisiones D1 a D5 (desarrollador, 2026-10-23).

### 0.3.8 · 23 oct 2026 · Especificación

- Personas y empresas abre con una portada: pestañas por tipo con su cantidad, avisos de fichas incompletas (con
  reglas de buenas prácticas) y de posibles duplicados que filtran la tabla, y los últimos cambios.
- En la ficha, la pestaña de los tipos se llama «Tipos»; el CRM y las compras podrán sumar sus pestañas.
- Nuevo pendiente: cómo unir fichas duplicadas.
- Origen: plan 1.4m, decisiones D1 a D5 (desarrollador, 2026-10-23).

### 0.3.7 · 23 oct 2026 · Especificación

- Modo desarrollo, solo en el equipo del desarrollador: simular la fecha de lo decorativo y cambiar con un clic el
  dibujo, el logo y el gran momento entre las variantes que existen. En producción no hay forma de activarlo.
- La «Vista previa de fechas» deja Configuración y vive dentro del modo desarrollo.
- En el menú de la foto, «Barra de módulos» con una palanca que dice si está prendida.
- Catálogos (1.4j): como cajas agrupadas por tema con un buscador, y cada tabla en un panel en su lugar.
- Origen: plan 1.4i, decisiones D1 a D7, y pedidos del desarrollador (2026-10-23).

### 0.3.6 · 23 oct 2026 · Especificación

- «Mien», el tablero personal: área «Personal» en dorado, antes de Núcleo, con una sola caja. Cada módulo aportará sus
  tarjetas con lo relevante del negocio para cada persona (pendientes, aprobaciones, alertas), visibles con su permiso.
  Por ahora, sin tarjetas.
- Se descartan los módulos favoritos: «Mien» no muestra atajos de la aplicación.
- En la barra de módulos, el módulo abierto se pinta con el color de su área.
- Origen: plan 1.4l, decisiones D1 a D4, y pedido del desarrollador (2026-10-23).

### 0.3.5 · 23 oct 2026 · Especificación

- «Mis datos»: cada persona ve los datos de su cuenta y cambia su cumpleaños, su teléfono y su contraseña. El
  cumpleaños sale del menú de la foto.
- Contraseñas: una sola regla para todo el sistema (8 caracteres con mayúscula, minúscula, número y carácter
  especial); el cambio voluntario pide un código que llega al correo; quien entra con una contraseña que no cumple debe
  renovarla antes de seguir.
- Nuevo pendiente de operación: el servicio de correo saliente.
- Origen: plan 1.4h, decisiones D1 a D5 (desarrollador, 2026-10-23).

### 0.3.4 · 22 oct 2026 · Especificación

- Preferencias por usuario: cada módulo declara las suyas en un registro del núcleo y cada persona las ajusta en «Mis
  preferencias». Solo cambian la presentación; nunca permisos, datos ni reglas, ni guardan datos personales o estado
  de trabajo (`docs/convenciones/interfaz.md` y `codigo.md`, «Preferencias»).
- Nuevas: tema guardado en la base, tamaño de letra, densidad de las tablas, reducir animaciones y pantalla al entrar.
  Para después, cada una con su pantalla: proyecto u obra y almacén por defecto (prellenan, la persona confirma),
  columnas, filtros y filas de las tablas, avisos, y favoritos (con «Mien»).
- El cumpleaños no es preferencia: pasa a «Mis datos» (1.4h).
- Origen: plan 1.4g, decisiones D1 a D7 (desarrollador, 2026-10-22).

### 0.3.3 · 21 oct 2026 · Especificación

- Un solo modo de navegación: se retira el modo paneles. El inicio muestra solo los módulos permitidos de cada
  persona; en escritorio, cada una puede prender una barra lateral con esos módulos, menos los que oculte, y en el
  celular no hay barra (`docs/convenciones/interfaz.md`, «Inicio», «Barra de módulos» y «Pantalla angosta»).
- El tablero de inicio por rol que el v4 (parte 5) ponía en el modo paneles queda como pendiente del negocio, con la
  idea del tablero personal «Mien» (`docs/decisiones/pendientes.md`).
- Origen: plan 1.4f, decisiones D1 a D12 (desarrollador, 2026-10-20 y 2026-10-21).

### 0.3.2 · 20 oct 2026 · Especificación

- Un solo catálogo de motivos (`tipos_motivo`, `motivos`) para todo el sistema: una lista por cada
  transición que exige motivo, siempre con «Otro» y comentario opcional. Se construye en la tarea 1.5
  (antes, en la 1.9) y reemplaza a `motivos_perdida` del CRM.
- Regla general: lo que se analiza se elige de un catálogo o de una lista fija, no se escribe libre
  (`docs/convenciones/base-de-datos.md`). Diecinueve campos del modelo quedan marcados para pasar a catálogo.
- Anular revierte todos los efectos vivos del documento, en orden inverso: interpretación de la v4, que dice
  «los efectos de la última transición» (`docs/convenciones/codigo.md`).
- Origen: plan 1.5, decisiones D4, D9 y D9b (desarrollador, 2026-10-20).

### 0.3.1 · 9 oct 2026 · Especificación (reconstruida)

- La sección 17 del documento 06 detalla imputación, IA, alertas, aprobaciones y permisos según la
  v4. Las convenciones y las decisiones 0001 a 0010 fijan cómo se construye.
- Origen: commit `493d687` del 9 oct 2026.

### 0.3.0 · 21 set 2026 · Especificación (reconstruida)

- La v4 define los mecanismos comunes en sus partes 1, 5 y 7: personas y roles, permisos con
  restricción por campo, estados, numeración, aprobaciones configurables, auditoría, alertas e IA.
- Origen: `docs/diseno/v4/parte-1`, `parte-5` y `parte-7`.
