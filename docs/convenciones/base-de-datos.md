# Convenciones de base de datos

Fuente: especificación v4, documento 06 y decisiones del desarrollo. Si un fragmento de código de
`docs/diseno/` contradice este archivo, **gana este archivo**.

## Nombres

| Qué | Regla | Ejemplo |
|---|---|---|
| Tablas de dominio | español, plural, `snake_case` | `ordenes_compra`, `partidas_control` |
| Tablas de framework y paquetes | nombre original | `users`, `jobs`, `failed_jobs`, `roles` |
| Llave primaria | `$table->id()` **siempre** | — |
| Código de negocio | columna aparte, `unique` | `codigo`, `numero` |
| Llave foránea | `<tabla_singular>_id`, **siempre con índice** | `partida_control_id` |
| Booleanos | prefijo `es_` o `requiere_` | `es_activo` |
| Fechas | `_at` para marcas de tiempo; `fecha_` para fechas de negocio | `aprobado_at`, `fecha_entrega` |

**Prohibido:** `empresa_id` en cualquier tabla (empresa única, ADR 0003), `float`/`double`, `char`,
binarios, código de negocio como PK, triggers y procedimientos con lógica.

## El modelo de datos

`docs/diseno/techquk_modelo.dbml` (v2) ya usa tipos de PostgreSQL. Se traduce casi literal:

| En el DBML | En la migración |
|---|---|
| `bigint [pk, increment]` | `$table->id()` |
| `bigint [ref: > x.id]` | `$table->foreignId('x_id')->index()->constrained('xs')` (+ `->nullable()` si no dice `not null`) |
| `varchar(n)` / `text` / `boolean` / `date` / `jsonb` | `string('c', n)` / `text` / `boolean` (con default) / `date` / `jsonb` |
| `timestamptz` | `timestampTz` |
| `decimal(p,s)` | `decimal('c', p, s)` |

Las tablas del grupo `ZZ_Obsoletas_v4` **no se crean**. Las marcadas `[S]` son diseño propuesto:
revísalas con el flujo que las usa antes de migrarlas.

## Dinero y tiempo

- Dinero `decimal(14,2)`; tipo de cambio `decimal(10,4)`. Todo monto con su `moneda_id`.
- En PHP, montos como `string` + `bcmath` o la clase de dinero del núcleo. Nunca `float`.
- Fechas y horas en `timestamptz`; zona horaria de la aplicación `America/Lima`. Vencimientos,
  penalidades y plazos se calculan por **fecha de negocio en Lima**.

## Auditoría (M4)

Toda tabla de negocio lleva `$table->auditoria();` (macro del núcleo: `created_by` y `created_at`
obligatorios, `updated_by` y `updated_at`). Excepción solo con comentario:
`// harness:sin-auditoria <motivo>` (pivotes puras, tablas de framework).
Seeders, jobs y comandos usan el **usuario de sistema** (lo pone el trait `ConAuditoria`; los seeders
no usan `WithoutModelEvents`). Bitácora campo por campo (`auditoria_cambios`, trait `RegistraCambios`)
en tablas sensibles: parties, reglas de aprobación, presupuestos, partidas, órdenes, contratos,
parámetros, tipos de cambio, catálogos (vía `EsCatalogo`). `users` no lleva `auditoria()` (tabla de framework) pero sí `created_by`/`updated_by`;
el usuario de sistema es su propio autor.

## Integridad y concurrencia

- Si un dato importa, `NOT NULL`. Toda relación es FK declarada **con índice** (PostgreSQL no lo crea).
- **Unicidad en la base**, no solo en PHP (RUC, serie y número de comprobante, correlativos, una
  imputación por línea de origen). El código captura la violación y muestra un mensaje claro.
- **Bloqueo optimista:** los documentos editables (cotizaciones, presupuestos, contratos, proyectos,
  parámetros, catálogos) llevan `version integer not null default 1` con `CHECK (version >= 1)`; al guardar
  se compara y se incrementa. Toda tabla que se edite desde una pantalla la lleva.
