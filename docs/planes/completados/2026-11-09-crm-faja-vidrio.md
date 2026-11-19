# Plan: el tablero de oportunidades como «faja de vidrio» (CRM-02)

- **Estado:** terminada (2026-11-10): `composer check:tarea` en verde; rama subida. El `composer check` completo va al
  cerrar la entrega `entrega/crm-embudo`, antes del PR (a cargo de Sebastián)
- **Responsable:** Sebastián (Comercial; modo de trabajo solo, con sesiones en paralelo)
- **Entrega:** `entrega/crm-embudo` (CRM-02 y lo que siga de Comercial)
- **Módulo(s):** Crm/Oportunidades (solo pantalla y lecturas de su servicio)
- **Fase:** F2
- **Creado:** 2026-11-09 · **Actualizado:** 2026-11-10

## Objetivo

La vista «Tablero» de Oportunidades pasa a ser una faja horizontal en 3D (CSS 3D, Alpine y Livewire, sin Three.js),
como el prototipo aprobado: cada oportunidad es una caja de vidrio que se mueve arrastrándola, y cada movimiento
ejecuta la acción real de la 2.21. La vista «Tabla» se queda. Tarea 2.21b del mapa.

## Contexto que leí

- El prototipo aprobado, `docs/diseno/fuentes/prototipos/faja-vidrio-oportunidades.html` (guardado por el
  desarrollador el 2026-11-09). Es guía de comportamiento: su código es ilustrativo y sus datos, requisitos, textos,
  días y contratos son de ejemplo (`HOY`, 12 oportunidades inventadas, `GATES`, `MOT`, `PA`, `CT-2026-0NN`).
  - Mundo: `.world` con `perspective` y cámara `translateZ(-dist) rotateX(pitch) rotateZ(yaw) translate3d(-tx,-ty,0)`;
    piso en X/Y y +Z hacia arriba; zona de 600 px, caja de 170×120 y alto por monto `90 + √(m/410000)·120`.
  - Escáner por zona (lámina con barrido; clases `aim`, `ok`, `no`), tubería de 22 caras con bridas, cortina de 10
    tiras con resorte amortiguado, canaleta roja delante, sala de ganadas con pallets y sótano de perdidas.
  - Cámara: rueda acerca y aleja; Ctrl+rueda, A/D y ←/→ saltan de zona; arrastrar el fondo desplaza; `overview()`,
    minibarra, inclinación con el mouse; viñetas que no cambian de tamaño en pantalla y desaparecen de cerca.
  - Arrastre: la caja se levanta, se proyecta el cursor al piso (`toFloor`), avance automático en los bordes,
    `dropTarget` (misma zona, siguiente, salto, atrás, tubería, canaleta), empuje del hombrecito y resultado.
  - Despiece: 7 capas apiladas con un panel de lectura a la derecha; Esc vuelve.
  - Hombrecito: cuerpo por articulaciones, uniforme por zona, ordenar, dormir con tres poses de piernas y «zzz».
  - **Lo que el prototipo no tiene:** terminales por zona, tablas, límite de cajas (solo 4 lugares por zona), modo
    claro y guardado de nada.
- Plan 2.21 (`docs/planes/completados/2026-11-06-crm-oportunidades.md`): acciones `CalificarOportunidad`,
  `EnviarPropuestaOportunidad`, `NegociarOportunidad`, `GanarOportunidad` y `PerderOportunidad`; requisitos por etapa
  (`RequisitosDeEtapa`), secciones de formulario (`SeccionesDeOportunidad`), impedimentos, `PanelesOportunidad`, el
  tablero actual (`TableroOportunidades`), `PortadaOportunidades` (días en la etapa, `pasaElMaximo`, cerradas del
  período), `ContratosDeOportunidad` (hoy `ContratosSinModulo`) y `RegistroPestanasOportunidad`.
- **La próxima acción no existe todavía:** la 2.21 la dejó para CRM-05 (Actividades), con su punto de extensión en
  `SeccionesDeOportunidad` (bitácora de la 2.21, puntos 7 y 8: «nada sobre datos que aún no existen»).
- `interfaz.md` (patrón general de pantallas, colores, reducir animaciones) y `AGENTS.md`.
- Pendientes: la fila «Embudo comercial» (qué exige cada etapa, porcentajes y días) no bloquea: la faja lee lo que
  la 2.21 ya tiene configurable.

## Decisiones

Del desarrollador en el pedido del 2026-11-09, salvo las marcadas «propuesta del agente», que se le presentan antes
de escribir código.

- **D1. Se aparta del patrón general** de `interfaz.md` (decisión del desarrollador, 2026-11-09): pantalla 3D propia
  en lugar de cajas con buscador y panel deslizante. Se anota en `interfaz.md` como excepción con su motivo.
- **D2. Reemplaza al «Tablero» actual;** la «Tabla» se queda. En el celular, la vista por defecto es la Tabla y la
  faja queda como opción.
- **D3. La interfaz no decide reglas.** Soltar en la zona siguiente llama, por Livewire, a la acción de esa
  transición por `EjecutorAcciones` (calificar, enviar_propuesta, negociar); en la tubería, a ganar; en la canaleta,
  a perder. Saltar o retroceder se explica en una línea y no llama a nada. La ficha ofrece las mismas acciones.
- **D4. Bloqueo optimista:** la versión se captura al tomar la caja y viaja con la acción. Si otro la movió, se avisa
  y se recarga la zona.
- **D5. El diálogo antes de animar** muestra lo que la acción pide (sus mismos campos: los requisitos de la etapa y
  las secciones registradas). **Próxima acción (propuesta del agente, aprobada por el desarrollador el 2026-11-09):** hasta que exista CRM-05 no se pide, no hay
  punto ámbar y la columna dice «—». El punto y la columna leen de un dato opcional del resumen, que CRM-05 llena.
