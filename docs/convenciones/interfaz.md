# Convenciones de interfaz

## Identidad visual

El sistema usa los colores de la marca Techquk (logo y material de la empresa), no colores genéricos
de Filament ni los de los lienzos de diseño.

| Token | Uso | Valor |
|---|---|---|
| **Azul noche** (`primary`) | Color principal: botones, enlaces, selección, encabezados del panel | `#01006F` |
| **Turquesa** (`info`) | Acento: información, estados neutros positivos, resaltados | `#00DAFF` |
| **Morado** (`morado`) | Acento secundario: avisos suaves, destacados; el área CRM. En modo claro, la orquídea del logo | `#7C6EFF` (claro: `#B35FD0`) |
| **Claro** | Fondos claros y texto sobre el azul | `#F6F0F0` |

**En modo oscuro**, el tono 600 de `primary` (el de los botones) es un azul más luminoso
(`ColoresMarca::principalModoOscuro()`): el azul noche exacto apenas se distinguía del fondo casi negro.

**El modo oscuro es gris carbón, no negro** (plan 1.4t, pedido del desarrollador, 2026-10-28): en `html.dark`, los
grises de Filament que hacen de fondo, tarjetas, relieves y bordes (`--gray-950` a `--gray-600`) suben juntos, de 0,25
a 0,47 de claridad, para que nada quede más oscuro que el fondo (`ColoresMarca::grisesModoOscuro()`, en todas las
pantallas y el ingreso). El texto gris claro sigue en AA sobre el fondo y las tarjetas. Lo verifica `OscuroAtenuadoTest`.

**Los valores son los del logo** (ADR 0017), tomados del PNG que entregó el desarrollador. Se escriben una sola vez,
en `Nucleo\Navegacion\ColoresMarca`: el proveedor del panel los da a Filament y el logo y el agradecimiento del
aniversario los leen de ahí. Si llega el manual de marca con otros códigos, se cambian allí y en ningún otro lugar.

**Letras** (plan 1.4ab, pedido del desarrollador, 2026-11-15, con el prototipo de Proyectos aprobado): todo con la
letra de Filament (Inter), salvo el nombre junto al logo. La frase bajo el saludo, en cursiva.

**El logo y su nombre** (plan 1.4ae, pedido del desarrollador, 2026-11-16): **TECHQUK** va en **Audiowide**, la letra
de la marca (licencia OFL, servida desde `public/fonts/audiowide`), en mayúsculas; volvió tras la 1.4ab, que la había
cambiado por Inter. El ícono va **achatado al 85 %** de su alto. Desde la 1.4af (pedido del desarrollador,
2026-11-17) el **ícono es más grande que el nombre** y cada uno tiene su número: `--tq-logo-alto` (1.5 rem) para el
ícono, del que sale su ancho, y `--tq-logo-nombre-alto` (1 rem) para la mayúscula del nombre, de la que sale el tamaño
de la letra. El nombre va centrado a lo alto del ícono; ya no coinciden arriba y abajo («no importa si no están
alineados»). Para ajustar uno, se cambia solo su número.

**Escala de letra** (plan 1.4ab, `nucleo::filament.tipografia`, en todas las pantallas): títulos de página de 1.5 rem
en negrita y de sección de 0.9375 rem; etiquetas de grupo de la barra lateral chicas, en mayúsculas y espaciadas, en
gris; encabezados de tabla y etiquetas de los datos de una ficha chicos y en gris (el dato manda, no su nombre);
números en cifras tabulares, alineados en columna. La línea bajo la barra superior, fina (media línea en pantallas de
alta densidad). Una pantalla nueva no escribe tamaños propios: usa estos.

**Cuándo se anima el logo** (plan `docs/planes/completados/2026-10-27-interfaz-logo-animacion.md`): adentro, la
construcción completa corre solo en la primera pantalla tras iniciar sesión (marca de sesión que deja
`RespuestaDeIngreso` y toma `AppPanelProvider` una vez), al actualizar la página, al cambiar entre modo claro y oscuro
y al pasar el mouse; al ir de una pantalla a otra queda quieto, igual que al terminar la animación. En el ingreso hace
la **espera**: piezas tenues que laten (crecen y se achican) a ratos, cada vez distinto (golpes, fuerza y descanso al
azar, `tqLogoEspera`), y nunca se construyen; con el mouse se ve la cuadrícula de sus escombros y forcejean como si
quisieran salir; con la pestaña oculta, quietas (pedido del desarrollador, 2026-10-28). Con «reducir
movimiento» o «reducir animaciones», el logo normal y quieto. Lo verifica `LogoTechqukTest`. **Ojo:** el selector de
modo de Filament avisa `theme-changed` al cargar cada página, con el tema que ya había; quien lo escuche para animar
debe comparar con el tema anterior.

## Reglas

- Los colores se declaran **una sola vez**: los de marca en `ColoresMarca`, que el proveedor del panel da a Filament (`->colors([...])`). Las pantallas
  usan los nombres de Filament (`primary`, `info`, `warning`…), nunca un código de color escrito a mano.
- Los colores de **estado** (aprobado, rechazado, vencido) siguen el significado de Filament
  (`success`, `danger`, `warning`), aunque no sean de la marca: un rojo de error debe verse como error.
  El color de un estado de documento se elige de una lista de tonos con nombre (neutro, en curso, por aprobar,
  aprobado, alerta, cerrado, anulado), cada uno ligado a un color de Filament; nunca se escribe un código.
  **«En curso» toma el color del área** de su documento (plan 1.4ab, D4; desarrollador, 2026-11-15): turquesa (`info`)
  en el ERP y el núcleo, morado (`morado`) en el CRM; el área sale de la clave del documento. Los demás tonos no
  cambian. En pantalla, el color de un estado se pide con `Estado::colorEnPantalla()`, no con `colorFilament()`.
- **El color de un estado siempre va junto a su nombre, nunca solo** (accesibilidad: quien no distingue colores, o
  ve la pantalla en blanco y negro, debe poder leer el estado). Vale para la barra de estados, las tablas y las
  insignias (plan 1.5, D10).
- Contraste: texto sobre azul siempre claro; texto sobre turquesa, morado o claro siempre oscuro. Revisar
  modo claro y oscuro.
- **Los lienzos de diseño no mandan en el color.** El diseño de pantallas del CRM del 13 oct 2026
  (`docs/diseno/fuentes/crm-pantallas-2026-10-13/`) usa una paleta verde (`#0F6E56`) y fondo hueso. Esos
  lienzos son fuente para la **estructura** de cada pantalla (qué campos, qué acciones, qué orden); el
  color sale de esta convención. Las fuentes no se corrigen: la diferencia queda anotada aquí.

## Colores del ambiente en toda pantalla nueva (MVP, observación 8.14)

- Toda pantalla nueva usa **las variables del ambiente**, nunca colores fijos: `--tq-fondo`, `--tq-superficie`,
  `--tq-superficie-elevada`, `--tq-borde`, `--tq-texto`, `--tq-texto-suave` y los colores de franja con tinte
  (`--tq-franja-*`, `--tq-oro`, `--primary-600`). Nada de `white`, `bg-white`, `#…` ni `rgb(…)` para fondos, bordes o
  textos.
- Los **colores de estado** propios de una pantalla (coral de vencida, dorado, turquesa, tramos de etapa…) se escriben
  con el toque leve del ambiente:
  `color-mix(in oklch, #HEX calc(100% - var(--tq-tinte-leve, 0%)), var(--tq-tinte, #HEX))`.
- Excepciones: íconos de aplicación, el logo, las ilustraciones y el texto blanco sobre un botón o etiqueta de color.
- Comprobarlo con la hora de desarrollo al amanecer, a media tarde, al atardecer y de noche, en claro y en oscuro.

## Paneles y ventanas (MVP, observación 8.7)

- Los **paneles deslizantes** y las **ventanas** van con **fondo sólido** (`--tq-superficie-elevada`) y una sombra que los
  separa; el vidrio (`--tq-vidrio`) queda para lo pequeño que flota: muelle, globos, menús.
- Lo de atrás: un **panel del costado** lo **oscurece** con negro parejo al 42 % (58 % en oscuro); una **ventana al
  medio** (modal, agenda, confirmación) lo **desenfoca** y aclara un poco, sin oscurecer.
- El **asistente** está **siempre encima de todo** (única excepción, MV-088): nunca se oscurece, desenfoca ni esconde;
  con un panel del costado abierto se corre al otro lado.
- Dentro de un panel, los campos con borde y fondo de campo y texto normal (el gris suave solo para la ayuda); los montos
  en soles (`S/ 20,000.00`); lo desactivado legible (4.5:1) con su etiqueta («más adelante»).

## Inicio

