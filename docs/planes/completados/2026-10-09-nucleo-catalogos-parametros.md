# Plan: 1.2 · Catálogos y parámetros

- **Estado:** terminado
- **Módulo(s):** Nucleo (M5 catálogos, M9 parámetros)
- **Etapa:** 1 · tarea 1.2
- **Creado:** 2026-10-09 · **Actualizado:** 2026-10-10

## Objetivo

1. Existen las tablas de M5 y `parametros` (M9) con sus datos iniciales, unicidad y CHECKs en la base,
   y servicios del núcleo para leerlas (`CatalogosService`, `TipoCambioService`, `ParametrosService`)
   que los demás módulos usarán en la etapa 2.
2. Un parámetro editable y un tipo de cambio se cambian por acción registrada (con permiso, bitácora y
   bloqueo optimista), sin interfaz y desde Filament.
3. Los errores de validación salen en español (ADR 0011), y una prueba falla si una regla usada por un
   `Campo` no tiene traducción.

## Contexto que leí

- `AGENTS.md`, `docs/planes/mapa-etapas.md` (1.2 = M5, M9)
- `docs/planes/completados/2026-10-09-nucleo-auditoria-acciones.md` §Qué sigue y bitácora
- ADR 0011 (traducciones propias), 0009 (precedencia), 0003 (empresa única: no hay tabla `empresas`)
- `docs/diseno/techquk_modelo.dbml` §M5 (l. 254–351) y §M9 `parametros` (l. 441–455)
- `docs/diseno/06-Arquitectura-Nucleo-Datos.md` §8 (M5) y §12 (M9) — agosto, pierde ante el DBML v2
- `docs/diseno/00-Reconciliacion-con-v4.md` D3 (CUBSO sin carga)
- `docs/diseno/v4/parte-7-transversal.md` (tipo de cambio: carga diaria manual o automática **POR VALIDAR**)
- `docs/convenciones/base-de-datos.md`, `docs/convenciones/codigo.md` §Modelos, §Acciones, §Filament
- Código: `Nucleo/RegistroAcciones/Campo.php` (reglas que usan los campos), `TipoCampo`
- `docs/decisiones/pendientes.md`: **nada bloquea.** CUBSO: la tabla existe sin carga. Tipo de cambio
  automático: POR VALIDAR → se construye la carga manual y queda preparado el campo `origen`.

## Deuda que entra en esta tarea

| Deuda | Dónde |
|---|---|
| Mensajes de validación en español (ADR 0011) | **paso 1** |
| Prueba de la rama «acción rechazada → aviso» de `AccionFilament` | **paso 8** (primera acción con permiso) |
| Pestaña de historial, `User` a `Nucleo\Models`, morph map | siguen en 1.3 / 1.4 |

## Decisiones de diseño (aprobadas 2026-10-09 y 2026-10-10)

- **D1 · Qué tablas entran.** Las diez del grupo M5 del DBML: `monedas`, `tipos_cambio`,
  `unidades_medida`, `ubigeos`, `tipos_documento`, `formas_pago`, `lineas_solucion`, `cubso`,
  `tipos_penalidad`, `tipos_trabajo`; y `parametros`. **No entran:** `tipos_documento_identidad`
  (está en M1 → 1.3), `series_documento` (M6 → 1.6), `empresas` (ADR 0003).
  **Aprobada 2026-10-09.** Deuda que sale de aquí: `tipos_documento_identidad` se crea en la 1.3 con
  `parties`; `series_documento` en la 1.6; los datos de la empresa, como parámetros cuando un documento
  los pida.
- **D2 · El DBML v2 gana al doc 06** donde difieren: `tipos_documento` (no `tipos_comprobante`),
  `ubigeos` plano (sin `parent_id`), `cubso` sin `segmento/familia/clase`, `lineas_solucion` sin
  `tipologia/vertical`, `monedas` sin `es_local`. La moneda funcional va como parámetro
  `general.moneda_local = PEN` (no es una columna que solo una fila puede tener en `true`).
  **Aprobada 2026-10-09.** Techquk opera en soles y dólares (ambas activas en `monedas`) y lleva su
  contabilidad en soles: moneda local PEN. Los resultados en otra moneda se convierten con el TC del
  día del documento.
- **D3 · Auditoría.** El DBML no pone `created_by` en M5, pero son tablas de negocio editables: todas
  llevan `auditoria()` y `ConAuditoria` (la convención gana). `RegistraCambios` (bitácora) en
  `parametros` (está en la lista de la convención) **y en `tipos_cambio`** (cambia montos de documentos;
  propongo agregarla a la lista).
  **Aprobada 2026-10-09.** `tipos_cambio` entra a la lista de tablas sensibles de `base-de-datos.md` (paso 9).
