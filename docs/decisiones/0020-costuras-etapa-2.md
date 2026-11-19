# 0020 — Las costuras de la etapa 2: quién hace qué y cómo se hablan las áreas

- **Estado:** aceptada (2026-11-04, por el desarrollador). Reparto revisado el mismo día con una medición por
  puntos de esfuerzo: la revisión se conversa en equipo antes de fusionarse. **Reparto aplicado el 2026-11-16** por
  Sebastián al cumplirse el corte (`docs/planes/completados/corte-reparto.md`): ver «El reparto aplicado».
- **Fecha:** 2026-11-04
- **Decide:** desarrollador; las costuras, en equipo

## Contexto

La etapa 2 (control de costos) la construyen cuatro personas en paralelo, cada una dueña de un área. El «qué» de
cada flujo ya está en el v4 (flujos 1, 2, 3 y 7, parte 6 y parte 8) y las columnas en el DBML. Lo que no está escrito
es **cómo se hablan las áreas**: si cada una inventa su versión del punto de contacto, el choque aparece al integrar.

`ARCHITECTURE.md` ya fija la regla: entre módulos de negocio, solo `Services/` y `Events/`. Este ADR nombra las
costuras concretas de la etapa 2, con lo mínimo para empezar en paralelo. Cada área completa el detalle en el plan de
su tarea; una costura nueva o cambiada se escribe aquí.

La imputación ya tiene su punto de enchufe desde la 1.9: `ImputacionService::destinar()` y `mover()` en el núcleo,
con la interfaz `DestinoImputacion`. Hoy el único destino es el centro de costo; la etapa 2 suma la partida.

## Decisión

### Cómo se midió el reparto

El primer reparto se midió por tablas del modelo y dejaba al área comercial con casi el triple de trabajo que la de
personal. Se rehízo midiendo cada módulo por sus documentos con estados, transiciones y efectos entre módulos,
pantallas, reglas de cálculo, concurrencia, escenarios de la parte 8 y dependencias. A cada módulo se le dio una
complejidad de 1 a 5, convertida a puntos de esfuerzo (1→2, 2→3, 3→5, 4→8, 5→13):

| Módulo | Complejidad | Puntos | Lo que más pesa |
|---|---|---|---|
| Presupuestos, con el esqueleto de C1, los cambios del núcleo en la imputación y el selector de imputación | 5 | 20 | APU, árbol con versiones, saldos; base de todas las imputaciones |
| Compras | 4 | 8 | Doce tipos de compra, orden con seis estados, reservas |
| Almacén | 4 | 8 | Stock con concurrencia, series, costo promedio, recepción desde el celular |
| Licitaciones | 4 | 8 | Quince pantallas, cronograma, expediente, públicas y privadas |
| Contratos | 3 | 5 | Penalidad calculada, alertas de la carta fianza, su comisión como orden de servicio |
| Oportunidades | 3 | 5 | Trece pantallas, tablero con requisitos por etapa |
| Cotizaciones | 3 | 5 | Versiones, comparador, PDF, aprobación bajo el margen mínimo |
| Tareo | 3 | 5 | Grilla en el celular, bloqueo por habilitación, imputación |
| Convocatorias del OECE (ADR 0018) | 4 | 8 | Integración externa con cola propia y vigilancia; explorador de todas las convocatorias |
| Proyectos, con el esqueleto de C2 | 2 | 3 | Casi todo es la costura C2 |
| Subcontratos · Valorizaciones · Gastos no presupuestados · Cuentas · Personal · Catálogo | 2 | 3 c/u | — |
| Actividades | 1 | 2 | — |
| Campañas | 1 | 2 | En validación: sin dueño hasta que se confirme |

Los puntos son relativos, no horas. Se recalibran con lo que tomen de verdad las primeras tareas.

### Las cuatro áreas