- **Restricciones `CHECK` de integridad** sí se permiten (por ejemplo, `comprometido >= ejecutado`,
  una imputación con destino). Son integridad, no reglas de negocio.
- Estados: FK a `estados`, nunca `enum` ni texto libre.
- **Lo que se analiza se elige, no se escribe.** Todo dato que se vaya a analizar (motivos, tipos, categorías,
  canales, medios) se elige de un catálogo o de una lista fija; no se escribe libre. El texto libre solo va como
  comentario o detalle complementario, o como detalle obligatorio de la opción «Otro». Si existe un catálogo
  oficial (SUNAT, ISO), se usa ese. Las listas fijas (`enum` con CHECK) se usan cuando el sistema toma decisiones
  según el valor; los catálogos editables, cuando el valor solo describe o clasifica. Los campos del DBML que
  todavía son texto libre llevan la nota «PASA A CATÁLOGO» y se convierten al construir su módulo (plan 1.5, D9).

## Catálogos y datos maestros (M5, M9)

- Un elemento de catálogo **usado no se borra: se desactiva** (`es_activo`/`es_activa` con default `true`);
  los documentos viejos lo siguen mostrando. Lo que nunca se usó sí se borra: la llave foránea decide
  (ADR 0013). Su `codigo` es único en la base e inmutable.
- Toda tabla que apunte a un catálogo lo hace con FK: así queda protegido contra el borrado sin código extra.
- Los datos maestros los carga `NucleoSeeder` en **todo ambiente** (también producción, en cada
  despliegue). Se puede repetir y **solo crea lo que falta**: nunca pisa lo editado en la pantalla, tampoco
  en las listas oficiales (SUNAT, ISO) ni la descripción de un parámetro. Ubigeos: los actualiza la pantalla
  contra el INEI, que respeta los agregados a mano (`origen = manual`).
- **Excepción a la bitácora:** un seeder que siembra cientos de filas de un catálogo puede insertarlas en bloque con el
  usuario de sistema y sin bitácora de creación (plan 1.4b: `FrasesSaludoSeeder`, 300 frases que corren en cada prueba
  que carga el núcleo). Lo que alguien cambie después por la pantalla sí queda en la bitácora.
- Los parámetros los **declara cada módulo** en su provider (`RegistroParametros`); el seeder crea los que
  faltan y nunca pisa un valor cambiado. Cambiar el tipo de un parámetro existente exige migración.
- Archivos de datos públicos de un módulo en `app/Modules/<…>/database/data/`, con su fuente
  (`*.fuente.json`). Ubigeos: códigos **INEI** (los usa SUNAT; los del RENIEC son otros).

## Búsquedas

Extensión `unaccent` en la primera migración. Búsquedas humanas con el scope del núcleo
`buscarTexto($texto, $columnas)` (trait `BuscaSinAcentos`), que compara sin mayúsculas ni acentos y escapa
`%` y `_`. RUC y DNI se normalizan antes de guardar (`DocumentoIdentidad`) y un CHECK en la base lo
garantiza; el número se busca por prefijo con un índice `varchar_pattern_ops`, no con el scope.

## Migraciones

- Viven en `app/Modules/<Grupo>/<Modulo>/database/migrations/`. Toda migración tiene `down()`.
- **Hasta el primer despliegue con datos reales** se pueden editar y reconstruir la base
  (`migrate:fresh` pide confirmación). **Desde entonces**, una migración en `origin/main` no se edita:
  se crea otra (ADR 0010; el hook lo hace cumplir cuando existe `.claude/PRODUCCION`).
- Un módulo no referencia tablas de un módulo de una etapa posterior.
- `php scripts/verificar-migraciones.php` revisa: tipos prohibidos, PK, `empresa_id`, auditoría,
  `timestamps()`, y llaves foráneas sin índice (excepción: `// harness:sin-indice <motivo>`).
  También revisa `database/migrations` (framework y paquetes), sin exigir auditoría ni la forma de la PK.
