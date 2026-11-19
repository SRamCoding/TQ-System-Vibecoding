# Plan: portada con fotos y ficha con tablero de control de Proyectos (2.61)

- **Estado:** terminado (falta la revisión en pantalla del desarrollador y confirmar D13)
- **Responsable:** Sebastián (área 4 por el ADR 0020; en el modo actual, todas las áreas son suyas)
- **Entrega:** `entrega/proyectos-portada`
- **Módulo(s):** `Erp/Proyectos` (portada, cabecera, foto, registro de tarjetas), `Erp/Presupuestos` (sus tarjetas)
- **Fase:** —
- **Creado:** 2026-11-15 · **Actualizado:** 2026-11-15

## Objetivo

La lista de Proyectos se ve como tarjetas con la foto de referencia de cada proyecto, con filtros en una barra
lateral, y la ficha abre con una cabecera con la foto de fondo y una pestaña «Resumen» que responde ¿cuánto ganamos?,
¿vamos a tiempo?, ¿cuánto nos han pagado? y ¿qué necesita atención hoy?, con los datos que ya existen y un registro
para que cada módulo sume su tarjeta cuando nazca. Prototipo aprobado:
`docs/diseno/fuentes/prototipos/proyectos-portada-ficha.html`.

## Contexto que leí

- Plan 2.1 (`completados/2026-11-04-costura-c1.md`), D2: la tabla `proyectos` mínima. **No tiene** `tipologia`
  (pendiente «Tipologías de proyecto»), `monto_contratado` (vive en el contrato, C10), `avance_porcentaje` ni
  `fecha_fin_real` (fases y cierre, área 4).
- Plan 2.10: la ficha tiene pestañas que registran otros módulos (`Erp/Proyectos/Pestanas`); la portada actual es de
  pestañas por estado (`PortadaProyectos`).
- C1: `PartidasService::saldo()` da, por partida, presupuestado, comprometido, ejecutado, pagado y disponible.
- ADR 0016: los montos se ven con el permiso del grupo sensible `campos.ver-costos`.
- Plan 1.4o: la foto de perfil (`FotoPerfilService`, recorte y achicado en el navegador, disco privado).
- Pendiente que la afecta: **Tipologías de proyecto** (regla 12: no se inventa).

## Decisiones

- **D1.** (desarrollador, 2026-11-15) Cada proyecto tiene **una** foto de referencia que se **sube**; se recorta a 16:9
  y se achica en el navegador antes de subirla, como la foto de perfil. Las fotos de avance van aparte (galería de
  «Archivos y fotos», con su módulo).
- **D2.** (desarrollador, 2026-11-15) **Solo el jefe del proyecto** cambia o quita la foto. Acción registrada
  `erp.proyectos.cambiar-foto` (y `quitar-foto`), sin permiso propio: la condición es ser el jefe del proyecto.
- **D3.** (desarrollador, 2026-11-15) **El dinero solo lo ve quien tiene `campos.ver-costos`**: el semáforo de la
  tarjeta, la sección «¿Cuánto estamos ganando?», el monto contratado de la cabecera, los montos de garantías y
  cobranza y los filtros «Margen en riesgo» y «Con partidas excedidas». Quien no lo tiene ve estado, plazo, jefe,
  alertas sin montos y actividad.
- **D4.** (prototipo aprobado, 2026-11-15) **Portada:** tarjetas con foto (sin foto, un dibujo genérico del sistema),
  estado, código, nombre, cliente, barras de avance y plazo, semáforo de margen, jefe y días que faltan; barra
  lateral de filtros con la cantidad de cada opción (Salud, Estado, Jefe de proyecto y los que existan); buscador y
  orden. Se reemplaza la portada de pestañas de la 2.10; «Míos» pasa a ser un filtro.
- **D5.** (prototipo aprobado, 2026-11-15) **Ficha:** cabecera con la foto de fondo difuminada, nombre, cliente,
  estado, jefe, plazo y acciones. Pestaña «Resumen» primera, con cuatro secciones y la actividad reciente. **Todo
  monto se toca** y abre el panel deslizante con su desglose, hasta el documento.
- **D6.** (técnica) **Registro de tarjetas del Resumen** en `Erp/Proyectos` (como `RegistroPestanasProyecto`): cada
  módulo registra la suya, con su sección (dinero, plazo, cobranza, atención), su orden y si exige `campos.ver-costos`.
  `Erp/Proyectos` no lee modelos de otros módulos (regla 2). En esta tarea: `Erp/Presupuestos` registra «Dinero»
  (desde los saldos de C1) y «Partidas excedidas» / «Falta armar el árbol» en «Atención»; `Erp/Proyectos` registra
  «Plazo» (fechas) y «Jefe inactivo» en «Atención»; la actividad sale del historial que ya existe.
