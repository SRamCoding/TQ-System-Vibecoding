# Ficha · Licitaciones (CRM-04)

- **Estado:** en curso (v8, ronda de cambios; la v2 quedó aprobada)
- **Versión:** v8
- **Tramo de la ruta:** T1
- **Aprobada (v2):** 2026-11-20 · etiqueta `aprobado/t1-licitaciones-v2`
- **Aprobada (v1):** 2026-11-19 · etiqueta `aprobado/t1-licitaciones-v1`
- **Fuentes del diseño (v5):** `docs/vibe/pedidos/observaciones-7.md` (punto 3) y su lienzo `lienzos/postulacion/`
  (`Main.dc.html`, «A · Ruta por pasos»; `Oferta.dc.html`).
- **Fuentes del diseño:** `docs/diseno/v4/parte-2-crm.md` (pasos 4 y 5 del recorrido comercial);
  `docs/analisis/bloques/crm-pantallas.md` (CRM-04, 15 pantallas); DBML `convocatorias`, `convocatoria_cronograma`,
  `participaciones`, `participacion_documentos`, `participacion_resultado`.

## Qué hace

Lleva cada convocatoria pública o privada desde que se registra hasta su resultado. Se carga a mano (la lectura del
SEACE/OECE queda fuera). Se decide participar o no; al participar nace su oportunidad y la IA propone, desde las
bases, la lista de documentos del expediente, que funciona como lista de verificación: no se presenta con faltantes.
Se anotan consultas y absoluciones, se registra la propuesta y, gane quien gane, el resultado con todos los
participantes, su oferta, su puntaje y su puesto. Ese resultado alimenta el histórico de la entidad («esta entidad
adjudica entre el 76.6 % y el 94.5 % del referencial»). Si se pierde o queda desierta, la oportunidad se cierra como
perdida con el ganador y su precio.

## Decisiones de Sebastián

- **MV-089** (ficha) · Región y entidad: por línea en la configuración (regiones y entidades excluidas) y como filtros rápidos en la bandeja; «¿Por qué no entró?» las explica.

- **MV-078** (ficha) · Convocatorias del SEACE: «B · Bandeja con detalle» (lista compacta y detalle, chips de líneas de
  negocio y de «ver»), configuración con líneas de negocio, palabras negativas y «¿Por qué no entró?».
- **MV-077** (ficha) · Convocatorias del SEACE: se guarda todo lo liviano del OECE y la bandeja muestra lo filtrado; se
  lee solo lo nuevo, cada hora y al entrar (si pasaron 15 min), con «Leer ahora»; cada llegada o cambio dispara
  reacciones separadas (filtrar, priorizar, avisar en Mien).
- **MV-076** (negocio) · La postulación se gestiona entera desde el sistema; el SEACE solo para el envío final, hecho por
  una persona. Sin robots en el portal ni claves del SEACE guardadas. Diseño «A · Ruta por pasos» en la pestaña
  Licitación de la oportunidad, con la oferta desde el presupuesto.
- **MV-074** (cruza módulos) · Las licitaciones por decidir son tarjetas «Por calificar» en Oportunidades (Participar /
  No participar) y el filtro TIPO «Licitación pública» reemplaza la pestaña de MV-023; `/licitaciones` lleva ahí.
- **MV-066** (ficha) · «Leer ahora del OECE» lee la API real de contrataciones abiertas por páginas.
- **MV-023** (cruza módulos) · La licitación es una sección de la oportunidad; su lista, una pestaña de Oportunidades.
- **MV-005** (ficha) · Licitaciones va después del puente al ERP.
- **MV-009** (general) · Aprobada sin revisión, como todas hasta terminar el sistema; los cambios vienen al final.

## Pantallas y navegación

- **v8 · Región y entidad** (MV-089): en la bandeja, junto al orden, **Región ▾** y **Entidad ▾** con buscador, «Todas» y la cantidad de cada opción (se combinan con los chips; quedan en la dirección `?region=` y `?entidad=`). En la configuración, cada línea suma **REGIONES** (chips de los 25 departamentos y «Todas») y **ENTIDADES EXCLUIDAS** (buscador sobre lo guardado del OECE desde 3 letras; × para volver a incluir). «¿Por qué no entró?» dice «… pero es de Cusco y esa línea solo busca en …» con «Agregar Cusco a la línea», o «… la entidad está entre las excluidas …» con «Quitar la entidad de las excluidas»; al guardar, todo se vuelve a filtrar al instante.

