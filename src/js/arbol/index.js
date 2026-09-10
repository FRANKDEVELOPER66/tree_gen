import Swal from 'sweetalert2';
import { Dropdown } from "bootstrap";


const BASE = document.querySelector('[data-base]')?.dataset.base ?? '';

const lienzo = document.getElementById('lienzo');
const migas = document.getElementById('migas');
const panelDetalle = document.getElementById('panelDetalle');

// Pila de navegacion: [{id, nombre}] — el ultimo es el nucleo actual
let pila = [];
// Indice de la union activa (pestaña) dentro del nucleo actual
let unionActivaIndex = 0;

function urlFoto(nombreArchivo) {
    return nombreArchivo ? `${BASE}/public/uploads/${nombreArchivo}` : null;
}

function edadOFechas(persona) {
    const nace = persona.fecha_nacimiento ? persona.fecha_nacimiento.substring(0, 4) : '?';
    const muere = persona.fecha_fallecimiento ? persona.fecha_fallecimiento.substring(0, 4) : '';
    if (!persona.fecha_nacimiento && !persona.fecha_fallecimiento) return '';
    return muere ? `${nace} – ${muere}` : `${nace}`;
}

function iniciales(nombres, apellidos) {
    const a = (nombres || '?').trim()[0] || '?';
    const b = (apellidos || '').trim()[0] || '';
    return (a + b).toUpperCase();
}

// ── Etiquetas de parentesco (Padre/Madre/Hijo/Hija/Esposo/Esposa...) ────────
function rolProgenitor(persona) {
    if (persona.genero === 'femenino') return 'Madre';
    if (persona.genero === 'masculino') return 'Padre';
    return 'Progenitor/a';
}

function rolHijo(persona) {
    if (persona.genero === 'femenino') return 'Hija';
    if (persona.genero === 'masculino') return 'Hijo';
    return 'Hijo/a';
}

function rolPareja(persona, tipoUnion) {
    if (tipoUnion === 'matrimonio') {
        if (persona.genero === 'femenino') return 'Esposa';
        if (persona.genero === 'masculino') return 'Esposo';
        return 'Cónyuge';
    }
    return 'Pareja';
}

const ETIQUETAS_FILIACION = {
    biologico: null,
    adoptivo: 'Adoptivo/a',
    padrastro: 'Hijastro/a',
    madrastra: 'Hijastro/a',
    tutor: 'Bajo tutela',
};

// ── Retrato (nodo persona), con marco en arco y ficha aparte ────────────────
function retratoHTML(persona, { central = false, mini = false, rol = null, etiqueta = null } = {}) {
    const foto = urlFoto(persona.foto_perfil);
    const fallecido = persona.fecha_fallecimiento ? 'fallecido' : '';
    const claseMarco = mini ? 'retrato-marco retrato-chico' : 'retrato-marco';
    const contenidoImg = foto
        ? `<img src="${foto}" alt="${persona.nombres}" loading="lazy">`
        : `<div class="sin-foto">${iniciales(persona.nombres, persona.apellidos)}</div>`;

    if (mini) {
        return `
            <div class="progenitor-mini" data-id="${persona.id}" title="Subir a ${persona.nombres}">
                <div class="${claseMarco} ${fallecido}">${contenidoImg}</div>
                <span>${persona.nombres} ${persona.apellidos}</span>
            </div>`;
    }

    return `
        <div class="retrato-persona ${central ? 'central' : ''}" data-id="${persona.id}">
            <div class="marco-wrap">
                <div class="${claseMarco} ${fallecido}">${contenidoImg}</div>
                <button class="btn-ficha" data-ficha-id="${persona.id}" title="Ver ficha técnica" aria-label="Ver ficha técnica">
                    <i class="bi bi-info-circle-fill"></i>
                </button>
            </div>
            <div class="retrato-nombre">${persona.nombres}<br>${persona.apellidos}</div>
            ${rol ? `<div class="retrato-rol">${rol}</div>` : ''}
            <div class="retrato-fechas">${edadOFechas(persona)}</div>
            ${etiqueta ? `<div class="etiqueta-filiacion">${etiqueta}</div>` : ''}
        </div>`;
}