- **D7.** (técnica) La tipografía y los colores son los del sistema (plan 1.4ab), con el turquesa del ERP; nada de
  colores escritos a mano en la vista.

## Respondidas (desarrollador, 2026-11-15)

- **D8 (era P1). Tipología, fuera por ahora.** Sin filtro ni dibujo por tipología; el dibujo sin foto es genérico.
  Entra cuando se resuelva el pendiente «Tipologías de proyecto».
- **D12.** (desarrollador, 2026-11-15, «las imágenes de los proyectos que vienen por defecto me gustan») **Sin foto, el
  dibujo del prototipo** (`docs/diseno/fuentes/prototipos/proyectos-portada-ficha.html`, función `dibujo`): degradado
  diagonal de dos colores, siluetas de edificios en el color oscuro al 55 %, ventanas blancas al 35 % y una franja
  abajo, en 16:9. Las ocho paletas del prototipo (claro → oscuro): `#A5F3FC→#155E75`, `#7DD3FC→#0369A1`,
  `#C7D2FE→#3730A3`, `#E9D5FF→#6D28D9`, `#FBCFE8→#9D174D`, `#FECACA→#B91C1C`, `#FDE68A→#B45309`,
  `#BBF7D0→#15803D`; cada proyecto toma una fija por su id (la misma siempre). El ícono del centro (cámara, red,
  acceso, monitores, fuego, antena) va por **tipología**, que está fuera por ahora (D8). **Mientras, sale con un ícono
  genérico** (desarrollador, 2026-11-15, «que salga desde ya con un ícono genérico»): un chip, en el mismo blanco al 90 %
  y en el mismo lugar que los del prototipo (un cuadrado de esquinas redondeadas con patitas a los lados), distinto de
  los seis de tipología para que no se confunda con ninguno. Cuando se resuelva el pendiente «Tipologías de proyecto»,
  cada tipología reemplaza el chip por el suyo (queda el parámetro listo, ver «Preparado para lo diferido»).
- **D9 (era P2). La tarjeta muestra solo el plazo; el avance físico se hace después, sí o sí.** La barra de avance, la
  comparación avance contra plazo, el filtro «Atrasados» y la línea de tiempo por avance los suma el módulo de fases
  (área 4) con su tarjeta. Queda como condición de cierre de esa tarea en el mapa (fila 2.61, nota para las fases).
- **D10 (era P3). Sin margen hasta el contrato, con el punto listo para vincularlo.** La sección de dinero muestra
  costos (presupuestado, comprometido, ejecutado, pagado, proyección, PC-99). El margen previsto y proyectado y el
  semáforo de margen esperan al monto del contrato (C10): la tarjeta de dinero pide la venta a una interfaz
  `VentaDelProyecto` que hoy no tiene implementación (devuelve nada y la sección no dibuja el margen); contratos solo
  la implementa. Mientras tanto, el semáforo de la tarjeta compara costo: proyección contra presupuestado.
- **D11 (era P4). Proyección al cierre: lo mayor de los dos.** Por partida, lo mayor entre presupuestado y comprometido,
  más lo cargado a la PC-99. Semáforo (de costo hoy, de margen con el contrato): ámbar si la proyección pasa lo
  presupuestado en más de 2 puntos, rojo si pasa más de 6; los umbrales son parámetros editables.

## Pasos

- [x] 1. Plan con las decisiones del chat (D1–D7) y lo que falta decidir (P1–P4).
- [x] 2. P1–P4 respondidas por el desarrollador (D8–D11).
- [x] 3. Migración: `proyectos.foto_ruta` (nullable: un proyecto puede no tener foto) y su servicio `FotoProyectoService`
  sobre el disco privado, con descarga protegida.
- [x] 4. Acciones `erp.proyectos.cambiar-foto` y `erp.proyectos.quitar-foto`, solo para el jefe; pruebas.
- [x] 5. Registro de tarjetas del Resumen y su contrato; tarjetas de `Erp/Proyectos` (plazo, jefe inactivo).
- [x] 6. Tarjetas de `Erp/Presupuestos` (dinero desde C1, partidas excedidas, falta el árbol), con el permiso de costos;
  desgloses en el panel deslizante.
- [x] 7. Portada: tarjetas con foto, filtros con conteo, buscador y orden; todo filtrado en la base.
- [x] 8. Ficha: cabecera con la foto y pestaña «Resumen» primera.
- [x] 9. Pruebas: quién cambia la foto, qué ve quien no tiene permiso de costos, conteos de los filtros, el escenario
  del flujo 1 (19,525.00 presupuestado) en la tarjeta de dinero.
- [x] 10. `composer check:tarea` en verde.

