# Plan: ajustes del núcleo (muelle, menú, barra, avisos y saludo)

- **Estado:** terminada (2026-11-10)
- **Responsable:** Sebastián (Núcleo; modo de trabajo solo, con sesiones en paralelo)
- **Módulo(s):** Nucleo
- **Fase:** F1
- **Creado:** 2026-11-09 · **Actualizado:** 2026-11-10

## Objetivo

Cinco ajustes de interfaz pedidos por el desarrollador el 2026-11-09 (tarea 1.4aa del mapa):

1. Mover un ícono del muelle o de la cuadrícula se siente fluido: arrastre con Pointer Events, sin espera, con los
   demás corriéndose en vivo.
2. «Mis avisos» sale del menú de la foto.
3. La primera sesión de una persona muestra solo las cajas, sin barra lateral.
4. El texto de la campana y el correo de «Mis preferencias › Avisos» sale una sola vez, como descripción de la sección.
5. El saludo es una sorpresa: sin preferencias, en cinco idiomas, adaptado a la hora, a la sesión y a lo que la
   persona hizo, con la frase estable por tramo del día.

## Contexto que leí

- `PanelAplicaciones.php`, `panel-aplicaciones.blade.php` y `panel-aplicaciones-app.blade.php`; las acciones
  `nucleo.poner-aplicacion-principal`, `nucleo.mover-aplicacion` y `nucleo.registrar-uso-aplicacion`; sus pruebas
  (`PanelAplicacionesPantallaTest`, `AccionesAplicacionesTest`).
- `AppPanelProvider::userMenuItems` y `CampanaYMisAvisosTest`.
- `navegacion.barra_modulos` (`NucleoServiceProvider`, por defecto apagada), `NavegacionService::barraVisible`,
  `BarraModulos::accionMostrar` y `EleccionDeModulos`, `UserFactory::conBarraModulos()`.
- `RegistroAvisos::registrarGrupo`, `MisPreferencias` (una sección por `GrupoPreferencia`, sin descripción).
- `SaludoPorReglas` (todo determinista con `numero()` sha256; idioma por persona y día; versículos por semana;
  frase sin repetir la de ayer), `SemillaSesion`, `IdiomaSaludo`, `frases-saludo.php` y `.fuente.json`,
  `ElegirCategoriasFrase` (sin permiso propio), `CategoriasDeFrases`, `categorias_frase_usuario`,
  `users.ultimo_acceso_at` y `RegistrarUltimoAcceso`, `auditoria_cambios`.
- `interfaz.md` (reducir animaciones: `html.tq-sin-animaciones` y `prefers-reduced-motion`).
- Nada de esta tarea depende de `pendientes.md`.

## Decisiones

Decididas por el desarrollador en el pedido del 2026-11-09, salvo las marcadas «propuesta del agente».

- **D1. Muelle con Pointer Events.** Se quita el arrastre nativo (`draggable`, `dragstart`, `dragover`) y la espera
  de 0,3 s. El arrastre empieza al moverse 5 px desde `pointerdown` (con `setPointerCapture`); si no hubo
  movimiento, es un clic y abre la app como hoy. Mientras se arrastra, una copia del ícono sigue al puntero y los
  demás se corren en vivo con FLIP (`transform`, ~150 ms). Al soltar, el ícono se acomoda en su lugar y recién ahí
  se llama a la acción que ya existe (`ponerPrincipal` en el muelle y en los 3 primeros lugares; `mover` en la
  cuadrícula). Si la acción devuelve un error, el orden vuelve a como estaba y se muestra el mensaje. Con
  «reducir animaciones», sin deslizamientos: solo el cambio de lugar. El clic que sigue a un arrastre se cancela
  (propuesta del agente).
- **D2.** «Mis avisos» sale del menú de la foto. La página se queda y se llega desde la campana.
- **D3. Primera sesión, solo cajas.** La preferencia ya viene apagada por defecto. Se busca la causa real con una
  prueba que reproduce el primer ingreso por la pantalla de login; lo que la prenda se corrige. Las pruebas que
  necesitan la barra la prenden con `conBarraModulos()`.
- **D4.** El texto se quita de cada preferencia de correo y va una vez como descripción de la sección Avisos:
  «Para todas estas preferencias: los avisos siempre llegan a la campana; los obligatorios también llegan por
  correo aunque lo apagues.» `GrupoPreferencia` gana `descripcion()` (nula en los demás grupos).
