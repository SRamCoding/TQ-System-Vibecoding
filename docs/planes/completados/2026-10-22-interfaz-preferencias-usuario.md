# Plan: 1.4g · Interfaz: preferencias por usuario

- **Estado:** terminado (2026-10-22)
- **Módulo(s):** Nucleo (registro de preferencias, `PreferenciasUsuarioService`, navegación, saludo) y panel `app`
- **Etapa:** 1 · tarea 1.4g · rama `interfaz-preferencias-usuario` (desde `main`, ad30dd2)
- **Creado:** 2026-10-22 · **Actualizado:** 2026-10-22

## Objetivo

Cada persona ajusta la presentación del sistema a su gusto desde una sola pantalla, «Mis preferencias», y sus ajustes
la siguen a cualquier equipo. Las preferencias las declara cada módulo en un registro del Núcleo, se guardan en la
base (una tabla clave-valor validada por el registro, salvo las listas), se leen y escriben solo por
`PreferenciasUsuarioService` con acciones registradas y se aplican al instante, sin recargar. Personalizar nunca
cambia permisos, datos ni reglas del negocio.

## Contexto que leí

- `docs/planes/mapa-etapas.md` (orden de trabajo del 2026-10-22, filas 1.4d, 1.4f, 1.4g, 1.4h y 1.13).
- `docs/planes/completados/2026-10-20-interfaz-modo-unico.md` (D4, D6 y bitácora: barra de módulos, ventana de la
  primera vez por inicio de sesión, `refresh-topbar`, `Livewire::originalUrl()`).
- `docs/convenciones/interfaz.md` («Inicio», «Barra de módulos», «Pantalla angosta», «Vistas propias», «Saludo»),
  `docs/convenciones/codigo.md` («Acciones registradas»), `docs/convenciones/base-de-datos.md`,
  `docs/decisiones/0010-migraciones-editables.md` (no existe `.claude/PRODUCCION`).
- Código: `RegistroParametros` (modelo del registro), `parametros` (clave-valor con `tipo`, `valor text`),
  `PreferenciasUsuarioService`, `NavegacionService`, `BarraModulos`, `BordeBarraLateral`, `AppPanelProvider`,
  migraciones `2026_10_10_000002`, `2026_10_19_000001` (saludo) y `2026_10_19_000003` (ancho de la barra).
- Filament 5.8: el tema vive en `localStorage` (clave `theme`, `light` | `dark` | `system`); el script de
  `layout/base.blade.php` pone la clase `dark` antes de dibujar; el selector envía el evento `theme-changed`. La
  respuesta de inicio de sesión se reemplaza en el contenedor (`Filament\Auth\Http\Responses\Contracts\LoginResponse`).
- Pendientes que afectan: ninguno bloquea. El tablero de inicio por rol y «Mien» (`pendientes.md`) deciden los
  favoritos (D5).

### Inventario de lo que ya existe

| Preferencia | Dónde vive hoy | Quién la lee | Cómo se cambia |
|---|---|---|---|
| Barra de módulos prendida | `users.es_barra_modulos_visible` | `NavegacionService`, `BarraModulos`, vista `navegacion` | `nucleo.mostrar-barra-modulos` |
| Módulos ocultos (lista) | `modulos_ocultos` | `NavegacionService` | `nucleo.elegir-modulos` |
| Ancho de la barra | `users.ancho_barra_lateral` (CHECK 0 o 200-480) | `AppPanelProvider`, vista `navegacion` | `nucleo.cambiar-ancho-barra-lateral` (al soltar el borde) |
| Saludo: inglés, francés, ambientación | `preferencias_saludo` (una fila por persona, `version`) | `PreferenciasUsuarioService::saludoDe()` | `nucleo.cambiar-preferencias-saludo` |
| Categorías de frases apagadas (lista) | `categorias_frase_usuario` | `PreferenciasUsuarioService::saludoDe()` | la misma acción |
| Tema claro u oscuro | el navegador (`localStorage`) | Filament | su selector del menú del avatar |
| Cumpleaños | `users.cumpleanos_dia`, `cumpleanos_mes` | el saludo | `nucleo.registrar-mi-cumpleanos` — **no es preferencia** (D1): va a «Mis datos» (1.4h) |

