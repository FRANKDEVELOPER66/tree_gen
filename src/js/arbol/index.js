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

function retratoHTML(persona, { central = false, mini = false, etiqueta = null } = {}) {
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
            <div class="${claseMarco} ${fallecido}">${contenidoImg}</div>
            <div class="retrato-nombre">${persona.nombres}<br>${persona.apellidos}</div>
            <div class="retrato-fechas">${edadOFechas(persona)}</div>
            ${etiqueta ? `<div class="etiqueta-filiacion">${etiqueta}</div>` : ''}
        </div>`;
}

const ETIQUETAS_FILIACION = {
    biologico: null,
    adoptivo: 'Adoptivo/a',
    padrastro: 'Hijastro/a',
    madrastra: 'Hijastro/a',
    tutor: 'Bajo tutela',
};

async function cargarNucleo(personaId, { agregarAPila = true } = {}) {
    lienzo.innerHTML = '<div class="cargando">Recuperando el registro familiar…</div>';

    const resp = await fetch(`${BASE}/api/arbol/nucleo?id=${personaId}`);
    const data = await resp.json();

    if (data.codigo === 0 || !data.datos) {
        lienzo.innerHTML = `<div class="vacio-arbol">No se encontró esa persona en el registro.</div>`;
        return;
    }

    const { persona, nucleos, progenitores } = data.datos;

    if (agregarAPila) {
        // Si ya estaba en la pila (navegacion hacia atras via miga), recorta desde ahi
        const posExistente = pila.findIndex((p) => p.id === persona.id);
        if (posExistente >= 0) {
            pila = pila.slice(0, posExistente + 1);
        } else {
            pila.push({ id: persona.id, nombre: `${persona.nombres} ${persona.apellidos}` });
        }
    }

    unionActivaIndex = 0;
    pintarMigas();
    pintarNucleo(persona, nucleos, progenitores);
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

function pintarNucleo(persona, nucleos, progenitores) {
    const nucleoActivo = nucleos[unionActivaIndex] || null;
    const hijos = nucleoActivo ? nucleoActivo.hijos : [];

    let html = '';

    // Progenitores (para subir en el arbol)
    if (progenitores && progenitores.length) {
        html += `<div class="fila-progenitores">`;
        html += progenitores.map((p) => retratoHTML(p, { mini: true })).join('');
        html += `</div>`;
    }

    // Pareja central
    html += `<div class="pareja-central">`;
    html += retratoHTML(persona, { central: true });
    if (nucleoActivo && nucleoActivo.pareja) {
        html += `
            <div class="simbolo-union">
                ∞
                <span class="rango">${nucleoActivo.tipo === 'matrimonio' ? 'Matrimonio' : 'Unión'}${nucleoActivo.estado !== 'activa' ? ' · ' + nucleoActivo.estado : ''}</span>
            </div>`;
        html += retratoHTML(nucleoActivo.pareja, { central: true });
    }
    html += `</div>`;

    // Pestañas si hay mas de una union
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

    // Hijos
    if (nucleos.length) {
        html += `<div class="linea-descendencia"></div>`;
        if (hijos.length) {
            html += `<div class="fila-hijos">`;
            html += hijos.map((h) => retratoHTML(h)).join('');
            html += `</div>`;
        } else {
            html += `<div class="sin-hijos">Sin descendencia registrada</div>`;
        }
    } else {
        html += `<div class="sin-hijos">Sin unión ni descendencia registrada</div>`;
    }

    lienzo.innerHTML = html;
    lienzo.classList.remove('entrando');
    void lienzo.offsetWidth; // fuerza reflow para reiniciar la animacion
    lienzo.classList.add('entrando');

    activarInteracciones(persona, nucleos, progenitores);
}

function activarInteracciones(persona, nucleos, progenitores) {
    // Click en un hijo o en la pareja central -> recentra el arbol en esa persona
    lienzo.querySelectorAll('.retrato-persona').forEach((el) => {
        el.addEventListener('click', (ev) => {
            const id = Number(el.dataset.id);
            // Doble accion: click simple recentra; con Alt/click derecho se podria abrir detalle
            if (ev.shiftKey) {
                abrirDetalle(id);
            } else {
                cargarNucleo(id);
            }
        });
    });

    // Progenitores: subir en el arbol
    lienzo.querySelectorAll('.progenitor-mini').forEach((el) => {
        el.addEventListener('click', () => cargarNucleo(Number(el.dataset.id)));
    });

    // Pestañas de uniones multiples
    lienzo.querySelectorAll('.pestana-union').forEach((el) => {
        el.addEventListener('click', () => {
            unionActivaIndex = Number(el.dataset.index);
            pintarNucleo(persona, nucleos, progenitores);
        });
    });
}

// ── Migas de pan: click para volver a un punto anterior de la rama ────────
migas.addEventListener('click', (ev) => {
    const miga = ev.target.closest('.miga');
    if (miga) {
        cargarNucleo(Number(miga.dataset.id));
    }
});

// ── Panel de detalle (Shift+click sobre un retrato) ────────────────────────
async function abrirDetalle(personaId) {
    panelDetalle.classList.add('abierto');
    panelDetalle.innerHTML = '<div class="cargando">Cargando ficha…</div>';

    const resp = await fetch(`${BASE}/api/personas/detalle?id=${personaId}`);
    const data = await resp.json();
    if (data.codigo === 0) {
        panelDetalle.innerHTML = '<span class="cerrar">✕</span><p>No se pudo cargar la ficha.</p>';
        panelDetalle.querySelector('.cerrar').addEventListener('click', cerrarDetalle);
        return;
    }

    const { persona, fotos } = data.datos;
    const foto = urlFoto(persona.foto_perfil);

    panelDetalle.innerHTML = `
        <span class="cerrar">✕</span>
        ${foto ? `<img src="${foto}" style="width:100%;border-radius:8px;margin-top:2.2rem;" alt="">` : ''}
        <h2>${persona.nombres} ${persona.apellidos}</h2>
        ${persona.apodo ? `<div class="dato">Conocido/a como "${persona.apodo}"</div>` : ''}
        <div class="dato">${edadOFechas(persona)}${persona.lugar_nacimiento ? ' · ' + persona.lugar_nacimiento : ''}</div>
        ${persona.biografia ? `<p class="bio">${persona.biografia}</p>` : ''}
        ${fotos.length ? `<div class="galeria">${fotos.map((f) => `<img src="${urlFoto(f.ruta)}" alt="">`).join('')}</div>` : ''}
    `;
    panelDetalle.querySelector('.cerrar').addEventListener('click', cerrarDetalle);
}

function cerrarDetalle() {
    panelDetalle.classList.remove('abierto');
}

// ── Arranque ────────────────────────────────────────────────────────────
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