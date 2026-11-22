# Observaciones 6 (sexta tanda)

Pedido de Sebastián, 2026-11-22. Cada punto se registra como decisión en `docs/traspaso/decisiones.md` y actualiza su
ficha.

## 1. El Embudo de oportunidades muestra las cifras dos veces

En la vista «Embudo de oportunidades» aparecen dos filas de cifras: arriba, cinco tarjetas con título y línea divisoria
(Embudo abierto, Ponderado, Cierran este mes, Atascadas, Cierre estimado vencido) y, debajo, cuatro tarjetas en
mayúsculas (EMBUDO ABIERTO, PONDERADO, CIERRAN ESTE MES, ATASCADAS). **Se queda solo la fila de arriba**, la de cinco;
la de abajo se borra. Revisa que las otras vistas de Oportunidades no tengan la misma duplicación.

**Alcance:** ficha (Oportunidades, vista «Embudo de oportunidades»).

## 2. El muelle se puede mover

El muelle (la barra de herramientas flotante: pantalla completa, Mien, Excel, WhatsApp, agenda, tijeras…) se puede
arrastrar a otro lugar de la pantalla, igual que el asistente.

- Se agarra desde cualquier parte del muelle que no sea un ícono (o manteniendo presionado un ícono un instante); un
  clic normal en un ícono sigue abriendo su herramienta.
- Al soltarlo, **se pega al borde más cercano** (derecho, izquierdo, arriba o abajo) con una animación corta; en los
  bordes de arriba y abajo se pone horizontal. Nunca queda tapando la barra lateral ni el encabezado.
- **Recuerda dónde lo dejó** cada persona, en ese navegador; al volver a entrar aparece ahí.
- Doble clic en la zona de agarre lo devuelve a su lugar de siempre.
- Lo que se abre desde el muelle (la agenda, los globos) sale hacia el lado de la pantalla con más espacio.

**Alcance:** ficha (marco de la aplicación, muelle).

**Posición inicial:** el asistente se queda donde aparece hoy (abajo a la derecha). El muelle, en cambio, baja su
posición por defecto un poco **más arriba**, lo justo para que nunca se superpongan.

## 3. El asistente no funciona

Hoy el personaje aparece, pero **nada de su comportamiento funciona**:

- un clic no abre el chat;
- no se mueve solo (ni va hacia lo que se mira, ni se aparta al escribir, ni cambia de meta);
- no muestra sus globos con frases;
- el clic derecho no abre el menú de Descansar / Callar.

Como falla todo a la vez, lo más probable es que su código no esté corriendo (un error de JavaScript, o que se perdió
al pasar a la navegación sin recargar la página, que cambia el contenido pero no vuelve a ejecutar los scripts).
Revísalo con la consola del navegador, arréglalo y comprueba cada punto: clic → chat; paseo con intención; globos
(para probar, deja una forma de forzar una frase sin esperar las reglas de cuándo habla); clic derecho → menú; y que
siga funcionando después de navegar entre varias pantallas sin recargar. El comportamiento esperado es el de su ficha
(`op-asistente`), que no cambia.

**Alcance:** ficha (asistente).

## 4. La carita del asistente, más realista y con gestos naturales

La carita «n_n» es la referencia y gusta, pero se quiere **más realista**: con volumen, luz y sombra, ojos con brillo,
cejas y boca, en vez de un dibujo plano. Y que **cambie sus facciones de forma natural**: parpadea a ratos (no a ritmo
fijo), los ojos siguen el puntero, sonríe al saludar, mueve la boca al hablar, levanta las cejas al escuchar o tener
una duda, entrecierra los ojos al dormir; cada cambio pasa suave de una cara a otra, nunca de golpe. Debe seguir
reconociéndose como «n_n» cuando está tranquilo.