| Área | Dueño | Módulos | Puntos | % |
|---|---|---|---|---|
| **1 · Presupuesto y costo** | Sebastián | `Erp/Presupuestos` (con C1, el selector de imputación y los cambios del núcleo en la imputación) y los gastos no presupuestados del flujo 7 | 23 | ~24 % |
| **2 · Comercial** | Ander | `Crm/Cuentas`, `Crm/Oportunidades`, `Crm/Cotizaciones`, `Crm/Licitaciones`, `Crm/Actividades` | 23 | ~24 % |
| **3 · Contratos y abastecimiento** | Lennart | `Erp/Contratos`, `Erp/Compras`, `Erp/Almacen` y los subcontratos (viven en `Erp/ObraPublica`): todo lo que se firma, con el cliente, con proveedores y con subcontratistas | 24 | ~25 % |
| **4 · Obra y personas** | Yair | `Erp/Proyectos` (con C2), las valorizaciones (en `Erp/Proyectos` y `Erp/ObraPublica`), `Erp/Personal`, `Erp/Tareo`, `Crm/Catalogo` y, al final, las convocatorias del OECE con su explorador | 25 | ~26 % |

Sebastián construye un poco menos de lo que le tocaría por promedio porque, además, fusiona, revisa, desempata y
sigue con las tareas abiertas de la etapa 1. Las campañas (`Crm/Campanias`) quedan sin dueño hasta que se validen.

Cada dueño decide dentro de su área, con AGENTS.md y las convenciones. Crear o cambiar una costura se decide
**en equipo** (la IA ayuda a proponer y comparar opciones); si no hay acuerdo, desempata el desarrollador. El núcleo lo
decide el desarrollador; las convenciones y las reglas de los agentes se conversan en equipo (AGENTS.md,
«Mantenimiento de este archivo»). Lo que está en `pendientes.md` no lo decide nadie por su cuenta
(AGENTS.md, regla 12).

Un área puede construir piezas que viven en un módulo de otra (el catálogo y el OECE, en el CRM; las valorizaciones,
en Obra pública): el dueño del módulo revisa esos cambios.

### El reparto aplicado (2026-11-16)

Decidido por Sebastián al cumplirse el corte (con la 2.21d fusionada). Reemplaza la tabla de arriba para lo que **falta**
de la etapa 2; lo ya hecho en modo solo (Cuentas, Oportunidades, Cotizaciones, el árbol y el dinero del proyecto, la
portada de Proyectos) queda con el dueño de su módulo. **Cuatro personas: dos a tiempo completo y dos a tiempo
parcial**; se reparte por capacidad, contando el tiempo parcial como medio. Vale solo para la etapa 2: las etapas 3, 4 y 5
se reparten al abrirlas.

| Persona | Tiempo | Lo que lleva | Puntos | Empieza por |
|---|---|---|---|---|
| **Sebastián** | completo | El área 1 (presupuesto, árbol y dinero del proyecto; presupuesto de la empresa), el núcleo, la revisión de los PR y **las convocatorias del OECE** (pasan del área 4) | ~8 además de su área | lo que sigue del área 1 (2.12, 2.13) |
| **Yair** | completo | Obra y personas: Proyectos (fases, hitos, avance, cierre), valorizaciones, **subcontratos** (pasan del área 3), personal, tareo, el catálogo y **Almacén** (pasa del área 3) | ~24 | el costo de las horas (C5) |
| **Lennart** | parcial | Contratos y Compras | ~13 | el contrato (C10) |
| **Ander** | parcial | Lo que falta de Comercial: Licitaciones y Actividades | ~10 | Actividades (la próxima acción que espera la compuerta, CRM-05) |

- **Por qué Almacén a Yair:** está pegado a la obra (almacén de obra, despacho, series), y Compras más Almacén a medio
  tiempo es demasiado. La C6 queda entre los dos: Lennart es dueño de la orden de compra como costo (comprometido) y
  Yair recibe contra ella y lleva el almacén como costo (el despacho a obra mueve a ejecutado), con C1 e
  `ImputacionService`.