async function cargarNucleo(personaId, { agregarAPila = true } = {}) {
    lienzo.innerHTML = '<div class="cargando">Abriendo el registro familiar…</div>';

    const resp = await fetch(`${BASE}/api/arbol/nucleo?id=${personaId}`);
    const data = await resp.json();

    if (data.codigo === 0 || !data.datos) {
        lienzo.innerHTML = `<div class="vacio-arbol">No se encontró esa persona en el registro.</div>`;
        return;
    }

    const { persona, nucleos, progenitores, hijosSueltos } = data.datos;

    if (agregarAPila) {
        const posExistente = pila.findIndex((p) => p.id === persona.id);
        if (posExistente >= 0) {
            pila = pila.slice(0, posExistente + 1);
        } else {
            pila.push({ id: persona.id, nombre: `${persona.nombres} ${persona.apellidos}` });
        }
    }

    unionActivaIndex = 0;
    pintarMigas();
    pintarNucleo(persona, nucleos, progenitores, hijosSueltos || []);
}

function pintarMigas() {
    if (pila.length <= 1) {
        migas.innerHTML = pila.length ? `<span class="miga actual">${pila[0].nombre}</span>` : '';
        return;
    }
    migas.innerHTML = pila
        .map((p, i) => {
            const esUltimo = i === pila.length - 1;
            return `<span class="miga ${esUltimo ? 'actual' : ''}" data-id="${p.id}">${p.nombre}</span>` +
                (esUltimo ? '' : '<span class="sep">›</span>');
        })
        .join('');
}

function pintarNucleo(persona, nucleos, progenitores, hijosSueltos = []) {
    const nucleoActivo = nucleos[unionActivaIndex] || null;
    const hijos = nucleoActivo ? nucleoActivo.hijos : [];

    let html = '';

    if (progenitores && progenitores.length) {
        html += `<div class="fila-progenitores">`;
        html += progenitores.map((p) => retratoHTML(p, { mini: true })).join('');
        html += `</div>`;
    }

    html += `<div class="pareja-central">`;
    html += retratoHTML(persona, { central: true });
    if (nucleoActivo && nucleoActivo.pareja) {
        html += `
            <div class="simbolo-union">
                ∞
                <span class="rango">${nucleoActivo.tipo === 'matrimonio' ? 'Matrimonio' : 'Unión'}${nucleoActivo.estado !== 'activa' ? ' · ' + nucleoActivo.estado : ''}</span>
            </div>`;
        html += retratoHTML(nucleoActivo.pareja, { central: true, rol: rolPareja(nucleoActivo.pareja, nucleoActivo.tipo) });
    }
    html += `</div>`;

    if (nucleos.length > 1) {
        html += `<div class="pestanas-uniones">`;
        html += nucleos
            .map((n, i) => {
                const etiquetaPersona = n.pareja ? `${n.pareja.nombres} ${n.pareja.apellidos}` : 'Sin pareja registrada';
                return `<button class="pestana-union ${i === unionActivaIndex ? 'activa' : ''}" data-index="${i}">
                    ${etiquetaPersona}<span class="estado-badge">${n.estado}</span>
                </button>`;
            })
            .join('');
        html += `</div>`;
    }

    if (nucleos.length) {
        html += `<div class="linea-descendencia"></div>`;
        if (hijos.length) {
            html += `<div class="fila-hijos">`;
            html += hijos.map((h) => retratoHTML(h, {
                rol: rolHijo(h),
                etiqueta: ETIQUETAS_FILIACION[h.tipo_relacion] || null,
            })).join('');
            html += `</div>`;
        } else if (!hijosSueltos.length) {
            html += `<div class="sin-hijos">Sin descendencia registrada</div>`;
        }

        if (hijosSueltos.length) {
            html += `<div class="seccion-hijos-sueltos-titulo">Otros/as hijos/as (sin pareja registrada)</div>`;
            html += `<div class="fila-hijos">`;
            html += hijosSueltos.map((h) => retratoHTML(h, {
                rol: rolHijo(h),
                etiqueta: ETIQUETAS_FILIACION[h.tipo_relacion] || null,
            })).join('');
            html += `</div>`;
        }
    } else if (hijosSueltos.length) {
        html += `<div class="linea-descendencia"></div>`;
        html += `<div class="fila-hijos">`;
        html += hijosSueltos.map((h) => retratoHTML(h, {
            rol: rolHijo(h),
            etiqueta: ETIQUETAS_FILIACION[h.tipo_relacion] || null,
        })).join('');
        html += `</div>`;
    } else {
        html += `<div class="sin-hijos">Sin unión ni descendencia registrada</div>`;
    }

    lienzo.innerHTML = html;
    lienzo.classList.remove('entrando');
    void lienzo.offsetWidth;
    lienzo.classList.add('entrando');

    activarInteracciones(persona, nucleos, progenitores, hijosSueltos);
}

