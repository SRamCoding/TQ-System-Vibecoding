# 0001 — Monolito modular

- **Estado:** aceptada
- **Fecha:** 2026-10
- **Decide:** responsable técnico

## Contexto

149 tablas en agosto, 210 activas tras el v4; `parties` y la imputación atraviesan todos los módulos, y el margen real exige consistencia transaccional entre compras, almacén, tareo y proyecto. Desarrolla una sola persona. Usuarios: decenas.

## Decisión

Una aplicación, una base, un despliegue. Módulos con límites en el código (`ARCHITECTURE.md`), verificados por `tests/Arch`.

## Consecuencias

- Las llaves foráneas entre módulos se conservan: la base garantiza integridad.
- Riesgo de degradarse en un «todo con todo». Mitigación: reglas de dependencia, pruebas de arquitectura y revisión.

## Alternativas descartadas

- **Microservicios:** reemplazar llaves foráneas por consistencia eventual, con un costo operativo que pagaría una sola persona.
- **Separar backend y frontend desde el inicio:** ver ADR 0007.
