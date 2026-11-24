# Observaciones 9 (novena tanda)

Pedido de Sebastián, desde el 2026-11-24. Cada punto se registra como decisión en `docs/traspaso/decisiones.md` y
actualiza su ficha.

## 1. «Mis oportunidades», el Embudo y Convocatorias con el mismo ancho que la portada de Proyectos

En Gerencia de proyectos (Proyectos) la pantalla tiene su columna de filtros y las tarjetas, pero el contenido **no
ocupa todo el ancho**: queda centrado con el ancho máximo normal de las páginas, y se ve parejo aunque se aleje o
acerque el zoom. En Oportunidades, la vista **«Mis oportunidades»** se estira a todo el ancho y cambia mucho con el
zoom. **Que se vea como la portada de Proyectos.**

- Las vistas **Mis oportunidades** (`TarjetasOportunidades`) y **Embudo de oportunidades** (`TableroOportunidades`)
  dejan de forzar el ancho completo (`getMaxContentWidth()` con `Width::Full`) y usan el **ancho por omisión** de las
  páginas, el mismo de Proyectos. En el Embudo, el embudo y su panel de la etapa se reparten ese ancho (revisa que las
  bandas, cifras y el panel sigan legibles).
- Copia la estructura de la portada de Proyectos: columna de filtros de 15 rem y cuadrícula de tarjetas con la misma
  regla de columnas y el mismo espacio entre tarjetas (`repeat(auto-fill, minmax(17rem, 1fr))` dentro de ese ancho, que
  da 3 columnas en una pantalla normal), en vez de la regla propia que tiene hoy.
- Compara las dos lado a lado al 67 %, 80 %, 100 % y 125 % de zoom: márgenes, ancho de los filtros, tamaño de las
  tarjetas y número de columnas deben coincidir.
- **Convocatorias del SEACE** (`BandejaConvocatorias`) también pasa al ancho normal: la lista se queda igual y el
  detalle gana un ancho cómodo de lectura (la tabla de ganadores compacta, el cronograma como una línea clara y los
  botones al alcance). Revisa que el muelle no tape columnas ni botones.
- **No cambian** la Línea de tiempo ni Campañas: se quedan como están. Al cambiar de vista hacia o
  desde la Línea de tiempo, el paso de un ancho a otro es suave, sin salto brusco.

**Alcance:** ficha (Oportunidades, vistas «Mis oportunidades» y «Embudo de oportunidades»; Convocatorias del SEACE).

## 2. Un solo botón «+ Nuevo» en Oportunidades

Hoy hay dos botones: «Nueva oportunidad» y «Por calificar» (un menú con «Nuevo lead» y «Registrar licitación»). El
nombre «Por calificar» en un botón confunde con la etapa y el filtro. Se juntan en **un solo botón «+ Nuevo»** con
menú:

1. **Oportunidad** (la principal): entra como Prospecto, como hoy «Nueva oportunidad».
2. **Contacto por calificar**: lo que hoy es «Nuevo lead»; pide además el **canal** (punto 3).
3. **Licitación a mano**: lo que hoy es «Registrar licitación»; entra a Por calificar como licitación por decidir.

Lo que hace cada opción no cambia. Igual en las tres vistas de Oportunidades.

**Alcance:** ficha (Oportunidades).

## 3. El canal de cada lead, y la landing page de Techquk como fuente

**Canal (origen) de cada lead.** Todo lead (lo que está en Por calificar sin ser licitación) lleva su **canal**:
Página web, WhatsApp, Instagram, Facebook, TikTok, LinkedIn, Referido, Feria o evento, Llamada, Correo, Otro. Es un
catálogo (Directorio y catálogos) que se puede ampliar, con su ícono. Un lead puede tener además una **campaña**
(la acción concreta: «Promo Navidad en Instagram»); canal y campaña no son lo mismo.

- En las tarjetas y el panel de Por calificar, el canal como etiqueta con su ícono.
- En los filtros de Mis oportunidades, un grupo nuevo **«Origen»** (plegable, como los demás).
- Los leads que ya entran solos llevan su canal: WhatsApp, el formulario de una campaña (el canal de esa campaña).

**La landing page como fuente.** Los formularios de contacto de la página web de Techquk crean el lead solo, con
canal «Página web», sin copiarlos a mano:

