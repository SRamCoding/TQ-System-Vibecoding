<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Services;

use App\Modules\Nucleo\Ia\ClienteAnthropic;
use App\Modules\Nucleo\Ia\PedidoModeloIa;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Navegacion\RegistroModulos;
use App\Modules\Nucleo\Numeracion\FormatoNumeracion;
use App\Modules\Nucleo\Tiempo\Calendario;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * MVP rápido (ficha op-asistente, plan 1.14): la conversación del asistente de ayuda. Lo que sabe sale de la ayuda del
 * sistema (las cajas del inicio con su descripción y sus preguntas) y de la pantalla donde está la persona; no ve datos
 * de los registros. Con la IA configurada contesta con ella; si no, busca la pantalla que responde. Ante un problema,
 * propone un ticket; solo se abre si la persona lo confirma. Nunca ejecuta acciones del negocio.
 */
final readonly class AsistenteService
{
    private const array PROBLEMA = ['error', 'falla', 'no funciona', 'no puedo', 'no carga', 'no me deja', 'se cuelga', 'no sale', 'no aparece', 'problema'];

    /** @return list<array{nombre: string, url: string, descripcion: string, preguntas: list<string>}> */
    private function ayuda(User $usuario): array
    {
        $cajas = [];

        foreach (app(RegistroModulos::class)->todas() as $c) {
            if ($c->permiso === null || $usuario->can($c->permiso)) {
                $cajas[] = ['nombre' => $c->nombre, 'url' => $c->url, 'descripcion' => (string) $c->descripcion, 'preguntas' => $c->preguntas ?? []];
            }
        }

        return $cajas;
    }

    /** @return array{texto: string, ticket: ?array{resumen: string}} */
    public function responder(User $usuario, string $pregunta, string $pantalla): array
    {
        $pregunta = mb_substr(trim($pregunta), 0, 500);
        $minus = mb_strtolower($pregunta);
        $esProblema = collect(self::PROBLEMA)->contains(fn (string $p): bool => str_contains($minus, $p));
        $ticket = $esProblema ? ['resumen' => mb_substr($pregunta, 0, 280)] : null;
        $clave = (string) (config('nucleo.ia.claves')['IA_CLAVE_ANTHROPIC'] ?? '');

        if ($clave !== '') {
            try {
                $ayuda = collect($this->ayuda($usuario))->map(fn (array $c): string => "- {$c['nombre']} ({$c['url']}): {$c['descripcion']}. Responde: ".implode(' ', $c['preguntas']))->implode("\n");
                $r = app(ClienteAnthropic::class)->llamar(new PedidoModeloIa(
                    (string) config('nucleo.ia.modelo_asistente', 'claude-haiku-5-5'), 400,
                    "Eres el asistente de ayuda del sistema de Techquk. Responde en español del Perú, corto (2 a 4 frases), amable, sin halagar, neutro en género. Solo ayudas a usar el sistema: di a qué pantalla ir y qué botón usar. No inventes datos de clientes ni de proyectos. Si la persona describe un problema, dilo y sugiere abrir un ticket.\nPantallas disponibles:\n{$ayuda}",
                    "Pantalla actual: {$pantalla}\nPregunta: {$pregunta}", [],
                    ['type' => 'object', 'properties' => ['respuesta' => ['type' => 'string']], 'required' => ['respuesta']], 40,
                ), $clave);

                if (($r->salida['respuesta'] ?? '') !== '') {
                    return ['texto' => (string) $r->salida['respuesta'], 'ticket' => $ticket];
                }
            } catch (Throwable) {
                // Sin IA disponible: sigue el camino sin IA.
            }
        }

        if ($esProblema) {
            return ['texto' => 'Lo siento. Si quieres, abro un ticket con lo que me cuentas y la pantalla donde estás, para que soporte lo revise.', 'ticket' => $ticket];
        }

        // Sin IA: la pantalla cuya descripción o preguntas más se parecen a lo que se pregunta.
        $palabras = array_filter(preg_split('/[^\p{L}\p{N}]+/u', $this->sinTildes($minus)) ?: [], fn (string $p): bool => mb_strlen($p) > 3);
        $mejor = null;
        $puntos = 0;

        foreach ($this->ayuda($usuario) as $c) {
            $texto = $this->sinTildes(mb_strtolower($c['nombre'].' '.$c['descripcion'].' '.implode(' ', $c['preguntas'])));
            $p = count(array_filter($palabras, fn (string $w): bool => str_contains($texto, $w)));

            if ($p > $puntos) {
                [$mejor, $puntos] = [$c, $p];
            }
        }

        return $mejor === null
            ? ['texto' => 'No estoy seguro. Prueba en el inicio: cada caja dice qué preguntas responde al pasar el mouse. Si algo falla, cuéntamelo y abro un ticket.', 'ticket' => null]
            : ['texto' => "Eso se ve en «{$mejor['nombre']}» ({$mejor['url']}): {$mejor['descripcion']}.", 'ticket' => null];
    }

    public function abrirTicket(User $usuario, string $resumen, string $pantalla, string $conversacion): string
    {
        return DB::transaction(function () use ($usuario, $resumen, $pantalla, $conversacion): string {
            $codigo = app(NumeracionService::class)->siguiente('ticket_ayuda', new FormatoNumeracion('TK'), Calendario::ahora());
            DB::table('tickets_ayuda')->insert(['codigo' => $codigo, 'user_id' => $usuario->id, 'pantalla' => mb_substr($pantalla, 0, 200), 'resumen' => mb_substr($resumen, 0, 300),
                'conversacion' => mb_substr($conversacion, 0, 5000), 'estado' => 'abierto', 'created_at' => now(), 'updated_at' => now()]);

            return $codigo;
        });
    }

    /** El catálogo de frases (D5): ayuda por pantalla y, más o menos una de cada cinco, de la vida, con respuestas en botones. */
    public static function frases(): array
    {
        return [
            'pantallas' => [
                '/proyectos' => ['¿Buscas cuánto va ganando un proyecto? Ábrelo: la tarjeta «Costo del proyecto» lo dice.', 'Los proyectos con semáforo ámbar o rojo están gastando más de lo previsto.'],
                '/oportunidades' => ['Las cajas que laten no tienen próxima acción: conviene programarla.', 'Arrastra una oportunidad a la siguiente etapa cuando avance.'],
                '/requerimientos' => ['Cada línea del requerimiento va a una partida: así se ve cómo queda antes de comprar.'],
                '/tablero-pagos' => ['Lo que vence primero está arriba. Una factura con diferencia en el cruce no se paga.'],
                '/resultados' => ['Aquí se responde la pregunta del sistema: cuánto se ganó de verdad en cada proyecto.'],
                '/agenda' => ['Registrar lo que hiciste toma un minuto, y te pide la próxima acción.'],
                '/licitaciones' => ['El histórico de la entidad dice entre qué porcentajes del referencial suele adjudicar.'],
                '/tareo' => ['Al cerrar la semana, las horas pasan a ser costo del proyecto.'],
            ],
            'vida' => [
                ['¿Qué tal empezó la semana?', ['Bien', 'Movida', 'Mejor no pregunto']],
                ['¿Ya tomaste agua hoy?', ['Sí', 'Ahora tomo']],
                ['¿Mucho trabajo hoy?', ['Lo normal', 'Bastante']],
                ['¿Pudiste almorzar tranquilo?', ['Sí', 'A la carrera']],
            ],
            'respuestas' => ['Bien' => '¡Qué bueno!', 'Movida' => 'Ánimo, paso a paso.', 'Mejor no pregunto' => 'Entendido. Aquí estoy si me necesitas.', 'Sí' => 'Bien hecho.',
                'Ahora tomo' => 'Buena idea.', 'Lo normal' => 'Que siga así.', 'Bastante' => 'Si te ayudo con algo, avísame.', 'A la carrera' => 'Que la tarde sea más tranquila.'],
        ];
    }

    private function sinTildes(string $t): string
    {
        return strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    }
}
