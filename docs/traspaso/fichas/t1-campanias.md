# Ficha · Campañas y leads (CRM-07)

- **Estado:** en curso (v8, ronda de cambios; la v1 quedó aprobada)
- **Versión:** v8
- **Tramo de la ruta:** T1
- **Aprobada:** 2026-11-19 · etiqueta `aprobado/t1-campanias-v1`
- **Fuentes del diseño (v3):** `docs/vibe/pedidos/observaciones-7.md` (punto 6) y `lienzos/campanias/` (`Main.dc.html`,
  `Clientes.dc.html`; `Pac.dc.html` en espera).
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

- **MV-105** (ficha) · Retorno real (margen de lo ganado ÷ gastado; en ejecución, el margen actual del proyecto) en verde o coral y retorno esperado (margen previsto × probabilidad ÷ gastado) en gris; los dos si hay ganadas y abiertas; «sin gasto» sin gasto. Calculado al mirar.

- **MV-104** (ficha) · Clic en la tarjeta abre la ficha de la campaña (cabecera, cifras en grande, pestañas Contactos · Oportunidades · Gastos · Seguimiento · Actividad); los atajos de la tarjeta y «Registrar gasto» se abren en una ventana al medio.

- **MV-103** (ficha) · Cada contacto que llega es nuevo o ya registrado (teléfono, correo o RUC); el ya registrado no se duplica. Tarjetas de Campañas y Canales: contactos sin barra, oportunidades «N de X» y ganadas «N de X» con barra.

- **MV-098** (ficha) · Sección «Canales» en Campañas: una tarjeta por canal con leads, calificados, ganados, ganado, margen, gasto, costo por lead y retorno del periodo (mes, trimestre o año); clic lleva a Mis oportunidades filtrado por Por calificar y ese canal.

- **MV-097** (cruza módulos) · Cada lead lleva su canal (catálogo «Canales de los leads» con ícono); la campaña también. Filtro «Origen» en Mis oportunidades. La landing crea leads en `POST /entrada/leads` con clave, casilla trampa y límite por minuto; aviso en Mien.

- **MV-079** (cruza módulos) · Campañas con su retorno (gasto real, margen de lo ganado, formulario y QR) y Mis clientes
  (tres grupos y campañas a clientes con su seguimiento como tareas). Radar del PAC: primero investigar y avisar.
- **MV-074** (cruza módulos) · Los leads son tarjetas «Por calificar» en la lista de Oportunidades (Calificar /
  Descartar); `/leads` lleva ahí. Reemplaza la pestaña de MV-024.
- **MV-024** (cruza módulos) · Los leads son la primera etapa del embudo («Por calificar»); Campañas muestra resultados.
- **MV-005** (ficha) · Va al final del tramo 1.
- **MV-009** (general) · Aprobada sin revisión, como todas hasta terminar el sistema; los cambios vienen al final.

## Pantallas y navegación

- **Retorno** (v8, MV-105): en las tarjetas y cifras de Campañas, en Canales y en la ficha de la campaña: «9.1×» (verde o coral), «6.7× esperado» (gris), «9.1× · + 2.3× esperado» (en la tarjeta y la cifra de arriba, el esperado en una segunda línea) o «sin gasto». Al pasar el puntero: «Por cada S/ 1 invertido volvieron S/ 9.10 de ganancia» y el detalle de lo esperado por oportunidad (etapa, probabilidad, margen previsto y lo que aporta; sin cotización, «monto × margen mínimo»).

