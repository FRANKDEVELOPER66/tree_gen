<style>
    @import url('https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Inter:wght@300;400;500;600&display=swap');

    body {
        background: linear-gradient(180deg, #f3ead6, #eadfc4);
    }

    .personas-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1.5rem;
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
    }

    .btn-nueva-persona:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(232, 184, 75, .35);
    }

    .persona-card {
        background: linear-gradient(160deg, #23402f, #16281d);
        border: 1px solid #3d6250;
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

    .persona-acciones {
        display: flex;
        gap: .5rem;
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

    .dropdown-mas .btn-mas {
        background: transparent;
        border: 1px solid #4a7060;
        color: #c8d6cc;
        border-radius: 8px;
        padding: .35rem .75rem;
        font-size: .8rem;
        cursor: pointer;
    }

    .dropdown-mas .btn-mas:hover {
        border-color: #c9a24b;
        color: #e8eaf0;
    }

    .dropdown-menu-dark {
        background: #242837;
        border: 1px solid #2e3347;
    }

    .dropdown-menu-dark .dropdown-item {
        color: #e8eaf0;
    }

    .dropdown-menu-dark .dropdown-item:hover {
        background: rgba(232, 184, 75, .1);
        color: #e8b84b;
    }

    .dropdown-menu-dark .dropdown-item.text-danger:hover {
        background: rgba(224, 82, 82, .1);
        color: #e05252 !important;
    }
</style>

<div class="personas-header">
    <div class="titulo">Personas registradas</div>
    <button class="btn-nueva-persona" id="btnNuevaPersona">
        <i class="bi bi-person-plus-fill"></i> Nueva persona
    </button>
</div>

<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3" id="gridPersonas" data-base="<?= urlBase() ?>">
    <div class="col">
        <div class="text-center py-5" style="color:#7c8398;">Cargando personas…</div>
    </div>
</div>

<script src="<?= asset('build/js/personas/index.js') ?>" type="module"></script>