**Diseño elegido: «A · Gota de miel»** (lienzo: https://claude.ai/artifact/Vg2LezYj71LX1ErpLwfdWk; copia en
`docs/vibe/pedidos/lienzos/asistente/A.dc.html`, que manda): esfera dorada con luz arriba a la izquierda, brillo
especular, sombra suave en el piso, ojos oscuros con punto de luz, mejillas leves y la estrellita en la cabeza. Sus
cinco gestos (tranquilo «n_n», saluda, habla, escucha o duda, duerme) y las animaciones (respira, parpadeo irregular,
ojos que miran, boca que habla, mano que saluda, «z z z») están en el lienzo. A su tamaño real (64 × 72 px) debe seguir
leyéndose bien en modo claro y oscuro.

**Se vuelve ámbar con el trabajo seguido:** después de **2 horas de actividad sin pausa**, la miel se va oscureciendo
poco a poco hacia el ámbar de la propuesta C (`lienzos/asistente/C.dc.html`: naranja más profundo, luz por dentro,
facetas), a lo largo de unos **30 minutos**, como un aviso suave de que toca descansar. Si la persona se ausenta
**15 minutos o más** (sin teclado ni mouse, o con la pestaña oculta), vuelve a miel. El cambio es continuo (una mezcla
gradual de colores), nunca un salto. Con el ámbar completo, de vez en cuando puede decir una frase invitando a una
pausa, respetando sus reglas de cuándo habla.

**Alcance:** ficha (asistente).

## 5. Doble clic en la estrella de Mien de la barra lateral abre la página de Mien

Un clic en la estrella sigue abriendo el globo con el resumen de pendientes, y Ctrl + clic sigue abriendo Mien en una
pestaña nueva. **Doble clic lleva a la página de Mien** (donde está todo), en la misma pestaña y sin recargar la página
entera. El primer clic del doble clic no debe dejar el globo abierto ni hacerlo parpadear: espera un instante para
saber si viene un segundo clic.

**Alcance:** ficha (Mien / barra lateral).

## 6. «Leer ahora del OECE» con la dirección real de la API

Al pulsar «Leer ahora del OECE» en Convocatorias SEACE sale el aviso de que falta `OECE_OCDS_URL`. La dirección vigente
de la API de contrataciones abiertas del OECE (la misma que usa Kingfisher Collect, la herramienta oficial de Open
Contracting Partnership) es:

`https://contratacionesabiertas.oece.gob.pe/api/v1/releasesAfter`

- Sebastián la pone en su `.env` como `OECE_OCDS_URL=https://contratacionesabiertas.oece.gob.pe/api/v1/releasesAfter`.
  Actualiza también `.env.example` con esa dirección como valor de ejemplo (y quita «confirmar la dirección vigente»).
- **La API no acepta `since`.** Se pide con `format=json&order=desc` (lo más reciente primero) y devuelve un paquete de
  releases con `links.next`, que trae el parámetro `searchAfter` para la página siguiente. Cambia `sincronizar()`
  para: pedir la primera página con esos parámetros; importar sus releases; seguir `links.next` mientras los releases
  de la página sigan siendo más nuevos que la fecha «desde» (por defecto, 3 días atrás), con un tope de páginas (por
  ejemplo 20) para no quedarse leyendo sin fin. Ojo: si a `Http::get()` se le pasan parámetros, Guzzle reemplaza los
  que ya trae la dirección; arma la dirección completa a mano o pasa todos los parámetros juntos.
- Al terminar, el aviso dice cuántas leyó, cuántas entraron y cuántas quedaron fuera por el filtro, como hoy. Si el
  OECE no responde, el mensaje de hoy se queda.
- Prueba con la app levantada que «Leer ahora» traiga convocatorias reales recientes y que el filtro de palabras las
  deje pasar o no. Si la respuesta tiene otra forma (por ejemplo `records` en vez de `releases`), el lector ya acepta
  las dos.
- En la ficha `t1-licitaciones`, el supuesto «la dirección del servicio del OECE no se confirmó» pasa a resuelto con
  esta dirección.

**Alcance:** ficha (Licitaciones, Convocatorias SEACE).

