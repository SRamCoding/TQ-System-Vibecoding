<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Saludo;

use App\Modules\Nucleo\Desarrollo\ModoDesarrollo;
use App\Modules\Nucleo\Desarrollo\Piezas\ClimaDelDibujo;
use App\Modules\Nucleo\Desarrollo\Piezas\DibujoDelSaludo;
use App\Modules\Nucleo\Desarrollo\Piezas\LogoDeFiesta;
use App\Modules\Nucleo\Enums\Ocasion;
use App\Modules\Nucleo\Enums\Temporada;
use App\Modules\Nucleo\Enums\TipoFraseSaludo;
use App\Modules\Nucleo\Models\FraseSaludo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Saludo\Piezas\RegistroPiezasDibujo;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Services\PreferenciasUsuarioService;
use Carbon\CarbonImmutable;

/**
 * Qué ambientación ve una persona hoy (plan 1.4b, D5 y D8): la de su fecha especial o, si no hay o todavía no tiene
 * dibujo, la de la temporada. Nada si está apagada para la empresa (`interfaz.ambientacion_activa`) o para la persona.
 */
final class Ambientacion
{
    /** @var array<string, ?VarianteAmbientacion> lo ya calculado en esta petición: la franja, el ícono y el inicio lo piden */
    private array $calculadas = [];

    public function __construct(
        private readonly CalendarioFestivo $calendario,
        private readonly RegistroAmbientaciones $registro,
        private readonly ParametrosService $parametros,
        private readonly PreferenciasUsuarioService $preferencias,
        private readonly RegistroPiezasDibujo $piezas,
    ) {}

    /** Los colores de la franja de una estación por piezas, según la luz (plan 1.4e2). Las fechas, los suyos. */
    private const array COLORES_DE_LUZ = [
        'manana' => ['#A6DAE5', '#8CC084', '#F2A7C3'],
        'tarde' => ['#F6C27A', '#E8A06A', '#8CC084'],
        'noche' => ['#3D4772', '#6B7AB8', '#F2C14E'],
    ];

    /** @param  CarbonImmutable  $ahora  en hora de Lima */
    public function para(User $usuario, CarbonImmutable $ahora): ?VarianteAmbientacion
    {
        // Modo desarrollo (plan 1.4i, D3): la variante elegida en el sitio manda, de cualquier fecha.
        $elegida = ModoDesarrollo::eleccion(DibujoDelSaludo::CLAVE);

        if ($elegida !== null && str_contains($elegida, ':')) {
            [$tema, $claveVariante] = explode(':', $elegida, 2);
            $variante = $this->registro->buscar($tema, $claveVariante);

            if ($variante !== null) {
                return $variante;
            }
        }

        // También la luz: el dibujo por piezas cambia de luz con la hora (plan 1.4e2).
        $clave = "{$usuario->id}|{$ahora->toDateString()}|".RegistroPiezasDibujo::luz($ahora)->value;

        return array_key_exists($clave, $this->calculadas) ? $this->calculadas[$clave] : $this->calculadas[$clave] = $this->calcular($usuario, $ahora);
    }

    /**
     * La fecha cuyo logo propio toca hoy (D9), o nula: el logo de siempre. Vale también sin sesión (la pantalla de
     * ingreso). No mira el cumpleaños: el logo es de la empresa.
     *
     * @param  CarbonImmutable  $ahora  en hora de Lima
     */
    public function ocasionDelLogo(?User $usuario, CarbonImmutable $ahora): ?Ocasion
    {
        // Modo desarrollo (plan 1.4i, D3): el logo elegido en el sitio manda.
        $elegido = ModoDesarrollo::eleccion(LogoDeFiesta::CLAVE);

        if ($elegido !== null) {
            $ocasion = Ocasion::tryFrom($elegido);

            return LogoTechquk::tieneLogoPropio($ocasion) ? $ocasion : null;
        }

        if (! $this->estaActiva($usuario)) {
            return null;
        }

        $ocasion = $this->calendario->ocasion($ahora);

        return LogoTechquk::tieneLogoPropio($ocasion) ? $ocasion : null;
    }

