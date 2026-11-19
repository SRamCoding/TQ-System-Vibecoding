# Plan: Cuentas (CRM-01)

- **Estado:** terminada (2026-11-06), en `docs/planes/completados/`; rama `crm-cuentas` con `main` al día (2.2 incluida), lista para el PR después de la revisión visual del desarrollador
- **Responsable:** Sebastián (área 2, Comercial, del ADR 0020; modo actual de AGENTS.md)
- **Módulo(s):** `Crm/Cuentas` (nuevo). Lo del núcleo va en la 2.20a
- **Fase:** F1 (esqueleto y decisiones)
- **Creado:** 2026-11-04 · **Actualizado:** 2026-11-06

## Objetivo

Existe el módulo `Crm/Cuentas`: una cuenta se crea desde el RUC sin duplicados, siempre con su ejecutivo y su origen;
la lista y la ficha siguen los patrones del sistema, y `CuentasService` deja lista la cuenta para Oportunidades,
Cotizaciones y Licitaciones. Las pruebas de las reglas del v4 (RUC único, ejecutivo y origen obligatorios) pasan.

## Contexto que leí

- `AGENTS.md` (incluido «Modo actual»), `ARCHITECTURE.md` (módulos, estructura y reglas de dependencia).
- ADR 0020: área 2 (Comercial); C7 (las personas salen de `PartiesService`); reglas para todas. Cuentas no espera
  a ninguna otra área.
- `docs/analisis/bloques/crm-pantallas.md` §CRM-01 (13 pantallas) y su diseño en
  `docs/diseno/fuentes/crm-pantallas-2026-10-13/` (`Cuentas`, `CuentasPanel`, `Cuenta`, `CuentaNueva`, `Contactos`,
  `CuentaHistorial`, `CabCuenta`).
- v4 parte 2: lista de cuentas, crear cuenta, reglas del CRM («RUC obligatorio y único; no se crea una cuenta con RUC
  ya registrado; toda cuenta tiene ejecutivo y origen»; «nada se borra»; duplicados por RUC, nombre y correo).
- DBML v2, `CRM_01_Cuentas_y_contactos`: `cuentas`, `cuenta_verticales`, `cuenta_historial`.
- Núcleo: `PartiesService` (registrar, buscarPorDocumento, asignarRol), `ContactosPartyService` (rotación de contactos,
  plan 1.3b), `PosiblesDuplicados`, `FuentePadronContribuyentes` (`SinPadron`), `RegistroPestanasFicha`, `Catalogo`,
  `TablaHistorial`; `docs/convenciones/interfaz.md` §Patrones de pantalla y §Portada de Personas y empresas.
- Pendientes: ninguno bloquea. «Consulta de RUC y DNI» deja el padrón en `SinPadron` (nada se precarga hasta que se
  decida); «Unir fichas duplicadas» sigue sin unir (solo se avisa).

## Decisiones

Propuestas el 2026-11-04 y aprobadas una por una por el desarrollador el 2026-11-05, con sus ajustes. Lo que tocan
del núcleo lo construye la 2.20a (`docs/planes/completados/2026-11-05-nucleo-para-cuentas.md`): `nombre_normalizado`,
`nombre_comercial_normalizado` y `posiblesPorNombre` (D2); los catálogos «Orígenes comerciales», «Niveles de gobierno»
y «Rubros», con el modo cerrado (D4, D5); `parties.rubro_id` y `nucleo.parties.cambiar-rubro` (D5);
`puedeSobrePropio` y `filtrarPropios` (D7); `tiposDocumento` de `PestanaFicha` (D13); el prefijo de área de los
permisos y el registro de permisos de módulo (D14). Esta tarea solo los usa.

- **D1 · La cuenta es la ficha comercial de una party: una cuenta por ficha de Personas y empresas.** **Aprobada
  2026-11-05 por el desarrollador, con cuatro ajustes (ya incorporados).**
  - **Una cuenta por ficha:** índice único sobre `cuentas.party_id`. El RUC único ya lo garantiza `parties`, con su
    único por tipo y número de documento; la cuenta no lo repite.
  - **No se copia la identidad:** razón social, RUC, dirección, sedes y contactos se quedan en el núcleo y se leen
    de ahí (ADR 0020, C7). La cuenta guarda solo lo comercial: ejecutivo, origen, nivel de gobierno si es pública (D4), prospecto o
    cliente, y notas.
  - **Al crear la cuenta, la ficha recibe el tipo «Cliente»** (`PartiesService::asignarRol`), salvo que ya lo tenga
    vigente (`PartiesService::tieneRol`): entonces no se vuelve a asignar, porque `asignarRol` falla si el periodo se
    cruza (`party_roles_sin_cruce`). Si la ficha está inactiva, no se crea la cuenta: se pide reactivarla primero en
    Personas y empresas (`asignarRol` rechaza fichas inactivas).
  - **Persona natural:** solo puede tener cuenta con RUC (10); con DNI solo, no.
  - **Un prospecto también recibe el tipo «Cliente»**, a propósito: el catálogo lo define como «hay relación
    comercial: cuenta, cotizaciones o contratos». Si la cuenta es prospecto o cliente se guarda en la cuenta del CRM
    (D8), no en el tipo de la ficha.
  - **Descartado, varias cuentas por ficha** (por ejemplo, una por unidad ejecutora de un gobierno regional): no
    hace falta. Muchas unidades ejecutoras tienen RUC propio ante SUNAT: son otra ficha y tienen su propia cuenta.
    Las que no lo tienen se registran como sedes o contactos de la entidad. Agruparlas bajo su gobierno regional
    (una cuenta «padre») no está en el v4: queda en `pendientes.md` («Cuenta padre de unidades ejecutoras»).
- **D2 · Crear cuenta empieza por el RUC.** **Aprobada 2026-11-05 por el desarrollador, con ocho ajustes (ya
  incorporados).** El v4 dice que esta pantalla «decide si el CRM sirve a los dos años o es un basurero de
  duplicados»: abre con un solo campo y pide lo demás después.
  - **Paso 1, el RUC.** El núcleo lo valida con su dígito verificador (`PartiesService::documento()`). Cuatro casos:
    1. *La ficha existe y tiene cuenta:* «Esta cuenta ya existe», con su nombre, su ejecutivo y «Abrir la existente».
       No se crea nada.
    2. *La ficha existe, activa y sin cuenta* (proveedor, entidad): el paso 2 muestra sus datos sin editarlos (se
       corrigen en Personas y empresas) y pide solo lo comercial.
    3. *La ficha existe e inactiva:* «Esta ficha está inactiva; revisa sus datos antes de reactivarla en Personas y
       empresas», con enlace (D1). Puede haberse desactivado por un RUC equivocado (D9).
    4. *La ficha no existe:* se consulta el padrón (hoy `SinPadron`, no precarga nada) y el paso 2 pide la razón
       social. **Mientras no haya padrón, el paso 2 muestra el RUC escrito en grande y pide confirmarlo antes de
       «Crear»**: atrapa el error de tipeo que el dígito verificador deja pasar.
  - **Paso 2, lo comercial:** pública o privada, nivel de gobierno si es pública, ejecutivo, origen, rubro (de la
    ficha, D5) y notas. No
    pide correo. «Crear» guarda todo en una sola transacción: si algo falla, no queda nada a medias. En el caso 4,
    la ficha se crea con `PartiesService::registrar($datos, ['cliente'])` dentro de esa transacción (no con
    `obtenerOCrear`, que no asigna tipos); en el caso 2, el tipo «Cliente» se asigna según la D1.
  - **Pública o privada no se guarda en la cuenta** (sería copiar un dato del núcleo, contra la D1): la cuenta es
    pública si la ficha tiene el tipo «Entidad pública» (`entidad`). Si en el paso 2 se marca pública, se asigna ese
    tipo en la misma operación, sin fallar si ya lo tiene (`tieneRol` antes de `asignarRol`).
  - **Aviso por nombre igual o parecido, que nunca bloquea** (municipalidades provincial y distrital se parecen y
    son distintas). El CRM no usa `Nucleo\Parties\PosiblesDuplicados`; la D2 agrega al núcleo
    `PartiesService::posiblesPorNombre(string $nombre): list<CoincidenciaNombre>`. Cada `CoincidenciaNombre` lleva
    la ficha (`ResumenParty`), contra qué coincidió (razón social o nombre comercial), si es el mismo nombre o uno
    parecido, y cuánto se parece:
    - compara el nombre escrito, normalizado (`PosiblesDuplicados::normalizar`: sin tildes, mayúsculas, puntuación
      ni forma societaria), contra la **razón social** y contra el **nombre comercial** de cada ficha (desarrollador,
      2026-11-05). Una ficha aparece **una sola vez, con su mejor coincidencia**: mismo nombre antes que parecido y,
      entre parecidos, el mayor parecido (a igual parecido, la razón social). Primero las de mismo nombre y luego las
      parecidas, de mayor a menor;
    - el parecido usa la extensión `pg_trgm` de PostgreSQL, activada con una migración del núcleo (como `unaccent` y
      `btree_gist`). Para no repetir en SQL la normalización de PHP, `parties` guarda dos columnas (aceptadas por el
      desarrollador, 2026-11-05), cada una con su índice GIN de trigramas y llenadas por el modelo al guardar con la
      misma `PosiblesDuplicados::normalizar`; una migración las completa para las existentes:
      - `nombre_normalizado`: la razón social normalizada;
      - `nombre_comercial_normalizado`: el nombre comercial normalizado, nula si la ficha no tiene nombre comercial.
    - **La portada de Personas y empresas no cambia:** sigue comparando solo la razón social (`CalidadPartiesService`).
      El nombre comercial entra solo en el aviso de crear cuenta.
    - Resguardos de las dos columnas:
      - **Comando del núcleo que las recalcula** en todas las fichas, con `--simular` (dice cuántas cambiarían sin
        escribir). **Regla:** si cambia la normalización, se corre ese comando en el mismo PR. Va escrita aquí y en
        el comentario de `PosiblesDuplicados::normalizar`.
      - **Prueba de coherencia:** para cada ficha, `nombre_normalizado === PosiblesDuplicados::normalizar(razon_social)`
        y `nombre_comercial_normalizado` igual a la normalización del nombre comercial (nula sin él). También después
        de editar: la razón social cambia solo la primera; el nombre comercial, solo la segunda; borrar el nombre
        comercial la deja nula.
      - **Comentario de las columnas:** dentro del núcleo, la razón social y el nombre comercial solo se escriben por
        el modelo (nada de `update` masivo ni `DB::table('parties')`), porque el modelo es quien llena las columnas.
    - **`posiblesPorNombre` usa los índices:** dentro de su transacción, `SET LOCAL pg_trgm.similarity_threshold` con
      el parámetro; filtra con el operador `%` sobre cada columna y ordena como se dijo arriba, por `similarity`
      descendente;
    - el umbral es un parámetro del núcleo, `parties.umbral_nombre_parecido` (decimal), con valor inicial **0.70**,
      el mismo para las dos columnas.
      Medido con nombres inventados: «…distrital de pimentel» y «…de pimentell», 0.92; «gobierno regional
      lambayeque» y «…de lambayeque», 0.91; «ferreteria el norte» y «ferreteria del norte», 0.78 (todas avisan).
      En cambio, «…distrital de pimentel» y «…distrital de reque», 0.63, y «…distrital» y «…provincial de
      pimentel», 0.61 (no avisan: las palabras comunes las acercan sin ser la misma). Una abreviatura
      («muni distrital pimentel», 0.64) no avisa: es el costo de no llenar el aviso de entidades distintas;
    - el aviso dice contra qué coincidió («mismo nombre», «razón social parecida», «mismo nombre comercial»,
      «nombre comercial parecido») y permite abrir cada ficha;
    - las pruebas del aviso usan nombres inventados, no las entidades reales de los ejemplos del umbral.
    - Persona natural: además, el aviso de DNI ↔ RUC 10 que el núcleo ya da (`PartyRegistrada::posibleMismaPersona`).
  - **El correo:** la coincidencia por correo del v4 se revisa al convertir un lead (CRM-07, campañas y leads), no
    al crear la cuenta.
  - **Dos personas a la vez, dos carreras**, las dos traducidas al mensaje del caso 1 y las dos en la prueba de
    concurrencia (procesos reales, `tests/Concurrencia`): en el caso 2, el choque en el único de `cuentas.party_id`;
    en el caso 4, el choque en `parties` (`PartyRechazada::duplicada`, desde `registrar`).
  - **Dónde vive:** en `CuentasService` y la acción registrada `crm.cuentas.crear`, ejecutable sin pantalla; la
    pantalla en dos pasos solo la llama.
  - **Descartado:** un formulario único con todos los campos (invita a escribir la razón social antes de saber si
    existe) y bloquear por nombre (hay entidades distintas con nombres casi iguales).
  - **Lo que cierra el problema y depende de la empresa** (regla 12; no se construye aquí):
    - *Consulta de RUC y DNI* (`pendientes.md`): validar contra el padrón de SUNAT es lo que más duplicados evita;
      cuando se decida, entra por `SinPadron` sin cambiar la pantalla, y la confirmación del RUC deja de hacer falta.
    - *Unir fichas duplicadas* y *DNI y RUC 10 de una persona* (`pendientes.md`): la red de seguridad para lo que se
      cuele. Las tres filas llevan la nota de que esta D2 las espera.
