# 0018 — Convocatorias y bases desde los datos abiertos del OECE

- **Estado:** aceptada (2026-11-03, por el desarrollador)
- **Fecha:** 2026-10-20
- **Decide:** desarrollador, con el visto bueno de la Gerencia Comercial (aprueba el alcance del CRM)

## Contexto

En Techquk el negocio entra por convocatorias del Estado (v4, parte 2). El diseño de CRM-04 tiene la
bandeja de convocatorias, el análisis de precios y el histórico de la entidad, pero la bandeja se llena a
mano y el histórico solo crece con los resultados que Techquk registra. La idea del 22 de septiembre de
usar Licitalab como fuente quedó pendiente porque no se sabía si el proveedor ofrece una API
([bloque CRM](../analisis/bloques/crm.md)). El v4 no prevé ninguna integración con el SEACE.

Lo verificado el 2026-10-20:

- El OECE publica sus contrataciones en el estándar de datos abiertos OCDS, con licencia CC BY 4.0
  (permite el uso comercial citando la fuente). Su portal (`contratacionesabiertas.oece.gob.pe/descargas`)
  se actualiza **a diario**. El espejo de Open Contracting (`data.open-contracting.org`, publicación 135)
  lo copia **una vez al mes**: no sirve como fuente diaria.
- El archivo de 2026 del espejo (corte al 2 de octubre) trae 134,917 procesos. **Todos** tienen al menos
  un documento de bases («Bases Administrativas», y «Bases Integradas» cuando ya existen) con su enlace,
  junto con el pliego de absolución de consultas, los documentos de evaluación y el otorgamiento de la
  buena pro. Todos los enlaces son de descarga directa del SEACE
  (`prod1.seace.gob.pe/SeaceWeb-PRO/SdescargarArchivoAlfresco?fileCode=…`).
- El desarrollador abrió tres de esos enlaces desde Chiclayo, sin iniciar sesión en el SEACE, y
  descargaron el PDF sin captcha. Desde un servidor fuera del Perú el SEACE no respondió.
- El mismo archivo trae las adjudicaciones (quién ganó y los documentos de la buena pro).

## Decisión

Las convocatorias y sus bases se traen **automáticamente de los datos abiertos del OECE**, como
integración propia del sistema, y entran a la bandeja de CRM-04. Se **amplía el alcance del MVP**
([ADR 0006](0006-alcance-y-etapas.md)): esta fuente no está en el v4, pero entra en la etapa 2 con el
resto del CRM, con las condiciones de abajo.

1. **Fuente diaria:** una tarea programada lee cada día lo nuevo del portal del OECE, no del espejo mensual.
2. **Filtros configurables** por rubro (palabras clave y códigos de catálogo), región, tipo de
   procedimiento y monto. Lo que pasa el filtro entra a la bandeja. Los valores los define la
   Gerencia Comercial; se editan por pantalla, sin programar.
2b. **Explorador de todas las convocatorias** (desarrollador, 2026-11-04): por si el filtro falla, se guardan
   los datos básicos de **todos** los procesos que se leen cada día (entidad, objeto, región, monto, fechas,
   estado y el enlace de origen), buscables y filtrables en una pantalla aparte de la bandeja. Desde ahí,
   «Seguir esta» pasa una convocatoria a la bandeja y recién entonces se descargan sus bases y se crea o
   encuentra su entidad en Personas y empresas. Una vista muestra lo que el filtro descartó, para ajustarlo.
   Son unos 135 mil procesos al año, solo con datos básicos; en el explorador no se descargan bases ni se
   crean fichas de personas o empresas.
3. **Bases dentro de la convocatoria:** se descargan solo las de las convocatorias de la bandeja (filtradas o
   seguidas a mano) y se guardan
   como adjuntos de la convocatoria (tabla `adjuntos`, con su tipo), en el mismo lugar donde hoy se suben
   a mano. Cada documento guarda su origen (descargado del SEACE o subido por una persona), la fecha y el
   enlace original. El hash evita guardar dos veces el mismo archivo.