function activarInteracciones(persona, nucleos, progenitores, hijosSueltos) {
    // Click en el retrato (fuera del icono de ficha) -> navega/hace zoom a esa persona
    lienzo.querySelectorAll('.retrato-persona').forEach((el) => {
        el.addEventListener('click', (ev) => {
            if (ev.target.closest('.btn-ficha')) return; // el icono maneja su propio click
            cargarNucleo(Number(el.dataset.id));
        });
    });

    // Icono de ficha tecnica -> abre el panel de detalle, sin navegar
    lienzo.querySelectorAll('.btn-ficha').forEach((btn) => {
        btn.addEventListener('click', (ev) => {
            ev.stopPropagation();
            abrirDetalle(Number(btn.dataset.fichaId));
        });
    });

    lienzo.querySelectorAll('.progenitor-mini').forEach((el) => {
        el.addEventListener('click', () => cargarNucleo(Number(el.dataset.id)));
    });

    lienzo.querySelectorAll('.pestana-union').forEach((el) => {
        el.addEventListener('click', () => {
            unionActivaIndex = Number(el.dataset.index);
            pintarNucleo(persona, nucleos, progenitores, hijosSueltos);
        });
    });
}

migas.addEventListener('click', (ev) => {
    const miga = ev.target.closest('.miga');
    if (miga) {
        cargarNucleo(Number(miga.dataset.id));
    }
});

// ── Panel de detalle (ficha tecnica) ────────────────────────────────────────
async function abrirDetalle(personaId) {
    panelDetalle.classList.add('abierto');
    panelDetalle.innerHTML = '<div class="cargando">Cargando ficha…</div>';

    const [respDetalle, respHermanos] = await Promise.all([
        fetch(`${BASE}/api/personas/detalle?id=${personaId}`),
        fetch(`${BASE}/api/personas/hermanos?id=${personaId}`),
    ]);
    const data = await respDetalle.json();
    const dataHermanos = await respHermanos.json();

    if (data.codigo === 0) {
        panelDetalle.innerHTML = '<span class="cerrar">✕</span><p>No se pudo cargar la ficha.</p>';
        panelDetalle.querySelector('.cerrar').addEventListener('click', cerrarDetalle);
        return;
    }

    const { persona, fotos } = data.datos;
    const hermanos = dataHermanos.codigo === 1 ? dataHermanos.datos : [];
    const foto = urlFoto(persona.foto_perfil);

    const hermanosHTML = hermanos.length ? `
        <div class="hermanos-titulo">Hermanos/as</div>
        <div class="hermanos-lista">
            ${hermanos.map((h) => `
                <div class="hermano-item" data-id="${h.id}">
                    <span>${h.nombres} ${h.apellidos}</span>
                    <span class="badge-parentesco badge-${h.tipo}">${h.tipo === 'completo' ? 'Hermano/a' : 'Medio/a hermano/a'}</span>
                </div>
            `).join('')}
        </div>` : '';

    panelDetalle.innerHTML = `
        <span class="cerrar">✕</span>
        ${foto ? `<img src="${foto}" style="width:100%;border-radius:8px;margin-top:2.2rem;" alt="">` : ''}
        <h2>${persona.nombres} ${persona.apellidos}</h2>
        ${persona.apodo ? `<div class="dato">Conocido/a como "${persona.apodo}"</div>` : ''}
        <div class="dato">${edadOFechas(persona)}${persona.lugar_nacimiento ? ' · ' + persona.lugar_nacimiento : ''}</div>
        ${persona.biografia ? `<p class="bio">${persona.biografia}</p>` : ''}
        ${fotos.length ? `<div class="galeria">${fotos.map((f) => `<img src="${urlFoto(f.ruta)}" alt="">`).join('')}</div>` : ''}
        ${hermanosHTML}
    `;
    panelDetalle.querySelector('.cerrar').addEventListener('click', cerrarDetalle);
    panelDetalle.querySelectorAll('.hermano-item').forEach((el) => {
        el.addEventListener('click', () => {
            cerrarDetalle();
            cargarNucleo(Number(el.dataset.id));
        });
    });
}