- **Costuras que cambian de dueño** (avisado a quien las usa): **C6** se comparte como arriba; **el OECE** (ADR 0018)
  pasa a Sebastián, que lo conecta con las oportunidades de Ander (CRM-04).
- **Lo pendiente que ya tiene dueño:** el avance físico de Proyectos (2.61, D9, obligatorio: barra, «Atrasados» y línea
  de tiempo por avance), de Yair; el margen por `VentaDelProyecto` (2.61, D10), de Lennart con el contrato; la próxima
  acción de Oportunidades (2.21d, D14) y la limpieza del tablero de gemas de la 2.21c, de Ander.

### Las costuras (once al aceptarse; la C12 se sumó en el plan 2.2; la C13 se sumó en el plan 2.21)

Cada una dice qué ofrece el área dueña, qué evento emite y quién la usa. Los nombres son una propuesta; el plan de la
tarea del dueño los puede ajustar avisando a quien la usa.

**C1 · La partida como destino de costo** · dueño: Sebastián · la usan Lennart y Yair.

- `PartidasService::buscar(proyectoId, texto, limite)`: las partidas hoja activas de un árbol aprobado de un
  proyecto que acepta costos, por código o nombre en todo el árbol, con su ruta completa («PC-02 › PC-02.01 Cable»).
  Sin la PC-99, que se pide aparte (plan 2.1, D7).
- `PartidasService::saldo(partidaId)`: presupuestado, comprometido, ejecutado, pagado y disponible en soles
  (v4, parte 1; plan 2.1, D7). Quien pide ve si se pasa **antes** de pedir.
- `PartidasService::destino(partidaId, motivoId?, comentario?, centroCostoId?)`: devuelve un `DestinoImputacion`
  para `ImputacionService::destinar()`. Rechaza una partida inactiva, que no es hoja, de un árbol no aprobado o de un
  proyecto que no acepta costos; la PC-99 exige motivo de «gasto no presupuestado» y comentario, con centro de
  referencia opcional (plan 2.1, D6; desarrollador, 2026-11-05). **Nadie escribe en `partidas_control` ni calcula
  saldos fuera de Presupuestos.**
- `PartidasService::noPresupuestada(proyectoId)`: la PC-99 del proyecto, o nada si su árbol no se aprobó (flujo 7).
- `PartidasService::resumen(partidaId)`: una partida por su id, aunque ya no esté activa, para mostrar lo que una
  línea ya tiene guardado (plan 2.2, D2; desarrollador, 2026-11-05).
- `PartidasService::saldo(partidaId, bloquear: true)`: el saldo con las partidas bloqueadas, para revisar el exceso
  al aprobar en la misma transacción que sube el comprometido (plan 2.2, D3 y D4; desarrollador, 2026-11-05).
- Versiones falsas para las pruebas de quien las usa: `App\Modules\Erp\Presupuestos\Services\EnMemoria\PartidasEnMemoria` (lecturas y rechazos) y
  `Tests\Costuras\ArbolDePrueba` (un árbol real, para imputar de verdad); para C2, `App\Modules\Erp\Proyectos\Services\EnMemoria\ProyectosEnMemoria`.
  Las versiones en memoria viven en la app (plan 2.2, D8) solo para las pruebas y la página de modo desarrollo del
  selector: nunca se registran en producción; `tests/Arch` vigila quién las usa.
- El **selector de imputación** es un solo componente para todos los documentos con costo; lo construye Sebastián con
  C1 y lo usan los demás.
- `MargenService::estimado(proyectoId)`: el margen estimado del proyecto (venta sin IGV menos el mayor entre
  presupuestado y comprometido de cada partida). Usa C10 para el monto del contrato.
- Sebastián hace en el núcleo los cambios que la 1.9 dejó anotados: `proyecto_id`, `partida_control_id`,
  `posibles_adicionales` y el nuevo `imputaciones_destino_check`.

