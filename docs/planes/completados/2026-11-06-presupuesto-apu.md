# Plan: presupuesto con APU (tarea 2.9)

- **Estado:** completado (rama subida; el PR lo abre Sebastián)
- **Responsable:** Sebastián (área 1, Presupuesto y costo; ADR 0020)
- **Entrega:** `entrega/presupuesto-apu` (la 2.9 sola: llegó al tope de ~60 archivos; la 2.10 y la 2.9b van en la siguiente)
- **Módulo(s):** `Erp/Presupuestos`
- **Fase:** F2
- **Creado:** 2026-11-06 · **Actualizado:** 2026-11-10

## Objetivo

El presupuesto con análisis de precios unitarios del flujo 1, paso 3: títulos, partidas con metrado, APU por partida
con insumos de un catálogo, la lista de insumos que suma el costo directo, los totales (PRE-2026-031: costo directo
17,750.00, total 24,715.10), los estados (borrador, ofertado, aprobado, reemplazado, anulado) con sus versiones, y la
costura C8 para la cotización. Sin pantallas (van en la 2.10) ni proponer el árbol (2.9b).

## Contexto que leí

- `docs/diseno/v4/parte-4-flujo-01.md`, paso 3 (líneas 56–91): el presupuesto PRE-2026-031 con títulos, partidas,
  metrados y el APU de cada partida (cable 1.05 m con 5 % de desperdicio; técnico 0.05 h = 20 m por hora). Costo
  directo 17,750.00; gastos generales 10 % 1,775.00; utilidad 8 % 1,420.00; IGV 18 % 3,770.10; total 24,715.10. «La
  lista de insumos debe sumar exactamente el costo directo». Al ganarse, el mismo presupuesto pasa al proyecto y queda
  congelado como versión aprobada (no se copia). Paso 4: «proponer desde presupuesto» el árbol (opcional). Excepciones
  (líneas 144–156): nueva versión por adenda; contrato chico sin APU (camino corto desde la cotización).
- DBML v2: `presupuestos` (líneas 2964–2992: `numero` + `version` únicos, `oportunidad_id`, `proyecto_id`, `usa_apu`,
  porcentajes y montos, estado), `presupuesto_titulos`, `presupuesto_partidas`, `insumos` (tipo material | mano_obra |
  equipo | subcontrato; `producto_id` a `productos`), `apu_insumos` (cantidad o rendimiento, desperdicio, precio,
  parcial), `formulas_polinomicas` y `formula_monomios` (la fórmula es el punto 4 de la D8, otra tarea).
- ADR 0020: C8 (`PresupuestosService::resumen(presupuestoId)` para la cotización, la usa Comercial); C4 ya existe
  (2.60: `CatalogoService`, `productos`); **C5 no existe** (`CostoHoraService::vigente(categoriaId, fecha)`, tabla
  `categorias_costo_hora`, dueña el área 4).
- `pendientes.md`, fila «Presupuesto»: cuándo APU completo y cuándo precio por ítem, y el umbral del camino corto
  (Ronal y Lisset). **No bloquea:** configurable (`usa_apu`).
- `oportunidades` vive en la rama `crm-oportunidades`, sin fusionar: `presupuestos.oportunidad_id` no puede llevar
  llave foránea todavía.

## Decisiones

Tomadas con el desarrollador, una por una (2026-11-06).

1. **C5 no existe: precio escrito y punto de extensión.** El precio de la mano de obra se escribe en el APU y se guarda
   como foto, igual que el de los materiales. Queda la interfaz «precio sugerido» de la mano de obra, que recibe lo
   mismo que pide C5 en el ADR 0020 (categoría y fecha) para que C5 se enchufe sin adaptadores; hoy devuelve «sin
   sugerencia». En la C5 del ADR 0020 se anota que el APU la usa como precio sugerido, no obligatorio: quien
   presupuesta puede cambiarlo.
