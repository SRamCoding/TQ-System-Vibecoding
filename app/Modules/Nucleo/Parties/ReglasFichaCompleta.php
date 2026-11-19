<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Parties;

use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Models\Party;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Qué le falta a una ficha para poder trabajar con ella (plan 1.4m, D2: buenas prácticas de datos maestros, delegadas
 * por el desarrollador). Único lugar de esas reglas: la portada de Personas y empresas las cuenta y filtra con ellas.
 * Cada regla es una condición sobre una consulta de fichas: la cumplen las que **les falta** algo. Solo miran fichas
 * activas (eso lo pone quien consulta) y los tipos vigentes hoy. Los códigos que nombran (cliente, proveedor, entidad,
 * colaborador) son del sistema: el catálogo no deja desactivarlos ni borrarlos.
 */
final class ReglasFichaCompleta
{
    /**
     * Clave => [qué falta, para el aviso; la condición]. La de la cuenta bancaria solo con `$conDatosBancarios`: decir
     * qué proveedor tiene cuenta y cuál no es un dato bancario, que no ve cualquiera (como la pestaña de cuentas).
     *
     * @return array<string, array{0: string, 1: Closure(Builder<Party>, string): void}>
     */
    public static function todas(bool $conDatosBancarios = true): array
    {
        $sinContactoVigente = fn (Builder $q): Builder => $q->whereDoesntHave('contactos', fn (Builder $c): Builder => $c->scopes(['vigentes']));

        return [
            // Solo las fichas con algún tipo vigente que no sea colaborador (que tiene su regla): una ficha sin tipo (la de
            // una cuenta de usuario, por ejemplo) no se trabaja como cliente ni proveedor.
            'sin_medio_de_contacto' => ['sin teléfono ni correo', function (Builder $q, string $hoy): void {
                $q->whereHas('roles', fn (Builder $r): Builder => $r->scopes(['vigenteEl' => [$hoy]])
                    ->whereHas('rol', fn (Builder $t): Builder => $t->where('codigo', '<>', 'colaborador')->where('es_activo', true)));
                self::sinTelefonoNiCorreoPropios($q);
                $q->whereDoesntHave('contactos', fn (Builder $c): Builder => $c->scopes(['vigentes'])
                    ->where(fn (Builder $m): Builder => $m->whereNotNull('telefono')->where('telefono', '<>', '')
                        ->orWhere(fn (Builder $e): Builder => $e->whereNotNull('email')->where('email', '<>', ''))));
            }],
            'sin_direccion_fiscal' => ['con RUC y sin dirección fiscal', function (Builder $q): void {
                $q->whereHas('tipoDocumento', fn (Builder $t): Builder => $t->where('codigo', Catalogo::RUC))
                    ->where(fn (Builder $d): Builder => $d->whereNull('direccion_fiscal')->orWhere('direccion_fiscal', '')->orWhereNull('ubigeo_id'));
            }],
            ...($conDatosBancarios ? ['proveedor_sin_cuenta' => ['proveedores sin cuenta bancaria', function (Builder $q, string $hoy): void {
                $q->scopes(['conRolVigente' => ['proveedor', $hoy]])->whereDoesntHave('cuentasBancarias', fn (Builder $c): Builder => $c->where('es_activa', true));
            }]] : []),
            'proveedor_sin_contacto' => ['proveedores sin contacto', function (Builder $q, string $hoy) use ($sinContactoVigente): void {
                $sinContactoVigente($q->scopes(['conRolVigente' => ['proveedor', $hoy]]));
            }],
            'cliente_sin_contacto' => ['clientes sin contacto', function (Builder $q, string $hoy) use ($sinContactoVigente): void {
                $sinContactoVigente($q->scopes(['conRolVigente' => ['cliente', $hoy]]));
            }],
            'entidad_sin_contacto' => ['entidades sin contacto', function (Builder $q, string $hoy) use ($sinContactoVigente): void {
                $sinContactoVigente($q->scopes(['conRolVigente' => ['entidad', $hoy]]));
            }],
            'colaborador_sin_medio' => ['colaboradores sin teléfono ni correo propios', function (Builder $q, string $hoy): void {
                self::sinTelefonoNiCorreoPropios($q->scopes(['conRolVigente' => ['colaborador', $hoy]]));
            }],
        ];
    }

    /** @param  Builder<Party>  $q */
    private static function sinTelefonoNiCorreoPropios(Builder $q): void
    {
        $q->where(fn (Builder $t): Builder => $t->whereNull('telefono')->orWhere('telefono', ''))
            ->where(fn (Builder $e): Builder => $e->whereNull('email')->orWhere('email', ''));
    }
}