- **Ficha de la campaña** (v7, MV-104): «← Volver a Campañas» (a la sección donde se estaba); cabecera con nombre, estado (planificada, en curso, terminada), tipo, canal con ícono, fechas, responsable, objetivo y el retorno; botones Formulario y QR, Registrar gasto, Editar. Cifras: invertido / presupuesto, contactos (nuevos y ya registrados), oportunidades (N de X), ganadas y ganado, margen, costo por contacto y por contacto nuevo. Pestañas: Contactos (nombre, organización, teléfono, correo, nuevo o ya registrado y qué era, fecha, en qué quedó; clic abre su oportunidad o su ficha de persona), Oportunidades (etapa con su gema, monto, margen, responsable), Gastos (fecha, concepto, imputación, comprobante, monto; gastado, presupuesto y lo que queda o se pasó), Seguimiento (campañas a clientes) y Actividad (quién creó, editó y registró gastos).
- **Tarjeta** (v7): «N contactos llegaron», «3 de 15», «1 de 3» y «Gastado / presupuesto» son enlaces (subrayados al pasar el puntero) que abren su lista en una ventana al medio («Oportunidades de Expo Seguridad Norte · 3»; × o Esc cierran); clic en el resto de la tarjeta abre la ficha. «Ver contactos» abre el atajo de contactos.
- **Registrar gasto** (v7): ventana al medio con fecha, concepto, monto, proveedor, sede, comprobante (PDF o imagen, opcional), el selector de imputación (por omisión, gasto directo sin proyecto · centro de costo Comercial) y motivo del exceso. Al guardar, la tarjeta y la ficha se redibujan sin recargar.

- **Embudo de la tarjeta** (v6, MV-103): «15 contactos llegaron · 11 nuevos · 4 ya registrados» (al pasar el puntero por «ya registrados», la lista con nombre y qué era: ya era cliente, contacto de un cliente, ya había escrito antes, ya tenía una oportunidad abierta); «Oportunidades» con barra y «3 de 15»; «Ganadas» con barra y «1 de 3». En Canales, igual con «Calificados». Al pasar el puntero por «Gastado», el costo por contacto nuevo. Al registrar un contacto con «+ Nuevo», el aviso dice si ya lo conocíamos.

- **Canales** (v5, MV-098, lienzo `campanias-vista/Canales.dc.html`): chips Campañas · Canales · Mis clientes. Arriba, cuatro cifras (leads del periodo y de cuántos canales, calificados y %, el canal que trae más, el que rinde más). Selector Este mes · Trimestre · Año y «Ordenar: leads / ganado / retorno». Una tarjeta por canal con leads (solo los canales con leads); retorno en verde (1× o más), coral (menos) o «sin gasto». Clic en la tarjeta: Mis oportunidades con `etapa=por_calificar` y `origen=<canal>`. Enlace «Probar la entrada de la página web» (abre `/entrada/prueba`, observación 9.3).

- **Canal** (v4, MV-097): «+ Nuevo → Contacto por calificar» y «Nueva campaña» piden el canal (el origen comercial del lead sale de él y se puede cambiar). En las tarjetas de Por calificar y en el panel de Por calificar del Embudo, el canal como etiqueta con su ícono. Mis oportunidades tiene el grupo de filtros «Origen» (plegable), con los canales que tienen algo: filtra los leads por su canal y las oportunidades por el canal del lead del que nacieron.
- **Entrada de la landing** (v4, MV-097): `POST /entrada/leads` (campos y respuestas en `docs/vibe/conectar-landing.md`); `/entrada/prueba` es la página de prueba (con sesión) que envía un formulario de ejemplo. Mien: «Nuevo contacto desde la página web: [nombre] · [organización]» (los de los últimos 3 días sin atender).

