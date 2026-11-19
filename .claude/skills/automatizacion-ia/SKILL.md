---
name: automatizacion-ia
description: Agrega o modifica un proceso de IA (lectura de fotos, PDF, enlaces sugeridos) con su prompt versionado, dueño, casos de prueba y tope. Usar antes de tocar cualquier llamada a un modelo de lenguaje.
---

# Agregar o cambiar un proceso de IA

Lee primero `docs/convenciones/ia.md`.

1. **¿Corresponde IA?** Solo si la entrada es desordenada o hace falta criterio para relacionar. Si
   tiene una sola respuesta correcta, prográmalo fijo y detente.
2. **¿Está autorizado?** Gerencia debe haber autorizado qué datos salen (`pendientes.md`, «IA y datos»).
   Si no, construye el proceso apagado y avisa.
3. **Registro:** `DefinicionProcesoIa` en `RegistroProcesosIa` desde el provider del módulo (código, nombre, esquema
   de salida, reglas por campo, qué envía: tipos de adjunto o campos del registro, nunca sensibles; el **permiso del
   módulo** para resolver sus sugerencias, `permisoResolver`, y los **tipos que produce**, `produce`, del morph map).
   Se sincroniza a `ia_procesos`, apagado, sin modelo y con tope 0 hasta que Gerencia lo fije.
4. **Prompt versionado** en `app/Modules/Nucleo/Ia/Prompts/<proceso>/vN.md`, con fecha y motivo del
   cambio. Contexto variable con las opciones válidas; salida estructurada con evidencia por campo; nunca inventar
   datos ilegibles.
5. **Uso:** la acción llama a `IaService::sugerir()`, que pasa por `ClienteModeloLenguaje`, registra cada llamada en
   `ia_llamadas`, valida, verifica la evidencia y guarda la sugerencia. Nunca dentro de una transacción; nunca con
   adjuntos ni campos sensibles. El modelo se elige comparando modelos con los casos del paso 8.
6. **Interfaz:** la sugerencia llena el formulario de la acción correspondiente; la persona confirma,
   corrige o rechaza. Siempre existe el camino manual.
   - **La resuelve el módulo (regla para todo proceso):** al aceptarla o corregirla, la acción del módulo que crea el
     registro llama a `IaService::resolver()` **dentro de su misma transacción**, con ese registro como resultado (de
     un tipo de `produce`; cualquier otro se rechaza). Rechazarla es la acción del núcleo
     `nucleo.ia-rechazar-sugerencia`. Las dos exigen `permisoResolver`; no hay un permiso global para resolver.
     Prueba que la acción del módulo crea el registro y deja la sugerencia resuelta y asociada a él.
7. **Pruebas de código** con la implementación falsa o `Http::fake` (nunca el servicio real).
8. **Casos de evaluación** en `tests/Ia/<proceso>/`: 10 a 20 ejemplos anonimizados con la respuesta
   correcta. Al cambiar de versión, comparar aciertos y anotarlos en la bitácora. No van en `composer check` ni en `check:tarea`.
