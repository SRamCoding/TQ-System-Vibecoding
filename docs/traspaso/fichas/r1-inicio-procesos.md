# Ficha · El inicio por procesos

- **Estado:** en curso (ronda de cambios de Sebastián; espera su «así me gusta»)
- **Versión:** v4
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
  - **La frase** del proceso, una línea de 28 px en negrita, en el color de texto de su franja (en claro, turquesa
    `#00788A`, morado `morado-700`, azul noche, gris; en oscuro, sus versiones claras). Sale del parámetro
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
  - **Reordenar:** arrastrar una caja sobre otra, o con el foco en una, **Alt + ← / →**. Se guarda al instante para esa
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
  para que no se empaste. Referencias en `docs/vibe/referencias/`.

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

- **«El contenido aparece debajo, sin ir a otra página»** se logra con una navegación normal entre páginas y la
  transición de vista del navegador (`@view-transition`), con la barra fija en su lugar: técnicamente cada caja sigue
  siendo su propia página. El repo original (Vue) lo hace como rutas hijas dentro de la vista del proceso.
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

- **v4** (MV-035): la portada con cajas que llevaban a otra pantalla y las pestañas con miga se reemplazan por la frase
  y la barra de cajas del proceso arriba de cada pantalla; al entrar, la caja del medio; orden por persona.

- **v3** (MV-034): las cajas suben de 6,5 a 7,5 rem de alto, iguales en toda pantalla.

- **v2** (MV-030 a MV-033): íconos propios de Conseguir el negocio y Postventa; el inicio centrado con la ventana del
  saludo, el saludo grande, la frase y la estrella de Mien (ya no hay caja ancha de Mien); los colores por franja en
  claro y oscuro, con un gris nuevo para Plataforma (reemplaza el supuesto de colores prestados de la v1).
