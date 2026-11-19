---
name: cerrar-tarea
description: Lista de verificación antes de dar por terminada una tarea de código. Usar siempre antes de decir que algo está listo o de proponer un commit.
---

# Cerrar una tarea

No digas «listo» hasta que todo esto se cumpla:

1. Pruebas según el nivel (decisión del desarrollador, 2026-11-12; `AGENTS.md`, «Cómo trabajar»):
   - **Cierre de una tarea:** `composer check:tarea` en verde (las pruebas de todo lo que cambió la rama).
   - **Cierre de la entrega, antes del PR:** `composer check:tarea` otra vez en verde (cubre todo lo que cambió la rama).
   - **Cierre de una etapa del mapa:** `composer check` completo en verde, una vez por etapa, en local, en segundo plano.
   - Si la tarea tocó `tests/Pest.php`, `config/`, rutas o `composer.json`, `check:tarea` ya corre todas las pruebas.
   Pega el resumen de la salida, no lo parafrasees.
2. Cada regla nueva tiene una prueba que intenta romperla, y cada acción nueva se prueba sin interfaz.
3. Si la tarea completa un flujo, **sus escenarios de aceptación del v4 pasan con los números exactos**.
4. Al cerrar la entrega, si cambió estructura (módulo, migración, efectos entre módulos, dependencias), revisión
   del subagente `revisor-arquitectura` atendida.
5. Ningún registro, mensaje de error ni dato de prueba contiene datos reales o sensibles.
6. Bitácora del plan al día: qué se hizo, qué se desvió del diseño y por qué, qué sigue. Si terminó,
   el plan pasa a `docs/planes/completados/`.
7. Si algo de `pendientes.md` se resolvió o apareció algo nuevo por decidir, actualízalo.
8. Si te equivocaste en algo que una regla habría evitado, propón la línea para `AGENTS.md` o la
   convención que corresponda (sin agregarla sin que el responsable la vea).
9. Propón el mensaje de commit: `<Módulo>: <verbo en imperativo> <qué>`.
10. Si cierra la entrega: sube la rama y avisa con los enlaces a los planes, y propón el PR
    `Entrega <nombre>: <resumen>` con la lista de tareas y planes. Ningún agente abre ni fusiona el PR.