2. **Alcance: partido en dos.** Esta tarea (2.9): presupuesto, títulos, partidas, insumos, APU, totales, versiones y
   congelado, y C8. La 2.9b: proponer el árbol desde el presupuesto (`partida_arboles.presupuesto_id`,
   `origen_titulo_id`, `origen_partida_id`).
3. **Pantallas: no en esta tarea, pero con fecha.** Aquí solo servicios, acciones y pruebas con los números del flujo
   1. La tarea siguiente del área (la 2.10, antes de la 2.9b) hace las pantallas que faltan del área, siguiendo
   `docs/convenciones/interfaz.md`: el presupuesto, la lista y la ficha de proyectos y el árbol de partidas, para ver y
   probar en la app lo ya construido.
4. **Cantidad efectiva del APU, calculada.** Material (y subcontrato): se escriben la cantidad neta y el % de
   desperdicio; efectiva = neta × (1 + desperdicio) (1.00 + 5 % → 1.05). Mano de obra y equipo: se escriben el
   rendimiento, su unidad (por hora o por jornada) y la cuadrilla (cuántas personas o equipos de ese insumo; 1 por
   defecto); la jornada es un parámetro configurable (8 horas por defecto); efectiva = cuadrilla × horas de la unidad ÷
   rendimiento (20 m/h → 0.05 h; 160 m/día con cuadrilla 1 → 0.05 h; con cuadrilla 2 → 0.10 h). Se guardan lo escrito
   (rendimiento, unidad, cuadrilla, desperdicio) y la efectiva redondeada a 5 decimales, que es la que vale para todos
   los totales. Pruebas: los tres ejemplos de mano de obra, el desperdicio y 15 m/h → 0.06667. Si el rendimiento va
   por partida o por insumo en S10, y si usan cuadrilla: a `pendientes.md` (hoy, por insumo).
5. **Redondeo: el parcial como en S10 y el ajuste a la vista.** En obra pública la oferta muestra metrado × P.U. =
   parcial, y la entidad revisa esa aritmética. Parcial de cada insumo del APU = efectiva × precio, a 4 decimales;
   P.U. de la partida = su suma redondeada a 2 decimales (parámetro, 2 por defecto, como S10); parcial de la partida =
   metrado × P.U., redondeado a 2; costo directo = suma de los parciales. La lista de insumos sale de los APU: en cada
   partida, cada insumo aporta metrado × su parcial del APU redondeado a 2, y la lista suma esos aportes por insumo. Si
   no coincide al céntimo con el costo directo, la diferencia va en una línea explícita «Ajuste por redondeo» (nunca
   dentro de un insumo), con el detalle de qué partidas la generan y cuánto aporta cada una (parcial de la partida
   menos la suma de sus aportes; p. ej. «Cableado, 12.345 m, S/ 0.01»). No pide aceptación: las dos cuentas salen de
   los mismos datos, así que solo puede ser redondeo, y la aprobación nunca se bloquea por céntimos. Pruebas: el flujo
   1 sin ajuste; uno con metrado 12.345 m con el ajuste y su detalle por partida. A `pendientes.md`: decimales del
   P.U. en las ofertas y si la entidad revisa metrado × P.U.
6. **Estados: borrador, ofertado, aprobado, reemplazado (y anulado).** Una oferta enviada no se cambia, y el
   presupuesto tampoco: si siguiera en borrador entre la oferta y el triunfo, se aprobaría una versión distinta de la
   ofertada, con el margen mal desde el inicio. Borrador: editable. Ofertado: congelado, sin proyecto; se llega con
   «Ofertar», una acción registrada con su permiso que hoy se usa a mano (cuando exista la cotización, Comercial la
   dispara por C8). Aprobado: «Traer al proyecto» vincula y aprueba a la vez, desde ofertado o directo desde borrador
   (proyectos internos y contrataciones sin licitación); exige proyecto que acepte costos y presupuesto completo.
   Reemplazado: lo pone el sistema al aprobarse la versión siguiente. Anular, solo desde borrador y con motivo. Si se
   pierde la oportunidad, queda ofertado como historia. «Nueva versión» copia la aprobada a versión + 1 en borrador,
   con el mismo número; la anterior sigue vigente hasta que se aprueba la nueva. Un proyecto tiene una sola versión
   aprobada vigente, garantizado en la base. Pruebas: los dos caminos, que un ofertado no se edita, que el anterior
   pasa a reemplazado y que no puede haber dos aprobadas vigentes en el mismo proyecto.
