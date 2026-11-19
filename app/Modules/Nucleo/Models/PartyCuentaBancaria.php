<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\database\factories\PartyCuentaBancariaFactory;
use App\Modules\Nucleo\Excepciones\CuentaBancariaRechazada;
use App\Modules\Nucleo\Historial\DescribeBitacora;
use App\Modules\Nucleo\Historial\DescribibleEnBitacora;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\RegistraCambios;
use App\Modules\Nucleo\Seguridad\DeclaraCamposSensibles;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * M1 · Cuenta de una party en un banco, para pagarle o cobrarle (plan 1.3b, D5). Banco, número y moneda no
 * cambian: si cambian, se desactiva y se registra otra. La de detracciones (Banco de la Nación) nunca es
 * principal ni cuenta de pago normal. La misma cuenta solo puede estar en otra party si es la misma persona
 * (pareja DNI ↔ RUC 10): la base lo garantiza con la clave de persona, que se copia del titular al crear.
 *
 * @property int $id
 * @property int $party_id
 * @property int $persona_tipo_id
 * @property string $persona_numero
 * @property int $banco_party_id
 * @property string $numero_cuenta
 * @property ?string $cci
 * @property int $moneda_id
 * @property bool $es_detraccion
 * @property bool $es_principal
 * @property bool $es_activa
 * @property int $version
 * @property int $created_by
 * @property ?int $updated_by
 * @property-read Party $party
 * @property-read Party $banco
 * @property-read Moneda $moneda
 */
#[UseFactory(PartyCuentaBancariaFactory::class)]
class PartyCuentaBancaria extends Model implements DeclaraCamposSensibles, DescribibleEnBitacora
{
    /** @use HasFactory<PartyCuentaBancariaFactory> */
    use ConAuditoria, DescribeBitacora, HasFactory, RegistraCambios;

    protected $table = 'party_cuentas_bancarias';

    protected $fillable = [
        'party_id', 'banco_party_id', 'numero_cuenta', 'cci', 'moneda_id', 'es_detraccion', 'es_principal', 'es_activa',
    ];

    protected static function booted(): void
    {
        // Guardia contra el fraude clásico (D5): por ningún camino de modelo cambian banco, número, moneda ni titular,
        // y un CCI registrado tampoco. Se desactiva y se registra otra.
        static::updating(function (PartyCuentaBancaria $cuenta): void {
            if ($cuenta->isDirty(['party_id', 'persona_tipo_id', 'persona_numero', 'banco_party_id', 'numero_cuenta', 'moneda_id'])
                || ($cuenta->isDirty('cci') && $cuenta->getOriginal('cci') !== null)) {
                throw CuentaBancariaRechazada::datosFijos();
            }
        });

        static::creating(function (PartyCuentaBancaria $cuenta): void {
            $titular = Party::query()->select(['persona_tipo_id', 'persona_numero'])->findOrFail($cuenta->party_id);
            $cuenta->persona_tipo_id = $titular->persona_tipo_id;
            $cuenta->persona_numero = $titular->persona_numero;
        });
    }

    /**
     * Solo dígitos: `191-1234567-0-12` y `191 1234567 0 12` son `1911234567012`.
     */
    public static function normalizarNumero(string $numero): string
    {
        return (string) preg_replace('/[\s.\-]+/u', '', $numero);
    }

    /**
     * Dígitos de control del CCI. **Hoy acepta todo**: el cálculo se confirmará con los CCI de Techquk de sus
     * facturas (`docs/decisiones/pendientes.md`). La forma (20 dígitos) ya la exige la base.
     */
    public static function cciValido(string $cci): bool
    {
        return true;
    }

    /**
     * @return list<string>
     */
    public function camposSinBitacora(): array
    {
        return ['version', 'persona_tipo_id', 'persona_numero'];
    }

    public function etiquetasBitacora(): array
    {
        return [
            'party_id' => 'Persona o empresa',
            'banco_party_id' => 'Banco',
            'numero_cuenta' => 'Número de cuenta',
            'cci' => 'CCI',
            'moneda_id' => 'Moneda',
            'es_detraccion' => 'Cuenta de detracciones',
            'es_principal' => 'Principal',
            'es_activa' => 'Activa',
        ];
    }

    /**
     * Número y CCI: solo para quien tiene `campos.ver-datos_bancarios` (plan 1.4, D6; antes, `parties.ver-cuentas-bancarias`).
     *
     * @return array<string, GrupoSensible>
     */
    public function camposSensibles(): array
    {
        return ['numero_cuenta' => GrupoSensible::DatosBancarios, 'cci' => GrupoSensible::DatosBancarios];
    }

    public function camposNoSensibles(): array
    {
        return [];
    }

    /**
     * «Cuenta BCP en PEN»: sin el número, porque también lo lee quien no puede verlo. El número sale en las filas de
     * sus campos y en el resumen de creación, que el historial solo muestra a quien puede ver todos los campos.
     */
    public function sujetoBitacora(): string
    {
        return 'Cuenta '.($this->banco->nombre_comercial ?? $this->banco->razon_social).' en '.$this->moneda->codigo;
    }

    /**
     * «Registró cuenta Banco Ejemplo 1911234567012 en PEN · principal».
     *
     * @param  array<string, mixed>  $valores
     */
    public function resumenCreacionBitacora(array $valores): ?string
    {
        $partes = array_filter([
            'Registró cuenta '.$this->valorLegibleBitacora('banco_party_id', $valores['banco_party_id'] ?? null).' '.((string) ($valores['numero_cuenta'] ?? ''))
                .' en '.$this->valorLegibleBitacora('moneda_id', $valores['moneda_id'] ?? null),
            ($valores['cci'] ?? null) === null ? null : 'CCI '.$valores['cci'],
            ($valores['es_principal'] ?? false) ? 'principal' : null,
            ($valores['es_detraccion'] ?? false) ? 'de detracciones' : null,
        ]);

        return implode(' · ', $partes);
    }

    public function valorLegibleBitacora(string $campo, mixed $valor): string
    {
        if ($valor === null) {
            return '—';
        }

        return match ($campo) {
            'party_id', 'banco_party_id' => Party::query()->whereKey($valor)->value('razon_social') ?? self::valorComun($valor),
            'moneda_id' => Moneda::query()->whereKey($valor)->value('codigo') ?? self::valorComun($valor),
            'es_detraccion', 'es_principal', 'es_activa' => self::valorComun((bool) $valor),
            default => self::valorComun($valor),
        };
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function banco(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'banco_party_id');
    }

    /**
     * @return BelongsTo<Moneda, $this>
     */
    public function moneda(): BelongsTo
    {
        return $this->belongsTo(Moneda::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'party_id' => 'integer',
            'persona_tipo_id' => 'integer',
            'banco_party_id' => 'integer',
            'moneda_id' => 'integer',
            'es_detraccion' => 'boolean',
            'es_principal' => 'boolean',
            'es_activa' => 'boolean',
            'version' => 'integer',
        ];
    }
}
