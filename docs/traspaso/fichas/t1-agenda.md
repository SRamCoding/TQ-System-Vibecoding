# Ficha · Agenda y actividades (CRM-05)

- **Estado:** en curso (v2, ronda de cambios; la v1 quedó aprobada)
- **Versión:** v3
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

- **MV-055** (ficha) · El ícono de la agenda en el muelle es la hoja del día, estilo aplicación.
- **MV-025** (cruza módulos) · La agenda es una herramienta del muelle; Mien muestra «Mi agenda».
- **MV-026** (ficha) · Botón «Año»; desde las descripciones se marca hecha y se registra.
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

## Cambios desde la versión anterior

- **v2** (ronda 1, MV-025 y MV-026): la caja Agenda deja el inicio. En el muelle, arriba de Recortes, un ícono de
  calendario con reloj abre en el centro dos recuadros pegados:
  - **Calendario** (grande): primero el año (12 meses chicos con cada día pintado) y a los 0,9 s se acerca solo al mes
    actual, agrandando ese mes desde su lugar (0,55 s). En el mes: flechas ‹ › de mes, el botón **Año** (se aleja) y
    los días con una barra del color de su estado más urgente: **vencida** (pendiente y ya pasó; rojo), **por vencer**
    (pendiente en los próximos 3 días; ámbar), **programada** (pendiente más adelante; azul), **hecha** (verde). Hoy
    lleva una flecha ▼ que salta suave encima. Leyenda abajo. En el año, un clic en un mes se acerca a él.
  - **Descripciones** (angosto, 19 rem): el día elegido (al abrir, hoy), cada actividad con hora y tipo, su estado,
    el asunto, «Con» cliente y oportunidad, responsable y resultado; **Marcar hecha** en las pendientes y **Registrar
    actividad** (tipo, cliente, oportunidad de ese cliente opcional, asunto, hora) para ese día. Esc o ✕ cierran.
  - Solo las actividades de la persona (responsable). En el celular, uno debajo del otro.
- **Mien:** tarjeta **Mi agenda** (primera): Hoy, Vencidas y Por vencer con su cantidad y hasta tres de cada una, y
  «Abrir la agenda».
- **Supuestos:** «por vencer» son 3 días; la agenda muestra solo lo propio; la pantalla `/agenda` vieja sigue existiendo
  sin caja.
- **Atajos:** las listas de clientes y oportunidades del formulario se leen en el componente; el reparto del año se
  calcula en PHP en cada apertura.
- **v3** (MV-055): el ícono de la agenda en el muelle pasa a ser **la hoja del día**, de 32 px como las demás
  aplicaciones: hoja blanca de esquinas redondeadas, banda turquesa (`#00B8D9` → `#00DAFF`) con el día abreviado en
  blanco y negrita (LUN, MAR, MIÉ, JUE, VIE, SÁB, DOM), el número del día grande en tinta (`#1F2328`, peso 800 para que
  se lea a 32 px) y un relojito dorado (`#F2C14E`, borde blanco, agujas `#5A3E00`) abajo a la derecha. Muestra hoy en la
  hora de Lima y se pone al día solo cada minuto (sin recargar). En modo claro, la hoja con borde de 1 px `#D9D4C9`. No
  toma franja, tema ni estación. `aria-label` «Agenda: sábado 21».
