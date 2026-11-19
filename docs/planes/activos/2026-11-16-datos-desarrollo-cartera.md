# Plan: datos de desarrollo con la cartera de nueve proyectos

- **Estado:** aprobado, listo para construir (se construye cuando Sebastián avise)
- **Responsable:** Sebastián (herramienta de desarrollo; no es de un área del ADR 0020)
- **Entrega:** `entrega/datos-desarrollo` (por ahora la rama es `plan/datos-desarrollo-cartera`)
- **Módulo(s):** ninguno nuevo; un seeder fuera de los módulos (`database/seeders/Desarrollo/`) que usa los servicios
  de `Crm/Cuentas`, `Crm/Oportunidades`, `Crm/Cotizaciones`, `Erp/Proyectos`, `Erp/Presupuestos` y del núcleo
- **Fase:** etapa 2 (tarea 2.62)
- **Creado:** 2026-11-16 · **Actualizado:** 2026-11-16

## Objetivo

Con `APP_ENV=local`, `php artisan db:seed` deja la base de desarrollo con una cartera de nueve proyectos rearmada a
partir de lo que el análisis de agosto verificó en S10, recorrida por el camino real del sistema: cuenta, oportunidad,
presupuesto, cotización, oportunidad ganada, proyecto, presupuesto aprobado en el proyecto y árbol de partidas, cada
paso en su fecha histórica. Fuera de `local` no hace nada.

## Contexto que leí

- `docs/analisis/archivo/2026-08-24-analisis-funcional/metodologia-y-resultados/02-Proyectos-y-Procesos.md` (v2.0):
  el portafolio de nueve contratos de S10 y lo verificado de él. No trae la lista de proyectos: solo cifras de conjunto
  y el cliente de P6 y P9.
- `tests/Costuras/PresupuestoDePrueba.php` y `ArbolDePrueba.php`; los fixtures `OportunidadesDePrueba`,
  `CotizacionesDePrueba` y `EmbudoDePrueba`; `AprobacionCotizacionTest` (la regla de margen bajo el mínimo):
  cómo se recorre el camino con los servicios.
- `database/seeders/DatabaseSeeder.php`: lo que ya se crea solo en local (usuario y gerente de desarrollo).
- `docs/decisiones/pendientes.md`: «Tipologías de proyecto» sigue pendiente, así que T1–T6 no se guardan en el
  proyecto (no hay columna): van en la línea de solución y en la descripción de la oportunidad.
- AGENTS.md: organigrama en espera (se trabaja con datos de prueba) y regla 10 (ver D2).

## La evidencia y cómo la cumple la cartera

| Hecho verificado en S10 (doc 02) | En la cartera |
|---|---|
| Nueve contratos en el portafolio | P1 a P9 |
| P6 y P9 son del Hospital Regional Docente Las Mercedes, con software, servidor y otros activos en un contrato | P6 y P9 con ese cliente, con frente de software y de hardware en el mismo presupuesto |
| El 55.3 % del portafolio combina hardware y software | P6, P7 y P9 suman S/ 5,208,677.48 de S/ 9,418,946.61: el 55.30 % |
| Una penalidad por mora de S/ 250,498.54, mayor que el valor íntegro de cinco de los nueve contratos | P2, P3, P4, P5 y P7 valen menos; P1 lleva la penalidad |

`[P]` Que la penalidad sea el tope del 10 % es una suposición de esta cartera; por eso P1 vale exactamente
S/ 2,504,985.40. `[P]` El 55.3 % se toma sobre el monto y no sobre la cantidad (5 de 9 daría 55.6 %). Los nombres de
los proyectos, los demás clientes, las partidas, los precios, los márgenes y las fechas son inventados y verosímiles.

## La cartera

Montos en soles. «GG / Ut.» son los porcentajes de gastos generales y utilidad del presupuesto; el total incluye el
IGV de 18 % y es el monto de la cotización aceptada y de la oportunidad ganada.