- **D6. Perder** pide lo de `PerderOportunidad`: motivo del catálogo, «¿lo ganó otra empresa?», ganador y precio.
- **D7. Código de la caja (propuesta del agente, aprobada por el desarrollador el 2026-11-09):** el número real de la oportunidad (serie `OP`), no `OPO-AAAA-NNN`,
  que no existe en el sistema.
- **D8. Límite por zona,** constantes en un solo lugar (`LimiteCajasFaja`): hasta 6, caja completa; de 7 a 12, solo el
  código y más chica; más de 12, las 11 de mayor monto más una pila «+N más» que abre el modal de la zona. Igual en
  las salas de ganadas y perdidas, contando desde las más recientes.
- **D9. Tablas de cada zona y de las salas** desde un método nuevo de `OportunidadesService` (por etapa o por
  resultado, con búsqueda, orden y paginación), que devuelve resúmenes sin modelos de otros módulos (cuenta por
  `CuentasService`, contrato por `ContratosDeOportunidad`). Contrato «—» mientras no exista el módulo.
- **D10. Despiece:** una capa por pestaña que existe o está registrada (Resumen, Competidores, Historial de etapas, y
  las que sumen otros módulos por `RegistroPestanasOportunidad`). Cotizaciones, Presupuesto, Actividades y Documentos,
  vacías con su texto de espera hasta que llegue su módulo.
- **D11. El hombrecito es solo pantalla:** su posición y el «desorden» viven en `sessionStorage` (con try/catch),
  nunca en la base. El empuje al escáner es la animación de la acción real.
- **D12. Reducir animaciones** (preferencia o sistema): sin hombrecito animado, sin vuelos de cámara ni física de la
  cortina; los cambios son instantáneos.
- **D13. Colores (propuesta del agente, aprobada por el desarrollador el 2026-11-09):** el mundo es oscuro también en modo claro, como un visor; la barra
  superior, los diálogos y las tablas siguen el tema. Los colores salen de variables con nombre, no de códigos
  sueltos en cada pieza.
- **D14. Accesibilidad:** cada caja es enfocable y Enter la abre; desde la ficha se pasa, gana o pierde sin
  arrastrar.

## Pasos

- [x] 1. Plan y fila 2.21b en el mapa; prototipo en `docs/diseno/fuentes/prototipos/`. Decisiones marcadas
      «propuesta del agente», aprobadas por el desarrollador.
- [x] 2. Servicio: consulta por etapa o resultado (búsqueda, orden, paginación) y `LimiteCajasFaja`, con pruebas Pest.
- [x] 3. Pantalla base: página Livewire que reemplaza al Tablero; mundo, cámara, zonas, viñetas, escáneres y cajas
      (alto por monto, grabado, grieta de atascada); la Tabla por defecto en el celular.
- [x] 4. Arrastre y acciones: levantar, proyectar, avance en bordes, soltar, diálogo con los campos de la acción,
      escáner verde o rojo, mensaje de validación, bloqueo optimista; pruebas Livewire.
- [x] 5. Tubería, cortina, canaleta, salas de ganadas y perdidas, terminales y modales con sus tablas.
- [x] 6. Despiece de la caja con sus capas y el panel de lectura.
- [x] 7. El hombrecito: cuerpo, uniformes, ordenar, dormir.
- [x] 8. Reducir animaciones, teclado, medición con decenas de oportunidades de prueba.
- [x] 9. `interfaz.md` (la excepción), revisor de arquitectura, `composer check:tarea` (el `composer check` completo,
      al cerrar la entrega).

## Preparado para lo diferido

- CRM-05 llena la próxima acción del resumen y su sección del diálogo; el punto ámbar y la columna ya la esperan.
- Cotizaciones, Presupuesto, Actividades y Documentos se suman como capas con `RegistroPestanasOportunidad`.
- El contrato de las ganadas sale de `ContratosDeOportunidad` cuando exista el módulo de contratos.
- El arrastre entre cajas desordenadas y el hombrecito no guardan nada: nada que migrar después.
- Cuando llegue el módulo de contratos, su dueño implementa `ContratosDeOportunidad::tieneDatos()` devolviendo `true`
  (y `etiquetasDeContratos`): hasta entonces `ContratosSinModulo` dice que no hay datos y el despiece no muestra la
  fila «Contrato» de las ganadas.

## Criterio de aceptación

- Pruebas: el límite (`LimiteCajasFaja`), las consultas de cada modal, que soltar en la zona siguiente llame a la
  acción correcta y que su fallo devuelva la caja con el mensaje, ganar y perder desde la faja, y que el escenario de
  aceptación de oportunidades del v4 siga pasando.
- Visible: la faja en perspectiva con sus cuatro zonas, las cajas que se arrastran y pasan por el escáner, la
  tubería con su cortina, la canaleta, las salas, los modales con tabla y el despiece.

## Bitácora

- 2026-11-09 — Plan escrito con el resumen del prototipo. Faltan en el prototipo los terminales, las tablas y el
  límite de cajas: se construyen según el pedido. La próxima acción llega con CRM-05.
- 2026-11-10 — Paso 2: `LimiteCajasFaja::para(n)` en `Services/` (junto a los demás datos de lectura del módulo; sin
  carpeta nueva) con `ModoCajasFaja` y `CajasFaja`; `LugarFaja` (4 zonas + ganadas + perdidas) con
  `ordenDeLasCajas()`; `OportunidadesService::tablaDeLaFaja()` (devuelve `PaginaFaja` de `FilaFaja`) y
  `totalesDeLaFaja()` (`TotalLugarFaja` por lugar); `FajaConsultasTest`. Decisiones: (a) «mayor monto» es en moneda
  local al T.C. de compra de hoy, como el embudo; sin T.C., al final; las salas, por cierre real desc e id desc. (b) Las
  salas y sus totales usan el período de la columna «Cierre» del tablero (`crm.tablero_cierre_periodo`,
  `cerradasDelPeriodo`), para que el terminal y su tabla digan lo mismo; las de antes, en la Tabla. Los montos de las
  cerradas también se convierten al T.C. de hoy. (c) La búsqueda es por número o nombre: buscar por cuenta exigiría
  leer la tabla de Cuentas (`CuentasService::buscar` solo da usables y hasta 50). (d) Ver no se limita a las propias
  (la D10 de la 2.21 limita editar): todos ven todas, como el tablero. (e) El contrato de las ganadas: método nuevo
  `ContratosDeOportunidad::etiquetasDeContratos(ids)` (D9); `ContratosSinModulo` devuelve `[]` → «—». Contratos lo
  implementa con el resto de la C3. (f) El motivo de una perdida, del último paso a «perdida» del historial, con su
  detalle. (g) `proximaAccion` siempre nula (D5); la fila trae la `version` para el bloqueo optimista (D4).
