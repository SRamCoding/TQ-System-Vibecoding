<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages;

use App\Modules\Crm\Oportunidades\Enums\LugarFaja;
use App\Modules\Crm\Oportunidades\Filament\DibujoDeLaBotonera;
use App\Modules\Crm\Oportunidades\Filament\MovimientoDeOportunidad;
use App\Modules\Crm\Oportunidades\Filament\MuestraLaFicha;
use App\Modules\Crm\Oportunidades\Filament\MuestraLasVistas;
use App\Modules\Crm\Oportunidades\Filament\PanelesOportunidad;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\OportunidadResource;
use App\Modules\Crm\Oportunidades\Filament\VistasDeOportunidades;
use App\Modules\Crm\Oportunidades\OportunidadesServiceProvider;
use App\Modules\Crm\Oportunidades\Services\PortadaOportunidades;
use App\Modules\Crm\Oportunidades\Services\ResumenOportunidad;
use App\Modules\Crm\Oportunidades\Services\TableroDeBotonera;
use App\Modules\Nucleo\Excepciones\ErrorDeNegocio;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;

/**
 * La vista «Tablero» de Oportunidades, la de entrada (plan 2.21c, D1, D2 y D12): la entrada del menú y la caja del
 * inicio abren aquí, también en el celular. Arriba, las pestañas de vista y la fila de cifras (`MuestraLasVistas`).
 * `?aviso=` (los enlaces a la portada de la lista) lleva a la Lista ya filtrada.
 *
 * Plan 2.21d (D3–D7), que reemplaza el tablero de gemas de la 2.21c: cada columna con su gema solo en el título, la
 * botonera con la cantidad que despliega la tabla de la etapa (`abrirEtapa`), los códigos que la persona puso (hasta 5,
 * `alternarEnTablero` por las acciones `crm.oportunidades.fijar-en-tablero` / `soltar-del-tablero`) y «Avanzar a …»
 * con una sola puesta (D6). Arrastrar un código y ese botón van por `tq-oportunidad-mover` →
 * `MueveOportunidades::abrirMovimiento` con el diálogo de la acción y `moverOportunidad` con la versión tomada: el mismo
 * camino que la faja y los botones de la ficha (2.21c, D5). Vista `oportunidades::tablero.botonera`
 * (`DibujoDeLaBotonera`).
 *
 * Tocar un código (doble clic) o una fila abre su ficha en el panel deslizante (`MuestraLaFicha`); `?ficha=<id>` la abre
 * directo.
 */
final class TableroOportunidades extends Page
{
    use MuestraLaFicha;
    use MuestraLasVistas;

    protected static string $resource = OportunidadResource::class;

    protected static ?string $title = 'Tablero de oportunidades';

    protected static ?string $breadcrumb = 'Tablero';

    /** Soltar una tarjeta fuera de las columnas (D4): vuelve y lo dice, sin llamar a nada. */
    public const string SUELTALA_EN = 'Suéltala en la etapa siguiente, en Ganadas (desde Propuesta enviada o Negociación) o en Perdidas.';

    public static function canAccess(array $parameters = []): bool
    {
        return OportunidadResource::usuarioPuede(OportunidadesServiceProvider::PERMISO_VER);
    }

    public static function vista(): string
    {
        return VistasDeOportunidades::TABLERO;
    }

    public function mount(): void
    {
        $aviso = request()->query('aviso');

        if (is_string($aviso) && in_array($aviso, PortadaOportunidades::AVISOS, true)) {
            $this->redirect(ListarOportunidades::getUrl(['aviso' => $aviso]));

            return;
        }

        $this->calcularCifras();
        $this->abrirFichaDeLaDireccion();
    }

    /** La etapa cuya tabla está desplegada debajo del tablero (plan 2.21d, D4), o null. */
    #[Url(as: 'etapa')]
    public ?string $etapaAbierta = null;

    /** La botonera de una etapa (D4): despliega su tabla o la oculta. */
    public function abrirEtapa(string $lugar): void
    {
        $this->etapaAbierta = $this->etapaAbierta === $lugar || LugarFaja::tryFrom($lugar) === null ? null : $lugar;
    }

    /** Marcar en la tabla (D5): la pone en el tablero (hasta 5 por etapa) o la quita. Por la acción registrada. */
    public function alternarEnTablero(int $oportunidadId, bool $poner): void
    {
        try {
            app(EjecutorAcciones::class)->ejecutar($poner ? TableroDeBotonera::FIJAR : TableroDeBotonera::SOLTAR, ['oportunidad_id' => $oportunidadId], self::usuario());
        } catch (ErrorDeNegocio|ValidationException $e) {
            Notification::make()->warning()->title($e->getMessage())->send();
        }
    }

    private static function usuario(): User
    {
        $usuario = Filament::auth()->user();

        return $usuario instanceof User ? $usuario : throw new AuthorizationException;
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    /**
     * @return list<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            PanelesOportunidad::nueva(null, function (mixed $resultado): void {
                Notification::make()->success()->title('Oportunidad creada.')->send();

                if ($resultado instanceof ResumenOportunidad) {
                    $this->redirect(VerOportunidad::getUrl(['record' => $resultado->id]), navigate: true);
                }
            }),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->cabeceraDeVistas(),
            // Plan 2.21d (D3–D6): la gema en el título, la botonera con la cantidad, los códigos puestos y la tabla.
            View::make('oportunidades::tablero.botonera')->viewData(fn (): array => [
                'columnas' => app(DibujoDeLaBotonera::class)->columnas(self::usuario(), $this->etapaAbierta),
                'maximo' => TableroDeBotonera::MAXIMO,
                'fichaAbierta' => $this->ficha,
                'textos' => [
                    'salto' => MovimientoDeOportunidad::NO_SE_SALTA,
                    'atras' => MovimientoDeOportunidad::NO_SE_RETROCEDE,
                    'fuera' => self::SUELTALA_EN,
                    'ganada' => MovimientoDeOportunidad::GANADA,
                    'perdida' => MovimientoDeOportunidad::PERDIDA,
                ],
            ]),
            $this->fichaDeOportunidad(),
        ]);
    }
}
