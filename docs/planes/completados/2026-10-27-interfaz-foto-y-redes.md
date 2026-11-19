# Plan: Foto de perfil y redes sociales

- **Estado:** completado
- **Módulo(s):** Nucleo (Parties, usuarios, interfaz)
- **Fase:** F1
- **Creado:** 2026-10-27 · **Actualizado:** 2026-10-27

## Objetivo

Cada persona del equipo puede poner su foto de perfil (se ve en el círculo del menú) y sus redes sociales, eligiendo
red por red quién las ve. Los clientes y proveedores, y cada uno de sus contactos, tienen sus redes en su ficha. Las
redes salen de un catálogo editable con ícono y revisión del enlace.

## Contexto que leí

- Ningún documento de diseño (DBML v2, v4, pantallas del CRM, decisiones) menciona redes sociales ni foto de perfil:
  todo lo de aquí es pedido del desarrollador (2026-10-27).
- Cada usuario es una persona de Parties (`users.party_id`, `completar_users`). Las empresas tienen contactos en
  `party_contactos`, que **no** son parties (`ContactosPartyService`). Techquk no está registrada como party.
- «Mis datos» (`Filament/Pages/MisDatos.php`, plan 1.4h): cumpleaños, teléfono y contraseña; se abre desde el menú de la
  foto. Fichas de Parties: `Filament/Resources/Parties/Pages/VerParty.php`.
- Adjuntos (`AdjuntosService`, plan 1.8): disco privado `adjuntos`, revisa el contenido real del archivo.
- En el entorno no hay GD ni Imagick: el recorte y el achicado de la foto se hacen en el navegador (el subidor de
  Filament: recorte en círculo y tamaño de destino), no en el servidor.

## Decisiones

Aprobadas por el desarrollador el 2026-10-27.

- **D1 · Redes de un catálogo editable** (Configuración → Catálogos): LinkedIn, Facebook, Instagram, TikTok, YouTube, X y
  sitio web para empezar. Cada una con su ícono y la revisión de que el enlace sea de esa red (su dominio); el sitio web
  acepta cualquier dominio.
- **D2 · Las redes son de una party o de un contacto de una empresa:** un cliente o proveedor tiene las suyas, y cada
  contacto (el de compras, el ingeniero) las suyas, en la ficha. Las del equipo, en «Mis datos».
- **D3 · En el equipo, cada persona elige red por red** si la ve todo el equipo o solo ella (sin «administración»: el desarrollador la quitó el 2026-10-27). Hoy
  ninguna pantalla muestra las redes de un compañero (el directorio del equipo está por planear): la visibilidad se
  guarda ya y el servicio da el punto de consulta (qué redes ve quién) que usará el directorio.
- **D4 · La foto de perfil la cambia solo la propia persona** (subir, cambiar, quitar), desde «Mis datos». Se ve en el
  círculo del menú; sin foto, las iniciales como hoy.
- **D5 · Recortada en círculo.** Al subir, la persona encuadra la foto en un círculo.
- **D6 · Sin pensar en tamaños:** se acepta la foto tal como sale del celular; el navegador la achica (unos 400 × 400)
  antes de subirla. Un tope alto solo contra lo absurdo. Nada de errores por peso en una foto normal.
- **D7 · Cualquier foto vale** (no tiene que ser de la cara).
- **D8 · Techquk como party, en otra tarea** (1.15): su ficha (RUC, dirección, redes) también servirá a cotizaciones y
  documentos; sus redes llegan con ella, sobre lo que deja esta tarea.

## Pasos

- [x] 1. Plan con las decisiones aprobadas.
- [x] 2. Catálogo de redes (`/nueva-migracion`): tabla, semilla de las siete, íconos de marca (SVG propios con
  licencia libre, servidos desde el repositorio) y su caja en Catálogos; pruebas.
- [x] 3. Redes de una party o de un contacto (`/nueva-migracion`, `/nueva-accion`): tabla, servicio con la revisión del
  enlace, la visibilidad (D3) y el punto de consulta; acciones registradas de agregar, cambiar y quitar; pruebas.
- [x] 4. Ficha de Parties: las redes de la empresa y las de cada contacto; pruebas.
- [x] 5. «Mis datos»: mis redes con su visibilidad, y la foto (subir con recorte en círculo y achicado en el navegador,
  cambiar, quitar; acción registrada); el círculo del menú con la foto; pruebas. Revisar fotos de iPhone (HEIC).