- 2026-11-10 — Paso 3: `TableroOportunidades` (misma ruta `/oportunidades/tablero` y nombre `tablero`; título «Faja de
  oportunidades»; «Lista» arriba; mismo permiso) dibuja `oportunidades::faja.pantalla` con los datos de
  `Filament/FajaDeVidrio::datos()`, que lee `totalesDeLaFaja()` y, por zona, `tablaDeLaFaja()` con
  `LugarFaja::ordenDeLasCajas()` y `porPagina` = las dibujadas de `LimiteCajasFaja::para()` (sin método nuevo en el
  servicio). `FajaDeVidrio` solo pone medidas de pantalla: la cuadrícula por zona (completas: 3 × 2 cajas de 170 × 120;
  con código: 4 × 3 de 120 × 84, la pila en el lugar 12) y el alto por monto en soles (`hOf` del prototipo). Los colores
  del mundo, una vez, en `FajaDeVidrio::colores()` (brillo = `ColoresMarca::MORADO`); los de los controles siguen el
  tema. La cámara (`faja/camara.blade.php`, `window.tqFaja` como las demás vistas con Alpine) porta del prototipo
  `aplicarCamara`, `volarA`, `saltarZona`, `desplazar`, la rueda, A/D y ←/→ (no con el foco en un campo ni con un
  diálogo abierto), viñetas con escala inversa e inclinación. Ganchos: eventos `tq-faja-abrir` (cancelable; sin quien
  lo cancele abre la ficha), `tq-faja-abrir-lugar` (la pila; sin quien lo cancele, la Tabla) y `tq-faja-quieta`;
  `arrastre.caja`, `alPiso()`, `marcarEscaner()` y `avisar()` para el paso 4; huecos `data-hueco` de tubería, canaleta y
  hombrecito. Los paneles «Avanzar» (`calificar`, `enviarPropuesta`, `negociar`) siguen en la página para el paso 4.
  Desvíos: (a) el alto lleva tope (√ hasta 1,5: 270 px) para que un monto enorme no tape la zona; (b) celular: la
  entrada de Oportunidades ya abre la Tabla (lista), así que no se redirige nada; en la faja, un aviso con el enlace a la
  Tabla en pantallas de menos de 768 px; (c) sin pellizco con dos dedos (no lo pide el pedido); (d) la viñeta no se pinta
  ámbar por atascadas (el pedido dice «solo el número»); (e) las ganadas y perdidas ya no salen en el tablero hasta
  sus salas (paso 5): la columna «Cierre» se fue con el tablero viejo. `FajaPantallaTest` (8 pruebas) y el tablero de
  `OportunidadesPantallasTest`, al día. Por revisar a ojo: perspectiva, caras de las cajas y grabado, cuadrícula de 12,
  escáneres, viñetas, rueda y Ctrl + rueda, inclinación, modo oscuro y claro de los controles y «reducir animaciones».
- 2026-11-10 — `OportunidadesPantallasTest` «las pestañas de la ficha» fallaba (0 días en vez de 5), solo o junto a
  `FajaConsultasTest`: no era estado que se filtrara entre pruebas. La `fecha` de `historial_estados` la pone el reloj de
  la base (`useCurrent`; en PostgreSQL, la hora de inicio de la transacción), así que `travelTo` no la movía y las dos
  filas nacían con la misma hora; el `assertSee('5')` pasaba solo cuando la hora real o un id aleatorio de la página
  tenía un 5. Arreglo en la prueba (el reloj de la base es a propósito: `EntraEnEtapa` copia esa fecha para no depender
  de dos relojes): fija las dos fechas a mano con `DB::table` (el historial solo se inserta, como en
  `LecturasEstadosTest`) y compara los días fila por fila con `getTableRecords()`, más fechas y cambios en orden.
- 2026-11-10 — Rama renombrada a `entrega/crm-embudo` (antes `crm-faja-vidrio`). Flujo de entregas aprobado por el
  desarrollador (`AGENTS.md`, «Cómo trabajar»): la 2.21b cierra con `composer check:tarea`; la corrida completa,
  al cerrar la entrega.