| # | Proyecto | Cliente | Tipo | Híbrido | GG / Ut. | Costo directo | Total | Ganada | Plazo | Estado |
|---|---|---|---|---|---|---:|---:|---|---|---|
| P1 | Videovigilancia y centro de monitoreo de Valle Norte | Municipalidad Provincial de Valle Norte | T1 | No | 10 / 8 | 1,799,041.51 | 2,504,985.40 | 2024-02-12 | 2024-03-04 → 2024-10-31 | Cerrado |
| P2 | Cableado estructurado de la I.E. N.° 10245 Los Algarrobos | Unidad de Gestión Educativa Local Valle Norte | T2 | No | 10 / 8 | 152,350.00 | 212,132.14 | 2024-05-20 | 2024-06-10 → 2024-09-08 | Cerrado |
| P3 | Suministro de 60 computadoras para la Municipalidad de Santa Rosa del Valle | Municipalidad Distrital de Santa Rosa del Valle | T5 | No | 5 / 6 | 106,570.00 | 139,585.39 | 2024-09-02 | 2024-09-16 → 2024-10-31 | Cerrado |
| P4 | Sala de videoconferencia del directorio de Agroindustrial Norteña | Agroindustrial Norteña S.A.C. | T3 | No | 10 / 35 | 63,000.00 | 107,793.00 | 2025-01-28 | 2025-02-10 → 2025-03-21 | Cerrado |
| P5 | Mantenimiento de CCTV de la Clínica del Norte (12 meses) | Clínica del Norte S.A.C. | T6 | No | 10 / 35 | 38,000.00 | 65,018.00 | 2025-12-15 | 2026-01-02 → 2026-12-31 | En ejecución |
| P6 | Sistema de información hospitalaria, software y servidor del Hospital Regional Docente Las Mercedes | Hospital Regional Docente Las Mercedes | T4 | Sí | 10 / 8 | 2,284,971.08 | 3,181,593.74 | 2025-08-18 | 2025-09-15 → 2026-12-15 | En ejecución |
| P7 | Telemetría de pozos de riego con plataforma web | Agrícola Pampa Verde S.A.C. | T4 | Sí | 10 / 35 | 133,060.00 | 227,665.66 | 2025-04-07 | 2025-04-21 → 2025-08-29 | Cerrado |
| P8 | Red de datos y enlaces de la sede del Gobierno Regional de Valle Norte | Gobierno Regional de Valle Norte | T2 | No | 10 / 8 | 848,000.00 | 1,180,755.20 | 2026-05-11 | 2026-06-01 → 2027-01-27 | En ejecución |
| P9 | Historia clínica electrónica, servidores y equipamiento del Hospital Regional Docente Las Mercedes | Hospital Regional Docente Las Mercedes | T4 | Sí | 10 / 8 | 1,292,314.05 | 1,799,418.08 | 2026-10-26 | 2026-11-23 → 2027-06-30 | En preparación |
Clientes (RUC inventados, válidos por su dígito verificador, serie `20699…`): las municipalidades y el Gobierno
Regional son entidades públicas de nivel local o regional; la UGEL y el hospital, regional. Agroindustrial Norteña,
Clínica del Norte y Agrícola Pampa Verde son privadas. Cada cliente tiene un contacto decisor inventado.

Jefes de proyecto: tres personas inventadas (Rosa Quispe, Diego Salazar y Andrea Vílchez, los del prototipo de la
2.61), con DNI de desarrollo `0000001x` y correo `@techquk.test`. El ejecutivo comercial es el usuario de desarrollo.

### Partidas de cada presupuesto

Sin APU (precio unitario directo). La última partida de P1, P6, P8 y P9 es la que ajusta el costo directo a la cifra
de la tabla.

**P1**
- 01.01 Suministro e instalación de cámara PTZ 4K · 36 NIU × 14,850.00  (CÁMARAS Y POSTES)
- 01.02 Suministro e instalación de cámara bullet 4 MP · 80 NIU × 2,480.00  (CÁMARAS Y POSTES)
- 01.03 Poste metálico de 9 m con cimentación · 52 NIU × 4,650.00  (CÁMARAS Y POSTES)
- 02.01 Enlace inalámbrico punto a punto · 30 NIU × 6,900.00  (ENLACES Y CABLEADO)
- 02.02 Tendido de fibra óptica monomodo · 8600 MTR × 11.40  (ENLACES Y CABLEADO)
- 03.01 Videowall de 3 x 3 pantallas · 1 GLB × 120,000.00  (CENTRO DE MONITOREO)
- 03.02 Servidores de grabación y videoanalítica · 4 NIU × 58,600.00  (CENTRO DE MONITOREO)
- 03.03 Mobiliario y climatización del centro de monitoreo · 1 GLB × 84,500.00  (CENTRO DE MONITOREO)
- 04.01 Canalización, buzones y obra civil · 1 GLB × 80,301.51  (OBRAS CIVILES)