**C2 · El proyecto** · dueño: Yair · lo usan todas.

- `ProyectosService::resumen(proyectoId)`: código, nombre, cliente, contrato, jefe, estado, si es interno, si acepta
  costos (no cerrado).
- `ProyectosService::activos(texto)`: para los selectores.
- `ProyectosService::crearDesdeContrato(contratoId, DatosProyecto)`: el proyecto «en preparación» que nace con el
  contrato. La usa Contratos. La firma suma `DatosProyecto` (plan 2.1, D4; desarrollador, 2026-11-04) porque el
  nombre, el jefe de proyecto y las fechas no salen del contrato.
- `ProyectosService::iniciarEjecucion(proyectoId)` (agregada en el plan 2.1, D4; desarrollador, 2026-11-04): pasa el
  proyecto a «en ejecución». La llama Presupuestos al aprobar el árbol de partidas; no es una acción de usuario y no
  aparece en ningún botón. Si el proyecto ya está en ejecución no hace nada (una versión nueva del árbol la vuelve a
  llamar); si está cerrado o anulado, falla con un mensaje claro.
- Eventos: `ProyectoCreado`, `ProyectoCerrado` (al cerrarse ya no acepta costos).
- **Ampliación del plan 2.21 (D1). Aprobada por el desarrollador, 2026-11-07.**
  `ProyectosService::anularEnPreparacion(proyectoId, motivo)`: anula un proyecto que sigue en preparación. La usa
  Contratos al anular su borrador (C3). Si el proyecto no está en preparación, falla con un mensaje claro. La
  construye la sesión del área 4; mientras, Oportunidades y Contratos usan su versión falsa (regla 3).

**C3 · La oportunidad ganada** · dueño: Ander · la usa Lennart.

- Evento `OportunidadGanada` con lo que pasa al ERP sin reescribirse (v4, parte 3): el cliente (`party_id`), la
  cotización aceptada, el monto y la moneda, las condiciones (plazo, forma de pago, garantía), el presupuesto
  enlazado y la convocatoria de origen si la hubo.
- Lennart lo escucha, crea el contrato en borrador y pide a C2 el proyecto en preparación; Administración de
  Contratos completa lo demás.
- `CotizacionesService::resumen(cotizacionId)` para leer la cotización desde el contrato.
- **Ampliación del plan 2.21 (D1). Aprobada por el desarrollador, 2026-11-07.**
  - `OportunidadGanada` suma `oportunidadId` (el contrato guarda de qué oportunidad nace). Hasta que existan sus
    módulos van vacíos la cotización aceptada y sus condiciones (plazo, forma de pago, garantía) y el presupuesto
    enlazado (CRM-03), y la convocatoria de origen (CRM-04): hasta CRM-03 se puede ganar sin cotización (D3).
  - `ContratosDeOportunidad::anularBorrador(oportunidadId, motivo)`, que ofrece el área 3 y usa el área 2. Se llama
    dentro de la transacción que anula una oportunidad ganada. Bloquea el contrato de esa oportunidad y: si no hay
    contrato, no hace nada; si está en borrador, lo anula con el mismo motivo y pide a C2 anular el proyecto en
    preparación; si ya avanzó, falla con «Primero anula el contrato CT-…» y la anulación entera se deshace.
  - **Orden de bloqueo fijo:** primero la oportunidad, después el contrato, después el proyecto y al final la
    cuenta (al anular, Cuentas revierte la marca de cliente después de Contratos). Quien bloquee dos o
    más de estas filas lo hace en este orden, para no trabarse con otra transacción.
  - **Contrato creado después de anular la ganada** (revisión de arquitectura de la 2.21; desarrollador,
    2026-11-07): Contratos crea el borrador desde la cola, después de confirmar `OportunidadGanada`. Antes de crearlo,
    su oyente llama, dentro de su transacción, a `OportunidadesService::exigirGanada(oportunidadId)`, que bloquea la
    oportunidad (primera en el orden de bloqueo) y dice si sigue ganada; si no, el oyente no crea nada. Así, si la
    ganada se anula antes de que el oyente corra, no queda contrato; y si el oyente corre antes, `anularBorrador` lo
    encuentra y lo anula.
  - Evento `OportunidadGanadaAnulada(oportunidadId, cuentaId, motivo)`, emitido después de confirmar. Es un aviso:
    lo esencial (contrato, proyecto, marca de cliente) ya se hizo dentro de la transacción.
  - Mientras Contratos no exista, Oportunidades usa una versión falsa de `ContratosDeOportunidad` que responde «no
    hay contrato» (regla 3).
  - Descartado: revisar «sigue en borrador» y que Contratos anule al escuchar el evento: entre la revisión y el aviso
    el contrato podía aprobarse y quedaba un contrato vivo de una oportunidad anulada.
