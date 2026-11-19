# 0003 — Una sola empresa

- **Estado:** aceptada
- **Fecha:** 2026-10
- **Decide:** responsable técnico (decisión de la empresa)

## Contexto

El sistema es exclusivamente para Techquk (decisión de la empresa y del v4).

## Decisión

Sin multiempresa. Ninguna tabla lleva `empresa_id`. La tabla `empresas` (M9) guarda solo los datos del emisor.

## Consecuencias

- Permisos, consultas y pruebas más simples.
- Si algún día se quisiera vender el sistema, separar datos por empresa sería una migración grande. Se asume.

## Alternativas descartadas

- **Multiempresa desde el inicio:** complejidad sin necesidad.
