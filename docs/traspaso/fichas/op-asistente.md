# Ficha · Asistente de ayuda «n_n» (opcional, plan 1.14)

- **Estado:** aprobada (MV-009)
- **Versión:** v1
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