- **Ampliación del plan 2.21b (faja de vidrio, D9). Aprobada por el desarrollador, 2026-11-10.**
  - `ContratosDeOportunidad::etiquetasDeContratos(oportunidadIds)`: la etiqueta del contrato de cada ganada que ya lo
    tiene («CT-2026-0012»), en lote, para la sala de ganadas de la faja. Las que no tienen contrato no vienen.
  - `ContratosDeOportunidad::tieneDatos(): bool`: si la versión registrada tiene datos reales. La de fábrica
    (`ContratosSinModulo`) responde `false`; la de Contratos, `true`.
  - **Regla de la columna oculta:** mientras `tieneDatos()` sea `false`, la tabla de ganadas no muestra la columna
    «Contrato» (no una columna llena de «—»), como la D11 del plan de Cuentas con los datos que aún no existen. La
    columna aparece sola cuando Contratos registra su versión; Oportunidades no cambia.

**C4 · El catálogo** · dueño: Yair (vive en `Crm/Catalogo`; Ander revisa sus cambios) · lo usan Sebastián (insumos),
Lennart (compras y almacén) y Ander (cotizaciones).

- `CatalogoService::buscar(texto)` y `CatalogoService::resumen(productoId)`. Un solo catálogo, sin copias (v4,
  parte 2).
- Ampliada en el plan 2.60 (desarrollador, 2026-11-06). El resumen trae código, nombre (la descripción corta, o la
  completa si falta), descripción completa, unidad (id y abreviatura), si va por serie, si es servicio y si está
  activo. Lo que se sumó, y por qué:
  - **descripción completa:** Compras (orden al proveedor) y Cotizaciones (especificaciones para la entidad) la
    necesitan y no pueden leer la tabla del catálogo (regla 2);
  - **id de la unidad:** las líneas del presupuesto, la orden y el almacén guardan la unidad como llave foránea a
    `unidades_medida` del núcleo;
  - **si es servicio:** un servicio no entra al almacén, y el APU separa materiales de servicios;
  - **si está activo:** `resumen()` también devuelve descontinuados, y la pantalla debe poder marcarlos.
- `buscar()` da solo activos, hasta 20, por código o descripción sin tildes ni mayúsculas; el código exacto sale
  primero. El código del producto va siempre en mayúsculas (plan 2.60, D6).

**C5 · El costo de las horas** · dueño: Yair · lo usan Sebastián (APU) y Lennart.

- `CostoHoraService::vigente(categoriaId, fecha)`: el costo estándar por hora de una categoría a una fecha.
  El APU (tarea 2.9) la usa como **precio sugerido**, no obligatorio: quien presupuesta puede cambiarlo, y el APU
  guarda el precio que quedó como foto. Mientras C5 no exista, el punto de extensión del APU devuelve «sin sugerencia».
- `PersonalService::habilitado(colaboradorId, proyectoId, fecha)`: si puede trabajar en obra ese día (habilitación
  de campo, v4 flujo 9). La usa Lennart si un despacho exige receptor habilitado.
