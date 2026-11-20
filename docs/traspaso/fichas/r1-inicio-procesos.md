# Ficha · El inicio por procesos

- **Estado:** en curso (ronda de cambios de Sebastián; espera su «así me gusta»)
- **Versión:** v14
- **Tramo de la ruta:** ronda de cambios 1 (reorganizar el sistema por procesos)
- **Fuentes del diseño:** las decisiones de Sebastián del 19 y 20 de noviembre (MV-016 a MV-035); el inicio de cajas de
  siempre (`docs/convenciones/interfaz.md`, «Inicio») y la pantalla de Catálogos para las cajas internas.

## Qué hace

El sistema se ve como el mapa de procesos de Techquk, no como sus módulos técnicos. El inicio, centrado, tiene la
ventana del saludo, el saludo, la frase y la estrella de Mien; debajo, cuatro franjas (Estratégicos, Misionales, De
Apoyo y Plataforma) con 11 cajas de proceso. Cada caja abre su proceso en la caja del medio: arriba, la frase del
proceso y sus cajas internas (las pantallas de siempre) como barra de navegación; debajo, el contenido de la caja
elegida. Cada persona ve solo los procesos en que puede abrir al menos una pantalla y ordena sus cajas a su gusto.

## Decisiones de Sebastián

- **MV-016** (cruza módulos) · Cuatro franjas y 11 cajas de proceso con sus cajas internas y nombres exactos;
  Reglas del negocio y Listas salen de Configuración.
- **MV-017** (cruza módulos) · Mien arriba, a lo ancho, y absorbe «Mi tablero».
- **MV-018** (ficha) · Las franjas se reparten solas en dos columnas de alto parecido; una vacía desaparece; en el
  celular, una columna.
- **MV-034** (ficha) · Todas las cajas de 9 × 7,5 rem, con el nombre en dos líneas como máximo.
- **MV-033** (cruza módulos) · Colores por franja: Misionales turquesa, Estratégicos azul noche, De Apoyo morado,
  Plataforma gris, el oro solo para Mien; en oscuro, cada una en su versión clara (reemplaza MV-019).
- **MV-032** (ficha) · Inicio centrado: ventana del saludo, saludo grande, frase, Mien como estrella sin caja.
- **MV-031** (ficha) · Postventa lleva su ícono propio: el rompecabezas armado con la flecha que sigue.
- **MV-030** (ficha) · Conseguir el negocio lleva su ícono propio: la montaña de dos picos con la bandera en la cima.
- **MV-029** (cruza módulos) · «Ejecutar proyecto», nunca «Ejecutar la obra»; nombres exactos; cada pantalla se
  titula como su caja; la franja Plataforma tiene una sola caja, Sistema, y las cuatro franjas llevan título.
- **MV-046** (cruza módulos) · Sin ruta ni título grande; bajo la frase, el nombre del módulo en Audiowide.
- **MV-045** (cruza módulos) · En las barras laterales internas, la marca de la opción elegida se desliza en vertical.
- **MV-044** (cruza módulos) · La barra de cajas queda quieta entre pantallas; solo se desliza la marca de la elegida,
  en el color de su franja y bien distinta de sus vecinas.
- **MV-043** (ficha) · La carga al cambiar de pantalla es energía que recorre la franja bajo la barra de arriba.
- **MV-042** (cruza módulos) · Un solo azul de resaltado (azul noche; `#6F9BFF` en oscuro) para lo elegido; la franja
  solo en la barra lateral principal, las cajas, la frase y los íconos.
- **MV-041** (cruza módulos) · Al filtrar u ordenar, las filas se deslizan, salen recogiéndose y entran escalonadas.
- **MV-039** (cruza módulos) · Ninguna pantalla lleva texto que explique cómo funciona; quedan datos, etiquetas y
  mensajes.
- **MV-038** (ficha) · Tickets de ayuda lleva su ícono propio: el ticket con audífonos de soporte.
- **MV-037** (cruza módulos) · Cambiar de pantalla sin recargar: solo cambia el contenido; el marco queda quieto.
- **MV-036** (ficha) · La frase debajo de las cajas, chica y apagada; reordenar con una copia que sigue al puntero
  y las demás corriéndose en vivo, como en el muelle.
- **MV-035** (cruza módulos) · Cada proceso: frase en el color de su franja y sus cajas centradas como un solo cuerpo
  que es su navegación; el contenido debajo; al entrar, la caja del medio; orden por persona (reemplaza MV-020).

