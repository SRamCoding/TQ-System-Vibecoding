# Plan: Numeración de documentos (M6) con prueba de concurrencia real

- **Estado:** terminado
- **Módulo(s):** Nucleo
- **Fase:** F1
- **Creado:** 2026-10-26 · **Actualizado:** 2026-10-26

## Objetivo

Un servicio único (`NumeracionService`) da a cada documento su número (`REQ-2026-0001`), sin huecos ni duplicados
aunque varias personas guarden a la vez, y una prueba con procesos de verdad en paralelo lo demuestra. La misma prueba
cubre `PartiesService::obtenerOCrear` y dos transiciones a la vez sobre un documento. El mensaje de «no se puede
anular» muestra el número en vez del id.

## Contexto que leí

- `docs/planes/mapa-etapas.md` fila 1.6.
- `docs/analisis/bloques/comun.md` M6: «correlativos sin huecos ni duplicados, aun con varias personas a la vez».
- `docs/convenciones/codigo.md`: «Correlativos solo vía el servicio de numeración, tomados lo más tarde posible en la
  transacción»; «Concurrencia de correlativos y de aprobaciones: hay pruebas que la simulan».
- `docs/convenciones/base-de-datos.md`: unicidad de correlativos en la base.
- `docs/diseno/techquk_modelo.dbml` `series_documento` (tipo, `documento_type`, serie, `proyecto_id`, `contrato_id`,
  año, último número, longitud, activa). Su `tipo_documento_id` apunta a `tipos_documento`, que es el catálogo SUNAT
  (factura, boleta): no aplica a REQ, OC, ING…, que se identifican por su `documento_type`.
- `docs/diseno/v4/parte-6-fichas-documentos.md`: formatos `PREFIJO-AAAA-NNNN` en casi todos; algunos por proyecto o
  contrato (`PC-NN.NN`, `VAL-contrato-NN`), por mes (`AS-AAAA-MM-NNNN`) o de terceros (serie del proveedor).
- `app/Modules/Nucleo/Estados/DefinicionDocumento.php`, `DocumentoQueImpide.php`, `PartiesService::obtenerOCrear`.
- Plan 1.5 (`completados/2026-10-20-nucleo-estados-transiciones.md`) D5: transiciones con `lockForUpdate`.
- Pendientes: ninguno bloquea.

## Decisiones

Aprobadas por el desarrollador el 2026-10-26 (D2 a D5 elegidas entre opciones; D1 viene del diseño, M6).

- **D1 · Sin huecos.** El contador de cada serie es una fila que se bloquea dentro de la misma transacción que guarda
  el documento: si algo falla, se deshace todo y el número no se pierde. Un documento anulado conserva su número.
- **D2 · Cuándo se toma el número.** Al crear el documento (ya en borrador lleva su número). Los documentos no se
  borran, se anulan (regla 6), así que no deja huecos.
- **D3 · Formato y reinicio.** `PREFIJO-AAAA-NNNN`, reinicia cada 1 de enero (hora de Lima), por la fecha del documento.
  Al pasar de 9999 crece a 5 dígitos, nunca falla. Otros formatos (por mes, sin año) se declaran en el código del tipo.
- **D4 · Series por proyecto o contrato.** Las tablas `proyectos` y `contratos` son de la etapa 2. En vez de sus dos
  columnas, un `ambito` de texto (vacío = toda la empresa; si no, el código del proyecto o contrato) dentro de la
  unicidad, y ese código va dentro del número (`VAL-CT0003-2026-0001`) para que siga siendo único: listo para la
  etapa 2 sin migración nueva.
- **D5 · Quién define las series.** Cada módulo declara las de sus documentos en código (prefijo, formato), como los
  estados; la fila de cada contador en `series_documento` nace con su primer número. Una pantalla de solo lectura en Configuración las muestra con su
  último número.

## Pasos

- [x] 1. Plan aprobado (D1 a D5).
- [x] 2. Tabla `series_documento`, `NumeracionService`, declaración en `DefinicionDocumento` y sincronización.
- [x] 3. Prueba de concurrencia real (procesos en paralelo contra una base sin transacción de prueba): numeración,
  `obtenerOCrear` y dos transiciones a la vez.
