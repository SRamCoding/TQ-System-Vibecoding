# Observaciones 8 (octava tanda)

Pedido de Sebastián, desde el 2026-11-23. Cada punto se registra como decisión en `docs/traspaso/decisiones.md` y
actualiza su ficha.

## 1. «Mis oportunidades»: tarjetas en 3 columnas

En Oportunidades, vista «Mis oportunidades», las tarjetas hoy salen en 4 columnas (o más, según el ancho). Pasan a
**3 columnas como máximo**: 3 en pantallas anchas, 2 en medianas y 1 en angostas. Las tarjetas crecen para ocupar el
ancho (la foto o ilustración de arriba mantiene su proporción). La animación al filtrar u ordenar sigue igual.

**Alcance:** ficha (Oportunidades, vista «Mis oportunidades»).

## 2. Embudo de oportunidades: «Por calificar» también abre su panel

En la vista «Embudo de oportunidades», al pulsar una etapa se abre a la derecha el panel con sus oportunidades. Al
pulsar **«Por calificar»** (la primera etapa, observación 7.1) debe pasar lo mismo: el panel muestra **lo que falta
calificar**, igual que las demás etapas.

- Cada elemento con lo que tiene: su gema, nombre, cuenta o contacto, **origen** (WhatsApp, formulario, campaña con su
  nombre, o «Convocatoria del SEACE»), monto o valor referencial si lo hay, y **cuánto lleva esperando** («hace 3
  días», en coral desde 3 días).
- Orden: primero las licitaciones por decidir, por la fecha de su próximo plazo (en coral a menos de 5 días); luego los
  leads, del más antiguo al más nuevo.
- Sus botones a la mano, como en la lista: **Calificar / Descartar** para un lead y **Participar / No participar** para
  una licitación. Al resolver uno, sale del panel con la animación suave y la cifra de la banda se actualiza.
- Arriba del panel, como en las otras etapas, la cantidad y el total (lo que tenga monto).

**Alcance:** ficha (Oportunidades, vista «Embudo de oportunidades»).

## 3. «Mis oportunidades»: el panel de filtros baja por su cuenta

Hoy el panel de filtros de la izquierda (Atención, Tipo, Etapa, Ejecutivo, Línea de solución, Cliente) es más alto que
la pantalla y queda pegado arriba (`position: sticky`): para ver los últimos filtros hay que bajar hasta el final de
las tarjetas.

- El panel tiene **su propio desplazamiento**: alto máximo = lo que queda de pantalla bajo el encabezado, y si no
  entra, se baja con la rueda del mouse sobre él sin mover las tarjetas. La barra de desplazamiento, invisible hasta
  que se usa (como en las barras laterales, observación 4.1/4.2).
- **Grupos plegables:** Atención y Etapa abiertos; Tipo, Ejecutivo, Línea de solución y Cliente plegados, con la
  cantidad de filtros activos junto al título («Cliente · 2»). Se recuerda qué grupos dejó abiertos cada persona.
- **Cliente** y **Ejecutivo**, si tienen más de 6 opciones: un buscador pequeño dentro del grupo y las 6 con más
  oportunidades primero, con «Ver todos».
- Arriba del panel, **«Quitar filtros»** cuando hay alguno activo.

**Alcance:** ficha (Oportunidades, vista «Mis oportunidades»).

## 4. Sin el título «Embudo de oportunidades» al cambiar de vista

Al pasar a la vista «Embudo de oportunidades» aparece el título de página **«Embudo de oportunidades»** (grande, a la
izquierda, sobre las pestañas de vistas). **No debe salir**, igual que se quitaron los títulos de las demás pantallas
(observaciones 2 y 3 de la segunda y tercera tanda): el nombre del proceso ya está arriba y la pestaña elegida dice en
qué vista se está. Revisa que tampoco salga en «Mis oportunidades» ni en «Línea de tiempo», ni al entrar por un enlace
directo o después de navegar sin recargar.

El botón **«+ Nueva oportunidad»** se queda, alineado a la derecha en la misma fila de las pestañas de vistas, y sin
que lo tape el muelle.

