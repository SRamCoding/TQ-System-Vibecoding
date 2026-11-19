# Plan: pantallas del área 1 (tarea 2.10)

- **Estado:** completado
- **Responsable:** Sebastián (área 1, Presupuesto y costo; ADR 0020)
- **Entrega:** `entrega/presupuesto-pantallas` (solo la 2.10; la 2.9b va en una entrega aparte, confirmado por el
  desarrollador el 2026-11-11)
- **Módulo(s):** `Erp/Presupuestos`, `Erp/Proyectos`
- **Fase:** F2
- **Creado:** 2026-11-11 · **Actualizado:** 2026-11-12

## Objetivo

Ver y probar en la app lo que ya construyeron la 2.1 (proyectos y árbol de partidas) y la 2.9 (presupuesto con APU y
catálogo de insumos), con los patrones de `docs/convenciones/interfaz.md`: la lista y la ficha de proyectos, el
árbol de partidas de un proyecto, el presupuesto con sus títulos, partidas, APU, lista de insumos y totales, y el
catálogo de insumos. Toda escritura pasa por las acciones registradas que ya existen (`AccionFilament`); la pantalla
no calcula nada: lo pide a los servicios.

## Contexto que leí

- `docs/planes/completados/2026-11-06-presupuesto-apu.md` (D1 a D13 y «Preparado para lo diferido»): la 2.10 muestra
  el aviso de materiales sin producto (D13, `ConsultaPresupuestos::materialesSinProducto()`) en la confirmación de
  «Ofertar» y de «Traer al proyecto»; el monto sin detallar (D10) y el ajuste por redondeo con su detalle (D5).
- `docs/planes/completados/2026-11-04-costura-c1.md`, D4 y D5: proyecto (en preparación, en ejecución, cerrado,
  anulado; crear interno y anular con motivo) y árbol (borrador → aprobado; crear, agregar partida, fijar
  presupuestado, desactivar, aprobar; condiciones de aprobar). Ahí decía «sin pantallas: las hace el área 4»; el mapa
  las pasó a esta tarea.
- `docs/convenciones/interfaz.md`: «Patrones de pantalla» (portada con pestañas de texto, avisos con «Ver cuáles» y
  últimos cambios para una lista; panel deslizante desde la derecha sin recargar, con la URL siguiéndolo; íconos
  únicos con `IconosUnicosTest`; ruta completa), «Reglas» (color de estado siempre con su nombre), «Mayúsculas»
  (`EtiquetasEnEspanol`).
- Patrón ya construido que se copia: Cuentas y Oportunidades (`ListarCuentas` con portada, `VerCuenta` con pestañas de
  un registro ampliable `RegistroPestanasCuenta`, `PanelesOportunidad` con los paneles de las acciones,
  `CajaModulo` en el proveedor del módulo).
- v4: parte 6, ficha «Árbol de partidas de control» (columnas código, partida, responsable, presupuestado, origen;
  «Proponer desde presupuesto» es de la 2.9b); parte 4, flujo 1, pasos 3 y 4 (cómo se ve el presupuesto y el árbol).
- `pendientes.md`: «Presupuesto» y «Árbol de partidas» no bloquean (configurable y árbol libre).

## Decisiones

Tomadas con el desarrollador, una por una (2026-11-11).

- **D1 (desarrollador, 2026-11-11). Dónde va cada pantalla.** Dos cajas del área ERP en el inicio: **Proyectos** (`Erp/Proyectos`)
  y **Presupuestos** (`Erp/Presupuestos`). El presupuesto existe antes que el proyecto (ofertado sin proyecto), así
  que no puede vivir solo dentro de la ficha del proyecto.
- **D2 (desarrollador, 2026-11-11). El catálogo de insumos, dentro de Presupuestos.** Un botón «Insumos» en el
  encabezado de la lista de Presupuestos abre su tabla en el panel deslizante (agregar, editar, desactivar). No toca
  el núcleo ni la lista fija de Configuración > Catálogos.
- **D3 (desarrollador, 2026-11-11). La ficha del proyecto con pestañas ampliables.** `Erp/Proyectos` no toca los modelos de
  `Erp/Presupuestos` (regla 2): la ficha arma sus pestañas con un registro, como la de Cuentas
  (`RegistroPestanasProyecto`); Presupuestos registra las suyas: «Presupuesto» (la versión vigente y las anteriores)
  y «Partidas de control» (el árbol). Proyectos solo pone «Datos» e «Historial».