- **D5. Saludo sin preferencias.** Salen `saludo.ingles`, `saludo.frances` y `saludo.categorias_frase`, la acción
  `ElegirCategoriasFrase`, la fuente `CategoriasDeFrases`, el grupo `Saludo` y la tabla `categorias_frase_usuario`.
  `saludo.ambientacion` pasa a `apariencia.ambientacion`, con el mismo texto. Una migración nueva copia su valor
  guardado y borra las filas de las claves retiradas (propuesta del agente: migración y no limpieza a mano, porque
  las bases de desarrollo de cada copia ya tienen esas filas).
- **D6. Cinco idiomas.** `IdiomaSaludo` suma portugués (`pt`) y alemán (`de`). Todos los ven. El español domina:
  `saludo_porcentaje_espanol` aplica a todos y se corrige su descripción; también la de `saludo_dias_versiculo`.
- **D7. Un idioma distinto, de vez en cuando.** Como mucho uno por día y nunca dos sesiones seguidas. Para saberlo
  hace falta recordar el saludo anterior de la persona: se guarda en una tabla del núcleo, `saludos_mostrados`
  (`user_id`, `idioma`, `sesion`, `mostrado_at`), una fila por sesión (propuesta del agente; el núcleo es dueño, no
  lee modelos de otro módulo). La sesión es la de `SemillaSesion`.
- **D8. Se adapta a la hora, a la sesión y a lo que hizo.** Con lo que el núcleo ya sabe de la propia persona: sus
  saludos anteriores (`saludos_mostrados`: primera vez del día, vuelve tras un rato, vuelve el mismo día) y sus
  cambios de hoy en `auditoria_cambios` (si ya hizo cosas hoy, un «de vuelta» o un reconocimiento breve). Saludos
  nuevos con la condición `sesion` (`primera_del_dia`, `vuelve`, `vuelve_con_actividad`) en `frases-saludo.php`.
- **D9. Frase estable por tramo.** La frase de debajo cambia como mucho dos veces al día: un tramo hasta las 13:00 y
  otro desde las 13:00 (propuesta del agente; el corte coincide con el almuerzo de la jornada). Es estable dentro de
  cada tramo. El saludo de arriba puede cambiar por sesión.
- **D10. Contenido en portugués y alemán.** Natural, sin traducción literal. Versículos de traducciones de dominio público, con su cita: Lutero 1912 en
  alemán y, en portugués, la Bíblia Portuguesa Mundial (BPM), porque no hay una Almeida de dominio público que se
  pueda cotejar en línea (cambio aprobado por el desarrollador el 2026-11-09; el pedido decía «por ejemplo, Almeida»). Citas de autores solo si son reales y bien atribuidas.
- **D11. Pruebas deterministas.** Reloj fijo (`travelTo`) y la semilla de la sesión; sin `rand()` suelto.

## Pasos

- [x] 1. Plan, fila 1.4aa en el mapa.
- [x] 2. A2 y A4: menú de la foto y descripción de la sección Avisos, con sus pruebas.
- [x] 3. A3: prueba del primer ingreso y corrección (no hizo falta corregir: ver bitácora).
- [x] 4. A1: muelle con Pointer Events, con sus pruebas.
- [x] 5. A5a: quitar las preferencias del saludo, mover «Ambientación de la fecha» a Apariencia, migración de
      limpieza; descripciones de los parámetros; `CatalogosSeederTest` si cambia la lista.
- [x] 6. A5b: `saludos_mostrados`, idioma con sus límites, saludo por sesión y actividad, frase por tramo.
- [x] 7. A5c: frases y saludos en portugués y alemán (y los de sesión en los cinco idiomas); `FrasesSaludoSeeder`.
- [x] 8. `interfaz.md` y glosario si cambia algo; revisor de arquitectura (hay migración y tabla nueva).
- [x] 9. `composer check` en verde.

## Preparado para lo diferido

- `saludos_mostrados` crece una fila por sesión y por persona; su limpieza o paso al archivo histórico queda para
  cuando pese (revisor, 2026-11-09).

- El saludo sigue detrás de `GeneradorSaludo`, listo para la IA (sin activarla).
- `saludos_mostrados` deja la historia de saludos de cada persona para otras reglas de sesión.