4. **Versiones de las bases:** cuando aparecen las bases integradas, se descargan, se conservan también
   las administrativas, se marca cuál está vigente y se avisa al responsable de la convocatoria.
5. **Histórico de adjudicaciones** de las entidades que interesan (no de todo el país), para que el
   histórico de la entidad y el análisis de precios sirvan desde el primer día.
6. **Arquitectura:** integración detrás de una interfaz (regla 5 de `ARCHITECTURE.md`), con versión falsa
   en las pruebas, dentro del monolito ([ADR 0001](0001-monolito-modular.md)). La descarga corre en su
   propia cola y su propio proceso de trabajo, para que un fallo o una carga pesada no afecte a quienes
   usan el sistema. Las entidades se crean o encuentran solo por `PartiesService::obtenerOCrear()`.
7. **Siempre hay camino manual:** registrar una convocatoria y subir sus bases a mano sigue funcionando.

Condiciones para que no ponga en riesgo el MVP:

- **Antes de construir**, una comprobación corta: que el portal del OECE entrega lo nuevo cada día y que
  se descarga desde el servidor de Techquk. Si no se cumple, este ADR se revisa antes de seguir.
- **No es condición para cerrar la etapa 2,** que se sigue cerrando con los escenarios de los flujos 1,
  2, 3 y 7. Si esta tarea se atrasa, no frena el resto.
- **Queda fuera del MVP:** alertas por búsquedas guardadas, análisis de competidores y lo demás que pueda
  aportar Licitalab. El explorador básico (2b) sí entra.

## Consecuencias

- La bandeja de convocatorias se llena sola, con las bases adjuntas, y el histórico de cada entidad está
  completo desde el inicio.
- La lectura de bases con IA (`licitacion_bases`) recibe el PDF sin que nadie lo descargue. Sigue sujeta
  al pendiente «IA y datos»; sin IA, la persona lee las bases que el sistema ya trajo. Como las bases son
  documentos públicos, es buen candidato para ser el primer proceso de IA autorizado.
- **El servidor tiene que estar en el Perú.** Si el sistema se mudara a un servidor fuera del país, la
  descarga de bases podría dejar de funcionar.
- **Descarga moderada:** solo las bases filtradas y a un ritmo bajo; es un servidor del Estado.
- **Vigilancia:** una alerta avisa si un día no llega nada, porque el OECE puede cambiar el formato o los
  enlaces sin aviso, y entonces hay que ajustar la integración.
- Los ganadores con RUC 10 llegan con el nombre completo sin separar: les aplica el pendiente «Nombres de
  una persona desde el OECE o el padrón», que debe resolverse antes de construir esta integración.
- `ARCHITECTURE.md` suma esta integración a su lista (hecho al aceptarse).
- Los filtros (rubros, palabras clave, regiones, tipos de procedimiento, montos) se definen al construir la
  integración, no en la planificación (desarrollador, 2026-11-03). Siguen siendo de la Gerencia Comercial.

## Alternativas descartadas

- **Licitalab como fuente principal:** no se conoce una API ni una exportación, y la bandeja dependería
  de un proveedor externo. Queda como complemento a evaluar por lo que ofrezca además de la fuente oficial
  (pendiente «Fuente de convocatorias»).
- **Servicios o scrapers que resuelven el captcha del buscador del SEACE:** eluden a propósito un control
  del Estado, chocan con sus condiciones de uso y son frágiles. Además, no hacen falta: los enlaces de
  descarga vienen en los datos abiertos.
- **Un microservicio aparte:** duplicaría las entidades y agregaría despliegue y comunicación entre
  servicios, en un sistema de una sola empresa con un servidor. El aislamiento necesario se logra con un
  módulo, una cola y un proceso de trabajo propios (ADR 0001).
- **Solo carga manual:** es lo que hace hoy una persona revisando el SEACE; ahora se sabe que se puede
  automatizar sin costo de licencias. Queda como respaldo.
- **Dejarlo fuera del MVP:** el CRM saldría con la bandeja manual y el histórico vacío, que es justo lo
  que le resta valor al análisis de precios.
