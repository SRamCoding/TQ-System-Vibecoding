# Plan: Cuándo se anima el logo, y su espera en el ingreso

- **Estado:** completado
- **Módulo(s):** Nucleo (interfaz)
- **Fase:** F1
- **Creado:** 2026-10-27 · **Actualizado:** 2026-10-27

## Objetivo

Adentro del sistema, la animación completa del logo deja de correr en cada cambio de pantalla y corre solo cuando el
desarrollador la pidió. En la pantalla de ingreso, el logo hace una «espera»: sus piezas en su lugar, con color tenue,
vibran por pulsos como si quisieran arrancar, sin terminar nunca.

## Contexto que leí

- `app/Modules/Nucleo/resources/views/filament/logo.blade.php`: la animación en 4 tiempos (bloque → tiembla
  `tq-lg-tiembla` → se rompe en escombros `tq-lg-cae` → las piezas se pintan `tq-lg-pinta` y brillan `tq-lg-brilla`,
  luz `tq-lg-emerge`, detalle de fecha `tq-lg-aparece`). Se dispara con la clase `tq-corre`, que pone `correr()` en
  `x-init`, en `theme-changed.window`, `mouseenter` y `touchstart`. **El problema:** el panel no es SPA, cada enlace
  carga la página completa y `x-init` la corre en cada cambio de pantalla.
- Con «reducir movimiento» o la preferencia «reducir animaciones» (`html.tq-sin-animaciones`) ya queda quieto.
- `AppPanelProvider::logo()` dibuja la vista con `LogoTechquk::paleta()` y `escombros()`; el logo aparece en la barra
  superior y en el ingreso. `Filament/RespuestaDeIngreso::toResponse()` es la respuesta al iniciar sesión (lugar para
  dejar la marca de «primera pantalla tras el ingreso»).
- Pruebas que ya cubren el logo: `tests/Modules/Nucleo/LogoTechqukTest.php` (25).
- Regla de pruebas por tarea (`AGENTS.md`): en cada paso solo `LogoTechqukTest` y lo tocado; una corrida completa al
  final. Los colores del modo claro y oscuro del logo, como están (pedido del 2026-10-26).

## Decisiones

Aprobadas por el desarrollador el 2026-10-27, conversadas en la sesión de la 1.10.

- **D1 · Adentro, la animación completa solo corre:** al cambiar entre modo claro y oscuro; en la **primera pantalla
  después de iniciar sesión**; al pasar el mouse (o tocarlo); y al **actualizar la página** (F5, Ctrl+R o el botón del
  navegador). Al ir de una pantalla a otra con la barra o un enlace, y al ir atrás o adelante, el logo queda **quieto**,
  con su color pleno. Cómo: el navegador dice si la carga fue un «actualizar»
  (`performance.getEntriesByType('navigation')[0].type === 'reload'`); la primera pantalla tras el ingreso la marca el
  servidor (una marca de sesión que deja `RespuestaDeIngreso` y se usa una sola vez).
- **D2 · En la pantalla de ingreso, la espera:** las piezas del logo **en su lugar**, con su color **en tono bajo**
  (tenue, ni el bloque negro o blanco ni el color pleno), **vibran por pulsos** (vibran un momento, descansan, vuelven),
  como si quisieran arrancar la animación, **sin terminarla nunca** (no se rompe, no se pinta del todo, no brilla).
- **D3 · Al pasar el mouse en el ingreso:** vibran **más fuerte**, como impacientes.
- **D4 · Al presionar «Ingresar»:** solo entra; la primera pantalla de adentro corre la animación de siempre (D1).
- **D5 · Con «reducir animaciones» (o «reducir movimiento» del equipo):** el logo **normal y quieto, con sus colores
  plenos**, el de modo claro y el de modo oscuro como están: ni tenue ni vibrando.

## Pasos