7. **C8 entrega costo y totales, sin prorrateo.** Encabezado: número, versión, estado, moneda, porcentajes y montos
   (costo directo, gastos generales, utilidad, IGV, total). Partidas: código, título, descripción, unidad, metrado,
   P.U. y parcial a costo directo, como se presenta en obra pública (gastos generales y utilidad al pie, no dentro de
   cada partida). Comercial decide cómo mostrarlo.
8. **Catálogo de insumos propio, sin duplicar el de productos.** Catálogo global en `Erp/Presupuestos` con acciones
   crear, editar y desactivar. Un material puede enlazarse a un producto de C4 (llave foránea a `productos`), y
   `producto_id` es único entre los insumos (v4, parte 2: «un solo catálogo»): así la comparación entre lo
   presupuestado y lo comprado no se parte entre dos insumos del mismo producto. Al enlazarlo, la unidad del insumo
   debe ser la del producto (lo valida el servicio con `CatalogoService::resumen()`, que da `unidadMedidaId`). La mano
   de obra lleva `categoria_costo_hora_id` opcional y sin llave hasta que exista C5. Al poner un insumo en un APU, el
   precio inicial es la sugerencia de C5 (hoy ninguna) o el precio de referencia; se puede cambiar y queda como foto
   en la línea. A `pendientes.md`: si todo material presupuestado debe existir como producto.
9. **`usa_apu` por presupuesto y qué es «completo».** Completo (para Ofertar y Traer al proyecto): al menos una
   partida; toda partida con metrado > 0 y P.U. > 0, colgada de un título; los códigos de hijo empiezan con el del
   padre (como en el árbol). Con `usa_apu` = sí, toda partida tiene APU y su P.U. sale de él (no se escribe), y hay
   lista de insumos que suma el costo directo. Una partida sin detalle se resuelve como en S10: un APU de un solo
   insumo; p. ej. «02.03 Obras civiles por subcontrato, 1 glb»: APU con un insumo tipo subcontrato, cantidad 1, precio
   8,500.00. Así la lista cubre todas las partidas, sin caso mixto. Con `usa_apu` = no, el P.U. se escribe en la
   partida y no hay APU ni lista. Cambiarlo, solo en borrador: de no a sí, cada partida recibe un APU con un solo
   insumo genérico por su P.U. escrito (no se pierde el precio; quien presupuesta lo detalla después); de sí a no, el
   P.U. de cada partida queda con el valor que tenía su APU y los APU se descartan, con confirmación. Pruebas para los
   dos cambios.
10. **Insumo del sistema «Precio sin detallar».** Es el que recibe cada partida al pasar `usa_apu` de no a sí (tipo
    subcontrato, unidad glb, cantidad 1 por unidad de partida, precio = P.U. escrito); nadie lo edita ni lo desactiva.
    Visible y sin bloquear: un presupuesto con partidas sin detallar se puede ofertar y aprobar. En la lista de insumos
    va aparte y solo con su monto, sin cantidad ni unidad (sumar glb de partidas con distintas unidades no tiene
    sentido). El resumen del presupuesto dice cuánto del costo directo está sin detallar, para verlo al ofertar o
    aprobar. Prueba: con una partida sin detallar se puede ofertar, y la lista muestra ese monto aparte.