- **D4 · Integridad en la base** (reglas 4 y 5), con ajustes al DBML (borrar lo nunca usado: ajustado por ADR 0013):
  - `codigo` único en cada catálogo; `tipos_cambio (moneda_id, fecha)` único; `parametros (grupo, clave)` único.
  - `tipos_cambio.origen` pasa a `NOT NULL` con CHECK `sunat|manual`; CHECK `compra > 0` y `venta > 0`.
  - `tipos_documento.signo` CHECK `in (1, -1)`; `monedas.decimales` CHECK `between 0 and 4`;
    `formas_pago.dias` CHECK `>= 0` (nulo = contado).
  - Booleanos `es_activo*` con default `true`. No se borra un elemento de catálogo: se desactiva.
  **Aprobada 2026-10-09.**
- **D5 · `parametros`.**
  - `tipo`: el DBML dice `texto|numero|booleano|fecha`; propongo **`texto|entero|decimal|booleano|fecha`**
    (CHECK en la base), igual a `TipoCampo`: «número» no dice si se lee como entero o como decimal en
    string. Sin `json` hasta que un parámetro lo necesite.
  - `version integer not null default 1` (bloqueo optimista; la convención lo pide para parámetros).
  - `ParametrosService`: lectura tipada (`entero()`, `decimal()` → string, `booleano()`, `fecha()`,
    `texto()`) por `'grupo.clave'`; clave inexistente o tipo distinto → excepción de dominio. Memoriza
    por petición; sin caché compartida por ahora.
  - Valores iniciales **solo los que no son decisión pendiente**: `tributario.igv_porcentaje = 18.00`
    (decimal) y `general.moneda_local = PEN` (texto). U1/U2/U3 los siembra la 1.7; el resto, cada
    flujo cuando lo use.
  **Aprobada 2026-10-09.**
- **D6 · Tipo de cambio.** Carga manual por acción; `TipoCambioService::del(moneda, fecha)` devuelve
  compra y venta del día; **si no hay TC para esa fecha, lanza excepción** (no tomo el del día hábil
  anterior: es una regla que no está decidida). Registrar el TC de la moneda local se rechaza. Se anota
  en `pendientes.md`: carga automática y regla de días sin publicación.
  **Aprobada 2026-10-10 (opción A: error si falta el TC del día).** Se descartaron por ahora «último
  publicado» y «último publicado con tope de días»: la regla para sábados, domingos y feriados la
  confirma contabilidad (pendiente).
  **Reemplazada 2026-10-10 por ADR 0012:** TC automático desde SUNAT (tarea programada, origen `sunat`),
  margen de 4 días (`tipo_cambio.dias_tolerancia = 4`) y registro manual como respaldo.
- **D7 · Acciones y Filament.** *(La edición de catálogos se adelantó en la tarea 1.2b: ADR 0013.)* Acciones registradas: `nucleo.cambiar-parametro` (rechaza `es_editable
  = false`, valida el valor según su `tipo`, exige la `version` leída) y `nucleo.registrar-tipo-cambio`
  (crea o corrige el del día). Permisos spatie: `parametros.editar`, `tipos_cambio.registrar`,
  `catalogos.ver` (sembrados; ámbitos en 1.4). Filament, sección «Configuración»: lista de parámetros
  y de tipos de cambio con sus acciones, y **listas de solo lectura** de los demás catálogos.
  Crear y editar elementos de catálogo **queda fuera**: se mantienen por seeder hasta que un flujo de
  la etapa 2 lo pida (serían ~20 acciones sin uso todavía).
  **Aprobada 2026-10-10.** Deuda para la etapa 2: acciones de crear/editar/desactivar unidades de medida
  y formas de pago (compras, cotizaciones), tipos de penalidad (contratos), líneas de solución (CRM) y
  tipos de trabajo (tareo). Monedas, tipos de documento y ubigeos: listas oficiales, por seeder.
  Descartada por ahora una base genérica de acción de catálogo.
  **Ampliada 2026-10-10:** tercera acción `nucleo.actualizar-ubigeos` (permiso `ubigeos.actualizar`):
  botón en «Configuración → Ubigeos» que descarga la lista del INEI, muestra las diferencias contra la
  **base** y, al confirmar, crea, renombra y desactiva (nunca borra). Escribe la base, no el CSV (en
  producción no se toca el código). Por eso el seeder de ubigeos solo crea los que faltan; el comando
  queda para refrescar el CSV del repositorio. Moneda local confirmada como no editable.
