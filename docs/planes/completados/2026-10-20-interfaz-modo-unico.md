# Plan: 1.4f · Interfaz: un solo modo, barra de módulos con interruptor e inicio con los módulos permitidos

- **Estado:** terminado (2026-10-21)
- **Módulo(s):** Nucleo (navegación, preferencias) y panel `app`
- **Etapa:** 1 · tarea 1.4f · rama `interfaz-modo-unico` (desde `main`, d42c128)
- **Creado:** 2026-10-20 · **Actualizado:** 2026-10-21

## Objetivo

Se acaba la elección entre modo paneles y modo modular: queda un solo modo. El inicio siempre es el de cajas y
muestra solo los módulos que la persona tiene permitidos (sin candados ni cajas apagadas; D8). En escritorio, la barra
lateral se prende o se apaga desde el menú del avatar, al instante y sin recargar, y muestra los módulos permitidos
menos los que la persona ocultó. En el celular no hay barra: se vuelve al inicio para cambiar de módulo. Inicio y barra
salen de la misma regla (`NavegacionService`).

## Contexto que leí

- `docs/analisis/README.md`, `docs/planes/mapa-etapas.md` (filas 1.3c, 1.3d y siguientes de interfaz),
  `docs/convenciones/interfaz.md` (completo), `ARCHITECTURE.md` («Interfaz»), ADR 0010, planes terminados
  `2026-10-16-interfaz-transicion-modos.md` y `2026-10-19-interfaz-navegacion.md`, `docs/planes/plantilla.md`.
- `docs/diseno/v4/parte-5-interfaz-roles.md` l.40-80: barra lateral «solo lo que el rol permite», tablero de inicio por
  rol y la propuesta de mosaicos (POR VALIDAR); en el celular, «cada persona ve solo los suyos».
- `docs/decisiones/pendientes.md`: nada bloquea. Se agrega uno (D11).
- Código actual:
  - `AppPanelProvider`: `->navigation(fn () => ! esModular())`; la barra lateral sale de la navegación automática de
    los Resources y clusters (hoy Personas, Seguridad y Configuración, las mismas tres cajas); ítem
    `modo-navegacion` del menú del avatar (acción `nucleo.cambiar-modo-navegacion` y `redirect` al inicio);
    `enlazarConSusCajas()` pone `data-enlace-modulo`; render hook de `transicion-modos`.
  - `Inicio::getWidgets()` elige `CajasModulos` o `AccountWidget` según el modo.
  - `RegistroModulos::visiblesPara()` (permiso de la caja con `can()`) y `claveParaUrl()` (solo para la 1.3c).
  - `CajaModulo` (clave validada, url, permiso, área); vistas `cajas-modulos` y `caja-modulo` (abren en otra pestaña).
  - `ModoNavegacion`, `CambiarModoNavegacion`, `PreferenciasUsuarioService::cambiarModo()`, `User::esModular()`,
    `users.modo_navegacion` (migración `2026_10_10_000002`, con CHECK), `UserFactory`, `CambiarModoNavegacionTest`,
    `TransicionModosTest`, y menciones en `RegistroAccionesTest`, `UsuariosTest`, `MigracionesTest`, `PanelTest` y
    el fixture `AccionDePrueba`.
  - Barra lateral (1.3d): `BordeBarraLateral`, vista `navegacion` (estado plegado, solo ≥ 1024 px), `BarraLateral`.
  - Filament 5: el corte en que la barra lateral pasa a menú desplegable es `lg` (1024 px). En angosto dibuja el botón
    `fi-topbar-open-sidebar-btn` y la barra encima (`fi-sidebar-open`, `fi-sidebar-close-overlay`). El logo ya lleva al
    inicio (`getHomeUrl()` en `topbar.blade.php`).

## Decisiones (propuestas, por aprobar una por una)

- **D1 · Número y rama.** **Aprobada 2026-10-20.** 1.4f, el siguiente libre de interfaz (después de 1.4e). Rama
  `interfaz-modo-unico` desde `main` (d42c128), al día con `origin/main` (2352280). La 1.5 está cerrada en su rama y
  falta fusionarla: cuando entre a main, se trae a esta rama y sus choques en `mapa-etapas.md` y los planes se
  resuelven conservando lo de las dos ramas.

- **D2 · Datos: se edita la migración de agosto.** **Aprobada 2026-10-20 (opción 1, ADR 0010: no existe
  `.claude/PRODUCCION` ni hay datos reales), con condiciones:**
  1. `2026_10_10_000002` crea `users.es_barra_modulos_visible boolean NOT NULL DEFAULT false` y ya no crea
     `modo_navegacion` ni su CHECK. Su `down()` se ajusta igual: sin el `DROP CONSTRAINT` ni la columna vieja.
  2. Una migración nueva crea solo `modulos_ocultos` (`user_id` FK con índice, `clave_modulo varchar(40)` con CHECK
     del formato de la clave, único `(user_id, clave_modulo)` en la base, `auditoria()`), como
     `categorias_frase_usuario`. Toda llave foránea con índice. Sin FK a la caja: las cajas son código, no filas; una
     clave de una caja que ya no existe se ignora.
  3. Sin conversión de datos: no hay datos reales que conservar.
  4. Sin rastro de `modo_navegacion`: enum `ModoNavegacion`, casts, `$attributes` y la lista de ocultos de `User`,
     `UserFactory`, vistas y pruebas. Al final, `grep` de `modo_navegacion` y `ModoNavegacion` sin resultados fuera
     del historial de `docs/`.
  5. El agente no corre `migrate:fresh`: al terminar le da al desarrollador el comando exacto para reconstruir su
     base de desarrollo y le dice si hay que volver a sembrar.
  - **Valor por defecto del interruptor: `false`** (confirmado por el desarrollador): un usuario nuevo empieza sin
    barra, solo con el inicio de cajas, y la prende desde el menú del avatar.

