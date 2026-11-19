# Plan: bienvenida con el asistente al centro (1.13b)

- **Estado:** terminado
- **Módulo(s):** Nucleo
- **Fase:** F1
- **Creado:** 2026-11-03 · **Actualizado:** 2026-11-03

## Objetivo

Quien configura el sistema, al entrar por primera vez, no ve su inicio sino una ventana grande al centro con la
configuración inicial, un paso a la vez, desde la que configura cada cosa y avanza. La página Configuración →
Configuración inicial sigue como respaldo, igual que hoy.

## Contexto que leí

- Pedido del desarrollador (2026-11-03, durante la 1.15): «cuando el admin se loguee por primera vez […] lo primero
  que vea es una ventana en todo el medio y que le permita configurar desde allí y avanzar en cada paso […] como
  cuando te logueas por primera vez te planta la configuración inicial en toda la cara».
- Plan de la 1.13 (`docs/planes/completados/2026-10-30-asistente-configuracion.md`): los pasos son un registro
  (`RegistroPasosConfiguracion`, D5) con su pantalla de siempre, resumen y si son obligatorios (la IA no); «listo» lo
  marca la persona (`MarcarPasoListo`, `ReabrirPaso`, D3); el aviso del inicio mientras falten obligatorios (D6);
  permiso `configuracion.inicial` (D7). La página mete la pantalla de cada paso en un panel deslizante, con enlaces
  que se abren ahí mismo (`abrirEnPanel`) y la flecha para volver; la vista previa del encabezado de la 1.15 vive ahí.
- `RespuestaDeIngreso` decide adónde llega la persona al entrar. `GranMomentoEnPantalla` (1.4d) es el precedente de
  algo que se dibuja encima de cualquier pantalla al entrar, con «lo vi» guardado en la base.
- `interfaz.md`: «nunca nada encima del contenido» (salvo el panel de aplicaciones y el gran momento). La bienvenida
  sería otra excepción, anotada ahí.
- `pendientes.md`: nada bloquea. No toca el organigrama (en espera): solo lo abre como un paso más.

## Decisiones (aprobadas por el desarrollador el 2026-11-03, una por una)

- **D1. Cuándo aparece:** en cada ingreso mientras falten pasos obligatorios, hasta que la persona presione «Seguir
  después desde Configuración», que la apaga para ella (queda en la base, con acción registrada; desde la página de
  Configuración inicial se puede volver a encender). La ✕ o Esc la ocultan solo hasta el próximo ingreso (marca de
  la sesión, como la del logo).
- **D2. A quién:** a todos los que tienen `configuracion.inicial`, cada uno por su cuenta. Los pasos listos son de
  todos (como hoy); «Seguir después» es de cada persona.
- **D3. La ventana:** grande, al centro, con el fondo atenuado. Arriba, «Te damos la bienvenida. Configuremos
  Techquk» (neutro: no se sabe quién entra), «Paso 3 de 10 · Organigrama» y una fila de puntos, uno por paso (verde
  si está listo; se puede saltar a cualquiera); en medio, la pantalla de siempre del paso; abajo, «Seguir después»,
  «Anterior», «Saltar» y «Listo, siguiente». En el celular, a pantalla completa.
- **D4. Botones:** «Listo, siguiente» marca el paso como listo (`MarcarPasoListo`) y avanza; «Saltar» avanza sin
  marcar; «Anterior» retrocede. Si el paso ya está listo, el botón dice «Siguiente» y no marca nada; desmarcar es
  «Reabrir», como hoy.
- **D5. Al final:** una pantalla de cierre con los pasos hechos y los que faltan (cada pendiente con su botón para
  ir). Con todos los obligatorios listos, «Ir al inicio» y no vuelve a salir; si faltan, «Seguir después» lleva al
  inicio y la ventana vuelve en el próximo ingreso (D1).
  *Precisado el 2026-11-03 (el desarrollador eligió la recomendada):* en el cierre el botón dice siempre «Ir al inicio»;
  si faltan pasos, solo cierra y la ventana vuelve en el próximo ingreso. «Seguir después desde Configuración», en el
  pie, es el único que la apaga: cada nombre, un solo efecto.
