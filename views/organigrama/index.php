<?php

/**
 * views/organigrama/index.php
 *
 * Vista "árbol grande tipo organigrama". Sigue el mismo patrón que
 * views/arbol/index.php: data-base para que el JS arme las URLs, y todo
 * el comportamiento vive en el módulo (sin <script> inline con lógica).
 */
?>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Inter:wght@300;400;500;600&display=swap');

    .og-layout {
        display: flex;
        flex-direction: column;
        height: 100%;
        min-height: 600px;
        position: relative;
        z-index: 1;
    }

    .og-barra {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: center;
        padding: .85rem 1.75rem;
    }

    .og-btn {
        display: flex;
        align-items: center;
        gap: .4rem;
        background: #fff;
        border: 1px solid #c9bb92;
        border-radius: 20px;
        padding: .4rem .9rem;
        font-family: 'Inter', sans-serif;
        font-size: .8rem;
        color: #4c7a5d;
        cursor: pointer;
        transition: background .15s, border-color .15s;
    }

    .og-btn:hover {
        background: #f7f0dc;
        border-color: #e8b84b;
    }

    .og-btn-activo {
        background: #4c7a5d;
        border-color: #4c7a5d;
        color: #fff9ec;
    }

    .og-buscador {
        position: relative;
    }

    .og-buscador input {
        padding: 6px 10px;
        border: 1px solid #c9bb92;
        border-radius: 6px;
        font-family: 'Inter', sans-serif;
        min-width: 200px;
    }

    .og-resultados {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: #fffefb;
        border: 1px solid #e3d9bb;
        border-radius: 6px;
        max-height: 220px;
        overflow-y: auto;
        z-index: 20;
        box-shadow: 0 4px 10px rgba(0, 0, 0, .08);
    }

    .og-resultado-item {
        padding: 6px 10px;
        cursor: pointer;
        font-family: 'Inter', sans-serif;
        font-size: .85rem;
    }

    .og-resultado-item:hover {
        background: #f0ead9;
    }

    #organigrama-contenedor {
        flex: 1;
        position: relative;
        overflow: hidden;
        background: #fbfaf6;
        background-image: radial-gradient(#e7e2d3 1px, transparent 1px);
        background-size: 22px 22px;
    }

    .organigrama-svg {
        display: block;
        /* position:absolute + inset:0 en vez de width/height:100% — el
           height:100% en un <svg> dentro de un layout flex anidado a veces
           no se resuelve bien en el navegador (se queda con un alto chico
           "por defecto"), y ahí el SVG termina recortado aunque el
           contenedor sí mida lo que debería. inset:0 lo pega directo a los
           4 bordes del contenedor (que ya tiene position:relative), sin
           depender de ese cálculo de porcentaje. */
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        cursor: grab;
    }

    .organigrama-svg:active {
        cursor: grabbing;
    }

    /* ---- aristas ---- */

    .og-arista {
        fill: none;
        stroke: #9c8f6d;
        stroke-width: 1.6px;
    }

    .og-arista-extra {
        fill: none;
        stroke: #b7ab8a;
        stroke-width: 1.4px;
        stroke-dasharray: 4 3;
    }

    .og-union {
        stroke: #8a7c53;
        stroke-width: 2px;
    }

    .og-arista-en-ruta {
        stroke: #c0392b !important;
        stroke-width: 3px !important;
    }

    .og-linea-resaltada {
        fill: none;
        stroke: #c0392b;
        stroke-width: 3px;
        stroke-dasharray: 2 4;
        stroke-linecap: round;
        pointer-events: none;
    }

    /* ---- nodos (personas) ---- */

    .og-nodo {
        cursor: pointer;
    }

    .og-nodo-caja {
        fill: #fff9ec;
        stroke: #c9bb92;
        stroke-width: 1.4px;
        filter: drop-shadow(0 1px 2px rgba(76, 90, 50, .15));
        transition: stroke .15s ease;
    }

    .og-nodo:hover .og-nodo-caja {
        stroke: #4c7a5d;
    }

    .og-nodo-raiz .og-nodo-caja {
        stroke: #c9a24b;
        stroke-width: 2.4px;
    }

    .og-nodo-seleccionado .og-nodo-caja {
        stroke: #2c6fbb;
        stroke-width: 2.4px;
    }

    .og-nodo-en-ruta .og-nodo-caja {
        fill: #fdece9;
        stroke: #c0392b;
        stroke-width: 2.2px;
    }

    .og-nodo-foto-fondo {
        fill: #ece1c4;
    }

    .og-nodo-iniciales {
        font: 700 15px 'Rajdhani', sans-serif;
        fill: #6b5a38;
    }

    .og-nodo-nombre {
        font: 600 11.5px 'Rajdhani', sans-serif;
        fill: #2e2716;
    }

    .og-nodo-fallecido {
        font: 11px 'Inter', sans-serif;
        fill: #8a5a4a;
    }

    /* ---- panel de parentesco ---- */

    #og-panel-parentesco {
        display: none;
        position: absolute;
        right: 16px;
        top: 16px;
        max-width: 320px;
        background: #fffefb;
        border: 1px solid #c9bb92;
        border-radius: 10px;
        padding: 14px 16px;
        box-shadow: 0 6px 18px rgba(0, 0, 0, .15);
        z-index: 30;
        font-family: 'Inter', sans-serif;
    }

    #og-panel-parentesco.og-panel-visible {
        display: block;
    }

    #og-panel-parentesco h4 {
        margin: 0 0 6px;
        font-family: 'Rajdhani', sans-serif;
        font-size: 15px;
        color: #2e2716;
    }

    .og-panel-etiqueta {
        font-weight: 700;
        color: #4c7a5d;
        margin: 0 0 8px;
    }

    .og-panel-error {
        color: #c0392b;
    }

    .og-modo-comparar .organigrama-svg {
        cursor: crosshair;
    }
</style>

<div class="og-layout" id="organigrama" data-base="<?= urlBase() ?>" data-id-raiz="<?= (int) ($idRaiz ?? 0) ?>">
    <div class="og-barra">
        <!-- Modo normal: un solo buscador para saltar de persona. Se
             oculta mientras estás en modo "comparar" para no amontonar
             varios buscadores a la vez. -->
        <div id="og-modo-normal" style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
            <div class="og-buscador">
                <input id="og-buscar-raiz" type="text" placeholder="Ir a persona...">
                <div id="og-resultados-raiz" class="og-resultados"></div>
            </div>

            <button id="og-btn-modo-comparar" type="button" class="og-btn">
                Comparar parentesco entre dos personas
            </button>

            <button id="og-btn-completo" type="button" class="og-btn">
                Ver árbol completo
            </button>
        </div>

        <div id="og-controles-comparar" style="display:none; gap:12px; align-items:center; flex-wrap:wrap;">
            <div class="og-buscador">
                <input id="og-buscar-a" type="text" placeholder="Buscar persona A...">
                <div id="og-resultados-a" class="og-resultados"></div>
            </div>
            <div class="og-buscador">
                <input id="og-buscar-b" type="text" placeholder="Buscar persona B...">
                <div id="og-resultados-b" class="og-resultados"></div>
            </div>
            <button id="og-btn-comparar" type="button" class="og-btn" disabled>Ver parentesco</button>
            <button id="og-btn-limpiar" type="button" class="og-btn">Cancelar</button>
        </div>
    </div>

    <div id="organigrama-contenedor">
        <div id="og-panel-parentesco"></div>
    </div>
</div>

<script src="<?= asset('build/js/organigrama/index.js') ?>" type="module"></script>