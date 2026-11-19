# Registro de validaciones

Aquí se anota cada vez que una parte del análisis se revisa con las personas de Techquk que la van a
usar, y cada aprobación de una línea base. Es la evidencia de que el sistema describe a la empresa y
no solo a la idea que el analista tenía de ella.

Una sesión que no está anotada aquí, para el análisis, no ocurrió. Si se olvidó anotar alguna,
se agrega después con su fecha real y una nota de que se registró tarde.

## Cómo se anota una sesión

Copiar este bloque al inicio de la lista, con la sesión más reciente arriba:

```markdown
### AAAA-MM-DD · Bloque · Tema

- **Con quién:** nombre y cargo
- **Qué se revisó:** módulos, flujos o pantallas, con su identificador (CRM-02, F03…)
- **Versión revisada:** la del bloque en ese momento
- **Resultado:** validado · con observaciones · rechazado
- **Observaciones:** qué hay que corregir o decidir, cada una con su responsable
- **Qué cambió en el análisis:** versión nueva que resultó y enlace al PR
```

## Aprobaciones de línea base

Ningún bloque tiene línea base todavía. La primera esperada es **CRM 1.0**, que aprueba la Gerencia
Comercial.

## Validaciones en preparación

Lo que ya está armado para validar pero todavía no se hizo. Cuando ocurra, pasa a **Sesiones** con su
resultado.

### Formularios para validar en la práctica (armados el 2026-11-02, sin enviar)

Cuatro formularios en Jotform, para todo el personal (son pocas personas), con los que se confirma si
el sistema cubre cómo trabaja Techquk de verdad y no solo lo que dice el análisis de casos de uso:

| # | Formulario | Qué valida | Depende del organigrama |
|---|---|---|---|
| 1 | Cómo se trabaja hoy | Las 66 tareas de los procesos del CRM y el ERP: si se hacen así, distinto, no se hacen o no se sabe | Poco: solo si cambian las áreas |
| 2 | Organización | Quién está en cada área y puesto, y a quién reporta | Sí: es el organigrama |
| 3 | Decisiones del negocio | Temas de `docs/decisiones/pendientes.md` que dependen de cómo opera la empresa | Poco: solo si cambian las áreas |
| 4 | Quién hace qué | Qué puesto hace cada tarea, de donde se infiere la grilla de roles (pendiente «Grilla de roles real») | Sí: pregunta por puestos |

**En espera (2026-11-03):** Techquk está coordinando un organigrama nuevo, porque hubo varios cambios
desde el de agosto, y todavía no lo entrega. Cuando llegue, los formularios **se ajustan antes de
enviarse**. No se sabe aún de qué tamaño será el cambio:

- **2 y 4:** cambian con seguridad (áreas, puestos y personas).
- **1 y 3:** pueden necesitar ajustes menores si cambian las áreas o quién responde cada bloque.

Reglas para cuando se envíen:

- **Las respuestas no entran al repositorio:** llevan nombres reales. Aquí se anota la sesión, el
  resultado y lo que cambió en el análisis, como en cualquier validación.
- **Las respuestas no deciden solas:** lo que salga de los formularios se revisa con Gerencia antes de
  cerrar un pendiente.
- **Los formularios no piden documentos reales.**

## Sesiones

### 2026-10-15 · CRM · Revisión general con Cristhian

- **Con quién:** Cristhian Díaz (cargo por anotar)
- **Qué se revisó:** el CRM en conjunto, con el diseño de pantallas del 13 de octubre como guía
- **Versión revisada:** CRM 0.4.0
- **Resultado:** en curso
- **Observaciones:** por registrar al cerrar la sesión
- **Qué cambió en el análisis:** con esta sesión el CRM entró a la etapa de validación
