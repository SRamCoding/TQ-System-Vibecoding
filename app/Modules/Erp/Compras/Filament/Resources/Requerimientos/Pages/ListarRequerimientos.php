<?php

declare(strict_types=1);

namespace App\Modules\Erp\Compras\Filament\Resources\Requerimientos\Pages;

use App\Modules\Crm\Catalogo\Services\CatalogoService;
use App\Modules\Erp\Compras\Filament\Compras;
use App\Modules\Erp\Compras\Filament\Resources\Requerimientos\RequerimientoResource;
use App\Modules\Erp\Compras\Models\TipoCompra;
use App\Modules\Erp\Compras\Services\ComprasService;
use App\Modules\Nucleo\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** MVP rápido (t3-compras): la lista, con «Nuevo requerimiento» desde un proyecto. */
final class ListarRequerimientos extends ListRecords
{
    protected static string $resource = RequerimientoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('nuevo')->label('Nuevo requerimiento')->icon('heroicon-o-plus')->slideOver()->modalWidth('5xl')
                ->visible(fn (): bool => Compras::puede(ComprasService::PERMISO_REQUERIR))
                ->schema([
                    Select::make('proyecto_id')->label('Proyecto')->required()->live()->searchable()
                        ->options(fn (): array => DB::table('proyectos as p')->join('estados as e', 'e.id', '=', 'p.estado_id')->whereIn('e.codigo', ['en_preparacion', 'en_ejecucion'])
                            ->orderBy('p.codigo')->selectRaw("p.id, p.codigo || ' · ' || p.nombre AS t")->pluck('t', 'p.id')->all()),
                    TextInput::make('descripcion')->label('Para qué')->required()->maxLength(200)->placeholder('Instalación de postes'),
                    DatePicker::make('necesidad')->label('Se necesita el'),
                    Repeater::make('lineas')->label('Líneas')->required()->minItems(1)->columns(6)->schema([
                        Select::make('tipo')->label('Tipo de compra')->required()->options(fn (): array => TipoCompra::query()->where('es_activo', true)->orderBy('orden')->pluck('nombre', 'codigo')->all())->default('bien_proyecto'),
                        Select::make('producto_id')->label('Del catálogo')->searchable()->live()
                            ->getSearchResultsUsing(fn (string $search): array => collect(app(CatalogoService::class)->buscar($search))->mapWithKeys(fn ($p): array => [$p->id => "{$p->codigo} · {$p->nombre}"])->all())
                            ->afterStateUpdated(function ($state, Set $set): void {
                                if (is_numeric($state)) {
                                    $p = app(CatalogoService::class)->resumen((int) $state);
                                    $set('descripcion', $p->nombre);
                                    $set('unidad_medida_id', $p->unidadMedidaId);
                                }
                            }),
                        TextInput::make('descripcion')->label('Descripción')->required()->maxLength(250)->columnSpan(2),
                        Select::make('unidad_medida_id')->label('Und')->required()->options(fn (): array => DB::table('unidades_medida')->orderBy('codigo')->pluck('codigo', 'id')->all()),
                        TextInput::make('cantidad')->label('Cant.')->numeric()->required()->minValue(0.001),
                        Select::make('partida_control_id')->label('Imputación')->columnSpan(2)
                            ->options(fn (Get $get): array => Compras::partidasDe(is_numeric($get('../../proyecto_id')) ? (int) $get('../../proyecto_id') : null)),
                        TextInput::make('precio_estimado')->label('Precio estimado')->numeric()->helperText('Vacío: el costo del catálogo.'),
                    ]),
                ])
                ->action(function (array $data, Action $action): void {
                    try {
                        /** @var User $u */
                        $u = auth()->user();
                        $req = app(ComprasService::class)->crearRequerimiento((int) $data['proyecto_id'], $data['descripcion'], filled($data['necesidad'] ?? null) ? CarbonImmutable::parse($data['necesidad']) : null,
                            array_map(fn (array $l): array => [...$l, 'cantidad' => (string) $l['cantidad'], 'precio_estimado' => filled($l['precio_estimado'] ?? null) ? (string) $l['precio_estimado'] : null,
                                'partida_control_id' => filled($l['partida_control_id'] ?? null) ? (int) $l['partida_control_id'] : null, 'producto_id' => filled($l['producto_id'] ?? null) ? (int) $l['producto_id'] : null], array_values($data['lineas'])), $u);
                    } catch (RuntimeException $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();
                        $action->halt();

                        return;
                    }

                    $this->redirect(VerRequerimiento::getUrl(['record' => $req]));
                }),
        ];
    }
}
