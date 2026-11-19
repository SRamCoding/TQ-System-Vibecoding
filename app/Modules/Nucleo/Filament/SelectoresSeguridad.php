<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament;

use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Enums\TipoPersona;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\Puesto;
use App\Modules\Nucleo\Models\TipoDocumentoIdentidad;
use App\Modules\Nucleo\Models\UnidadOrganizativa;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Parties\DocumentoIdentidad;
use App\Modules\Nucleo\Seguridad\PermisosLegibles;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as Consulta;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Selectores de las pantallas de usuarios y organigrama (plan 1.4, D10). Solo presentación: la acción y su servicio
 * validan igual. Siempre ofrecen también el valor que el registro ya tiene, aunque hoy no se pudiera elegir
 * (AGENTS.md: si no, Filament no deja guardar).
 */
final class SelectoresSeguridad
{
    /** Personas que pueden tener cuenta y todavía no la tienen: DNI, CE o pasaporte, activas. Por buscador. */
    public static function personaSinCuenta(string $nombre): Select
    {
        return Select::make($nombre)
            ->searchable()
            ->getSearchResultsUsing(fn (string $search): array => mb_strlen(trim($search)) < 2 ? [] : Party::query()
                ->where('tipo', TipoPersona::Natural)
                ->where('es_activo', true)
                ->whereIn('tipo_documento_id', TipoDocumentoIdentidad::query()->whereIn('codigo', [Catalogo::DNI, DocumentoIdentidad::CARNE_EXTRANJERIA, DocumentoIdentidad::PASAPORTE])->select('id'))
                ->whereNotExists(fn (Consulta $q): Consulta => $q->from('users')
                    ->whereColumn('users.persona_tipo_id', 'parties.persona_tipo_id')
                    ->whereColumn('users.persona_numero', 'parties.persona_numero'))
                ->buscarNombreODocumento($search)
                ->orderBy('razon_social')
                ->limit(20)
                ->get()
                ->mapWithKeys(fn (Party $p): array => [$p->id => "{$p->razon_social} · {$p->numero_documento}"])
                ->all())
            ->getOptionLabelUsing(fn (mixed $value): ?string => is_numeric($value) ? Party::query()->whereKey((int) $value)->value('razon_social') : null);
    }

    /** Puestos activos de cualquier unidad, «Unidad › Puesto», más el que el usuario ya tiene. */
    public static function puesto(string $nombre): Select
    {
        return Select::make($nombre)
            ->searchable()
            ->options(fn (mixed $state): array => self::puestos(Puesto::query(), $state));
    }

    /** Puestos activos de la unidad de la fila, más el de jefatura que ya tiene. */
    public static function puestoDeLaUnidad(string $nombre): Select
    {
        return Select::make($nombre)
            ->searchable()
            ->options(fn (mixed $state, ?UnidadOrganizativa $record): array => $record === null ? [] : self::puestos(
                Puesto::query()->where('unidad_organizativa_id', $record->id),
                $state,
            ));
    }

    /** Unidades activas, más la ya elegida aunque se haya desactivado. */
    public static function unidad(string $nombre): Select
    {
        return Select::make($nombre)
            ->searchable()
            ->options(function (mixed $state): array {
                $opciones = UnidadOrganizativa::query()->where('es_activa', true)->orderBy('nombre')->pluck('nombre', 'id')->all();

                if (is_numeric($state) && ! array_key_exists((int) $state, $opciones)) {
                    $actual = UnidadOrganizativa::query()->find((int) $state);

                    if ($actual !== null) {
                        $opciones[$actual->id] = "{$actual->nombre} (desactivada)";
                    }
                }

                return $opciones;
            });
    }

    /** Cuentas activas de personas: responsables de un control compensatorio. */
    public static function usuarioActivo(string $nombre): Select
    {
        return Select::make($nombre)
            ->searchable()
            ->options(fn (): array => User::query()->where('es_activo', true)->where('es_sistema', false)->orderBy('name')->pluck('name', 'id')->all());
    }

    public static function rol(string $nombre): Select
    {
        return Select::make($nombre)
            ->searchable()
            ->options(fn (): array => Role::query()->orderBy('name')->pluck('name', 'id')->all());
    }

    /** Todos los permisos, por su nombre legible: «Grupo · Permiso». */
    public static function permiso(string $nombre): Select
    {
        return Select::make($nombre)
            ->searchable()
            ->options(function (): array {
                /** @var array<int, string> $permisos */
                $permisos = Permission::query()->pluck('name', 'id')->all();

                $opciones = [];

                foreach (PermisosLegibles::agrupar($permisos) as $grupo) {
                    $opciones += array_map(fn (string $etiqueta): string => "{$grupo['nombre']} · {$etiqueta}", $grupo['permisos']);
                }

                return $opciones;
            });
    }

    /**
     * @param  Builder<Puesto>  $consulta
     * @return array<int, string>
     */
    private static function puestos(Builder $consulta, mixed $actual): array
    {
        $etiqueta = fn (Puesto $p): string => "{$p->unidad->nombre} › {$p->nombre}";
        $opciones = (clone $consulta)->with('unidad')->where('es_activo', true)->get()
            ->sortBy($etiqueta)
            ->mapWithKeys(fn (Puesto $p): array => [$p->id => $etiqueta($p)])
            ->all();

        if (is_numeric($actual) && ! array_key_exists((int) $actual, $opciones)) {
            $puesto = (clone $consulta)->with('unidad')->find((int) $actual);

            if ($puesto !== null) {
                $opciones[$puesto->id] = $etiqueta($puesto).' (desactivado)';
            }
        }

        return $opciones;
    }
}
