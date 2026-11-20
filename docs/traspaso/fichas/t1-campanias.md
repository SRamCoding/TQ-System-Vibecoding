# Ficha · Campañas y leads (CRM-07)

- **Estado:** en curso (v2, ronda de cambios; la v1 quedó aprobada)
- **Versión:** v2
- **Tramo de la ruta:** T1
- **Aprobada:** 2026-11-19 · etiqueta `aprobado/t1-campanias-v1`
- **Fuentes del diseño:** `docs/diseno/v4/parte-2-crm.md` (paso 6, «Leads y conversión»); `crm-pantallas.md` (CRM-07:
  campañas y leads, convertir lead, detalle de campaña, nueva campaña); DBML `campanias`, `leads`.

## Qué hace

Recibe los contactos que llegan por ferias, la web o referidos (leads), los atiende y los convierte en oportunidades
solo después de revisar coincidencias: si la organización ya es cuenta (por RUC o por nombre parecido), se usa esa;
«Crear una cuenta nueva de todas formas» existe, pero es la última opción. Al convertir, el origen del lead pasa tal
cual a la cuenta y a la oportunidad. Cada campaña muestra cuántos leads trajo, cuántos se convirtieron, cuánto suman
sus oportunidades y cuánto costó cada lead. Como dice el v4, en Techquk el negocio entra por convocatorias: este
módulo es el último del CRM.

## Decisiones de Sebastián

- **MV-024** (cruza módulos) · Los leads son la primera etapa del embudo («Por calificar»); Campañas muestra resultados.
- **MV-005** (ficha) · Va al final del tramo 1.
- **MV-009** (general) · Aprobada sin revisión, como todas hasta terminar el sistema; los cambios vienen al final.

## Pantallas y navegación

- **Inicio:** caja «Campañas y leads» en el área CRM.
- **Campañas y leads** `/leads`: subtítulo «Sin atender +3 días · 3 · …». Tabla con Nombre (cargo debajo),
  Organización (RUC debajo), Origen (campaña debajo), Hace (en rojo si lleva más de 3 días sin atender), Estado y
  Oportunidad (enlace). Filtros: Ver (Por atender y contactados · Sin atender +3 días · Convertidos · Descartados ·
  Todos) y Campaña. En cada lead abierto: **Contactado**, **Convertir** y **Descartar**. Arriba: **Campañas** y **Nuevo
  lead**.
- **Convertir** (panel deslizante): «Se encontraron N coincidencias antes de crear nada.», y la elección «¿Con qué
  cuenta?»: cada coincidencia («Municipalidad Distrital de La Pampa Alta · 20699100065 · mismo nombre», «Hotel Mirador
  del Valle S.A.C. · … · coincide RUC»), «Otra cuenta existente…» o, al final, «Crear una cuenta nueva de todas
  formas» (pide RUC). Después, la oportunidad: nombre, monto estimado, línea de solución y cierre estimado. Al terminar
  ofrece «Abrir la oportunidad».
- **Campañas** `/campanias`: nombre (tipo debajo), periodo, presupuesto, leads, convertidos con su tasa, activa o
  cerrada. **Nueva campaña**. **Detalle** `/campanias/{id}`: datos, «Resultado» (leads, convertidos, tasa, en
  oportunidades, costo por lead) y sus leads.

## Operaciones

| Operación | Campos | Valida | Qué produce |
|---|---|---|---|
| Nuevo lead | nombre (obligatorio), cargo, organización, RUC, correo, teléfono, origen (obligatorio), campaña, qué pidió | — | lead «sin atender» |
| Contactado | — | lead sin atender | «contactado», con quién y cuándo |
| Convertir | cuenta (una coincidencia, otra existente o nueva con RUC), nombre de la oportunidad, monto (> 0), línea, cierre | lead abierto; cuenta nueva con RUC válido y que no exista | (cuenta nueva con el origen del lead) + oportunidad en prospecto con el origen del lead y el mensaje como descripción; lead «convertido» con su cuenta y oportunidad |
| Descartar | motivo (lista: no es mercado para Techquk, sin presupuesto, solo pedía información, datos falsos o duplicado, no respondió) | lead abierto | «descartado» con su motivo |
| Nueva campaña | nombre, tipo (feria, web, correo masivo, referidos, redes), desde, hasta, presupuesto, objetivo | — | campaña activa |

## Datos

Tablas nuevas `campanias` (nombre, tipo, desde, hasta, presupuesto, objetivo, activa) y `leads` (campaña, origen
comercial, nombre, cargo, organización, RUC, correo, teléfono, mensaje, cuenta, oportunidad, estado, motivo de
descarte, asignado, atendido el). El DBML guarda la ficha (`party_id`) del convertido; aquí, la cuenta. Permisos
`crm.campanias.ver` y `crm.campanias.gestionar`.

## Reglas y números

- Sin atender = lead «sin atender» que llegó hace más de 3 días.
- Coincidencias: primero la cuenta con el mismo RUC; después las cuentas cuyo nombre es igual o parecido al de la
  organización (la misma búsqueda de parecidos de «Nueva cuenta»).
- Tasa = convertidos ÷ leads × 100, a un decimal; costo por lead = presupuesto ÷ leads.
- Feria Tecnológica del Norte 2026: 5 leads, 1 convertido (20.0 %), S/ 145,000.00 en oportunidades, presupuesto
  8,500.00 → S/ 1,700.00 por lead.
- Demo: 3 campañas, 12 leads (3 sin atender más de 3 días, 3 contactados, 3 convertidos —uno creó su cuenta y
  oportunidad, dos apuntan a oportunidades que ya existían—, 2 descartados y 1 nuevo de hoy).

## Supuestos sin confirmar

- Tipos de campaña y motivos de descarte como listas fijas.
- Al convertir, la oportunidad nace en prospecto con el ejecutivo que convierte.
- No hay asignación automática de leads ni aviso por los que llevan días sin atender.
- El correo y el teléfono del lead no se vuelven contacto de la cuenta al convertir.

## Atajos tomados

- Sin acciones registradas: los botones llaman al servicio o crean el registro directo.
- Estado, tipo y motivo como texto.
- Las cifras de la campaña se calculan fila por fila en la lista.
- Los leads de demo que apuntan a oportunidades existentes se enlazan directo, sin pasar por «Convertir».

## Dónde está en el código

`app/Modules/Crm/Campanias/` (provider, `Services/LeadsService.php`, modelos `Campania` y `Lead`,
`Filament/Resources/Leads` y `Filament/Resources/Campanias`); datos en `DemoSeeder::campanias()`.

## Cambios desde la versión anterior

- **v2** (ronda 1, MV-024): la caja «Campañas y leads» pasa a ser **Campañas** (`/campanias`), con columnas nuevas
  Monto generado y Costo por lead junto a Leads y Convertidos (con su tasa). Los leads (`/leads`) son la pestaña
  **Por calificar**, la primera de la portada de Oportunidades (con su cantidad de nuevos y contactados).
- **Supuesto:** el tablero y la faja de oportunidades no suman una columna de leads; «Por calificar» vive como pestaña.