- **D8 · Datos iniciales** en `CatalogosSeeder` idempotente (`updateOrCreate` por `codigo`), que corre en
  todo ambiente (no es seeder de desarrollo). Solo datos públicos o de las notas del DBML:
  - `monedas`: PEN, USD. · `unidades_medida`: subconjunto del catálogo 03 de SUNAT (NIU, ZZ, MTR, KGM…).
  - `tipos_documento`: catálogo 01 de SUNAT (01, 03, 07, 08, 09; 02 recibo por honorarios si se usa).
  - `formas_pago`: contado, crédito 30, crédito 60. · `tipos_penalidad`: mora, incumplimiento parcial,
    resolución. · `tipos_trabajo`: desarrollo, análisis, pruebas, soporte, reunión.
  - `lineas_solucion`: las que ya nombra el diseño (videovigilancia, redes, datacenter, software…);
    **la lista real de nueve líneas la confirma el negocio** → anotar en pendientes (no bloquea: son datos).
  - `ubigeos`: ~1,890 distritos del INEI desde un CSV en `database/data/` (dato público). · `cubso`:
    sin carga (reconciliación D3).
  **Aprobada 2026-10-10.**
  - Ubigeos: códigos **INEI** (los usa SUNAT; los del RENIEC son distintos). El agente descarga la lista
    una vez desde datosabiertos.gob.pe (permiso dado) y la muestra antes de cargarla; fuente y fecha en la
    cabecera del seeder. Sin consulta en vivo: la lista cambia solo por ley de creación de distritos y
    se actualiza el CSV. Prueba: 6 dígitos, sin repetidos, incluye 140101 (Chiclayo).
  - Líneas de solución: se siembran las cuatro conocidas; las demás se agregan al seeder cuando el
    negocio dé la lista (pendiente) y, desde la etapa 2, con la acción de edición (D7).

## Preparado para lo diferido

Pedido del desarrollador (2026-10-10): lo que se pospone deja listo su punto de entrada, para que
adoptarlo después sea natural. Preparar no es construir: sin pantallas, acciones ni tablas sin uso.

| Diferido | Para cuándo | Cómo queda preparado | Paso |
|---|---|---|---|
| Tipos nuevos de parámetro (`json`…) | Cuando un parámetro lo pida | `TipoParametro`: un caso + su `match` + el CHECK. Cada tipo sabe con qué `Campo` se edita | 3 ✅ |
| Parámetros de otras tareas (U1/U2/U3, márgenes, datos de la empresa) | 1.7, etapa 2 | Cada módulo **declara** sus parámetros en código (grupo, clave, tipo, valor inicial, descripción); el seeder crea los que faltan y nunca pisa un valor ya cambiado. La tarea futura solo declara | 5 (aprobado) |
| Edición de catálogos | Etapa 2 | Trait `EsCatalogo` en los 10 modelos: `activos()`, `porCodigo()`, `desactivar()`, y la columna de activo resuelta por modelo (el DBML mezcla `es_activo` y `es_activa`). Las acciones futuras solo llaman al servicio | 6 ✅ |
| TC automático desde SUNAT | ~~POR VALIDAR~~ ADR 0012 | Construido en el paso 9b sobre lo preparado (`origen`, única escritura). Otra fuente: otra implementación de `FuenteTipoCambio` | 9b ✅ |
| Regla de días sin TC publicado | Contabilidad | La búsqueda vive en un solo método de `TipoCambioService`. Propuesto: parámetro `tipo_cambio.dias_tolerancia = 0` (0 = opción A aprobada); contabilidad lo sube sin tocar código | 6 (aprobado) |
| Actualizar ubigeos y líneas de solución | Cuando cambien | Seeder idempotente por `codigo`; lo que sale del archivo se desactiva, no se borra | 5 |
| Permisos de acciones futuras | Cada tarea | `PermisosSeeder` crea el `permiso()` de toda acción registrada: una acción nueva trae su permiso sin tocar el seeder | 5 ✅ |
| Edición de catálogos sin choque con el seeder | Etapa 2 | Listas del negocio (unidades, formas de pago, líneas, penalidades, tipos de trabajo) se siembran con `firstOrCreate`: el seeder nunca pisa lo editado. Las oficiales (monedas, tipos de documento, ubigeos) sí siguen al seeder | 5 ✅ |
| Errores de negocio de cualquier módulo en pantalla | Siempre | Base `ErrorDeNegocio`: `AccionFilament` muestra como aviso toda excepción que la extienda | 6 ✅ |
| Bloqueo optimista de otros documentos | Etapa 2 (cotizaciones, presupuestos…) | `VersionDesactualizada` es genérica; el patrón está en `ParametrosService::cambiar()` | 6 ✅ |
| Catálogo nuevo | Cuando haga falta | Un caso en el enum `Catalogo` + modelo con `ElementoCatalogo`/`EsCatalogo`: `CatalogosService` lo sirve sin cambios | 6 ✅ |
| Bloqueo optimista desde pantallas | Etapa 2 (toda edición) | `Campo::deControl()`: la pantalla captura la versión **al abrir** el formulario (oculta). Probado con el orden real: abrir → otro guarda → enviar | 8 ✅ |
| Pantallas de otros módulos | Cada módulo | `Panel::configureUsing()` desde su provider (como el núcleo) y su `CajaModulo` para el modo modular | 8 ✅ |
| Carga de CUBSO | Si Licitaciones la pide | Tabla lista con `version_catalogo`; la carga será un comando aparte que no toca el esquema | — |

