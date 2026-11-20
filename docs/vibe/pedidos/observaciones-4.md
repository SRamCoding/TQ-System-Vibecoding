# Observaciones de Sebastián, cuarta tanda

Pedido de Sebastián (20 de noviembre de 2026). Cada observación es una decisión suya: regístrala en
`docs/traspaso/decisiones.md` con su alcance y anótala en la ficha que corresponda (sube su versión). Hazlas en
orden, un commit por observación. Si algo choca con lo ya construido, pregúntame antes.

## 1. Sin elegir qué módulos ver: cada uno ve lo que le toca

**Qué quiero.** Que **no exista ninguna opción para que la persona elija qué módulos o cajas ver** (ocultar o mostrar
cajas, elegir módulos en sus preferencias o en el inicio, o cualquier ajuste parecido). Cada persona ve **siempre y
solo** lo que su rol le permite; nada más, nada menos.

- Quita esas opciones de las preferencias, del inicio, de la barra lateral y de donde aparezcan, y lo que guardaban.
- **Se queda** el cambio de **orden** de las cajas dentro de la barra de cada proceso (decidido antes): reordenar no
  es elegir qué se ve.
- Lo que ve cada rol lo define quien administra, en Sistema (usuarios, roles y permisos), no cada persona.

**Alcance:** cruza módulos.

## 2. La barra lateral: solo expandir o comprimir

**Qué quiero.** Que **no exista la opción de cambiar el ancho** de la barra lateral (arrastrando su borde o con un
ajuste en las preferencias). Solo dos estados:

- **Expandida:** con los nombres de los módulos, a un ancho fijo.
- **Comprimida:** solo los íconos.

Se cambia con un botón (y con su atajo de teclado si ya existe), con una transición corta, y el sistema recuerda el
último estado de cada persona. Quita el ajuste de ancho de las preferencias y lo que guardaba. Lo mismo para las barras
laterales internas de los módulos, si alguna permite cambiar su ancho.

**Alcance:** cruza módulos.

## 3. Las barras laterales siempre entran: sin la barra de desplazamiento

**Qué pasa hoy.** Al hacer zoom, dentro de las barras laterales aparece una barra de desplazamiento para subir y bajar.
Estorba.

**Qué quiero.** Una combinación de dos cosas:

1. **Que entren siempre, ajustándose al alto.** La barra lateral mide el alto disponible y ajusta sus elementos:
   - cada elemento y el espacio entre ellos tienen un tamaño **máximo** (el de hoy) y un **mínimo**, y entre los dos se
     ajustan solos según el alto de la ventana (por ejemplo, con `clamp()` y unidades del alto de la ventana);
   - **primero se quita el aire** (separadores, márgenes), **después se achica** el ícono, nunca por debajo de unos
     **28 px** (que se pueda presionar con comodidad);
   - expandida, también se reduce un poco el tamaño de la letra de los nombres.

   Con unos 13 elementos (Mien, las 11 cajas y la configuración), con el mínimo de 28 px entran en unos 450 px de alto:
   una laptop común con zoom al 200 %.
2. **Respaldo para el caso extremo** (zoom de 300 % o una ventana muy baja), cuando ni con el mínimo entra: se puede
   desplazar con la rueda del mouse o el touchpad **sin que se dibuje la barra de desplazamiento**, con un **degradado
   suave abajo** (y arriba, cuando ya se desplazó) que avisa que hay más.

Vale para la barra lateral principal y para **todas las barras laterales internas** (Configuración, Listas…). Con
teclado, el elemento enfocado siempre se trae a la vista.

**Alcance:** cruza módulos.

## 4. La estrella de Mien en la barra lateral abre un resumen, no la página

**Qué quiero.** Solo para Mien: al presionar la **estrella de la barra lateral principal**, **no** se va a la página de
Mien. Se abre un **globo pegado a la estrella**, a su derecha, con una puntita que la señala, con **Mien resumido**.

- **Para qué sirve:** responder en un vistazo la pregunta del día a día «¿qué más me toca?», sin salir de la pantalla
  en la que se está.
- **Lo que muestra:** Mien resumido, en texto, pero **ordenado y con estilo** (agrupado, con títulos chicos, números
  y colores de estado). **No se puede olvidar ningún pendiente:** todo lo que Mien tiene pendiente para esa persona
  aparece en el resumen, aunque sea en una línea. Si son muchos, se agrupan por tipo con su cantidad («Aprobaciones
  · 3») y se pueden desplegar, pero ninguno se omite.
