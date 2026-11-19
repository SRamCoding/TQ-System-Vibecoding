<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models\Concerns;

use App\Modules\Nucleo\Enums\EventoAuditoria;
use App\Modules\Nucleo\Services\HistorialCambiosService;
use Illuminate\Database\Eloquent\Model;

/**
 * Bitácora campo por campo (M4) para tablas sensibles: parties, reglas de aprobación, presupuestos,
 * partidas, órdenes, contratos, parámetros, catálogos (docs/convenciones/base-de-datos.md). Guarda solo lo que
 * cambió; nunca las marcas de auditoría, los campos ocultos ni los de camposSinBitacora().
 *
 * save() y delete() corren en una transacción: el cambio y su bitácora entran juntos o no entra ninguno.
 * Cada modelo declara camposSinBitacora() (obligatorio): decidir qué no se guarda (sueldos, salud).
 *
 * Límite: un update() masivo por query builder no dispara eventos y no deja bitácora. En una tabla
 * sensible, el servicio guarda por modelo.
 *
 * @mixin Model
 */
trait RegistraCambios
{
    public static function bootRegistraCambios(): void
    {
        static::created(function (self $modelo): void {
            self::anotarCambio($modelo, EventoAuditoria::Creado, null, self::valoresAuditables($modelo, $modelo->getAttributes()));
        });

        static::updated(function (self $modelo): void {
            $nuevos = self::valoresAuditables($modelo, $modelo->getChanges());

            if ($nuevos !== []) {
                $anteriores = array_intersect_key($modelo->getRawOriginal(), $nuevos);
                self::anotarCambio($modelo, EventoAuditoria::Actualizado, $anteriores, $nuevos);
            }
        });

        static::deleted(function (self $modelo): void {
            self::anotarCambio($modelo, EventoAuditoria::Eliminado, self::valoresAuditables($modelo, $modelo->getRawOriginal()), null);
        });
    }

    /**
     * Campos que no entran a la bitácora además de los ocultos (sueldos, datos de salud). Obligatorio:
     * cada tabla sensible decide; [] si no hay ninguno.
     *
     * @return list<string>
     */
    abstract public function camposSinBitacora(): array;

    /** Lo da ConAuditoria: toda tabla sensible es tabla de negocio con auditoria(). */
    abstract public function autorSinContexto(): ?int;

    /**
     * @param  array<string, mixed>  $options
     */
    public function save(array $options = []): bool
    {
        return $this->getConnection()->transaction(fn (): bool => parent::save($options));
    }

    public function delete(): ?bool
    {
        return $this->getConnection()->transaction(fn (): ?bool => parent::delete());
    }

    /**
     * @param  array<string, mixed>  $valores
     * @return array<string, mixed>
     */
    private static function valoresAuditables(self $modelo, array $valores): array
    {
        $excluidos = array_merge(
            ['created_at', 'created_by', 'updated_at', 'updated_by'],
            $modelo->getHidden(),
            $modelo->camposSinBitacora(),
        );

        return array_diff_key($valores, array_flip($excluidos));
    }

    /**
     * @param  ?array<string, mixed>  $anteriores
     * @param  ?array<string, mixed>  $nuevos
     */
    private static function anotarCambio(self $modelo, EventoAuditoria $evento, ?array $anteriores, ?array $nuevos): void
    {
        app(HistorialCambiosService::class)->registrar($modelo, $evento, $anteriores, $nuevos, $modelo->autorSinContexto());
    }
}