**P2**
- 01.01 Punto de red Cat 6 certificado · 220 NIU × 245.00  (CABLEADO ESTRUCTURADO)
- 01.02 Gabinete de piso de 42 RU · 3 NIU × 5,850.00  (CABLEADO ESTRUCTURADO)
- 02.01 Switch de acceso de 48 puertos PoE · 5 NIU × 8,400.00  (EQUIPOS ACTIVOS)
- 02.02 Punto de acceso inalámbrico · 24 NIU × 1,350.00  (EQUIPOS ACTIVOS)
- 02.03 Configuración, certificación y pruebas · 1 GLB × 6,500.00  (EQUIPOS ACTIVOS)

**P3**
- 01.01 Computadora de escritorio con monitor de 24" · 60 NIU × 1,480.00  (EQUIPOS DE CÓMPUTO)
- 01.02 Impresora multifuncional láser · 8 NIU × 1,890.00  (EQUIPOS DE CÓMPUTO)
- 01.03 Entrega, instalación y acta · 1 GLB × 2,650.00  (EQUIPOS DE CÓMPUTO)

**P4**
- 01.01 Pantalla interactiva de 86" · 1 NIU × 21,400.00  (SALA DE DIRECTORIO)
- 01.02 Sistema de videoconferencia con cámara y barra de audio · 1 NIU × 18,900.00  (SALA DE DIRECTORIO)
- 01.03 Audio de sala con micrófonos de techo · 1 GLB × 14,600.00  (SALA DE DIRECTORIO)
- 01.04 Montaje, cableado y capacitación · 1 GLB × 8,100.00  (SALA DE DIRECTORIO)

**P5**
- 01.01 Mantenimiento preventivo trimestral de CCTV · 4 ZZ × 6,200.00  (MANTENIMIENTO)
- 01.02 Bolsa de atención correctiva · 120 HUR × 68.00  (MANTENIMIENTO)
- 01.03 Repuestos en consignación · 1 GLB × 5,040.00  (MANTENIMIENTO)

**P6**
- 01.01 Licencias del sistema de información hospitalaria · 1 GLB × 612,000.00  (SISTEMA DE INFORMACIÓN)
- 01.02 Implementación, parametrización y migración de datos · 4200 HUR × 95.00  (SISTEMA DE INFORMACIÓN)
- 01.03 Capacitación al personal asistencial · 1 GLB × 48,000.00  (SISTEMA DE INFORMACIÓN)
- 02.01 Servidor de rack con virtualización · 4 NIU × 86,500.00  (SERVIDORES Y ALMACENAMIENTO)
- 02.02 Almacenamiento SAN · 1 NIU × 245,000.00  (SERVIDORES Y ALMACENAMIENTO)
- 03.01 Computadoras para consultorios y estaciones de enfermería · 180 NIU × 2,950.00  (OTROS ACTIVOS)
- 03.02 Red de datos de los servicios · 1 GLB × 103,971.08  (OTROS ACTIVOS)

**P7**
- 01.01 Estación de telemetría con sensor de nivel y caudal · 14 NIU × 4,350.00  (EQUIPAMIENTO DE CAMPO)
- 01.02 Gateway LoRaWAN con energía solar · 3 NIU × 6,800.00  (EQUIPAMIENTO DE CAMPO)
- 02.01 Desarrollo de la plataforma de monitoreo · 520 HUR × 85.00  (PLATAFORMA WEB)
- 02.02 Servidor en la nube por 12 meses · 12 MON × 420.00  (PLATAFORMA WEB)
- 02.03 Capacitación y transferencia · 1 GLB × 2,520.00  (PLATAFORMA WEB)

