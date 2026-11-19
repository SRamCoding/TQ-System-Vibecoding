# Plan: Cotizaciones (CRM-03, tarea 2.22)

- **Estado:** terminada el 2026-11-15 (2.22a, 2.22b y 2.22c; `check:tarea` en verde)
- **Responsable:** Sebastián (área 2, Comercial, en modo solo; ADR 0020). Toca piezas del área 1 (C8) y del núcleo, que
  hoy también son de Sebastián
- **Entrega:** `entrega/crm-cotizaciones` (primera tarea de la entrega; se propone partirla en 2.22a, 2.22b y 2.22c,
  ver «Tamaño y partición»)
- **Módulo(s):** `Crm/Cotizaciones` (nuevo); toca `Crm/Oportunidades` (el aviso al ganar), `Crm/Cuentas` (pestaña, por
  su registro), `Erp/Presupuestos` (C8) y Nucleo (número compartido entre versiones, condición de aprobación)
- **Fase:** F2
- **Creado:** 2026-11-12 · **Actualizado:** 2026-11-12

## Objetivo

1. La cotización existe como documento con versiones (`COT-2026-0184` v1, v2…), estados como datos y efectos
   separados: se arma con líneas del catálogo (C4), líneas libres «por catalogar» y, para instalación, el presupuesto
   con APU enlazado (C8); calcula subtotal, descuentos, IGV, total, costo y margen; bajo el margen mínimo no se emite
   sin la aprobación registrada de Gerencia Comercial; una emitida no se edita (se hace otra versión); solo una versión
   puede quedar aceptada; vence sola.
2. Oportunidades exige «una cotización emitida» para enviar la propuesta y «una cotización aceptada» para ganar, y
   `OportunidadGanada` sale con la cotización aceptada, su monto, moneda, condiciones y presupuesto enlazado (C3).
3. Lo prueban los escenarios del v4 con sus números exactos (parte 8, CRM y Frontera; ver D17).

## Contexto que leí

- `AGENTS.md`, `CLAUDE.md`, `docs/planes/plantilla.md`, `docs/planes/mapa-etapas.md` (etapa 2: filas 2.9, 2.10,
  2.20–2.21c, «Entregas», «Documentos impresos (de la 1.15)», «cada documento guarda la tasa de IGV que usó al
  emitirse»), `ARCHITECTURE.md` (`Crm/Cotizaciones`, área 2).
- ADR 0020: tabla de puntos (Cotizaciones: «versiones, comparador, PDF, aprobación bajo el margen mínimo»), C3 (la
  oportunidad ganada y su ampliación: `CotizacionesService::resumen(cotizacionId)` para Contratos; el orden de bloqueo
  oportunidad → contrato → proyecto → cuenta), C4 (`CatalogoService::buscar/resumen`, con descripción completa e id de
  unidad pensados para Cotizaciones), C8 (`PresupuestosService::resumen`; al enviarse la oferta, el presupuesto pasa a
  ofertado, «cuando exista la cotización, Comercial la dispara por esta costura»), C13 (`OportunidadesService::buscar`
  y `resumen`), reglas 1–5.
- v4: parte 2 (CRM-03: editor de cotización con `COT-2026-0184 · versión 2`, PEN, TC 3.74, válida 15 días, líneas
  con descuento y margen, condiciones comerciales que viajan al PDF y al contrato, margen 19.4 % con objetivo 25 % y
  mínimo 22 %; reglas del CRM: «una cotización enviada no se edita: se duplica como versión; solo una versión puede
  estar aceptada; bajo el margen mínimo requiere aprobación»; IA «Términos de referencia → ítems y cantidades»),
  parte 3 (frontera: «Cotización aceptada COT-2026-0190 v1 · S/ 24 715.10», presupuesto «enlazado, no copiado»),
  parte 4 flujo 1 (camino corto: precio por ítem de la cotización), parte 6 (el contrato hereda monto, plazo y forma
  de pago de la cotización aceptada), parte 7 (automatización «CRM · cotización: margen, umbrales y aprobación
  requerida; vencimiento automático», regla fija; restricción de campo: quien no debe no ve costos ni márgenes),
  parte 8 (escenarios CRM y Frontera, D17).
- Diseño del 13 oct (`docs/analisis/bloques/crm-pantallas.md`, siete pantallas de CRM-03 más
  `OpCotizaciones`, `CuentaCotizaciones`, `AprobacionesBandeja` y `Traspaso`): lista en carpetas Cuenta › Oportunidad
  › Cotización › Versión con filtros (canal, Por aprobar, Enviadas, Aceptadas, Por vencer, Vencidas, Solo mías);
  editor; solicitud de aprobación (comentario obligatorio al rechazar; «cada revisión conserva su costo y su
  margen»); comparador de versiones línea por línea; vista previa del PDF; bandeja de aprobación de descuentos.
- DBML v2, CRM-03: `cotizaciones` (cabecera), `cotizacion_versiones` (totales, costo, margen, estado, PDF),
  `cotizacion_lineas` (producto opcional, descripción, unidad, costo y precio unitarios, descuento, margen,
  `es_opcional`), `condiciones_comerciales`. Glosario: «Margen estimado».
- Planes: 2.21 (`completados/2026-11-06-crm-oportunidades.md`: D1, D3, D9 y D12.6: lo que CRM-03 debe registrar y
  completar), 2.9 (`completados/2026-11-06-presupuesto-apu.md`: «Ofertar desde Comercial» y «Número compartido entre
  versiones» en «Preparado para lo diferido»), 2.60 (C4), 2.6 (cómo se sumó una `CondicionRegla`).
- Código: `Crm/Oportunidades` (`Requisitos/RequisitosDeEtapa` y `RequisitoDeEtapa`, `Pestanas/RegistroPestanasOportunidad`,
  `Filament/CapasDelDespiece::EN_ESPERA['cotizaciones']`, `Impedimentos/ImpedimentosDeOportunidad`,
  `Efectos/GanadaAvisaAlErp`, `Events/OportunidadGanada` con `cotizacionId`, `plazo`, `formaPago`, `garantia`,
  `presupuestoId` en `null`, `Services/OportunidadesService::buscar/resumen`), `Crm/Cuentas/Pestanas/RegistroPestanasCuenta`,
  `Crm/Catalogo/Services/CatalogoService`, `Erp/Presupuestos/Services/PresupuestosService` (solo `resumen()`) y
  `Estados/DefinicionPresupuesto` (`ofertar`: borrador → ofertado, con `PresupuestoCompleto`), Nucleo
  (`NumeracionService`, `AprobacionesService` y `Enums/CondicionRegla`, `TipoCambioService::del`, parámetro
  `tributario.igv_porcentaje`, catálogo `formas_pago`, `Seguridad/GrupoSensible::Costos`, `EmpresaService::datos()`).
  No hay librería de PDF en `composer.json` ni pantalla «Mis aprobaciones» (solo `AprobacionesService::porFirmar`).
- Otra sesión: `entrega/presupuesto-pantallas` (2.10, plan `2026-11-11-pantallas-area-1.md`) no cambia
  `PresupuestosService` ni `ResumenPresupuesto`; deja «Ofertar» en la ficha del presupuesto y dice que cuando Comercial
  lo dispare por C8 la pantalla sigue igual. Sin choque; si la 2.10 se fusiona antes, la 2.22a trae `main` y listo.
- **Pendientes que afectan** (`docs/decisiones/pendientes.md`):
  - «Embudo comercial» (actualizado el 2026-11-11): «¿qué exige pasar de Negociación a Ganada?» y «¿se gana también
    desde Propuesta enviada?». La 2.21 (D9, aprobada) ya decidió con el v4 que ganar exige cotización aceptada; la
    respuesta de la empresa no bloquea si el requisito se puede apagar como dato (D11).
  - «Aprobaciones» (U1–U3): no aplica; la aprobación de la cotización es por margen, no por monto.
  - «Organigrama real» (en espera): quién es «Gerencia Comercial» se configura en la regla; en pruebas, datos
    inventados.
  - Nuevos, que esta tarea suma (D18): margen objetivo y mínimo, días de validez, motivos de nueva versión y de rechazo.

## Decisiones