- **D3 · Pública o privada se lee del tipo «Entidad pública» de la ficha.** **Aprobada 2026-11-05 por el
  desarrollador, con tres precisiones (ya incorporadas).**
  - **Se quita `es_entidad_publica` de `cuentas`**, como ya anota el DBML («se deriva del rol entidad de la party»).
  - **Al crear la cuenta** (D2), marcar «Pública» asigna el tipo «Entidad pública» a la ficha. Es un efecto de
    `crm.cuentas.crear`: queda auditado con esa acción y no requiere ni otorga el permiso de editar tipos en Personas
    y empresas. **Después de creada la cuenta, solo se cambia en la pestaña «Tipos» de la ficha**; la ficha de la
    cuenta lo muestra y enlaza ahí, pero no lo edita.
  - **La regla «vigente» no se reescribe:** la lista, el filtro «Pública / Privada» y la cabecera usan el scope del
    núcleo `Party::conRolVigente('entidad', $dia)` a través de la relación con la party (permitido: regla 1 de
    dependencias de `ARCHITECTURE.md`), con `$dia = Calendario::hoy()`, en la misma consulta y sin una llamada por
    fila.
  - **A una fecha, no solo hoy:** `CuentasService::resumen(int $cuentaId, ?CarbonImmutable $fecha = null)` calcula
    `esPublica` a esa fecha (hoy si no se da) con `PartiesService::tieneRol`. Los tableros y reportes por pública o
    privada preguntan con la fecha de la oportunidad o de su cierre, no con hoy: cerrar el tipo después no
    reclasifica la historia.
  - **Nota para el plan de CRM-04:** si una convocatoria es pública o privada lo dice la convocatoria (SEACE, o el
    registro manual de las privadas), no `esPublica` de la cuenta. Anotada también en el mapa, en «Licitaciones
    privadas».
  - **El «Gobierno local» del diseño** («Tipo: Pública · Gobierno local») no es este dato: es el nivel de gobierno
    (D4).
  - **Descartado:** guardar una copia en la cuenta y sincronizarla (dos lugares para un dato terminan diciendo cosas
    distintas) y dejar que la cuenta lo cambie en los dos sentidos (el CRM administraría los tipos del núcleo).
- **D4 · El origen y el nivel de gobierno se eligen de catálogos del núcleo.** **Aprobada 2026-11-05 por el
  desarrollador: los orígenes como se propusieron; «Sectores» se separó en dos ejes (ya incorporado).**
  - **Por qué catálogo:** lo que se analiza se elige, no se escribe (`docs/convenciones/base-de-datos.md`); el
    `origen` del DBML llevaba «PASA A CATÁLOGO». **Catálogo editable y no lista fija**, porque solo describen: el
    sistema no decide nada según su valor.
  - **Dónde viven:** en el núcleo, como casos del enum `Catalogo`, editables en Configuración → Catálogos con las
    acciones genéricas, sus permisos, la bitácora y «lo usado no se borra» (ADR 0013); antecedente comercial:
    «Líneas de solución». `cuentas` apunta a ellos con llave foránea (regla 1 de dependencias).
  - **«Orígenes comerciales»**, obligatorio en toda cuenta (`cuentas.origen_comercial_id`, FK, `NOT NULL`).
    - **Un solo catálogo, compartido con los leads (CRM-07):** al convertir un lead, su origen pasa tal cual a la
      cuenta. Se elimina `origenes_lead` del DBML; `leads.origen_lead_id` y `oportunidades.origen_lead_id` apuntan
      al catálogo común. Anotado para los planes de CRM-07 y CRM-02 en el mapa («Orígenes comerciales»).
    - **Siembra:** Convocatoria pública, Convocatoria privada (las privadas se registran desde la etapa 2, mapa
      «Licitaciones privadas»), Referencia, Feria, Sitio web, Visita en frío.
    - **Sin «Otro»:** termina siendo el cajón de todo; si falta un origen, Administración lo agrega.
  - **«Niveles de gobierno»** (Gobierno local, Gobierno regional, Gobierno nacional), en la cuenta
    (`cuentas.nivel_gobierno_id`). Es el «Pública · Gobierno local» del diseño.
    - **Se pide y es obligatorio solo si la cuenta es pública** (D3: la ficha tiene el tipo «Entidad pública»
      vigente); oculto y nulo si es privada. Lo exige `CuentasService`, no la base: pública se deriva de
      `party_roles`, y un `CHECK` no puede mirar otra tabla.
    - **Si la ficha deja de ser pública después,** el valor se conserva, no se muestra y no es un error.
  - **La industria o rubro** (educación, salud, retail…), que aplica a públicas y privadas, **no se siembra aquí:**
    lo resuelve la D5, que decide si es un valor único (`cuentas.sector`) o los verticales múltiples
    (`cuenta_verticales`), para que no queden dos campos con la misma lista.
  - **Descartado:** un solo catálogo «Sectores» con niveles de gobierno e industrias (mezcla dos ejes: el Hospital
    Regional, ¿Gobierno regional o Salud?); catálogos propios del módulo (el mecanismo editable vive en el núcleo y un
    módulo no le suma casos); lista fija con `CHECK` (cada valor nuevo, una migración).
  - **DBML al día** (2026-11-05): `cuentas.origen_comercial_id` y `nivel_gobierno_id`, sin `origen` ni
    `es_entidad_publica` (D3); tablas `origenes_comerciales` y `niveles_gobierno` en M5; sin `origenes_lead`.
