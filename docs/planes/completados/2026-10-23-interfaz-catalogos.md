# Plan: Catálogos como cajas (1.4j)

- **Estado:** completado (2026-10-23)
- **Módulo(s):** Nucleo
- **Fase:** F1
- **Creado:** 2026-10-23 · **Actualizado:** 2026-10-23

## Objetivo

Configuración → Catálogos muestra los catálogos como cajas (como los módulos del inicio), con un buscador que las
filtra. Al presionar una, su tabla se abre en un panel a la derecha que deja a la vista y usable la lista de
Configuración. Solo pantalla: no cambian acciones, servicios ni permisos.

## Contexto que leí

- `docs/planes/mapa-etapas.md`, fila 1.4j (diseño del desarrollador del 2026-10-23, que reemplaza la idea del 22).
- `Filament/Pages/Catalogos.php` (hoy: una «pestaña» por catálogo como botón del encabezado, `?catalogo=` en la URL,
  la tabla con sus acciones registradas `nucleo.<catalogo>.*` y, en ubigeos, buscar y aplicar la actualización del
  INEI), `Enums/Catalogo.php` (14 catálogos, `titulo()`), `docs/convenciones/interfaz.md` («Inicio»: cajas de igual
  tamaño, descripción en el tooltip).
- Rama: sale de `interfaz-modo-desarrollo` (su PR aún no se fusiona).

## Decisiones

- **D1 · El diseño** (desarrollador, 2026-10-23, en el mapa): cajas con ícono y nombre, la descripción en el tooltip,
  y un buscador arriba que filtra las cajas al escribir (sin acentos ni mayúsculas, como el resto). Al presionar una,
  su tabla (con todo lo de hoy) se abre en un panel a la derecha; la lista de Configuración sigue a la vista y usable;
  se cierra con la X o con Esc. Para cambiar, se cierra y se presiona otra caja. Al entrar se ven las cajas; un enlace
  con `?catalogo=…` abre su panel directo. La ruta: «Configuración > Catálogos» y, con un panel abierto, el nombre del
  catálogo al final. En el celular el panel ocupa la pantalla (no hay lugar para las dos cosas).
- **D2 · Íconos y descripciones** (desarrollador, 2026-10-23), en `Catalogo::icono()` y `Catalogo::descripcion()`:
  Monedas (billetes, «Monedas con las que se cotiza y se paga»), Unidades de medida (balanza, «Unidades de cantidad:
  unidad, metro, kilo…»), Ubigeos (pin, «Departamentos, provincias y distritos (INEI)»), Tipos de documento (documento,
  «Comprobantes: factura, boleta, guía… (SUNAT)»), Formas de pago (tarjeta, «Contado, crédito, plazos»), Líneas de
  solución (pieza, «Qué vende Techquk, por línea»), CUBSO (etiqueta, «Códigos de bienes y servicios del Estado»),
  Tipos de penalidad (advertencia, «Penalidades de los contratos»), Tipos de trabajo (herramientas, «Clases de trabajo
  en obra»), Tipos de documento de identidad (credencial, «DNI, RUC, carné de extranjería… (SUNAT)»), Tipos de persona y
  empresa (antes «Roles de personas y empresas»; grupo, «Cliente, proveedor, entidad, personal…»), Categorías de frases (pila, «Grupos de frases del
  saludo»), Frases del saludo (globos de diálogo, «Frases y versículos del inicio»), Feriados (calendario, «Feriados y
  días no laborables»).
- **D3 · Agrupadas por tema** (desarrollador, 2026-10-23), en este orden (`GrupoCatalogo`): **Comercio y pagos**
  (Monedas, Formas de pago, Tipos de documento), **Personas y lugares** (Tipos de documento de identidad, Tipos de
  persona y empresa —antes «Roles de personas y empresas»—, Ubigeos), **Proyectos y obras** (Líneas de solución, CUBSO, Unidades de medida, Tipos de trabajo,
  Tipos de penalidad) y **Calendario y saludo** (Feriados, Frases del saludo, Categorías de frases). El buscador filtra
  en todos y esconde los grupos que quedan vacíos.

## Pasos

- [x] 1. Plan aprobado (D2 y D3).
- [x] 2. **Datos de las cajas:** `Catalogo::icono()`, `descripcion()`, `grupo()` y `GrupoCatalogo`; prueba de que cada
      catálogo tiene los tres.