## 7. «Personal asignado» en tarjetas de dos columnas, con foto de perfil

Hoy es una tabla (una fila por persona y una columna por cada una de las próximas 4 semanas). Pasa a **tarjetas en dos
columnas** (una columna en pantallas angostas), una por persona activa:

- **Arriba de la tarjeta:** la **foto de perfil** en círculo, el nombre, «categoría · vínculo» y la habilitación SCTR
  como etiqueta (en coral si está vencida o no tiene).
- **Debajo:** sus **4 semanas** (esta y las tres siguientes) como barritas de ocupación con su porcentaje y la
  etiqueta «Sem NN · dd/mm»; gris si está en 0 %, en el color normal hasta 100 %, en coral con «sobreasignado» si pasa
  del 100 %. Los números salen del mismo cálculo de hoy.
- Arriba de las tarjetas, el buscador por nombre, como en las demás cajas. Al filtrar, las tarjetas se reacomodan con
  la misma animación suave de las otras vistas.
- Un clic en la tarjeta abre el panel deslizante con el detalle de la persona: en qué proyectos está asignada cada
  semana y con qué porcentaje (si hoy no existe ese detalle, muestra lo que ya hay y anótalo como supuesto).

**Foto de perfil y avatares por defecto:**

- El colaborador gana dos datos: **sexo** (Hombre / Mujer, obligatorio) y **foto** (opcional, se sube desde su ficha,
  se recorta cuadrada).
- Sin foto, se muestra un **avatar por defecto**: uno de hombre y uno de mujer, ilustrados (silueta simple de busto con
  pelo, en tonos neutros que funcionen en modo claro y oscuro y sigan al ambiente), del mismo estilo entre sí. Nunca
  fotos de personas reales.
- El mismo avatar o foto se usa donde aparezca esa persona en el sistema (si hoy hay iniciales en algún lado, cámbialas
  por esto).
- Datos de demo: todas las personas con su sexo; la mitad, más o menos, con una foto inventada (ilustración o imagen
  generada, nunca de una persona real) y el resto con el avatar por defecto, para que se vean los dos casos.

**Alcance:** ficha (Personal / Gerencia de proyectos, «Personal asignado»); el dato de sexo y la foto cruzan módulos
(Personas).

## 8. Gerencia de proyectos: «Proyectos» al centro, y sin «Volver al orden original»

- **Nuevo orden por defecto de las cajas de Gerencia de proyectos:** Contratos · Subcontratos · **Proyectos** ·
  Personal asignado · Tareo. Proyectos queda en el medio y es la que se abre al entrar; a la izquierda lo contractual
  y a la derecha lo de las personas. Cambia el orden original del sistema en `Proceso::cajas()` (no solo el de una
  persona). Como lo guardado por persona manda sobre el original, borra el orden guardado de este proceso en la base
  de demo para que se vea el nuevo.
- **Se quita el botón «Volver al orden original»** en todos los procesos (y lo que solo él usa). Reordenar arrastrando
  sigue igual y se sigue guardando por persona.

**Alcance:** cruza módulos (navegación de todos los procesos).

## 9. Tareo: atajos para no llenar a mano lo que ya se sabe

**La jornada de cada persona.** Al registrar o editar un colaborador se define su **jornada**: qué días trabaja
(chips Lu Ma Mi Ju Vi Sá Do) y cuántas horas cada día. Viene propuesta según el vínculo (planilla: Lu a Vi 8 h y Sá
4 h = 48 h, la jornada máxima legal; eventual: vacía, se llena al contratarlo) y se puede cambiar. Se ve en su ficha y
en su tarjeta de «Personal asignado».

**El tareo se rellena solo.** Al abrir una semana nueva de un proyecto, el sistema ya trae una línea por cada persona
**asignada a ese proyecto esa semana**, con las horas de su jornada multiplicadas por su dedicación (60 % de 48 h →
28.8 h, repartidas por igual en sus días), en la partida donde tareó la semana anterior en ese proyecto (si no tiene,
la línea queda pidiendo la partida). Reglas:

- Los **feriados** de Perú de esa semana van en 0.
- A quien tiene el **SCTR vencido** no se le rellena: aparece la línea en coral con el aviso, para que se vea que falta.
- Las líneas rellenadas se ven más suaves, con la marca «de la jornada», hasta que alguien las toca o se cierra la
  semana: así se distingue lo propuesto de lo revisado. Corregir es lo de siempre (faltas, horas extra, cambiar de
  partida o repartir en dos).
- Cerrar la semana funciona igual que hoy y toma lo que haya, rellenado o corregido.

**Otros atajos en la barra del tareo:**

- **Copiar la semana anterior:** trae las mismas personas, partidas y horas (sin las de feriados ni SCTR vencido).
- **Rellenar con la jornada:** vuelve a proponer las líneas que nadie tocó (útil si cambió una asignación).
- **Llenar la fila:** en una línea, escribir las horas de un día y repetirlas en los demás días de su jornada.

**Supuestos a confirmar:** que el relleno use la dedicación de la asignación; que la partida sea la de la semana
anterior; los 48 h de planilla. Anótalos en «Supuestos sin confirmar» de la ficha.

**Alcance:** ficha (t4-personal-tareo); la jornada es un dato nuevo del colaborador.

## 10. Tareo: la vista nueva

Sebastián aprobó todas estas mejoras. Van junto con los atajos del punto 9 y respetan los patrones de pantalla del
sistema (colores de Gerencia de proyectos, cajas, panel deslizante, animaciones suaves, sin textos explicativos).

1. **Cuadrícula tipo hoja de cálculo.** Filas = persona y partida; columnas = Lu a Sá (Do si alguien lo trabaja), total
   de horas y costo. Se escribe directo en la celda (clic y escribir; Tab y Enter avanzan; Esc deshace la celda) y se
   guarda sola al salir de la celda, sin recargar. Abajo, el total de cada día y el de la semana. Agregar una persona o
   una partida es una fila nueva al final con su selector. El formulario «Agregar horas» se va. Con la semana cerrada,
   la cuadrícula es solo lectura.
2. **Foto o avatar** de cada persona en su fila (los del punto 7), con su categoría debajo del nombre.
3. **Tira de semanas del mes** arriba: ✓ las cerradas, resaltada la abierta, en coral las que ya pasaron sin cerrar;
   flechas ← → para pasar de semana y el **proyecto como chip** (clic para cambiarlo, con buscador).
4. **Cuatro cifras arriba:** horas de la semana; costo de la semana; **mano de obra gastada contra presupuestada** del
   proyecto (barra, con el porcentaje; coral si pasa del 100 %), del mismo cálculo que usa el resto del sistema; y
   **personas tareadas sobre asignadas** («5 de 6», coral si falta alguien).
5. **Avisos dentro de la cuadrícula:** feriados sombreados con su nombre al pasar el puntero; más de 8 h en un día,
   marcadas como «horas extra»; día en blanco de alguien asignado en un día de su jornada, con una señal «¿faltó?»;
   SCTR vencido, fila en coral y sin poder escribir.
6. **Ver por persona / por partida.** Un selector cambia la agrupación: por partida muestra horas y costo de cada
   partida con su barra contra lo presupuestado de mano de obra de esa partida. Al cambiar, las filas se reacomodan con
   la animación suave.
7. **La persona en todos sus proyectos.** Junto al total de cada persona, cuánto suma esa semana sumando todos sus
   proyectos («44 de 48 h»); si pasa su jornada, coral. Al pasar el puntero, el detalle por proyecto.
8. **Cerrar con resumen.** «Cerrar semana» abre primero un panel con lo que se cargará a cada partida (horas y monto) y
   los avisos pendientes (faltas, horas extra, SCTR); se confirma ahí. Lo que hace al cerrar no cambia.
