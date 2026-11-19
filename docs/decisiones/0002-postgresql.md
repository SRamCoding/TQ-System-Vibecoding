# 0002 — PostgreSQL 16 como motor

- **Estado:** aceptada
- **Fecha:** 2026-10
- **Decide:** responsable técnico

## Contexto

El análisis de agosto escribió el modelo con tipos de SQL Server; los documentos 01 y 05 y el v4 proponían PostgreSQL, que el equipo conoce.

## Decisión

PostgreSQL 16. El modelo v2 ya está en tipos de PostgreSQL.

## Consecuencias

- `jsonb`, índices de expresión, `unaccent`, restricciones `CHECK`.
- Sin licencia.
- Distingue mayúsculas y acentos: las búsquedas se normalizan.
- Las llaves foráneas no se indexan solas: índice obligatorio.
- Las pruebas corren contra PostgreSQL, no SQLite.

## Alternativas descartadas

- **SQL Server:** coincidía con S10, pero exige licencia y encaja peor con Laravel y Docker en Linux.
