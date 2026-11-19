# Archivo · Análisis funcional de agosto 2026

Este es el **primer entregable del análisis de sistemas** de Techquk, guardado tal como se entregó.
Es el paquete «AnalisisFuncional-TechQuk», y todos sus archivos tienen fecha del 24 de agosto de 2026.
**[V]**

Está aquí para que la historia del análisis tenga su evidencia completa. Antes, estos documentos
vivían solo en el proyecto de Claude. [La historia](../../historia.md) cuenta qué parte de ellos
sobrevivió y por qué.

!!! warning "Archivo congelado: no manda"
    Nada de esta carpeta se edita ni se usa como fuente para construir. En la precedencia del
    ADR 0009 está **por debajo de todo**, incluidos los documentos de agosto actualizados en
    `docs/diseno/`. Si algo de aquí contradice al repositorio, manda el repositorio.

    Un agente de IA solo lee esta carpeta si la tarea pide historia o evidencia de S10. Los CSV
    son grandes, así que se buscan con `grep` y no se abren completos.

## Qué contiene

**`metodologia-y-resultados/`** reúne los dieciséis documentos del programa. El `00` es la portada y
explica cómo se armó el paquete y cómo leerlo. Del `01` al `10` está el diseño: plan, procesos,
organigrama, formulario de levantamiento, núcleo, modelo conceptual, columnas, procesos de S10 y
procesos propuestos, módulos y paneles.

**`s10/`** es el análisis de la base de datos de S10 que Techquk operaba en ese momento. Incluye los
tres documentos explicativos y las extracciones crudas del catálogo del sistema: tablas, columnas,
relaciones, índices y objetos programables. Es la evidencia de la que salieron los patrones y los
antipatrones que hoy recogen las convenciones. La base analizada corresponde a los respaldos del 7 y
el 17 de agosto de 2026. **[V]**

Los documentos se leen desde el menú. Los ocho CSV se descargan desde aquí:

- [Catálogo de tablas](s10/S10_catalogo_tablas.csv): filas, columnas, dominio y referencias de cada tabla
- [Diccionario de columnas](s10/S10_diccionario_columnas.csv) y [todas las columnas](s10/S10_todas_columnas.csv)
- [Relaciones entre tablas relacionadas](s10/S10_dbmodel_solo_tablas_relacionadas.csv),
  [mapa completo de relaciones](s10/S10_dbmodel_mapa_completo_relaciones.csv) y
  [todas las relaciones](s10/S10_todas_relaciones.csv)
- [Índices](s10/S10_todos_indices.csv) e [inventario de objetos programables](s10/S10_inventario_objetos_programables.csv)

**`Techquk_Como_Funciona_la_Empresa.md`** explica el negocio en lenguaje llano.

## Qué pasó con cada parte

Una parte del paquete **siguió viva y evolucionó**. Los documentos 01, 05, 06, 08, 09-B y 10 tienen
su versión actual en `docs/diseno/`, llevada a 3.0 y matizada con la especificación v4. La
diferencia entre ambas versiones está en `docs/diseno/00-Reconciliacion-con-v4.md`.

Otra parte **fue reemplazada sin pasar al repositorio**:

- El **modelo conceptual (07)** lo reemplaza el modelo de datos v2 (`docs/diseno/techquk_modelo.dbml`).
- Los **procesos declarados (02 y 02-A)** los reemplazan los diez flujos de la v4. Recursos Humanos
  está en el flujo 9.
- El **organigrama (03)** lo reemplazan los roles y puestos de la v4, parte 5. Esa parte aclara que
  los puestos de este organigrama, pensados para una empresa ideal, no se copian al sistema.
- El **resumen ejecutivo (00)** lo reemplazan `docs/analisis/` y el mapa de etapas. Ahí ya no hay
  nueve fases, sino seis etapas.

Otra parte **sigue valiendo como referencia**, porque describe hechos y no propuestas. Son los
procesos de S10 reconstruidos desde la base (09-A) y todo `s10/`. Servirán de nuevo cuando llegue la
migración desde S10, que está fuera del MVP.

Por último, **el formulario y el método de levantamiento (04 y 04-A)** fueron el instrumento de esa
etapa. Se conservan por si se vuelve a levantar información con el personal.

## Lo que no está en este paquete

En agosto también se escribió un **modelo físico de 149 tablas** para SQL Server, con su script de
creación. No venía en este paquete y no se conserva. Lo reemplazó el modelo v2 en PostgreSQL
(ADR 0002). Tampoco está «El CRM de Techquk», el documento propio del CRM de esa época, que la v4 cita
como reemplazado.