9. **Asistencia rápida desde el celular.** En pantalla angosta, la vista del día: una tarjeta por persona asignada con
   su foto y dos botones grandes, **Vino / Faltó**. «Vino» pone las horas de su jornada en la partida de su línea
   (como el relleno del punto 9); «Faltó» deja ese día en 0 con la marca «faltó». El detalle por partida se ajusta
   después en la cuadrícula. Se llega con el mismo enlace del tareo y un selector Semana / Hoy.

Datos de demo: que se vean todos los casos (una semana cerrada, una abierta con líneas «de la jornada» y otras
corregidas, una semana pasada sin cerrar, un feriado, horas extra, una falta, un SCTR vencido, una persona en dos
proyectos que pasa su jornada, y una partida que pasa su presupuesto de mano de obra).

**Alcance:** ficha (t4-personal-tareo).

## 11. Contratos: de tabla a tarjetas que avisan antes

Hoy `/contratos` es una tabla. Pasa a esta vista (aprobada completa por Sebastián), con los patrones de pantalla del
sistema y sin textos explicativos:

1. **Tarjetas en dos columnas** (una en pantallas angostas), una por contrato: cliente, objeto y código `CT-…` (con el
   número de la entidad debajo); monto vigente con adendas; estado.
   - **Barra doble de plazo contra avance:** arriba, el plazo transcurrido (% de días pasados del plazo vigente, con
     «13 días restantes» o «vencido hace 5 días»); abajo, el avance según lo valorizado (valorizado acumulado / monto
     vigente, del mismo cálculo que usa Valorizaciones). Si el avance va más de 10 puntos detrás del tiempo, la barra
     de avance va en coral con «atrasado N puntos». Sin valorizaciones, la de avance queda en 0 % gris.
   - **Hitos en miniatura:** firma, entrega de equipos, instalación y conformidad sobre una línea, con la marca de
     «hoy»; cumplidos llenos, vencidos sin cumplir en coral.
   - Penalidad diaria (y aplicada, si hay) y **semáforo de la carta fianza:** verde vigente, ámbar si vence en 60 días
     o menos, coral a 30 días o vencida, gris si no tiene.
2. **Orden por urgencia** (por defecto): primero los borradores, agrupados arriba como «Por completar» con su botón
   «Completar y activar» en la tarjeta; luego los vencidos (el más atrasado primero); luego los que tienen carta fianza
   por vencer en 30 días; luego por días restantes, de menos a más; al final los culminados. Otros órdenes en un
   selector: monto, días restantes, atraso (avance contra tiempo), cliente.
3. **Cinco cifras arriba:** contratos vigentes; monto vigente total; **penalidad en riesgo** (suma, de los vencidos, de
   días de atraso × penalidad diaria, recortada a lo que falta de cada tope; en coral); cartas fianza que vencen en 30
   días; monto total garantizado con bancos (garantías vigentes o renovadas).
4. **Filtros en chips:** Vigentes, Por completar, Por vencer (30 días), Vencidos, Con penalidad, Culminados; buscador
   (código, número de la entidad, objeto, cliente) y cliente. Al filtrar u ordenar, las tarjetas se reacomodan con la
   animación suave.
5. **Segunda vista, «Calendario de vencimientos»:** columnas por semana (Vencidos, Esta semana, las siguientes y luego
   por mes), con chips de lo que vence ahí: fin de plazo de un contrato o vencimiento de una carta fianza (cada uno con
   su ícono y monto). Selector de vista arriba: Contratos · Calendario de vencimientos.
6. **Clic en una tarjeta:** panel deslizante con el resumen rápido (lo de «Lo que hay que mirar todos los días») y los
   botones Aplicar penalidad, Registrar adenda y Registrar garantía. **Doble clic** (o «Abrir ficha» en el panel):
   la ficha completa de siempre.
7. **Avisos en Mien:** «La carta fianza de CT-… vence en 30 días», «El contrato CT-… vence en 7 días» y «CT-… va
   atrasado N puntos contra su plazo».

