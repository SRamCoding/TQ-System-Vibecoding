# Ficha · Asistente de ayuda «n_n» (opcional, plan 1.14)

- **Estado:** en curso (v2, ronda de cambios; la v1 quedó aprobada)
- **Versión:** v3
- **Tramo de la ruta:** opcionales (MV-014)
- **Aprobada:** 2026-11-20 · etiqueta `aprobado/op-asistente-v1`
- **Fuentes del diseño:** `docs/planes/activos/2026-10-29-ayuda-asistente.md` (D1 a D9).

## Qué hace

Un personaje de ayuda, la carita «n_n» dorada con cuerpo, brazos y una estrellita, vive en todas las pantallas. Se
mueve con intención: al entrar, junto a lo que se mira primero; si la persona escribe, se aparta al otro lado; si no
pasa nada, cambia de meta cada 7 a 16 s; se puede arrastrar. Sigue el mouse con los ojos y se duerme («z z z») tras 20
minutos sin actividad. Habla poco (un globo con frases de ayuda de la pantalla y, a veces, «de la vida» con respuestas
en botones). Un clic abre el chat, que responde dudas del sistema y, ante un problema, propone un ticket que solo se
abre si la persona lo confirma. Clic derecho: descansar o callar por un rato. Nunca ejecuta acciones del negocio.

## Decisiones de Sebastián

- **MV-063** (ficha) · El asistente vuelve a funcionar; una frase se fuerza con `tqAsistente.probarFrase()`.
- **MV-040** (ficha) · Franja fija de filtros en Tickets de ayuda; «abierto» pasa a «Sin atender».
- **MV-038** (ficha) · Ícono propio de Tickets de ayuda.
- **MV-014** (ficha) · Entra al MVP.
- **MV-009** (general) · Aprobada sin revisión, como todas hasta terminar el sistema.
- Del plan del repo original (aprobadas allá): D1 a D9 en su versión base (ver supuestos).

## Pantallas y navegación

- **Todas las pantallas:** el personaje (64 × 72 px) flotando; empieza en la esquina inferior derecha.
- **Globo:** el texto, botones de respuesta (en las frases «de la vida») y siempre «Ahora no» (calla 30 min).
- **Chat** (un clic): panel abajo a la derecha, «n_n · Ayuda del sistema», mensajes y campo «¿Cómo registro una
  factura?»; si la respuesta detecta un problema, botón **«Abrir ticket»** → «Listo: abrí el ticket TK-2026-0003…».
- **Menú** (clic derecho): Descansar 1 h / 2 h / 4 h / hasta mañana (8:00), y lo mismo para Callar. Descansando duerme
  en la esquina; callado pasea sin hablar; un clic lo devuelve a lo normal.
- **Tickets de ayuda** `/tickets-ayuda` (caja en el área Núcleo): ticket y fecha, quién, qué pasó (en qué pantalla),
  estado; soporte (quien administra roles) los toma y los resuelve; «Conversación» muestra el chat.

## Operaciones

| Operación | Campos | Valida | Qué produce |
|---|---|---|---|
| Preguntar (POST `/asistente/chat`) | pregunta (≤ 500), pantalla | sesión iniciada | respuesta; con IA (`IA_CLAVE_ANTHROPIC`) la redacta el modelo con la ayuda del sistema (las cajas del inicio y sus preguntas) y la pantalla; sin IA, la pantalla que mejor responde; si menciona un problema (error, falla, no funciona, no puedo, no me deja…), propone ticket |
| Abrir ticket (POST `/asistente/ticket`) | resumen, pantalla, conversación | — | `TK-AAAA-NNNN` abierto |
| Tomar / Resolver ticket | — | soporte | en curso / resuelto |

## Reglas y números

- Cuándo habla (D4): nunca con movimiento reducido, con el chat abierto, dormido, si se escribió hace menos de 90 s,
  con «Ahora no» vigente; máximo 3 veces por hora y 10 min entre una y otra; no repite una frase en la misma pantalla.
- Qué dice (D5): frases de ayuda de 8 pantallas y, una de cada cinco veces más o menos, 4 «de la vida» con respuestas.
- Demo: 2 tickets abiertos (cerrar la semana de tareo; el PDF de la cotización sin logo).