- Una **dirección de recepción** en el sistema (por ejemplo `POST /entrada/leads`) que recibe los campos del
  formulario (nombre, organización, teléfono, correo, mensaje) y crea el lead en Por calificar. La landing sigue igual
  y además envía ahí (su correo de aviso puede seguir).
- Si el enlace por el que llegó la persona trae marca de campaña (`?campania=…` o los `utm_source`, `utm_campaign`
  habituales), el lead queda asociado a esa campaña y su canal sale del `utm_source`.
- Protección contra spam: una clave secreta compartida con la landing, una verificación invisible (casilla trampa
  oculta) y un límite de envíos por minuto; lo rechazado no crea nada. Anota los atajos que tomes.
- No sé con qué está hecha la landing: deja en `docs/vibe/` una nota corta de cómo conectarla (qué dirección, qué
  campos, qué clave) para que Sebastián la configure; y una página de prueba en el sistema que envíe un formulario de
  ejemplo para verlo funcionar.
- Aviso en Mien: «Nuevo contacto desde la página web: [nombre] · [organización]».

**Alcance:** cruza módulos (Oportunidades, Campañas, Directorio y catálogos).

## 4. Vista «Canales» en Campañas

En la caja Campañas, junto a «Campañas» y «Mis clientes», una sección más: **«Canales»**. Mide cómo rinde cada medio.

- **Una tarjeta por canal** (los del catálogo que tengan leads), con su ícono y, para el periodo elegido (este mes por
  omisión; selector Mes · Trimestre · Año): leads, calificados (y %), ganados, monto ganado, margen de lo ganado y, si
  el canal tiene gasto (por sus campañas), **costo por lead** y **retorno**, con los mismos cálculos de Campañas.
- Orden por leads, de más a menos; selector para ordenar por ganado o por retorno.
- **Clic en una tarjeta:** lleva a Oportunidades, vista Mis oportunidades, con el filtro Etapa = Por calificar y
  Origen = ese canal ya puestos.
- Cifras arriba: leads del periodo, % calificados, canal que más trae, canal que más rinde.
- Datos de demo: leads de todos los canales con números distintos (uno que trae muchos pero califica poco, otro que
  trae pocos pero gana), para que la comparación diga algo.
- Lo que no se construye todavía (formularios de anuncios de Meta y de TikTok que entran solos) va a
  `docs/vibe/ideas-para-despues.md`; la entrada de leads queda lista para recibirlos (punto de extensión).

**Alcance:** ficha (Campañas).

## 5. Oportunidades: diez mejoras para ganar con margen y no perder negocios

Lienzo: https://claude.ai/artifact/FnTaQsFzuoXFbUb2vetshG (copia en `docs/vibe/pedidos/lienzos/oportunidades-mejoras/`;
la copia manda; cada pizarra lleva el número de la mejora). Regla de dónde va cada cosa: lo de **un negocio**, en su
tarjeta o su ficha; lo que **alguien tiene que hacer**, en Mien; lo que **mira todo junto**, en una vista nueva
«Resultados».

**En las tarjetas de Mis oportunidades** (`Main.dc.html`)

1. **Margen previsto:** si la oportunidad tiene cotización, la tarjeta muestra **una etiqueta** con el margen en soles
   y en %: **verde** («margen S/ 94,700 · 18.5 %») si pasa el mínimo, **coral** («margen bajo · 9.1 %») si no. **Sin
   barrita** (la del lienzo se descarta: su escala confundía). Sin cotización: «margen por calcular» en gris. Mismo
   cálculo de margen que la cotización.
   - **El mínimo es un parámetro que se cambia desde la pantalla de parámetros** (`crm.margen_minimo`, 12 % por
     omisión), con su explicación corta. Lo usan la etiqueta de la tarjeta, la aprobación de descuentos (2) y los
     colores del «¿Cuánto bajar?» de la postulación (observación 7.3). Al cambiarlo, todo eso se recalcula sin tocar
     código.
6. **Propuesta sin respuesta:** si lleva **7 días** (parámetro) en Propuesta enviada sin ninguna actividad registrada,
   la tarjeta muestra «N días sin respuesta desde que se envió la propuesta · Registrar llamada», y al responsable le
   llega el aviso en Mien.

**En la cotización** (`Aprobacion.dc.html`)