- **D3 · Un servicio del núcleo decide qué se ve.** **Aprobada 2026-10-21, con condiciones (abajo).** `NavegacionService` (en `Services/`), único lugar con la regla:
  - `permitidos(User)`: las cajas cuyo permiso tiene hoy (hoy es `RegistroModulos::visiblesPara()`, que pasa a ser
    solo el registro: `todas()`).
  - `deLaBarra(User)`: permitidos menos ocultos. La preferencia nunca da acceso.
  - El inicio usa `permitidos(User)` (D8: sin cajas bloqueadas, no hace falta otro método).
  - `mostrarBarra(User, bool)` y `ocultar(User, list<string> $ocultos)`.
  `PreferenciasUsuarioService` se queda con el saludo, el cumpleaños y el ancho; pierde `cambiarModo()`.
  *Condiciones del desarrollador:*
  - Se quita la acción `CambiarModoNavegacion` (con su registro y su prueba; ver D12).
  - Prender o apagar la barra y ocultar o mostrar módulos son acciones registradas (D4) que llaman a
    `NavegacionService`; ni Filament ni las vistas escriben las preferencias por su cuenta.
  - Las cajas del inicio y la barra lateral leen los módulos solo de `NavegacionService`. Una prueba verifica que
    concuerdan: los módulos del inicio son exactamente los visibles de la barra más los ocultos (sin ocultos, son
    los mismos).

- **D4 · Aprobada 2026-10-21.** **Dos acciones registradas, sin permiso (preferencias propias) y sin versión** (la última escritura gana, como
  el ancho de la barra):
  - `nucleo.mostrar-barra-modulos`: campo `visible` (sí/no).
  - `nucleo.elegir-modulos`: campo `ocultos` (lista de claves). La ventana muestra una lista de los permitidos, todos
    marcados salvo los ya ocultos; al guardar, lo desmarcado pasa a `ocultos`. Se guarda lo oculto y no lo elegido
    por dos razones: un módulo recién permitido aparece marcado solo, y uno que se permite mientras la ventana está
    abierta no se oculta por accidente.
  - Reglas en el servicio: cada clave de `ocultos` debe ser de un módulo que la persona puede abrir hoy (una clave
    ajena se rechaza: la lista no revela módulos sin permiso); no se guarda con todos desmarcados («Deja al menos un
    módulo en la barra»); al guardar se reemplaza toda la lista, así que un oculto de un módulo cuyo permiso se perdió
    desaparece, y si el permiso vuelve, el módulo vuelve marcado.

- **D5 · La barra lateral se arma con las cajas, no con la navegación automática.** **Aprobada 2026-10-21, con
  condiciones y con el cambio de D6.** `->navigation(Closure NavigationBuilder)`: un grupo por área (Núcleo, CRM, ERP)
  con **todos los permitidos** de la persona (`permitidos()`), cada uno con el ícono y el nombre de su caja. Quien no
  tiene ningún módulo permitido: `->navigation(false)`. Cada ítem lleva `data-modulo="<clave>"`, la misma
  marca que su caja (`extraAttributes`; D12) y cada grupo `data-tq-area="<área>"` (`extraSidebarAttributes`), API pública de Filament.
  - Los ocultos se dibujan con la clase `tq-oculto` y se esconden por CSS (no por la marca: las cajas del inicio la
    comparten); un área cuyos ítems están todos
    ocultos se esconde con `:has()`. Lo visible es siempre `deLaBarra()`. La barra no revela módulos sin permiso: solo
    dibuja permitidos.
  - Con la barra apagada, `<html>` lleva `tq-barra-apagada` desde el servidor y el CSS esconde la barra con las tres
    clases internas que D9 ya aceptó (`fi-sidebar`, `fi-topbar-open-sidebar-btn`, `fi-sidebar-close-overlay`); no se
    suma ninguna. En Filament 5 la barra y el contenido van en fila (flex): al esconderla, el contenido ocupa el
    ancho solo, sin tocar márgenes. Una prueba verifica que el HTML del panel sigue trayendo esas tres clases: si
    una actualización de Filament las renombra, `composer check` falla (si no, la barra apagada seguiría viéndose).
  - Las subnavegaciones de los clusters (Seguridad, Configuración) no cambian. El arrastre del ancho (1.3d) sigue
    igual, con la barra prendida.
  *Condiciones del desarrollador:*
  - El módulo activo queda resaltado también en sus pantallas internas (`isActiveWhen` con la URL de la caja como
    prefijo).
  - Cada ítem lleva el mismo ícono y el mismo nombre que su caja.
  - Una prueba verifica que los ítems **visibles** de la barra y las cajas permitidas muestran los mismos módulos, en
    el mismo orden, con el mismo ícono y el mismo nombre (junto con la de D3: inicio = visibles de la barra +
    ocultos).
  - El nombre de transición compartido de la 1.3c **no** se conserva: el desarrollador eligió retirarla con D12
    (sin cambio de modo, la animación pierde su objeto; queda solo la entrada de la barra de D7).

