# Plan: ajustes del panel de aplicaciones (1.4z)

- **Estado:** terminado
- **Módulo(s):** Nucleo (interfaz)
- **Fase:** F1
- **Creado:** 2026-10-29 · **Actualizado:** 2026-10-29

## Objetivo

Que cada ícono quede centrado con su nombre, que Gmail y Google Drive tengan sus colores, que el panel abierto se haga a
un lado cuando algo se abre encima y que arrastrar funcione también desde el centro del ícono. Además, sumar dos
pizarras, Meet y Teams, y la opción de abrir en la versión de escritorio las que la tienen.

## Contexto que leí

- Pedido del desarrollador del 2026-10-29:
  1. PowerPoint, y en realidad todos, no están centrados con su nombre;
  2. Gmail y Google Drive no tienen los colores de su ícono original;
  3. todo lo que se abre encima y cubre la cuadrícula hace que el panel vuelva solo al muelle, y lo otro sigue abierto
     («me refiero a todo»);
  4. arrastrar desde el centro del ícono no da la manita: tras mantener presionado un momento (0,3 s, de acuerdo; bajada a 0,15 s el 2026-10-30),
     que se pueda arrastrar;
  5. una pizarra entre las aplicaciones (las dos: Microsoft Whiteboard y Excalidraw), Meet y Teams;
  6. abrir aplicaciones de escritorio: cada persona elige, por aplicación, si la abre en la web o en el escritorio; las
     propias también, eligiendo el programa de una lista cerrada;
  7. el atajo a la herramienta de recortes, no como una aplicación del muelle sino fijo, abajo;
  8. que administración pueda subir el ícono de una aplicación nueva desde la pantalla, para no necesitar código;
  9. poner cualquier aplicación en cualquier lugar de la cuadrícula, no solo en las 3 de arriba (cada una dentro de su
     sección).
- Medido en la app el 2026-10-29 (centro del ícono menos centro del nombre): Google Drive −19,9 px, PowerPoint −14,
  WhatsApp −10,9, OneDrive −8,4, LinkedIn −5,9, Outlook −4,9, Claude −2,9; Excel, Word y Gmail 0. Crece con el largo
  del nombre: el enlace es `display: contents` dentro de la caja; el corrimiento de PowerPoint de la 1.4x (D6) lo
  empeoraba.
- Plan 1.4x (`completados/2026-10-29-interfaz-aplicaciones-principales.md`): íconos en `resources/aplicaciones/`, vista
  `panel-aplicaciones-app.blade.php`, arrastrar con HTML5 (el enlace con `draggable="false"`, por eso desde el ícono no
  se arrastra).
- Filament lanza `x-modal-opened` en `document` al abrir cualquier ventana o panel lateral (la campana, las
  confirmaciones, los formularios). Sus menús desplegables y la búsqueda no lanzan nada.

## Decisiones

- **D1. Centrado:**
  - El enlace de cada aplicación pasa a ser una columna centrada, con el ícono y el nombre como hijos.
  - Se quita el `translate` de PowerPoint (1.4x, D6).
  - Se mide otra vez en la app: la diferencia tiene que ser 0 en todas.
- **D2. Gmail y Google Drive a color:** se usan los logos oficiales de Google, con sus colores: los cuatro de Gmail y
  los tres de Drive.
  - Se copian tal cual; no traen scripts ni enlaces (lo revisa la prueba de íconos).
  - Su origen va en `LICENCIAS.md`.
  - Claude, WhatsApp y LinkedIn ya son de un color en su original y quedan igual.
- **D3. El panel se hace a un lado:** si está en cuadrícula y se abre algo encima, vuelve al muelle (como «—») y lo
  otro sigue abierto. Cuenta como «algo encima»:
  - cualquier ventana o panel lateral de Filament (`x-modal-opened`);
  - un menú desplegable (al presionar su botón);
  - los resultados de la búsqueda (al escribir).

  En el celular, que no tiene muelle, vuelve al botón.