## Pantallas y navegación

- **Inicio** `/` (v2, MV-032): centrados, la **ventana** del saludo, el **saludo** grande, la **frase** y la
  **estrella de Mien**; debajo, las
  franjas con su título y su frase, repartidas en dos columnas desde 1024 px de ancho; en pantallas más chicas, una
  sola columna en el orden Estratégicos, Misionales, De Apoyo, Plataforma.
  - **El reparto:** se estima el alto de cada franja como su título (1,3 filas) más sus filas de tres cajas; de los
    repartos posibles gana el de menor diferencia entre columnas y, a igual diferencia, el que deja las franjas en
    orden. Ejemplo con todos los permisos: Estratégicos (1 caja, 2,3) + Misionales (5 cajas, 3,3) = 5,6 a la
    izquierda; De Apoyo (4 cajas, 3,3) + Plataforma (1 caja, 2,3) = 5,6 a la derecha.
  - **Una franja sin cajas** para esa persona no aparece, ni su título. Ejemplo: el usuario comercial de la demo ve
    Misionales (Conseguir el negocio) y De Apoyo (Presupuestar y cotizar, Directorio y catálogos) a la izquierda y
    Plataforma (Sistema) a la derecha; no ve Estratégicos.
  - **Cada caja de proceso** responde, al dejar el mouse, la primera pregunta de cada una de sus cajas internas, hasta
    tres. Cada una abre su portada en otra pestaña en escritorio, como siempre.
- **Un proceso (MV-035)** `/procesos/<clave>` lleva a su **caja del medio** en el orden de la persona: de 3 cajas la
  2.ª, de 4 la 2.ª, de 5 la 3.ª, de 6 la 3.ª (Finanzas abre «Flujo de caja y conciliación»; Postventa, de 2, «Tickets y
  series»; Abastecer, de 4, «Órdenes»; Conseguir el negocio, «Oportunidades»). Un enlace (Mien, la barra lateral, un
  aviso) abre directo la caja de ese enlace.
- **Arriba de cada pantalla del proceso** (también en sus fichas, como `/oportunidades/1`), centrados:
  - **La frase** del proceso (MV-036: **debajo de las cajas**), una línea de 15 px en cursiva, con el color de texto de
    su franja mezclado a la mitad con gris (más apagado; en oscuro, con el gris claro). Sale del parámetro
    `procesos.frase_<clave>` (Configuración → Parámetros); las de la propuesta: Techquk «Así va Techquk.»,
    Conseguir el negocio «¡Vamos por esas oportunidades!», Gerencia de proyectos «Cada proyecto, bajo control.»,
    Abastecer «Lo que la obra necesita, a tiempo.», Facturación y cobranza «Lo trabajado, cobrado.», Postventa «El
    cliente sigue siendo nuestro.», Presupuestar y cotizar «Números claros, ofertas que ganan.», Finanzas «La caja en
    orden.», Personas «La gente correcta en cada obra.», Directorio y catálogos «Todo en su sitio.», Sistema «Lo que
    hace funcionar todo.».
  - **Las cajas internas juntas como un solo cuerpo** (un marco redondeado, separadores finos, 9 × 7,5 rem cada una;
    en pantallas chicas pasan a otra fila centradas). La de la pantalla actual va marcada con un fondo suave y una
    barra de 4 px del color de su franja (`aria-current`). Presionar otra muestra su contenido debajo; la barra se
    queda quieta en su lugar.
  - **Reordenar (MV-036):** se presiona una caja y se mueve (más de 6 px; con mouse o con el dedo): una copia con sombra
    sigue al puntero y las demás se deslizan en vivo (200 ms) al lugar que tendrían; al soltar, la copia se desliza a su
    sitio y el clic no abre la caja. Con el foco en una, **Alt + ← / →** la corre un lugar con el mismo deslizamiento. Se guarda al instante para esa
    persona y cambia la caja del medio. **«Volver al orden original»** aparece debajo cuando hay un orden propio.
  - Ya no hay título de portada, descripción, etiqueta de franja, pestañas ni miga.
- **Barra lateral:** un grupo por franja con las cajas de proceso (Mien en «Personal»). Un proceso queda marcado en cualquiera
  de sus pantallas. «Elegir módulos» y «Pantalla al entrar» (preferencias) eligen entre
  procesos.
