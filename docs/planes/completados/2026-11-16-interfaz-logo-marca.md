# Plan: el logo con el ícono achatado y el nombre en Audiowide (1.4ae)

- **Estado:** terminado
- **Responsable:** Sebastián (núcleo; modo actual de AGENTS.md)
- **Entrega:** `entrega/interfaz-logo-marca`
- **Módulo(s):** Nucleo (`filament/logo.blade.php`)
- **Fase:** etapa 1, interfaz
- **Creado:** 2026-11-16 · **Actualizado:** 2026-11-16

## Objetivo

El ícono de Techquk va más bajo que ancho y el nombre vuelve a la letra de la marca, con la mayúscula del mismo alto que
el ícono: el borde de arriba y el de abajo del ícono coinciden con la parte alta y la base de las letras.

## Contexto que leí

- `docs/convenciones/interfaz.md`, «Letras»; plan 1.4ab (D2: el nombre había pasado a Inter el 2026-11-15).
- `filament/logo.blade.php` y `LogoTechquk` (las tres piezas en un `viewBox` de 122 × 119).
- El commit `8cd9bd99` (Audiowide, 2026-10-26) y `7fc6bc92` (su retiro): la fuente se recupera del historial, con su
  licencia.

## Decisiones

- **D1.** (desarrollador, 2026-11-16, entre tres opciones en un boceto) El ícono, achatado al **85 %** de su alto, «pero
  más pequeño». Mismo dibujo estirado a una caja más baja (`preserveAspectRatio="none"`): las animaciones, los escombros
  y los detalles de fecha no cambian, solo se ven más bajos.
- **D2.** (desarrollador, 2026-11-16) El nombre vuelve a **Audiowide**: «ya es marca de la empresa». Revierte la D2 de la
  1.4ab solo para el nombre; el resto del sistema sigue en Inter.
- **D3.** La mayúscula del nombre mide lo mismo que el ícono. Con las medidas de Audiowide (unidades de 2048: mayúscula
  1434, ascendente 2027, descendente 584), la letra es `alto / 0.7002` y el texto sube `0.1521 em` dentro de una caja del
  alto del ícono: su base cae justo en el borde de abajo. Exacto en todos los navegadores, sin depender de `text-box`.
- **D4.** «Más pequeño»: un solo número, `--tq-logo-alto`, en **1.25 rem** (20 px). El ícono queda de 20 × 24 px y el
  nombre de unos 166 px de ancho. Para ajustar el tamaño se cambia solo ese número.

## Pasos

- [x] 1. Fuente Audiowide y su licencia de vuelta en `public/fonts/audiowide`.
- [x] 2. El logo: `--tq-logo-alto`, el ícono achatado y el nombre a su altura.
- [x] 3. `TipografiaTest`: la prueba del nombre pasa de «sin Audiowide» a «en Audiowide y a la altura del ícono».
- [x] 4. `interfaz.md` y el mapa.
- [x] 5. `composer check:tarea` en verde (en la máquina del desarrollador).

## Preparado para lo diferido

Nada se pospone. Si llega el manual de marca con otra proporción, se cambian el 0.85 del ancho y `--tq-logo-alto`.

## Criterio de aceptación

- `TipografiaTest` y `LogoTechqukTest` en verde.
- En pantalla, en claro y en oscuro: arriba y abajo del ícono coinciden con la parte alta y la base de TECHQUK.

## Bitácora

- 2026-11-16 — Boceto con el logo actual y tres grados de achatado (85, 75 y 65 %); eligió 85 % y más pequeño. Revisado
  con Chrome sin cabeza sobre una página con el mismo CSS y el ícono real, en claro y en oscuro, con líneas guía: la
  mayúscula y el ícono coinciden arriba y abajo. La Q baja un poco por su cola, como en la letra. Composer no pudo
  instalar los paquetes en esta sesión (el proxy rechaza las descargas de GitHub), así que no corrieron las pruebas.
- 2026-11-16 — Probada en la máquina del desarrollador, con `main` traído (sin conflictos): pint y phpstan en verde,
  todas las pruebas del núcleo (2,472) y las de `tests/Arch` y `tests/Feature` (131) en verde, en 8 procesos. El
  cambio es del logo y su prueba: el script elegía todas las pruebas por tocar una vista del núcleo.