- **D6 · Prender, apagar y elegir se aplican al instante, sin recargar.** **Aprobada 2026-10-21 (cambio pedido por el
  desarrollador).** Nunca se pierde lo que se estaba escribiendo.
  - **Barra apagada:** «Mostrar barra de módulos» abre la ventana «Elegir módulos» con todos los permitidos marcados
    salvo los ya ocultos. Aceptar ejecuta las dos acciones de D4 (`nucleo.elegir-modulos` y luego
    `nucleo.mostrar-barra-modulos` con `visible = sí`) y la barra aparece en ese momento. Con todos desmarcados, el
    error de D4 y la barra no se prende.
  - **Cancelar o cerrar la ventana** no guarda nada: la barra sigue apagada y los ocultos como estaban. Con prueba.
  - **Barra prendida:** el ítem la apaga al instante, sin ventana.
  - **«Elegir módulos…»** (debajo) también aplica al instante.
  - El ítem dice siempre «Mostrar barra de módulos» y su ícono muestra el estado (visto si está prendida, cuadro
    vacío si está apagada; Filament no tiene un interruptor dentro del menú). La barra superior es un componente
    Livewire: al terminar la acción se vuelve a dibujar y el ícono cambia en ese momento.
  - Al guardar, la acción avisa al navegador (evento con `visible` y `ocultos`) y un script de la vista `navegacion`
    pone o quita `tq-barra-apagada` y las marcas de los ítems. La siguiente página llega del servidor ya coherente.
  - *Prueba:* las acciones guardan sin redirigir y envían el aviso con lo que hay que mostrar; la siguiente carga
    respeta la preferencia (clase en `<html>` e ítems marcados).
  - *Descartado:* recargar la página (lo de la 1.3d para el cambio de modo): perdía lo escrito.
  - **Cambio del desarrollador (2026-10-21): la ventana solo la primera vez por inicio de sesión.**
    - «Mostrar barra de módulos» con la barra apagada abre «Elegir módulos» solo la **primera vez** que se prende en
      cada inicio de sesión. Las siguientes veces en esa sesión la prende al instante, sin ventana, con los módulos ya
      guardados (`modulos_ocultos`). Para cambiarlos, «Elegir módulos…».
    - La marca de «ya se mostró» vive en la **sesión de Laravel**, no en la base: al cerrar sesión, o si vence y se
      vuelve a entrar, cuenta como primera vez otra vez. Se limpia en el evento de inicio de sesión (`Login`).
    - **«Inicio de sesión» es autenticación completa.** Si más adelante se agregan captcha o MFA, la marca se reinicia
      recién al completar el último paso (después del código MFA), no al validar la clave. La tarea que los agregue
      debe respetarlo (mover la limpieza al evento del último paso).
    - Solo cuenta como mostrada si se **aceptó** y la barra quedó prendida. Cancelar o cerrar no guarda nada y la
      próxima vez que se prenda en esa sesión la ventana vuelve a aparecer.
    - Lo demás no cambia: al instante, sin recargar; al menos un módulo.
    - *Pruebas:* primera vez en la sesión → ventana; segunda vez → sin ventana y con los módulos guardados; cancelar →
      la siguiente vez vuelve la ventana; nuevo inicio de sesión → vuelve la ventana.

- **D7 · Animación de entrada de la barra.** **Aprobada 2026-10-21 (ajustada a D6).** Se dispara solo al aceptar, en el
  navegador, con el aviso de D6; sin marca en la sesión. Al cargar cualquier página, nada se anima.
  - Al prender: los ítems visibles entran uno por uno, de arriba abajo (opacidad y un desplazamiento corto).
  - Al elegir módulos con la barra prendida: entran animados solo los que recién se muestran; los que se ocultan
    desaparecen sin animación.
  - Al apagar: desaparece al instante, sin animación.
  - Tiempos: duración 200 ms; paso = mín(60 ms, 400 ms ÷ (n − 1)), con n = ítems que entran, calculado por el script
    de la vista `navegacion` (`--tq-retraso` por ítem): el último termina antes de 600 ms aunque haya muchos módulos.
  - Con «reducir movimiento», sin animación. CSS en la vista con clases `tq-`.
  - *Pruebas:* el aviso lleva lo necesario y la vista trae la regla de «reducir movimiento»; la animación la revisa el
    desarrollador a la vista.

- **D8 · Inicio con los módulos permitidos.** **Aprobada 2026-10-21, con cambio del desarrollador: sin cajas
  bloqueadas.** `Inicio` siempre muestra `CajasModulos` (desaparece `AccountWidget`) con las cajas de
  `NavegacionService::permitidos()`, la misma regla que la barra (D5). Sin candado ni cajas apagadas; un área sin cajas
  permitidas no aparece. Cada caja, como hoy: enlace (pestaña según D10), tooltip con su descripción. La regla de
  `interfaz.md` («cada uno ve solo lo suyo») no cambia. Entrar por URL a un módulo sin permiso sigue dando 403.
  *Descartado:* mostrar todas las cajas con candado en las no permitidas (propuesta original del plan).