- **D13.** (técnica, 2026-11-16; **a confirmar por el desarrollador**: amplía D11) La proyección al cierre suma además
  **lo que falta repartir** del costo presupuestado del árbol, si falta: si no, un árbol a medio repartir proyectaría
  menos que lo presupuestado sin haber ahorrado nada. Con el árbol entero repartido no cambia nada.
- **D14.** (técnica, 2026-11-16, revisión de arquitectura) El dinero sale del **último árbol aprobado**, el mismo del que
  sale el saldo de cada partida (C1): uno en preparación no se mezcla. Sin árbol aprobado, la sección de dinero no
  dibuja nada.
- **D2, aclaración** (técnica, 2026-11-16) La foto se puede cambiar o quitar en cualquier estado del proyecto, también
  cerrado o anulado: es una imagen de referencia, no un dato del documento.

## Preparado para lo diferido

- **El ícono de la tipología en el dibujo sin foto (D12):** el dibujo recibe el motivo como parámetro (hoy, el chip
  genérico); cuando existan las tipologías, cada una declara el suyo (los seis del prototipo) y no cambia nada más.

- **Avance físico (D9, obligatorio después):** el módulo de fases registra en el Resumen la tarjeta de avance y en la
  portada la barra de avance y el filtro «Atrasados». No se cierra la tarea de fases sin eso.
- **Margen (D10):** interfaz `VentaDelProyecto` sin implementación; contratos la implementa con el monto y sus adendas,
  y aparecen el margen y su semáforo sin tocar la ficha.

- El **registro de tarjetas** del Resumen: contratos (monto, adendas, hitos, penalidad), valorizaciones y garantías
  (cobranza), fases (avance físico y línea de tiempo), compras y tareo (actividad, comprometido) suman la suya sin
  tocar la ficha.
- Los **filtros** se declaran en una lista: tipología, nivel de gobierno y departamento entran cuando existan sus datos.

## Criterio de aceptación

- Pruebas de las acciones de foto, del registro de tarjetas, de los filtros y de la vista sin permiso de costos.
- En pantalla, la portada y la ficha como en el prototipo aprobado, con lo que hoy tiene datos.

## Bitácora

- 2026-11-15 — Al bajar el prototipo al modelo real aparecieron cuatro huecos (P1–P4): el prototipo mostraba tipología,
  avance físico, monto contratado y una proyección que hoy no tienen dato. Se preguntan antes del código.
- 2026-11-15 — La sesión en la nube no puede instalar las dependencias (el proxy bloquea las descargas de Composer):
  el código de esta tarea espera a poder correr las pruebas.
- 2026-11-15 — D12 del desarrollador: sin foto, el dibujo del prototipo con sus ocho paletas (una fija por proyecto);
  el ícono por tipología espera al pendiente (D8).
- 2026-11-15 — D12 ajustada: mientras no haya tipologías, el dibujo sin foto lleva un ícono genérico (un chip).
- 2026-11-16 — Pasos 3 y 4. `proyectos.foto_ruta` (migración `2026_11_16_000001`), `FotoProyectoService` y las acciones
  `erp.proyectos.cambiar-foto` y `quitar-foto` (sin permiso: la condición es ser el jefe, revisada otra vez dentro del
  bloqueo). La foto se sirve por una puerta nueva del núcleo, **`RegistroImagenes`** + `MostrarImagen`
  (`/imagenes/{clave}/{id}`): cada módulo registra cómo encontrar su imagen y quién la ve, así el panel no importa
  rutas de módulos ni el núcleo lee sus tablas. La revisión de la imagen pasó de `FotoPerfilService` a
  `RevisionDeFoto`, compartida. La carpeta `fotos-proyecto` se suma a `HuerfanosAdjuntosService::CARPETAS_DE_OTROS`
  (si no, la limpieza la borraría). La foto no sube la versión del proyecto ni entra en su historial (lo anota la
  acción). Prueba `FotoProyectoTest`.
- 2026-11-16 — Pasos 5 y 6. `Erp/Proyectos/Resumen/`: `TarjetaResumen` (clave, sección, orden, si exige costos y su
  contenido), `RegistroTarjetasResumen` (no pide las de costos a quien no los ve: el monto ni se calcula) y los datos
  que dibuja la ficha (`ContenidoTarjeta`, `LineaResumen` con su desglose, `AvisoResumen`). Se declara **registro
  público** en `ARCHITECTURE.md` y `HARNESS_PUBLICAS`, como `Pestanas/`. Proyectos: `TarjetaPlazo` (fechas, % del plazo
  y días; ámbar en los últimos 15, rojo vencido) y `TarjetaJefeInactivo`. Presupuestos: `DineroDelProyecto` (D11:
  proyección = por partida lo mayor de presupuestado y comprometido + lo que falta repartir + la PC-99; desvío en puntos;
  umbrales `presupuestos.semaforo_ambar_puntos` 2 y `semaforo_rojo_puntos` 6, editables), `TarjetaDinero`,
  `TarjetaPartidasExcedidas` y `TarjetaFaltaArbol`. **D10:** `Erp/Proyectos/Services/VentaDelProyecto` con
  `SinVentaDelProyecto` (`bindIf`): Contratos la reemplaza y aparecen «Vendido» y los márgenes. Las tarjetas piden sus
  servicios al usarse (no en el constructor): si no, el reemplazo de Contratos no las alcanzaría. Montos con `Soles`,
  sin `float`. Pruebas `ResumenProyectoTest` y `ResumenDineroTest` (flujo 1: 19,525.00 presupuestado; con 18,500.00
  comprometido en PC-01 y 200.00 en la PC-99, proyección 20,475.00, +4.86 puntos, ámbar).
