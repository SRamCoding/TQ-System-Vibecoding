# Planes de ejecución

Un plan es la memoria de trabajo entre sesiones y el **punto de guardado**: si una sesión se corta (por ejemplo, por límite de uso), la siguiente lee el plan y continúa. Se crea para cualquier tarea que toque más de
un archivo o que no termine en una sola sesión.

- `activos/` — en curso o bloqueados. Nombre: `AAAA-MM-DD-modulo-tema.md`.
- `completados/` — se mueven aquí al terminar, con la bitácora completa.

El orden general está en `mapa-etapas.md`.
Hasta dónde se trabaja antes de repartir la etapa 2, en `corte-reparto.md`.

Reglas:

1. Antes de escribir código, el plan existe y lista qué se leyó.
2. Al cerrar cada sesión, la bitácora dice dónde quedó el trabajo y qué sigue.
3. Si aparece una decisión pendiente (`docs/decisiones/pendientes.md`), el plan pasa a
   `bloqueado` y se pregunta. No se inventa la respuesta.
4. Al terminar, el plan pasa a `completados/` y cualquier regla nueva aprendida se lleva a
   `AGENTS.md` o al doc de convenciones que corresponda.
