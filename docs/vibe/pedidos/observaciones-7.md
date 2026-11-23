# Observaciones 7 (séptima tanda)

Pedido de Sebastián, desde el 2026-11-23. Cada punto se registra como decisión en `docs/traspaso/decisiones.md` y
actualiza su ficha.

## 1. Oportunidades: una sola lista, sin las pestañas Por calificar · Oportunidades · Licitaciones

Hoy la portada de Oportunidades tiene tres pestañas (Por calificar, con los leads; Oportunidades; Licitaciones, con sus
plazos). Se quitan: **todo va en la lista de oportunidades** y se separa con los filtros del costado.

- **«Por calificar» es la primera etapa del embudo**, antes de Prospecto. En el filtro ETAPA: Por calificar ·
  Prospecto · Calificada · Propuesta enviada · Negociación · (Ganadas / Perdidas). Elige una gema para esta etapa,
  anterior a «en bruto» (por ejemplo, una piedra aún sin limpiar o la roca con la veta), y úsala en el embudo y en la
  línea de tiempo.
- En «Por calificar» caen:
  - **los leads** (WhatsApp, formularios, campañas). **Calificar** lo pasa a Prospecto como oportunidad (lo que hoy hace
    convertir el lead); **Descartar** lo saca con su motivo.
  - **las licitaciones por decidir**: al pulsar **Registrar** en Convocatorias del SEACE (o registrar una convocatoria a
    mano), la licitación entra aquí como tarjeta. **Participar** la pasa a Prospecto (lo que hoy hace Participar);
    **No participar** la saca con su motivo.
  Las tarjetas por calificar muestran lo que tienen (nombre, cuenta o contacto, origen, monto o valor referencial si lo
  hay) y sus dos botones a la mano.
- **Filtro nuevo «TIPO»:** Privada · Licitación pública (y privada por convocatoria, si se distingue hoy). Con
  «Licitación pública» se ve lo que hoy muestra la pestaña Licitaciones.
- **Plazos de las licitaciones en la tarjeta:** el próximo hito de su cronograma («Presentación en 3 días»), en coral a
  menos de 5 días. El orden por urgencia las considera: un hito de licitación vencido o cercano cuenta como un cierre
  vencido o cercano.
- **Los contadores de las pestañas** (7 por calificar, 6 licitaciones) pasan a ser los números junto a cada filtro,
  como «Cierre estimado vencido 2».
- La sección «Licitación» de la ficha de la oportunidad no cambia (bases, cronograma, expediente, consultas,
  propuesta y resultado).
- **Convocatorias del SEACE sigue como caja aparte** (la bandeja de lo que publica el Estado); Campañas también sigue
  con sus resultados. Quita las rutas y vistas que solo servían a las pestañas (`/leads`, `/licitaciones` como listas)
  o redirígelas a Oportunidades con su filtro puesto.
- Las cifras de arriba (Embudo abierto, Ponderado…) no cuentan lo que está por calificar, salvo que ya lo hagan; anota
  lo que decidas como supuesto.

**Reemplaza** las decisiones MV-023 (en lo que dice de «Licitaciones» como pestaña de la portada) y MV-024 (en lo que
dice de «Por calificar» como pestaña): registra una decisión nueva y marca esas como «reemplazada por» en lo que
corresponda.

**Alcance:** cruza módulos (Oportunidades, Campañas y leads, Licitaciones).

## 2. Conseguir el negocio: sobra espacio bajo el nombre del proceso

En la portada de Conseguir el negocio, entre «¡Vamos por esas oportunidades!» / «CONSEGUIR EL NEGOCIO» y el contenido
de la caja (hoy las pestañas, después de la observación 1 las vistas de Oportunidades) queda un espacio vacío grande.
El contenido empieza justo debajo del nombre del proceso, con un respiro normal (como entre las demás secciones). Revisa
que no pase lo mismo en las portadas de los otros procesos.

**Alcance:** ficha (portada de procesos).

## 3. La postulación se gestiona entera desde el sistema; el SEACE solo para el envío final