- Evento `SemanaTareoCerrada` (proyecto, semana, horas valorizadas por partida). Antes de emitirlo, Tareo ya imputó
  con C1; el evento es para quien quiera reaccionar.

**C6 · Las compras y el almacén como costo** · dueño: Lennart (la orden de compra); el almacén, Yair desde el reparto
aplicado (2026-11-16) · lo usan Sebastián y, en la etapa 3, Tesorería.

- La orden aprobada imputa el **comprometido** y el despacho a obra (o la recepción directa en obra) mueve a
  **ejecutado**, todo con C1 e `ImputacionService`.
- Eventos: `OrdenCompraAprobada`, `RecepcionConfirmada`, `DespachoConfirmado` (con proyecto, partida, cantidades y
  montos). Presupuestos puede escuchar `OrdenCompraAprobada` para el precio real de un insumo.
- `ComprasService::resumenOrden(ordenId)`: para el cruce de tres vías de la etapa 3.

**C7 · Los datos de las personas** · dueño: el núcleo (ya existe) · lo usan todas.

- Cliente, proveedor, trabajador y la propia Techquk salen de `PartiesService` y `EmpresaService`. Nadie crea su
  propia tabla de personas ni de empresas.

**C8 · El presupuesto de una cotización** · dueño: Sebastián · la usa Ander.

- `PresupuestosService::resumen(presupuestoId)`: versión, costo directo, gastos generales y las partidas con su
  precio, para que la cotización se arme sobre el presupuesto real sin copiarlo.
- Al enviarse la oferta, el presupuesto pasa a **ofertado** (congelado: una oferta enviada no se cambia). Hoy es una
  acción registrada que se usa a mano (plan 2.9, D6); cuando exista la cotización, Comercial la dispara por esta costura.
- **Ampliada el 2026-11-12** (plan 2.22, D8; desarrollador, P3): `PresupuestosService::deOportunidad(oportunidadId)`
  (los presupuestos de la oportunidad en borrador u ofertados, la versión más reciente primero, para que la cotización
  elija cuál enlaza) y `PresupuestosService::ofertar(presupuestoId, User)`, que la cotización llama al emitirse.
  `ofertar` es **idempotente** (ya ofertado o aprobado: no cambia nada), corre como transición del sistema a nombre de
  quien emite (no pide el permiso de ofertar a mano) y falla con `PresupuestoRechazado` si está incompleto, reemplazado o
  anulado. Si la cotización necesita otros costos, se hace una **versión nueva del presupuesto**, que ahora sale también
  de uno ofertado; al ofertarse o traerse al proyecto la nueva, la ofertada anterior pasa a reemplazada. La versión
  falsa (`PresupuestosEnMemoria`) pasa las mismas pruebas del contrato.
  Para quien recibe `OportunidadGanada` (área 3): el `presupuestoId` es el que la cotización aceptada tenía enlazado al
  emitirse; puede estar ya reemplazado por una versión ofertada después (queda como foto de lo que se ofertó). El
  vigente para la obra es el que se trae al proyecto.

**C9 · Las certificaciones del personal** · dueño: Yair · la usa Ander.

- `PersonalService::certificacionesVigentes(especialidad, fecha)`: cuántas personas tienen vigente cada certificación
  que exige una licitación (v4, parte 2: el expediente verifica personal certificado).

**C10 · El contrato** · dueño: Lennart · lo usan Sebastián (margen) y Yair (valorizaciones).

- `ContratosService::resumen(contratoId)`: monto vigente con adendas, plazo, hitos, forma de pago, penalidad.
- Evento `AdendaAprobada`: cambia monto o plazo; quien calcula el margen o reclasifica un gasto reacciona a él.

**C11 · El posible adicional** · dueño: Sebastián · lo usa Lennart.

- Evento `PosibleAdicionalRegistrado`: un gasto no presupuestado cuyo motivo es un cambio pedido por el cliente
  (flujo 7). Contratos avisa a Administración de Contratos para cobrarlo con una adenda.