- **D4 (desarrollador, 2026-11-11). Cómo se arma el presupuesto en pantalla.** Ficha del presupuesto con encabezado (número,
  versión, estado, totales y lo sin detallar) y pestañas: «Partidas» (títulos y partidas en una tabla con sangría por
  nivel; al presionar una partida, su APU se abre en el panel deslizante), «Lista de insumos» (con «Precio sin
  detallar» aparte y la línea «Ajuste por redondeo» con su detalle) e «Historial».
- **D5 (desarrollador, 2026-11-11). El costo presupuestado del árbol, sugerido y editable.** Al crear el árbol, si
  el proyecto tiene presupuesto aprobado vigente, el campo viene lleno con costo directo + gastos generales (flujo 1:
  17,750.00 + 1,775.00 = 19,525.00) y se puede cambiar; si no tiene, se escribe. La sugerencia la calcula un
  servicio de `Erp/Presupuestos` (no la pantalla). La 2.9b lo amarra al presupuesto al proponer el árbol.

## Pasos

- [x] 1. Plan aprobado (decisiones D1 a D5).
- [x] 2. Proyectos: caja, lista con portada (pestañas por estado con su cantidad, avisos, últimos cambios), panel
  «Nuevo proyecto interno», ficha con «Datos» e «Historial», acciones anular y cerrar, y el registro de pestañas (D3).
- [x] 3. Árbol de partidas: pestaña de la ficha del proyecto; crear el árbol, agregar partida, fijar presupuestado,
  desactivar y aprobar, con la suma de las hojas contra el costo presupuestado a la vista.
- [x] 4. Catálogo de insumos (D2): crear, editar (con el enlace al producto y su unidad), desactivar; el insumo del
  sistema se ve pero no se edita.
- [x] 5. Presupuestos: caja, lista con portada (pestañas por estado), panel «Nuevo presupuesto», ficha (D4) con
  encabezado y totales; títulos y partidas (agregar, editar, quitar).
- [x] 6. El APU de una partida en el panel deslizante: agregar, editar y quitar líneas, rendimiento de la partida y
  propio de la línea (D12 de la 2.9), precio sugerido inicial.
- [x] 7. Lista de insumos y estados: Ofertar y Traer al proyecto con el aviso de materiales sin producto (D13 de la
  2.9) y lo sin detallar; Nueva versión; Anular con motivo; cambiar `usa_apu` con su confirmación.
- [x] 8. Pestaña «Presupuesto» en la ficha del proyecto.
- [x] 9. Cierre: revisor de arquitectura, `composer check` en segundo plano, documentos (`interfaz.md` si nace un
  patrón, mapa).

Cada paso: sus pruebas (Livewire sobre la página y sobre cada componente hijo, porque `Livewire::test()` no dibuja las
pestañas), pint, phpstan, commit y push.

## Preparado para lo diferido

- **Proponer el árbol (2.9b):** la pestaña «Partidas de control» deja el lugar del botón «Proponer desde el
  presupuesto» y la columna «Origen» (vacía hasta la 2.9b).
- **Pestañas de otras áreas en la ficha del proyecto:** el registro (D3) recibe las de Contratos, Compras u Obra sin
  tocar la ficha.
- **Cambiar el jefe de proyecto:** la portada ya avisa los proyectos abiertos con jefe inactivo
  (`PortadaProyectos::enAviso('jefe-inactivo')`); cuando el área 4 haga la acción de cambiarlo, el aviso suma su botón.
- **Cotización (C8):** la ficha del presupuesto no depende de la cotización; cuando Comercial dispare «Ofertar» por C8,
  la pantalla sigue igual.

## Criterio de aceptación

- El escenario del flujo 1 armado desde las pantallas (pruebas Livewire): PRE-2026-031 con costo directo 17,750.00,
  gastos generales 1,775.00, utilidad 1,420.00, IGV 3,770.10 y total 24,715.10 a la vista, y la lista de insumos que
  suma 17,750.00.
