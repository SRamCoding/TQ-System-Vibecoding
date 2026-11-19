# Monitoreo

Cuatro capas, cada una con su dueño. Para el MVP se implementa lo mínimo que protege; lo demás se suma con
el uso.

| Capa | Pregunta | Herramienta | Dueño | MVP |
|---|---|---|---|---|
| Servidor | ¿Disco, memoria, CPU? | La que use TI | TI | Lo que TI ya tenga |
| Disponibilidad | ¿Responde la app? ¿Corrió el programador? ¿El respaldo? | Healthchecks (y opcionalmente Uptime Kuma), en servidor propio | Desarrollo configura, TI recibe | **Sí** |
| Errores y rendimiento | ¿Qué falló y qué está lento? | Registros diarios de Laravel; Laravel Pulse | Desarrollo | **Sí** |
| Salud del negocio | ¿Salen las alertas? ¿Hay jobs fallidos, asientos fallidos, respaldos vencidos? ¿Cuánto gasta la IA? | Página «Salud del sistema» del núcleo | Desarrollo | **Sí** |

## Señales que emite la aplicación

- **Latido del programador** cada pocos minutos; si deja de llegar, Healthchecks avisa.
- **Resultado de cada respaldo**; si falla o no corre, aviso.
- **Jobs fallidos:** se guardan en `failed_jobs`; cualquier fallo avisa al responsable.
- **Página «Salud del sistema»:** última ejecución del programador y de cada alerta, jobs fallidos y en
  cola, último respaldo y última prueba de restauración, exportaciones contables fallidas, uso de cada
  proceso de IA frente a su tope.

## Registros

- Canal diario; los archivos se borran solos después del plazo que defina TI.
- Nunca contienen sueldos, datos médicos, contraseñas, tokens ni contenido de documentos.
- Si algún día se usa un servicio externo de errores, debe borrar datos personales antes de enviarlos.

## Pulse (plan 1.12)

En `/pulse`, solo con el permiso `salud.ver` (nace con el administrador): colas, consultas lentas, errores, pedidos
lentos, jobs lentos y llamadas lentas a servicios externos. **Sin datos personales:** no sabe quién hizo cada pedido
(sin uso por persona ni avatares de Gravatar); de los errores guarda la clase y el lugar, nunca el mensaje; de los
pedidos, la ruta sin la cadena de consulta; de las llamadas externas, solo el dominio. Apagados: servidores (la capa
es de TI y necesitaría `pulse:check` corriendo siempre) y caché. Guarda 7 días en la base: no borra al azar; cada noche `nucleo:archivar-pulse` pasa sus mediciones más
viejas al archivo histórico. Sus tablas (`pulse_*`) están en la
misma base. En las pruebas está apagado (`phpunit.xml`), salvo en `PulseTest`, que revisa que no guarde datos
personales.