## Pasos

- [x] 1. ADR 0011: `lang/es/validation.php` con las reglas que usan los `Campo` (`required`, `nullable`,
      `string`, `max.string`, `integer`, `numeric`, `decimal`, `date_format`, `boolean`, `enum`) y las
      nuevas de esta tarea; el ejecutor pasa la etiqueta de cada campo como `:attribute`.
      `TraduccionesValidacionTest`: recorre las acciones registradas y falla si una regla no tiene línea;
      un error de validación sale en español con la etiqueta.
- [x] 2. Migraciones M5 (`/nueva-migracion`, D1–D4): una por tabla o agrupadas por relación
      (`monedas`+`tipos_cambio`, `unidades_medida`+`cubso`, resto). Modelos con `ConAuditoria`
      (+ `RegistraCambios` en `tipos_cambio`), casts y factories.
- [x] 3. Migración y modelo `parametros` (D5), con `RegistraCambios` y `version`.
- [x] 4. Pruebas de integridad que intentan romper la base: códigos duplicados, TC duplicado por día,
      TC ≤ 0, origen inválido, signo inválido, tipo de parámetro inválido, `created_by` nulo.
- [x] 5. `CatalogosSeeder` (D8) y seeder de permisos; ambos idempotentes (prueba: correr dos veces no
      duplica). Se llaman desde `DatabaseSeeder`.
- [x] 6. Servicios: `ParametrosService` (D5), `TipoCambioService` (D6, con `tipo_cambio.dias_tolerancia`),
      `CatalogosService` (búsqueda de activos por código para los demás módulos; trait `EsCatalogo`) y
      `UbigeosService` (diferencias contra la base y aplicarlas en transacción; la descarga va antes,
      fuera de la transacción). Pruebas del camino feliz y de cada rechazo.
- [x] 7. Acciones `CambiarParametro`, `RegistrarTipoCambio` y `ActualizarUbigeos` (`/nueva-accion`, D7). Pruebas sin
      interfaz: cambia y deja bitácora; rechaza sin permiso, no editable, valor que no corresponde al
      tipo, versión vieja (otro lo cambió antes), TC de moneda local, TC ≤ 0.
- [x] 8. Filament «Configuración»: recursos de parámetros y tipos de cambio con sus acciones vía
      `AccionFilament`; ubigeos con el botón «Buscar actualización» (diferencias) y «Aplicar»; listas de solo lectura de catálogos (con `catalogos.ver`). Prueba de panel,
      incluida la rama de rechazo de `AccionFilament` (deuda 1.1).
- [x] 9. Documentos: `base-de-datos.md` (tipos_cambio en la lista de tablas sensibles; catálogos se
      desactivan, no se borran), `codigo.md` (cómo leer un parámetro), `pendientes.md` (TC automático y
      días sin publicación; lista real de líneas de solución), `ARCHITECTURE.md` si cambia algo. Bitácora.
- [x] 9b. TC automático (ADR 0012): fuente SUNAT tras una interfaz `FuenteTipoCambio`, sincronización
      que no pisa valores manuales, comando programado, acción «Traer de SUNAT ahora», margen 4 días.
- [x] 10. `composer check` en verde, `/cerrar-tarea` y revisión de `revisor-arquitectura`.

## Criterio de aceptación