- **D5 · El rubro de la empresa: las secciones de la CIIU Rev. 4, un solo valor, en la ficha del núcleo.**
  **Aprobada 2026-11-05 por el desarrollador, con cuatro ajustes (ya incorporados).**
  - **Catálogo oficial «Rubros»:** las 21 secciones de la CIIU Rev. 4 (A a U), la que SUNAT usa en el RUC. La
    convención manda usar el catálogo oficial si existe; los valores de la nota del DBML (gobierno, educación,
    salud, retail) caben en O, P, Q y G. Código: la letra oficial; nombre: el oficial abreviado (por ejemplo,
    «Q · Salud y asistencia social»). Fuente en `*.fuente.json` (INEI, CIIU Rev. 4 adaptada al Perú), como los
    ubigeos.
  - **Es un dato de la empresa, no de la cuenta** (D1: lo de la empresa vive en el núcleo; SUNAT lo registra por
    RUC): `parties.rubro_id` (FK a `rubros`, nullable), no en `cuentas`.
    - Se ve y se edita en la ficha de Personas y empresas y desde la ficha de la cuenta, siempre por el núcleo. Como
      `PartiesService::editar` reemplaza todos los datos generales que recibe, el cambio de rubro es una acción
      propia, `nucleo.parties.cambiar-rubro` (`PartiesService::cambiarRubro(id, ?rubroId, versionLeida)`), con su
      permiso: un ejecutivo puede corregir el rubro sin poder editar la razón social ni la dirección. No es un tipo
      con periodos: cambia sin historia propia, con la bitácora.
    - Al crear la cuenta en el caso 4 (D2), se guarda en la ficha dentro de la misma transacción (`DatosParty`
      suma el rubro); en el caso 2, se muestra el de la ficha.
  - **Un solo valor, opcional:** la pregunta de Gerencia es «¿en qué rubros vendemos y cuánto?»; con varios rubros
    por cuenta, cada sol se contaría dos veces. Un caso mixto (hospital universitario) elige el principal.
    Opcional porque obligarlo da datos de relleno; la portada de la lista avisa «N cuentas sin rubro» con «Ver
    cuáles», calculado por el CRM leyendo la party (regla 1 de dependencias).
  - **Catálogo cerrado:** hoy `Catalogo` impide desactivar o borrar los códigos del sistema, pero no impide crear
    filas. Se agrega al núcleo un **modo «cerrado» por catálogo**: sin crear ni borrar desde la pantalla, solo
    editar el nombre. Se aplica a «Rubros», con las 21 secciones como códigos del sistema. Con su prueba.
  - **No se confunde con «Líneas de solución»:** esa dice qué vende Techquk y va en la oportunidad; el rubro dice a
    qué se dedica el cliente.
  - **Descartado:** `cuentas.sector` y `cuenta_verticales` (salen del DBML); verticales múltiples (doble conteo);
    rubro principal más secundarios (dos campos con la misma lista); rubro obligatorio (datos de relleno); una lista
    propia (inventada, y no conecta con el padrón).
  - **DBML al día** (2026-11-05): `parties.rubro_id`, tabla `rubros` en M5; sin `cuenta_verticales`.
- **D6 · La clasificación A, B o C no se construye en esta tarea.** **Aprobada 2026-11-05 por el desarrollador.**
  - Solo aparece en el DBML de agosto (`clasificacion varchar(20) [note: 'A | B | C']`): no está en la parte 2 del
    v4, ni en las 13 pantallas de cuentas del diseño de octubre, ni en el glosario. Nadie dice qué significa ni quién
    la pone (por lo facturado, por potencial, por estrategia).
  - Regla 12: el criterio depende de la empresa, y una lista sin criterio se llena a ojo. Además, el criterio cambia
    la construcción: por lo facturado se calcula (dato de Facturación, etapa 3); por potencial o estrategia es un
    dato a mano con responsable y revisión.
  - **No se crea `cuentas.clasificacion`**; sale del DBML con la nota «pendiente, ver D6». Fila en `pendientes.md`
    («Clasificación A, B o C de las cuentas»): decide Gerencia Comercial, y se pregunta en la revisión del CRM en
    curso (camino a la 1.0, `docs/analisis/README.md`), con el criterio, quién la pone y cada cuánto se revisa.
  - **Punto de extensión** en «Preparado para lo diferido».
  - **Descartado:** la lista fija a mano ya (un dato sin criterio) y calcularla ya por lo facturado (no hay
    facturación hasta la etapa 3).
- **D7 · El ejecutivo de la cuenta.** **Aprobada 2026-11-05 por el desarrollador, con cinco ajustes y la opción A
  para el ámbito de edición (punto 6).**
  1. **Obligatorio en la base:** `cuentas.ejecutivo_user_id` `NOT NULL` (el v4: «toda cuenta tiene ejecutivo y
     origen»; ajusta el DBML). Es un usuario (`users`), no un puesto: el organigrama está en espera.
  2. **Quién puede serlo:** los usuarios activos con el permiso `crm.cuentas.atender`. Gerencia decide en Roles y
     permisos qué roles lo tienen. El selector ofrece esos y, si la cuenta ya tiene uno que dejó de cumplir
     (desactivado o sin el permiso), también ese, marcado (regla de AGENTS.md sobre selectores).
  3. **Al crear:** por defecto, quien crea, si tiene `atender`; si no (alguien de Licitaciones que registra una
     entidad), llega vacío y hay que elegir. El servicio valida que el elegido cumpla el punto 2.
  4. **Cambiar el ejecutivo es una acción aparte,** `crm.cuentas.reasignar`, con su permiso (pensado para Gerencia
     Comercial); `editar` no lo cambia.
     - **Una o varias cuentas:** desde la ficha (compara la versión, como todo formulario) o marcando varias en la
       lista. La de varias es **una sola transacción, todo o nada**: cada cuenta se bloquea al cambiarla y, como
       solo toca el ejecutivo, no compara versiones; cada cambio queda en la bitácora.
     - **Sus oportunidades abiertas:** la acción ofrece «pasar también sus oportunidades abiertas», marcado por
       defecto. Las cerradas (ganadas o perdidas) nunca cambian de ejecutivo. Como CRM-02 no existe todavía,
       `CuentasService` dispara el evento `EjecutivoDeCuentaCambiado` (cuenta, ejecutivo anterior, nuevo y si incluye
       las abiertas; después de confirmar) y Oportunidades lo escuchará. Anotado para su plan en el mapa.
     - **Un solo aviso por nuevo ejecutivo,** no uno por cuenta: «Se te asignaron N cuentas» (o «la cuenta X» si es
       una), con enlace a la lista filtrada, por `NotificacionesService`.
  5. **Atribución:** los reportes y tableros por ejecutivo atribuyen lo vendido al ejecutivo **de la oportunidad**,
     no al ejecutivo actual de la cuenta. Reasignar una cartera no mueve la historia. Anotado para CRM-02 y los
     tableros en el mapa.
  6. **Ámbito:** **ver**, todas las cuentas para quien tenga `crm.cuentas.ver` (así nadie duplica una cuenta por no
     ver la existente). **Editar**, con dos permisos (opción A, desarrollador, 2026-11-05): `crm.cuentas.editar`
     (todas; Gerencia Comercial) y `crm.cuentas.editar-propias` (donde soy el ejecutivo; los ejecutivos).
     - El mecanismo de ámbitos del núcleo (1.4) no lo cubre tal cual: un ámbito es un rol acotado a una cosa
       (`permisos_por_ambito`), el único tipo es `unidad_organizativa` (fijado por un `CHECK`) y depende del
       organigrama en espera; «propias» es una relación del registro con la persona.
     - **La regla vive en un solo lugar del núcleo:** `SeguridadService::puedeSobrePropio(User $usuario, string
       $permisoTodas, string $permisoPropias, int $responsableUserId): bool` (tiene el de todas, o tiene el de
       propias y es el responsable) y su pareja para consultas, `filtrarPropios(...)`, que restringe un `Builder` a
       las filas donde el usuario es el responsable salvo que tenga el de todas. No toca `permisos_por_ambito` ni la
       pantalla de Roles y permisos. Con sus pruebas.
     - `CuentasService` usa `puedeSobrePropio` para editar; la lista usa `filtrarPropios` para mostrar «Editar» solo
       donde aplica, filtrando en la base.
     - Tener los dos permisos = editar todas; no es un cruce de funciones.
     - `crm.cuentas.reasignar` sigue aparte: `editar-propias` nunca cambia el ejecutivo.
     - Convención escrita en `docs/convenciones/codigo.md` («Lo propio y lo de todos»).
     - **Descartado, la opción B** (un tipo de ámbito «propios» en el núcleo): cambia `permisos_por_ambito`,
       `SeguridadService`, la asignación de roles acotados y la pantalla de la 1.4c; es una tarea del núcleo en sí.
  7. **Cuando un ejecutivo se va:** sus cuentas quedan con un ejecutivo inactivo (la base no las deja sin dueño).
     - La portada avisa «N cuentas con ejecutivo inactivo» con «Ver cuáles», y desde ahí se reasignan en bloque.
     - Además, una **alerta del núcleo** (1.10: idempotente, en la revisión diaria, una `FuenteAlertas` del módulo):
       «N cuentas con ejecutivo inactivo», con enlace a la lista filtrada. Sus destinatarios se eligen en
       Configuración → Alertas; de fábrica, Gerencia General, porque el rol de Gerencia Comercial todavía no existe
       («Grilla de roles real» en `pendientes.md`). Cuando exista, se le agrega ahí, sin código.
     - No se reasignan solas: a quién pasan lo decide una persona.
  - **Descartado:** ejecutivo opcional (contradice el v4); cualquier usuario activo (el selector se llena de gente de
    otras áreas); ligarlo a un puesto (organigrama en espera; la cartera es de una persona); reasignar solo al
    desactivar un usuario (no hay a quién por defecto).