- **D9 · Pantalla angosta: cómo sabe el servidor que es un celular.** **Aprobada 2026-10-20: opción A (CSS por
  ancho), con condiciones:**
  1. Donde se pueda, se esconde con clases propias `tq-` puestas por `extraAttributes` o render hooks (ítems del menú
     del avatar, botón de inicio). Clases internas de Filament solo donde no hay otra forma (la
     barra lateral y su botón de menú en celular), y no más de las tres de la tabla.
  2. Cada clase interna nueva se anota en las excepciones de `interfaz.md` con por qué no hay alternativa, y tiene una
     prueba que dibuja la página y verifica que la clase sigue en el HTML de Filament: si una actualización la
     renombra, falla en CI y no en pantalla.
  3. El corte es el `lg` de Filament (1024 px), definido en un solo lugar y reutilizado por todas las reglas.
  4. Se esconde con `display: none`, para que el teclado y los lectores de pantalla tampoco lleguen a lo escondido.
  5. Revisión a la vista: celular, escritorio y tablet girando, con la barra prendida y apagada.

  Comparación que se presentó:

  | Opción | Cómo | A favor | En contra |
  |---|---|---|---|
  | **A. CSS por ancho** (recomendada) | El servidor dibuja la versión de escritorio y una regla `@media (max-width: 1023px)` esconde lo que no va: la barra y su botón de menú, los dos ítems del avatar (las cajas bloqueadas ya no existen: D8) | El servidor no necesita saberlo. Girar la tablet cambia al instante, sin recargar. Sin parpadeo. Las preferencias quedan intactas: solo no se aplican | Esconder la barra y su botón usa tres clases internas de Filament más (`fi-sidebar`, `fi-topbar-open-sidebar-btn`, `fi-sidebar-close-overlay`), a anotar como excepción |
  | B. Dibujar las dos versiones | Dos bloques en la página, uno por ancho, cada uno escondido con CSS en el otro | Igual de inmediato al girar | Duplica el inicio y la barra; dos lugares que mantener |
  | C. El navegador se lo dice al servidor | Un script guarda el ancho en una cookie y el servidor dibuja una sola versión | HTML justo para cada pantalla | La primera visita sale con la versión equivocada; girar la tablet exige recargar; el ancho en una cookie se desactualiza. Adivinar por el navegador (`User-Agent`) es peor: una tablet o una ventana angosta engañan |

- **D10 · Volver al inicio en el celular.** **Aprobada 2026-10-20: casa junto al logo, con condiciones.** El logo ya
  lleva al inicio; además, un botón con una casa a la izquierda del logo:
  1. Área táctil de al menos 44 × 44 px, aunque el ícono se vea más chico.
  2. `aria-label` «Ir al inicio» (en el celular no hay tooltip que lo explique).
  3. Se pone una sola vez desde el proveedor del panel (render hook de la barra superior), no en cada pantalla, con
     clase `tq-` y escondido en escritorio con el corte de D9.
  4. No aparece en el inicio. Prueba: en una pantalla de módulo el HTML trae el enlace al inicio con su `aria-label`;
     en el inicio, no.
  5. Revisión a la vista: no empuja el logo ni el avatar fuera de la barra en un celular de 360 px.

  **Misma pestaña en el celular, otra en escritorio (aprobada 2026-10-20, con condiciones):**
  1. Se decide al tocar la caja, con el corte de D9, en la vista común a todas las cajas: un módulo nuevo no hace
     nada.
  2. Sin JavaScript, o si falla, la caja abre en la misma pestaña (lo seguro en el celular).
  3. En escritorio, el enlace a otra pestaña lleva `rel="noopener"`.
  4. Pruebas: la caja trae el atributo que marca el comportamiento y su URL correcta. Revisión a la vista: en el celular, abrir tres módulos seguidos volviendo con la casa no deja pestañas
     extra; en escritorio, cada caja abre su pestaña.

- **D11 · Pendiente nuevo: tablero de inicio por rol.** **Aprobada 2026-10-21.** El v4 (parte 5) ponía en modo paneles un tablero por rol al
  centro. Con un solo modo, el inicio es el de cajas; si cada rol tendrá además su tablero (y dónde va) no se decide
  aquí. Se anota en `docs/decisiones/pendientes.md` («Del negocio»), para validarlo con las áreas usuarias junto con
  la propuesta de mosaicos del v4.
  *Idea del desarrollador para anotar con el pendiente (2026-10-21; no se construye en la 1.4f):*
  - Un tablero personal único llamado **«Mien»** (nombre decidido por el desarrollador).
  - En el inicio, un área nueva **«Personal»**, **antes** de Núcleo, con una sola caja y su descripción en el tooltip,
    por ejemplo: «Cómo va tu trabajo de un vistazo: tus pendientes, tus aprobaciones y los números de tu área.»
  - Color del área: **dorado**, en `ColoresMarca`, con un tono para modo claro y otro para oscuro, con contraste
    suficiente en los dos.
  - En la barra lateral, su ítem lleva el ícono dorado; los demás quedan sin color, como hoy.
  - Contenido: el Núcleo pone el marco y cada módulo aporta sus propias tarjetas (como `CajaModulo`), visibles solo
    con permiso en ese módulo; ningún módulo lee datos de otro. Primeras tarjetas posibles: aprobaciones pendientes
    (1.7) y alertas (1.10). Qué ve cada rol lo decide Gerencia.

- **D12 · Retiro de lo que pierde su objeto (1.3c y modo paneles).** **Aprobada 2026-10-21, con dos ajustes (abajo).** Se borran: `ModoNavegacion`,
  `CambiarModoNavegacion` y su registro, `PreferenciasUsuarioService::cambiarModo()`, `User::esModular()` y lo del
  modo en `User` (cast, valor por defecto, ocultos), el estado del factory, el ítem del menú, `enlazarConSusCajas()`,
  `RegistroModulos::claveParaUrl()`, la vista `transicion-modos` y su render hook, `CambiarModoNavegacionTest`,
  `TransicionModosTest` y las menciones en las demás pruebas. Se queda la validación de la `clave` de `CajaModulo`:
  ahora se guarda en `modulos_ocultos` y nombra clases y marcas (`data-modulo`).
  *Ajustes:*
  1. `RegistroModulos::visiblesPara()` sale (D3): `CajasModulos`, `Inicio` y el saludo (`Saludo/AreaDeUsuario`)
     llaman a `NavegacionService::permitidos()`. El saludo usa los permitidos, no los de la barra: ocultar un módulo
     no cambia el saludo.
  2. Una sola marca por módulo: los ítems de la barra llevan `data-modulo`, como las cajas (D5).