- **v3 · Campañas** `/campanias` (MV-079): chips **Campañas · Mis clientes** (el Radar del PAC no se muestra hasta que
  Sebastián decida; el calendario, más adelante). «Nueva campaña» arriba.
  - **Campañas:** cinco cifras del año: invertido (contra lo presupuestado), contactos (y costo por contacto),
    oportunidades (y % de los contactos), ganado (y cuántos proyectos) y **retorno** (verde desde 1×, coral si no). Una
    tarjeta por campaña: nombre, tipo, retorno; **mini embudo** contactos → oportunidades → ganadas en barras
    proporcionales; gastado / presupuesto (coral si se pasó); ganado; **margen de lo ganado** («(previsto)» si algún
    proyecto no cerró); «N sin atender hace 3 días»; en las de clientes, cuántos clientes. Botones **Formulario y QR**
    (enlace público, QR para imprimir o descargar, enlace de WhatsApp «Hola, vengo de [campaña]», con copiar),
    **Registrar gasto** (panel) y **Ver contactos** (la ficha de la campaña).
  - **Mis clientes:** tres tarjetas con su cantidad y monto estimado: Garantía por vencer en 90 días (ofrecer
    mantenimiento preventivo), Instalado hace 3 años o más (ofrecer renovación), Solo tienen una línea (ofrecer otra).
    **Ver la lista**: cada cliente con su proyecto y el dato que lo puso ahí («vence en 21 días», coral bajo 30), con su
    casilla para incluirlo o no. **Crear campaña** (panel): nombre, responsable y el seguimiento editable (día, canal,
    plantilla con {cliente}, {oferta} y {proyecto}); al enviar lleva a la ficha de la nueva campaña.
  - **Ficha de la campaña** `/campanias/{id}`: además de lo de siempre, «Retorno» con los gastos reales y, en las campañas
    a clientes, «Clientes y seguimiento»: cada paso con el texto ya armado por cliente y su botón **Copiar**.
  - **Formulario público** `/c/{token}` (sin sesión): nombre, organización, teléfono, correo y qué necesita; al enviar,
    «¡Gracias!». Solo para campañas activas.
  - **Mien:** «N contactos de [campaña] llevan 3 días sin atender» y, de las campañas a clientes, cada paso como
    actividad en la agenda del responsable.

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

- **Retorno** (v8, MV-105): real = margen de las ganadas ÷ gastado (margen real del cierre; en ejecución, el margen proyectado del proyecto: venta − lo proyectado; sin proyecto, sin margen). Esperado = Σ (margen previsto de la cotización vigente, o monto × `crm.cotizacion_margen_minimo` sin cotización) × probabilidad de la etapa ÷ gastado, de las oportunidades abiertas nacidas de la campaña (o, en Canales, de los leads del canal en el periodo). Sin gasto: «sin gasto». Sin ganadas ni abiertas: 0×.

- **Ya registrado** (v6, MV-103): mismo RUC de una cuenta (cliente), mismo correo o teléfono (últimos 9 dígitos) de un contacto de un cliente o de un lead anterior. Si tiene un lead abierto (nuevo o contactado) o una oportunidad abierta de su cuenta, no se crea nada: queda la llegada (`lead_llegadas`) y, con oportunidad, una actividad hecha «X llegó también por la campaña…». Si no, el lead se crea marcado ya registrado y unido a su cuenta. Vale para el formulario de la campaña, la landing y «+ Nuevo». Costo por contacto nuevo = gastado ÷ nuevos.

- **Cifras de Canales** (v5, MV-098): periodo desde el 1 del mes, trimestre o año hasta hoy, por la fecha de llegada del lead. Calificado: el lead pasó a oportunidad. Ganado y margen: las oportunidades ganadas de esos leads, con el mismo margen de Campañas (real del cierre o previsto del proyecto). Gastado: cada lead del periodo carga el costo por contacto de su campaña (todo lo gastado en la campaña ÷ todos sus contactos); retorno = margen ÷ gastado; costo por lead = gastado ÷ leads. «Rinde más»: el de mayor retorno entre los que tienen gasto.

- **Canal del lead** (v4, MV-097): de `utm_source` (ig → Instagram, fb/meta → Facebook, google/web → Página web…), si no de su campaña, si no «Página web». La campaña sale de `campania` (su código o nombre) o `utm_campaign`. Origen comercial según el canal: Feria → FERIA, Referido → REFERENCIA, Llamada → VISITA_FRIO, los demás → SITIO_WEB. Los leads ya cargados tomaron el canal de su campaña o, sin campaña, de su origen. Límite: 10 envíos por minuto por dirección (`TECHQUK_ENTRADA_LEADS_POR_MINUTO`); se exige teléfono o correo.

- **v3 · Retorno** = margen de lo ganado ÷ gastado (una decimal). **Gastado** = suma de los gastos reales de la campaña
  (gasto directo, centro de costo Comercial, rubro «Publicidad y ferias», con su sede; si pasa el saldo de la sede en
  el mes, pide motivo). **Margen de lo ganado** = por cada oportunidad ganada de sus contactos, el margen real del cierre
  de su proyecto; si no cerró, el previsto del proyecto (venta − presupuestado). Contactos = leads de la campaña;
  oportunidades = los convertidos; ganadas = sus oportunidades ganadas.
