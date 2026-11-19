@AGENTS.md

## Específico de Claude Code

- Flujos disponibles: `/nuevo-modulo`, `/nueva-migracion`, `/nuevo-documento`, `/nueva-accion`,
  `/automatizacion-ia`, `/cerrar-tarea`.
- Flujo de entregas (decisión del desarrollador, 2026-11-10; detalle en `AGENTS.md`, «Cómo trabajar»): las tareas
  se apilan en la rama `entrega/<nombre>`. `composer check` completo, solo una vez por etapa. Al cerrar cada tarea, `composer check:tarea`; al cerrar la entrega,
  `composer check:tarea` otra vez (decisión del 2026-11-12), y después subes la rama y avisas con los enlaces a los planes.
- Antes de cerrar una entrega con cambios de estructura (módulos, migraciones, dependencias,
  efectos entre módulos), pide revisión al subagente `revisor-arquitectura`.
- Los hooks formatean el PHP que edites y bloquean cambios a `.env`. Las migraciones se pueden
  editar hasta el primer despliegue con datos reales; desde entonces, el hook las bloquea
  (`docs/decisiones/0010-migraciones-editables.md`). Si un hook te bloquea, lee el mensaje y corrige.
- El desarrollador trabaja en Windows con WSL2 o Docker. Scripts en PHP, no en bash ni PowerShell.
- Antes de lanzar `composer check` (cierre de la etapa) o `composer check:tarea` (cierre de cada tarea), revisa
  con `pgrep -af "artisan test"` que no haya otra corrida: dos a la vez en la misma copia comparten la base de pruebas y se rompen entre sí (una copia de trabajo distinta usa su propia base,
  pero dos corridas completas cargan la máquina: mejor esperar). Dentro de un comando que ya contiene ese texto, `pgrep -f` se
  encuentra a sí mismo: ahí usa `ps -eo cmd | grep "[a]rtisan test"`. Revísalo también después de detener una corrida:
  sus procesos pueden seguir vivos y tumbar la siguiente (pasó el 2026-10-27).
- Cuida el cupo: lee partes de documentos, no documentos completos; abre una sesión limpia por tarea.
