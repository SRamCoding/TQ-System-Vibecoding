# Ficha · Personas: demanda, selección, habilitación, planilla y salida (flujo 9)

- **Estado:** aprobada (MV-009)
- **Versión:** v1
- **Tramo de la ruta:** fuera de la ruta (MV-015)
- **Aprobada (v1):** 2026-11-20 · etiqueta `aprobado/t7-personas-v1`
- **Fuentes del diseño:** `docs/diseno/v4/parte-4-flujo-09.md`; `parte-8-criterios-aceptacion.md` (F9: J. Chávez
  2,400.00 en ONP → aporte 312.00, neto 2,088.00, EsSalud 216.00, costo empresa 2,616.00).

## Qué hace

Lleva a las personas de la necesidad a la salida, sobre el personal y el tareo de `t4-personal-tareo`. Prevé a quién
se va a necesitar en 90 días por categoría; abre vacantes, junta candidatos y contrata (nace el colaborador con su
remuneración, régimen e ingreso, y si la vacante es de un proyecto queda asignado al 100 %). La habilitación de campo
deja de ser una fecha escrita: sale de los documentos con vencimiento (SCTR y examen médico ocupacional); con la
habilitación vencida no se tarea, salvo con autorización y motivo. Las certificaciones vencen con alerta y se ven junto
a la experiencia (los proyectos en los que estuvo), porque las convocatorias piden personal certificado. La planilla
del mes sale de los contratos y del tareo, con boletas, archivo PLAME y asiento, y muestra cuánto cuestan de verdad
las horas contra su costo estándar por proyecto. La salida arma la lista de pendientes y calcula la liquidación.

## Decisiones de Sebastián

- **MV-015** (general) · Lo que el v4 tenía y la ruta dejó fuera también entra; flujo 9 con planilla.
- **MV-009** (general) · Aprobada sin revisión; los cambios vienen al final.

## Pantallas y navegación

- **Inicio:** cajas «Personas», «Demanda y selección» y «Planilla» en el área ERP.
- **Personas** `/personas`: persona (categoría, vínculo y régimen debajo), remuneración, habilitación de campo («hasta
  dd/mm/aaaa», en rojo si venció o no tiene), certificaciones con su vencimiento, experiencia («3 proyectos: …») y
  salida (motivo y liquidación debajo). Filtro Activos / Que se fueron / Todos. Subtítulo: cuántos documentos vencen
  en 30 días. Botones por fila: **Documento** (agrega SCTR, examen, inducción o certificación) y **Salida**.
- **Demanda y selección** `/vacantes`: arriba la tabla de demanda (categoría, activos, ocupados al 100 % la próxima
  semana, libres, pedidos por vacantes abiertas en 90 días, brecha «faltan N» en rojo); abajo los candidatos por
  vacante con su estado y los botones **Contratar** y **Descartar**. Arriba: **Nueva vacante** y **Agregar candidato**.
- **Planilla** `/planilla?periodo=AAAA-MM`: subtítulo con el estado, el neto y el costo empresa del mes; tabla por
  trabajador (horas tareadas debajo, régimen, remuneración, aporte, neto, EsSalud, costo empresa) con **Boleta** por
  fila; debajo «Costo real de las horas contra su costo estándar» por proyecto. Botones: Periodo, Calcular, Cerrar
  planilla, Exportar a PLAME, Asientos.
- **Tareo → Agregar horas:** campo «Autorización con motivo (solo si su habilitación venció)».

## Operaciones

| Operación | Campos | Valida | Qué produce |
|---|---|---|---|
| Documento | tipo (sctr, emo, inducción, certificación), nombre, emisor, vence | — | documento; recalcula la habilitación |
| Nueva vacante | categoría, proyecto (opcional), cantidad, desde, perfil | — | vacante abierta |
| Agregar candidato | vacante abierta, nombres, DNI (8), nota | — | candidato «postulante» |
| Contratar | remuneración, régimen (ONP o AFP), ingreso | no contratado antes | colaborador en planilla; candidato contratado; vacante cubierta al completar su cantidad; asignación 100 % al proyecto de la vacante |
| Descartar | — | — | candidato descartado |
| Tarear con autorización | las horas + autorización (texto) | habilitación vencida → exige autorización con texto y el permiso `erp.personal.autorizar` | línea de tareo con su autorización guardada |
| Calcular planilla | periodo | no cerrada | rehace las líneas de los contratos vigentes en el mes (ingreso ≤ fin de mes, sin salida antes del mes) con las horas tareadas del mes |
| Cerrar planilla | — | en cálculo | cerrada; ya no se recalcula |
| Exportar a PLAME / Asientos | — | hay planilla | archivo de texto por trabajador (DNI, conceptos 0121, 0601, 0804); asiento (6211, 6271 contra 4031, 4032/417, 4111) |
| Salida | fecha, motivo y nota de traspaso | activo | asignaciones terminadas en esa fecha; usuario desactivado; liquidación; inactivo |