- **Mien** `/mien`: sus tarjetas de siempre y, debajo, «El tablero de tu rol» con las pestañas de los roles que la
  persona puede ver (`/mien?rol=jefatura|almacen|tesoreria|comercial|gerencia`). `/mi-tablero` lleva a Mien.
- **Lo que salió de Configuración:**
  - **Reglas del negocio** `/reglas-negocio`: pestañas «Aprobaciones» (`/reglas-negocio/aprobaciones`, las reglas de
    aprobación de siempre) y «Parámetros del negocio» (`/reglas-negocio/parametros`).
  - **Listas** `/listas`: «Catálogos» (`/listas/catalogos`) y «Motivos» (`/listas/motivos`).
  - **Configuración** `/configuracion` se queda con lo de plataforma: parámetros de la plataforma, tipo de cambio,
    estados, numeración, alertas, IA, salud y configuración inicial.
- **Usuarios** `/seguridad` (antes «Usuarios y organigrama»): usuarios, roles, conflictos e incompatibles. El
  **Organigrama** sale a `/organigrama`, en Directorio y catálogos.
- **Fichas laborales** `/fichas-laborales` (antes «Personas» del flujo 9, que chocaba con `/personas` de Personas y
  empresas y no se podía abrir).

- **Nombres (MV-029):** cada pantalla se titula como su caja: «Lo aprendido», «Convocatorias del SEACE», «Personal
  asignado», «Tareo», «Órdenes», «Tickets y series», «Suscripciones», «Flujo de caja y conciliación», «Rendiciones»,
  «Para el contador» y «Catálogo de productos» (antes decían otra cosa). «Ejecutar la obra» no aparecía en ninguna
  pantalla ni dato de este repositorio: la regla queda para el repo original, donde ese paso del flujo se llama
  «Ejecutar proyecto».

- **Íconos propios de procesos:** Conseguir el negocio usa `tq-montana-bandera` (MV-030): de línea, 24 × 24, trazo
  1,9, `currentColor`, sin texto; Postventa usa `tq-rompecabezas-flecha` (MV-031), con una lengüeta por borde interior
  para que no se empaste. Tickets de ayuda usa `tq-ticket-soporte` (MV-038). Referencias en
  `docs/vibe/referencias/`.

- **La ventana (MV-032):** 168 × 180, arco de medio punto, alféizar, travesaños donde empieza el arco y a media altura
  de la parte recta, parante al centro; marco `#6B4A2E` (5 px; travesaños 3 px), `#A07850` en oscuro. Adentro, recortada
  por la forma, la escena de la **transición del cielo** (t3b, la fase de la hora de la sede): amanecer (sol bajo a la
  izquierda, cielo rosado), día (la de la propuesta), atardecer (sol bajo a la derecha, naranja y morado) y noche (luna
  y estrellas que titilan); con nubes si el clima de la sede es nublado o niebla y además lluvia si llueve. En modo
  oscuro, siempre la noche. El sol sube y baja 4 px en 6 s. El dibujo de una fecha especial se ve dentro de la
  ventana. Quieta con «reducir movimiento» o «reducir animaciones». `aria-hidden`.
- **La estrella de Mien (MV-032):** enlace a `/mien` con `aria-label` («Mien: 6 pendientes · …»); estrella de 112 px
  con degradado `#F9D77E` → `#D9A21F`, contorno dorado del sistema y un brillo en la punta; halo radial de 168 px que
  late en 4 s; tres destellos de cuatro puntas que titilan desfasados (3 s); el número de pendientes arriba a la
  derecha (tinta y blanco en claro; dorado `#F2C14E` y tinta en oscuro). Debajo «Mien» en dorado y el resumen
  («6 pendientes · 6 actividades para hoy o vencidas»; con firmas: «· 2 esperan tu firma»). Al pasar el mouse o con
  foco crece a 1,06 y el halo se enciende. Todo al día: sin halo ni número y más pálida. Los pendientes son la suma de
  lo que registra cada módulo: las firmas que esperan a la persona y sus actividades de hoy y vencidas.
- **Colores por franja (MV-033):** ver la decisión; los títulos de franja usan la versión de texto (4,5:1 o más):
  turquesa `#00788A` y morado `morado-700` en claro. En verano, Estratégicos toma el azul mar (245) y Misionales el agua
  de piscina (195), al revés que antes, para seguir el color de cada franja; Plataforma no tiene color de verano (gris).

