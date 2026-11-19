# 0007 — API móvil preparada, no construida

- **Estado:** aceptada
- **Fecha:** 2026-10
- **Decide:** responsable técnico

## Contexto

El v4 define el celular como «acciones» (tarear, fotografiar, recibir, aprobar). Los técnicos casi siempre tienen señal.

## Decisión

Interfaz web adaptable e instalable como aplicación web. Toda la lógica vive en servicios y acciones registradas, de modo que una API para una app nativa se agrega después sin reescribir el backend.

## Consecuencias

- Un solo código base hoy.
- Se construye la API (con autenticación por tokens) solo si aparece una necesidad real: trabajo prolongado sin señal, notificaciones totalmente confiables o funciones del teléfono que el navegador no cubre.

## Alternativas descartadas

- **Backend y frontend separados desde el inicio:** duplicaría el trabajo para una sola persona.
