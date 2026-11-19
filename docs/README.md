# Documentación del proyecto

Este directorio es la fuente de verdad. Si algo no está escrito aquí o en el código, para un agente no existe.

También se publica como sitio web (Netlify, privado), armado desde aquí sin copiar nada: ver `sitio/LEEME.md`. Para
documentar, se edita este directorio; el sitio no se toca.

| Carpeta o archivo | Contenido |
|---|---|
| `analisis/` | **Empieza aquí.** Estado y versión de cada bloque (CRM, ERP, Común), su historia, validaciones y cambios |
| `convenciones/` | Reglas de base de datos, código, IA e interfaz. Mandan sobre todo lo demás |
| `decisiones/` | ADR aceptados y la lista de pendientes |
| `diseno/` | Especificación v4 por partes, modelo de datos v2, documentos actualizados y fuentes originales |
| `planes/` | Mapa de etapas y planes de ejecución (activos y completados) |
| `operaciones/` | Servidor, despliegue, respaldos, restauración y monitoreo |
| `glosario.md` | Términos del negocio y del contexto peruano |
| `setup.md` | Instalación de Laravel y del harness, paso a paso |

Precedencia cuando dos fuentes chocan: **convenciones > decisiones aceptadas > especificación v4 >
flujograma de gastos > prototipo > documentos de agosto** (ADR 0009). Para el CRM, el diseño de pantallas del
13 oct 2026 va antes que la parte 2 de la v4 (ADR 0014). Si el choque persiste, se pregunta.
