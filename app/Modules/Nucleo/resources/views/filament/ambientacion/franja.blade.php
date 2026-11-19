{{-- Bajo la barra superior, en todas las pantallas: los colores de la variante del día. Recibe `$variante`. --}}
<div class="tq-franja" aria-hidden="true" data-franja="{{ $variante->tema }}-{{ $variante->clave }}"
    style="background: linear-gradient(90deg, {{ implode(', ', array_map(fn (string $c): string => preg_match('/^#[0-9A-Fa-f]{6}$/', $c) === 1 ? $c : 'transparent', $variante->colores)) }})"></div>
