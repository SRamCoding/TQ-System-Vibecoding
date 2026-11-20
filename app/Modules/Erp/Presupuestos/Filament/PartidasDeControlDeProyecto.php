<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Filament;

use App\Modules\Erp\Presupuestos\Estados\DefinicionArbolPartidas;
use App\Modules\Erp\Presupuestos\Excepciones\ArbolRechazado;
use App\Modules\Erp\Presupuestos\Models\ArbolPartidas;
use App\Modules\Erp\Presupuestos\Plantillas\PlantillaArbol;
use App\Modules\Erp\Presupuestos\Services\ConsultaArbol;
use App\Modules\Erp\Presupuestos\Services\FilaArbol;
use App\Modules\Erp\Presupuestos\Services\PropuestaArbol;
use App\Modules\Erp\Proyectos\Pestanas\ComponentePestanaProyecto;
use App\Modules\Nucleo\Excepciones\ErrorDeNegocio;
use App\Modules\Nucleo\Filament\AccionFilament;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;
use App\Modules\Nucleo\Services\PlantillasService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;

/**
 * La pestaña «Partidas de control» de la ficha del proyecto (plan 2.10, paso 3; v4, parte 6, «Árbol de partidas de
 * control»): las partidas en orden de código con sangría por nivel, su responsable y su presupuestado (los padres
 * suman sus hojas), y arriba el costo presupuestado, lo repartido y lo que falta. Crear el árbol (con el costo sugerido
 * del presupuesto aprobado, D5), agregar partidas, fijar montos, desactivar y aprobar van por sus acciones
 * registradas; aquí no se calcula nada.
 */
