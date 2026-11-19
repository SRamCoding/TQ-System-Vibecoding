# Plan: Pruebas en paralelo

- **Estado:** terminado
- **Módulo(s):** — (herramientas de desarrollo)
- **Fase:** F1
- **Creado:** 2026-10-27 · **Actualizado:** 2026-10-27

## Objetivo

`composer check` corre las pruebas repartidas en varios procesos, cada uno con su propia base, y tarda bastante menos
que los ~15 minutos de hoy (1.711 pruebas), con el mismo resultado. Queda escrita la regla de cuándo se corren.

## Contexto que leí

- `composer.json` (`check`, `check:tarea`), `scripts/pruebas-de-la-tarea.php`, `tests/Pest.php`, `phpunit.xml`
  (`DB_DATABASE=techquk_test`), `tests/Concurrencia` (su propia base, `techquk_concurrencia`).
- `brianium/paratest` ya está instalado; el equipo tiene 8 núcleos; el usuario de PostgreSQL puede crear bases.
- Pedido del desarrollador (2026-10-27): «esperando varios minutos a cada rato no es lo correcto»; regla confirmada:
  un bloque = una tarea, una sola corrida completa al final.
- Pendientes: ninguno afecta.

## Decisiones

Aprobadas por el desarrollador el 2026-10-27, juntas.

- **D1 · Procesos.** 6 a la vez (de 8 núcleos: quedan 2 para la app y Docker). Configurable.
- **D2 · Una base por proceso.** Laravel crea `techquk_test_test_1` … `_6` la primera vez y las reutiliza; cada prueba
  sigue empezando limpia. Nunca otra base.
- **D3 · Las de concurrencia.** Siguen en su propia base; si chocan con las demás en paralelo, se corren aparte, al
  final, como un paso más de `composer check`.
- **D4 · Lo que corre en paralelo.** `composer check` y `composer check:tarea`. Correr un solo archivo sigue igual.
- **D5 · Regla escrita.** En `AGENTS.md`: un bloque = una tarea; en cada paso solo las pruebas de lo tocado; una sola
  corrida completa al final, sin otra corriendo a la vez.

## Pasos

- [x] 1. Plan y decisiones aprobadas.
- [x] 2. Medir: corrida en paralelo; corregir las pruebas que solo fallan en paralelo.
- [x] 3. `composer.json`, `AGENTS.md`, `CLAUDE.md` y la convención de pruebas; tiempo antes y después en la bitácora.
- [x] 4. `composer check` en verde (ya en paralelo).

## Criterio de aceptación

- `composer check` da las mismas 1.711 pruebas en verde, en paralelo, y tarda claramente menos.
- Correr dos veces seguidas da el mismo resultado (sin fallos al azar).

## Bitácora

- 2026-10-27 — Rama `mantenimiento-pruebas-paralelo`, apilada sobre `nucleo-alertas` (PR de la 1.10 por abrir).
  Antes: `composer check` en serie, 1.711 pruebas en 14,6 min.
- 2026-10-27 — Medición, 6 procesos: **1.711 de 1.711 en verde en 6,0 min** (antes, 14,6 min en serie), a la primera:
  ninguna prueba falló solo en paralelo, tampoco las de concurrencia (D3: siguen dentro de la corrida). `composer check`
  usa `--parallel --processes=6`; `scripts/pruebas-de-la-tarea.php` también, cuando corre todas o al menos 6 archivos
  (con menos, repartir cuesta más de lo que ahorra). `AGENTS.md` (regla por tarea), `CLAUDE.md` (procesos que siguen
  vivos tras detener una corrida) y `codigo.md` (la suite en paralelo: nada compartido fuera de la base).
- 2026-10-27 — Cierre: `composer check` en paralelo, 1.711 de 1.711 en 5,9 min; igual que la medición (sin fallos al
  azar). Sin cambios de estructura del sistema: no hace falta `revisor-arquitectura`. Sigue: la rama del logo.

