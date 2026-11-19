<?php

declare(strict_types=1);

namespace Tests\Modules\Nucleo\Fixtures;

use App\Modules\Nucleo\Estados\DocumentoQueImpide;
use App\Modules\Nucleo\Estados\RevisionAntesDeAnular;
use App\Modules\Nucleo\Models\Estado;
use Illuminate\Database\Eloquent\Model;

/** Los documentos de prueba vivos (no anulados) que salieron de otro: impiden anularlo. */
final class DependientesDePrueba implements RevisionAntesDeAnular
{
    public function documentosQueImpiden(Model $documento): array
    {
        return DocumentoPrueba::query()
            ->where('origen_id', $documento->getKey())
            ->whereIn('estado_id', Estado::query()->select('id')->where('es_cancelacion', false))
            ->orderBy('id')
            ->get()
            ->map(fn (DocumentoPrueba $dependiente): DocumentoQueImpide => new DocumentoQueImpide(
                'el documento de prueba',
                (new DefinicionPrueba)->identificador($dependiente),
                Estado::query()->findOrFail($dependiente->estado_id)->nombre,
            ))
            ->all();
    }
}
