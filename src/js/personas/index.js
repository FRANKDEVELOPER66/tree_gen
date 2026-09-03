import Swal from 'sweetalert2';

const BASE = document.querySelector('[data-base]')?.dataset.base ?? '';
const contenedor = document.getElementById('contenedorFamilias');

const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 2600,
    timerProgressBar: true,
});

function urlFoto(nombreArchivo) {
    return nombreArchivo ? `${BASE}/public/uploads/${nombreArchivo}` : null;
}

// ── Tarjeta de una persona individual (reutilizada para "sin asociar") ──
function personaCardHTML(p, _idNoUsado, sinAsociar = false) {
    const foto = urlFoto(p.foto_perfil);
    const iniciales = (p.nombres || '?').trim()[0]?.toUpperCase() || '?';
    return `
        <div class="persona-card">
            <div class="persona-avatar">
                ${foto ? `<img src="${foto}" alt="">` : `<span>${iniciales}</span>`}
            </div>
            <div class="flex-grow-1 min-w-0">
                ${sinAsociar ? '<div class="sin-asociar-badge">Sin asociar aún</div>' : ''}
                <div class="persona-nombre">${p.nombres} ${p.apellidos}</div>
                <div class="persona-sub">
                    ${p.fecha_nacimiento ? p.fecha_nacimiento.substring(0, 4) : 's.f.'}${p.lugar_nacimiento ? ' · ' + p.lugar_nacimiento : ''}
                </div>
                <div class="persona-acciones">
                    <button class="btn-editar" onclick="editarPersona(${p.id})">
                        <i class="bi bi-pencil-square"></i> Editar
                    </button>
                    <button class="btn btn-sm btn-outline-info" onclick="verFicha(${p.id})" title="Ver ficha técnica">
                        <i class="bi bi-info-circle"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="gestionarUnion(${p.id})" title="Agregar unión">
                        <i class="bi bi-heart"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-success" onclick="gestionarHijo(${p.id})" title="Vincular hijo/a">
                        <i class="bi bi-diagram-3"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-primary" onclick="gestionarProgenitor(${p.id})" title="Vincular como hijo/a de...">
                        <i class="bi bi-diagram-3-fill"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-warning" onclick="fijarRaiz(${p.id})" title="Fijar como raíz">
                        <i class="bi bi-flag"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-dark" onclick="eliminarPersona(${p.id})" title="Eliminar">
                        <i class="bi bi-trash3"></i>
                    </button>
                </div>
            </div>
        </div>`;
}

// ── Vista de una familia en pantalla completa (reemplaza el listado) ────
// vistaActualFamiliaId guarda en que familia esta parado el usuario (por su
// id estable, ej. "union_11"), asi que al refrescar despues de un guardado
// se puede volver a pintar la MISMA vista en vez de saltar al listado
// principal y perder el hilo de lo que se estaba haciendo.
let vistaActualFamiliaId = null;

function pintarVistaFamilia(familia) {
    const tituloEl = document.querySelector('.personas-header .titulo');
    const buscadorEl = document.querySelector('.buscador-wrap');
    if (tituloEl) tituloEl.textContent = familia.nombre;
    if (buscadorEl) buscadorEl.style.display = 'none';

    // Usamos los datos completos de cachePersonas (fecha, lugar, foto) en
    // vez de los datos resumidos de la familia, para reusar la misma
    // tarjeta completa del listado normal.
    const miembrosCompletos = familia.miembros.map(
        (m) => cachePersonas.find((p) => String(p.id) === String(m.id)) || m
    );

    let html = `
        <button class="btn btn-sm btn-outline-secondary mb-3" onclick="volverAFamilias()">
            <i class="bi bi-arrow-left"></i> Volver a familias
        </button>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3">`;
    html += miembrosCompletos.map((p) => `<div class="col">${personaCardHTML(p)}</div>`).join('');
    html += `</div>`;

    contenedor.innerHTML = html;
}

window.mostrarVistaFamilia = (indice) => {
    const familia = familiasCache[indice];
    if (!familia) return;
    vistaActualFamiliaId = familia.id;
    pintarVistaFamilia(familia);
};

window.volverAFamilias = () => {
    vistaActualFamiliaId = null;
    const tituloEl = document.querySelector('.personas-header .titulo');
    const buscadorEl = document.querySelector('.buscador-wrap');
    if (tituloEl) tituloEl.textContent = 'Personas registradas';
    if (buscadorEl) buscadorEl.style.display = '';
    renderFamilias(familiasCache, sinAsociarCache);
};

