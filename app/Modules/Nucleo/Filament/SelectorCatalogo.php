<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament;

use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Excepciones\ErrorDeNegocio;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;
use App\Modules\Nucleo\Services\CatalogosService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Exceptions\Halt;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * El selector de catálogo común (plan 2.0, D1 y D4): lo dibuja `AccionFilament` para `Campo::elementoDe` y
 * `Campo::listaDe`, y lo usa tal cual un formulario que no sale de una acción (un alta en pasos). Ninguna pantalla arma
 * un `Select` de catálogo a mano: así todas reciben la búsqueda en la base, el valor actual desactivado y el «+».
 *
 * - Al abrir muestra los primeros 50 activos por código (en un catálogo corto, todos); al escribir busca en la base
 *   cada vez (`CatalogosService::buscar`), así que un valor agregado en otra pestaña aparece al buscarlo.
 * - Lo que el registro ya tiene se ve aunque esté desactivado, con «(desactivado)», y el formulario se guarda.
 * - Ubigeos, por niveles (D1): al abrir, los departamentos; elegir uno muestra sus provincias, y elegir una, sus
 *   distritos. Se guarda el distrito: un departamento o una provincia elegidos no son un valor válido. Al escribir busca
 *   directo en los distritos, con su ruta.
 */
final class SelectorCatalogo
{
    /** Valores de nivel en el selector de ubigeos: «dep:14» (Lambayeque), «prov:1401» (Chiclayo). */
    private const string DEPARTAMENTO = 'dep:';

    private const string PROVINCIA = 'prov:';

    public static function de(Catalogo $catalogo, string $nombre, bool $varios = false): Select
    {
        $porNiveles = $catalogo === Catalogo::Ubigeos && ! $varios;

        $selector = Select::make($nombre)->searchable()->multiple($varios)
            ->options(fn (mixed $state): array => self::alAbrir($catalogo, $state, $varios))
            ->getSearchResultsUsing(fn (string $search): array => app(CatalogosService::class)->buscar($catalogo, $search))
            ->getOptionLabelUsing(fn (mixed $value): ?string => self::etiqueta($catalogo, $value))
            ->getOptionLabelsUsing(fn (array $values): array => array_filter(
                array_combine($values, array_map(fn (mixed $v): ?string => self::etiqueta($catalogo, $v), $values)),
                fn (?string $etiqueta): bool => $etiqueta !== null,
            ));

        self::conAgregar($selector, $catalogo);

        if ($porNiveles) {
            // Elegir un nivel vuelve a dibujar las opciones con el siguiente.
            $selector->live()->validationMessages([
                'integer' => 'Elige un distrito: abre la lista, entra al departamento y a la provincia, o escribe su nombre.',
            ]);
        }

        return $selector;
    }

    /**
     * El «+» junto al selector (plan 2.0, D2): solo para quien puede agregar a ese catálogo (el permiso de su
     * `CrearElementoCatalogo`, que ya distingue los de dinero) y nunca en uno cerrado (Rubros). Pide el nombre y sugiere
     * el código (editable; a mano en los de código oficial), avisa si ya hay uno igual o parecido sin bloquear, crea por
     * la acción registrada (queda en la bitácora) y deja elegido lo creado.
     */
    private static function conAgregar(Select $selector, Catalogo $catalogo): void
    {
        if ($catalogo->esCerrado()) {
            return;
        }

        $clave = self::claveAgregar($catalogo);

        $selector
            ->createOptionForm(fn (): array => self::formularioAgregar($catalogo, $clave))
            ->createOptionUsing(function (array $data) use ($clave): int {
                $usuario = Filament::auth()->user();

                try {
                    return (int) app(EjecutorAcciones::class)->ejecutar(app(RegistroAcciones::class)->obtener($clave), $data, $usuario instanceof User ? $usuario : throw new AuthorizationException);
                } catch (ErrorDeNegocio $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();
                } catch (AuthorizationException) {
                    Notification::make()->danger()->title('Ya no tienes permiso para agregar a esta lista.')->send();
                } catch (ValidationException $e) {
                    Notification::make()->danger()->title((string) (collect($e->errors())->flatten()->first() ?? 'Revisa lo que escribiste.'))->send();
                }

                // El panel sigue abierto con lo escrito, para corregirlo.
                throw new Halt;
            })
            ->createOptionAction(fn (Action $action): Action => $action
                ->label("Agregar a {$catalogo->nombre()}")
                ->modalHeading("Agregar a {$catalogo->nombre()}")
                ->modalSubmitActionLabel('Agregar y elegir')
                ->visible(fn (): bool => self::puedeAgregar($clave)));
    }

    /** La clave de la acción registrada que agrega a ese catálogo: `nucleo.<catálogo>.crear`. */
    public static function claveAgregar(Catalogo $catalogo): string
    {
        return 'nucleo.'.str_replace('_', '-', $catalogo->value).'.crear';
    }

    public static function puedeAgregar(string $clave): bool
    {
        $permiso = app(RegistroAcciones::class)->obtener($clave)->permiso();
        $usuario = Filament::auth()->user();

        return $permiso === null || ($usuario?->can($permiso) ?? false);
    }