## Decisiones (aprobadas por el desarrollador, 2026-10-22)

- **D1 · Número y lugar.** 1.4g, justo después de la 1.4f y antes de la 1.13 (que pondrá los defectos por rol). Pieza
  del Núcleo, no un módulo aparte: `app/Modules/Nucleo/Preferencias/`, como `RegistroParametros`; cada módulo solo
  declara sus preferencias en su provider. El cumpleaños no es preferencia: va a «Mis datos» (1.4h). Orden de trabajo
  anotado en el mapa: 1.4g; 1.4d (lista antes del 25 de diciembre de 2026); 1.6 y 1.7; después 1.4e, 1.4h y el resto
  de interfaz (1.4c).

- **D2 · Guardado: tabla clave-valor validada por el registro** (opción A). `preferencias_usuario` (`user_id` FK con
  índice, `clave varchar` con CHECK del formato `grupo.nombre`, `valor text NOT NULL`, único `(user_id, clave)`,
  `auditoria()`). Una fila solo si la persona cambió el defecto; sin fila vale el defecto, que vive solo en el
  registro.
  - Razones: un módulo declara sin tocar `users` (regla 2 de `AGENTS.md`); precedente de `parametros`; «Restablecer»
    es borrar filas.
  - Lo que la base no garantiza (el tipo de cada valor) lo compensan: el formato de la clave y la unicidad en la base;
    el servicio rechaza una clave no registrada y un valor que no cumple su tipo u opciones; una prueba exige que cada
    defecto sea válido; **un valor guardado que ya no vale cuenta como el defecto**, sin romper nada.
  - *Condiciones del desarrollador:* (1) el valor se guarda como **texto**, igual que en `parametros`, y el registro lo
    convierte a su tipo; **nunca `float`**. (2) En `preferencias_usuario` **nunca van datos personales** (cumpleaños,
    teléfono, documento): van en «Mis datos» (regla en D7).

- **D3 · Las existentes se mueven ahora; las listas se quedan en sus tablas** (opción A).
  - Pasan a `preferencias_usuario`: barra prendida, ancho de la barra, inglés, francés y ambientación. Salen
    `users.es_barra_modulos_visible`, `users.ancho_barra_lateral` y la tabla `preferencias_saludo`, **editando las
    migraciones de agosto** (ADR 0010), sin conversión de datos. El desarrollador reconstruye su base una vez.
  - `modulos_ocultos` y `categorias_frase_usuario` se quedan: un conjunto se garantiza mejor con una fila por elemento
    y su unicidad en la base. El registro las declara con **tipo lista**, cuyo módulo dueño (una fuente) lee, guarda y
    restablece; así salen solas en la pantalla y en «Restablecer».
  - **Acciones:** se mantienen solo las que tienen lógica propia: `nucleo.mostrar-barra-modulos` (por la ventana de la
    primera vez de la 1.4f) y las dos de listas (`nucleo.elegir-modulos` y una para las categorías de frases). El ancho
    y las opciones del saludo pasan a la acción general **`nucleo.cambiar-preferencia`** (clave y valor); salen
    `nucleo.cambiar-ancho-barra-lateral` y `nucleo.cambiar-preferencias-saludo`.
  - *Condiciones del desarrollador:* la acción general acepta **solo claves registradas**, **valida el valor con el
    registro** y cambia **solo las preferencias de quien la ejecuta**, nunca las de otro. Con una prueba de cada una.