Un solo modo (plan 1.4f, `docs/planes/completados/2026-10-20-interfaz-modo-unico.md`): el inicio
siempre es el de cajas, con o sin barra lateral.

- **Las cajas visten la estación** (plan 1.4r, `docs/planes/completados/2026-10-28-interfaz-cajas-estacion.md`):
  primavera fresca, verano intenso (la caja entera en el color del área), otoño cálido con marca de agua, invierno
  escarchado. Solo la estación (hemisferio sur, `Temporada`): las fechas especiales no las tocan. Solo el inicio, no la
  barra lateral.
  - **El matiz es siempre el del área** (salvo en verano, abajo) y sale de `ColoresMarca::matizDeArea()`; por estación cambian solo claridad y
    croma (`EstiloEstacion`), que se arman por área: una caja nueva no declara nada. Ningún color se escribe a mano en
    la vista.
  - **Colores que la pantalla muestra:** la croma se recorta a la máxima de sRGB para esa claridad y ese matiz. El
    Núcleo, más apagado y oscuro en los tonos medios, para que siga siendo azul noche.
  - **El nombre se lee (AA, 4,5:1)** en las cuatro estaciones y los dos temas, medido donde cae dentro del degradado.
    Ningún brillo o adorno pasa detrás del nombre.
  - **Livianas** (1.4t, «lo noto pesado»): poca croma, sobre todo en primavera (casi blanca en claro, la ficha en
    pastel y el ícono en el color del área); en oscuro, siempre algo más claras que el fondo gris carbón, para no
    hundirse. El verano, sin ficha (el ícono suelto) y **con colores de playa que se distinguen** (pedido del
    desarrollador: «que permita diferenciar es lo más importante»): cada área cambia su matiz solo en verano
    (`EstiloEstacion::MATIZ_DE_ESTACION`): Mien sol (85), Núcleo agua de piscina (195), ERP azul mar (245; su turquesa
    quedaba pegado a la piscina) y CRM coral (20), a 30° o más entre dos áreas. En claro, fondo claro y suave (croma
    0,075 como máximo), nombre e ícono en su tono oscuro y **el sol que le pega**: un destello arriba a la izquierda y un
    reflejo en diagonal (sobre fondo claro y nombre oscuro, el blanco solo suma contraste); en oscuro, croma 0,10 como
    máximo.
  - **El sol les pega** en primavera y verano, en claro: un destello arriba a la izquierda y un reflejo en diagonal,
    más grande y más brillante en verano.
  - **Adornos por caja** (`AdornosDeEstacion`, distintos en cada caja y siempre los mismos para la misma, salen de su
    clave): en otoño, 3 o 4 hojas chicas en la esquina de abajo a la izquierda (ovalada, redondeada o de roble, con tallo y
    nervaduras; sin arce, que chica parecía estrella; rojo, naranja, ámbar,
    ocre, granate o marrón; giro y lugar propios); en invierno, escarcha desde la esquina de arriba a la derecha, a veces
    también desde la de abajo a la izquierda, con cristalitos y un brillo de hielo. Van en `.tq-caja-adornos`, fuera del
    flujo: no ocupan lugar. Encima de ellos, el nombre lleva un halo del color de su fondo.
  - **Íconos con contraste:** trazo 1,9 en todas las estaciones; en otoño e invierno claros, 3:1 o más contra el fondo.
  - **En claro, con relieve** (1.4t, «que se pueda distinguir y que haya el efecto 3D, pero tampoco exagerado»): borde
    fino, luz arriba, una sombra corta al pie por dentro y dos sombras debajo, del color de cada caja; con el mouse se
    levanta un poco más.
  - **En oscuro, fosforescentes** (1.4t, «tampoco exagerado, pero que sí se note»): borde fino y halo con el color del
    área (`--tq-e-neon`) y el ícono que brilla en ese color; con el mouse, algo más. En las cuatro estaciones.
  - **Miden lo mismo:** con ficha (2,75 rem; primavera e invierno), menos aire arriba y abajo para que dos líneas de nombre quepan.
  - **El degradado de un ícono** usa un id propio por caja (`tq-trazo-<clave>`): hay varias en la página.
  - **Estático:** nada se mueve; igual con animaciones reducidas. No cambia lo de la 1.4q (crecer con las preguntas,
    animación del ícono) ni el hover.
  - **Preferencia** «Apariencia según el día» (Apariencia, `apariencia.estacion`), prendida por defecto; la misma
    apaga la luz de la barra y el fondo (1.4ac, abajo). Apagada, como antes: en modo claro, el color del área más intenso de arriba hacia
    abajo y un reflejo de sol (2026-10-26); en oscuro, el tinte suave.
  - **Modo desarrollo:** «Estación» en la barra, un clic por estación, solo en la sesión.
  - Lo verifica `CajasEstacionTest`.
- **El ícono de cada caja va en una baldosa** (plan 1.4ad, imagen del desarrollador, 2026-11-16): redondeada, con el
  color de su área en degradado (más claro arriba a la izquierda), un brillo arriba a la derecha, un filo de luz y una
  sombra suave del mismo color; el ícono en blanco. En todas las estaciones y en oscuro (la caja sigue con su
  estación). Un módulo solo declara su ícono. Lo verifica `CajasBaldosaTest`.
- **La luz del día en la barra y el fondo** (plan 1.4ac, `docs/planes/completados/2026-11-15-interfaz-luz-de-la-barra.md`;
  reemplaza al tinte por estación de la 1.4s y al destello de primavera y verano de la 1.4t). Los números son los de los
  dos simuladores que aprobó el desarrollador el 2026-11-15, tal cual (`LuzDelDia`):
  - **Fondo y barra por temperatura y hora:** frío blanco azulado, templado neutro, calor arena (en oscuro, el carbón
    apenas azulado o tibio); de noche, más frío. El calor es `(°C − 10) / 24`; sin clima, templado.
  - **La luz, arriba a la derecha junto al buscador:** amanecer naranja (al 70 %), día amarillo (más dorado e intenso con
    calor), atardecer coral (pegado al borde). Nublado la agrisa a la mitad; con lluvia casi no hay astro. En oscuro, a
    0,55: resplandece, no ilumina.
  - **De noche, la luna con su fase real** (`FaseLunar`, por la fecha): llena entera; creciente o menguante, una sombra
    del color de la barra deja el borde iluminado (creciente por la izquierda, hemisferio sur); nueva, casi nada. Con
    cielo despejado, 7 estrellas en claro y 9 en oscuro.
  - **Cómo:** la hora y la luna en el servidor; la temperatura la pone el script del clima en `--tq-calor` y el navegador
    mezcla con `color-mix()` la misma cuenta del simulador. Cartas, tablas y botones no cambian.
  - **Contraste:** el texto principal pasa siempre; el gris secundario en claro queda entre 4,1 y 4,6:1 (la barra perla
    de antes, 4,0:1).
  - Se apaga con la preferencia «Apariencia según el día» (antes «según la estación»). Modo desarrollo: la fecha y hora
    simuladas y «Temperatura» (frío 10 °C, templado 22 °C, calor 34 °C). Lo verifica `LuzDelDiaTest`.
- **El clima real ajusta los efectos** (plan 1.4u; la estación la sigue decidiendo el calendario): la vista
  `nucleo::filament.clima`, en el encabezado de todas las pantallas, consulta el clima y la temperatura desde el
  navegador (las mismas reglas de privacidad que el dibujo) y los pone en `<html>` (`data-tq-clima`,
  `data-tq-temperatura`); de noche, la vista de navegación pone `data-tq-noche`. Con la apariencia según el día:
  - **Sol:** `--tq-sol` multiplica los reflejos de las cajas: 1 con despejado de día, 1,35 con calor (30 °C o
    más), 0 con nublado, niebla, llovizna, lluvia, tormenta o de noche; cambia con un fundido de 1,2 s.
  - **Lluvia** (llovizna, lluvia, tormenta): gotas quietas en la barra, como sobre un vidrio. **Niebla:** bruma en la
    barra. Detrás del contenido de la barra.
  - **Frío** (15 °C o menos): un poco de escarcha en las cajas de cualquier estación.
  - Sin clima, todo como si estuviera despejado. En el modo desarrollo, «Clima» y «Temperatura».
  - Los datos del clima se piden **antes** de dibujar su vista: una excepción dentro de una vista limpia la pila de
    componentes y rompe lo que se dibuja después (las cajas del inicio). Lo verifica `ClimaEntornoTest`.
- **La barra superior según la estación** (plan 1.4t): en otoño, cada 45 segundos caen 3 o 4 hojas dentro de la barra, detrás
  de lo que tiene (`isolation` y `z-index: -1`): nunca encima del contenido. Con la pestaña oculta, «reducir movimiento»
  o «reducir animaciones», ninguna. **Es la única animación que se repite** (excepción a «ningún bucle», pedida por el
  desarrollador). Vista `nucleo::filament.barra-estacion` (gancho `TOPBAR_START`); lo verifica `BarraEstacionTest`.