**Alcance:** ficha (Oportunidades).

## 5. Reordenar las cajas de un proceso no debe mover nada más

En la barra de cajas de un proceso (por ejemplo, Convocatorias del SEACE · Oportunidades · Campañas), al arrastrar una
caja para cambiarla de lugar **la página se vuelve a cargar** y se pierde dónde estaba la persona: si estaba en
Oportunidades, vista «Embudo de oportunidades», vuelve a «Mis oportunidades».

Al reordenar **solo deben moverse las cajas** (con su animación de deslizar) y guardarse el nuevo orden por persona,
sin recargar ni volver a pedir la página. Todo lo demás queda **exactamente como estaba**: la caja elegida, la vista
(Embudo, Línea de tiempo…), los filtros puestos, el panel abierto, la etapa elegida del embudo y la posición de
desplazamiento. Si la caja elegida cambia de lugar, sigue siendo la elegida en su nueva posición.

Revísalo en todos los procesos y con varias vistas, también después de navegar sin recargar. Si la vista actual no
queda en la dirección (URL), guárdala ahí (por ejemplo `?vista=embudo`) para que tampoco se pierda al recargar o
compartir el enlace.

**Alcance:** cruza módulos (navegación de todos los procesos).

## 6. La caja sigue elegida mientras se esté dentro de su módulo

En Oportunidades la caja está resaltada, pero al pulsar «+ Nueva oportunidad» (y en general al abrir cualquier pantalla
hija: crear, editar, la ficha de una oportunidad, un formulario) y luego volver o cerrar, la caja aparece
**deseleccionada**. La caja de un módulo debe quedar **elegida mientras la persona siga en ese módulo**, en cualquiera
de sus pantallas (lista, vistas, crear, editar, ficha, paneles), y solo cambiar cuando pase a otra caja o a otro proceso.

- La caja elegida se decide por el **módulo** de la pantalla actual, no por la dirección exacta de su lista: todas las
  rutas de Oportunidades (`/oportunidades`, `/oportunidades/crear`, `/oportunidades/{id}`, …) marcan Oportunidades.
- Lo mismo en todos los procesos y cajas (Convocatorias, Campañas, Contratos, Proyectos…), también al volver con el
  botón «atrás» del navegador y después de navegar sin recargar.
- Al volver a la lista desde una pantalla hija, se vuelve a la vista en la que se estaba (observación 5).

**Alcance:** cruza módulos (navegación de todos los procesos).

## 7. El panel «Búsqueda» de Convocatorias del SEACE no se lee bien

El panel deslizante de la configuración de la búsqueda se ve lavado y con poco contraste: el fondo del panel es
translúcido (vidrio) y deja pasar la pantalla de atrás, las cajas de cada línea casi no se distinguen del fondo, los
campos «+ agregar» y los montos («desde S/ 20000», «Prioritaria desde S/ 500000») apenas se ven, las fuentes
desactivadas quedan casi invisibles y el asistente queda encima del contenido.

- **Paneles deslizantes con fondo sólido**, no vidrio: el vidrio de la observación 5.5 se queda para lo pequeño que
  flota (muelle, globos, menús); la **agenda también pasa a fondo sólido** (decisión de Sebastián); un panel de trabajo con formularios usa la superficie elevada opaca del
  ambiente y una sombra que lo separe de la pantalla atenuada. Aplícalo a todos los paneles deslizantes.
- **Qué pasa con la pantalla de atrás** (decisión de Sebastián, para todo el sistema): los **paneles que salen de un
  costado** (paneles deslizantes) la **oscurecen** con una capa oscura pareja (negro al 40-45 %, sin el gris que lava
  todo de hoy); las **ventanas que salen al medio** (ventanas modales, la agenda, confirmaciones) la **desenfocan**
  (blur) y la aclaran un poco, sin oscurecer. En modo oscuro, la capa del costado un poco más fuerte para que el panel
  siga separándose.
- **Cada línea de negocio** en una caja con borde visible (`--tq-borde`) y su nombre con el cuadrito de su color.
- **Campos visibles:** los «+ agregar» y los montos con borde y fondo de campo, texto en el color normal (el gris suave
  solo para el texto de ayuda), y los montos con formato de soles (`S/ 20,000.00`).