- **D8 · Prospecto o cliente: `cuentas.cliente_desde`.** **Aprobada 2026-11-05 por el desarrollador, con tres
  ajustes (ya incorporados).**
  1. **Una fecha, no un estado:** `cliente_desde` (date, nula). Nula = prospecto; con fecha = cliente desde ese día.
     Sin lista fija `prospecto | cliente`. «Nuevos clientes del mes» = cuentas con `cliente_desde` en ese mes. El
     cambio queda en la bitácora (pestaña Historial).
  2. **Por qué es cliente: `cuenta_marcas_cliente`** (cuenta, origen, fecha). Para revertir sin que Cuentas lea
     Oportunidades (la dependencia va en un solo sentido), Cuentas guarda cada hecho que la hace cliente: una fila
     por oportunidad ganada (`origen = oportunidad`, con su id) y como mucho una manual (`origen = manual`, con su
     comentario). Único por cuenta, origen e id (y uno manual por cuenta, con índice parcial): repetir una marca no
     la duplica. `cliente_desde` es siempre la fecha más antigua de sus marcas (nula si no tiene); se recalcula en la
     misma transacción, con la cuenta bloqueada. **Prueba de coherencia:** `cliente_desde` = la fecha más antigua de
     sus marcas (o nula sin marcas) después de marcar, revertir, marcar a mano y quitar la marca manual
     (`cuenta_marcas_cliente` aceptada por el desarrollador, 2026-11-05).
  3. **Pasar a cliente es un efecto de la transición «ganar» de la oportunidad** (mecanismo de estados del núcleo,
     1.5), no una llamada suelta. Como CRM-02 no existe, `CuentasService` ofrece:
     - `marcarCliente(cuentaId, fecha, origen)`: agrega la marca. La fecha queda en `min(cliente_desde actual,
       fecha)`: una oportunidad cargada después con cierre anterior corrige «nuevos clientes por mes».
     - `revertirCliente(cuentaId, origen)`: al anular la transición (regla 6), quita la marca de esa oportunidad;
       `cliente_desde` pasa a la marca más antigua que quede (otra ganada o la manual) o vuelve a nula si no queda
       ninguna.
     El efecto de CRM-02 los usará; anotado para su plan en el mapa. La fecha es la del cierre de la oportunidad.
  4. **«No vuelve a prospecto» se refiere al paso del tiempo,** no a anular un hecho que no ocurrió: una cuenta que
     lleva años sin comprar sigue siendo cliente (eso lo dicen el último contacto y lo facturado); una ganada que se
     anula sí se revierte, porque esa venta no existió.
  5. **Clientes de antes del sistema:** acción manual `crm.cuentas.marcar-cliente`, con su permiso (pensado para
     Gerencia Comercial):
     - fecha «cliente desde» no futura (lo valida el servicio: un `CHECK` no puede usar «hoy»);
     - comentario obligatorio (por ejemplo, «cliente desde antes del sistema; contrato 2024»);
     - si luego llega una ganada más antigua, aplica el punto 3 (la fecha más antigua).
  6. **`cliente_desde` no se edita con `crm.cuentas.editar`.** Para corregir un error, «quitar marca de cliente»
     (`crm.cuentas.quitar-marca-cliente`): acción propia con permiso y motivo obligatorio, permitida solo si la cuenta
     no tiene marcas de oportunidades ganadas; quita la marca manual y la cuenta vuelve a prospecto. Todo en la
     bitácora. **Precisión (desarrollador, 2026-11-06):** la bitácora no guarda motivos, así que la marca manual no se
     borra: se anula (`quitada_en`, `quitada_por_user_id`, `motivo_quitada`), y solo cuentan las vigentes. El motivo
     es texto libre, con el mínimo de `motivos.minimo_detalle_otro`. Revertir una ganada sí borra su fila.
  7. **«Clientes activos»** de la lista = con `cliente_desde` y activas (D9); «Prospectos» = sin `cliente_desde` y
     activas.
  - **Descartado:** la lista fija (la fecha dice lo mismo y cuándo); derivarlo de los contratos (C10, otra área y
    etapa 3); que Cuentas escuche `OportunidadGanada` (dependencia en los dos sentidos y pasos separados); que Cuentas
    lea las oportunidades para revertir (misma dependencia); «cliente inactivo» por tiempo sin comprar (nadie lo
    definió, regla 12: si se pide, sale de lo facturado en la etapa 3).
- **D9 · Desactivar y reactivar, nunca borrar.** **Aprobada 2026-11-05 por el desarrollador, con tres ajustes (ya
  incorporados).**
  1. **Nunca se borra** (v4: «nada se borra»), ni siquiera una creada por error: se desactiva con su motivo.
  2. **Desactivar exige motivo de catálogo:** familia propia `crm.cuentas.desactivar` en los motivos del núcleo (se
     guardan fuera de un documento con estados, como la oportunidad perdida: `FuenteDeMotivos`, plan 1.5), con
     «Otro» y detalle obligatorio. Valores de partida, editables: «Dejó de operar», «Creada por error (RUC
     equivocado)», «Ya no es mercado para Techquk», «Otro». En la cuenta, `motivo_inactiva_id` y su detalle; un
     `CHECK` exige motivo si está inactiva y ninguno si está activa. Al reactivar se limpian; la bitácora conserva lo
     que fue.
     - **«Creada por error (RUC equivocado)»:** el mismo paso ofrece desactivar también la ficha si no tiene otros
       tipos que «Cliente» (`PartiesService::desactivar`, con el permiso del núcleo). Si la persona no tiene ese
       permiso, solo avisa que la ficha queda con datos que no son de esa empresa.
  3. **No se desactiva con trabajo en curso:** `ActividadDeCuentas` (D11) devuelve los impedimentos para desactivar
     (oportunidades abiertas, licitaciones en curso, cotizaciones vigentes…; cada módulo aporta lo suyo cuando
     exista) y el mensaje los dice con su número. Hoy no hay ninguno; la regla empieza a funcionar sola cuando
     lleguen.
  4. **Una cuenta inactiva** no sale en los buscadores (`CuentasService::buscar` solo da las usables), así que no
     recibe oportunidades, cotizaciones ni licitaciones nuevas; se ve en la pestaña «Inactivas» y en «Todas», que
     muestra activas e inactivas (D11.1; confirmado por el desarrollador, 2026-11-06), y sale de los avisos y de la
     alerta de ejecutivo inactivo.
  5. **Reactivar,** con el mismo permiso `crm.cuentas.desactivar`; exige la ficha activa («reactiva primero la
     ficha», D1).
  6. **La cuenta y su ficha son dos interruptores:** desactivar la ficha en el núcleo no toca la cuenta (el núcleo no
     conoce al CRM). Para el CRM, la cuenta es usable si las dos están activas: `resumen()->activa` lo dice así, la
     lista la marca «ficha inactiva» y los buscadores la excluyen. La portada avisa «N cuentas con su ficha
     inactiva» con «Ver cuáles», como los de ejecutivo inactivo y sin rubro; no cuentan como «Clientes activos» ni
     «Prospectos».
  7. **Bloqueo optimista:** `cuentas.version`, capturada al abrir el formulario.
  - **Descartado:** borrar las nunca usadas (no es un catálogo; el v4 dice «nada se borra»); motivo en texto libre;
    desactivar la cuenta automáticamente con la ficha (el núcleo tendría que conocer al CRM).
- **D10 · El historial de la cuenta es la bitácora del núcleo.** **Aprobada 2026-11-05 por el desarrollador, con dos
  agregados (ya incorporados).** El diseño: «se alimenta de la auditoría del sistema: quién cambió qué y cuándo».
  1. **La pestaña Historial usa `TablaHistorial` del núcleo**, que ya junta varios registros en una tabla. Junta:
     - **la cuenta:** creación, ejecutivo, origen, nivel de gobierno, notas, desactivar y reactivar con su motivo;
     - **sus marcas de cliente** (D8): agregadas y quitadas, con su origen;
     - **de su ficha, solo para quien tiene `parties.ver`:** sus datos (razón social, dirección, rubro: el rubro se
       cambia también desde la cuenta, D5) y los periodos de sus tipos «Entidad pública» y «Cliente» (abrir y
       cerrar). Por la D3, cerrar «Entidad pública» pasa la cuenta de pública a privada, y el historial lo explica.
     Los campos sensibles los quita el núcleo. Un enlace lleva al historial completo de la ficha (contactos, sedes,
     cuentas bancarias).
  2. **No se crea `cuenta_historial`** (sale del DBML): los cambios están en la bitácora, que se escribe sola, y lo que
     pasa con el cliente (llamadas, visitas, notas) va en Actividades (CRM-05), con su pestaña en la cuenta.
  3. **Para que se lea como en el diseño,** la cuenta y sus marcas se registran en la auditoría del núcleo con
     nombres legibles de sus campos y sus valores traducidos (el nombre del ejecutivo, no su id).
  - **Descartado:** `cuenta_historial` (duplica la bitácora o las actividades); un historial armado por el CRM
    (repite el componente); todo el historial de la ficha dentro de la cuenta (ruido que se ve mejor en Personas y
    empresas, con su enlace).