## Supuestos sin confirmar

- El personaje es SVG y CSS propios (no Rive); sin gestos de hablar, pensar o contento distintos del tranquilo.
- Descansar, callar, el silencio y las frases ya dichas viven en el navegador (localStorage), no como preferencias
  registradas de la persona; el catálogo de frases está en el código (no editable).
- Los tickets viven en el sistema (no hay API de un sistema de tickets externo).
- Sin las excepciones de interfaz aprobadas por escrito («nada encima del contenido», «ningún bucle»).

## Atajos tomados

- La conversación llama al cliente del modelo directo (sin el proceso registrado, tope ni registro de la IA del núcleo).
- Las rutas del chat están en `routes/web.php` con funciones anónimas.

## Dónde está en el código

`app/Modules/Nucleo/resources/views/filament/asistente.blade.php` (personaje, iniciativa, chat y menú; al final del
cuerpo en `AppPanelProvider`), `Nucleo/Services/AsistenteService.php`, `Nucleo/Filament/Pages/TicketsAyuda.php`,
`routes/web.php`, migración `2026_11_20_700001_crear_tickets_ayuda.php`.

## Cambios desde la versión anterior

- **v2** (MV-038, MV-039, MV-040): ícono propio; sin el texto que explicaba la pantalla; **franja fija de filtros**
  arriba de la lista:
  - **Estado como pestañas con contador:** Pendientes (sin atender + en curso; se abre al entrar), Sin atender, En curso,
    Resueltos, Todos.
  - **Buscador** (código, resumen y conversación, sin esperar a Enter) y **filtros en botones**: Proceso (el de la
    pantalla donde pasó, entre las 11 cajas), Quién lo abrió (solo soporte), Fecha (hoy, esta semana, este mes o un
    rango), Esperando más de (2 o 7 días). Activo: lleno en el azul de resaltado con su valor y «✕» para quitarlo;
    inactivo: borde punteado y «+». «Limpiar filtros» cuando hay alguno.
  - **Lista:** código (con fecha y hora), problema, proceso y pantalla, quién lo abrió, estado (Sin atender ámbar, En
    curso azul, Resuelto verde) y esperando (días; ámbar desde 2). **Orden:** pendientes del más antiguo al más nuevo;
    resueltos del más nuevo al más antiguo.
  - **Se recuerdan** los filtros de cada persona al salir y volver (en su sesión).
  - **Estado:** el código `abierto` pasa a `sin_atender` (migración que cambia los existentes y el valor por defecto).
    En los tickets de postventa, el estado `abierto` se muestra «Sin atender».
  - **Demo:** 12 tickets en 8 procesos: 6 sin atender, 3 en curso, 3 resueltos, con esperas de 0 a 15 días.
- **Supuestos:** «se recuerdan» dura la sesión (no se guarda en la base); el proceso se deduce de la dirección de la
  pantalla sin mirar permisos.
- **Atajos:** la lista se filtra en PHP sobre todos los tickets (sin consulta paginada); el código de estado de los
  tickets de postventa no cambió, solo su nombre.
- **v3** (MV-063): **el asistente no corría**. Causa: en las plantillas Blade, una línea que termina en `@js(...)`
  pierde su salto de línea (PHP descarta el salto que sigue a `?>`), y el script, escrito sin punto y coma, quedaba con
  dos sentencias pegadas: error de sintaxis y nada se ejecutaba (ni el clic, ni el paseo, ni los globos, ni el menú). Lo
  mismo rompía el script del cielo (la ubicación de la sede) y el de la ambientación de fechas. Arreglo: punto y coma al
  final de esas cuatro líneas. **Regla para el repo original:** en un script de una plantilla, nunca terminar una línea
  en una directiva sin punto y coma. **Comprobado** en un navegador sin pantalla (Chromium), al cargar, después de
  navegar a otra pantalla sin recargar y al volver: el clic abre el chat, el clic derecho abre el menú con sus 8
  opciones, el personaje cambia de meta y los globos salen. **Para probar los globos** sin esperar sus reglas:
  `tqAsistente.probarFrase()` en la consola del navegador.