- **D4 · Tres preferencias nuevas.**
  1. **Tema** (Apariencia): claro, oscuro o según el sistema; defecto, según el sistema. El evento `theme-changed`
     del selector de Filament se escucha y se guarda con `nucleo.cambiar-preferencia`. Al cargar, un script en la
     cabecera pone en `localStorage` lo que dice la base y corrige la clase `dark` de `<html>` antes de dibujar (sin
     parpadeo). El selector de Filament queda en el menú y también está en «Mis preferencias». Riesgo: la clave
     `theme` y el evento no están documentados; si cambian, el tema vuelve a vivir solo en el navegador, sin romper
     nada. **Una prueba vigila que la página siga trayendo el script de Filament con esa clave.**
  2. **Densidad** (Apariencia): cómodo o compacto; defecto, cómodo. **Solo en tablas.** Usa dos clases internas de
     Filament (la celda y el encabezado de la tabla), **anotadas como excepción en `interfaz.md` y vigiladas por una
     prueba**.
  3. **Pantalla al entrar** (Navegación): el inicio o un módulo permitido (opciones: el inicio más los permitidos de
     hoy, de `NavegacionService`); defecto, el inicio. Se aplica solo al iniciar sesión, reemplazando la respuesta de
     login de Filament (punto de extensión público). Reglas: (a) si perdió el permiso de ese módulo, va al inicio (la
     preferencia se ignora, no se borra); (b) **si iba a un enlace concreto antes de entrar, gana el enlace**; (c) el
     logo y la casa siguen llevando al inicio.
     *Condición del desarrollador:* si más adelante se agrega MFA, la redirección ocurre **al completar el último
     paso del ingreso** (después del código), igual que la marca de la ventana en D6 de la 1.4f.

- **D5 · También entran ahora el tamaño de letra y «reducir animaciones»; los favoritos, después.**
  1. **Tamaño de letra** (Apariencia): normal o grande (100 % y 112,5 % en la raíz; Filament mide en `rem`, todo crece
     en proporción sin clases internas). Defecto, normal. El desarrollador revisará a la vista las cajas del inicio con
     letra grande en el cierre.
  2. **Reducir animaciones** (Apariencia): según el sistema o reducir siempre; defecto, según el sistema. Con
     «reducir siempre», `<html>` lleva una clase propia; **toda animación nuestra respeta las dos cosas** (el logo que
     se construye, la ambientación, la entrada de la barra y, después, la 1.4d). **Una prueba recorre nuestras vistas**
     y lo exige. Anotado en la fila 1.4d del mapa: con animaciones reducidas, el gran momento se muestra quieto (imagen
     y versículo, sin movimiento) y cuenta igual como visto.
  3. **Módulos favoritos: después**, a decidir junto con «Mien» (si fueran primero en el inicio y la barra, romperían
     el «mismo orden» de la 1.4f; si viven aparte, se pisan con el tablero personal).

- **D6 · Pantalla «Mis preferencias».**
  - Página del Núcleo (`/mis-preferencias`), abierta a toda persona con sesión, desde el menú del avatar; no va en la
    barra de módulos.
  - Se arma sola desde el registro. Grupos en orden fijo: **Apariencia, Navegación, Avisos, Saludo**. Un grupo sin
    preferencias no aparece (hoy, Avisos).
  - Cada preferencia, con su nombre y una línea de ayuda declaradas en el registro, y su control según el tipo: sí/no →
    interruptor; opción hasta 4 → botones de opción; opción con más → selector; número → campo con sus límites; lista →
    casillas (como «Elegir módulos»).
  - **Se guarda al cambiar, sin botón**, y se aplica al instante. **Los campos numéricos guardan al salir del campo**,
    no con cada tecla. Si algo se rechaza, el control vuelve a su valor y sale el aviso.
  - **«Restablecer» por grupo**, en su encabezado, con confirmación («¿Volver Apariencia a como viene?»): borra las
    filas del grupo y vacía sus listas. Acción registrada `nucleo.restablecer-preferencias` (campo `grupo`).
  - En el celular, las preferencias de la barra se muestran con la nota «Solo en escritorio», no se esconden.
  - *Respuestas del desarrollador:* (1) **el ancho de la barra no se muestra** en la pantalla (se cambia arrastrando);
    «Restablecer» de Navegación lo devuelve a 320 px. (2) **Menú del avatar:** sale «Mi saludo»; quedan «Mostrar barra
    de módulos», «Elegir módulos…», «Mi cumpleaños» (hasta la 1.4h), el selector de tema y «Mis preferencias».
    (3) Guardar al cambiar, sin botón; los numéricos, al salir del campo.