- Hecho en el plan 2.6 (D6): `App\Modules\Erp\Presupuestos\Events\PosibleAdicionalRegistrado` con `proyectoId`,
  `imputacionId` (la línea, de cualquier documento) y `montoSoles`; se dispara después del commit en cada aprobación de
  una línea a la PC-99 cuyo motivo tiene `motivos.es_cambio_cliente`, aunque la alerta «Posible adicional detectado»
  esté apagada o falle. Contratos reconoce una línea repetida (aprobada otra vez tras anular) por su `imputacionId`.
  No tiene inverso todavía: anular el documento no avisa; Contratos, al construirse, revisa que la línea siga
  comprometida (o la costura suma un evento de retiro, decidido entonces). La lista
  de posibles adicionales la da `GastosNoPresupuestadosService::posiblesAdicionales()`.

**C12 · El exceso de partida** · dueño: Sebastián · la usan Lennart (Compras, Almacén) y Yair (Tareo). Agregada en el
plan 2.2 (D5; desarrollador, 2026-11-05). Cada documento con costo:

- (a) pone el selector de imputación del núcleo en sus líneas y, en su servicio, guarda cada línea con su destino
  (`FuenteDeDestinos::destino()` e `ImputacionService::destinar()`) en una sola transacción: si el destino falla, la
  línea no queda (D2);
- (b) llama a `ExcesoDePartidaService::alEnviar($lineas)` al enviar, dentro de la transacción de su transición (por
  eso «enviar» es de acción propia), y a
  `alAprobar($lineas)` al aprobar, antes de subir el comprometido y en su misma transacción (D3 y D4);
- (c) declara sus líneas de costo en su definición (`lineasDeCosto($documento)`), para que las reglas de aprobación con
  la condición «exceso de partida» entren solo cuando alguna línea excede (D5).

Plan 2.6 (D2): con esas mismas tres piezas el documento ya admite gastos no presupuestados (líneas a la PC-99): la
condición «Solo si alguna línea va a la PC-99» y los avisos al enviar y aprobar se cuelgan de (b) y (c). Ningún
documento agrega nada.

La firma que exigen el exceso o la PC-99 nunca la da quien pidió el documento (planes 2.2 y 2.6; desarrollador,
2026-11-06): esas reglas no admiten autoaprobación, y si la misma jefatura firma también por la regla del monto, la
firma única lleva la regla sin autoaprobación, aunque la del monto tenga tope. La autoaprobación de la regla del monto
en otra unidad sigue valiendo: si la regla del monto la firma la Jefatura A (con tope) y la de la PC-99 la Gerencia B,
el jefe de A que pidió autoaprueba la firma de A, y la de B la da su jefe. Detalle en `docs/convenciones/codigo.md`
(«Imputación»).

**C13 · La oportunidad para otras áreas** · dueño: área 2 (Comercial) · la usan el área 1 (Presupuestos) y, en la
etapa 5, Analítica. **Del plan 2.21 (D12). Aprobada por el desarrollador, 2026-11-07.** Junto a la C3, que
ya nombra el «presupuesto enlazado».

- `OportunidadesService::buscar(texto, cuentaId?, incluirId?)`: las abiertas, para elegir la oportunidad de un
  presupuesto o una cotización. `OportunidadesService::resumen(oportunidadId)`: número, nombre, cuenta, ejecutivo,
  etapa, monto, moneda y si está abierta.
- Presupuestos enlaza con `presupuestos.oportunidad_id` (FK con índice) y lee por `resumen`; nadie lee la tabla
  `oportunidades` directamente.
- Los tableros de Analítica leen por métodos de resumen de `OportunidadesService` (embudo, ganadas y perdidas por
  período, motivos de pérdida, precios de competidores), que se agregan cuando se construyan.

### Reglas para todas

1. Un servicio devuelve un **resumen** (objeto de solo lectura, como `ResumenCuentaBancaria`), nunca el modelo de
   otro módulo.