- Ofertar con un material sin producto muestra el aviso y deja ofertar.
- El árbol no se aprueba si la suma de las hojas no cuadra, y la pantalla dice cuánto falta.
- `IconosUnicosTest`, `EtiquetasEnEspanolTest` y `CatalogosSeederTest` en verde.

## Bitácora

- 2026-11-11 — Rama `entrega/presupuesto-pantallas` creada desde `origin/main` (0b572ce). Plan escrito; D1 a D5
  confirmadas por el desarrollador.
- 2026-11-11 — Paso 2 hecho. Caja y lista de Proyectos con portada (Todos, Míos, uno por estado; aviso «jefe
  inactivo»; últimos cambios), panel «Nuevo proyecto interno», ficha con Datos, pestañas registradas
  (`RegistroPestanasProyecto`) e Historial; Cerrar y Anular en «Más». Permiso nuevo `erp.proyectos.ver`.
  Descubierto: `Proyecto` no llevaba bitácora (`RegistraCambios`), así que el historial salía vacío; se le agregó con
  sus etiquetas. El aviso de jefe inactivo no ofrece cambiarlo: no existe la acción (es del área 4); queda anotado en
  «Preparado para lo diferido».
- 2026-11-11 — Paso 3 hecho. Pestaña «Partidas de control» (`PartidasDeControlDeProyecto`, registrada desde
  Presupuestos con el permiso nuevo `erp.presupuestos.ver`): tabla en orden de código con sangría por nivel,
  responsable y presupuestado (los padres suman sus hojas activas), y arriba «costo · repartido · faltan/cuadra».
  Crear el árbol con el costo sugerido (D5, `ConsultaArbol::costoSugerido`), agregar partida (raíz o hija, con el
  código siguiente ya escrito), fijar monto, desactivar y aprobar, todo por sus acciones. Los montos solo con
  `campos.ver-costos` (`Filament/Montos`, sin `float`). `Erp\Proyectos` expone `Pestanas/` (ArquitecturaTest y
  ARCHITECTURE.md, regla 2, con su motivo). La columna «Origen» queda para la 2.9b.
- 2026-11-11 — Paso 4 hecho. Caja «Presupuestos» (`heroicon-o-calculator`) con su lista base (número y versión,
  nombre, estado con «vigente», total); la portada y la ficha son del paso 5. «Insumos», arriba, abre
  `InsumosDelCatalogo` en el panel deslizante encima de la lista atenuada, con las piezas de Catálogos
  (`cajas-con-panel`); `?panel=insumos` lo abre directo. Tabla con filtros (tipo, activos, materiales sin producto),
  crear, editar (código y tipo solo lectura; el producto de C4 solo para materiales, por `CatalogoService::buscar`)
  y desactivar; el insumo del sistema no ofrece acciones. Precio de referencia solo con `campos.ver-costos`.
- 2026-11-11 — Paso 5 hecho. Portada de Presupuestos (Todos, Borradores, Ofertados, Vigentes, Reemplazados,
  Anulados; aviso «en curso con materiales sin producto», D13 de la 2.9; últimos cambios), «Nuevo presupuesto» en el
  panel deslizante (soles, IGV 18 % y con APU ya escritos; GG y utilidad los escribe quien presupuesta), ficha con
  totales arriba (y «Sin detallar», D10), pestañas «Partidas» (`PartidasDePresupuesto`: títulos con su suma, partidas
  con unidad, metrado, P.U. con los decimales del presupuesto y parcial; agregar título, subtítulo y partida con el
  código siguiente, editar y quitar; solo en borrador; con APU no pide P.U. sino rendimiento) e «Historial».
  Descubierto: los modelos del presupuesto llevaban bitácora pero no `DescribibleEnBitacora`; se agregó a
  Presupuesto, título y partida (la línea del APU, en el paso 6). Servicios nuevos: `ConsultaPresupuestos::filas` y
  `codigoSiguiente`, `PortadaPresupuestos`.