Decisión de negocio de Sebastián: «quiero que se haga toda la gestión de postulación y demás desde nuestro sistema».
Como el SEACE no ofrece una conexión para que otro sistema registre participación, consultas u ofertas (el proveedor
entra con su certificado SEACE, usuario y clave, en el portal), se elige: **todo el trabajo en el sistema y en el SEACE
solo el envío**, hecho por una persona. **No** se automatiza el portal con un robot ni se guardan credenciales del
SEACE en el sistema.

Lo que el sistema hace dentro de la sección «Licitación» de la oportunidad (completa lo que ya existe):

- **Participar en el SEACE:** recordatorio con la fecha límite de registro de participantes, botón que abre el SEACE en
  esa convocatoria y «Ya me registré» (fecha y, opcional, la constancia).
- **Consultas y observaciones:** se redactan en el sistema (con la IA sugiriendo a partir de las bases, y una persona
  confirma), con el número de página o numeral de las bases; botón «Copiar para el SEACE» con el texto en el formato
  que pide el portal; luego «Enviada» con la constancia. La absolución se registra al salir.
- **Expediente:** la lista de documentos (sugerida por IA, confirmada por una persona) con responsable, estado y
  archivo; avisos de lo que falta y de lo que vence (vigencias de constancias, por ejemplo).
- **Oferta económica desde el presupuesto:** el monto ofertado sale del presupuesto de la cotización o del proyecto,
  con el margen que deja, para que la decisión de precio se vea con su ganancia.
- **Paquete listo para subir:** un botón arma el PDF único en el orden de las bases, foliado, con índice, y marca qué
  páginas necesitan firma; se descarga listo para el SEACE.
- **Presentar:** botón que abre el SEACE en la página de presentación de esa convocatoria; al volver, «Registrar
  presentación» pide la constancia (archivo o foto de pantalla) y la hora, y la licitación pasa a «presentada».
- **Subsanación y resultado:** si la entidad pide subsanar, se registra el pedido con su plazo (aviso en Mien) y su
  respuesta con constancia; el resultado sigue como hoy (buena pro, la ganó otro, desierta).
- **Avisos en Mien** para cada fecha del cronograma que se acerca y lo que le falta a cada paso.

**Diseño elegido: «A · Ruta por pasos»** (lienzo: https://claude.ai/artifact/3k6QfeLw7SscVW7rRvBSSe; copia en
`docs/vibe/pedidos/lienzos/postulacion/Main.dc.html` y la oferta en `Oferta.dc.html`; la copia manda). En la pestaña
«Licitación» de la oportunidad:

- **Cabecera:** tipo, etapa y nomenclatura; objeto, entidad y valor referencial; **cuenta regresiva** a la presentación
  («3 días · jue 26 nov 23:59», en coral a menos de 5 días) y barra **«Listo para presentar N %»** (documentos listos o
  firmados sobre los que aplican, más la oferta).
- **Izquierda, los 8 pasos** en orden: Participar en el SEACE · Consultas y observaciones · Bases integradas ·
  Expediente · Oferta económica · Paquete para el SEACE · Presentar en el SEACE · Resultado. Cada uno con su estado
  (check turquesa si está hecho, número en azul resaltado si es el actual, gris si falta) y una línea de resumen
  («Registrado el 12 nov», «5 de 8 documentos listos», «S/ 1,198,000.00 · margen 18.4 %»). Clic en un paso muestra
  su contenido a la derecha; al entrar se abre el paso actual.
- **Derecha, el contenido del paso** (lo descrito arriba para cada uno). El **Expediente**: documentos en el orden de
  las bases integradas, con responsable (avatar) y estado (Firmado, Listo, En revisión, Falta, No aplica; coral lo que
  falta con su urgencia); «+ Documento»; al pie, lo que falta y los botones «Ver el paquete» y **«Armar paquete para el
  SEACE»**, activo solo cuando no falta nada.
