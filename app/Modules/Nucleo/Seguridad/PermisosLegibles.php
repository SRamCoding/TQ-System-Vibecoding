<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Seguridad;

use App\Modules\Nucleo\Navegacion\AreaModulo;
use Illuminate\Support\Str;

/**
 * Cómo se llama cada permiso en pantalla (plan 1.4c, D4). Solo presentación: el permiso sigue siendo su clave. Los
 * módulos dan el nombre de los suyos en RegistroPermisos (plan 2.20a, N8); si no, salen con un nombre derivado de su
 * clave. Los de negocio se agrupan por área y módulo («CRM · Cuentas»); los demás, por su prefijo: legibles, y nunca
 * escondidos.
 */
final class PermisosLegibles
{
    public const GRUPO_SENSIBLES = 'Datos sensibles';

    /** En este orden se muestran; los grupos de otros módulos van después. */
    private const array GRUPOS = [
        'campos' => self::GRUPO_SENSIBLES,
        'usuarios' => 'Usuarios y roles',
        'roles' => 'Usuarios y roles',
        'organigrama' => 'Organigrama',
        'parties' => 'Personas y empresas',
        'aprobaciones' => 'Aprobaciones',
        'alertas' => 'Alertas',
        'catalogos' => 'Catálogos y parámetros',
        'ubigeos' => 'Catálogos y parámetros',
        'parametros' => 'Catálogos y parámetros',
        'tipos_cambio' => 'Catálogos y parámetros',
        'estados' => 'Documentos',
        'motivos' => 'Documentos',
        'ia' => 'Inteligencia artificial',
        'configuracion' => 'Sistema',
        'salud' => 'Sistema',
    ];

    private const array NOMBRES = [
        'alertas.configurar' => 'Configurar alertas',
        'aprobaciones.autoaprobar' => 'Aprobar sus propios documentos',
        'aprobaciones.configurar' => 'Configurar reglas de aprobación',
        'aprobaciones.revisar' => 'Revisar autoaprobaciones',
        'catalogos.editar' => 'Editar catálogos',
        'catalogos.editar-sensibles' => 'Editar catálogos sensibles',
        'configuracion.inicial' => 'Usar el asistente de configuración',
        'ia.autorizar' => 'Autorizar lo que sale a un servicio externo',
        'ia.configurar' => 'Configurar proveedores y modelos',
        'nucleo.estados.editar-presentacion' => 'Editar cómo se muestran los estados',
        'nucleo.motivos.editar' => 'Editar motivos',
        'organigrama.gestionar' => 'Gestionar el organigrama',
        'parametros.editar' => 'Editar parámetros',
        'parametros.editar-sensibles' => 'Editar parámetros sensibles',
        'parties.asignar-roles' => 'Asignar tipos de persona y empresa',
        'parties.borrar' => 'Borrar fichas',
        'parties.cambiar-rubro' => 'Cambiar el rubro',
        'parties.crear' => 'Crear fichas',
        'parties.desactivar' => 'Desactivar fichas',
        'parties.editar' => 'Editar fichas',
        'parties.editar-tributarios' => 'Editar datos tributarios',
        'parties.gestionar-contactos' => 'Gestionar contactos',
        'parties.gestionar-cuentas-bancarias' => 'Gestionar cuentas bancarias',
        'parties.gestionar-redes' => 'Gestionar redes',
        'parties.gestionar-sedes' => 'Gestionar sedes',
        'roles.gestionar' => 'Gestionar roles y permisos',
        'tipos_cambio.registrar' => 'Registrar tipos de cambio',
        'ubigeos.actualizar' => 'Actualizar ubigeos',
        'usuarios.gestionar' => 'Gestionar usuarios',
    ];

    public static function etiqueta(string $permiso): string
    {
        $declarado = app(RegistroPermisos::class)->nombre($permiso) ?? self::NOMBRES[$permiso] ?? null;

        if ($declarado !== null) {
            return $declarado;
        }

        foreach (GrupoSensible::cases() as $grupo) {
            if ($grupo->permiso() === $permiso) {
                return 'Ver '.Str::lcfirst($grupo->etiqueta());
            }
        }

        return Str::ucfirst(str_replace(['-', '_'], ' ', Str::afterLast($permiso, '.')));
    }

    public static function grupo(string $permiso): string
    {
        $area = AreaModulo::tryFrom(Str::before($permiso, '.'));

        // Plan 2.20a, N7: los de negocio llevan su área delante (`crm.cuentas.ver`) y se agrupan por área y módulo.
        if (in_array($area, [AreaModulo::Crm, AreaModulo::Erp], true) && substr_count($permiso, '.') >= 2) {
            $modulo = Str::before(Str::after($permiso, '.'), '.');

            return app(RegistroPermisos::class)->grupo($area->value.'.'.$modulo)
                ?? $area->etiqueta().' · '.Str::ucfirst(str_replace(['-', '_'], ' ', $modulo));
        }

        $prefijo = self::prefijo($permiso);

        return self::GRUPOS[$prefijo] ?? Str::ucfirst(str_replace(['-', '_'], ' ', $prefijo));
    }

    /**
     * Los permisos por grupo, en el orden de pantalla y con el nombre legible, de la A a la Z dentro de cada grupo.
     *
     * @param  array<int, string>  $permisos  id => clave
     * @return list<array{nombre: string, permisos: array<int, string>}> `permisos`: id => nombre legible
     */
    public static function agrupar(array $permisos): array
    {
        $orden = array_flip(array_values(array_unique(self::GRUPOS)));
        $grupos = [];

        foreach ($permisos as $id => $permiso) {
            $grupos[self::grupo($permiso)][$id] = self::etiqueta($permiso);
        }

        uksort($grupos, fn (string $a, string $b): int => [$orden[$a] ?? PHP_INT_MAX, $a] <=> [$orden[$b] ?? PHP_INT_MAX, $b]);

        return array_map(function (string $nombre, array $delGrupo): array {
            asort($delGrupo, SORT_LOCALE_STRING);

            return ['nombre' => $nombre, 'permisos' => $delGrupo];
        }, array_keys($grupos), $grupos);
    }

    /** `nucleo.estados.editar-presentacion` es del grupo de `estados`: el prefijo del módulo no dice nada. */
    private static function prefijo(string $permiso): string
    {
        return Str::before(Str::after($permiso, 'nucleo.'), '.');
    }
}