- **Inicio:** caja «Licitaciones» en el área CRM.
- **Lista** `/licitaciones`: código (y nomenclatura debajo), objeto (y entidad debajo), procedimiento, valor
  referencial, próximo hito con su fecha, estado (etiqueta de color) y responsable. Filtros por estado y procedimiento.
  Botón **«Registrar convocatoria»** (panel deslizante).
- **Ficha** `/licitaciones/{id}`: título «LIC-2026-0007 · entidad»; subtítulo con el estado, el próximo hito y en
  cuántos días, cuántos documentos faltan y el responsable («En preparación · Presentación de ofertas en 2 días ·
  faltan 2 documentos del expediente · responsable: …», como en el v4). Pestañas:
  - **Datos generales:** la convocatoria (con enlace «Abrir la oportunidad») y la sección **Histórico de la entidad**
    con su rango de % del referencial y la lista de sus otras convocatorias con resultado (código, objeto, adjudicado,
    % del referencial y quién ganó).
  - **Cronograma:** etapas con fecha y si se cumplió (en rojo las vencidas sin cumplir).
  - **Expediente:** lista de verificación; arriba dice cuántos documentos sugirió la IA y faltan confirmar.
  - **Consultas:** consulta u observación y su absolución.
  - **Resultado:** participantes con oferta, % del referencial, puntaje y puesto («buena pro» en verde).
- **Botones de la ficha** según el estado: Participar · No participar (por decidir); Aceptar lista sugerida ·
  Registrar propuesta (en preparación); Registrar resultado (presentada); Ir a la oportunidad (siempre que haya).

- **Pestaña «Licitación» de la oportunidad (v5, MV-076)** `/oportunidades/{id}?pestana=licitacion::tab`:
  - **Cabecera:** etiquetas (licitación pública o privada, etapa de la oportunidad, estado de la licitación) y la
    nomenclatura; objeto; entidad y valor referencial; **cuenta regresiva** a la presentación («3 días · jue 26 nov ·
    23:59», coral a menos de 5 días; no se muestra ya presentada) y barra **«Listo para presentar N %»** con «4 de 7
    documentos · oferta lista». Si está por decidir, una franja con Participar y No participar.
  - **Izquierda, los 8 pasos:** Participar en el SEACE · Consultas y observaciones · Bases integradas · Expediente ·
    Oferta económica · Paquete para el SEACE · Presentar en el SEACE · Resultado. Check turquesa si está hecho, número
    con aro azul y fondo azul claro si es el actual, gris si falta, y su línea de resumen («Registrado el 12 nov», «2
    enviadas · absueltas el 20 nov», «4 de 7 documentos listos», «S/ 1,198,000.00 · margen 23.5 %», «Hasta el 26 nov
    23:59», «Buena pro el 03 dic», «Subsanar hasta el 25 nov»). Clic en un paso muestra su contenido; al entrar, el
    actual. Al pie, «Cronograma completo y participantes →» a la ficha de la licitación.
  - **1 Participar:** el recordatorio de la fecha límite, **Abrir el SEACE** (el buscador público; la persona entra con
    su certificado, usuario y clave) y **Ya me registré** (fecha y constancia opcional).
  - **2 Consultas y observaciones:** cada una con su tipo, estado (borrador, enviada, absuelta), «sugerida por IA», el
    numeral de las bases, el texto y la absolución. **Sugerir con IA** (deja borradores), **+ Consulta u observación**,
    **Copiar para el SEACE** (al portapapeles: tipo, numeral y texto), **Enviada** (fecha y constancia) y **Registrar
    absolución**.
  - **3 Bases integradas:** **Abrir el SEACE** y **Ya las descargué** (fecha y dirección).
  - **4 Expediente:** documentos en el orden de las bases con su responsable (iniciales) y su estado (Firmado, Listo, En
    revisión, Falta, No aplica; «Falta · vence pedirla hoy» o «pedirla en 2 días» si tiene fecha); «necesita firma».
    Clic en un documento abre su panel; **+ Documento**; al pie lo que falta y **Ver el paquete** y **Armar paquete
    para el SEACE** (activo solo sin faltantes).
  - **5 Oferta económica:** las partidas del presupuesto con cantidad, unidad, costo unitario y costo, más «Gastos
    generales y garantías»; el **precio ofertado (con IGV)** editable con el **margen al instante** y el % sobre el
    valor referencial; **Guardar oferta**. Debajo, **«¿Cuánto bajar?»**: el descuento promedio de los ganadores en esa
    entidad y el margen a 98 % del VR, a tu oferta, a 90 % y a 85 %, en verde, ámbar o coral. «La IA sugiere; el precio
    lo decide una persona».
  - **6 Paquete:** el índice que tendrá (con «firma» y lo que falta), **Armar paquete** y **Descargar el paquete**.
  - **7 Presentar:** **Abrir el SEACE** y **Registrar presentación** (fecha y hora, constancia obligatoria): pasa a
    «presentada».
  - **8 Subsanación y resultado:** **La entidad pide subsanar** (qué pide y plazo), cada pedido con su plazo en coral y
    **Responder** (texto, fecha, constancia); los participantes y **Registrar resultado** como siempre.
