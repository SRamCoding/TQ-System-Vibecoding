# 0005 — Repositorio en la cuenta personal del desarrollador

- **Estado:** aceptada como desviación
- **Fecha:** 2026-10
- **Decide:** responsable técnico

## Contexto

El documento 05 pedía el repositorio a nombre de la empresa. Hoy vive en `github.com/SRamCoding/TechQuk-IntegralSystem`.

## Decisión

Se mantiene en la cuenta personal, **en privado**. TI despliega con una *deploy key* de solo lectura.

## Consecuencias

- Se revisa la decisión **antes de sumar a otra persona o antes del primer despliegue con datos reales**, lo que ocurra primero. GitHub permite transferirlo conservando el historial.
- La protección de `main` puede no estar disponible en un repositorio privado del plan gratuito: la regla de no unir con CI en rojo se mantiene por disciplina.

## Alternativas descartadas

- **Organización de Techquk desde ya:** preferible, pero no decidida por la empresa.