- **D4. Arrastrar desde el ícono:**
  - Al mantener presionado el ícono 0,3 s, el cursor pasa a la manita y la aplicación se puede arrastrar.
  - Un clic corto sigue abriendo la aplicación.
  - Si se arrastra, no se abre.
  - En el muelle, lo mismo: una de las 3 se arrastra sobre otra y cambian de lugar, con la misma acción
    `nucleo.poner-aplicacion-principal`.

- **D5. Catálogo:** se suman, después de Gmail, Teams, Google Meet, Microsoft Whiteboard y Excalidraw, con su versión
  web:
  - `teams.microsoft.com`, `meet.google.com`, la de Whiteboard en Microsoft 365 y `excalidraw.com`.
  - Íconos: Teams, el original de Microsoft (ya está); Whiteboard, el de Microsoft, bajado del mismo lugar que los
    demás; Meet, el oficial de Google a color; Excalidraw, de Simple Icons (CC0) en su color.
  - Excalidraw guarda lo dibujado en ese navegador, salvo que se exporte. Se dice en su descripción.
- **D6. Abrir en el escritorio:**
  - Una página web no puede abrir cualquier programa: solo los que registran su propio enlace (`ms-word:`, `msteams:`,
    `whatsapp:`…). La primera vez el navegador pregunta. Si el programa no está instalado, no pasa nada. Gmail, Google
    Drive, LinkedIn, Meet y Excalidraw no tienen versión de escritorio.
  - Cada aplicación (de Techquk o propia) puede tener un **programa de escritorio**, elegido de una **lista cerrada**
    (enum `ProgramaEscritorio`: Word, Excel, PowerPoint, Outlook, OneNote, Teams, Whiteboard, WhatsApp, Claude, Zoom),
    con su CHECK en la base. Nunca se escribe el enlace a mano: así nadie lo usa para `javascript:` ni `file:`.
  - **Cada persona elige, por aplicación**, «Abrir en la web» o «Abrir en el escritorio», en modo edición. De fábrica,
    en la web, que funciona en cualquier computadora. Lo elegido se guarda (tabla nueva, como sus 3 principales) por una
    acción registrada.
  - **A comprobar en la computadora del desarrollador** (Windows): qué enlace abre de verdad cada programa. Los de
    Office, por ejemplo, abren un documento (`ms-word:ofe|u|…`); para abrir el programa solo puede hacer falta otra
    forma. Lo que no se pueda abrir así no se ofrece.
- **D7.** «Abrir en el escritorio» no registra el uso distinto del de la web: es la misma aplicación.
- **D8. Recortar pantalla:**
  - Un botón fijo de tijeras en el muelle, abajo: después de las 3 aplicaciones y encima del «—».
  - No es una aplicación: no está en el catálogo, no se arrastra, no se oculta y no cuenta entre las 3.
  - Abre la herramienta de recortes de Windows (`ms-screenclip:`), lo mismo que Windows + Mayús + S. Al pasar el mouse:
    «Recortar pantalla (Windows + Mayús + S)».
  - Solo aparece en Windows (por el navegador): en Mac, Linux o el celular ese enlace no hace nada.
  - Se comprueba en la computadora del desarrollador.
- **D9. Ícono subido por administración** (para que sumar una aplicación con su logo no necesite código):
  - En Configuración → Catálogos → Aplicaciones, quien edita el catálogo puede subir el ícono de una aplicación de
    Techquk: PNG, WEBP o SVG, de hasta 200 KB.
  - Se guarda como un adjunto de la aplicación (M7, disco privado), con su propio tipo de adjunto; el ícono de la lista
    pasa a «Propio». La base exige que «Propio» tenga su imagen.
  - La pantalla lo incrusta como imagen (`data:`), como los demás: no se pide nada a otro sitio y, dentro de `<img>`,
    un SVG no ejecuta nada. Aun así, un SVG con `<script>`, atributos `on…`, `<foreignObject>` o enlaces externos se
    rechaza al subirlo.
  - Solo para las de Techquk: las propias de cada persona siguen con la lista de íconos (y la inicial).
  - Agregar un programa de escritorio nuevo (D6) sigue necesitando código: esa lista cerrada es la protección.
