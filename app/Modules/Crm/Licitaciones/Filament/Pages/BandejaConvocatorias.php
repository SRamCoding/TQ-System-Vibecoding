<?php

declare(strict_types=1);

namespace App\Modules\Crm\Licitaciones\Filament\Pages;

use App\Modules\Crm\Licitaciones\Services\AsistenteBusquedaSeace;
use App\Modules\Crm\Licitaciones\Services\BandejaSeace;
use App\Modules\Crm\Licitaciones\Services\ConvocatoriasSeaceService;
use App\Modules\Crm\Licitaciones\Services\FiltroConvocatorias;
use App\Modules\Crm\Licitaciones\Services\LicitacionesService;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\TarjetasOportunidades;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\Services\ParametrosService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use RuntimeException;

/**
 * Observación 7.5 (MV-078, lienzo «B · Bandeja con detalle»): la bandeja de Convocatorias del SEACE funciona como un
 * correo: arriba el buscador (con «Buscar en todo el SEACE»), «Leído hace…», Leer ahora y Búsqueda; los chips de líneas
 * de negocio y de «ver» con su cantidad; a la izquierda la lista compacta, a la derecha el detalle de la elegida con
 * Ver en el SEACE, Descartar, Asignar y Registrar como oportunidad. Observación 7.4: abre al instante con lo guardado y
 * lee lo nuevo por detrás.
 */
final class BandejaConvocatorias extends Page
{
    protected static ?string $slug = 'convocatorias';

    protected static ?string $title = 'Convocatorias del SEACE';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-inbox-stack';

    #[Url(as: 'q', except: '')]
    public string $buscar = '';

    #[Url(as: 'todo', except: false)]
    public bool $todo = false;

    #[Url(as: 'linea')]
    public ?int $linea = null;

    #[Url(as: 'ver')]
    public ?string $ver = null;

    /** Observación 8.9: filtros rápidos de la bandeja. */
    #[Url(as: 'region')]
    public ?string $region = null;

    #[Url(as: 'entidad')]
    public ?string $entidad = null;

    #[Url(as: 'orden', except: 'urgencia')]
    public string $orden = 'urgencia';

    #[Url(as: 'c')]
    public ?int $elegida = null;

    /** La configuración de la búsqueda que se edita en el panel «Búsqueda». */
    public array $lineas = [];

    public string $umbral = '';

    public string $porQue = '';

    /** Palabra que se está escribiendo en cada línea: "i-palabras" o "i-negativas" => texto. */
    public array $nuevaPalabra = [];

    public string $nuevaEtiqueta = '';

    public static function canAccess(): bool
    {
        $u = Filament::auth()->user();

        return $u instanceof User && $u->can(LicitacionesService::PERMISO_VER);
    }

    /** Sin encabezado propio: el nombre ya lo dice la barra del proceso. */
    public function getHeading(): string
    {
        return '';
    }

    private static function puedeConfigurar(): bool
    {
        return auth()->user()?->can(AsistenteBusquedaSeace::PERMISO_CONFIGURAR) ?? false;
    }

    /** Observación 9.6: al guardar el asistente, la bandeja se vuelve a dibujar con lo filtrado. */
    #[On('tq-busqueda-guardada')]
    public function alGuardarLaBusqueda(): void {}

    private static function puede(): bool
    {
        return auth()->user()?->can(LicitacionesService::PERMISO_GESTIONAR) ?? false;
    }

    /** Observación 7.4: si la última lectura tiene más de 15 minutos, lee otra; lo nuevo aparece solo. */
    public function leerAlEntrar(): void
    {
        $r = app(ConvocatoriasSeaceService::class)->leerSiHaceFalta();

        if ($r !== null && $r['a_bandeja'] > 0) {
            Notification::make()->success()->title($r['a_bandeja'] === 1 ? 'Llegó 1 convocatoria nueva.' : "Llegaron {$r['a_bandeja']} convocatorias nuevas.")->send();
        }
    }

    public function leerAhora(): void
    {
        if (! self::puede()) {
            return;
        }

        try {
            $r = app(ConvocatoriasSeaceService::class)->sincronizar('a_mano');
            Notification::make()->success()->title("Leídas {$r['leidas']}: {$r['a_bandeja']} nuevas para la bandeja y {$r['actualizadas']} actualizadas.")->send();
        } catch (RuntimeException $e) {
            Notification::make()->warning()->title($e->getMessage())->send();
        }
    }