2. **Aprobación de descuentos:** si el precio deja el margen bajo el mínimo, la cotización pasa a **«Esperando
   aprobación»**: muestra costo, precio, descuento y margen, pide el **motivo** y el botón «Pedir aprobación»; no se
   puede emitir ni enviar hasta que alguien con el permiso nuevo (`crm.cotizaciones.aprobar-margen`) la apruebe o la
   rechace (con motivo). Volver a un precio con margen suficiente no necesita aprobación. Queda en el historial quién
   aprobó, cuándo y con qué margen. En el MVP, el usuario de desarrollo tiene el permiso.

**En Mien**

- La tarjeta **«Por aprobar»** con el descuento, el margen, quién lo pide y su motivo, con Aprobar y Rechazar.
- El aviso de propuesta sin respuesta (6).

**Al crear** («+ Nuevo», observación 9.2)

7. **¿Ya existe?:** al escribir cliente y nombre, si hay una oportunidad abierta del **mismo cliente** con nombre
   parecido (palabras en común) o de la misma línea en los últimos 90 días, aviso ámbar con su nombre, etapa y
   responsable, y los botones «Ver esa» y «Es otra, seguir». Avisa, no impide.

**Al marcar Perdida** (`Perdidas.dc.html`)

3. **Por qué se perdió:** el motivo de una lista (Precio, Plazo, Experiencia, Sin presupuesto, Técnica, Otro; catálogo
   ampliable), **quién ganó** (buscador de competidores, se puede crear uno nuevo) y **a qué precio aproximado**
   (muestra cuánto menos que nosotros). El motivo es obligatorio; ganador y precio, opcionales. Si es una licitación,
   ganador y monto pueden venir del OECE (observación 8.12).

**En la ficha de la oportunidad** (`Ficha.dc.html`)

10. **Resumen y siguiente paso (IA):** arriba, un resumen corto de dónde está el negocio y un siguiente paso sugerido,
    a partir de su historial (etapa, actividades, cotización, quién decide). Botón para programar ese paso como
    actividad; dice «sugerido · lo confirmas tú». En los datos de demo, resúmenes ya sugeridos (no se llama a la IA);
    en la pantalla, «Volver a sugerir» llama a la IA solo si está configurada.
8. **Quién decide:** las personas del cliente en este negocio, cada una con su cargo, su **papel** (Decide, Influye,
   Usa, Bloquea) y su postura (a favor, neutral, en contra, con nota). Se eligen de los contactos del cliente o se crea
   uno.
9. **Competidores:** quién más oferta en este negocio, con una nota y su **precio aproximado** («lo que se sabe, no
   confirmado»). El mismo catálogo de competidores del punto 3.

**En la vista nueva «Resultados»** (cuarta vista: Mis oportunidades · Embudo · Línea de tiempo · Resultados;
`Perdidas.dc.html` y `Pronostico.dc.html`), con selector de periodo:

- **¿Por qué perdemos?:** % de cada motivo (barras), cantidad y monto perdido; **contra quién perdemos** (competidor,
  veces y cuánto más barato en promedio).
4. **Pronóstico de cierres:** por mes de cierre esperado, lo **ponderado** (monto × probabilidad de la etapa) y lo que
   entraría si se ganara todo; los próximos 6 meses.
5. **Metas por ejecutivo:** cada ejecutivo con su meta del periodo (se cargan en una pantalla simple de metas: persona,
   periodo, monto) y lo ganado contra ella, en barra verde (cumplida), ámbar (50 % o más) o coral (menos).

Datos de demo: que se vea cada caso (tarjetas con margen bueno, bajo y por calcular; una propuesta 7 días sin respuesta;
una cotización esperando aprobación con su tarjeta en Mien; un duplicado al crear; perdidas con motivos y ganadores
variados para que el reporte diga algo; metas para tres ejecutivos; una ficha con quién decide, competidores y resumen).

**Alcance:** cruza módulos (Oportunidades, Cotizaciones, Mien, Directorio y catálogos por motivos y competidores).

## 6. Convocatorias del SEACE: configuración inicial guiada de la búsqueda

Hoy las líneas de negocio, palabras, regiones y montos vienen armados en los datos de demo; en una instalación real la
bandeja empezaría vacía. Se agrega un **asistente de configuración inicial**.

