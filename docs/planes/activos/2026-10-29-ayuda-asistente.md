# Plan: asistente de ayuda con personalidad (1.14)

- **Estado:** bloqueado (falta la API del sistema de tickets y la excepción del chat; ver «Contexto que leí»)
- **Módulo(s):** Nucleo (M12 e interfaz)
- **Fase:** F1
- **Creado:** 2026-10-29 · **Actualizado:** 2026-10-29

## Objetivo

Un asistente de ayuda con forma de personaje (la carita «n_n» dorada) que vive en todas las pantallas: se mueve con algo
de azar pero hacia donde la persona mira primero, pregunta poco y escucha, ayuda con el sistema por un chat y, si algo
falla, propone un ticket que la persona confirma. Se puede poner a descansar o callar por unas horas.

## Contexto que leí

- Pedido del desarrollador (2026-10-28): «que tenga una personalidad amable y diligente… moviéndose libremente por la
  pantalla, no de forma programable o fija, sino con esa sensación de aleatoriedad pero con sentido… que haga preguntas
  casuales… recomendaciones… expresivo y propositivo… un balance entre ser hablador y escuchador… que incluso a veces
  se ponga a hablar de la vida». Mapa, fila 1.14 (burbuja, chat que conoce el sistema, tickets del sistema del colega,
  ícono «n_n» dorado).
- **Vista previa aprobada** (2026-10-29): https://claude.ai/artifact/EBm7WqezYd7NVyzoLXZT86 (versión 3). El desarrollador
  la revisó en tres vueltas y la dio por suficiente: «ya con eso es suficiente».
- Pendientes que **bloquean** (no se inventan): «Sistema de tickets del colega» (su API) y «Chat de ayuda con IA» (la
  excepción a `ia.md` para un chat de texto libre). Depende de la 1.11 (mecanismo de IA).
- `docs/convenciones/interfaz.md`: «nunca nada encima del contenido» (excepción: el panel de aplicaciones), «ningún
  bucle» y «reducir movimiento». `ia.md`: salida estructurada, la IA sugiere y una persona confirma.

## Decisiones (de la vista previa)

- **D1. Tres capas.** (1) El cuerpo (moverse y expresarse) y (2) la iniciativa (cuándo habla y qué dice) corren en el
  navegador, **sin IA ni costo**. (3) La conversación del chat usa la IA de la 1.11 (tope, autorización, registro).
- **D2. Moverse con intención.** Siempre tiene una meta y frena al llegar, con un vaivén al azar (nunca en línea recta
  perfecta):
  - al entrar a una pantalla, junto a lo que se mira primero (el título, el buscador, el primer campo);
  - si la persona escribe, al lado contrario, para no estorbar; si aparece un error, junto al aviso;
  - si no pasa nada, cada 7 a 16 s otra meta: 6 de cada 10 junto a un elemento del contenido, el resto en un margen
    libre. Nunca encima de un campo, botón o texto en uso;
  - se puede arrastrar: se queda donde lo dejan un rato.
- **D3. Estados con gesto propio:** tranquilo («n_n»), curioso (ojos que siguen al mouse), hablando, escuchando,
  pensando, contento y dormido («z z z», tras 20 min sin actividad; despierta al volver). Flota, respira, se inclina al
  avanzar y mueve los brazos.
- **D4. Cuándo habla** (lo revisa cada pocos segundos; si una regla se cumple, calla): movimiento reducido; chat
  abierto o ya hablando; dormido; escribió hace menos de 90 s; silencio pedido («Ahora no»: 30 min) o dos preguntas
  seguidas sin respuesta (45 min); ya habló el máximo de la hora (por defecto, **3**); habló hace menos de 10 min.
- **D5. Qué dice:** de un **catálogo editable** (como las frases del saludo), según la pantalla: ayuda, recomendación y,
  más o menos una de cada cinco, **de la vida** («¿Qué tal empezó la semana?»), con respuestas en botones; escucha y
  contesta corto. No repite en la misma pantalla. Reglas de contenido de las frases: amable, sin halagar ni exagerar,
  neutro en género, respetuoso del cristianismo, con la ortografía de la 1.4w.
- **D6. Chat y tickets.** Un clic lo abre. Responde dudas del sistema (con la IA: lo que sabe sale de la ayuda del
  sistema y de en qué pantalla está la persona; **no ve los datos de los registros** salvo lo que Gerencia autorice).
  Ante un problema, **propone** un ticket con el resumen y la pantalla, y solo se abre si la persona lo confirma. Nunca
  ejecuta acciones del negocio.
- **D7. Descansar y callar** (sin chat, sin IA): **clic derecho** (o la tecla de menú, o Mayús + F10) abre un menú
  con **Descansar** y **Callar**, cada uno por 1 h, 2 h, 4 h o hasta mañana (8:00). Descansando, se duerme en la esquina
  y la actividad no lo despierta; callado, pasea sin hablar. **Un clic** lo devuelve a lo de siempre; si ya estaba
  normal, el clic abre el chat. Cada opción es una **acción registrada** de la persona (sus preferencias del asistente).
  Se descartaron los pedidos por chat (adivinar frases parecidas).
- **D8. Movimiento reducido** (del equipo o «Reducir siempre»): quieto en la esquina y solo habla si le hablan.
- **D9. El personaje:** la carita «n_n» dorada con cuerpo, brazos y una estrellita, como en la vista previa. Para la
  animación real, evaluar **Rive** (máquina de estados; anotar su licencia y versión antes de agregarlo, como el ADR
  0019) frente a SVG y CSS propios.

## Excepciones que hay que aprobar antes de construir

1. A «nunca nada encima del contenido» (`interfaz.md`): el personaje, con sus límites (nunca tapa lo que se usa, el
   clic lo atraviesa salvo sobre él, se aparta solo).
2. A «ningún bucle»: su movimiento continuo; quieto con el movimiento reducido.
3. A `ia.md`: el chat de texto libre (pendiente «Chat de ayuda con IA»), con sus límites: solo explica y propone
   tickets, no ejecuta acciones, no lee datos sensibles, tope mensual, y si puede hablar de temas fuera del trabajo.

## Preguntas abiertas

- ¿Un nombre para el personaje?
- ¿El máximo por hora queda en 3?
- ¿Cuánto de «la vida»: una de cada cinco, o solo en ciertos momentos (al llegar, antes del almuerzo, los viernes)?
- ¿Para todos desde el inicio o primero un grupo pequeño?

## Pasos (tentativos, al desbloquearse)

- [ ] 1. Excepciones aprobadas y anotadas en `interfaz.md` e `ia.md`.
- [ ] 2. El personaje en la interfaz: cuerpo, estados, movimiento con metas (D2, D3, D8).
- [ ] 3. Iniciativa: reglas de cuándo habla y catálogo de frases editable (D4, D5).
- [ ] 4. Acciones de la persona: descansar, callar y volver a lo normal (D7).
- [ ] 5. Chat con la IA de la 1.11 y propuesta de ticket (D6); el envío, con la API del sistema de tickets.
- [ ] 6. `composer check` en verde; plan a completados; PR.

## Criterio de aceptación

- Lo que se probó en la vista previa, en la app real, con pruebas de cada regla de D4 y D7.

## Bitácora

- 2026-10-29 — Plan escrito desde la vista previa aprobada. Bloqueado por los dos pendientes; va después de la 1.11.
