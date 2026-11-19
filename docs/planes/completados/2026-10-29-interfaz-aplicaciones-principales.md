# Plan: tus 3 aplicaciones principales y la campana encendida (1.4x)

- **Estado:** terminado
- **Módulo(s):** Nucleo (interfaz)
- **Fase:** F1
- **Creado:** 2026-10-29 · **Actualizado:** 2026-10-29

## Objetivo

Que cada persona elija, arrastrando, las 3 aplicaciones de su muelle; que el panel traiga las aplicaciones que se usan en
Techquk; y que la campana se note encendida cuando hay avisos sin leer.

## Contexto que leí

- Pedido del desarrollador del 2026-10-29:
  - el ícono de PowerPoint no está centrado;
  - por defecto, arriba Claude, Excel y WhatsApp; debajo, Word y PowerPoint, y además Outlook, Drive, LinkedIn y Gmail;
  - en lugar de «De Techquk», algo como «las 3 más usadas»;
  - la persona arrastra las aplicaciones para recolocarlas ahí;
  - la campana con avisos se ilumina en oscuro y se enciende en claro, congruente con los tonos.
- Respuestas del desarrollador:
  - las 3 de arriba las elige la persona arrastrando; el conteo de clics deja de decidirlas, pero se sigue contando;
  - Drive: los dos, Google Drive y OneDrive;
  - «Poner arriba» para elegir sin arrastrar;
  - secciones «Tus 3 principales», «Más aplicaciones» y «Mías»;
  - campana encendida fija, sin movimiento.
- Plan 1.4p (`completados/2026-10-27-interfaz-panel-aplicaciones.md`):
  - el muelle eran las 3 más usadas (D5);
  - las aplicaciones de todos las pone administración, con su orden; cada persona oculta las que no usa y suma las suyas
    (D7).
- Código:
  - `AplicacionesService::delPanel()` arma el muelle y la cuadrícula;
  - `PanelAplicaciones` (Livewire) ejecuta las acciones de `Acciones/Aplicaciones`;
  - `IconoAplicacion` incrusta los originales de Microsoft desde `resources/aplicaciones/`;
  - el catálogo de fábrica está en `CatalogosSeeder`: hoy Word 1, Excel 2 y PowerPoint 3.
- `docs/convenciones/interfaz.md`: «ningún bucle» de animación; la campana es la de Filament (`databaseNotifications`).

## Decisiones

- **D1. Tus 3 principales:**
  - Tabla nueva `aplicaciones_principales`, con una fila por persona y posición del 1 al 3. Apunta a una aplicación de
    Techquk o a una propia, una sola de las dos (CHECK).
  - Unicidad en la base: una aplicación no se repite y una posición tampoco.
  - Sin filas, las principales son las 3 primeras del orden de administración.
  - Si una principal deja de estar disponible (la ocultó, la quitó o administración la desactivó), su lugar lo toma la
    siguiente del orden.
  - El muelle muestra estas 3, en su orden.
- **D2. Elegirlas:**
  - Acción registrada `nucleo.poner-aplicacion-principal` (posición, origen y aplicación), sin permiso, porque es su
    propio panel.
  - Si la aplicación ya era principal en otra posición, intercambian lugares; si no, reemplaza a la de esa posición.
  - En pantalla:
    - se arrastra cualquier aplicación sobre uno de los 3 lugares;
    - en modo edición, cada aplicación de abajo tiene «Poner arriba», que la deja en el 3.er lugar.
  - Las de abajo no se reordenan: siguen el orden de administración.
- **D3. El conteo de clics se sigue guardando** (`usos_aplicacion`), pero ya no decide nada.
- **D4. Catálogo de fábrica:**
  - Orden: Claude, Excel, WhatsApp, Word, PowerPoint, Outlook, OneDrive, Google Drive, LinkedIn y Gmail.
  - Enlaces web:
    - `claude.ai`, `web.whatsapp.com`, `mail.google.com`, `drive.google.com` y `www.linkedin.com`;
    - los de Microsoft, por `office.com/launch`, salvo Outlook: `outlook.office.com/mail`, que abre el correo directo.
  - En una base que ya tiene Word, Excel y PowerPoint con el orden de fábrica viejo (1, 2, 3), el seeder los reordena.
    Si administración ya los cambió, no los toca.
