# Ficha · Personal, asignación y tareo (flujo 3)

- **Estado:** en curso (v2, observaciones-6; la v1 quedó aprobada)
- **Versión:** v2
- **Tramo de la ruta:** T4
- **Aprobada (v1):** 2026-11-20 · etiqueta `aprobado/t4-personal-tareo-v1`
- **Fuentes del diseño:** `docs/diseno/v4/parte-4-flujo-03.md`; `parte-8-criterios-aceptacion.md` (F3).

## Qué hace

Cada persona tiene su categoría (con el costo hora estándar del APU), su vínculo y su habilitación de campo (SCTR). Se
asigna a proyectos con un porcentaje y una vigencia, y el tablero marca la sobreasignación antes del conflicto. Cada
semana se tarean sus horas por día y por partida; solo a quien tiene la habilitación vigente. Al cerrar la semana, las
horas pasan a comprometido y ejecutado de cada partida a la vez: el costo del proyecto incluye a la gente.

## Decisiones de Sebastián

- **MV-070** (ficha) · El tareo como cuadrícula tipo hoja de cálculo, con tira de semanas, cifras, avisos, vistas por persona o partida, cierre con resumen y asistencia del día.
- **MV-069** (ficha) · La jornada de cada persona y el tareo que se rellena solo, con sus atajos.
- **MV-067** (ficha; el sexo y la foto cruzan a Personas) · Personal asignado en tarjetas con foto de perfil o avatar por defecto.
- **MV-009** (general) · Aprobada sin revisión, como todas hasta terminar el sistema; los cambios vienen al final.

## Pantallas y navegación

- **Inicio:** cajas «Personal» y «Tareo» (ERP).
- **Personal asignado** `/asignacion` (v2, MV-067): buscador por nombre arriba a la derecha y **tarjetas en dos
  columnas** (una en pantallas angostas), una por persona activa, por nombre. Arriba: foto o avatar en círculo, nombre,
  «categoría · vínculo» y la etiqueta «SCTR hasta dd/mm/aaaa» (coral si venció o «Sin SCTR»). Debajo, sus 4 semanas
  (esta y las tres siguientes) como barritas «Sem NN · dd/mm» con su porcentaje: gris en 0 %, turquesa hasta 100 %,
  coral con «sobreasignado» si pasa. Al filtrar, las tarjetas se reacomodan con la animación de las filas. **Clic** (o
  Enter): panel deslizante con la foto grande, el SCTR y, semana por semana, el total y cada proyecto (código, nombre,
  vigencia, %) o «Sin asignar»; al pie **Subir/Cambiar foto** (recorte cuadrado; en esa ventana, «Quitar foto»).
  Botones de la página: Nuevo colaborador (pide **sexo**), Asignar (avisa si queda sobreasignado).
- **Foto y avatar (v2):** el colaborador tiene **sexo** (Hombre/Mujer, obligatorio al crearlo y al contratarlo desde
  Demanda y selección) y **foto** opcional (JPG, PNG o WebP hasta 5 MB, recortada cuadrada a 400 × 400). Sin foto se
  dibuja el avatar por defecto: busto ilustrado de hombre (pelo corto) o de mujer (pelo largo), en tonos neutros que
  salen de los colores del ambiente (claro y oscuro). Se ve en Personal asignado y en Fichas laborales (columna a la
  izquierda del nombre; acción **Foto** para cambiar sexo y foto).