- **v3 · Demo:** feria 2,631.21 / 2,400.00 (se pasó), ganado 107,793.00, margen 23,944.00 → 9.1×; Facebook 2,411.00 /
  2,500.00, margen 16,153.67 → 6.7×; correo 180.00 → 0×; referidos 3,853.53, margen 54,334.80 (previsto) → 14.1×. Año:
  S/ 9,075.74 invertido, 76 contactos, 14 oportunidades, 4 proyectos, 10.4×.
- **v3 · Mis clientes:** garantía = series instaladas con la garantía en los próximos 90 días (por proyecto); renovación =
  proyectos cerrados que terminaron hace 3 años o más; una línea = cuentas cuyas oportunidades ganadas son todas de la
  misma línea de solución. Estimados: mantenimiento 10 % de lo vendido (S/ 8,400.00 si no se sabe), renovación 60 %
  (S/ 150,000.00 si no se sabe), otra línea S/ 40,000.00 por cliente.
- **v3 · Seguimiento por defecto:** día 1 correo, día 3 WhatsApp, día 7 llamada; cada paso, una actividad por cliente en
  la agenda del responsable el día que toca (correo como «correo»; WhatsApp y llamada como «llamada»).

- Sin atender = lead «sin atender» que llegó hace más de 3 días.
- Coincidencias: primero la cuenta con el mismo RUC; después las cuentas cuyo nombre es igual o parecido al de la
  organización (la misma búsqueda de parecidos de «Nueva cuenta»).
- Tasa = convertidos ÷ leads × 100, a un decimal; costo por lead = presupuesto ÷ leads.
- Feria Tecnológica del Norte 2026: 5 leads, 1 convertido (20.0 %), S/ 145,000.00 en oportunidades, presupuesto
  8,500.00 → S/ 1,700.00 por lead.
- Demo: 3 campañas, 12 leads (3 sin atender más de 3 días, 3 contactados, 3 convertidos —uno creó su cuenta y
  oportunidad, dos apuntan a oportunidades que ya existían—, 2 descartados y 1 nuevo de hoy).

## Supuestos sin confirmar

- **v8 · Margen actual** del proyecto en ejecución = el «margen proyectado» que muestra su tablero (venta − la proyección de costo).

- **v7 · Imputación de una campaña:** solo gasto directo a un centro de costo (si se elige «Proyecto › partida», avisa y no guarda).
- **v7 · Actividad** se arma con la creación, la última edición y los gastos registrados (sin la bitácora campo por campo).

- **v6 · Oportunidades de la campaña:** solo las que nacieron de sus leads; una llegada a una oportunidad que ya existía cuenta como contacto ya registrado, no como oportunidad nueva.
- **v6 · Demo:** en la feria, 5 clientes ya conocidos y 3 contactos con oportunidad abierta; en Facebook, 3 que ya habían escrito.

- **v5 · Gasto prorrateado por lead:** se eligió así para que el retorno de un canal cuadre con el de sus campañas (un gasto de julio no deja en cero un canal cuyos leads llegaron en noviembre). Los leads sin campaña no cargan gasto.
- **v5 · Demo:** los calificados de los canales nuevos se unieron a oportunidades que ya existían sin lead; el canal «Página web» tiene una ganada con margen negativo (la del proyecto con pérdida de la demo).

- **v4 · Origen comercial y canal conviven:** el lead sigue teniendo su origen comercial (pasa a la cuenta y a la oportunidad, plan 2.20a); se llena solo desde el canal y se puede cambiar.
- **v4 · Clave en local:** sin `TECHQUK_ENTRADA_LEADS_CLAVE`, en un equipo local vale `clave-local-de-prueba`; en el servidor, sin clave, la entrada queda cerrada.
- **v4 · Ícono de WhatsApp:** dibujo propio (globo con auricular), no el logotipo oficial.