Fuente de cada una: v4, ADR, plan anterior o **propuesta del agente**. Aprobadas por el desarrollador el 2026-11-12
(P1 a P10, con los ajustes de «Respuestas del desarrollador», que mandan sobre el texto de cada D).

- **D1 · El módulo y lo que cruza.** `Crm/Cotizaciones`, área 2. Usa: C13 (`OportunidadesService::buscar/resumen`),
  `CuentasService` (cuenta y contactos), C4 (`CatalogoService`), C8 (`PresupuestosService`), Nucleo (estados,
  numeración, aprobaciones, tipo de cambio, parámetros, adjuntos, empresa). Ofrece `CotizacionesService::resumen()`
  (C3) y sus registros en Oportunidades y Cuentas. Nunca toca modelos de otros módulos (regla 2). *(ADR 0020, C3, C4,
  C8, C13.)*

- **D2 · Cada versión es el documento; el número se comparte.** *Propuesta del agente, apartándose del DBML.* Una sola
  tabla `cotizaciones` donde cada fila es una versión (`numero` + `numero_version`, únicos juntos), con
  `cotizacion_lineas` colgando de ella; igual que el presupuesto (plan 2.9). Así cada versión tiene su estado, su
  historial, sus totales, su costo y su margen («el costo y el margen viven en cada versión y no en la cabecera», diseño
  del comparador), y el motor de estados trabaja sobre una fila. La cabecera aparte del DBML (`cotizaciones` +
  `cotizacion_versiones`) se descarta: duplicaría estado y moneda. El DBML se actualiza. El número compartido se mueve
  al núcleo, como dejó escrito `docs/convenciones/codigo.md` («cuando un segundo documento lo necesite, la cotización»):
  la definición declara que hereda el número de su versión anterior y `TransicionesService::crear` no numera en ese
  caso; el presupuesto pasa a usarlo y la excepción desaparece de la convención. *(Convención de código; plan 2.9.)*

- **D3 · Numeración.** `COT-AAAA-NNNN`, correlativo anual del núcleo, tomado al crear la versión 1 (borrador), no al
  emitir: el diseño muestra el número en un borrador (`COT-2026-0184 · versión 2 · Borrador`). Las versiones heredan el
  número. *(v4 parte 2 y diseño; formato del correlativo, propuesta del agente.)*

- **D4 · Relación con oportunidad y cuenta.** La oportunidad es **obligatoria** (`oportunidad_id NOT NULL`, FK con
  índice; el DBML la permitía nula): las carpetas del diseño son Cuenta › Oportunidad › Cotización, y la postventa
  devolverá una oportunidad al CRM (v4 parte 3), no una cotización suelta. Se elige con `OportunidadesService::buscar`
  (solo abiertas) y la cuenta se toma de su `resumen` (`cuenta_id NOT NULL`, la misma de la oportunidad). Contacto
  «Atención» opcional, de los contactos de la cuenta. Crear, editar, emitir y aceptar exigen la oportunidad abierta;
  las cotizaciones de una oportunidad cerrada quedan como están (historia). Sin `party_id` en `cotizaciones`. **Nunca
  hay cotizaciones sueltas.** **Cotizaciones solo enlaza oportunidades que ya existen; no las crea.** La oportunidad
  nace en Oportunidades (desde su lista o desde la cuenta), que es donde se coordinó, con el monto estimado que pone
  quien la crea. Si todavía no existe, primero se crea allá y después se cotiza. *(P1, desarrollador; revisado el
  2026-11-12: se retira «+ Nueva oportunidad» del formulario de la cotización. Ese botón creaba la oportunidad con el
  monto de una cotización recién nacida, 0 sin líneas, y Oportunidades exige un monto positivo, `validarMonto`.)*

- **D5 · Estados y transiciones (datos; cada efecto, una pieza).** *Propuesta del agente sobre el v4 y el diseño.*
  - Estados: `borrador`, `por_aprobar`, `aprobada`, `emitida` (el diseño dice «Enviada»), `aceptada`, `rechazada`
    (la rechazó el cliente), `vencida`, `reemplazada`, `anulada`.
  - Transiciones:
    | Código | De → a | Quién | Condición / efectos |
    |---|---|---|---|
    | `solicitar_aprobacion` | borrador → por_aprobar | ejecutivo | Solo si el margen está bajo el mínimo; abre la ronda del núcleo |
    | `aprobar` | por_aprobar → aprobada | firma de la regla | `requiereAprobacion`; rechazar = observar con comentario obligatorio → borrador |
    | `emitir` | borrador o aprobada → emitida | ejecutivo | Desde borrador solo si el margen ≥ mínimo. Efectos: congela (D9), ofertar el presupuesto (D8), PDF (D13, en la 2.22c), reemplaza la versión emitida anterior |
    | `aceptar` | emitida → aceptada | ejecutivo | Fecha de aceptación (no futura); **documento de respaldo**: tipo de lista (familia `crm.cotizaciones.respaldo`: O/C, O/S, buena pro, contrato, correo) y su referencia, obligatorios; adjunto opcional en el servicio (se sube desde la pantalla, 2.22b). Exige no vencida y oportunidad abierta. Efecto: marca la aceptada única (D7) *(respaldo: P2, desarrollador)* |
    | `rechazar` | emitida → rechazada | ejecutivo | Motivo de lista (familia `crm.cotizaciones.rechazo`) |
    | `vencer` | emitida → vencida | sistema | Tarea diaria cuando pasa `fecha_vencimiento` (v4 parte 7, «vencimiento automático») |
    | `reemplazar` | emitida, rechazada o vencida → reemplazada | sistema | Al emitirse la versión siguiente |
    | `anular` | borrador, por_aprobar o aprobada → anulada | ejecutivo | Motivo; sin efectos que revertir (nada salió al cliente) |
    | `anular_aceptacion` | aceptada → emitida | Gerencia Comercial | Motivo; revierte la marca de aceptada; impedida si la oportunidad ya está ganada (primero se anula la ganada) |
  - Una emitida no se anula ni se edita (salió al cliente): se reemplaza con otra versión, o el cliente la rechaza, o
    vence. *(Regla del v4: «una cotización enviada no se edita».)*
  - **Nueva versión**: acción desde la última versión si está emitida, rechazada o vencida; copia encabezado,
    condiciones y líneas a un borrador `numero_version + 1` con motivo de cambio (familia
    `crm.cotizaciones.nueva-version`, más comentario: el DBML manda el motivo a catálogo, plan 1.5 D9). Solo una
    versión en curso (borrador, por aprobar o aprobada) por número, garantizado en la base.

- **D6 · Líneas.** *v4, diseño y DBML; ajustes, propuesta del agente.* Tres clases de línea, por una columna `origen`
  (`catalogo`, `libre`, `presupuesto`):
  - **Del catálogo** (C4): `producto_id`, la descripción completa y la unidad salen de `CatalogoService::resumen` al
    agregarla (foto: la línea guarda su texto; el catálogo no se reescribe). Solo activos (`buscar`).
  - **Libre «por catalogar»** (diseño: «Instalación y puesta en marcha · por catalogar»): descripción y unidad escritas.
  - **Del presupuesto** (D8): una línea con el presupuesto enlazado.
  - Cada línea: cantidad (3 decimales), precio unitario sin IGV (4 decimales), descuento % (0 a 100), costo unitario
    (4 decimales, `NOT NULL`, escrito por Preventa: el catálogo no trae costo), subtotal = cantidad × precio ×
    (1 − descuento), redondeado a 2; margen de la línea = (subtotal − costo × cantidad) / subtotal; orden.
  - `es_opcional` (DBML) **no se construye**: queda en «Preparado para lo diferido» (pregunta P7).

- **D7 · Solo una aceptada.** Una sola versión aceptada por número y una sola cotización aceptada por oportunidad (la
  que viaja en `OportunidadGanada`), garantizadas en la base con una columna `es_aceptada` y dos índices únicos
  parciales, como el «una sola aprobada por proyecto» del presupuesto. La pone el efecto de `aceptar` y la quita el de
  `anular_aceptacion`. *(v4 «solo una versión puede estar aceptada»; por oportunidad, propuesta del agente.)*