- [x] 4. `DocumentoQueImpide` con el número.
- [x] 5. Pantalla de solo lectura de las series (D5).
- [x] 6. Documentos, `revisor-arquitectura`, `composer check`, `/cerrar-tarea` y PR.

## Criterio de aceptación

- `tests/Concurrencia/ConcurrenciaTest.php`: N procesos piden números a la vez → N números distintos, consecutivos, sin huecos.
- Lo mismo para `obtenerOCrear` (una sola party) y para dos transiciones a la vez (una gana, la otra falla limpia).
- Un fallo después de tomar el número no deja hueco; el cambio de año reinicia en 0001.

## Bitácora

- 2026-10-26 — Paso 1. Plan escrito y aprobado: número al crear, `PREFIJO-AAAA-NNNN` con reinicio anual, ámbito
  preparado para proyectos y contratos, series declaradas por cada módulo y visibles en Configuración.
- 2026-10-26 — Pasos 2 y 4. `series_documento` con desviaciones del DBML anotadas en la migración: sin
  `tipo_documento_id` (ese catálogo es el de SUNAT), `ambito` de texto en vez de `proyecto_id`/`contrato_id` (D4),
  `periodo` de texto en vez de `anio` (anual `2026`, mensual `2026-10`, sin reinicio vacío; la unicidad no depende de
  nulos) y sin `es_activa` (D5: lo declara el código). No hace falta sincronizar: la fila del contador nace con el primer
  número (`INSERT … ON CONFLICT DO UPDATE … RETURNING`, que bloquea la fila hasta el final de la transacción).
  `NumeracionService::siguiente` exige transacción abierta. `DefinicionDocumento` gana `numeracion()`,
  `fechaDeNumeracion()`, `ambitoDeNumeracion()` e `identificador()` (número, o `#id` sin numeración);
  `TransicionesService::crear` toma el número justo antes de guardar. El documento de prueba se numera (`DPR`) y
  `DependientesDePrueba` nombra por número. La prueba «fuera de una transacción no da número» va en la de concurrencia:
  aquí habría que confirmar la transacción de RefreshDatabase.
- 2026-10-26 — Paso 3. `tests/Concurrencia/ConcurrenciaTest.php` lanza procesos de verdad (`tests/Concurrencia/proceso.php`,
  con Symfony Process) contra `techquk_concurrencia`, sin RefreshDatabase: la crea si falta y la reconstruye una vez por
  corrida (con guardas: solo esa base). Todos parten en el mismo instante. Cubre: 8 procesos piden número → `REQ-…-0001`
  a `0008`; fuera de transacción no da número; 6 procesos registran el mismo RUC → una sola party, creada por uno; dos
  transiciones a la vez → una gana, la otra falla limpia. **Comprobado que la prueba detecta la carrera:** con el
  servicio roto a propósito (leer y después escribir) los 8 procesos sacaron `REQ-2026-0001`. Nueva suite
  «Concurrencia» en `phpunit.xml`; nota en `AGENTS.md`.
- 2026-10-26 — Paso 5. Configuración → Numeración (`SerieDocumentoResource`, ícono `heroicon-o-hashtag`, elegido por el
  desarrollador): cada contador con documento, serie, ámbito, periodo, último número con su formato y cuándo reinicia;
  solo lectura, con el permiso `catalogos.ver` como Estados. `PantallaNumeracionTest`. Documentos: `codigo.md`
  (cómo se declara y usa la numeración), `comun.md` M6 construido, Común 0.3.11, mapa.
- 2026-10-26 — Revisión de `revisor-arquitectura` (sin bloqueantes), atendidas las siete observaciones: el código del
  ámbito va dentro del número (`VAL-CT0003-2026-0001`; si no, dos proyectos darían el mismo número y la unicidad de
  `numero` se rompería), con CHECK y validación del ámbito como código; fuera la columna `digitos` (la declara el
  código; una copia podía contradecirlo); las dos guardas de la prueba de concurrencia comparan
  `current_database()`, no la configuración; mensaje claro si falta permiso para crear la base; se restaura la base
  original; los procesos no salen a internet; plan corregido (D4, D5, nombre de la prueba).
- 2026-10-26 — Paso 6. `composer check` en verde (1562 pruebas). Plan terminado. Sigue la 1.7 (aprobaciones), que puede
  apoyarse en `tests/Concurrencia` para su prueba de dos firmas a la vez.