- **Convocatorias del SEACE (v7, MV-078)** `/convocatorias`, como el lienzo «B · Bandeja con detalle»:
  - **Arriba:** buscador por objeto, entidad o nomenclatura con el interruptor **«Buscar en todo el SEACE»** (busca en todo
    lo guardado; lo que no está en la bandeja se ve más suave y dice «fuera de tu bandeja»); «Leído hace N minutos»;
    **Leer ahora**; **Búsqueda**. Debajo, chips con su cantidad: líneas de negocio (Todas, Videovigilancia, Redes,
    Software…) y «ver» (Nuevas, Prioritarias, Mías, Por vencer, Registradas, Descartadas; otro clic lo quita). Sin chip
    «ver», las que esperan decisión (nuevas con la presentación por delante).
  - **Izquierda, la lista** (430 px): punto turquesa y negrita si es nueva para esa persona (sin abrir), estrella dorada
    si es prioritaria, entidad, días a la presentación (coral a menos de 5), objeto en dos líneas con lo que coincidió
    resaltado, valor referencial, línea y el responsable (iniciales). Orden por urgencia y selector (mayor valor, más
    recientes, por entidad). La elegida en azul de selección; **↑ ↓** pasan de una a otra. Abrir una la marca como vista.
  - **Derecha, el detalle:** procedimiento, «Nueva», «Ya es cliente», línea, «Presentación en N días»; objeto grande con
    lo resaltado; entidad, región y nomenclatura; valor referencial, **coincide con** y **con esta entidad** («2
    proyectos ganados · último en 2026»); **cronograma** de puntos (convocatoria, consultas, absolución, integración,
    presentación en coral, buena pro; lo pasado lleno); **ganadores anteriores en esta entidad** (objeto y año, ganador
    con Techquk resaltado, adjudicado y descuento) o «Sin procesos anteriores guardados»; responsable y etiquetas
    (agregar con Enter, quitar con ×). Botones: **Ver en el SEACE**, **Descartar** (motivo), **Asignar** (responsable) y
    **Registrar como oportunidad** (entra a Oportunidades como «Por calificar»); registrada, «Ver la licitación».
  - **Búsqueda** (panel deslizante): líneas de negocio con su nombre, valor mínimo, palabras clave y palabras negativas
    (agregar con Enter, quitar con ×), «+ Nueva línea de negocio»; **¿Por qué no entró?** (nomenclatura o código: qué
    coincidió y qué la dejó fuera, con **Traer igual a la bandeja** y **Quitar la palabra negativa**); **Fuentes**
    (procedimientos del SEACE activo; contratos menores y PAC «más adelante»), «Importar archivo OCDS» y el umbral de
    prioritaria. **Guardar y volver a filtrar** aplica las líneas a todo lo guardado.
- **Convocatorias del SEACE (v6, MV-077)** `/convocatorias`: abre al instante con lo guardado; si la última lectura
  tiene más de 15 minutos, lee otra por detrás y lo nuevo aparece solo en la lista (con un aviso «Llegó 1 convocatoria
  nueva»). Arriba: «N nuevas · Leído hace 3 minutos» y, si la última lectura falló, «El OECE no respondió a las HH:MM:
  se muestra lo guardado». **Leer ahora** fuerza la lectura. Solo se listan las que pasan el filtro.
- **Avisos en Mien (v6):** grupo «Convocatorias del SEACE» con lo de los últimos 3 días: «Nueva convocatoria: objeto ·
  entidad · S/ …» (lleva a la bandeja) y «La convocatoria de [entidad] cambió su fecha de presentación al dd/mm/aaaa»
  (coral; lleva a su licitación).