## Criterio de aceptación

- Pruebas: `PanelAplicacionesPantallaTest` (arrastre con Pointer Events, reordenamiento y clic que abre),
  `CampanaYMisAvisosTest`, `BarraModulosTest` y `BarrasNavegacionTest` (primer ingreso sin barra),
  `MisPreferenciasTest` (descripción de Avisos; sin grupo Saludo), `SaludoPorReglasTest` (idiomas, límites por día
  y por sesión, tramo, sesión y actividad), `FrasesSaludoSeederTest` y `SaludoIntegridadTest`.
- Visible: el muelle se reordena sin tirones; el menú de la foto sin «Mis avisos»; una cuenta nueva entra sin
  barra; Avisos explica la campana una sola vez; el saludo cambia de idioma de vez en cuando.

## Bitácora

- 2026-11-09 — Plan escrito. La barra ya está apagada por defecto y ningún seeder la prende: la causa del primer
  ingreso con barra se busca en el paso 3.
- 2026-11-09 — Paso 2: «Mis avisos» fuera del menú de la foto. `GrupoPreferencia::descripcion()` (solo Avisos) va
  como descripción de la sección en «Mis preferencias»; las preferencias de correo quedan sin ayuda propia (una
  ayuda vacía no se dibuja).
- 2026-11-09 — Paso 3 (en espera de un dato del desarrollador): la prueba que reproduce el primer ingreso por la
  pantalla de login (`BarraModulosTest`, «la primerísima sesión…») pasa sin tocar nada: sin fila de preferencia,
  la página lleva `tq-barra-apagada` y el CSS esconde `.fi-sidebar`. Ningún seeder ni la bienvenida la prenden; solo
  `UserFactory::conBarraModulos()` en pruebas. Queda la prueba como regresión y se pregunta en qué cuenta y
  navegador se vio prendida (por ejemplo, la cuenta de desarrollo, que guarda la preferencia en la base compartida).
- 2026-11-09 — Paso 4: el muelle y la cuadrícula se arrastran con Pointer Events (`presionar`, `moverPuntero`,
  `soltarPuntero`; umbral `UMBRAL_PX: 5`, sin la espera de 0,15 s ni el arrastre nativo). La que cedería su lugar se
  corre en vivo con `element.animate` (no con `style`: Livewire lo borraría al redibujar); la copia que sigue al
  puntero vive en una capa `wire:ignore`. Las acciones son las mismas (`ponerPrincipal`, `mover`), que intercambian:
  por eso la vista previa es un intercambio (se corre solo la del lugar de destino), no un corrimiento de todas. Las
  medidas de los lugares se toman al empezar el arrastre (si la cuadrícula se desplaza en medio, no se vuelven a
  tomar). `touch-action: none` en cada ícono: en el celular, la cuadrícula se desplaza tocando fuera de los íconos.
- 2026-11-09 — Paso 3, cerrado: el desarrollador la vio prendida con la cuenta de desarrollo, que ya la había
  prendido antes (su preferencia está guardada en la base de desarrollo, compartida entre la carpeta principal y las
  copias). Es el comportamiento pedido: desde la segunda sesión, como la dejó. Sin cambio de código; queda la prueba.
- 2026-11-09 — Paso 5: sin grupo Saludo. Salen `PreferenciasSaludo` y `saludoDe()` (la ambientación se lee con
  `siNo($usuario, 'apariencia.ambientacion')`; la caché por petición ya la daba `guardadasDe`), `apagarCategorias`, la
  acción, la fuente de lista y el modelo `CategoriaFraseUsuario` (y su alias en el mapa de auditoría: nunca escribió en
  la bitácora de cambios). La acción no tenía permiso: `CatalogosSeederTest` no cambia. Migración
  `2026_11_09_000001_retirar_preferencias_del_saludo`: copia `saludo.ambientacion` a `apariencia.ambientacion` (si ya
  había fila nueva, se queda), borra las cuatro claves y la tabla; `down()` la recrea vacía y devuelve la clave. Las
  categorías activas para la empresa siguen filtrando. Mientras llega el paso 6, el idioma extra se elige entre los
  idiomas que tienen algún saludo o frase. En las pruebas del saludo, el porcentaje de español va a 100 en
  `beforeEach` (antes lo daba apagar inglés y francés en la persona).