- [x] 6. Pedirle al desarrollador que lo mire en la app; ajustar.
- [x] 7. `revisor-arquitectura`; `docs/convenciones/interfaz.md` si cambia una regla; mapa; `composer check` en verde.

## Criterio de aceptación

- Un enlace que no es de la red elegida se rechaza; el sitio web acepta cualquiera.
- Una red de una persona del equipo marcada «solo la veo yo» no la ve otro compañero (punto de consulta).
- Nadie puede cambiar la foto de otra persona (prueba de la acción sin interfaz).
- Una foto de celular normal se sube sin errores y queda pequeña; el menú la muestra en círculo.

## Bitácora

- 2026-10-27 — Rama `interfaz-foto-y-redes`, apilada sobre `interfaz-logo-animacion` (PR por abrir), que está sobre
  `mantenimiento-pruebas-paralelo` (PR por abrir). Decisiones conversadas en la sesión del logo; la siguiente sesión
  empieza por el paso 2.
- 2026-10-27 — Paso 2. Tabla `redes_sociales` (no está en el DBML: pedido del desarrollador): `dominios` separados por
  comas, sin espacios y en minúsculas (nulo: cualquiera, el sitio web), revisados por la acción y por un `CHECK`; `icono`
  es uno de `IconoRed`, también con `CHECK`. Una red nueva sin dibujo propio usa el ícono de enlace. Íconos de marca en
  `app/Modules/Nucleo/resources/svg/red-*.svg`: Simple Icons (CC0) y LinkedIn de Material Design Icons (Simple Icons ya
  no lo trae); licencias en `LICENCIAS.md` de esa carpeta. Sitio web y enlace, heroicons. La caja va en «Personas y
  lugares» con `heroicon-o-share`, y la tabla muestra el ícono junto al nombre. Semilla: las siete, con los dominios
  cortos que usan las redes (`youtu.be`, `fb.com`, `twitter.com`). Base de desarrollo migrada y sembrada. Pruebas en
  `RedesSocialesCatalogoTest`.
- 2026-10-27 — Paso 3. Tabla `party_redes_sociales`: `party_id` siempre; con `contacto_id`, de ese contacto (llave
  compuesta a `party_contactos (party_id, id)`, como las sedes). `es_privada` (D3) solo sin contacto (`CHECK`), y el
  servicio la permite solo a una persona del equipo (party con cuenta). El enlace se guarda normalizado (`https://` si
  falta, esquema y dominio en minúsculas) y no se repite en el mismo dueño (índice único `NULLS NOT DISTINCT`).
  `RedesSocialesService`: agregar, cambiar, quitar (con versión) y el punto de consulta `redesQueVe()`.
  ~~«Administración» = permiso `parties.ver-redes-privadas`~~: al preguntarle, el desarrollador decidió quitar
  «administración» (2026-10-27). Una red privada la ve solo su dueño; si no, todo el equipo. Sin permiso nuevo.
  Acciones: `nucleo.parties.{agregar,cambiar,quitar}-red-social` con permiso nuevo `parties.gestionar-redes` (como
  sedes y contactos, que tienen el suyo), que rechazan a una persona del equipo; y `nucleo.{agregar,cambiar,quitar}-mi-
  red-social` sin permiso, solo sobre las propias. Borrar una party o un contacto se lleva sus redes; trasladar un
  contacto copia sus redes al vínculo nuevo (son de la persona, no del vínculo). Base de desarrollo migrada. Pruebas en
  `RedesSocialesTest`. La bitácora de la ficha las muestra en el paso 4.
- 2026-10-27 — Paso 4. Pestaña «Redes» en la ficha (`RedesDeParty`), entre Sedes y Cuentas bancarias: las de la
  persona o empresa primero, luego las de cada contacto, con el ícono de la red y el enlace (abre en otra pestaña).
  Agregar (con contacto opcional), cambiar y quitar, por las acciones `nucleo.parties.*-red-social`. Una persona del
  equipo: sin botones y con el aviso de que las cambia ella desde «Mis datos»; sus privadas no se ven, salvo para ella.
  El historial de la ficha incluye las redes; las de una persona del equipo, solo para ella (la bitácora de una red
  borrada no dice si era privada). Pruebas en `RedesFichaPartyTest`.
