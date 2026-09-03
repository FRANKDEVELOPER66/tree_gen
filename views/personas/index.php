<style>
    @import url('https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Inter:wght@300;400;500;600&display=swap');

    body {
        background: linear-gradient(180deg, #f3ead6, #eadfc4);
    }

    .personas-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 1.25rem;
    }

    .personas-header .titulo {
        font-family: 'Rajdhani', sans-serif;
        font-size: 1.6rem;
        font-weight: 700;
        color: #2e2716;
        margin: 0;
        letter-spacing: .5px;
        line-height: 1.2;
    }

    .btn-nueva-persona {
        background: linear-gradient(135deg, #e8b84b, #d4a032);
        border: none;
        border-radius: 10px;
        color: #0f1117;
        padding: .6rem 1.25rem;
        font-family: 'Rajdhani', sans-serif;
        font-size: .95rem;
        font-weight: 700;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: .5rem;
        transition: all .3s;
        letter-spacing: .5px;
        flex-shrink: 0;
    }

    .btn-nueva-persona:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(232, 184, 75, .35);
    }

    /* ── Buscador global ──────────────────────────────────────────────────── */
    .buscador-wrap {
        position: relative;
        margin-bottom: 1.5rem;
    }

    .buscador-wrap i {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: #8a7a52;
    }

    #buscarGlobal {
        width: 100%;
        padding: .65rem 1rem .65rem 2.4rem;
        border-radius: 10px;
        border: 1.5px solid #c9bb92;
        background: #fdf7ea;
        color: #2e2716;
        font-size: .9rem;
    }

    #buscarGlobal:focus {
        outline: none;
        border-color: #4c7a5d;
        box-shadow: 0 0 0 3px rgba(76, 122, 93, .12);
    }

    .seccion-titulo {
        font-family: 'Rajdhani', sans-serif;
        font-size: 1rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: #6b5a38;
        margin: 1.5rem 0 .75rem;
    }

    /* ── Tarjeta de familia ───────────────────────────────────────────────── */
    .familia-card {
        background: linear-gradient(160deg, #23402f, #16281d);
        border: 1px solid #3d6250;
        border-radius: 14px;
        padding: 1.1rem 1.25rem;
        cursor: pointer;
        transition: all .2s;
        height: 100%;
        display: flex;
        align-items: center;
        gap: .85rem;
    }

    .familia-card:hover {
        border-color: #c9a24b;
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, .25);
    }

    .familia-icono {
        flex-shrink: 0;
        width: 48px;
        height: 48px;
        border-radius: 12px;
        background: rgba(232, 184, 75, .12);
        border: 1.5px solid #c9a24b;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #e8b84b;
        font-size: 1.3rem;
    }

    .familia-nombre {
        font-family: 'Rajdhani', sans-serif;
        font-weight: 700;
        font-size: 1.05rem;
        color: #e8eaf0;
    }

    .familia-count {
        font-size: .76rem;
        color: #9db3a4;
        margin-top: .1rem;
    }

    /* ── Tarjeta de persona (sueltas, y dentro del modal de familia) ────────── */
    .persona-card {
        background: linear-gradient(160deg, #23402f, #16281d);
        border: 1px dashed #8a6d2e;
        border-radius: 14px;
        padding: 1.25rem;
        display: flex;
        gap: .85rem;
        transition: all .25s;
        height: 100%;
    }

    .persona-card:hover {
        border-color: #c9a24b;
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, .25);
    }

    .persona-avatar {
        flex-shrink: 0;
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: #16281d;
        border: 1.5px solid #c9a24b;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    .persona-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .persona-avatar span {
        color: #e8b84b;
        font-weight: 700;
        font-family: 'Rajdhani', sans-serif;
        font-size: 1.2rem;
    }

    .persona-nombre {
        font-family: 'Rajdhani', sans-serif;
        font-size: 1.1rem;
        font-weight: 700;
        color: #e8eaf0;
        margin-bottom: .15rem;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .persona-sub {
        font-size: .78rem;
        color: #9db3a4;
        margin-bottom: .75rem;
    }

    .sin-asociar-badge {
        display: inline-block;
        font-size: .62rem;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #a06a2e;
        border: 1px solid #a06a2e;
        border-radius: 10px;
        padding: .05rem .4rem;
        margin-bottom: .4rem;
    }

    .persona-acciones {
        display: flex;
        gap: .4rem;
        flex-wrap: wrap;
    }

    .btn-editar {
        background: transparent;
        border: 1px solid #e8b84b;
        color: #e8b84b;
        border-radius: 8px;
        padding: .35rem .75rem;
        font-size: .8rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: .3rem;
    }

    .btn-editar:hover {
        background: rgba(232, 184, 75, .1);
    }

    /* Los botones de acción secundaria (unión, hijo, raíz, eliminar, ficha)
       usan clases reales de Bootstrap (btn-outline-*) directamente en el
       JS -- no necesitan CSS propio. Todos visibles siempre, sin menú
       desplegable, para que nunca queden tapados por otras tarjetas. */

    /* La lista de integrantes del modal "ver familia" usa componentes
       reales de Bootstrap (list-group, badge, btn-outline-secondary) —
       no necesita CSS propio. */
</style>

<div class="personas-header">
    <div class="titulo">Personas registradas</div>
    <button class="btn-nueva-persona" id="btnNuevaPersona">
        <i class="bi bi-person-plus-fill"></i> Nueva persona
    </button>
</div>

<div class="buscador-wrap">
    <i class="bi bi-search"></i>
    <input type="text" id="buscarGlobal" placeholder="Buscar cualquier persona, en cualquier familia…">
</div>

<div id="contenedorFamilias" data-base="<?= urlBase() ?>">
    <div class="text-center py-5" style="color:#7c8398;">Cargando personas…</div>
</div>

<script src="<?= asset('build/js/personas/index.js') ?>" type="module"></script>