final class PartidasDeControlDeProyecto extends ComponentePestanaProyecto implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    /**
     * La acción registrada de cada origen de «Armar el árbol». Al confirmar se usa esta tabla, no la lista de los
     * disponibles: si algo cambió con el panel abierto, el rechazo lo da el servicio con su mensaje (D7), no la pantalla.
     */
    private const array ACCIONES = [
        'presupuesto' => 'erp.presupuestos.armar-arbol-desde-presupuesto',
        'plantilla' => 'erp.presupuestos.armar-arbol-desde-plantilla',
    ];

    /** Plan 2.11, D9: el panel «Plantillas» abierto, para administrarlas (sin «Usar»). */
    public bool $plantillasAbiertas = false;

    #[On('proyecto-cambiado')]
    #[On('arbol-cambiado')]
    public function alCambiar(): void
    {
        $this->resetTable();
    }

    public function cerrarPlantillas(): void
    {
        $this->plantillasAbiertas = false;
        // Una plantilla nueva o publicada cambia los orígenes de «Armar el árbol».
        $this->resetTable();
    }

    /** El árbol del que se guardan o actualizan plantillas en el panel; nulo si el proyecto no tiene. */
    public function arbolParaPlantillas(): ?int
    {
        return $this->arbol()?->id;
    }

    public function table(Table $table): Table
    {
        $arbol = $this->arbol();
        $enBorrador = $arbol !== null && $arbol->estado->codigo === DefinicionArbolPartidas::BORRADOR;

        return $table
            ->records(fn (): array => $arbol === null ? [] : $this->filas($arbol->id))
            ->paginated(false)
            ->heading($arbol === null ? 'Partidas de control' : "Partidas de control · v{$arbol->numero_version} · {$arbol->estado->nombre}")
            ->description($arbol === null ? null : $this->descripcion($arbol))
            ->columns([
                TextColumn::make('codigo')->label('Código')->fontFamily('mono')
                    ->description(fn (array $record): ?string => $record['es_activa'] ? null : 'Desactivada'),
                // La sangría muestra la profundidad, que es libre (v4: «una partida puede tener hijas sin límite»).
                TextColumn::make('nombre')->label('Partida')->wrap()
                    ->extraAttributes(fn (array $record): array => ['style' => 'padding-inline-start: '.($record['nivel'] * 1.25).'rem'])
                    ->weight(fn (array $record): ?string => $record['es_hoja'] ? null : 'bold')
                    ->description(fn (array $record): ?string => match (true) {
                        $record['es_no_presupuestado'] => 'No presupuestados: la crea el sistema al aprobar',
                        $record['es_gastos_generales'] => 'Gastos generales',
                        default => null,
                    }),
                TextColumn::make('responsable')->label('Responsable'),
                TextColumn::make('presupuestado')->label('Presupuestado')->alignEnd()->fontFamily('mono')
                    ->formatStateUsing(fn (string $state): string => Montos::siVisible($state))
                    ->description(fn (array $record): ?string => $record['es_hoja'] ? null : 'Suma de sus hojas'),
                // Plan 2.9b, D6: de qué título o partida del presupuesto salió; vacía si se armó a mano.
                TextColumn::make('origen')->label('Origen')->placeholder('')->wrap()->color('gray'),
                IconColumn::make('es_hoja')->label('Hoja')->boolean()->alignCenter(),
            ])
            ->headerActions([
                $this->armarArbol(),
                // Plan 2.11, D9: el panel común de plantillas, solo para administrarlas.
                Action::make('plantillas')->label('Plantillas')->icon('heroicon-o-square-2-stack')->color('gray')
                    ->visible(fn (): bool => Filament::auth()->user()?->can('erp.presupuestos.arbol.editar') ?? false)
                    ->action(fn () => $this->plantillasAbiertas = true),
                ...($arbol === null ? [] : $this->accionesDelArbol($arbol, $enBorrador)),
            ])
            ->recordActions([
                ...$this->accionesDeFila($arbol, $enBorrador),
            ])
            ->emptyStateHeading($arbol === null ? 'Este proyecto todavía no tiene árbol de partidas' : 'Sin partidas')
            ->emptyStateDescription($arbol === null
                ? 'El árbol dice cómo se vigila el costo del proyecto. Su suma debe ser igual al costo presupuestado. Ármalo desde el presupuesto aprobado, o créalo vacío y agrégale partidas.'
                : 'Agrega la primera con «Agregar partida», o usa «Armar el árbol».')
            ->emptyStateActions($arbol !== null ? [] : [
                AccionFilament::desde('erp.presupuestos.crear-arbol', fn (): array => ['proyecto_id' => $this->proyectoId], $this->avisarGuardado(...))
                    ->name('crearArbol')->label('Crear el árbol vacío')->icon('heroicon-o-plus')->color('gray')
                    // Plan 2.11, D11: crear el árbol pide ver costos (la acción también lo exige).
                    ->hidden(! Montos::visibles())
                    // D5: el sugerido sale de los costos del presupuesto aprobado.
                    ->modalDescription(fn (): string => app(ConsultaArbol::class)->costoSugerido($this->proyectoId) === null
                        ? 'El proyecto no tiene presupuesto aprobado: escribe el costo presupuestado.'
                        : 'Viene con costo directo + gastos generales del presupuesto aprobado; puedes cambiarlo.')
                    ->fillForm(fn (): array => ['costo_presupuestado' => app(ConsultaArbol::class)->costoSugerido($this->proyectoId)]),
            ]);
    }

    /**
     * @return list<Action>
     */
    private function accionesDelArbol(ArbolPartidas $arbol, bool $enBorrador): array
    {
        return [
            $this->agregar($arbol->id, null)->name('agregarPartida')->label('Agregar partida')->icon('heroicon-o-plus')
                ->hidden(! $enBorrador),
            AccionFilament::desde(DefinicionArbolPartidas::CLAVE.'.aprobar', fn (): array => ['documento_id' => $arbol->id], $this->avisarGuardado(...))
                ->name('aprobarArbol')->label('Aprobar')->icon('heroicon-o-check-circle')->color('success')
                ->fillForm(fn (): array => ['version' => ArbolPartidas::query()->whereKey($arbol->id)->value('version')])
                ->hidden(! $enBorrador),
        ];
    }

    /**
     * @return list<Action>
     */
    private function accionesDeFila(?ArbolPartidas $arbol, bool $enBorrador): array
    {
        return [
            $this->agregar($arbol->id ?? 0, fn (array $record): int => (int) $record['id'])
                ->name('agregarHija')->label('Agregar hija')->icon('heroicon-o-plus-circle')->iconButton()
                ->hidden(fn (array $record): bool => ! $enBorrador || ! $record['es_activa'] || $record['es_no_presupuestado']),
            AccionFilament::desde('erp.presupuestos.editar-partida', fn (array $record): array => ['partida_id' => (int) $record['id']], $this->avisarGuardado(...),
                controles: ['responsable_user_id' => fn (): Field => self::responsable()])
                ->name('editarPartida')->label('Editar')->icon('heroicon-o-pencil-square')->iconButton()
                ->fillForm(fn (array $record): array => ['nombre' => $record['nombre'], 'responsable_user_id' => (int) $record['responsable_user_id'], 'version' => (int) $record['version']])
                ->hidden(fn (array $record): bool => ! $enBorrador || ! $record['es_activa'] || $record['es_no_presupuestado']),
            AccionFilament::desde('erp.presupuestos.fijar-presupuestado', fn (array $record): array => ['partida_id' => (int) $record['id']], $this->avisarGuardado(...))
                ->name('fijarPresupuestado')->label('Presupuestado')->icon('heroicon-o-banknotes')->iconButton()
                ->fillForm(fn (array $record): array => ['monto' => $record['presupuestado'], 'version' => (int) $record['version']])
                ->hidden(fn (array $record): bool => ! $enBorrador || ! Montos::visibles() || ! $record['es_hoja'] || ! $record['es_activa'] || $record['es_no_presupuestado']),
            AccionFilament::desde('erp.presupuestos.desactivar-partida', fn (array $record): array => ['partida_id' => (int) $record['id']], $this->avisarGuardado(...))
                ->name('desactivarPartida')->label('Desactivar')->icon('heroicon-o-eye-slash')->color('gray')->iconButton()
                ->modalDescription('Solo una partida sin monto, sin costos y sin partidas activas debajo. No se borra.')
                ->fillForm(fn (array $record): array => ['version' => (int) $record['version']])
                ->hidden(fn (array $record): bool => ! $enBorrador || ! $record['es_activa'] || $record['es_no_presupuestado']),
        ];
    }

    /**
     * Los orígenes con que se arma el árbol (plan 2.9b, D7), en el panel de «Armar el árbol». Cada uno es su propia
     * acción registrada con su permiso, y solo aparece si la persona puede usarlo y tiene de dónde: agregar uno (la
     * plantilla de la 2.11; un archivo, cuando exista) es sumar una entrada aquí.
     *
     * Cada entrada dice su etiqueta, su acción registrada, si «hay de dónde» y cómo arma su propuesta; el panel solo
     * lee esto, así que el llenado y el envío no cambian al sumar un origen.
     *
     * `propuesta` recibe lo elegido en el panel (la plantilla, 2.11).
     *
     * @return array<string, array{etiqueta: string, accion: string, propuesta: \Closure(array<string, mixed>): PropuestaArbol}> solo los disponibles
     */
    private function origenes(): array
    {
        $usuario = Filament::auth()->user();
        $todos = [
            'presupuesto' => [
                'etiqueta' => 'Desde el presupuesto aprobado',
                'accion' => self::ACCIONES['presupuesto'],
                'hay' => fn (): bool => app(ConsultaArbol::class)->presupuestoAprobado($this->proyectoId) !== null,
                'propuesta' => fn (array $elegido): PropuestaArbol => app(ConsultaArbol::class)->propuestaDesdePresupuesto($this->proyectoId),
            ],
            // Plan 2.11, D7: la plantilla es un origen más, con la misma comprobación de «el árbol se puede llenar».
            'plantilla' => [
                'etiqueta' => 'Desde una plantilla',
                'accion' => self::ACCIONES['plantilla'],
                'hay' => fn (): bool => $this->plantillas() !== [],
                'propuesta' => fn (array $elegido): PropuestaArbol => app(ConsultaArbol::class)->propuestaDesdePlantilla(
                    $this->proyectoId,
                    (int) ($elegido['plantilla_id'] ?? array_key_first($this->plantillas())),
                    null,
                    null,
                    $usuario instanceof User ? $usuario : throw new AuthorizationException,
                ),
            ],
        ];

        $disponibles = [];

        foreach ($todos as $clave => $origen) {
            $permiso = app(RegistroAcciones::class)->obtener($origen['accion'])->permiso();

            if (($permiso === null || ($usuario?->can($permiso) ?? false)) && $origen['hay']()) {
                $disponibles[$clave] = ['etiqueta' => $origen['etiqueta'], 'accion' => $origen['accion'], 'propuesta' => $origen['propuesta']];
            }
        }

        return $disponibles;
    }

    /**
     * «Armar el árbol» (plan 2.9b, D7): un solo botón, solo si el árbol se puede llenar (D3) y hay algún origen para
     * esta persona. Abre el panel deslizante con el origen y la propuesta editable (D5): nombre y responsable por
     * partida, y quitar; el monto y el origen solo se leen. Al confirmar, la acción registrada del origen la vuelve a
     * armar en el servidor y aplica los ajustes.
     */
    private function armarArbol(): Action
    {
        $quitar = fn (Get $get): bool => (bool) $get('quitar');

        return Action::make('armarArbol')->label('Armar el árbol')->icon('heroicon-o-squares-plus')
            ->slideOver()
            ->modalHeading('Armar el árbol de partidas')
            ->modalSubmitActionLabel('Armar el árbol')
            // D11: pide ver costos. Con el panel ya abierto sigue visible aunque el árbol o los orígenes cambien: si no,
            // Filament ignoraría el envío sin aviso; así el servicio lo rechaza diciendo por qué (D7).
            ->visible(fn (): bool => Montos::visibles() && ($this->armandoElArbol()
                || (app(ConsultaArbol::class)->sePuedeLlenar($this->proyectoId) && $this->origenes() !== [])))
            ->fillForm(fn (): array => $this->propuestaEnFormulario((string) array_key_first($this->origenes())))
            ->schema([
                // Cambiar de origen vuelve a armar la propuesta con el nuevo.
                ToggleButtons::make('origen')->label('Desde')->inline()->required()->live()
                    ->options(fn (): array => array_map(fn (array $o): string => $o['etiqueta'], $this->origenes()))
                    ->afterStateUpdated(function (mixed $state, Set $set): void {
                        foreach ($this->propuestaEnFormulario((string) $state) as $campo => $valor) {
                            if ($campo !== 'origen') {
                                $set($campo, $valor);
                            }
                        }
                    }),
                // Plan 2.11: la plantilla (las mías primero y las de la empresa, la versión vigente, D3); elegir otra
                // vuelve a armar la propuesta.
                Select::make('plantilla_id')->label('Plantilla')->required()->live()
                    ->options(fn (): array => $this->plantillas())
                    ->visible(fn (Get $get): bool => $get('origen') === 'plantilla')
                    ->afterStateUpdated(function (mixed $state, Set $set): void {
                        foreach ($this->propuestaEnFormulario('plantilla', ['plantilla_id' => (int) $state]) as $campo => $valor) {
                            if (! in_array($campo, ['origen', 'plantilla_id'], true)) {
                                $set($campo, $valor);
                            }
                        }
                    }),
                Hidden::make('plantilla_version_id'),
                // D8: sugerido (el del borrador vacío o el del presupuesto aprobado) y editable. Solo lo ve quien ve
                // costos: sin ese permiso no se ofrece «Armar el árbol» (D11).
                TextInput::make('costo_presupuestado')->label('Costo presupuestado')->numeric()->minValue(0)->required()
                    ->visible(fn (Get $get): bool => $get('origen') === 'plantilla')
                    ->helperText('La plantilla no trae montos: todo este costo queda por repartir entre sus partidas.'),
                Select::make('responsable_user_id')->label('Responsable de todas')->searchable()
                    ->options(fn (): array => self::usuariosActivos())
                    ->helperText('Por omisión, el jefe del proyecto. Cada partida puede llevar otro.'),
                Repeater::make('partidas')->label('Partidas propuestas')
                    ->addable(false)->deletable(false)->reorderable(false)->collapsible(false)
                    ->itemLabel(fn (array $state): string => (string) ($state['etiqueta'] ?? ''))
                    ->columns(['default' => 1, 'md' => 3])
                    ->schema([
                        Hidden::make('codigo'),
                        Hidden::make('etiqueta'),
                        Hidden::make('es_gastos_generales'),
                        Hidden::make('es_titulo'),
                        TextInput::make('nombre')->label('Nombre')->required()->maxLength(200)
                            ->disabled($quitar),
                        Select::make('responsable_user_id')->label('Responsable')->placeholder('El de todas')->searchable()
                            ->options(fn (): array => self::usuariosActivos())
                            ->disabled($quitar),
                        Toggle::make('quitar')->label('No va')->live()
                            ->disabled(fn (Get $get): bool => (bool) $get('es_gastos_generales'))
                            ->helperText(fn (Get $get): ?string => match (true) {
                                (bool) $get('es_gastos_generales') => 'La de gastos generales no se quita aquí.',
                                (bool) $get('es_titulo') => 'Quita también sus partidas: todo su monto queda por repartir.',
                                default => null,
                            }),
                    ]),
            ])
            ->action(function (Action $action, array $data): void {
                $this->armarDesde($action, $data);
            });
    }

    /**
     * La propuesta de un origen como la ve la persona. Los montos van en la etiqueta solo si puede verlos (ADR 0016).
     *
     * @param  array<string, mixed>  $elegido  lo elegido en el panel: la plantilla
     * @return array<string, mixed>
     */
    private function propuestaEnFormulario(string $origen, array $elegido = []): array
    {
        $armar = $this->origenes()[$origen]['propuesta'] ?? null;

        try {
            $propuesta = $armar === null ? throw ArbolRechazado::conMotivo('Ese origen no está disponible.') : $armar($elegido);
        } catch (ErrorDeNegocio $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            return ['origen' => $origen, 'partidas' => [], 'plantilla_version_id' => null];
        }

        $verMontos = Montos::visibles();
        $deUnaPlantilla = $propuesta->plantillaVersionId !== null;

        return [
            'origen' => $origen,
            'plantilla_id' => $deUnaPlantilla ? (int) ($elegido['plantilla_id'] ?? array_key_first($this->plantillas())) : null,
            'plantilla_version_id' => $propuesta->plantillaVersionId,
            'costo_presupuestado' => $deUnaPlantilla ? app(ConsultaArbol::class)->costoParaLlenar($this->proyectoId) : null,
            'responsable_user_id' => $propuesta->responsableUserId,
            'partidas' => array_map(fn ($n): array => [
                'codigo' => $n->codigo,
                'etiqueta' => implode(' · ', array_filter([$n->codigo, $n->origen, $verMontos && $n->monto !== null ? Montos::texto($n->monto) : null])),
                'es_gastos_generales' => $n->esGastosGenerales,
                'es_titulo' => ! $n->esHoja,
                'nombre' => $n->nombre,
                'responsable_user_id' => null,
                'quitar' => false,
            ], $propuesta->nodos),
        ];
    }

    /**
     * Pasa la propuesta revisada a la acción registrada del origen (nombres y responsables como «PC-…=…», y las
     * quitadas) y la ejecuta como cualquier acción: con su permiso, y el rechazo del servicio como mensaje.
     *
     * @param  array<string, mixed>  $data
     */
    private function armarDesde(Action $action, array $data): void
    {
        $nombres = [];
        $responsables = [];
        $quitar = [];

        foreach ((array) ($data['partidas'] ?? []) as $fila) {
            $codigo = (string) $fila['codigo'];

            if ((bool) ($fila['quitar'] ?? false)) {
                $quitar[] = $codigo;

                continue;
            }

            // Se manda el nombre de todas: el servicio lo valida, y uno igual al propuesto no cambia nada.
            $nombres[] = $codigo.'='.trim((string) ($fila['nombre'] ?? ''));

            if (filled($fila['responsable_user_id'] ?? null)) {
                $responsables[] = $codigo.'='.(int) $fila['responsable_user_id'];
            }
        }

        $usuario = Filament::auth()->user();
        $accion = self::ACCIONES[(string) ($data['origen'] ?? '')] ?? null;

        if ($accion === null) {
            Notification::make()->danger()->title('Elige de dónde armar el árbol.')->send();
            $action->halt();

            return;
        }

        // La propuesta no se pudo armar (su aviso ya salió): no se confirma lo que no se vio (D10).
        if ((array) ($data['partidas'] ?? []) === []) {
            Notification::make()->danger()->title('No hay propuesta que confirmar: elige otro origen u otra plantilla.')->send();
            $action->halt();

            return;
        }

        try {
            app(EjecutorAcciones::class)->ejecutar(app(RegistroAcciones::class)->obtener($accion), array_filter([
                'proyecto_id' => $this->proyectoId,
                // Solo los usa el origen «plantilla» (2.11): la versión revisada (D10) y el costo (D8).
                ...((($data['origen'] ?? null) === 'plantilla') ? [
                    'plantilla_id' => filled($data['plantilla_id'] ?? null) ? (int) $data['plantilla_id'] : null,
                    'plantilla_version_id' => filled($data['plantilla_version_id'] ?? null) ? (int) $data['plantilla_version_id'] : null,
                    'costo_presupuestado' => filled($data['costo_presupuestado'] ?? null) ? (string) $data['costo_presupuestado'] : null,
                ] : []),
                'responsable_user_id' => filled($data['responsable_user_id'] ?? null) ? (int) $data['responsable_user_id'] : null,
                'nombres' => $nombres,
                'responsables' => $responsables,
                'quitar' => $quitar,
            ], fn (mixed $v): bool => $v !== null && $v !== []), $usuario instanceof User ? $usuario : throw new AuthorizationException);
        } catch (ErrorDeNegocio $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
            $action->halt();

            return;
        } catch (ValidationException $e) {
            // Los campos de la acción no son los del panel: el error va como aviso, no a un campo que no existe.
            Notification::make()->danger()->title(collect($e->errors())->flatten()->first() ?? 'Revisa la propuesta.')->send();
            $action->halt();

            return;
        }

        Notification::make()->success()->title('Árbol armado: revísalo y apruébalo cuando cuadre.')->send();
        $this->resetTable();
        $this->dispatch('arbol-cambiado');
    }

    /**
     * @return array<int, string>
     */
    private static function usuariosActivos(): array
    {
        return User::query()->where('es_activo', true)->where('es_sistema', false)->orderBy('name')->pluck('name', 'id')->all();
    }

    /** Si el panel «Armar el árbol» está abierto. */
    private function armandoElArbol(): bool
    {
        return collect($this->mountedActions)->contains(fn (array $montada): bool => ($montada['name'] ?? null) === 'armarArbol');
    }

    /**
     * Las plantillas de árbol que la persona puede usar (2.11, D1 y D3): «Videovigilancia · v2 · mía».
     *
     * @return array<int, string>
     */
    private function plantillas(): array
    {
        $usuario = Filament::auth()->user();

        if (! $usuario instanceof User || ! $usuario->can('erp.presupuestos.arbol.editar')) {
            return [];
        }

        $opciones = [];

        foreach (app(PlantillasService::class)->disponibles(PlantillaArbol::CLAVE, $usuario) as $p) {
            $opciones[$p->id] = "{$p->nombre} · v{$p->numeroVigente} · ".($p->esDeLaEmpresa ? 'de la empresa' : 'mía');
        }

        return $opciones;
    }

    public function render(): View
    {
        return view('presupuestos::partidas-de-control');
    }

    /**
     * «Agregar partida» en la raíz o bajo una partida, con el código siguiente ya escrito.
     *
     * @param  ?\Closure(array<string, mixed>): int  $padre
     */
    private function agregar(int $arbolId, ?\Closure $padre): Action
    {
        $padreDe = fn (mixed $record): ?int => $padre === null || ! is_array($record) ? null : $padre($record);

        return AccionFilament::desde(
            'erp.presupuestos.agregar-partida',
            fn (mixed $record = null): array => ['arbol_id' => $arbolId, 'padre_id' => $padreDe($record)],
            $this->avisarGuardado(...),
            controles: ['responsable_user_id' => fn (): Field => self::responsable()],
        )
            ->slideOver()
            ->fillForm(fn (mixed $record = null): array => [
                'codigo' => app(ConsultaArbol::class)->codigoSiguiente($arbolId, $padreDe($record)),
                'es_gastos_generales' => false,
            ]);
    }

    /**
     * Los usuarios activos (v4: «una persona por partida»); lo valida ArbolPartidasService. Al editar, también el que
     * la partida ya tiene aunque esté inactivo (AGENTS.md: si no, Filament rechaza el valor y no se podría guardar; el
     * servicio pide entonces uno activo).
     */
    private static function responsable(): Field
    {
        return Select::make('responsable_user_id')->searchable()
            ->options(function (mixed $state): array {
                $consulta = User::query()->where(fn ($q) => $q->where('es_activo', true)->where('es_sistema', false));

                if (is_numeric($state)) {
                    $consulta->orWhere('id', (int) $state);
                }

                return $consulta->orderBy('name')->pluck('name', 'id')->all();
            });
    }

    private function arbol(): ?ArbolPartidas
    {
        return app(ConsultaArbol::class)->arbolDe($this->proyectoId);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function filas(int $arbolId): array
    {
        $filas = [];

        foreach (app(ConsultaArbol::class)->filas($arbolId) as $fila) {
            $filas["p{$fila->id}"] = self::fila($fila);
        }

        return $filas;
    }

    /**
     * @return array<string, mixed>
     */
    private static function fila(FilaArbol $fila): array
    {
        return [
            'id' => $fila->id, 'codigo' => $fila->codigo, 'nombre' => $fila->nombre, 'nivel' => $fila->nivel,
            'es_hoja' => $fila->esHoja, 'es_activa' => $fila->esActiva, 'es_gastos_generales' => $fila->esGastosGenerales,
            'es_no_presupuestado' => $fila->esNoPresupuestado, 'responsable' => $fila->responsable, 'responsable_user_id' => $fila->responsableUserId,
            'presupuestado' => $fila->presupuestado, 'version' => $fila->version, 'origen' => $fila->origen,
        ];
    }

    /** El cuadre, y de qué plantilla salió el árbol (2.11, D3): «Creado desde la plantilla Videovigilancia v1 · …». */
    private function descripcion(ArbolPartidas $arbol): string
    {
        $plantilla = app(ConsultaArbol::class)->plantillaDe($arbol);

        return ($plantilla === null ? '' : "Creado desde la plantilla {$plantilla} · ").$this->cuadre($arbol);
    }

    /** «Costo presupuestado 19,525.00 · repartido 19,000.00 · faltan 525.00»: lo que exige aprobar, a la vista. */
    private function cuadre(ArbolPartidas $arbol): string
    {
        if (! Montos::visibles()) {
            return 'Los montos se ven con el permiso de ver costos.';
        }

        $falta = app(ConsultaArbol::class)->porRepartir($arbol->id);
        $repartido = bcsub($arbol->costo_presupuestado, $falta, 2);
        $estado = match (bccomp($falta, '0', 2)) {
            0 => 'cuadra ✓',
            1 => 'faltan '.Montos::texto($falta).' por repartir',
            default => 'se pasa por '.Montos::texto(ltrim($falta, '-')),
        };

        return 'Costo presupuestado '.Montos::texto($arbol->costo_presupuestado).' · repartido '.Montos::texto($repartido)." · {$estado}";
    }

    private function avisarGuardado(): void
    {
        Notification::make()->success()->title('Guardado.')->send();
        $this->resetTable();
        $this->dispatch('arbol-cambiado');
    }
}
