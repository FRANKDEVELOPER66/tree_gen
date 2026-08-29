<style>
    @import url('https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Inter:wght@300;400;500;600&display=swap');

    body {
        background: linear-gradient(180deg, #f3ead6, #eadfc4);
    }

    /* ── Decoracion de hojas en las esquinas ─────────────────────────────── */
    .hojas-decoracion {
        position: fixed;
        inset: 0;
        pointer-events: none;
        z-index: 0;
        overflow: hidden;
    }

    .hoja {
        position: absolute;
        opacity: .35;
        color: #4c7a5d;
    }

    .hoja.esq-1 {
        top: -20px;
        left: -20px;
        width: 130px;
        transform: rotate(20deg);
    }

    .hoja.esq-2 {
        top: -30px;
        right: -30px;
        width: 160px;
        transform: rotate(160deg) scaleX(-1);
    }

    .hoja.esq-3 {
        bottom: -25px;
        left: -15px;
        width: 140px;
        transform: rotate(-30deg) scaleY(-1);
    }

    .hoja.esq-4 {
        bottom: -30px;
        right: -25px;
        width: 150px;
        transform: rotate(200deg);
    }

    /* ── Migas de pan ─────────────────────────────────────────────────────── */
    .migas {
        position: relative;
        z-index: 1;
        display: flex;
        align-items: center;
        gap: .4rem;
        padding: .85rem 1.75rem;
        flex-wrap: wrap;
        font-family: 'Rajdhani', sans-serif;
        font-weight: 600;
        font-size: 1.1rem;
        color: #6b5a38;
        min-height: 1.5rem;
    }

    .migas .miga {
        cursor: pointer;
        padding: .15rem .5rem;
        border-radius: 6px;
        transition: color .2s, background .2s;
    }

    .migas .miga:hover {
        color: #2e4a3a;
        background: rgba(76, 122, 93, .1);
    }

    .migas .miga.actual {
        color: #2e4a3a;
        font-weight: 700;
    }

    .migas .sep {
        color: #b7a878;
    }

    /* ── Lienzo ───────────────────────────────────────────────────────────── */
    .lienzo {
        position: relative;
        z-index: 1;
        padding: 1.5rem 1.5rem 5rem;
        max-width: 1100px;
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 2.5rem;
        min-height: 60vh;
    }

    .lienzo.entrando {
        animation: aparecer .38s ease;
    }

    @keyframes aparecer {
        from {
            opacity: 0;
            transform: scale(0.97) translateY(6px);
        }

        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }

    .fila-progenitores {
        display: flex;
        gap: .75rem;
        opacity: .6;
        transition: opacity .2s;
    }

    .fila-progenitores:hover {
        opacity: .95;
    }

    .progenitor-mini {
        display: flex;
        align-items: center;
        gap: .4rem;
        padding: .35rem .8rem .35rem .35rem;
        border: 1px solid #c9bb92;
        border-radius: 20px;
        cursor: pointer;
        font-size: .78rem;
        color: #6b5a38;
        background: rgba(255, 255, 255, .4);
    }

    .progenitor-mini:hover {
        border-color: #4c7a5d;
        color: #2e4a3a;
    }

    .progenitor-mini .retrato-chico {
        width: 30px;
        height: 34px;
        border-radius: 15px 15px 3px 3px;
    }

    .pareja-central {
        display: flex;
        align-items: flex-start;
        gap: 2.5rem;
        justify-content: center;
        flex-wrap: wrap;
    }

    .simbolo-union {
        font-family: 'Rajdhani', sans-serif;
        font-style: italic;
        color: #4c7a5d;
        font-size: 1.4rem;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: .15rem;
        margin-top: 3.5rem;
    }

    .simbolo-union .rango {
        font-size: .62rem;
        font-style: normal;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: #8a7a52;
        font-family: 'Inter', sans-serif;
    }

    /* ── Retrato: marco en arco ───────────────────────────────────────────── */
    .retrato-persona {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: .5rem;
        cursor: pointer;
        text-align: center;
        width: 150px;
    }

    .marco-wrap {
        position: relative;
    }

    .retrato-marco {
        width: 118px;
        height: 138px;
        border-radius: 59px 59px 6px 6px;
        border: 3px solid #4c7a5d;
        padding: 4px;
        background: #fff9ec;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        box-shadow: 0 4px 14px rgba(76, 90, 50, .15);
        transition: border-color .2s, transform .2s, box-shadow .2s;
    }

    .retrato-persona.central .retrato-marco {
        width: 148px;
        height: 172px;
        border-radius: 74px 74px 8px 8px;
        border-width: 4px;
        border-color: #c9a24b;
        box-shadow: 0 6px 20px rgba(76, 90, 50, .2);
    }

    .retrato-persona:hover .retrato-marco {
        border-color: #c9a24b;
        transform: translateY(-3px);
        box-shadow: 0 10px 24px rgba(76, 90, 50, .28);
    }

    .retrato-marco img,
    .retrato-marco .sin-foto {
        width: 100%;
        height: 100%;
        border-radius: 55px 55px 3px 3px;
        object-fit: cover;
    }

    .retrato-persona.central .retrato-marco img,
    .retrato-persona.central .retrato-marco .sin-foto {
        border-radius: 70px 70px 5px 5px;
    }

    .retrato-marco .sin-foto {
        display: flex;
        align-items: center;
        justify-content: center;
        background: #ece1c4;
        color: #6b5a38;
        font-family: 'Rajdhani', sans-serif;
        font-weight: 700;
        font-size: 1.8rem;
    }

    .retrato-marco.fallecido {
        filter: grayscale(.55);
        opacity: .85;
    }

    /* Boton de ficha tecnica, esquina del marco */
    .btn-ficha {
        position: absolute;
        top: -4px;
        right: -4px;
        width: 26px;
        height: 26px;
        border-radius: 50%;
        background: #fff9ec;
        border: 2px solid #4c7a5d;
        color: #4c7a5d;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: .8rem;
        cursor: pointer;
        box-shadow: 0 2px 6px rgba(0, 0, 0, .15);
        transition: all .2s;
    }

    .btn-ficha:hover {
        background: #4c7a5d;
        color: #fff9ec;
        transform: scale(1.1);
    }

    .retrato-nombre {
        font-family: 'Rajdhani', sans-serif;
        font-weight: 700;
        font-size: 1.02rem;
        line-height: 1.2;
        color: #2e2716;
    }

    .retrato-persona.central .retrato-nombre {
        font-size: 1.15rem;
    }

    .retrato-rol {
        font-family: 'Inter', sans-serif;
        font-size: .72rem;
        font-weight: 600;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: #4c7a5d;
    }

    .retrato-fechas {
        font-size: .7rem;
        color: #8a7a52;
        letter-spacing: .02em;
    }

    .pestanas-uniones {
        display: flex;
        gap: .5rem;
        flex-wrap: wrap;
        justify-content: center;
    }

    .pestana-union {
        padding: .35rem .9rem;
        border: 1px solid #c9bb92;
        border-radius: 20px;
        font-size: .78rem;
        color: #6b5a38;
        cursor: pointer;
        background: rgba(255, 255, 255, .4);
    }

    .pestana-union.activa {
        border-color: #c9a24b;
        color: #2e4a3a;
        background: rgba(201, 162, 75, .15);
        font-weight: 600;
    }

    .pestana-union .estado-badge {
        opacity: .7;
        font-style: italic;
        margin-left: .3rem;
    }

    .linea-descendencia {
        width: 2px;
        height: 32px;
        background: linear-gradient(to bottom, #4c7a5d, #c9bb92);
    }

    .fila-hijos {
        display: flex;
        gap: 1.75rem;
        flex-wrap: wrap;
        justify-content: center;
        padding-top: .5rem;
    }

    .sin-hijos {
        color: #8a7a52;
        font-family: 'Rajdhani', sans-serif;
        font-style: italic;
        font-size: 1rem;
        padding: 1rem 0;
    }

    .etiqueta-filiacion {
        font-size: .63rem;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: #a06a2e;
        border: 1px solid #a06a2e;
        border-radius: 10px;
        padding: .05rem .45rem;
        margin-top: -.15rem;
        font-family: 'Inter', sans-serif;
    }

    /* ── Panel de ficha tecnica ───────────────────────────────────────────── */
    .panel-detalle {
        position: fixed;
        top: 0;
        right: 0;
        height: 100%;
        width: min(380px, 92vw);
        background: #fdf7ea;
        border-left: 1px solid #c9bb92;
        transform: translateX(100%);
        transition: transform .3s ease;
        z-index: 50;
        overflow-y: auto;
        padding: 1.5rem;
    }

    .panel-detalle.abierto {
        transform: translateX(0);
    }

    .panel-detalle .cerrar {
        cursor: pointer;
        color: #8a7a52;
        float: right;
        font-size: 1.3rem;
    }

    .panel-detalle .cerrar:hover {
        color: #2e4a3a;
    }

    .panel-detalle h2 {
        font-family: 'Rajdhani', sans-serif;
        font-size: 1.4rem;
        margin: .5rem 0 .2rem;
        color: #2e2716;
    }

    .panel-detalle .bio {
        font-size: .9rem;
        line-height: 1.6;
        color: #4a3f28;
        margin-top: .75rem;
    }

    .panel-detalle .dato {
        font-size: .82rem;
        color: #8a7a52;
        margin-top: .35rem;
    }

    .panel-detalle .galeria {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: .4rem;
        margin-top: 1rem;
    }

    .panel-detalle .galeria img {
        width: 100%;
        aspect-ratio: 1;
        object-fit: cover;
        border-radius: 6px;
        border: 1px solid #c9bb92;
    }

    .hermanos-titulo {
        font-family: 'Rajdhani', sans-serif;
        font-weight: 700;
        font-size: .85rem;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: #4c7a5d;
        margin-top: 1.25rem;
        margin-bottom: .5rem;
        border-top: 1px solid #e3d9bb;
        padding-top: 1rem;
    }

    .hermanos-lista {
        display: flex;
        flex-direction: column;
        gap: .4rem;
    }

    .hermano-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .5rem;
        padding: .45rem .65rem;
        border: 1px solid #e3d9bb;
        border-radius: 8px;
        cursor: pointer;
        font-size: .85rem;
        color: #2e2716;
        transition: all .15s;
    }

    .hermano-item:hover {
        border-color: #4c7a5d;
        background: rgba(76, 122, 93, .06);
    }

    .badge-parentesco {
        font-size: .62rem;
        text-transform: uppercase;
        letter-spacing: .03em;
        padding: .1rem .45rem;
        border-radius: 10px;
        white-space: nowrap;
        flex-shrink: 0;
    }

    .badge-completo {
        background: rgba(76, 122, 93, .15);
        color: #2e4a3a;
        border: 1px solid #4c7a5d;
    }

    .badge-medio {
        background: rgba(201, 162, 75, .15);
        color: #8a6d2e;
        border: 1px solid #c9a24b;
    }

    .cargando {
        color: #8a7a52;
        font-family: 'Rajdhani', sans-serif;
        font-style: italic;
        padding: 3rem 0;
        text-align: center;
    }

    .vacio-arbol {
        text-align: center;
        padding: 3rem 1rem;
        color: #6b5a38;
        font-family: 'Rajdhani', sans-serif;
    }

    .vacio-arbol a {
        border: 1px solid #4c7a5d;
        color: #2e4a3a;
        padding: .5rem 1.1rem;
        border-radius: 20px;
        display: inline-block;
        margin-top: 1rem;
    }

    @media (max-width: 640px) {
        .pareja-central {
            gap: 1.25rem;
        }

        .retrato-persona {
            width: 118px;
        }

        .retrato-marco {
            width: 92px;
            height: 108px;
            border-radius: 46px 46px 5px 5px;
        }

        .retrato-persona.central .retrato-marco {
            width: 112px;
            height: 130px;
            border-radius: 56px 56px 6px 6px;
        }
    }
</style>

<div class="hojas-decoracion">
    <svg class="hoja esq-1" viewBox="0 0 100 100" fill="currentColor">
        <path d="M10 90 C 10 40, 40 10, 90 10 C 70 30, 55 55, 45 90 C 35 60, 22 55, 10 90 Z" />
    </svg>
    <svg class="hoja esq-2" viewBox="0 0 100 100" fill="currentColor">
        <path d="M10 90 C 10 40, 40 10, 90 10 C 70 30, 55 55, 45 90 C 35 60, 22 55, 10 90 Z" />
    </svg>
    <svg class="hoja esq-3" viewBox="0 0 100 100" fill="currentColor">
        <path d="M10 90 C 10 40, 40 10, 90 10 C 70 30, 55 55, 45 90 C 35 60, 22 55, 10 90 Z" />
    </svg>
    <svg class="hoja esq-4" viewBox="0 0 100 100" fill="currentColor">
        <path d="M10 90 C 10 40, 40 10, 90 10 C 70 30, 55 55, 45 90 C 35 60, 22 55, 10 90 Z" />
    </svg>
</div>

<div class="migas" id="migas"></div>

<main class="lienzo" id="lienzo" data-base="<?= urlBase() ?>">
    <div class="cargando">Abriendo el registro familiar…</div>
</main>

<aside class="panel-detalle" id="panelDetalle"></aside>

<p style="position:relative;z-index:1;text-align:center;color:#8a7a52;font-size:0.78rem;margin-top:-1.5rem;padding-bottom:2rem;">
    Click en un retrato para explorar esa rama · Click en el ⓘ para ver su ficha técnica
</p>

<script src="<?= asset('build/js/arbol/index.js') ?>" type="module"></script>