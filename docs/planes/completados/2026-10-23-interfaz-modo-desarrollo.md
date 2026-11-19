# Plan: modo desarrollo (1.4i)

- **Estado:** completado (2026-10-23)
- **Módulo(s):** Nucleo
- **Fase:** F1
- **Creado:** 2026-10-23 · **Actualizado:** 2026-10-23

## Objetivo

En el ambiente `local`, un interruptor en el menú de la foto prende el **modo desarrollo**: deja simular la fecha y
cambiar en el sitio, con un clic, las piezas decorativas entre las variantes que ya existen. Apagado, el sistema se ve
como lo ve cualquier persona. En producción no existe ni se puede activar (con prueba). No guarda nada en la base ni
cambia lo que ven los demás.

## Contexto que leí

- `docs/planes/mapa-etapas.md`, fila 1.4i (lo ya decidido: solo `local`, interruptor en el menú de la foto, estado en
  la sesión, fecha simulada, cambiar en el sitio, absorbe la «Vista previa de fechas»; fuera: editar dibujos a mano y
  «ver como otra persona»).
- Plan 1.4d (`docs/planes/activos/2026-10-22-interfaz-gran-momento.md`): la «Vista previa de fechas» (solo `local`,
  permiso `parametros.editar`) y el gran momento; su paso 6 (ambientación de pantalla completa) aún no existe.
- Código: `Tiempo/Calendario::ahora()` (única definición de «hoy»; la usa también el negocio: vigencias, tipos de
  cambio), `Saludo/Ambientacion` (dibujo del día), `RegistroAmbientaciones`, `LogoTechquk::paleta()`,
  `GranMomentoService`, `Filament/GranMomentoEnPantalla`, `Filament/Pages/VistaPreviaFechas`.
- Lo que viene: la 1.4e (dibujo diario por piezas, mañana, tarde y noche) y el paso 6 de la 1.4d deben poder sumarse
  a este modo sin rehacerlo (preparar lo diferido).

## Decisiones

- **D1 · Lo ya decidido en el mapa** (desarrollador, 2026-10-22): solo con `APP_ENV=local`; en cualquier otro ambiente
  la ruta, el interruptor y todo su código de pantalla no se registran (prueba que lo exige). Interruptor en el menú
  de la foto; el estado vive en la sesión (se apaga al cerrar sesión). Nada se guarda en la base.
- **D2 · La fecha simulada solo afecta lo decorativo** (desarrollador, 2026-10-23): el saludo y su frase, el dibujo,
  la franja, el logo de fiesta, el agradecimiento del aniversario y el gran momento. Lo del negocio sigue con
  `Calendario::ahora()` real: nada se guarda con una fecha falsa. Se elige fecha **y hora** (para ver mañana, tarde
  y noche). Las piezas decorativas piden la hora a un solo lugar, `ModoDesarrollo::ahoraDecorativo()`, que sin el
  modo prendido es `Calendario::ahora()`.
- **D3 · Un clic sobre una pieza ofrece todas sus variantes** (desarrollador, 2026-10-23), agrupadas por fecha o
  estación, sin tener que cambiar la fecha. Piezas de hoy: el dibujo del saludo (todas las de `RegistroAmbientaciones`),
  el logo (normal y las cinco fechas con logo propio) y el gran momento (cada variante de cada fecha; al elegirla se
  vuelve a ver). Lo elegido vive en la sesión, manda sobre la fecha simulada y tiene «Quitar». **Registro de piezas**
  (`PiezaDecorativa`: clave, nombre, opciones agrupadas): la ambientación de pantalla completa (1.4d, paso 6) y los
  dibujos por piezas de la 1.4e se suman ahí sin rehacer el modo.
- **D4 · La «Vista previa de fechas» pasa al modo desarrollo** (desarrollador, 2026-10-23): solo se abre con el modo
  prendido, se llega desde su barra y sale de Configuración. Deja de pedir `parametros.editar` (en `local` lo pide el
  modo, no un permiso).
- **D5 · Una barra fija al pie** (desarrollador, 2026-10-23) en toda pantalla con el modo prendido: «Modo
  desarrollo», la fecha y hora simuladas para cambiarlas, «Volver a hoy», «Vista previa» y «Apagar». No tapa el
  contenido (la página reserva su alto).
- **D6 · Sin acciones registradas** (escrita en `codigo.md`, «Modo desarrollo»): prender, simular y elegir
  variantes son métodos de los componentes del modo, no acciones de `EjecutorAcciones`. No tocan datos ni existen
  fuera de `local`; registrarlas las pondría en el catálogo de producción. Es la excepción a la regla 7, escrita en
  `codigo.md`.