- **D6. Cómo se arma:** encima del inicio atenuado, solo en la primera pantalla tras iniciar sesión, como el gran
  momento (`AppPanelProvider`, render hook). La parte que mete la pantalla de un paso (enlaces que se abren ahí,
  flecha para volver, «Elegir la ficha de Techquk», vista previa del encabezado de la 1.15) sale de la página del
  asistente a una pieza común que usan el panel deslizante y la ventana: nada se duplica. Tercera excepción a «nada
  encima del contenido», anotada en `interfaz.md`.

## Pasos

- [x] 1. Decisiones D1–D6 aprobadas; mapa con la fila 1.13b.
- [x] 2. **La pieza común:** sacar de `ConfiguracionInicial` lo que muestra la pantalla de un paso a un componente
      propio, usado por el panel deslizante. Las pruebas de hoy (`ConfiguracionInicialTest`) siguen en verde sin
      cambiar lo que comprueban.
- [x] 3. **Datos y acciones (D1):** quién apagó la bienvenida (tabla con `user_id` único e indexado, quién y
      cuándo), acciones registradas «Seguir después» y «Volver a mostrar la bienvenida», y la regla de si se muestra
      en `ConfiguracionInicialService`. Pruebas sin interfaz.
- [x] 4. **La ventana (D3–D5):** componente encima del inicio, marca de la sesión para la ✕ y Esc, foco atrapado,
      quieta con «reducir movimiento», pantalla de cierre; en el celular, completa. Si ese ingreso tiene gran
      momento pendiente, la bienvenida espera al siguiente (no se apilan dos capas). Pruebas del componente.
- [x] 5. Revisión a la vista (claro, oscuro, angosto) con datos inventados.
- [x] 6. Documentos: `interfaz.md` (la excepción y la ventana), mapa (1.13b ✅).
- [x] 7. Revisión de `revisor-arquitectura` y `composer check` en verde.

## Preparado para lo diferido

- **Pasos de otros módulos (etapa 2):** se registran en `RegistroPasosConfiguracion` como hoy y salen solos en la
  ventana y en la página, sin tocar la bienvenida.

## Criterio de aceptación

- Quien tiene el permiso y no terminó ve la ventana en su primera pantalla tras iniciar sesión; quien no tiene el
  permiso, quien ya terminó o quien la apagó, no.
- «Listo, siguiente», «Saltar», «Anterior» y los puntos hacen lo de D4; la pantalla de cierre, lo de D5.
- La ✕ y Esc la ocultan hasta el próximo ingreso; «Seguir después», para siempre (hasta volver a encenderla).
- La página Configuración → Configuración inicial sigue igual.

## Bitácora