- **Fuentes desactivadas** legibles (texto suave pero con contraste 4.5:1) y su «más adelante» como etiqueta.
- **El asistente no tapa los paneles:** cuando se abre un panel deslizante o una ventana, se aparta al otro lado de la
  pantalla (o se esconde) y vuelve cuando se cierra.
- Revisa el contraste en claro y oscuro, de día y de noche (colores del ambiente).

**Alcance:** cruza módulos (todos los paneles deslizantes; asistente).

## 8. La agenda nueva: «Mes con actividades», sólida sobre la pantalla desenfocada

Reemplaza el diseño actual del calendario de la agenda del muelle. Diseño elegido: **«A · Mes con actividades»**
(lienzo: https://claude.ai/artifact/WnRUSBpfXP9sKgcy3DwKcz; copia en
`docs/vibe/pedidos/lienzos/agenda-nueva/Main.dc.html`, que manda).

- **Ventana sólida** (sin vidrio), centrada, con esquinas redondeadas y sombra; **la pantalla de atrás desenfocada**
  (blur) y un poco aclarada, sin oscurecer. Se cierra con «×», con Esc o con un clic fuera.
- **Encabezado:** «Noviembre 2026», flechas de mes anterior y siguiente, «Hoy», el selector **Año · Mes** y
  «+ Registrar actividad». Las vistas Semana y Lista del lienzo no se construyen por ahora.
- **Año → Mes:** se mantiene lo decidido (MV-025, MV-026): al abrir se ve el año y enseguida se acerca solo al mes
  actual; «Año» se aleja. En el año, cada día pintado con el color de sus actividades.
- **El mes:** cuadrícula de lunes a domingo con celdas grandes; en cada día, sus actividades como **pastillas** con
  hora y asunto (recortado con «…»), del color de su estado (vencida coral, por vencer ámbar, programada turquesa,
  hecha verde), y «+N más» si no entran. **Hoy** con su número en un círculo turquesa (y la flecha de MV-025 encima);
  el día elegido con borde azul. Días de otros meses vacíos y más apagados. Leyenda de colores al pie.
- **Detalle del día** (a la derecha, angosto, pegado): día de la semana y fecha grande, cuántas actividades y cuántas
  vencidas quedan de días anteriores; cada actividad con hora (del color de su estado), asunto, tipo y cliente u
  oportunidad, su estado como etiqueta y el botón **Hecha** (pide «Qué pasó», como hoy); al pie, «+ Actividad para este
  día». Al abrir, el día elegido es hoy.
- Lo que hace cada botón no cambia (MV-026).

**Alcance:** ficha (t1-agenda).

## 9. Convocatorias del SEACE: filtros por región y por entidad

Como en LicitaLab, se puede acotar por **región** y por **entidad**, en dos lugares:

- **En la configuración de la búsqueda** (qué entra a la bandeja), por línea de negocio:
  - **Regiones:** chips con los departamentos del Perú; por omisión «Todas». Si se eligen algunas, solo entra lo de
    esas regiones (la región de la entidad convocante, del dato del OECE). En los datos de demo, Videovigilancia y
    Redes con Lambayeque, Piura, La Libertad, Cajamarca y Amazonas; Software con «Todas».
  - **Entidades excluidas:** buscador de entidades (de lo guardado del OECE) para que nunca entren las de una entidad
    (por ejemplo, una con la que Techquk no trabaja). Opcional, vacío por omisión.
- **En la bandeja** (qué se ve ahora, sin cambiar la configuración): junto al selector de orden de la lista, dos filtros
  rápidos con buscador, **Región** y **Entidad**, con la cantidad de cada opción; se combinan con los chips de línea y
  de «ver». Al filtrar, la lista se reacomoda con la animación suave.
- **«¿Por qué no entró?»** también explica estas dos reglas: «Coincidió con *cámaras* (Videovigilancia), pero es de
  Cusco y esa línea solo busca en Lambayeque, Piura, La Libertad, Cajamarca y Amazonas», con el botón «Traer igual a la
  bandeja» y, según el caso, «Agregar Cusco a la línea» o «Quitar la entidad de las excluidas».
