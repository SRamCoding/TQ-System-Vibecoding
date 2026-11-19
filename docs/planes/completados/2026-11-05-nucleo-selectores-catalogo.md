# Plan: Selectores de catálogo

- **Estado:** completado
- **Entrega:** `entrega/nucleo-selectores-catalogo` (sale del plan aprobado, con `main` traído el 2026-11-15)
- **Responsable:** Sebastián (núcleo; modo actual de AGENTS.md)
- **Módulo(s):** Nucleo (`Filament/AccionFilament`, `RegistroAcciones/Campo`, `Services/CatalogosService`, Catálogos y
  Motivos en Configuración)
- **Fase:** F1 (esqueleto y decisiones)
- **Creado:** 2026-11-05 · **Actualizado:** 2026-11-15

## Objetivo

Todo selector de catálogo de un formulario busca en la base al escribir y, a quien tiene permiso, le deja agregar el
valor que falta sin salir del formulario; a quien no, nunca lo bloquea («Otro» con detalle en los motivos). Todo vive
en el campo de catálogo común, así que cada módulo que lo use recibe la mejora sin tocar su código. Va **antes de que
las áreas armen sus formularios** (fila 2.0 del mapa).

## Contexto que leí

Pedido del desarrollador (2026-11-05, desde la sesión Comercial de la 2.20). Lo que hay hoy en el código:

- **El campo de catálogo común ya existe:** `Campo::elementoDe($nombre, $etiqueta, Catalogo::X)` y
  `Campo::listaDe(...)` (`app/Modules/Nucleo/RegistroAcciones/Campo.php`). `AccionFilament::componente()`
  (`app/Modules/Nucleo/Filament/AccionFilament.php`, `TipoCampo::ElementoCatalogo` y `ListaCatalogo`) los dibuja como
  `Select::make()->searchable()->options(...)` con `CatalogosService::opciones($catalogo, $actual)`: **carga toda la
  lista al abrir** y busca en el navegador. `opciones()` ya suma el valor actual aunque esté desactivado, con
  «(desactivado)» (regla de AGENTS.md). Ubigeos son ~1 900 filas que viajan enteras en cada formulario.
- **Ningún selector permite agregar un valor.** Si falta, hay que salir a Configuración → Catálogos, agregarlo y
  volver perdiendo lo escrito; sin `catalogos.editar`, la persona queda bloqueada. Un valor agregado en otra pestaña no
  aparece sin recargar. S10 permite agregar desde el selector; queremos lo mismo.
- **Selectores armados a mano, fuera del campo común** (no recibirían la mejora): `NuevaParty` (`tipo_documento_id`,
  `ubigeo_id`, `roles`, en `Filament/Resources/Parties/Pages/NuevaParty.php`) y `MotivoResource`
  (`tipo_motivo_id`). `SelectoresParty::party()` ya busca en el servidor (`getSearchResultsUsing`), pero sobre
  `PartiesService::buscar`: es el modelo a seguir para la búsqueda, no un catálogo.
- **Agregar un valor:** `CrearElementoCatalogo` (`Acciones/Catalogos/`, clave `nucleo.<catalogo>.crear`), con el
  permiso `catalogos.editar` o, en catálogos con campos de dinero (`Catalogo::tieneSensibles()`: monedas, tipos de
  documento), `catalogos.editar-sensibles`. Pide el **código** (`Catalogo::campoCodigo()`, con formato propio por
  catálogo: `FERIA`, `cliente`, `PEN`…) y los `campos()` del catálogo. Queda en la bitácora como toda acción.
- **Catálogo cerrado:** `Catalogo::esCerrado()` (plan 2.20a, N2); hoy solo `Rubros` (CIIU). `CatalogosService`
  rechaza crear en él (`ElementoCatalogoRechazado::catalogoCerrado`).
- **Columnas de búsqueda:** `Catalogo::columnasBusqueda()` (ubigeos: distrito, provincia, departamento; CUBSO:
  descripción; frases: texto y fuente; los demás, nombre). Ya las usa la tabla de Catálogos.
