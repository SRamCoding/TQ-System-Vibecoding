<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages;

use App\Modules\Crm\Oportunidades\Filament\AbreLaTablaDeUnLugar;
use App\Modules\Crm\Oportunidades\Filament\FajaDeVidrio;
use App\Modules\Crm\Oportunidades\Filament\MovimientoDeOportunidad;
use App\Modules\Crm\Oportunidades\Filament\MuestraLasVistas;
use App\Modules\Crm\Oportunidades\Filament\MueveOportunidades;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\OportunidadResource;
use App\Modules\Crm\Oportunidades\Filament\VistasDeOportunidades;
use App\Modules\Crm\Oportunidades\OportunidadesServiceProvider;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

/**
 * La vista «Faja 3D» de Oportunidades (plan 2.21b; desde la 2.21c, D1, una vista extra y ya no el tablero): un mundo en
 * CSS 3D con las cuatro zonas abiertas y sus cajas. Los datos salen de `FajaDeVidrio` (que lee `OportunidadesService`);
 * la cámara, el teclado y el arrastre viven en el navegador (Alpine). Las anuladas no salen. Arriba, las pestañas de
 * vista y la fila de cifras (`MuestraLasVistas`). En pantallas chicas avisa con el enlace al Tablero (D12 de la 2.21c).
 *
 * Paso 4 (D3–D6): soltar una caja llama a `abrirMovimiento`, que elige la acción de la transición y abre su diálogo
 * (los mismos paneles de la ficha, con sus campos). El diálogo no ejecuta: devuelve lo llenado a la faja, que lleva la
 * caja hasta el escáner y en ese momento llama a `moverOportunidad`, que ejecuta la acción real por `EjecutorAcciones`
 * con la versión tomada al agarrar la caja (D4). Desde la 2.21c ese camino es `MueveOportunidades`, el mismo del tablero
 * de gemas. La interfaz no decide reglas: la acción y su servicio validan.
 *
 * Paso 5 (pedido B5): `verLugar` (`AbreLaTablaDeUnLugar`) abre la ventana con la tabla de un lugar (zona o sala),
 * `TablaDeLaFaja`; la faja la pide desde los terminales, la pila «+N más» y el «Ver en tabla» de las salas.
 */
final class FajaOportunidades extends Page
{
    use AbreLaTablaDeUnLugar;
    use MuestraLasVistas;
    use MueveOportunidades;

    protected static string $resource = OportunidadResource::class;

    protected static ?string $title = 'Faja de oportunidades';

    protected static ?string $breadcrumb = 'Faja 3D';

    /** Los destinos fuera de las zonas abiertas: el final de la faja (tubería, paso 5) y la canaleta. */
    public const string GANADA = MovimientoDeOportunidad::GANADA;

    public const string PERDIDA = MovimientoDeOportunidad::PERDIDA;

    public const string NO_SE_SALTA = MovimientoDeOportunidad::NO_SE_SALTA;

    public const string NO_SE_RETROCEDE = MovimientoDeOportunidad::NO_SE_RETROCEDE;

    public static function canAccess(array $parameters = []): bool
    {
        return OportunidadResource::usuarioPuede(OportunidadesServiceProvider::PERMISO_VER);
    }

    public static function vista(): string
    {
        return VistasDeOportunidades::FAJA;
    }

    public function mount(): void
    {
        $this->calcularCifras();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->cabeceraDeVistas(),
            View::make('oportunidades::faja.pantalla')->viewData(fn (): array => app(FajaDeVidrio::class)->datos()),
        ]);
    }
}