- **La estrella de Mien** se ilumina desde adentro en los dos modos: relleno dorado (translúcido en el oscuro) y halo
  suave (pedido del desarrollador, 2026-10-26).

- **El inicio muestra solo los módulos que la persona tiene permitidos**, sin candados ni cajas apagadas. Quién ve
  qué lo decide un solo lugar, `NavegacionService` (`permitidos()`), el mismo que arma la barra lateral: inicio y
  barra muestran los mismos módulos, en el mismo orden, con el mismo ícono y nombre. Entrar por URL a un módulo sin
  permiso sigue dando 403.
- **Cada caja de módulo muestra el ícono arriba y el nombre debajo, centrados**, y nada más. La descripción
  va en el tooltip de Filament (`x-tooltip`), no en la caja ni en el atributo `title` del navegador.
- **Una caja con preguntas** (plan 1.4q; `CajaModulo::preguntas`, literales) no lleva tooltip: al dejar el mouse
  encima crece su celda (de 9 × 6,5 a 11,5 × 7,75 rem) y empuja a sus vecinas, que quedan a la altura de su centro; con mouse, cada fila reserva ese
  crecimiento (2,5 rem) para que ninguna caja salte de fila; la caja
  muestra, sin el ícono, su nombre y sus preguntas una por una, 3 segundos cada una; al final, su ícono hace su animación (`AnimacionCaja`; sin
  una propia, el pulso) y queda quieto hasta sacar el mouse. Solo con mouse: en el celular, nada. Con animaciones
  reducidas, las preguntas cambian sin movimiento y el ícono no se anima. Un módulo nuevo declara sus preguntas y su
  animación en su `CajaModulo`; las animaciones que mueven piezas piden esas clases en el SVG.
- **Todas las cajas miden lo mismo** (mismo ancho y misma altura), compactas: justas para el ícono y dos
  líneas de nombre, sin espacio sobrante. Un nombre más largo se corta con «…»; nunca agranda la caja.
- **Las cajas se agrupan por área**, en este orden: Personal, Núcleo, CRM y ERP. Cada módulo declara la suya en su
  `CajaModulo` (`area: AreaModulo::…`, obligatoria). Cada sección lleva su título, una frase corta de qué
  reúne (`AreaModulo::descripcion()`) y una línea que la separa de la anterior. Un área sin cajas permitidas
  para la persona no aparece.
- **Cada área tiene su color de marca**, que pinta el borde superior de la caja, su ícono, un tinte suave del
  fondo y el título de la sección: Personal en dorado (`dorado`), Núcleo en azul noche (`primary`), CRM en morado
  (`morado`) y ERP en turquesa (`info`). Así se ven los límites de las cajas también en modo oscuro. Tono 600 en modo
  claro y 400 en modo oscuro. **El morado del CRM sigue al logo** (pedido del desarrollador, 2026-11-09: «guíate del
  ícono»): en modo claro, la orquídea de su pieza morada (`ColoresMarca::MORADO_CLARO`, `#B35FD0`, el 600 de
  `paletaMorada()`), y en oscuro, el morado de la marca (`#7C6EFF`, el 400). Las cajas por estación toman el matiz
  de cada tema (`ColoresMarca::matizDeArea($area, $tema)`). Contraste del título del área en claro: 3,7:1, por debajo
  del 4,5 de texto normal (el morado anterior daba 4,0:1; tampoco llegaba).
- Toda caja se dibuja con la misma vista, `nucleo::filament.widgets.caja-modulo`, dentro de la grilla
  `tq-cajas-modulos` (columnas y filas de tamaño fijo). Un módulo nuevo solo declara su `CajaModulo` (con su
  descripción para el tooltip) en su provider; no dibuja su propia caja. Lo verifica
  `tests/Modules/Nucleo/CajasModulosTest.php`.
- **En escritorio, cada caja abre su módulo en otra pestaña; en el celular, en la misma** (abrir módulos seguidos no
  deja pestañas sueltas). Se decide al tocarla, con el corte de «Pantalla angosta», en la vista común de la caja: un
  módulo nuevo no hace nada. Sin JavaScript, la misma pestaña; con Ctrl, Cmd, Shift, Alt o el botón del medio, decide
  el navegador. El enlace no lleva `target`; lleva `rel="noopener"` y la marca `data-tq-pestana`.

## Barra de módulos

Plan 1.4f. La barra lateral se arma con las cajas (`Filament\BarraModulos`), no con la navegación automática de
Filament.

- **Muestra los permitidos menos los que la persona ocultó**, agrupados por área como el inicio. El módulo abierto
  queda resaltado también en sus pantallas internas (la URL de la caja es el prefijo). Ocultar nunca da acceso: la
  barra solo dibuja permitidos. «Mien» es de todas las personas, así que toda cuenta tiene al menos ese.
- **El módulo abierto se pinta con el color de su área** (pedido del desarrollador, 2026-10-23): Personal dorado,
  Núcleo azul noche (el de Filament), CRM morado y ERP turquesa, en el ícono y el nombre. Los tonos de Filament para
  el azul: 700 en modo claro (600 en el dorado, que es el exacto) y 400 en oscuro. Un módulo nuevo no hace nada: lo
  toma de `data-tq-area`. El ícono de «Mien» va dorado siempre, también sin abrirlo.
- **Se prende o se apaga desde el menú del avatar** («Barra de módulos», con un interruptor típico: apagado, con el
  color de los demás íconos y la perilla a la izquierda; prendido, la pista en el azul del tema —700 en modo claro, 400
  en oscuro— y la perilla blanca a la derecha; íconos propios `tq-interruptor-apagado` y `tq-interruptor-prendido` en
  `Nucleo/resources/svg`; pedido del desarrollador, 2026-10-27, en lugar de la palanca de alarma) y se elige con «Elegir módulos…». Una cuenta nueva empieza sin barra. Todo **al instante, sin
  recargar**: nunca se pierde lo que se estaba escribiendo. La siguiente página llega del servidor igual.
- **La ventana «Elegir módulos» sale al prender la barra solo la primera vez de cada inicio de sesión** y solo cuenta
  si se aceptó (`EleccionDeModulos`, en la sesión de Laravel; la limpia el evento `Login`). Las siguientes veces se
  prende con lo ya guardado. Inicio de sesión es autenticación completa: con captcha o MFA, la limpieza va al último
  paso. Se guarda lo oculto, no lo elegido (un módulo recién permitido aparece solo) y siempre queda al menos uno.
- **Cómo se ve sin recargar:** el servidor dibuja todos los permitidos; los ocultos llevan `tq-oculto` y, con la barra
  apagada, `<html>` lleva `tq-barra-apagada` (un `<script>` en el `<head>`, antes de dibujar: sin parpadeo). Los
  ítems llevan `data-modulo` (como su caja) y `data-tq-barra`; cada área, `data-tq-area`. Al guardar, la acción envía
  el evento `tq-barra-modulos` y el script de `nucleo::filament.navegacion` aplica lo mismo; también envía
  `refresh-topbar` (de Filament) para que el ícono del menú cambie en el acto.
- **Los módulos que recién se muestran entran uno por uno**, de arriba abajo (200 ms cada uno; el paso entre ellos se
  achica para que el último termine antes de 600 ms). Solo al aceptar; al cargar una página, nada se anima. Con
  «reducir movimiento», sin animación.
- Lo verifican `BarraModulosTest`, `NavegacionServiceTest` y `PreferenciasBarraModulosTest`.

## Mien, el tablero personal

Plan 1.4l (`docs/planes/completados/2026-10-23-interfaz-mien.md`). **«Mien»** muestra a cada persona lo
relevante **del negocio**: sus pendientes, sus aprobaciones, sus alertas, los números de su área. **Nunca atajos,
ajustes ni datos de la propia aplicación** (por eso se descartaron los módulos favoritos, D4).

- Es la única caja del área **«Personal»**, la primera del inicio y de la barra, y la ve toda persona con sesión
  (`/mien`). Color dorado: `ColoresMarca::DORADO` en modo claro (ocre, 4,86:1 sobre el fondo) y `DORADO_OSCURO` en
  oscuro.
- **Cada módulo aporta sus tarjetas** con `RegistroTarjetasMien` (`TarjetaMien`: clave, título, permiso obligatorio,
  componente y orden) y un componente que extiende `ComponenteTarjetaMien`, que solo escribe `contenido()` con datos de
  los servicios de su propio módulo. Se ve solo con el permiso de la tarjeta.
- **Cada tarjeta carga aparte**, después de abrir la página (título e indicador mientras tanto). Si falla, dice «No se
  pudo cargar», el error va al registro y las demás siguen.
