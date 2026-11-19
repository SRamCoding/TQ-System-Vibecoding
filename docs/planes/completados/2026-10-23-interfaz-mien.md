# Plan: «Mien», el tablero personal (1.4l)

- **Estado:** completado (2026-10-23)
- **Módulo(s):** Nucleo
- **Fase:** F1
- **Creado:** 2026-10-23 · **Actualizado:** 2026-10-23

## Objetivo

En el inicio hay un área nueva **«Personal»**, antes de Núcleo, con una sola caja dorada **«Mien»** que abre el
tablero personal. El Núcleo pone el marco y un mecanismo para que cada módulo aporte sus tarjetas, visibles solo con
permiso en ese módulo y sin que ningún módulo lea datos de otro.

## Contexto que leí

- `docs/planes/mapa-etapas.md`, fila 1.4l; `docs/decisiones/pendientes.md`, «Tablero de inicio por rol» (qué ve cada
  rol lo decide Gerencia: no se inventa aquí).
- `docs/convenciones/interfaz.md` («Identidad visual», «Inicio», «Barra de módulos», «Preferencias»).
- Plan 1.4g, D5: los módulos favoritos se deciden junto con «Mien».
- Código: `Navegacion/AreaModulo`, `CajaModulo`, `RegistroModulos`, `ColoresMarca`, `BarraModulos`,
  `widgets/cajas-modulos`, preferencia `navegacion.pantalla_inicio`.
- Rama: sale de `interfaz-mis-datos` (su PR aún no se fusiona; el menú de la foto ya cambió ahí).

## Decisiones

- **D1 · Qué trae ahora: solo el marco** (desarrollador, 2026-10-23). El área «Personal», la caja «Mien» y la página
  del tablero, que mientras no haya tarjetas dice que ahí verá sus pendientes, aprobaciones y alertas cuando lleguen
  sus módulos. Ninguna tarjeta del Núcleo por ahora.
- **D2 · El dorado: ocre en modo claro, dorado brillante en oscuro** (desarrollador, 2026-10-23). En `ColoresMarca`,
  un solo lugar. Claro `#936600` (se propuso `#9A6B00`; daba 4,49:1 sobre el fondo `#FAFAFA` de la página, apenas
  bajo el 4,5:1 que pide el texto; `#936600` da 4,86:1 y 4,61:1 sobre gris claro). Oscuro `#F2C14E` (10,6:1). En la
  barra lateral solo el ícono de «Mien» va dorado; en el inicio, el título del área y la caja.
- **D3 · Cada tarjeta carga sola** (desarrollador, 2026-10-23). Un registro de tarjetas en el Núcleo (como
  `RegistroModulos`): cada módulo registra las suyas desde su provider, con clave, título, permiso obligatorio
  (null a propósito si es para todos) y orden. Cada tarjeta es un componente Livewire que se dibuja aparte, después de
  abrir la página (carga diferida); si falla, esa dice «No se pudo cargar» y se registra el error, y las demás siguen.
  Solo se ven las de los módulos con permiso. La tarjeta lee sus datos de los servicios de su propio módulo.
- **D4 · Los módulos favoritos se descartan** (desarrollador, 2026-10-23): «la idea de Mien es que muestre lo
  relevante respecto al negocio, no respecto a la app». Cierra lo que dejó abierto la 1.4g (D5). **Regla para las
  tarjetas:** cada una muestra algo del negocio (pendientes, aprobaciones, alertas, números del área), nunca atajos,
  ajustes ni datos de la propia aplicación.

**Cómo queda** (con D1 a D4):

- `AreaModulo::Personal` («Personal»), primer caso: su sección va antes de Núcleo en el inicio y en la barra.
- Caja `mien` («Mien», `/mien`, ícono estrella, permiso nulo a propósito: es de todas las personas), con el tooltip
  «Cómo va tu trabajo de un vistazo: tus pendientes, tus aprobaciones y los números de tu área.».
- `ColoresMarca::DORADO` (`#936600`) y `DORADO_OSCURO` (`#F2C14E`); el proveedor del panel declara el color
  `dorado` (tono 600 el claro, 400 el oscuro). El área «Personal» usa ese color; en la barra, solo el ícono de «Mien».
- `Nucleo/Mien/`: `TarjetaMien` (clave, título, permiso obligatorio, componente y orden), `RegistroTarjetasMien`
  (registro desde el provider de cada módulo; clave repetida, error) y `ComponenteTarjetaMien` (base Livewire de cada
  tarjeta: carga diferida, y si falla, «No se pudo cargar» y el error al registro).
- Página `Filament/Pages/Mien` (`/mien`, toda persona con sesión): las tarjetas permitidas en su orden; sin ninguna,
  el mensaje de la D1.

## Pasos

- [x] 1. Plan aprobado (D1 a D4).
- [x] 2. Área «Personal», caja «Mien», dorado y página del tablero.
- [x] 3. Registro de tarjetas y lo que traiga la D1.
- [x] 4. Documentos: `interfaz.md`, `codigo.md`, `pendientes.md`, mapa, `cambios.md`.
- [x] 5. Revisión a la vista, `revisor-arquitectura`, `composer check`, `/cerrar-tarea` y PR.