- **Oferta económica** (del lienzo `Oferta.dc.html`): las partidas del presupuesto con su costo; el precio ofertado
  editable con el **margen al instante** y el % sobre el valor referencial (mismo cálculo de margen del resto del
  sistema); y **«¿Cuánto bajar?»**: el descuento promedio de los ganadores en esa entidad (de lo guardado del OECE) y
  el margen que queda al ofertar a varios porcentajes del valor referencial, en verde, ámbar o coral. La IA puede
  sugerir; el precio lo decide una persona.
- Las tareas con fecha de cada paso (pedir un documento, revisar, armar, presentar) van además como avisos en Mien.

Datos de demo: la licitación del lienzo (Valle Verde) en el paso Expediente, con documentos en todos los estados y la
oferta lista; otra ya presentada con su constancia; y otra con subsanación pedida.

**Alcance:** negocio (cómo Techquk postula a licitaciones); ficha t1-licitaciones.

## 4. Convocatorias del SEACE: guardar todo lo liviano, leer solo lo nuevo y reaccionar a lo que llega

Complementa la observación 6 de la sexta tanda (la lectura real del OECE por páginas).

**Guardar todo, mostrar lo filtrado.** Hoy solo entra lo que coincide con las palabras clave. Pasa a guardarse **todo**
lo que publica el OECE, pero solo sus datos livianos (código OCDS, nomenclatura, tipo de procedimiento, entidad y su
RUC, región, objeto, valor referencial, fechas del cronograma, estado, y cuando lo haya, el ganador y el monto
adjudicado), sin el JSON completo ni documentos. La **bandeja muestra solo lo que coincide** con las palabras clave y
pasa el valor mínimo, como hoy. Las bases y documentos se descargan **solo cuando se registra** una convocatoria.

Con eso:

- Si cambian las palabras clave o el valor mínimo, el filtro se vuelve a aplicar sobre lo guardado y aparecen al
  instante las convocatorias que antes no entraban.
- Queda listo el terreno para el buscador libre, el «¿por qué no entró?» y el análisis de precios y ganadores por
  entidad (punto de extensión; se diseñan después).

**Leer solo lo nuevo (lectura incremental).** El sistema guarda hasta dónde leyó (la fecha del release más nuevo que
tiene). Cada lectura pide al OECE lo más reciente primero (`order=desc`) y sigue las páginas hasta llegar a algo que ya
tiene o a esa fecha; ahí se detiene. Cada convocatoria se identifica por su código OCDS (único en la base): si llega dos
veces no se duplica, y si llega una versión más nueva (enmienda, cambio de cronograma, resultado) se actualiza y queda
anotado qué cambió. Con un tope de páginas por lectura para no quedarse leyendo sin fin la primera vez.

**Cuándo se lee.**

1. En segundo plano **cada hora** (hoy es una vez al día a las 7:00).
2. Al **entrar** a Convocatorias del SEACE, la pantalla abre **al instante** con lo guardado; si la última lectura tiene
   más de 15 minutos, lanza otra por detrás y lo nuevo **aparece solo** en la lista con la animación de entrada, sin
   recargar. Arriba, discreto: «Leído hace 3 min».
3. **«Leer ahora»** sigue para forzarla.
4. Si el OECE no responde, la pantalla sigue con lo guardado y lo dice en una línea, sin bloquear nada.

**Reaccionar a lo que llega (por eventos).** Cada convocatoria nueva o actualizada dispara un evento («llegó una
convocatoria» / «cambió una convocatoria») y cada reacción es una pieza separada:

- **Filtrar:** decide si entra a la bandeja (palabras clave y valor mínimo).
- **Priorizar:** la marca como prioritaria si el valor referencial es alto (parámetro, por ejemplo desde S/ 500,000) o
  si la entidad ya es cuenta de Techquk.
- **Avisar en Mien:** «Nueva convocatoria: [objeto] · [entidad] · S/ …» para las que entran; y si una ya registrada
  cambió su cronograma, «La convocatoria de [entidad] cambió su fecha de presentación al …». Más adelante, el mismo
  evento puede avisar por WhatsApp (punto de extensión).