- **Tareo** `/tareo?proyecto=…&semana=…&agrupar=persona|partida&vista=semana|hoy` (v2, MV-069 y MV-070). Llegar a una
  semana que no existe la abre (si la persona puede tarear) y la rellena con la jornada.
  - Botones arriba: **Copiar la semana anterior**, **Rellenar con la jornada**, **Cerrar semana** (solo con la semana
    abierta).
  - **Tira de semanas del mes** (el mes de su jueves): «Sem NN · dd/mm» con ✓ si está cerrada, la elegida resaltada,
    en coral las que ya pasaron sin cerrar (abiertas, o sin tareo pero con gente asignada); flechas ← → pasan de semana.
    A la derecha, el **proyecto como chip**: clic abre una ventana con el proyecto (selector con buscador).
  - **Cuatro cifras:** horas de la semana; costo de la semana; mano de obra gastada / presupuestada del proyecto
    (porcentaje grande, barra, «S/ gastado de S/ presupuestado»; coral si pasa de 100 %); personas tareadas «N de M»
    (coral si faltan).
  - Selector **Semana / Hoy**; en Semana, **Por persona / Por partida** (las filas se reacomodan con la animación).
  - **Por persona (cuadrícula):** filas = persona y partida; columnas Lu a Sá (Do si alguien lo trabaja o lo tiene en su
    jornada), Horas, Costo y dos íconos (Llenar la fila, Quitar la línea). En la primera fila de cada persona: foto o
    avatar, nombre, categoría y la etiqueta «44 de 48 h» (lo que suma esa semana en todos sus proyectos contra su
    jornada; coral si la pasa; al pasar el puntero, el detalle por proyecto). La partida es un selector en la fila
    («Elige la partida» si falta). Celdas: clic y escribir; Tab y Enter avanzan (Enter baja en la misma columna); Esc
    deshace la celda; se guarda al salir de la celda, sin recargar. Las líneas propuestas se ven suaves con la etiqueta
    «de la jornada» hasta que alguien las toca. Avisos: feriado con la columna sombreada y su nombre al pasar el
    puntero; más de 8 h en un día (sumando sus líneas) marcado «horas extra»; día en blanco de alguien asignado en un
    día de su jornada, ya pasado o de hoy, «¿faltó?»; «faltó» si se marcó; SCTR vencido: fila coral sin poder escribir,
    y quien está asignado con SCTR vencido sin línea aparece en coral «Asignado N %, sin tarear». Al final, la fila
    nueva (persona, partida, **Agregar fila**) y el pie con el total de cada día, de la semana y su costo. Cerrada, todo
    es de solo lectura.
  - **Por partida:** partida, personas, horas, costo de la semana y la barra de mano de obra gastada / presupuestada de
    esa partida (coral si pasa de 100 %).
  - **Hoy** (se elige solo en pantallas de menos de 640 px si no se pidió otra vista): chips de los días de la semana
    (hoy por omisión) y una tarjeta por persona con foto, horas de ese día y dos botones grandes **Vino / Faltó**.
  - **Cerrar semana** abre un panel deslizante con lo que se carga a cada partida (horas y monto), el total y los avisos
    pendientes (faltas, «¿faltó?», horas extra, sin partida, SCTR vencido); se confirma ahí.

## Operaciones

| Operación | Campos | Valida | Qué produce |
|---|---|---|---|
| Nuevo colaborador | nombres, DNI (8, único), categoría, vínculo (planilla o eventual), SCTR hasta | — | colaborador |
| Asignar | persona, proyecto, dedicación (1 a 100 %), desde, hasta | dedicación en rango | asignación; aviso si la suma pasa de 100 % |
| Jornada (v2) | horas Lu a Do (0 a 16; 0 = no trabaja) | rango | jornada del colaborador; al crearlo se propone por vínculo |
| Rellenar con la jornada (v2) | — | semana abierta | por cada asignado con SCTR vigente cuyas líneas nadie tocó: una línea «de la jornada» con jornada × dedicación repartida por igual en sus días, feriados en 0, en la partida de su última semana en el proyecto (o sin partida) |
| Copiar la semana anterior (v2) | — | semana abierta; hay semana anterior en el proyecto | las líneas de la semana anterior (sin feriados, sin SCTR vencido; no pisa líneas tocadas) |
| Celda (v2) | línea, día, horas (0 a 16, coma o punto) | semana abierta; SCTR vigente | horas del día; la línea deja de ser «de la jornada»; quita «faltó» si se ponen horas |
| Llenar la fila (v2) | línea | hay un día con horas | ese valor en los demás días de su jornada (sin feriados ni faltas) |
| Partida de la línea (v2) | línea, partida | del proyecto; no repetida para la persona | cambia la partida |
| Agregar fila (v2) | persona, partida | semana abierta; SCTR vigente; partida del proyecto; no repetida | línea sin horas |
| Quitar la línea (v2) | línea | semana abierta | se borra |
| Vino / Faltó (v2) | persona, día | semana abierta; SCTR vigente | Vino: su jornada de ese día (8 h si es 0) en su primera línea y 0 en las demás; Faltó: 0 en todas y «faltó» |
| Cerrar semana | — | abierta y con horas; las líneas con horas tienen partida | se borran las líneas sin horas; cerrada; cada línea, comprometido = ejecutado = horas × costo hora en su partida; ninguna queda «de la jornada» |

## Datos

Tablas nuevas `categorias_personal` (técnico 18.00, ayudante 12.00, especialista 35.00, jefe de proyecto 55.00,
desarrollador 45.00 por hora), `colaboradores`, `asignaciones`, `tareos` (proyecto y lunes de la semana, únicos;
estado) y `tareo_lineas` (persona, partida, horas por día, costo hora). v2: `colaboradores.sexo`, `.foto_ruta`,
`.jornada` (JSON día → horas); `tareo_lineas.do`, `.de_jornada` (booleano), `.faltas` (JSON con los días marcados) y
`partida_control_id` puede ser nula mientras la línea pide su partida. Permisos `erp.personal.ver`, `.tarear`,
`.cerrar`.

## Reglas y números

- Semana del 09/11/2026 en Plaza de Armas: Julio Chávez (técnico) 20 h en PC-02.03 (360.00) y 24 h en PC-01.02
  (432.00); Luis Vela (ayudante) 24 h en PC-02.03 (288.00) → **68 h por 1,080.00**; **PC-02.03 ejecutado 648.00**;
  **PC-01.02 ejecutado 432.00** (72 % de 600.00). La semana del 16/11 queda abierta.