- Sin tarjetas, el tablero lo dice: llegan con sus módulos (aprobaciones, 1.7; alertas, 1.10). Qué ve cada rol lo
  decide Gerencia (`pendientes.md`, «Tablero de inicio por rol»).

## Modo desarrollo

Plan 1.4i (`docs/planes/completados/2026-10-23-interfaz-modo-desarrollo.md`). **Solo en el equipo del desarrollador.**

- **En producción no hay forma de activarlo** (D7): hacen falta las tres llaves a la vez, `APP_ENV=local`,
  `APP_DEBUG=true` y `TECHQUK_MODO_DESARROLLO=true` (solo en el `.env` del desarrollador). Sin ellas, ni el ítem del
  menú ni la barra se dibujan, sus componentes y la vista previa responden 404, y la sesión se ignora. Con
  `APP_ENV=production` y la variable puesta, se ignora y se registra un aviso crítico. Todo pregunta a
  `Desarrollo\ModoDesarrollo`; lo prueba `ModoDesarrolloTest`.
- Se prende con **«Modo desarrollo»** en el menú de la foto; vive en la sesión y **no escribe en las tablas del
  sistema** (solo en la sesión, que en desarrollo también está en la base, en `sessions`).
- **Barra fija al pie:** la fecha y hora simuladas, «Volver a hoy», «Cambiar:» (cada pieza), «Vista previa» (la de
  fechas, que vive solo aquí) y «Apagar».
- **La fecha simulada solo mueve lo decorativo** (saludo, frase, dibujo, franja, logo, agradecimiento): lo decorativo
  pide la hora a `ModoDesarrollo::ahoraDecorativo()`; el negocio, siempre a `Calendario::ahora()`. El gran momento no
  sale solo ni se marca como visto: se elige y se mira en la vista previa. Con fecha simulada, el saludo tampoco se
  guarda en `saludos_mostrados`.
- **Cambiar en el sitio:** las piezas llevan `data-tq-pieza` (borde punteado dorado) y un clic abre todas sus
  variantes, de cualquier fecha. En el logo, solo el ícono: el nombre sigue llevando al inicio. Una pieza nueva (la
  ambientación de pantalla completa de la 1.4d, los dibujos por piezas de la 1.4e) se suma con una `PiezaDecorativa`
  en `RegistroPiezasDecorativas` y la marca en su vista.

## Patrones de pantalla

Lo que el desarrollador pidió y se repite (2026-10-23). Toda pantalla nueva lo sigue sin que haya que volver a
pedirlo; si una no puede, se pregunta antes.

- **El panel de aplicaciones flota encima, en todas las pantallas** (plan 1.4p, D1 a D9; única excepción, con la
  ambientación de fechas, a no poner nada encima del contenido): abajo a la derecha, alineado al lugar de la burbuja del
  chat (variables `--tq-chat-derecha`, `--tq-chat-abajo` y `--tq-chat-tamano`, que la burbuja de la 1.14 debe usar) y
  sin taparla. El muelle (tus 3 principales, plan 1.4x) y el botón redondo casi no tapan nada; la cuadrícula ocupa de 3 a 3,5 de cada
  10 partes del ancho; en el celular, solo el botón, y la cuadrícula a pantalla completa. El estado vive en la sesión y
  vuelve al muelle en cada ingreso (`PanelAplicaciones`).
- **Tus 3 principales** (plan 1.4x): las del muelle, arriba en la cuadrícula; cada persona las elige arrastrando una
  aplicación sobre uno de los 3 lugares o, en modo edición, con «Poner arriba» (al 3.er lugar), que es la alternativa
  para teclado y celular. Sin elegir, las 3 primeras del orden de administración. Debajo, «Más aplicaciones» (las de
  Techquk, en el orden de administración: se pueden arrastrar hacia arriba, pero no se reordenan) y «Mías». Los íconos que no son de Microsoft van en el color de su marca.
- **Arrastrar en el muelle y en la cuadrícula** (plan 1.4aa, D1): con Pointer Events, igual con mouse y con el dedo, y
  sin espera: el arrastre empieza al moverse 5 px desde que se presiona; si no hubo movimiento, es un clic y abre la
  app. Una copia del ícono sigue al puntero y la que cedería su lugar se corre en vivo (~150 ms); al soltar, la copia
  se acomoda y recién ahí se guarda con la acción de siempre (`ponerPrincipal` o `mover`). Si la acción no procede o se
  suelta en otra sección, todo vuelve a su lugar con el mensaje. El clic que sigue a un arrastre no abre la app. Con
  «reducir movimiento» o «reducir animaciones», solo el cambio de lugar, sin deslizarse.

- **Una pantalla que abre con una sola tabla no basta.** Si reúne cosas distintas, abre con **cajas** (ícono y nombre,
  la descripción en el tooltip), agrupadas por tema y con **un buscador arriba** que las filtra sin acentos ni
  mayúsculas: el inicio, Catálogos. Si es **una sola lista**, abre con una **portada** sobre la tabla: **pestañas de
  texto** con su cantidad para sus divisiones (sin íconos ni cajas), **avisos** de lo que hay que revisar con «Ver
  cuáles», que filtra la tabla en el sitio, y **últimos cambios**: Personas y empresas (1.4m).
- **Lo que se abre desde una caja va en un panel que se desliza desde la derecha, encima**: lo de atrás sigue visible,
  atenuado, y la navegación de la izquierda queda a la vista y usable. Se cierra con la X o con Esc, **deslizándose
  afuera**. **Sin recargar la página**: abrir, cambiar y cerrar pasan por Livewire y la URL sigue el cambio (un enlace
  guardado abre el panel directo; Ctrl+clic, otra pestaña).
- **Una pantalla de siempre también se abre dentro del panel** (asistente de configuración inicial, plan 1.13): se
  monta con `@livewire(...)`, se esconden con CSS su título, su ruta y el menú de su sección, y todo enlace a otra
  pantalla del sistema que se presione adentro se abre ahí mismo, con una flecha para volver. Así no se repiten tablas
  ni formularios. Un botón nunca va dentro de un enlace: el navegador sigue el enlace. Una caja que abre su propio panel
  lleva `data-tq-abre-aqui`: sin eso, el asistente toma su clic y vuelve a abrir la misma pantalla sin hacer nada
  (Catálogos, Alertas, Roles y permisos). Lo de adentro es una pieza común (plan 1.13b): el trait
  `Filament/ConfiguracionInicial/MuestraPasoAbierto` y la vista `configuracion-inicial/paso-abierto`; quien la usa solo
  pone su marco (título, botones, cerrar).
- **La bienvenida de la configuración inicial** (plan 1.13b) es la tercera excepción a «nada encima del contenido»:
  una ventana grande al centro con el fondo atenuado (en el celular, a pantalla completa), solo en la primera pantalla
  tras iniciar sesión, a quien tiene `configuracion.inicial` mientras falten pasos obligatorios y no la haya apagado
  con «Seguir después desde Configuración» (se vuelve a encender desde Configuración inicial). Un paso a la vez, con
  la pantalla de siempre del paso; la ✕ y Esc la cierran hasta el próximo ingreso. No se apila con el gran momento: si
  el ingreso trae uno, la bienvenida espera al siguiente. Es un diálogo (`x-trap`): las ventanas de las acciones van
  dentro de su capa, o el foco atrapado no las alcanza.
- **El mundo 3D de la faja de Oportunidades** (vista «Faja 3D», planes 2.21b y 2.21c, D10 y D11; decisión del
  desarrollador) es la **única parte que se aparta de estos patrones**: la faja, las cajas, los escáneres, la tubería,
  las salas y el despiece de una caja, en CSS 3D (sin Three.js), oscuros siempre, con sus colores de la faja y no de
  `ColoresMarca`. Todo lo demás de Oportunidades sigue el sistema en claro y oscuro: el tablero de gemas (la vista de
  entrada), la línea de tiempo, la lista y, en la faja, la cabecera, los botones, ayudas, avisos, etiquetas, la barra de
  etapas, los diálogos, las tablas y el marco y la ficha del despiece. La interfaz no decide reglas (cada movimiento es
  la acción real); teclado, foco y «reducir animaciones» como en todo el sistema. No es modelo para otras pantallas:
  otra excepción se pregunta antes.
- **La gema de Oportunidades** (`<x-oportunidades::gema>`, plan 2.21c, D3) es un componente reutilizable en SVG con
  un brillo finito, no un bucle: el reflejo pasa 2 veces y los destellos 3, y vuelven a pasar con el mouse o el foco
  sobre la gema o su tarjeta; con «reducir movimiento» o «reducir animaciones», quieta.