- **D7 · En producción no hay forma de activarlo** (desarrollador, 2026-10-23: «eso sí tengo bien asegurado»). Capas
  independientes:
  1. **Tres condiciones a la vez:** `APP_ENV=local`, `APP_DEBUG=true` y `TECHQUK_MODO_DESARROLLO=true` (variable
     propia que solo existe en el `.env` del desarrollador; `config('nucleo.modo_desarrollo')`, falso por defecto).
  2. **Sin ellas, nada se dibuja ni responde:** ni el ítem del menú, ni la barra; la vista previa y los componentes de
     Livewire del modo responden 404 (no existe) a quien los pida por URL o por Livewire.
  3. **Cada entrada vuelve a revisar** (`mount`/`boot` de cada componente, `canAccess` de la página,
     `ahoraDecorativo()` y las variantes elegidas): la sesión sola no activa nada; fuera de las condiciones, se ignora.
  4. **Alarma:** con `APP_ENV=production` y la variable en verdadero, se ignora y se registra un aviso crítico.
  5. **Pruebas:** en `production`, en `testing` y con cada condición apagada por separado, nada aparece aunque la sesión
     diga que está prendido.
  6. **Despliegue:** la lista de `docs/operaciones/` comprueba que la variable no esté en el `.env` del servidor.
- **El gran momento, en modo desarrollo, no se marca como visto** (no guarda nada en la base, D1): se ve como en la
  vista previa.

## Pasos

- [x] 1. Plan aprobado (D2 a D5).
- [x] 2. **El modo:** `Nucleo/Desarrollo/ModoDesarrollo` (solo `local`, estado en la sesión), «Modo desarrollo» en
      el menú de la foto, la barra fija al pie y la prueba de que fuera de `local` no existe.
- [x] 3. **Fecha simulada:** `ahoraDecorativo()` en las piezas decorativas; el gran momento sin marcar visto.
- [x] 4. **Cambiar en el sitio:** registro de piezas, las tres de hoy y la ventana al hacer clic.
- [x] 5. **Vista previa de fechas** dentro del modo.
- [x] 6. Documentos: `interfaz.md`, `codigo.md` (D6), mapa, `cambios.md`.
- [x] 7. Revisión a la vista, `revisor-arquitectura`, `composer check`, `/cerrar-tarea` y PR.

## Criterio de aceptación

- `ModoDesarrolloTest`: fuera de `local` (`production`, `testing`), ni el menú, ni la barra, ni la vista previa, ni
  el modo aunque la sesión diga que está prendido; en `local`, prender y apagar; la fecha simulada cambia el saludo y
  el dibujo y no `Calendario::ahora()`; una variante elegida manda y «Quitar» vuelve a la de la fecha; el gran momento
  en modo desarrollo no escribe en `momentos_vistos`; nada se escribe en la base al usar el modo.
- A la vista: la barra al pie en claro, oscuro y celular; el clic sobre el dibujo, el logo y el gran momento.

## Bitácora

- 2026-10-23 — Plan escrito; sale de `interfaz-mien` (su resto aún no se fusiona).
- 2026-10-23 — D7 a pedido del desarrollador («en producción no debe haber chance»): tres llaves y capas, en el plan.
  La capa 2 quedó como «nada se dibuja ni responde» (404) y no como «la ruta no existe»: registrar según el ambiente al
  arrancar no se puede probar (las pruebas cambian el ambiente después) y el efecto es el mismo.
- 2026-10-23 — Paso 2. `config('nucleo.modo_desarrollo')` (`TECHQUK_MODO_DESARROLLO`, comentada en `.env.example`,
  que además decía mal la clave del usuario de desarrollo); `Nucleo/Desarrollo/ModoDesarrollo` (estático: `disponible`,
  `activo`, `prender`, `apagar`, `simular`, `fechaSimulada`, `ahoraDecorativo`, `eleccion`, `elegir`; todo en la sesión
  bajo `nucleo.modo_desarrollo`; el aviso crítico, una vez por petición, con una marca en el contenedor);
  `Filament/Desarrollo/BarraDesarrollo` (barra al pie; `boot()` responde 404 sin el modo; recarga la página al cambiar)
  y su ítem del menú de la foto (`accionDelMenu`, oculto sin las llaves); `despliegue.md` comprueba la variable.
  `ModoDesarrolloTest` (8, con las cuatro formas de faltar una llave).