- **D10. Tu orden de la cuadrícula:**
  - Cada persona ordena todas sus aplicaciones, no solo las 3 de arriba: arrastra una sobre otra (con los mismos 0,3 s
    desde el ícono) y cambian de lugar.
  - Cada una dentro de su sección: las de Techquk entre sí, en «Más aplicaciones», y las propias entre sí, en «Mías».
    Pasar una a «Tus 3 principales» sigue como en la 1.4x.
  - Tabla nueva por persona (como sus principales), con la unicidad del lugar y de la aplicación en la base, y una
    acción registrada. Sin orden propio, el de administración; una aplicación que se suma después va al final de su
    sección.
  - Cambia el orden de lo que ve esa persona, nunca el de administración ni el de otra persona.

## Pasos

- [x] 1. Centrado (D1) y medición en la app.
- [x] 2. Gmail y Google Drive a color (D2).
- [x] 3. El panel se hace a un lado (D3).
- [x] 4. Arrastrar desde el ícono y en el muelle (D4).
- [x] 5. Catálogo: pizarras, Meet y Teams (D5).
- [x] 6. Recortar pantalla (D8) y abrir en el escritorio (D6): lista cerrada, columna y CHECK, elección por persona y acción; comprobado en
  Windows.
- [x] 7. Ícono subido por administración (D9).
- [x] 8. Tu orden de la cuadrícula (D10).
- [x] 9. Pruebas, prueba en la app, revisión de arquitectura, `composer check` y cierre.

## Criterio de aceptación

- En la app, cada ícono y su nombre comparten el centro (diferencia 0).
- Gmail y Google Drive se ven con sus colores.
- Con la cuadrícula abierta, abrir la campana, una ventana, un menú o la búsqueda la devuelve al muelle sin cerrar lo
  otro.
- Tras 0,3 s presionando el ícono, se arrastra; un clic corto la abre.
- Whiteboard, Excalidraw, Meet y Teams están en el panel de todos.
- En Windows, las tijeras del muelle abren el recorte de pantalla; en otro sistema no aparecen.
- Administración agrega una aplicación con su logo subido desde la pantalla, sin código; un SVG con script se rechaza.
- Una persona mueve Word delante de Excel en «Más aplicaciones» y lo ve así al volver; otra persona sigue viendo el orden
  de administración.
- Quien elige «escritorio» en Word (con Word instalado) lo abre en el programa; quien no, en la web. Un enlace de
  escritorio que no está en la lista no se puede guardar.

## Bitácora

- 2026-10-29 — Plan escrito con las respuestas del desarrollador. Rama `interfaz-panel-ajustes`, apilada sobre
  `avisos-solo-leer` (1.4y, cerrándose) para no sacar la app de su rama.
- 2026-10-29 — El desarrollador sumó a la 1.4z: pizarras (Whiteboard y Excalidraw), Meet, Teams y abrir en el
  escritorio (por persona y por aplicación; las propias, de una lista cerrada). D5 a D7 y pasos 5 y 6.
- 2026-10-29 — El desarrollador sumó el atajo a la herramienta de recortes, fijo abajo en el muelle (D8).
- 2026-10-29 — El desarrollador sumó el ícono subido por administración (D9), paso 7.
- 2026-10-29 — Pasos 1 y 2. El enlace de cada aplicación pasa de `display: contents` a una columna centrada
  (`.tq-apps-enlace`); se quita el `translate` de PowerPoint. Medido en la app: diferencia 0 en las diez (una primera
  medición dio lo de antes por la vista guardada en caché). Gmail y Google Drive: los logos oficiales a color de Google,
  copiados tal cual salvo un `viewBox` cuadrado con margen; `LICENCIAS.md`. Pruebas en `PanelAplicacionesPantallaTest`:
  la columna centrada sin corrimientos, y Gmail (4 colores) y Drive (3) no de uno solo.
- 2026-10-29 — Paso 3. En el panel, `hacerseAUnLado()` (cuadrícula → muelle; en el celular, al botón) al recibir
  `x-modal-opened` de Filament, al presionar un `.fi-dropdown-trigger` y al escribir en `.fi-global-search`. Probado en la
  app: la campana, el menú de la foto y la búsqueda la devuelven al muelle y siguen abiertos; escribir en el buscador
  del propio panel no. Prueba en `PanelAplicacionesPantallaTest`.
