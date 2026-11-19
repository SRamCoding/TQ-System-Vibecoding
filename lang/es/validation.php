<?php

declare(strict_types=1);

/*
 * Mensajes de validación del proyecto (ADR 0011). Solo las reglas que usan los campos de las
 * acciones; una regla nueva necesita su línea o falla TraduccionesValidacionTest.
 * :attribute es la etiqueta del campo.
 */
return [
    'array' => 'El campo :attribute debe ser una lista.',
    'boolean' => 'El campo :attribute debe ser sí o no.',
    'date_format' => 'El campo :attribute debe ser una fecha con el formato :format.',
    'distinct' => 'En :attribute hay un valor repetido.',
    'decimal' => 'El campo :attribute tiene más decimales de los permitidos (:decimal).',
    'enum' => 'El valor elegido en :attribute no es válido.',
    'exists' => 'El valor elegido en :attribute no existe.',
    'in' => 'El valor elegido en :attribute no es válido.',
    'integer' => 'El campo :attribute debe ser un número entero.',
    'max' => [
        'numeric' => 'El campo :attribute no puede ser mayor que :max.',
        'string' => 'El campo :attribute no puede tener más de :max caracteres.',
    ],
    'min' => [
        'numeric' => 'El campo :attribute debe ser al menos :min.',
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
    ],
    'numeric' => 'El campo :attribute debe ser un número.',
    // Plan 1.4h, D3: la regla de la contraseña (Password::defaults()), también al restablecerla desde el ingreso.
    'password' => [
        'letters' => 'La :attribute debe tener al menos una letra.',
        'mixed' => 'La :attribute debe tener al menos una mayúscula y una minúscula.',
        'numbers' => 'La :attribute debe tener al menos un número.',
        'symbols' => 'La :attribute debe tener al menos un carácter especial (por ejemplo, ! # $ % & * ? @).',
        'uncompromised' => 'Esa :attribute apareció en filtraciones de datos: elige otra.',
    ],
    'present' => 'Falta el campo :attribute (puede ir vacío, pero tiene que venir).',
    'regex' => 'El campo :attribute no tiene el formato esperado.',
    'required' => 'El campo :attribute es obligatorio.',
    'string' => 'El campo :attribute debe ser un texto.',

    'attributes' => [],
    'custom' => [],
];