- **D5. Íconos nuevos:**
  - Claude, WhatsApp, Gmail y Google Drive salen de Simple Icons 13.21.0 (CC0), de un solo color: el de cada marca.
  - LinkedIn es el de las redes sociales (Material Design Icons, Apache 2.0), con su azul.
  - OneDrive y Outlook ya estaban.
  - Las licencias van en `LICENCIAS.md`.
- **D6. PowerPoint:** el círculo deja más aire a la derecha y se ve corrido. Se ajusta en la pantalla
  (`translate`), sin tocar el original de Microsoft. Lo confirma el desarrollador en su pantalla.
- **D7. Campana encendida, fija:**
  - Con avisos sin leer, en claro el ícono toma el color principal; en oscuro, además, un halo suave de ese color.
  - Sin animación: se cumple «ningún bucle».
  - Se apaga sola cuando no quedan avisos sin leer.

## Pasos

- [x] 1. Tabla, servicio, acción y pruebas (D1 a D3).
- [x] 2. Catálogo e íconos (D4 a D6).
- [x] 3. Pantalla: secciones, arrastrar, «Poner arriba» y muelle; pruebas de pantalla.
- [x] 4. Campana encendida (D7) y su prueba.
- [x] 5. Documentos (`interfaz.md`, `LICENCIAS.md`), `composer check` y cierre.

## Criterio de aceptación

- Una persona sin elección ve Claude, Excel y WhatsApp arriba y en el muelle.
- Arrastrar o «Poner arriba» cambia sus 3, sin repetir ninguna, y no cambia las de nadie más.
- Una principal oculta o desactivada deja su lugar a la siguiente.
- Con avisos sin leer, la campana se ve encendida en los dos modos; sin avisos, normal.

## Bitácora

- 2026-10-29 — Plan escrito con las respuestas del desarrollador. Rama `interfaz-aplicaciones-principales` **desde
  `main`** (pedido del desarrollador: que su PR no arrastre la 1.12, en pausa tras su paso 4; la 1.4x no depende de
  nada de ella). Los avisos (solo marcar como
  leído, página «Mis avisos») van en la 1.4y, que se planea aparte.
- 2026-10-29 — `AGENTS.md` («Cómo trabajar»): verificaciones y commit separados o con `&&`, nunca con `;` (pedido del
  desarrollador).
- 2026-10-29 — Paso 1, y del paso 2 el catálogo y los íconos (las pruebas del servicio los usan). Migración
  `2026_10_29_000002_crear_aplicaciones_principales`: tabla con FK compuesta a la propia (se borra con ella), CHECK de
  una sola aplicación y del lugar 1 a 3, unicidad de lugar y de aplicación por persona; suma los íconos nuevos a los
  CHECK de `aplicaciones` y `aplicaciones_propias`. `AplicacionesService`: `principales()` (cada lugar guardado que sigue
  en su panel; los demás, la siguiente del orden), `ponerPrincipal()` (candado por persona, intercambio si ya era
  principal, guarda las 3 tal como quedan), `delPanel()` sin repetir las principales abajo; el uso se sigue contando.
  Acción `nucleo.poner-aplicacion-principal` y `PanelAplicaciones::ponerPrincipal()`. Catálogo de 10 en
  `CatalogosSeeder`, con el reordenamiento de la base vieja solo si sigue de fábrica (Word 1, Excel 2, PowerPoint 3 y
  nada más). Íconos: Claude, WhatsApp, Gmail y Google Drive de Simple Icons 13.21.0 con el color de su marca; LinkedIn,
  el de las redes con su azul. Pruebas: `AplicacionesPanelTest` y `AccionesAplicacionesTest` reescritas donde esperaban
  el catálogo viejo o el muelle por uso (44 en verde). Las de pantalla se ajustan en el paso 3.
