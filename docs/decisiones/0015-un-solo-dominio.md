# 0015 — Una sola aplicación y un solo dominio para el CRM y el ERP

- **Estado:** propuesta
- **Fecha:** 2026-10-15
- **Decide:** responsable técnico, con el visto bueno de Gerencia

## Contexto

Al ordenar el análisis surgió la pregunta de si el CRM y el ERP deberían tener cada uno su dominio, o
combinarse «tipo Okta», es decir, con un inicio de sesión único para dos aplicaciones separadas.

[ADR 0001](0001-monolito-modular.md) ya fija una aplicación, una base y un despliegue, y la etapa 0
dejó lista la navegación de los dos modos (CRM y ERP) dentro de esa aplicación. Los dos lados
comparten datos todo el tiempo: el cliente del CRM es la misma persona o empresa en el ERP (`parties`),
y la oportunidad ganada se convierte en contrato en el traspaso.

## Decisión

Una sola aplicación con un solo inicio de sesión. El CRM y el ERP se separan por dentro: cada uno es
un modo con su propio menú, y cada persona ve solo lo que su rol permite.

Si se quiere que se sientan como dos productos, pueden tener dos direcciones (por ejemplo
`crm.techquk…` y `erp.techquk…`) que apunten a la misma aplicación y abran cada una en su modo. Es un
detalle de presentación que se decide al configurar el dominio, junto con el pendiente «Acceso
externo» de `pendientes.md`.

## Consecuencias

- Un cliente es un solo registro para los dos lados; el traspaso a contrato no copia datos entre
  sistemas.
- Un solo despliegue, una sola base y un solo lugar donde administrar usuarios y permisos.
- La separación entre CRM y ERP depende de los permisos (M10) y de los límites entre módulos
  verificados por `tests/Arch`, no de la infraestructura.

## Alternativas descartadas

- **Dos aplicaciones con inicio de sesión único (tipo Okta):** obliga a sincronizar clientes entre dos
  bases, a mantener dos despliegues y a montar un servicio de identidad aparte. Tiene sentido cuando
  hay aplicaciones de distintos proveedores o equipos separados; no para una empresa de unas veintidós
  personas con un solo desarrollador.
- **Dos aplicaciones con inicios de sesión separados:** lo mismo, y además cada persona maneja dos
  contraseñas.

## Qué falta para aceptarla

La confirmación de Sebastián como responsable técnico y, si se quieren dos direcciones, el nombre del
dominio de la empresa.