- **D7 · Reglas.**
  - En `interfaz.md`, sección nueva «Preferencias»:
    1. Personalizar solo cambia la presentación; nunca permisos, datos, montos, decimales, moneda ni reglas del
       negocio.
    2. Ocultar no es seguridad: esconder un módulo, una columna o un aviso no quita el acceso; mandan los permisos.
    3. Cada preferencia tiene un defecto pensado para quien nunca la cambia: el sistema se ve y funciona bien sin
       tocar «Mis preferencias».
    4. Se aplican al instante y siguen a la persona a cualquier equipo: viven en la base, no en el navegador.
    5. Una preferencia que no aplica en un ancho de pantalla se muestra con su nota («Solo en escritorio»).
    6. *(del desarrollador)* Una preferencia puede **prellenar** un campo, nunca decidir por la persona: el valor
       prellenado se ve y se confirma antes de guardar. Ninguna preferencia imputa un costo o elige un destino sin que
       la persona lo vea (pensado para «proyecto u obra de trabajo» y «almacén por defecto»).
  - En `codigo.md`, sección nueva «Preferencias por usuario»:
    1. Una preferencia nueva se declara en `RegistroPreferencias` desde el provider de su módulo (clave, grupo, tipo,
       defecto, validación); no se crea una columna ni un método suelto.
    2. Solo `PreferenciasUsuarioService` las lee y escribe, con acciones registradas; `nucleo.cambiar-preferencia`
       cambia solo las de quien la ejecuta.
    3. El valor se guarda como texto y el registro lo convierte a su tipo; nunca `float`. Un valor guardado que ya no
       vale cuenta como el defecto.
    4. En `preferencias_usuario` nunca van datos personales (cumpleaños, teléfono, documento): van en «Mis datos».
    5. Una lista (conjunto de claves o ids) vive en su propia tabla, con una fila por elemento y su unicidad en la
       base; el registro la declara con tipo lista y su módulo dueño la lee, guarda y restablece.
    6. Pruebas obligatorias: cada preferencia registrada tiene defecto válido; una clave no registrada se rechaza;
       «Restablecer» vuelve a los defectos; nadie cambia las preferencias de otra persona.
    7. Toda animación nuestra respeta el ajuste del sistema y «reducir animaciones»; lo vigila una prueba.
    8. *(del desarrollador)* Una preferencia no guarda estado de trabajo (borradores, último registro abierto, pestaña
       actual): eso no va en `preferencias_usuario`.

## Para después (anotado, sin construir)

Cada una se declarará en el registro cuando llegue su pantalla, respetando las reglas de D7 (en especial la 6 de
`interfaz.md`: prellenar, nunca decidir):

- **Proyecto u obra de trabajo** y **almacén por defecto**: prellenan formularios; la persona ve el valor y confirma.
- **Tablas:** columnas visibles, filtros guardados y filas por página.
- **Avisos:** qué avisos recibir, por qué medio, resumen diario y horario sin avisos (con la 1.10).
- **Módulos favoritos:** a decidir junto con «Mien» (D5).

## Pasos

Un commit al cerrar cada paso. Cada paso deja `composer check` en verde.

- [x] 1. **Plan aprobado** (D1-D7) y mapa (orden de trabajo, filas 1.4g y 1.4h, nota en la 1.4d).
- [x] 2. **Registro y datos.** `Preferencias/RegistroPreferencias`, la definición (clave, grupo, nombre, ayuda, tipo
      sí/no | número | opción | lista, defecto, validación, opciones fijas o calculadas, «solo escritorio», «se
      muestra en la pantalla»), la conversión de texto a tipo y la fuente de una lista. Migración nueva
      `preferencias_usuario`; modelo; DBML. `PreferenciasUsuarioService`: leer (con defecto y con valor ya inválido →
      defecto), cambiar, restablecer un grupo. Acciones `nucleo.cambiar-preferencia` y `nucleo.restablecer-preferencias`.
      Pruebas sin interfaz.
- [x] 3. **Mover las existentes** (D3). Declarar barra prendida, ancho, inglés, francés y ambientación; las listas
      (`modulos_ocultos`, categorías) como tipo lista con su fuente. Editar `2026_10_10_000002`,
      `2026_10_19_000001` y `2026_10_19_000003` (salen las dos columnas de `users` y `preferencias_saludo`).
      `NavegacionService`, el saludo, `BarraModulos`, `BordeBarraLateral` y el proveedor del panel leen y escriben por
      el servicio. Salen `nucleo.cambiar-ancho-barra-lateral` y `nucleo.cambiar-preferencias-saludo`; entra la acción
      de lista de categorías. Prueba de que no queda rastro de lo retirado.