- 2026-11-10 — Paso 4: arrastrar una caja ejecuta la acción real. En `TableroOportunidades`: `abrirEnFaja(id, desde,
  hacia, version)` elige la acción del movimiento (`accionDeLaFaja`: zona siguiente → calificar, enviar_propuesta o
  negociar; final desde Negociación → ganar; canaleta → perder), revisa la versión tomada al agarrar la caja (D4) y abre
  su diálogo con `mountAction(<acción>, {oportunidad, version})`; saltar, retroceder o ganar fuera de Negociación
  devuelven su línea (`NO_SE_SALTA`, `NO_SE_RETROCEDE`) sin llamar a nada. Los diálogos (`calificar`, `enviarPropuesta`,
  `negociar`, `ganar`, `perder`) son los paneles de la ficha (`PanelesOportunidad`, con los campos de cada acción, los
  requisitos de la etapa y la confirmación de los 90 días) en ventana chica y sin ejecutar: al confirmar devuelven lo
  llenado a la faja (`tq-faja-dialogo-listo`, sin la versión leída al abrir). La faja lleva la caja al escáner (gancho
  `tq-faja-empujar` para el hombrecito del paso 7) y ahí `moverEnFaja` (`#[Renderless]`) ejecuta la acción por
  `EjecutorAcciones` y devuelve `{ok, conflicto, mensaje, version, etapa}`: el mensaje de la acción
  (`ValidationException`, `ErrorDeNegocio`) o `conflicto` con `VersionDesactualizada`. Vista: cada caja con
  `wire:key` y una capa `data-desplazo` (`wire:ignore.self`) que mueve el arrastre sin que Livewire la pise al redibujar;
  al terminar, `$refresh` y se limpian. Cerrar el diálogo sin confirmar devuelve la caja. La ficha no cambia: ya ofrece
  pasar, ganar y perder con las mismas acciones; Enter sigue abriendo la caja (D14). `FajaAccionesTest` (11 pruebas).
  Desvíos: (a) el permiso se revisa también al abrir el diálogo (si no, Filament no lo monta y no habría mensaje), con
  el mismo texto de `AccionRechazada::sinPermiso`; (b) sin tubería ni cortina (paso 5): ganar desliza la caja al final y
  la desvanece; (c) soltar en la misma zona la devuelve a su lugar (el «desorden» llega con el hombrecito, paso 7); (d)
  al pasar, la cámara vuela a la zona nueva solo si no está en la vista general. Por revisar a ojo: levantar y seguir al
  cursor con el ratón y con el dedo, el avance en los bordes, el escáner al apuntar, verde y rojo, la canaleta y el
  final resaltados, la caída a la canaleta, cancelar el diálogo y «reducir animaciones».
- 2026-11-10 — Paso 5: el final y los lugares para ver el contenido (B1, B5, B6). Mundo: la boca de la tubería de
  acero (22 caras, brida en la boca y otra atrás, soportes, fondo oscuro) con una cortina de 10 tiras de PVC con física
  (resorte amortiguado con ruido; `golpearCortina`); ganar ya entra por la cortina (`entrarPorLaCortina`). Canaleta:
  brillo rojo al apuntarle, labio de acero detrás y franjas de aviso delante. Terminales en el piso por zona («Ver las N
  de <zona>» y «N · S/ total»), el de ganadas junto a la tubería y el letrero rojo «↓ Ver perdidas» sobre la canaleta;
  en la barra, «Ver ganadas · N» y «Ver perdidas · N»; viñetas de las salas, solo con el número. Salas: niveles del
  mundo (`FajaDeVidrio::SALAS`) con las cajas de `tablaDeLaFaja` por `LugarFaja::ordenDeLasCajas()` y
  `LimiteCajasFaja` (pallets en ganadas; cajas caídas, con giro fijo por id, en el sótano), «Volver a la faja» (también
  Esc), «Ver en tabla» y un recuadro con cuántas y el total. Cámara: `entrarGanadas` (vuela a la boca, abre la cortina y
  entra), `entrarPerdidas` (baja hacia la canaleta), `volverAFaja`, con fundido; `volarA` ahora devuelve una promesa.
  Tablas: `TablaDeLaFaja` (componente Livewire con tabla de Filament de datos propios, como `TablaHistorial`) que lee
  solo `tablaDeLaFaja` (búsqueda, orden en `ORDENES_FAJA`, páginas de 10/25/50; sin orden elegido, el de las cajas), en
  la ventana `verLugar` de la página (título y «N · S/ total» por `FajaDeVidrio::textoDelTotal`). La pila «+N más» la
  abre (la faja cancela `tq-faja-abrir-lugar`). Tocar una fila emite `tq-faja-abrir-fila` y la faja lo pasa a
  `tq-faja-abrir` (sin despiece, la ficha; si el despiece lo toma, se cierra la ventana). Columnas: código,
  oportunidad, cuenta o entidad, monto, ejecutivo y, en las zonas, días en la etapa (ámbar si atascada) y cierre
  estimado; ganadas, «Ganada el» y contrato; perdidas, «Perdida el», motivo, ganador y precio. Decisión del
  desarrollador (2026-11-10, C3): `ContratosDeOportunidad::tieneDatos()`; con la de fábrica la columna «Contrato» no se
  muestra (ADR 0020, C3). La próxima acción sigue la misma regla en un solo lugar, `FajaDeVidrio::conProximaAccion()`
  (falso hasta CRM-05): ni columna ni punto ámbar. Reducir animaciones: sin vuelos, sin fundido y la cortina quieta.
  `FajaSalasTest` (13 pruebas); `FajaPantallaTest` y el tablero de `OportunidadesPantallasTest` al día (ganadas y perdidas ahora en sus salas); `tests/Modules/Crm/Oportunidades` en verde (115); fakes de
  `ContratosDeOportunidad` con `tieneDatos`. Desvíos: (a) las salas tienen 3 × 2 y 4 × 3 lugares (el prototipo, 4 × 2
  pallets); (b) en la vista general se ocultan los terminales (están las viñetas), como el prototipo. Por revisar a
  ojo: la boca, la brida y la cortina meciéndose y abriéndose al ganar, los terminales legibles sin tapar cajas ni
  rótulos, el vuelo de entrada a cada sala y la vuelta, las cajas en pallets y caídas, la ventana con la tabla (modo
  claro y oscuro), tocar una fila y «reducir animaciones».