- **Parecidos:** `PosiblesDuplicados::normalizar()` (`app/Modules/Nucleo/Parties/PosiblesDuplicados.php`) quita
  acentos, mayúsculas, puntuación y la forma societaria (pensado para fichas); `MotivosService::normalizacionSql()`
  hace en SQL lo mismo sin la forma societaria (`unaccent`, `lower`, espacios). La 2.20a agregó `pg_trgm` y
  `PartiesService::posiblesPorNombre` (parecido con umbral, parámetro `parties.umbral_nombre_parecido`).
- **Motivos con «Otro»:** toda familia de motivos tiene su «Otro» con detalle obligatorio (plan 1.5, D9; lo crea
  `SincronizadorEstados`). `MotivosService::detallesDeOtro($familia, $desde)` ya agrupa los detalles escritos en
  «Otro», del más repetido al menos, sumando lo que guardan otros módulos (`FuenteDeMotivos`), **pero ninguna pantalla
  lo muestra todavía**.
- **Catálogos usados en formularios y si tienen «Otro»:** ninguno lo tiene. Los que hoy se eligen en formularios:
  Monedas, Tipos de documento de identidad, Roles de party, Ubigeos, Redes sociales, Aplicaciones, Categorías de frase,
  Unidades de medida, Rubros; y, desde la 2.20, Orígenes comerciales y Niveles de gobierno (el seeder dice «Sin
  "Otro"» a propósito, plan 2.20a, N3). Ver la propuesta en D5.
- Filament `^5.8` (`composer.json`): `Select::getSearchResultsUsing`, `getOptionLabelUsing`,
  `createOptionForm` / `createOptionUsing`.
- Convenciones: `docs/convenciones/interfaz.md` §Patrones de pantalla y §Catálogos; reglas de selectores en AGENTS.md
  (valor actual inactivo; parámetros de closures con el nombre que inyecta Filament: `$search`, `$state`, `$data`).
- Pendientes (`docs/decisiones/pendientes.md`): ninguno bloquea.

## Decisiones

Lo fijado por el desarrollador el 2026-11-05 (a–g del pedido) está **aprobado**. Lo marcado «propuesta» lo decide la
sesión que haga la tarea, con el desarrollador.

- **D1 · Búsqueda en vivo.** **Aprobada (desarrollador, 2026-11-05).** Los selectores de catálogo buscan en la base al
  escribir (`searchable` con búsqueda en el servidor), sin acentos ni mayúsculas; un valor agregado en otra pestaña
  aparece al buscarlo. Incluyen el valor actual aunque esté inactivo (AGENTS.md).
  - *Propuesta:* `CatalogosService::buscar(Catalogo, string $texto, int $limite = 50)` sobre
    `columnasBusqueda()` con `unaccent(lower(...))`, y `etiquetaDe(Catalogo, int $id)` para `getOptionLabelUsing`.
    Los catálogos cortos (menos de ~50 activos) pueden seguir mostrando la lista al abrir (`preload`), pero siempre
    consultando la base al escribir. El orden: coincidencia al inicio primero, luego por código.
  - *Detalle aprobado (desarrollador, 2026-11-15):* la propuesta va tal cual, hasta 50 resultados, para todos los
    catálogos. **Ubigeos, por niveles:** al abrir sin escribir muestra los 25 departamentos; elegir uno muestra sus
    provincias, y elegir una, sus distritos (el valor guardado sigue siendo el distrito). Al escribir busca directo en
    los distritos sin pasar por los niveles, y cada resultado lleva su ruta («Chiclayo — Chiclayo, Lambayeque»), porque
    hay distritos con el mismo nombre. La tabla `ubigeos` solo tiene filas de distrito (con `departamento` y
    `provincia` como texto): los niveles salen de esas columnas (o de los 2 y 4 primeros dígitos de `codigo`), sin
    tabla nueva. El orden por código ya agrupa por departamento y provincia.