- [x] 4. **Las nuevas** (D4, D5): tema (con la sincronización con Filament), densidad (solo tablas), tamaño de letra,
      reducir animaciones (y que nuestras animaciones lo respeten) y pantalla al entrar (respuesta de login).
- [x] 5. **Pantalla «Mis preferencias»** (D6) y el menú del avatar (sale «Mi saludo», entra «Mis preferencias»).
- [x] 6. **Documentos:** reglas de D7 en `interfaz.md` y `codigo.md` (más las excepciones de clases de la densidad y el
      tema), `ARCHITECTURE.md` si hace falta, DBML, mapa, `cambios.md`.
- [x] 7. Revisión a la vista (letra grande con las cajas del inicio, modo oscuro, celular, reducir animaciones),
      revisión de `revisor-arquitectura`, `composer check` en verde y `/cerrar-tarea`.

## Criterio de aceptación

- **Registro y servicio (paso 2):** cada preferencia registrada tiene un defecto válido; una clave no registrada se
  rechaza; un valor que no cumple su tipo u opciones se rechaza; un valor guardado que ya no vale se lee como el
  defecto; «Restablecer» de un grupo deja sus preferencias en el defecto y no toca las de otros grupos; ningún valor se
  convierte a `float`. La base rechaza una clave con otro formato y una fila repetida.
- **Acción general (paso 2):** acepta solo claves registradas, valida con el registro y cambia solo las preferencias de
  quien la ejecuta (otra persona queda intacta); rechaza al usuario de sistema y a uno inactivo.
- **Movimiento (paso 3):** la barra, el ancho y el saludo funcionan igual que antes leyendo del registro; «Restablecer»
  de Navegación devuelve el ancho a 320 px y vacía los módulos ocultos; ninguna mención a lo retirado en `app/`,
  `resources/`, `config/` ni `tests/` (salvo la prueba de retiro).
- **Nuevas (paso 4):** el tema elegido llega del servidor en la cabecera y la página sigue trayendo el script de
  Filament con la clave `theme`; la densidad compacta solo cambia tablas y las dos clases internas siguen en el HTML de
  Filament; la letra grande cambia el tamaño de la raíz; con «reducir siempre», `<html>` lleva la clase y toda vista
  con animación la respeta junto con `prefers-reduced-motion`; al iniciar sesión se va a la pantalla elegida, al
  inicio si perdió el permiso, y al enlace concreto si venía de uno.
- **Pantalla (paso 5):** muestra solo los grupos con preferencias, en orden; cada tipo con su control; guardar al
  cambiar aplica sin recargar; un rechazo devuelve el control a su valor; «Restablecer» pide confirmación; el ancho no
  se muestra; las de la barra llevan «Solo en escritorio» en el celular; el menú del avatar ya no tiene «Mi saludo».
- **A la vista (paso 7):** letra grande con las cajas del inicio, modo oscuro, celular a 360 px, reducir animaciones.

## Bitácora

- 2026-10-22 — Tarea planeada en conversación con el desarrollador (pedido del 2026-10-21). Rama
  `interfaz-preferencias-usuario` desde `main` (ad30dd2, con la 1.4f fusionada). D1 a D7 aprobadas una por una, con
  las condiciones escritas en cada decisión. Mapa: orden de trabajo del desarrollador (2026-10-22) escrito encima de la
  tabla y filas reordenadas (1.4g, 1.4d, 1.6, 1.7, 1.4e, 1.4h, 1.4c y el resto del núcleo); filas nuevas 1.4g y 1.4h;
  en la 1.4d, el gran momento quieto con animaciones reducidas (D5). Siguiente: paso 2, después de que el desarrollador
  revise este plan.
- 2026-10-22 — El desarrollador aprobó el plan con los tres detalles (acción propia para las categorías de frases,
  «solo escritorio» y «se muestra» en la definición, una sola reconstrucción al final del paso 3). Rama subida a
  GitHub, sin PR.