- `tests/Modules/Nucleo/TraduccionesValidacionTest.php`, `CatalogosIntegridadTest.php`,
  `CatalogosSeederTest.php`, `ParametrosServiceTest.php`, `TipoCambioServiceTest.php`,
  `CambiarParametroTest.php`, `RegistrarTipoCambioTest.php` en verde, y `PanelTest` ampliado.
- `ParametrosService::decimal('tributario.igv_porcentaje')` devuelve `'18.00'` tras sembrar.
- Un administrador ve «Configuración» en el panel, cambia un parámetro editable y la bitácora lo muestra
  en `auditoria_cambios`; un usuario sin permiso no ve la acción.

## Bitácora

- 2026-10-09 — Rama `etapa-1-catalogos-parametros` creada desde `main`. Plan escrito; D1–D8 por
  confirmar con el desarrollador antes de programar.
- 2026-10-10 — D1–D8 aprobadas una por una con el desarrollador (detalle en cada decisión). Techquk
  opera en PEN y USD, contabilidad en soles. TC faltante → error (opción A). Siguiente: paso 1.
- 2026-10-10 — Paso 1. `lang/es/validation.php` (reglas de los `Campo`: required, string, max/min por
  tipo, integer, numeric, decimal, date_format, boolean, enum) y `lang/es.json` (resumen «(y N errores
  más)» de `ValidationException`, que no sale de validation.php). El ejecutor ya pasaba las etiquetas como
  `:attribute`. `TraduccionesValidacionTest` deduce las claves de cada regla (tamaño → `.string`/`.numeric`
  según el tipo; una regla objeto desconocida falla pidiendo su clave); verificado que falla al quitar
  `enum`. `composer check` en verde: 76 pruebas.
- 2026-10-10 — Paso 2. Migraciones `…000005` (monedas, tipos_cambio), `…000006` (unidades_medida, cubso),
  `…000007` (ubigeos, tipos_documento, formas_pago, lineas_solucion, tipos_penalidad, tipos_trabajo).
  Desviaciones del DBML (todas de D3/D4): `auditoria()`, `codigo` único, `(moneda_id, fecha)` único,
  `origen` NOT NULL + CHECK, CHECKs de compra/venta, signo, decimales y días; defaults en booleanos
  (`es_activo*` true, los demás false). Extra: índice en `tipos_cambio.fecha` (consultas por día).
  Modelos en `Nucleo/Models` con `ConAuditoria`; `TipoCambio` además `RegistraCambios` (sin campos
  excluidos) y enum `OrigenTipoCambio`. Factories en `Nucleo/database/factories` (namespace
  `App\Modules\Nucleo\database\factories`, enlazadas con `#[UseFactory]`). `CatalogosModelosTest`.
  `composer check` en verde: 88 pruebas.
- 2026-10-10 — Paso 3. Migración `…000008`: `parametros` con `version`, CHECK de tipo y CHECK de que el
  valor tenga la forma de su tipo (entero, decimal sin exponente, `true|false`, `Y-m-d`). Enum
  `TipoParametro` (texto ↔ PHP, y el `Campo` con el que se edita); modelo `Parametro` con bitácora sin
  `version`. `ParametroTest`. Agregada la sección «Preparado para lo diferido» (pedido del desarrollador).
- 2026-10-10 — Aprobados: parámetros declarados por cada módulo (registro en código, como
  `RegistroAcciones`; el seeder crea los que faltan y no pisa valores) y `tipo_cambio.dias_tolerancia = 0`.
- 2026-10-10 — Paso 4. `CatalogosIntegridadTest` (35 casos): inserta por query builder una fila válida con
  un dato roto y espera la restricción por su nombre (códigos únicos de los 9 catálogos, TC por día,
  parámetro por grupo, cada CHECK, NOT NULL, FK que impide borrar una moneda en uso) y que los valores
  válidos de cada tipo de parámetro sí entran.