Datos de demo: que se vean todos los casos (un borrador, uno vencido con penalidad en riesgo, uno atrasado contra su
avance, uno con carta fianza por vencer, uno al día y uno culminado).

**Alcance:** ficha (t2-contratos); los avisos tocan Mien.

## 12. Subcontratos: una tarjeta por subcontrato y la alarma de pagar de más

Hoy `/subcontratos` es una tabla que mezcla las valorizaciones de todos. Pasa a esta vista (aprobada completa por
Sebastián), con los patrones de pantalla del sistema y sin textos explicativos:

1. **Tarjetas en dos columnas** (una en pantallas angostas), una por subcontrato: subcontratista (con su logo o
   avatar), objeto, proyecto y código `SC-…`; monto del subcontrato.
   - **Barra apilada del dinero:** pagado · aprobado por pagar · por aprobar · por ejecutar, sobre el monto del
     subcontrato, con su leyenda en montos al pasar el puntero.
   - **Adelanto por amortizar** y **retención acumulada** (lo retenido aún no devuelto).
2. **Bandeja «Por aprobar»** arriba de las tarjetas: cada valorización pendiente con subcontratista, periodo, avance y
   su cuenta (monto − amortización − retención = **neto a pagar**) y el botón **Aprobar** (lo que hace al aprobar no
   cambia). Si no hay pendientes, la bandeja no aparece.
3. **Alarma «le pagas más avance del que cobras»:** para cada subcontrato, compara el avance acumulado que valorizó el
   subcontratista con el avance que Techquk le valorizó al cliente **en las mismas partidas del alcance** (de
   Valorizaciones, del mismo cálculo). Si el del subcontratista va más de 10 puntos adelante, aviso en coral en la
   tarjeta («le pagas 75 %, cobraste 60 %») y en la bandeja, junto al botón Aprobar de esa valorización. Avisa, no
   impide. Si las partidas del alcance no tienen valorización al cliente, se muestra «sin valorizar al cliente» en
   gris.
4. **Cinco cifras arriba:** subcontratos vigentes; comprometido total; por aprobar (cantidad y monto); por pagar
   (aprobado sin pagar); retenciones acumuladas por devolver.
5. **Liberar retención** (operación nueva): con el subcontrato al 100 % y su conformidad, se devuelve la retención
   acumulada; queda como monto por pagar al subcontratista y la tarjeta pasa a «terminado». Pide fecha de conformidad
   y una nota. Regístrala como acción con su permiso.
6. **Ficha del subcontrato** (doble clic; un clic abre el panel deslizante con el resumen y los botones Valorización
   del subcontratista, Aprobar, Liberar retención y Abrir ficha): sus valorizaciones como línea de pasos (periodo,
   avance, neto, estado) y su **alcance por partida** con barras de ejecutado contra comprometido de cada partida.
7. **Orden por urgencia** (por defecto): primero los que tienen algo por aprobar, luego los que tienen la alarma del
   punto 3, luego los que tienen algo por pagar, luego el resto; terminados al final. **Filtros en chips:** Por
   aprobar, Por pagar, Con aviso, Terminados; buscador por subcontratista, objeto o código; proyecto. Al filtrar u
   ordenar, las tarjetas se reacomodan con la animación suave.
8. **Avisos en Mien:** «La valorización de [subcontratista] espera aprobación hace N días» (desde 3 días) y «A
   [subcontratista] le pagas más avance del que cobraste en [proyecto]».

Datos de demo: que se vean todos los casos (uno con valorización por aprobar, uno con la alarma de pagar de más, uno
con saldo por pagar, uno al 100 % listo para liberar retención y uno terminado con la retención devuelta). Los números
del ejemplo de la ficha (SC-2026-0001) siguen dando lo mismo.

**Alcance:** ficha (t4-valorizaciones, parte de subcontratos); la alarma lee Valorizaciones y los avisos tocan Mien.
