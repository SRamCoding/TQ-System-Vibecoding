# Ficha · Puente al ERP: ganar, proyecto y ofertado contra presupuesto

- **Estado:** aprobada (MV-009)
- **Versión:** v1
- **Tramo de la ruta:** T1
- **Aprobada:** 2026-11-19 · etiqueta `aprobado/t1-puente-erp-v1` (código del commit `e02abf7`)
- **Fuentes del diseño:** ADR 0023 del repo original (numeración por dueño, copia al ganar);
  `docs/planes/activos/2026-11-16-presupuesto-copia-al-ganar.md` (plan 2.12, D1 a D5); `docs/vibe/ruta-mvp.md` (tramo 1).

## Qué hace

Cuando el ejecutivo marca una oportunidad como ganada, en ese mismo paso nace su proyecto «en preparación», con el
jefe y las fechas que eligió, y lo cotizado se copia como el presupuesto del proyecto («PRY-2026-0013 · PRE-01 v1»),
aprobado y vigente. El costeo de la oportunidad queda congelado. Si después el presupuesto del proyecto cambia
(versión 2, 3…), la ficha del proyecto compara partida por partida lo ofertado contra lo vigente y muestra cuánto se
movió el margen. Los costeos, cotizaciones y presupuestos se numeran por su dueño.

## Decisiones de Sebastián

- **MV-005** (ficha) · El tramo 1 empieza por este puente; después licitaciones, agenda, catálogo y leads.
- **MV-006** (cruza módulos) · Ganar, crear el proyecto y copiar el presupuesto van en un solo paso; si la copia falla,
  no se gana. Confirma el ADR 0023 del repo original.
- **MV-007** (cruza módulos) · Numeración por dueño desde ahora: «OP-2026-0007 · COT-01 v1», «OP-2026-0007 · PRE-01
  v1», «PRY-2026-0013 · PRE-01 v1»; sin dueño, «PRE-2026-0031 v1». Confirma el ADR 0023.
- **MV-009** (general) · Aprobada sin revisión, como todas hasta terminar el sistema; los cambios vienen al final.
- **MV-008** (ficha) · La comparación va en su propia pestaña «Ofertado vs. presupuesto» de la ficha del proyecto, y el
  tablero muestra la diferencia total y lleva a esa pestaña.

## Pantallas y navegación

- **Oportunidad → «Ganada»** (panel deslizante que ya existía, desde la ficha, el tablero o la faja). Ahora pide además
  **Jefe de proyecto**, **Inicio del proyecto** y **Fin previsto**. Debajo del jefe dice: «Nace el proyecto «en
  preparación» con lo cotizado como su presupuesto.».
- **Ficha del proyecto** (`/proyectos/{id}`): pestañas Resumen, Datos, Presupuesto, **Ofertado vs. presupuesto**
  (nueva, entre Presupuesto y Partidas de control), Partidas de control e Historial. La pestaña abierta queda en la
  dirección: `/proyectos/{id}?pestana=ofertado-vs-presupuesto::tab`.