**Cuándo aparece:** al entrar a Convocatorias del SEACE si no hay ninguna línea configurada («Configura qué
convocatorias le interesan a Techquk», con un botón «Empezar»); y se puede volver a abrir desde «Búsqueda»
(«Volver a configurar con el asistente»). Es una ventana al medio (fondo desenfocado, observación 8.7) con pasos y
barra de avance; se puede volver atrás sin perder lo elegido.

1. **¿Qué vende la empresa?** Rubros para marcar (Videovigilancia, Redes y cableado, Software, Control de acceso,
   Centro de datos, Energía y UPS, Telecomunicaciones, Alarmas e incendios; y «+ Otro»). Cada rubro trae sus
   **palabras clave y negativas sugeridas** (un catálogo de rubros con sus palabras, ampliable) que se pueden quitar o
   agregar. Cada rubro marcado será una línea de negocio.
2. **¿Dónde trabaja?** Regiones por línea (o una sola elección para todas), con «Todo el Perú».
3. **¿Desde qué monto?** Mínimo por línea y el umbral de prioritaria.
4. **Vista previa:** sobre lo ya guardado del OECE, «Con esta configuración, en el último mes habrían entrado
   **N convocatorias**» (por línea) y las 5 primeras de ejemplo con sus palabras resaltadas. Si salen demasiadas o muy
   pocas, una línea de ayuda dice qué ajustar.
5. **Guardar:** crea o reemplaza las líneas y la bandeja se vuelve a filtrar al instante.

**De la empresa, no de cada persona:**

- La configuración de la búsqueda es una sola para todos. Solo quien tenga el permiso nuevo
  `crm.convocatorias.configurar-busqueda` puede cambiarla (en el MVP, el usuario de desarrollo); los demás la ven en
  modo lectura.
- **Cada cambio queda registrado:** quién, cuándo y qué cambió (palabra agregada o quitada, región, monto), visible
  como historial al pie del panel «Búsqueda».

**Sugerencias con IA (opcional):** «Sugerir palabras» mira los proyectos que Techquk ya ganó (nombres y partidas) y
propone palabras o rubros que faltan («Ganaste 3 proyectos de *sistemas de alarma*; ¿agrego el rubro?»); una persona
confirma cada una. En los datos de demo, sugerencias ya hechas; en la pantalla, llama a la IA solo si está configurada.

**Datos de demo:** dejar la configuración armada como hoy, y una forma de ver el asistente desde cero (por ejemplo, el
botón «Volver a configurar con el asistente» precarga lo que hay).

**Alcance:** ficha (t1-licitaciones, Convocatorias del SEACE); el permiso nuevo y el historial de cambios.

## 7. Convocatorias del SEACE solo con datos reales, y el resultado de cada lectura a la vista

Decisión de Sebastián: en Convocatorias del SEACE **no se inventan datos**. Es una excepción a la regla de los datos
de demostración, porque esta pantalla tiene una fuente real y pública (el OECE).

- **Quita del demo** las convocatorias inventadas (las del `BandejaCasosSeeder`, `GanadoresCasosSeeder` y lo que haya en
  `DemoSeeder` y `CarteraDemo` para la bandeja y los ganadores anteriores) y lo que dependa solo de ellas. Antes de
  borrar, dime qué otras pantallas usan esas convocatorias (por ejemplo, licitaciones de demo en Oportunidades o la
  ruta de la postulación) y si alguna se queda vacía; esas siguen con sus propios datos de demo, sin convocatorias
  inventadas en la bandeja.
- La bandeja, «Buscar en todo el SEACE», «Ganadores anteriores» y el «¿Cuánto bajar?» se llenan **solo con lo leído
  del OECE** (lectura de cada hora, «Leer ahora» y la carga del histórico de la observación 8.12). Si todavía no hay
  nada, la bandeja vacía lo dice en una línea con el botón «Leer ahora».
- **Resultado de cada lectura**, junto a «Leído hace…»: «**X leídas · Y entraron a tu bandeja**» (de la última
  lectura). Si el OECE no respondió: en coral, «El OECE no respondió · reintentar». Al pasar el puntero, el detalle:
  cuántas eran nuevas, cuántas actualizadas, cuántas quedaron fuera y por qué (palabras, región, monto), y la hora.