- 2026-11-11 — Paso 6 hecho. Presionar una partida (o «Ver APU») abre `ApuDePartida` en un panel que se desliza sobre
  la pestaña «Partidas», atenuada detrás; `?apu=` lo abre directo y solo con una partida del mismo presupuesto (no se
  usó `?tableAction=` de Filament: lo comparten todas las tablas de la ficha, también el historial). Cada línea con
  lo escrito («Neta 1.00000 + 5.00 % de desperdicio»; «Cuadrilla 1 · rendimiento de la partida (20 MTR por hora)» o
  «rendimiento propio …»), cantidad efectiva, precio y parcial; arriba metrado, P.U. y rendimiento de la partida.
  Agregar (los campos según el tipo; precio vacío = el sugerido o el de referencia, que se muestra), editar (el insumo
  no cambia; rendimiento vacío = el de la partida) y quitar, solo en borrador. La línea del APU entra al historial.
- 2026-11-11 — Paso 7 hecho. Pestaña «Lista de insumos» (`ListaDeInsumosDePresupuesto`, solo con APU): un título por
  tipo como en S10 (mano de obra, materiales, equipos, subcontratos) con su suma, cada insumo con cantidad y monto, y
  aparte «Precio sin detallar» y «Ajuste por redondeo» con sus partidas; cierra con el costo directo (flujo 1:
  17,750.00). En la ficha: «Ofertar» y «Traer al proyecto» (proyecto por `ProyectosService::activos`, C2; en la versión
  2 en adelante viene puesto y no se cambia) dicen en su confirmación qué pasa, cuánto va sin detallar y los
  materiales sin producto (D13 de la 2.9: avisa, deja seguir); lo incompleto lo rechaza la condición y la pantalla lo
  dice. «Nueva versión» desde el aprobado abre la versión creada. En «Más»: pasar con o sin APU (sin APU pide
  confirmar que se descartan los análisis) y «Anular» con motivo. Sin permisos nuevos. Decidido con el desarrollador:
  la 2.9b va en una entrega aparte (esta llega a ~55 archivos).
- 2026-11-11 — Paso 8 hecho. Pestaña «Presupuesto» de la ficha del proyecto (`PresupuestoDeProyecto`, registrada
  desde Presupuestos con `erp.presupuestos.ver`, orden 10: va antes de «Partidas de control»; ícono
  `heroicon-o-document-currency-dollar`). Arriba el vigente con costo directo y total (flujo 1: 17,750.00 y
  24,715.10); debajo todas las versiones del proyecto, la más nueva primero, con su estado y enlace a su ficha. Solo
  lectura: sin presupuesto, lo dice y lleva a Presupuestos, donde se usa «Traer al proyecto».
- 2026-11-12 — Paso 9. Regla nueva de `main` (desarrollador, 2026-11-12): la entrega cierra con `composer check:tarea`
  en verde; el `composer check` completo va una vez por etapa. Primer `check:tarea` (desde `origin/main`, sin corrida
  verde previa en esta copia): 747 pruebas en verde, pint y phpstan. Revisor de arquitectura: Pestanas/, C2, bitácora
  y acciones sin hallazgos; dos bloqueantes del ADR 0016, arreglados:
  - **B1:** la utilidad (grupo «margen») se mostraba con el permiso de costos. `Montos::visibles()`/`siVisible()`
    reciben el grupo; la utilidad y su porcentaje van con `campos.ver-margen`, y «Editar encabezado» (trae
    `utilidad_pct`) no se ofrece sin él.
  - **B2:** cuatro formularios se rellenaban con montos de costos aunque la persona no los viera (lo oculto no se envía
    al navegador): editar línea de APU, editar partida sin APU y editar insumo ya no se ofrecen sin
    `campos.ver-costos`; «Crear el árbol» llega con el costo vacío. Una prueba por caso con quien edita sin ver.
  - Observación atendida: «Nuevo presupuesto» toma el IGV de `tributario.igv_porcentaje`, no un 18 escrito.
  - Quedan anotadas (no bloquean): la columna Proyecto de la lista y el historial del APU hacen una consulta por fila
    (conviene una lectura en lote en `ProyectosService`); `cuadre()` de la pestaña de partidas resta en la pantalla
    (lo podría dar `ConsultaArbol`); `ComponentePestanaProyecto` usa `auth()` y no `Filament::auth()`; y la 2.10 tocó
    `Erp/Proyectos` (área 4): cuando vuelva el equipo, lo revisa su dueño. Además, con IGV, total, costo directo y
    gastos generales a la vista, la utilidad se deduce por resta: si eso importa, `igv` y `total` deberían declararse
    sensibles en el modelo (decisión del desarrollador).