    public function elegir(int $id): void
    {
        $this->elegida = $id;
        app(BandejaSeace::class)->marcarVista($id, (int) auth()->id());
    }

    /** Flechas ↑ ↓: pasar a la anterior o la siguiente de la lista. */
    public function mover(int $paso, array $ids): void
    {
        $i = array_search($this->elegida, $ids, true);
        $siguiente = $ids[$i === false ? 0 : max(0, min(count($ids) - 1, $i + $paso))] ?? null;

        if ($siguiente !== null) {
            $this->elegir((int) $siguiente);
        }
    }

    public function ponerVer(?string $ver): void
    {
        $this->ver = $this->ver === $ver ? null : $ver;
    }

    public function ponerLinea(?int $linea): void
    {
        $this->linea = $linea;
    }

    public function quitarEtiqueta(string $etiqueta): void
    {
        $c = DB::table('convocatorias_seace')->find($this->elegida);

        if ($c !== null && self::puede()) {
            app(ConvocatoriasSeaceService::class)->etiquetar($c->id, array_values(array_diff((array) json_decode((string) $c->etiquetas, true), [$etiqueta])));
        }
    }

    public function agregarEtiqueta(): void
    {
        $c = DB::table('convocatorias_seace')->find($this->elegida);

        if ($c !== null && self::puede() && trim($this->nuevaEtiqueta) !== '') {
            app(ConvocatoriasSeaceService::class)->etiquetar($c->id, [...(array) json_decode((string) $c->etiquetas, true), $this->nuevaEtiqueta]);
        }

        $this->nuevaEtiqueta = '';
    }

    public function descartarAction(): Action
    {
        return Action::make('descartar')->label('Descartar')->color('gray')->visible(self::puede(...))
            ->schema([Select::make('motivo')->label('Motivo')->required()->options(array_combine($m = ['No es nuestro rubro', 'Fuera de nuestra zona', 'Monto muy alto para nosotros', 'Monto muy bajo', 'Plazo imposible', 'No cumplimos la experiencia'], $m))])
            ->action(function (array $data): void {
                app(ConvocatoriasSeaceService::class)->descartar((int) $this->elegida, $data['motivo']);
                Notification::make()->success()->title('Descartada.')->send();
            });
    }

    public function asignarAction(): Action
    {
        return Action::make('asignar')->label('Asignar')->color('gray')->visible(self::puede(...))
            ->fillForm(fn (): array => ['responsable' => DB::table('convocatorias_seace')->where('id', $this->elegida)->value('responsable_user_id') ?? auth()->id()])
            ->schema([Select::make('responsable')->label('Responsable')->searchable()
                ->options(fn (): array => User::query()->where('es_activo', true)->where('es_sistema', false)->orderBy('name')->pluck('name', 'id')->all())])
            ->action(fn (array $data) => app(ConvocatoriasSeaceService::class)->asignar((int) $this->elegida, filled($data['responsable'] ?? null) ? (int) $data['responsable'] : null));
    }