- 2026-10-29 — Paso 4. El enlace y los íconos del muelle son arrastrables (la imagen no: `draggable="false"`); al
  presionar empieza la cuenta de 0,3 s (`presionar`) y el cursor pasa a la manita; un arrastre que empieza antes se
  cancela en `dragstart`, así un clic o un movimiento rápido siguen abriendo la aplicación. Cada una del muelle recibe lo
  que se suelta y cambian de lugar (`soltar(lugar)`, la misma acción). **Tropiezo atrapado al probar en la app:** al
  empezar el arrastre, el navegador avisa que el puntero salió del ícono antes del `dragstart`, y eso borraba la marca;
  ahora salir solo detiene la cuenta y la marca se borra al soltar el botón o al terminar el arrastre. Probado en la
  app: muelle reordenado, Gmail desde el ícono al lugar 2 tras 0,4 s, un arrastre de 0,1 s no mueve nada, un clic corto
  abre Word; las principales del usuario de desarrollo se dejaron como estaban. Prueba en `PanelAplicacionesPantallaTest`.
- 2026-10-29 — El desarrollador sumó ordenar toda la cuadrícula por persona, cada una dentro de su sección (D10),
  paso 8.
- 2026-10-29 — Paso 5. Catálogo de 14: Teams (11), Google Meet (12), Whiteboard (13) y Excalidraw (14), con su
  versión web. Íconos: Meet, el oficial de Google; Excalidraw, de Simple Icons; Teams ya estaba. **Desvío:** el de
  Whiteboard no se publica en el CDN de íconos de marca de Microsoft (seis nombres probados, 404) ni en Wikimedia: va un
  dibujo propio y neutro de pizarra, sin imitar el logo; con D9, administración podrá subir el oficial. **Desvío:** la
  tabla `aplicaciones` no tiene descripción, así que lo de Excalidraw (guarda lo dibujado en ese navegador) queda en el
  comentario del seeder, no en la pantalla. Migración `2026_10_29_000004` (íconos en los CHECK). Pruebas del panel
  ajustadas al catálogo de 14 (81 en verde).
- 2026-10-29 — Paso 7. **Desvío de D9:** sin SVG. Los adjuntos los prohíben («nunca, aunque un parámetro las permita»:
  un navegador los abre como página); el ícono subido acepta solo PNG y WEBP, hasta 1 MB (el tamaño de un tipo de
  adjunto va en MB enteros, no 200 KB). `Aplicacion` lleva adjuntos (`ConAdjuntos`: los ve cualquiera, los sube quien
  tiene `catalogos.editar`); tipo de adjunto de fábrica `icono_aplicacion` (del sistema: no se borra). Migración
  `2026_10_29_000005`: `aplicaciones.icono_adjunto_id` y CHECK `icono <> 'propio' OR icono_adjunto_id IS NOT NULL`
  (usar uno de la lista con una imagen guardada vale). `IconoAplicacion::Propio` (no lo usan las propias:
  `paraPropias()`). `IconosAplicacionService::subir()` (agrega o reemplaza el adjunto, fuera de transacción; después la
  aplicación pasa a «Propio») e `imagenes()`; acción `nucleo.aplicaciones.subir-icono`; «Subir ícono» en la fila de
  cada aplicación del catálogo; el panel lo incrusta como `data:`. La columna no es llenable (la pone el servicio):
  `Catalogo::columnasDeServicio()` la declara y `CatalogoDefinicionTest` la descuenta. `IconoSubidoTest` (8).
- 2026-10-29 — Paso 8. Tabla `aplicaciones_orden` (migración `2026_10_29_000006`): posición por persona, una
  aplicación de Techquk o propia, FK compuesta a la propia, y unicidad de la posición dentro de cada sección (índices
  parciales). `AplicacionesService::mover()` (candado por persona; intercambia y guarda el orden completo de la sección)
  y la cuadrícula en su orden (las sin posición, al final). Acción `nucleo.mover-aplicacion`; cada aplicación de abajo
  recibe lo que se suelta (`soltarSobre`); de otra sección o de «Tus 3 principales» no se mueve y avisa. **Tropiezo
  atrapado al probar en la app:** un selector con comillas dobles dentro del `x-data` (que va entre comillas dobles)
  cortaba el atributo y el panel dejaba de funcionar entero; corregido, y una prueba revisa que el `x-data` llegue
  entero. Probado en la app: Gmail sobre Word las intercambia y se guarda; el orden del usuario de desarrollo se dejó
  como estaba. Pruebas del servicio, la base, la acción y la pantalla (104 + 1 en verde).
