# 0024 — Una persona: su usuario y su ficha laboral comparten ficha, puesto y foto

- **Estado:** aceptada (2026-11-16, por el desarrollador: «sí, tu propuesta está bien»), salvo quién ve a quién, que
  queda en `pendientes.md` («Directorio del equipo»).
- **Fecha:** 2026-11-16
- **Decide:** desarrollador
- **Toca:** el núcleo (`users`, organigrama, «Mis datos», Usuarios y organigrama) y el área 4 (`Erp/Personal`, ADR 0020).
  Va antes del reparto: el colaborador es lo primero que construye esa área.

## Contexto

Una misma persona aparece en dos módulos con propósitos distintos:

- **Usuarios y organigrama** (núcleo): la cuenta para entrar al sistema. Correo, clave, roles, puesto
  (`users.puesto_id`; la unidad sale del puesto, plan 1.4, D4) y la foto que cada persona sube en «Mis datos»
  (`users.foto_ruta`, plan 1.4o).
- **Personal** (área 4, `Erp/Personal`): la ficha laboral, `colaboradores` en el DBML. Fecha de ingreso, contratos
  laborales, categoría de costo hora, habilitación para obra y su estado (candidato, activo, no habilitado, en salida,
  cesado). El DBML le da además `puesto_id` y `unidad_organizativa_id` propios.

Las dos cuelgan de la misma ficha de Personas y empresas (`parties`). Pero no coinciden: muchos colaboradores no tienen
usuario (los técnicos que solo aparecen en el tareo), y puede haber usuarios que no son colaboradores (un contador
externo).

Si los dos guardan su propio puesto, se separan con el tiempo: RRHH asciende a alguien y su usuario sigue con el puesto
viejo, y con él la jefatura y las aprobaciones que el núcleo calcula a partir de `users.puesto_id`. Si el colaborador
guarda su unidad aparte del puesto, se repite el mismo problema que el plan 1.4 (D4) ya evitó en `users`. Y si la foto
vive solo en el usuario, quien no tiene cuenta no tiene foto.

Además, el desarrollador quiere ver las dos portadas como tarjetas de perfil con foto, en dos columnas (2026-11-16).

## Decisión

1. **Una persona, una ficha.** El usuario y el colaborador de una persona cuelgan de la misma ficha de `parties`. Ya es
   así en `users`; en `colaboradores`, `party_id` será obligatorio y único (el historial laboral va en sus contratos
   laborales, no en otro colaborador).
2. **El puesto tiene un solo dueño por persona:**
   - **Si la persona es colaborador, el dueño es RRHH.** El puesto vive en el colaborador y lo cambia `Erp/Personal`
     (con su contrato laboral). Si además tiene usuario, Personal lo copia a `users.puesto_id` en la misma transacción,
     por `SeguridadService::cambiarPuesto()`, el único camino que ya escribe el puesto de un usuario. La pantalla de
     Usuarios muestra ese puesto pero no lo edita: dice que lo define RRHH.
   - **Si no es colaborador** (un usuario externo), el dueño es Usuarios y organigrama, como hoy.
   - El núcleo sigue leyendo `users.puesto_id` para la jefatura y las aprobaciones: no cambia nada de eso.
3. **La unidad sale del puesto** también en el colaborador: `colaboradores.unidad_organizativa_id` no se crea, igual
   que en `users` (plan 1.4, D4).
4. **La foto es de la persona, no de la cuenta.** Pasa de `users.foto_ruta` a la ficha (`parties`), con las mismas
   reglas de archivo (recortada en círculo y achicada en el navegador, plan 1.4o). Quién la cambia: la propia persona si
   tiene usuario («Mis datos», como hoy); si no tiene, RRHH desde Personal. La foto de un proyecto (2.61) y la de una
   oportunidad (2.21d) no cambian: son de esos documentos.
5. **Una sola tarjeta de persona.** El núcleo tiene el componente: foto (o iniciales), nombre, una línea de datos y
   hasta dos etiquetas. Cada módulo lo llena con lo suyo, por un registro como el de `CajaModulo`:
   - Usuarios: puesto y unidad; activo o inactivo, cuántos roles, último acceso, sin puesto, clave por renovar.
   - Personal: cargo y modalidad (planilla o eventual); habilitación para obra, documento por vencer, proyecto asignado.

   Las dos portadas siguen `interfaz.md`: pestañas con su cantidad, avisos con «Ver cuáles», tarjetas en dos columnas
   en escritorio y una en el celular, y al tocar una tarjeta, el panel lateral.

## Consecuencias

- **Núcleo (antes del reparto o como primera tarea del núcleo después):** mover la foto a `parties` (las migraciones
  aún se editan, ADR 0010) y ajustar «Mis datos», el avatar del menú y las iniciales; el componente de tarjeta y su
  registro; la portada de Usuarios con tarjetas; el punto de extensión para que Usuarios sepa si una persona es
  colaborador («lo define RRHH»), que hoy responde siempre «no».
- **Área 4:** `colaboradores` sin `unidad_organizativa_id`; el cambio de puesto pasa por un servicio de Personal que
  llama a `SeguridadService::cambiarPuesto()` cuando hay usuario; la portada de Personal con la misma tarjeta; la foto
  de quien no tiene usuario.
- **El organigrama sigue en espera** (AGENTS.md): esto define cómo se guarda el puesto, no cuáles son las unidades ni
  los puestos reales. Se construye con datos de prueba.
- **Pendiente:** quién ve las tarjetas y las fotos de los demás (fila «Directorio del equipo» en `pendientes.md`).
  Hasta decidirlo, cada portada la ve solo quien tiene el permiso de su módulo, como hoy.

## Alternativas descartadas

- **Cada módulo con su propio puesto:** se separan, y la jefatura y las aprobaciones quedarían con el puesto viejo.
- **Que el puesto viva solo en `users`:** los colaboradores sin cuenta (técnicos) no tendrían puesto, o habría que
  crearles un usuario que no usan.
- **La foto solo en el usuario:** los técnicos sin cuenta quedarían sin foto en Personal.
