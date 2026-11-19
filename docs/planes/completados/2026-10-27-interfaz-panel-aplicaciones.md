# Plan: Panel de aplicaciones

- **Estado:** completado
- **Módulo(s):** Nucleo (interfaz)
- **Fase:** F1
- **Creado:** 2026-10-27 · **Actualizado:** 2026-10-28

## Objetivo

En todas las pantallas, a la derecha y encima de la burbuja del chat, un panel flotante con accesos a aplicaciones web
(Word, Excel, PowerPoint y las que se agreguen): muelle con las 3 más usadas, cuadrícula con todas, o cerrado en un
botón redondo; recuerda en la sesión cómo lo dejó la persona.

## Contexto que leí

- Ningún documento de diseño lo menciona: pedido del desarrollador (2026-10-27), con maqueta aprobada
  (artefacto «Panel de aplicaciones», versión 2: https://claude.ai/artifact/G7WQr3Mf34V28sdnjnjWPR).
- La burbuja del chat es de la 1.14 (sin planear: pendientes «Sistema de tickets del colega» y «Chat de ayuda con IA»).
  El panel se alinea a su lugar (abajo a la derecha) y le deja el espacio, aunque todavía no exista.
- `docs/convenciones/interfaz.md`: nada encima del contenido salvo la ambientación de fechas. El panel expandido flota
  encima: es una excepción nueva que se anota allí.
- Patrones del desarrollador: cajas con buscador, panel encima sin recargar, íconos únicos.

## Decisiones

Aprobadas por el desarrollador el 2026-10-27.

- **D1 · En todas las pantallas**, a la derecha, separado del borde y alineado con la burbuja del chat (mismo borde
  derecho), sin taparla. Ancho máximo: 3 a 3,5 de cada 10 partes del ancho de la pantalla.
- **D2 · Tres estados (computadora):** **muelle** (columna angosta con las 3 aplicaciones más usadas, el nombre al pasar
  el mouse, botones para expandir y cerrar); **cuadrícula** (todas, tres por fila, con buscador; arriba las de Techquk,
  abajo las propias y «+ Agregar»; botones para volver al muelle y cerrar); **cerrado** (botón redondo con una cuadrícula,
  encima del chat y de su tamaño; al presionarlo vuelve al estado en que estaba).
- **D3 · Al iniciar sesión, el muelle.** Durante la sesión recuerda el estado en que la persona lo dejó.
- **D4 · Expandido, flota encima** del contenido (excepción a `interfaz.md`); el muelle y el botón casi no tapan nada.
- **D5 · «Las 3 más usadas»:** se cuenta cada vez que la persona abre una aplicación desde el panel. Sin uso todavía, las
  3 primeras del orden que puso administración (Word, Excel, PowerPoint).
- **D6 · Abren la versión web** (Microsoft 365 en línea y las páginas que se registren), en otra pestaña.
- **D7 · Quién agrega:** administración pone las de todos (Configuración); cada persona suma las suyas y oculta las de
  Techquk que no usa.
- **D8 · En el celular, sin muelle:** solo el botón redondo, que abre la cuadrícula a pantalla completa.
- **D9 · Con «reducir animaciones»**, sin animación al abrir o cerrar (como todo lo demás).

## Pasos

- [x] 1. Plan con las decisiones aprobadas.
- [x] 2. Datos (`/nueva-migracion`): aplicaciones de Techquk (nombre, enlace, ícono, orden, activa), las propias de cada
  persona, las ocultas por persona y el conteo de uso. Semilla: Word, Excel, PowerPoint. Servicio con «las 3 más
  usadas» y la lista para la cuadrícula; pruebas (incluida la de 3 sin uso).
- [x] 3. Acciones registradas (`/nueva-accion`): agregar, cambiar y quitar una propia; ocultar y mostrar una de Techquk;
  registrar un uso; y las de administración. Enlaces solo `https://`; pruebas sin interfaz.
- [x] 4. Pantalla: el panel en todas las pantallas (gancho de render de Filament), los tres estados, la memoria de la
  sesión, el buscador, el celular (D8) y D9; administración en Configuración; pruebas.
- [x] 5. Íconos: los de Microsoft 365 en línea y uno genérico para las demás (sin pedir nada a otro sitio).
- [x] 6. Pedirle al desarrollador que lo mire en la app; ajustar.
- [x] 7. `revisor-arquitectura`; la excepción en `docs/convenciones/interfaz.md`; mapa; `composer check` en verde.

## Criterio de aceptación

- Al iniciar sesión se ve el muelle con 3 aplicaciones; sin uso, Word, Excel y PowerPoint.
- Tras abrir varias veces otra aplicación, entra a las 3 del muelle.
- Cerrar, cambiar de pantalla y volver: sigue cerrado; al iniciar sesión otra vez, el muelle.
- El panel nunca tapa la burbuja del chat (o su lugar) y no pasa de 3,5/10 del ancho.
- Una aplicación propia de una persona no la ve otra.

## Bitácora

- 2026-10-27 — Rama `interfaz-panel-aplicaciones`, apilada sobre `interfaz-foto-y-redes` (solo su plan), que está sobre
  `interfaz-logo-animacion`. Decisiones conversadas con maqueta en la sesión del logo; la siguiente empieza por el paso 2.
- 2026-10-28 — Rama rehecha desde `main` (su único commit, el del plan, ya estaba en `main`). Paso 2: `aplicaciones` es un
  catálogo (`EsCatalogo`, código, orden, ícono de `IconoAplicacion`), para que administración la edite en Configuración →
  Catálogos en el paso 3 sin pantalla nueva (falta el caso en `Catalogo`). Por persona: `aplicaciones_propias` (con
  ícono, por defecto el genérico; nombre único sin mayúsculas), `aplicaciones_ocultas` y `usos_aplicacion` (de una de
  Techquk o de una propia, nunca las dos; la de una propia con FK compuesta a la misma persona y se borra con ella; la de
  Techquk la protege del borrado). Enlaces `https://` ya exigidos en la base. `AplicacionesService`: `delMuelle`,
  `deLaCuadricula`, `registrarUso` (INSERT … ON CONFLICT). Desempate del muelle: más usos, luego la abierta más
  reciente, luego el orden de la cuadrícula. Sin DBML: es pedido del desarrollador.
- 2026-10-28 — Fuera del plan, pedido del desarrollador: el logo de la pantalla de ingreso late a ratos al azar (golpes,
  fuerza y descanso distintos cada vez) y con el mouse muestra su cuadrícula y forcejea; se detiene con la pestaña
  oculta (`logo.blade.php`, `tqLogoEspera`). Tres commits aparte en esta rama.
- 2026-10-28 — Paso 3: seis acciones de cada persona, sin permiso (`Acciones/Aplicaciones/`: agregar, cambiar y quitar
  una propia, con versión; ocultar y mostrar una de Techquk, sin versión, como los módulos de la barra; registrar un
  uso). La propia de otra persona responde «no está en tu panel», igual que una que no existe. Enlaces: se normalizan
  como los de las redes y solo `https://`. Administración: caso `Catalogo::Aplicaciones` (grupo «Documentos y
  archivos»), con las acciones genéricas del catálogo (`nucleo.aplicaciones.crear`…) y el enlace https en su regla.
- 2026-10-28 — Paso 4: `PanelAplicaciones` (Livewire) en `BODY_END`, dentro de `rescue` (si falla, la pantalla sale
  igual). El estado cambia en el navegador y se guarda en la sesión sin redibujar; es estado de pantalla, no un dato,
  así que no pasa por una acción. Al iniciar sesión se borra (D3). Lo demás llama a las acciones del paso 3. Buscador
  sin acentos en el navegador; en edición (⚙): ocultar y mostrar las de Techquk, cambiar y quitar las propias. Medidas
  del chat como variables (`--tq-chat-*`) para la 1.14. Íconos: letras de color de muestra hasta el paso 5. La
  excepción D4 ya está en `interfaz.md`. Probado en Chromium sin ventana con la cuenta de prueba `gerencia.dev`: los tres
  estados, la memoria entre pantallas, el buscador, el formulario, el 30 % del ancho y el celular, sin errores de
  consola. **Ojo:** la base de desarrollo no tenía las migraciones del paso 2 y la app daba 500 en toda pantalla; se
  corrieron `migrate` y `NucleoSeeder` (solo crean lo que falta). `CatalogosPantallaTest` deja fuera las consultas del
  panel (como las del calendario del saludo).
- 2026-10-28 — Primera mirada del desarrollador. Paso 5: los íconos originales de Microsoft (Fluent UI, de su CDN de
  marca, copiados al repositorio en `resources/aplicaciones/`, licencia en `LICENCIAS.md`), incrustados como imagen
  `data:` para que sus degradados no choquen entre sí; el genérico, la inicial. Cambio de D2 pedido por él: la
  cuadrícula ya no tiene «volver al muelle»; su «—» aminora al muelle (en el celular, al botón), y el «—» del muelle
  lo cierra al botón. En el muelle, expandir arriba y aminorar abajo.
- 2026-10-28 — El desarrollador lo aprobó en su app. Paso 7: `revisor-arquitectura` pidió dos correcciones, hechas:
  ocultar es un INSERT … ON CONFLICT DO NOTHING (un doble clic ya no choca con la unicidad) y la excepción a la regla 7
  para el estado de pantalla en la sesión quedó escrita en `codigo.md`. De sus sugerencias: `registrarUso` traduce la FK
  rota (propia quitada en otra pestaña) a «no está en tu panel». No se tomaron: quitar índices de `user_id` que cubren
  los únicos (se siguió el patrón de `modulos_ocultos`), nombre único en el catálogo de Techquk (lo decide
  administración) ni prueba de concurrencia del conteo (no es obligatoria). `composer check` en verde (1855 pruebas)
  y, tras las correcciones, sus 50 pruebas del panel. Mapa al día; `interfaz.md` con la excepción D4 y el logo nuevo.