11. **Nombres.** Tablas del DBML tal cual (`presupuestos`, `presupuesto_titulos`, `presupuesto_partidas`, `insumos`,
    `apu_insumos`). Modelos: `Presupuesto`, `TituloDePresupuesto`, `PartidaDePresupuesto`, `Insumo`, `LineaApu`. El
    modelo existente `PartidaPresupuesto` (lo presupuestado de una partida de control) se renombra a
    `PresupuestadoDePartida`, sin cambiar su tabla (`partida_presupuestos`), en un commit aparte al inicio y con sus
    pruebas pasando: los dos nombres se diferenciaban solo por «De», con montos los dos. La versión del presupuesto
    va en `numero_version` (como el árbol) y `version` es el bloqueo optimista. Número `PRE-AAAA-NNNN`. Permisos
    `erp.presupuestos.*`. «Traer al proyecto» lo hace una persona con permiso, sin cadena de aprobación (el
    presupuesto no está en la matriz de aprobaciones del v4).
12. **Rendimiento en la partida, con excepción por línea (desarrollador, 2026-11-10; reemplaza la parte de la D4 que lo
    ponía en cada línea):** el rendimiento va una vez en la partida, con su unidad (hora o jornada), como en S10, y
    cada línea de mano de obra o equipo lo usa. Una línea puede llevar uno propio, con su propia unidad, que reemplaza
    al de la partida solo para ella (un equipo que se usa una parte del tiempo): quien presupuesta escribe el dato como
    lo conoce y el sistema convierte. Cantidad efectiva = cuadrilla × horas de la unidad ÷ rendimiento (el de la línea
    si lo tiene; si no, el de la partida). Cambiar el de la partida recalcula solo las líneas que lo usan; quitarlo
    mientras alguna lo use se rechaza. `apu_insumos.rendimiento_propio` dice cuál es cuál, y la línea guarda siempre el
    rendimiento con que se calculó. Sin rendimiento en la línea ni en la partida, la línea no se guarda. La cuadrilla
    sigue en cada línea (1 por defecto); si la usan es pendiente.
13. **Materiales sin producto: opcional, con aviso (desarrollador, 2026-11-10):** el enlace del material con su producto
    (D8) sigue opcional. Obligarlo frenaría el proyecto entero (sin presupuesto aprobado no hay árbol ni costos) y
    forzaría productos falsos para lo que Techquk no compra (lo del subcontratista, como cemento o arena) o lo genérico
    («accesorios varios»). El control del gasto va por partida; el enlace solo sirve para comparar cantidades
    presupuestadas contra compradas. Al ofertar y al traer al proyecto hay un aviso, sin bloquear, con los materiales
    sin producto y por qué importa (`ConsultaPresupuestos::materialesSinProducto`). El enlace se completa después
    aunque el presupuesto esté aprobado: el insumo es del catálogo y no cambia ningún monto.

Lo que sale de los números del flujo 1, sin decisión aparte: gastos generales = costo directo × %, utilidad = costo
directo × % (8 % de 17,750.00 = 1,420.00), IGV = (costo directo + gastos generales + utilidad) × %, cada uno
redondeado a 2; los porcentajes van en el presupuesto (IGV 18 % por defecto). `oportunidad_id` va sin llave foránea
hasta que se fusione `crm-oportunidades`. La fórmula polinómica (punto 4 de la D8 del plan 2.1) y exportar son otras
tareas.

## Pasos

1. Renombrar `PartidaPresupuesto` a `PresupuestadoDePartida` (D11), commit aparte con sus pruebas.
2. Migraciones y modelos: `presupuestos`, `presupuesto_titulos`, `presupuesto_partidas`, `insumos`, `apu_insumos`
   (con lo escrito de la D4 y la cantidad efectiva), el insumo del sistema (D10), los parámetros de jornada y de
   decimales del P.U., la definición de estados.
3. El cálculo puro: cantidad efectiva (D4), P.U., parciales, totales y lista de insumos con su ajuste (D5, D10), con
   sus pruebas.
4. Catálogo de insumos: servicio, acciones crear, editar y desactivar, el enlace al producto (D8) y la interfaz del
   precio sugerido (D1).