- **D8 · El presupuesto con APU (C8): enlazado, no copiado.** *v4 parte 2 y 3, C8; detalle, propuesta del agente.*
  - Una versión enlaza a lo más un presupuesto (`presupuesto_id`, FK nula con índice): debe ser de la misma
    oportunidad (`ResumenPresupuesto::oportunidadId`), en la misma moneda, y estar en borrador u ofertado.
  - Su línea toma de `resumen()`: **precio** = costo directo + gastos generales + utilidad (el subtotal sin IGV del
    presupuesto) y **costo** = costo directo + gastos generales (la utilidad es el margen). Mientras la versión es
    borrador se relee; al emitir queda como foto. Admite descuento como cualquier línea. Pregunta P4 (¿los gastos
    generales son costo?).
  - **Ampliar C8** (cambio de costura, en el ADR 0020; dueño área 1): `PresupuestosService::deOportunidad(oportunidadId)`
    (los presupuestos vigentes de esa oportunidad, para elegir) y `PresupuestosService::ofertar(presupuestoId, User)`
    (dispara la transición «Ofertar» ya registrada; **idempotente**: si ya está ofertado, por ejemplo al emitir la
    versión 2 de la cotización, no falla ni cambia nada; si no está completo, falla con su mensaje y la emisión entera
    se deshace). Con su versión falsa y las mismas pruebas del contrato (regla 3). *(P3, desarrollador.)*
  - **Si la nueva versión de la cotización necesita otros costos**, se hace una nueva versión del presupuesto y la nueva
    versión de la cotización la enlaza (misma oportunidad, borrador u ofertado); la anterior queda con su foto. Lo
    cubre una prueba de `CotizacionVersionesTest`. *(P3, desarrollador.)* Para eso, **la versión nueva del presupuesto
    sale también de uno ofertado** (antes, solo de uno aprobado: plan 2.9, D6); al ofertarse o traerse al proyecto la
    nueva, la ofertada anterior pasa sola a reemplazada (efecto `erp.presupuestos.reemplazar-ofertadas`). `ofertar` de
    C8 usa una transición del sistema, `ofertar_con_cotizacion`, a nombre de quien emite: el ejecutivo no necesita el
    permiso de ofertar a mano. *(Desarrollador, 2026-11-12.)*
  - **Ofertar no se revierte**: una emitida no se anula (D5); si el cliente rechaza, el presupuesto queda ofertado
    (fue una oferta enviada) y la siguiente versión lo puede seguir usando o enlazar una versión nueva.

- **D9 · Moneda, tipo de cambio e IGV.** *v4 y mapa (regla de etapa 2); detalle, propuesta del agente.*
  - Moneda PEN o USD (catálogo `monedas`); por defecto, la de la oportunidad. Todas las líneas en la moneda de la
    cotización.
  - Tipo de cambio venta del día (`TipoCambioService::del`) congelado al emitir, aunque sea en soles (el diseño lo
    muestra): sirve para pasar a soles en el embudo y en las reglas de aprobación («las reglas van en soles»).
  - IGV: mientras es borrador se lee `tributario.igv_porcentaje`; al emitir se guarda en `igv_porcentaje` de la
    versión y nunca se vuelve a leer (mapa, «Reglas entre etapas»). IGV = subtotal × tasa, redondeado a 2; total =
    subtotal + IGV. Sin líneas exoneradas o inafectas en esta tarea (diferido).

- **D10 · Validez.** `dias_validez` (por defecto el parámetro `crm.cotizaciones.dias_validez`, 15 según el diseño) y
  `fecha_vencimiento` = fecha de emisión + días calendario, fijadas al emitir. La tarea diaria pasa a `vencida` las
  emitidas vencidas. Filtro «Por vencer»: las que vencen en los próximos N días (parámetro, 3 por defecto). Una vencida
  no se acepta: se hace otra versión. *(v4 parte 7 y diseño; parámetros, propuesta del agente.)*

- **D11 · Requisitos en `RequisitosDeEtapa` (plan 2.21, D9 y D12.6).**
  - `UnaCotizacionEmitida` (desde `propuesta_enviada`): la oportunidad tiene alguna versión que llegó a emitirse
    (`emitida_at` no nula), aunque luego venciera o la rechazaran. Escenario del v4: «se arrastra de calificada a
    propuesta enviada sin cotización emitida → el modal no permite avanzar; muestra el requisito faltante».
  - `UnaCotizacionAceptada` (al entrar en `ganada`): tiene la aceptada de D7.
  - **Apagables como dato**: cada uno lee un parámetro booleano (`crm.cotizaciones.exigir_emitida_para_propuesta`,
    `crm.cotizaciones.exigir_aceptada_para_ganar`, los dos en «sí»), porque «Embudo comercial» sigue abierto («¿qué
    exige ganar?»). Si Gerencia Comercial responde distinto, se cambia el parámetro, no el código. *(Parámetros,
    propuesta del agente; pregunta P2.)*
  - Al activarse, las abiertas que no cumplan se quedan en su etapa (D9.4 de la 2.21: nunca hacia atrás).
  - **Propuesta provisional del desarrollador para «Embudo comercial»** (P2, 2026-11-12; Gerencia no lo tiene definido,
    se registra en `pendientes.md`): (1) ganar exige una cotización aceptada con su documento de respaldo (O/C, O/S,
    buena pro, contrato o correo); en licitaciones, la oferta económica también es una cotización y la buena pro la
    acepta; (2) se gana desde «Propuesta enviada» o desde «Negociación»; (3) Propuesta enviada → Negociación no exige
    nada propio, solo los acumulados y la próxima acción; (4) perder exige motivo y, opcionalmente, el competidor
    ganador; (5) retroceder solo un paso, con motivo; (6) probabilidades 10/25/50/75 %; «Propuesta enviada» con un
    tiempo máximo igual a la validez de la cotización (15 días). **En esta tarea:** lo que es dato se carga como dato
    en el paso 6 (la transición `ganar` también desde `propuesta_enviada`, los días máximos, lo que falte de 3 a 6
    tras revisar lo que ya hay); el respaldo de la aceptación entra en `aceptar` (D5). Lo que pida código y no quepa,
    con su punto de extensión en «Preparado para lo diferido».

- **D12 · Ganar toma la cotización aceptada (C3).** *ADR 0020 C3 y plan 2.21 D3/D12.6; mecanismo, propuesta del
  agente.*
  - Oportunidades no lee tablas de Cotizaciones: define un puerto `CotizacionDeOportunidad` (en
    `Crm/Oportunidades/Services`, como `ContratosDeOportunidad`) con `aceptada(oportunidadId): ?CotizacionAceptada`
    (id, número y versión, total con IGV, moneda, plazo, forma de pago, garantía, presupuesto enlazado), con una
    versión de fábrica «sin módulo» que devuelve `null`. Cotizaciones registra la real.
  - `GanadaAvisaAlErp` llena con ella `cotizacionId`, `plazo`, `formaPago`, `garantia` y `presupuestoId`, y el monto y la
    moneda del evento salen de la aceptada (contrato = total con IGV, como el Traspaso del diseño: S/ 60 939.92, y la
    frontera: S/ 24 715.10). La forma del evento no cambia.
  - En la acción `ganar`, con aceptada, el monto final se propone con el total de la aceptada y **no se edita** («nadie
    vuelve a escribir lo que ya se escribió», v4 parte 3). Pregunta P5.
  - `CotizacionesService::resumen(cotizacionId)` para Contratos (C3), con líneas y condiciones.

- **D13 · Impedimentos (plan 2.21, D12.3).** Con una cotización aceptada, la oportunidad no se puede `anular` (por
  `RevisionAntesDeAnular` del núcleo, clave `crm.oportunidades.oportunidad`) ni `perder` (por
  `ImpedimentosDeOportunidad`): «Tiene la cotización COT-… aceptada; anula la aceptación primero». `anular_ganada` no
  se impide (la aceptada es parte de lo ganado y sigue siendo historia). *(Plan 2.21; alcance en perder, propuesta del
  agente.)*