- 2026-11-09 — Paso 6: `IdiomaSaludo` suma `pt` y `de` (respaldo «Olá» y «Hallo»). Migración
  `2026_11_09_000002_crear_saludos_mostrados`: el CHECK de idioma de `frases_saludo` acepta los cinco; columna
  `frases_saludo.sesion` (`primera_del_dia`, `vuelve`, `vuelve_con_actividad`; solo saludos, sin ocasión ni fuera de
  horario, lo exige la base y lo dice `ReglasFrase`; campo nuevo en el catálogo); tabla `saludos_mostrados` (user_id,
  idioma con CHECK, sesion, mostrado_at, `auditoria()` como `momentos_vistos`; única por persona y sesión). Solo la
  lee y escribe `Saludo/HistorialSaludos` (también lee `auditoria_cambios` para la actividad de hoy), que entrega un
  `ContextoSesion`. Decisiones:
  - El idioma es de la sesión y se guarda la primera vez que se calcula (`createOrFirst`; sin sesión, texto vacío, no
    se guarda). Se guarda el idioma que se mostró, ya con los respaldos a español. Otro idioma solo si la sesión
    anterior fue en español y hoy no hubo otro; entonces el porcentaje (semilla persona|fecha|sesión). Los candidatos
    son los idiomas con alguna frase (sin frases en ninguno, con algún saludo): la frase decide el idioma, y un idioma
    con solo saludos caería al español y gastaría la sesión del día. Se evita repetir el último otro idioma.
  - Clase de sesión: sin fila de hoy antes de esta, `primera_del_dia`; si hay, `vuelve_con_actividad` cuando la
    persona tiene alguna fila suya en `auditoria_cambios` de hoy antes de la sesión, y si no `vuelve`. «Antes» de una
    sesión ya guardada son las filas de id menor: la clase y el idioma no cambian mientras dura.
  - Saludo: la ocasión y fuera de horario mandan; luego, si hay saludos de la clase de sesión en el idioma, salen 9 de
    cada 12 sesiones al volver y 4 de cada 12 en la primera del día (sin saturar); `vuelve_con_actividad` sin los suyos
    usa los de `vuelve`. Si no, la mezcla de siempre (los de sesión no entran en ella).
  - Frase por tramo (corte a las 13:00, `HORA_SEGUNDO_TRAMO`): la de la mañana es la rotación por día de antes; la de
    la tarde va corrida dentro de la lista (nunca 0 y, con tres o más, distinto del paso). Así el mismo tramo de ayer no
    se repite y la tarde no repite la mañana; se excluyen además, si la lista cambió, la del mismo tramo de ayer y (de
    tarde) la de la mañana. La frase sale en el idioma de la sesión: la sesión en otro idioma (una al día como mucho)
    ve la del tramo en ese idioma; las demás, la del tramo en español, estable. Los días de versículo valen para los
    dos tramos. Fuera de horario, la especial se decide por tramo y situación (antes, por sesión), estable en el tramo.
  - Contenido: los saludos de sesión en español (`sesion-primera-*`, `sesion-vuelve-*`, `sesion-actividad-*`) y, para
    que «un saludo por idioma y momento» siga valiendo, lo mínimo en portugués y alemán (`saludo-{manana,tarde,noche}-pt`
    y `-de`). El resto, en el paso 7.
  - Pruebas: `SaludoSesionTest` (nuevo, con `travelTo`); en `SaludoHorarioTest`, la frase por tramo y la especial por
    tramo (400 días en lugar de 400 sesiones). El glosario no cambia (no es término del negocio).
