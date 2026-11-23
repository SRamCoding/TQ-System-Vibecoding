{{-- Observaciones 6, punto 12: los estilos de Subcontratos (tarjetas, panel y ficha), con los colores del ambiente. --}}
<style>
    .tq-sc { --tq-sc-sup: var(--tq-superficie, #fff); --tq-sc-elev: var(--tq-superficie-elevada, #fff); --tq-sc-borde: var(--tq-borde, var(--gray-200)); --tq-sc-suave: var(--tq-texto-suave, var(--gray-500));
        --tq-sc-erp: var(--tq-franja-misionales, #0790A8); --tq-sc-coral: #C2532F; --tq-sc-pagado: #3F8F5A; --tq-sc-poraprobar: #D99A1E; --tq-sc-resto: color-mix(in oklab, var(--tq-sc-suave) 22%, transparent); }
    .dark .tq-sc { --tq-sc-sup: var(--tq-superficie, #33302B); --tq-sc-elev: var(--tq-superficie-elevada, #3B3832); --tq-sc-borde: var(--tq-borde, #45413A); --tq-sc-suave: var(--tq-texto-suave, #A8ADB4);
        --tq-sc-erp: var(--tq-franja-misionales, #7FE9FF); --tq-sc-coral: #F2A58C; --tq-sc-pagado: #7DC48D; --tq-sc-poraprobar: #F2C66B; }
    .tq-sc-num { font-variant-numeric: tabular-nums; }
    .tq-sc-suave { color: var(--tq-sc-suave); }
    .tq-sc-cifras { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 12px; }
    @media (max-width: 1100px) { .tq-sc-cifras { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .tq-sc-cifra { padding: 14px 16px; border-radius: 14px; background: var(--tq-sc-sup); border: 1px solid var(--tq-sc-borde); display: flex; flex-direction: column; gap: 2px; }
    .tq-sc-cifra b { font-size: 20px; font-weight: 800; }
    .tq-sc-cifra span { font-size: 12px; color: var(--tq-sc-suave); }
    .tq-sc-franja { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 10px; }
    .tq-sc-chip { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 999px; font-size: 12px; font-weight: 600; border: 1px solid var(--tq-sc-borde); background: var(--tq-sc-sup); color: inherit; cursor: pointer; }
    .tq-sc-chip[aria-pressed="true"] { background: var(--primary-600); border-color: var(--primary-600); color: #fff; }
    .dark .tq-sc-chip[aria-pressed="true"] { background: var(--primary-400); border-color: var(--primary-400); color: var(--gray-950); }
    .tq-sc-chip small { font-size: 11px; opacity: .8; }
    .tq-sc-limpiar { font-size: 12px; color: var(--primary-600); text-decoration: underline; }
    .dark .tq-sc-limpiar { color: var(--primary-400); }
    .tq-sc-bandeja { border-radius: 16px; background: var(--tq-sc-sup); border: 1px solid var(--tq-sc-borde); overflow: hidden; }
    .tq-sc-bandeja h3 { padding: 12px 18px; font-size: 14px; font-weight: 700; border-bottom: 1px solid var(--tq-sc-borde); }
    .tq-sc-pend { display: grid; grid-template-columns: 36px minmax(0, 1.3fr) minmax(0, 2fr) auto; gap: 14px; align-items: center; padding: 12px 18px; border-bottom: 1px solid var(--tq-sc-borde); }
    .tq-sc-pend:last-child { border-bottom: 0; }
    @media (max-width: 900px) { .tq-sc-pend { grid-template-columns: 36px minmax(0, 1fr); } .tq-sc-pend > :nth-child(n+3) { grid-column: 2; } }
    .tq-sc-cuenta { font-size: 13px; }
    .tq-sc-cuenta b { font-weight: 700; }
    .tq-sc-grilla { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
    @media (max-width: 1000px) { .tq-sc-grilla { grid-template-columns: minmax(0, 1fr); } }
    .tq-sc-tarjeta { position: relative; display: flex; flex-direction: column; gap: 12px; padding: 16px 18px 16px 22px; border-radius: 16px; background: var(--tq-sc-sup); border: 1px solid var(--tq-sc-borde); cursor: pointer; text-align: left; user-select: none; transition: transform .18s ease, box-shadow .18s ease; }
    .tq-sc-tarjeta::before { content: ""; position: absolute; left: 0; top: 14px; bottom: 14px; width: 4px; border-radius: 0 4px 4px 0; background: var(--tq-sc-erp); }
    .tq-sc-tarjeta[data-alarma]::before { background: var(--tq-sc-coral); }
    .tq-sc-tarjeta[data-terminado] { opacity: .78; }
    .tq-sc-tarjeta[data-terminado]::before { background: var(--tq-sc-suave); }
    .tq-sc-tarjeta:hover, .tq-sc-tarjeta:focus-visible { transform: translateY(-2px); box-shadow: 0 10px 24px rgb(0 0 0 / .08); outline: none; }
    .tq-sc-tarjeta:focus-visible { box-shadow: 0 0 0 2px var(--primary-600); }
    @media (prefers-reduced-motion: reduce) { .tq-sc-tarjeta { transition: none; } }
    html.tq-sin-animaciones .tq-sc-tarjeta { transition: none; }
    .tq-sc-cabeza { display: flex; gap: 12px; align-items: flex-start; }
    .tq-sc-avatar { flex: none; width: 40px; height: 40px; border-radius: 12px; display: grid; place-items: center; font-size: 14px; font-weight: 700; color: #fff; background: var(--gray-700); }
    .dark .tq-sc-avatar { background: var(--gray-600); }
    .tq-sc-corta { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .tq-sc-insignia { display: inline-flex; align-items: center; gap: 4px; padding: 2px 9px; border-radius: 999px; font-size: 11px; font-weight: 700; white-space: nowrap; border: 1px solid var(--tq-sc-borde); color: var(--tq-sc-suave); }
    .tq-sc-insignia[data-tono="por_aprobar"] { color: var(--tq-sc-poraprobar); border-color: currentColor; }
    .tq-sc-insignia[data-tono="liberar"] { color: var(--tq-sc-erp); border-color: currentColor; }
    .tq-sc-barra { position: relative; }
    .tq-sc-barra-pista { display: flex; height: 12px; border-radius: 999px; overflow: hidden; background: var(--tq-sc-resto); }
    .tq-sc-barra-pista > span { height: 100%; }
    .tq-sc-leyenda { position: absolute; z-index: 10; left: 0; top: calc(100% + 6px); display: grid; grid-template-columns: auto auto; gap: 3px 14px; padding: 10px 12px; border-radius: 12px; background: var(--tq-sc-elev); border: 1px solid var(--tq-sc-borde); box-shadow: 0 10px 24px rgb(0 0 0 / .14); font-size: 12px; opacity: 0; transform: translateY(-4px); pointer-events: none; transition: opacity .15s ease, transform .15s ease; }
    .tq-sc-barra:hover .tq-sc-leyenda, .tq-sc-barra:focus-within .tq-sc-leyenda, .tq-sc-barra[data-fija] .tq-sc-leyenda { opacity: 1; transform: none; }
    .tq-sc-barra[data-fija] .tq-sc-leyenda { position: static; margin-top: 8px; box-shadow: none; }
    .tq-sc-punto { display: inline-block; width: 9px; height: 9px; border-radius: 3px; margin-right: 6px; vertical-align: baseline; }
    .tq-sc-datos { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; font-size: 12px; }
    .tq-sc-datos b { display: block; font-size: 14px; font-weight: 700; }
    .tq-sc-aviso { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 700; color: var(--tq-sc-coral); }
    .tq-sc-aviso[data-gris] { color: var(--tq-sc-suave); font-weight: 600; }
    .tq-sc-aviso[data-bien] { color: var(--tq-sc-suave); font-weight: 600; }
    .tq-sc-caja { border-radius: 16px; background: var(--tq-sc-sup); border: 1px solid var(--tq-sc-borde); padding: 18px 20px; }
    .tq-sc-caja h3 { font-size: 15px; font-weight: 700; margin-bottom: 14px; }
    .tq-sc-pasos { display: flex; flex-direction: column; }
    .tq-sc-paso { position: relative; display: grid; grid-template-columns: 30px minmax(0, 1fr) auto; gap: 12px; padding-bottom: 18px; }
    .tq-sc-paso:not(:last-child)::after { content: ""; position: absolute; left: 14px; top: 30px; bottom: 0; width: 2px; background: var(--tq-sc-borde); }
    .tq-sc-paso-num { width: 30px; height: 30px; border-radius: 999px; display: grid; place-items: center; font-size: 13px; font-weight: 700; color: #fff; background: var(--tq-sc-pagado); }
    .tq-sc-paso[data-estado="aprobada"] .tq-sc-paso-num { background: var(--tq-sc-erp); color: #06262D; }
    .tq-sc-paso[data-estado="por_aprobar"] .tq-sc-paso-num { background: transparent; color: var(--tq-sc-poraprobar); box-shadow: inset 0 0 0 2px var(--tq-sc-poraprobar); }
    .tq-sc-paso[data-estado="retencion"] .tq-sc-paso-num { background: var(--tq-sc-suave); }
    .tq-sc-partida { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 4px 12px; padding: 10px 0; border-bottom: 1px solid var(--tq-sc-borde); font-size: 13px; }
    .tq-sc-partida:last-child { border-bottom: 0; }
    .tq-sc-partida .tq-sc-barra-pista { grid-column: 1 / -1; height: 9px; }
</style>
