# 0011 — Mensajes de validación en español, escritos por el proyecto

- **Estado:** aceptada
- **Fecha:** 2026-10-09
- **Decide:** desarrollador

## Contexto

La aplicación corre en español (`APP_LOCALE=es`), pero Laravel no trae sus mensajes de validación en
español: un error sale como `validation.required`. Filament sí trae los suyos. Afecta a toda acción
registrada (tarea 1.1), cuyos campos validan con reglas de Laravel.

## Decisión

Escribimos `lang/es/validation.php` nosotros, solo con las reglas que el proyecto usa, y lo ampliamos
cuando una acción use una regla nueva. Se hace al empezar la tarea 1.2.

## Consecuencias

- Sin dependencia nueva; los textos usan el tono de Techquk y la etiqueta de cada campo (`:attribute`).
- Cada regla nueva necesita su línea: una prueba debe fallar si una regla usada por un `Campo` no
  tiene traducción.

## Alternativas descartadas

- **Paquete `laravel-lang/lang`:** completo y mantenido, pero es una dependencia más para pocas reglas,
  con textos genéricos.
