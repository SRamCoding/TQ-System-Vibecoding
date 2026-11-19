<?php

declare(strict_types=1);

use App\Modules\Nucleo\Enums\TipoPersona;
use App\Modules\Nucleo\Excepciones\DocumentoIdentidadInvalido;
use App\Modules\Nucleo\Parties\DocumentoIdentidad;

/*
 * Plan 1.3, D4: normalizar, validar (DNI 8 dígitos; RUC 11 con prefijo y módulo 11; los demás, la longitud
 * del catálogo), deducir el tipo de persona y dar la pareja DNI ↔ RUC 10 (D9). Números inventados: los RUC
 * se calcularon con el módulo 11 y se anota la cuenta donde importa.
 */

test('normaliza espacios, guiones y puntos, y pasa a mayúsculas', function (string $escrito, string $guardado) {
    expect(DocumentoIdentidad::normalizar($escrito))->toBe($guardado);
})->with([
    'espacios alrededor' => [' 20123456786 ', '20123456786'],
    'guiones y puntos' => ['20-123.456.786', '20123456786'],
    'espacio en medio' => ['0123 4567', '01234567'],
    'pasaporte en minúsculas' => ['ab-12 345', 'AB12345'],
]);

test('un RUC válido se acepta, también escrito con espacios', function (string $ruc) {
    expect(DocumentoIdentidad::de('6', " {$ruc} ")->numero)->toBe($ruc);
})->with([
    'jurídica' => '20123456786',
    'persona natural' => '10456789124',
    'prefijo 15' => '15765432101',
    'prefijo 16' => '16000000012',
    'prefijo 17' => '17123456785',
    'resto 11 da dígito 1' => '20000003001', // suma 22, 22 mod 11 = 0, 11 − 0 = 11 ⇒ 1
    'resto 10 da dígito 0' => '10012345670', // suma 111, 111 mod 11 = 1, 11 − 1 = 10 ⇒ 0
]);

test('un RUC inválido se rechaza con un mensaje claro', function (string $ruc, string $mensaje) {
    expect(fn () => DocumentoIdentidad::de('6', $ruc))->toThrow(DocumentoIdentidadInvalido::class, $mensaje);
})->with([
    'dígito verificador cambiado' => ['20123456787', 'El RUC 20123456787 no es válido: su último dígito no corresponde'],
    'dígitos invertidos' => ['20123456876', 'no es válido: su último dígito no corresponde'],
    'diez dígitos' => ['2012345678', 'El RUC tiene 11 dígitos.'],
    'doce dígitos' => ['201234567861', 'El RUC tiene 11 dígitos.'],
    'con letra' => ['2012345678A', 'El RUC tiene 11 dígitos.'],
    'prefijo que no existe' => ['30123456789', 'El RUC empieza con 10, 15, 16, 17 o 20.'],
]);

test('el DNI tiene exactamente 8 dígitos', function (string $dni, bool $valido) {
    $leer = fn () => DocumentoIdentidad::de('1', $dni);

    $valido ? expect($leer()->numero)->toBe(DocumentoIdentidad::normalizar($dni))
        : expect($leer)->toThrow(DocumentoIdentidadInvalido::class, 'El DNI tiene 8 dígitos.');
})->with([
    'ocho dígitos' => ['45678912', true],
    'con cero inicial' => ['01234567', true],
    'siete' => ['4567891', false],
    'nueve' => ['456789123', false],
    'con letra' => ['4567891A', false],
]);

test('los demás tipos usan la longitud de su catálogo, si la tienen', function () {
    expect(DocumentoIdentidad::de('4', 'ab123456789')->numero)->toBe('AB123456789')
        ->and(DocumentoIdentidad::de('9', 'X12345', longitud: 6)->numero)->toBe('X12345')
        ->and(fn () => DocumentoIdentidad::de('9', 'X1234', longitud: 6, nombreTipo: 'documento militar'))
        ->toThrow(DocumentoIdentidadInvalido::class, 'El documento militar tiene 6 caracteres.');
});

test('un número vacío o con caracteres raros se rechaza', function (string $numero, string $mensaje) {
    expect(fn () => DocumentoIdentidad::de('7', $numero))->toThrow(DocumentoIdentidadInvalido::class, $mensaje);
})->with([
    'vacío' => ['  ', 'Escribe el número de documento.'],
    'barra' => ['AB/123', 'solo lleva letras y números'],
    'eñe' => ['PEÑA123', 'solo lleva letras y números'],
    'veintiún caracteres' => [str_repeat('A', 21), 'hasta 20 caracteres'],
]);

test('el documento dice si es persona natural o jurídica, salvo cuando no lo sabe', function (string $tipo, string $numero, ?TipoPersona $esperado) {
    expect(DocumentoIdentidad::de($tipo, $numero)->tipoPersona())->toBe($esperado);
})->with([
    'DNI' => ['1', '45678912', TipoPersona::Natural],
    'carné de extranjería' => ['4', '001234567', TipoPersona::Natural],
    'pasaporte' => ['7', 'AB123456', TipoPersona::Natural],
    'RUC 10' => ['6', '10456789124', TipoPersona::Natural],
    'RUC 15' => ['6', '15765432101', TipoPersona::Natural],
    'RUC 16' => ['6', '16000000012', TipoPersona::Natural],
    'RUC 17' => ['6', '17123456785', TipoPersona::Natural],
    'RUC 20' => ['6', '20123456786', TipoPersona::Juridica],
    'no domiciliado' => ['0', 'US123456789', null],
]);

test('la pareja de un RUC 10 es el DNI que contiene, y la de ese DNI es el mismo RUC', function () {
    $ruc = DocumentoIdentidad::de('6', '10456789124');

    expect($ruc->pareja())->toEqual(DocumentoIdentidad::de('1', '45678912'))
        ->and(DocumentoIdentidad::de('1', '45678912')->pareja())->toEqual($ruc);
});

test('un RUC 20 y los demás documentos no tienen pareja', function (string $tipo, string $numero) {
    expect(DocumentoIdentidad::de($tipo, $numero)->pareja())->toBeNull();
})->with([
    'RUC 20' => ['6', '20123456786'],
    'RUC 15' => ['6', '15765432101'],
    'carné de extranjería' => ['4', '001234567'],
    'pasaporte' => ['7', 'AB123456'],
]);

test('el cero inicial de un DNI nunca se pierde, tampoco al ir y volver con su RUC 10', function () {
    // 10 + 01234567 = 1001234567; suma ponderada 111; 111 mod 11 = 1; 11 − 1 = 10 ⇒ dígito 0.
    $dni = DocumentoIdentidad::de('1', ' 0123-4567 ');
    $ruc = $dni->pareja();

    expect($dni->numero)->toBe('01234567')
        ->and(DocumentoIdentidad::digitoVerificadorRuc('1001234567'))->toBe('0')
        ->and($ruc?->codigoTipo)->toBe('6')
        ->and($ruc?->numero)->toBe('10012345670')
        ->and(DocumentoIdentidad::de('6', '10012345670')->pareja()?->numero)->toBe('01234567')
        ->and($ruc?->pareja()?->numero)->toBe('01234567');
});
