# Plan: los íconos de la barra y del muelle crecen al pasar el mouse (1.4v)

- **Estado:** terminado
- **Módulo(s):** Nucleo (interfaz)
- **Fase:** F1
- **Creado:** 2026-10-28 · **Actualizado:** 2026-10-28

## Objetivo

Al pasar el mouse (o el foco del teclado), el ícono de un módulo de la barra lateral y el de una aplicación del muelle
crecen con la misma animación que el ícono de las cajas del inicio.

## Contexto que leí

- Pedido del desarrollador (2026-10-28): «cuando pase el mouse por los íconos de la barra lateral de los módulos, que
  tengan esa animación de crecer, también para los íconos de las aplicaciones del muelle».
- `cajas-modulos.blade.php`: el ícono de la caja crece a 1,2 en 180 ms (`ease-out`); quieto con «reducir movimiento» o
  «reducir animaciones» (`html.tq-sin-animaciones`).
- `docs/convenciones/interfaz.md`, «Barras» y el panel de aplicaciones (plan 1.4p).
- Nada de `pendientes.md` afecta.

## Decisiones

- **D1.** Misma animación que las cajas: `scale(1.2)` en 180 ms, con el mouse y con el foco del teclado.
- **D2.** Barra lateral: el ícono de cada ítem (`.fi-sidebar-item-btn > .fi-icon`). Muelle: solo sus tres aplicaciones,
  no la cuadrícula.
- **D3.** Quieto con «reducir movimiento» del equipo o «Reducir siempre» de la persona.

## Pasos

- [x] 1. CSS en `navegacion.blade.php` y `panel-aplicaciones.blade.php`, con sus pruebas.
- [x] 2. Pruebas tocadas en verde; la corrida completa va con la de la 1.11 y el CI de GitHub.

## Criterio de aceptación

- `BarrasNavegacionTest` y `PanelAplicacionesPantallaTest` en verde.

## Bitácora

- 2026-10-28 — Hecho en una rama desde `main` (`git worktree`), para no cambiar los archivos bajo la app del
  desarrollador, que corre sobre `nucleo-ia` (1.11, en curso); los archivos tocados eran iguales en las dos ramas. Se
  probaron en la carpeta de la app y luego se fusionó esta rama en `nucleo-ia`, para que se vea ya. La corrida completa
  (`composer check`) va con el cierre de la 1.11, que la incluye, y con el CI de GitHub de este PR.
