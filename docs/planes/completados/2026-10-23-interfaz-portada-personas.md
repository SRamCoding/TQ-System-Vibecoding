# Plan: portada de Personas y empresas (1.4m)

- **Estado:** completado (2026-10-23)
- **Módulo(s):** Nucleo
- **Fase:** F1
- **Creado:** 2026-10-23 · **Actualizado:** 2026-10-23

## Objetivo

Personas y empresas deja de abrir con una tabla sola: pestañas por tipo con su cantidad, un aviso de fichas
incompletas y otro de posibles duplicados (que filtran la tabla), y los últimos cambios. Queda listo el registro de
pestañas de la ficha para que el CRM y las compras sumen las suyas. Solo pantalla y lecturas: no cambian acciones,
permisos ni datos.

## Contexto que leí

- `docs/planes/mapa-etapas.md`, fila 1.4m; `docs/convenciones/interfaz.md` («Patrones de pantalla»).
- `PartyResource` (tabla con filtros por rol, tipo y activas; ficha con pestañas Datos, Roles, Contactos, Sedes,
  Cuentas bancarias e historial), `ListarParties`, modelos `Party` (`scopeConRolVigente`, `contactos`, `sedes`,
  `cuentasBancarias`), `PartyContacto` (vigencia `desde`/`hasta`, teléfono, correo), `PartyCuentaBancaria`
  (`es_activa`, `es_detraccion`), `AuditoriaCambio`.
- Pendientes que tocan: «DNI y RUC 10 de una persona» (por eso los duplicados solo se avisan) y «Consulta de RUC y
  DNI», «Proveedores observados» (fuera de esta tarea).
- Rama: sale de `interfaz-catalogos` (su PR aún no se fusiona).

## Decisiones

- **D1 · Pestañas por tipo, no cajas** (desarrollador, 2026-10-23: «no vamos a manejar cajas»; sin íconos por tipo).
  Sobre la tabla, una fila de pestañas de texto: «Todos» y una por cada tipo activo del catálogo «Tipos de persona y
  empresa», cada una con su cantidad. Un tipo nuevo del catálogo aparece solo. Filtra la tabla en el sitio, sin
  recargar (pestañas de Filament). Desviación consciente del patrón «cajas» de `interfaz.md`, por decisión suya.
- **D2 · Fichas por completar, con reglas de buenas prácticas** (el desarrollador las delegó: «según los estándares
  altos»). Una ficha activa está incompleta si le falta:
  - cualquiera con algún tipo vigente (salvo colaborador, que tiene su regla): un medio de contacto (teléfono o
    correo propios, o de un contacto vigente). Una ficha sin tipo (la de una cuenta de usuario) no se avisa;
  - con RUC: dirección fiscal con distrito;
  - proveedor: una cuenta bancaria activa y un contacto vigente;
  - cliente: un contacto vigente;
  - entidad: un contacto vigente;
  - colaborador: teléfono o correo propios.
  Un aviso dice cuántas y por qué («3 proveedores sin cuenta bancaria…») y «Ver cuáles» filtra la tabla. Las reglas
  viven en una sola clase. Fuera, sin inventar: la cuenta de detracciones (depende del servicio que se contrata, no del
  proveedor).
- **D3 · Posibles duplicados: solo avisar** (desarrollador, 2026-10-23). Dos fichas activas son sospechosas si su
  nombre normalizado coincide (sin acentos, mayúsculas, puntuación ni la forma societaria: S.A.C., E.I.R.L.…) o si el
  DNI de una está dentro del RUC 10 de la otra. Un aviso con «Ver cuáles» filtra la tabla. Unir queda para después
  (pendiente «DNI y RUC 10 de una persona»; se anota el pendiente de unir).
- **D4 · Últimos cambios:** debajo de la tabla, los últimos 10 cambios de fichas de la bitácora (quién, qué ficha,
  cuándo), cada uno con enlace a su ficha. Solo con `parties.ver`, como todo el módulo.
- **D5 · Registro de pestañas de la ficha** (preparar lo diferido): `RegistroPestanasFicha`, como el de las tarjetas
  de «Mien»: cada módulo registra la suya (clave, título, permiso obligatorio, componente Livewire y orden) y la ficha
  la muestra con su permiso. Hoy no se registra ninguna nueva; las del Núcleo siguen como están.

## Pasos

- [x] 1. Plan aprobado (D1 a D5).
- [x] 2. Reglas: `Parties/ReglasFichaCompleta` (las de D2, como consulta y con su motivo) y `Parties/PosiblesDuplicados`
      (D3), en un servicio de lectura; pruebas de cada regla.