- **Abrir y cerrar:** clic en la estrella abre el globo; clic afuera, Esc o otro clic en la estrella lo cierran.
- **Ctrl + clic** (o Cmd + clic en Mac, o clic con la rueda) en la estrella abre **Mien completo en una pestaña
  nueva**, como cualquier enlace.
- Mientras el globo está abierto, la estrella se ve activa. En la barra comprimida y en la expandida funciona igual.
- Con «reducir movimiento», el globo aparece sin animación.

- **Desde el globo solo se mira y se salta:** cada pendiente es un enlace que lleva al lugar donde se resuelve (por
  ejemplo, «OC-045 espera tu aprobación» lleva a la orden). **No** se aprueba ni se rechaza desde el globo: lo que
  mueve dinero se firma viendo el documento completo. Al saltar, el globo se cierra.

**Alcance:** ficha (Mien y la barra lateral principal).

## 5. La caja Techquk lleva el logo de Techquk, con su nombre

**Qué quiero.** La caja **Techquk** (franja Estratégicos) usa como ícono **el logo completo de Techquk: el símbolo y
su nombre TECHQUK**, el mismo del encabezado.

- **En el color de la franja Estratégicos** (azul noche en modo claro, `#6F9BFF` en modo oscuro), en un solo color
  como los demás íconos: no en los colores de marca.
- **El nombre TECHQUK** va en Audiowide, en mayúsculas y con el espaciado del logo, y con el símbolo achatado al 85 %
  del alto, como en el encabezado. Sale del mismo lugar que el logo del encabezado (`logo.blade.php`), para que si
  cambia la marca cambie en los dos lados.
- **Dentro de la caja, el logo con su nombre reemplaza al texto «Techquk»** de debajo del ícono, para que el nombre no
  se repita dos veces.
- **En la barra lateral principal:** expandida, el símbolo y el nombre TECHQUK; comprimida, solo el símbolo (no hay
  espacio para el nombre).
- Que se vea del mismo peso visual que las cajas vecinas, y bien en modo claro y oscuro y en las cuatro estaciones.

**Alcance:** ficha.

## 6. Ícono de «Gerencia de proyectos»

**Qué quiero.** La caja **Gerencia de proyectos** (franja Misionales) lleva un ícono propio: **un reloj, una moneda
con el signo de dólar y unas barras que suben**. Son las tres cosas que se controlan en un proyecto: el plazo, el
costo y el avance. Imagen de referencia adjunta: `icono-gerencia-de-proyectos.png`; guárdala en
`docs/vibe/referencias/`.

**Cómo se ve en la referencia:**

- **El reloj:** un círculo grande con dos agujas (una hacia arriba, otra hacia abajo a la derecha). El círculo no se
  cierra abajo: se interrumpe donde están la moneda y las barras.
- **La moneda:** un círculo lleno abajo a la izquierda, delante del reloj, con el signo «$» en blanco.
- **Las barras:** tres barras que suben de izquierda a derecha, abajo a la derecha, delante del reloj.
- La referencia trae tres colores (gris, verde, morado): aquí manda el estilo del sistema.

**Cómo debe quedar:** igual que los demás íconos propios (`icono-conseguir-el-negocio.md`): solo el dibujo, sin texto;
de línea, trazo 1,9, **un solo color**, `currentColor` (turquesa, el de Misionales), lienzo de 24 × 24, el mismo en la
caja, la barra lateral y la portada; revisado en claro y oscuro y en las cuatro estaciones. La moneda puede ir
**rellena** con el «$» calado (en el color del fondo), para que se distinga del reloj; si a ese tamaño se empasta,
déjala de línea.

SVG de partida:

```svg
<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.9" stroke="currentColor"
     stroke-linecap="round" stroke-linejoin="round">
  <!-- reloj, abierto abajo -->
  <path d="M8.2 17.6A7.5 7.5 0 1 1 19.4 13" />
  <path d="M12 6.2V10.5l2.6 1.8" />
  <!-- moneda -->
  <circle cx="6.5" cy="17.5" r="4" />
  <path d="M7.7 15.8c-.4-.4-.9-.5-1.4-.5-.8 0-1.3.4-1.3 1s.6.8 1.3 1 1.4.4 1.4 1.1-.6 1.1-1.4 1.1c-.6 0-1.1-.2-1.5-.6M6.4 14.7v.6M6.4 19.6v.6" />
  <!-- barras que suben -->
  <path d="M13 21v-2M16.5 21v-4M20 21v-6.5" />
</svg>
```

**Alcance:** ficha.
