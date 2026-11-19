# 0010 — Migraciones editables hasta el primer despliegue con datos reales

- **Estado:** aceptada
- **Fecha:** 2026-10
- **Decide:** responsable técnico

## Contexto

Una sola persona, sin datos reales todavía. Prohibir editar migraciones desde el día uno acumularía decenas de migraciones de corrección.

## Decisión

Hasta el primer despliegue con datos reales se pueden editar migraciones y reconstruir la base. Ese día se crea el archivo vacío `.claude/PRODUCCION` y se hace commit: desde entonces el hook bloquea editar migraciones que ya están en `origin/main`.

## Consecuencias

- Cambiar el modelo es barato mientras no hay datos reales: conviene hacer los cambios grandes antes de ese día.

## Alternativas descartadas

—
