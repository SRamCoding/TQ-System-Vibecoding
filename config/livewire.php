<?php

declare(strict_types=1);

use App\Modules\Nucleo\Adjuntos\ValidadorTamanoAdjunto;

/*
 * Solo lo que cambia de Livewire (el resto, de fábrica). Plan 1.12b, D8: su tope de subida de fábrica era 12 MB, menor
 * que el de adjuntos (20, editable): un archivo de 12 a 20 MB se rechazaba aunque el parámetro lo permitía. Ahora es el
 * techo común con nginx y PHP; el parámetro decide por debajo. Livewire une esta clave entera con la suya, no campo a
 * campo: van todas.
 */
return [
    'temporary_file_upload' => [
        'disk' => env('LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK'),
        'rules' => ['required', 'file', 'max:'.(ValidadorTamanoAdjunto::TECHO_MB * 1024)],
        'directory' => null,
        'middleware' => null,
        // Sin SVG (los adjuntos lo prohíben): una vista previa de un SVG puede ejecutar código en el navegador.
        'preview_mimes' => ['png', 'gif', 'bmp', 'wav', 'mp4', 'mov', 'avi', 'wmv', 'mp3', 'm4a', 'jpg', 'jpeg', 'mpga', 'webp', 'wma'],
        'max_upload_time' => 5,
        'cleanup' => true,
    ],

    /*
    | MV-043: la señal de carga al cambiar de pantalla es la franja de energía bajo la barra de arriba
    | (nucleo::filament.carga-energia), no la barra de progreso de Livewire.
    */
    'navigate' => [
        'show_progress_bar' => false,
        'progress_bar_color' => '#2299dd',
    ],
];