- **D2 · Botón «+» junto al selector.** **Aprobada (desarrollador, 2026-11-05).** `createOptionForm`: solo con permiso
  de editar ese catálogo (el `permiso()` de su `CrearElementoCatalogo`, que ya distingue los sensibles); no aparece en
  catálogos cerrados (`esCerrado()`: Rubros); avisa si ya existe uno igual o parecido, sin bloquear; al guardar queda
  elegido; va por la acción registrada `CrearElementoCatalogo`, con su bitácora.
  - *Propuesta (código):* el formulario del «+» pide el nombre y sugiere el código derivado de él según el formato de
    `campoCodigo()` (`Convocatoria privada` → `CONVOCATORIA_PRIVADA`), editable; los catálogos cuyo código no se
    puede derivar (monedas ISO, ubigeos INEI, códigos SUNAT, feriados por fecha) lo piden tal cual.
  - *Propuesta (parecido):* igual con la normalización de `PosiblesDuplicados::normalizar()`; parecido con
    `pg_trgm` y el umbral `parties.umbral_nombre_parecido` (o uno propio de catálogos, si se prefiere separar).
    Ejemplo de prueba: «Feria» frente a «feria».
  - *Detalle aprobado (desarrollador, 2026-11-15):* las dos propuestas tal cual: código sugerido y editable (a mano
    en los catálogos de código oficial) y aviso de parecido con la normalización y el umbral que ya usan las parties
    (`parties.umbral_nombre_parecido`), sin umbral propio de catálogos.
- **D3 · Sin permiso, nada se bloquea.** **Aprobada (desarrollador, 2026-11-05).** En las familias de motivos,
  «Otro» con detalle obligatorio; en Configuración cada familia muestra **«Lo más escrito en Otro»**
  (`MotivosService::detallesDeOtro`) para que Administración agregue lo que falta.
  - *Propuesta:* en el panel de cada familia (Motivos), una sección con los detalles agrupados, sus veces y su último
    uso, de los últimos 12 meses por defecto, y un botón «Agregar a la lista» que abre el alta del motivo con el texto
    precargado (`MotivosService::agregar`).
  - *Detalle aprobado (desarrollador, 2026-11-15):* la propuesta tal cual (agrupado sin acentos ni mayúsculas,
    últimos 12 meses, con el botón «Agregar a la lista»).
- **D4 · Todo en el componente común.** **Aprobada (desarrollador, 2026-11-05).** La búsqueda, el «+» y el valor
  actual viven en `AccionFilament` (`TipoCampo::ElementoCatalogo` y `ListaCatalogo`); ninguna pantalla arma un
  `Select` de catálogo a mano. Regla escrita en `docs/convenciones/interfaz.md` §«Selectores de catálogo».
  - *Propuesta:* exponer también `SelectorCatalogo::de(Catalogo, $nombre)` (o similar) para los formularios que no
    salen de una acción (los de dos pasos, como Nueva cuenta), y pasar a él los selectores sueltos de `NuevaParty` y
    `MotivoResource`. Una prueba de arquitectura que falle si aparece `CatalogosService::opciones` fuera del componente.
  - *Detalle aprobado (desarrollador, 2026-11-15):* la propuesta tal cual, con la prueba de arquitectura.
- **D5 · Catálogos sin «Otro».** *Propuesta.* Ningún catálogo tiene «Otro» y la mayoría no lo necesita (monedas,
  ubigeos, tipos de documento, roles, aplicaciones, unidades, rubros: listas oficiales o del sistema). Candidatos a
  tenerlo, porque una persona sin permiso podría no encontrar su valor al llenar: **Orígenes comerciales** y **Redes
  sociales**. Opciones: (a) un elemento «Otro» con un campo de detalle en el registro que lo usa (como los motivos);
  (b) sin «Otro»: quien no tiene permiso elige lo más cercano y avisa (un aviso a Administración desde el selector);
  (c) dar el permiso de agregar a esos dos catálogos a más roles. A decidir con el desarrollador; la 2.20 (Cuentas)
  queda sin «Otro» en orígenes hasta entonces (plan 2.20a, N3).
  - **Aprobada (desarrollador, 2026-11-15): opción (a).** Orígenes comerciales y Redes sociales tienen un elemento
    «Otro» con detalle obligatorio en el registro que lo usa, como los motivos; esos detalles se suman a «Lo más
    escrito en Otro» (D3) para que Administración agregue lo que se repite. El campo de detalle en el registro de
    Cuentas (módulo `Crm/Cuentas`) y en el de redes sociales se define al hacer la tarea, sin tocar los modelos de otro
    módulo (regla 2): el detalle se informa por su servicio o como `FuenteDeMotivos` equivalente. Esto cierra la N3
    del plan 2.20a.

