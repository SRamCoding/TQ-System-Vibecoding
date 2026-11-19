# v4 · Flujo 9 · Las personas: de la necesidad a la salida

> Extracto de la parte 4 de la especificación v4. Fichas de sus documentos: `parte-6-fichas-documentos.md`. Escenarios de prueba: `parte-8-criterios-aceptacion.md`.

```text
Flujo 9 · Las personas: de la necesidad a la salida
Con la decisión de llevar la planilla dentro del ERP, Recursos Humanos pasa a ser un módulo completo.
Su columna vertebral ya estaba diseñada en el anexo 02-A del paquete de agosto: nueve procesos que
van desde prever cuánta gente hará falta hasta que alguien se va de la empresa. El anexo tiene una
observación que este flujo toma como principio: casi todo lo de RRHH se podría hacer con una hoja de
cálculo disciplinada; lo que solo un sistema puede hacer es detener algo a tiempo, en dos puntos: la
sobreasignación y la habilitación de campo vencida. AJUSTE SET 2026
|  1  |  Se prevé la demanda con 90 días
|  Gerencia de RRHH · Coordinación de Recursos
|  Los proyectos en ejecución y las licitaciones que se preparan en el CRM dicen cuánta gente hará
|  falta, por especialidad y por semana. Cruzado con las asignaciones vigentes, aparece la brecha
|  antes de que la obra empiece.
|  Demanda de personal · próximos 90 días
|  Especialidad  |  Necesarios  |  Disponibles  |  Brecha
|  Técnico CCTV  |  6  |  4  |  −2  |  Iniciar selección
|  Técnico de redes  |  3  |  3  |  0
|  Especialista de software  |  2  |  1  |  −1  |  Revisar banco
|  Sale de los proyectos en ejecución y de las licitaciones en preparación del CRM, cruzados con las asignaciones vigentes.
Planificación de la demanda. La brecha aparece con 90 días de anticipación, no el día que la obra empieza.
|  2  |  Se selecciona y se contrata
|  Selección · RRHH
|  Si la planta no alcanza, se busca primero en el banco de candidatos y, si no hay, se hace
|  convocatoria externa con precalificación técnica. Al contratar se registra el contrato laboral con
|  su modalidad (indeterminado, plazo fijo, por obra) y su vigencia. Para el personal eventual, el
|  contrato se enlaza al proyecto que lo originó.
|  3  |  Se habilita para campo
|  RRHH · Seguridad
|  Antes de trabajar en obra, cada persona necesita su examen médico ocupacional, su SCTR
|  vigente para ese proyecto, la inducción de seguridad y su equipo de protección. Si algo falta o
|  venció, el sistema bloquea su asignación a obra y su tareo hasta regularizarlo, o hasta que
|  alguien con autoridad lo autorice con motivo.
|  J. Chero · Técnico eventual  |  Habilitado para PRY-012
|  Datos  |  Contrato  |  Habilitación de campo  |  Asignaciones  |  Competencias  |  Historial
|  Requisito  |  Estado  |  Vence  |  CONTRATO
|  Examen médico ocupacional  |  apto  |  mar 2027  |  Por obra · PRY-2026-012 · 1 al 30 set
|  SCTR vigente para PRY-012  |  vigente  |  30 set
|  L. VERA · AYUDANTE
|  Inducción de seguridad  |  hecha  |  —  |  SCTR vencido: no puede tarearse en obra hasta
|  regularizarlo
|  Equipo de protección entregado  |  entregado  |  —
|  Del examen médico solo se ve el resultado. El detalle es dato de
|  salud y lo ve únicamente RRHH.
Habilitación de campo. Es uno de los dos puntos donde el sistema detiene algo en vez de solo registrarlo.
El examen médico es un dato de salud, y la ley peruana de protección de datos personales lo trata
como sensible. En el sistema solo se ve el resultado (apto o no apto); el detalle lo ve únicamente
RRHH, y nunca se envía a ningún servicio externo.
|  4  |  Se asigna y se tarea
|  Coordinación de Recursos · Jefatura de Proyectos
|  Es el flujo 3: asignación con dedicación y vigencia, alerta de sobreasignación, tareo semanal por
|  partida. Ambos controles del anexo (sobreasignación y habilitación) actúan aquí.
|  5  |  Se calcula la planilla del mes
|  RRHH · revisa el contador
|  La planilla sale de los contratos, las asistencias y el tareo. Al cerrarla se generan las boletas de
|  pago, el archivo para la declaración mensual ante SUNAT (PLAME) y los asientos de planilla por
|  plantilla. La diferencia entre el costo real de las horas y su costo estándar queda visible por
|  proyecto.
|  Planilla · setiembre 2026  |  en cálculo  |  Generar boletas  |  Exportar a PLAME  |  Cerrar planilla
|  Trabajador  |  Régimen  |  Remuneración  |  Aporte ONP 13 %  |  Neto  |  EsSalud 9 %  |  Costo empresa
|  J. Chero  |  ONP  |  2,400.00  |  312.00  |  2,088.00  |  216.00  |  2,616.00
|  L. Vera  |  ONP  |  1,500.00  |  195.00  |  1,305.00  |  135.00  |  1,635.00
|  Esp. CCTV  |  ONP  |  3,500.00  |  455.00  |  3,045.00  |  315.00  |  3,815.00
|  Ilustrativo y simplificado: la planilla real incluye AFP, asignación familiar, renta de quinta categoría, gratificaciones, CTS y vacaciones.
|  Las reglas vigentes las valida el contador. Al cerrar se generan las boletas, el archivo para SUNAT y los asientos de planilla.
Planilla del periodo. Sale de los contratos y del tareo; lo que el ERP produce son las boletas, el archivo de declaración y los
asientos.
|  6  |  Se actualizan competencias y certificaciones
|  RRHH
|  Cada proyecto terminado suma experiencia a las personas que participaron, y las certificaciones
|  tienen su vencimiento con alerta. Varias convocatorias exigen personal certificado vigente, así
|  que esta información también es comercial. Las brechas de competencia alimentan un plan de
|  desarrollo.
|  7  |  Se gestiona la salida
|  RRHH · Jefatura directa
|  Cuando alguien se va, el sistema arma la lista de lo que hay que resolver: liberar sus
|  asignaciones, revocar su acceso al sistema, traspasar lo que tenía a cargo (sus partidas como
|  responsable, sus requerimientos abiertos, sus clientes en el CRM, sus fondos por rendir),
|  documentar lo que sabía y calcular su liquidación. La empresa conserva el conocimiento y la
|  cartera.
|  CUANDO LAS COSAS NO SALEN COMO SE ESPERA
|  Habilitación vencida en plena obra. El tareo de esa persona se bloquea desde el día del
|  vencimiento; la jefatura recibe la alerta 15 días antes.
|  Personal eventual por pocos días. Se registra con un contrato por obra corto; la habilitación
|  de campo sigue siendo obligatoria.
|  Reglas de planilla que cambian (tasas, topes, conceptos). Son tablas configurables con
|  vigencia, no código; las mantiene RRHH con el visto bueno del contador.
|  POR VALIDAR Quién hace hoy RRHH y cómo se calcula hoy la planilla. Confirmar si existe
|  una persona de RRHH, qué programa o Excel se usa, y cómo se contrata al personal eventual.
```