**Datos de demo:** que se vea la bandeja con lo filtrado, la marca «Leído hace …», una convocatoria prioritaria, una
que apareció sola tras una lectura y una registrada con su cronograma cambiado (con el aviso en Mien).

**Alcance:** ficha (t1-licitaciones, Convocatorias del SEACE).

## 5. Convocatorias del SEACE: la bandeja con detalle

Diseño elegido: **«B · Bandeja con detalle»** (lienzo: https://claude.ai/artifact/Rffef9fypAFE3jd2zVrEtN; copia en
`docs/vibe/pedidos/lienzos/convocatorias/Main.dc.html`, y la configuración en `Configuracion.dc.html`; la copia manda).
Reemplaza la tabla de hoy. Funciona como un correo: decidir rápido, una por una.

**Arriba** (bajo el nombre del proceso): buscador por objeto, entidad o nomenclatura con el interruptor **«Buscar en
todo el SEACE»** (busca en todo lo guardado, no solo en la bandeja; lo que no está en la bandeja se ve más suave con
«fuera de tu bandeja»); «Leído hace N min»; **Leer ahora**; **Búsqueda** (abre la configuración). Debajo, dos grupos de
chips con su cantidad: **líneas de negocio** (Todas y una por línea) y **ver** (Nuevas, Prioritarias, Mías, Por vencer,
Registradas, Descartadas). Al cambiar un chip, la lista se reacomoda con la animación suave.

**Izquierda, la lista compacta** (unos 430 px): por convocatoria, el punto turquesa y la negrita si es nueva (sin abrir
por esa persona), la estrella dorada si es prioritaria, la entidad, los días a la presentación (coral a menos de 5), el
objeto en dos líneas con las palabras que coincidieron resaltadas, el valor referencial, la línea de negocio y el
responsable si tiene. Orden por urgencia (presentación más cercana primero) y un selector con otros órdenes (valor,
recientes, entidad). La elegida, resaltada en el azul de selección. Flechas ↑ ↓ para pasar de una a otra.

**Derecha, el detalle de la elegida:**

- Tipo de procedimiento, «Nueva», «Ya es cliente», línea de negocio y «Presentación en N días».
- Objeto (grande, con lo que coincidió resaltado); entidad, región y nomenclatura.
- Valor referencial; **coincide con** (las palabras); **con esta entidad** (proyectos ganados y el último año, desde
  los datos de Techquk).
- **Cronograma** como línea de puntos (convocatoria, consultas, absolución, integración, presentación, buena pro), lo
  pasado lleno y la presentación en coral.
- **Ganadores anteriores en esta entidad** (de lo guardado del OECE): objeto y año, ganador (Techquk resaltado), monto
  adjudicado y descuento sobre el valor referencial. Si no hay, una línea «Sin procesos anteriores guardados».
- Responsable (con su avatar) y etiquetas (agregar o quitar).
- Botones: **Ver en el SEACE**, **Descartar** (con motivo), **Asignar** (responsable), **Registrar como oportunidad**
  (entra a Oportunidades como «Por calificar», observación 1).

**Configuración de la búsqueda** (botón «Búsqueda», en panel deslizante):

- **Líneas de negocio** (Videovigilancia, Redes, Software y «+ Nueva línea»), cada una con sus **palabras clave** y sus
  **palabras negativas** (si aparece una negativa, no entra aunque coincida otra palabra) y su valor mínimo. Reemplaza
  el parámetro único de palabras clave de hoy (sus palabras se reparten en las tres líneas en los datos de demo).
- **«¿Por qué no entró?»:** se escribe una nomenclatura o código; explica si coincidió, con qué, y qué la dejó fuera
  (palabra negativa, valor mínimo, fuente); botones «Traer igual a la bandeja» y «Quitar la palabra negativa».
- **Fuentes:** procedimientos del SEACE (activo); contratos menores y Plan Anual de Contrataciones, visibles pero
  desactivados con «más adelante» (punto de extensión).