- [x] 3. **La pantalla:** cajas por grupo con el buscador, el panel a la derecha con la tabla y sus acciones (las del
      INEI en ubigeos), Esc y X para cerrar, `?catalogo=` que lo abre, la ruta, celular. Sin las pestañas.
- [x] 4. Pruebas de la pantalla y las que ya existían, al día.
- [x] 5. Documentos: `interfaz.md` (Catálogos), mapa, `cambios.md`.
- [x] 6. Revisión a la vista, `revisor-arquitectura`, `composer check`, `/cerrar-tarea` y PR.

## Criterio de aceptación

- `CatalogosPantallaTest`: al entrar, las 14 cajas en sus 4 grupos y ninguna tabla; cada caja con su ícono y su
  descripción; `?catalogo=monedas` abre el panel con la tabla y la ruta dice «Monedas»; cerrar vuelve a las cajas;
  un catálogo inventado en la URL no abre nada; ubigeos trae sus dos acciones del INEI dentro del panel; sin
  `catalogos.ver`, 403 como hoy.
- Las pruebas de catálogos que ya existen siguen pasando (acciones, búsqueda sin acentos, campos sensibles).
- A la vista: claro, oscuro y celular; el panel no tapa la lista de Configuración.

## Bitácora

- 2026-10-23 — Plan escrito.
- 2026-10-23 — Pasos 2 a 5. `Enums/GrupoCatalogo` (etiqueta y sus catálogos en orden) y `Catalogo::icono()`,
  `descripcion()` y `grupo()`. `Catalogos`: `?catalogo` ahora opcional (`abierto()`; `actual()` cae en Monedas solo
  para construir la tabla, que sin panel no se dibuja); el contenido es la vista `filament/catalogos/pantalla` (cajas
  con el buscador en Alpine, que normaliza los acentos; o el panel con `{{ $this->table }}`); las «pestañas» del
  encabezado se fueron y los dos botones del INEI pasaron a las acciones del encabezado de la tabla; `getBreadcrumbs()`
  arma la ruta. Desviación de D1: el panel ocupa el lugar de las cajas (no se ven debajo); el efecto pedido, la lista
  de Configuración a la vista y usable, se cumple. Abrir y cerrar son enlaces (recarga la página): cambiar de catálogo
  dentro del mismo componente obligaría a rehacer la tabla. Pruebas: `CatalogosPantallaTest` (6) y
  `ConfiguracionPanelTest` al día (los botones del INEI, como acciones de la tabla). A la vista: cajas en claro y
  celular bien; el panel no se pudo capturar fuera de un servidor real (la tabla carga sus scripts aparte): lo revisa
  el desarrollador en su equipo. `interfaz.md` («Catálogos»), `cambios.md`.
- 2026-10-23 — Pedido del desarrollador: el catálogo «Roles de personas y empresas» pasa a llamarse **«Tipos de persona
  y empresa»** (`Catalogo::nombre()`), para no confundirlo con los roles de seguridad de las cuentas. Solo el nombre que
  se ve: la tabla (`roles_party`), el enum y los códigos siguen igual. Se le explicó por qué cliente, proveedor,
  entidad, colaborador y banco no se desactivan ni se borran (el código los usa) y los agregados, como «aliado», sí.
- 2026-10-23 — Paso 6, revisión de `revisor-arquitectura` (nada bloqueante; solo pantalla, permisos intactos).
  Atendido: Esc ya no saca del panel si se está escribiendo, si hay un menú, una ventana de Filament, el selector del
  modo desarrollo o el gran momento abiertos; prueba de que sin panel no se consulta ninguna tabla de catálogos; el
  rango de acentos del buscador escrito con escapes; la descripción también para lectores de pantalla
  (`aria-description`) y «Ningún catálogo coincide» con `role="status"`. Corrección a la nota anterior: el nombre nuevo
  de `RolesParty` llega también a las etiquetas de sus acciones registradas y a los mensajes de error; la tabla, los
  códigos y las claves no cambian. **Por confirmar con el desarrollador:** la desviación de D1 (panel en el lugar de las
  cajas y recarga al abrir y cerrar), que se le avisó; hasta su respuesta, D1 dice lo aprobado. Sin cambio: `?catalogo[]=`
  (una lista en la URL) daba error igual que antes.
