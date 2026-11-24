# Conectar la landing de Techquk con la entrada de leads

Observación 9.3. Cada formulario de contacto de la página web crea un lead en Oportunidades («Por calificar») con el
canal «Página web» y avisa en Mien. La landing sigue igual y **además** envía sus datos aquí (su correo de aviso puede
seguir).

## Dirección

`POST https://<dirección del sistema>/entrada/leads`

## Campos

| Campo | Obligatorio | Qué es |
|---|---|---|
| `clave` (o la cabecera `X-Techquk-Clave`) | sí | La clave compartida: el valor de `TECHQUK_ENTRADA_LEADS_CLAVE` del `.env` del sistema |
| `nombre` | sí | Nombre de la persona (hasta 150) |
| `telefono` o `correo` | uno de los dos | Para contactarla |
| `organizacion`, `cargo`, `ruc` (11 dígitos), `mensaje` | no | Lo demás del formulario |
| `utm_source` | no | De dónde vino (instagram, facebook, google, tiktok, linkedin, whatsapp, correo…): decide el canal; sin él, «Página web» |
| `campania` o `utm_campaign` | no | El código de la campaña (el de su formulario y QR) o su nombre: el lead queda en esa campaña |
| `sitio_web` | **vacío siempre** | La casilla trampa: va oculta en el formulario; si llega llena, se rechaza (robot) |
| `volver` | no | Una dirección de la landing a la que volver después (`?enviado=1` o `?error=1`); sin ella, responde JSON |

Los `utm_*` se copian del enlace por el que llegó la persona (`?utm_source=instagram&utm_campaign=…`) a campos ocultos
del formulario.

## Respuestas

`201` creado · `403` clave incorrecta · `422` datos incompletos o casilla trampa llena · `429` demasiados envíos
seguidos (10 por minuto por dirección, `TECHQUK_ENTRADA_LEADS_POR_MINUTO`). Lo rechazado no crea nada.

## Dos formas de conectarla

1. **Formulario HTML simple:** `<form method="post" action="https://…/entrada/leads">` con los campos de arriba (la
   clave como campo oculto) y `volver` apuntando a la página de gracias de la landing.
2. **Desde el servidor de la landing o con JavaScript (`fetch`):** enviar los mismos campos con la cabecera
   `X-Techquk-Clave` y `Accept: application/json`. Para `fetch` desde el navegador, poner en
   `TECHQUK_ENTRADA_LEADS_ORIGENES` la dirección de la landing.

**Mejor desde el servidor de la landing:** así la clave no queda a la vista en el HTML de la página. Si la landing es
solo HTML, la clave se ve en el código de la página (atajo del MVP: la casilla trampa y el límite por minuto frenan a
los robots, no a alguien decidido).

## Probarla

En el sistema, Campañas → Canales → «Probar la entrada de la página web» abre un formulario de ejemplo que envía como lo
haría la landing. En un equipo local, sin `TECHQUK_ENTRADA_LEADS_CLAVE`, vale la clave `clave-local-de-prueba`.
