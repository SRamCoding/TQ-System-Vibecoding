# Plan: Núcleo para Cuentas (2.20a)

- **Estado:** terminado (fusionado en `main` el 2026-11-05, PR #79)
- **Responsable:** Sebastián (el núcleo lo decide el desarrollador, ADR 0020; modo actual de AGENTS.md)
- **Módulo(s):** Nucleo
- **Fase:** F1
- **Creado:** 2026-11-05 · **Actualizado:** 2026-11-05

## Objetivo

Está en `main` todo lo del núcleo que necesita Cuentas (2.20, `docs/planes/completados/2026-11-04-crm-cuentas.md`), con
sus pruebas: el aviso de nombres parecidos, los tres catálogos (uno cerrado), el rubro de la ficha, la regla de «lo
propio», las pestañas de la ficha por tipo de documento y el registro de permisos de módulo con el prefijo de área.
Se fusiona antes que la 2.20, que solo usa lo que ya está en `main`.

## Contexto que leí

- Las decisiones D2, D4, D5, D7, D13 y D14 del plan 2.20, aprobadas por el desarrollador el 2026-11-05 (están en la
  rama `crm-cuentas` hasta que se fusione; lo necesario está copiado abajo).
- `PartiesService` (`registrar`, `editar`, `documento`), `PosiblesDuplicados`, `CalidadPartiesService`; el enum
  `Catalogo` (`codigosDelSistema`, `esDelSistema`) y ADR 0013; `SeguridadService` (`puede`, `ambitosDonde`,
  `permisos_por_ambito` con un solo tipo, `unidad_organizativa`); `PermisosSeeder::DE_LECTURA` y
  `PermisosLegibles`; `PestanaFicha` y `RegistroPestanasFicha`; las migraciones de `unaccent` y `btree_gist`.
- Pendientes: ninguno bloquea. «Consulta de RUC y DNI» sigue pendiente (por eso el rubro no se precarga).

## Decisiones

Aprobadas por el desarrollador el 2026-11-05, dentro de las del plan 2.20 (se cita la de origen).

- **N1 · Nombres normalizados y nombres parecidos (2.20 D2).**
  - `parties.nombre_normalizado` (la razón social con `PosiblesDuplicados::normalizar`) y
    `parties.nombre_comercial_normalizado` (el nombre comercial normalizado; nula sin nombre comercial), cada una con
    su índice GIN de trigramas (`pg_trgm`, activada con una migración, como `unaccent` y `btree_gist`). Las llena el
    modelo al guardar; una migración las completa para las fichas existentes.
  - Comentario de las columnas: dentro del núcleo, la razón social y el nombre comercial solo se escriben por el
    modelo (nada de `update` masivo ni `DB::table('parties')`), porque el modelo llena las columnas.
  - Comando que las recalcula en todas las fichas, con `--simular`. Regla, escrita también en el comentario de
    `PosiblesDuplicados::normalizar`: si cambia la normalización, se corre ese comando en el mismo PR.
  - Prueba de coherencia: para cada ficha, las dos columnas iguales a la normalización de su campo (nula sin nombre
    comercial), también tras editar: la razón social cambia solo la primera; el nombre comercial, solo la segunda;
    borrarlo deja la segunda nula.
  - `PartiesService::posiblesPorNombre(string $nombre): list<CoincidenciaNombre>` (ficha como `ResumenParty`, contra
    qué coincidió, mismo nombre o parecido, cuánto). Compara contra las dos columnas; una ficha aparece una vez, con
    su mejor coincidencia (mismo nombre antes que parecido; entre parecidos, el mayor; a igual, la razón social).
    Dentro de su transacción, `SET LOCAL pg_trgm.similarity_threshold` con el parámetro; filtra con `%` y ordena por
    `similarity` descendente.
  - Parámetro `parties.umbral_nombre_parecido` (decimal), inicial **0.70**, el mismo para las dos columnas (medido
    con nombres inventados: errores de tipeo y variaciones pequeñas ≥ 0.78; entidades distintas con palabras comunes
    ≤ 0.64).
  - La portada de Personas y empresas no cambia: sigue comparando solo la razón social.
  - Las pruebas usan nombres inventados.
- **N2 · Catálogo cerrado (2.20 D5).** Hoy `Catalogo` impide desactivar o borrar los códigos del sistema, pero no
  impide crear filas. Modo «cerrado» por catálogo: desde la pantalla no se crea ni se borra; solo se edita el nombre.
  Con su prueba.
- **N3 · Tres catálogos nuevos (2.20 D4 y D5),** casos de `Catalogo`, editables en Configuración → Catálogos:
  - **«Orígenes comerciales»** (`origenes_comerciales`), común a cuentas, leads y oportunidades; reemplaza a
    `origenes_lead` en el DBML. Siembra: Convocatoria pública, Convocatoria privada, Referencia, Feria, Sitio web,
    Visita en frío. Sin «Otro».
  - **«Niveles de gobierno»** (`niveles_gobierno`): Gobierno local, Gobierno regional, Gobierno nacional.
  - **«Rubros»** (`rubros`): las 21 secciones de la CIIU Rev. 4 (A a U), la que SUNAT usa en el RUC. Código: la
    letra; nombre: el oficial abreviado. Cerrado (N2), con las 21 como códigos del sistema. Archivo de datos con su
    `*.fuente.json` (INEI, CIIU Rev. 4 adaptada al Perú), como los ubigeos.
- **N4 · El rubro de la ficha (2.20 D5).** `parties.rubro_id` (FK a `rubros`, nullable). Se cambia con la acción
  propia `nucleo.parties.cambiar-rubro` (`PartiesService::cambiarRubro(id, ?rubroId, versionLeida)`, permiso
  `parties.cambiar-rubro`): `editar` reemplaza todos los datos generales, y así un ejecutivo corrige el rubro sin
  poder editar la razón social. `DatosParty` suma el rubro para `registrar`. La ficha de Personas y empresas lo
  muestra y lo cambia.
- **N5 · Lo propio y lo de todos (2.20 D7).** `SeguridadService::puedeSobrePropio(User $usuario, string
  $permisoTodas, string $permisoPropias, int $responsableUserId): bool` (tiene el de todas, o el de propias y es el
  responsable) y su pareja `filtrarPropios(...)`, que restringe un `Builder` a las filas donde el usuario es el
  responsable salvo que tenga el de todas. No toca `permisos_por_ambito` ni la pantalla de Roles y permisos. Tener
  los dos es tener el de todas, no un cruce de funciones. Convención en `docs/convenciones/codigo.md` («Lo propio y
  lo de todos»). Si un día los ámbitos admiten «propios», solo cambian estos dos métodos.
- **N6 · Pestañas de la ficha por tipo de documento (2.20 D13).** `PestanaFicha` suma una condición declarativa
  opcional, `tiposDocumento: list<string>|null` (null = todas), que `RegistroPestanasFicha` respeta al armar las
  pestañas de una ficha. Con su prueba. Cuentas la usará con `[Catalogo::RUC]` (código `'6'`).
- **N7 · Prefijo de área en los permisos de negocio (2.20 D14).** Regla para todos los módulos de negocio: permisos
  y acciones `<area>.<modulo>.<verbo>` (`crm.cuentas.ver`, `erp.compras.orden.anular`); el núcleo sigue sin prefijo
  (`parties.ver`). Roles y permisos agrupa los de negocio por los dos primeros tramos («CRM · Cuentas», «ERP ·
  Compras»). Convención en `docs/convenciones/codigo.md`; va aquí para que las demás sesiones la tengan antes de
  nombrar sus permisos. Registrada, con N5, en el **ADR 0021** (cruza las cuatro áreas).
- **N8 · Registro de permisos de módulo (2.20 D14).** Cada módulo declara en su provider los permisos que no son
  acciones (`crm.cuentas.ver`, `crm.cuentas.atender`), con nombre legible y grupo, y puede dar nombre legible a los de
  sus acciones. `PermisosSeeder` los crea y `PermisosLegibles` los muestra; `DE_LECTURA` pasa a usar el registro.

- **N9 · Un ícono no significa dos cosas (desarrollador, 2026-11-05).** Prueba en `tests/Arch/IconosUnicosTest.php`
  sobre cajas, pantallas, pestañas y catálogos: el mismo concepto puede repetir su ícono (lista explícita de grupos,
  cada uno con lo que representa); un repetido con conceptos distintos que ya existía no se cambia: queda como deuda de
  la mejora visual, en su propia lista. Regla en `interfaz.md`, «Íconos».

## Pasos

- [x] 1. Fila del mapa, plan, rama; DBML (parties, los tres catálogos, sin `origenes_lead`) y convenciones (N5, N7)
- [x] 2. Registro de permisos de módulo (N8) y agrupación por área y módulo en Roles y permisos (N7)
- [x] 3. `puedeSobrePropio` y `filtrarPropios` (N5), con sus pruebas
- [x] 4. Modo cerrado (N2) y los tres catálogos con su siembra y la fuente de la CIIU (N3)
- [x] 5. `parties.rubro_id`, la acción `nucleo.parties.cambiar-rubro` y el rubro en la ficha (N4)
- [x] 6. `pg_trgm`, las dos columnas normalizadas con su comando, `posiblesPorNombre` y su parámetro (N1)
- [x] 7. `tiposDocumento` en `PestanaFicha` (N6)
- [x] 7b. Prueba de íconos (N9): grupos del mismo concepto y deuda; los íconos existentes no se tocan
- [x] 8a. Revisor de arquitectura, y lo que marcó (ver la bitácora)
- [x] 8b. `composer check` en verde (2388 de 2388, 2026-11-05)
- [x] 8c. PR #79, fusionado por el desarrollador
- [ ] Antes de producción: cotejar los 21 nombres oficiales de `rubros-ciiu.php` con la publicación del INEI

## Preparado para lo diferido

- **Ámbito «propios»:** si los ámbitos del núcleo lo admiten algún día, solo cambian `puedeSobrePropio` y
  `filtrarPropios`.
- **Rubro desde el padrón:** SUNAT devuelve la clase CIIU de cuatro dígitos. Cuando se decida «Consulta de RUC y
  DNI», la ficha guardará la clase y la sección se derivará de ella; el padrón (o la IA) sugiere y una persona
  confirma. Hoy solo se guarda la sección.

## Criterio de aceptación

- Pruebas de cada pieza: coherencia de las columnas normalizadas y del comando (`--simular` no escribe);
  `posiblesPorNombre` (mismo nombre, parecido, nombre comercial, una sola vez por ficha, umbral del parámetro);
  catálogo cerrado (sin crear ni borrar, sí renombrar); `cambiarRubro` con versión y permiso; `puedeSobrePropio` y
  `filtrarPropios` (todas, propias, ninguna, las dos); pestañas por tipo de documento; permisos de módulo creados y
  agrupados.
- `composer check` en verde.

## Bitácora

- 2026-11-05 — Rama `nucleo-para-cuentas` desde `main` (f012412). Separada de la 2.20 por pedido del desarrollador:
  se fusiona primero.
- 2026-11-05 — Paso 2: `Seguridad/RegistroPermisos` (`declarar`, `nombrar`, `agrupar`); `PermisosSeeder` crea los
  declarados y ya no tiene `DE_LECTURA` (el núcleo declara sus cinco permisos de lectura en su provider);
  `PermisosLegibles` toma el nombre del registro y agrupa los de negocio por área y módulo («CRM · Cuentas»; el nombre
  derivado se puede reemplazar con `agrupar`). `PermisosConAreaTest` vigila que las acciones y transiciones de `Crm`
  y `Erp` lleven su área delante: va en `tests/Modules/Nucleo` y no en `tests/Arch` porque necesita el contenedor
  con los registros llenos. phpstan del proyecto no analiza `tests/` (solo las fixtures).
- 2026-11-05 — Paso 3: `SeguridadService::puedeSobrePropio` y `filtrarPropios` (con el de todas no filtra; con el de
  propias, `where columna = usuario`; sin ninguno, `false`). Solo cuentan los roles globales, como `puede()` sin
  ámbito. `SeguridadPropiosTest`: las cuatro combinaciones en los dos métodos y la cuenta desactivada.
- 2026-11-05 — Paso 4: tablas `origenes_comerciales`, `niveles_gobierno` y `rubros` (una migración; `CHECK` de versión,
  de nombre no vacío y de la letra A a U en `rubros`), modelos con fábrica y alias del morph map, casos de `Catalogo`
  y siembra que no pisa lo editado. Las 21 secciones viven en `database/data/rubros-ciiu.php` (letra => [abreviado,
  oficial]) con su `rubros-ciiu.fuente.json`; **falta cotejar los nombres oficiales con la publicación del INEI**
  antes de producción. `Catalogo::esCerrado()` (solo Rubros): `EdicionCatalogosService` rechaza crear y borrar, y la
  pantalla no muestra «Agregar»; desactivar ya lo impide ser código del sistema. Grupos: «Orígenes comerciales» en
  Comercio y pagos; «Rubros» y «Niveles de gobierno» en Personas y lugares. Íconos: `inbox-arrow-down`,
  `globe-americas` y `view-columns` (este último, provisional: las secciones como columnas; se cambia si no se
  entiende solo). `EdicionCatalogosServiceTest` excluye los cerrados de su prueba genérica. Ya existía una prueba de
  íconos únicos entre catálogos, módulos y pantallas (`CatalogosPantallaTest`); el paso 7b la amplía a las pestañas.
- 2026-11-05 — Paso 5: `parties.rubro_id` (FK con índice a `rubros`), `PartiesService::cambiarRubro` y la acción
  `nucleo.parties.cambiar-rubro` (permiso `parties.cambiar-rubro`, «Cambiar el rubro»); `DatosParty::$rubroId` para
  `registrar`. El rubro elegido debe estar activo; el que la ficha ya tiene se conserva aunque no lo esté. La ficha
  muestra «Rubro (CIIU)» con su letra y el botón «Rubro». El alta de Personas y empresas (`NuevaParty`) no pide el
  rubro: se agrega después desde la ficha, y Cuentas lo pasa por `DatosParty`. `RubroPartyTest` (6).
- 2026-11-05 — Paso 6: migración con `pg_trgm`, las dos columnas (completadas para las fichas existentes con la misma
  función, el único lugar que escribe `parties` sin el modelo; luego `nombre_normalizado` pasa a `NOT NULL`), índices
  GIN de trigramas y el comentario de cada columna con la regla. El modelo las llena en `saving`
  (`Party::normalizarNombres`) y no van a la bitácora (se deducen). `PosiblesDuplicados::normalizarOpcional` para el
  nombre comercial. Comando `nucleo:recalcular-nombres {--simular}`: guarda con `saveQuietly` (sin versión ni
  bitácora: no cambió ningún dato). Parámetro `parties.umbral_nombre_parecido` (decimal, 0.70, de 0.01 a 1).
  `PartiesService::posiblesPorNombre(nombre, limite = 10)`: `set_config('pg_trgm.similarity_threshold', ?, true)` (el
  `SET LOCAL` con el valor como parámetro), `%` o igualdad en cada columna, `DISTINCT ON (id)` con la mejor
  coincidencia; empates, por id. Devuelve `CoincidenciaNombre` (ficha, `CampoNombre`, mismo nombre, parecido) con
  `aviso()`. Dos pruebas viejas insertaban parties a mano y ahora dan el nombre normalizado (`PartiesServiceTest`,
  `PartiesIntegridadTest`): el `NOT NULL` es la protección buscada. `NombresParecidosTest` (6).
- 2026-11-05 — Paso 7: `PestanaFicha::$tiposDocumento` (lista de códigos o null; vacía o con un código en blanco es un
  error) y `aplicaA(codigo)`. La ficha la muestra si se tiene el permiso **y** aplica a su tipo de documento. El
  componente sigue revisando solo el permiso en cada petición: el tipo decide cuándo se muestra, no quién la ve, y en
  `boot()` todavía no está `partyId`. Dos pruebas nuevas en `PestanasFichaTest`.
- 2026-11-05 — Paso 7b: `IconosUnicosTest` lee el código (cajas, `$navigationIcon`, `Tab::make()->icon()`,
  `PestanaFicha` y `Catalogo::icono()`). Primero con excepciones; el desarrollador pidió cero repetidos, así que se
  cambiaron los que había:
  - pestaña «Datos» de Personas y empresas, de `identification` a `clipboard-document-list`; la de Usuarios, a
    `user-circle`; la de Organigrama, a `information-circle` (`identification` queda para la pantalla Mis datos);
  - pestaña «Tipos» (Personas y empresas), de `tag` (el de CUBSO) a `bookmark`;
  - pestaña «Contactos», de `users` (el de la pantalla Usuarios) a `phone`;
  - pestaña «Sedes», de `map-pin` (el de Ubigeos) a `home-modern`;
  - pestaña «Redes», de `tq-redes-sociales` (el del catálogo) a `share`;
  - pestaña «Roles por unidad» (Usuarios), de `building-office-2` (el de Organigrama) a `building-office`;
  - pantalla «Vista previa de fechas» (modo desarrollo), de `sparkles` (el de Inteligencia artificial) a `play`.
  Fuera del alcance de la prueba: los íconos de los botones de acción («Editar» con `pencil-square` en todas partes;
  `briefcase` en la pestaña «Puestos» y en el botón «Cambiar puesto»).
- 2026-11-05 — Revisión de arquitectura. **Bloqueaba:** N7 (y N5) cruzan áreas y no tenían ADR → ADR 0021, con las
  dos reglas y su porqué; `codigo.md` lo cita. **Sugerencias aplicadas:** prueba `tests/Arch/PartiesSoloPorElModeloTest`
  (nada en `app/` escribe `parties` sin el modelo, salvo la migración de los nombres); `nucleo:recalcular-nombres` con
  una transacción y la fila bloqueada por ficha, sin tocar `updated_at`; las columnas normalizadas pasan a `text`
  (`Str::ascii` puede alargar el texto: ß → ss); `posiblesPorNombre` sin el `OR =` que sobraba (el mismo nombre da 1)
  y con la nota de que `set_config(..., true)` dura hasta el final de la transacción de nivel superior; una muestra
  fija de la normalización en `NombresParecidosTest` (si falla, se corre el comando en el mismo PR); docblocks y
  comentarios que habían quedado fuera de lugar. **No se cambió:** que el rubro esté activo lo revisa PHP (la FK
  garantiza que existe; el catálogo es cerrado y sus códigos no se desactivan). El cotejo de la CIIU con el INEI
  queda como casilla.
- 2026-11-05 — Primera corrida de `composer check`: 2386 de 2388. `CatalogosSeederTest` tenía la lista de permisos
  escrita (faltaba `parties.cambiar-rubro`); `IaAccionesTest` guarda a propósito un nombre comercial numérico antes de
  que la IA lo rechace, y `Party::normalizarNombres` exigía texto: ahora convierte a texto lo que llegue.
- 2026-11-05 — Segunda corrida de `composer check`: pint, phpstan, migraciones y 2388 de 2388 pruebas en verde.
- 2026-11-05 — Íconos, corrección del desarrollador: había pedido no cambiar los repetidos que ya existían. Se revirtieron
  los cambios de f612eb4 a los íconos existentes (pestañas «Datos», «Tipos», «Contactos», «Sedes», «Redes», «Roles por
  unidad» y la vista previa de fechas); los nuevos de esta tarea se quedan. La prueba ahora tiene dos listas:
  - **Mismo concepto (permitido):** `identification` (los datos de una ficha: pestañas «Datos» de Personas y empresas,
    Usuarios y Organigrama, y la pantalla Mis datos), `tq-redes-sociales` (catálogo y pestaña «Redes»),
    `building-office-2` (pantalla Organigrama y pestaña «Roles por unidad»).
  - **Deuda de la mejora visual (Sebastián, al final), conceptos distintos:** `map-pin` (catálogo Ubigeos y pestaña
    «Sedes» de Personas y empresas), `tag` (catálogo CUBSO y pestaña «Tipos»), `users` (pantalla Usuarios y pestaña
    «Contactos»), `sparkles` (pantallas Inteligencia artificial y Vista previa de fechas).
- 2026-11-05 — `main` trajo la 2.1 (Presupuesto) y el cambio de AGENTS.md (los agentes no abren ni fusionan PR). Merge
  con dos conflictos resueltos: el mapa (las filas de las dos áreas) y la lista de permisos de `CatalogosSeederTest`
  (la de `main` más `parties.cambiar-rubro`). La 2.1 tiene diez nombres sin el prefijo del ADR 0021; el desarrollador
  eligió una **excepción congelada** en `PermisosConAreaTest` (`PERMISOS_SIN_AREA_DE_LA_2_1`, que no crece y falla si un
  nombre ya no existe, para vaciarse sola) y la tarea 2.1b del área 1 para renombrarlos, incluidas las claves de
  documento, antes del primer despliegue con datos reales. Anotado en el mapa y en el ADR 0021.
- 2026-11-05 — Fusionado (PR #79). Plan a `completados`. Quedan abiertos: el cotejo de la CIIU con el INEI, el ícono provisional de «Rubros» y la tarea 2.1b.