- [x] 3. Pantalla: pestañas por tipo con su cantidad, avisos con «Ver cuáles» (filtros de la tabla), últimos cambios.
- [x] 4. Registro de pestañas de la ficha y su prueba.
- [x] 5. Documentos: `interfaz.md`, `codigo.md`, `pendientes.md` (unir duplicados), mapa, `cambios.md`.
- [x] 6. Revisión a la vista, `revisor-arquitectura`, `composer check`, `/cerrar-tarea` y PR.

## Criterio de aceptación

- Cada regla de D2 se prueba por separado (una ficha que la incumple aparece con su motivo; completándola, sale).
- Duplicados: dos «Ferretería El Norte S.A.C.» y «FERRETERIA EL NORTE SAC» se avisan; un DNI y el RUC 10 que lo
  contiene, también; dos nombres distintos, no.
- Pestañas: una por tipo activo con su cantidad; un tipo nuevo en el catálogo suma su pestaña; filtran sin recargar.
- Últimos cambios: los 10 más recientes, con enlace; sin `parties.ver`, la pantalla no abre (como hoy).
- Registro de pestañas: una de prueba se ve con su permiso y no sin él.

## Bitácora

- 2026-10-23 — Plan escrito con el desarrollador (D1 a D5).
- 2026-10-23 — Paso 2. `Parties/ReglasFichaCompleta` (las siete reglas de D2, cada una como condición sobre la consulta
  de fichas, con su texto para el aviso; contactos vigentes con `vigentes`, tipos con `conRolVigente` de hoy),
  `Parties/PosiblesDuplicados` (normaliza sin acentos, puntuación ni forma societaria; DNI dentro de un RUC 10) y
  `Services/CalidadPartiesService` (solo lectura: `soloIncompletas`, `resumenIncompletas`, `cuantasIncompletas`,
  `idsPosiblesDuplicados`; solo fichas activas). `CalidadPartiesTest` (9): cada regla sola, el resumen, duplicados.
- 2026-10-23 — Pasos 3 a 5. `ListarParties`: `getTabs()` (Todos y un tipo activo por pestaña, con su cantidad de fichas
  activas), `content()` con los avisos (`filament/parties/avisos`), la tabla y los últimos cambios
  (`filament/parties/ultimos-cambios`), y `verCalidad()`, que pone o quita el filtro «Revisar» nuevo de la tabla
  (`PartyResource`, por `CalidadPartiesService`). `CalidadPartiesService::ultimosCambios()` (solo la ficha misma, no
  sus cuentas). Ajuste a D2 al probar: «sin teléfono ni correo» solo para fichas con algún tipo que no sea colaborador
  (las fichas de las cuentas de usuario no tienen tipo y llenaban el aviso). Registro de pestañas: `PestanaFicha`,
  `RegistroPestanasFicha` y `ComponentePestanaFicha` (permiso en cada petición), en `PartyResource::infolist` antes
  del historial. La pestaña «Roles» de la ficha pasa a «Tipos». Pruebas: `PortadaPartiesTest` (6),
  `CalidadPartiesTest` (9), `PestanasFichaTest` (4). Documentos: `interfaz.md` (patrón de portada de una lista y
  «Portada de Personas y empresas»), `codigo.md` (pestañas de la ficha), `pendientes.md` (unir duplicados),
  `cambios.md` (Común 0.3.8).
- 2026-10-23 — Paso 6, revisión de `revisor-arquitectura` (nada bloqueante). Atendido: los avisos se calculan al
  abrir y en «Ver cuáles» (propiedad bloqueada `$avisos`), no en cada tecla ni página; `CalidadPartiesService` va
  `scoped` y guarda los posibles duplicados con `once()` (la portada y el filtro los comparten); **la regla de la
  cuenta bancaria solo cuenta para quien puede ver datos bancarios** (decir qué proveedor tiene cuenta es un dato
  bancario; `ReglasFichaCompleta::todas($conDatosBancarios)`); la regla general solo mira tipos activos; los signos
  separan palabras al comparar nombres (Hermanos-López); la tabla dice «Tipos» y «Tipo» en vez de «Roles» y «Rol»;
  `ComponentePestanaFicha::boot()` exige también `parties.ver`. Pruebas nuevas: tipos vencidos y futuros, contacto con
  datos vacíos y contacto cerrado, regla bancaria sin permiso, últimos cambios sin contactos ni cuentas y con ficha
  borrada, guion, valores inventados en «Ver cuáles» y en el filtro, 403 sin permiso, aviso bancario según permiso, y
  la pestaña «Tipos» buscada en su HTML. Sin cambio: un índice `(auditable_type, fecha, id)` para la bitácora, cuando
  crezca.
- 2026-10-23 — Paso 6 hecho. `composer check` en verde (1508 pruebas). Plan cerrado; pasa a `completados/`. Revisión a
  la vista: la tabla no se puede capturar fuera de un servidor real; la revisa el desarrollador. Sigue la 1.4e.
