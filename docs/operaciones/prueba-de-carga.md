# Prueba de carga

> Plan 1.12, D5. La corrida de verdad la hace **TI**, en un **servidor de prueba igual al de producción** (la imagen de la
> 1.12b: PHP con FPM, opcache, los dos workers). **Nunca contra producción.** Contra el servidor de desarrollo los
> tiempos no valen: no tiene opcache y atiende de a uno.

## Antes

1. El servidor de prueba con la misma versión que se va a desplegar, su base migrada y sembrada, y datos de prueba
   (nunca reales).
2. Una cuenta de prueba que pueda ver todas las pantallas del recorrido (un administrador de prueba).
3. Desde otra máquina de la misma red (no desde el servidor: compartiría su CPU), con PHP y la extensión `curl`.

## Correrla

```bash
php scripts/prueba-carga.php carga --base=https://servidor-de-prueba --correo=admin.prueba@… --usuarios=50 --duracion=300 --pausa=10
```

- La clave la pide al empezar, sin mostrarla (o la toma de `PRUEBA_CARGA_CLAVE`). Nunca como opción: queda en el
  historial de la terminal.
- `--usuarios`: cuántas personas a la vez. Correr **50, 100 y 200**, una tras otra, con unos minutos entre corridas.
- `--pausa`: segundos entre pantallas de cada persona (al azar, de la mitad al doble). 10 se parece a alguien que
  trabaja; con menos, la prueba es más dura que la realidad.
- `--duracion`: segundos de recorrido. 300 alcanza.

El script inicia **una** sesión como lo haría el navegador y todas las personas simuladas la comparten (el ingreso corta
los intentos seguidos desde una misma dirección: 50 ingresos de golpe fallaban). Cada una recorre al azar las pantallas
de `PANTALLAS` en el script (inicio, Mien, personas, usuarios, organigrama, roles, catálogos, parámetros, alertas, salud, mis
avisos, mis datos, mis preferencias). Los errores salen por código al final: `302` de `/configuracion` es normal (lleva
a su primera pantalla) y `403` es una pantalla que esa cuenta no tiene permiso de ver; cualquier `5xx` es del servidor.

## Leer los números

| Qué | Bien | Atender | Mal |
|---|---|---|---|
| **Errores** | 0 | Algunos sueltos | Muchos, o crecen con las personas: el servidor no da abasto |
| **p95** (el 95 % de los pedidos tarda menos que esto) | Menos de 500 ms | 500 ms a 1,5 s | Más de 1,5 s |
| **Mediana** | Menos de 200 ms | 200 a 500 ms | Más |

- Si el p95 crece poco de 50 a 100 personas y mucho de 100 a 200, el límite está entre medio: anotarlo.
- Durante la corrida, mirar el CPU y la memoria del servidor (la herramienta de TI) y la página **Salud del sistema**
  (Pulse: pedidos y consultas lentas).
- Guardar la salida de las tres corridas en el informe del despliegue, con la máquina (núcleos, memoria).

Estimación previa (2026-10-29, sin carga medida): con 4 núcleos, de 200 a 400 personas a la vez.

**Primera medición con la imagen de producción** (1.12b, 2026-10-30; en la computadora de desarrollo, con el compose de
producción, no en un servidor de TI): 50 personas, 90 s, pausa de 5 s (el doble de dura que la recomendada). 705
pedidos, **p95 de 198 ms**, mediana de 120 a 160 ms según la pantalla, sin errores del servidor. Orienta; no reemplaza
las corridas de 50, 100 y 200 en el servidor de prueba.

## Consultas por pantalla (para desarrollo)

```bash
php scripts/prueba-carga.php consultas
```

Dentro de la app, con una persona: por pantalla, cuántas consultas hace y cuánto tiempo de base gasta. Esto sí vale para
producción (una consulta repetida pesa igual con o sin opcache). Marca las que pasan de 40 consultas o 100 ms de base.