// Decide que pintar despues de recargar los datos: si el usuario estaba
// parado dentro de una familia, se queda ahi (repintada con los datos
// frescos); si no, se pinta el listado principal como siempre.
function renderVistaActual() {
    if (vistaActualFamiliaId) {
        const familia = familiasCache.find((f) => f.id === vistaActualFamiliaId);
        if (familia) {
            pintarVistaFamilia(familia);
            return;
        }
        // la familia ya no existe tal cual (por ejemplo se elimino a la
        // ultima persona que la sostenia) -- volvemos al listado principal
        vistaActualFamiliaId = null;
    }
    renderFamilias(familiasCache, sinAsociarCache);
}

// ── Cargar y pintar: familias + personas sin asociar ────────────────────
let familiasCache = [];
let sinAsociarCache = [];
async function cargarFamilias() {
    const r = await fetch(`${BASE}/api/personas/familias`);
    const d = await r.json();
    if (d.codigo !== 1) return;
    familiasCache = d.datos.familias;
    sinAsociarCache = d.datos.sin_asociar;
    renderVistaActual();
}

function renderFamilias(familias, sinAsociar) {
    if (!familias.length && !sinAsociar.length) {
        contenedor.innerHTML = `<div class="text-center py-5" style="color:#7c8398;">
            Todavía no hay personas. Agregá la primera con el botón de arriba.
        </div>`;
        return;
    }

    let html = '';

    if (familias.length) {
        html += `<div class="seccion-titulo">Familias</div>`;
        html += `<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3">`;
        html += familias.map((f, i) => {
            const buscar = (f.nombre + ' ' + f.miembros.map((m) => `${m.nombres} ${m.apellidos}`).join(' ')).toLowerCase();
            return `
            <div class="col familia-item" data-buscar="${buscar}">
                <div class="familia-card" onclick="mostrarVistaFamilia(${i})">
                    <div class="familia-icono"><i class="bi bi-people-fill"></i></div>
                    <div>
                        <div class="familia-nombre">${f.nombre}</div>
                        <div class="familia-count">${f.miembros.length} integrante${f.miembros.length === 1 ? '' : 's'}</div>
                    </div>
                </div>
            </div>`;
        }).join('');
        html += `</div>`;
    }

    if (sinAsociar.length) {
        html += `<div class="seccion-titulo">Sin asociar aún</div>`;
        html += `<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3">`;
        html += sinAsociar.map((p) => {
            const buscar = `${p.nombres} ${p.apellidos}`.toLowerCase();
            return `<div class="col persona-item" data-buscar="${buscar}">${personaCardHTML(p, null, true)}</div>`;
        }).join('');
        html += `</div>`;
    }

    contenedor.innerHTML = html;
}

// ── Buscador global (filtra tarjetas de familia y de sueltas ya pintadas) ─
document.getElementById('buscarGlobal')?.addEventListener('input', (e) => {
    const q = e.target.value.toLowerCase().trim();
    document.querySelectorAll('.familia-item, .persona-item').forEach((el) => {
        el.style.display = el.dataset.buscar.includes(q) ? '' : 'none';
    });
});

// ── Buscador de personas (para los selects de union/hijo) ────────────────
let cachePersonas = [];
async function obtenerTodas() {
    const r = await fetch(`${BASE}/api/personas/listar`);
    const d = await r.json();
    cachePersonas = d.codigo === 1 ? d.datos : [];
    return cachePersonas;
}

// ── Vista previa (foto + nombre + fecha) al elegir a alguien en un select ─
function previewPersonaHTML(p) {
    if (!p) return '';
    const foto = urlFoto(p.foto_perfil);
    const iniciales = (p.nombres || '?').trim()[0]?.toUpperCase() || '?';
    return `
        <div class="d-flex align-items-center gap-2 mt-1 mb-2 p-2"
             style="background:#f7f0dc;border:1px solid #c9bb92;border-radius:8px;">
            <div style="width:38px;height:38px;border-radius:50%;overflow:hidden;background:#eee2c6;
                        display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                ${foto
            ? `<img src="${foto}" style="width:100%;height:100%;object-fit:cover;">`
            : `<span style="font-weight:700;color:#8a6d2e;font-size:.9rem;">${iniciales}</span>`}
            </div>
            <div style="font-size:.8rem;text-align:left;">
                <div style="font-weight:600;color:#2e2716;">${p.nombres} ${p.apellidos}</div>
                <div style="color:#8a7a52;font-size:.72rem;">
                    ${p.fecha_nacimiento ? p.fecha_nacimiento.substring(0, 4) : 's.f.'}${p.lugar_nacimiento ? ' · ' + p.lugar_nacimiento : ''}
                </div>
            </div>
        </div>`;
}