- 2026-10-22 — Paso 2 hecho. `Nucleo/Preferencias/`: `GrupoPreferencia` (el orden de los casos es el de la pantalla),
  `TipoPreferencia` (sí/no, número, opción, lista; `interpretar()` acepta el texto de la acción o el valor ya en su
  tipo; un número es entero, nunca decimal), `DefinicionPreferencia` (constructores `siNo`, `numero`, `opcion`, `lista`;
  `soloEnEscritorio()` y `sinMostrar()`; opciones fijas o calculadas por persona; `validar()`), `FuenteDeLista` (el
  módulo dueño de una lista: `valores`, `opciones`, `restablecer`) y `RegistroPreferencias` (singleton; declarar dos
  veces la misma clave es un error). Migración `2026_10_22_000001_crear_preferencias_usuario` (FK con índice, único
  `(user_id, clave)`, CHECK del formato `grupo.nombre`, `valor text`), modelo `PreferenciaUsuario` (alias
  `preferencia_usuario`), DBML. `PreferenciasUsuarioService`: `valor()` (sin fila o con un valor que ya no vale, el
  defecto; la fila no se borra, así que si el permiso vuelve la preferencia vuelve), `cambiar()` (solo registradas;
  valida; el defecto borra la fila; una lista se rechaza: tiene su acción) y `restablecer()` (un grupo, con sus
  listas). Acciones `nucleo.cambiar-preferencia` (clave y valor en texto) y `nucleo.restablecer-preferencias`
  (grupo). Las preferencias no van a la bitácora, como las demás de interfaz.
  Pruebas: `PreferenciasUsuarioTest` (25), con preferencias de prueba y una lista en memoria
  (`Fixtures/FuenteListaDePrueba`). `composer check` en verde (1332). Siguiente: paso 3.
- 2026-10-22 — Paso 3 hecho. Declaradas en `NucleoServiceProvider::declararPreferencias()`: `navegacion.barra_modulos`
  (sí/no, defecto no, solo escritorio), `navegacion.modulos_barra` (lista; fuente `Navegacion/ModulosDeLaBarra`: lo
  elegido son los módulos a la vista, lo guardado siguen siendo los ocultos; solo escritorio), `navegacion.ancho_barra`
  (número 0-480 con la condición `BarraLateral::esValido()`, defecto 320; solo escritorio; no se muestra),
  `saludo.ingles`, `saludo.frances` (no), `saludo.ambientacion` (sí) y `saludo.categorias_frase` (lista; fuente
  `Saludo/CategoriasDeFrases`: lo elegido son las prendidas, lo guardado las apagadas).
  Migraciones editadas (ADR 0010): `2026_10_10_000002` ya no crea `es_barra_modulos_visible`;
  `2026_10_19_000001` ya no crea `preferencias_saludo`; `2026_10_19_000003` (solo creaba `ancho_barra_lateral`) se
  borró. Salen el modelo y el factory de `PreferenciaSaludo`, `cambiarAnchoBarraLateral()`, `cambiarSaludo()`,
  `anchoBarraLateralInvalido()` y las acciones `nucleo.cambiar-ancho-barra-lateral` y
  `nucleo.cambiar-preferencias-saludo`. Entra `nucleo.elegir-categorias-frase` (`PreferenciasUsuarioService::
  apagarCategorias()`, con el bloqueo de la fila del usuario como la lista de módulos). El borde de arrastre guarda con
  `nucleo.cambiar-preferencia`. `NavegacionService` gana `barraVisible()` y `anchoBarra()`; `mostrarBarra()` escribe
  por el servicio de preferencias. El factory `conBarraModulos()` guarda la preferencia al crear. Piezas nuevas en la
  definición: `soloSi()` (condición extra de un número) y lectores con tipo en el servicio (`siNo()`, `numero()`,
  `opcion()`).
  **Estado intermedio hasta el paso 5:** «Mi saludo» salió del menú (su acción ya no existe); hasta que llegue «Mis
  preferencias», idiomas, ambientación y categorías del saludo solo se cambian sin interfaz.
  Pruebas: `PreferenciasDelNucleoTest` (14: declaraciones, límites del ancho, listas, «Restablecer» de Navegación y
  de Saludo, y que no queda rastro de lo retirado); `AccionesSaludoTest`, `SaludoIntegridadTest`, `SaludoPorReglasTest`,
  `AmbientacionTest`, `LogoTechqukTest`, `BarrasNavegacionTest`, `NavegacionServiceTest`, `BarraModulosTest` y
  `PreferenciasBarraModulosTest` usan las preferencias del registro; `CambiarAnchoBarraLateralTest` se borró (lo cubren
  `PreferenciasDelNucleoTest` y `BarrasNavegacionTest`). DBML al día. `composer check` en verde (1330).
  **El desarrollador reconstruye su base ahora** (`php artisan migrate:fresh --seed`).
