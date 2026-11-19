---
name: nueva-accion
description: Registra una acción de usuario (registrar recepción, aprobar orden, subir XML...) como clase con campos tipados, permiso y ejecución por servicio, usable desde Filament, pruebas y una API futura.
---

# Registrar una acción

1. **Una clase por acción** en `app/Modules/<Grupo>/<Modulo>/Acciones/`, que extiende
   `App\Modules\Nucleo\RegistroAcciones\Accion`:
   - `clave()`: `modulo.accion` en minúsculas con guiones («compras.aprobar-orden»). Estable: la usan
     la API futura y las pruebas;
   - `nombre()`: nombre visible en español («Aprobar orden»);
   - `campos()`: `Campo::texto|entero|decimal|fecha|booleano|opcion(...)`, con `->opcional()` y
     `->conReglas(...)`. Montos con `Campo::decimal` (llegan como string), nunca float. Un elemento de
     catálogo con `Campo::elementoDe('x_id', 'Etiqueta', Catalogo::…)`. Toda regla nueva necesita su
     mensaje en `lang/es/validation.php`;
   - `permiso()`: permiso de spatie, o `null` si es una preferencia propia (permisos por ámbito: 1.4);
   - `ejecutar(array $datos, User $usuario)`: recibe los datos ya validados y convertidos y **solo**
     llama al servicio del módulo (inyectado por constructor);
   - si edita un documento con `version`, un campo `Campo::entero('version', 'Versión')->deControl()`.
2. **Sin lógica de negocio en la acción ni en Filament**: las reglas viven en el servicio, que lanza
   excepciones que extienden `ErrorDeNegocio` (la pantalla las muestra como aviso).
3. **Registro:** el provider del módulo llama a
   `$this->app->make(RegistroAcciones::class)->registrar(MiAccion::class)` en `boot()`. Si la misma
   operación se repite sobre varias cosas, una instancia por cada una con su clave
   (`registrar($this->app->make(MiAccion::class, ['catalogo' => $c]))`; ejemplo: `Nucleo/Acciones/Catalogos/`).
4. **Ejecución:** siempre por `EjecutorAcciones::ejecutar($claveOAccion, $datos, $usuario)`. Rechaza
   usuario de sistema, inactivos y sin permiso (`AccionRechazada`), valida (`ValidationException`) y
   ejecuta a nombre del usuario (auditoría).
5. **Filament:** `AccionFilament::desde($accion, fn (Modelo $record) => [/* datos fijos */], fn ($resultado) => /* aviso */)`
   arma el formulario y la invoca. Los campos `deControl()` se llenan al abrir con
   `->fillForm(fn (Modelo $record) => ['version' => $record->version])`. Ejemplo:
   `Nucleo/Filament/Resources/Parametros/ParametroResource.php`. El permiso lo crea `PermisosSeeder` solo.
6. **Pruebas sin interfaz** (ejemplos: `tests/Modules/Nucleo/CambiarParametroTest.php`,
   `PreferenciasBarraModulosTest.php`): que el
   módulo la registra, camino feliz, cada rechazo (sistema, inactivo, permiso, validación, regla de
   negocio) y el efecto esperado en otros módulos cuando lo hay.
7. Si la acción mueve dinero, nunca se ejecuta sin una persona (la IA o una API pueden proponerla,
   no confirmarla).