- 2026-10-10 — Paso 5.
  - `RegistroParametros` + `DefinicionParametro` en `Nucleo/RegistroParametros/` (igualdad explícita
    `igualA()`: Pint cambia `!=` por `!==`). El provider declara `tributario.igv_porcentaje = 18.00`,
    `general.moneda_local = PEN` (**no editable**: cambiarla con datos rehace las conversiones) y
    `tipo_cambio.dias_tolerancia = 0`. `ParametrosSeeder`: crea lo que falta, nunca pisa `valor`;
    descripción y `es_editable` siguen al código; tipo distinto → excepción (exige migración).
  - Ubigeos: fuente **datosabiertos.gob.pe, `UBIGEODISTRITOS.XLSX`** (INEI, ficha modificada 2025-12-17).
    El portal responde 418 sin User-Agent de navegador. 1,874 distritos, 25 departamentos, sin códigos
    repetidos; nombres en mayúsculas sin tildes, tal como los publica el INEI (las búsquedas usan
    `unaccent`). `Nucleo/Ubigeos/` (`LectorXlsx` propio con ext-zip, sin dependencia nueva; `PadronUbigeos`
    valida, compara y guarda; mínimo 1,800 distritos para rechazar descargas cortadas) y comando
    `nucleo:actualizar-csv-ubigeos` en `Nucleo/Console/` (carpeta nueva; muestra diferencias, pide
    confirmación o `--aplicar`). URL en `config/nucleo.php`. CSV en `Nucleo/database/data/ubigeos.csv`
    + `ubigeos.fuente.json` (url, fecha, cantidad). `tempnam` lo prohíbe el preset de seguridad: la
    descarga pasa por un archivo aleatorio en `storage/app`.
  - `CatalogosSeeder` (oficiales con `updateOrCreate`, del negocio con `firstOrCreate`; ubigeos crea,
    corrige, desactiva y reactiva), `PermisosSeeder` (permisos de las acciones + `catalogos.ver`) y
    `NucleoSeeder`, llamado desde `DatabaseSeeder` en todo ambiente. Primera carga 3.7 s; repetir, 0.4 s.
  - Valores elegidos que van a `pendientes.md` (paso 9): `tipos_trabajo.es_facturable_default = false` en
    todos hasta que Jefatura de Desarrollo decida; las cuatro líneas de solución conocidas.
    Símbolo del dólar `US$` (distingue de S/ cuando conviven).
  - Base de desarrollo: `php artisan migrate` (solo agrega) y `NucleoSeeder`.
  - Pruebas: `PadronUbigeosTest` (13, Http::fake y un XLSX armado en la prueba), `ParametrosDeclaradosTest`
    (6), `CatalogosSeederTest` (5). `composer check` en verde: 162 pruebas.
- 2026-10-10 — Confirmado: moneda local no editable; botón de actualización de ubigeos (D7 ampliada).
  `CatalogosSeeder` cambia: ubigeos solo se crean si faltan (prueba: no deshace renombres, retiros ni
  altas hechos por la pantalla aunque el CSV esté atrasado). Quedan como valores iniciales anotados:
  ningún tipo de trabajo facturable por defecto y `US$`.
- 2026-10-10 — Paso 6.
  - `ErrorDeNegocio` (abstracta): base de toda excepción de dominio; `AccionRechazada` la extiende y
    `AccionFilament` captura la base. Excepciones nuevas: `ParametroNoDisponible`, `ParametroRechazado`,
    `VersionDesactualizada` (genérica), `TipoCambioFaltante`, `TipoCambioInvalido`, `CatalogoNoEncontrado`.
  - Catálogos: interfaz `ElementoCatalogo` + trait `EsCatalogo` (columna de activo por modelo: `es_activa`
    en Moneda, UnidadMedida, FormaPago, LineaSolucion; etiqueta propia en Ubigeo y Cubso). Enum `Catalogo`
    (nombre → modelo) para que otros módulos no importen modelos del núcleo. `CatalogosService`:
    `idPorCodigo`, `codigoDe`, `estaActivo`, `opciones`.
  - `ParametrosService` (binding `scoped`): lectura tipada, `monedaLocal()`, `ficha()` y `cambiar()` con
    `lockForUpdate` + comparación de versión (rechaza no editable, valor de otro tipo, decimal que el
    CHECK no aceptaría como «.5»).
  - `TipoCambioService`: `del()` (moneda local = 1; tolerancia por parámetro; nunca un TC posterior) y
    `registrar()` (única escritura, con origen; crea o corrige). DTO `TipoCambioDelDia`.
  - `UbigeosService`: `descargar()` (fuera de transacción), `diferencias()` contra activos de la base,
    `aplicar()` en transacción (crea, reactiva, renombra, desactiva).
  - Pruebas: `ParametrosServiceTest` (14), `TipoCambioServiceTest` (12), `CatalogosServiceTest` (13),
    `UbigeosServiceTest` (3). Ojo: Pest comparte las funciones globales entre archivos; una prueba pasaba
    solo por el orden de carga. Verificado que cada archivo del núcleo pasa por separado.
    `composer check` en verde: 204 pruebas.