5. El presupuesto: servicio y acciones para crear, encabezado, títulos, partidas, líneas del APU y cambiar `usa_apu`
   (D9).
6. Estados y versiones: ofertar, traer al proyecto, nueva versión, reemplazado, anular, una sola aprobada por proyecto
   (D6).
7. C8 (`PresupuestosService::resumen()`, D7), el escenario de aceptación del flujo 1 y el de metrado 12.345.
8. Cierre: revisor de arquitectura, `composer check`, documentos.

## Preparado para lo diferido

- **Precio sugerido de la mano de obra (D1):** la interfaz con la firma de `CostoHoraService::vigente(categoriaId,
  fecha)`, que hoy devuelve «sin sugerencia»; C5 la llena.
- **Presupuesto importado de afuera (D5):** Excel del cliente, la variante asistida del v4. Ahí sí puede haber
  diferencias reales: comparar los totales del archivo con los calculados, mostrar la diferencia en valor y en
  porcentaje, señalar las partidas que no coinciden, y que alguien con permiso la acepte con motivo.
- **Número compartido entre versiones (revisión de arquitectura, decisión del desarrollador):** hoy es una excepción
  escrita en `docs/convenciones/codigo.md` (el servicio numera la versión 1 y copia el número). Cuando un segundo
  documento la necesite (la cotización), se mueve al núcleo: la definición declara que hereda el número de su versión
  anterior y `TransicionesService::crear` no numera en ese caso.
- **Unidad Global para facturar:** `unidades_medida.codigo_sunat` dice con qué código SUNAT se factura cada unidad (GLB →
  ZZ; las demás, su propio código). La facturación de la etapa 3 lo lee sin casos especiales.
- **Aviso de materiales sin producto en pantalla (D13):** `ConsultaPresupuestos::materialesSinProducto()` devuelve la
  lista y el mensaje; la 2.10 lo muestra en la confirmación de «Ofertar» y de «Traer al proyecto».
- **Compras completa el enlace (D13):** cuando Compras pida un material por primera vez y no tenga producto, podrá
  enlazarlo desde ahí con `InsumosService::editar` (el producto con la misma unidad), sin tocar el presupuesto.
- **Ofertar desde Comercial (D6):** la transición «Ofertar» es una acción registrada que hoy se usa a mano; cuando
  exista la cotización, Comercial la dispara por la costura C8, sin cambiar el presupuesto.

## Criterio de aceptación

- Parte 8, F1: «Se aprueba el presupuesto PRE-2026-031 → costo directo 17,750.00; gastos generales 1,775.00;
  utilidad 1,420.00; IGV 3,770.10; total 24,715.10; la lista de insumos suma 17,750.00» (cable 630 m, técnico 80 h,
  ayudante 74 h; el APU de 02.01 da P.U. 4.02).
- Las pruebas que piden las decisiones 4, 5, 6, 9 y 10.

## Bitácora

- 2026-11-06 — Rama `etapa-2-presupuesto-apu` desde `main` (be4277e, con la 2.60 fusionada). Lectura hecha; la sesión
  se cortó a pedido del desarrollador (una tarea, una sesión). La siguiente empieza por las tres decisiones de arriba.
- 2026-11-06 — Decisiones 1 a 3 tomadas. Al mapa: filas 2.9, 2.10 (pantallas del área 1) y 2.9b (proponer el árbol).
  Nota en la C5 del ADR 0020 (precio sugerido). Siguen las decisiones 4 en adelante, una por una.
- 2026-11-06 — Decisiones 4 a 11 tomadas, una por una; nuevas filas en `pendientes.md` (rendimiento del APU,
  decimales del P.U., insumos y productos) y la C8 del ADR 0020 con «ofertar». Pasos escritos. Sigue el paso 1.
