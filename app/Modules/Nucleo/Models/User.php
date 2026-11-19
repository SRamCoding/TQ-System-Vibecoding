<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\database\factories\UserFactory;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\RegistraCambios;
use Carbon\CarbonImmutable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * M10 · Una cuenta para iniciar sesión. Salvo el usuario de sistema, es siempre una persona (DNI, CE o pasaporte) y
 * una persona tiene una sola cuenta: copia la clave de persona de su ficha, única en la base (plan 1.4, D4). Su
 * unidad sale del puesto. Los permisos le llegan solo por roles, globales o acotados a un ámbito.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property ?int $party_id
 * @property ?int $persona_tipo_id
 * @property ?string $persona_numero
 * @property ?int $puesto_id
 * @property ?CarbonImmutable $ultimo_acceso_at
 * @property ?int $cumpleanos_dia
 * @property ?int $cumpleanos_mes
 * @property ?string $foto_ruta
 * @property bool $debe_cambiar_clave
 * @property ?CarbonImmutable $clave_cambiada_at
 * @property int $version
 * @property int $created_by
 * @property ?int $updated_by
 * @property bool $es_activo
 * @property bool $es_sistema
 * @property-read ?Party $party
 * @property-read ?Puesto $puesto
 */
#[Fillable(['name', 'email', 'password', 'party_id', 'puesto_id'])]
#[Hidden(['password', 'remember_token'])]
#[UseFactory(UserFactory::class)]
class User extends Authenticatable implements FilamentUser, HasAvatar
{
    /** @use HasFactory<UserFactory> */
    use ConAuditoria, HasFactory, HasRoles, Notifiable, RegistraCambios;

    /**
     * Mismos valores por defecto que la base, para que un modelo recién creado los tenga.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'es_activo' => true,
        'es_sistema' => false,
        'debe_cambiar_clave' => false,
    ];

    protected static function booted(): void
    {
        // La clave de persona se copia de la ficha; la llave compuesta de la base impide que se desvíe.
        static::saving(function (User $usuario): void {
            if (! $usuario->isDirty('party_id')) {
                return;
            }

            $persona = $usuario->party_id === null ? null
                : Party::query()->select(['persona_tipo_id', 'persona_numero'])->findOrFail($usuario->party_id);
            $usuario->persona_tipo_id = $persona?->persona_tipo_id;
            $usuario->persona_numero = $persona?->persona_numero;
        });
    }

    /**
     * Bitácora de la cuenta (revisión de la 1.4): nombre, correo, persona, puesto y si está activa. Los roles globales
     * los anota SeguridadService en esta misma bitácora. Nunca la clave ni el token; tampoco el último acceso, que no es un cambio
     * de la cuenta, ni el cumpleaños, que es un dato personal (plan 1.4b, D6), ni la ruta de la foto (plan 1.4o). Las preferencias de interfaz viven aparte
     * (`preferencias_usuario`, plan 1.4g).
     *
     * @return list<string>
     */
    public function camposSinBitacora(): array
    {
        return ['password', 'remember_token', 'ultimo_acceso_at', 'version', 'persona_tipo_id', 'persona_numero', 'email_verified_at', 'cumpleanos_dia', 'cumpleanos_mes', 'foto_ruta'];
    }

    /**
     * El usuario de sistema y los usuarios dados de baja no inician sesión.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->es_activo && ! $this->es_sistema;
    }

    /**
     * Su foto en el círculo del menú (plan 1.4o, D4); sin foto, nulo y Filament muestra las iniciales. La dirección
     * cambia con cada foto nueva: el navegador la puede guardar sin mostrar una vieja.
     */
    public function getFilamentAvatarUrl(): ?string
    {
        return $this->foto_ruta === null ? null
            : route('filament.app.foto', ['usuario' => $this->id, 'v' => substr(hash('sha256', $this->foto_ruta), 0, 12)]);
    }

    /**
     * Sin sesión, quien cambia su propia fila es el mismo usuario: iniciar sesión con «recordarme»,
     * cerrarla o restablecer la clave guardan antes de que exista la sesión.
     */
    public function autorSinContexto(): ?int
    {
        return $this->exists ? (int) $this->getKey() : null;
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /**
     * @return BelongsTo<Puesto, $this>
     */
    public function puesto(): BelongsTo
    {
        return $this->belongsTo(Puesto::class);
    }

    /**
     * @return HasMany<PermisoPorAmbito, $this>
     */
    public function permisosPorAmbito(): HasMany
    {
        return $this->hasMany(PermisoPorAmbito::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'es_activo' => 'boolean',
            'es_sistema' => 'boolean',
            'party_id' => 'integer',
            'persona_tipo_id' => 'integer',
            'puesto_id' => 'integer',
            'ultimo_acceso_at' => 'immutable_datetime',
            'cumpleanos_dia' => 'integer',
            'cumpleanos_mes' => 'integer',
            'debe_cambiar_clave' => 'boolean',
            'clave_cambiada_at' => 'immutable_datetime',
            'version' => 'integer',
        ];
    }

    /**
     * Sus avisos (plan 1.8): la campana del panel los lee de nuestra tabla `notificaciones`, no de la de Laravel.
     *
     * @return MorphMany<Notificacion, $this>
     */
    public function notifications(): MorphMany
    {
        return $this->morphMany(Notificacion::class, 'notifiable')->latest('id');
    }
}