- 2026-10-10 — Paso 7.
  - `CambiarParametro` (`parametro`, `valor` como texto, `version`): los campos de una acción son fijos y
    el tipo depende del parámetro, así que el servicio interpreta el texto (`ParametrosService::
    cambiarDesdeTexto()` + `TipoParametro::interpretar()`: «sí/no», espacios, fecha con ida y vuelta para
    rechazar 2026-02-30; Carbon lanza `InvalidFormatException`, se captura). Mensaje de valor inválido dice
    qué se espera (`TipoParametro::formatoEsperado()`).
  - `RegistrarTipoCambio` (`moneda`, `fecha`, `compra`, `venta` con 4 decimales; origen manual).
  - `ActualizarUbigeos` (sin campos): descarga y aplica a la base. Errores de descarga y formato pasan a
    `PadronUbigeosNoDisponible` (`ErrorDeNegocio`): la pantalla los mostrará como aviso.
  - El comando se renombró a `ActualizarCsvUbigeos` / `nucleo:actualizar-csv-ubigeos`: con el mismo nombre
    que la acción (`nucleo.actualizar-ubigeos`) se confundían, y hacen cosas distintas (CSV vs base).
  - Los permisos `parametros.editar`, `tipos_cambio.registrar` y `ubigeos.actualizar` los crea
    `PermisosSeeder` solo, desde las acciones (ajustada la prueba que fijaba la lista).
  - Pruebas: `CambiarParametroTest` (21), `RegistrarTipoCambioTest` (11), `ActualizarUbigeosTest` (4).
    Cada archivo del núcleo pasa por separado. `composer check` en verde: 240 pruebas.
- 2026-10-10 — Paso 8.
  - Clúster `Configuracion` (`/configuracion`, con pestañas propias: sirve en modo modular) con
    `ParametroResource` (acción «Cambiar» por fila; oculta si no es editable), `TipoCambioResource`
    («Registrar» arriba con USD y hoy; «Corregir» por fila) y la página `Catalogos` (una pestaña por
    catálogo vía `?catalogo=`, búsqueda por código y nombre sin tildes; en ubigeos, «Buscar actualización»
    —solo lectura, no es acción registrada— y «Aplicar actualización» —acción registrada, con confirmación).
    Ver: permiso `catalogos.ver` (`canViewAny`/`canAccess`). Caja «Configuración» en el modo modular.
  - Registro de pantallas: `Panel::configureUsing()` en `NucleoServiceProvider::register()` (el panel se
    arma perezosamente, después).
  - `AccionFilament`: datos fijos con inyección de Filament (`$record`), campos fijos calculados al
    armar el formulario y `$alTerminar` para avisar el resultado.
  - **Defecto encontrado por la prueba:** la versión se tomaba al guardar (Filament relee la fila), así
    que el bloqueo optimista nunca saltaba desde la pantalla. `Campo::deControl()` + `Hidden`: se captura
    al abrir. Verificado que la prueba falla con el comportamiento anterior. Límite aceptado: el valor
    oculto viaja por el navegador; alterarlo solo desactiva el aviso de conflicto de quien lo altera.
  - `DatabaseSeeder` (solo local): el usuario de desarrollo recibe todos los permisos (roles: 1.4).
  - `ConfiguracionPanelTest` (14). Cada archivo de pruebas pasa por separado. `composer check`: 254.
- 2026-10-10 — Paso 9. Actualizados: `base-de-datos.md` (tipos de cambio sensibles; sección «Catálogos y
  datos maestros»), `codigo.md` (`ErrorDeNegocio`, bloqueo optimista, qué ofrece el núcleo, `AccionFilament`
  con `deControl()`, permisos automáticos, registro de pantallas, factories con `#[UseFactory]`, funciones
  globales de Pest), `pendientes.md` (TC sin publicación, TC automático, líneas de solución, tipos de
  trabajo facturables), `despliegue.md` (`NucleoSeeder` en cada despliegue; ubigeos y salida HTTPS),
  `ARCHITECTURE.md` (`Console/`, `database/data/`), `/nueva-accion` y `setup.md`.
- 2026-10-10 — Revisión de arquitectura (paso 10): 1 bloqueante, 4 recomendados, 8 menores. Antes de
  atenderlos, el desarrollador decidió el **TC automático** (ADR 0012) y pidió ver lo demás después.