- **D14 · Margen y aprobación bajo el mínimo.** *v4 parte 2 y 7; mecanismo, propuesta del agente.*
  - Margen de la versión = (subtotal − costo total) / subtotal, con 2 decimales (en pantalla, 1). Objetivo y mínimo:
    parámetros `crm.cotizaciones.margen_objetivo` (25) y `crm.cotizaciones.margen_minimo` (22), sensibles (cambian
    dinero). Se guardan en la versión al pedir aprobación o emitir, para que la solicitud muestre con qué se midió.
  - El núcleo suma `CondicionRegla::MargenBajoElMinimo` («Solo si el margen queda bajo el mínimo», sin
    autoaprobación); `aprobar` declara `montoParaAprobacion()` (total en soles con el TC del día) y la regla de
    Configuración → Aprobaciones dice quién firma (Gerencia Comercial; en pruebas, un rol inventado). Rechazar exige
    comentario (observar del núcleo). Escenario del v4: D17.1.
  - Si después de aprobada se edita una línea, vuelve a borrador (la aprobación era de esos números): en `aprobada` la
    versión no se edita; para cambiar algo, «Volver a borrador» (transición del sistema al observar, la misma). *(Detalle,
    propuesta.)*

- **D15 · Costos y márgenes son dato sensible.** Columnas de costo y margen solo para quien tiene el grupo sensible
  `Costos` del núcleo (v4 parte 7, «no ven costos ni márgenes: esas columnas no aparecen»); el PDF nunca los muestra.
  *(v4; mecanismo existente.)*

- **D16 · Condiciones comerciales.** En la versión: plazo de entrega en días calendario, forma de pago (catálogo
  `formas_pago` del núcleo) con detalle escrito («50 % adelanto»), garantía en meses, lugar de entrega (texto). Viajan
  al PDF y a `OportunidadGanada` como texto («45 días calendario», «50 % adelanto», «24 meses»). Las plantillas
  `condiciones_comerciales` del DBML se difieren. *(v4 «se escriben una sola vez»; campos, propuesta del agente.)*

- **D17 · Escenarios del v4 como pruebas, con números exactos (regla 8).**
  1. **CRM · aprobación** (parte 8): «Se solicita aprobación de COT-2026-0184 v2 con margen 19.4 % y mínimo 22 % → va a
     la bandeja de Gerencia Comercial; no se puede enviar al cliente sin aprobación registrada». Líneas del diseño:
     cámara 24 × 1 180.00 − 5 % = 26 904.00; switch 3 × 4 250.00 − 12 % = 11 220.00; NVR 1 × 6 900.00 − 20 % = 5 520.00;
     instalación 1 × 8 000.00 = 8 000.00. **Subtotal 51 644.00; descuentos 4 326.00; IGV 9 295.92; total 60 939.92.** El
     v4 no da costos: la prueba usa costos inventados que reproducen los márgenes del diseño: 807.12, 3 104.20,
     5 188.80 y 7 752.78 por unidad → márgenes de línea 28.00 %, 17.00 %, 6.00 % y 3.09 %; costo total 41 625.06;
     **margen 19.40 %**. Comprueba: `emitir` desde borrador falla; `solicitar_aprobacion` abre la ronda con la regla de
     margen; tras la firma, `emitir` pasa.
     - Nota: el texto del v4 (parte 2) dice «dscto −4 856»; el diseño del editor dice −4 326.00, que es lo que dan las
       líneas. Se usa 4 326.00 y se anota la errata.
  2. **CRM · requisito** (parte 8): sin cotización emitida, `enviar_propuesta` falla y lista «Una cotización emitida»;
     con una emitida, pasa.
  3. **Frontera, la mitad del CRM** (parte 8): COT-2026-0190 v1 enlazada a un presupuesto (versión falsa de C8) con
     costo directo 17 750.00, gastos generales 1 775.00 y utilidad 1 420.00 (el PRE-2026-031 del flujo 1) → línea de
     20 945.00, **IGV 3 770.10, total 24 715.10**; aceptada; al ganar, `OportunidadGanada` lleva esa cotización, monto
     24 715.10, PEN, sus condiciones y el presupuesto enlazado. (El contrato CT-2026-018 lo prueba el área 3.)

- **D18 · Pendientes nuevos.** A `pendientes.md`, fila «Cotizaciones» (Gerencia Comercial; no bloquea, son datos):
  margen objetivo y mínimo (¿uno solo o por línea de solución o tipo de cliente?), días de validez, motivos de nueva
  versión y de rechazo, y la errata del descuento del v4.

- **D19 · Pantallas (en la 2.22b).** *Diseño del 13 oct y `interfaz.md`; propuesta del agente.*
  - **Cotizaciones** como módulo del área CRM con portada: cajas por estado (Por aprobar, Emitidas, Por vencer,
    Aceptadas, Vencidas), buscador y la lista en carpetas Cuenta › Oportunidad › Cotización › Versión del diseño, con
    «Solo mías».
  - **Ficha / editor** de la versión: encabezado (número, versión, estado con color y nombre, moneda, TC, validez),
    líneas con agregar desde el catálogo, libre o desde el presupuesto, totales y margen en vivo, condiciones, botones
    de las transiciones en paneles (`AccionFilament::desde`), versiones y «Comparar».
  - **Comparador** de dos versiones, línea por línea (total, margen, líneas nuevas, quitadas y cambiadas).
  - **Pestaña «Cotizaciones» en la oportunidad** (`RegistroPestanasOportunidad`, clave `cotizaciones`: reemplaza la
    capa en espera del despiece) con «Nueva cotización de esta oportunidad»; **pestaña en la cuenta**
    (`RegistroPestanasCuenta`).
  - **Bandeja de aprobaciones**: el núcleo no tiene pantalla de `porFirmar`. Se propone una genérica del núcleo, «Mis
    aprobaciones», donde cada documento aporta su resumen por un registro (la cotización: cliente, total, margen,
    puntos contra el mínimo, quién pide); la usarán también requerimientos, órdenes y gastos. Pregunta P6.

- **D20 · Documento impreso (en la 2.22c).** *Mapa («Documentos impresos (de la 1.15)»); librería, propuesta del agente.*
  La cotización elige la librería de PDF del sistema. Recomendada: **dompdf** (`barryvdh/laravel-dompdf`), PHP puro, sin
  Chrome en el servidor; la alternativa (Browsershot) da mejor CSS pero exige Chrome y Node en producción. Datos de
  Techquk por `EmpresaService::datos()` y `nucleo::empresa.encabezado`; cuenta bancaria por `PartiesService`. Vista
  previa en la ficha; al emitir, el PDF se guarda como adjunto de la versión (`adjunto_pdf_id`): lo que se envió queda
  igual para siempre. Pregunta P8.

- **D21 · Permisos** (prefijo `crm.cotizaciones.`; todos a la lista exacta de `CatalogosSeederTest`). Módulo:
  `crm.cotizaciones.ver`. Acciones: `crear`, `editar` (encabezado, líneas y condiciones del borrador), `nueva-version`,
  y una por transición de usuario (`solicitar_aprobacion`, `emitir`, `aceptar`, `rechazar`, `anular`,
  `anular_aceptacion`). `aprobar` no lleva permiso (lo deciden las reglas, convención). Sobre lo propio como
  Oportunidades (`SobreLoPropio`: el ejecutivo de la oportunidad; Gerencia Comercial sobre todas).

- **D22 · Fuera de esta tarea.** Licitaciones (CRM-04: la propuesta de una licitación usará esta cotización), la IA de
  términos de referencia → líneas (v4: «Preventa confirma»; proceso de IA aparte), el rango de precio sugerido por el
  histórico de la entidad, líneas opcionales, plantillas de condiciones, IGV por línea (exonerado o inafecto), envío
  por correo (el servicio de correo saliente está pendiente) y el contrato (área 3).

### Tamaño y partición

La tarea es grande (ADR 0020 le da 5 puntos, como Oportunidades, que llevó 2.21 + 2.21b + 2.21c) y cruza una costura
(C8). Se propone **partirla en tres tareas de la misma entrega**, que se fusionan juntas:

- **2.22a · La cotización como documento** (sin pantallas, como la 2.9): núcleo (número compartido, condición de
  margen), C8 ampliada, tablas, cálculo, estados, aprobación, vencimiento, requisitos, impedimentos, ganar con la
  aceptada y los tres escenarios. Pasos 1 a 7.
