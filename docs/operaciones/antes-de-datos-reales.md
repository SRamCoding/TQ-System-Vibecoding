# Antes de usar el sistema con datos reales

Lo que se **configura** en las pantallas (no en el código) antes de que Techquk registre sus primeros datos reales.
`despliegue.md` cubre la instalación técnica; esta lista es lo que viene después, en Configuración. Cada tarea que deja
algo así lo agrega aquí, con su plan.

| Qué | Dónde | Por qué | De dónde viene |
|---|---|---|---|
| Una regla de aprobación activa con la condición «Solo si alguna línea excede su partida» | Configuración → Aprobaciones | Sin ella, toda orden (o documento con costo) con una línea que excede su partida queda frenada al enviar | Plan 2.2, D5 |
| Una regla de aprobación activa con la condición «Solo si alguna línea va a la PC-99» (Jefatura de Proyectos; con una segunda regla sobre U1, también Gerencia de Operaciones) | Configuración → Aprobaciones | Sin ella, todo documento con un gasto no presupuestado queda frenado al enviar | Plan 2.6, D2 |
| En la alerta «Posible adicional detectado», quitar Gerencia General y poner Administración de Contratos | Configuración → Alertas | Mientras no exista el rol, va a Gerencia General para que ningún posible adicional quede sin ver | Plan 2.6, D6; espera la «Grilla de roles real» de `docs/decisiones/pendientes.md` |
| En las alertas «Partida excedida o al 90 %» y «Gasto no presupuestado registrado», agregar el rol Jefatura de Proyectos | Configuración → Alertas | Hoy solo llegan a la persona responsable (de la partida o del proyecto): el rol no existe todavía | Plan 2.2, D6; plan 2.6, D6; espera la misma grilla |