- **Montos cortos solo donde el espacio es poco** (plan 2.21c, D16 y D17): son un recurso visual para no recargar la
  pantalla. Tarjetas, encabezados, círculos, cifras, línea de tiempo y faja muestran «S/ 27k», «S/ 1.25M»
  (`MontoCorto`), con el monto exacto en el `title` y para el lector de pantalla (`<x-oportunidades::monto>`). Las
  ventanas laterales que se abren para leer (la ficha del tablero, la cabecera del despiece), las tablas emergentes, la
  Lista y los formularios llevan el monto completo.
- **Plantillas** (ADR 0022; plan 2.11): en la pantalla de un documento, un botón «Plantillas» abre el panel común
  (`nucleo::filament.plantillas.panel`, ícono `square-2-stack`) que se desliza desde la derecha, como el APU: las mías y
  las de la empresa con su versión («v2 · 12/11/2026»), «Guardar como plantilla» con el documento abierto, y por fila
  «Usar», «Actualizar desde este documento», «Cambiar nombre», «Publicar en la empresa» y «Desactivar», cada una solo
  para quien puede. «Usar» no crea nada: lleva a la propuesta editable del documento, y lo creado muestra «Creado desde
  Videovigilancia v3». Sin el permiso del grupo sensible de un tipo, ni la lista.
- **Documentos con versiones: árbol de carpetas** (desarrollador, 2026-11-12). Los documentos con una fila por
  versión (`numero` + `numero_version`: la cotización y el presupuesto con APU) se muestran con una **barra lateral de
  carpetas** que dice de qué cuelgan, con **la versión más reciente siempre primero**. Referencia: la barra «Carpetas ·
  Cuenta › Oportunidad › Cotización › Versión» del diseño del 13 oct
  (`docs/diseno/fuentes/crm-pantallas-2026-10-13/CotizacionesDetalle.dc.html`). Es el patrón que siguen esas pantallas
  cuando se construyan:
  - Cotización: Cuenta › Oportunidad › Cotización › Versión.
  - Presupuesto: Proyecto › Presupuesto › Versión. Mientras no esté vinculado a un proyecto, cuelga de su oportunidad
    (Cuenta › Oportunidad › Presupuesto › Versión); sin ninguno de los dos, en la carpeta «Sin proyecto».
  - Dentro de un número, las versiones van por `numero_version` de mayor a menor, cada una con su estado («versión 2 ·
    aprobado · vigente», «versión 1 · reemplazado»).
  - La carpeta de una oportunidad muestra juntos sus cotizaciones y su presupuesto: el precio y el costo detrás, en el
    mismo lugar.
  - Los filtros de arriba recortan las carpetas que se ven.
- **Las transiciones son suaves, nunca bruscas** (entrar, salir, atenuar), y todas respetan «reducir movimiento» y la
  preferencia «reducir animaciones» (quietas).
- **Al pasar el mouse (o con el foco del teclado), la caja crece con su ícono**: más en el inicio (1,08) que en una
  subparte (1,04, clase `tq-caja-sub`), y queda encima de sus vecinas sin moverlas.
- **Íconos:** un ícono **no significa dos cosas distintas** en todo el sistema; el mismo concepto sí puede repetirlo
  (desarrollador, 2026-11-05; plan 2.20a, N9). Una pantalla y la caja de su módulo comparten el suyo. Lo vigila
  `tests/Arch/IconosUnicosTest.php` en cajas, pantallas, pestañas y catálogos, con dos listas explícitas:
  - **Mismo concepto, permitido** (`ICONOS_MISMO_CONCEPTO`, cada grupo con lo que representa): `building-storefront`,
    una cuenta del CRM (caja y pantalla de Cuentas, pestaña «Resumen» de su ficha y pestaña «Cuenta comercial» de
    Personas y empresas; plan 2.20); `user-circle`, los contactos de una ficha (pestañas «Contactos» de Personas y
    empresas y de una cuenta; antes compartían `users` con Usuarios, desarrollador, 2026-11-06); `identification`, los
    datos de una ficha (pestañas «Datos» de Personas y empresas, Usuarios, Organigrama y Proyectos; la pantalla Mis datos);
    `tq-redes-sociales`, las redes sociales (catálogo y pestaña «Redes»); `building-office-2`, una unidad del
    organigrama (pantalla Organigrama y pestaña «Roles por unidad»). Un lugar nuevo con uno de estos íconos se agrega
    al grupo solo si es el mismo concepto. La regla vale también para los botones de acción, aunque la prueba no los
    lee: la pestaña «Puestos» y el botón «Cambiar puesto» comparten `briefcase` porque son lo mismo.
  - **Deuda, conceptos distintos** (`ICONOS_REPETIDOS_DEUDA`): los que ya existían al escribir la prueba. Se arreglan
    en la mejora visual del final, a cargo de Sebastián, y se sacan de la lista.

  Un repetido nuevo que no está en ninguna de las dos listas hace fallar la prueba. Tienen que **entenderse solos**: si uno necesita explicación, se cambia. En modo oscuro, los de una
  subparte van blancos. Si Heroicons no tiene uno que cuadre, se toma de una colección libre (Lucide, Font Awesome
  Free) con su aviso de licencia dentro del archivo, o se dibuja en `Nucleo/resources/svg` (prefijo `tq-`), y se le
  muestra al desarrollador antes de usarlo.
- **La ruta de arriba siempre está completa**: «Área > Pantalla > Listado» y, con algo abierto, su nombre en vez de
  «Listado».
- **Los nombres no se cruzan con otros conceptos** del sistema (por eso «Tipos de persona y empresa» y no «Roles»).
- **Antes de cambiar una decisión ya aprobada, se pregunta**; si una prueba visual no se puede hacer (por ejemplo, una
  tabla fuera de un servidor real), se le pide al desarrollador que la mire.

## Catálogos

Plan 1.4j (`docs/planes/completados/2026-10-23-interfaz-catalogos.md`). Configuración → Catálogos:

- **Al entrar, una caja por catálogo**, como las del inicio (mismo tamaño, ícono y nombre, la descripción en el
  tooltip), **agrupadas por tema** (`GrupoCatalogo`: Comercio y pagos, Personas y lugares, Proyectos y obras,
  Calendario y saludo) y con **un buscador** que filtra las cajas al escribir, sin acentos ni mayúsculas, y esconde los
  grupos vacíos. Ícono, descripción y grupo viven en el enum `Catalogo`; un catálogo nuevo los declara ahí (una prueba
  lo exige).
- **Cajas de una subparte:** toda caja dentro de un módulo (los catálogos; después, portadas como la de Personas y
  empresas) lleva `tq-caja-sub` y su ícono `tq-caja-sub-icono`: blanco en modo oscuro y, al pasar el mouse o con el
  foco, la caja entera crece 1,04 veces (y su ícono adentro). Las del inicio crecen 1,08, para distinguirlas; la que
  crece queda encima de sus vecinas, sin empujarlas. Quietas con animaciones reducidas. El estilo
  vive en `nucleo::filament.navegacion`, para todas las pantallas.
- **Ningún ícono se repite** (pedido del desarrollador, 2026-10-23): ni entre catálogos, ni con un módulo del inicio, ni
  con una pantalla; una pantalla y la caja de su módulo sí comparten el suyo. Lo exige `CatalogosPantallaTest`. Si
  Heroicons no tiene uno que cuadre, va uno propio en `Nucleo/resources/svg` (prefijo `tq-`): el apretón de manos de
  Personas y empresas (de Lucide, licencia ISC, con su aviso dentro del archivo), la persona con el edificio de
  «Tipos de persona y empresa» y la mano haciendo la «V» de amor y paz de «Frases del saludo» (de Font Awesome Free, licencia CC BY 4.0, con su aviso
  dentro del archivo).
- **Al presionar una, su tabla se abre en un panel** que se desliza desde la derecha **encima de las cajas**, que siguen
  detrás, atenuadas (una que asome se puede presionar para cambiar de catálogo); la lista de Configuración sigue a la
  vista. Título con su ícono, la X para cerrar y todo lo de la tabla (agregar, editar, activar, borrar y, en ubigeos,
  los dos botones del INEI). Esc también cierra, salvo que haya una ventana abierta. Para cambiar de catálogo se cierra
  y se presiona otra caja (o la que asome detrás). `?catalogo=…` abre su panel directo (un enlace guardado); uno que no existe muestra las
  cajas. La ruta: «Configuración > Catálogos > Listado», como las demás pantallas, y, con un panel abierto, su nombre
  en vez de «Listado».

## Selectores de catálogo

Plan 2.0 (`docs/planes/completados/2026-11-05-nucleo-selectores-catalogo.md`, D1 a D5). Todo campo que elige de un
catálogo de M5 es el **selector común**: `Campo::elementoDe`/`listaDe` en una acción (lo dibuja `AccionFilament`) o
`SelectorCatalogo::de(Catalogo::…, 'campo')` en un formulario que no sale de una acción (un alta en pasos). **Nadie
arma un `Select` de catálogo a mano**: `tests/Arch/SelectoresCatalogoTest` falla si aparece
`CatalogosService::opciones` fuera de su servicio. Los filtros de tabla usan `CatalogosService::paraFiltro`.