- 2026-11-09 — Paso 7: contenido en `frases-saludo.php` (el seeder no cambia: ya leía `sesion`).
  - Saludos de sesión: inglés 7, francés 6, portugués 6, alemán 6 (2 o 3 por clase de sesión).
  - Portugués (Brasil, «você»): saludos 29 nuevos (fuera de horario 9, momento 3 más los 3 del paso 6, a cualquier
    hora 3, día de la semana 3, ocasiones 5: las mismas que el inglés, sin aniversario); frases 43 (propias 16, con 3
    de fuera de horario; refranes 9; autores 2: Pessoa, «Mar Portuguez» y «O Infante»; versículos 16).
  - Alemán («du»): saludos 28 nuevos (igual que el portugués, con 2 de día de la semana); frases 44 (propias 16,
    refranes 9, autores 3: Schiller, «Das Lied von der Glocke» y Wilhelm Tell; Goethe, «Das Göttliche»; versículos 16).
  - Versículos, los mismos 16 en los dos idiomas, con las etiquetas del español: Éxodo 33:14 (fuera de horario),
    Proverbios 16:3, Salmos 118:24 y Lamentaciones 3:22-23 (mañana), Salmos 4:8 (noche), Mateo 11:28 (tarde),
    Josué 1:9 (lunes), Lucas 2:11 y 2:14 (Navidad), Mateo 28:6 y Juan 3:16 (Semana Santa), Salmos 65:11 (Año Nuevo),
    Números 6:24-26 (cumpleaños), Gálatas 6:9, Filipenses 4:13, Salmos 23:1. Solo de dominio público (pedido del
    desarrollador), cotejados palabra por palabra en ebible.org: Lutero 1912 (`deu1912`) y, en portugués, la Bíblia
    Portuguesa Mundial (`porbrbsl`, cita «(BPM)»). No hay Almeida de dominio público cotejable en línea: la Revista e
    Corrigida en línea es la de 2009 (SBB, con derechos); la de 1819 en Wikisource no tiene esos libros; la Tradução
    Brasileira de Wikisource es de segunda mano y mezcla capítulos. La BPM es un borrador en revisión: el texto queda
    fijo en lo cotejado. De ella se quitan solo las comillas sin pareja (Josué 1:9, Mateus 11:28). En alemán, cita con
    coma y numeración de Lutero: Psalm 4,9 y 65,12 (ebible usa la inglesa); Psalm 23,1 sin el título. Lo cotejado
    corrigió dos de memoria («darinnen», Psalm 118,24; «vorangehen», 2. Mose 33,14).
  - Citas de autores cotejadas en Wikisource contra la edición original escaneada; corregidas a su texto y ortografía:
    «Tudo vale a pena Se a alma não é pequena» («Mar Portuguez», 1934), «Von der Stirne heiß Rinnen muß der Schweiß,
    Soll das Werk den Meister loben, Doch der Segen kommt von oben» (1800: coma, no punto y coma), «Edel sey der
    Mensch, Hülfreich und gut!» (1789). Como los versículos, cada verso empieza con mayúscula. Ninguna se quitó.
  - En un día de versículo, la sesión en un idioma sin versículo pasa al español (`SaludoPorReglas`, regla 6); con
    portugués y alemán ya no hace falta.
  - `FrasesSaludoSeederTest`: frases en los cinco idiomas y el formato de cita de BPM y Luther 1912.
- 2026-11-09 — Decisión del desarrollador: los versículos en portugués quedan de la Bíblia Portuguesa Mundial (BPM,
  dominio público, cotejada el 2026-11-09), porque no hay una Almeida de dominio público que se pueda cotejar en línea.
- 2026-11-09 — Revisor de arquitectura: nada bloqueaba el código. Corregido: `interfaz.md` (grupos de «Mis
  preferencias» y la descripción de Avisos, la frase por tramo, idioma y sesión, los versículos, la ambientación en
  Apariencia, «Mis avisos» fuera del menú, la fecha simulada); D10 dice la BPM; con fecha simulada del modo
  desarrollo el saludo no se guarda en `saludos_mostrados` (prueba en `SaludoSesionTest`). Anotado: D1 quedó como
  intercambio (solo se corre el ícono del destino, porque las acciones intercambian dos; bitácora del paso 4); «vuelve
  con actividad» solo ve los cambios de las tablas con historial (`auditoria_cambios`); la fila 2.21 ✅ del mapa es
  intencional (la 2.21 se fusionó en el PR #84). Los `down()` de las dos migraciones nuevas no tienen prueba propia.
- 2026-11-10 — Cierre. La primera corrida completa no arrancó (`enLima()` repetida en `SaludoSesionTest` y
  `AmbientacionTest`; renombrada). La segunda falló solo en `PreferenciasNuevasTest` (la lista exacta de Apariencia no
  tenía «Ambientación de la fecha»); corregida y corrida sola. La tercera, en verde: pint, phpstan, 68 migraciones y
  2787 pruebas. Para el PR: cambia `interfaz.md` (grupos de preferencias, saludo, muelle, «Mis avisos»); probar a mano
  el arrastre del muelle con mouse, dedo y animaciones reducidas.
