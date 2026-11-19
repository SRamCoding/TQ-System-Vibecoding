<?php

declare(strict_types=1);

namespace Tests\Modules\Nucleo\Fixtures;

use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Filament\SelectorCatalogo;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Livewire\Component;

/**
 * Un formulario suelto con selectores de catálogo comunes (plan 2.0, D4), para probar el «+» y la búsqueda.
 *
 * @property-read Schema $form
 */
final class FormularioConCatalogo extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            SelectorCatalogo::de(Catalogo::OrigenesComerciales, 'origen_id'),
            SelectorCatalogo::de(Catalogo::Rubros, 'rubro_id'),
        ])->statePath('data');
    }

    public function render(): string
    {
        return <<<'BLADE'
            <div>
                {{ $this->form }}
                <x-filament-actions::modals />
            </div>
            BLADE;
    }
}