function cerrarDetalle() {
    panelDetalle.classList.remove('abierto');
}

// ── Cambiar la raiz del arbol (buscador de persona) ──────────────────────
window.cambiarRaiz = async () => {
    const resp = await fetch(`${BASE}/api/personas/listar`);
    const data = await resp.json();
    const todas = data.codigo === 1 ? data.datos : [];

    const { isConfirmed } = await Swal.fire({
        title: 'Ver árbol desde...',
        html: `
            <div class="text-start small">
                <input type="text" id="raiz-buscar" class="form-control form-control-sm mb-1"
                       placeholder="Escribí un nombre para buscar…" autocomplete="off">
                <input type="hidden" id="raiz-persona">
                <div id="raiz-resultados" class="list-group" style="max-height:220px;overflow-y:auto;display:none;"></div>
            </div>`,
        didOpen: () => {
            const inputBuscar = document.getElementById('raiz-buscar');
            const inputHidden = document.getElementById('raiz-persona');
            const resultados = document.getElementById('raiz-resultados');
            const normalizar = (s) => s.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');

            inputBuscar.addEventListener('input', () => {
                inputHidden.value = '';
                const q = normalizar(inputBuscar.value.trim());
                if (!q) {
                    resultados.innerHTML = '';
                    resultados.style.display = 'none';
                    return;
                }
                const coincidencias = todas
                    .filter((p) => normalizar(`${p.nombres} ${p.apellidos}`).includes(q))
                    .slice(0, 8);
                resultados.innerHTML = coincidencias.length
                    ? coincidencias.map((p) =>
                        `<button type="button" class="list-group-item list-group-item-action small" data-id="${p.id}">${p.nombres} ${p.apellidos}</button>`
                    ).join('')
                    : '<div class="list-group-item small text-muted">Sin resultados</div>';
                resultados.style.display = '';
                resultados.querySelectorAll('[data-id]').forEach((btn) => {
                    btn.addEventListener('click', () => {
                        inputHidden.value = btn.dataset.id;
                        inputBuscar.value = btn.textContent;
                        resultados.innerHTML = '';
                        resultados.style.display = 'none';
                    });
                });
            });
        },
        showCancelButton: true,
        confirmButtonText: 'Ver desde acá',
        confirmButtonColor: '#e8b84b',
        width: '460px',
        preConfirm: () => {
            if (!document.getElementById('raiz-persona').value) {
                Swal.showValidationMessage('Selecciona a una persona de la lista');
                return false;
            }
            return true;
        },
    });

    if (!isConfirmed) return;

    const personaId = Number(document.getElementById('raiz-persona').value);
    const body = new FormData();
    body.append('persona_id', personaId);
    await fetch(`${BASE}/api/arbol/raiz`, { method: 'POST', body });

    pila = []; // arranca de cero, sin arrastrar las migas de la vista anterior
    cargarNucleo(personaId);
};

// ── Arranque ────────────────────────────────────────────────────────────────
async function iniciar() {
    const resp = await fetch(`${BASE}/api/arbol/raiz`);
    const data = await resp.json();
    const raizId = data.datos ? data.datos.persona_id : null;

    if (!raizId) {
        lienzo.innerHTML = `
            <div class="vacio-arbol">
                Todavía no hay personas registradas en el árbol.<br>
                <a href="${BASE}/personas">Agregar la primera persona</a>
            </div>`;
        return;
    }

    cargarNucleo(raizId);
}

iniciar();