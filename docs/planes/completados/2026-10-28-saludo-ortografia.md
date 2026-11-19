# Plan: ortografía y puntuación de los saludos y frases (1.4w)

- **Estado:** terminado
- **Módulo(s):** Nucleo (interfaz)
- **Fase:** F1
- **Creado:** 2026-10-28 · **Actualizado:** 2026-10-28

## Objetivo

Los saludos y frases del inicio, con la ortografía y la puntuación correctas en español, inglés y francés.

## Contexto que leí

- Pedido del desarrollador (2026-10-28): «hay muchos textos del saludo que utilizas mal la coma, eso no debe pasar»; «en
  saludos que deberían estar entre signos de exclamación no lo están; corrige, utiliza los más altos estándares de
  gramática y ortografía».
- `database/data/frases-saludo.php` (351 textos) y los títulos del gran momento (`NucleoServiceProvider`).

## Decisiones

- **D1. Coma:** nunca entre sujeto y verbo, aunque el sujeto sea largo o una oración (RAE): «Lo que no se mide no se
  puede mejorar», «Quien tiene un amigo tiene un tesoro», «El que busca encuentra», «Quien mucho abarca poco aprieta»,
  «Lo que bien empieza bien acaba». Se mantiene la del complemento antepuesto («Al que madruga, Dios lo ayuda»), la de
  la elipsis («A mal tiempo, buena cara») y la de vocativo («Buenos días, {nombre}»).
- **D2. Exclamación:** los saludos que expresan emoción o deseo, entre ¡ !: «¡Qué temprano, {nombre}!», «¡Qué gusto
  verte, {nombre}!», «¡Feliz lunes, {nombre}!», «¡Bendecida Semana Santa, {nombre}!», «¡Feliz día a todas las mamás del
  equipo!», el título «¡Feliz Navidad!». Los neutros («Buenos días, {nombre}») quedan sin ellos.
- **D3.** Los de más de una oración, con punto final; «Hola, {nombre}: sigamos…» pasa a dos oraciones.
- **D4.** Francés: dos comas que unían oraciones completas, corregidas; espacio que no se separa (U+00A0) antes de ! ? :
  ;. Inglés: «Here’s to great projects!».
- **D5.** Versículos y citas de autores, sin tocar: van con su texto exacto (regla del archivo).

## Pasos

- [x] 1. Textos corregidos y pruebas que los nombran.

## Criterio de aceptación

- Las pruebas del saludo y del gran momento en verde.

## Bitácora

- 2026-10-28 — Hecho en una rama desde `main` (`git worktree`), como la 1.4v, y fusionado en `nucleo-ia` para que se vea
  ya. El sembrador solo crea lo que falta: en una base ya sembrada, los textos nuevos llegan al reconstruirla (antes del
  primer despliegue no hay datos reales que cuidar). La corrida completa va con el cierre de la 1.11 y el CI de este PR.