- **2.22b · Pantallas** (D19). Pasos 8 a 11.
- **2.22c · PDF** (D20). Pasos 12 y 13.

## Preguntas para el desarrollador

Respondidas el 2026-11-12 (ver «Respuestas del desarrollador», al final de esta sección). Cada una con la opción que
recomendaba el agente.

- **P1 · ¿Toda cotización cuelga de una oportunidad?** Recomendado: **sí, obligatoria** (la postventa abrirá una
  oportunidad). Alternativa: permitir cotizaciones sueltas de una cuenta.
- **P2 · Ganar exige cotización aceptada, aunque «Embudo comercial» siga sin respuesta de Gerencia.** Recomendado:
  **sí, como dice el v4 y la D9 de la 2.21**, con un parámetro para apagarlo sin código (y otro para «emitida para
  enviar la propuesta»).
- **P3 · Ampliar la C8 en el ADR 0020** con `deOportunidad(oportunidadId)` y `ofertar(presupuestoId)` (la cotización
  ofertará el presupuesto al emitirse; hoy es a mano). Recomendado: **sí**.
- **P4 · Línea desde el presupuesto: ¿los gastos generales son costo?** Recomendado: **precio = costo directo + gastos
  generales + utilidad; costo = costo directo + gastos generales** (el margen de esa línea es la utilidad).
- **P5 · Al ganar con cotización aceptada, ¿el monto final se puede cambiar?** Recomendado: **no; es el total con IGV de
  la aceptada** (si el cliente negoció otro precio, se hace otra versión y se acepta esa).
- **P6 · Bandeja de aprobaciones:** recomendado: **una genérica del núcleo, «Mis aprobaciones»**, que sirve a todos los
  documentos; alternativa: una solo de cotizaciones (más rápida, se rehace después).
- **P7 · Líneas opcionales** («ítem opcional» que no suma al total): recomendado: **no ahora**; se suman si Comercial las
  pide.
- **P8 · Librería de PDF:** recomendado: **dompdf** (sin Chrome en el servidor). Alternativa: Browsershot.
- **P9 · Una tabla por versión en vez de cabecera + versiones del DBML (D2), y llevar el número compartido al núcleo.**
  Recomendado: **sí**, como el presupuesto.
- **P10 · Partir en 2.22a / 2.22b / 2.22c en la misma entrega.** Recomendado: **sí**.

### Respuestas del desarrollador (2026-11-12)

- **P1:** sí, obligatoria; sin `party_id`; «+ Nueva oportunidad» mínima desde el formulario (D4). **Revisada el
  2026-11-12:** sin «+ Nueva oportunidad»; Cotizaciones solo enlaza oportunidades que ya existen; se crean en
  Oportunidades, donde se coordinó (D4).
- **P2:** sí, con los dos parámetros en «sí»; su propuesta provisional del embudo va a `pendientes.md` (D11) y el
  respaldo de la aceptación entra en `aceptar` (D5).
- **P3:** sí, ampliar C8; `ofertar()` idempotente; otros costos = nueva versión del presupuesto (D8).
- **P4:** sí, los gastos generales son costo. **P5:** no, el monto es el total de la aceptada. **P6:** bandeja genérica
  del núcleo; **ampliada el 2026-11-12:** se firma ahí mismo (Aprobar y Observar con motivo, en el panel deslizante, y
  enlace al documento), con dos condiciones, cada una con su prueba: (1) la firma lleva la versión del documento tomada
  al abrir el panel y, si cambió, se rechaza con aviso; (2) el resumen respeta los campos sensibles: margen y puntos
  contra el mínimo solo con permiso, filtrados en el servidor. **P7:** no ahora. **P8:** dompdf; **ampliada el 2026-11-14:** dompdf necesita la extensión GD para el logo y no estaba en la imagen de producción: GD obligatoria (Dockerfile con JPG y WEBP, `ext-gd` en `composer.json`, `requisitos-servidor.md`). **P10:** sí, tres tareas.
- **P9:** sí, una fila por versión. Además, el patrón de pantalla «Documentos con versiones: árbol de carpetas» (la
  cotización y el presupuesto; la versión más reciente primero; la carpeta de la oportunidad muestra juntos
  cotizaciones y presupuesto) quedó en `docs/convenciones/interfaz.md` por la rama
  `arreglo/patron-carpetas-versiones`; las pantallas de la 2.22b lo siguen.
- **Portada (2026-11-12):** sigue la convención, no las «cajas por estado» de D19: pestañas de texto con su cantidad,
  el aviso «Por vencer» con «Ver cuáles», la barra de carpetas, «Solo mías» y la tabla, como el presupuesto.
- **Concurrencia (2026-11-12):** sí, en `tests/Concurrencia`: aceptar una cotización contra perder y contra anular su
  oportunidad, con dos procesos reales; al final nunca queda una oportunidad cerrada con una cotización aceptada. Molde:
  las de concurrencia del embudo.

## Pasos

Cada paso: sus pruebas (`php artisan test <archivos>`), pint y phpstan; commit y push. Al cerrar cada tarea,
`composer check:tarea`.

**2.22a · La cotización como documento**

- [x] 1. **Núcleo:** número compartido entre versiones en `TransicionesService::crear` y la definición (D2); el
  presupuesto pasa a usarlo sin cambiar su comportamiento (sus pruebas, intactas); convención actualizada.
  `CondicionRegla::MargenBajoElMinimo` (D14). Pruebas: `NumeroCompartidoTest`, las del presupuesto, la de reglas.
- [x] 2. **C8 ampliada** (D8): ADR 0020 con `deOportunidad` y `ofertar`; implementación real y falsa con las mismas
  pruebas del contrato (`ContratoPresupuestosTest`).
- [x] 3. **Esqueleto y tablas** (`/nuevo-modulo`, `/nueva-migracion`): `Crm/Cotizaciones`, `cotizaciones` y
  `cotizacion_lineas` (D2, D6, D7, D9, D10, D16), parámetros (D10, D11, D14), familias de motivos, permisos (D21) en
  `CatalogosSeederTest`; DBML actualizado. Pruebas: migración, seeder, arquitectura.
- [x] 4. **Cálculo puro** (D6, D8, D9, D14): subtotal, descuentos, IGV, total, costo y margen por línea y versión.
  Prueba: `CalculoCotizacionTest` con los números de D17.1 y D17.3.
- [x] 5. **Documento y acciones** (`/nuevo-documento`, `/nueva-accion`): definición de estados (D5), servicio con crear,
  editar, líneas (catálogo por C4, libre, presupuesto por C8), nueva versión, emitir con sus efectos (congelar, ofertar,
  reemplazar la anterior), aceptar, rechazar, anular, anular aceptación; tarea diaria de vencimiento; aprobación con la
  regla de margen. Pruebas: `CotizacionEstadosTest`, `CotizacionVersionesTest`, `VencimientoCotizacionesTest`,
  `AprobacionCotizacionTest` (escenario D17.1).
- [x] 6. **Con Oportunidades** (D11, D12, D13): los dos requisitos con sus parámetros, el puerto
  `CotizacionDeOportunidad` con la versión «sin módulo», `GanadaAvisaAlErp` y `ganar` con la aceptada, impedimentos,
  `CotizacionesService::resumen`. Pruebas: `RequisitosCotizacionTest` (escenario D17.2), `GanarConCotizacionTest`
  (escenario D17.3), `ImpedimentosCotizacionTest`; las de Oportunidades siguen verdes.
- [x] 7. **Cierre de la 2.22a:** pendientes nuevos (D18), revisor de arquitectura, `composer check:tarea`.

**2.22b · Pantallas**

- [x] 8. Bandeja «Mis aprobaciones» del núcleo con su registro de resúmenes (P6): firma ahí mismo con la versión
  tomada al abrir y resumen sin campos sensibles para quien no tiene permiso. Pruebas de pantalla y de las dos
  condiciones.
- [x] 9. Módulo Cotizaciones: portada con pestañas, aviso «Por vencer» y carpetas (convención), ficha/editor con paneles de las transiciones (versión del
  bloqueo optimista capturada al abrir), costos y márgenes solo con el grupo sensible; el selector de oportunidad
  ofrece solo las abiertas que ya existen, sin «+ Nueva oportunidad» (D4). Pruebas de pantalla.