- 2026-10-22 — El desarrollador reconstruye su base tras el paso 3. Paso 4 hecho. Declaradas: `apariencia.tema`
  (`system` | `light` | `dark`, los valores de Filament; defecto `system`), `apariencia.letra` (normal | grande),
  `apariencia.densidad` (cómoda | compacta), `apariencia.animaciones` (según el sistema | reducir siempre) y
  `navegacion.pantalla_inicio` (el inicio más los permitidos de hoy, con `ModulosDeLaBarra::opciones()`).
  - **Cómo se aplican:** `Filament/Apariencia` traduce las preferencias a clases propias de `<html>` (`tq-letra-grande`,
    `tq-compacto`, `tq-sin-animaciones`) y al tema. La vista `nucleo::filament.apariencia` (`HEAD_END`) las pone antes
    de dibujar, escribe el tema de la base en `localStorage` (donde lo lee Filament) y corrige la clase `dark` que el
    script de Filament ya puso. Escucha `tq-apariencia` para aplicarlas al instante (lo usará la pantalla del paso 5).
  - **Tema a la base:** el componente Livewire `Filament/GuardarTema` (`BODY_END`) escucha `theme-changed` del selector
    de Filament y guarda con `nucleo.cambiar-preferencia` solo si cambió (Filament lo envía también al cargar).
  - **Densidad, sin clases internas nuevas:** el CSS compilado de Filament calcula los rellenos con la variable
    `--spacing` de Tailwind 4; la densidad compacta la achica dentro de `fi-ta-cell` y `fi-ta-header-cell` (las dos
    aprobadas). Una prueba vigila que el HTML las traiga y que el CSS siga usando `--spacing`.
  - **Reducir animaciones:** la regla `html.tq-sin-animaciones` va junto a la de `prefers-reduced-motion` en los tres
    lugares que ya la tenían (`ambientacion/estilos` para todos los dibujos dentro de `.tq-amb`, `logo` y
    `navegacion`). Una prueba recorre `resources/views` y exige las dos reglas en toda vista con animación (los dibujos
    de la ambientación, por su hoja común).
  - **Pantalla al entrar:** `Filament/RespuestaDeIngreso` reemplaza la respuesta de login de Filament (`bind` en el
    proveedor del Núcleo, después del de Filament): `redirect()->intended(destino)`, así un enlace concreto gana; un
    módulo sin permiso no es opción válida y el registro lo lee como el defecto (el inicio).
  Pruebas: `PreferenciasNuevasTest` (12). `composer check` en verde (1342). Siguiente: paso 5.