- **Datos** del proyecto: «Origen» dice «Oportunidad ganada OP-2025-0003» (antes, «Contrato #…» o «Proyecto interno»).
- **Resumen → tarjeta «Costo del proyecto»**: nueva línea **«Presupuesto vs. ofertado»** con la diferencia total (con
  signo; ámbar si el costo subió) y la nota «PRY-… · PRE-01 v2 contra lo ofertado PRY-… · PRE-01 v1». Su desglose lista
  las partidas que cambiaron, y cada una lleva a la pestaña. Sin cambios: «Sin cambios: ver la comparación».
- **Pestaña «Ofertado vs. presupuesto»**: título «Ofertado {v1} contra vigente {vN}»; debajo, costo ofertado, costo
  vigente y diferencia, y si hay venta, «Vendido … · margen ofertado … → vigente …». Tabla: Partida, Descripción,
  Ofertado, Presupuesto vigente y Diferencia (rojo si subió, verde si bajó), con una última fila «GG · Gastos
  generales». Sin ofertado: «Este proyecto no nació de una cotización».

Para verlo: `/proyectos/6` (P6, con v2: +59,235.00) y `/proyectos/8` (P8: −16,665.00); `/proyectos/2` (sin cambios).
Para ganar una desde la pantalla: «Plataforma de trazabilidad de lotes», en negociación; primero se acepta su
cotización.

## Operaciones

| Operación | Campos | Valida | Qué produce |
|---|---|---|---|
| Ganar oportunidad (cambia) | los de antes (oportunidad, versión, fecha de cierre, monto final, comentario) + jefe de proyecto (usuario activo, obligatorio), inicio (fecha, obligatoria; por omisión hoy + 7 días), fin previsto (fecha ≥ inicio, obligatoria; por omisión inicio + 90 días) | lo de antes + que tenga cotización aceptada; que la oportunidad no tenga ya proyecto | oportunidad ganada; proyecto «en preparación» con el cliente de la cuenta, el nombre de la oportunidad y esa oportunidad como origen; su presupuesto PRE-01 v1 aprobado y vigente, copiado de la cotización aceptada |
| Anular ganada (cambia) | los de antes | además: su proyecto sigue en preparación y sin costos | anula el proyecto con el motivo «Se anuló la oportunidad ganada: …»; si el proyecto avanzó, rechaza: «Su proyecto PRY-… ya está en ejecución: no se puede anular la ganada.» |
| Nueva versión de un presupuesto (cambia) | — | si es el costeo de una oportunidad ganada, la rechaza | «{número} es el costeo de una oportunidad ganada: queda congelado. Las versiones nuevas se hacen en el presupuesto del proyecto.» |
| Ver ofertado vs. presupuesto (nueva, consulta) | proyecto | permiso de ver presupuestos; montos con ver costos | la comparación descrita arriba |

## Estados

No hay estados nuevos. Al ganar: proyecto en «en preparación»; su presupuesto copia nace en borrador y en el mismo paso
pasa a «aprobado» y vigente («traer al proyecto»). El costeo de la oportunidad se queda «ofertado». Al hacer la v2 y
traerla, la v1 pasa a «reemplazado», pero sigue siendo «lo ofertado» de la comparación.

## Datos

- `proyectos.oportunidad_id` (entero, opcional, único; sin llave foránea porque es de otro módulo): la oportunidad de
  la que nació. La regla de la tabla pasa a: interno, o con contrato, **o con oportunidad**.
- `presupuestos.origen_cotizacion_id` (entero, opcional, con índice): la cotización aceptada de la que se copió. Solo
  la tiene la v1 del proyecto; las versiones siguientes no.
- Formato de `cotizaciones.numero`: `OP-AAAA-NNNN · COT-NN`. Formato de `presupuestos.numero`: `PRE-AAAA-NNNN` (sin
  dueño) o `OP-AAAA-NNNN · PRE-NN` / `PRY-AAAA-NNNN · PRE-NN`. El contador va por dueño (ámbito `OP2026.0007`), sin
  periodo y con dos dígitos.

## Reglas y números

- **La copia al ganar.** Con costeo enlazado: se copia el costeo entero (títulos, partidas, APU, porcentajes y
  totales). Las demás líneas de la cotización (de catálogo o libres) van como partidas en un título aparte, «OTRAS
  LÍNEAS DE LA COTIZACIÓN», con su cantidad y su **costo** unitario como precio. Sin costeo: un título «LO COTIZADO»
  con una partida por línea, gastos generales y utilidad en 0 %. Ejemplo («Plataforma de trazabilidad de lotes», solo
  líneas libres): PRE-01 v1 con 01.01 «Desarrollo de la plataforma» 1 × 106,000.00 y 01.02 «Lectores y etiquetas»
  1 × 43,500.00 = costo directo 149,500.00; vendido 215,000.00.
- **Lo vendido** es el total sin IGV (después de descuentos) de la cotización aceptada de la oportunidad del proyecto.
- **Comparación:** costo = costo directo + gastos generales. Diferencia = vigente − ofertado. Margen ofertado = vendido −
  costo ofertado; margen vigente = vendido − costo vigente.
  - P6: costo ofertado 2,513,468.19, vigente 2,572,703.19, diferencia +59,235.00 (SAN +18,450.00; 12 computadoras
    +35,400.00; gastos generales +5,385.00); vendido 2,696,265.88; margen 182,797.69 → 123,562.69.
  - P8: diferencia −16,665.00; margen 67,840.00 → 84,505.00. P1: +13,418.34; margen 143,923.32 → 130,504.98.
- Las nueve ganadas de la demo siguen sumando S/ 9,418,946.61.

## Supuestos sin confirmar

- **Ganar exige la cotización aceptada**, porque de ella sale el presupuesto del proyecto. Sin ella, no se gana.
- **Ganar pide jefe, inicio y fin previsto** en el mismo panel. Por omisión: inicio = hoy + 7 días y fin = inicio + 90
  días. Si se gana por otro camino sin esos datos, el jefe es el ejecutivo y las fechas salen del cierre.
- **El nombre del proyecto** es el de la oportunidad; **el cliente**, la empresa de la cuenta.
- Las líneas que no vienen del costeo se copian **a su costo** (no a su precio), porque el presupuesto del proyecto
  es costo.
- Anular la ganada **anula el proyecto** si sigue en preparación y sin costos; si no, no deja anularla.
- El árbol de partidas no nace solo al ganar: lo arma el jefe desde el presupuesto, como antes.
- Dentro de la ficha de su dueño, el número no se acorta a «COT-01 v3» (el ADR lo pide): se muestra completo.

## Atajos tomados

- **El número se guarda como se muestra** («OP-2026-0007 · COT-01»), no sin guiones como dice el ADR 0023; el ámbito
  sí va sin guiones en el contador. Hay una función para acortarlo que todavía no usa ninguna pantalla.
- **El núcleo ganó dos ganchos** en la definición de un documento (`numeracionDe` y `numeroVisible`) para numerar por
  dueño; la definición de presupuesto y la de cotización consultan el número de su dueño en otros módulos.
- **El efecto de ganar llama al ERP** por una interfaz del CRM (`ProyectoAlGanar`), con el jefe y las fechas pasados por
  un objeto compartido mientras corre la transición, no como datos de la transición.
- **Anular el proyecto** al anular la ganada lo hace el usuario que anula, sin revisar aparte su permiso sobre
  proyectos; el motivo es siempre «Otro» con el detalle.
- **La comparación** se calcula en memoria al abrir la pestaña y al dibujar el tablero (dos veces por página).
- **La venta provisional** (`VentaDesdeLaCotizacion`) reemplaza la de Contratos hasta que exista.
- Las líneas copiadas sin unidad usan «NIU».

## Dónde está en el código

- Núcleo: `Nucleo/Estados/DefinicionDocumento.php` (ganchos), `Nucleo/Services/TransicionesService.php`,
  `Nucleo/Numeracion/NumeroPorDueno.php`.
- CRM: `Crm/Oportunidades/Services/{ProyectoAlGanar,SinProyectoAlGanar,DatosDelProyectoAlGanar,PedidoGanar}.php`,
  `Crm/Oportunidades/Efectos/GanadaAvisaAlErp.php`, `Crm/Oportunidades/Acciones/GanarOportunidad.php`,
  `Crm/Oportunidades/Filament/PanelesOportunidad.php` (panel «Ganada»), `Crm/Cotizaciones/Estados/DefinicionCotizacion.php`,
  `Crm/Cotizaciones/Services/CotizacionDeOportunidadEnBase.php`.
- ERP: `Erp/Presupuestos/Services/{ProyectoDesdeLaGanada,ComparacionOfertado,VentaDesdeLaCotizacion}.php`,
  `ArmadoPresupuestoService::copiarAlProyecto()`, `Erp/Presupuestos/Filament/OfertadoVsPresupuesto.php`,
  `Erp/Presupuestos/Resumen/TarjetaDinero.php`, `Erp/Proyectos/Services/ProyectosEnBase.php` (`crearDesdeOportunidad`,
  `anularDesdeOportunidad`), `Erp/Proyectos/Filament/Resources/Proyectos/ProyectoResource.php`.
- Migraciones editadas: `crear_proyectos`, `crear_presupuestos`, `crear_cotizaciones`.