- **Busca en la base al escribir**, sin acentos ni mayúsculas, hasta 50 resultados, primero lo que empieza con lo
  escrito; al abrir muestra los primeros 50 por código. Un valor agregado en otra pestaña aparece al buscarlo.
- **Lo que el registro ya tiene se ve aunque esté desactivado**, con «(desactivado)», y el formulario se guarda.
- **Ubigeos, por niveles:** al abrir, los departamentos; elegir uno muestra sus provincias, y una provincia, sus
  distritos (con «‹ Todos los departamentos» y «‹ Provincias de …» para volver). Al escribir busca directo en los
  distritos, cada uno con su ruta («Chiclayo — Chiclayo, Lambayeque»). Se guarda el distrito.
- **El «+» junto al selector** agrega sin salir del formulario: solo con el permiso de agregar a ese catálogo (el de su
  acción `nucleo.<catálogo>.crear`) y nunca en un catálogo cerrado (Rubros). Pide el nombre y sugiere el código con el
  formato del catálogo (editable; a mano en los de código oficial: ISO, INEI, SUNAT, CUBSO, fechas), avisa si ya hay
  uno igual o parecido (el mismo parecido y umbral que las fichas de parties) sin bloquear, y deja elegido lo creado.
- **Sin permiso, nada se bloquea:** en las familias de motivos y en los catálogos con «Otro» (orígenes comerciales,
  redes sociales), se elige «Otro» y se escribe cuál es; el detalle aparece solo con «Otro» y es obligatorio. Lo que
  se escribe va a Configuración → Motivos → **«Lo más escrito en «Otro»»** (últimos 12 meses, agrupado sin acentos ni
  mayúsculas), con «Agregar a la lista» para sumar lo que se repite. Un módulo que guarda un detalle de «Otro» de un
  catálogo lo informa con una `FuenteDeOtros` (como `FuenteDeMotivos` en los motivos).

## Portada de Personas y empresas

Plan 1.4m (`docs/planes/completados/2026-10-23-interfaz-portada-personas.md`). Al abrir el módulo:

- **Pestañas por tipo:** «Todos» y una por cada tipo activo del catálogo «Tipos de persona y empresa», con cuántas
  fichas activas lo tienen hoy. Un tipo nuevo suma la suya; filtran la tabla sin recargar.
- **Avisos:** cuántas fichas están incompletas y por qué (las reglas de `Parties/ReglasFichaCompleta`), y cuántas
  podrían estar duplicadas (`Parties/PosiblesDuplicados`: mismo nombre sin acentos ni forma societaria, o el DNI
  dentro de un RUC 10). «Ver cuáles» pone el filtro «Revisar» de la tabla; «Ver todas» lo quita. Los duplicados solo
  se avisan: unirlos espera una decisión pendiente.
- **Últimos cambios:** los 10 más recientes de fichas, de la bitácora (quién, qué, a cuál, cuándo), con enlace.
- En la ficha, la pestaña de los tipos se llama **«Tipos»** (no «Roles»), y otros módulos suman las suyas con
  `RegistroPestanasFicha` (`docs/convenciones/codigo.md`).

## Pantalla angosta

Plan 1.4f, D9 y D10. **Un solo corte:** `BarraLateral::CORTE_ESCRITORIO` (1024 px, el `lg` de Filament, donde su barra
deja de ser un menú desplegable). Toda regla por ancho lo lee de ahí (`consultaCelular()`, `consultaEscritorio()`).

- **En el celular no hay barra lateral** ni su botón de menú, ni sus dos ítems del menú del avatar
  (`tq-solo-escritorio`). Se vuelve al inicio para cambiar de módulo. Las preferencias quedan intactas: solo no se
  aplican. Se esconde con CSS por ancho (girar la tablet cambia al instante) y con `display: none`, para que el
  teclado y los lectores tampoco lleguen.
- **La casa para volver al inicio**, a la izquierda del logo, solo en el celular (`tq-solo-celular`) y nunca en el
  propio inicio: área táctil de 44 × 44 px y `aria-label` «Ir al inicio». La pone una vez el proveedor del panel
  (`TOPBAR_LOGO_BEFORE`). El logo también lleva al inicio.
- Lo verifica `PantallaAngostaTest`; lo demás, a la vista (celular de 360 px, escritorio y tablet girando, con la
  barra prendida y apagada).

## Preferencias

Plan 1.4g (`docs/planes/completados/2026-10-22-interfaz-preferencias-usuario.md`). Cada persona ajusta la
presentación en **«Mis preferencias»** (menú del avatar). Cómo se declaran y se guardan: `codigo.md`, «Preferencias
por usuario».

**Reglas:**
1. **Personalizar solo cambia la presentación**; nunca permisos, datos, montos, decimales, moneda ni reglas del
   negocio.
2. **Ocultar no es seguridad:** esconder un módulo, una columna o un aviso no quita el acceso; mandan los permisos.
3. **Cada preferencia tiene un defecto pensado para quien nunca la cambia:** el sistema se ve y funciona bien sin
   tocar «Mis preferencias».
4. **Se aplican al instante y siguen a la persona a cualquier equipo:** viven en la base, no en el navegador.
5. **Una preferencia que no aplica en un ancho de pantalla se muestra con su nota** («Solo en escritorio»), no se
   esconde.
6. **Una preferencia puede prellenar un campo, nunca decidir por la persona:** el valor prellenado se ve y se
   confirma antes de guardar. Ninguna preferencia imputa un costo o elige un destino sin que la persona lo vea
   (pensado para «proyecto u obra de trabajo» y «almacén por defecto»).

**La pantalla:**
- Se arma sola desde el registro: una sección por grupo, en este orden: Apariencia, Navegación y Avisos (el saludo no
  se configura desde la 1.4aa). Un grupo sin preferencias no aparece; lo que vale para todo un grupo va una vez como
  descripción de su sección (`GrupoPreferencia::descripcion()`; hoy, la campana y el correo en Avisos). **Cada preferencia en su fila:** el nombre a la izquierda y el control a la derecha,
  con su línea de ayuda debajo (en el celular, uno sobre otro). Sí/no → interruptor; opción con pocos valores fijos (hasta
  4) → **botones agrupados**, con la elegida resaltada; opción que depende
  de la persona (sus módulos) o con más valores → desplegable; número → campo con sus límites; lista → casillas.
  Etiquetas cortas, para que los botones quepan en un celular de 360 px (`Automático`, no «Según el sistema»).
- **Guarda al cambiar, sin botón**; los números, al salir del campo. Un rechazo devuelve el control a lo guardado y
  avisa. **«Restablecer»** en cada grupo, con confirmación; no toca los otros grupos.

**Las de apariencia:**
- **Tema** (automático, claro u oscuro; automático sigue al equipo): se guarda en la base y, antes de dibujar, se pasa al navegador, donde
  lo lee Filament (vista `nucleo::filament.apariencia`). Lo elegido con el selector de Filament del menú también se
  guarda (`GuardarTema`). El selector no ve un cambio hecho desde la pantalla hasta recargar.
- **Tamaño de letra** (normal o grande): el tamaño de la raíz (`tq-letra-grande`, 112,5 %); Filament mide en `rem`.
- **Densidad de las tablas** (cómoda o compacta): solo tablas (`tq-compacto`; excepción en «Vistas propias»).
- **Animaciones** (automático o reducir siempre): con «reducir siempre», `<html>` lleva `tq-sin-animaciones`.
  **Toda animación nuestra respeta las dos cosas,** «reducir movimiento» del sistema y esta clase, junto a su regla
  (los dibujos de la ambientación, por su hoja común `ambientacion/estilos`). Lo exige `PreferenciasNuevasTest`.
- **Pantalla al entrar** (Navegación): el inicio o un módulo permitido; solo al iniciar sesión. Si perdió el permiso,
  el inicio; si venía de un enlace concreto, gana el enlace. Con MFA, al completar el último paso del ingreso.

## Mis datos y contraseña

Plan 1.4h (`docs/planes/completados/2026-10-23-interfaz-mis-datos.md`). **«Mis datos»** (menú de la foto)
reúne lo de la cuenta de cada persona; no es una preferencia (el cumpleaños y el teléfono son datos con versión).

- **Mi cuenta**, solo lectura: nombre y documento (de su ficha), correo de acceso, puesto y último acceso. Lo que define
  la identidad y el acceso lo cambia quien administra usuarios o personas, no la propia persona.