- **Avisos en Mien (v5):** grupo «Licitaciones: lo que toca»: «Presentar LP-… · en 3 días · falta: expediente» (coral
  a 5 días o menos), el paso actual de cada licitación en preparación, «Pedir "Carta de garantía…"» si falta y vence en
  5 días, y «Subsanar CONV-… · en 2 días». Cada uno lleva a la pestaña.

Quedan fuera de esta versión, del inventario de 15 pantallas: calendario de
cronogramas, «Lo que me tocó», y las pestañas Análisis, Preparación y Propuesta como pantallas aparte (la propuesta se
registra con un botón).

## Operaciones

| Operación | Campos | Valida | Qué produce |
|---|---|---|---|
| Registrar convocatoria | cuenta (obligatoria, usable), nomenclatura (≤ 80), procedimiento (lista), objeto (≤ 500), línea de solución, valor referencial (≥ 0), fecha de convocatoria, dirección de las bases, cronograma (etapa y fecha; propone las seis etapas) | cuenta usable; nomenclatura y objeto con texto | licitación `LIC-AAAA-NNNN` «por decidir»; pública si la cuenta es entidad pública y no es convocatoria privada |
| Participar | responsable (usuario activo) | estado «por decidir» | oportunidad en prospecto en esa cuenta (nombre = objeto, monto = valor referencial, cierre = fecha de buena pro, origen convocatoria pública o privada); expediente sugerido por IA (7 documentos) por confirmar; estado «en preparación» |
| No participar | motivo (texto ≤ 300, obligatorio) | estado «por decidir» | estado «no participamos» con su motivo |
| Aceptar lista sugerida | — | hay sugeridos por confirmar | todos confirmados |
| Agregar, cambiar o quitar hito, documento o consulta | los de cada pestaña | — | — |
| Registrar propuesta | monto ofertado (> 0), fecha | estado «en preparación»; ningún documento sin confirmar ni sin listo | estado «presentada»; hitos hasta la presentación cumplidos |
| Registrar resultado | resultado (buena pro, la ganó otro, desierta), fecha, participantes (postor, ficha de Personas y empresas, oferta, puntaje, puesto, «somos nosotros») | estado «presentada» | puesto 1 = buena pro; todos los hitos cumplidos; perdida o desierta → su oportunidad abierta pasa a perdida (motivo precio con el ganador y su oferta si tiene ficha, o «proceso desierto») |

| Ya me registré (v5) | fecha, constancia (archivo, opcional) | — | registrado como participante |
| Redactar consulta (v5) | tipo (consulta u observación), numeral (≤ 60), texto | — | consulta en borrador |
| Sugerir con IA (v5) | — | — | las sugeridas que no estén, en borrador, marcadas «IA» |
| Enviada (v5) | fecha, constancia (opcional) | — | consulta enviada |
| Registrar absolución (v5) | respuesta, fecha | — | consulta absuelta; si ya no queda ninguna sin absolver, el paso queda hecho con esa fecha |
| Ya descargué las bases (v5) | fecha, dirección | — | bases integradas; hito «Integración de bases» cumplido |
| Documento (v5) | nombre (≤ 200), estado (lista), responsable, vence, necesita firma, archivo, nota (≤ 300) | estado de la lista | nuevo o cambiado; «listo» = listo, firmado o no aplica |
| Guardar oferta (v5) | precio con IGV (> 0) | — | monto ofertado y el presupuesto del que sale |
| Armar paquete (v5) | — | ningún documento que aplique sin listo o firmado; oferta decidida | PDF con índice y una página foliada por documento (y la oferta), con las de firma marcadas |
| Registrar presentación (v5) | fecha y hora, constancia (obligatoria) | oferta decidida; las de Registrar propuesta | estado «presentada» con su hora y constancia |
| Pedir subsanación (v5) | qué pide, plazo | — | pedido abierto (aviso en Mien) |
| Responder subsanación (v5) | respuesta, fecha, constancia | — | pedido respondido |

## Estados

Por decidir → En preparación (participar) → Propuesta presentada → Buena pro / Perdida / Desierta. Por decidir → No
participamos. Con buena pro, la oportunidad se gana desde su ficha, con la cotización aceptada (de ahí nace el
proyecto, ficha `t1-puente-erp`); la licitación no la gana sola.

## Datos

