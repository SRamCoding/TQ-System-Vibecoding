<?php

declare(strict_types=1);

namespace App\Modules\Crm\Catalogo\Filament\Resources\Catalogo;

use App\Modules\Crm\Catalogo\Models\FamiliaProducto;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Illuminate\Support\Facades\DB;

/** MVP rápido (t1-catalogo): los campos de un ítem, para «Nuevo ítem» y «Editar». */
final class FormularioProducto
{
    /** @return list<mixed> */
    public static function campos(bool $conPrecio): array
    {
        return array_values(array_filter([
            TextInput::make('codigo')->label('Código')->required()->maxLength(40)->disabled(! $conPrecio)->dehydrated($conPrecio),
            TextInput::make('descripcion')->label('Descripción')->required()->maxLength(500),
            TextInput::make('descripcion_corta')->label('Descripción corta')->maxLength(150),
            Select::make('familia_producto_id')->label('Familia')->searchable()
                ->options(fn (): array => FamiliaProducto::query()->with('padre')->whereNotNull('parent_id')->get()->sortBy(fn (FamiliaProducto $f): string => $f->ruta())->mapWithKeys(fn (FamiliaProducto $f): array => [$f->id => $f->ruta()])->all()),
            Select::make('unidad_medida_id')->label('Unidad')->required()->searchable()
                ->options(fn (): array => DB::table('unidades_medida')->orderBy('nombre')->pluck('nombre', 'id')->all()),
            Select::make('linea_solucion_id')->label('Línea de solución')->options(fn (): array => DB::table('lineas_solucion')->orderBy('nombre')->pluck('nombre', 'id')->all()),
            Grid::make(2)->schema([
                TextInput::make('marca')->label('Marca')->maxLength(80),
                TextInput::make('modelo')->label('Modelo')->maxLength(100),
            ]),
            Select::make('cubso_id')->label('Código del Estado (CUBSO)')->searchable()
                ->options(fn (): array => DB::table('cubso')->where('es_activo', true)->orderBy('codigo')->selectRaw("id, codigo || ' · ' || descripcion AS t")->pluck('t', 'id')->all()),
            Grid::make(2)->schema([
                Toggle::make('es_servicio')->label('Es servicio'),
                Toggle::make('requiere_serie')->label('Pide número de serie'),
            ]),
            $conPrecio ? Grid::make(3)->schema([
                TextInput::make('precio')->label('Precio sin IGV')->numeric(),
                TextInput::make('costo')->label('Costo referencial')->numeric(),
                TextInput::make('margen')->label('Margen obj. (%)')->numeric(),
            ]) : null,
        ]));
    }

    /** @return array<string, mixed> */
    public static function datos(array $data): array
    {
        return collect($data)->only(['codigo', 'descripcion', 'descripcion_corta', 'familia_producto_id', 'unidad_medida_id', 'linea_solucion_id', 'marca', 'modelo', 'cubso_id', 'es_servicio', 'requiere_serie'])
            ->map(fn ($v) => $v === '' ? null : $v)->all();
    }
}
