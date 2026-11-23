# Atajos generales

Lo que este repositorio deja de lado a propósito, en todo su código. Todo lo de esta lista lo tiene que cumplir el
repo original al construir cualquier ficha, aunque la ficha no lo repita. Si un agente toma un atajo nuevo que vale
para todo, lo agrega aquí.

- **Pruebas:** no hay ninguna. Cada regla con números de cada ficha tiene que volverse una prueba, y cada flujo su
  escenario de aceptación del v4.
- **Permisos:** el usuario de desarrollo tiene todos. Los permisos de las acciones nuevas existen, pero nadie revisó
  quién debe tenerlos, ni las incompatibilidades entre funciones, ni los campos sensibles por permiso (ADR 0016).
- **Acciones registradas:** se intentan seguir, pero puede haber lógica que vive en la pantalla y no se puede
  ejecutar sin la interfaz. La ficha lo dice en «Atajos tomados» cuando el agente lo sabe.
- **Estados y transiciones:** pueden estar como texto en una columna en vez de usar el mecanismo de estados del
  núcleo, sin efectos separados ni reversión al anular.
- **Concurrencia:** sin bloqueo optimista ni pruebas de dos personas a la vez, salvo en lo que ya existía.
- **Base de datos:** las migraciones se editaron sin cuidado de la historia, pueden faltar índices en llaves
  foráneas, `NOT NULL` y unicidades garantizadas en la base.
- **Auditoría:** lo nuevo puede no dejar historial de cambios.
- **IA:** sin prompts versionados, sin casos de prueba y sin tope de costo; en los datos de demo, las sugerencias
  vienen sembradas.
- **Datos sensibles:** nadie revisó qué queda en registros o mensajes de error.
- **Calidad del código:** sin phpstan ni revisión de arquitectura; puede haber cálculos duplicados o un módulo que
  lee los modelos de otro.
- **Rendimiento:** pensado para datos de demo y medido una sola vez (2026-11-23, servidor de desarrollo): cada cambio
  de pantalla tarda de 0.8 a 1.2 s, y casi un segundo de eso es armar la barra, el clima, el muelle y las decoraciones
  de cada página (unos 650 KB). Lo único corregido: el dinero de un proyecto (portada de Proyectos, Resumen) pide los
  saldos de sus partidas en lote, con los mismos números que una por una (comparado al céntimo en 95 partidas). En el
  repo original, cualquier pantalla que muestre saldos de muchas partidas los pide en lote. Pendiente: los números de
  Contratos del inicio todavía se calculan contrato por contrato.
