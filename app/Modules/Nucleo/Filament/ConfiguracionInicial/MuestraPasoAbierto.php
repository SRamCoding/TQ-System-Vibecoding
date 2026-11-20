<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\ConfiguracionInicial;

use App\Modules\Nucleo\ConfiguracionInicial\DefinicionPaso;
use App\Modules\Nucleo\ConfiguracionInicial\RegistroPasosConfiguracion;
use App\Modules\Nucleo\Enums\TipoPersona;
use App\Modules\Nucleo\Filament\AccionFilament;
use App\Modules\Nucleo\Filament\Pages\ConfiguracionInicial;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Parties\DatosEmpresa;
use App\Modules\Nucleo\Parties\ValidadorFichaEmpresa;
use App\Modules\Nucleo\Services\EmpresaService;
use App\Modules\Nucleo\Services\ParametrosService;
use Filament\Actions\Action;
use Filament\Clusters\Cluster;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\Page as PaginaDeRecurso;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Throwable;

/**
 * La pantalla de un paso de la configuración inicial, abierta dentro de otra (plan 1.13b, D6): la usan el panel
 * deslizante de Configuración inicial y la ventana de bienvenida, cada una con su marco. Los enlaces de adentro se
 * abren ahí mismo, con la flecha para volver; en el paso de la empresa, «Elegir la ficha de Techquk» y la vista previa
 * del encabezado (plan 1.15). Su vista es `nucleo::filament.configuracion-inicial.paso-abierto`.
 *
 * Quien la usa declara `public ?string $paso` (el paso abierto, o null) y `public ?string $ficha` (otra pantalla
 * abierta dentro, o null: la del paso).
 */
trait MuestraPasoAbierto
{
    public function abierto(): ?DefinicionPaso
    {
        return $this->paso === null ? null : app(RegistroPasosConfiguracion::class)->paso($this->paso);
    }

    /**
     * Un enlace presionado dentro del paso, o una redirección de una pantalla de adentro (pedido del desarrollador,
     * 2026-10-30: «mantener la ficha dentro del panel»): si lleva a una pantalla de este panel que la persona puede ver,
     * se abre ahí mismo; si no (otro sitio, una descarga, sin permiso), devuelve false y el navegador la sigue. Lo que va
     * después de «?» (filtros, pestañas) se pierde: la pantalla abre como recién llegada.
     */
    public function abrirEnPanel(string $ruta): bool
    {
        if ($this->abierto() === null || self::pantallaDe($ruta) === null) {
            return false;
        }

        $this->ficha = $ruta;

        return true;
    }

    public function volver(): void
    {
        $this->ficha = null;
    }

    /** @return array{clase: class-string<Page>, parametros: array<string, mixed>}|null */
    public static function pantallaDe(string $ruta): ?array
    {
        $partes = parse_url($ruta);

        if ($partes === false || isset($partes['host']) || ! str_starts_with($partes['path'] ?? '', '/')) {
            return null;
        }

        try {
            $encontrada = Route::getRoutes()->match(Request::create($partes['path'], 'GET'));
        } catch (Throwable) {
            return null;
        }

        // Una pantalla de Livewire se registra como «Clase@__invoke».
        $accion = $encontrada->getAction('uses');
        $clase = is_string($accion) ? Str::before($accion, '@') : null;

        if (! is_string($clase) || ! str_starts_with((string) $encontrada->getName(), 'filament.app.') || ! is_subclass_of($clase, Page::class) || $clase === ConfiguracionInicial::class) {
            return null;
        }

        // Revisión de arquitectura: si la persona no puede verla, el enlace sigue su camino (la pantalla le dirá que no),
        // en vez de tumbar el asistente con el 403 que la pantalla lanza al montarse.
        return self::puedeAbrir($clase, $encontrada->parameters()) ? ['clase' => $clase, 'parametros' => $encontrada->parameters()] : null;
    }

    /**
     * La misma revisión que hace la pantalla al montarse: una sección (cluster) no, porque solo redirige a su primera
     * pantalla; las de un recurso, con su registro.
     *
     * @param  class-string<Page>  $clase
     * @param  array<string, mixed>  $parametros
     */
    public static function puedeAbrir(string $clase, array $parametros): bool
    {
        if (is_subclass_of($clase, Cluster::class)) {
            return false;
        }

        if (! is_subclass_of($clase, PaginaDeRecurso::class)) {
            return $clase::canAccess();
        }

        // El `canAccess()` de una pantalla de recurso no mira el recurso: eso lo revisa `Resource::canAccess()` aparte.
        $recurso = $clase::getResource();

        if (! $recurso::canAccess()) {
            return false;
        }

        if (! isset($parametros['record'])) {
            return is_subclass_of($clase, CreateRecord::class) ? $recurso::canCreate() : $clase::canAccess();
        }

        $registro = $recurso::resolveRecordRouteBinding($parametros['record']);

        return $registro !== null && match (true) {
            is_subclass_of($clase, ViewRecord::class) => $recurso::canView($registro),
            is_subclass_of($clase, EditRecord::class) => $recurso::canEdit($registro),
            default => $clase::canAccess(['record' => $registro]),
        };
    }