- Al cambiar regiones o entidades, la bandeja se vuelve a filtrar al instante con lo ya guardado (observación 7.4).

**Alcance:** ficha (t1-licitaciones, Convocatorias del SEACE).

## 10. «Ver en el SEACE» lleva a un formulario en vez de a la convocatoria

En el detalle de una convocatoria, «Ver en el SEACE» abre el buscador del SEACE (un formulario vacío) y no la
convocatoria. El buscador público del SEACE no tiene una dirección fija por proceso (funciona por sesión), así que
no se puede enlazar directo a una convocatoria desde fuera. Se resuelve así:

1. **Primero, investiga** si el portal de contrataciones abiertas del OECE (`contratacionesabiertas.oece.gob.pe`, el
   mismo de la API) tiene una página pública de detalle por proceso a partir de su código OCDS (`ocds-dgv273-…`). Si
   la tiene, el botón pasa a **«Ver en el OECE»** y abre esa página directo.
2. **Documentos directos:** los releases del OECE traen los documentos del proceso (bases, bases integradas, actas)
   con su enlace de descarga. Muéstralos en el detalle de la convocatoria como lista («Bases · PDF», «Bases integradas ·
   PDF»…) que abren el archivo directo. Así lo más importante no depende del buscador.
3. **Si de todos modos hay que ir al buscador del SEACE:** el botón **«Buscar en el SEACE»** copia la nomenclatura al
   portapapeles y abre el buscador, con un aviso corto «Nomenclatura copiada: pégala en el buscador del SEACE».
4. Lo mismo para los botones del SEACE de la postulación (observación 7.3: registrarse, presentar): abren el SEACE y
   copian la nomenclatura.

Si el punto 1 no se puede, quédate con 2 y 3 y anótalo en «Supuestos sin confirmar».

**Alcance:** ficha (t1-licitaciones).

## 11. La estrella de prioritaria dice por qué

En la bandeja de Convocatorias del SEACE (lista y detalle), la estrella dorada de las prioritarias, al pasar el
puntero (y al tocarla en pantallas táctiles), dice el motivo: «Prioritaria: valor referencial alto» (desde el umbral
de la configuración) o «Prioritaria: ya es cliente»; si son las dos, las dos.

**Alcance:** ficha (t1-licitaciones, Convocatorias del SEACE).

## 12. Ganadores anteriores: datos de demo y carga del histórico del OECE

«Ganadores anteriores en esta entidad» muestra los procesos pasados **parecidos** de esa misma entidad (misma línea de
negocio o palabras en común) con su ganador (Techquk resaltado), monto adjudicado y **descuento** sobre el valor
referencial. Hoy sale vacío casi siempre.

**Datos de demo:** que todas las entidades del demo con convocatorias tengan 2 o 3 procesos anteriores parecidos, con
ganadores variados (alguno de Techquk) y descuentos distintos.

**Carga del histórico** (una vez, con cuidado):

1. **Fuente:** las descargas masivas por año del portal de contrataciones abiertas del OECE
   (`contratacionesabiertas.oece.gob.pe/descargas`, JSON o CSV), no la API página por página.
2. **En el MVP, una carga chica:** solo **2025** y solo las **regiones** configuradas en las líneas de negocio
   (observación 8.9). La carga de 3 años completos queda para el repo original, en el servidor (anótalo en la ficha).
3. **En segundo plano**, como comando o tarea en cola, nunca desde una pantalla.
4. **Leyendo por partes:** el archivo se recorre proceso por proceso sin cargarlo entero en memoria; se guarda solo lo
   liviano (los mismos campos de la observación 7.4, con ganador y monto adjudicado), sin documentos.
5. **Sin duplicados y retomable:** cada proceso por su código OCDS; si ya existe, se actualiza solo si el dato es más
   nuevo. Guarda por dónde va: si se corta, sigue desde ahí.