- Marco Ruiz (especialista) 60 % en Plaza de Armas y 60 % en P8 desde el 23/11 → **120 %, sobreasignado**.
- Tomás León tiene el SCTR vencido (31/10/2026): no se le puede tarear.
- (v2) Planilla: Lu a Vi 8 h y Sá 4 h = **44 h** (el pedido decía 48: ver supuestos). Marco 60 % de 44 h = 26.4 h →
  **4.4 h** en cada uno de sus 6 días. Horas extra: más de **8 h** en un día. Demo de la semana del 23/11 en Plaza de
  Armas: Julio 10 h el lunes (horas extra), Luis «de la jornada», Marco sin horas el lunes («¿faltó?») y en PC-01.02,
  Tomás en coral; **3 de 4** tareadas; mano de obra **213 %** (S/ 4,214.00 de S/ 1,980.00); PC-01.02 pasa su
  presupuesto. Red de datos: Carla faltó el lunes, Marco suma 48.4 de 44 h (dos proyectos); la semana del 07/12 con
  el 8 y el 9 de diciembre (feriados) en 0.

## Supuestos sin confirmar

- El costo del tareo es el estándar de la categoría; el real de planilla (flujo 9) no existe todavía.
- No hay pantalla «Mis horas» para desarrollo: las horas de desarrollo se tarean igual, en la misma tabla.
- Una semana cerrada no se corrige desde aquí (el ajuste de reclasificación es el del plan 2.8).
- La sobreasignación avisa pero no impide.
- (v2) La **mano de obra presupuestada** de una partida es su presupuesto vigente (el mismo saldo que usa Partidas):
  no existe todavía el desglose de mano de obra por partida; la del proyecto suma las partidas donde se tarea. Lo
  gastado es el costo del tareo de todas sus semanas hasta la elegida (cerradas y abiertas).
- (v2) Que el relleno use la **dedicación** de la asignación (MV-069 pide confirmarlo).
- (v2) Que la partida sea la de la **semana anterior**: se toma la última semana en que tareó en ese proyecto (MV-069 pide
  confirmarlo).
- (v2) **Planilla: 48 h o 44 h.** El pedido dice «Lu a Vi 8 h y Sá 4 h = 48 h»; esos días suman 44 h. Se dejó Lu a Vi 8 h
  y Sá 4 h (44 h); si son 48, se cambia la jornada propuesta.
- (v2) Eventual sin jornada propuesta (vacía); en la demo los eventuales llevan Lu a Vi 8 h (Julio, Lu a Sá 8 h; Diana,
  vacía).
- (v2) «¿faltó?» solo marca días ya pasados o de hoy, y no en líneas «de la jornada».
- (v2) Llegar a una semana (también futura) la abre y la rellena.
- (v2) El detalle del panel lista las asignaciones vigentes de cada semana (no había otro detalle); no se editan desde
  ahí. Las iniciales que siguen en el sistema son de usuarios (menú, avisos), no de colaboradores: no se tocaron.

## Atajos tomados

- (v2) Tareo: la cuadrícula llama métodos de la página que llaman a `TareoService`; sin acciones registradas ni
  historial por celda; la jornada se guarda como JSON en el colaborador; «faltó» como JSON en la línea; las cifras se
  recalculan en cada cambio con consultas directas.
- (v2) La foto se sirve por `/personal/fotos/{id}` con solo el permiso de ver personal; el sexo es nulo en la base
  (obligatorio solo en los formularios); las fotos de demo son SVG dibujados por el seeder.

- Estado como texto; sin acciones registradas ni historial; consultas directas en las pantallas.
- La habilitación de campo es una fecha en el colaborador (no un documento con su SCTR por proyecto).

## Cambios desde la versión anterior

- **v2** (observaciones-6, MV-067): Personal asignado pasa de tabla a tarjetas con foto o avatar, buscador y panel de
  detalle; el colaborador gana sexo y foto. Demo (`Demo\FotosPersonalSeeder`): todos con sexo; 5 de 10 con foto
  ilustrada inventada (hombres y mujeres), el resto con avatar; dos colaboradoras nuevas: Milagros Torres Alva (60 % en
  Red de datos, con foto) y Diana Paz Carrión (sin asignar, sin SCTR, sin foto).
- **v2** (observaciones-6, MV-069 y MV-070): jornada por persona (en Nuevo colaborador, en Fichas laborales con su
  acción **Jornada** y su columna, al contratar y en la tarjeta de Personal asignado); el tareo se rellena solo y pasa a
  cuadrícula con tira de semanas, cifras, avisos, vistas por persona o partida, cierre con resumen y asistencia «Hoy».
  Se va «Agregar horas» y «Proyecto y semana». Demo en `Demo\TareoCasosSeeder`.

## Dónde está en el código

`app/Modules/Erp/Personal/` (provider, migración con las categorías, `Services/PersonalService.php`,
`Filament/Pages/Asignacion.php` y `TareoSemanal.php`); datos en `DemoSeeder::tramo4()`.