    /**
     * El agradecimiento del aniversario para la barra superior (D10), con los años ya puestos, o nulo: fuera de la
     * ventana del aniversario, sin fecha de fundación, con la ambientación apagada o sin agradecimientos activos. Cada
     * año sale uno, el mismo para todos (rota por los años cumplidos).
     *
     * @param  CarbonImmutable  $ahora  en hora de Lima
     */
    public function agradecimiento(?User $usuario, CarbonImmutable $ahora): ?string
    {
        $anios = $this->estaActiva($usuario) ? $this->calendario->aniosDelAniversario($ahora) : null;

        if ($anios === null) {
            return null;
        }

        $textos = FraseSaludo::query()->activos()->where('tipo', TipoFraseSaludo::Agradecimiento)->orderBy('id')->pluck('texto');

        return $textos->isEmpty() ? null : str_replace('{anios}', (string) $anios, (string) $textos[$anios % $textos->count()]);
    }

    /** Si la ambientación está prendida para la empresa y para la persona; también decide el gran momento (plan 1.4d, D9). */
    public function estaActiva(?User $usuario): bool
    {
        if (! $this->parametros->booleano('interfaz.ambientacion_activa')) {
            return false;
        }

        return $usuario === null || $this->preferencias->siNo($usuario, 'apariencia.ambientacion');
    }

    private function calcular(User $usuario, CarbonImmutable $ahora): ?VarianteAmbientacion
    {
        if (! $this->estaActiva($usuario)) {
            return null;
        }

        $ocasion = $this->calendario->ocasion($ahora, $usuario->cumpleanos_dia, $usuario->cumpleanos_mes);
        $temporada = $this->calendario->temporada($ahora)->value;

        return ($ocasion === null ? null : $this->delTema($ocasion->value, $ahora))
            ?? $this->delTema($temporada, $ahora);
    }

    /**
     * El dibujo del tema: por piezas si ya las tiene (plan 1.4e2: cambia cada día, con la luz de la hora y las capas de
     * clima), y si no, su variante del año de siempre.
     */
    private function delTema(string $tema, CarbonImmutable $ahora): ?VarianteAmbientacion
    {
        $combinacion = $this->piezas->delDia($tema, $ahora);

        if ($combinacion === null) {
            return $this->registro->delAnio($tema, self::anioDelTema($tema, $ahora));
        }

        $luz = RegistroPiezasDibujo::luz($ahora)->value;
        // Una fecha especial conserva los colores de su franja (rojo y blanco en Fiestas Patrias…); una estación, los de la luz.
        $colores = Temporada::tryFrom($tema) !== null ? self::COLORES_DE_LUZ[$luz] : ($this->registro->delAnio($tema, self::anioDelTema($tema, $ahora))->colores ?? self::COLORES_DE_LUZ[$luz]);

        return new VarianteAmbientacion($tema, 'piezas:'.$combinacion->clave(), $combinacion->nombre(), $colores, 'nucleo::filament.ambientacion.compuesto', [
            'combinacion' => $combinacion,
            'luz' => $luz,
            'clima_activo' => $this->parametros->booleano('interfaz.clima_activo'),
            'clima_forzado' => ModoDesarrollo::eleccion(ClimaDelDibujo::CLAVE),
        ]);
    }

    /**
     * Año Nuevo y verano empiezan en diciembre y siguen en enero: cuentan como del año que empieza, para no cambiar
     * de variante a mitad de camino.
     */
    private static function anioDelTema(string $tema, CarbonImmutable $ahora): int
    {
        return in_array($tema, ['anio_nuevo', 'verano'], true) && $ahora->month === 12 ? $ahora->year + 1 : $ahora->year;
    }
}
