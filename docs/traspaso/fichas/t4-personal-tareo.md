# Ficha · Personal, asignación y tareo (flujo 3)

- **Estado:** aprobada (MV-009)
- **Versión:** v1
- **Tramo de la ruta:** T4
- **Aprobada:** 2026-11-20 · etiqueta `aprobado/t4-personal-tareo-v1`
- **Fuentes del diseño:** `docs/diseno/v4/parte-4-flujo-03.md`; `parte-8-criterios-aceptacion.md` (F3).

## Qué hace

Cada persona tiene su categoría (con el costo hora estándar del APU), su vínculo y su habilitación de campo (SCTR). Se
asigna a proyectos con un porcentaje y una vigencia, y el tablero marca la sobreasignación antes del conflicto. Cada
semana se tarean sus horas por día y por partida; solo a quien tiene la habilitación vigente. Al cerrar la semana, las
horas pasan a comprometido y ejecutado de cada partida a la vez: el costo del proyecto incluye a la gente.

## Decisiones de Sebastián

- **MV-009** (general) · Aprobada sin revisión, como todas hasta terminar el sistema; los cambios vienen al final.

## Pantallas y navegación

- **Inicio:** cajas «Personal» y «Tareo» (ERP).
- **Personal y asignación** `/asignacion`: persona (categoría y vínculo debajo), habilitación («SCTR hasta …», en
  rojo si venció) y cuatro semanas desde la actual («PRY-2026-0003 60 % · PRY-2026-0001 60 %», en rojo con «120 % ·
  sobreasignado»). Botones: Nuevo colaborador, Asignar (avisa si queda sobreasignado).
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

## Atajos tomados

- Estado como texto; sin acciones registradas ni historial; consultas directas en las pantallas.
- La habilitación de campo es una fecha en el colaborador (no un documento con su SCTR por proyecto).

## Dónde está en el código

`app/Modules/Erp/Personal/` (provider, migración con las categorías, `Services/PersonalService.php`,
`Filament/Pages/Asignacion.php` y `TareoSemanal.php`); datos en `DemoSeeder::tramo4()`.