- **v3 · Radar del PAC** no se construye todavía (pedido: investigar y avisar). Lo investigado: el OECE publica el PAC
  como un Excel anual (`PAC{año}_0.xlsx`, ~8–17 MB, actualizado cada mes) con entidad y RUC, procedimiento, objeto,
  descripción del ítem, cantidad, ubicación y **mes previsto**, pero **sin valor estimado**; la API OCDS no trae el PAC.
- **v3 · Estimados de Mis clientes** (10 %, 60 %, S/ 40,000.00) y que la campaña a clientes no tenga presupuesto.
- **v3 · Lo que el cliente responda** entra como lead de la campaña (Por calificar) cuando alguien lo registra; el
  envío automático por correo o WhatsApp queda como punto de extensión.
- **v3 · El número de WhatsApp** es el parámetro `crm.whatsapp_comercial` (inventado en la demo).
- **v3 · Tres proyectos antiguos** (2021–2023) se agregaron a la demo para el grupo de renovación, con su oportunidad
  ganada de entonces.

- Tipos de campaña y motivos de descarte como listas fijas.
- Al convertir, la oportunidad nace en prospecto con el ejecutivo que convierte.
- No hay asignación automática de leads ni aviso por los que llevan días sin atender.
- El correo y el teléfono del lead no se vuelven contacto de la cuenta al convertir.

## Atajos tomados

- **v7 · Comprobante** guardado en el disco público (`campanias/comprobantes`) con su ruta en `campania_gastos`, sin pasar por los adjuntos del núcleo.

- **v6 · Reconocimiento** con consultas directas a las tablas de contactos y cuentas desde el servicio de leads (sin pasar por los servicios del Directorio).

- **v5 · Canales se calcula al mirar** recorriendo los leads del periodo y el margen de cada ganada (sin consulta agregada ni caché).

- **v4 · Entrada de leads sin más defensa:** clave compartida (visible si la landing es solo HTML), casilla trampa y límite por dirección; sin CAPTCHA ni firma de cada envío. Lo crea el usuario de sistema. La ruta está fuera de CSRF y con CORS solo para ella.
- **v4 · Canal de una oportunidad:** se busca por el lead que la creó (`leads.oportunidad_id`), sin columna propia en la oportunidad.

- **v3:** el formulario público solo se protege con un límite por dirección (5 envíos cada 10 minutos) y un campo
  trampa. Mis clientes y el margen leen proyectos, series y cierres con consultas directas. Los proyectos antiguos de la
  demo se insertaron directo en la base. Los gastos de campaña se enlazan en una tabla propia.

- Sin acciones registradas: los botones llaman al servicio o crean el registro directo.
- Estado, tipo y motivo como texto.
- Las cifras de la campaña se calculan fila por fila en la lista.
- Los leads de demo que apuntan a oportunidades existentes se enlazan directo, sin pasar por «Convertir».

## Dónde está en el código

`app/Modules/Crm/Campanias/` (provider, `Services/LeadsService.php`, modelos `Campania` y `Lead`,
`Filament/Resources/Leads` y `Filament/Resources/Campanias`); datos en `DemoSeeder::campanias()`.

## Cambios desde la versión anterior

- **v8** (MV-105): retorno real y esperado.

- **v7** (MV-104): ficha de la campaña, atajos y gasto al medio.

- **v6** (MV-103): contactos nuevos o ya registrados y barras N de X.

- **v5** (MV-098): vista Canales.

- **v4** (MV-097): canal de cada lead, filtro «Origen» y entrada desde la landing.

- **v3** (MV-079): Campañas con retorno y gasto real, formulario y QR, Mis clientes y campañas a clientes con su
  seguimiento; avisos en Mien.

- **v2** (ronda 1, MV-024): la caja «Campañas y leads» pasa a ser **Campañas** (`/campanias`), con columnas nuevas
  Monto generado y Costo por lead junto a Leads y Convertidos (con su tasa). Los leads (`/leads`) son la pestaña
  **Por calificar**, la primera de la portada de Oportunidades (con su cantidad de nuevos y contactados).
- **Supuesto:** el tablero y la faja de oportunidades no suman una columna de leads; «Por calificar» vive como pestaña.