- 2026-11-16 — Paso 7. `ListarProyectos` pasa a ser una página propia con tarjetas (`proyectos::portada.index`): foto o
  dibujo (`DibujoProyecto`, D12: ocho paletas por id y el chip), estado con su color, código, nombre, cliente, barra del
  plazo, semáforo de costo (solo con `campos.ver-costos`), jefe y días. Filtros a la izquierda con la cantidad de cada
  opción contada sin su propio grupo, como el prototipo: **Salud**, Estado, Jefe y «Solo los míos»; buscador (nombre,
  código o cliente, sin acentos) y orden; todo en la base (`PortadaProyectos::filtrar`, `opciones`, `ordenar`;
  `FiltrosPortada`). Dos costuras nuevas en `Resumen/`: **`FiltroSalud`** + `RegistroFiltrosSalud` (Proyectos:
  «Plazo vencido» y «Jefe inactivo», que reemplaza al aviso de la 2.10 y su `?aviso=` sigue funcionando;
  Presupuestos: «Costo en riesgo» y «Con partidas excedidas», con costos) y **`SemaforoDeCosto`** (por omisión
  `SinSemaforoDeCosto`; Presupuestos la implementa con `SaludDesdeElArbol`). Sin pestañas: «Míos» es un filtro (D4). Se
  quedan «Últimos cambios», como en las demás portadas. «Margen en riesgo» se llama «Costo en riesgo» hasta que haya
  contrato (D10).
- 2026-11-16 — Pasos 8 y 9. Ficha: cabecera `proyectos::ficha.cabecera` (foto difuminada de fondo o el dibujo, miniatura
  16:9, código, nombre, estado, cliente, jefe y fechas; reemplaza al subtítulo), pestaña **«Resumen» primera**
  (`ResumenDeProyecto`, componente propio de Proyectos: las cuatro preguntas con las tarjetas del registro, lo que dice
  cada sección sin tarjetas y la actividad reciente de la bitácora, `PortadaProyectos::actividadDe`). Cada dato con
  desglose es un botón que abre un panel deslizante (`x-filament::modal slide-over`) con sus filas, ya dibujadas: sin ir
  al servidor. «Subir foto» / «Cambiar foto» en la cabecera y «Quitar la foto» en «Más», visibles solo para el jefe; el
  editor de Filament encuadra en 16:9 y achica a 1600 px antes de subir. Pruebas en `ProyectosPantallasTest` (portada,
  filtros, buscador, dibujo, ficha) y `ResumenDineroTest` (flujo 1 en la tarjeta de dinero, desglose, semáforo y filtros
  de costo solo con permiso). **En pantalla falta la revisión del desarrollador** (no se pudo abrir la app desde la
  sesión).
- 2026-11-16 — Cierre. Corrida de toda la rama contra `origin/main` (todas las pruebas, en paralelo): **3,328 de 3,329**;
  la única falla, `IconosUnicosTest`: el ícono de la pestaña «Resumen» repetía el de Oportunidades, cambiado a
  `heroicon-o-presentation-chart-bar`. Revisión de arquitectura sin bloqueantes; sus seis observaciones, atendidas:
  (1) los filtros de salud se calculan una vez por vista (`PortadaProyectos::saludes()`) y los de costo solo recorren
  los proyectos abiertos (`ProyectosService::idsAbiertos()`, nuevo en C2); (2) D14, el dinero sale del último árbol
  aprobado (`ConsultaArbol::arbolAprobadoDe`); (3) D13 escrita, a confirmar; (4) el semáforo y los filtros de costo
  piden también `erp.presupuestos.ver` (`FiltroSalud::permiso()`, `SemaforoDeCosto::tonos(…, $usuario)`); (5) las piezas
  internas de Proyectos salen de `Resumen/` a `Tablero/`, así lo público queda en interfaces y registros; (6) la foto en
  cualquier estado, escrita en D2. Después: Arch, Proyectos, `ResumenDineroTest` (con la prueba de D14) y todo
  Presupuestos en verde.