- El umbral de prioritaria (S/ 500,000 o entidad que ya es cliente).

Datos de demo: las seis convocatorias del lienzo (o equivalentes) repartidas en las tres líneas, con nuevas,
prioritarias, una con responsable y etiqueta, una por vencer, varias descartadas, y procesos anteriores guardados de
al menos una entidad (uno ganado por Techquk) para que se vea la tabla de ganadores; y en la configuración, un caso de
«¿Por qué no entró?» por palabra negativa (la cámara frigorífica).

**Alcance:** ficha (t1-licitaciones, Convocatorias del SEACE).

## 6. Campañas: retorno, campañas a los propios clientes y radar del PAC

Lienzo: https://claude.ai/artifact/Ahx6KoSh3RPTdjNQd1VEgJ (copia en `docs/vibe/pedidos/lienzos/campanias/`; la copia
manda). La caja Campañas se divide en secciones con chips arriba: **Campañas · Mis clientes · Radar del PAC** (el
calendario del año queda para más adelante; no se construye).

**6.1 Campañas con su retorno** (`Main.dc.html`), ahora:

- Cinco cifras arriba: invertido en el año (contra lo presupuestado), contactos (y costo por contacto),
  oportunidades (y % de los contactos), ganado (y cuántos proyectos) y **retorno** = margen de lo ganado ÷ lo gastado
  (en verde si es 1× o más, coral si no).
- Tarjeta por campaña: tipo; **mini embudo** contactos → oportunidades → ganadas (barras proporcionales); gastado /
  presupuesto (coral si se pasó); ganado; **margen de lo ganado** (del margen real de esos proyectos, el mismo cálculo
  que usa el resto del sistema; mientras el proyecto no cierre, el margen previsto, marcado así); retorno.
- **Gasto real:** «Registrar gasto» registra un gasto de verdad con el selector de imputación que ya existe (gasto
  directo del centro de costo de Comercial, vinculado a la campaña). Lo gastado de la campaña es la suma de esos
  gastos; el presupuesto es el dato de hoy.
- **Formulario y QR:** cada campaña tiene un enlace público a un formulario corto (nombre, organización, teléfono,
  correo, qué necesita) y su QR para imprimir. Lo que se envía entra a Oportunidades como «Por calificar», marcado con
  la campaña. También un enlace de WhatsApp con el texto «Hola, vengo de [campaña]». El formulario no pide sesión;
  límitalo contra envíos repetidos (atajo aceptado en el MVP: anótalo).
- Aviso en Mien: «N contactos de [campaña] llevan 3 días sin atender».

**6.2 Mis clientes** (`Clientes.dc.html`), ahora:

- Tres grupos que el sistema arma solo, con su cantidad y un monto estimado: **garantía por vencer en 90 días**
  (ofrecer mantenimiento), **instalado hace 3 años o más** (ofrecer renovación) y **solo tienen una línea** (ofrecer
  otra: por ejemplo, solo videovigilancia → red o software). Salen de Proyectos y Postventa (sus servicios, no sus
  modelos).
- «Ver la lista»: los clientes del grupo con su proyecto y el dato que los puso ahí, con casilla para incluir o no.
- «Crear campaña» desde el grupo: nace una campaña de tipo «Clientes» con esos destinatarios y un **seguimiento**
  (día 1 correo, día 3 WhatsApp, día 7 llamada, editable). En el MVP **no se envía nada de verdad**: cada paso se
  vuelve una **tarea en Mien** para el responsable, con el texto de la plantilla listo para copiar. El envío
  automático por correo o WhatsApp queda como punto de extensión (necesita un servicio de pago).
- Lo que el cliente responda se registra como oportunidad «Por calificar» con la campaña.

**6.3 Radar del PAC** (`Pac.dc.html`), **primero investigar**:

- Antes de construirlo, averigua si los datos abiertos del OECE (la misma API de contrataciones abiertas, la etapa de
  planificación, u otra publicación oficial descargable) entregan los Planes Anuales de Contrataciones de las entidades
  con su objeto, trimestre y valor estimado, y en qué forma. **Detente y avísame** con lo que encontraste y una
  propuesta de cómo leerlos, antes de construir.
