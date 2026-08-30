import Swal from 'sweetalert2';
import { Dropdown } from 'bootstrap';

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
function personaCardHTML(p, dropdownId, sinAsociar = false) {
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
                    <div class="dropdown dropdown-mas">
                        <button class="btn-mas dropdown-toggle" type="button"
                                id="${dropdownId}" data-bs-toggle="dropdown" aria-expanded="false">
                            Más
                        </button>
                        <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end" aria-labelledby="${dropdownId}">
                            <li><a class="dropdown-item" href="#" onclick="gestionarUnion(${p.id});return false;">
                                <i class="bi bi-heart"></i> Agregar unión
                            </a></li>
                            <li><a class="dropdown-item" href="#" onclick="gestionarHijo(${p.id});return false;">
                                <i class="bi bi-diagram-3"></i> Vincular hijo/a
                            </a></li>
                            <li><a class="dropdown-item" href="#" onclick="gestionarProgenitor(${p.id});return false;">
                                <i class="bi bi-diagram-3-fill"></i> Vincular como hijo/a de...
                            </a></li>
                            <li><a class="dropdown-item" href="#" onclick="fijarRaiz(${p.id});return false;">
                                <i class="bi bi-flag"></i> Fijar como raíz
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="#" onclick="eliminarPersona(${p.id});return false;">
                                <i class="bi bi-trash3"></i> Eliminar
                            </a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>`;
}

// ── Vista de una familia en pantalla completa (reemplaza el listado) ────
window.mostrarVistaFamilia = (indice) => {
    const familia = familiasCache[indice];
    if (!familia) return;

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
    html += miembrosCompletos.map((p, i) => `<div class="col">${personaCardHTML(p, `dropdownFam${i}`)}</div>`).join('');
    html += `</div>`;

    contenedor.innerHTML = html;
    contenedor.querySelectorAll('[data-bs-toggle="dropdown"]').forEach((el) => {
        if (!Dropdown.getInstance(el)) new Dropdown(el);
    });
};

window.volverAFamilias = () => {
    const tituloEl = document.querySelector('.personas-header .titulo');
    const buscadorEl = document.querySelector('.buscador-wrap');
    if (tituloEl) tituloEl.textContent = 'Personas registradas';
    if (buscadorEl) buscadorEl.style.display = '';
    renderFamilias(familiasCache, sinAsociarCache);
};

// ── Cargar y pintar: familias + personas sin asociar ────────────────────
let familiasCache = [];
let sinAsociarCache = [];
async function cargarFamilias() {
    const r = await fetch(`${BASE}/api/personas/familias`);
    const d = await r.json();
    if (d.codigo !== 1) return;
    familiasCache = d.datos.familias;
    sinAsociarCache = d.datos.sin_asociar;
    renderFamilias(d.datos.familias, d.datos.sin_asociar);
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
        html += sinAsociar.map((p, i) => {
            const buscar = `${p.nombres} ${p.apellidos}`.toLowerCase();
            return `<div class="col persona-item" data-buscar="${buscar}">${personaCardHTML(p, `dropdownSuelta${i}`, true)}</div>`;
        }).join('');
        html += `</div>`;
    }

    contenedor.innerHTML = html;

    // Los dropdowns con data-bs-toggle se activan solos si el bundle de
    // Bootstrap ya esta cargado globalmente; esto es un respaldo explicito
    // por si este entry es el primero en importar el componente Dropdown.
    contenedor.querySelectorAll('[data-bs-toggle="dropdown"]').forEach((el) => {
        if (!Dropdown.getInstance(el)) new Dropdown(el);
    });
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

    // Excluir a la propia persona, a sus progenitores, a sus hijos y a sus
    // hermanos/medios hermanos (no puede ser pareja de ninguno de esos)
    const [rProgenitores, rHijos, rUniones, rHermanos] = await Promise.all([
        fetch(`${BASE}/api/personas/progenitores?id=${personaId}`),
        fetch(`${BASE}/api/personas/hijos?id=${personaId}`),
        fetch(`${BASE}/api/personas/uniones?id=${personaId}`),
        fetch(`${BASE}/api/personas/hermanos?id=${personaId}`),
    ]);
    const dProgenitores = await rProgenitores.json();
    const dHijos = await rHijos.json();
    const dUniones = await rUniones.json();
    const dHermanos = await rHermanos.json();
    const progenitoresIds = dProgenitores.codigo === 1 ? dProgenitores.datos.map((p) => p.id) : [];
    const hijosIds = dHijos.codigo === 1 ? dHijos.datos.map((h) => h.id) : [];
    const hermanosIds = dHermanos.codigo === 1 ? dHermanos.datos.map((h) => h.id) : [];
    const unionesExistentes = dUniones.codigo === 1 ? dUniones.datos : [];
    const excluir = [personaId, ...progenitoresIds, ...hijosIds, ...hermanosIds];

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
                <select id="u-pareja" class="form-select form-select-sm mb-2">
                    <option value="">— Sin pareja registrada —</option>
                    ${opcionesSelect(todas, excluir)}
                </select>
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
        showCancelButton: true,
        confirmButtonText: 'Guardar unión',
        confirmButtonColor: '#e8b84b',
        width: '520px',
        preConfirm: () => true,
    });

    if (!isConfirmed || !form) return;

    const body = new FormData();
    body.append('persona_a_id', personaId);
    body.append('persona_b_id', document.getElementById('u-pareja').value);
    body.append('tipo', document.getElementById('u-tipo').value);
    body.append('estado', document.getElementById('u-estado').value);
    body.append('fecha_inicio', document.getElementById('u-inicio').value);
    body.append('fecha_fin', document.getElementById('u-fin').value);

    const r = await fetch(`${BASE}/api/uniones/guardar`, { method: 'POST', body });
    const d = await r.json();
    Toast.fire({ icon: d.codigo === 1 ? 'success' : 'error', title: d.mensaje });
    if (d.codigo === 1) cargarFamilias();
};

// ── Agregar hijo/a (filiación) ────────────────────────────────────────
window.gestionarHijo = async (progenitorId) => {
    const todas = await obtenerTodas();

    // Excluir a la propia persona, a los hijos ya vinculados, y a su(s) pareja(s)
    const [rHijos, rParejas, rUniones] = await Promise.all([
        fetch(`${BASE}/api/personas/hijos?id=${progenitorId}`),
        fetch(`${BASE}/api/personas/parejas?id=${progenitorId}`),
        fetch(`${BASE}/api/personas/uniones?id=${progenitorId}`),
    ]);
    const dHijos = await rHijos.json();
    const dParejas = await rParejas.json();
    const dUniones = await rUniones.json();
    const yaVinculados = dHijos.codigo === 1 ? dHijos.datos.map((h) => h.id) : [];
    const parejas = dParejas.codigo === 1 ? dParejas.datos.map((p) => p.id) : [];
    const uniones = dUniones.codigo === 1 ? dUniones.datos : [];
    const excluir = [progenitorId, ...yaVinculados, ...parejas];

    const disponibles = todas.filter((p) => !excluir.map(String).includes(String(p.id)));

    if (!disponibles.length) {
        Swal.fire({
            icon: 'info',
            title: 'No hay personas disponibles',
            text: 'Todas las personas registradas ya son hijos/as o parejas de esta persona. Creá una persona nueva primero si querés vincular a alguien más.',
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
                <select id="h-hijo" class="form-select form-select-sm mb-2">
                    ${opcionesSelect(disponibles, [])}
                </select>
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

    // Excluir a la propia persona, a sus propios hijos (no puede ser hijo
    // de su propio hijo/a) y a quienes ya sean sus progenitores
    const [rHijosPropios, rProgenitoresActuales] = await Promise.all([
        fetch(`${BASE}/api/personas/hijos?id=${hijoId}`),
        fetch(`${BASE}/api/personas/progenitores?id=${hijoId}`),
    ]);
    const dHijosPropios = await rHijosPropios.json();
    const dProgenitoresActuales = await rProgenitoresActuales.json();
    const hijosPropiosIds = dHijosPropios.codigo === 1 ? dHijosPropios.datos.map((h) => h.id) : [];
    const progenitoresActualesIds = dProgenitoresActuales.codigo === 1 ? dProgenitoresActuales.datos.map((p) => p.id) : [];
    const excluir = [hijoId, ...hijosPropiosIds, ...progenitoresActualesIds];

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
                <select id="pg-progenitor" class="form-select form-select-sm mb-2">
                    ${opcionesSelect(disponibles, [])}
                </select>
                <label class="form-label">Tipo de relación</label>
                <select id="pg-tipo" class="form-select form-select-sm">
                    <option value="biologico">Biológico/a</option>
                    <option value="adoptivo">Adoptivo/a</option>
                    <option value="padrastro">Hijastro/a (padrastro)</option>
                    <option value="madrastra">Hijastro/a (madrastra)</option>
                    <option value="tutor">Bajo tutela</option>
                </select>
            </div>`,
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