2. Los eventos se disparan **después de confirmar la transacción** (`ShouldDispatchAfterCommit`).
3. Mientras la costura real no exista, quien la usa trabaja con una **versión falsa** registrada en el contenedor, y
   la falsa y la real pasan las mismas pruebas.
4. **Las primeras tareas** son los esqueletos de las costuras que otros esperan: Sebastián, C1; Yair, C2 y C4.
   Ander no depende de nadie y arranca ya. Como las partidas apuntan a un proyecto real en la base de datos,
   la primera tarea de Sebastián crea también lo mínimo de `Erp/Proyectos` (la tabla `proyectos`, sus estados y
   C2); Yair continúa desde ahí (fases, hitos, entregables) y es el dueño del módulo.
5. Una costura se crea o se cambia **en este ADR**, decidida en equipo y avisando a quien la usa; si no hay acuerdo,
   desempata el desarrollador.

### Los escenarios que cierran la etapa

| Escenario | Dueño |
|---|---|
| Margen del proyecto 1,327.00 | Sebastián |
| Taxi en la PC-99 | Sebastián |
| Penalidad 205.96 por día | Lennart |
| Cable 1,248.00 en su partida | Lennart |

### Por decidir al planificar

- **Los gastos no presupuestados** tienen ficha con cuatro estados pero no tienen tabla propia: Sebastián decide su
  documento en el plan de esa tarea.
- **Los adelantos** (decidido por Sebastián, 2026-11-16): en la etapa 2, el subcontrato **registra** el porcentaje y
  el monto del adelanto entregado; el **descuento** en cada valorización lo aplica la etapa 3, junto con la retención y
  el reajuste. El escenario del flujo 10 (20 %) se prueba entero en la etapa 3; en la 2, que el adelanto quede
  registrado con su porcentaje.
- **La fórmula polinómica** (desarrollador, 2026-11-04): se **define** en la etapa 2 con el presupuesto
  (monomios, coeficientes y la carga manual de índices, tarea de Sebastián) y se **aplica** en la etapa 3, en las
  valorizaciones de obra pública, junto con adelantos y retención. En la etapa 2 la valorización deja preparado
  dónde se enchufa el reajuste.
- **Las pantallas:** todas siguen los patrones que ya tiene el sistema (`docs/convenciones/interfaz.md`). La mejora
  de la interfaz se hace al final, a cargo de Sebastián.

## Consecuencias

- Cada área avanza sin esperar a las otras: usa la versión falsa de lo que le falta y la cambia por la real cuando
  llega, sin tocar su propio código.
- Los saldos de partida y el margen tienen un solo lugar donde se calculan (Presupuestos).
- La carta fianza que crea una orden de servicio queda dentro de un área (contratos y compras), sin costura.
- Al principio, Sebastián (C1) y Yair (C2, C4) están en el camino de los demás: sus esqueletos tienen que ser cortos
  y salir primero.
- `tests/Arch` ya vigila que nadie importe modelos de otro módulo; las costuras le dan a cada uno el camino permitido.

## Alternativas descartadas

- **El primer reparto, por tablas** (Columna, Comercial completo, Compras y Almacén, Personal y Tareo): medido por
  esfuerzo dejaba 35 % a Comercial y 12 % a Personal.
- **Mapear el 100% de la etapa 2 antes de repartir:** lo haría una sola persona, en cascada, y les quitaría a los
  dueños las decisiones de su área.
- **Repartir sin costuras escritas:** cada área inventa su versión del punto de contacto y el choque aparece al
  integrar, que es lo más caro.
- **Un documento de diseño en vez de un ADR:** un documento se edita sin pasar por el equipo; una costura cambiada en
  silencio rompe el trabajo de otro.
- **Que cada módulo lea las tablas de los otros directamente:** lo prohíbe `ARCHITECTURE.md` y lo vigila `tests/Arch`.