**P8**
- 01.01 Punto de red Cat 6A certificado · 640 NIU × 265.00  (RED DE DATOS)
- 01.02 Switch de distribución de 48 puertos · 12 NIU × 12,800.00  (RED DE DATOS)
- 02.01 Enlace inalámbrico a sede descentralizada · 9 NIU × 24,600.00  (ENLACES INALÁMBRICOS)
- 03.01 Gabinetes, UPS y climatización · 1 GLB × 160,000.00  (CENTRO DE DATOS)
- 03.02 Firewall de siguiente generación · 2 NIU × 58,400.00  (CENTRO DE DATOS)
- 03.03 Instalación, certificación y pruebas · 1 GLB × 26,600.00  (CENTRO DE DATOS)

**P9**
- 01.01 Módulo de historia clínica electrónica · 1 GLB × 486,000.00  (HISTORIA CLÍNICA ELECTRÓNICA)
- 01.02 Integración con laboratorio e imágenes · 2600 HUR × 95.00  (HISTORIA CLÍNICA ELECTRÓNICA)
- 02.01 Servidor de contingencia y respaldo · 2 NIU × 92,000.00  (SERVIDORES)
- 03.01 Tabletas para visita médica · 120 NIU × 1,850.00  (EQUIPAMIENTO)
- 03.02 Impresoras de pulseras y etiquetas · 40 NIU × 2,600.00  (EQUIPAMIENTO)
- 03.03 Puesta en marcha y soporte del primer mes · 1 GLB × 49,314.05  (EQUIPAMIENTO)

## Recorrido de cada proyecto

Cada paso corre con la fecha del sistema puesta en su día histórico (`Carbon::setTestNow`, que se limpia al terminar,
también si algo falla), para que la numeración (`PRE-2024-…`, `COT-2024-…`, `PRY-2024-…`), la auditoría y el historial
de estados queden con sus fechas:

1. **35 días antes de ganar:** la cuenta (o la existente, si el RUC ya tiene una: el hospital la comparte), su
   contacto decisor por la acción `nucleo.parties.agregar-contacto`, la oportunidad y su calificación.
2. **21 días antes:** el presupuesto de la oportunidad con sus títulos y partidas.
3. **14 días antes:** la cotización enlazada al presupuesto; si su margen está bajo el mínimo (22 %), se solicita la
   aprobación y la firma el gerente de desarrollo; luego se emite y la oportunidad pasa a «Propuesta enviada».
4. **El día que se ganó:** se acepta la cotización (buena pro en las públicas, orden de servicio en las privadas), la
   oportunidad pasa a Negociación y se gana.
5. **Una semana después:** nace el proyecto (D6), se le trae el presupuesto aprobado y se arma el árbol desde el
   presupuesto, con el jefe como responsable; se aprueba (salvo P9) y el proyecto pasa a «En ejecución».
6. **El día del fin, si ya pasó:** el proyecto se cierra.

## Decisiones

- **D1.** (Sebastián, 2026-11-16) La cartera se rearma con la evidencia del doc 02 y lo que falta se inventa, en vez de
  esperar la exportación de S10.
- **D2.** (Sebastián, 2026-11-16) **Excepción a la regla 10 de AGENTS.md:** P6 y P9 llevan el nombre real del cliente,
  «Hospital Regional Docente Las Mercedes». Es una entidad pública y sus contratos son públicos en el SEACE. Todo lo
  demás de esos dos proyectos es inventado, incluido el RUC. Ninguna persona real. La regla misma no cambia.
- **D3.** Solo en `local`, llamado desde `DatabaseSeeder` después del usuario y el gerente de desarrollo, que recibe
  como parámetro. Si ya existe una oportunidad con el nombre de un proyecto de la cartera, ese proyecto se salta: se
  puede volver a correr sin duplicar.
- **D4.** Todo por los servicios de cada módulo (regla 7), no con factories ni escrituras directas: cada fila tiene su
  número, sus estados, su historial y su auditoría como si la hubiera hecho una persona. Las factories solo para las
  personas inventadas, como ya hace `DatabaseSeeder`.