    /**
     * La ficha de la empresa o sus archivos cambiaron dentro del paso: la vista previa del encabezado se vuelve a dibujar
     * (plan 1.15, D4). No hace nada más: basta con que Livewire dibuje de nuevo.
     */
    #[On('ficha-party-cambiada')]
    #[On('archivos-cambiados')]
    public function alCambiarLaFicha(): void {}

    /**
     * Lo que dibuja la vista del paso abierto; sin paso, `pantalla` es null.
     *
     * @return array{abierto: ?DefinicionPaso, pantalla: array{clase: class-string<Page>, parametros: array<string, mixed>}|null, permitida: bool, enFicha: bool, empresa: ?DatosEmpresa, logoEmpresa: ?string}
     */
    private function datosDelPasoAbierto(): array
    {
        $abierto = $this->abierto();
        $ficha = $abierto !== null && $this->ficha !== null ? self::pantallaDe($this->ficha) : null;

        return [
            'abierto' => $abierto,
            'pantalla' => $pantalla = $abierto === null ? null : $ficha ?? $abierto->abrir(),
            // La pantalla del paso, solo si la persona puede verla: si no, se dice en vez de montarla (se caería).
            'permitida' => $pantalla !== null && self::puedeAbrir($pantalla['clase'], $pantalla['parametros']),
            'enFicha' => $ficha !== null,
            // La vista previa del encabezado (plan 1.15, D4): en el paso de la empresa, con la ficha elegida, y no en otra pantalla.
            'empresa' => $empresa = $abierto?->clave === 'nucleo.empresa' && $ficha === null ? app(EmpresaService::class)->datos() : null,
            'logoEmpresa' => $empresa === null ? null : app(EmpresaService::class)->imagenDelLogo($empresa),
        ];
    }

    /** Desmarcar un paso listo (plan 1.13, D3), igual en la página y en la ventana (plan 1.13b, D4). */
    public function reabrirAction(): Action
    {
        return AccionFilament::desde('nucleo.configuracion-inicial.reabrir-paso', fn (array $arguments): array => ['paso' => (string) ($arguments['paso'] ?? '')])
            ->name('reabrir')
            ->label('Reabrir')
            ->link()
            ->color('gray')
            ->size('sm');
    }

    /**
     * Elige la ficha de Techquk (plan 1.13, D2) con la acción de siempre de los parámetros: la versión se toma al abrir la
     * ventana (AGENTS.md: bloqueo optimista al abrir, no al guardar).
     */
    public function elegirFichaAction(): Action
    {
        return AccionFilament::desde(
            'nucleo.cambiar-parametro',
            fn (): array => ['parametro' => EmpresaService::PARAMETRO],
            ocultos: fn (): array => ['descripcion'],
            // Sin etiqueta: AccionFilament le pone la del campo de la acción («Valor»); lo explica la ventana.
            controles: ['valor' => fn () => Select::make('valor')
                ->hiddenLabel()
                ->placeholder('Busca por razón social o RUC')
                ->searchable()
                ->required()
                ->getSearchResultsUsing(fn (string $search): array => self::fichasDeEmpresa(null, $search))
                ->getOptionLabelUsing(fn (mixed $value): ?string => is_string($value) ? (self::fichasDeEmpresa(preg_replace('/\D/', '', $value))[$value] ?? null) : null)
                ->helperText('Si Techquk todavía no tiene ficha, regístrala primero en la lista de este paso.')],
        )
            ->name('elegirFicha')
            ->label(fn (): string => app(EmpresaService::class)->ficha() === null ? 'Elegir la ficha de Techquk' : 'Cambiar la ficha de Techquk')
            ->icon('heroicon-m-arrows-right-left')
            ->size('sm')
            ->modalHeading('La ficha de Techquk')
            ->modalSubmitActionLabel('Elegir')
            ->fillForm(fn (): array => [
                'valor' => ($ficha = app(EmpresaService::class)->ficha()) === null ? null : 'RUC '.$ficha->numero_documento,
                'version' => app(ParametrosService::class)->ficha(EmpresaService::PARAMETRO)['version'],
            ])
            ->after(function (): void {
                $this->ficha = null;
                Notification::make()->success()->title('Ficha de Techquk elegida.')->send();
            });
    }

    /**
     * Las empresas activas, por razón social o RUC. El valor es «RUC 20…» y no el número solo: PHP vuelve entero una clave
     * de solo dígitos y la opción elegida dejaba de coincidir. El parámetro guarda solo los dígitos (su validador).
     *
     * @return array<string, string>
     */
    private static function fichasDeEmpresa(?string $actual, ?string $busqueda = null): array
    {
        $consulta = Party::query()->where('tipo', TipoPersona::Juridica)->where('es_activo', true);

        if ($busqueda !== null && trim($busqueda) !== '') {
            $termino = '%'.mb_strtolower(trim($busqueda)).'%';
            $consulta->where(fn ($query) => $query->whereRaw('lower(razon_social) like ?', [$termino])->orWhere('numero_documento', 'like', $termino));
        } elseif ($actual !== null && $actual !== ValidadorFichaEmpresa::SIN_FICHA) {
            $consulta->where('numero_documento', $actual);
        } else {
            return [];
        }

        return $consulta->orderBy('razon_social')->limit(20)->get(['razon_social', 'numero_documento'])
            ->mapWithKeys(fn (Party $party): array => ['RUC '.$party->numero_documento => "{$party->razon_social} · RUC {$party->numero_documento}"])
            ->all();
    }
}
