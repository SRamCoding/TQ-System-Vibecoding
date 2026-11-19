---
name: nuevo-documento
description: Construye un tipo de documento del v4 con sus estados, transiciones, efectos, validaciones y su escenario de aceptación como prueba. Usar para requerimientos, órdenes, recepciones, despachos, partes de horas, valorizaciones, garantías, etc.
---

# Construir un tipo de documento

Las reglas completas están en `docs/convenciones/codigo.md` §Estados y efectos. El mecanismo es el de la tarea
1.5; su documento de prueba (`tests/Modules/Nucleo/Fixtures/DocumentoPrueba.php` y `DefinicionPrueba.php`) es el
ejemplo de todo lo que sigue.

1. **Lee su ficha** en `docs/diseno/v4/parte-6-fichas-documentos.md`: numeración, campos, estados,
   transiciones (desde, acción, hacia, quién) y efectos. Lee su flujo en `parte-4-flujo-NN.md` y sus
   escenarios en `parte-8-criterios-aceptacion.md`.
2. **Tablas** con `/nueva-migracion`: la cabecera con `estado_id` (FK a `estados`, con índice) y `version`; las
   líneas con la FK a su cabecera. El tipo va en el morph map.
3. **Modelos:** la cabecera implementa `DocumentoConEstados` con el trait `TieneEstados`; sus líneas usan
   `ParteDeDocumento` (`documentoConEstados()`). Declara `camposDerivados()` (lo que otro documento mantiene al
   día, como la cantidad recibida) y `ambitoDePermisos()` (su proyecto o su unidad). `estado_id` no va en
   `$fillable`.
4. **Definición:** una clase que extiende `DefinicionDocumento` (`clave()` como `compras.orden`, `nombre()`,
   `articulo()` si es «la», estados y transiciones). Regístrala en el provider con `RegistroDocumentos::registrar()`.
   - Anular va a un estado `esCancelacion` (final, no editable) y siempre con `requiereMotivo`.
   - «Por aprobar» no es editable; la transición que espera firmas lleva `requiereAprobacion` y no tiene permiso.
   - Si una acción tiene dos destinos según los datos, son dos transiciones y el servicio del módulo elige.
   - La transición que necesita más datos (registrar una adenda) lleva `accionPropia` y su acción va con
     `/nueva-accion`; las demás tienen su `AccionTransicion` sola.
   - Reglas de la ficha que impiden una transición («no se aprueba si excede») van como `CondicionTransicion`.
   - Considera el patrón «Reabrir» de `codigo.md`.
5. **Un efecto, una clase** en `Efectos/`, con nombre que diga qué hace (`DespachoEjecutaCostoEnPartida`),
   registrada con `RegistroDocumentos::efecto()`. Interno (`Efecto`): `aplicar()` devuelve lo que hizo (montos
   como texto decimal) y `revertir()` deshace con esos datos. Externo (`EfectoExterno`, correos y avisos):
   `preparar()`, `ejecutar()` y `revertir()`. Los efectos sobre costos pasan por `ImputacionService` del núcleo.
6. **Documentos que dependen de otros:** si este documento nace de otro (la recepción de una orden), bloquea la
   fila del origen al crearse y registra una `RevisionAntesDeAnular` para el tipo del origen, para que no se anule
   mientras este viva.
7. **Validaciones** de la ficha («no se despacha sin stock») en el servicio o como condición de la transición,
   cada una con su prueba que intenta romperla.
8. **Motivos:** cada transición con motivo tiene su lista, creada por el sincronizador con «Otro». Los motivos
   iniciales de la ficha los siembra el módulo.
9. **Pantalla:** la barra de estados sale de `EstadosService::barraDeEstados()` y el historial de `historialDe()`;
   el color del estado siempre con su nombre.
10. **Escenario de aceptación:** convierte los escenarios del v4 de este flujo en pruebas Pest con los
    números exactos. El documento no está terminado hasta que pasan.
11. `/cerrar-tarea` (con `composer check:tarea`); `revisor-arquitectura` y `composer check:tarea`, al cerrar la
    entrega; `composer check` completo, al cerrar la etapa.