- **D11 · Las pantallas de esta tarea.** **Aprobada 2026-11-05 por el desarrollador, con cuatro ajustes (ya
  incorporados).** Todas siguen los patrones que ya existen.
  1. **Lista de cuentas con portada** (el patrón de Personas y empresas, 1.4m):
     - **Pestañas de texto con su cantidad:** Todas (de verdad todas: activas e inactivas, con la marca «Inactiva» o
       «Ficha inactiva»), Mías, Clientes activos y Prospectos (D8; sin las de ficha inactiva, D9), Nuevas este mes
       (con su origen), Sin actividad 90 días, Inactivas.
     - **Avisos con «Ver cuáles»:** sin actividad 90 días, con ejecutivo inactivo (D7), con su ficha inactiva (D9) y
       sin rubro (D5).
     - **Cartera por ejecutivo:** cuentas por ejecutivo (y sus oportunidades abiertas); un clic filtra la tabla.
     - **Últimos cambios:** los 10 más recientes de la bitácora.
     - **Tabla:** razón social (con el nombre comercial), RUC, «Pública · nivel» o «Privada», rubro, oportunidades
       abiertas, último contacto, ejecutivo, «Cliente desde». Buscador por razón social, nombre comercial o RUC, sin
       tildes ni mayúsculas, que encuentra cualquier cuenta, activa o no (importa antes de crear una). Filtros:
       pública o privada, nivel de gobierno, departamento (de la ficha), ejecutivo, rubro, origen. Acción en bloque
       «Reasignar» (D7); «Editar» solo donde aplica (`filtrarPropios`).
     - **«Sin actividad 90 días»:** último contacto con más de 90 días, o sin contacto y la cuenta con más de 90 días
       de creada; los 90, parámetro `crm.dias_sin_actividad`.
     - **Nada de avisos sobre datos que aún no existen:** `ActividadDeCuentas` dice si tiene un aporte real o es la
       versión vacía. Mientras sea la vacía, no se muestran la pestaña «Sin actividad 90 días», su aviso, la columna
       «Último contacto» ni las oportunidades de la cartera (la cartera muestra solo cuentas por ejecutivo).
       Aparecen solos cuando Actividades (CRM-05) u Oportunidades (CRM-02) aporten.
     - **Lo que viene de otros módulos se filtra en la base:** `ActividadDeCuentas` da los datos por página en lote
       (nunca por fila) y restricciones para consultas («Preparado para lo diferido»).
  2. **Nueva cuenta:** página con pasos (`Wizard`), como el alta de Personas y empresas (`NuevaParty`), con los dos
     pasos de la D2.
  3. **Ficha de la cuenta:**
     - **Cabecera:** razón social, RUC, «Cliente desde…» o «Prospecto», «Pública · nivel» o «Privada», ejecutivo,
       marcas «Inactiva» o «Ficha inactiva»; botones según permiso: Editar, Reasignar, Marcar cliente o Quitar marca,
       Desactivar o Reactivar.
     - **Resumen:** datos de la cuenta y el rubro con su botón (D5); sedes y contactos de la ficha, con enlace; y lo
       que aporte `ActividadDeCuentas` (oportunidades en curso, última y próxima actividad, facturado), que no se
       muestra mientras sea la versión vacía.
     - **Contactos:** el componente del núcleo (`ContactosDeParty`), con su rotación, sus acciones y sus permisos.
       Pide `parties.ver`: la pestaña se muestra a quien lo tiene. Nota en `pendientes.md` («Grilla de roles real»):
       los roles comerciales necesitan `parties.ver` y `parties.gestionar-contactos`.
     - **Historial** (D10).
     - **Las demás pestañas y botones del diseño** se suman con `RegistroPestanasCuenta` (D12).
  - **Selectores de catálogo (desarrollador, 2026-11-05):** origen, nivel de gobierno, rubro y motivo de desactivar
    usan siempre el campo de catálogo común del núcleo (`Campo::elementoDe(..., Catalogo::…)`, que `AccionFilament`
    dibuja con `CatalogosService::opciones`), nunca un `Select` armado a mano, para que Cuentas reciba sola la mejora
    de la tarea 2.0 «Selectores de catálogo» (búsqueda en el servidor y botón «+»). Si la Nueva cuenta (página con
    pasos, no una acción) llega antes que el selector común para páginas de la 2.0, sus campos de catálogo se arman
    con la misma pieza que dibuja `Campo::elementoDe`, no a mano.
  4. **Fuera de esta tarea:** «Relaciones» de la ficha (espera «Cuenta padre de unidades ejecutoras», D1); «Cuenta ›
     Nueva oportunidad» y el tablero de oportunidades de la cuenta (CRM-02); el filtro «Facturado» (etapa 3).
  - **Descartado:** un «Panel de cuentas» como pantalla aparte (sus avisos van en la portada); crear la cuenta en un
    panel deslizante (el alta de Personas y empresas es una página con pasos).
- **D12 · Lo que Cuentas ofrece a los demás módulos.** **Aprobada 2026-11-05 por el desarrollador, con tres ajustes
  (ya incorporados).** Siempre un `ResumenCuenta` de solo lectura, nunca el modelo (regla 2).
  1. **Lectura, con `CuentasService`:**
     - `buscar(string $texto, int $limite = 20, ?int $incluirId = null)`, para los selectores de otros módulos:
       solo cuentas **usables** (activas y con su ficha activa, D9), para que nadie cuelgue trabajo nuevo de una
       cuenta inactiva. Con `incluirId`, la respuesta incluye esa cuenta aunque no sea usable, marcada (inactiva o
       ficha inactiva), para que un registro viejo se pueda abrir y guardar (regla de AGENTS.md sobre selectores);
       nunca se ofrece para trabajo nuevo. Con su prueba.
     - `resumen(int $cuentaId, ?CarbonImmutable $fecha = null)`: id, id de la ficha, etiqueta («razón social ·
       RUC»), ejecutivo, `esPublica` a esa fecha (D3), nivel de gobierno, `clienteDesde`, `usable` y, si no lo es,
       el motivo (cuenta inactiva, ficha inactiva).
     - `deParty(int $partyId)`: la cuenta de una ficha aunque no sea usable, con `usable` y el motivo, o nula si no
       tiene. Así Licitaciones dice «tiene una cuenta inactiva; reactívala» en vez de ofrecer crear una que fallaría
       por el único de la D1.
  2. **Escritura de otros módulos:** solo `marcarCliente` y `revertirCliente` (D8), para el efecto «ganar» de CRM-02.
     Lo demás son acciones registradas de Cuentas, con su permiso.
  3. **Un solo evento, `EjecutivoDeCuentaCambiado`** (D7), después de confirmar: CRM-02 lo escuchará. Sin
     `CuentaCreada`: nadie lo escucharía; se agrega cuando un módulo lo necesite.
  4. **Dos registros para enchufarse:** `RegistroPestanasCuenta` (pestañas de la ficha, cada una con su permiso, y
     botones de la cabecera; como `RegistroPestanasFicha`) y `ActividadDeCuentas` (D11).
  5. **No es una costura del ADR 0020:** lo usan módulos de la misma área, así que no hay versión falsa. Cruzaría
     áreas, y se escribiría como costura en un ADR:
     - que el OECE (área 4, vive en el CRM) creara cuentas desde las convocatorias (hoy le basta registrar la ficha
       con `PartiesService`);
     - que los tableros de Analítica (área ERP) necesiten leer cuentas. Nadie lee las tablas de Cuentas
       directamente.
  6. `tests/Arch` vigila que ningún otro módulo importe los modelos de Cuentas (el módulo entra solo).
  - **Descartado:** `CuentaCreada` sin oyente; que `buscar` ofrezca inactivas para trabajo nuevo; que otros módulos
    escriban en la cuenta por servicio fuera de `marcarCliente` y `revertirCliente`.
- **D13 · Pestaña «Cuenta comercial» en la ficha de Personas y empresas.** **Aprobada 2026-11-05 por el
  desarrollador, con dos ajustes (ya incorporados).** Quien llega a una empresa por Personas y empresas ve ahí si es
  cuenta del CRM y quién la atiende.
  1. **Se suma con `RegistroPestanasFicha`** (1.4m), desde el provider de Cuentas, con el permiso `crm.cuentas.ver`.
     El núcleo no conoce al CRM.
  2. **Solo en fichas con RUC** (D1). `PestanaFicha` hoy solo filtra por permiso: la tarea del núcleo le agrega una
     condición declarativa opcional, `tiposDocumento: list<string>|null` (null = todas), que `RegistroPestanasFicha`
     respeta al armar las pestañas de una ficha, con su prueba. Cuentas la registra con
     `tiposDocumento: [Catalogo::RUC]` (el código del núcleo es `'6'`, catálogo 06 de SUNAT).
  3. **Qué muestra,** con `CuentasService::deParty()` (D12):
     - *Tiene cuenta:* ejecutivo, «Cliente desde…» o «Prospecto», «Pública · nivel» o «Privada», «Inactiva» con su
       motivo si lo está, y «Abrir la cuenta».
     - *No tiene cuenta y la ficha está activa:* «Esta empresa no es cuenta del CRM» y, con `crm.cuentas.crear`,
       «Crear cuenta», que abre Nueva cuenta con el RUC puesto, en el caso 2 de la D2. **El servicio vuelve a
       comprobar todo al guardar:** si entretanto alguien creó la cuenta, sale el mensaje del caso 1 (D2,
       concurrencia).
     - *No tiene cuenta y la ficha está inactiva:* el mensaje del caso 3 de la D2, sin botón.
  4. **Solo muestra; no edita:** los cambios se hacen en la ficha de la cuenta.
  5. **Posición:** después de las pestañas propias de la ficha y antes del historial (orden del registro).
  - **Descartado:** un enlace en la cabecera de la ficha (es del núcleo); la pestaña en todas las fichas (con DNI
    nunca hay cuenta); editar desde la pestaña (dos lugares para lo mismo).
