# Plan: el ícono de las cajas en una baldosa y el ícono de Proyectos (1.4ad)

- **Estado:** terminado (falta `composer check:tarea` de la entrega)
- **Responsable:** Sebastián (interfaz)
- **Entrega:** `entrega/proyectos-portada`
- **Módulo(s):** Nucleo (vista de las cajas del inicio, íconos `tq-`), Erp/Proyectos (su ícono)
- **Fase:** —
- **Creado:** 2026-11-16 · **Actualizado:** 2026-11-16

## Objetivo

Las cajas de los módulos del inicio llevan su ícono en una baldosa como la de una aplicación, con el estilo de la imagen
que dio el desarrollador (degradado, brillo, sombra, ícono blanco de línea) y el color de su área. Proyectos estrena el
ícono de esa imagen: una lista de tareas con un cohete.

## Contexto que leí

- La imagen del desarrollador (2026-11-16): baldosa redondeada verde a turquesa, brillo arriba a la derecha, sombra
  suave del mismo color, ícono blanco de línea (lista con cohete).
- `cajas-modulos.blade.php` y `caja-modulo.blade.php`: cada estación (1.4r, 1.4t) pinta la caja y la ficha del ícono.
- `interfaz.md`, «Inicio» y «Identidad visual»; los íconos propios en `Nucleo/resources/svg` (prefijo `tq-`).

## Decisiones

- **D1.** (desarrollador, 2026-11-16, «este ícono para proyectos y el estilo del color, su iluminación… que lo usen
  todas las cajas de los módulos pero que respeten la paleta de colores que hemos definido») Todas las cajas usan el
  estilo, con el color de su área (Personal dorado, Núcleo azul noche, CRM morado, ERP turquesa; 600 en claro y 400 en
  oscuro, como siempre).
- **D2.** (desarrollador, 2026-11-16, eligió «Ícono en baldosa» entre las dos opciones) El estilo va en **una baldosa
  con el ícono dentro de la caja**; la caja y su aspecto por estación se quedan.
- **D3.** (técnica) La baldosa: degradado a 150° del color del área aclarado (55 % con blanco) al color y a un poco más
  hondo; brillo radial arriba a la derecha; filo de luz arriba y sombra del color del área. El ícono, blanco con una
  sombra mínima. Al pasar el mouse crece la baldosa (1,1), no el ícono dentro. Va al final de los estilos y con
  `!important`, porque cada estación pinta su ficha con selectores más fuertes.
- **D4.** (técnica) El ícono de Proyectos es un dibujo propio en el trazo de los demás (`tq-proyecto-cohete`, 24 × 24,
  línea de 1,5): sale `heroicon-o-clipboard-document-list`. Lo usan la caja, la barra lateral y la pantalla.

## Pasos

- [x] 1. El ícono `proyecto-cohete.svg`, revisado como imagen (vista previa con Chromium sin pantalla) en claro y oscuro.
- [x] 2. La baldosa en la vista de las cajas, para todas las estaciones y el modo oscuro.
- [x] 3. `CajasBaldosaTest`; `interfaz.md` y `LICENCIAS.md`.
- [x] 4. Pruebas de las cajas y de Proyectos en verde.

## Preparado para lo diferido

Nada se pospone. Un módulo nuevo solo declara su ícono: la baldosa se la pone la caja.

## Criterio de aceptación

- `CajasBaldosaTest`: la página trae la baldosa después de los estilos de la estación; Proyectos usa su ícono.
- En pantalla: cada caja con su baldosa del color de su área, en claro y oscuro y en cada estación.

## Bitácora

- 2026-11-16 — Hecho en la misma sesión que la 1.4ac. Primera versión del ícono con el cohete encima de la lista: no se
  leía a 2 rem; el cohete quedó en la esquina de arriba a la derecha, con la lista sin el borde de esa esquina.