## Pasos

- [x] 1. Decisiones propuestas (D1 a D5) aprobadas con el desarrollador
- [x] 2. `CatalogosService::buscar` y `etiquetaDe`, con sus pruebas (acentos, mayúsculas, columnas de ubigeo, valor
      inactivo)
- [x] 3. Búsqueda en el servidor en `AccionFilament` (`ElementoCatalogo` y `ListaCatalogo`) y el selector suelto (D4)
- [x] 4. El «+»: permiso, catálogo cerrado, código sugerido, aviso de parecido, queda elegido, por
      `CrearElementoCatalogo`
- [x] 5. Pasar al componente común los selectores sueltos (`NuevaParty`, `MotivoResource`) y la prueba de arquitectura
- [x] 6. «Lo más escrito en Otro» en Configuración → Motivos (D3), y lo que se decida en D5
- [x] 7. Regla «Selectores de catálogo» en `docs/convenciones/interfaz.md`
- [x] 8. Revisor de arquitectura y `composer check` en verde

## Preparado para lo diferido

- Los módulos que usen `Campo::elementoDe` / `listaDe` (o el selector suelto de D4) reciben la mejora solos. La 2.20
  (Cuentas) ya se compromete a usar el campo común para origen, nivel de gobierno, rubro y motivo de desactivación.
- **Fuera de esta tarea:** cambiar de catálogo sin cerrar el panel en Configuración → Catálogos; va a la mejora visual
  final (Sebastián).

## Criterio de aceptación

- Un valor creado después de abrir el formulario aparece al buscarlo.
- El «+» está oculto sin permiso y en un catálogo cerrado.
- Crear desde el selector lo deja elegido y en la bitácora.
- Aviso de parecido con «Feria» frente a «feria» (avisa, no bloquea).
- Un valor actual inactivo se ve y el formulario se guarda.
- «Lo más escrito en Otro» cuenta bien (agrupa sin acentos ni mayúsculas, suma las `FuenteDeMotivos`).

## Bitácora

- 2026-11-05 — Plan creado por la sesión Comercial (2.20) a pedido del desarrollador, en la rama
  `nucleo-selectores-catalogo` desde `main`. Lo hace otra sesión. Contexto del código averiguado (arriba).
- 2026-11-15 — D1 aprobada en detalle por el desarrollador: la propuesta para todos los catálogos y ubigeos por
  niveles (departamento › provincia › distrito al abrir; búsqueda directa de distritos con su ruta al escribir).
- 2026-11-15 — D2 aprobada en detalle: código sugerido editable y aviso de parecido con el umbral de parties.
- 2026-11-15 — D3 aprobada en detalle: «Lo más escrito en Otro» de 12 meses con «Agregar a la lista».
- 2026-11-15 — D4 aprobada en detalle: selector suelto común, los dos selectores a mano pasan a él y prueba de
  arquitectura que lo vigila.
- 2026-11-15 — D5 aprobada: opción (a), «Otro» con detalle en Orígenes comerciales y Redes sociales. Paso 1 cerrado:
  D1 a D5 aprobadas. Sigue el paso 2.
- 2026-11-15 — Rama `entrega/nucleo-selectores-catalogo` en la copia `TechQuk-selectores`, desde
  `plan/nucleo-selectores-catalogo` con `main` traído (sin conflictos). El desarrollador pidió hacer todo lo del corte
  de corrido, sin pausas.
