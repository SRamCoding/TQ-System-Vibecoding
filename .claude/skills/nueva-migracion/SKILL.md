---
name: nueva-migracion
description: Escribe la migración de una tabla del modelo Techquk desde el DBML v2 con las convenciones del proyecto. Usar para crear o modificar cualquier tabla.
---

# Crear o cambiar una tabla

1. **Ubica la tabla** en `docs/diseno/techquk_modelo.dbml` y su módulo dueño (correspondencia en
   `ARCHITECTURE.md`). Si está en `ZZ_Obsoletas_v4`, **no se crea**. Si está marcada `[S]`, revísala
   con la ficha del documento en `docs/diseno/v4/parte-6-fichas-documentos.md`.
2. **Lee sus reglas** en `docs/diseno/08-…` (cuerpo o anexo v3.0).
3. **Escribe la migración** (`docs/convenciones/base-de-datos.md`):
   - `$table->id()`; código de negocio con `->unique()`.
   - Toda FK: `foreignId('x_id')->index()->constrained('xs')`. Sin índice solo con
     `// harness:sin-indice <motivo>`.
   - Dinero `decimal(14,2)`; fechas `timestampTz`; booleanos con default.
   - Documentos editables: columna `version` para bloqueo optimista.
   - Unicidades e integridad en la base (`unique`, `CHECK`), no solo en PHP.
   - `$table->auditoria();` al final. **Nunca** `empresa_id`.
4. **Tabla de una etapa posterior:** si la FK apunta a una tabla de otra etapa, detente y anótalo.
5. **Antes de datos reales** puedes editar la migración y reconstruir la base (`migrate:fresh`, pide
   confirmación). **Con `.claude/PRODUCCION`**, crea una migración nueva con `Schema::table`.
6. **`down()`** que revierta exactamente `up()`.
7. **Modelo y factory** en el mismo módulo: `$fillable` explícito, casts, relaciones.
8. `php scripts/verificar-migraciones.php` y las pruebas del módulo; al cerrar la tarea, `composer check:tarea`. El
   `composer check` completo va una vez por etapa, al cerrarla.
9. Desviaciones del DBML, anotadas en la bitácora del plan con su motivo.