- **Mis datos de contacto:** cumpleaños (día y mes) y teléfono (el de su ficha: un solo teléfono por persona), con un
  botón **«Guardar»** y las versiones leídas al abrir. Quien gestiona usuarios también cambia el cumpleaños desde la
  ficha del usuario; los dos caminos usan el mismo servicio.
- **Contraseña:** una sola regla para todo el sistema (`ClaveService::regla()`, registrada con `Password::defaults()`):
  al menos 8 caracteres, con mayúscula, minúscula, número y carácter especial; vale también al restablecerla y para el
  primer administrador (que además pide 12). El cambio voluntario pide un **código de 6 dígitos que llega al correo de
  acceso** (10 minutos, una sola vez, 5 intentos, reenvío como mucho por minuto); no pide la actual. Al cambiarla, esta
  sesión sigue y las de otros equipos se cierran. **Quien entra con una contraseña que no cumple la regla** queda
  marcado y no usa nada más hasta renovarla en «Cambia tu contraseña» (ahí, sin código: acaba de entrar con la suya).
  La contraseña nunca va a la bitácora ni a los registros; sí cuándo se cambió.
- El directorio del equipo va aparte (decisiones de privacidad pendientes de planear).

## Barras

Plan 1.3d (`docs/planes/completados/2026-10-19-interfaz-navegacion.md` al cerrarse).

- **La barra superior tiene fondo propio**, del logo al avatar: perla (`ColoresMarca::PERLA`) en modo claro y un gris
  con el matiz del azul noche (`ColoresMarca::barraSuperiorOscura()`) en modo oscuro. No el azul noche puro: las
  piezas azul noche del logo se perderían. Con la apariencia según el día (1.4ac), las dos con el tinte de la
  temperatura y la hora (`LuzDelDia`).
- **Al pasar el mouse** (o el foco), el ícono de un módulo de la barra lateral y el de una aplicación del muelle crecen
  como el de las cajas del inicio (1,2 en 180 ms; plan 1.4v); quietos con el movimiento reducido.
- **La barra lateral se ensancha o se angosta arrastrando la línea de su borde**, entre 200 y 480 px.
  Soltada por debajo de 140 px queda solo con íconos (el plegado de Filament). **Solo se pliega arrastrando:** la
  flecha de plegar que Filament pone junto al logo está escondida (pedido del desarrollador). Los límites viven en `Navegacion\BarraLateral`.
- **El ancho es de cada persona y lo guarda la base** (preferencia `navegacion.ancho_barra`, `0` = solo íconos), con la
  acción general `nucleo.cambiar-preferencia` al soltar el borde. No sale en «Mis preferencias»; «Restablecer» de
  Navegación lo devuelve a 320 px. Filament guarda en el navegador si la barra está plegada;
  la vista `nucleo::filament.navegacion` lo reemplaza con lo de la base antes de que Alpine lo lea. En pantallas
  angostas no hay borde: la barra se abre encima, como siempre.
- **Al actualizar Filament, revisar a la vista** que la barra plegada guardada se respete y que el borde la pliegue y
  la abra: se apoya en detalles internos que Filament no documenta (las claves `_x_isOpen` y `_x_isOpenDesktop` de
  `localStorage`, la variable `--sidebar-width` y `$store.sidebar.open()`/`close()`). Si cambian, no hay error: la
  barra simplemente deja de recordar si estaba plegada. Las pruebas solo ven que la página los trae.

## Vistas propias

El panel usa el CSS ya compilado de Filament (no hay tema propio con Tailwind). **Una clase de utilidad de
Tailwind que Filament no usa no existe en esa hoja**: la vista se ve sin estilos aunque el HTML esté bien. Pasó con
la tabla del historial (`px-3`, `divide-y`…: columnas pegadas, sin líneas). Por eso, en este orden:

1. **Primero, un componente de Filament.** Una tabla es una tabla de Filament (`Table`, con `->records()` si las
   filas no salen de un modelo, como `TablaHistorial`); una ficha, un infolist; un formulario, un schema. Se ven
   igual que el resto, en modo claro y oscuro, y sobreviven a una actualización de Filament.
2. **Si hace falta HTML propio** (como las cajas del inicio), sus estilos van en la misma vista, en un
   `<style>` con clases propias con prefijo `tq-` y colores tomados de las variables de Filament
   (`var(--primary-600)`…). Nunca utilidades de Tailwind sueltas ni clases internas de Filament (`fi-ta-…`), que
   cambian entre versiones. Excepciones hoy: `fi-section`, la clase del componente sección, que las cajas usan
   para verse como tarjeta, `fi-topbar`, la barra superior, que no tiene otra forma de recibir su fondo, y
   `fi-topbar-collapse-sidebar-btn-ctn`, la flecha de plegar, que Filament no deja quitar de otra forma (plan 1.3d). Si una actualización de Filament la renombra, la barra vuelve al blanco y lo detecta
   `BarrasNavegacionTest` solo a medias: revisarla a la vista. Y tres para esconder la barra lateral apagada o en el
   celular (plan 1.4f, D5 y D9): `fi-sidebar` (la barra), `fi-topbar-open-sidebar-btn` (su botón de menú) y
   `fi-sidebar-close-overlay` (la capa que la cierra). Filament no deja quitar la barra de una página ya dibujada ni
   según el ancho de otra forma. Si una actualización las renombra, la barra apagada se seguiría viendo: lo detecta
   `BarraModulosTest`, que exige que el HTML de Filament las traiga. Dos para la foto de «Mis datos» (plan 1.4o):
   `fi-user-avatar` (la foto del menú, que cambia sin recargar al subir otra) y `fi-fo-file-upload-input-ctn` (el
   círculo del subidor, que va al centro); Filament no deja hacer ninguna de las dos cosas de otra forma. Lo vigila
   `MiFotoYRedesTest`. Y dos para la densidad compacta de las tablas
   (plan 1.4g, D4): `fi-ta-cell` y `fi-ta-header-cell`, dentro de las cuales se achica `--spacing`, la variable de
   Tailwind con la que el CSS compilado de Filament calcula los rellenos (así no hace falta nombrar la clase de cada
   tipo de columna). Si Filament renombra las clases o deja de usar la variable, la densidad compacta deja de verse,
   sin romper nada: lo detecta `PreferenciasNuevasTest`. Además, el tema se apoya en dos detalles no documentados de
   Filament: la clave `theme` de `localStorage` y el evento `theme-changed` de su selector. Si cambian, el tema vuelve
   a vivir solo en el navegador; lo vigila la misma prueba.
3. **Verificar a la vista**, en modo claro y oscuro: las pruebas confirman el HTML, no que el navegador lo pinte.

## Saludo y ambientación de la fecha

Plan 1.4b (`docs/planes/completados/2026-10-18-interfaz-saludo.md` al cerrarse).

- **Ortografía y puntuación de saludos y frases** (pedido del desarrollador, 2026-10-28, «los más altos estándares»):
  coma de vocativo («Buenos días, {nombre}») y nunca entre sujeto y verbo («Quien tiene un amigo tiene un tesoro»); los
  saludos que expresan emoción o deseo, entre signos de exclamación («¡Feliz lunes, {nombre}!»); los de más de una
  oración, con punto final; en francés, espacio que no se separa antes de ! ? : ;. Versículos y citas, con su texto
  exacto. Vale también para lo que se agregue desde Catálogos.
- **El inicio saluda** en vez de decir «Inicio»: el saludo con el nombre de pila y, debajo, la frase con su fuente
  (`GeneradorSaludo`). La pestaña del navegador sigue diciendo «Inicio». Saludos y frases son un catálogo editable.
- **Un saludo natural** (plan 1.4e, `docs/planes/completados/2026-10-23-interfaz-saludo-horario.md`): según el
  horario de la empresa (`Tiempo/HorarioEmpresa`; parámetros `interfaz.horario_inicio`, `horario_fin`, `horario_dias`).
  - **En horario**, el saludo es de la **sesión** (`SemillaSesion`, nueva al iniciar sesión): fijo mientras se navega,
    otro si la sesión cambia de momento del día o entra o sale del horario; mitad casuales («Hola, Ana»), mitad de la
    hora («Buenos días, Ana»; `interfaz.saludo_porcentaje_de_la_hora`). La frase, **una por tramo** (plan 1.4aa, D9): hasta las 13:00 y
    desde las 13:00, estable dentro de cada tramo; las de un momento del día ya no salen.
  - **Idioma y sesión** (plan 1.4aa, D6 a D8): el saludo no se configura. El español manda
    (`interfaz.saludo_porcentaje_espanol`); inglés, francés, portugués o alemán salen de vez en cuando, como mucho una
    sesión al día por persona y nunca dos sesiones seguidas (`saludos_mostrados`, una fila por sesión). La sesión se
    clasifica en primera del día, vuelve o vuelve con actividad (cambios propios de hoy en `auditoria_cambios`, que
    solo registra las tablas con historial), y hay saludos para cada una (`frases_saludo.sesion`).
  - **Fuera de horario** (antes del inicio, desde el fin, días no laborables y feriados), un saludo especial de esa
    situación (`frases_saludo.fuera_de_horario`: temprano, noche, fin de semana, feriado o cualquiera; de noche también
    los «de la noche»), que nota con naturalidad la hora **sin exagerar emociones ni halagar**. La frase,
    `interfaz.saludo_porcentaje_frase_especial` de cada 100 veces una especial tranquila, y si no, la del día. Sin
    especial en el idioma de la sesión, en español.
  - La fecha especial sigue mandando, también fuera de horario.