- La configuración de la búsqueda (líneas, palabras, regiones, montos) sí puede venir sembrada como hoy, porque no son
  datos inventados sino la configuración de Techquk.
- Comprueba con la app levantada que una lectura real traiga procesos con entidades reales y que el contador diga la
  verdad. Si el OECE no responde desde tu entorno, dímelo con el error.

**Alcance:** ficha (t1-licitaciones, Convocatorias del SEACE); toca los seeders de demo.

## 8. El modo desarrollo: que se pueda prender, y un interruptor de datos de demo

**Por qué no aparece el modo desarrollo.** Necesita tres llaves a la vez (`ModoDesarrollo::disponible()`):
`APP_ENV=local`, `APP_DEBUG=true` y `TECHQUK_MODO_DESARROLLO=true`. En `.env.example` la tercera viene **comentada**
(`# TECHQUK_MODO_DESARROLLO=true`), así que el `.env` de Sebastián, copiado de ahí, no la tiene. Sebastián la agrega a
su `.env` y corre `php artisan config:clear`; con eso, en el menú de usuario aparece «Modo desarrollo».

- En este repo (modo rápido) deja `TECHQUK_MODO_DESARROLLO=true` **sin comentar** en `.env.example`, con su nota de que
  nunca va al servidor, y anótalo en el README de arranque.
- Si con las tres llaves puestas sigue sin aparecer, revisa por qué (configuración en caché, el elemento del menú,
  `config/nucleo.php`) y dime la causa.

**Interruptor «Datos de demo» en la barra de desarrollo.** Con el modo desarrollo prendido, un interruptor **Datos de
demo: encendidos / apagados**:

- **Encendidos:** el sistema se ve como hoy, con los datos de demostración.
- **Apagados:** el sistema se ve como quedaría en una instalación real: sin nada inventado, solo la configuración
  (catálogos, parámetros, la configuración de la búsqueda del SEACE, usuarios) y lo que venga de fuentes reales (el
  OECE). Sirve para ver las pantallas vacías, el asistente de configuración inicial (observación 9.6) y lo real.
- **Vale para todo el sistema, sin excepción:** con el interruptor apagado no aparece **ningún dato inventado en
  ninguna parte**: ni en las listas y tarjetas, ni en fichas, cifras, totales, gráficos, embudo, reportes, Mien,
  avisos, agenda, búsquedas, selectores, exportaciones a Excel, ni en lo que calcula el sistema a partir de ellos (un
  margen, un pronóstico o un retorno no puede sumar datos de demo). Revisa módulo por módulo y dime en una lista
  corta que cada uno quedó cubierto.
- El estado del interruptor se ve siempre en la barra de desarrollo (por ejemplo, «Demo: apagado») para no confundir
  lo que se está mirando.
- **No debe borrar nada ni obligar a reconstruir la base** al cambiarlo, y lo que Sebastián cargó a mano debe
  conservarse. Propón la forma antes de construirla (por ejemplo, dos bases, una con demo y otra limpia, y el
  interruptor cambia cuál usa la sesión; o marcar lo sembrado como demo y ocultarlo), con sus ventajas, y espera mi
  elección.
- Convocatorias del SEACE no tiene datos inventados en ninguno de los dos estados (observación 9.7).

**Alcance:** cruza módulos (núcleo: modo desarrollo; datos de demo de todos los módulos).

## 9. Campañas y Canales: contactos nuevos o ya registrados, y barras que dicen «N de X»

En las tarjetas de Campañas (y igual en las de Canales, observación 9.4), el embudo de cada una queda así:

- **Contactos, sin barra:** una línea «**15 contactos llegaron** · 11 nuevos · 4 ya registrados».
  - **Nuevos:** el sistema no los conocía. **Ya registrados:** ya existían como cliente, contacto de una oportunidad o
    lead anterior. Se reconocen por **mismo teléfono, mismo correo o mismo RUC** de la organización.
  - Un contacto ya registrado **no se duplica**: se une al que existe y queda anotado que también llegó por esta
    campaña (o canal). Si ya tenía una oportunidad abierta, no se crea otra: se anota en su historial.
