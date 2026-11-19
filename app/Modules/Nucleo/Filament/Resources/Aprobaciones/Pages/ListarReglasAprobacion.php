<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Resources\Aprobaciones\Pages;

use App\Modules\Nucleo\Filament\Resources\Aprobaciones\AutoaprobacionesPorRevisar;
use App\Modules\Nucleo\Filament\Resources\Aprobaciones\ReglaAprobacionResource;
use App\Modules\Nucleo\Models\FirmaAprobacion;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Tables\Table;

/**
 * Configuración → Aprobaciones, en dos pestañas (plan 1.7, D6): las reglas y las autoaprobaciones por revisar. Cada
 * pestaña tiene su propia tabla; al cambiar de pestaña la tabla se rehace.
 */
final class ListarReglasAprobacion extends ListRecords
{
    protected static string $resource = ReglaAprobacionResource::class;

    protected static ?string $title = 'Aprobaciones';

    public function getTabs(): array
    {
        $porRevisar = FirmaAprobacion::query()->where('es_autoaprobacion', true)->whereNull('revisada_at')->count();

        return [
            'reglas' => Tab::make('Reglas'),
            'autoaprobaciones' => Tab::make('Autoaprobaciones por revisar')->badge($porRevisar > 0 ? $porRevisar : null),
        ];
    }

    public function updatedActiveTab(): void
    {
        parent::updatedActiveTab();
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        return $this->activeTab === 'autoaprobaciones' ? AutoaprobacionesPorRevisar::tabla($table) : ReglaAprobacionResource::tablaDeReglas($table);
    }
}