Tablas nuevas: `licitaciones` (código único, nomenclatura, cuenta, es_publica, tipo_procedimiento, objeto, línea de
solución, valor referencial, fecha de convocatoria, url de bases, estado, responsable, motivo de no participar, monto
ofertado, fechas de presentación y resultado, oportunidad única, versión, auditoría), `licitacion_hitos`,
`licitacion_documentos` (origen manual o ia, confirmado, listo, nota, vence), `licitacion_consultas`,
`licitacion_participantes` (nombre, ficha opcional, nosotros, oferta, puntaje, puesto, ganador). Junta
`convocatorias` y `participaciones` del DBML en una sola fila. Permisos `crm.licitaciones.ver` y
`crm.licitaciones.gestionar`.

## Reglas y números

- **v8 · Región:** la del OECE es `address.department` de la entidad convocante (`region` es la provincia); se guarda en `departamento`. Una línea con regiones solo toma convocatorias de esas regiones; sin regiones, todas. Una entidad excluida (por RUC) nunca entra por esa línea. Demo: Videovigilancia y Redes con Lambayeque, Piura, La Libertad, Cajamarca y Amazonas; Software, todas; la demo de «Valle Norte» está en Lambayeque. Caso: AS-SM-12-2026-MDW-1 (cámaras en Cusco) queda fuera por región.

- **v7 · Filtro por líneas:** por cada línea, coincide si el texto (sin tildes ni mayúsculas) contiene alguna palabra
  clave; si contiene una negativa, esa línea no la toma; si el valor referencial está bajo su mínimo, tampoco. Entra con
  la línea que más palabras coincide; si ninguna, queda fuera con su motivo (sin palabra, palabra negativa o bajo el
  mínimo). «Traer igual» la deja en la bandeja aunque no pase. Líneas de la demo (desde S/ 20,000): Videovigilancia
  (videovigilancia, cámaras, cámara, cctv, monitoreo · negativas: cámara frigorífica, cámara de gas), Redes (red de
  datos, cableado estructurado, fibra óptica, switch, wifi, inalámbric, enlaces · red de agua, red de desagüe), Software
  (software, sistema de información, telemetría, centro de datos, data center, servidor, servidores · software contable
  licencia).
- **v7 · Registrar como oportunidad** crea la licitación «por decidir» (con la línea de solución del mismo nombre que la
  línea de negocio) y aparece en Oportunidades como «Por calificar».

- **v6 · Lectura del OECE:** `GET /api/v1/releases?format=json&order=desc`, 20 por página, siguiendo `links.next` hasta
  encontrar un release con fecha igual o anterior al más nuevo guardado; tope de 20 páginas por lectura. Fechas con
  microsegundos y su desfase. Cada convocatoria por su código OCDS (único): si llega igual no se toca; si llega más nueva
  se actualiza y se anota qué cambió (presentación, buena pro, fin de consultas, valor, estado, ganador, monto
  adjudicado, objeto). Prueba real: la primera lectura trajo 400, la siguiente 0.
- **v6 · Lo liviano que se guarda:** código OCDS, nomenclatura, procedimiento, entidad y su RUC, región, objeto (la
  descripción; en el OECE el título es la nomenclatura), texto para filtrar (título, descripción e ítems, hasta 2000),
  valor referencial (0 = no público), moneda, fechas (publicación, fin de consultas, presentación, buena pro), estado,
  ganador con su RUC y monto adjudicado. Sin el JSON completo ni documentos (las bases se bajan al registrar).
- **v6 · Filtro:** entra si el texto contiene alguna palabra clave y el valor referencial no está bajo el mínimo (sin
  valor, entra). Si cambian las palabras o el mínimo, se vuelve a filtrar todo lo guardado en la siguiente lectura o al
  entrar. **Prioritaria:** valor desde S/ 500,000 (parámetro `crm.seace_valor_prioritaria`) o entidad que ya es cuenta.
- **v6 · Cuándo se lee:** cada hora (programada), al entrar si la última tiene más de 15 minutos (una sola a la vez) y
  con «Leer ahora».

- **v5 · Paso hecho:** 1 con fecha de registro; 2 con todas absueltas (o fecha de absolución); 3 con fecha de bases
  integradas; 4 sin documentos que falten (de los que aplican); 5 con monto ofertado; 6 con paquete armado; 7
  presentada; 8 con resultado. Una vez presentada, 1 a 6 cuentan como hechos. El actual es el primero sin hacer; con
  una subsanación abierta, el 8.
- **v5 · Listo para presentar** = (documentos listos o firmados + 1 si hay oferta) ÷ (documentos que aplican + 1).
  Ejemplo de la demo: (4 + 1) ÷ (7 + 1) = 63 %.