- [x] 10. Comparador de versiones; pestañas en la oportunidad (con la capa del despiece) y en la cuenta; quitar
  `cotizaciones` de `CapasDelDespiece::EN_ESPERA`. Pruebas de los componentes hijos (no por la ficha, AGENTS.md).
- [x] 10b. Ganar desde «Propuesta enviada»: la transición en `DefinicionOportunidad` y la regla del tablero y la faja
  («a Ganadas solo desde Negociación») cambian juntas, con sus pruebas. El respaldo de la aceptación se sube desde la
  ficha como adjunto de la cotización y el servicio comprueba que sea suyo. Prueba de concurrencia: aceptar contra
  perder y contra anular la oportunidad.
- [x] 11. Cierre de la 2.22b: `composer check:tarea`.

**2.22c · PDF**

- [x] 12. Librería (P8, dependencia nueva, revisor), plantilla con el encabezado de la empresa, vista previa y efecto
  de `emitir` que guarda el PDF como adjunto. Prueba: el PDF existe al emitir y no cambia después.
- [x] 13. Cierre de la entrega: revisor de arquitectura, `composer check:tarea`, bitácora y mapa.

## Preparado para lo diferido

- **Licitaciones (CRM-04):** la propuesta de una licitación usará una versión de cotización; el puerto
  `CotizacionDeOportunidad` y `resumen()` sirven igual.
- **Contratos (área 3):** `CotizacionesService::resumen()` (C3) con líneas y condiciones; `OportunidadGanada` ya completo.
- **IA de términos de referencia:** las líneas se agregan por el servicio (`agregarLinea`), así que un proceso de IA
  futuro propondrá líneas que Preventa confirma, sin tocar el documento.
- **Precio sugerido por el histórico de la entidad:** el editor deja el lugar junto al total; lo llena un cálculo
  sobre ganadas y perdidas cuando exista.
- **Líneas opcionales, IGV por línea, plantillas de condiciones:** columnas nuevas sin cambiar el cálculo (el cálculo
  recibe la línea, no la tabla).
- **Envío por correo:** `emitir` deja el PDF guardado; enviarlo es un efecto externo más cuando haya correo saliente.
- **Alerta «cotización por vencer»:** el filtro ya existe; la alerta del núcleo se suma con su configuración.

## Criterio de aceptación

- `CalculoCotizacionTest`: 51 644.00 / 4 326.00 / 9 295.92 / 60 939.92 / margen 19.40 %; y 20 945.00 / 3 770.10 /
  24 715.10.
- `AprobacionCotizacionTest`: bajo el mínimo no se emite sin aprobación registrada; la solicitud va a quien dice la
  regla; rechazar exige comentario.
- `RequisitosCotizacionTest`: sin emitida no se envía la propuesta (mensaje con el faltante); sin aceptada no se gana.
- `GanarConCotizacionTest`: `OportunidadGanada` con COT-2026-0190, 24 715.10, PEN, condiciones y presupuesto.
- `CotizacionVersionesTest`: una emitida no se edita; nueva versión copia y reemplaza al emitirse; una sola aceptada
  (por número y por oportunidad, en la base).
- Pantallas (2.22b): se crea, cotiza, pide aprobación, emite y acepta desde la app; la oportunidad muestra su pestaña.
- PDF (2.22c): la vista previa y el adjunto guardado al emitir.

## Bitácora

- 2026-11-12 — Plan escrito en `entrega/crm-cotizaciones` (desde `origin/main`, con el commit de pendientes del
  embudo). Leídos ADR 0020 (C3, C4, C8, C13), v4 (partes 2, 3, 6, 7, 8), diseño del 13 oct, DBML, planes 2.21 y 2.9 y
  los puntos de extensión del código. La rama `entrega/presupuesto-pantallas` (2.10) no cambia C8. Propuesta de
  partir en 2.22a/b/c. Esperando las respuestas P1 a P10 antes del paso 1.
- 2026-11-12 — Respuestas P1 a P10 anotadas (Decisiones y «Respuestas del desarrollador»). El patrón de carpetas,
  aparte, en `arreglo/patron-carpetas-versiones` (solo Markdown). Empieza el paso 1.
- 2026-11-12 — Paso 1 hecho. `DefinicionDocumento::versionesCompartenNumero()`: `TransicionesService::crear` numera la
  v1 y deja heredar el número a las siguientes (exige que exista la anterior). El presupuesto declara `PRE` y lo usa (su
  servicio ya no numera; sus pruebas, intactas). `DefinicionDocumento::condicionesDeAprobacion()` y
  `CondicionRegla::MargenBajoElMinimo` (migración del CHECK; sin autoaprobación; sin regla para una condición que el
  documento cumple, no se abre la ronda). Convención actualizada. Pruebas: `NumeroCompartidoTest`,
  `CondicionMargenTest`, aprobaciones, numeración, transiciones, presupuesto, exceso y PC-99: en verde.
- 2026-11-12 — Paso 2 hecho. C8 con `deOportunidad()` y `ofertar()` (idempotente; real y falsa con las mismas pruebas
  del contrato); ADR 0020 al día. El presupuesto saca versión nueva también desde ofertado (pregunta aparte al
  desarrollador: sí) y la ofertada anterior se reemplaza al ofertar o traer la nueva. Pruebas del presupuesto y del
  contrato en verde.
- 2026-11-12 — Paso 3 hecho. `Crm/Cotizaciones` con su proveedor (en `bootstrap/providers.php`: desde aquí
  `check:tarea` corre todas las pruebas hasta su próxima verde), `cotizaciones` (una fila por versión) y
  `cotizacion_lineas`, modelos con campos sensibles (costo, margen) y enums `TipoRespaldo` y `OrigenLinea`. Parámetros
  en snake_case, como los pide el núcleo: `crm.cotizacion_dias_validez` (15), `crm.cotizacion_dias_por_vencer` (3),
  `crm.cotizacion_margen_objetivo` (25) y `crm.cotizacion_margen_minimo` (22, los dos sensibles),
  `crm.cotizacion_exigir_emitida_para_propuesta` y `crm.cotizacion_exigir_aceptada_para_ganar` (sí). Lista de motivos
  de versión nueva. DBML al día. Desviaciones del DBML en el encabezado de la migración; «una sola versión en curso por
  número» sale de que la nueva solo sale de la última más el único `numero` + `numero_version` (no de un índice por
  estado). Los permisos de las acciones llegan en el paso 5. Pruebas: `CotizacionesModuloTest`,
  `CotizacionesBaseTest`, arquitectura y la lista de permisos.
- 2026-11-12 — Paso 4 hecho. `CalculoCotizacion` (bcmath, mitad lejos del cero) con `LineaParaCalculo`,
  `LineaCalculada` y `TotalesCotizacion`. `CalculoCotizacionTest`: D17.1 (51 644.00 / 4 326.00 / 9 295.92 / 60 939.92,
  margen 19.40 %, líneas 28.00 / 17.00 / 6.00 / 3.09 %) y D17.3 (20 945.00 / 3 770.10 / 24 715.10, margen 6.78 %).
- 2026-11-12 — Paso 5 hecho. `DefinicionCotizacion` (estados y transiciones de D5; «observar» devuelve a borrador con
  `devolver`, del sistema; `reabrir` lleva una aprobada a borrador), condiciones (dueño de la oportunidad, oportunidad
  abierta, no ganada, con líneas, margen), efectos (congelar al emitir, ofertar el presupuesto por C8, reemplazar la
  versión anterior, marcar y quitar la aceptada), `CotizacionesService` (crear, editar, líneas del catálogo y libres,
  enlazar el presupuesto, versión nueva, solicitar aprobación, emitir, aceptar con respaldo, vencer), diez acciones
  registradas y `crm:vencer-cotizaciones` (cada día a las 00:10). Ajustes al plan, sobre la marcha:
  - **Permisos (D21):** `crm.cotizaciones.crear`, `.editar` (encabezado y líneas del borrador), `.nueva-version`,
    `.todas` (sobre las oportunidades de cualquier ejecutivo; sin él, solo las propias) y uno por transición de persona
    (`crm.cotizaciones.cotizacion.{solicitar_aprobacion, reabrir, emitir, aceptar, rechazar, anular, anular_aceptacion}`).
  - Los totales y el margen no son campos derivados (los recalcula el servicio en borrador); sí lo congelado al emitir y
    la aceptación, que pone su efecto con lo que le deja el servicio (`PedidoAceptacion`, como `PedidoTraerAlProyecto`).
  - Si la tasa del IGV cambió entre el cálculo y la emisión, no se emite (vuelve a borrador para recalcular).
  - En el presupuesto la utilidad va sobre el costo directo (plan 2.9): la línea de un CD de 200.00 da 236.00.
  Pruebas: `AprobacionCotizacionTest` (D17.1 con sus números), `CotizacionEstadosTest`, `CotizacionVersionesTest`
  (incluido P3: otros costos = versión nueva del presupuesto), `VencimientoCotizacionesTest`, arquitectura y permisos.