- 2026-11-10 — Paso 6: el despiece (B3, D10, D14). `DespieceDeOportunidad` (componente Livewire en `Filament/`, vista
  `faja/despiece`, comportamiento en `faja/despiece-script`) va encima del visor y escucha `tq-faja-abrir`: lo cancela
  (ya no se abre la ficha), pide la caja y la vista nace desde la caja (vuelo desde su lugar en pantalla, `--fx`/`--fy`)
  mientras la caja se separa en capas (de 24 a 62 px, como `layoutSlabs`). Las filas de las tablas (`tq-faja-abrir-fila`)
  pasan por el mismo evento y la ventana se cierra. Capas: `CapasDelDespiece::para(usuario)`, en el orden del prototipo
  (Resumen, Cotizaciones, Presupuesto, Actividades, Competidores, Documentos, Historial de etapas), las demás registradas
  y al final el Historial (bitácora) de la ficha. Las que esperan su módulo, en un solo lugar: `CapasDelDespiece::EN_ESPERA`
  (clave, título y texto de espera; CRM-03 y CRM-05 nombrados donde se sabe); cuando un módulo registra la pestaña con esa
  clave, su capa toma ese lugar; si la persona no tiene su permiso, no ve ninguna de las dos
  (`RegistroPestanasOportunidad::registrada()`, nuevo). Tocar una capa (o Enter/Espacio) la asoma y el panel la muestra:
  Resumen (número, nombre, cuenta, monto, ejecutivo, etapa, probabilidad, cierre estimado y días en la etapa), los
  componentes de la ficha tal cual (`CompetidoresDeOportunidad`, `EtapasDeOportunidad`, `HistorialDeOportunidad` y los
  registrados) o el texto de espera. Debajo, el escáner siguiente: los requisitos de `RequisitosDeEtapa::revisar` con
  cumple/falta y los botones Pasar a «siguiente», Ganar (en Negociación) y Perder, cada uno solo con el permiso de su
  acción (el mismo que revisa `abrirEnFaja`). Los botones no ejecutan: cierran el despiece y emiten `tq-faja-mover`; la faja
  (`moverSinArrastre`) hace lo mismo que al soltar la caja (`pedirDialogo`, sacado de `soltarCaja`): el mismo diálogo, el
  escáner y la acción real, y recarga. En una ganada o perdida, su cierre (fecha, contrato si Contratos tiene datos,
  motivo, ganador y precio) y ningún botón. Datos: `OportunidadesService::filaDeLaFaja(id)` (nuevo, las mismas reglas de
  `tablaDeLaFaja`, cuyo armado de filas pasó a `filasDeLaFaja`); `TablaDeLaFaja::registro()` ahora público para los
  textos del cierre. Teclado y foco: el despiece es `role="dialog"` con `x-trap`; el foco entra al panel al abrir y vuelve
  a la caja al cerrar (o a la faja si la caja no está dibujada); Esc y «Volver · Esc» cierran (no si el Esc es de un
  diálogo de Filament); la faja no toma teclas ni la rueda mientras está abierto. Reducir animaciones: sin vuelo ni
  separación animada. `FajaDespieceTest` (9 pruebas); `tests/Modules/Crm/Oportunidades` en verde. Desvíos: (a) el
  Historial (bitácora) de la ficha también es capa (la ficha lo tiene); (b) la capa elegida se lee del servidor (cada
  pestaña es un componente con sus datos), así que el panel cambia tras la respuesta, no al instante; (c) sin
  conteos en las capas (el prototipo los inventaba): la tapa dice «Tócala para leerla» o «Sin datos todavía»; (d) con la
  caja en una pila «+N más», los botones abren el diálogo y ejecutan sin ver moverse la caja. Por revisar a ojo: el
  vuelo desde la caja y la separación, el asomo al pasar el puntero y al elegir, la escala en pantallas chicas y en el
  celular (panel abajo), el panel en modo claro y oscuro con las tablas de Competidores e Historial, el foco al abrir y
  al cerrar, Esc con un diálogo de Competidores abierto, Pasar/Ganar/Perder desde el panel y «reducir animaciones».
- 2026-11-10 — Paso 7: el hombrecito (B4, D11, D12). `faja/hombrecito` (estilos y `tqHombrecito`, incluido en la
  pantalla) llena el hueco `data-hueco="hombrecito"` del mundo (`aria-hidden`, `wire:ignore`, `pointer-events: none` en todo):
  cuerpo de cubos con caderas, rodillas, hombros, codos, cuello, manos y zapatos, y cara (ojos con pupila, cejas, nariz,
  orejas, boca, mejillas y pelo), como `manHTML` del prototipo; uniforme por zona en un solo lugar (`UNIFORMES`:
  explorador con sombrero de ala y binoculares, inspector con casco y tablilla, mensajero con gorra y bolso, ejecutivo con
  corbata), con destello al cambiarse. Reglas: en `tq-faja-quieta`, si la zona que se mira tiene cajas desordenadas, va
  (camina; corre si es lejos; por el carril de atrás, sin cruzar las láminas) y las empuja a su lugar una por una; si
  está ordenada, no va. Tocar una caja ordenada la desordena (`tq-faja-tocar`); soltarla en su misma zona la deja donde
  cayó, girada, y él viene a ordenarla (`tq-faja-desordenar`; antes volvía sola); una caja que cambia de zona llega
  desordenada; la primera vez en la sesión, todas. Duerme de espaldas al costado de una caja o a la lámina de un escáner
  (sitios libres como `wallSpots`), barbilla caída, un brazo sobre la barriga, el sombrero sobre los ojos, una de tres
  poses de piernas (estiradas, una rodilla arriba, tobillos cruzados) y «zzz». Empuja la caja al escáner (cancela
  `tq-faja-empujar` y la faja espera su promesa). Estado en `sessionStorage` (`tq-faja-hombrecito`, con try/catch):
  desorden de cada caja (`dx`, `dy`, `giro`, `lugar`) y su lugar, uniforme y pose; nada en la base. La faja: `tomarCaja`
  parte del desorden (`data-dx`, `data-dy`, `data-giro` del `data-desplazo`) y `pintar` conserva el giro; `volver`
  endereza la caja y avisa `tq-faja-devuelta`; `recargar` limpia el desorden y avisa `tq-faja-redibujada`; el despiece
  avisa `tq-faja-despiece-cerrado`. Cada secuencia tiene turno y se corta (tomar una caja con el puntero, la pestaña
  oculta, un empuje al escáner); la caja a medio ordenar queda donde iba. Solo transforms, sin leer medidas por cuadro
  (las cajas se leen de su estilo en línea), y nada de Alpine reactivo en cada cuadro. Reducir animaciones: no camina
  ni se anima (todo instantáneo), duerme quieto, «zzz» fijo, sin destello, y la caja va sola al escáner.
  `FajaPantallaTest` (+1: el hueco oculto al lector y sin puntero, los cuatro uniformes, `sessionStorage`, reducir
  animaciones y los ganchos); `FajaPantallaTest`, `FajaAccionesTest`, `FajaDespieceTest`, `FajaSalasTest` y
  `OportunidadesPantallasTest` en verde (58). Desvíos: (a) sin uniformes de fiestas (el pedido no los pide); (b) sin el
  letrero «El explorador va a…» del prototipo (no está en el pedido y el mensaje de la faja es para las acciones); (c)
  tocar una caja la desordena al instante (el despiece la tapa), sin la animación corta del prototipo; (d) una fila de
  tabla o Enter abren el despiece sin desordenar; (e) con la caja en una pila «+N más», no empuja nada. Por revisar a
  ojo: el muñeco de cerca (cara, manos, zapatos, que el sombrero tape los ojos al dormir), cada uniforme y el destello,
  caminar y correr entre zonas, ordenar sin atravesar cosas de forma fea, las tres poses y el «zzz», que no tape ni
  tome clics de cajas, terminales ni viñetas, que agarrar una caja desordenada no salte, el empuje al escáner (verde y
  rojo), recargar la página (sigue el desorden y él dónde estaba), otra pestaña o sesión (todo desordenado), y «reducir
  animaciones».