- **Oportunidades, con barra:** la barra mide oportunidades sobre contactos y lleva el texto «**3 de 15**» (20 %).
- **Ganadas, con barra:** la barra mide ganadas sobre oportunidades y lleva el texto «**1 de 3**» (33 %).
- Al pasar el puntero por «ya registrados», la lista corta de quiénes eran (nombre y si ya eran cliente).
- El retorno y el costo por contacto siguen igual; además, «costo por contacto nuevo» al pasar el puntero, porque es
  lo que la campaña de verdad consiguió.
- Datos de demo: campañas con mezcla de nuevos y ya registrados (una feria con muchos ya registrados, para que se vea
  la diferencia).

**Alcance:** ficha (Campañas); el reconocimiento de contactos ya registrados toca Directorio (contactos y cuentas).

## 10. Campañas: ficha de cada campaña, atajos en la tarjeta y «Registrar gasto» al medio

**Clic en la tarjeta → la ficha de la campaña** (una página propia, como la ficha de una oportunidad o de un
proyecto, con «volver» a la lista donde se estaba). Lleva:

- Cabecera: nombre, tipo, canal, fechas, responsable, estado (planificada, en curso, terminada) y sus botones
  (Formulario y QR, Registrar gasto, Editar).
- Las cifras de la tarjeta en grande (invertido contra presupuesto, contactos nuevos y ya registrados, oportunidades,
  ganadas, ganado, margen, retorno, costo por contacto y por contacto nuevo).
- Pestañas: **Contactos** (todos los que llegaron, con nuevo o ya registrado, a quién se unió, fecha y en qué quedó:
  por calificar, oportunidad, descartado); **Oportunidades** (las nacidas de la campaña, con etapa, monto y margen);
  **Gastos** (cada gasto con fecha, concepto, monto, su imputación y comprobante, y la suma contra el presupuesto);
  **Seguimiento** (para campañas a clientes: los pasos y sus tareas en Mien, hechas o pendientes); **Actividad** (el
  historial: quién creó, editó, registró gastos).

**Atajos en cada tarjeta** (sin entrar a la ficha), cada uno abre una **ventana al medio** (no un panel lateral;
decisión de Sebastián), con la pantalla de atrás desenfocada como las demás ventanas del medio (observación 8.7), su
título («Contactos de Expo Seguridad Norte · 15») y «×» o Esc para cerrar:

- **Contactos** («15 contactos llegaron»): la lista con nombre, organización, teléfono, correo, nuevo o ya
  registrado y en qué quedó; clic en uno abre su ficha de contacto.
- **Oportunidades** («3 de 15»): la lista con etapa (gema), monto, margen y responsable; clic abre la oportunidad.
- **Ganadas** («1 de 3»): igual, solo las ganadas, con lo ganado y el margen.
- **Presupuesto y gasto** («S/ 3,420.00 / S/ 3,000.00»): el detalle de los gastos y lo que queda (o lo que se pasó).

Los atajos se ven como enlaces (al pasar el puntero se subrayan) para que se note que se pueden pulsar; el clic en el
resto de la tarjeta abre la ficha.

**«Registrar gasto» en una ventana al medio** (no en un panel lateral): fondo desenfocado (observación 8.7), con
fecha, concepto, monto, comprobante (archivo opcional) y el **selector de imputación** que ya existe (gasto directo del
centro de costo de Comercial, vinculado a la campaña). Al guardar, la tarjeta y la ficha se actualizan sin recargar.

**Alcance:** ficha (Campañas).

## 11. Retorno real y retorno esperado, calculados al mirar

El retorno de una campaña (y de un canal) deja de mostrar «0×» mientras sus oportunidades siguen abiertas:

- **Retorno esperado** (mientras no hay ganadas, o por la parte que sigue abierta): suma, de cada oportunidad abierta
  de la campaña, **margen previsto × probabilidad de su etapa** (si no tiene cotización, monto × probabilidad × el
  margen mínimo del parámetro, anotado como supuesto), dividido entre lo gastado. Se muestra en gris con la palabra
  **«esperado»**: «6.7× esperado». Al pasar el puntero, el detalle por oportunidad (etapa, probabilidad, margen
  previsto, lo que aporta).
- **Retorno real** (de lo ganado): margen de los proyectos ganados ÷ lo gastado, en verde (1× o más) o coral (menos).
  Mientras el proyecto está en ejecución, usa su **margen actual** (el mismo cálculo que muestra el proyecto); al
  cerrarse, el final.
