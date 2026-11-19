<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Services;

use App\Modules\Nucleo\Enums\EventoAuditoria;
use App\Modules\Nucleo\Models\AuditoriaCambio;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Escribe y lee la bitácora campo por campo (M4). La escribe el trait RegistraCambios, dentro de la
 * transacción que el trait abre en save() y delete(): el cambio y su bitácora entran juntos.
 */
final class HistorialCambiosService
{
    public function __construct(private readonly ContextoAuditoria $contexto) {}

    /**
     * @param  ?array<string, mixed>  $anteriores
     * @param  ?array<string, mixed>  $nuevos
     */
    public function registrar(Model $modelo, EventoAuditoria $evento, ?array $anteriores, ?array $nuevos, ?int $autorPorDefecto = null): void
    {
        AuditoriaCambio::query()->create([
            'auditable_type' => $modelo->getMorphClass(),
            'auditable_id' => $modelo->getKey(),
            'evento' => $evento,
            'user_id' => $this->contexto->idActor($autorPorDefecto),
            'valores_anteriores' => $anteriores,
            'valores_nuevos' => $nuevos,
            'ip' => $this->contexto->ip(),
            'agente' => $this->contexto->agente(),
            'fecha' => now(),
        ]);
    }

    /**
     * Historial de una fila, del cambio más reciente al más antiguo.
     *
     * @return Collection<int, AuditoriaCambio>
     */
    public function de(Model $modelo): Collection
    {
        return AuditoriaCambio::query()
            ->with('usuario')
            ->where('auditable_type', $modelo->getMorphClass())
            ->where('auditable_id', $modelo->getKey())
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Las filas ya borradas de una parte de la ficha (un contacto, una cuenta), rearmadas con los valores que la
     * bitácora guardó al borrarlas: así su historial sigue en la ficha. No se guardan (`exists` en falso); solo
     * sirven para leer su bitácora y describirla. `$columna` es la que las une a la ficha (`party_id`).
     *
     * @template TModelo of Model
     *
     * @param  class-string<TModelo>  $clase
     * @return list<TModelo>
     */
    public function borradosDe(string $clase, string $columna, int $valor): array
    {
        $modelo = new $clase;

        return AuditoriaCambio::query()
            ->where('auditable_type', $modelo->getMorphClass())
            ->where('evento', EventoAuditoria::Eliminado)
            ->whereRaw('(valores_anteriores ->> ?) = ?', [$columna, (string) $valor])
            ->orderBy('id')
            ->get()
            ->map(function (AuditoriaCambio $cambio) use ($clase): Model {
                $borrado = new $clase;
                $borrado->forceFill($cambio->valores_anteriores ?? []);
                $borrado->setAttribute($borrado->getKeyName(), $cambio->auditable_id);
                $borrado->exists = false;

                return $borrado;
            })
            ->values()
            ->all();
    }

    /**
     * Historial de varias filas juntas (una ficha y sus partes, como una party y sus roles), en una sola línea
     * de tiempo del cambio más reciente al más antiguo.
     *
     * @param  list<Model>  $modelos
     * @return Collection<int, AuditoriaCambio>
     */
    public function deVarios(array $modelos): Collection
    {
        if ($modelos === []) {
            return new Collection;
        }

        return AuditoriaCambio::query()
            ->with('usuario')
            ->where(function (Builder $q) use ($modelos): void {
                foreach ($modelos as $modelo) {
                    $q->orWhere(fn (Builder $f): Builder => $f
                        ->where('auditable_type', $modelo->getMorphClass())
                        ->where('auditable_id', $modelo->getKey()));
                }
            })
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();
    }
}
