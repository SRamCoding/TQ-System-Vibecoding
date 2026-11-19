<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\Adjuntos\ConAdjuntos;
use App\Modules\Nucleo\database\factories\PartyFactory;
use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Enums\TipoPersona;
use App\Modules\Nucleo\Historial\DescribeBitacora;
use App\Modules\Nucleo\Historial\DescribibleEnBitacora;
use App\Modules\Nucleo\Models\Concerns\BuscaSinAcentos;
use App\Modules\Nucleo\Models\Concerns\BusquedaSinAcentos;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\RegistraCambios;
use App\Modules\Nucleo\Parties\DocumentoIdentidad;
use App\Modules\Nucleo\Parties\PosiblesDuplicados;
use App\Modules\Nucleo\Tiempo\Calendario;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * M1 · Persona u organización, una sola vez sin importar cuántos papeles cumpla (plan 1.3). Su identidad es
 * el documento: tipo y número no cambian (D5). La escriben solo los servicios del núcleo; los demás módulos
 * guardan su id.
 *
 * @property int $id
 * @property TipoPersona $tipo
 * @property int $tipo_documento_id
 * @property string $numero_documento
 * @property string $razon_social
 * @property ?string $nombre_comercial
 * @property string $nombre_normalizado
 * @property ?string $nombre_comercial_normalizado
 * @property ?string $nombres
 * @property ?string $apellidos
 * @property ?string $direccion_fiscal
 * @property ?int $ubigeo_id
 * @property ?int $rubro_id
 * @property ?string $telefono
 * @property ?string $email
 * @property ?string $condicion_sunat
 * @property ?string $estado_sunat
 * @property bool $es_agente_retencion
 * @property bool $es_agente_percepcion
 * @property bool $es_buen_contribuyente
 * @property bool $es_activo
 * @property int $persona_tipo_id
 * @property string $persona_numero
 * @property int $version
 * @property int $created_by
 * @property ?int $updated_by
 * @property-read TipoDocumentoIdentidad $tipoDocumento
 * @property-read ?Ubigeo $ubigeo
 * @property-read ?Rubro $rubro
 * @property-read Collection<int, PartyContacto> $contactos
 * @property-read Collection<int, PartySede> $sedes
 * @property-read Collection<int, PartyCuentaBancaria> $cuentasBancarias
 */
#[UseFactory(PartyFactory::class)]
class Party extends Model implements ConAdjuntos, DescribibleEnBitacora
{
    /** @use HasFactory<PartyFactory> */
    use BuscaSinAcentos, ConAuditoria, DescribeBitacora, HasFactory, RegistraCambios;

    protected $table = 'parties';

    protected $fillable = [
        'tipo', 'tipo_documento_id', 'numero_documento', 'razon_social', 'nombre_comercial', 'nombres', 'apellidos',
        'direccion_fiscal', 'ubigeo_id', 'rubro_id', 'telefono', 'email', 'condicion_sunat', 'estado_sunat',
        'es_agente_retencion', 'es_agente_percepcion', 'es_buen_contribuyente', 'es_activo',
    ];

    /**
     * La clave de persona (plan 1.3b, D5 · 5b) sale del documento, que no se edita: se calcula una vez, al crear.
     */
    protected static function booted(): void
    {
        static::creating(function (Party $party): void {
            $party->asignarClavePersona();
        });
        // Plan 2.20a, N1: los nombres normalizados los llena siempre el modelo. Por eso, dentro del núcleo, la razón
        // social y el nombre comercial solo se escriben por el modelo.
        static::saving(function (Party $party): void {
            $party->normalizarNombres();
        });
    }

    /** Pone al día `nombre_normalizado` y `nombre_comercial_normalizado` (plan 2.20a, N1). */
    public function normalizarNombres(): void
    {
        // Como texto: antes de guardar, un atributo puede llegar con otro tipo (la base lo guarda como texto igual).
        $comercial = $this->getAttribute('nombre_comercial');
        $this->nombre_normalizado = PosiblesDuplicados::normalizar((string) $this->getAttribute('razon_social'));
        $this->nombre_comercial_normalizado = PosiblesDuplicados::normalizarOpcional($comercial === null ? null : (string) $comercial);
    }