function activarPreviewSelect(selectId, previewId, personas, unionesActivasMap = null) {
    const sel = document.getElementById(selectId);
    const preview = document.getElementById(previewId);
    if (!sel || !preview) return;
    const actualizar = () => {
        const p = personas.find((x) => String(x.id) === String(sel.value));
        let html = previewPersonaHTML(p);
        if (p && unionesActivasMap && unionesActivasMap[p.id]) {
            const pareja = unionesActivasMap[p.id];
            html += `<div style="background:rgba(224,82,82,.12);border:1px solid #e05252;border-radius:8px;
                        padding:.4rem .65rem;margin-top:-.4rem;margin-bottom:.6rem;font-size:.75rem;color:#8a2020;">
                        ⚠️ Ya tiene una unión <strong>activa</strong> con ${pareja.nombres} ${pareja.apellidos}.
                     </div>`;
        }
        preview.innerHTML = html;
    };
    sel.addEventListener('change', actualizar);
    actualizar();
}

// ── Ficha técnica (modal): misma info que en el árbol, accesible desde
// la vista de Personas -- foto, apodo, fechas, biografía, galería, y
// hermanos/medios hermanos, clickeables para saltar de ficha en ficha.
window.verFicha = async (personaId) => {
    Swal.fire({
        title: 'Cargando ficha…',
        html: '<div class="text-center py-3"><div class="spinner-border" role="status"></div></div>',
        showConfirmButton: false,
        showCloseButton: true,
        width: 640,
    });

    const rDetalle = await fetch(`${BASE}/api/personas/detalle?id=${personaId}`);
    const dDetalle = await rDetalle.json();

    if (dDetalle.codigo === 0) {
        Swal.fire({ icon: 'error', title: 'No se pudo cargar la ficha' });
        return;
    }

    const { persona, fotos } = dDetalle.datos;
    const foto = urlFoto(persona.foto_perfil);

    const fechas = `${persona.fecha_nacimiento ? persona.fecha_nacimiento.substring(0, 4) : 's.f.'}` +
        `${persona.fecha_fallecimiento ? ' – ' + persona.fecha_fallecimiento.substring(0, 4) : ''}` +
        `${persona.lugar_nacimiento ? ' · ' + persona.lugar_nacimiento : ''}`;

    const galeriaHTML = fotos.length ? `
        <div class="d-flex gap-1 flex-wrap mt-2">
            ${fotos.map((f) => {
        const urlF = urlFoto(f.ruta);
        return `<img src="${urlF}" onclick="mostrarFotoGrande('${urlF}')"
                    style="width:76px;height:76px;object-fit:cover;border-radius:6px;border:1px solid #c9bb92;cursor:pointer;">`;
    }).join('')}
        </div>` : '';

    Swal.fire({
        html: `
            <div class="text-start">
                ${foto ? `<img src="${foto}" onclick="mostrarFotoGrande('${foto}')"
                    style="width:100%;max-height:420px;object-fit:contain;background:#f3ead6;border-radius:8px;cursor:pointer;">` : ''}
                <h4 class="mt-3 mb-1">${persona.nombres} ${persona.apellidos}</h4>
                ${persona.apodo ? `<div class="text-muted small">Conocido/a como "${persona.apodo}"</div>` : ''}
                <div class="text-muted small mb-2">${fechas}</div>
                ${persona.biografia ? `<p style="font-size:.9rem;">${persona.biografia}</p>` : ''}
                ${galeriaHTML}
            </div>`,
        showConfirmButton: false,
        showCloseButton: true,
        width: 640,
    });
};

// Previsualizacion de foto en grande (desde la ficha tecnica)
window.mostrarFotoGrande = (url) => {
    Swal.fire({
        imageUrl: url,
        imageAlt: 'Foto',
        showConfirmButton: false,
        showCloseButton: true,
        width: 'min(90vw, 800px)',
        background: '#0f1117',
        padding: '1rem',
    });
};

function opcionesSelect(personas, excluirIds = null) {
    const excluidos = Array.isArray(excluirIds) ? excluirIds.map(String) : [String(excluirIds)];
    return personas
        .filter((p) => !excluidos.includes(String(p.id)))
        .map((p) => `<option value="${p.id}">${p.nombres} ${p.apellidos}</option>`)
        .join('');
}