- **Altura de las cajas (MV-034):** inicio, portadas y cajas internas (también Catálogos y las demás pantallas de cajas)
  miden 9 × 7,5 rem; con preguntas, la caja activa crece a 11,5 × 8,75. El nombre más largo, «Flujo de caja y
  conciliación», entra en dos líneas («Flujo de caja y» / «conciliación»); ninguno necesita una tercera.

- **Sin recargar (MV-037):** el panel navega sin recargar la página (modo SPA de Filament y `wire:navigate` en los
  enlaces propios: barra de cajas del proceso, estrella de Mien, tarjetas y tablero de Mien). La barra lateral, el
  encabezado y el marco se quedan; el muelle, la agenda, el asistente, el cielo, la ambientación y el guardado del tema
  se conservan vivos entre pantallas (`x-persist`). Mientras carga, la **franja de energía** (MV-043). Las cajas del
  inicio, en escritorio, siguen abriendo su proceso en otra pestaña (plan 1.4f, D10); en el celular, sin recargar.

- **Sin textos explicativos (MV-039):** las cabeceras de las pantallas solo muestran datos: se quitó la explicación y se
  dejó lo que es un número o un estado (p. ej. Cobranzas «Por cobrar S/ … · vencido S/ …», Almacén «Almacén Central ·
  valor del stock S/ … a costo promedio», Tareo «Semana 47 … · 62.0 h · S/ 984.00», Planilla «Periodo 2026-11 · en
  cálculo · neto … · costo empresa …»). Sin subtítulo: ¿Cuánto se ganó?, Contratos, Personal asignado, Catálogo de
  productos, Tickets de ayuda, Facturas de proveedor. También se quitaron 39 explicaciones de ventanas (p. ej. «Se lee
  del XML…», «Pega desde Excel…», «Busca a la persona…») y 8 de secciones (Mis datos, Roles, Vista previa de fechas,
  Líneas del requerimiento, «La empresa» de la cuenta, Falta el gerente general).

- **Filas que se mueven (MV-041):** en cada actualización de una pantalla con filas (las de toda tabla de Filament y
  las marcadas `data-tq-fila`, como las tarjetas del Tablero de pagos), se recuerda dónde estaba cada fila por su clave;
  después, las que siguen se deslizan de su lugar viejo al nuevo (300 ms, suave al final), las nuevas suben 8 px
  apareciendo con 40 ms entre una y otra (hasta 8 escalones) y las que salieron dejan una copia que se desvanece y se
  recoge hacia arriba (220 ms). Quietas con «reducir movimiento» o «reducir animaciones».

- **Azul de resaltado (MV-042):** el tono 400 del color principal pasa a `#6F9BFF` (el azul que Filament usa en oscuro para
  enlaces, pestañas activas, foco y la opción elegida de las barras laterales internas como la de Configuración). Lo
  que usaba el morado o el turquesa para resaltar algo elegido pasa al azul: la tarjeta de proyecto y la de oportunidad
  al pasar el mouse, la columna sobre la que se suelta en el tablero de oportunidades y la fila puesta de su botonera.
  Los filtros activos de Tickets de ayuda, también en azul.

- **La franja de energía (MV-043):** 3 px, fija justo bajo la barra superior, invisible en reposo. Al empezar un cambio
  de pantalla se enciende y se llena de izquierda a derecha (rápido al principio y cada vez más lento, hasta el 88 % en
  2,4 s) con un degradado del azul de resaltado a celeste y un resplandor; dos chispas blancas con estela la recorren
  por lo cargado, desfasadas (1,1 s cada una, en bucle). Al llegar la pantalla, se completa (180 ms), una última chispa
  cruza todo el ancho y se apaga (450 ms). Con «reducir movimiento» o «reducir animaciones», se llena sin chispas. La
  barra de progreso propia de Livewire se apagó (`config/livewire.php`, `navigate.show_progress_bar`).

- **Barra quieta, marca que se desliza (MV-044):** la barra de cajas (con su frase) se conserva al pasar entre
  pantallas del mismo proceso (`x-persist` por proceso); al llegar, la marca —un solo elemento con el fondo de la franja
  al 20 % (26 % en oscuro), un borde fino y una línea de 4 px abajo— se desliza en horizontal hasta la caja de la
  pantalla actual en 250 ms, suave al final; la caja elegida toma el color de texto de la franja y su nombre en negrita.
  También con «atrás». La caja se elige por la dirección más larga que coincide (las fichas marcan su caja). El contenido
  nuevo aparece desvaneciéndose y subiendo 6 px (200 ms). Con «reducir movimiento» o «reducir animaciones», la marca
  salta y el contenido aparece de golpe.