- 2026-11-15 — Paso 2 hecho. `CatalogosService::buscar(catálogo, texto, límite = 50)`: activos, código y columnas de
  búsqueda sin acentos ni mayúsculas (`BusquedaSinAcentos`, comodines escapados), primero lo que empieza con el texto,
  luego por código; en ubigeos solo el distrito, con la ruta (`Ubigeo::ruta()`: «Chiclayo — Chiclayo, Lambayeque»).
  `etiquetaDe()` (con «(desactivado)»; nula si no existe), `cuantosActivos()`, y los niveles de ubigeo
  `departamentos()`, `provincias(dd)` y `distritos(dddd)` por los dígitos del código INEI (sin tabla nueva). Pruebas:
  `CatalogosBusquedaTest` (6).
- 2026-11-15 — Paso 3 hecho. `Nucleo/Filament/SelectorCatalogo::de(catálogo, nombre, varios)`: el selector común
  (D4) que `AccionFilament` usa para `ElementoCatalogo` y `ListaCatalogo`. Al abrir, los primeros 50 por código más el
  valor actual aunque esté desactivado; al escribir, `CatalogosService::buscar` cada vez (`getSearchResultsUsing`,
  `getOptionLabelUsing`/`getOptionLabelsUsing`). Ubigeos de uno, por niveles: valores «dep:14» y «prov:1401» que
  vuelven a dibujar la lista (`live`), con «‹ Todos los departamentos» y «‹ Provincias de …» para volver; un nivel
  elegido no pasa la validación («Elige un distrito…»). Encontrado al probar: `[...$a]` renumera las llaves enteras, y
  los ids de los distritos se perdían; se usa `+`. Pruebas: `SelectorCatalogoTest` (4); las pantallas con campos de
  catálogo (Cuentas, Parties, redes, rubro: 98) siguen en verde.
- 2026-11-15 — Paso 4 hecho. El «+» del selector común (`createOptionForm`/`createOptionUsing`): visible solo con el
  permiso de `nucleo.<catálogo>.crear` (que ya pide `catalogos.editar-sensibles` en los de dinero) y nunca en un
  catálogo cerrado (Rubros). El formulario son los campos de esa acción con el nombre primero: al dejarlo, sugiere el
  código (`Catalogo::codigoDesdeNombre`: con el formato de cada catálogo; nulo en los de código oficial) mientras no se
  haya cambiado a mano, y avisa de iguales o parecidos (`CatalogosService::parecidos`: normalización de
  `PosiblesDuplicados` en SQL y `similarity` con `parties.umbral_nombre_parecido`, activos o no). Crea por
  `EjecutorAcciones` (bitácora) y deja elegido lo creado: `EdicionCatalogosService::crear` y `CrearElementoCatalogo`
  devuelven el id. Un rechazo sale como aviso y el panel sigue abierto con lo escrito (`Halt`). Pruebas:
  `SelectorCatalogoAgregarTest` (5) con un formulario suelto de prueba (`Fixtures/FormularioConCatalogo`). Al probar:
  «Ferias» frente a «Feria» da 0.62, bajo el umbral de 0.70, y no avisa (lo igual sí).
- 2026-11-15 — Paso 5 hecho. `NuevaParty`: tipo de documento, ubigeo (ahora por niveles) y roles pasan a
  `SelectorCatalogo::de`. **Desviación:** el selector de `MotivoResource` (`tipo_motivo_id`) no elige de un catálogo M5
  sino la familia de motivos (no está en `Catalogo`), así que queda como está; lo de Motivos va en el paso 6. Los dos
  filtros de tabla de Oportunidades (línea de solución, origen) usaban `opciones()`: pasan a
  `CatalogosService::paraFiltro()` (un filtro no es un formulario). Prueba de arquitectura
  `tests/Arch/SelectoresCatalogoTest`: falla si `->opciones(Catalogo::…)` aparece fuera de su servicio.
