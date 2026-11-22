<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Filament;

use App\Modules\Crm\Oportunidades\Enums\GanadorConocido;
use App\Modules\Crm\Oportunidades\Enums\LugarFaja;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\VerOportunidad;
use App\Modules\Crm\Oportunidades\OportunidadesServiceProvider;
use App\Modules\Crm\Oportunidades\Services\ContratosDeOportunidad;
use App\Modules\Crm\Oportunidades\Services\FilaFaja;
use App\Modules\Crm\Oportunidades\Services\OportunidadesService;
use App\Modules\Crm\Oportunidades\Services\ProximaAccionDeOportunidades;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * La tabla de un lugar de la faja de vidrio (plan 2.21b, paso 5; pedido B5, D9): la ventana que abren el terminal de
 * cada zona, la pila «+N más» y el «Ver en tabla» de las salas. Una tabla de Filament con datos propios (como
 * `TablaHistorial`) que lee solo `OportunidadesService::tablaDeLaFaja`: búsqueda por número o nombre, orden en su lista
 * blanca y páginas. Tocar una fila abre su caja en la faja (`tq-faja-abrir-fila`; la faja la abre con `tq-faja-abrir`).
 *
 * Columnas que esperan datos que todavía no existen no se muestran (como la D11 de Cuentas): la próxima acción hasta
 * CRM-05 (`ProximaAccionDeOportunidades::existe`; MV-057: la Faja de vidrio ya no existe) y el contrato mientras `ContratosDeOportunidad` sea la de fábrica
 * (`tieneDatos`). Aparecen solas cuando llegan sus módulos.
 */