- Si se puede: lista de lo que las entidades planean comprar en las líneas de negocio (mismas palabras clave y
  negativas de la bandeja), con entidad («Ya es cliente»), objeto, trimestre y valor estimado; cifras arriba; botón
  «Crear acercamiento» que crea una campaña de tipo «Acercamiento» con sus pasos como tareas; y cuando sale la
  convocatoria de esa entidad y objeto, la bandeja la marca prioritaria y la une a la campaña.
- Mientras tanto, el chip «Radar del PAC» puede no mostrarse.

Datos de demo: las cuatro campañas del lienzo (o equivalentes) con sus gastos reales y sus contactos, oportunidades y
proyectos ganados, de modo que los retornos den lo del lienzo; los tres grupos de Mis clientes con clientes reales de
la demo; una campaña a clientes con sus tareas en Mien.

**Alcance:** cruza módulos (Campañas, Oportunidades, Proyectos, Postventa, Finanzas por el gasto, Mien).

## 7. El modo oscuro no se activa

En el selector de tema de Filament (sol · luna · pantalla, en el menú de usuario) se puede pulsar «oscuro», pero la app
**no cambia a modo oscuro**. Es probable que venga de la observación 5.5 (colores que siguen al ambiente, commit
`8bde1b0`), porque antes funcionaba.

Encuentra la causa con la app levantada y la consola del navegador, revisando en este orden:

1. Al pulsar «oscuro», ¿`<html>` recibe la clase `dark`? Si la recibe y se la quitan enseguida, busca quién: el script
   de `nucleo::filament.apariencia` (que aplica el tema guardado en la base), una navegación sin recarga que vuelve a
   correrlo con el tema viejo, o el componente `GuardarTema` (si `nucleo.cambiar-preferencia` falla, por ejemplo por un
   permiso, lo calla y la base sigue en «claro»).
2. Si la clase queda puesta pero la pantalla sigue clara: revisa que las reglas de `colores-ambiente.blade.php` y de
   `LuzDelDia` para `html.dark` ganen a las de `html:root` / `html:not(.dark)` (orden y especificidad), y que ningún
   `!important` deje superficies, fondo o textos con valores claros fijos.
3. Prueba los tres temas (claro, oscuro, sistema), con y sin «apariencia según el día», en varias pantallas y después
   de navegar sin recargar; el cambio debe verse al instante y mantenerse al recargar y en otro navegador (se guarda en
   la base).

**Segundo fallo, del mismo lado: «Sistema» está copiando al sistema operativo.** A las 2:30 p. m. en Chiclayo, con
«Sistema», la app se ve **oscura**. Eso contradice MV-010, MV-012 y MV-013 (vigentes): en «Sistema» la pantalla **no**
copia al sistema operativo, sino que sigue el cielo de la ubicación (la del navegador si ya dio permiso; si no, la de la
sede), de forma continua: luz dorada mientras el sol baja, naranja y rosado al ocultarse, la hora azul y luego oscuro,
sin un cambio de tema perceptible; al amanecer, al revés; el clima lo matiza. A esa hora debe verse **claro**.
Sospechoso: el script de `nucleo::filament.apariencia` decide `dark` con `prefers-color-scheme` cuando el tema es
`system` (y el arranque de Filament hace lo mismo), pisando la transición del cielo (ficha `t3b-transicion-cielo`).
Haz que en «Sistema» mande el cielo en todos los lugares que deciden la clase `dark`, también después de navegar sin
recargar, y comprueba con la hora de desarrollo (`ahoraDecorativo`) a media tarde, al atardecer y de noche.

Pista: como con «Sistema» la app sí se pone oscura, los estilos oscuros funcionan; lo que falla al elegir «Oscuro» a
mano es otra cosa: empieza por el punto 1.

Al terminar, dime en una línea cuál era la causa.

**Alcance:** ficha (apariencia).