// ── Crear / editar persona ────────────────────────────────────────────────
async function mostrarFormPersona(datos = null) {
    const esEdicion = datos !== null;

    const { value: ok, isConfirmed } = await Swal.fire({
        title: esEdicion ? 'Editar persona' : 'Nueva persona',
        html: `
            <div class="text-start small">
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label">Nombres *</label>
                        <input id="f-nombres" class="form-control form-control-sm" value="${datos?.nombres || ''}">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Apellidos *</label>
                        <input id="f-apellidos" class="form-control form-control-sm" value="${datos?.apellidos || ''}">
                    </div>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label">Apodo</label>
                        <input id="f-apodo" class="form-control form-control-sm" value="${datos?.apodo || ''}">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Género</label>
                        <select id="f-genero" class="form-select form-select-sm">
                            <option value="desconocido" ${!datos || datos?.genero === 'desconocido' ? 'selected' : ''}>Desconocido</option>
                            <option value="masculino" ${datos?.genero === 'masculino' ? 'selected' : ''}>Masculino</option>
                            <option value="femenino" ${datos?.genero === 'femenino' ? 'selected' : ''}>Femenino</option>
                            <option value="otro" ${datos?.genero === 'otro' ? 'selected' : ''}>Otro</option>
                        </select>
                    </div>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label">Fecha de nacimiento</label>
                        <input id="f-nace" type="date" class="form-control form-control-sm" value="${datos?.fecha_nacimiento || ''}">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Fecha de fallecimiento</label>
                        <input id="f-muere" type="date" class="form-control form-control-sm" value="${datos?.fecha_fallecimiento || ''}">
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label">Lugar de nacimiento</label>
                    <input id="f-lugar" class="form-control form-control-sm" value="${datos?.lugar_nacimiento || ''}">
                </div>
                <div class="mb-2">
                    <label class="form-label">Biografía</label>
                    <textarea id="f-bio" class="form-control form-control-sm">${datos?.biografia || ''}</textarea>
                </div>
                <div>
                    <label class="form-label">Foto de perfil</label>
                    <input id="f-foto" type="file" class="form-control form-control-sm" accept="image/png,image/jpeg,image/webp">
                </div>
            </div>`,
        showCancelButton: true,
        confirmButtonText: esEdicion ? 'Guardar cambios' : 'Crear persona',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#e8b84b',
        width: '560px',
        preConfirm: () => {
            const nombres = document.getElementById('f-nombres').value.trim();
            const apellidos = document.getElementById('f-apellidos').value.trim();
            if (!nombres || !apellidos) {
                Swal.showValidationMessage('Nombres y apellidos son obligatorios');
                return false;
            }
            return true;
        },
    });

    if (!isConfirmed || !ok) return;

    const body = new FormData();
    if (esEdicion) body.append('id', datos.id);
    body.append('nombres', document.getElementById('f-nombres').value.trim());
    body.append('apellidos', document.getElementById('f-apellidos').value.trim());
    body.append('apodo', document.getElementById('f-apodo').value.trim());
    body.append('genero', document.getElementById('f-genero').value);
    body.append('fecha_nacimiento', document.getElementById('f-nace').value);
    body.append('fecha_fallecimiento', document.getElementById('f-muere').value);
    body.append('lugar_nacimiento', document.getElementById('f-lugar').value.trim());
    body.append('biografia', document.getElementById('f-bio').value.trim());

    const archivo = document.getElementById('f-foto').files[0];
    if (archivo) body.append('foto_perfil', archivo);

    const url = esEdicion ? `${BASE}/api/personas/modificar` : `${BASE}/api/personas/guardar`;
    const r = await fetch(url, { method: 'POST', body });
    const d = await r.json();

    Toast.fire({ icon: d.codigo === 1 ? 'success' : 'error', title: d.mensaje });
    if (d.codigo === 1) { obtenerTodas(); cargarFamilias(); }
}

window.editarPersona = (id) => {
    const persona = cachePersonas.find((p) => p.id == id);
    if (persona) mostrarFormPersona(persona);
};

window.eliminarPersona = async (id) => {
    const conf = await Swal.fire({
        icon: 'warning',
        title: '¿Eliminar esta persona?',
        text: 'También se eliminarán sus fotos, uniones y filiaciones asociadas.',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#c96b4b',
    });
    if (!conf.isConfirmed) return;

    const body = new FormData();
    body.append('id', id);
    const r = await fetch(`${BASE}/api/personas/eliminar`, { method: 'POST', body });
    const d = await r.json();
    Toast.fire({ icon: d.codigo === 1 ? 'success' : 'error', title: d.mensaje });
    if (d.codigo === 1) { obtenerTodas(); cargarFamilias(); }
};