    /**
     * `version` sube en cada cambio: anotarla solo agrega ruido. La clave de persona se deduce del documento, y los
     * nombres normalizados, de la razón social y el nombre comercial (plan 2.20a, N1). No hay datos sensibles que excluir.
     *
     * @return list<string>
     */
    public function camposSinBitacora(): array
    {
        return ['version', 'persona_tipo_id', 'persona_numero', 'nombre_normalizado', 'nombre_comercial_normalizado'];
    }

    public function etiquetasBitacora(): array
    {
        return [
            'tipo' => 'Tipo de persona',
            'tipo_documento_id' => 'Tipo de documento',
            'numero_documento' => 'Número de documento',
            'razon_social' => 'Razón social',
            'nombre_comercial' => 'Nombre comercial',
            'nombres' => 'Nombres',
            'apellidos' => 'Apellidos',
            'direccion_fiscal' => 'Dirección fiscal',
            'ubigeo_id' => 'Ubigeo',
            'rubro_id' => 'Rubro',
            'telefono' => 'Teléfono',
            'email' => 'Correo',
            'condicion_sunat' => 'Condición SUNAT',
            'estado_sunat' => 'Estado SUNAT',
            'es_agente_retencion' => 'Agente de retención',
            'es_agente_percepcion' => 'Agente de percepción',
            'es_buen_contribuyente' => 'Buen contribuyente',
            'es_activo' => 'Activa',
        ];
    }

    /**
     * El alta en una sola fila, como los roles: «Registró persona natural · DNI 01234567 · Pérez Soto Ana». Los
     * demás datos del alta se ven en la ficha; los cambios posteriores, campo por campo.
     *
     * @param  array<string, mixed>  $valores
     */
    public function resumenCreacionBitacora(array $valores): ?string
    {
        $tipo = TipoPersona::tryFrom((string) ($valores['tipo'] ?? ''));

        return implode(' · ', array_filter([
            'Registró '.($tipo === null ? 'persona o empresa' : mb_strtolower($tipo->etiqueta())),
            $this->valorLegibleBitacora('tipo_documento_id', $valores['tipo_documento_id'] ?? null).' '.((string) ($valores['numero_documento'] ?? '')),
            $valores['razon_social'] ?? null,
        ]));
    }

    public function valorLegibleBitacora(string $campo, mixed $valor): string
    {
        if ($valor === null) {
            return '—';
        }

        return match ($campo) {
            'tipo' => TipoPersona::tryFrom((string) $valor)?->etiqueta() ?? (string) $valor,
            'tipo_documento_id' => TipoDocumentoIdentidad::query()->whereKey($valor)->value('nombre') ?? (string) $valor,
            'ubigeo_id' => Ubigeo::query()->find($valor)?->etiqueta() ?? (string) $valor,
            'rubro_id' => Rubro::query()->find($valor)?->etiqueta() ?? (string) $valor,
            'es_agente_retencion', 'es_agente_percepcion', 'es_buen_contribuyente', 'es_activo' => self::valorComun((bool) $valor),
            default => self::valorComun($valor),
        };
    }

    /**
     * El número empieza con el texto (ya normalizado: «20-1234» sirve) o la razón social o el nombre comercial lo
     * contienen, sin mayúsculas ni acentos (plan 1.3, D6 y D11). Lo usan el buscador de los selectores y la lista.
     *
     * @param  Builder<static>  $consulta
     */
    public function scopeBuscarNombreODocumento(Builder $consulta, string $texto): void
    {
        $numero = DocumentoIdentidad::normalizar($texto);

        $consulta->where(function (Builder $q) use ($texto, $numero): void {
            BusquedaSinAcentos::aplicar($q, $texto, ['razon_social', 'nombre_comercial']);

            if (preg_match('/^[0-9A-Z]+$/', $numero) === 1) {
                // `like` con el índice varchar_pattern_ops: el número guardado ya está normalizado.
                $q->orWhere('numero_documento', 'like', $numero.'%');
            }
        });
    }