    public function registrarAction(): Action
    {
        return Action::make('registrar')->label('Registrar como oportunidad')->visible(self::puede(...))
            ->requiresConfirmation()->modalDescription('Entra a Oportunidades como «Por calificar»: ahí se decide si participar.')
            ->action(function (): void {
                try {
                    /** @var User $u */
                    $u = auth()->user();
                    app(ConvocatoriasSeaceService::class)->registrar((int) $this->elegida, $u);
                } catch (RuntimeException $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title('Registrada: está en Oportunidades, por calificar.')
                    ->actions([Action::make('ver')->label('Ver por calificar')->url(TarjetasOportunidades::getUrl(['etapa' => ['por_calificar']]))])->send();
            });
    }

    public function importarAction(): Action
    {
        return Action::make('importar')->label('Importar archivo OCDS')->icon('heroicon-o-arrow-up-tray')->color('gray')->size('sm')->visible(self::puede(...))
            ->modalDescription('Un archivo JSON de contrataciones abiertas (release o record package) descargado del portal de datos abiertos del OECE.')
            ->schema([FileUpload::make('archivo')->label('Archivo JSON')->required()->storeFiles(false)->acceptedFileTypes(['application/json', 'text/plain'])])
            ->action(function (array $data): void {
                try {
                    $r = app(ConvocatoriasSeaceService::class)->importar($data['archivo']->get());
                    Notification::make()->success()->title("Leídas {$r['leidas']}: {$r['a_bandeja']} nuevas para la bandeja.")->send();
                } catch (RuntimeException $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();
                }
            });
    }

    /** Abre el panel «Búsqueda» con la configuración de hoy. */
    public function abrirBusqueda(): void
    {
        $this->lineas = array_map(fn (array $l): array => [...$l, 'minimo' => number_format((float) $l['minimo'], 0, '.', '')], app(FiltroConvocatorias::class)->lineas());
        $this->umbral = (string) app(ParametrosService::class)->entero(ConvocatoriasSeaceService::PARAMETRO_PRIORIDAD);
        $this->dispatch('open-modal', id: 'tq-busqueda-seace');
    }

    public function agregarPalabra(int $i, string $tipo): void
    {
        $clave = "{$i}-{$tipo}";
        $p = mb_strtolower(trim((string) ($this->nuevaPalabra[$clave] ?? '')));

        if ($p !== '' && isset($this->lineas[$i]) && in_array($tipo, ['palabras', 'negativas'], true) && ! in_array($p, $this->lineas[$i][$tipo], true)) {
            $this->lineas[$i][$tipo][] = $p;
        }

        $this->nuevaPalabra[$clave] = '';
    }

    public function quitarPalabra(int $i, string $tipo, int $j): void
    {
        if (isset($this->lineas[$i][$tipo][$j])) {
            array_splice($this->lineas[$i][$tipo], $j, 1);
        }
    }

    public function nuevaLinea(): void
    {
        $this->lineas[] = ['id' => null, 'nombre' => '', 'palabras' => [], 'negativas' => [], 'minimo' => '20000', 'regiones' => [], 'excluidas' => []];
    }

    public function quitarLinea(int $i): void
    {
        array_splice($this->lineas, $i, 1);
    }

    public function guardarBusqueda(): void
    {
        if (! self::puedeConfigurar()) {
            return;
        }

        app(ConvocatoriasSeaceService::class)->guardarLineas($this->lineas);
        // El umbral de prioritaria es un parámetro: se cambia con su acción general (con su versión).
        [$grupo, $clave] = explode('.', ConvocatoriasSeaceService::PARAMETRO_PRIORIDAD, 2);
        $version = DB::table('parametros')->where('grupo', $grupo)->where('clave', $clave)->value('version');

        if ($version !== null && (string) (int) $this->umbral !== (string) app(ParametrosService::class)->entero(ConvocatoriasSeaceService::PARAMETRO_PRIORIDAD)) {
            /** @var User $u */
            $u = auth()->user();
            app(EjecutorAcciones::class)->ejecutar('nucleo.cambiar-parametro', ['parametro' => ConvocatoriasSeaceService::PARAMETRO_PRIORIDAD, 'valor' => (string) (int) $this->umbral, 'version' => (int) $version], $u);
            DB::table('convocatorias_seace')->pluck('id')->each(fn ($id) => app(ConvocatoriasSeaceService::class)->priorizar((int) $id));
        }

        $this->dispatch('close-modal', id: 'tq-busqueda-seace');
        Notification::make()->success()->title('Búsqueda guardada: la bandeja se volvió a filtrar.')->send();
    }

    public function agregarRegion(string $linea, string $region): void
    {
        if (self::puedeConfigurar()) {
            app(ConvocatoriasSeaceService::class)->agregarRegion($linea, $region);
            $this->abrirBusqueda();
            Notification::make()->success()->title('Agregada '.FiltroConvocatorias::nombreRegion($region)." a {$linea}.")->send();
        }
    }

    public function quitarExcluida(string $linea, string $ruc): void
    {
        if (self::puedeConfigurar()) {
            app(ConvocatoriasSeaceService::class)->quitarExcluida($linea, $ruc);
            $this->abrirBusqueda();
            Notification::make()->success()->title("La entidad ya no está excluida de {$linea}.")->send();
        }
    }

    /** Observación 8.9: en la configuración, una región se marca o desmarca en una línea (ninguna = todas). */
    public function alternarRegion(int $i, string $region): void
    {
        if (! isset($this->lineas[$i])) {
            return;
        }

        $actuales = $this->lineas[$i]['regiones'] ?? [];
        $this->lineas[$i]['regiones'] = $region === '*' ? [] : (in_array($region, $actuales, true) ? array_values(array_diff($actuales, [$region])) : [...$actuales, $region]);
    }

    /** Excluir o volver a incluir una entidad (de lo guardado del OECE) en una línea. */
    public function excluirEntidad(int $i, string $ruc): void
    {
        $nombre = (string) DB::table('convocatorias_seace')->where('ruc_entidad', $ruc)->value('entidad');

        if (isset($this->lineas[$i]) && $nombre !== '' && ! collect($this->lineas[$i]['excluidas'] ?? [])->contains('ruc', $ruc)) {
            $this->lineas[$i]['excluidas'][] = ['ruc' => $ruc, 'nombre' => $nombre];
        }

        $this->buscarEntidad[$i] = '';
    }

    public function incluirEntidad(int $i, string $ruc): void
    {
        if (isset($this->lineas[$i])) {
            $this->lineas[$i]['excluidas'] = array_values(array_filter($this->lineas[$i]['excluidas'] ?? [], fn (array $e): bool => $e['ruc'] !== $ruc));
        }
    }

    /** @var array<int, string> lo que se escribe en el buscador de entidades de cada línea */
    public array $buscarEntidad = [];

    /** Observación 9.13: «Recuperar» una descartada (vuelve a la bandeja como nueva). */
    public function recuperar(int $id): void
    {
        if (self::puede()) {
            app(ConvocatoriasSeaceService::class)->recuperar($id);
            Notification::make()->success()->title('Recuperada: volvió a la bandeja.')->send();
        }
    }

    public function traerIgual(int $id): void
    {
        if (self::puede()) {
            app(ConvocatoriasSeaceService::class)->traerIgual($id);
            Notification::make()->success()->title('Traída a la bandeja.')->send();
        }
    }

    public function quitarNegativa(string $linea, string $palabra): void
    {
        if (self::puedeConfigurar()) {
            app(ConvocatoriasSeaceService::class)->quitarNegativa($linea, $palabra);
            $this->abrirBusqueda();
            Notification::make()->success()->title("Quitada «{$palabra}» de {$linea}.")->send();
        }
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('licitaciones::bandeja.pantalla')->viewData(fn (): array => $this->datos()),
        ]);
    }

    /** @return array<string, mixed> */
    private function datos(): array
    {
        $usuario = (int) auth()->id();
        $bandeja = app(BandejaSeace::class);
        $lista = $bandeja->lista(['buscar' => $this->buscar, 'todo' => $this->todo, 'linea' => $this->linea, 'ver' => array_key_exists((string) $this->ver, BandejaSeace::VER) ? $this->ver : null,
            'orden' => array_key_exists($this->orden, BandejaSeace::ORDENES) ? $this->orden : 'urgencia', 'region' => $this->region ?: null, 'entidad' => $this->entidad ?: null], $usuario);
        $ids = array_column($lista['filas'], 'id');

        if ($this->elegida === null || ! in_array($this->elegida, $ids, true)) {
            $this->elegida = $ids[0] ?? null;
        }

        if ($this->elegida !== null) {
            // Abrir una la marca como vista (deja de ser «Nueva» para esa persona) después de dibujar la lista.
            $detalle = $bandeja->detalle($this->elegida, $usuario);
            $bandeja->marcarVista($this->elegida, $usuario);
        }

        return [
            ...$lista,
            'ids' => $ids,
            'detalle' => $detalle ?? null,
            'lectura' => app(ConvocatoriasSeaceService::class)->estadoLectura(),
            'porQueResultado' => $bandeja->porQue($this->porQue),
            'regionesPeru' => FiltroConvocatorias::REGIONES,
            'sugerenciasEntidad' => collect($this->buscarEntidad)->filter(fn ($q): bool => mb_strlen(trim((string) $q)) >= 3)->map(fn ($q): array => DB::table('convocatorias_seace')->whereNotNull('ruc_entidad')
                ->where('entidad', 'ilike', '%'.trim((string) $q).'%')->distinct()->orderBy('entidad')->limit(8)->pluck('entidad', 'ruc_entidad')->all())->all(),
            'puede' => self::puede(),
            // Observación 9.6: la configuración es de la empresa; la cambia quien tiene su permiso.
            'puedeConfigurar' => self::puedeConfigurar(),
            'cambios' => app(ConvocatoriasSeaceService::class)->cambiosDeLaBusqueda(),
            'sinLineas' => app(FiltroConvocatorias::class)->lineas() === [],
        ];
    }
}