final class TablaDeLaFaja extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public const string ABRIR_FILA = 'tq-faja-abrir-fila';

    /** La llave de cada fila es «o» + id: con solo el número, PHP la volvería entera. */
    public const string CLAVE = 'o';

    /** Fijo: cada lugar es su propia tabla (la faja la monta con otra llave al cambiar de lugar). */
    #[Locked]
    public string $lugar;

    /** «Mías» en Ganadas y Perdidas del tablero (plan 2.21c, D15): solo las de ese ejecutivo; nulo, todas. */
    #[Locked]
    public ?int $soloDe = null;

    public function mount(): void
    {
        abort_unless(Filament::auth()->user()?->can(OportunidadesServiceProvider::PERMISO_VER) ?? false, 403);
        abort_if(LugarFaja::tryFrom($this->lugar) === null, 404);
    }

    public function table(Table $table): Table
    {
        $lugar = LugarFaja::from($this->lugar);

        return $table
            ->records(fn (?string $search, ?string $sortColumn, ?string $sortDirection, int|string $page, int|string $recordsPerPage): LengthAwarePaginator => $this->pagina($lugar, $search, $sortColumn, $sortDirection, (int) $page, (int) $recordsPerPage))
            ->searchable()
            ->searchPlaceholder('Buscar por número o nombre')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->columns([...self::columnasComunes(), ...($lugar->esSala() ? self::columnasDeSala($lugar) : self::columnasDeZona())])
            ->recordAction('abrirFila')
            ->emptyStateHeading($lugar->esSala() ? "Sin {$lugar->etiqueta()} en el período" : "Sin oportunidades en {$lugar->etiqueta()}")
            ->emptyStateIcon('heroicon-o-cube-transparent');
    }

    /** Tocar una fila: la faja abre esa caja (despiece, paso 6) o, mientras nadie lo haga, su ficha. */
    public function abrirFila(string $clave): void
    {
        $id = (int) ltrim($clave, self::CLAVE);

        $this->dispatch(self::ABRIR_FILA, id: $id, url: VerOportunidad::getUrl(['record' => $id]));
    }

    public function render(): View
    {
        return view('oportunidades::tabla');
    }

    /**
     * @return LengthAwarePaginator<string, array<string, mixed>>
     */
    private function pagina(LugarFaja $lugar, ?string $busqueda, ?string $orden, ?string $direccion, int $pagina, int $porPagina): LengthAwarePaginator
    {
        // Sin orden elegido (o uno que el servicio no admite), el de las cajas: así la tabla empieza como se ve la faja.
        [$orden, $direccion] = in_array($orden, OportunidadesService::ORDENES_FAJA, true)
            ? [$orden, $direccion === 'desc' ? 'desc' : 'asc']
            : $lugar->ordenDeLasCajas();
        $resultado = app(OportunidadesService::class)->tablaDeLaFaja($lugar, $busqueda, $orden, $direccion, $pagina, $porPagina, $this->soloDe);
        $registros = [];

        foreach ($resultado->filas as $fila) {
            $registros[self::CLAVE.$fila->id] = self::registro($fila);
        }

        return new LengthAwarePaginator($registros, $resultado->total, $resultado->porPagina, $resultado->pagina);
    }

    /**
     * Una fila como la dibujan la tabla y el panel del despiece (paso 6): montos, fechas y ganador ya en texto.
     *
     * @return array<string, mixed>
     */
    public static function registro(FilaFaja $fila): array
    {
        $ganador = match ($fila->ganadorConocido) {
            GanadorConocido::Si => $fila->ganador,
            GanadorConocido::No => 'Ninguna empresa',
            GanadorConocido::NoSeSabe => 'No se sabe',
            null => null,
        };

        return [
            'codigo' => $fila->numero,
            'nombre' => $fila->nombre,
            'cuenta' => $fila->cuenta,
            'monto' => PanelesOportunidad::dinero($fila->monto, $fila->simbolo),
            'ejecutivo' => $fila->ejecutivo,
            'dias' => (string) $fila->diasEnEtapa,
            'atascada' => $fila->atascada,
            'proxima' => $fila->proximaAccion,
            'cierre' => ($fila->fechaCierre ?? $fila->cierreEstimado)->format('d/m/Y'),
            'contrato' => $fila->contrato,
            'motivo' => $fila->motivo,
            'ganador' => $ganador,
            'precio' => match (true) {
                $fila->precioDesconocido => 'Precio desconocido',
                $fila->precio === null => null,
                default => PanelesOportunidad::dinero($fila->precio, $fila->precioSimbolo),
            },
        ];
    }

    /** @return list<TextColumn> */
    private static function columnasComunes(): array
    {
        return [
            TextColumn::make('codigo')->label('Código')->fontFamily('mono')->sortable(),
            TextColumn::make('nombre')->label('Oportunidad')->wrap()->sortable(),
            TextColumn::make('cuenta')->label('Cuenta o entidad')->wrap(),
            TextColumn::make('monto')->label('Monto')->alignEnd()->sortable(),
            TextColumn::make('ejecutivo')->label('Ejecutivo'),
        ];
    }

    /** @return list<TextColumn> */
    private static function columnasDeZona(): array
    {
        return [
            TextColumn::make('dias')->label('Días en la etapa')->alignEnd()->sortable()
                ->color(fn (array $record): ?string => $record['atascada'] ? 'warning' : null)
                ->weight(fn (array $record): ?string => $record['atascada'] ? 'bold' : null)
                ->tooltip(fn (array $record): ?string => $record['atascada'] ? 'Atascada: pasa los días máximos de la etapa.' : null),
            // CRM-05 la llena (D5); ámbar si está vencida o sin programar. Hasta entonces no se muestra.
            TextColumn::make('proxima')->label('Próxima acción')->placeholder('Sin programar')
                ->color('warning')
                ->hidden(fn (): bool => ! app(ProximaAccionDeOportunidades::class)->existe()),
            TextColumn::make('cierre')->label('Cierre estimado')->sortable(),
        ];
    }

    /** @return list<TextColumn> */
    private static function columnasDeSala(LugarFaja $lugar): array
    {
        if ($lugar === LugarFaja::Ganadas) {
            return [
                TextColumn::make('cierre')->label('Ganada el')->sortable(),
                // Solo con datos reales de Contratos (decisión del desarrollador, 2026-11-10; ADR 0020, C3).
                TextColumn::make('contrato')->label('Contrato')->fontFamily('mono')->placeholder('—')
                    ->hidden(fn (): bool => ! app(ContratosDeOportunidad::class)->tieneDatos()),
            ];
        }

        return [
            TextColumn::make('cierre')->label('Perdida el')->sortable(),
            TextColumn::make('motivo')->label('Motivo')->wrap()->placeholder('—'),
            TextColumn::make('ganador')->label('Ganador')->wrap()->placeholder('—'),
            TextColumn::make('precio')->label('Precio')->alignEnd()->placeholder('—'),
        ];
    }
}