// ── Agregar unión (pareja) ─────────────────────────────────────────────
window.gestionarUnion = async (personaId) => {
    const todas = await obtenerTodas();

    // Excluir a TODA la red familiar de esta persona (progenitores, hijos,
    // hermanos, abuelos, tios, primos, parejas actuales y pasadas -- todo
    // en cascada), no solo a los parientes directos.
    const [rRed, rUniones, rUnionesActivas] = await Promise.all([
        fetch(`${BASE}/api/personas/red-familiar?id=${personaId}`),
        fetch(`${BASE}/api/personas/uniones?id=${personaId}`),
        fetch(`${BASE}/api/personas/uniones-activas`),
    ]);
    const dRed = await rRed.json();
    const dUniones = await rUniones.json();
    const dUnionesActivas = await rUnionesActivas.json();
    const redIds = dRed.codigo === 1 ? dRed.datos : [];
    const unionesExistentes = dUniones.codigo === 1 ? dUniones.datos : [];
    const unionesActivasMap = dUnionesActivas.codigo === 1 ? dUnionesActivas.datos : {};
    const excluir = [personaId, ...redIds];

    // Heterosexual por defecto: si conocemos el genero de quien inicia,
    // solo mostramos candidatos de genero distinto. Si no lo sabemos
    // (null/otro), no filtramos por genero.
    const personaActual = todas.find((p) => String(p.id) === String(personaId));
    const generoOpuesto = personaActual?.genero === 'masculino' ? 'femenino'
        : personaActual?.genero === 'femenino' ? 'masculino'
            : null;

    const disponibles = todas.filter((p) => {
        if (excluir.map(String).includes(String(p.id))) return false;
        if (generoOpuesto && p.genero && p.genero !== generoOpuesto) return false;
        return true;
    });

    // Aviso (no bloqueo) si ya tiene una union activa -- una segunda union
    // es valida (viudez, divorcio, segundas nupcias), pero DOS activas a
    // la vez suele ser un error de carga, asi que lo señalamos.
    const unionActivaExistente = unionesExistentes.find((u) => u.estado === 'activa');
    const avisoActiva = unionActivaExistente
        ? `<div style="background:rgba(232,184,75,.18);border:1px solid #c9a24b;border-radius:8px;
              padding:.5rem .75rem;margin-bottom:.6rem;font-size:.78rem;color:#6b5a20;">
              ⚠️ Ya tiene una unión <strong>activa</strong> registrada${unionActivaExistente.pareja ? ' con ' + unionActivaExistente.pareja.nombres + ' ' + unionActivaExistente.pareja.apellidos : ''}.
              Si esta nueva unión también va a quedar como "Activa", asegurate de que sea correcto
              (por ejemplo, no cargar dos matrimonios activos por error).
           </div>`
        : '';

    const { value: form, isConfirmed } = await Swal.fire({
        title: 'Registrar unión',
        html: `
            <div class="text-start small">
                ${avisoActiva}
                <label class="form-label">Pareja</label>
                <select id="u-pareja" class="form-select form-select-sm mb-1">
                    <option value="">— Sin pareja registrada —</option>
                    ${opcionesSelect(disponibles, [])}
                </select>
                <div id="u-pareja-preview"></div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label">Tipo</label>
                        <select id="u-tipo" class="form-select form-select-sm">
                            <option value="matrimonio">Matrimonio</option>
                            <option value="union_libre">Unión libre</option>
                            <option value="pareja">Pareja</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Estado</label>
                        <select id="u-estado" class="form-select form-select-sm">
                            <option value="activa">Activa</option>
                            <option value="separada">Separada</option>
                            <option value="divorciada">Divorciada</option>
                            <option value="viuda">Viuda</option>
                            <option value="terminada">Terminada</option>
                        </select>
                    </div>
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label">Fecha de inicio</label>
                        <input id="u-inicio" type="date" class="form-control form-control-sm">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Fecha de fin</label>
                        <input id="u-fin" type="date" class="form-control form-control-sm">
                    </div>
                </div>
            </div>`,
        didOpen: () => activarPreviewSelect('u-pareja', 'u-pareja-preview', disponibles, unionesActivasMap),
        showCancelButton: true,
        confirmButtonText: 'Guardar unión',
        confirmButtonColor: '#e8b84b',
        width: '520px',
        preConfirm: () => true,
    });

    if (!isConfirmed || !form) return;

    const parejaId = document.getElementById('u-pareja').value;
    const body = new FormData();
    body.append('persona_a_id', personaId);
    body.append('persona_b_id', parejaId);
    body.append('tipo', document.getElementById('u-tipo').value);
    body.append('estado', document.getElementById('u-estado').value);
    body.append('fecha_inicio', document.getElementById('u-inicio').value);
    body.append('fecha_fin', document.getElementById('u-fin').value);

    const r = await fetch(`${BASE}/api/uniones/guardar`, { method: 'POST', body });
    const d = await r.json();
    Toast.fire({ icon: d.codigo === 1 ? 'success' : 'error', title: d.mensaje });

    if (d.codigo === 1) {
        if (parejaId) {
            await ofrecerVincularHijosCompartidos(personaId, parejaId, d.datos ? d.datos.id : null);
        }
        cargarFamilias();
    }
};