- 2026-11-15 — Paso 6 hecho. **D5:** `Catalogo::codigoOtro()` (orígenes `OTRO`, redes `otra`, del sistema; sembrados
  «Otro» y «Otra red» con el ícono de enlace), al final del selector. `CatalogosService::esOtro()`,
  `detalleDeOtro()` (con «Otro», obligatorio y con `motivos.minimo_detalle_otro`; sin él, nulo; `DetalleDeOtroRechazado`)
  y `detallesDeOtro()`, que suma las `Catalogos\FuenteDeOtros` registradas en `RegistroOtrosDeCatalogo` (el equivalente
  de `FuenteDeMotivos`; `MotivosService::juntar` pasa a público). `Campo::elementoDe(…, detalle:)`: `AccionFilament` lo
  vuelve `live` y muestra el detalle solo con «Otro», como el de los motivos. Columnas nuevas (migraciones de cada
  módulo): `cuentas.origen_detalle` y `oportunidades.origen_detalle` (150) y `party_redes_sociales.detalle` (100); los
  servicios las validan (la base no conoce el id del «Otro» del núcleo, así que sin `CHECK`). Oportunidades: sin origen
  propio hereda el de la cuenta con su detalle (`ResumenCuenta::origenDetalle`). La red se muestra con su detalle
  (`PartyRedSocial::nombreDeLaRed()`). Fuentes: `OtrosDeOrigenDeCuentas`, `OtrosDeOrigenDeOportunidades` y
  `OtrasRedesSociales`. **D3:** «Lo más escrito en «Otro»» en Configuración → Motivos, un panel deslizante con el
  componente `LoMasEscritoEnOtro` (filtro «Lista»: cada familia de motivos y «Catálogo: …»; texto, veces y última vez
  de los últimos 12 meses; «Agregar a la lista» por `nucleo.motivos.agregar` o `nucleo.<catálogo>.crear` con el texto
  y el código sugerido). Pruebas: `OtroEnCatalogosTest` (5), `Crm/Cuentas/OrigenOtroTest` (3) y
  `Crm/Oportunidades/OrigenOtroOportunidadTest` (2).
- 2026-11-15 — Paso 7 hecho. `interfaz.md` § «Selectores de catálogo» (búsqueda, desactivados, ubigeos por niveles,
  el «+», «Otro» y lo más escrito); `codigo.md`: cómo lleva un catálogo con «Otro» su detalle y su fuente.
- 2026-11-15 — Pruebas de los pasos 6 y 7 en paralelo: Arch 117, CRM 414 y núcleo 2416. Ajustadas por D5: las dos que
  cuentan lo sembrado (ahora con «Otro» y «Otra red») y una de la ficha de Cuentas que tomaba el último origen (ahora
  «Otro», que pide su detalle). Encontrado: la ayuda de prueba `distritosDePrueba()` chocaba con otra del mismo nombre
  (Pest comparte las funciones globales); se renombró.
- 2026-11-15 — Paso 8 hecho. `composer check` completo en verde (**3236**). Revisor de arquitectura: sin bloqueantes;
  observaciones atendidas: `CHECK` de detalle no vacío en las tres columnas (`btrim(coalesce(…, 'x')) <> ''`, como
  `cuentas_textos_check`; que vaya solo con «Otro» sigue en el servicio porque la base no conoce el id del «Otro»);
  Oportunidades rechaza un detalle sin origen; el «+» avisa si el permiso se retiró a mitad (`AuthorizationException`
  → aviso y `Halt`); «Lo más escrito» usa `Calendario::ahora()` y dice que en los catálogos «Última vez» es el último
  cambio del registro; `codigosDelSistema()` une el «Otro» con los demás; la prueba de arquitectura dice que es
  parcial y nombra la excepción de `tipo_motivo_id`. Pruebas nuevas de rechazo (Oportunidades al editar, redes por su
  servicio, `CHECK` en blanco, agregar sin permiso sin pantalla). Después: CRM (415), redes y migraciones en verde.
  Plan a `completados/`, mapa con ✅.