- 2026-10-10 — Paso 9b (ADR 0012).
  - Fuentes verificadas: SUNAT `a/txt/tipoCambio.txt` responde `10/10/2026|3.45|3.457|` (solo el día,
    también sábados); BCRP tiene historial pero es cierre SBS (descartado como respaldo: no se confirmó
    equivalencia). Elegido SUNAT; registro manual como respaldo.
  - `Nucleo/TipoCambio/`: interfaz `FuenteTipoCambio`, `FuenteSunat` (connect 10 s, total 20 s; formato,
    fecha válida, 4 decimales con bcmath), `LecturaTipoCambio`. `FuenteTipoCambioNoDisponible` (`ErrorDeNegocio`).
  - `TipoCambioService::sincronizar()`: consulta fuera de la transacción; decide **con la fila bloqueada**
    (no pisa manual; sin cambios; actualiza si SUNAT corrige). `registrar()` y `sincronizar()` comparten
    `guardar()`, que **reintenta ante `UniqueConstraintViolationException`** (atiende el bloqueante 1 de la
    revisión: dos registros simultáneos del mismo día ya no dan 500). `where('fecha')` en vez de
    `whereDate` (menor 7). Enum `ResultadoSincronizacion`.
  - Comando `nucleo:sincronizar-tipo-cambio`, programado 08:30/12:00/18:00 Lima con `withoutOverlapping`
    desde el provider (`callAfterResolving(Schedule)`); falla → `Log::warning` sin datos sensibles (alerta
    real en 1.10). Acción `nucleo.sincronizar-tipo-cambio` («Traer de SUNAT ahora», permiso
    `tipos_cambio.registrar`) y botón en la pantalla; «Registrar a mano» queda como secundario.
  - `tipo_cambio.dias_tolerancia` inicial = 4. Base de desarrollo: actualizado a 4 por el servicio y
    sincronizado con SUNAT real (10/10: 3.4500 / 3.4570; consulta del 13/10 usa la del 10/10).
  - Pruebas: `TipoCambioAutomaticoTest` (13; un solo `Http::fake` con respuesta reemplazable, porque un
    segundo `fake` se suma detrás del primero), colisión simultánea en `TipoCambioServiceTest` (verificado
    que falla sin el reintento). `composer check` en verde: 269 pruebas.
- 2026-10-10 — Revisión atendida (aprobada una por una por el desarrollador): #2 descarga de ubigeos con
  connect 10 s / total 30 s y aviso claro; #3 `RegistroPantallas` por panel (Filament aplica
  `configureUsing` antes de que el panel tenga id, así que no se podía filtrar ahí) y `AppPanelProvider`
  toma solo las de `app`; #4 `Http::preventStrayRequests()` global en `tests/Pest.php` (quitado de cada
  archivo); #5 `canCreate/canEdit/canDelete/canDeleteAny = false` en los dos Resources; #6 columnas de
  búsqueda escapadas con `wrap()`; #8 ubigeos bloquea y después compara; #9 `parametros.descripcion` NOT
  NULL (migración editada; en la base de desarrollo, `ALTER … SET NOT NULL` sin `migrate:fresh`);
  #12 «Corregir» con moneda y fecha fijas desde la fila; #13 tope de 10 MB (cabecera `on_headers` y cuerpo).
  No se cambian: #10 (CHECK no detecta 2026-02-30; lo rechaza el servicio) y #11 (índice redundante que
  exige el script). Ya atendidos en 9b: #1 y #7. Pint convierte `Resource` en `resource` en PHPDoc
  (pseudotipo): alias `RecursoFilament`. `composer check` en verde: 277 pruebas.
- 2026-10-10 — Paso 10. `/cerrar-tarea` completo, revisión de `revisor-arquitectura` atendida.
  `composer check` en verde: 277 pruebas. Plan terminado.

## Qué sigue (deuda)

- **1.2b** (adelantada desde la etapa 2 por pedido del desarrollador): edición de todos los catálogos y de
  valor y descripción de parámetros desde la pantalla. Plan: `completados/2026-10-10-nucleo-edicion-catalogos.md`.
- **1.3:** `tipos_documento_identidad` con `parties` (D1); pestaña de historial (lee `HistorialCambiosService`).
- **1.4:** roles que reciben `catalogos.ver`, `parametros.editar`, `tipos_cambio.registrar`,
  `ubigeos.actualizar`; morph map antes de datos reales (incluye `Parametro` y `TipoCambio` en la bitácora).
- **1.6:** `series_documento` (D1).
- **1.10:** alerta cuando falle la sincronización del TC (hoy `Log::warning`) o falte el TC más allá del margen.
- **Negocio (en `pendientes.md`):** lista real de líneas de solución; tipos de trabajo facturables; confirmar
  con el contador el margen de 4 días (ADR 0012).
- **TI:** salida HTTPS a `www.sunat.gob.pe` y `www.datosabiertos.gob.pe`; programador activo; `NucleoSeeder`
  en cada despliegue (`despliegue.md`).
