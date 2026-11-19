<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Plan 1.4, paso 1: la bitácora y spatie guardaban el nombre completo de la clase. Desde el morph map guardan
 * su alias. La lista está escrita aquí, no leída del mapa: la migración debe dar lo mismo aunque el mapa cambie.
 */
return new class extends Migration
{
    private const ALIAS = [
        'App\\Models\\User' => 'user',
        'App\\Modules\\Nucleo\\Models\\AuditoriaCambio' => 'auditoria_cambio',
        'App\\Modules\\Nucleo\\Models\\Cubso' => 'cubso',
        'App\\Modules\\Nucleo\\Models\\FormaPago' => 'forma_pago',
        'App\\Modules\\Nucleo\\Models\\LineaSolucion' => 'linea_solucion',
        'App\\Modules\\Nucleo\\Models\\Moneda' => 'moneda',
        'App\\Modules\\Nucleo\\Models\\Parametro' => 'parametro',
        'App\\Modules\\Nucleo\\Models\\Party' => 'party',
        'App\\Modules\\Nucleo\\Models\\PartyContacto' => 'party_contacto',
        'App\\Modules\\Nucleo\\Models\\PartyCuentaBancaria' => 'party_cuenta_bancaria',
        'App\\Modules\\Nucleo\\Models\\PartyRol' => 'party_rol',
        'App\\Modules\\Nucleo\\Models\\PartySede' => 'party_sede',
        'App\\Modules\\Nucleo\\Models\\RolParty' => 'rol_party',
        'App\\Modules\\Nucleo\\Models\\TipoCambio' => 'tipo_cambio',
        'App\\Modules\\Nucleo\\Models\\TipoDocumento' => 'tipo_documento',
        'App\\Modules\\Nucleo\\Models\\TipoDocumentoIdentidad' => 'tipo_documento_identidad',
        'App\\Modules\\Nucleo\\Models\\TipoPenalidad' => 'tipo_penalidad',
        'App\\Modules\\Nucleo\\Models\\TipoTrabajo' => 'tipo_trabajo',
        'App\\Modules\\Nucleo\\Models\\Ubigeo' => 'ubigeo',
        'App\\Modules\\Nucleo\\Models\\UnidadMedida' => 'unidad_medida',
    ];

    private const COLUMNAS = [
        'auditoria_cambios' => 'auditable_type',
        'model_has_roles' => 'model_type',
        'model_has_permissions' => 'model_type',
    ];

    public function up(): void
    {
        $this->reemplazar(self::ALIAS);
    }

    public function down(): void
    {
        $this->reemplazar(array_flip(self::ALIAS));
    }

    /**
     * @param  array<string, string>  $cambios
     */
    private function reemplazar(array $cambios): void
    {
        foreach (self::COLUMNAS as $tabla => $columna) {
            foreach ($cambios as $antes => $despues) {
                DB::table($tabla)->where($columna, $antes)->update([$columna => $despues]);
            }
        }
    }
};