- **v5 · Margen de la oferta** = (precio sin IGV − costo) ÷ precio sin IGV, el mismo de las cotizaciones; precio sin IGV
  = precio con IGV ÷ (1 + IGV del presupuesto); costo = costo directo + gastos generales del presupuesto. Demo: costo
  S/ 776,451.00 (601,900.00 + 29 %), precio S/ 1,198,000.00 → sin IGV 1,015,254.24 → margen 23.5 %; 93.3 % del VR.
- **v5 · ¿Cuánto bajar?:** descuento promedio = 100 − promedio del % adjudicado sobre el VR de las licitaciones con
  resultado de esa entidad (demo: 12.3 %, 3 procesos). Colores: verde desde el margen mínimo de cotizaciones (22 %),
  ámbar desde la mitad (11 %), coral debajo. Demo: 98 % → 27.2 %, tu oferta → 23.5 %, 90 % → 20.8 %, 85 % → 16.1 %.
- **v5 · Cuenta regresiva y avisos:** coral a menos de 5 días; la fecha límite del registro de participantes se
  muestra como el día anterior a la presentación.

- % del referencial = oferta ÷ valor referencial × 100, a un decimal. Histórico de la Municipalidad Provincial de Valle
  Norte: semaforización (Seguridad Norte 368,000 de 480,000 = 76.6 %), lectura de placas (285,200 de 310,000 = 92.0 %)
  y videovigilancia (nuestra 2,504,985.40 de 2,650,000 = 94.5 %) → «entre el 76.6 % y el 94.5 %».
- No se presenta con faltantes: en «AS-SM-14-2026-MPVN-1» faltan la carta fianza y los certificados del personal, y
  «Registrar propuesta» responde «Faltan 2 documentos del expediente: …».
- Datos de demo: 15 licitaciones. Las 6 públicas ganadas de la cartera (P1, P2, P3, P6, P8 y P9) con buena pro y sus
  competidores; 3 perdidas (una enlazada a «Cámaras corporales para serenazgo»); 2 en preparación (una con el
  expediente 3 de 5 y consultas; otra con la lista de la IA por confirmar); 2 presentadas (el GORE y una convocatoria
  privada de la minera); 1 por decidir y 1 en la que no participamos. Competidores inventados: Seguridad Norte, Andes
  Tech, Conectividad del Pacífico y Sistemas Integrados de Salud.

## Supuestos sin confirmar

- **v8 · Lo guardado antes** sin departamento se completó releyendo las páginas del OECE (quedan 3 viejas sin dato: fuera de las líneas con regiones). Una convocatoria sin región no entra a una línea que tiene regiones.

- **v7 · Fechas estimadas del cronograma:** el OECE trae convocatoria, fin de consultas, presentación y buena pro;
  absolución (+4 días de las consultas) e integración (+7) se estiman y nunca pasan la presentación.
- **v7 · «Nueva»** es por persona y se apaga al abrirla (también la primera, que se abre sola al entrar).
- **v7 · La lista sin chip** muestra lo que espera decisión: nuevas con presentación por delante (o sin fecha). Las de
  presentación vencida solo se ven buscando o con los chips de estado.
- **v7 · Prioritaria** también por entidad que ya es cuenta: en la demo casi todas las entidades lo son.
- **v7 · «Con esta entidad»** cuenta las oportunidades ganadas de la cuenta con ese RUC y el año de la última.

- **v6 · Las guardadas antes** de esta versión no tienen su texto completo: se quedan en la bandeja como estaban y no
  se vuelven a filtrar.
- **v6 · Avisos en Mien** de convocatorias: los de los últimos 3 días, para quien ve licitaciones (no por persona).
- **v6 · Una registrada que cambia su presentación** solo avisa; no mueve sola el cronograma de su licitación.

- **v5 · La del lienzo** («Valle Verde») se adaptó a la demo como LIC-2026-0005 de la Municipalidad Provincial de Valle
  Norte, con el objeto, el valor referencial, los documentos y la oferta del lienzo; su presupuesto se armó para que el
  margen quede sobre el mínimo. La «presentada con constancia» es LIC-2026-0007 y la «con subsanación» LIC-2026-0008.
- **v5 · Registro de participantes:** se recuerda hasta el día anterior a la presentación (el cronograma no lo trae).
- **v5 · «Abrir el SEACE»** abre el buscador público (no hay dirección directa de cada convocatoria guardada); la
  nomenclatura se muestra para buscarla.
- **v5 · Colores de «¿Cuánto bajar?»** con el margen mínimo de cotizaciones; la IA aún no sugiere precio (solo la
  lista fija de consultas sugeridas).