- **Barras laterales internas (MV-045):** en Configuración, Listas, Reglas del negocio y Usuarios, la opción elegida
  ya no lleva el fondo de Filament: una marca propia (fondo azul de resaltado al 9 %, 14 % en oscuro, y una barrita de
  3 px a la izquierda) se desliza en vertical desde la opción anterior hasta la nueva en 250 ms; la barra conserva su
  desplazamiento. Solo cambia el contenido de la derecha (sin recargar), que aparece suave. Con «reducir movimiento» o
  «reducir animaciones», la marca salta.

- **El nombre del módulo (MV-046):** debajo de la frase, centrado, el nombre de la caja de proceso en mayúsculas
  («DIRECTORIO Y CATÁLOGOS») en Audiowide (la letra del logo), 22 px (18 px en celular, 16 px bajo 380 px de ancho),
  interlineado 1,2, espaciado 0,06 em, una sola línea, en el color de texto de su franja; 6 px bajo la frase y 28 px
  antes del contenido. Orden: barra de cajas, frase, nombre del módulo, contenido.
- **Sin ruta ni título grande (MV-046):** la miga de pan no aparece en ninguna pantalla. En la pantalla de una caja de
  proceso no aparece el título grande (la caja elegida y el nombre del módulo dicen dónde se está); en una ficha (por
  ejemplo, una oportunidad) se queda el título, que dice qué registro es.

## Operaciones

Ninguna nueva: se reubican pantallas. Los permisos de siempre deciden qué cajas internas ve cada persona.

| Operación | Campos | Valida | Qué produce |
|---|---|---|---|
| Elegir procesos de la barra | procesos ocultos (lista de claves de proceso) | cada clave es un proceso que la persona ve; queda al menos uno | guarda los ocultos de la barra |
| Ordenar las cajas de un proceso | proceso; claves en el nuevo orden (lista; vacía = original) | solo claves de ese proceso | guarda el orden de la persona |
| Pantalla al entrar | «El inicio» o un proceso | el proceso es uno que la persona ve | al iniciar sesión llega a la portada de ese proceso |

## Datos

Tabla nueva `orden_cajas_proceso` (MV-035): `user_id` (FK users, borra en cascada), `proceso` (texto 40),
`claves` (JSON con las claves de las cajas en orden), única por persona y proceso. Parámetros nuevos
`procesos.frase_<clave>` (texto), uno por proceso. Además: `modulos_ocultos.clave_modulo` ahora guarda claves de proceso (las de módulos viejas dejan de
contar solas, porque no coinciden con ningún proceso).

## Reglas y números

- **Qué procesos ve una persona:** los que tienen al menos una caja interna cuyo permiso tiene. Ejemplo: con solo
  `crm.*` y `catalogos.ver`, ve Conseguir el negocio, Presupuestar y cotizar, Directorio y catálogos y Sistema.
- **Parámetros del negocio:** los de los grupos `crm`, `presupuestos` y `tributario`, más los sensibles de dinero
  (`parties.ruc_banco_detracciones`). Los demás (interfaz, adjuntos, avisos, respaldos, salud, tipo de cambio,
  motivos, empresa, general) siguen en Configuración.

## Supuestos sin confirmar

- **Fichas con título (MV-046):** en una ficha (la oportunidad OP-…, el requerimiento) se dejó su título, porque dice qué
  registro es; el título grande se quita solo en la pantalla de cada caja.

- **Qué es «explicativo» (MV-039):** se dejaron los textos de las ventanas que avisan una consecuencia antes de
  confirmar («No se borra: queda en la lista como anulada», «Se pierden los cambios…», «Solo se borra lo que nunca se
  usó…»), las reglas de contraseña al renovarla y las ayudas bajo cada campo (son de los campos, no de la pantalla).
  Todavía no hay íconos de información: lo quitado no se movió a ningún lado.

- **La dirección del navegador** cambia con la caja (cada pantalla conserva la suya, como `/flujo-caja`), así «atrás» y
  los enlaces funcionan; no se anidaron bajo el proceso (Sebastián: no le importa ver direcciones).

- **«Parámetros sensibles»** se tomó como los parámetros que deciden cómo trabaja Techquk (los grupos de arriba) más los
  marcados como sensibles de dinero.
- **Organigrama** se abre solo, sin las pestañas de Usuarios; **Usuarios** conserva roles, conflictos e incompatibles.
- **Las preguntas de una caja de proceso** son la primera de cada pantalla, hasta tres.