## Criterio de aceptación

- `MienTest`: el área «Personal» va primero y la caja «Mien» la ve cualquier persona con sesión; la página abre para
  todas; sin tarjetas, el mensaje; una tarjeta de prueba se ve con su permiso y no sin él; se dibuja diferida; si
  falla, dice «No se pudo cargar», informa el error y las demás siguen; clave repetida o mal formada, error.
- El dorado claro y el oscuro cumplen 4,5:1 sobre el fondo de su modo (prueba con el cálculo de contraste).
- A la vista: inicio en claro, oscuro y celular, con la caja dorada antes de Núcleo y el ícono dorado en la barra.

## Bitácora

- 2026-10-23 — Plan escrito; decisiones en conversación con el desarrollador.
- 2026-10-23 — Pasos 2 a 4. `AreaModulo::Personal` (primer caso, color `dorado`); caja `mien` en el provider del
  Núcleo (permiso nulo, ícono estrella); `ColoresMarca::DORADO`/`DORADO_OSCURO` y `paletaDorada()` (la de
  `Color::hex()` con 600 y 400 exactos), declarada en el panel; `Nucleo/Mien/` (`TarjetaMien`, `RegistroTarjetasMien`,
  que además registra el componente en Livewire con nombre `mien.<clave>` para la carga diferida, y
  `ComponenteTarjetaMien`, con `render()` final que atrapa el error, lo informa y dibuja «No se pudo cargar», y
  `placeholder()`); página `Filament/Pages/Mien` y vistas `filament/mien/` (tablero, tarjeta y su espera); la estrella
  del tablero vacío en dorado.
  **Fuera del plan, pedido del desarrollador (2026-10-23):** en la barra, el módulo abierto se pinta con el color de su
  área (Personal dorado, CRM morado 700/400, ERP turquesa 700/400; Núcleo sigue con el azul de Filament), por CSS
  sobre `data-tq-area`; el ícono de «Mien», dorado siempre. Revisado a la vista con cajas de prueba de cada área, en
  claro y oscuro.
  Como «Mien» la ve todo el mundo, toda cuenta tiene ahora al menos un módulo: `PanelTest` («cuenta nueva») ve la
  caja «Mien» y la barra dibujada pero apagada; las pruebas de la barra y de preferencias usan un `RegistroModulos`
  vacío para sus cajas inventadas (sin «Mien») y `CajasModulosTest` espera el área «Personal» primero. `MienTest` (7).
  Documentos: `interfaz.md` («Mien», áreas, color del módulo abierto), `codigo.md` («Tarjetas de Mien»),
  `pendientes.md` («Tablero de inicio por rol»: el marco existe; favoritos descartados), `cambios.md` (Común 0.3.6).
- 2026-10-23 — Paso 5, revisión de `revisor-arquitectura` (3 «debería», nada bloqueante) atendida: la base de las
  tarjetas revisa el permiso en cada petición (`boot()` final con `RegistroTarjetasMien::porNombre()` y `puedeVer()`;
  antes, quien guardara la marca de la carga diferida podía pedir la tarjeta sin permiso); la vista de `contenido()`
  ya no se dibuja antes de tiempo sino dentro del ciclo de Livewire, en `tarjeta.blade.php` (antes `$this` fallaba
  siempre); `exception()` convierte en «No se pudo cargar» un error al montar, en un gancho o en una acción (antes,
  ventana de error sobre la página). Pruebas: diferida por nombre con `$this` en la vista, sin permiso se rechaza,
  falla al montar; contraste también contra gris 100 y 950. Menores anotados en `codigo.md` (escapar, errores sin datos
  sensibles, rol acotado por decidir con la 1.7); el aviso «Aún no hay módulos» queda como defensa, comentado.
- 2026-10-23 — Al probarlo aparecieron dos trampas: (1) Livewire inyecta los parámetros de `exception()` por nombre
  (`$e`, `$stopPropagation`); con otros nombres los pide al contenedor, falla y la vuelve a llamar sin fin (la prueba
  se colgaba). (2) Una vista pasada como dato a otra, Laravel la dibuja al juntar los datos, fuera del `try`: la base
  pasa una función que la dibuja dentro. Un rechazo de permiso en una prueba de Livewire no se lanza: responde 403
  (`assertStatus(403)`). La sesión se cortó a mitad y Docker detuvo el contenedor de la base; se volvió a arrancar.
- 2026-10-23 — `composer check` encontró dos efectos de que todos vean «Mien»: `AreaDeUsuario` (área del saludo)
  descartaba solo el Núcleo, así que nadie tenía ya una sola área y las frases del CRM o del ERP no salían; ahora solo
  cuentan CRM y ERP. `BarrasNavegacionTest`: con la barra apagada, la barra y su borde se dibujan y el CSS los esconde.
- 2026-10-23 — Paso 5 hecho. `composer check` en verde (1449 pruebas). Plan cerrado; pasa a `completados/`. Sigue la
  1.4i (modo desarrollo).
