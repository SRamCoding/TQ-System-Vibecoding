<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Saludo;

use App\Modules\Nucleo\Enums\FueraDeHorario;
use App\Modules\Nucleo\Enums\IdiomaSaludo;
use App\Modules\Nucleo\Enums\MomentoDia;
use App\Modules\Nucleo\Enums\Ocasion;
use App\Modules\Nucleo\Enums\SesionSaludo;
use App\Modules\Nucleo\Enums\TipoFraseSaludo;
use App\Modules\Nucleo\Models\FraseSaludo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Navegacion\AreaModulo;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Tiempo\HorarioEmpresa;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * El saludo por reglas (plan 1.4b, D1 y D2; plan 1.4e, D2 a D4; plan 1.4aa, D6 a D9). Guarda solo el idioma de cada
 * sesión (`HistorialSaludos`).
 * 1. **El idioma es de la sesión** y se queda toda la sesión (1.4aa, D6 y D7). El español domina: otro idioma sale solo
 *    si la sesión anterior de la persona fue en español y hoy no tuvo otro (como mucho una sesión al día, nunca dos
 *    seguidas); entonces, el parámetro `interfaz.saludo_porcentaje_espanol` dice cuántas de cada 100 siguen en español.
 *    El otro idioma se elige entre inglés, francés, portugués y alemán, los que tengan alguna frase (sin frases, algún
 *    saludo), sin repetir el último si hay otro. Fuera de horario, si no hay saludo especial en ese idioma, todo en español.
 * 2. Valen las frases activas cuyas condiciones se cumplen (una condición nula vale siempre) y, en las frases, cuya
 *    categoría está activa para la empresa. Si no hay frase en el idioma, todo sale en español. Las marcadas «fuera de
 *    horario» solo salen fuera de horario (regla 7); las marcadas con una sesión, solo en esa clase de sesión (regla 4).
 * 3. La fecha especial y el día de la semana mandan: si hay frases de la ocasión, salen solo esas; si no, las del día;
 *    si no, las demás. El área no excluye: una frase «del CRM» se suma a las generales.
 * 4. **El saludo, en horario, es de la sesión** (plan 1.4e, D3): se elige al iniciar sesión (`SemillaSesion`) y se
 *    queda mientras se navega; cambia si la sesión pasa a otro momento del día o entra o sale del horario. Si hay
 *    saludos de la clase de sesión (1.4aa, D8: primera del día, vuelve, vuelve después de trabajar), salen 3 de cada 4
 *    veces al volver y 1 de cada 3 en la primera del día; si no, mezcla casuales (sin momento) y propios de la hora
 *    según `interfaz.saludo_porcentaje_de_la_hora`; si uno de los dos grupos no tiene, el otro.
 * 5. **La frase es del tramo** (1.4aa, D9): uno hasta las 13:00 y otro desde las 13:00; como mucho dos al día, estables
 *    en toda sesión, sin las de un momento del día. Rota por día con un punto de partida y un paso propios de la persona;
 *    la de la tarde va corrida dentro de la lista: dos personas no ven por fuerza la misma, el mismo tramo de ayer no se
 *    repite y la de la tarde no es la de la mañana (si hay otra). Si la lista cambió y tocaría una de esas, la siguiente.
 *    Sale en el idioma de la sesión: la sesión en otro idioma (una al día como mucho) ve la del tramo en ese idioma.
 * 6. Días de versículo (pedido del desarrollador, 2026-10-21): cada semana, `interfaz.saludo_dias_versiculo` días de
 *    lunes a viernes (4 por defecto), elegidos por persona y semana, la frase de los dos tramos sale de la categoría
 *    «versiculos». La fecha especial sigue mandando si tiene frases propias y ningún versículo. Si ese día el idioma
 *    no tiene versículo y el español sí, saludo y frase salen en español. Si la empresa apagó la categoría, no hay
 *    versículo.
 * 7. **Fuera de horario** (`HorarioEmpresa`: antes de la hora de inicio, desde la de fin, días no laborables y
 *    feriados), el saludo es uno especial de esa situación o de «cualquiera» (también los saludos «de la noche» si es
 *    de noche), elegido por sesión; la fecha especial sigue mandando. La frase: `interfaz.saludo_porcentaje_frase_especial`
 *    de cada 100 tramos una especial de esa situación (estable en el tramo), y si no, la del tramo; la de una fecha
 *    especial, siempre.
 * 8. Si no hay saludo, el de respaldo de aquí, en el idioma: el saludo nunca se cae.
 */