- 2026-11-12 — Paso 6 hecho. Puerto `CotizacionDeOportunidad` en Oportunidades (de fábrica `CotizacionesSinModulo`;
  la real, `CotizacionDeOportunidadEnBase`); `GanadaAvisaAlErp` llena cotización, monto con IGV, moneda, plazo, forma de
  pago, garantía y presupuesto; `ganar` toma el total de la aceptada (monto opcional; otro monto se rechaza, P5).
  Requisitos `UnaCotizacionEmitida` y `UnaCotizacionAceptada`, apagables: Oportunidades suma `RequisitoApagable` (un
  requisito apagado no se exige ni sale en «Avanzar»). Impedimentos: con una aceptada no se anula (revisión del núcleo,
  salvo la ganada) ni se pierde. `CotizacionesService::resumen()` (C3), sin costo ni margen. «Propuesta enviada» nace
  con 15 días máximos (P2). Las pruebas del embudo apagan los dos requisitos (`OportunidadesDePrueba::sinExigirCotizacion`):
  prueban sus reglas; los de la cotización, en su módulo. Pruebas: `RequisitosCotizacionTest` (D17.2),
  `GanarConCotizacionTest` (D17.3: COT-2026-0190, 24 715.10, PEN, condiciones y presupuesto), `ImpedimentosCotizacionTest`,
  todo `tests/Modules/Crm` y arquitectura en verde.
  Pasan a la 2.22b, porque tocan pantallas: **ganar desde «Propuesta enviada»** (la transición es dato, pero el
  tablero y la faja traen la regla «a Ganadas solo desde Negociación» en su código: se cambian juntos) y **«+ Nueva
  oportunidad» desde el formulario de la cotización** (la oportunidad pide línea de solución y fecha de cierre, que da
  el formulario; el servicio solo encadena `OportunidadesService::crear` y `CotizacionesService::crear`).
- 2026-11-12 — Revisión de arquitectura de la 2.22a: 4 bloqueantes, corregidos. (1) Las líneas usan
  `ParteDeDocumento`: en una versión no editable no se cambian ni por el modelo. (2) El CHECK de la aceptada exige
  `respaldo_referencia IS NOT NULL` (con nulo, `btrim` daba NULL y pasaba). (3) «Volver a borrador» pide motivo (lista
  `crm.cotizaciones.cotizacion.reabrir`), con su prueba. **Desvío de D14:** es una transición de persona (`reabrir`),
  no la del sistema al observar (`devolver`), porque la pide el ejecutivo. (4) Crear, aceptar y anular la aceptación
  bloquean antes la oportunidad (`OportunidadesService::bloquearParaDependiente`, C13); anular la aceptación pasa a
  acción propia (`AnularAceptacionCotizacion`) para bloquear en ese orden. Observaciones corregidas: desde aprobada no se
  emite si el presupuesto enlazado cambió desde la firma; la versión nueva se crea aunque su presupuesto ya no se pueda
  enlazar (se pide enlazar el vigente al emitir); falta de la unidad «GLB» con mensaje claro; el comando cuenta las que
  de verdad venció; ADR 0020 (C8) dice qué `presupuestoId` llega en `OportunidadGanada`.
  Quedan anotados, sin cambio:
  - **Desvío de D5 (respaldo):** se construyó como lista fija (`TipoRespaldo`, con CHECK), no como lista editable de
    motivos: son tipos de documento que el sistema reconoce (la buena pro, en licitaciones). Si Gerencia quiere
    editarlos, se pasa a catálogo.
  - **Desvío de D5 («una sola en curso, en la base»):** lo garantizan el servicio (la nueva sale solo de la última, con
    las versiones bloqueadas) y el único `numero` + `numero_version`; un índice por estado no se puede (el estado es una
    llave).
  - Para la 2.22b: el adjunto del respaldo se sube desde la ficha y queda colgado de la cotización; el servicio
    comprobará que sea suyo (hoy solo la llave). Una prueba de concurrencia de aceptar contra perder o ganar
    (`tests/Concurrencia`) se suma con las pantallas, si el desarrollador la quiere.
  - Emitir pide el TC venta del dólar del día aunque sea en soles (D9); si la sincronización con SUNAT falla, se
    registra a mano en Configuración.
- 2026-11-12 — **2.22a cerrada.** `composer check:tarea` en verde (todas las pruebas, por el proveedor nuevo en
  `bootstrap/`: 3078). La primera corrida dio 2 fallas, corregidas: dos columnas que «suenan sensibles» declaradas como
  no sensibles (`cotizaciones.cuenta_id`, `cotizacion_lineas.precio_unitario`) y la prueba de concurrencia de dos
  ganadas, que apaga los requisitos de la cotización como las demás del embudo. Sigue la 2.22b (pantallas), paso 8.
- 2026-11-12 — **D4 revisada por el desarrollador:** Cotizaciones solo enlaza oportunidades que ya existen; las
  oportunidades se crean en Oportunidades, donde se coordinó. Sale de la 2.22b «+ Nueva oportunidad» desde el
  formulario de la cotización (pasado desde la 2.22a): la cotización nace sin líneas (monto 0) y `validarMonto` de
  Oportunidades exige un monto positivo. La regla de Oportunidades no cambia y no se construyó código para ese botón.