- **Las dos a la vez** si hay ganadas y abiertas: «9.1× · + 2.3× esperado».
- Sin gasto registrado: «sin gasto» (no se divide entre cero).
- **Se calcula al mirar**, con los datos de ese momento, usando los servicios de Oportunidades, Proyectos y Finanzas
  (no se guarda ni se recalcula por horario). Igual las cifras de arriba de Campañas y Canales y la ficha de la
  campaña. Si alguna pantalla tarda más de lo normal por esto, dímelo antes de guardarlo en caché.
- Junto a la cifra, un texto corto con cómo leerlo al pasar el puntero: «Por cada S/ 1 invertido volvieron S/ 9.10 de
  ganancia».

**Alcance:** ficha (Campañas); lee Oportunidades y Proyectos por sus servicios.

## 12. «Mis clientes» pasa a llamarse «Volver a vender»

En la caja Campañas, la sección «Mis clientes» (observación 7.6) se llama **«Volver a vender»**: dice mejor para qué
es (venderle de nuevo a quien ya compró). Cambia el chip, el título, los textos, la ruta si lleva el nombre (con
redirección desde la vieja) y las fichas; el tipo de campaña que nace de ahí se sigue llamando «Clientes». Orden de los
chips: Campañas · Canales · Volver a vender.

**Alcance:** ficha (Campañas).

## 13. Convocatorias del SEACE: comprobar que todo funciona de punta a punta

Al terminar la tanda (después de la 9.7), **prueba con la app levantada y datos reales del OECE** todo el circuito de
Convocatorias del SEACE, paso por paso, y entrégame una lista corta con cada punto marcado **funciona / no funciona**
(y, si no, qué pasaba y cómo lo arreglaste). Lo que no se pueda probar desde tu entorno (por ejemplo, si el OECE no
responde), dilo con el error exacto.

1. **Lectura real:** «Leer ahora» trae procesos reales del OECE (entidades reales), el contador «X leídas · Y entraron»
   dice la verdad y «Leído hace…» se actualiza. La lectura de cada hora corre sola (el programador de tareas está
   funcionando en el entorno de desarrollo; si hace falta un comando para levantarlo, dímelo).
2. **Solo lo nuevo:** una segunda lectura no duplica nada y se detiene donde quedó la anterior; un proceso con cambios
   se actualiza y anota qué cambió.
3. **Filtros de la búsqueda:** una convocatoria entra por palabra clave; una palabra negativa la saca; el monto mínimo,
   las regiones y las entidades excluidas funcionan; una convocatoria que coincide con dos líneas aparece en las dos.
   Cambiar algo de la configuración vuelve a filtrar al instante.
4. **«¿Por qué no entró?»** explica bien cada caso (palabra negativa, región, monto, entidad, sin coincidencia) y sus
   botones hacen lo que dicen.
5. **Asistente de configuración inicial:** se recorre completo, la vista previa cuenta bien y guardar deja la bandeja
   llena.
6. **Bandeja:** chips de línea y de «ver» (Nuevas, Prioritarias, Mías, Por vencer, Registradas, Descartadas), filtros de
   región y entidad, orden, buscador y «Buscar en todo el SEACE», flechas ↑ ↓, resaltado de palabras, estrella de
   prioritaria con su motivo.
7. **Detalle:** cronograma correcto, documentos del proceso que abren el archivo, «Ver en el OECE» o «Buscar en el
   SEACE» (copia la nomenclatura), ganadores anteriores con datos reales.
8. **Acciones:** Asignar responsable, etiquetas, Descartar con motivo y recuperar desde «Descartadas», **Registrar como
   oportunidad** (entra a Oportunidades como «Por calificar» con su cronograma) y su ruta de postulación.
9. **Avisos en Mien:** convocatoria nueva que entra, cambio de cronograma de una registrada.
10. **Histórico:** el ensayo cuenta y muestra los números (sin cargar de verdad si no lo apruebo).
11. **Con los datos de demo apagados y encendidos** (9.8): la bandeja muestra lo mismo (solo lo real).
12. **Sin errores:** ninguna página de Convocatorias da error 500 y no quedan errores nuevos en `storage/logs/laravel.log`.

**Alcance:** ficha (t1-licitaciones, Convocatorias del SEACE).