final readonly class SaludoPorReglas implements GeneradorSaludo
{
    /** @var array<string, string> */
    public const array RESPALDO = ['es' => 'Hola, {nombre}', 'en' => 'Hello, {nombre}', 'fr' => 'Bonjour, {nombre}', 'pt' => 'Olá, {nombre}', 'de' => 'Hallo, {nombre}'];

    /** El código de la categoría de los días de versículo (regla 6). */
    public const string CATEGORIA_VERSICULOS = 'versiculos';

    /** Desde esta hora (Lima), el segundo tramo de la frase (regla 5). */
    public const int HORA_SEGUNDO_TRAMO = 13;

    public function __construct(
        private CalendarioFestivo $calendario,
        private AreaDeUsuario $areas,
        private ParametrosService $parametros,
        private HorarioEmpresa $horario,
        private HistorialSaludos $historial,
    ) {}

    public function para(User $usuario, CarbonImmutable $ahora, string $sesion = ''): Saludo
    {
        $momento = MomentoDia::deLaHora($ahora->hour);
        $fuera = $this->horario->fueraDeHorario($ahora);
        $deSesion = "{$usuario->id}|{$sesion}|".($fuera !== null ? $fuera->value : $momento->value);
        $area = $this->areas->de($usuario);
        $filas = FraseSaludo::query()->activos()->with('categoria')->orderBy('id')->get()
            ->filter(fn (FraseSaludo $fila): bool => $fila->tipo === TipoFraseSaludo::Saludo || $fila->categoria?->estaActivo() === true);
        $contexto = $this->historial->contexto($usuario, $sesion, $ahora);
        $tramo = self::tramo($ahora);

        $idioma = $contexto->idiomaGuardado ?? $this->idioma($filas, $contexto, "{$usuario->id}|{$ahora->toDateString()}|{$sesion}");

        if ($fuera !== null && $idioma !== IdiomaSaludo::Espanol && self::deFueraDeHorario($filas, TipoFraseSaludo::Saludo, $idioma, $fuera, $area, $ahora)->isEmpty()) {
            $idioma = IdiomaSaludo::Espanol;
        }

        // La del mismo tramo de ayer y, de tarde, la de la mañana: si la lista cambió y tocaría una de ellas, la siguiente.
        $diasVersiculo = $this->parametros->entero('interfaz.saludo_dias_versiculo');
        $excluir = [$this->elegir($usuario, $ahora->subDay(), $tramo, $momento, $idioma, $area, $filas, [], $diasVersiculo)['frase']?->id];

        if ($tramo === 1) {
            $excluir[] = $this->elegir($usuario, $ahora, 0, $momento, $idioma, $area, $filas, [], $diasVersiculo)['frase']?->id;
        }

        $hoy = $this->elegir($usuario, $ahora, $tramo, $momento, $idioma, $area, $filas, array_values(array_filter($excluir)), $diasVersiculo);
        $idioma = $hoy['idioma'];

        $saludo = $this->saludoDeSesion($hoy['validas'], $filas, $idioma, $momento, $fuera, $deSesion, $area, $ahora, $contexto->sesion);
        $frase = $hoy['frase'];
        $delTramo = "{$usuario->id}|{$ahora->toDateString()}|{$tramo}|".($fuera !== null ? $fuera->value : '');

        // La frase de una fecha especial manda también fuera de horario (Navidad suele ser feriado).
        if ($fuera !== null && $frase?->ocasion === null && self::numero("{$delTramo}|frase-especial") % 100 < $this->parametros->entero('interfaz.saludo_porcentaje_frase_especial')) {
            $especiales = self::deFueraDeHorario($filas, TipoFraseSaludo::Frase, $idioma, $fuera, $area, $ahora);
            $frase = $especiales->isEmpty() ? $frase : $especiales[self::numero("{$delTramo}|frase") % $especiales->count()];
        }

        if ($contexto->idiomaGuardado === null) {
            $this->historial->registrar($usuario, $sesion, $idioma, $ahora);
        }

        return new Saludo(
            saludo: str_replace('{nombre}', self::nombreDePila($usuario), $saludo->texto ?? self::RESPALDO[$idioma->value]),
            frase: $frase?->texto,
            fuente: $frase?->fuente,
            idioma: $idioma,
            ocasion: $hoy['ocasion'],
            temporada: $this->calendario->temporada($ahora),
        );
    }

    /** El tramo de la frase (regla 5): 0 hasta las 13:00, 1 desde las 13:00. */
    public static function tramo(CarbonImmutable $ahora): int
    {
        return $ahora->hour >= self::HORA_SEGUNDO_TRAMO ? 1 : 0;
    }

    /**
     * Las condiciones del día y la frase del tramo (reglas 2, 3, 5 y 6), sin las marcadas fuera de horario.
     *
     * @param  Collection<int, FraseSaludo>  $filas  las que pueden salir para esta persona
     * @param  list<int>  $excluir  las que no deben repetirse (regla 5)
     * @param  int  $diasVersiculo  días de versículo por semana (regla 6)
     * @return array{validas: Collection<int, FraseSaludo>, frase: ?FraseSaludo, idioma: IdiomaSaludo, ocasion: ?Ocasion}
     */
    private function elegir(User $usuario, CarbonImmutable $dia, int $tramo, MomentoDia $momento, IdiomaSaludo $idioma, ?AreaModulo $area, Collection $filas, array $excluir, int $diasVersiculo): array
    {
        $ocasion = $this->calendario->ocasion($dia, $usuario->cumpleanos_dia, $usuario->cumpleanos_mes);
        $rotacion = "{$usuario->id}|frase";
        $numeroDia = intdiv($dia->startOfDay()->getTimestamp() + $dia->getOffset(), 86400);
        $validas = $filas->filter(fn (FraseSaludo $fila): bool => $fila->fuera_de_horario === null
            && ($fila->momento === null || ($fila->tipo === TipoFraseSaludo::Saludo && $fila->momento === $momento))
            && ($fila->dia_semana === null || $fila->dia_semana === $dia->dayOfWeekIso)
            && ($fila->area === null || $fila->area === $area?->value)
            && ($fila->ocasion === null || $fila->ocasion === $ocasion));

        $deVersiculo = self::esDiaDeVersiculo($usuario->id, $dia, $diasVersiculo);

        if ($deVersiculo && $idioma !== IdiomaSaludo::Espanol && ! self::hayVersiculo($validas, $idioma) && self::hayVersiculo($validas, IdiomaSaludo::Espanol)) {
            $idioma = IdiomaSaludo::Espanol;
        }

        $frase = self::mejor($validas, TipoFraseSaludo::Frase, $idioma, $rotacion, $numeroDia, $tramo, $excluir, $deVersiculo);
        $hayFrases = $validas->contains(fn (FraseSaludo $fila): bool => $fila->tipo === TipoFraseSaludo::Frase);

        if ($frase === null && $hayFrases && $idioma !== IdiomaSaludo::Espanol) {
            $idioma = IdiomaSaludo::Espanol;
            $frase = self::mejor($validas, TipoFraseSaludo::Frase, $idioma, $rotacion, $numeroDia, $tramo, $excluir, $deVersiculo);
        }

        return ['validas' => $validas, 'frase' => $frase, 'idioma' => $idioma, 'ocasion' => $ocasion];
    }

    /**
     * El saludo de la sesión (reglas 4 y 7): la fecha especial manda; fuera de horario, uno especial; en horario, los de
     * la clase de sesión o la mezcla de casuales y propios de la hora.
     *
     * @param  Collection<int, FraseSaludo>  $validas  las del día, en horario
     * @param  Collection<int, FraseSaludo>  $filas  todas las que pueden salir para esta persona
     */
    private function saludoDeSesion(Collection $validas, Collection $filas, IdiomaSaludo $idioma, MomentoDia $momento, ?FueraDeHorario $fuera, string $deSesion, ?AreaModulo $area, CarbonImmutable $ahora, SesionSaludo $clase): ?FraseSaludo
    {
        $saludos = $validas->filter(fn (FraseSaludo $fila): bool => $fila->tipo === TipoFraseSaludo::Saludo && $fila->idioma === $idioma);
        $deOcasion = $saludos->filter(fn (FraseSaludo $fila): bool => $fila->ocasion !== null);

        if ($deOcasion->isNotEmpty()) {
            return self::deLaSesion($deOcasion, "{$deSesion}|saludo");
        }

        if ($fuera !== null) {
            $especiales = self::deFueraDeHorario($filas, TipoFraseSaludo::Saludo, $idioma, $fuera, $area, $ahora);

            if ($especiales->isNotEmpty()) {
                return self::deLaSesion($especiales, "{$deSesion}|saludo");
            }
        }

        $deLaClase = self::deLaClase($saludos, $clase);
        $quiereDeLaClase = self::numero("{$deSesion}|clase") % 12 < ($clase === SesionSaludo::PrimeraDelDia ? 4 : 9);

        if ($quiereDeLaClase && $deLaClase->isNotEmpty()) {
            return self::deLaSesion($deLaClase, "{$deSesion}|saludo");
        }

        $sinClase = $saludos->filter(fn (FraseSaludo $fila): bool => $fila->sesion === null);
        $deLaHora = $sinClase->filter(fn (FraseSaludo $fila): bool => $fila->momento === $momento);
        $casuales = $sinClase->filter(fn (FraseSaludo $fila): bool => $fila->momento === null);
        $quiereDeLaHora = self::numero("{$deSesion}|mezcla") % 100 < $this->parametros->entero('interfaz.saludo_porcentaje_de_la_hora');
        $grupo = ($quiereDeLaHora && $deLaHora->isNotEmpty()) || $casuales->isEmpty() ? $deLaHora : $casuales;

        return $grupo->isEmpty() ? null : self::deLaSesion($grupo, "{$deSesion}|saludo");
    }

    /**
     * Los saludos de esa clase de sesión (regla 4). Quien vuelve después de trabajar, si no hay de los suyos, los de quien
     * vuelve.
     *
     * @param  Collection<int, FraseSaludo>  $saludos
     * @return Collection<int, FraseSaludo>
     */
    private static function deLaClase(Collection $saludos, SesionSaludo $clase): Collection
    {
        $propios = $saludos->filter(fn (FraseSaludo $fila): bool => $fila->sesion === $clase);

        return $propios->isEmpty() && $clase === SesionSaludo::VuelveConActividad
            ? $saludos->filter(fn (FraseSaludo $fila): bool => $fila->sesion === SesionSaludo::Vuelve)
            : $propios;
    }

    /**
     * Las especiales de fuera de horario de esa situación (o de «cualquiera»); de noche, también las «de la noche» sin
     * marca (regla 7). Respetan el área y el día de la semana, como todas (regla 2).
     *
     * @param  Collection<int, FraseSaludo>  $filas
     * @return Collection<int, FraseSaludo>
     */
    private static function deFueraDeHorario(Collection $filas, TipoFraseSaludo $tipo, IdiomaSaludo $idioma, FueraDeHorario $fuera, ?AreaModulo $area, CarbonImmutable $ahora): Collection
    {
        return $filas->filter(fn (FraseSaludo $fila): bool => $fila->tipo === $tipo && $fila->idioma === $idioma
            && $fila->ocasion === null
            && ($fila->area === null || $fila->area === $area?->value)
            && ($fila->dia_semana === null || $fila->dia_semana === $ahora->dayOfWeekIso)
            && (in_array($fila->fuera_de_horario, [$fuera, FueraDeHorario::Cualquiera], true)
                || ($fuera === FueraDeHorario::Noche && $fila->fuera_de_horario === null && $fila->momento === MomentoDia::Noche)))->values();
    }

    /**
     * La más específica (día de la semana) y, entre ellas, la que toca a la sesión: estable mientras dure.
     *
     * @param  Collection<int, FraseSaludo>  $candidatas
     */
    private static function deLaSesion(Collection $candidatas, string $semilla): FraseSaludo
    {
        $maxima = $candidatas->max(fn (FraseSaludo $fila): int => self::especificidad($fila));
        $empatadas = $candidatas->filter(fn (FraseSaludo $fila): bool => self::especificidad($fila) === $maxima)->values();

        return $empatadas[self::numero($semilla) % $empatadas->count()];
    }

    /**
     * El idioma de una sesión nueva (regla 1). Español, salvo que la sesión pueda ir en otro (la anterior fue en español
     * y hoy no hubo otro) y el porcentaje lo deje; entonces, uno de los otros idiomas con alguna frase, sin repetir el
     * último si hay otro.
     *
     * @param  Collection<int, FraseSaludo>  $filas
     */
    private function idioma(Collection $filas, ContextoSesion $contexto, string $semilla): IdiomaSaludo
    {
        if (! $contexto->puedeOtroIdioma || self::numero("{$semilla}|idioma") % 100 < $this->parametros->entero('interfaz.saludo_porcentaje_espanol')) {
            return IdiomaSaludo::Espanol;
        }

        // La frase decide el idioma (sin frase en él, todo sale en español): cuentan los que tienen frase; si no hay
        // ninguna frase, los que tienen saludo.
        $hayFrases = $filas->contains(fn (FraseSaludo $fila): bool => $fila->tipo === TipoFraseSaludo::Frase);
        $otros = array_values(array_filter(IdiomaSaludo::cases(), fn (IdiomaSaludo $idioma): bool => $idioma !== IdiomaSaludo::Espanol
            && $filas->contains(fn (FraseSaludo $fila): bool => $fila->idioma === $idioma && (! $hayFrases || $fila->tipo === TipoFraseSaludo::Frase))));

        if (count($otros) > 1) {
            $otros = array_values(array_filter($otros, fn (IdiomaSaludo $idioma): bool => $idioma !== $contexto->ultimoOtroIdioma));
        }

        return $otros === [] ? IdiomaSaludo::Espanol : $otros[self::numero("{$semilla}|extra") % count($otros)];
    }

    /**
     * La más específica del tipo e idioma; entre empatadas, la que toca por la rotación del tramo (regla 5), sin las
     * excluidas si hay otra. En un día de versículo, solo los versículos, salvo que la fecha especial tenga frases y
     * ningún versículo (regla 6).
     *
     * @param  Collection<int, FraseSaludo>  $validas
     * @param  list<int>  $excluir
     */
    private static function mejor(Collection $validas, TipoFraseSaludo $tipo, IdiomaSaludo $idioma, string $rotacion, int $numeroDia, int $tramo, array $excluir, bool $deVersiculo = false): ?FraseSaludo
    {
        $candidatas = $validas->filter(fn (FraseSaludo $fila): bool => $fila->tipo === $tipo && $fila->idioma === $idioma);

        if ($candidatas->isEmpty()) {
            return null;
        }

        if ($deVersiculo) {
            $versiculos = $candidatas->filter(fn (FraseSaludo $fila): bool => self::esVersiculo($fila));
            $versiculoDeOcasion = $versiculos->contains(fn (FraseSaludo $fila): bool => $fila->ocasion !== null);
            $fraseDeOcasion = $candidatas->contains(fn (FraseSaludo $fila): bool => $fila->ocasion !== null);

            if ($versiculos->isNotEmpty() && ($versiculoDeOcasion || ! $fraseDeOcasion)) {
                $candidatas = $versiculos;
            }
        }

        $maxima = $candidatas->max(fn (FraseSaludo $fila): int => self::especificidad($fila));
        $empatadas = $candidatas->filter(fn (FraseSaludo $fila): bool => self::especificidad($fila) === $maxima)->values();
        $cantidad = $empatadas->count();
        $paso = self::pasoCoprimo($cantidad, self::numero("{$rotacion}|paso"));
        $indice = (self::numero("{$rotacion}|inicio") + $numeroDia * $paso + $tramo * self::corrimientoDeLaTarde($cantidad, $paso)) % $cantidad;

        for ($vueltas = 1; $vueltas < $cantidad && in_array($empatadas[$indice]->id, $excluir, true); $vueltas++) {
            $indice = ($indice + 1) % $cantidad;
        }

        return $empatadas[$indice];
    }

    /**
     * Si ese día de la semana toca versículo a esa persona (regla 6): `$cantidad` días de lunes a viernes, los de
     * menor número estable para la persona y la semana ISO. Cambian de una semana a otra y entre personas.
     */
    private static function esDiaDeVersiculo(int $usuarioId, CarbonImmutable $dia, int $cantidad): bool
    {
        $diaSemana = $dia->dayOfWeekIso;

        if ($diaSemana > 5 || $cantidad <= 0) {
            return false;
        }

        $semana = $dia->format('o-W');
        $elegidos = collect(range(1, 5))
            ->sortBy(fn (int $d): string => sprintf('%010d-%d', self::numero("{$usuarioId}|{$semana}|versiculo|{$d}"), $d))
            ->take($cantidad);

        return $elegidos->contains($diaSemana);
    }

    /** @param  Collection<int, FraseSaludo>  $validas */
    private static function hayVersiculo(Collection $validas, IdiomaSaludo $idioma): bool
    {
        return $validas->contains(fn (FraseSaludo $fila): bool => $fila->tipo === TipoFraseSaludo::Frase && $fila->idioma === $idioma && self::esVersiculo($fila));
    }

    private static function esVersiculo(FraseSaludo $fila): bool
    {
        return $fila->categoria?->codigo === self::CATEGORIA_VERSICULOS;
    }

    /** Solo la ocasión y el día separan; el momento y el área ya filtraron y no pesan. */
    private static function especificidad(FraseSaludo $fila): int
    {
        return ($fila->ocasion !== null ? 2 : 0) + ($fila->dia_semana !== null ? 1 : 0);
    }

    /**
     * Un paso entre 1 y n-1 que no comparte divisores con n: la rotación pasa por todas antes de repetir y nunca
     * cae dos días seguidos en la misma.
     */
    private static function pasoCoprimo(int $cantidad, int $numero): int
    {
        if ($cantidad < 2) {
            return 0;
        }

        $paso = 1 + $numero % ($cantidad - 1);

        while (self::mcd($paso, $cantidad) !== 1) {
            $paso = $paso % ($cantidad - 1) + 1;
        }

        return $paso;
    }

    /**
     * Cuánto se corre la frase de la tarde respecto de la de la mañana (regla 5): nunca 0 (la tarde no repite la mañana)
     * y, con tres o más, distinto del paso (la tarde no anticipa la mañana siguiente). Cerca de la mitad de la lista.
     */
    private static function corrimientoDeLaTarde(int $cantidad, int $paso): int
    {
        if ($cantidad < 2) {
            return 0;
        }

        $corrimiento = max(1, intdiv($cantidad, 2));

        while ($cantidad > 2 && $corrimiento === $paso) {
            $corrimiento = $corrimiento % ($cantidad - 1) + 1;
        }

        return $corrimiento;
    }

    private static function mcd(int $a, int $b): int
    {
        return $b === 0 ? $a : self::mcd($b, $a % $b);
    }

    /** Un número estable a partir de un texto: el mismo texto da siempre el mismo número. */
    private static function numero(string $texto): int
    {
        return (int) hexdec(substr(hash('sha256', $texto), 0, 8));
    }

    /** El primer nombre de la persona; sin persona (usuario de sistema), el primero de `name`. */
    private static function nombreDePila(User $usuario): string
    {
        $nombres = trim((string) ($usuario->party->nombres ?? $usuario->name));

        return explode(' ', $nombres)[0];
    }
}