// Si al crear la union, una de las dos personas ya tenia hijos que la
// otra todavia no tiene vinculados (el caso de un progenitor soltero que
// despues forma pareja), lo detecta y ofrece vincularlos de una vez, en
// vez de tener que ir "a pie" a hacerlo persona por persona.
async function ofrecerVincularHijosCompartidos(personaAId, personaBId, unionId) {
    const [rHijosA, rHijosB] = await Promise.all([
        fetch(`${BASE}/api/personas/hijos?id=${personaAId}`),
        fetch(`${BASE}/api/personas/hijos?id=${personaBId}`),
    ]);
    const dHijosA = await rHijosA.json();
    const dHijosB = await rHijosB.json();
    const hijosA = dHijosA.codigo === 1 ? dHijosA.datos : [];
    const hijosB = dHijosB.codigo === 1 ? dHijosB.datos : [];
    const idsA = hijosA.map((h) => String(h.id));
    const idsB = hijosB.map((h) => String(h.id));

    const faltantes = [
        ...hijosA.filter((h) => !idsB.includes(String(h.id))).map((h) => ({ ...h, progenitorFaltante: personaBId })),
        ...hijosB.filter((h) => !idsA.includes(String(h.id))).map((h) => ({ ...h, progenitorFaltante: personaAId })),
    ];

    if (!faltantes.length) return;

    const nombres = faltantes.map((h) => `${h.nombres} ${h.apellidos}`).join(', ');
    const conf = await Swal.fire({
        icon: 'question',
        title: '¿Vincular también con el otro progenitor?',
        html: `<p class="text-start">
                 <strong>${nombres}</strong> ya está(n) vinculado/a(s) a solo uno de los dos.
                 ¿Querés vincularlo/a(s) también con el otro, dentro de esta misma unión?
               </p>`,
        showCancelButton: true,
        confirmButtonText: 'Sí, vincular',
        cancelButtonText: 'No, gracias',
        confirmButtonColor: '#e8b84b',
    });

    if (!conf.isConfirmed) return;

    for (const h of faltantes) {
        const body = new FormData();
        body.append('hijo_id', h.id);
        body.append('progenitor_id', h.progenitorFaltante);
        body.append('union_id', unionId || '');
        body.append('tipo_relacion', 'biologico');
        await fetch(`${BASE}/api/filiaciones/guardar`, { method: 'POST', body });
    }

    Toast.fire({ icon: 'success', title: 'Vinculados correctamente' });
}