- 2026-10-23 — Fuera del plan, pedido del desarrollador: «Mostrar barra de módulos» pasa a **«Barra de módulos»** con
  una palanca de alarma contra incendio (íconos propios `tq-palanca-arriba` y `tq-palanca-abajo` en
  `Nucleo/resources/svg`, registrados en Blade Icons con el prefijo `tq`): arriba apagada, con el color de los demás;
  abajo prendida, solo el ícono en rojo suave (`#D9605A`, oscuro `#F08A84`); al pasar el mouse amaga moverse (sin
  movimiento con animaciones reducidas). `BarraModulosTest` lo prueba; `interfaz.md` al día.
- 2026-10-23 — Paso 3. `ModoDesarrollo::ahoraDecorativo()` en el saludo y su dibujo (`Inicio`), el agradecimiento, el
  logo y la franja (`AppPanelProvider`); lo demás sigue con `Calendario::ahora()`. Con el modo prendido, el gran momento
  no sale solo (elegirlo por la fecha simulada escribiría la fila de `grandes_momentos`) y `GranMomentoEnPantalla`
  no marca «Lo vi». Pruebas: con el 25 de diciembre simulado el inicio trae el dibujo y el gorro de Navidad; el 28 de
  diciembre, con el modo, ni gran momento ni filas escritas, y sin el modo sí sale.
- 2026-10-23 — Pasos 4 y 5. `Desarrollo/PiezaDecorativa` y `RegistroPiezasDecorativas` (singleton; valida la clave y
  que el valor elegido sea uno que la pieza ofrece) con tres piezas en `Desarrollo/Piezas/`: `DibujoDelSaludo`
  (`tema:clave`, de `RegistroAmbientaciones`, que suma `temas()` y `buscar()`), `LogoDeFiesta` (`normal` o la ocasión)
  y `GranMomentoDeFecha` (`ocasion:clave`; no se guarda: abre la vista previa con `?ocasion=…&variante=…`).
  `Ambientacion::para()` y `ocasionDelLogo()` respetan lo elegido (también la franja). La página marca las piezas con
  `data-tq-pieza` solo con el modo prendido (borde punteado dorado); la barra atrapa el clic sobre ellas (el logo no
  lleva al inicio mientras tanto) y abre su ventana (`filament/desarrollo/selector`), que también se abre desde
  «Cambiar:» en la barra. La barra reserva su alto real con un `ResizeObserver`. `VistaPreviaFechas` sale de
  Configuración (`/vista-previa-fechas`, fuera del menú), responde 404 sin el modo (`boot()`) y ya no pide
  `parametros.editar`; con `?ocasion&variante` abre mostrando esa variante. Pruebas: `ModoDesarrolloTest` (13) y
  `GranMomentoPreviaTest` al día. Revisada a la vista la barra y la ventana.
- 2026-10-23 — Pedido del desarrollador: en el logo, solo el ícono lleva la marca y abre la ventana; el nombre TECHQUK
  sigue llevando al inicio (prueba). Paso 6: `interfaz.md` («Modo desarrollo»), `codigo.md` («Modo desarrollo»: D6 y
  `ahoraDecorativo`), `cambios.md` (Común 0.3.7). El desarrollador ya lo probó en su equipo (le faltaba la variable en
  el `.env`: la aplicación no la veía; se comprobó con `tinker`, sin leer el `.env`).
- 2026-10-23 — Paso 7, revisión de `revisor-arquitectura`: ningún camino para activarlo en producción (URL, Livewire,
  sesión, `config:cache`, acción oculta del menú, caché de vistas). Atendido: prueba de la acción oculta llamada
  directo y de la barra ya abierta cuando falta una llave a mitad de sesión (404); la variable solo vale con `true`
  exacto (`no`, `off`, `0`, `1` o vacía la dejan apagada, con prueba); «no guarda nada en la base» precisado a «no
  escribe en las tablas del sistema, solo en la sesión» (en desarrollo `SESSION_DRIVER=database`); la prueba de la fecha
  fija el día. Queda anotado, sin cambio: si un servidor quedara con `APP_ENV=local`, `APP_DEBUG=true` y la variable
  (copiando un `.env` de desarrollo), el modo se prendería; la defensa es la comprobación de `despliegue.md`.
- 2026-10-23 — Paso 7 hecho. `composer check` en verde (1470 pruebas). Plan cerrado; pasa a `completados/`. Sigue la
  1.4j (Catálogos como cajas; el desarrollador evalúa además llevar «Usuarios y organigrama» a Configuración).