    /**
     * Con el rol vigente en la fecha (`PartyRol::scopeVigenteEl`).
     *
     * @param  Builder<static>  $consulta
     */
    public function scopeConRolVigente(Builder $consulta, string $codigoRol, string $dia): void
    {
        $consulta->whereHas('roles', fn (Builder $q): Builder => $q
            ->whereHas('rol', fn (Builder $r): Builder => $r->where('codigo', $codigoRol))
            ->vigenteEl($dia));
    }

    /**
     * @return BelongsTo<TipoDocumentoIdentidad, $this>
     */
    public function tipoDocumento(): BelongsTo
    {
        return $this->belongsTo(TipoDocumentoIdentidad::class, 'tipo_documento_id');
    }

    /**
     * @return BelongsTo<Ubigeo, $this>
     */
    public function ubigeo(): BelongsTo
    {
        return $this->belongsTo(Ubigeo::class);
    }

    /**
     * Plan 2.20a, N4: la sección CIIU de la empresa.
     *
     * @return BelongsTo<Rubro, $this>
     */
    public function rubro(): BelongsTo
    {
        return $this->belongsTo(Rubro::class);
    }

    /**
     * Todos los periodos, vigentes y cerrados.
     *
     * @return HasMany<PartyRol, $this>
     */
    public function roles(): HasMany
    {
        return $this->hasMany(PartyRol::class);
    }

    /**
     * Los periodos vigentes hoy, en Lima (`PartyRol::scopeVigenteEl`). Para la lista: se carga con `with()`.
     *
     * @return HasMany<PartyRol, $this>
     */
    public function rolesVigentes(): HasMany
    {
        return $this->roles()->vigenteEl(Calendario::hoy());
    }

    /**
     * Vínculos de contacto, vigentes y cerrados (plan 1.3b, D1).
     *
     * @return HasMany<PartyContacto, $this>
     */
    public function contactos(): HasMany
    {
        return $this->hasMany(PartyContacto::class);
    }

    /**
     * @return HasMany<PartySede, $this>
     */
    public function sedes(): HasMany
    {
        return $this->hasMany(PartySede::class);
    }

    /**
     * @return HasMany<PartyCuentaBancaria, $this>
     */
    public function cuentasBancarias(): HasMany
    {
        return $this->hasMany(PartyCuentaBancaria::class);
    }

    /**
     * Un RUC 10 toma la clave del DNI que lleva dentro (`10` + DNI + dígito); todo lo demás, su propio documento.
     * Sin el DNI en el catálogo, la propia: el CHECK la acepta y solo impide compartir cuentas con ese DNI.
     */
    private function asignarClavePersona(): void
    {
        $this->persona_tipo_id = $this->tipo_documento_id;
        $this->persona_numero = $this->numero_documento;

        if (preg_match('/^10[0-9]{9}$/', $this->numero_documento) !== 1) {
            return;
        }

        $codigo = TipoDocumentoIdentidad::query()->whereKey($this->tipo_documento_id)->value('codigo');
        $dni = TipoDocumentoIdentidad::query()->where('codigo', Catalogo::DNI)->value('id');

        if ($codigo === Catalogo::RUC && $dni !== null) {
            $this->persona_tipo_id = (int) $dni;
            $this->persona_numero = substr($this->numero_documento, 2, 8);
        }
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoPersona::class,
            'tipo_documento_id' => 'integer',
            'ubigeo_id' => 'integer',
            'rubro_id' => 'integer',
            'es_agente_retencion' => 'boolean',
            'es_agente_percepcion' => 'boolean',
            'es_buen_contribuyente' => 'boolean',
            'es_activo' => 'boolean',
            'persona_tipo_id' => 'integer',
            'version' => 'integer',
        ];
    }

    /** Sus archivos (plan 1.8): los ve quien ve las personas y empresas; los cambia quien puede editarlas. */
    public function puedeVerAdjuntos(User $usuario): bool
    {
        return $usuario->can('parties.ver');
    }

    public function puedeAdjuntar(User $usuario): bool
    {
        return $usuario->can('parties.editar');
    }
}