// ── Agregar hijo/a (filiación) ────────────────────────────────────────
window.gestionarHijo = async (progenitorId) => {
    const todas = await obtenerTodas();

    // Excluir a TODA la red familiar de este progenitor (ya cubre hijos
    // ya vinculados, parejas, hermanos, abuelos, etc.), y a quienes ya
    // tienen 2+ progenitores registrados (ya no necesitan mas)
    const [rRed, rUniones, rDosProgenitores] = await Promise.all([
        fetch(`${BASE}/api/personas/red-familiar?id=${progenitorId}`),
        fetch(`${BASE}/api/personas/uniones?id=${progenitorId}`),
        fetch(`${BASE}/api/personas/con-dos-progenitores`),
    ]);
    const dRed = await rRed.json();
    const dUniones = await rUniones.json();
    const dDosProgenitores = await rDosProgenitores.json();
    const redIds = dRed.codigo === 1 ? dRed.datos : [];
    const uniones = dUniones.codigo === 1 ? dUniones.datos : [];
    const dosProgenitoresIds = dDosProgenitores.codigo === 1 ? dDosProgenitores.datos : [];
    const excluir = [progenitorId, ...redIds, ...dosProgenitoresIds];

    const disponibles = todas.filter((p) => !excluir.map(String).includes(String(p.id)));

    if (!disponibles.length) {
        Swal.fire({
            icon: 'info',
            title: 'No hay personas disponibles',
            text: 'Todas las personas registradas ya son parte de esta familia. Creá una persona nueva primero si querés vincular a alguien más.',
            confirmButtonColor: '#e8b84b',
        });
        return;
    }

    const opcionesUnion = uniones.map((u, i) => {
        const etiquetaPareja = u.pareja ? `${u.pareja.nombres} ${u.pareja.apellidos}` : 'sin pareja registrada';
        return `<option value="${i}">${u.tipo === 'matrimonio' ? 'Matrimonio' : 'Unión'} con ${etiquetaPareja} (${u.estado})</option>`;
    }).join('');

    const { value: form, isConfirmed } = await Swal.fire({
        title: 'Vincular hijo/a',
        html: `
            <div class="text-start small">
                <label class="form-label">Hijo/a (debe existir ya como persona)</label>
                <select id="h-hijo" class="form-select form-select-sm mb-1">
                    ${opcionesSelect(disponibles, [])}
                </select>
                <div id="h-hijo-preview"></div>
                ${uniones.length ? `
                <label class="form-label">Pertenece a esta unión</label>
                <select id="h-union" class="form-select form-select-sm mb-2">
                    ${opcionesUnion}
                    <option value="">— Sin unión específica —</option>
                </select>
                <p class="text-muted small mb-2" id="h-aviso-pareja"></p>
                ` : '<input type="hidden" id="h-union" value="">'}
                <label class="form-label">Tipo de relación</label>
                <select id="h-tipo" class="form-select form-select-sm mb-2">
                    <option value="biologico">Biológico/a</option>
                    <option value="adoptivo">Adoptivo/a</option>
                    <option value="padrastro">Hijastro/a (padrastro)</option>
                    <option value="madrastra">Hijastro/a (madrastra)</option>
                    <option value="tutor">Bajo tutela</option>
                </select>
                <p class="text-muted small mb-0">
                    Si esta persona no existe todavía, cerrá esto y creala primero con "Nueva persona".
                </p>
            </div>`,
        didOpen: () => {
            activarPreviewSelect('h-hijo', 'h-hijo-preview', disponibles);
            const selectUnion = document.getElementById('h-union');
            const aviso = document.getElementById('h-aviso-pareja');
            if (!selectUnion || !aviso || selectUnion.tagName !== 'SELECT') return;
            const actualizarAviso = () => {
                const u = uniones[selectUnion.value];
                aviso.textContent = u && u.pareja
                    ? `Se vinculará también como hijo/a de ${u.pareja.nombres} ${u.pareja.apellidos} automáticamente.`
                    : '';
            };
            selectUnion.addEventListener('change', actualizarAviso);
            actualizarAviso();
        },
        showCancelButton: true,
        confirmButtonText: 'Vincular',
        confirmButtonColor: '#e8b84b',
        width: '480px',
        preConfirm: () => {
            if (!document.getElementById('h-hijo').value) {
                Swal.showValidationMessage('Selecciona a la persona hija');
                return false;
            }
            return true;
        },
    });

    if (!isConfirmed || !form) return;

    const hijoId = document.getElementById('h-hijo').value;
    const tipoRelacion = document.getElementById('h-tipo').value;
    const indiceUnion = document.getElementById('h-union').value;
    const unionSeleccionada = indiceUnion !== '' ? uniones[indiceUnion] : null;

    const vincular = async (progenitorVinculo) => {
        const body = new FormData();
        body.append('hijo_id', hijoId);
        body.append('progenitor_id', progenitorVinculo);
        body.append('union_id', unionSeleccionada ? unionSeleccionada.union_id : '');
        body.append('tipo_relacion', tipoRelacion);
        const r = await fetch(`${BASE}/api/filiaciones/guardar`, { method: 'POST', body });
        return r.json();
    };

    const resultadoPrincipal = await vincular(progenitorId);

    let mensaje = resultadoPrincipal.mensaje;
    if (resultadoPrincipal.codigo === 1 && unionSeleccionada && unionSeleccionada.pareja) {
        const resultadoPareja = await vincular(unionSeleccionada.pareja.id);
        if (resultadoPareja.codigo === 1) {
            mensaje = 'Hijo/a vinculado/a con ambos progenitores';
        }
        // si la pareja ya lo tenia vinculado (duplicado), no es un error real -- se ignora en silencio
    }

    Toast.fire({ icon: resultadoPrincipal.codigo === 1 ? 'success' : 'error', title: mensaje });
    if (resultadoPrincipal.codigo === 1) cargarFamilias();
};

// ── Fijar raíz del árbol ─────────────────────────────────────────────
window.fijarRaiz = async (id) => {
    const body = new FormData();
    body.append('persona_id', id);
    const r = await fetch(`${BASE}/api/arbol/raiz`, { method: 'POST', body });
    const d = await r.json();
    Toast.fire({ icon: d.codigo === 1 ? 'success' : 'error', title: d.mensaje });
};

