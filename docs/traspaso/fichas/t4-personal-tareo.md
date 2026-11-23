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
- **Tareo semanal** `/tareo?proyecto=…&semana=…`: subtítulo con la semana, el proyecto, abierta o cerrada, horas y
  costo. Tabla: persona (y categoría), partida, Lu a Sá, horas y costo. Botones: Proyecto y semana, Agregar horas
  (persona, partida, seis días), Cerrar semana.

## Operaciones

| Operación | Campos | Valida | Qué produce |
|---|---|---|---|
| Nuevo colaborador | nombres, DNI (8, único), categoría, vínculo (planilla o eventual), SCTR hasta | — | colaborador |
| Asignar | persona, proyecto, dedicación (1 a 100 %), desde, hasta | dedicación en rango | asignación; aviso si la suma pasa de 100 % |
| Agregar horas | persona, partida del proyecto, horas Lu a Sá (0 a 16) | semana abierta; SCTR vigente («… no tiene su habilitación de campo (SCTR) vigente»); partida del proyecto | línea del tareo al costo hora de su categoría (una por persona y partida; se reemplaza si existe) |
| Cerrar semana | — | abierta y con horas | cerrada; cada línea, comprometido = ejecutado = horas × costo hora en su partida |

## Datos

Tablas nuevas `categorias_personal` (técnico 18.00, ayudante 12.00, especialista 35.00, jefe de proyecto 55.00,
desarrollador 45.00 por hora), `colaboradores`, `asignaciones`, `tareos` (proyecto y lunes de la semana, únicos;
estado) y `tareo_lineas` (persona, partida, horas por día, costo hora). Permisos `erp.personal.ver`, `.tarear`,
`.cerrar`.

## Reglas y números

- Semana del 09/11/2026 en Plaza de Armas: Julio Chávez (técnico) 20 h en PC-02.03 (360.00) y 24 h en PC-01.02
  (432.00); Luis Vela (ayudante) 24 h en PC-02.03 (288.00) → **68 h por 1,080.00**; **PC-02.03 ejecutado 648.00**;
  **PC-01.02 ejecutado 432.00** (72 % de 600.00). La semana del 16/11 queda abierta.
- Marco Ruiz (especialista) 60 % en Plaza de Armas y 60 % en P8 desde el 23/11 → **120 %, sobreasignado**.
- Tomás León tiene el SCTR vencido (31/10/2026): no se le puede tarear.

## Supuestos sin confirmar

- El costo del tareo es el estándar de la categoría; el real de planilla (flujo 9) no existe todavía.
- No hay pantalla «Mis horas» para desarrollo: las horas de desarrollo se tarean igual, en la misma tabla.
- Una semana cerrada no se corrige desde aquí (el ajuste de reclasificación es el del plan 2.8).
- La sobreasignación avisa pero no impide.
- (v2) El detalle del panel lista las asignaciones vigentes de cada semana (no había otro detalle); no se editan desde
  ahí. Las iniciales que siguen en el sistema son de usuarios (menú, avisos), no de colaboradores: no se tocaron.

## Atajos tomados

- (v2) La foto se sirve por `/personal/fotos/{id}` con solo el permiso de ver personal; el sexo es nulo en la base
  (obligatorio solo en los formularios); las fotos de demo son SVG dibujados por el seeder.

- Estado como texto; sin acciones registradas ni historial; consultas directas en las pantallas.
- La habilitación de campo es una fecha en el colaborador (no un documento con su SCTR por proyecto).

## Cambios desde la versión anterior

- **v2** (observaciones-6, MV-067): Personal asignado pasa de tabla a tarjetas con foto o avatar, buscador y panel de
  detalle; el colaborador gana sexo y foto. Demo (`Demo\FotosPersonalSeeder`): todos con sexo; 5 de 10 con foto
  ilustrada inventada (hombres y mujeres), el resto con avatar; dos colaboradoras nuevas: Milagros Torres Alva (60 % en
  Red de datos, con foto) y Diana Paz Carrión (sin asignar, sin SCTR, sin foto).

## Dónde está en el código

`app/Modules/Erp/Personal/` (provider, migración con las categorías, `Services/PersonalService.php`,
`Filament/Pages/Asignacion.php` y `TareoSemanal.php`); datos en `DemoSeeder::tramo4()`.
