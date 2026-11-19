<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cuentas\Models;

use App\Modules\Crm\Cuentas\Actividad\OportunidadEnlazable;
use App\Modules\Crm\Cuentas\database\factories\CuentaMarcaClienteFactory;
use App\Modules\Crm\Cuentas\Enums\OrigenMarcaCliente;
use App\Modules\Nucleo\Historial\DescribeBitacora;
use App\Modules\Nucleo\Historial\DescribibleEnBitacora;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\RegistraCambios;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Seguridad\DeclaraCamposSensibles;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Por qué una cuenta es cliente (plan 2.20, D8): una por oportunidad ganada y como mucho una manual vigente. La de una
 * oportunidad se borra al revertirla; la manual se anula al quitarla, con su motivo (D8.6). Solo cuentan las vigentes.
 *
 * @property int $id
 * @property int $cuenta_id
 * @property OrigenMarcaCliente $origen
 * @property ?int $oportunidad_id
 * @property CarbonImmutable $fecha
 * @property ?string $comentario
 * @property ?CarbonImmutable $quitada_en
 * @property ?int $quitada_por_user_id
 * @property ?string $motivo_quitada
 */
#[UseFactory(CuentaMarcaClienteFactory::class)]
class CuentaMarcaCliente extends Model implements DeclaraCamposSensibles, DescribibleEnBitacora
{
    /** @use HasFactory<CuentaMarcaClienteFactory> */
    use ConAuditoria, DescribeBitacora, HasFactory, RegistraCambios;

    protected $table = 'cuenta_marcas_cliente';

    protected $fillable = ['cuenta_id', 'origen', 'oportunidad_id', 'fecha', 'comentario', 'quitada_en', 'quitada_por_user_id', 'motivo_quitada'];

    protected function casts(): array
    {
        return [
            'cuenta_id' => 'integer',
            'origen' => OrigenMarcaCliente::class,
            'oportunidad_id' => 'integer',
            'fecha' => 'immutable_date',
            'quitada_en' => 'immutable_datetime',
            'quitada_por_user_id' => 'integer',
        ];
    }

    public function camposSinBitacora(): array
    {
        return [];
    }

    public function etiquetasBitacora(): array
    {
        return [
            'cuenta_id' => 'Cuenta', 'origen' => 'Por qué', 'oportunidad_id' => 'Oportunidad', 'fecha' => 'Cliente desde',
            'comentario' => 'Comentario', 'quitada_en' => 'Quitada', 'quitada_por_user_id' => 'Quitada por', 'motivo_quitada' => 'Motivo',
        ];
    }

    public function sujetoBitacora(): string
    {
        return 'Marca de cliente';
    }

    public function valorLegibleBitacora(string $campo, mixed $valor): string
    {
        return match (true) {
            $valor === null => '—',
            $campo === 'origen' => OrigenMarcaCliente::tryFrom((string) $valor)?->etiqueta() ?? self::valorComun($valor),
            $campo === 'fecha' => CarbonImmutable::parse((string) $valor)->format('d/m/Y'),
            $campo === 'quitada_en' => CarbonImmutable::parse((string) $valor)->format('d/m/Y H:i'),
            $campo === 'quitada_por_user_id' => User::query()->whereKey($valor)->value('name') ?? self::valorComun($valor),
            $campo === 'oportunidad_id' => app(OportunidadEnlazable::class)->etiqueta((int) $valor),
            default => self::valorComun($valor),
        };
    }

    /**
     * «Marcada cliente desde 15/01/2025 (a mano)»: una marca se lee junto con su cuenta.
     *
     * @param  array<string, mixed>  $valores
     */
    public function resumenCreacionBitacora(array $valores): ?string
    {
        $porque = isset($valores['oportunidad_id'])
            ? $this->valorLegibleBitacora('oportunidad_id', $valores['oportunidad_id'])
            : Str::lower($this->valorLegibleBitacora('origen', $valores['origen'] ?? null));

        return 'Cliente desde '.$this->valorLegibleBitacora('fecha', $valores['fecha'] ?? null)." ({$porque})";
    }

    public function camposSensibles(): array
    {
        return [];
    }

    public function camposNoSensibles(): array
    {
        return ['cuenta_id' => 'La cuenta comercial (CRM) a la que pertenece la marca: no es una cuenta bancaria.'];
    }

    /**
     * Las que cuentan para `cliente_desde`: no anuladas.
     *
     * @param  Builder<self>  $consulta
     */
    public function scopeVigentes(Builder $consulta): void
    {
        $consulta->whereNull('quitada_en');
    }

    /** @return BelongsTo<Cuenta, $this> */
    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(Cuenta::class);
    }
}