// ── Vincular como hijo/a de... (el espejo de gestionarHijo, desde el ────
// lado del hijo: eligís vos quién es su progenitor/a) ────────────────────
window.gestionarProgenitor = async (hijoId) => {
    const todas = await obtenerTodas();

    // Excluir a TODA la red familiar de esta persona (padres actuales,
    // hijos propios, hermanos, abuelos, etc. en un solo llamado)
    const rRed = await fetch(`${BASE}/api/personas/red-familiar?id=${hijoId}`);
    const dRed = await rRed.json();
    const redIds = dRed.codigo === 1 ? dRed.datos : [];
    const excluir = [hijoId, ...redIds];

    const disponibles = todas.filter((p) => !excluir.map(String).includes(String(p.id)));
    if (!disponibles.length) {
        Swal.fire({
            icon: 'info',
            title: 'No hay personas disponibles',
            text: 'No hay a quién vincular como progenitor/a. Creá una persona nueva primero si hace falta.',
            confirmButtonColor: '#e8b84b',
        });
        return;
    }

    const { isConfirmed: pasoUno } = await Swal.fire({
        title: 'Vincular como hijo/a de...',
        html: `
            <div class="text-start small">
                <label class="form-label">Progenitor/a (padre o madre)</label>
                <select id="pg-progenitor" class="form-select form-select-sm mb-1">
                    ${opcionesSelect(disponibles, [])}
                </select>
                <div id="pg-progenitor-preview"></div>
                <label class="form-label">Tipo de relación</label>
                <select id="pg-tipo" class="form-select form-select-sm">
                    <option value="biologico">Biológico/a</option>
                    <option value="adoptivo">Adoptivo/a</option>
                    <option value="padrastro">Hijastro/a (padrastro)</option>
                    <option value="madrastra">Hijastro/a (madrastra)</option>
                    <option value="tutor">Bajo tutela</option>
                </select>
            </div>`,
        didOpen: () => activarPreviewSelect('pg-progenitor', 'pg-progenitor-preview', disponibles),
        showCancelButton: true,
        confirmButtonText: 'Continuar',
        confirmButtonColor: '#e8b84b',
        width: '420px',
        preConfirm: () => {
            if (!document.getElementById('pg-progenitor').value) {
                Swal.showValidationMessage('Selecciona a la persona progenitora');
                return false;
            }
            return true;
        },
    });
    if (!pasoUno) return;

    const progenitorId = document.getElementById('pg-progenitor').value;
    const tipoRelacion = document.getElementById('pg-tipo').value;

    // Paso 2: si ese progenitor tiene pareja(s), preguntar a que union
    // pertenece, para vincular tambien al otro progenitor automaticamente
    const rUniones = await fetch(`${BASE}/api/personas/uniones?id=${progenitorId}`);
    const dUniones = await rUniones.json();
    const uniones = dUniones.codigo === 1 ? dUniones.datos : [];

    let unionSeleccionada = null;
    if (uniones.length) {
        const opcionesUnion = uniones.map((u, i) => {
            const etiquetaPareja = u.pareja ? `${u.pareja.nombres} ${u.pareja.apellidos}` : 'sin pareja registrada';
            return `<option value="${i}">${u.tipo === 'matrimonio' ? 'Matrimonio' : 'Unión'} con ${etiquetaPareja} (${u.estado})</option>`;
        }).join('');

        const { isConfirmed: pasoDos } = await Swal.fire({
            title: '¿Pertenece a alguna de estas uniones?',
            html: `
                <div class="text-start small">
                    <select id="pg-union" class="form-select form-select-sm">
                        ${opcionesUnion}
                        <option value="">— Sin unión específica —</option>
                    </select>
                    <p class="text-muted small mt-2 mb-0">
                        Si elegís una unión, también se vinculará automáticamente con el otro progenitor.
                    </p>
                </div>`,
            showCancelButton: true,
            confirmButtonText: 'Vincular',
            confirmButtonColor: '#e8b84b',
            width: '420px',
            preConfirm: () => true,
        });
        if (!pasoDos) return;

        const idx = document.getElementById('pg-union').value;
        unionSeleccionada = idx !== '' ? uniones[idx] : null;
    }

    const vincular = async (progenitorVinculo) => {
        const body = new FormData();
        body.append('hijo_id', hijoId);
        body.append('progenitor_id', progenitorVinculo);
        body.append('union_id', unionSeleccionada ? unionSeleccionada.union_id : '');
        body.append('tipo_relacion', tipoRelacion);
        const r = await fetch(`${BASE}/api/filiaciones/guardar`, { method: 'POST', body });
        return r.json();
    };

    const resultadoPrincipal = await vincular(progenitorId);
    let mensaje = resultadoPrincipal.mensaje;

    if (resultadoPrincipal.codigo === 1 && unionSeleccionada && unionSeleccionada.pareja) {
        const resultadoPareja = await vincular(unionSeleccionada.pareja.id);
        if (resultadoPareja.codigo === 1) {
            mensaje = 'Vinculado/a con ambos progenitores';
        }
    }

    Toast.fire({ icon: resultadoPrincipal.codigo === 1 ? 'success' : 'error', title: mensaje });
    if (resultadoPrincipal.codigo === 1) cargarFamilias();
};

// ── Init ──────────────────────────────────────────────────────────────
document.getElementById('btnNuevaPersona').addEventListener('click', () => mostrarFormPersona());
obtenerTodas();
cargarFamilias();