6. **En silencio:** lo histórico **no dispara eventos ni avisos**, no entra a la bandeja ni se marca prioritario; solo
   alimenta «Ganadores anteriores», «¿Cuánto bajar?» (observación 7.3) y «Buscar en todo el SEACE».
7. **Primero un ensayo** que solo cuenta (procesos, peso, tiempo estimado) sin guardar; muéstrame esos números antes de
   la carga de verdad.
8. **Avance visible** en la caja Sistema («Importando histórico 2025 · 62 %») con pausar y reanudar.
9. **Verificación:** al final, el total por año contra el que informa el OECE, y 5 procesos al azar revisados contra el
   portal.

**Búsqueda rápida:** «Buscar en todo el SEACE» sobre todo lo guardado (con 3 años, unos 170 mil procesos) necesita
un índice de texto de PostgreSQL (en español, sin importar tildes) sobre objeto, entidad y nomenclatura, para
responder al instante. Guarda por lotes (por ejemplo de 500) en la carga del histórico.

Los datos descargados no van al repositorio (solo a la base).

**Alcance:** ficha (t1-licitaciones); la carga toca Sistema.

## 13. Los cambios de apariencia se aplican en todas las pestañas abiertas

Si la persona tiene el sistema abierto en varias pestañas y cambia algo que es de ella (no de la pantalla), hoy solo
cambia la pestaña donde lo hizo; las demás siguen como estaban hasta recargarlas. Debe aplicarse **al instante en todas
las pestañas abiertas** de esa persona en ese navegador, sin recargarlas:

- el tema (claro, oscuro, sistema) y lo que trae (los colores del ambiente);
- las preferencias de «Mis preferencias» (tamaño de letra, densidad, animaciones, apariencia según el día);
- el orden de las cajas de un proceso (observación 8.5) y los grupos de filtros abiertos o plegados (observación 8.3);
- el asistente en descanso o callado, y la posición del muelle (observación 6.2).

Cómo: al guardar uno de estos cambios, la pestaña avisa a las demás con un canal del navegador (`BroadcastChannel`, o el
evento `storage` como respaldo) y cada una lo aplica con el mismo evento que ya existe (`tq-apariencia`), sin pedir
la página de nuevo. Lo que es de la pantalla (la vista elegida, los filtros puestos, la etapa abierta) **no** se
copia a otras pestañas: cada una sigue donde está. En otro navegador o equipo, el cambio llega al recargar (ya se
guarda en la base).

**Alcance:** cruza módulos (apariencia y preferencias).

## 14. La «Línea de tiempo» no toma el tono del ambiente

Al atardecer la pantalla se ve rojiza (los colores del ambiente, observación 5.5), pero al pasar a la vista «Línea de
tiempo» de Oportunidades ese tono **no se aplica**: la vista conserva sus colores fijos. Lo mismo puede pasar en otras
pantallas hechas después de la 5.5 o con estilos propios.

- Haz que la Línea de tiempo use las variables del ambiente (`--tq-fondo`, `--tq-superficie`, `--tq-superficie-elevada`,
  `--tq-borde`, `--tq-texto`, `--tq-texto-suave` y los colores de franja con tinte) en su fondo, filas, encabezado del
  eje, filtros y paneles; los colores de estado (coral de vencida, rombo dorado, tramos de etapa) con el toque leve del
  ambiente, como los demás.
- **Revisa todas las pantallas** (en especial las nuevas: Mis oportunidades, Embudo, Línea de tiempo, Convocatorias,
  postulación, Campañas, Tareo, Contratos, Subcontratos, Personal asignado, la agenda) buscando colores escritos a mano
  (`#…`, `rgb(…)`, `white`, clases fijas como `bg-white`) que no sigan al ambiente, y pásalos a las variables. Dime en
  una línea cuáles corregiste.
- **Que no vuelva a pasar:** anota en `docs/convenciones/interfaz.md` que toda pantalla nueva usa las variables del
  ambiente y nunca colores fijos (salvo íconos de aplicación, logo e ilustraciones).
- Compruébalo con la hora de desarrollo al amanecer, a media tarde, al atardecer y de noche, en claro y en oscuro.

**Alcance:** cruza módulos (apariencia de todas las pantallas).
