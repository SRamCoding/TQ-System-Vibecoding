# Plan: 1.3c · Interfaz: animación al cambiar entre modo paneles y modo modular

- **Estado:** terminado
- **Módulo(s):** Nucleo (navegación) y panel `app`
- **Etapa:** 1 · tarea 1.3c · rama `interfaz-transicion-modos`
- **Creado:** 2026-10-16 · **Actualizado:** 2026-10-16

## Objetivo

Al cambiar de modo, cada enlace de la barra lateral se desliza hasta su caja del inicio modular, y al volver, cada
caja regresa a su enlace. Lo hace el navegador (View Transitions entre páginas), solo con CSS y nombres en la vista:
sin servicios, sin base, sin JavaScript propio. Sin animación en Firefox (no soporta transiciones entre páginas) ni
con «reducir movimiento».

## Contexto que leí

- `docs/planes/mapa-etapas.md` (fila 1.3c), `docs/convenciones/interfaz.md` («Inicio modular», «Vistas propias»).
- Código: `CajaModulo`, `RegistroModulos`, vistas `cajas-modulos` y `caja-modulo`, `AppPanelProvider` (el cambio de
  modo es la acción `nucleo.cambiar-modo-navegacion` y luego una redirección completa al inicio), `Inicio`.
- Filament 5: `NavigationItem` es `Configurable` y sus `extraAttributes` aceptan un closure que se evalúa al dibujar;
  van al `<li>` del enlace (`sidebar/group.blade.php`). Render hook `PanelsRenderHook::HEAD_END`.
- `docs/decisiones/pendientes.md`: nada afecta.

## Decisiones

- **D1. Emparejar por URL.** La caja ya declara su `url`; el enlace lateral se reconoce porque su URL tiene el mismo
  camino. Así ningún Resource repite la `clave` de su caja. `RegistroModulos::claveParaUrl()` hace la búsqueda.
- **D2. Marcas en el HTML, nombres en una sola vista.** La caja ya lleva `data-modulo`; el enlace lleva
  `data-enlace-modulo` (puesto con `NavigationItem::configureUsing` en el proveedor del panel). Una vista,
  `nucleo::filament.transicion-modos`, dada por el render hook, declara `@view-transition` y da a ambos el nombre
  `tq-modulo-{clave}`. En pantallas angostas la barra lateral está escondida: ahí el enlace no lleva nombre.
- **D3. La `clave` de una caja es un identificador CSS** (minúsculas, cifras y guiones): `CajaModulo` lo exige.
- **D4. Fuera del cambio de modo no se nota nada.** Opt-in global (`navigation: auto`), pero sin fundido de la
  página completa (`root` sin animación): al navegar dentro de un mismo modo los enlaces no se mueven y la página
  cambia como hoy. Solo se mueven los elementos con nombre que cambian de lugar.

## Pasos

- [x] 1. Plan y rama.
- [x] 2. `CajaModulo` valida la clave; `RegistroModulos::claveParaUrl()`. Pruebas.
- [x] 3. Enlaces laterales con `data-enlace-modulo`; vista `transicion-modos` en el render hook. Pruebas: cada caja y
      su enlace comparten nombre, sin repetidos; reducir movimiento; pantallas angostas.
- [x] 4. `docs/convenciones/interfaz.md` (sección nueva), `composer check` en verde.
- [x] 5. Revisión en pantalla del desarrollador; luego ✅ en el mapa y plan a `completados/`.

## Criterio de aceptación

- `tests/Modules/Nucleo/TransicionModosTest.php` en verde.
- El desarrollador lo revisa en pantalla (Chrome/Edge): enlace → caja y caja → enlace, modo claro y oscuro; sin
  animación con «reducir movimiento» y en Firefox.

## Bitácora

- 2026-10-16 — Plan escrito.
- 2026-10-16 — Pasos 2-4 hechos. Los atributos de `NavigationItem` van al `<li>` del enlace; el cluster Configuración
  queda emparejado porque su URL de navegación es `/configuracion`, igual que su caja. `composer check` en verde
  (841 pruebas). Ojo: dos `composer check` a la vez comparten `techquk_test` y fallan con «relation does not exist».
  Falta solo la revisión en pantalla (paso 5).
- 2026-10-16 — El desarrollador lo revisó en pantalla: la animación funciona de paneles a modular y de modular a
  paneles. Aclarado de paso: el saludo y las animaciones por fecha (Semana Santa, Navidad…) son la tarea 1.4b, no
  esta. Plan a `completados/`. Sigue la 1.4 (seguridad), o la 1.4b si el desarrollador la adelanta.