- 2026-11-10 — Paso 8: reducir animaciones, teclado y rendimiento (B6, B7, D12, D14). **Reducir animaciones:** una sola
  regla para todo lo de la faja (`.tq-faja *` y sus `::before`/`::after`, sin animaciones ni transiciones, con la
  preferencia del sistema y con `tq-sin-animaciones`), en lugar de la lista pieza por pieza, que dejaba fuera la brida y el
  brillo de la canaleta y lo que traen las pestañas del despiece. La cortina ya no pide cuadros con «reducir animaciones»
  (antes el bucle seguía vacío); la preferencia de la persona se mira en cada cuadro porque no avisa al cambiar.
  **Teclado:** Espacio además de Enter en cajas, pilas «+N más» y terminales; las viñetas son botones enfocables
  («Ir a <zona>: N oportunidades», «Entrar a ganadas…», «Bajar a perdidas…»), con foco visible; desde una viñeta, el foco
  pasa a la primera caja de la zona (`irDesdeVineta`), porque de cerca las viñetas se ocultan. Lo que se oculta por la
  distancia (terminales lejos, viñetas cerca) lleva `visibility: hidden`: Tab ya no se detiene en algo invisible. Con
  Tab, si lo enfocado quedó fuera de la vista, la cámara va hasta ahí (`alEnfocar`, solo con `:focus-visible`, para no
  mover la cámara al tocar con el ratón); el visor no se desplaza solo (el navegador movía su scroll para mostrar lo
  enfocado). Al entrar o salir de una sala, si lo enfocado se ocultó, el foco vuelve al visor; al usar Pasar, Ganar o
  Perder en el despiece, el foco queda en la faja (antes se perdía en el cuerpo de la página) y el diálogo de Filament lo
  devuelve ahí. Mover una caja sin arrastrar: Tab hasta la caja, Enter, Tab hasta Pasar/Ganar/Perder (D14). Esc: cierra
  el despiece o sale de la sala (sin cambios). El `aria-label` del visor lo explica. **Rendimiento:** la faja observa si
  se ve (`IntersectionObserver`) y si la pestaña está visible (`visibilitychange`): en pausa, la cortina deja de pedir
  cuadros (tampoco corre en una sala), el visor lleva `en-pausa` (`animation-play-state: paused` en todo) y avisa
  `tq-faja-pausa`; el hombrecito se corta y al volver revisa. La banda corre con `transform` en un `::before` (antes
  `background-position`, que repintaba la banda entera en cada cuadro) y el barrido de los escáneres baja con `transform`
  y un degradado (antes `top`, que recalculaba, y una sombra con desenfoque). Sombras: el brillo desenfocado del grabado
  solo en las cajas completas (hasta 6 por zona; antes en las 66), las demás con la sombra dura; quedan con desenfoque
  los terminales (6), las viñetas (6), y las marcas momentáneas (caja levantada, canaleta, final, foco). No hay
  `filter` ni `backdrop-filter` en la faja. `will-change: transform` solo en la caja levantada. **Adornos del núcleo:**
  la única animación que se repite en todas las páginas son las hojas de otoño de la barra (`barra-estacion`, cada 45 s);
  la faja las oculta con `html:has(.tq-faja-visor) .tq-barra-hojas { visibility: hidden }`, sin tocar el núcleo
  (`visibility` y no `display`, para que su animación termine y el núcleo las quite: con `display: none` se acumularían).
  Los dibujos de la ambientación corren una vez y no se repiten: no se tocan. **Celular (D2):** confirmado: la entrada de
  Oportunidades abre la Tabla (lista) en cualquier pantalla; la faja es la acción «Tablero» y bajo 768 px muestra el aviso
  con el enlace a la Tabla. Sin cambios. **Medición** (`FajaTecladoYMedidaTest`, 3 pruebas): 40 en Propuesta enviada,
  13 en cada otra zona y 15 ganadas y 15 perdidas (109): cada lugar dibuja 11 cajas y su pila (66 cajas, 6 pilas, «+29
  más»), ninguna completa; el documento de la faja (sin estilos ni guiones) tiene 1 268 elementos (428 vacía: ~12 por
  caja), 320 KB de HTML con estilos y guiones (209 KB sin ellos), y se arma en ~240 ms en la prueba (servidor, base de
  pruebas). La prueba fija los topes: 66 cajas, 6 pilas y menos de 2 000 elementos. Desvíos: (a) no hay perfilador de
  navegador aquí: no medí cuadros por segundo ni tiempos de pintado; (b) las sombras de los terminales y las viñetas se
  quedan (son 12 y dan el aspecto); (c) `FajaPantallaTest` y `FajaSalasTest` ahora buscan la regla única de reducir
  animaciones; `tests/Modules/Crm/Oportunidades` en verde (128). Por revisar en el navegador: en DevTools › Performance, grabar 10 s con la faja quieta (vista general y de
  cerca) y arrastrando una caja con 66 cajas: cuadros cerca de 60 por segundo, sin «Layout» ni «Paint» largos en cada
  cuadro (la banda y los escáneres deben salir solo como «Composite»); con «Rendering › Paint flashing», que la banda y
  los escáneres no parpadeen en verde; desplazar la página hasta que la faja no se vea y cambiar de pestaña: en
  Performance, sin `requestAnimationFrame` de la cortina; «Rendering › Emulate prefers-reduced-motion: reduce»: todo
  quieto. A ojo: la banda y el barrido se ven como antes, el grabado de las cajas con código se lee sin el brillo, Tab
  recorre cajas, pilas, terminales y viñetas con foco visible y la cámara las sigue, Espacio y Enter, el foco tras salir
  de una sala y tras Pasar/Ganar/Perder, y en otoño que no caigan hojas en la barra con la faja abierta.