## Pendientes que decide Sebastián

- Si se renumeran los códigos de los procesos.
- Si contrato y proyecto pueden ser ejes separados.
- Si el uso de las herramientas se carga como costo al proyecto (MV-028).

## Atajos tomados

- **La barra lateral interna** la dibuja Filament en cada pantalla: no se conserva entre pantallas, se vuelve a dibujar
  igual y el script le devuelve su desplazamiento y desliza la marca desde donde estaba (se ve quieta).

- **La animación de filas** es un solo script global que mira cada actualización de Livewire; no distingue un filtro de
  otra acción (una fila que cambia de lugar por cualquier motivo también se desliza). Los tableros de tarjetas fuera del
  Tablero de pagos (p. ej. el de oportunidades) no se marcaron todavía.

- **«El contenido aparece debajo, sin ir a otra página»** se logra con la navegación sin recarga de Livewire (MV-037):
  técnicamente cada caja sigue siendo su propia página, que reemplaza solo el contenido. El repo original (Vue) lo hace como rutas hijas dentro de la vista del proceso.
- **El orden se guarda** por una ruta propia (`POST /procesos/{proceso}/orden`), no por una acción registrada, y no
  aparece en Mis preferencias.

- **Qué proceso agrupa qué pantalla** vive en un enum del núcleo (`Proceso`), no en una tabla configurable.
- **Los procesos se recalculan** varias veces por página (inicio, barra, pestañas) sin guardarlos.
- **El reparto en dos columnas** se estima con tres cajas por fila, no con el ancho real de la pantalla.
- **La ventana** usa la fase del cielo calculada al dibujar la página; si la hora cambia, se ve al recargar.
- **La sección del tablero por rol** se dibuja dentro de Mien como HTML, no como un componente con su propia carga.

## Dónde está en el código

`app/Modules/Nucleo/Navegacion/Franja.php` y `Proceso.php` (el mapa), `Services/NavegacionService.php`
(`procesos`, `procesoDeRuta`, `porFranja`, `enDosColumnas`), `Filament/Widgets/CajasModulos.php` y su vista
`widgets/cajas-modulos.blade.php`, `Filament/Pages/PortadaProceso.php` (entra a la caja del medio), `views/filament/procesos/pestanas.blade.php` (la
frase y la barra), la ruta del orden en `routes/web.php`, el gancho
`PAGE_START` en `app/Providers/Filament/AppPanelProvider.php`, `Filament/Clusters/Listas.php` y `ReglasNegocio.php`,
`Filament/Resources/Parametros/ParametroNegocioResource.php`, `Mien/RegistroSeccionesMien.php`.

## Cambios desde la versión anterior

- **v14** (MV-046): sin ruta ni título grande; el nombre del módulo bajo la frase.

- **v13** (MV-045): la marca de las barras laterales internas se desliza en vertical.

- **v12** (MV-044): la barra de cajas se conserva entre pantallas y la marca de la elegida se desliza.

- **v11** (MV-043): la señal de carga pasa a ser la franja de energía bajo la barra de arriba.

- **v10** (MV-042): un solo azul de resaltado en todo el sistema.

- **v9** (MV-041): las filas se mueven al filtrar u ordenar.

- **v8** (MV-039): se quitan los textos explicativos de cabeceras, ventanas y secciones.

- **v7** (MV-038): ícono propio de Tickets de ayuda.

- **v6** (MV-037): navegación sin recarga en todo el panel; lo vivo se conserva entre pantallas.

- **v5** (MV-036): la frase pasa debajo de las cajas, chica y apagada; el reordenamiento deja el arrastre del navegador
  y usa el del muelle (copia que sigue al puntero y cajas que se corren en vivo).

- **v4** (MV-035): la portada con cajas que llevaban a otra pantalla y las pestañas con miga se reemplazan por la frase
  y la barra de cajas del proceso arriba de cada pantalla; al entrar, la caja del medio; orden por persona.

- **v3** (MV-034): las cajas suben de 6,5 a 7,5 rem de alto, iguales en toda pantalla.

- **v2** (MV-030 a MV-033): íconos propios de Conseguir el negocio y Postventa; el inicio centrado con la ventana del
  saludo, el saludo grande, la frase y la estrella de Mien (ya no hay caja ancha de Mien); los colores por franja en
  claro y oscuro, con un gris nuevo para Plataforma (reemplaza el supuesto de colores prestados de la v1).