## Pasos

Un commit al cerrar cada paso. Cada paso deja `composer check` en verde.

- [x] 1. **Plan aprobado** (D1-D12) y fila 1.4f en el mapa.
- [x] 2. **Datos y servicio.** Migración (D2), modelo `ModuloOculto`, `NavegacionService` (D3), las dos acciones
      (D4), DBML al día. Pruebas de la migración, del servicio y de las acciones sin interfaz.
- [x] 3. **Retiro del modo paneles y de la 1.3c** (D12), con la prueba de que no queda rastro.
- [x] 4. **Barra lateral desde las cajas** (D5), interruptor y «Elegir módulos…» en el menú del avatar (D6),
      animación (D7).
- [x] 5. **Inicio con los módulos permitidos** (D8) y **pantalla angosta** (D9, D10, según lo aprobado).
- [x] 6. **Documentos:** `interfaz.md` (inicio, barra de módulos, pantalla angosta, excepciones de clases de
      Filament; fuera la sección «Cambio de modo»), `ARCHITECTURE.md` («Interfaz»), mapa (1.3c y el punto (1) de la
      1.3d superados, 1.4f), `pendientes.md` (D11), `cambios.md` (Común 0.3.3).
- [x] 7. Revisión a la vista (escritorio, celular, tablet girando la pantalla; claro y oscuro; «reducir
      movimiento»), revisión de `revisor-arquitectura`, `composer check` en verde y `/cerrar-tarea`.

## Criterio de aceptación

- **Servicio y acciones (paso 2):** barra = permitidos menos ocultos; un módulo recién permitido aparece marcado; un
  permiso perdido hace desaparecer el módulo de la lista y de la barra; no se guarda la lista con todos desmarcados;
  una clave ajena se rechaza; el interruptor se recuerda por persona (otra persona no lo ve cambiado); las dos acciones
  se ejecutan por `EjecutorAcciones`, sin interfaz.
- **Migración (paso 2):** `users.es_barra_modulos_visible` existe, `NOT NULL DEFAULT false`, y `modo_navegacion` no;
  `modulos_ocultos` rechaza un par `(user_id, clave_modulo)` repetido y una clave con formato inválido.
- **Retiro (paso 3):** ninguna mención a `ModoNavegacion`, `modo_navegacion`, `cambiar-modo-navegacion`,
  `esModular`, `data-enlace-modulo`, `transicion-modos` ni «modo paneles» en `app/` (migraciones incluidas: D2),
  `resources/`, `config/` ni `tests/` (salvo la propia prueba).
- **Barra (paso 4):** los ítems visibles de la barra son exactamente los de `deLaBarra()`, agrupados por área, con el
  ícono y el nombre de su caja, y en el mismo orden que el inicio; con la barra apagada, `<html>` lleva
  `tq-barra-apagada`; el HTML del panel trae las tres clases internas de D9; las acciones guardan sin redirigir y
  envían el aviso al navegador; Cancelar en «Elegir módulos» no guarda nada; los retrasos de la animación no pasan de
  600 ms con muchos módulos.
- **Inicio (paso 5):** aparecen solo las cajas permitidas y solo las áreas que tienen alguna; ninguna caja sin
  enlace; los módulos del inicio = visibles de la barra + ocultos; entrar por URL a un módulo sin permiso da 403.
- **A la vista (paso 7):** lo que depende del ancho o de la animación, en escritorio, celular y tablet girando la
  pantalla.

## Bitácora

- 2026-10-20 — Pedido del desarrollador (tarea nueva de interfaz, antes del paso 4 de la 1.5). Rama
  `interfaz-modo-unico` desde `main` (d42c128). Plan escrito; D1-D12 por aprobar. D9 y la pregunta de D10 (misma
  pestaña en el celular) son elección del desarrollador.
- 2026-10-20 — D9 y D10 (botón de inicio y misma pestaña en el celular) aprobadas con condiciones, escritas en cada
  decisión. Siguen por aprobar D2 (migración nueva o editar la vieja) y el resto (D1, D3-D8, D11, D12).
- 2026-10-20 — Llegó otra tarea, «Mis datos» y directorio del equipo, que también toca el menú del avatar: su
  pedido exige que la 1.4f esté cerrada antes de planearla. Orden propuesto (sin confirmar): terminar la 1.5, cerrar la 1.4f y luego
  planear «Mis datos».
