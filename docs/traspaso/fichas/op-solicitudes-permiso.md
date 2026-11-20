# Ficha · Solicitudes de permiso (opcional, plan 2.0b)

- **Estado:** aprobada (MV-009)
- **Versión:** v1
- **Tramo de la ruta:** opcionales (MV-014)
- **Aprobada:** 2026-11-20 · etiqueta `aprobado/op-solicitudes-permiso-v1`
- **Fuentes del diseño:** `docs/planes/activos/2026-11-06-solicitudes-de-permiso.md` (objetivo y D1 a D13, aprobadas en el
  repo original; aquí se construye su versión base).

## Qué hace

Quien no tiene el permiso de una acción de trabajo ya no deja de ver el botón: lo ve bloqueado (gris, más tenue), con
el aviso «Te falta el permiso «…». Tócalo para pedirlo.». Al tocarlo, una ventana le dice qué permiso le falta y le
pregunta para qué lo necesita; con un clic queda pedido. Quien administra Roles y permisos ve los pedidos y los concede
**por rol** (nunca a una persona suelta) o los rechaza con comentario. Todo vive en la pieza común de las acciones:
ninguna pantalla lo programa por su cuenta.

## Decisiones de Sebastián

- **MV-014** (ficha) · Entra al MVP.
- **MV-009** (general) · Aprobada sin revisión, como todas hasta terminar el sistema.

## Pantallas y navegación

- **Cualquier botón de acción registrada sin permiso:** gris y tenue, con el aviso al pasar el mouse; su ventana dice
  «Para «Ganar oportunidad» te falta el permiso «…». Quien administra Roles y permisos recibe tu pedido y te lo da por
  rol.» y pide «¿Para qué lo necesitas? (opcional)».
- **Solicitudes de permiso** `/solicitudes-permiso` (caja en el área Núcleo): quién, permiso (para qué acción y su
  clave), para qué, estado («Concedida al rol «…»», rechazada con su comentario). Filtro Ver (pendientes por omisión).
  Quien administra roles ve todas y tiene **Conceder** (elige el rol; propone el primero de la persona) y **Rechazar**
  (comentario obligatorio); los demás ven solo las suyas.

## Operaciones

| Operación | Campos | Valida | Qué produce |
|---|---|---|---|
| Pedir permiso (desde el botón bloqueado) | para qué (opcional, ≤ 300) | no hay un pedido pendiente del mismo permiso | solicitud pendiente |
| Conceder | rol | pendiente; permiso `roles.gestionar` | el rol recibe el permiso (si la persona no tenía el rol, se le asigna); concedida |
| Rechazar | comentario (obligatorio) | pendiente | rechazada con su comentario |

## Datos

Tabla nueva `solicitudes_permiso` (persona, permiso, nombre de la acción, motivo, estado, rol con que se concedió,
quién resolvió, comentario).

## Reglas y números

Demo: la vendedora **Valeria Montalván Ríos** (`ventas.demo@techquk.test`, clave de la fábrica de usuarios
`Clave-de-prueba1`) con el rol «comercial-demo» (solo ver el CRM, registrar actividades y crear cotizaciones y
oportunidades). Pidió 4 permisos: ganar oportunidades y registrar convocatorias (pendientes), crear cuentas (concedido a
su rol) y cambiar precios del catálogo (rechazado: «Los precios del catálogo los cambia solo Compras.»).

## Supuestos sin confirmar

- Sin aviso al administrador ni a la persona (se ve en la bandeja); el plan pide alerta y notificación.
- El botón bloqueado aplica solo a las acciones registradas (las que pasan por la pieza común); las acciones propias de
  las pantallas nuevas del MVP siguen ocultándose sin permiso.
- No se separan las reglas «de propias» (el plan muestra la regla completa).

## Atajos tomados

- Sin acción registrada para solicitar ni resolver, sin error propio del núcleo (`PermisoFaltante`) ni ADR.
- La solicitud se escribe con consultas directas.

## Dónde está en el código

`app/Modules/Nucleo/Filament/AccionFilament.php` (`tiene()`, botón bloqueado y pedido),
`Nucleo/Services/SolicitudesPermisoService.php`, `Nucleo/Filament/Pages/SolicitudesPermiso.php`, migración
`2026_11_20_600001_crear_solicitudes_permiso.php`; datos en `DemoSeeder::solicitudesPermiso()`.