- **D5.** Márgenes: obra pública con 10 % y 8 % (margen de 6.8 %); el suministro (P3), 5 % y 6 % (5.4 %); los privados,
  10 % y 35 % (24.1 %). Los públicos quedan bajo el mínimo y pasan por la aprobación real: el seeder crea, solo en
  local y si no existen, la unidad «Gerencia General (desarrollo)» con su puesto de jefatura ocupado por el gerente de
  desarrollo (por `OrganigramaService` y `SeguridadService`), y la regla «Cotización bajo el margen mínimo» que la hace
  firmar. La regla queda en la base de desarrollo, que hasta hoy no podía emitir ninguna cotización bajo el mínimo.
- **D6.** Contrato: todavía no existe (C10). Cada proyecto nace con `ProyectosService::crearDesdeContrato` y un id de
  contrato provisional (`900000 + n`), sin fila, como permite hoy la columna sin llave foránea.
- **D7.** Estados: P1, P2, P3, P4 y P7 cerrados; P5, P6 y P8 en ejecución; P9 en preparación (presupuesto aprobado,
  árbol en borrador), para que la portada muestre los tres estados.
- **D8.** Emitir congela el tipo de cambio del día. Para cada fecha de emisión sin tipo de cambio, el seeder registra
  uno manual de 3.7400 / 3.7500 (inventado, como en las pruebas); no toca los días que ya tienen uno.

## Confirmado por Sebastián (2026-11-16)

La tabla de la cartera y sus partidas, D5 (la unidad, el puesto y la regla de aprobación en la base de desarrollo) y D8
(los tipos de cambio inventados en fechas pasadas), tal como están arriba.

Si la 2.12 (numeración por dueño, ADR 0023) se fusiona antes de construir el seeder, los números de la cartera salen
con el formato nuevo («OP-2024-0001 · COT-01 v1», «PRY-2024-0001 · PRE-01 v1») y el presupuesto del proyecto nace por
copia al ganar: el recorrido del seeder sigue igual, solo cambia lo que la prueba espera ver.

## Pasos

- [ ] 1. El seeder `Database\Seeders\Desarrollo\CarteraDeProyectosSeeder` con los nueve proyectos como datos y el
  recorrido; su llamada desde `DatabaseSeeder` solo en local.
- [ ] 2. Prueba `tests/Feature/Desarrollo/CarteraDeProyectosTest.php`: nueve proyectos con sus estados; los totales
  de cada presupuesto y cotización; el 55.30 % híbrido; cinco bajo S/ 250,498.54; P1 en S/ 2,504,985.40; el cliente de
  P6 y P9; los números con el año de su fecha; correrlo dos veces no duplica; fuera de local no hace nada.
- [ ] 3. `composer check:tarea` en verde.

## Preparado para lo diferido

- Contratos (C10): cada proyecto trae en sus datos el plazo y, P1, la penalidad; el proyecto nace en un solo lugar
  del seeder, que pasa a crear el contrato primero y usar su id.
- Compras, almacén y horas (áreas 3 y 4): la cartera no tiene gasto ejecutado. Las nueve órdenes de compra y las 80
  importaciones de 2024 del doc 02 se suman cuando existan esos módulos.
- Tipologías: cuando se decida el pendiente, la columna del proyecto toma el «Tipo» de la tabla.
- Datos reales: si llega la exportación de S10 (tabla `Proyecto`), se reemplazan los datos del arreglo sin tocar el
  recorrido.

## Criterio de aceptación

- `CarteraDeProyectosTest` en verde.
- En local, tras `php artisan migrate:fresh --seed`: la portada de Proyectos muestra los nueve con su cliente, estado
  y costo presupuestado; Oportunidades, nueve ganadas por S/ 9,418,946.61; Cotizaciones, nueve aceptadas; «Mis
  aprobaciones» del gerente, las seis firmas.

## Bitácora

- 2026-11-16 — Sebastián pidió llenar la base de desarrollo con los proyectos de Techquk. El repositorio no tiene la
  lista (regla 10) y el doc 02 solo trae cifras de conjunto. Eligió rearmarlos (D1) con el nombre real del hospital
  (D2). Las cifras de la tabla se calcularon con el mismo redondeo del presupuesto (GG y utilidad sobre el costo
  directo, IGV sobre el subtotal, a dos decimales). Pidió solo el plan: la construcción espera su aviso.
- 2026-11-16 — Sebastián aprobó la tabla, D5 y D8.
