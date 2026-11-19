# v4 · Flujo 3 · Las horas de la gente

> Extracto de la parte 4 de la especificación v4. Fichas de sus documentos: `parte-6-fichas-documentos.md`. Escenarios de prueba: `parte-8-criterios-aceptacion.md`.

```text
Flujo 3 · Las horas de la gente
Sin este flujo, el costo de un proyecto está incompleto: se ve todo lo que se compró, pero no lo que se
pagó en personas. En Techquk el personal de obra puede ir de 4 a 42 personas según el proyecto, y
hoy sus horas probablemente no se registran en ningún sistema: Techquk no compró los módulos de
tareo ni de planillas de S10.  |  AJUSTE SET 2026
|  1  |  Se asigna a las personas
|  Coordinación de Recursos · Jefatura de Proyectos
|  Cada persona se asigna a proyectos con un porcentaje de dedicación y una vigencia. El tablero
|  marca la sobreasignación antes de que ocurra el conflicto: el problema de que la misma persona
|  quede comprometida en dos frentes.
|  Asignación · setiembre y octubre  |  Por persona  |  Por proyecto  |  + Asignar
|  Persona  |  Sem 37  |  Sem 38  |  Sem 39  |  Sem 40
|  R. Díaz · Jefe de  |  PRY-012 40 % · PRY-008 60  |  igual  |  igual  |  PRY-008 100
|  proyectos  |  %  |  %
|  Esp. CCTV  |  PRY-012 60 %  |  PRY-012 60 % · PRY-010 60 %  |  PRY-010 100  |  PRY-010 100
|  120 %  |  %  |  %
|  Esp. redes  |  PRY-012 100 %  |  PRY-012 50 %  |  sin asignar  |  sin asignar
|  Técnico eventual J.  |  PRY-012 100 %  |  PRY-012 100 %  |  fin de contrato —
|  Chero
Tablero de asignación. La sobreasignación se marca antes de que ocurra el conflicto.
|  2  |  Se registran las horas de obra
|  Jefe de cuadrilla o especialista, desde el celular
|  Cada semana, por persona, por día y por partida. Para el control se usa el costo hora estándar de
|  cada categoría, el mismo del análisis de precios unitarios, para que presupuesto y real se midan
|  igual. El costo real de cada persona sale de la planilla (flujo 9) y va a contabilidad. Solo se puede
|  tarear a quien tiene su habilitación de campo vigente.
|  Tareo · semana 37 · PRY-2026-012  |  abierto  |  Cerrar semana
|  Persona  |  Partida  |  Lu  |  Ma  |  Mi  |  Ju  |  Vi  |  Sá  |  Horas  |  Costo
|  J. Chero · técnico  |  PC-02.03 Mano de obra  |  8  |  8  |  4  |  20  |  360.00
|  J. Chero · técnico  |  PC-01.02 M. de obra cámaras  |  4  |  8  |  8  |  4  |  24  |  432.00
|  L. Vera · ayudante  |  PC-02.03 Mano de obra  |  8  |  8  |  8  |  24  |  288.00
|  Total semana  |  68  |  1,080.00
|  Costo hora estándar por categoría (técnico S/ 18, ayudante S/ 12): el mismo que usa el análisis de precios unitarios.
Tareo semanal por persona y partida. En el celular es una lista de personas con un número por día.
|  3  |  Se cierra la semana
|  Jefatura de Proyectos
|  Al cerrar, las horas pasan a comprometido y ejecutado de cada partida a la vez. Una semana
|  cerrada no se edita: se corrige con un ajuste que queda en el historial.
|  Qué queda registrado · 68 horas por S/ 1,080.00 · PC-02.03 Mano de obra: ejecutado S/ 648.00 · PC-01.02
|  Mano de obra cámaras: ejecutado S/ 432.00 (72 %)
|  4  |  Las horas de desarrollo
|  Desarrolladores · aprueba la Jefatura de Desarrollo
|  Es el mismo mecanismo con otra pantalla, pensada para registrar la semana en menos de un
|  minuto. Ambas escriben en la misma tabla de partes de horas, así que el presupuesto contra real
|  suma las dos. Los contratos híbridos son el 55.3 % del portafolio y sin estas horas su margen
|  está mal calculado.
|  Mis horas · semana 37  |  32 de 40 h  |  Enviar semana
|  Proyecto · partida  |  Lu  |  Ma  |  Mi  |  Ju  |  Vi  |  Total
|  PRY-2026-008 · Hospital · PC-05 Software de gestión  |  6  |  6  |  8  |  4  |  24
|  Interno · soporte del ERP (gasto directo)  |  2  |  2  |  4  |  8
|  + agregar fila (recuerda las de la semana pasada)
Horas de desarrollo. Misma entidad que el tareo de obra, otra pantalla. Tiene que tomar menos de un minuto por semana.
|  CUANDO LAS COSAS NO SALEN COMO SE ESPERA
|  Una persona sin SCTR vigente para ese proyecto. No se le puede tarear en obra; el
|  sistema lo avisa al asignarla.
|  Horas cargadas a la partida equivocada. Después del cierre, se corrigen con un ajuste de
|  reclasificación entre partidas, aprobado.
|  Diferencia entre costo estándar y costo real. Al cerrar la planilla del mes, la diferencia
|  entre lo que costaron de verdad las horas y su costo estándar se muestra por proyecto.
|  POR VALIDAR Confirmar con Gerencia si esa diferencia se reparte a las partidas o se queda
|  como ajuste del mes.
|  Horas internas sin proyecto. Se imputan a gasto directo con su centro de costo.
```