- 2026-11-03 — Rama `bienvenida-asistente` desde `main` (con la 1.15 fusionada, PR #69). Plan en borrador.
- 2026-11-03 — El desarrollador aprobó D1–D6, una por una, todas con la opción recomendada. En el título, «Te damos la
  bienvenida» en vez de «Bienvenido» (no se sabe quién entra).
- 2026-11-03 — Paso 2: la pieza común es el trait `Filament/ConfiguracionInicial/MuestraPasoAbierto` (abrir enlaces ahí
  mismo, volver, qué pantalla y si se puede ver, «Elegir la ficha de Techquk», la vista previa del encabezado) y su vista
  `configuracion-inicial/paso-abierto.blade.php` (captura de enlaces y redirecciones, estilos de la pantalla de adentro,
  vista previa). Trait y no componente hijo: el anfitrión sigue siendo el que recibe `abrirEnPanel` y las acciones, así
  que `ConfiguracionInicialTest` queda igual (22 en verde) y la ventana solo pone su marco. La captura de redirecciones
  ahora busca el paso abierto que contiene la petición, no el primero de la página (puede haber dos: página y ventana).
- 2026-11-03 — Paso 3: tabla `configuracion_bienvenidas` (`user_id` único, que es también el índice de la llave;
  `es_apagada`; auditoría e historial de cambios, como `configuracion_pasos`). Acciones `SeguirDespues`
  (`nucleo.configuracion-inicial.seguir-despues`) y `MostrarBienvenida` (`…mostrar-bienvenida`), sin campos: actúan
  sobre quien las ejecuta, con el permiso `configuracion.inicial`. La regla es
  `ConfiguracionInicialService::muestraBienvenida()`: permiso, no apagada y faltan obligatorios; la ✕ y el gran momento
  quedan para la pantalla (duran una sesión). `BienvenidaConfiguracionTest`: 3 pruebas en verde.
- 2026-11-03 — Paso 4: `Filament/BienvenidaConfiguracion` (Livewire con acciones de Filament y `MuestraPasoAbierto`) y
  su vista `configuracion-inicial/bienvenida.blade.php`. La monta `AppPanelProvider::bienvenida()` en `BODY_END`, solo
  si `recienIngresa()` (la marca del logo, de una sola vez: la ✕ y Esc no necesitan marca propia, porque al recargar ya
  no sale) y no hay gran momento pendiente (en modo desarrollo no espera, porque ahí el gran momento no sale solo).
  Empieza por el primer obligatorio que falta. «Listo, siguiente» es `marcar-paso-listo` con `after` que avanza;
  «Reabrir» pasó al trait (página y ventana). Las ventanas de las acciones van dentro de la capa del `x-trap`. En la
  página, «Volver a mostrar la bienvenida» en el encabezado, solo si la persona la apagó. 7 pruebas nuevas; 75 en verde
  con `ConfiguracionInicialTest`, `GranMomentoPantallaTest`, `LogoTechqukTest` y `tests/Arch`.
- 2026-11-03 — Paso 5: revisada la pantalla real con el Chromium de Playwright (dibujada en una transacción que se
  deshace, con un usuario y una empresa inventados): paso de la empresa con su vista previa, organigrama y cierre, en
  claro, oscuro y angosto (390 px, dentro de un marco: Chromium sin ventana no baja de ~500 px). Corregido: en angosto
  la ventana se salía por la derecha (la rejilla con `place-items` crecía con el contenido: ahora flex); y el menú de la
  sección para celular (`fi-page-sub-navigation-dropdown`) salía vacío encima de la pantalla del paso, también en el
  panel de la página: la pieza común lo esconde. Visto también: el rol administrador no trae `parties.ver`, así que
  sin ese permiso el paso de la empresa dice «No tienes permiso» bajo la vista previa (igual que en la página: es lo
  esperado). La base de desarrollo recibió la migración nueva (`migrate`, sin `fresh`).
- 2026-11-03 — Paso 6: `interfaz.md` (la pieza común del paso abierto, la bienvenida como tercera excepción a «nada
  encima del contenido» y su regla de las ventanas de acciones dentro de la capa) y el mapa (1.13b ✅).
- 2026-11-03 — Paso 7: `revisor-arquitectura` sin hallazgos que corregir. De sus sugerencias: se tomó la guarda en
  `BienvenidaConfiguracion::mount()` (a quien no le toca, nace cerrada; prueba nueva) y se ajustó la prueba del cierre
  «Todo listo» para llegar a él desde la ventana. No se tomaron: `es_apagada` sin default y sin `version` (mismo
  criterio que `configuracion_pasos`: estado fijo, sin formulario, bajo candado) ni el aplazamiento por gran momento
  (deja de estar pendiente al marcarse «Lo vi»: un solo ingreso). `composer check` en verde (2323 pruebas) antes de la
  guarda; después, `BienvenidaConfiguracionTest` y `ConfiguracionInicialTest` en verde (33), pint y phpstan.