- 2026-10-27 — Paso 5. «Mis datos» suma «Mi foto» (antes de los datos de contacto) y «Mis redes» (antes de
  Seguridad). Foto: columna `users.foto_ruta` (única, con `CHECK` de forma), en el disco privado `adjuntos` bajo
  `fotos-perfil/<id>/`; la sirve `MostrarFoto` (ruta `fotos/{usuario}`, con sesión, en `AppPanelProvider` junto a la de
  adjuntos) con una dirección que cambia con cada foto. `FotoPerfilService` revisa el contenido real (JPG, PNG o WebP;
  HEIC con mensaje propio) y un tope de 20 MB; borra la anterior. No sube la versión de la cuenta: solo la cambia su
  dueña y no debe invalidar el formulario abierto. Acciones `nucleo.cambiar-mi-foto` y `nucleo.quitar-mi-foto`, sin
  campo de usuario: siempre la propia. El subidor abre el editor con recorte en círculo y achica a 400 × 400 en el
  navegador; al guardar, el círculo del menú cambia en el sitio, sin recargar. Sin foto, las iniciales como antes.
  **HEIC:** el iPhone la convierte a JPG al subirla desde Safari; una HEIC copiada a una computadora se rechaza con
  un mensaje que dice qué hacer (sin GD ni Imagick no se puede convertir). «Mis redes»: `MisRedesSociales`, con
  agregar, cambiar y quitar, y la columna «La ve: Solo yo / Todo el equipo». Pruebas en `MiFotoYRedesTest`.
- 2026-10-27 — Paso 6, primer ajuste (pedido del desarrollador): sin sección «Mi foto» aparte; el círculo de la foto va
  al costado de los datos de «Mi cuenta» (encima en el celular), y tocarlo abre la ventana para subirla. Debajo,
  «Cambiar mi foto» y «Quitar mi foto».
- 2026-10-27 — Paso 6, segundo ajuste (pedido del desarrollador): ícono propio `tq-redes-sociales`, tres bocetos a línea
  en uno (Instagram arriba a la izquierda, TikTok arriba a la derecha, X abajo al centro), para la caja del catálogo y
  la pestaña Redes de la ficha (antes `heroicon-o-share`).
- 2026-10-27 — Paso 6, tercer ajuste (pedido del desarrollador): la foto ocupa la columna izquierda de «Mi cuenta» (un
  tercio, hasta 13rem, centrada; los datos en los otros dos tercios; en el celular, encima). En la ventana para subirla,
  el círculo va al centro y del mismo tamaño (`.tq-subir-foto`).
- 2026-10-27 — Paso 6, cuarto ajuste: la foto va en la columna **derecha** de «Mi cuenta» (el desarrollador se corrigió).
  En el celular queda debajo de los datos.
- 2026-10-27 — Pedido fuera del plan, en esta rama: «Barra de módulos» del menú de la foto pasa de palanca de alarma a
  interruptor típico (`tq-interruptor-apagado` / `tq-interruptor-prendido`; prendido en el azul del tema, 700 claro y
  400 oscuro). Regla actualizada en `docs/convenciones/interfaz.md`.
- 2026-10-27 — Paso 7, cierre. Primera corrida completa: 1797 pruebas, 1 falla (`SinModoNavegacionTest` prohíbe el
  nombre `visiblesPara`, del modo de navegación que se quitó): el punto de consulta pasa a llamarse `redesQueVe()`.
  `revisor-arquitectura`, sin problemas de estructura; atendido: (1) las clases de Filament `fi-user-avatar` y
  `fi-fo-file-upload-input-ctn` anotadas como excepción en `interfaz.md` y vigiladas por `MiFotoYRedesTest`; los
  estilos en línea de la foto, a clases `tq-`; (2) la regla de quién escribe (D2) pasa de las acciones a
  `RedesSocialesService` (parámetro `propia`), con prueba del servicio sin las acciones; (3) no hay pantalla general
  de la bitácora: el enlace de una red privada solo se ve en la ficha, que ya la filtra; si algún día la hay, deberá
  filtrarla igual; (4) **las iniciales de quien no tiene foto se dibujan aquí** (`AvatarIniciales`, proveedor por
  defecto del panel): el de Filament mandaba el nombre a ui-avatars.com (regla 10), aprobado por el desarrollador;
  (5) la foto se guarda por flujo; (6) trasladar un contacto **copia** sus redes: el vínculo cerrado conserva las suyas
  (prueba).
- 2026-10-27 — Cierre. `revisor-arquitectura` atendido (ver la entrada del cierre de la 1.4o). `composer check` en verde
  (pint, phpstan, 40 migraciones, 1799 pruebas). Una corrida anterior falló por Postgres sin memoria de bloqueos al
  reconstruir 6 bases a la vez: `docker-compose.yml` sube `max_locks_per_transaction` a 256 (aprobado por el
  desarrollador). Plan a `completados/`; fila del mapa marcada.
