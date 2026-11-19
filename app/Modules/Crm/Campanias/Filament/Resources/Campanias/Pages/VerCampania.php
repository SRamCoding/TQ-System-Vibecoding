<?php

declare(strict_types=1);

namespace App\Modules\Crm\Campanias\Filament\Resources\Campanias\Pages;

use App\Modules\Crm\Campanias\Filament\Resources\Campanias\CampaniaResource;
use Filament\Resources\Pages\ViewRecord;

/** MVP rápido (t1-campanias): detalle de la campaña con sus cifras y sus leads. */
final class VerCampania extends ViewRecord
{
    protected static string $resource = CampaniaResource::class;

    public function getTitle(): string
    {
        return (string) $this->record->getAttribute('nombre');
    }
}