- 2026-10-30 — A pedido del desarrollador, la espera para arrastrar desde el ícono baja de 0,3 s a 0,15 s (un clic sin
  mover nunca arrastra; la espera solo cubre el clic con la mano temblorosa). Probado en la app: con 0,2 s arrastra, con
  0,06 s no, un clic corto abre.
- 2026-10-30 — Paso 6. El desarrollador probó en su Windows (Windows + R) los once enlaces: todos abren su programa.
  `ProgramaEscritorio` (Word, Excel, PowerPoint, Outlook, OneNote, Teams, Whiteboard, WhatsApp, Claude, Zoom), lista
  cerrada con CHECK; columna `escritorio` en `aplicaciones` y `aplicaciones_propias`; tabla `aplicaciones_en_escritorio`
  (qué abre cada persona en el escritorio; sin fila, en la web) — migración `2026_10_30_000001`.
  `AplicacionesService::abrirEn()` (solo una con programa) y `AplicacionDelPanel::destino()`; acción
  `nucleo.elegir-donde-abrir-aplicacion`; en modo edición, abajo a la derecha, «Abrir en el escritorio / en la web»; el
  enlace al programa va sin pestaña nueva. Programa de las de fábrica en la semilla (solo si falta: no pisa lo elegido);
  las propias lo eligen de la lista al agregarlas o cambiarlas (validado: nunca un enlace a mano). Tijeras en el muelle
  (`ms-screenclip:`), encima del «—», solo en Windows. **Tropiezos atrapados:** (1) tras cada acción, Livewire veía
  cambiado el `x-data` y reiniciaba el panel (se perdía el modo edición): `wire:ignore.self` en su contenedor;
  (2) la semilla ponía los programas antes de crear las aplicaciones (en una base nueva, ninguno). Probado en la app
  (como Windows y como Linux). 185 pruebas tocadas en verde.
- 2026-10-30 — `composer check` (antes de la revisión): pint y phpstan sin errores, 49 migraciones bien, pest 2174/2174.
- 2026-10-30 — Revisión de arquitectura: sin bloqueantes. Atendido: (1) «Propio» sin imagen se rechaza con mensaje antes
  de la base, en el catálogo (`EdicionCatalogosService::validarFormato`) y en las propias (`AplicacionRechazada::iconoPropio`);
  (2) el programa de las de fábrica viene al crearlas en la semilla y, en una base que ya las tenía, lo pone una sola vez
  la migración `2026_10_30_000001`: la semilla ya no lo devuelve si administración lo quitó; (4) un ícono quitado o sin
  archivo no se muestra (cae a la inicial) y las imágenes van en caché por id de adjunto (inmutable); (5) `mover()`
  rechaza si una dejó la sección en medio, en vez de intercambiar con la primera; menores: el aviso al soltar una del
  muelle sobre la cuadrícula, prueba de una propia ajena en el escritorio, comentario de la semilla en su lugar, y el tipo
  de adjunto del ícono con `documento_type`, `extensiones` y `tamano_maximo_mb` fijos. **Riesgo aceptado (3):** la base no
  garantiza que `icono_adjunto_id` sea un adjunto de esa misma aplicación (haría falta una unicidad compuesta en
  `adjuntos`, la tabla de todo el sistema); lo garantiza `IconosAplicacionService`, único que lo escribe. Sin prueba de
  la carrera de `mover()` (no se provoca sin procesos de verdad); el cambio es una guarda simple.
- 2026-10-30 — Cierre. `main` traído (con la 1.4y). `composer check`: pint y phpstan sin errores, 49 migraciones bien,
  pest 2178/2178 (10681 aserciones). Siguiente: retomar la 1.12 en su paso 5 (sus limpiezas al archivo histórico; el
  respaldo con la carpeta del histórico).