- 2026-10-22 — Paso 5 hecho. `Filament/Pages/MisPreferencias` (`/mis-preferencias`, registrada en `RegistroPantallas`,
  fuera de la barra; toda persona con sesión). Se arma desde el registro: una sección por grupo con preferencias
  visibles (`seMuestra`), en el orden de `GrupoPreferencia`; hoy salen Apariencia, Navegación y Saludo. Controles:
  sí/no → interruptor; opción hasta 4 → botones de opción en línea; más → selector; número → campo entero con límites
  (`live(onBlur)`); lista → casillas. Campo `grupo__nombre` (el punto se leería como anidamiento). Guarda en
  `afterStateUpdated` con `nucleo.cambiar-preferencia` o, en una lista, con la acción de su módulo. Un rechazo devuelve
  el control a lo guardado y avisa. Después aplica sin recargar: Apariencia → `tq-apariencia`; Navegación →
  `tq-barra-modulos` y `refresh-topbar` (el ícono del menú). «Restablecer» en el encabezado de cada sección
  (`nucleo.restablecer-preferencias` con el grupo fijo, confirmación «¿Volver <grupo> a como viene?»), y vuelve a
  llenar. Las de la barra llevan la nota «Solo en escritorio», visible solo en el celular (`tq-solo-celular`). Menú
  del avatar: entra «Mis preferencias» (ya no está «Mi saludo» desde el paso 3).
  Pieza nueva: `FuenteDeLista` gana `accion()` y `datosParaGuardar($elegidos, $mostrados)`, para que la pantalla guarde
  una lista sin saber de qué módulo es; lo no elegido se calcula contra lo mostrado al abrir (como «Elegir módulos» de
  la 1.4f). Las categorías: se guarda la lista entera (una apagada que la empresa desactivó sale de la lista).
  Límites conocidos: el selector de tema de Filament no ve el cambio hecho desde la pantalla hasta recargar (el tema sí
  se aplica); el ancho que deja «Restablecer» se ve al cargar otra página. En la prueba, «Restablecer» se monta con la
  misma llamada que hace el botón (`mountAction` con `schemaComponent`): el ayudante `TestAction` de Filament no la
  encontraba.
  Pruebas: `MisPreferenciasTest` (11). `composer check` en verde (1353). Siguiente: paso 6 (documentos).
- 2026-10-22 — Paso 6 hecho. `interfaz.md`: sección nueva «Preferencias» (las seis reglas de D7, la pantalla y las de
  apariencia); «Barras» y «Saludo» al día (el ancho es la preferencia `navegacion.ancho_barra`; la ambientación se
  apaga en «Mis preferencias»); excepciones de «Vistas propias»: `fi-ta-cell` y `fi-ta-header-cell` con `--spacing`, y
  los dos detalles del tema de Filament (clave `theme`, evento `theme-changed`). `codigo.md`: sección nueva
  «Preferencias por usuario» (las ocho reglas). `ARCHITECTURE.md` («Interfaz»): una frase sobre el registro.
  `cambios.md`: Común 0.3.4 (README y `bloques/comun.md` al día). Mapa: fila 1.4g al día (✅ al cerrar). DBML ya
  estaba al día desde los pasos 2 y 3. Siguiente: paso 7 (cierre).
- 2026-10-22 — Paso 7, revisión de `revisor-arquitectura`: sin bloqueantes. Atendido:
  (1) La elección de la pantalla al entrar pasa a `NavegacionService::pantallaDeInicio()`; `RespuestaDeIngreso` solo
  redirige (regla 7 de `AGENTS.md`: la lógica no vive en Filament).
  (2) `MisPreferencias` usa `PreferenciasUsuarioService::fuente()` (ahora público) y `TipoPreferencia::aTexto()` en vez
  de copias propias.
  (3) El docblock de `declararParametros()` volvió a su método.
  (4) `preferencias_usuario.valor` con `CHECK (btrim(valor) <> '')` (se editó la migración nueva; el desarrollador
  reconstruye su base al cerrar), con prueba.
  (6) `GuardarTema` anota el tema que llega por `tq-apariencia`: un cambio desde la pantalla no se vuelve a guardar.
  (7) La prueba de animaciones recorre las vistas de todos los módulos (`app/Modules/*/resources/views`).
  Sin cambio: (5) el índice de `user_id`, que repite el inicio del único `(user_id, clave)`; es inocuo y la convención
  pide índice en toda FK.
  `composer check` en verde (1355). Falta la revisión a la vista del desarrollador para cerrar.
- 2026-10-22 — El desarrollador revisó a la vista (letra grande con las cajas del inicio, modo oscuro, celular,
  reducir animaciones, pantalla al entrar): se ve bien. En la 1.4h, el cumpleaños pasa a «Mis datos» y sale «Mi
  cumpleaños» del menú (anotado en el mapa). `composer check` en verde (1355). Plan movido a
  `docs/planes/completados/` y 1.4g ✅ en el mapa. El PR lo abre el desarrollador. **Siguiente, según el orden de
  trabajo:** la 1.4d (ambientación de pantalla completa), lista antes del 25 de diciembre de 2026, respetando
  «reducir animaciones» (el gran momento quieto) desde el principio.
