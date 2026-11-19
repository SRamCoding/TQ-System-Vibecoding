# Plan: trabajo en paralelo con varias sesiones

- **Estado:** terminado
- **Responsable:** Sebastián
- **Módulo(s):** pruebas y herramientas del repositorio
- **Fase:** F2
- **Creado:** 2026-11-04 · **Actualizado:** 2026-11-04

## Objetivo

Que dos o más sesiones de Claude Code trabajen la etapa 2 a la vez en la misma máquina sin romperse entre sí, cada
una en su copia del repositorio, y que la carpeta principal siga siendo la de la app del desarrollador.

## Contexto que leí

- `phpunit.xml`: la base de pruebas estaba fija en `techquk_test` (con `force`), y la de concurrencia en
  `techquk_concurrencia` (`tests/Concurrencia`). Dos sesiones en paralelo se pisaban hasta en las pruebas chicas.
- `AGENTS.md` y `CLAUDE.md`: «nunca dos corridas a la vez»; la app corre sobre la carpeta principal.
- ADR 0020: dos sesiones al inicio (Presupuesto y costo, y Comercial).

## Decisiones

- **D1.** Cada sesión trabaja en un `git worktree` hermano (`../TechQuk-<nombre>`), creado sin rama sobre `main`;
  la sesión crea la rama de su tarea. La carpeta principal no cambia de rama.
- **D2.** La base de pruebas se deduce de la carpeta, solo en un worktree (su `.git` es un archivo):
  `techquk_test_<nombre>` y `techquk_concurrencia_<nombre>`. La principal y la integración continua no cambian.
- **D3.** El script `scripts/nueva-copia.php` lo corre la persona, porque copia el `.env`.

## Pasos

- [x] 1. `tests/bootstrap.php` (la base por copia) y `phpunit.xml` apuntando a él.
- [x] 2. Concurrencia: la base y sus controles salen de `TECHQUK_BASE_CONCURRENCIA`.
- [x] 3. `scripts/nueva-copia.php`.
- [x] 4. `AGENTS.md` («Varias sesiones en paralelo») y `CLAUDE.md`.
- [x] 5. Probar una copia real: sus pruebas usan su base, sin tocar la de la principal.

## Preparado para lo diferido

Sumar una tercera o cuarta sesión es correr el script con otro nombre: no hay nada que cambiar.

## Criterio de aceptación

- En la carpeta principal, `tests/Concurrencia`, `tests/Arch` y una prueba de muestra pasan como antes (37 en verde).
- En una copia, las pruebas corren contra `techquk_test_<nombre>`.

## Bitácora

- 2026-11-04 — Pasos 1 a 4. En la principal pasan concurrencia, arquitectura y una de muestra (37). Falta probar una
  copia real: la crea el desarrollador con el script.
- 2026-11-04 — Paso 5: el desarrollador creó `../TechQuk-presupuesto` con el script (worktree, `.env`, dependencias y la
  base `techquk_test_presupuesto`, creada por el script). En la copia, `BienvenidaConfiguracionTest` pasó (11) contra
  `techquk_test_presupuesto`, que quedó con sus 54 migraciones; la base de concurrencia de la copia es
  `techquk_concurrencia_presupuesto`. La de la principal (`techquk_test`) no se tocó.
