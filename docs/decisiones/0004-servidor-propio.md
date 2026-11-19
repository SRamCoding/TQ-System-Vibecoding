# 0004 — Servidor propio con Linux, administrado por TI

- **Estado:** aceptada
- **Fecha:** 2026-10
- **Decide:** responsable técnico (decisión de la empresa)

## Contexto

Techquk decidió alojar el sistema en su propio servidor. Lo administra TI; el desarrollo no administra el servidor.

## Decisión

Despliegue con Docker Compose en el servidor Linux de Techquk. Responsabilidades entre TI y desarrollo en `docs/operaciones/responsabilidades.md`.

## Consecuencias

- No hace falta escalar: decenas de usuarios.
- El respaldo fuera del servidor (S3) pasa a ser crítico.
- Acceso externo para técnicos en obra: VPN o HTTPS, a definir con TI.
- Los agentes de IA nunca acceden al servidor ni a sus credenciales.
- La decisión es de la empresa: si surgen problemas de disponibilidad, el origen está documentado.

## Alternativas descartadas

- **Nube:** no elegida por la empresa.
