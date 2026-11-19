<?php

declare(strict_types=1);

use App\Modules\Nucleo\Enums\TonoEstado;
use App\Modules\Nucleo\Estados\EstadoDefinido;
use App\Modules\Nucleo\Estados\RegistroDocumentos;
use App\Modules\Nucleo\Estados\TransicionDefinida;
use App\Modules\Nucleo\Excepciones\DefinicionDocumentoInvalida;
use Tests\Modules\Nucleo\Fixtures\DefinicionPrueba;

/*
 * Una definición que rompe las reglas del núcleo no se registra (plan 1.5, D1, D2, D4 y D7): el error sale al
 * arrancar la aplicación, no cuando alguien usa el documento.
 */

/**
 * @param  list<EstadoDefinido>|null  $estados
 * @param  list<TransicionDefinida>|null  $transiciones
 */
function registrarDefinicionPrueba(?array $estados = null, ?array $transiciones = null): void
{
    app(RegistroDocumentos::class)->registrar(new DefinicionPrueba($estados, $transiciones));
}

test('la definición de prueba se registra', function () {
    registrarDefinicionPrueba();

    expect(app(RegistroDocumentos::class)->definiciones())->toHaveKey('nucleo.prueba');
});

test('una clave no se registra dos veces', function () {
    registrarDefinicionPrueba();

    expect(fn () => registrarDefinicionPrueba())->toThrow(DefinicionDocumentoInvalida::class, 'ya hay una definición con esa clave');
});

test('anular sin motivo no se registra', function () {
    $transiciones = [new TransicionDefinida('anular', 'Anular', 'borrador', 'anulado')];

    expect(fn () => registrarDefinicionPrueba(transiciones: $transiciones))->toThrow(DefinicionDocumentoInvalida::class, 'anular siempre exige motivo');
});

test('«Por aprobar» editable no se registra', function () {
    $estados = DefinicionPrueba::estadosBase();
    $estados[1] = new EstadoDefinido('por_aprobar', 'Por aprobar', TonoEstado::PorAprobar, esEditable: true);

    expect(fn () => registrarDefinicionPrueba($estados))->toThrow(DefinicionDocumentoInvalida::class, '«aprobar» requiere aprobación y sale de «por_aprobar», que es editable');
});

test('un documento tiene un solo estado inicial', function () {
    $sinInicial = [new EstadoDefinido('borrador', 'Borrador', TonoEstado::Neutro)];
    $dosIniciales = [...DefinicionPrueba::estadosBase(), new EstadoDefinido('otro', 'Otro', TonoEstado::Neutro, esInicial: true)];

    expect(fn () => registrarDefinicionPrueba($sinInicial, []))->toThrow(DefinicionDocumentoInvalida::class, 'un solo estado inicial y tiene 0')
        ->and(fn () => registrarDefinicionPrueba($dosIniciales))->toThrow(DefinicionDocumentoInvalida::class, 'un solo estado inicial y tiene 2');
});

test('un estado de anulación es final y no editable', function () {
    $estados = DefinicionPrueba::estadosBase();
    $estados[4] = new EstadoDefinido('anulado', 'Anulado', TonoEstado::Anulado, esCancelacion: true);

    expect(fn () => registrarDefinicionPrueba($estados))->toThrow(DefinicionDocumentoInvalida::class, 'debe ser final y no editable');
});

test('una transición une estados que existen y no se repite desde el mismo origen', function () {
    $haciaNada = [new TransicionDefinida('enviar', 'Enviar', 'borrador', 'perdido')];
    $desdeNada = [new TransicionDefinida('enviar', 'Enviar', 'perdido', 'enviado')];
    $repetida = [new TransicionDefinida('enviar', 'Enviar', 'borrador', 'enviado'), new TransicionDefinida('enviar', 'Enviar otra vez', 'borrador', 'aprobado')];

    expect(fn () => registrarDefinicionPrueba(transiciones: $haciaNada))->toThrow(DefinicionDocumentoInvalida::class, 'va a «perdido»')
        ->and(fn () => registrarDefinicionPrueba(transiciones: $desdeNada))->toThrow(DefinicionDocumentoInvalida::class, 'sale de «perdido»')
        ->and(fn () => registrarDefinicionPrueba(transiciones: $repetida))->toThrow(DefinicionDocumentoInvalida::class, 'sale dos veces de «borrador»');
});

test('los códigos van en minúsculas y con guiones bajos', function () {
    $transiciones = [new TransicionDefinida('Enviar-Ya', 'Enviar', 'borrador', 'enviado')];

    expect(fn () => registrarDefinicionPrueba(transiciones: $transiciones))->toThrow(DefinicionDocumentoInvalida::class, 'minúsculas');
});

test('un efecto se registra solo si implementa Efecto', function () {
    expect(fn () => app(RegistroDocumentos::class)->efecto('nucleo.prueba.nada', stdClass::class))
        ->toThrow(DefinicionDocumentoInvalida::class, 'no implementa Efecto');
});

test('una condición se registra solo si implementa CondicionTransicion', function () {
    $transiciones = [new TransicionDefinida('enviar', 'Enviar', 'borrador', 'enviado', condiciones: [stdClass::class])];

    expect(fn () => registrarDefinicionPrueba(transiciones: $transiciones))->toThrow(DefinicionDocumentoInvalida::class, 'no implementa CondicionTransicion');
});

test('una transición con aprobación no se marca también como del sistema', function () {
    $transiciones = [new TransicionDefinida('aprobar', 'Aprobar', 'por_aprobar', 'aprobado', requiereAprobacion: true, esDelSistema: true)];

    expect(fn () => registrarDefinicionPrueba(transiciones: $transiciones))->toThrow(DefinicionDocumentoInvalida::class, 'no la marques también como del sistema');
});