- 2026-11-12 — Respuestas para la 2.22b: portada según la convención (pestañas, no cajas); la bandeja «Mis
  aprobaciones» firma ahí mismo con la versión tomada al abrir y sin campos sensibles sin permiso (P6); se suma la
  prueba de concurrencia de aceptar contra perder y anular. Lo que pasó desde la 2.22a (ganar desde «Propuesta
  enviada», el respaldo desde la ficha) va en el paso 10b. Se trajo `main` (pantallas del presupuesto, #93) con
  *merge*; el conflicto de `CatalogosSeederTest` se resolvió juntando las dos listas.
- 2026-11-12 — Paso 8 hecho. Núcleo: `ResumenParaAprobar` (datos y enlace de cada documento) con `DatoParaAprobar`
  (etiqueta, valor y grupo sensible opcional) y `RegistroResumenesAprobacion`; `AprobacionesService::bandeja()` arma
  `PendienteDeFirma` desde `porFirmar` y **quita en el servidor** los datos de un grupo sensible sin permiso. `firmar` y
  `observar` (y sus acciones, campo de control `version`, opcional sin interfaz) reciben la versión leída: si el
  documento cambió, se rechaza (`AprobacionRechazada::cambioDesdeQueLoAbriste`) sin tocar la ronda. Pantalla
  `MisAprobaciones` (`/mis-aprobaciones`): tabla con «Aprobar» y «Observar» en panel deslizante con el resumen y la
  versión tomada al abrir, y «Abrir». **Desvío de la recomendación aceptada:** no lleva caja propia en la barra de
  módulos, porque `interfaz.md` deja «Mien» como única caja del área Personal y dice que Mien muestra «sus
  aprobaciones»: se abre desde la tarjeta «Mis aprobaciones» de Mien (para todos, con la cantidad). Cotizaciones
  registra su resumen: cliente, oportunidad, total y, con «Márgenes», margen y puntos contra el mínimo; el enlace llega
  con la ficha (paso 9). Prueba: `MisAprobacionesCotizacionTest` (7: filtro sensible en servicio y panel, firmar,
  versión cambiada por pantalla y sin ella, observar, tarjeta); aprobaciones del núcleo, Mien y arquitectura en verde.
- 2026-11-14 — Paso 9 hecho. Caja «Cotizaciones» (CRM, `heroicon-o-document-chart-bar`) y `CotizacionResource`.
  Portada según la convención: pestañas (Todas, En curso, Por aprobar, Emitidas, Aceptadas, Vencidas, Cerradas) con su
  cantidad, aviso «Por vencer» (parámetro `cotizacion_dias_por_vencer`) con «Ver cuáles», barra de carpetas Cuenta ›
  Oportunidad › Cotización › Versión (la más nueva primero; en la oportunidad, sus presupuestos por C8; `?carpeta=`),
  «Solo mías» (las oportunidades del ejecutivo) y últimos cambios (`PortadaCotizaciones`). «Nueva cotización» en panel,
  con el selector de oportunidades abiertas que ya existen (D4). Ficha: título, subtítulo (estado, moneda, TC, validez,
  oportunidad), totales con costo y margen solo con su permiso (objetivo y mínimo del parámetro mientras no se fijan),
  condiciones, pestañas «Líneas» (`LineasDeCotizacion`: del catálogo por C4, libre, desde el presupuesto por C8; editar
  sin «Costos» conserva el costo sin mostrarlo) e «Historial» (`DescribibleEnBitacora` en los dos modelos). Botones de
  las transiciones con la versión leída al abrir; «Aceptar» aún sin el archivo (paso 10b). Oportunidades suma
  `resumenesDe` e `idsDelEjecutivo` (C13). Pruebas: `PortadaCotizacionesTest` (6), `FichaCotizacionTest` (6); todo
  `tests/Modules/Crm/Cotizaciones` (66), Oportunidades y arquitectura en verde.
- 2026-11-14 — Paso 10 hecho. `ComparadorVersiones` (total y margen de cada versión; líneas nuevas, quitadas y
  cambiadas; una línea es la misma por producto o, si es libre, por descripción), en la pestaña «Versiones» de la ficha
  (la más nueva primero, «Comparar» en panel) y en el botón «Comparar con la versión N−1»; margen y costo solo con su
  permiso. Pestañas «Cotizaciones» en la oportunidad (también capa `cotizaciones` del despiece, con «Nueva cotización de
  esta oportunidad» si está abierta) y en la cuenta (`TablaDeCotizaciones`). `cotizaciones` sale de
  `CapasDelDespiece::EN_ESPERA`; una clave del orden sin texto de espera ya no deja capa vacía. `FajaDespieceTest` usa la
  pestaña real. Pruebas: `VersionesYPestanasCotizacionTest` (4); todo `tests/Modules/Crm` (401) y arquitectura en verde.
- 2026-11-14 — Paso 10b hecho. **Ganar desde «Propuesta enviada» o «Negociación»**: el origen de `ganar` en
  `DefinicionOportunidad` (provisional, pendiente «Embudo comercial») y `MovimientoDeOportunidad::ganaDesde()` lo lee para
  el tablero, la faja y el botón «Ganar» de la ficha (ya no hay «solo Negociación» escrito en el código);
  `TableroRuedaTest` actualizada. **Respaldo de la aceptación**: la cotización es `ConAdjuntos` (ve quien ve
  cotizaciones; adjunta quien puede sobre su oportunidad); «Aceptar» recibe el archivo (`respaldo_archivo`, lo adjunta
  `AdjuntosService` dentro de la transacción) o el id de uno ya adjunto, y el servicio rechaza uno de otra cotización o los
  dos a la vez. La subida por la pantalla no se prueba de punta a punta: Livewire guarda el archivo de prueba en un
  disco falso, fuera de las carpetas que acepta `AdjuntosService` (en producción, `livewire-tmp`); se prueba que el
  campo esté y el servicio con un archivo real. **Concurrencia** (`tests/Concurrencia`): aceptar contra perder y contra
  anular la oportunidad, con dos procesos; nunca queda cerrada con una aceptada (las dos pasan). La serie COT sigue desde
  las que dejaron otras pruebas, como OP. Pruebas: `GanarDesdePropuestaYRespaldoTest` (3); CRM y arquitectura (520) y
  `ConcurrenciaTest` (15) en verde.
- 2026-11-14 — Paso 11 hecho: **2.22b cerrada**. `composer check:tarea` en verde: pint, phpstan (0 errores),
  migraciones (72), y 1759 pruebas de 128 de 233 archivos más `tests/Arch` y `tests/Feature` (106 archivos cambiados
  desde la última corrida verde). Sigue la 2.22c (PDF, pasos 12 y 13).
- 2026-11-14 — Paso 12 hecho. **dompdf** (`barryvdh/laravel-dompdf` 3.1; sus dependencias son nuevas, no sube otros
  paquetes). `PdfCotizacion` arma el PDF desde el resumen de C3 (sin costo ni margen: D15 por construcción) con el
  encabezado del núcleo, el cliente y la atención, las líneas, los totales, las condiciones y la cuenta bancaria de
  Techquk en la moneda de la cotización (`PartiesService::cuentaPrincipal`); `html()` aparte para probar lo que dice.
  Fuente recortada a las letras usadas (de ~850 KB a ~25 KB) y sin recursos remotos. **Efecto `guardar-pdf-al-emitir`**,
  el último de `emitir`: lo guarda como adjunto de la versión con `AdjuntosService` y lo deja en `adjunto_pdf_id`; la
  emitida no es editable, así que no se quita, y no se revierte (la emitida no se anula). Sin la ficha de Techquk
  elegida se emite igual, sin encabezado (decisión del agente: no bloquear la emisión por la configuración inicial).
  **Ficha:** «Vista previa del PDF» en borrador (dibujado al vuelo, marcado como borrador, sin descarga) y «PDF» cuando
  está emitida (el guardado, con «Descargar» por la puerta de adjuntos), en el panel deslizante con el visor del
  navegador. **GD** (P8 ampliada): Dockerfile, `ext-gd`, requisitos del servidor y una prueba con logo. El encabezado
  del núcleo usa flex, que dompdf no dibuja: el logo sale arriba del nombre; queda para la mejora visual del final.
  El fixture `CotizacionesDePrueba::nueva()` usa un disco de adjuntos de prueba (emitir escribe el PDF). Pruebas:
  `PdfCotizacionTest` (5); Cotizaciones, arquitectura y concurrencia en verde.
- 2026-11-14 — Paso 13, revisión de arquitectura (toda la entrega): sin bloqueantes. Atendido: `nuevaVersion` bloquea la
  oportunidad antes que las versiones (codigo.md: quien crea un dependiente bloquea su origen; mismo orden que
  `aceptar`), y `emitir` (con solicitar aprobación, por `transicionarDesdeBorrador`) también la bloquea primero: no se
  emite ni se oferta el presupuesto sobre una oportunidad que otro pierde o anula a la vez. Sin prueba de concurrencia
  nueva: emitir y después perder es legítimo (no hay un invariante limpio que mirar) y el bloqueo es el mismo
  `bloquearOportunidadDe` que ya prueba la de aceptar. La CI declara `gd`. **Decisión consciente:** el PDF se dibuja y
  se escribe dentro de la transacción de emitir, para congelarlo junto con la emisión; si la transición falla después,
  el archivo queda huérfano y lo limpia la revisión de huérfanos (1.12). Menores anotados, sin cambio: montos con
  `(float)` solo para mostrar (como el núcleo; sin error con 2 decimales), la vista previa lee el disco tras
  `paraDescargar` (que revisa el permiso), y `QuitarAceptacion` deja dos efectos vivos sobre la misma cotización (solo
  importaría si un día se permite anular una emitida).
- 2026-11-15 — Paso 13 hecho: **entrega cerrada** (2.22a, 2.22b y 2.22c). `composer check:tarea` en verde: pint, phpstan
  (0 errores), migraciones (73) y, como cambió `composer.json`, todas las pruebas: 3210 en verde. Mapa al día (2.22 ✅ y
  la librería elegida en «Documentos impresos»); el plan pasa a `completados/`.
