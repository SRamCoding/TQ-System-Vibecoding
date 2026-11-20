# Ficha · Licitaciones (CRM-04)

- **Estado:** aprobada (MV-009)
- **Versión:** v2
- **Tramo de la ruta:** T1
- **Aprobada (v2):** 2026-11-20 · etiqueta `aprobado/t1-licitaciones-v2`
- **Aprobada (v1):** 2026-11-19 · etiqueta `aprobado/t1-licitaciones-v1`
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

- **MV-005** (ficha) · Licitaciones va después del puente al ERP.
- **MV-009** (general) · Aprobada sin revisión, como todas hasta terminar el sistema; los cambios vienen al final.

## Pantallas y navegación

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

- Una licitación es a la vez la convocatoria y nuestra participación (se participa a lo más una vez).
- La entidad que convoca tiene que ser una cuenta del CRM.
- Al participar, la oportunidad nace en prospecto (no calificada) con el valor referencial como monto.
- La «lectura de las bases por IA» es, en el MVP, una lista fija de siete documentos marcada como sugerida.
- Con buena pro, ganar la oportunidad sigue siendo un paso aparte (necesita la cotización aceptada).
- Las seis etapas del cronograma por omisión, con +0, +6, +11, +14, +19 y +26 días desde la convocatoria.

## Atajos tomados

- Estado y tipo de procedimiento como texto con listas fijas en el código, sin el motor de estados ni catálogo.
- Sin acciones registradas: los botones llaman al servicio directamente; hitos, documentos y consultas se editan con
  formularios simples sin bloqueo optimista ni historial.
- El permiso «gestionar» cubre todo; no hay responsable obligatorio por documento.
- El buscador de fichas del resultado busca por razón social con `ilike`.
- El seeder pone estados y fechas históricas directo en la tabla (no recorre el servicio paso a paso).

## Dónde está en el código

`app/Modules/Crm/Licitaciones/` (provider, `Services/LicitacionesService.php`, modelos, enums,
`Filament/Resources/Licitaciones/` con la lista, la ficha y sus cuatro pestañas); datos en
`database/seeders/Demo/CarteraDemo.php` (`licitaciones()`, `competidores()`) y `DemoSeeder::licitaciones()`.

## Cambios desde la versión anterior

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