- **D14 · Permisos y caja.** **Aprobada 2026-11-05 por el desarrollador, con cuatro ajustes (ya incorporados).**
  1. **Nombres con prefijo de área**, regla para todos los módulos de negocio: permisos y acciones
     `<area>.<modulo>.<verbo>` (`crm.cuentas.ver`, `erp.compras.orden.anular`); el núcleo sigue sin prefijo
     (`parties.ver`). «Cuentas» solo sería ambiguo (cuentas de usuario, bancarias, por pagar y por cobrar). Roles y
     permisos agrupa los de negocio por los dos primeros tramos («CRM · Cuentas»). La convención y la agrupación van
     en la 2.20a, que se fusiona primero, para que las demás sesiones la tengan antes de nombrar sus permisos.
  2. **Registro de permisos de módulo** (nombre legible y grupo) en la 2.20a; `DE_LECTURA` del núcleo pasa a usarlo.
     Cuentas declara ahí `crm.cuentas.ver` y `crm.cuentas.atender`, que no son acciones.
  3. **Los permisos de Cuentas** (quién los tiene lo decide Gerencia en Roles y permisos; hoy, el rol
     `administrador`):

     | Permiso | Qué permite | Pensado para |
     |---|---|---|
     | `crm.cuentas.ver` | Lista, ficha, caja y pestaña en Personas y empresas | Equipo comercial y Licitaciones |
     | `crm.cuentas.crear` | Crear (incluye asignar «Cliente» y «Entidad pública», D3) | Ejecutivos, Licitaciones |
     | `crm.cuentas.editar` | Editar cualquier cuenta | Gerencia Comercial |
     | `crm.cuentas.editar-propias` | Editar donde soy el ejecutivo (D7) | Ejecutivos |
     | `crm.cuentas.atender` | Poder ser ejecutivo (D7) | Ejecutivos |
     | `crm.cuentas.reasignar` | Cambiar el ejecutivo, de una o varias (D7) | Gerencia Comercial |
     | `crm.cuentas.desactivar` | Desactivar y reactivar (mismo permiso, D9) | Gerencia Comercial |
     | `crm.cuentas.marcar-cliente` | Marcar cliente a mano (D8) | Gerencia Comercial |
     | `crm.cuentas.quitar-marca-cliente` | Quitar esa marca, con motivo (D8) | Gerencia Comercial |

     Del núcleo (2.20a): `parties.cambiar-rubro` (D5). Sin pares incompatibles nuevos: ninguno mueve dinero ni
     aprueba lo que otro pidió.
  4. **En Configuración, con los permisos del núcleo:** el parámetro `crm.dias_sin_actividad` (D11), la familia de
     motivos `crm.cuentas.desactivar` (D9) y la alerta de ejecutivo inactivo (D7).
  5. **La caja «Cuentas»:** área CRM, ruta `/cuentas`, permiso `crm.cuentas.ver`. Descripción: «Los clientes y
     prospectos, quién atiende a cada uno y de dónde llegaron». Preguntas (1.4q), solo de lo que esta tarea
     responde: «¿Esta empresa ya es cuenta y quién la atiende?», «¿Cuántos clientes nuevos tuvimos este mes?», «¿Qué
     cuentas tiene cada ejecutivo?», «¿De dónde nos llegan las cuentas?». Animación existente (`Pulso`).
     **Ícono: `heroicon-o-building-storefront`** (desarrollador, 2026-11-05; `briefcase` ya lo usan la pestaña
     «Puestos» y «Cambiar puesto»). **Reservado `heroicon-o-presentation-chart-line`** para Oportunidades o el
     tablero comercial.
  - **Descartado:** `cuentas.<verbo>` sin prefijo; agregar `crm.cuentas.ver` a mano a la lista del núcleo; un
    permiso por campo.

## Pasos