    /**
     * Los campos de la acción, con el nombre primero: al escribirlo sugiere el código y avisa de los parecidos.
     *
     * @return list<Field>
     */
    private static function formularioAgregar(Catalogo $catalogo, string $clave): array
    {
        $principal = $catalogo === Catalogo::Ubigeos ? 'distrito' : $catalogo->columnasBusqueda()[0];
        $campos = [];

        foreach (app(RegistroAcciones::class)->obtener($clave)->campos() as $campo) {
            $control = AccionFilament::campo($clave, $campo->nombre);

            if ($campo->nombre === $principal && $control instanceof TextInput) {
                $control->live(onBlur: true)
                    // Sugiere el código mientras la persona no lo haya cambiado a mano.
                    ->afterStateUpdated(function (?string $state, ?string $old, Get $get, Set $set) use ($catalogo): void {
                        $actual = (string) $get('codigo');

                        if ($actual === '' || $actual === $catalogo->codigoDesdeNombre((string) $old)) {
                            $set('codigo', $catalogo->codigoDesdeNombre((string) $state) ?? $actual);
                        }
                    })
                    ->hint(fn (?string $state): ?string => self::avisoParecidos($catalogo, (string) $state))
                    ->hintColor('warning');
                array_unshift($campos, $control);

                continue;
            }

            if ($campo->nombre === 'codigo' && $catalogo->codigoDesdeNombre('a') !== null) {
                $control->helperText('Se sugiere desde el nombre; puedes cambiarlo. No cambia después.');
            }

            $campos[] = $control;
        }

        return $campos;
    }

    /** «Ya hay uno igual o parecido: «Feria». Revisa la lista antes de agregar.»; nulo si no hay. */
    public static function avisoParecidos(Catalogo $catalogo, string $nombre): ?string
    {
        $parecidos = app(CatalogosService::class)->parecidos($catalogo, $nombre);

        return $parecidos === [] ? null : 'Ya hay uno igual o parecido: «'.implode('», «', $parecidos).'». Revisa la lista antes de agregar.';
    }

    /**
     * Lo que muestra al abrir sin escribir.
     *
     * @return array<int|string, string>
     */
    public static function alAbrir(Catalogo $catalogo, mixed $state, bool $varios = false): array
    {
        $catalogos = app(CatalogosService::class);

        if ($catalogo === Catalogo::Ubigeos && ! $varios) {
            return self::niveles($state);
        }

        $opciones = $catalogos->buscar($catalogo, '');

        foreach (self::ids($state) as $id) {
            if (! isset($opciones[$id]) && ($etiqueta = $catalogos->etiquetaDe($catalogo, $id)) !== null) {
                $opciones[$id] = $etiqueta;
            }
        }

        return $opciones;
    }

    /** La etiqueta de un valor elegido: un elemento (aunque esté desactivado) o, en ubigeos, un nivel. */
    public static function etiqueta(Catalogo $catalogo, mixed $valor): ?string
    {
        if (is_numeric($valor)) {
            return app(CatalogosService::class)->etiquetaDe($catalogo, (int) $valor);
        }

        if ($catalogo !== Catalogo::Ubigeos || ! is_string($valor)) {
            return null;
        }

        $catalogos = app(CatalogosService::class);

        if (str_starts_with($valor, self::PROVINCIA)) {
            $provincia = substr($valor, strlen(self::PROVINCIA));
            $departamento = substr($provincia, 0, 2);
            $nombre = $catalogos->provincias($departamento)[$provincia] ?? null;

            return $nombre === null ? null : "{$nombre}, ".($catalogos->departamentos()[$departamento] ?? '').' ›';
        }

        if (str_starts_with($valor, self::DEPARTAMENTO)) {
            $nombre = $catalogos->departamentos()[substr($valor, strlen(self::DEPARTAMENTO))] ?? null;

            return $nombre === null ? null : "{$nombre} ›";
        }

        return null;
    }

    /**
     * Ubigeos al abrir: departamentos; con uno elegido, sus provincias; con una provincia, sus distritos. Cada nivel
     * trae cómo volver al anterior.
     *
     * @return array<int|string, string>
     */
    private static function niveles(mixed $state): array
    {
        $catalogos = app(CatalogosService::class);

        if (is_string($state) && str_starts_with($state, self::PROVINCIA)) {
            $provincia = substr($state, strlen(self::PROVINCIA));
            $departamento = substr($provincia, 0, 2);

            // Con `+`, no `...`: el spread renumera las llaves enteras y se perderían los ids de los distritos.
            return [
                self::DEPARTAMENTO.$departamento => '‹ Provincias de '.($catalogos->departamentos()[$departamento] ?? $departamento),
                $state => self::etiqueta(Catalogo::Ubigeos, $state) ?? $state,
            ] + $catalogos->distritos($provincia);
        }

        if (is_string($state) && str_starts_with($state, self::DEPARTAMENTO) && strlen($state) > strlen(self::DEPARTAMENTO)) {
            $departamento = substr($state, strlen(self::DEPARTAMENTO));
            $opciones = [self::DEPARTAMENTO => '‹ Todos los departamentos', $state => self::etiqueta(Catalogo::Ubigeos, $state) ?? $state];

            foreach ($catalogos->provincias($departamento) as $codigo => $nombre) {
                $opciones[self::PROVINCIA.$codigo] = "{$nombre} ›";
            }

            return $opciones;
        }

        $opciones = [];

        foreach ($catalogos->departamentos() as $codigo => $nombre) {
            $opciones[self::DEPARTAMENTO.$codigo] = "{$nombre} ›";
        }

        // El distrito elegido, para que se vea su etiqueta.
        if (is_numeric($state) && ($etiqueta = $catalogos->etiquetaDe(Catalogo::Ubigeos, (int) $state)) !== null) {
            $opciones[(int) $state] = $etiqueta;
        }

        return $opciones;
    }

    /** @return list<int> */
    private static function ids(mixed $state): array
    {
        $valores = is_array($state) ? $state : [$state];

        return array_values(array_map(intval(...), array_filter($valores, is_numeric(...))));
    }
}
