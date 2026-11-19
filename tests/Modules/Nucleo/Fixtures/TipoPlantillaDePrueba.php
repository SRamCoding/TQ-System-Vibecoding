<?php

declare(strict_types=1);

namespace Tests\Modules\Nucleo\Fixtures;

use App\Modules\Nucleo\Excepciones\PlantillaRechazada;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Plantillas\DefinicionPlantilla;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use stdClass;

/**
 * Un tipo de plantilla inventado para probar el núcleo sin el árbol (plan 2.11, D4): una «lista de cosas». Sus
 * documentos viven en memoria (`$documentos`). Formato 2: `{"cosas": [{"nombre": …, "precio": …}]}`; el 1, que ya no
 * se captura pero se sigue leyendo, era `{"nombres": […]}`. Con `$sensible`, sus precios son del grupo «costos» (D5).
 */
final class TipoPlantillaDePrueba extends DefinicionPlantilla
{
    /** @var array<int, list<array{nombre: string, precio: string}>> */
    public static array $documentos = [];

    public function __construct(
        private readonly string $clave = 'pruebas.listas.cosas',
        private readonly bool $sensible = false,
    ) {}

    public function clave(): string
    {
        return $this->clave;
    }

    public function nombre(): string
    {
        return 'Lista de cosas';
    }

    public function permisoUsar(): string
    {
        return 'pruebas.listas.editar';
    }

    public function permisoPublicar(): string
    {
        return 'pruebas.listas.plantillas.publicar';
    }

    public function formatoActual(): int
    {
        return 2;
    }

    public function grupoSensible(): ?GrupoSensible
    {
        return $this->sensible ? GrupoSensible::Costos : null;
    }

    public function validar(array $contenido, int $formato): array
    {
        $lista = $contenido[$formato === 1 ? 'nombres' : 'cosas'] ?? null;

        return is_array($lista) && $lista !== [] ? [] : ['la lista está vacía'];
    }

    public function capturar(int $documentoId, User $usuario): array
    {
        $cosas = self::$documentos[$documentoId] ?? throw PlantillaRechazada::conMotivo("La lista {$documentoId} no existe.");

        return ['cosas' => $cosas];
    }

    public function proponer(array $contenido, int $formato, array $contexto, User $usuario): object
    {
        if (($contexto['ocupado'] ?? false) === true) {
            throw PlantillaRechazada::conMotivo('Esa lista ya tiene cosas: vacíala antes de usar una plantilla.');
        }

        $propuesta = new stdClass;
        $propuesta->cosas = $formato === 1
            ? array_map(fn (string $nombre): array => ['nombre' => $nombre, 'precio' => '0.00'], $contenido['nombres'])
            : $contenido['cosas'];

        return $propuesta;
    }
}