La 2.20a se fusionó el 2026-11-05 (PR #79) y `main` ya está en esta rama.

- [x] 1. Fila del mapa, plan, rama y decisiones aprobadas (2026-11-05)
- [x] 2. Esqueleto del módulo con `/nuevo-modulo`: provider en `bootstrap/providers.php`, caja, permisos de módulo
      (`crm.cuentas.ver`, `crm.cuentas.atender`), parámetro `crm.dias_sin_actividad`, familia de motivos
      `crm.cuentas.desactivar`
- [x] 3. Migraciones `cuentas` y `cuenta_marcas_cliente` con `/nueva-migracion` (únicos, `CHECK` y versión)
- [x] 4. `CuentasService` de lectura (`buscar` con `incluirId`, `resumen` a una fecha, `deParty`), `ResumenCuenta`,
      `RegistroPestanasCuenta` y `ActividadDeCuentas` vacía, con sus pruebas
- [x] 5. Crear cuenta (D2): acción, los cuatro casos, aviso por nombre, tipos de la ficha, y la prueba de concurrencia
      con las dos carreras (`tests/Concurrencia`)
- [x] 6. Editar y editar-propias, reasignar (una o varias, evento `EjecutivoDeCuentaCambiado`, un aviso por
      ejecutivo), desactivar y reactivar con motivo e impedimentos (D9), y la alerta de ejecutivo inactivo
- [x] 7. Marcas de cliente (D8): `marcarCliente`, `revertirCliente`, marcar a mano y quitar la marca, con la prueba de
      coherencia
- [x] 8. Pantallas (D11): lista con portada, Nueva cuenta en dos pasos, ficha con Resumen, Contactos e Historial
      (D10), y la pestaña «Cuenta comercial» en Personas y empresas (D13)
- [x] 9. Revisor de arquitectura, una corrida de `composer check` en verde y PR (lo abre Sebastián)

## Preparado para lo diferido

- **Pestañas de la ficha de cuenta:** `RegistroPestanasCuenta` (como `RegistroPestanasFicha`); Oportunidades,
  Cotizaciones, Licitaciones, Actividades y, en la etapa 3, Contratos y facturación solo registran la suya.
- **Botones de la cabecera** («Nueva oportunidad», «Registrar actividad»): el mismo registro admite acciones.
- **Lo que otros módulos dicen de una cuenta: `ActividadDeCuentas` (D9, D11).** Es un registro: cada módulo suma su
  aporte cuando exista (Oportunidades, CRM-02; Actividades, CRM-05; Licitaciones, CRM-04; Cotizaciones, CRM-03;
  Facturación, etapa 3). Sin aportes, es la versión vacía, y dice qué tiene (`tiene(...)`) para que la pantalla no
  muestre lo que no existe. Cada aporte ofrece:
  - **datos por página, en lote** (nunca por fila): oportunidades abiertas, último y próximo contacto, facturado;
  - **restricciones para consultas** (al estilo de `filtrarPropios`), para filtrar y ordenar en la base sin traer
    todo a PHP: cuentas con último contacto anterior a una fecha (o sin contacto), cuentas con oportunidades
    abiertas, y el orden por último contacto. Con eso se arman la pestaña y el filtro «Sin actividad» y la columna
    ordenable;
  - **impedimentos para desactivar** (D9): oportunidades abiertas, licitaciones en curso, cotizaciones vigentes…,
    con su número.
- **Pasar a cliente:** `CuentasService::marcarCliente()` y `revertirCliente()` (D8), listos para el efecto de
  «ganar» de CRM-02.
- **Filtro «Facturado»:** espera a Facturación (etapa 3); la interfaz anterior tendrá ese dato.
- **Clasificación A, B o C (D6):** si se decide calculada, se suma a `ActividadDeCuentas` (D11), que en la etapa 3
  también dará lo facturado; si se decide a mano, es una columna y una acción nuevas, sin tocar lo demás. La lista y
  la ficha dejan su lugar previsto (un filtro y un dato en el Resumen).
- **Ámbito «propios» en el núcleo (D7):** si un día los ámbitos del núcleo admiten «propios» (la opción B), solo
  cambia `SeguridadService::puedeSobrePropio` y su pareja; los servicios y las pantallas no.
- **Enlace a la oportunidad en el historial (D10):** las filas de marcas de cliente por oportunidad enlazan a esa
  oportunidad cuando CRM-02 exista: la marca ya guarda su id (`cuenta_marcas_cliente.oportunidad_id`).
- **Rubro desde el padrón (D5):** SUNAT devuelve la clase CIIU de cuatro dígitos. Cuando se decida «Consulta de
  RUC y DNI», la ficha guardará la clase y la sección se derivará de ella; el padrón (o la IA) sugiere y una
  persona confirma. Hoy solo se guarda la sección (`parties.rubro_id`); no se construye nada de ese camino.

## Criterio de aceptación

- Servicio y acciones: una cuenta por ficha (también en la base), RUC con dígito verificador, ejecutivo y origen
  obligatorios, nivel de gobierno solo en públicas, tipos «Cliente» y «Entidad pública» sin fallar si ya están,
  ficha inactiva rechazada, editar y editar-propias, reasignación todo o nada, impedimentos para desactivar.
- Concurrencia: las dos carreras de la D2 terminan en el mensaje del caso 1.
- Coherencia: `cliente_desde` = la marca más antigua tras marcar, revertir, marcar a mano y quitar (D8).
- `buscar` con `incluirId` y `deParty` con su motivo (D12).
- Pantallas: la lista con sus pestañas y avisos (sin los de datos que aún no existen), la creación en dos pasos, la
  ficha y la pestaña en Personas y empresas; `tests/Arch` incluye el módulo.

## Bitácora

- 2026-11-04 — Rama `crm-cuentas` desde `main` (f012412). Decisiones D1 a D14 propuestas al desarrollador.
- 2026-11-05 — Las catorce decisiones, aprobadas una por una con sus ajustes. Los cambios del núcleo se separan en la
  2.20a (desarrollador), que se fusiona primero; esta rama queda solo con lo de Cuentas (el DBML de núcleo, las
  convenciones y la fila del mapa viajan en la 2.20a). Falta confirmar el ícono de la caja (D14.5).
- 2026-11-05 — Ícono de la caja: `building-storefront`; `presentation-chart-line` reservado para Oportunidades o el
  tablero comercial (desarrollador).
- 2026-11-05 — La 2.20a está en `main` (PR #79); `main` traído a esta rama sin conflictos. Los agentes no abren ni
  fusionan PR (AGENTS.md): al terminar, se sube la rama y se avisa.
- 2026-11-05 — **Punto de partida para la próxima sesión (paso 2, el esqueleto).** Lo que ya se averiguó:
  - **Módulos de modelo:** `Erp/Proyectos` y `Erp/Presupuestos` (de la 2.1). Ninguno tiene todavía pantallas ni caja:
    Cuentas es el primer módulo de negocio con interfaz. Su provider se agrega en `bootstrap/providers.php` **antes**
    de `AppPanelProvider`.
  - **Pantallas:** se registran en el `register()` del provider, no en `boot()`, con
    `RegistroPantallas::registrar('app', paginas: [...], recursos: [...])` (como `NucleoServiceProvider::registrarPantallas`).
    La ruta de la caja es el `$slug` del recurso (Personas y empresas usa `personas`; Cuentas, `cuentas`).
  - **Caja:** `RegistroModulos::registrar(new CajaModulo(clave, nombre, url, permiso:, area: AreaModulo::Crm, icono:,
    descripcion:, preguntas:, animacion:))`; ícono `heroicon-o-building-storefront` (D14).
  - **Permisos:** `RegistroPermisos::declarar('crm.cuentas.ver', 'Ver cuentas')` y `declarar('crm.cuentas.atender', …)`;
    los de las acciones salen de su `permiso()`, y su nombre legible con `RegistroPermisos::nombrar()`. Todo con prefijo
    `crm.` (ADR 0021; `PermisosConAreaTest` lo vigila).
  - **Familia de motivos de desactivar (D9):** `RegistroDocumentos::familiaDeMotivos('crm.cuentas.desactivar', …)`
    crea la lista con su «Otro» (lo hace `SincronizadorEstados`). **Falta averiguar** cómo se siembran los motivos de
    partida («Dejó de operar», «Creada por error (RUC equivocado)», «Ya no es mercado para Techquk»): no se encontró un
    mecanismo de siembra por familia; mirar `MotivosService::agregar` y si conviene un seeder del módulo.
  - **Parámetro** `crm.dias_sin_actividad` (90): con `RegistroParametros` en el provider, como los del núcleo.
  - **Lo que ya da el núcleo (2.20a):** `PartiesService::posiblesPorNombre`, `documento()`, `registrar(DatosParty,
    ['cliente'])` con `rubroId`, `tieneRol`, `asignarRol`; `SeguridadService::puedeSobrePropio` y `filtrarPropios`;
    `PestanaFicha` con `tiposDocumento: [Catalogo::RUC]`; los catálogos `OrigenesComerciales`, `NivelesGobierno` y
    `Rubros`.
  - **Pruebas:** `composer check:tarea` falla cuando la tarea toca 6 archivos de prueba o más
    (`scripts/pruebas-de-la-tarea.php` pasa `--parallel` con varias rutas): por paso, `php artisan test <archivos>`;
    al cerrar, `composer check` completo.
  - **AGENTS.md (desde `main`):** los agentes no abren ni fusionan PR (se sube la rama y se avisa), y una cadena con
    `&&` no se pasa por `| tail`.
- 2026-11-05 — **Motivos de partida (desarrollador):** el núcleo se amplía en esta rama, en un commit propio «Núcleo:»
  (9087406): `RegistroDocumentos::familiaDeMotivos(código, nombre, motivosDePartida)`; el sincronizador los crea solo
  al nacer la familia y después son de Administración (renombrados o desactivados no vuelven; uno nuevo en el código
  no se crea). Regla en `docs/convenciones/codigo.md`, con sus pruebas en `SincronizadorEstadosTest`.
- 2026-11-05 — **Selectores de catálogo (desarrollador):** creada la tarea del núcleo 2.0 (rama
  `nucleo-selectores-catalogo`, solo el plan; la hace otra sesión). Cuentas usa siempre el campo de catálogo común
  (anotado en la D11).
- 2026-11-05 — **Paso 2 hecho.** `app/Modules/Crm/Cuentas/CuentasServiceProvider.php` (en `bootstrap/providers.php`
  antes de `AppPanelProvider`): permisos de módulo `crm.cuentas.ver` y `crm.cuentas.atender` (Roles y permisos los
  agrupa solo en «CRM · Cuentas», sin `agrupar()`), parámetro `crm.dias_sin_actividad` (90, de 7 a 365 con
  `ValidadorDiasSinActividad`), familia `crm.cuentas.desactivar` («Desactivar · cuenta») con sus tres motivos de
  partida y su «Otro», y la caja. Las constantes de esas claves viven por ahora en el provider. La caja enlaza a
  `/cuentas`, que no existe hasta el paso 8 (la rama no se fusiona antes). Solo se crean las carpetas que se usan
  (git no guarda carpetas vacías). Prueba: `tests/Modules/Crm/Cuentas/CuentasModuloTest.php`; `tests/Arch` y las de
  navegación, permisos y parámetros, en verde (225).
- 2026-11-05 — **Paso 3 hecho.** Migración `2026_11_05_100001_crear_cuentas.php` (después de las del núcleo del mismo
  día), modelos `Cuenta` y `CuentaMarcaCliente` (con bitácora del núcleo, `RegistraCambios`; alias `cuenta` y
  `cuenta_marca_cliente`), enum `OrigenMarcaCliente` y factories. En la base: una cuenta por ficha; inactiva si y solo
  si tiene motivo, y sin motivo no hay detalle (`cuentas_motivo_inactiva_check`); textos no vacíos; versión ≥ 1; origen
  de la marca `oportunidad | manual`, la de oportunidad con su id y la manual con comentario y sin id; una marca por
  oportunidad y una manual por cuenta (índice parcial). **Desviaciones del DBML:** `cuenta_marcas_cliente` lleva
  `auditoria()` completa (convención; sus columnas de modificación quedan nulas, porque una marca no se edita);
  `oportunidad_id` con índice y sin llave foránea hasta CRM-02. `CuentaMarcaCliente.cuenta_id` se declara no sensible
  (el vigilante lo confunde con una cuenta bancaria): las tablas de Oportunidades, Cotizaciones y Licitaciones con
  `cuenta_id` harán lo mismo. Prueba: `tests/Modules/Crm/Cuentas/CuentasBaseTest.php`.
- 2026-11-05 — **Paso 4 hecho.** `CuentasService` (clase concreta, sin interfaz: no es costura, D12.5) con `buscar`
  (solo usables; `incluirId` siempre presente, marcado y sin repetir; RUC exacto primero), `resumen(id, ?fecha)`
  (`esPublica` a la fecha con `Party::conRolVigente`, en lote) y `deParty`; `usables(Builder)` público para la lista.
  `ResumenCuenta` (con `etiquetaParaSelector()`) y `MotivoNoUsable` (manda la cuenta si las dos están inactivas).
  `RegistroPestanasCuenta` con pestañas (`PestanaCuenta`, `ComponentePestanaCuenta`, que revisa `crm.cuentas.ver` y
  el permiso de la pestaña en cada petición) y botones de cabecera (`BotonCuenta`, una fábrica de la acción de
  Filament por cuenta). `ActividadDeCuentas` vacía, con `AporteActividad` y dos capacidades que la obligan a filtrar
  en la base: `AporteUltimoContacto` (sin contacto desde, orden) y `AporteOportunidadesAbiertas`; un dato, un solo
  módulo; sin aporte, `tiene()` es falso y filtrar falla. `tests/Arch` ya vigila los modelos de Cuentas desde otros
  módulos (descubre el módulo solo). Pruebas: `CuentasLecturaTest` y `RegistrosCuentaTest` (95 en verde con
  `tests/Arch`).
- 2026-11-05 — **Paso 5 hecho.** `CuentasService::revisarRuc` (el primer paso: valida el RUC y da el caso, sin
  escribir), `parecidosPorNombre` (pasa a `PartiesService::posiblesPorNombre`; nunca bloquea) y `crear(DatosCuenta)`
  → `CuentaCreada` (resumen, si la ficha es nueva y la posible pareja DNI ↔ RUC 10), en una sola transacción; acción
  `crm.cuentas.crear` («Crear cuentas»). Reglas: ejecutivo activo con `crm.cuentas.atender`; pública = marcada **o**
  la ficha ya es «Entidad pública» (desde aquí no se le quita, D3), y entonces el nivel es obligatorio; privada sin
  nivel; «Cliente» y «Entidad pública» con `tieneRol` antes de `asignarRol`; en el caso 2 la identidad escrita se
  ignora (se corrige en Personas y empresas). **Carrera del caso 2:** sin más, la segunda persona chocaba al asignar
  «Cliente» («el periodo se cruza») antes de llegar al único de la cuenta; por eso el caso 2 bloquea la fila de la
  ficha (`lockForUpdate`, solo lectura) y vuelve a mirar. **Carrera del caso 4:** `PartyRechazada` de `registrar` se
  relee y, si la ficha ya tiene cuenta, sale el mensaje del caso 1. Las dos, en `tests/Concurrencia` (modo
  `crear-cuenta`). **La confirmación del RUC en grande (caso 4, sin padrón)** es de la pantalla (paso 8): ayuda a quien
  teclea; la acción no la pide. Pruebas: `CrearCuentaTest` (13) y las dos carreras.
- 2026-11-05 — **Paso 6 hecho.** Acciones `crm.cuentas.editar` y `crm.cuentas.editar-propias` (dos clases con los
  mismos campos; el servicio revisa siempre con `puedeSobrePropio`, sin tocar `permisoPorAmbito`): origen, nivel y
  notas; nunca ejecutivo, cliente desde ni pública; una inactiva no se edita; en una privada el nivel guardado se
  conserva. `crm.cuentas.reasignar` (una con versión o varias sin ella, todo o nada, en orden de id; salta las que ya
  son del nuevo; `EjecutivoDeCuentaCambiado` con `ShouldDispatchAfterCommit`; un solo aviso
  `crm.cuentas-asignadas` al nuevo ejecutivo, con enlace a `/cuentas?ejecutivo=<id>`, que la lista del paso 8 debe
  entender). `crm.cuentas.desactivar` (motivo activo de su lista, detalle solo con «Otro» y con el mínimo
  `motivos.minimo_detalle_otro`, impedimentos de `ActividadDeCuentas`, y «desactivar también la ficha» si solo es
  «Cliente» y hay `parties.desactivar`; si no, solo avisa) y `crm.cuentas.reactivar` (mismo permiso, exige la ficha
  activa). `MotivosDeCuentas` (`FuenteDeMotivos`) suma los «Otro» de las cuentas hoy inactivas a «Lo más escrito en
  Otro». Alerta `crm.cuentas-ejecutivo-inactivo` (grupo de avisos nuevo «Comercial»; de fábrica, Gerencia General)
  con `CuentasConEjecutivoInactivo`, enlace `/cuentas?aviso=ejecutivo-inactivo`. Pruebas: `CambiarCuentaTest` (10);
  con `tests/Arch` y las del núcleo de avisos, alertas, preferencias y permisos, 267 en verde.
- 2026-11-06 — **Paso 7 hecho.** Al construir «quitar marca» apareció que la bitácora no guarda motivos: el
  desarrollador eligió anular la marca manual en vez de borrarla, y el motivo en texto libre (precisión en la D8.6).
  La migración se editó (no hay despliegue con datos reales): tres columnas de anulación con su `CHECK` (juntas y solo
  en la manual) y el índice de «una manual» solo sobre las vigentes; DBML al día. `CuentasService::marcarCliente(id,
  fecha, oportunidadId)` y `revertirCliente(id, oportunidadId)` para el efecto de CRM-02 (sin versión; repetir no
  duplica), `marcarClienteManual` y `quitarMarcaCliente`; acciones `crm.cuentas.marcar-cliente` y
  `crm.cuentas.quitar-marca-cliente`. `cliente_desde` se recalcula siempre desde las vigentes, con la cuenta
  bloqueada. Prueba de coherencia y de reglas: `MarcasClienteTest` (125 en verde con `tests/Arch` y el módulo).
- 2026-11-06 — **Paso 8 hecho.** Núcleo, en un commit propio (98fd368): `AccionFilament::campo($accion, $nombre)`, el
  control común de un campo de una acción; «Nueva cuenta» arma con él origen, nivel y rubro (nunca un `Select` a mano).
  En Cuentas: `CuentaResource` (`/cuentas`), `ListarCuentas` con su portada (`PortadaCuentas`: pestañas Todas, Mías,
  Clientes activos, Prospectos, Nuevas este mes, Sin actividad solo si alguien aporta el último contacto, e Inactivas;
  avisos de ejecutivo inactivo, ficha inactiva y sin rubro con «Ver cuáles»; cartera por ejecutivo que filtra; últimos
  cambios de cuentas y marcas; «Reasignar» en bloque; `?ejecutivo=` y `?aviso=` abren la tabla filtrada), `NuevaCuenta`
  en dos pasos (los cuatro casos; en el 4, el RUC en grande con su confirmación y el aviso de nombres parecidos;
  `?ruc=` lo trae puesto desde la ficha), `VerCuenta` (cabecera con cliente o prospecto, pública o privada, ejecutivo y
  marcas; Editar, Reasignar, Rubro, Marcar o Quitar marca, Desactivar con su motivo y «también la ficha», Reactivar,
  y los botones de `RegistroPestanasCuenta`), pestañas Resumen, Contactos (el componente del núcleo, con
  `parties.ver`), las registradas e Historial (`HistorialDeCuenta`: cuenta y marcas; ficha y sus tipos «Entidad
  pública» y «Cliente» solo con `parties.ver`). Cuenta y marca describen su bitácora con nombres legibles (D10.3).
  La pestaña «Cuenta comercial» en Personas y empresas (`CuentaComercialDeFicha`, solo RUC, D13). Pública o privada
  sale de la misma consulta en la lista (D3, sin una llamada por fila).
  - **«Todas» = activas e inactivas**, como dice la D11.1 (la D9.4 decía «Todas muestra las activas»; manda la D11,
    que se aprobó con sus ajustes después).
  - **Selector de motivos de desactivar:** el núcleo aún no tiene uno común (los motivos no son un catálogo); hasta la
    tarea 2.0, `SelectoresCuenta::motivoDesactivar` es el único lugar que los lista. Anotarlo para la 2.0.
  - **Íconos (`tests/Arch/IconosUnicosTest`):** `building-storefront` entra como mismo concepto («una cuenta del
    CRM»: caja, pantalla, pestaña Resumen y pestaña de la ficha); la pestaña «Contactos» de la cuenta se suma a la
    deuda ya anotada de `users` (es el mismo componente que la de la ficha; se arregla con ella en la mejora visual).
  - **Sin revisión visual:** las pruebas cubren lo que hace cada pantalla, no cómo se ve. Falta que el desarrollador
    las mire en su app (lista, portada, Nueva cuenta, ficha y la pestaña en Personas y empresas).
  - Pruebas: `CuentasPantallasTest` (18); con el módulo, `tests/Arch` y las de Personas y empresas, en verde.
- 2026-11-06 — **Revisor de arquitectura.** Un bloqueante y once más; corregidos:
  - **Bloqueante:** «desactivar también la ficha» solo se limitaba en la pantalla. Ahora el servicio lo rechaza si el
    motivo no es «Creada por error (RUC equivocado)» (`CuentasServiceProvider::MOTIVO_CREADA_POR_ERROR`, el código, que
    no cambia al renombrar), con su prueba.
  - **Íconos (desarrollador):** en vez de agrandar la deuda, los contactos de una ficha pasan a `user-circle` en las dos
    fichas (commit «Núcleo:» 0fe5b79); `users` sale de la deuda. Los dos grupos de mismo concepto, también en
    `interfaz.md`.
  - **Una oportunidad marca una sola cuenta:** único parcial sobre `cuenta_marcas_cliente.oportunidad_id` (antes era
    por cuenta y oportunidad); `cuenta_id` con su propio índice. Migración editada, DBML al día, con su prueba.
  - **«Todas»** = activas e inactivas (D11.1), confirmado; la D9.4 corregida arriba.
  - «Quitar la marca» exige la cuenta activa, como «marcar» (y la ficha no lo ofrece en una inactiva); `reactivar`
    bloquea la fila de la ficha al mirarla; «Editar» desde la lista oculta el nivel en una privada; enum en vez de
    `'manual'`; comentario viejo del provider.
  - **Núcleo** (commit 1ae232c): los motivos de partida se validan por su código («Otro.» o dos nombres con el mismo
    código fallan al declarar); un solo comentario en `codigoDe`.
  - **Queda anotado, sin cambiar:** `MotivosDeCuentas` toma `updated_at` como fecha de desactivación; reasignar o
    marcar una cuenta inactiva la corre. Solo afecta el «último uso» de «Lo más escrito en Otro». Si importa, la tarea
    2.0 (que construye esa pantalla) puede leerlo de la bitácora.
- 2026-11-06 — **`composer check` en verde: 2575 de 2575.** Antes, dos corridas se cortaron por pruebas, no por el
  código: tres funciones de prueba con el nombre de otras del núcleo, la lista completa de permisos de
  `CatalogosSeederTest` sin los de Cuentas, y una prueba de motivos que leía sin orden (fallaba al azar). Cada una se
  corrigió y se corrió sola antes de volver a la corrida completa. Rama subida; el PR lo abre Sebastián.
- 2026-11-06 — **`main` traído a la rama (merge 55c23e9), con la 2.2 fusionada (PR #80).** Tres conflictos resueltos
  conservando las dos tareas: en el mapa, las filas 2.1b y 2.2 de `main` y la 2.20a terminada; en
  `tests/Concurrencia`, los escenarios del exceso de partida (modo `aprobar-orden`) y las dos carreras de crear cuenta
  (modo `crear-cuenta`); ningún nombre de función ni escenario se repetía. Revisado contra lo que trajo la 2.2 al
  núcleo: alertas con destinatario propio (parámetro opcional al final; la de Cuentas sigue por sus roles), condición
  en `reglas_aprobacion` (Cuentas no usa aprobaciones) y motivos (las familias de Presupuestos llaman a
  `familiaDeMotivos` con dos argumentos; los motivos de partida son opcionales): sin choques. Plan a
  `docs/planes/completados/` y fila del mapa con ✅.
- 2026-11-06 — **`composer check` en verde con la 2.2 incluida: 2611 de 2611** (pint, phpstan, migraciones y todas las
  pruebas). Rama subida; el PR lo abre Sebastián después de su revisión visual.
