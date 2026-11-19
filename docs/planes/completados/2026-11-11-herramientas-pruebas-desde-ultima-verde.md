# Plan: `check:tarea` mide desde la última corrida verde

- **Estado:** completado
- **Responsable:** Sebastián (herramientas de desarrollo; modo actual: trabaja solo)
- **Módulo(s):** — (herramientas de desarrollo: `scripts/pruebas-de-la-tarea.php`)
- **Fase:** —
- **Creado:** 2026-11-11 · **Actualizado:** 2026-11-15

## Objetivo

`composer check:tarea` elige las pruebas de lo que cambió **desde su última corrida en verde** en esa copia de
trabajo, no desde que la rama salió de `main`. Así cada corrida prueba solo lo tocado desde la anterior y no crece con
cada paso de la tarea. Si no hay una corrida verde registrada, se comporta como hoy.

## Contexto que leí

- `scripts/pruebas-de-la-tarea.php`, `composer.json` (`check`, `check:tarea`), `tests/bootstrap.php` (bases por copia),
  `AGENTS.md` («Pruebas por tarea»), `docs/equipo/1-como-trabajamos.html` §07, plan
  `completados/2026-10-27-mantenimiento-pruebas-paralelo.md`.
- Medición antes del cambio (simulando un cambio por archivo, `--listar`): `PresupuestosService` → 5 de 193 archivos,
  `ArbolPartidasService` → 9, `ProyectosService` → 11, `MotivosService` → 117. El problema que se ataca aquí no es ese
  abanico, sino que la base fija (`merge-base` con `origin/main`) acumula todo lo de la rama en cada corrida.
- Pedido del desarrollador (2026-11-11): que cada corrida identifique antes solo lo necesario. Eligió esta opción
  (la 1) frente al mapa por cobertura (la 2), que queda para después.
- Pendientes: ninguno afecta.

## Decisiones

- **D1 (2026-11-11, Sebastián).** Punto de partida: la última corrida verde de `check:tarea` en la copia; si no hay, la
  base de siempre. `--base=<ref>` fuerza la base (para revisar la tarea entera).
- **D2.** La «foto» de la corrida verde es el árbol completo de la copia (con lo que no tiene commit y los archivos
  nuevos), guardado como objeto de git con un índice temporal; la comparación es árbol contra árbol. Se guarda en la
  carpeta de git de la copia (`git rev-parse --git-path`): cada worktree tiene la suya y nunca entra a un commit.
- **D3.** Solo se guarda la foto si la corrida pasó y no llevaba argumentos extra para `artisan test` (un `--filter`
  en verde no prueba lo elegido). Si falla, la siguiente vuelve a incluir todo lo cambiado desde la última verde.
- **D4.** Si nada cambió desde la última verde, no corre nada y sale en verde.

## Pasos

- [x] 1. Plan
- [x] 2. Script: foto del árbol, punto de partida, guardar en verde, `--base` fuerza
- [x] 3. Verificar en una copia aparte con un `artisan` falso (verde, rojo, sin cambios, `--base`, archivo nuevo, borrado)
- [x] 4. Documentar: `AGENTS.md` y `docs/equipo/1-como-trabajamos.html`
- [x] 5. pint y `composer check` en la máquina del desarrollador. En la sesión del agente no hay PHP 8.5, PostgreSQL ni
  acceso a Packagist: no se pudo correr pint. phpstan no aplica (analiza `app/`, no `scripts/`)

## Preparado para lo diferido

El mapa por cobertura (opción 2) solo tendría que reemplazar la parte que convierte «archivos cambiados» en «pruebas»;
el cálculo de qué cambió desde la última verde queda igual y lo reutiliza.

## Criterio de aceptación

- Tras una corrida verde, tocar un solo servicio hace que la siguiente liste solo las pruebas de ese servicio, aunque la
  rama tenga muchos otros cambios.
- Tras una corrida roja, la siguiente sigue incluyendo lo que falló.
- `--base=origin/main` vuelve al comportamiento anterior.

## Bitácora

- 2026-11-11 — Plan creado.
- 2026-11-11 — Script hecho. Verificado en una copia aparte con un `artisan` falso: sin marca usa `origin/main`; tras
  verde guarda la foto en `.git/techquk-pruebas-ultima-verde`; sin cambios no corre nada; tocar `ArbolPartidasService`
  después de una verde lista 9 archivos aunque la rama tenga otros cambios; tras una roja la siguiente sigue incluyendo
  lo de antes; `--base=origin/main` vuelve a lo de siempre; un verde con `--filter` no guarda; un archivo nuevo y uno
  borrado entran. El índice real de git no se toca (la foto usa un índice temporal).
- 2026-11-11 — Se agregó `--no-renames` a los dos `git diff`: con detección de renombres, el nombre viejo de una clase
  renombrada no aparecía y sus pruebas (que lo siguen nombrando) no se elegían.
- 2026-11-11 — Varios programadores (pregunta del desarrollador): la marca es de cada copia (vive en su carpeta de
  git, no se sube). Lo que llega de otros por `pull` o al fusionar `main` cambia la foto y sus pruebas entran en la
  siguiente corrida. CI no tiene marca y se comporta como antes.
- 2026-11-11 — Sigue: pint y `composer check` del desarrollador; después, el plan a `completados/`.
- 2026-11-15 — Paso 5 hecho en la máquina del desarrollador (copia `TechQuk-presupuesto-pantallas`, que ya tiene PHP
  8.5 y PostgreSQL): pint en verde y `composer check` completo en verde, **3210 pruebas** en 30 min. Con eso el punto 6
  del corte queda hecho. En la misma rama, `arreglo/check-tarea-paralelo` (decisión del desarrollador del mismo día):
  si a la tarea le toca más de la cuarta parte de los archivos de prueba, corren todas en paralelo
  (`PARTE_PARA_TODAS`), porque 175 de 227 en un proceso tardaron ~64 min y las 227 en paralelo ~28 min. `AGENTS.md`
  y `docs/equipo/1-como-trabajamos.html` al día. Plan a `completados/`.