- 2026-10-20 — main traído a la rama (merge de `origin/main`, sin choques: solo traía el PR #21, ADR 0018). La 1.5
  todavía no está en main (su cierre sigue en `nucleo-estados-transiciones`): sus choques con `mapa-etapas.md` y los
  planes quedan para cuando se fusione.
- 2026-10-20 — D2 aprobada: se edita la migración vieja `2026_10_10_000002` por la ADR 0010 (sin `.claude/PRODUCCION`
  ni datos reales), y una migración nueva crea solo `modulos_ocultos`. No hay conversión de datos. Condiciones en D2.
  El desarrollador confirmó el defecto `false` de `es_barra_modulos_visible` (usuario nuevo sin barra). D1
  aprobada con el texto al día (la 1.5 cerrada, falta fusionarla). Siguen D3-D8, D11 y D12.
- 2026-10-21 — D3 aprobada con condiciones: se quita `CambiarModoNavegacion`; las preferencias solo se escriben con
  acciones registradas que llaman a `NavegacionService`; inicio y barra leen solo del servicio, con una prueba de que
  concuerdan (sin candado en el inicio = barra + ocultos). D4 aprobada tal cual.
- 2026-10-21 — D5 aprobada con condiciones: activo en pantallas internas, mismo ícono y nombre que la caja, prueba de
  mismos módulos y orden. Se le preguntó al desarrollador si conservar el nombre de transición de la 1.3c: eligió
  retirarla (D12 queda como está). Siguen D6-D8, D11 y D12.
- 2026-10-21 — D6: el desarrollador cambia el comportamiento (sin recarga; «Mostrar barra de módulos» abre «Elegir
  módulos» y la barra aparece o desaparece al instante; la siguiente carga viene coherente desde el servidor) y pide,
  antes de aprobar, saber si se puede sin clases internas de Filament y qué cambia en D5 y D6. Respuesta dada en la
  sesión; D5 y D6 quedan en espera de su aprobación.
- 2026-10-21 — D5 y D6 aprobadas con la propuesta dada (barra siempre dibujada con los permitidos, ocultos y apagado
  por CSS con las tres clases de D9, sin recargar), más una condición: Cancelar o cerrar «Elegir módulos» no guarda
  nada, con prueba. Sigue D7, que se ajusta (la animación se dispara en el navegador, sin marca en la sesión).
- 2026-10-21 — D7 aprobada ajustada a D6: animación solo al aceptar, en el navegador; al elegir, solo los nuevos; al
  apagar, sin animación. Siguen D8, D11 y D12.
- 2026-10-21 — D8 aprobada con cambio: sin cajas bloqueadas; el inicio muestra solo los permitidos, desde
  `NavegacionService::permitidos()` (sale `delInicio()` de D3), y un área sin permitidos no aparece. Ajustados el
  objetivo, D3, D9, D10, el criterio de aceptación y la fila 1.4f del mapa. Criterios de migración y retiro al día
  con D2 (sin conversión; sin excepción para migraciones) y de barra con D6-D7. Siguen D11 y D12.
- 2026-10-21 — D11 aprobada tal cual. Sigue D12.
- 2026-10-21 — D12 aprobada con dos ajustes: el saludo, las cajas y el inicio pasan a `NavegacionService::permitidos()`;
  la barra usa la misma marca `data-modulo` que las cajas. **Plan aprobado completo (D1-D12).** Siguiente: paso 2.
- 2026-10-21 — Rama subida a GitHub (`origin/interfaz-modo-unico`), sin PR.
- 2026-10-21 — Paso 2 hecho. `2026_10_10_000002` crea `users.es_barra_modulos_visible` (`NOT NULL DEFAULT false`);
  `modo_navegacion` y su CHECK siguen ahí **hasta el paso 3**, que los quita junto con el código que los usa (así
  `composer check` queda en verde en cada paso; el desarrollador reconstruye su base una sola vez, al final). Migración
  nueva `2026_10_21_000001_crear_modulos_ocultos` (FK con índice, único `(user_id, clave_modulo)`, CHECK del formato),
  modelo `ModuloOculto` (alias `modulo_oculto` en el morph map), `NavegacionService` (`permitidos`, `ocultos`,
  `deLaBarra`, `mostrarBarra`, `ocultar`), acciones `nucleo.mostrar-barra-modulos` y `nucleo.elegir-modulos`, DBML al
  día. `RegistroModulos::todas()` nuevo; `visiblesPara()` sigue hasta el paso 3 (D12), cuando sus tres usos pasan al
  servicio.
  Desviaciones y piezas nuevas: (1) tipo de campo `ListaTextos` (`Campo::listaDeTextos`, `TagsInput` por defecto en
  `AccionFilament`): no había lista de textos y las claves de módulo no son ids; la ventana del paso 4 usará su propio
  control. (2) `CajaModulo` exige la clave hasta 40 caracteres, lo que cabe en `modulos_ocultos.clave_modulo`.
  (3) `ocultos()` devuelve solo los que la persona todavía puede abrir: un oculto de un módulo sin permiso, o de una
  caja retirada, no cuenta. (4) Con cero permitidos se puede guardar una lista vacía (no hay barra que dejar vacía).
  Para el paso 3: la acción de prueba `tests/Modules/Nucleo/Fixtures/AccionDePrueba.php` usa `ModoNavegacion` como
  enum de ejemplo; hay que cambiarlo por otro enum.
  Pruebas: `NavegacionServiceTest` (16) y `PreferenciasBarraModulosTest` (10). `composer check` en verde (1168).
- 2026-10-21 — La 1.5 subida a GitHub (`git push origin nucleo-estados-transiciones`, `b60f0f5..47982eb`, sin forzar);
  su PR lo abre el desarrollador. `codigo.md` («Acciones registradas») explica `Campo::listaDeTextos()`: para qué
  sirve y cuándo usar `listaDe()` o `listaDeIds()` en su lugar.
- 2026-10-21 — Paso 3 hecho. Borrados: `ModoNavegacion`, `CambiarModoNavegacion` (con su registro y su prueba),
  `PreferenciasUsuarioService::cambiarModo()`, `User::esModular()` y el modo en `User` (cast, defecto, propiedad,
  campos sin bitácora), el estado `modular()` del factory (en su lugar, `conBarraModulos()`), el ítem del menú,
  `enlazarConSusCajas()`, `RegistroModulos::claveParaUrl()` y `visiblesPara()`, la vista `transicion-modos` con su
  render hook y `TransicionModosTest`. `2026_10_10_000002` ya no crea `modo_navegacion` ni su CHECK (su `down()`
  tampoco los toca). `CajasModulos` y el saludo (`AreaDeUsuario`) leen de `NavegacionService::permitidos()` (D12).
  La acción de prueba usa `AreaModulo` como enum de ejemplo. Comentarios y nombres que decían «inicio modular» dicen
  «inicio»; la sección de `interfaz.md` pasa a llamarse «Inicio» (el resto de `interfaz.md` va en el paso 6). El skill
  `/nueva-accion` cita `PreferenciasBarraModulosTest.php` como ejemplo en vez de la prueba borrada.
  Prueba de retiro: `SinModoNavegacionTest` recorre `app`, `resources`, `config`, `database`, `routes`, `lang` y
  `tests` (salvo ella misma) buscando los nombres del modo y de la 1.3c. `grep` de `modo_navegacion` y
  `ModoNavegacion`: solo en `docs/planes` (historia) y en esa prueba.
  **Estado intermedio hasta el paso 4:** el inicio ya es siempre el de cajas con los permitidos (adelanto de D8, porque
  `esModular()` desapareció); la barra lateral sale solo con `es_barra_modulos_visible` y todavía es la navegación
  automática de Filament; no hay ítem en el menú para prenderla (llega en el paso 4).
  `composer check` en verde (1145; bajan por las dos pruebas borradas). El desarrollador reconstruye su base ahora
  (`php artisan migrate:fresh --seed`), una sola vez.
- 2026-10-21 — El desarrollador reconstruyó su base. Las preferencias por usuario se planean en otra sesión, con rama
  desde main ya con la 1.4f: no se tocan en esta rama.
- 2026-10-21 — Paso 4 hecho. `Nucleo/Filament/BarraModulos.php` arma la navegación (`->navigation()` del panel) con
  `NavegacionService` y dibuja los dos ítems del menú del avatar; la vista `navegacion` lleva el CSS que esconde, la
  marca de `<html>` y el script que aplica el evento y anima (D7). `NavegacionService::porArea()` agrupa igual para
  el inicio (`CajasModulos::secciones()`) y la barra.
  Precisiones al construir (dentro de lo aprobado):
  (1) Los ítems de la barra llevan, además de `data-modulo`, la marca `data-tq-barra`: las cajas del inicio también
  llevan `data-modulo`, y el CSS y el script solo deben tocar la barra. (2) `tq-barra-apagada` se pone con un
  `<script>` en `HEAD_END` que corre antes de dibujarse el cuerpo (sin parpadeo): Filament no tiene cómo agregar una
  clase a `<html>` desde el servidor. (3) Filament arma los ítems del menú del avatar al empezar cada petición; para
  que el ícono cambie en el momento, la acción envía además `refresh-topbar` (evento público de Filament). (4) «Elegir
  módulos…» solo aparece con la barra prendida (apagada, el otro ítem ya abre la ventana); sin módulos permitidos no
  aparece ninguno de los dos. (5) Íconos: `heroicon-o-check` (prendida) y `heroicon-o-stop` (apagada), a revisar a
  la vista. (6) Sin aviso al guardar: el cambio se ve en el acto. (7) La ventana lista los permitidos en el orden de
  la barra (por área) y su control (`CheckboxList`) muestra marcado lo visible; a la acción le llega lo desmarcado.
  (8) El ítem activo se calcula con la URL de la petición: si un día la barra se redibuja por Livewire, ninguno
  quedaría resaltado (hoy no pasa: solo se dibuja al cargar la página).
  Pruebas: `BarraModulosTest` (16): permitidos y ocultos en el HTML, concordancia con el inicio (módulos, orden,
  ícono, nombre, URL), activo en pantallas internas, marca de `<html>`, las tres clases internas en el HTML de
  Filament, sin permitidos sin barra ni ítems, prender con la ventana sin redirigir, Cancelar no guarda, todo
  desmarcado no guarda, apagar sin ventana, «Elegir módulos…», íconos, y la fórmula y «reducir movimiento» de la
  animación. `PanelTest` y `BarrasNavegacionTest` dan un módulo permitido donde esperan barra. `composer check` en
  verde (1161). Falta anotar las tres clases internas en las excepciones de `interfaz.md` (pasos 5 y 6).
- 2026-10-21 — El desarrollador probó la barra en el navegador: funciona. Confirmado que existe la prueba de un usuario
  sin módulos permitidos (sin barra ni ítems en el menú: `BarraModulosTest`). Idea de «Mien» (tablero personal, área
  «Personal» dorada antes de Núcleo) agregada a D11, para anotarla en `pendientes.md` en el paso 6, sin construir.
- 2026-10-21 — Paso 5 hecho. D8: el inicio ya mostraba solo los permitidos desde el paso 3; prueba nueva de que un
  área sin permitidos no aparece. D9: el corte vive en `BarraLateral::CORTE_ESCRITORIO` (1024, el `lg` de Filament)
  con `consultaCelular()` y `consultaEscritorio()`; lo usan el CSS de `navegacion`, el borde de arrastre, la marca
  de plegado y la caja (antes, 1024 escrito en tres sitios). En el celular se esconden la barra, su botón, su capa y
  los dos ítems del menú (`tq-solo-escritorio`, con `extraAttributes`); en escritorio, la casa (`tq-solo-celular`).
  Sin clases internas nuevas: las tres de D5. D10: la casa (`nucleo::filament.ir-al-inicio`, render hook
  `TOPBAR_LOGO_BEFORE`), 44 × 44 px, `aria-label` «Ir al inicio», no sale en el inicio. La caja ya no lleva
  `target="_blank"`: un `x-on:click` abre otra pestaña (`noopener`) solo desde el corte y con un clic simple (Ctrl,
  Cmd, Shift, Alt o el botón del medio quedan para el navegador); sin JavaScript, la misma pestaña. Marca
  `data-tq-pestana="nueva-en-escritorio"`.
  Precisión: `BarraModulos::rutaActual()` lee la URL con `Livewire::originalUrl()`, así que la casa y el ítem activo
  siguen sabiendo dónde está la persona cuando Livewire redibuja la barra superior (`refresh-topbar`): si no, tras
  prender la barra en el inicio aparecería la casa. Esto también resuelve la nota (8) del paso 4.
  Pruebas: `PantallaAngostaTest` (5). `PanelTest` cambia `target="_blank"` por la marca nueva. `composer check` en
  verde (1166). Queda para el paso 6 anotar la casa y la regla de pestañas en `interfaz.md`.
- 2026-10-21 — El desarrollador probó el paso 5 en el navegador: funciona. Main traído a la rama (merge `0087efe`): la
  1.5 (PR #23) y los versículos (PR #22). Choques resueltos conservando los dos lados: la descripción del clúster
  Configuración (estados y motivos, sin la mención al modo) y los imports y el morph map de `NucleoServiceProvider`.
  `mapa-etapas.md` queda con la 1.5 ✅ y su plan en `completados/`. La regla de los versículos no se toca. Lo traído
  de main no nombra el modo (`SinModoNavegacionTest` en verde). `composer check` en verde (1300).
- 2026-10-21 — Cambio del desarrollador a D6, escrito en D6: la ventana «Elegir módulos» al prender sale solo la
  primera vez de cada inicio de sesión. `Navegacion/EleccionDeModulos` guarda la marca en la sesión de Laravel;
  `Listeners/ReiniciarEleccionDeModulos` la limpia en `Login` (con captcha o MFA, se moverá al último paso). La marca
  se pone solo cuando la barra quedó prendida tras aceptar. Con la marca, «Mostrar barra de módulos» con la barra
  apagada es la misma acción `nucleo.mostrar-barra-modulos` sin ventana. Pruebas en `BarraModulosTest` (20): primera
  vez → ventana; segunda → sin ventana y con lo guardado; cancelar → vuelve; todo desmarcado → no cuenta; nuevo inicio
  de sesión (por la pantalla de entrada) → vuelve. `composer check` en verde (1304). Siguiente: paso 6.
- 2026-10-21 — Paso 6 hecho. `interfaz.md`: «Inicio» (solo permitidos, mismo origen que la barra, pestañas por
  ancho), «Barra de módulos» y «Pantalla angosta» en lugar de «Cambio de modo», «Barras» sin «modo paneles», y las
  tres clases internas de Filament (`fi-sidebar`, `fi-topbar-open-sidebar-btn`, `fi-sidebar-close-overlay`) en las
  excepciones de «Vistas propias», con la prueba que las vigila. `ARCHITECTURE.md` («Interfaz»): un solo modo.
  `pendientes.md` («Del negocio»): tablero de inicio por rol (D11) con la idea de «Mien». Mapa: 1.3c y el punto (1)
  de la 1.3d superados, 1.4f al día (casa, pestañas, ventana la primera vez de cada sesión) y la 1.4d con dos
  decisiones del desarrollador (2026-10-21), sin construir: variantes del gran momento por año (sin repetir la del
  año anterior; versículo del catálogo de la ocasión, también rotando) con vista previa de una fecha antes de
  construirlas todas, y plazo por fecha como datos editables en lugar de «sin plazo». `cambios.md`: Común 0.3.3; el
  README del análisis y `bloques/comun.md` pasan a 0.3.3 (el README seguía en 0.3.1). `comun.md` habla de «dos modos»
  en otro sentido (CRM y ERP en una sola aplicación, ADR 0015): no se toca. Siguiente: paso 7.
- 2026-10-21 — Paso 7. El desarrollador revisó a la vista: escritorio, celular a 360 px, modo oscuro y «reducir
  movimiento», todo bien. Revisión de `revisor-arquitectura`: sin bloqueantes; atendido:
  (1) La ventana «Elegir módulos» calculaba los ocultos contra lo permitido **al guardar**: un módulo permitido con la
  ventana abierta quedaba oculto, justo lo que D4 dice evitar. Ahora se calcula contra lo mostrado **al abrir** (campo
  de apoyo `mostrados`); `AccionFilament::desde()` gana `$deApoyo` (campos solo de presentación, `dehydrated(false)`,
  documentado en `codigo.md`). Prueba: permitir un módulo con la ventana abierta y aceptar → no queda oculto.
  (2) `MigracionesTest` prueba en la base `es_barra_modulos_visible NOT NULL DEFAULT false`.
  (3) Prueba con dos personas: el interruptor y los ocultos de una no tocan los de la otra.
  (4) Docblock de `BarraModulos::prender()`: las dos acciones van en transacciones separadas (aceptado: preferencias
  propias; prenderla otra vez lo completa).
  (5) El CHECK de `modulos_ocultos.clave_modulo` repite el tope de 40 (`{0,39}`), como `CajaModulo`.
  (6) La validación de `ocultar()` antes del bloqueo: sin cambio (depende de permisos, no de las filas bloqueadas).
  `composer check` en verde (1307). Plan movido a `docs/planes/completados/` y 1.4f ✅ en el mapa. El PR lo abre el
  desarrollador. **Base de desarrollo:** el CHECK cambió en `2026_10_21_000001`; reconstruirla con
  `php artisan migrate:fresh --seed`. **Siguiente:** planear las preferencias por usuario en una sesión nueva, con
  rama desde main cuando la 1.4f esté fusionada; después, «Mis datos» y el directorio del equipo.