- [x] 1. Plan con las decisiones aprobadas.
- [x] 2. Adentro: la animación solo en los cuatro casos de D1 (marca de ingreso, detección de «actualizar»); pruebas.
- [x] 3. Ingreso: la espera (D2, D3, D5); pruebas de lo que se puede probar (clases y marcas en el HTML).
- [x] 4. Pedirle al desarrollador que lo mire en la app (una vibración hay que verla); ajustar.
- [x] 5. `docs/convenciones/interfaz.md` si cambia una regla; mapa; `composer check` en verde.

## Criterio de aceptación

- Ir de una pantalla a otra no anima el logo; actualizar, cambiar de modo, pasar el mouse y entrar al sistema, sí.
- En el ingreso, el logo vibra por pulsos con color tenue y nunca termina; con el mouse, más fuerte.
- Con «reducir animaciones», quieto y con su color pleno, en el ingreso y adentro.

## Bitácora

- 2026-10-27 — Rama `interfaz-logo-animacion`, apilada sobre `mantenimiento-pruebas-paralelo` (PR por abrir), que está
  sobre main con la 1.10. Plan escrito al final de la sesión de la 1.10, con sus decisiones ya conversadas; la
  siguiente sesión empieza por el paso 2.
- 2026-10-27 — Paso 2. `RespuestaDeIngreso` deja `LogoTechquk::MARCA_INGRESO` en la sesión; `AppPanelProvider` la toma
  una vez por petición (el logo se dibuja dos veces por página; `once()` no sirve: guarda el valor entre peticiones del
  mismo proceso y el ingreso ya había dibujado el logo) y solo con sesión iniciada; la vista la recibe como
  `$animar` → `data-animar="1"`. En el navegador, `alCargar()` corre la animación con esa marca o si la carga fue un
  «actualizar». Quieto, el logo es el de la animación terminada (luz en reposo y brillo de cada pieza), para que no
  cambie entre una pantalla que animó y otra que no; con «reducir animaciones» también se ve así (antes, sin la luz):
  a confirmar en el paso 4. 2 pruebas nuevas en `LogoTechqukTest`.
- 2026-10-27 — Paso 3. Sin sesión (el ingreso), la vista recibe `$espera`: clase `tq-espera` y sin `x-init` ni
  `x-on`, así que nada la construye. Piezas y detalle al 45 %, sin luz ni escombros; el bloque vibra 0,8 s de cada
  2,6 s (`tq-lg-espera`) y, con el mouse, más amplio y cada 1,1 s (`tq-lg-impaciente`). Todo dentro de
  `prefers-reduced-motion: no-preference` y `html:not(.tq-sin-animaciones)` (D5). En el ingreso no hay persona, así que
  solo vale el «reducir movimiento» del equipo; la preferencia del perfil se aplica adentro. 3 pruebas nuevas (18 en
  el archivo). De paso, pedido del desarrollador: en Parámetros, «para qué sirve» bajo la clave (commit aparte).
- 2026-10-27 — Paso 4. El desarrollador lo miró en la app: «están bien» (tono tenue, vibración, brillo del logo quieto).
- 2026-10-27 — Paso 5. Regla en `docs/convenciones/interfaz.md` («Cuándo se anima el logo») y fila 1.4n en el mapa.
  `revisor-arquitectura`: sin bloqueantes; observó que si el destino tras el ingreso no dibuja el logo del panel, la
  marca se gasta en la siguiente pantalla que sí: hoy todo destino es del panel y es decoración, se deja así.
  `composer check` en verde (1716 pruebas, 6,0 min). Plan cerrado.
- 2026-10-27 — Arreglo tras el cierre (lo vio el desarrollador al ir de módulo en módulo): el selector de modo de
  Filament dispara `theme-changed` al cargar cada página (su `$watch` salta al poner el tema guardado), y el logo se
  animaba siempre. Ahora recuerda el tema con que cargó y solo corre si el aviso trae otro. Las pruebas revisan el
  HTML, no el navegador: por eso no lo atraparon.
