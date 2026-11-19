{{--
    El despiece de una caja (plan 2.21b, paso 6; D10, D14; pedido B3), de `DespieceDeOportunidad`. Cerrado no dibuja
    nada más que su raíz, que escucha `tq-faja-abrir` (lo cancela: ya no se abre la ficha). Abierto: la escena con las
    capas (una por pestaña, de `CapasDelDespiece`) y el panel de lectura con la capa elegida, los requisitos del
    escáner siguiente y los botones, o el cierre en una ganada o perdida. El comportamiento, en `faja/despiece-script`.
    La escena y la pila llevan `wire:ignore.self`: Livewire no les quita las clases ni las medidas que pone la animación.
--}}
@php
    $activa = $abierta === null ? null : (collect($capas)->firstWhere('clave', $capa) ?? $capas[0]);
@endphp
<div
    class="tq-faja-ui tq-despiece"
    data-despiece
    x-data="tqDespiece()"
    x-show="abierta"
    x-cloak
    x-bind:data-abierto="abierta ? 'si' : null"
    x-trap.noreturn.noautofocus="abierta"
    x-on:tq-faja-abrir.window="abrir($event)"
    x-on:keydown.escape.window="esc($event)"
    x-on:resize.window="ajustar()"
    x-on:wheel.stop
    role="dialog"
    aria-modal="true"
    aria-label="{{ $abierta === null ? 'Oportunidad abierta' : 'Oportunidad abierta: '.$abierta['numero'].', '.$abierta['nombre'] }}"
