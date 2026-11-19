# Común · lo que comparten el CRM y el ERP

- **Versión:** 0.3.14 · **Etapa:** especificación (con partes ya construidas)
- **Desde:** 9 de octubre de 2026
- **Aprueba la línea base:** Gerencia General

## Qué cubre

Hay piezas que ningún área pide por su nombre, pero sin las cuales nada funciona: saber quién es
quién, qué puede ver y hacer cada persona, cómo cambia de estado un documento, quién aprueba qué,
cómo se numera, qué quedó registrado y qué alertas salen. Este bloque las reúne, junto con la
**frontera** entre el CRM y el ERP.

Es el único bloque que ya se está construyendo. Ver la nota al final.

## Dónde está escrito

1. **Especificación v4**, partes 1 (modelo mental), 5 (interfaz y roles) y 7 (transversal), en
   `docs/diseno/v4/`.
2. **Documento 06, sección 17** (`docs/diseno/06-Arquitectura-Nucleo-Datos.md`): imputación, IA,
   alertas, aprobaciones y permisos al detalle.
3. **Decisiones y convenciones** (`docs/decisiones/`, `docs/convenciones/`): cómo se construye.

## Los mecanismos y su estado

Los identificadores M1 a M13 son los del mapa de etapas (`docs/planes/mapa-etapas.md`), que también
dice en qué orden se construyen.

| Mecanismo | Qué hace | Estado |
|---|---|---|
| **M4** · Auditoría y registro de acciones | Quién creó y cambió cada registro, y cada acción de usuario con sus campos | Construido, con pruebas (tarea 1.1) |
| **M5** · Catálogos | Listas que la empresa mantiene: monedas, tipos de documento, ubigeos… editables desde pantalla | Construido, con pruebas (tareas 1.2 y 1.2b) |
| **M9** · Parámetros | Valores configurables, incluido el tipo de cambio automático de SUNAT | Construido, con pruebas (tareas 1.2 y 1.2b) |
| **M1** · Personas y roles | Un registro único por persona o empresa, con sus roles (cliente, proveedor, colaborador…) | Propuesto |
| **M10** · Seguridad | Usuarios, puestos, permisos por ámbito, restricciones por campo, separación de funciones | Propuesto |
| **M3** · Estados y transiciones | Los estados de cada documento como datos, y sus efectos como piezas separadas | Construido, con pruebas (tarea 1.5) |
| **M6** · Numeración | Correlativos sin huecos ni duplicados, aun con varias personas a la vez | Construido, con pruebas de concurrencia real (tarea 1.6) |
| **M2** · Aprobaciones | Reglas por monto y tipo, con umbrales fijados por Gerencia desde la pantalla | Construido, con prueba de concurrencia real (tarea 1.7). Faltan los montos reales U1, U2 y U3 (Gerencia) |
| **M7** · Adjuntos | Archivos asociados a cualquier documento | Construido, con pruebas (tarea 1.8) |
| **M8** · Notificaciones | Avisos a las personas, por el canal que elijan | Construido: campana y correo, con preferencias (tarea 1.8). Plantillas editables y anticipación con las alertas (1.10) |
| **M11** · Imputación | Que toda línea de costo vaya a una partida o al gasto directo con centro de costo | Construido el mecanismo (tarea 1.9): centros de costo e imputaciones con sus tres montos; las partidas se enchufan en la etapa 2. Motivos, en la 1.5 |
| **M13** · Alertas | Un catálogo único de alertas y vencimientos, sin avisos duplicados | Propuesto |
| **M12** · IA | El mecanismo para que la IA sugiera y una persona confirme, sin procesos activos | Construido, con pruebas (tarea 1.11): proveedores, modelos y precios; autorización de Gerencia con huella y tope mensual; llamadas medidas, respaldo y evidencia por campo; la sugerencia la resuelve el módulo; Configuración → IA. Sin procesos: llegan con sus módulos. Falta el monto del tope (Gerencia) y vincular la cuenta de Claude (TI) |

## La frontera: el traspaso a contrato

Es el momento en que una oportunidad ganada en el CRM se convierte en contrato en el ERP. Lo que ya se
sabe (cliente, cotización aceptada, condiciones, presupuesto enlazado, licitación de origen) pasa sin
volver a escribirse. Administración de Contratos completa lo que solo trae el contrato firmado: número,
fecha de firma, hitos, forma de pago, penalidad, adelantos y garantías. La IA puede sugerir esos datos
leyendo el PDF del contrato, para que una persona los confirme.

Está diseñada en la pantalla «Traspaso a contrato» del CRM (13 oct 2026) y en el flujo F01 de la v4.
Se valida dos veces: con el área comercial, que entrega, y con Administración de Contratos, que recibe.

Queda abierta una idea de septiembre que toca justo esta frontera: **dos carriles por contrato**, uno
completo y otro simple, para proyectos que no necesitan toda la estructura del ERP, con la decisión
registrada en cada caso.

## Cómo se entra al sistema

Hay una propuesta registrada: **una sola aplicación, con un solo inicio de sesión y un solo dominio**,
y el CRM y el ERP separados por dentro como dos modos con su propio menú. La razón, las alternativas y
lo que falta para decidirla están en [ADR 0015](../../decisiones/0015-un-solo-dominio.md).

## Nota: se construye antes de la línea base

Las piezas M4, M5 y M9 se construyeron entre el 9 y el 10 de octubre de 2026, cuando este bloque
estaba en 0.3. Se aceptó porque son mecanismos que cualquier versión del negocio necesita y que no
dependen de cómo trabaje un área en particular. La regla para lo que sigue es la misma: un mecanismo
común se puede construir sobre 0.3 si no depende de una decisión del negocio todavía abierta; si
depende, se espera a validarla (`AGENTS.md`, regla 12).
