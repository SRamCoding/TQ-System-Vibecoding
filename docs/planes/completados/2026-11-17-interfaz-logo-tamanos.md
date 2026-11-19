# Plan: el ícono del logo más grande y el nombre más chico (1.4af)

- **Estado:** terminado (falta `composer check:tarea` en la máquina del desarrollador)
- **Responsable:** Sebastián (núcleo)
- **Entrega:** `arreglo/logo-tamanos` (arreglo chico, fuera de una entrega)
- **Módulo(s):** Nucleo (`filament/logo.blade.php`)
- **Fase:** etapa 1, interfaz
- **Creado:** 2026-11-17 · **Actualizado:** 2026-11-17

## Objetivo

En la app, el nombre TECHQUK del logo un poco más chico y el ícono un poco más grande. Pedido del desarrollador,
2026-11-17: «no importa si no están alineados en las líneas horizontales».

## Contexto que leí

- Plan 1.4ae (`docs/planes/completados/2026-11-16-interfaz-logo-marca.md`): un solo número, `--tq-logo-alto`, mandaba
  en el ícono y en la mayúscula del nombre.
- `docs/convenciones/interfaz.md`, «El logo y su nombre»; `TipografiaTest`; `AppPanelProvider` (`brandLogoHeight('2rem')`:
  cabe el ícono nuevo).

## Decisiones

- **D1.** El ícono y el nombre tienen cada uno su número: `--tq-logo-alto` (ícono, de 1.25 a **1.5 rem**, +20 %) y
  `--tq-logo-nombre-alto` (mayúscula del nombre, de 1.25 a **1 rem**, −20 %). Reemplaza la D3 y la D4 de la 1.4ae en
  lo de «misma altura»: el nombre queda centrado a lo alto del ícono, sin coincidir arriba y abajo.
- **D2.** Se mantiene la cuenta de Audiowide de la 1.4ae (letra = alto / 0.7002, sube 0.1521 em): la caja del nombre
  mide justo sus mayúsculas, así que el centrado de la barra las centra con el ícono.

## Pasos

- [x] 1. `logo.blade.php`: la variable nueva y los tamaños.
- [x] 2. `TipografiaTest`: los dos números y la letra que sale del nombre.
- [x] 3. `interfaz.md` y el mapa.
- [ ] 4. `composer check:tarea` en verde (en la máquina del desarrollador).

## Preparado para lo diferido

Nada se pospone. Para ajustar otra vez, se cambia solo el número del ícono o el del nombre.

## Criterio de aceptación

- `TipografiaTest` y `LogoTechqukTest` en verde.
- En pantalla, en claro y en oscuro: el ícono más grande que antes, el nombre más chico y centrado a su altura.

## Bitácora

- 2026-11-17 — Cambio hecho y revisado con Chrome sin cabeza sobre una página con el mismo CSS, antes y ahora, en claro
  y en oscuro. Composer no se instaló en esta sesión, así que no corrieron las pruebas (solo `php -l` de la prueba).
