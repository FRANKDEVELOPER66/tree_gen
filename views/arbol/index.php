<link rel="stylesheet" href="<?= asset('css/estilo.css') ?>">

<div class="migas" id="migas"></div>

<main class="lienzo" id="lienzo" data-base="<?= urlBase() ?>">
    <div class="cargando">Abriendo el registro familiar…</div>
</main>

<aside class="panel-detalle" id="panelDetalle"></aside>

<p style="text-align:center;color:var(--texto-suave, #888);font-size:0.78rem;margin-top:-1.5rem;padding-bottom:2rem;">
    Click en un retrato para centrar el árbol en esa persona · Shift + click para ver su ficha completa
</p>

<script src="<?= asset('build/js/arbol/index.js') ?>" type="module"></script>