- 2026-10-29 — Pasos 2 y 3. Vista `panel-aplicaciones-app` (una aplicación, igual en las tres secciones): se arrastra
  (`draggable`; el enlace no, para que no arrastre la URL), «Poner arriba» al 3.er lugar en modo edición, «Ocultar» o
  «Cambiar» y «Quitar». La cuadrícula: «Tus 3 principales» con 3 lugares que reciben lo que se suelta (resaltados
  mientras se arrastra), «Más aplicaciones» y «Mías»; ya no dice «De Techquk». Muelle «Tus 3 aplicaciones principales».
  PowerPoint con `translate: 4% 0` (no choca con el `transform` del crecer al pasar el mouse). `LICENCIAS.md` e
  `interfaz.md`. `PanelAplicacionesPantallaTest`: las 4 que esperaban el muelle viejo, corregidas; nuevas: secciones y
  lugares, arrastrar o «Poner arriba» cambia el muelle, el uso no sube una al muelle (71 en verde entre las 3 del panel).
  Falta que el desarrollador pruebe el arrastre y el centrado de PowerPoint en su pantalla: las pruebas no ejecutan el
  JavaScript.
- 2026-10-29 — Paso 4. En `navegacion.blade.php`, la campana con contador (`:has(.fi-icon-btn-badge-ctn)`): en claro,
  `--primary-600` sobre su 13 %; en oscuro, `--primary-400` sobre su 16 % y un halo con `drop-shadow`. Fija. Revisado
  dibujándola con el CSS real de Filament, con y sin avisos, en los dos modos. Prueba en `BarrasNavegacionTest`: las
  reglas están en la página y Filament sigue dibujando esas clases (con avisos, el contador; sin avisos, no). Regla en
  `interfaz.md`.
- 2026-10-29 — Revisión de arquitectura: sin bloqueantes. Atendido: el seeder reordena la base vieja por el modelo (sube
  `version`, deja bitácora: un guardado abierto en Catálogos choca); `ponerPrincipal` convierte la FK rota por una propia
  quitada en otra pestaña en «Esa aplicación no está en tu panel.»; prueba de que elegir las suyas no cambia las de otra
  persona y de la versión al reordenar; frase de `interfaz.md` («se pueden arrastrar hacia arriba, pero no se
  reordenan»); Outlook anotado en D4. Sin cambio, con su porqué: la regla del seeder («nunca pisa lo editado») se cumple,
  porque solo reordena si las 3 siguen como las dejó la fábrica y no hay ninguna más; el índice de `user_id` lo pide la
  convención (toda FK con índice); la FK de `aplicacion_id` sin `ON DELETE` es igual que en `usos_aplicacion` (una de
  Techquk usada se desactiva, ADR 0013).
- 2026-10-29 — Prueba en la app del desarrollador (navegador sin pantalla, usuario de desarrollo): arrastrar Gmail al
  lugar 1 y «Poner arriba» LinkedIn funcionan, también en el muelle; sin errores en el navegador. Corregido al verlo: la
  flecha de «Poner arriba» tapaba el ícono (pasa a la esquina izquierda; «Cambiar» de una propia, abajo); los íconos de
  Simple Icons se veían más grandes que los de Microsoft (margen con `viewBox="-1.5 -1.5 27 27"`); en claro la campana
  encendida casi no se distinguía (ahora además se rellena). El aviso de prueba quedó leído y las principales del usuario
  de desarrollo, borradas (vuelve a las de fábrica).
- 2026-10-29 — `composer check`: 6 fallas. 5 eran de los SVG cambiados con la corrida en marcha (la prueba comparó el
  archivo nuevo con la imagen ya cargada); 1 real: `AplicacionPrincipal` sin alias en el morph map (agregado,
  `aplicacion_principal`). 99 pruebas tocadas en verde; se relanza la corrida completa.
- 2026-10-29 — Cierre. `composer check`: pint y phpstan sin errores, 44 migraciones bien, pest 2110/2110 (10437
  aserciones). Siguiente: la 1.4y (avisos solo para leer y «Mis avisos»), por planear; luego retomar la 1.12 en su paso 5.