- **v5 · Subsanación** vive dentro del paso 8 (Resultado), con su plazo.

- Una licitación es a la vez la convocatoria y nuestra participación (se participa a lo más una vez).
- La entidad que convoca tiene que ser una cuenta del CRM.
- Al participar, la oportunidad nace en prospecto (no calificada) con el valor referencial como monto.
- La «lectura de las bases por IA» es, en el MVP, una lista fija de siete documentos marcada como sugerida.
- Con buena pro, ganar la oportunidad sigue siendo un paso aparte (necesita la cotización aceptada).
- Las seis etapas del cronograma por omisión, con +0, +6, +11, +14, +19 y +26 días desde la convocatoria.

## Atajos tomados

- **v7:** la bandeja arma la lista en memoria con todo lo guardado; «Con esta entidad» y los RUC de las cuentas se leen
  con consultas directas. El parámetro viejo de palabras clave quedó sin uso (sus palabras pasaron a las líneas).

- **v6:** la lectura al entrar corre en la misma petición de la pantalla (después de dibujarla), no en una cola; en
  local, lo que la demo deja «por publicar» (`storage/app/private/seace/por-publicar.json`) llega en la primera lectura.
  Los avisos de Mien se guardan en una tabla propia; el estado de la convocatoria es texto.

- **v5:** el paquete es un PDF con índice y una página foliada por documento; los archivos subidos no se incrustan ni se
  firman (se suben aparte). Los estados de documento y consulta son texto con su lista en el modelo. Las consultas
  «sugeridas por IA» son una lista fija. Los archivos van al disco local sin revisar tipo ni tamaño. La pantalla lee la
  etapa de la oportunidad y la entidad con consultas directas.

- Estado y tipo de procedimiento como texto con listas fijas en el código, sin el motor de estados ni catálogo.
- Sin acciones registradas: los botones llaman al servicio directamente; hitos, documentos y consultas se editan con
  formularios simples sin bloqueo optimista ni historial.
- El permiso «gestionar» cubre todo; no hay responsable obligatorio por documento.
- El buscador de fichas del resultado busca por razón social con `ilike`.
- El seeder pone estados y fechas históricas directo en la tabla (no recorre el servicio paso a paso).

## Dónde está en el código

`app/Modules/Crm/Licitaciones/` (provider, `Services/LicitacionesService.php`, modelos, enums,
`Filament/Resources/Licitaciones/` con la lista, la ficha y sus cuatro pestañas); datos en
`database/seeders/Demo/CarteraDemo.php` (`licitaciones()`, `competidores()`) y `DemoSeeder::licitaciones()`. v5:
`Services/PostulacionService.php`, `Filament/LicitacionDeOportunidad.php` con `resources/views/oportunidad/licitacion.blade.php`
y `paquete.blade.php`, la migración `2026_11_23_700001_postulacion_por_pasos.php` y `Demo/PostulacionCasosSeeder.php`. v6:
`Services/ConvocatoriasSeaceService.php` (`sincronizar`, `guardar`, `leerSiHaceFalta`, `priorizar`), `Services/FiltroConvocatorias.php`,
`Events/` y `Listeners/` (filtrar, priorizar, avisar), la migración `2026_11_23_800001_convocatorias_todo_lo_liviano.php`,
`routes/console.php` (cada hora) y `Demo/ConvocatoriasCasosSeeder.php`. v7: `Services/BandejaSeace.php`,
`Filament/Pages/BandejaConvocatorias.php` con `resources/views/bandeja/pantalla.blade.php`, la migración
`2026_11_23_900001_bandeja_con_detalle.php` y `Demo/BandejaCasosSeeder.php`.

## Cambios desde la versión anterior

- **v8** (MV-089): regiones y entidades excluidas por línea; filtros rápidos de región y entidad.

- **v7** (MV-078): la bandeja con detalle reemplaza la tabla; líneas de negocio con palabras negativas y su mínimo
  (reemplazan el parámetro único de palabras clave), «¿Por qué no entró?», responsable, etiquetas, vistas por persona.

- **v6** (MV-077): se guarda todo lo liviano del OECE; lectura incremental cada hora y al entrar; eventos «llegó» y
  «cambió» con sus reacciones (filtrar, priorizar, avisar en Mien).

- **v5** (MV-076): la pestaña Licitación de la oportunidad es la ruta de 8 pasos con la oferta desde el presupuesto, el
  paquete, la presentación con constancia, la subsanación y los avisos en Mien.