- 2026-10-23 — Pedido del desarrollador: ningún ícono repetido. Cambian Personas y empresas (caja del inicio y su
  pantalla: apretón de manos, `tq-apreton-manos`, de Lucide con licencia ISC), Tipos de persona y empresa (persona y
  edificio, `tq-persona-edificio`, dibujo propio), Tipos de documento de identidad (huella digital) y Frases del saludo
  (mano saludando). Prueba que lo exige en `CatalogosPantallaTest`; la de «sin panel no se consulta» ahora mira solo
  las consultas de la tabla (el calendario laboral del saludo lee feriados por su cuenta) y comprueba su contraparte.
- 2026-10-23 — Pedido del desarrollador («o de cualquier otra subparte»): estilo común `tq-caja-sub` para las cajas
  dentro de un módulo (blanco en oscuro, crece 1,15 al pasar el mouse o con el foco), en `filament/navegacion`; las
  del inicio crecen 1,35. Quietas con animaciones reducidas. El título del panel, blanco en oscuro.
- 2026-10-23 — Aclaración del desarrollador: crece la caja entera con su ícono, no solo el ícono. Inicio 1,08 (ícono
  1,2 adentro); subpartes 1,04 (ícono 1,1); la caja que crece queda encima de sus vecinas (`z-index`), sin moverlas.
- 2026-10-23 — Pedido del desarrollador: Frases del saludo con la mano haciendo la «V» de amor y paz (`tq-mano-paz`,
  dibujo propio) en vez de la mano saludando.
- 2026-10-23 — El desarrollador no aprobó el dibujo propio de la «V»: se usa la mano de Font Awesome Free (versión de
  contorno, licencia CC BY 4.0, aviso dentro del archivo), que combina con el trazo de los demás.
- 2026-10-23 — Resuelta la desviación de D1: el desarrollador eligió el panel **encima** de las cajas, que siguen
  detrás (como lo aprobó). El panel va `position: absolute` a la derecha, con sombra y animación de entrada; las cajas,
  atenuadas (`tq-catalogos-detras`), y una que asome se puede presionar para cambiar de catálogo. Abrir y cerrar siguen
  siendo enlaces (recargan la página).
- 2026-10-23 — Pedido del desarrollador: abrir y cerrar sin recargar, con transición suave. `Catalogos::abrir()` y
  `cerrar()` (Livewire) cambian el catálogo, vacían la búsqueda y rehacen la tabla con `resetTable()`; la URL sigue el
  cambio (`#[Url]`). La caja abre con un clic normal (`$wire.abrir`) y conserva su enlace para Ctrl o el botón del
  medio. El panel entra deslizándose (`wire:key` por catálogo, para que la animación vuelva a correr al cambiar) y, al
  cerrar con la X o Esc, primero se desliza afuera (190 ms) y después Livewire lo quita; las cajas de atrás recuperan su
  color con una transición. Sin movimiento con animaciones reducidas. Al cerrar, el foco vuelve a la caja. La ruta
  suma «Listado» como las demás pantallas (con un panel abierto, el nombre del catálogo).
- 2026-10-23 — A pedido del desarrollador («va aprendiéndote esos patrones de diseño porque eso se repetirá»):
  `interfaz.md` suma «Patrones de pantalla» (cajas con buscador, panel deslizante encima sin recargar, transiciones
  suaves, crecimiento al pasar el mouse, íconos únicos y que se entiendan solos, ruta completa, nombres que no se
  cruzan, preguntar antes de cambiar lo aprobado). Las cajas y el panel se vuelven piezas reutilizables en la 1.4m,
  cuando haya una segunda pantalla que los use.
- 2026-10-23 — `composer check` encontró un comentario de CSS en `filament/navegacion` que nombraba «Personas y
  empresas» y llegaba a toda página (también a quien no ve ese módulo): pasa a comentario de Blade, que no se envía.
- 2026-10-23 — Fuera de esta tarea, anotado en el mapa: el desarrollador amplió la 1.4e (saludo, frase y dibujo según
  la hora y el horario de la empresa; saludos naturales y especiales fuera de horario).
- 2026-10-23 — Paso 6 hecho. `composer check` en verde (1481 pruebas). Plan cerrado; pasa a `completados/`. Sigue la
  1.4m (portada de Personas y empresas), que reutiliza las cajas y el panel.