>
    @if ($abierta !== null)
        <div class="tq-despiece-cabeza">
            <div>
                <div class="tq-despiece-lbl">{{ $abierta['cuenta'] }} · {{ $abierta['ejecutivo'] }}</div>
                <h2>{{ $abierta['nombre'] }}</h2>
                <div class="tq-despiece-sub">
                    {{ $abierta['numero'] }} · {{ $abierta['montoCompleto'] }} · <span class="tq-despiece-pastilla">{{ $abierta['pastilla'] }}</span>
                    @if ($abierta['atascada'])
                        · <span class="tq-despiece-atascada">Atascada</span>
                    @endif
                </div>
            </div>
            <button type="button" class="tq-faja-boton" x-on:click="cerrar()" data-volver>Volver · Esc</button>
        </div>

        <div class="tq-despiece-cuerpo">
            <div class="tq-despiece-escena desde" data-escena wire:ignore.self>
                <div class="tq-despiece-pila juntas" data-pila-despiece wire:ignore.self style="--n: {{ count($capas) }}" role="group" aria-label="Capas de la caja">
                    @foreach ($capas as $i => $una)
                        <div
                            @class(['tq-despiece-capa', 'espera' => $una['tipo'] === 'espera', 'on' => $una['clave'] === $activa['clave']])
                            style="--i: {{ $i }}"
                            data-capa-despiece="{{ $una['clave'] }}"
                            x-bind:class="{ on: $wire.capa === @js($una['clave']) }"
                            x-bind:aria-pressed="$wire.capa === @js($una['clave']) ? 'true' : 'false'"
                            aria-pressed="{{ $una['clave'] === $activa['clave'] ? 'true' : 'false' }}"
                            tabindex="0"
                            role="button"
                            aria-label="{{ $una['titulo'] }}{{ $una['tipo'] === 'espera' ? ', sin datos todavía' : '' }}"
                            x-on:click="elegir(@js($una['clave']))"
                            x-on:keydown.enter.prevent="elegir(@js($una['clave']))"
                            x-on:keydown.space.prevent="elegir(@js($una['clave']))"
                        >
                            <div class="tq-despiece-cara tq-despiece-arriba">
                                <div class="tq-despiece-sn">{{ $una['titulo'] }}</div>
                                <div class="tq-despiece-ss">
                                    @if ($una['tipo'] === 'resumen')
                                        <span title="{{ $abierta['montoCompleto'] }}">{{ $abierta['monto'] }}</span> · {{ $abierta['pastilla'] }}
                                    @elseif ($una['tipo'] === 'espera')
                                        Sin datos todavía
                                    @else
                                        Tócala para leerla
                                    @endif
                                </div>
                                <div class="tq-despiece-k">{{ $i + 1 }}/{{ count($capas) }}</div>
                            </div>
                            <div class="tq-despiece-cara tq-despiece-frente"></div>
                            <div class="tq-despiece-cara tq-despiece-der"></div>
                        </div>
                    @endforeach
                </div>
            </div>

            <aside class="tq-despiece-panel" data-panel tabindex="-1" aria-labelledby="tq-despiece-capa-{{ $abierta['id'] }}">
                <h3 id="tq-despiece-capa-{{ $abierta['id'] }}" data-capa-abierta="{{ $activa['clave'] }}">{{ $activa['titulo'] }}</h3>

                @if ($activa['tipo'] === 'resumen')
                    <dl class="tq-despiece-datos" data-resumen>
                        @foreach ($abierta['resumen'] as [$etiqueta, $valor])
                            <div><dt>{{ $etiqueta }}</dt><dd>{{ $valor }}</dd></div>
                        @endforeach
                    </dl>
                @elseif ($activa['tipo'] === 'espera')
                    <p class="tq-despiece-espera" data-espera>{{ $activa['espera'] }}</p>
                @else
                    <div class="tq-despiece-pestana">
                        @livewire($activa['componente'], [$activa['parametro'] => $abierta['id']], key('despiece-'.$abierta['id'].'-'.$activa['clave']))
                    </div>
                @endif

                @if ($abierta['escaner'] !== null)
                    <section class="tq-despiece-escaner" data-escaner-siguiente>
                        <div class="tq-despiece-lbl">{{ $abierta['escaner']['titulo'] }}</div>
                        @if ($abierta['escaner']['requisitos'] === [])
                            <p class="tq-despiece-nota">Este escáner no exige nada más que los datos de su diálogo.</p>
                        @else
                            <ul class="tq-despiece-requisitos">
                                @foreach ($abierta['escaner']['requisitos'] as $requisito)
                                    <li @class(['ok' => $requisito['cumple']]) data-requisito="{{ $requisito['cumple'] ? 'cumple' : 'falta' }}">
                                        <span class="tq-despiece-marca" aria-hidden="true">{{ $requisito['cumple'] ? '✓' : '' }}</span>
                                        <span>{{ $requisito['texto'] }}</span>
                                        <span class="tq-despiece-estado">{{ $requisito['cumple'] ? 'Cumple' : 'Falta' }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        @if ($abierta['escaner']['botones'] === [])
                            <p class="tq-despiece-nota">No tienes permiso para mover esta oportunidad.</p>
                        @else
                            <p class="tq-despiece-nota">Se abre el mismo diálogo que al arrastrar la caja; también puedes volver y arrastrarla.</p>
                            @foreach ($abierta['escaner']['botones'] as $boton)
                                <button type="button" class="tq-despiece-accion {{ $boton['tono'] }}" data-hacia="{{ $boton['hacia'] }}"
                                    x-on:click="mover(@js(['id' => $abierta['id'], 'desde' => $abierta['etapa'], 'hacia' => $boton['hacia'], 'version' => $abierta['version']]))">{{ $boton['etiqueta'] }}</button>
                            @endforeach
                        @endif
                    </section>
                @else
                    <section class="tq-despiece-escaner" data-cierre>
                        <div class="tq-despiece-lbl">Cierre</div>
                        <dl class="tq-despiece-datos">
                            @foreach ($abierta['cierre'] as [$etiqueta, $valor])
                                <div><dt>{{ $etiqueta }}</dt><dd>{{ $valor }}</dd></div>
                            @endforeach
                        </dl>
                    </section>
                @endif
            </aside>
        </div>
    @endif
</div>