- **Días de versículo:** cada semana, `interfaz.saludo_dias_versiculo` días de lunes a viernes (4 por defecto), elegidos
  por persona y semana, la frase es un versículo en todos los momentos del día. La fecha especial sigue mandando si no
  tiene versículo propio, y una persona ya no puede apagarlos (solo la empresa, con el parámetro).
- **Tres toques, nada más** (y un cuarto desde la 1.4s: el tinte de la estación en el fondo y la barra superior, ver
  «Inicio»): el dibujo animado junto al saludo (80 px; 64 en el celular), una franja de 4 px bajo la barra superior en
  todas las pantallas y, en cinco fechas, los colores o la luz del logo. **Nunca** nada encima del contenido (las otras excepciones son el panel de aplicaciones y la bienvenida de la
  configuración inicial, plan 1.13b), ni confeti sobre
  formularios o tablas, ni cambiar los colores de botones o estados.
- **Movimiento:** el dibujo y el logo corren al cargar o refrescar la página y en cada cambio entre modo claro y oscuro
  (pedido del desarrollador, 2026-10-26), y quedan quietos; vuelven a correr al pasar el mouse (o al tocarlo).
  Ningún bucle (salvo las hojas que caen en la barra superior en otoño, 1.4t). Con «reducir movimiento», todo quieto.
- **Dibujos por piezas, uno por día (plan 1.4e2):** cada tema arma su dibujo con un fondo, un motivo y, a veces, un
  acompañante (`PiezasDeLaFecha`, `RegistroPiezasDibujo`). Cada pieza dice qué es (`etiquetas`) y qué necesita
  (`requiere`): la tabla de surf necesita mar; la llama, la sierra. El día elige la combinación con un contador continuo
  y un paso fijo, igual para todos y nunca la misma dos días seguidos. El mismo dibujo cambia con la luz de la hora
  (cielo, sol o luna, estrellas) y, si `interfaz.clima_activo`, con el clima del lugar de la computadora (el navegador
  lo consulta a Open-Meteo; nada pasa por el servidor ni se guarda; desde la 1.4u, en todas las pantallas). Una estación: 5 fondos, unos 20 motivos y 6
  acompañantes; una fecha especial, menos. Lo que sirve a varias fechas (la sala, el confeti) va en `piezas/comunes`.
  Un tema sin piezas sigue con sus variantes de `RegistroAmbientaciones`, una por año.
- **Cómo se dibuja una pieza:** una vista en `resources/views/filament/ambientacion/piezas/<tema>/<tipo>-<clave>`, SVG
  en un lienzo de 80 × 80 (el suelo desde y = 60, el motivo al centro, el sol arriba a la derecha), con su CSS, sin
  librerías ni imágenes externas. Las animaciones solo corren dentro de `.tq-corre` y sus clases llevan el prefijo
  `tq-pz-`. Una pieza animada que hay que mover lleva la posición en un grupo exterior: la animación pisa el
  `transform` del grupo que anima. Las fechas religiosas, en silueta y sin rostros.
- **Excepción a «colores solo en el proveedor del panel»:** los colores de cada variante (su dibujo y su franja)
  viven en su vista y en el registro. Con piezas, la franja de una estación sigue la luz (`Ambientacion::COLORES_DE_LUZ`)
  y la de una fecha especial conserva los colores de su tema. Son decoración de fecha, no colores de la interfaz.
- **Es decoración: nunca rompe una pantalla.** Si falla (por ejemplo, falta un parámetro), se registra el error y la
  pantalla sale sin adornos y con «Inicio» (`rescue`).
- Se apaga para toda la empresa (`interfaz.ambientacion_activa`) o por persona («Mis preferencias», grupo Apariencia,
  `apariencia.ambientacion`, desde la 1.4aa).
- **En modo claro, el logo** lleva la pieza celeste más turquesa (`ColoresMarca::TURQUESA_LOGO_CLARO`, `#0FC2C4`) y la morada en orquídea (`ColoresMarca::MORADO_CLARO`, `#B35FD0`), como
  el logo impreso (pedido del desarrollador, 2026-10-26); en modo oscuro y en las fechas con su paleta, los de siempre.
- **El logo** (la T de tres piezas, `LogoTechquk` y `nucleo::filament.logo`) va junto al nombre en la barra y en el
  ingreso. Al pasar el mouse se construye: un bloque blanco se rompe en escombros en tres tandas (arriba, abajo a la
  izquierda, abajo a la derecha), las piezas se colorean y cada una se ilumina desde adentro con su propio color.
  Fechas con logo propio, pocas a propósito: aniversario (luz más fuerte), Navidad (rojo, blanco, rojo y gorro), Fiestas
  Patrias (rojo arriba a la izquierda, blanco arriba a la derecha, rojo abajo, y el escudo del Perú colgando del pecho de
  la T), Año Nuevo (estrella en el medio) y Semana Santa (morado litúrgico y una Biblia en el medio). Cada detalle es
  una vista `logo-detalle-<nombre>`. La pieza blanca lleva un borde fino en modo claro.

## Archivos y campana

- **Pestaña «Archivos»** (ícono `heroicon-o-folder-open`, plan 1.8): en la ficha de cualquier registro con adjuntos. El
  nombre del archivo es su descarga; «Adjuntar archivo» (clip) arriba; «Reemplazar» y «Quitar» por fila, que no salen
  en un documento que ya no se edita. Quitar avisa que queda rastro.
- **Campana** (`heroicon-o-bell`; plan 1.4y, `CampanaAvisos` sobre la de Filament): los avisos **sin leer** de la
  persona, arriba a la derecha, al día cada 30 s. Cada aviso lleva su título, su texto, «Abrir» (si tiene enlace) y
  «Marcar como leído»; arriba, «Marcar todos como leídos»; al pie, «Ver todos mis avisos». **Nadie borra un aviso**:
  sin «Borrar» ni la ✕ (que Filament dibuja dentro del aviso: se esconde y su método no hace nada).
- **«Mis avisos»** (`heroicon-o-inbox-stack`, plan 1.4y): todos los avisos de la persona, con filtro que abre en los
  leídos; «Abrir», «Marcar como leído» o «Marcar como no leído» (vuelve a la campana). Desde el pie de la campana («Ver todos mis
  avisos»); ya no está en el menú de la foto (plan 1.4aa, D2). Los leídos de más de `avisos.dias_leidos` días pasan al archivo histórico.
- **Con avisos sin leer, la campana se enciende** (plan 1.4x, D7): en claro toma el color principal sobre un fondo suave;
  en oscuro, además, brilla. Fija, sin animación. Se reconoce por el contador que Filament pone dentro del botón
  (`fi-icon-btn-badge-ctn`, con `:has()` en `nucleo::filament.navegacion`); `BarrasNavegacionTest` avisa si Filament
  cambia esos nombres.

## Encabezado de los documentos

- **Los datos de Techquk en un documento** salen de `EmpresaService::datos()` y se dibujan con
  `nucleo::empresa.encabezado` (plan 1.15): nunca de la ficha directamente ni escritos a mano. El encabezado es
  **papel**: blanco también en modo oscuro, con la tinta de `ColoresMarca` (`TINTA`, `TINTA_SUAVE`) y el azul noche
  en el nombre, sin variables de Filament (un PDF no las tiene): sus colores salen de `ColoresMarca`, no de
  Filament. El logo va incrustado como `data:`.
- **El logo** es el adjunto de tipo «Logo» más reciente de la ficha (PNG, JPG o WEBP, hasta 2 MB). La vista previa
  está en el paso «Datos de la empresa» del asistente, con lo que falta.

## Mayúsculas

Títulos, etiquetas, botones y pestañas con **mayúscula solo al inicio** y en nombres propios («Personas y
empresas», «Tipos de cambio», «Banco de la Nación»), nunca en cada palabra. Filament pone mayúscula en cada palabra
del nombre de un Resource (`Str::ucwords`): **todo Resource usa el trait `EtiquetasEnEspanol`**, y una prueba
(`EtiquetasEnEspanolTest`) revisa que ninguno quede sin él.