- 2026-11-10 — Paso 9: revisión del subagente `revisor-arquitectura` (sin bloqueos), atendida: (1) una anulada abría
  el despiece: `filaDeLaFaja` y el nuevo `versionEnLaFaja` excluyen las anuladas (`PortadaOportunidades::sinAnuladas`,
  ahora pública), y `abrir` da 404 si no está en la faja; `resumen()` no cambia (la ficha y otros la usan). (2) El
  aviso de permiso de la faja y del despiece decía revisar «lo mismo que EjecutorAcciones» y no miraba «propias» (lo
  revisa el servicio al ejecutar, `bloquearAbiertaPropia`, y `SobreLoPropio` en la transición):
  `TableroOportunidades::rechazoAlMover` revisa el permiso de la acción como el ejecutor (respetando
  `permisoPorAmbito`) y `OportunidadesService::rechazoAlMoverla` la regla D10 con su mismo mensaje (un solo
  `puedeEditarla` privado para los dos). Con «editar las propias», la caja ajena vuelve con el mensaje y su despiece no
  ofrece botones. (3) Las lecturas de la página pasan al servicio (`versionEnLaFaja`, `resumen` para el mensaje de
  `moverEnFaja`). (4) Contrato con la barra del núcleo: comentario en los dos lados y una prueba que exige
  `<span class="tq-barra-hojas"` en `barra-estacion`. (5) La ventana de un lugar usa `totalDeLugarDeLaFaja` (el mismo
  cálculo; `totalesDeLaFaja` lo recorre). (6) `tieneDatos()` anotado en «Preparado para lo diferido». (7) El `float`
  de `FajaDeVidrio` (alto en píxeles) comentado: solo dibujo. `interfaz.md`: la faja como excepción a los patrones de
  pantalla (D1, D13). Pruebas: `FajaAccionesTest` (+1, «propias»), `FajaDespieceTest` (+1, anulada; la de botones ahora
  da `editar`, porque sin él la acción tampoco la movía), `FajaTecladoYMedidaTest` (+1, contrato). `composer
  check:tarea` (2026-11-11): en verde, 264 pruebas de 14 archivos más `tests/Arch` y `tests/Feature`. La primera vez falló
  antes de correr: `scripts/pruebas-de-la-tarea.php` pasaba varios archivos a paratest, que acepta una sola ruta («Too
  many arguments»); ahora el paralelo es solo cuando corren todas. Desvío: el revisor ubicaba `SobreLoPropio` en EjecutorAcciones; las acciones de la
  faja no van por ámbito (el ejecutor exige su permiso global) y la regla de «propias» la aplica el servicio al
  ejecutarlas.

## Anexo: el pedido del desarrollador (2026-11-09), completo

Copiado del pedido para que cada paso lo tenga a mano. Las decisiones de arriba lo precisan donde hizo falta (D5:
próxima acción hasta CRM-05; D7: código real de la serie `OP`; D13: mundo oscuro siempre).

**B1. El mundo y la cámara**
- Faja horizontal 3D, hecha con CSS 3D, Alpine y Livewire. Sin Three.js.
- Cuatro zonas: Prospecto, Calificada, Propuesta enviada y Negociación, cada una con su luz y su rótulo en el piso
  (nombre, probabilidad, monto). Entre zonas, un escáner: lámina color café con barrido de luz.
- Al final, la boca de una tubería de acero con brida y una cortina de tiras de PVC con física (se mece sola y se abre
  al pasar una caja). Por ahí salen las ganadas.
- Delante de la faja, una canaleta por la que caen las perdidas.
- Por defecto se abre en vista general en perspectiva, con una viñeta flotante por zona que muestra solo el número de
  cajas. Las viñetas no tapan los vidrios, mantienen su tamaño en pantalla y desaparecen al acercarse. Las de ganadas y
  perdidas también muestran solo el número.
- Navegación: rueda hacia abajo aleja, hacia arriba acerca; Ctrl + rueda, A/D y flechas ←/→ saltan a la zona anterior
  o siguiente sin cambiar el zoom; arrastrar el fondo desplaza la vista; sin rotación libre y sin flechas
  arriba/abajo; botón «Vista general», minibarra inferior con las 4 zonas y una leve inclinación al mover el mouse.
- Cada oportunidad es una caja de vidrio cuya altura depende del monto. El nombre, el monto y la entidad van grabados
  en la cara visible, sin texto espejado por detrás. Atascada (días > máximo de la etapa): grieta ámbar. Sin próxima
  acción o vencida: punto ámbar que late.