- 2026-11-06 — Paso 1 hecho: `PartidaPresupuesto` → `PresupuestadoDePartida` (misma tabla; el alias del morph map
  pasa a `presupuestado_de_partida`). Pint, phpstan y `tests/Modules/Erp/Presupuestos` (82) en verde. Sigue el paso 2.
- 2026-11-07 — Nueva decisión al programar (preguntada): la unidad «Global» no existía. Se agrega GLB (glb, sin
  decimales) al catálogo del núcleo con `codigo_sunat` = ZZ; la columna nueva `unidades_medida.codigo_sunat` dice con
  qué código se factura cada unidad (las demás, su propio código). Prueba en `CatalogosSeederTest`.
- 2026-11-07 — Pasos 2 a 7 escritos juntos (comparten el bloqueo y el recálculo): migración `crear_presupuestos`, cinco
  modelos, `DefinicionPresupuesto` (ofertar; traer al proyecto, de acción propia; reemplazar, del sistema; anular),
  `CalculoPresupuesto` y `ListaDeInsumos` (puros), `InsumosService`, `ArmadoPresupuestoService`,
  `ConsultaPresupuestos`, C8 (`PresupuestosService`, `PresupuestosEnBase`, `EnMemoria\PresupuestosEnMemoria`), el
  punto de extensión `PrecioSugeridoManoObra` (hoy `SinPrecioSugerido`), 16 acciones y los parámetros
  `presupuestos.horas_jornada` (8.00) y `presupuestos.decimales_precio_unitario` (2). Cómo se resolvió lo técnico:
  - El número `PRE-AAAA-NNNN` lo pone el servicio y no el motor: el motor numera cada documento nuevo, y las versiones
    comparten número.
  - «Traer al proyecto» recibe el proyecto: la acción lo deja en `PedidoTraerAlProyecto` (scoped) mientras corre la
    transición, y su efecto `HacerVigente` lo lee, revisa por C2 que el proyecto acepte costos, pasa la versión
    anterior a reemplazada (transición del sistema, cuyo efecto le quita la vigencia) y marca la nueva. `proyecto_id`
    y `es_vigente` son campos derivados: solo los cambian esos efectos. Un proyecto con otro presupuesto vigente (de
    otro número) no recibe uno distinto: los cambios van como versión nueva del vigente.
  - Desviaciones del DBML (en el encabezado de la migración): `numero_version`, `es_vigente`, `oportunidad_id` sin
    llave, sin `aprobado_por`/`aprobado_at`, `titulo_id` obligatorio, llaves compuestas, `insumos.es_sistema`,
    `categoria_costo_hora_id` sin llave, `producto_id` único, lo escrito del APU y `horas_jornada` como foto.
  - Toda escritura bloquea el presupuesto, recalcula la partida y los totales y sube su `version`.
  - Pruebas: `CalculoPresupuestoTest`, `PresupuestoFlujo1Test` (aceptación F1), `PresupuestoTest`, `InsumosTest`,
    `ContratoPresupuestosTest`, `AccionesPresupuestoTest`; ayudante `tests/Costuras/PresupuestoDePrueba.php`.
- 2026-11-08 — Revisión del revisor de arquitectura: un bloqueante y siete sugerencias. Bloqueante, el número puesto por
  el servicio contra `codigo.md`: el desarrollador decide dejarlo como excepción escrita en `codigo.md`, con su
  disparador para moverlo al núcleo (ver «Preparado para lo diferido»). En `codigo.md` también los dos patrones nuevos:
  el pedido `scoped` para pasar datos de una acción propia a su efecto, y los campos derivados que mantienen los
  efectos del propio documento. Corregido: el choque de dos presupuestos traídos a la vez al mismo proyecto da un
  mensaje claro; los decimales del P.U. quedan como foto en el presupuesto (`decimales_precio_unitario`); la unidad de
  un insumo que ya está en un APU no cambia; las reglas que quedan en el servicio, anotadas en la migración; los
  valores por defecto salen de `DatosPresupuesto` y `DatosLineaApu`, no de las acciones. Queda sin cambiar, anotado:
  `aceptaCostos` se lee por C2 sin bloquear el proyecto (como el árbol). `tests/Modules/Erp` (139) en verde antes de
  estas correcciones; las seis pruebas nuevas del presupuesto (34) en verde después.
