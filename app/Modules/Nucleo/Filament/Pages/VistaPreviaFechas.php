<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Pages;

use App\Modules\Nucleo\Desarrollo\ModoDesarrollo;
use App\Modules\Nucleo\Enums\Ocasion;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Saludo\EscenaGranMomento;
use App\Modules\Nucleo\Saludo\RegistroGranMomento;
use App\Modules\Nucleo\Saludo\VarianteGranMomento;
use App\Modules\Nucleo\Services\GranMomentoService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

/**
 * «Vista previa de fechas» (plan 1.4d, D8): cualquier variante del gran momento, animada o quieta, con el versículo que
 * tocaría el año elegido, para aprobar el estilo. No marca «Lo vi» ni guarda nada. **Solo en desarrollo** (ambiente
 * `local`): vive dentro del modo desarrollo (plan 1.4i, D4) y sin él responde 404; se llega desde su barra o eligiendo un
 gran momento en el sitio, que la abre con `?ocasion=…&variante=…`.
 *
 * @property-read Schema $form
 */
final class VistaPreviaFechas extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = 'Vista previa de fechas';

    /** No va en ningún menú: se llega desde la barra del modo desarrollo (plan 1.4i, D4). */
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Vista previa de fechas';

    protected static ?string $slug = 'vista-previa-fechas';

    /** @var array<string, mixed> */
    public ?array $data = [];

    /**
     * Lo que se está mostrando; nulo si no hay nada abierto.
     *
     * @var ?array{ocasion: string, variante: string, anio: int, animado: bool}
     */
    public ?array $previa = null;

    /** Cambia con cada «Ver»: la escena se vuelve a dibujar y empieza de cero. */
    public int $vez = 0;

    public static function canAccess(): bool
    {
        return Filament::auth()->user() instanceof User;
    }

    /** D7: sin el modo desarrollo no existe, en cada petición. */
    public function boot(): void
    {
        abort_unless(ModoDesarrollo::activo(), 404);
    }

    public function mount(): void
    {
        // Elegida desde el modo desarrollo (D3): la abre ya mostrándose; si no, Navidad.
        $ocasion = Ocasion::tryFrom((string) request()->query('ocasion')) ?? Ocasion::Navidad;
        $pedida = app(RegistroGranMomento::class)->variante($ocasion, (string) request()->query('variante'));
        $variante = $pedida ?? (app(RegistroGranMomento::class)->de($ocasion)[0] ?? null);

        $this->form->fill([
            'ocasion' => $ocasion->value,
            'variante' => $variante?->clave,
            'anio' => ModoDesarrollo::ahoraDecorativo()->year,
        ]);

        if ($pedida !== null) {
            $this->ver(true);
        }
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedSchema::make('form'),
            View::make('nucleo::filament.gran-momento.previa')->viewData(fn (): array => ['escena' => $this->escena(), 'animado' => (bool) ($this->previa['animado'] ?? true), 'vez' => $this->vez]),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Gran momento')
                ->columns(3)
                ->schema([
                    Select::make('ocasion')->label('Fecha')->required()->live()->selectablePlaceholder(false)
                        ->options(fn (): array => $this->ocasionesConVariantes())
                        ->afterStateUpdated(fn (mixed $state) => $this->data['variante'] = (app(RegistroGranMomento::class)->de(Ocasion::from((string) $state))[0] ?? null)?->clave),
                    Select::make('variante')->label('Variante')->required()->selectablePlaceholder(false)
                        ->options(fn (Get $get): array => $this->variantesDe($get('ocasion'))),
                    TextInput::make('anio')->label('Año')->integer()->minValue(2026)->maxValue(2100)->required()
                        ->helperText('Elige el versículo que tocaría ese año.'),
                    Actions::make([
                        Action::make('verAnimado')->label('Ver animado')->icon('heroicon-o-play')->action(fn () => $this->ver(true)),
                        Action::make('verQuieto')->label('Ver quieto')->icon('heroicon-o-pause')->color('gray')->action(fn () => $this->ver(false)),
                    ])->columnSpanFull(),
                ]),
        ]);
    }

    public function ver(bool $animado): void
    {
        $datos = $this->form->getState();
        $this->previa = ['ocasion' => $datos['ocasion'], 'variante' => $datos['variante'], 'anio' => (int) $datos['anio'], 'animado' => $animado];
        $this->vez++;
    }

    public function cerrar(): void
    {
        $this->previa = null;
    }

    private function escena(): ?EscenaGranMomento
    {
        if ($this->previa === null) {
            return null;
        }

        $ocasion = Ocasion::tryFrom((string) $this->previa['ocasion']);

        return $ocasion === null ? null : app(GranMomentoService::class)->previa($ocasion, (string) $this->previa['variante'], (int) $this->previa['anio']);
    }

    /** @return array<string, string> */
    private function ocasionesConVariantes(): array
    {
        $registro = app(RegistroGranMomento::class);
        $opciones = [];

        foreach (Ocasion::cases() as $ocasion) {
            if ($registro->de($ocasion) !== []) {
                $opciones[$ocasion->value] = $ocasion->etiqueta();
            }
        }

        return $opciones;
    }

    /** @return array<string, string> */
    private function variantesDe(mixed $ocasion): array
    {
        $ocasion = is_string($ocasion) ? Ocasion::tryFrom($ocasion) : null;

        return $ocasion === null ? [] : collect(app(RegistroGranMomento::class)->de($ocasion))
            ->mapWithKeys(fn (VarianteGranMomento $variante): array => [$variante->clave => $variante->nombre])->all();
    }
}