**B2. Mover cajas, siempre arrastrando**
- La caja se levanta, sigue al cursor proyectado sobre el piso y la cámara avanza sola cerca de los bordes.
- Soltar en la zona siguiente: el escáner se ilumina al apuntar. Luego el hombrecito empuja la caja hasta el escáner.
  En ese momento se ejecuta la acción real. Si falla, el escáner vibra en rojo, se muestra el mensaje de validación de
  la acción y la caja vuelve a su zona. Si pasa: verde y vibración corta, la caja cruza y queda en la zona nueva; el
  escáner vuelve al café. Si la etapa exige próxima acción, antes de animar se pide en un diálogo pequeño (sus mismos
  campos).
- De Negociación, soltar en la tubería: GanarOportunidad, la caja entra por la cortina y desaparece.
- Soltar en la canaleta: diálogo con lo que exige PerderOportunidad (motivo del catálogo, ganador, precio). La caja se
  desliza al borde, se inclina y cae.
- Saltar etapas o retroceder: no se permite. Se explica en una línea y la caja queda en su zona.
- La ficha (tocar la caja) también ofrece pasar, ganar y perder con las mismas acciones.
- Concurrencia: usa la versión de bloqueo optimista capturada al tomar la caja. Si otro la movió, se avisa y se
  recarga la zona.

**B3. Abrir una caja: despiece**
- Al tocarla, la vista nace desde la caja y la caja se separa en capas 3D. Cada capa es una pestaña de la ficha que ya
  existe (Resumen, Cotizaciones, Presupuesto, Actividades, Competidores, Documentos, Historial de etapas, según lo que
  la 2.21 dejó o registró como extensión).
- Al tocar una capa, se asoma y su contenido sale plano y legible en un panel a la derecha, donde también están los
  requisitos del siguiente escáner. Esc vuelve a la faja.
- Las pestañas que todavía no tienen datos (porque sus módulos no existen) se muestran vacías con su texto de espera,
  no inventadas.

**B4. El hombrecito (uno solo, decorativo)**
- Muñeco pequeño de cubos con cuerpo humano (rodillas, codos, cuello, manos, zapatos) y cara simple (ojos con pupila,
  cejas, nariz, orejas, boca, pelo). Usa un uniforme por zona: explorador en Prospecto, inspector en Calificada,
  mensajero en Propuesta y ejecutivo en Negociación. Se cambia con un destello al llegar.
- Reglas: si estás en una zona con cajas desordenadas, va enseguida (camina o corre) y las ordena rápido; tocar una
  caja ordenada la desordena; si entras a una zona ordenada, no va: se queda donde estaba; la primera vez que se entra
  a Oportunidades en la sesión, todo aparece desordenado.
- Duerme echado de forma natural, con la cabeza apoyada en una superficie vertical (costado de una caja o lámina de un
  escáner), la barbilla caída, un brazo sobre la barriga y la gorra o casco sobre los ojos. La pierna varía cada vez
  (estiradas, una rodilla arriba, tobillos cruzados). Lleva «zzz» sobre la cabeza.
- Es solo pantalla: el «desorden» y su posición viven en el navegador (sessionStorage/localStorage con try/catch),
  nunca en la base. El empuje al escáner es la animación de la acción real.

**B5. Lugares y ventanas para ver el contenido (obligatorio)**
- Ganadas: junto a la tubería, un terminal con «Ver ganadas · N · S/ total», y además un botón en la barra superior.
  Abre la sala de ganadas: la cámara entra por la tubería y se ven las cajas sobre pallets. En la sala hay un botón
  «Ver en tabla».
- Perdidas: letrero rojo sobre la canaleta «↓ Ver perdidas · N · S/ total», y además un botón en la barra superior.
  La cámara baja a un sótano con las cajas caídas, también con «Ver en tabla».
- Cada una de las 4 zonas tiene su propio terminal en el piso, «Ver las N de <zona>». Abre una ventana emergente
  (modal) con una tabla de las oportunidades de esa zona. Columnas: código, oportunidad, cuenta o entidad, monto,
  ejecutivo, días en la etapa (ámbar si atascada), próxima acción (ámbar si vencida o sin programar) y cierre
  estimado. Con búsqueda y orden. Tocar una fila abre el despiece de esa caja.
- Las tablas de ganadas y perdidas cambian las columnas finales: contrato en las ganadas; motivo, ganador y precio en
  las perdidas.
- Las tablas leen de un método de servicio de Oportunidades (consulta por etapa o resultado, paginada), probado con
  Pest.

**B6. Límite de cajas por zona (cuando haya muchas)**
- Hasta 6 cajas en una zona: cada caja muestra nombre, monto y entidad.
- De 7 a 12: todas las cajas de esa zona muestran solo el código de la oportunidad, en una caja un poco más chica y
  más juntas.
- Más de 12: se dibujan 11 cajas (las de mayor monto) solo con código, más una pila «+N más» que abre el modal de esa
  zona.
- Las mismas reglas valen en las salas de ganadas y perdidas, contando desde las más recientes.
- Los umbrales (6 y 12) como constantes en un solo lugar, con prueba de la función que decide cuánto mostrar.

**B7. Accesibilidad, celular y rendimiento**
- Cada caja es enfocable y con Enter se abre. Desde la ficha se puede pasar, ganar o perder sin arrastrar.
- Con «reducir animaciones» (preferencia o sistema): sin hombrecito animado ni vuelos de cámara; los cambios son
  instantáneos.
- En el celular, la vista por defecto es la Tabla y la faja queda como opción.
- Que no se trabe con decenas de oportunidades (el límite de B6 ayuda); mide con datos de prueba.

**Pruebas mínimas:** la función del límite; las consultas de cada modal; que soltar en la zona siguiente invoque la
acción correcta y que su fallo devuelva la caja con el mensaje; ganar y perder desde la faja; el escenario de
aceptación de oportunidades del v4 (parte 8) sigue pasando.
