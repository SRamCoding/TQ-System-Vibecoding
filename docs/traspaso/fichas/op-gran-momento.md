# Ficha · Gran momento y ambientación de las fechas (opcional, planes 1.4d, 1.4d2 y 1.4k)

- **Estado:** aprobada (MV-009)
- **Versión:** v1
- **Tramo de la ruta:** opcionales (MV-014)
- **Aprobada:** 2026-11-20 · etiqueta `aprobado/op-gran-momento-v1`
- **Fuentes del diseño:** `docs/planes/activos/2026-10-22-interfaz-gran-momento.md` (pasos 1 a 5 ya construidos en el
  repo original: calendario laboral, gran momento con sus datos, reglas y capa en pantalla, y la variante del
  nacimiento; aquí, los pasos 6 y 7).

## Qué hace

En las fechas con logo propio, el sistema acompaña sin estorbar. Ya existía el «gran momento» (una vez al año por
persona, 30 segundos con su escena y un versículo, con plazo para no perderlo). Se suman: la **ambientación de
pantalla completa** de 10 segundos que no tapa ni bloquea (el clic la atraviesa): nieve en Navidad (24 y 25 de
diciembre) y fuegos lejanos en Año Nuevo (31 de diciembre y 1 de enero), una vez cada pocas horas por persona; y las
**tres variantes** que faltaban de la escena: la estrella sobre los cerros (Navidad), fuegos sobre la ciudad y el
primer amanecer (Año Nuevo). El año elige la variante como ya hacía el mecanismo.

## Decisiones de Sebastián

- **MV-014** (ficha) · Entra al MVP.
- **MV-009** (general) · Aprobada sin revisión, como todas hasta terminar el sistema.

## Pantallas y navegación

- **Todas las pantallas, en las fechas:** la capa de 10 s (se desvanece en 1.5 s). Para verla otro día, en la consola
  del navegador: `tqAmbientacionFestiva('navidad')` o `tqAmbientacionFestiva('anio_nuevo')`.
- **Gran momento:** las escenas nuevas se ven en la vista previa de fechas del modo desarrollo, como la del nacimiento.

## Operaciones

Ninguna nueva: es interfaz. Parámetro nuevo `interfaz.ambientacion_minutos` (entero, 240 por omisión): cada cuántos
minutos, como mínimo, se repite la ambientación para una persona en un navegador.

## Reglas y números

- Navidad: 70 copos de 3 a 8 px que caen en 4 a 9 s. Año Nuevo: 9 fuegos (uno por segundo) de 120 a 240 px, punteados,
  en cinco colores; cada uno se abre y se apaga en 2.2 s, sin destellos.
- Con «reducir animaciones» (del equipo o de Mis preferencias) no se muestra la ambientación y las escenas quedan quietas.

## Supuestos sin confirmar

- Las fechas de la ambientación son fijas (24-25/12 y 31/12-1/1); Semana Santa, Fiestas Patrias y aniversario (1.4d2)
  siguen sin escena propia.
- Las escenas nuevas son dibujos del agente, sin la aprobación de estilo que pedía el plan.
- Lo ya visto se recuerda en el navegador (localStorage), no en la base.

## Atajos tomados

- La ambientación decide la fecha en la vista, sin pasar por el calendario festivo del núcleo.
- Sin la actualización de `interfaz.md`, el DBML ni el mapa que pedía el paso 8 del plan.

## Dónde está en el código

`app/Modules/Nucleo/resources/views/filament/gran-momento/navidad-estrella.blade.php`, `anio-nuevo-fuegos.blade.php`,
`anio-nuevo-amanecer.blade.php` (registradas en `NucleoServiceProvider::registrarGranMomento()`),
`filament/ambientacion-festiva.blade.php` (al final del cuerpo en `AppPanelProvider`), el parámetro en
`NucleoServiceProvider`.
