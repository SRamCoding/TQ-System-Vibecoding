# Ficha · Personas y empresas con la cuenta adentro

- **Estado:** en curso (ronda de cambios 1)
- **Versión:** v1
- **Tramo de la ruta:** ronda de cambios 1, paso 3
- **Fuentes del diseño:** MV-022; la ficha de la cuenta del CRM (plan 2.20) y la de Personas y empresas (plan 1.4m).

## Qué hace

La caja Cuentas desaparece. Personas y empresas abre dos cajas internas, «Clientes y entidades» (papeles cliente y
entidad) y «Proveedores y colaboradores» (proveedor y colaborador); una empresa con los dos papeles sale en las dos. La
ficha de una empresa con RUC muestra lo que tenía la ficha de la cuenta.

## Decisiones de Sebastián

- **MV-022** (cruza módulos) · Cuentas dentro de Personas y empresas; dos cajas internas por papel.
- **MV-016** (cruza módulos) · Personas y empresas va en Directorio y catálogos.

## Pantallas y navegación

- `/personas`: las dos cajas. `/personas?papel=clientes|proveedores`: arriba, pestañas para pasar a la otra; debajo, la
  lista de siempre con sus pestañas por tipo, solo de los tipos de ese papel, sus avisos y sus últimos cambios.
- **Ficha** `/personas/{id}`, en empresas con RUC: «Cuenta comercial» (quién atiende, tipo, cliente desde; la
  **Actividad** de la cuenta, pendientes primero, en rojo las vencidas; el **Histórico de precios de la entidad** si
  tiene licitaciones con resultado; botón «Editar, reasignar o marcar cliente» que abre la ficha vieja de la cuenta) y
  las pestañas **Oportunidades** y **Cotizaciones** de la cuenta. Los contactos son los de la ficha, como siempre.
- En la oportunidad, el enlace de la cuenta lleva a la ficha de la persona o empresa.

## Supuestos sin confirmar

- Papeles: clientes = `cliente` y `entidad`; proveedores = `proveedor` y `colaborador`. Aliado, banco y competidor solo
  se ven en «Todos» de… ninguna de las dos (quedan fuera de ambas cajas).
- La ficha vieja de la cuenta (`/cuentas/{id}`) y su lista siguen existiendo para sus acciones y los avisos que llevan
  allá; ya no tienen caja.

## Atajos tomados

- Las pestañas de la cuenta se cuelgan de la ficha con un componente puente que decide cuál dibujar por su nombre.
- La actividad sale de una consulta con joins dentro del servicio de actividades.

## Dónde está en el código

`Nucleo/Filament/Resources/Parties/Pages/ListarParties.php` (PAPELES), vistas `parties/papeles*.blade.php`;
`Crm/Cuentas/Filament/PestanaCuentaEnFicha.php`, `CuentaComercialDeFicha.php` y su vista; `ActividadesService::deCuenta`.