- 2026-11-08 — Primera corrida de `composer check`: 2609 pruebas, 3 fallas y 1 error, todas por las columnas nuevas:
  `decimales_precio_unitario` suena sensible (va en `camposNoSensibles()`), `codigo_sunat` faltaba entre los campos
  del catálogo de unidades (ahora editable en Configuración → Catálogos; vacío, toma el propio código al guardar) y
  `CatalogosIntegridadTest` inserta unidades con SQL sin esa columna. Corregidas; sus archivos (135) en verde.
- 2026-11-08 — Paso 8: segunda corrida de `composer check` en verde (pint, phpstan, migraciones, 2609 pruebas). Plan a
  `completados/` y la 2.9 marcada en el mapa. Rama subida; sigue la 2.10 (pantallas del área 1).
- 2026-11-09 — Trae `main` (Cuentas y Oportunidades, PR #84 y #85) para que el PR se pueda fusionar. Dos conflictos,
  resueltos sumando los dos lados: el mapa (filas 2.9, 2.10 y 2.9b con las de la 2.20 y la 2.21) y la lista exacta de
  permisos de `CatalogosSeederTest`. Pint, phpstan, 68 migraciones y las pruebas de Presupuestos y del seeder, en verde.
  Ya existe `oportunidades`: la llave de `presupuestos.oportunidad_id` queda por decidir.
- 2026-11-10 — Decisión del desarrollador sobre la cuadrilla y el rendimiento (D12): el rendimiento va en
  la partida, una vez y con su unidad (hora o jornada), y cada línea de mano de obra o equipo lo usa por defecto. Una
  línea puede llevar uno propio, con su propia unidad, que reemplaza al de la partida solo para ella. Cantidad efectiva
  = cuadrilla × horas de la unidad ÷ rendimiento (el de la línea si lo tiene; si no, el de la partida). Cambiar el de la
  partida recalcula solo las líneas que lo heredan. Se guarda si la línea hereda o lleva uno propio. Sin rendimiento
  en la línea ni en la partida, la línea no se guarda. Prueba: partida a 20 m/h y una línea a 400 m/jornada → 0.02 h
  por metro. En `pendientes.md` queda solo si usan cuadrilla.
- 2026-11-10 — Decisión del desarrollador sobre el P.U. (D5): 2 decimales, lo estándar en los programas de presupuesto
  del medio (S10, Delphin) y en las bases del Estado, con parcial = metrado × P.U. Sin cambios de código; el parámetro
  se queda. Sale de `pendientes.md`.
- 2026-11-10 — D12 y D13 programadas. Migración: `presupuesto_partidas.rendimiento` y su unidad (los dos o ninguno),
  `apu_insumos.rendimiento_propio`. El armado usa el de la partida si la línea no trae uno, recalcula las que lo
  heredan al cambiarlo y la versión nueva lo copia. `PresupuestoDePrueba::flujo1()` pone el rendimiento en cada
  partida (los números del F1 no cambian). Pruebas nuevas: `RendimientoDePartidaTest` y `MaterialesSinProductoTest`.
  Pregunta nueva en `pendientes.md`: materiales que Techquk no compra o genéricos.
- 2026-11-10 — Flujo de entregas (decisión del desarrollador): la rama `etapa-2-presupuesto-apu` pasa a
  `entrega/presupuesto-apu`, una entrega con la 2.9 sola (75 archivos, sobre el tope de ~60); la 2.10 y la 2.9b, en la
  siguiente. `composer check` en verde antes de traer `main` (2803 pruebas); trae `main` (PR #86 y #87) con *merge*, sin
  conflictos, y la corrida completa se repite sobre eso.