## Estados

Vacante: abierta → cubierta. Candidato: postulante → entrevistado → contratado / descartado. Planilla: borrador →
cerrada. Colaborador: activo → con salida.

## Datos

En `colaboradores`: remuneración, régimen (onp, afp), ingreso, salida, motivo de salida, liquidación. En
`tareo_lineas`: autorización. Tablas nuevas `colaborador_documentos` (tipo, nombre, emisor, vence), `vacantes`
(categoría, proyecto, cantidad, desde, perfil, estado), `candidatos` (vacante, nombres, DNI, nota, estado), `planillas`
(periodo único, estado, cerrada en) y `planilla_lineas` (régimen, remuneración, aporte, neto, EsSalud, costo empresa,
horas tareadas). Permisos nuevos `erp.personal.autorizar` y `erp.personal.planilla`.

## Reglas y números

- **Habilitación** = el menor entre el vencimiento del último SCTR y el del último examen médico; sin SCTR, no hay.
- **Planilla (simplificada):** aporte ONP 13 % o AFP 11.84 %; neto = remuneración − aporte; EsSalud 9 % a cargo de la
  empresa; costo empresa = remuneración + EsSalud. Redondeo a dos decimales.
  F9: J. Chávez 2,400.00 ONP → **312.00 / 2,088.00 / 216.00 / 2,616.00**. L. Vela 1,500.00 → 195.00 / 1,305.00 /
  135.00 / 1,635.00. M. Ruiz 3,500.00 AFP → 414.40 / 3,085.60 / 315.00 / 3,815.00.
- **Costo real de la hora** = costo empresa ÷ 192 h; la diferencia por proyecto = horas × real − horas × costo estándar
  (el de la categoría, que es el que va a las partidas). Demo noviembre, Plaza de Armas: 130 h, estándar 2,064.00,
  real 1,527.12, diferencia −536.88.
- **Demanda:** ocupado = dedicación ≥ 100 % la próxima semana; brecha = pedidos − libres (mínimo 0).
- **Liquidación (simplificada):** remuneración × meses del año ÷ 12 × 2 (CTS y vacaciones truncas).
- Demo: 6 personas con contrato, documentos y certificaciones (las de Julio Chávez y Carla Ríos vencen en diciembre);
  Tomás León con el SCTR vencido el 31/10 y dos días tareados con autorización; vacante de 2 técnicos para P8 con tres
  candidatos (Raúl Mendoza contratado desde el 01/12, uno por entrevistar, uno descartado) y otra de ayudante; Kevin
  Ramos salió el 30/09 por renuncia (liquidación 2,250.00); planilla de octubre cerrada y la de noviembre en cálculo.

## Supuestos sin confirmar

- La planilla es ilustrativa: sin asignación familiar, quinta categoría, gratificaciones, CTS mensual ni vacaciones;
  las reglas reales las valida el contador. La tasa AFP es una sola (11.84 %), sin distinguir la AFP ni la comisión.
- El costo real por hora usa 192 h al mes para todos.
- El archivo PLAME tiene un formato aproximado (no es el de importación oficial de SUNAT).
- Una vacante de proyecto asigna al contratado al 100 % desde su ingreso.
- La salida desactiva el usuario si lo tiene; los demás pendientes (partidas, requerimientos, cuentas, fondos) se
  muestran pero no se traspasan desde aquí.
- Se paga a todos los que tienen contrato el mes completo (sin prorrateo por ingreso o salida a mitad de mes).

## Atajos tomados

- Estados como texto; sin acciones registradas ni motor de estados; las pantallas consultan tablas directo (`DB`).
- «Descartar» actualiza la tabla desde la pantalla, sin pasar por el servicio.
- La experiencia sale de las asignaciones; no hay CV ni proyectos anteriores a Techquk.
- Los asientos son un CSV, no entran a contabilidad.
- El seeder pone estados de candidatos directo en la tabla.

## Dónde está en el código

`app/Modules/Erp/Personal/` (`Services/PersonasService.php`, `Services/PersonalService.php::tarear`, páginas
`Filament/Pages/Personas.php`, `Vacantes.php`, `Planilla.php`, vistas `resources/views/demanda`, `boleta`,
`diferencia`, migración `2026_11_21_200001_personas_planilla_y_salida.php`); datos en `DemoSeeder::personas()`.