- **v4** (observaciones-6, MV-066): «Leer ahora del OECE» lee
  `https://contratacionesabiertas.oece.gob.pe/api/v1/releases` (`format=json`): lo más nuevo primero, 20 releases por
  página, siguiendo `links.next` mientras haya releases publicados después de «desde» (3 días atrás por omisión) y hasta
  20 páginas (400 releases). Si la dirección configurada es `releasesAfter`, se usa `/releases` (la primera ordena por
  id interno y trae releases de 2024). El servicio responde 403 a clientes sin nombre: se pide con un agente genérico
  propio. Si la primera página falla, avisa; si falla una siguiente, se queda con lo leído. Un valor referencial 0 se
  toma como «no publicado» (antes el valor mínimo lo dejaba fuera: el OECE manda 0 en la mayoría). Probado contra el
  servicio real el 2026-11-23: 400 leídas, 11 entran, 389 fuera por el filtro, en unos 35 s. El supuesto de la v2 sobre
  la dirección queda resuelto.

- **v3** (ronda 1, MV-023): ya no hay caja Licitaciones. La oportunidad que nació de una licitación tiene la pestaña
  **Licitación** (primera de las que suman otros módulos): estado y plazo próximo, las acciones del estado (participar,
  no participar, aceptar lista sugerida, registrar propuesta, registrar resultado), y Bases, Cronograma, Expediente,
  Consultas, Propuesta (con el rango histórico de la entidad) y Resultado. Cronograma, expediente y consultas se editan
  en la ficha completa (`/licitaciones/{id}`, botón en la pestaña). **La buena pro ahora gana la oportunidad** por lo
  ofertado (antes solo se cerraba al perder); si el embudo exige una cotización aceptada, queda abierta. La lista
  `/licitaciones` es la pestaña «Licitaciones» de la portada de Oportunidades, con su miga «Conseguir el negocio ›
  Oportunidades».
- **Supuesto:** la pestaña aparece cuando la oportunidad tiene licitación (no por ser entidad pública sin licitación
  registrada); una convocatoria registrada sin participar todavía no tiene oportunidad y se ve en la lista.
- **Atajo:** cronograma, expediente y consultas se ven de solo lectura en la pestaña y se editan en la ficha vieja.

v2 (2026-11-20, pedido de Sebastián: «sí, hazlo»): **Bandeja de convocatorias del SEACE** (`/convocatorias`, caja
«Convocatorias del SEACE»). Las convocatorias llegan de los datos abiertos de contrataciones del OECE en el estándar
OCDS (paquetes de releases o records): solo entran las que mencionan alguna de las palabras clave de Techquk (parámetro
`crm.seace_palabras_clave`: videovigilancia, cámaras, CCTV, red de datos, cableado estructurado, fibra óptica, switch,
servidor, centro de datos, software, sistema de información, telemetría, wifi, inalámbrico) y pasan el valor mínimo
(parámetro `crm.seace_valor_minimo`, 20,000). La tabla muestra convocatoria y tipo, objeto con entidad y región, si la
entidad ya es cuenta, valor referencial, presentación (rojo a menos de 5 días) y con qué palabras coincide; filtro
nuevas, registradas, descartadas o todas. **Registrar** crea la licitación «por decidir» con nomenclatura, tipo,
objeto, valor y cronograma (convocatoria, consultas, absolución, integración, presentación y buena pro del release; las
que no trae, a +6, +11, +14 días) y la cuenta de la entidad si no existe (pública, nivel por el nombre: municipalidad →
local; regional, UGEL, proyecto especial → regional; si no, nacional). **Descartar** con motivo de una lista. «Ver en
el SEACE» abre el enlace del documento. Arriba: **Leer ahora del OECE** y **Importar archivo OCDS**. La lectura corre
sola cada día a las 7:00 (`php artisan seace:leer`).

Demo: 9 releases inventados importados con el mismo lector; entran 6 (los de pistas, uniformes y un mantenimiento de
12,500 quedan fuera); el del GORE se registró como licitación y el de farmacia del hospital se descartó.

Supuesto: **la dirección del servicio del OECE no se confirmó** (el dominio cambió del OSCE al OECE); se configura en
`OECE_OCDS_URL` y se le pasa `since`. Hasta tenerla, «Leer ahora» lo avisa y la bandeja se llena importando el archivo
del portal de datos abiertos. Atajos: sin acción registrada; la cuenta de la entidad no consulta el padrón de SUNAT.
