<?php

declare(strict_types=1);

namespace Tests\Modules\Erp\Presupuestos\Fixtures;

use App\Modules\Nucleo\Filament\Imputacion\SelectorImputacion;
use App\Modules\Nucleo\Imputacion\FuenteDeDestinos;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Livewire\Component;

/**
 * Un formulario de línea de costo inventado que monta el selector de imputación (plan 2.2, D8), como lo hará el de un
 * documento real: el monto, la moneda y la fecha de la línea, y los controles del selector. La prueba le da la fuente.
 */
final class FormularioConSelector extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public static ?FuenteDeDestinos $fuente = null;

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** @param array<string, mixed> $inicial */
    public function mount(array $inicial = []): void
    {
        $this->form->fill(['monto' => '0', 'moneda' => 'PEN', 'fecha' => '2026-11-12', ...$inicial]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            TextInput::make('monto')->label('Monto')->live(),
            TextInput::make('moneda')->label('Moneda'),
            DatePicker::make('fecha')->label('Fecha'),
            ...array_map(fn (\Closure $control) => $control(), array_values(SelectorImputacion::controles(self::$fuente))),
        ]);
    }

    public function render(): string
    {
        return <<<'BLADE'
            <div>{{ $this->form }}</div>
            BLADE;
    }
}
