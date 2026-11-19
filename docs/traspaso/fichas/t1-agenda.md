# Ficha · Agenda y actividades (CRM-05)

- **Estado:** aprobada (MV-009)
- **Versión:** v1
- **Tramo de la ruta:** T1
- **Aprobada:** 2026-11-19 · etiqueta `aprobado/t1-agenda-v1`
- **Fuentes del diseño:** `docs/diseno/v4/parte-2-crm.md` (paso 6: «Mi agenda», registro rápido, próxima acción);
  `docs/analisis/bloques/crm-pantallas.md` (CRM-05); DBML `actividades`.

## Qué hace

Cada gestión con un cliente (visita, llamada, correo o reunión) se registra como actividad y pide la siguiente: la
**próxima acción** es el mecanismo central del CRM, con la opción explícita de no programar ninguna. «Mi agenda»
muestra lo pendiente (vencido en rojo, lo de hoy en ámbar, lo que viene), lo hecho y las oportunidades abiertas que
se quedaron sin próxima acción. Las actividades le dan a Cuentas el último y el próximo contacto, y a la faja de
Oportunidades su columna «Próxima acción» y el punto ámbar de las que no tienen o la tienen vencida.

## Decisiones de Sebastián

- **MV-005** (ficha) · Va después de licitaciones, en el tramo 1.
- **MV-009** (general) · Aprobada sin revisión, como todas hasta terminar el sistema; los cambios vienen al final.

## Pantallas y navegación

- **Inicio:** caja «Agenda» en el área CRM.
- **Mi agenda** `/agenda`: subtítulo «2 vencidas · 2 oportunidades sin próxima acción». Tabla con Cuándo («Vencida ·
  16/11» en rojo, «Hoy · 10:00» en ámbar, «jue 20/11 · 09:00»), Tipo (con icono), Asunto (con lo que pasó debajo) y
  Relacionado con (la oportunidad, que abre su ficha, con la cuenta debajo; o la cuenta). Filtro «Ver»: Pendientes
  (por omisión), Lo hecho, Todo. En cada pendiente, botón **«Hecha»** que pide «Qué pasó».
- **Botones de arriba:** «Registrar actividad» (panel deslizante) y «Programar las que faltan» (lista de las
  oportunidades sin próxima acción, cada una enlazada a su ficha).
- **Registrar actividad**, en cuatro toques como pide el v4: Tipo (Visita, Llamada, Correo, Reunión, como botones con
  icono), Relacionado con (oportunidades abiertas y cuentas, agrupadas), Asunto, Cuándo (ahora por omisión), Qué pasó y
  **Próxima acción** (Mañana · 3 días · 1 semana · Ninguna) con «¿Qué sigue?».
- **Oportunidades → faja:** aparece la columna «Próxima acción» («Vencida · Llamar para calificar la necesidad ·
  16/11») y el punto ámbar en las cajas sin próxima acción o con la próxima vencida.
- **Cuentas:** el último y el próximo contacto de cada cuenta salen de las actividades (filtro «Sin actividad»).

## Operaciones

| Operación | Campos | Valida | Qué produce |
|---|---|---|---|
| Registrar actividad | tipo (visita, llamada, correo, reunión), cuenta u oportunidad abierta, asunto (≤ 200), fecha y hora, qué pasó (texto), próxima acción (mañana, 3 días, 1 semana o ninguna) y su asunto | tipo y asunto | si la fecha ya pasó, una actividad hecha, y la pendiente vencida o de hoy de esa misma oportunidad (o cuenta) queda cumplida; si es futura, queda pendiente. Con próxima acción, una pendiente a esos días a las 10:00 (una llamada si lo hecho fue un correo) con «¿Qué sigue?» o «Seguimiento: …» |
| Marcar hecha | qué pasó | — | la pendiente pasa a hecha |

## Estados

Pendiente → Hecha. No hay más.

## Datos

Tabla nueva `actividades`: tipo (visita, llamada, correo, reunión), cuenta (obligatoria), oportunidad (opcional),
asunto, fecha (con hora y zona), resultado, es_realizada, responsable, versión y auditoría. El DBML la quiere
polimórfica (`actividable_type`) para cuentas, oportunidades, proyectos y tickets; aquí son dos columnas. Permisos
`crm.actividades.ver` y `crm.actividades.registrar`.

## Reglas y números

- Una oportunidad está «sin próxima acción» si está abierta (prospecto, calificada, propuesta enviada o negociación)
  y no tiene ninguna actividad pendiente. Su próxima acción es la pendiente más cercana; «vencida» si su fecha es
  anterior a hoy.
- Demo: 58 actividades, 14 pendientes: 2 vencidas (16/11 y 18/11), 2 para hoy (10:00 y 16:00), las demás entre el 20/11
  y el 9/12; 2 oportunidades abiertas sin próxima acción; una reunión de inicio por cada oportunidad ganada.

## Supuestos sin confirmar

- La agenda es personal: muestra solo las actividades de quien entra (no hay vista del equipo).
- Registrar una gestión hecha cumple sola la pendiente de esa oportunidad que estaba vencida o era para hoy.
- La próxima acción se programa a las 10:00 del día que toca.
- No hay registro de actividad desde la ficha de la oportunidad ni pestaña de actividades en la cuenta: se hace desde
  la agenda eligiendo la oportunidad.
- El v4 muestra una agenda «semana» y otra «escritorio»; aquí es una sola lista.

## Atajos tomados

- Sin acciones registradas ni bloqueo optimista; los botones llaman al servicio.
- El tipo es texto con una lista fija en el código.
- La próxima acción vencida se marca en la faja anteponiendo «Vencida · » al texto, en vez de un dato propio.
- El seeder crea las actividades directo en la tabla.

## Dónde está en el código

`app/Modules/Crm/Actividades/` (provider, `Services/ActividadesService.php`, `AporteActividadesACuentas.php`,
`ProximaAccionDesdeActividades.php`, `Filament/Pages/Agenda.php`); en Oportunidades,
`Services/ProximaAccionDeOportunidades.php` (interfaz) y su uso en `OportunidadesService` y `FajaDeVidrio`; datos en
`DemoSeeder::actividades()`.
