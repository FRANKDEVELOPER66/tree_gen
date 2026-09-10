/**
 * src/js/organigrama/index.js — Vista "árbol grande tipo organigrama".
 *
 * Sigue el mismo patrón que src/js/arbol/index.js: módulo ES, lee la BASE
 * de la URL desde un data-attribute del contenedor, y se auto-inicializa
 * al final del archivo.
 *
 * Requiere el paquete `d3` instalado (npm install d3).
 */
import * as d3 from 'd3';

const raiz = document.querySelector('#organigrama');
const BASE = raiz?.dataset.base ?? '';
const ID_RAIZ_INICIAL = Number(raiz?.dataset.idRaiz || 0);

const API = {
    subgrafo: (id, arriba, abajo) => {
        let url = `${BASE}/api/organigrama/subgrafo?id=${id}`;
        if (arriba !== undefined && arriba !== null) url += `&arriba=${arriba}`;
        if (abajo !== undefined && abajo !== null) url += `&abajo=${abajo}`;
        return url;
    },
    parentesco: (idA, idB) => `${BASE}/api/organigrama/parentesco?idA=${idA}&idB=${idB}`,
    // Reutiliza tu endpoint existente (ArbolController::buscarAPI / Personas::buscarPorTexto).
    buscar: (q) => `${BASE}/api/personas/buscar?q=${encodeURIComponent(q)}`,
};

const CONFIG = {
    anchoNodo: 150,
    altoNodo: 66,
    espacioHorizontal: 34,
    espacioVertical: 100,
};

function urlFoto(nombreArchivo) {
    return nombreArchivo ? `${BASE}/public/uploads/${nombreArchivo}` : null;
}

function iniciales(nombreCompleto) {
    const partes = (nombreCompleto || '').trim().split(/\s+/);
    const a = partes[0]?.[0] || '?';
    const b = partes.length > 1 ? partes[partes.length - 1][0] : '';
    return (a + b).toUpperCase();
}

/**
 * Conecta un <input> de búsqueda con un contenedor de resultados tipo
 * autocomplete (usa /api/personas/buscar). Está fuera de la clase Organigrama
 * porque también la usamos para el buscador de la barra ANTES de que la
 * clase exista (cuando entras a /organigrama sin ?id=).
 */
function conectarBuscador(selectorInput, selectorResultados, alSeleccionar) {
    const input = document.querySelector(selectorInput);
    const resultados = document.querySelector(selectorResultados);
    if (!input || !resultados) return;

    let temporizador = null;
    input.addEventListener('input', () => {
        clearTimeout(temporizador);
        const q = input.value.trim();
        if (q.length < 2) {
            resultados.innerHTML = '';
            return;
        }
        temporizador = setTimeout(async () => {
            const resp = await fetch(API.buscar(q));
            const json = await resp.json();
            const filas = json.datos || [];
            resultados.innerHTML = '';
            filas.forEach((p) => {
                const item = document.createElement('div');
                item.className = 'og-resultado-item';
                item.textContent = `${p.nombres} ${p.apellidos}`;
                item.addEventListener('click', () => {
                    input.value = `${p.nombres} ${p.apellidos}`;
                    resultados.innerHTML = '';
                    alSeleccionar(p);
                });
                resultados.appendChild(item);
            });
        }, 250);
    });
}

class Organigrama {
    /**
     * @param {string} selectorContenedor selector CSS del div donde se dibuja
     * @param {number} idRaiz id de la persona sobre la que se centra el árbol
     */
    constructor(selectorContenedor, idRaiz) {
        this.idRaiz = idRaiz;
        this.contenedor = d3.select(selectorContenedor);
        this.datos = null;
        this.modoComparar = false;
        this.personaA = null;
        this.personaB = null;
        this._initSvg();
        this._initControles();
    }

    _initSvg() {
        this.svg = this.contenedor.append('svg').attr('class', 'organigrama-svg');

        this.capaZoom = this.svg.append('g').attr('class', 'capa-zoom');
        this.capaAristas = this.capaZoom.append('g').attr('class', 'capa-aristas');
        this.capaAristasExtra = this.capaZoom.append('g').attr('class', 'capa-aristas-extra');
        this.capaRuta = this.capaZoom.append('g').attr('class', 'capa-ruta');
        this.capaNodos = this.capaZoom.append('g').attr('class', 'capa-nodos');

        this.zoom = d3
            .zoom()
            .scaleExtent([0.15, 2.5])
            .on('zoom', (evento) => this.capaZoom.attr('transform', evento.transform));

        this.svg.call(this.zoom);

        // El <svg> con width/height:100% dentro de este layout (flex anidado
        // varias veces) a veces NO resuelve el alto por porcentaje — bug común
        // de navegadores donde el <svg> se queda con su alto "por defecto"
        // (150px) aunque el contenedor mida lo que debería. En vez de pelear
        // con el CSS, fijamos el tamaño del SVG en píxeles EXACTOS tomados del
        // contenedor (que sí mide bien) y los mantenemos sincronizados.
        const contenedorNode = this.contenedor.node();
        const ajustarTamano = () => {
            const rect = contenedorNode.getBoundingClientRect();
            if (rect.width > 0 && rect.height > 0) {
                this.svg.attr('width', rect.width).attr('height', rect.height);
            }
        };
        ajustarTamano();
        if (window.ResizeObserver) {
            new ResizeObserver(ajustarTamano).observe(contenedorNode);
        } else {
            window.addEventListener('resize', ajustarTamano);
        }
    }

    _initControles() {
        // Buscador SIEMPRE visible en la barra de arriba para saltar a
        // cualquier persona sin tener que editar la URL a mano.
        this._conectarBuscador('#og-buscar-raiz', '#og-resultados-raiz', (persona) => {
            this._irAConURL(persona.id);
            const input = document.querySelector('#og-buscar-raiz');
            const resultados = document.querySelector('#og-resultados-raiz');
            if (input) input.value = '';
            if (resultados) resultados.innerHTML = '';
        });

        this._conectarBuscador('#og-buscar-a', '#og-resultados-a', (persona) => {
            this.personaA = persona;
            this._actualizarBotonComparar();
        });
        this._conectarBuscador('#og-buscar-b', '#og-resultados-b', (persona) => {
            this.personaB = persona;
            this._actualizarBotonComparar();
        });

        document.querySelector('#og-btn-comparar')?.addEventListener('click', () => {
            if (this.personaA && this.personaB) {
                this.compararParentesco(this.personaA.id, this.personaB.id);
            }
        });

        document.querySelector('#og-btn-limpiar')?.addEventListener('click', () => this.limpiarComparacion());
    }

    /** Cambia de raíz Y actualiza la URL (?id=), para poder refrescar/compartir el link. */
    async _irAConURL(idPersona) {
        const url = new URL(window.location.href);
        url.searchParams.set('id', idPersona);
        window.history.pushState({ idRaiz: idPersona }, '', url);
        this.limpiarComparacion();
        await this.irA(idPersona);
    }

    _actualizarBotonComparar() {
        const btn = document.querySelector('#og-btn-comparar');
        if (btn) btn.disabled = !(this.personaA && this.personaB);
    }

    /** Conecta un <input> de búsqueda con un contenedor de resultados tipo autocomplete. */
    _conectarBuscador(selectorInput, selectorResultados, alSeleccionar) {
        conectarBuscador(selectorInput, selectorResultados, alSeleccionar);
    }

    // ============================================================
    // Carga y layout
    // ============================================================

    /** @param {number|null} arriba límite de generaciones hacia arriba (null = todas) */
    /** @param {number|null} abajo límite de generaciones hacia abajo (null = todas) */
    async cargar(arriba = null, abajo = null) {
        const resp = await fetch(API.subgrafo(this.idRaiz, arriba, abajo));
        const json = await resp.json();
        if (json.codigo !== 1) {
            throw new Error(json.mensaje || 'No se pudo cargar el árbol.');
        }
        this.datos = json.datos;
        // Defensivo: si alguna persona quedó sin "generacion" calculable (rama
        // desconectada del resto, algún caso raro de parentesco), la tratamos
        // como generación 0 en vez de dejar pasar un NaN/undefined — un solo
        // valor inválido ahí contamina TODO el cálculo del encuadre (ver
        // _centrarEnRaiz) y el árbol entero deja de dibujarse sin dar error.
        this.datos.nodos.forEach((n) => {
            if (typeof n.generacion !== 'number' || !Number.isFinite(n.generacion)) {
                n.generacion = 0;
            }
        });
        this._prepararLayout();
        this._dibujar();
        this._centrarEnRaiz();
    }

    /**
     * Agrupa a las personas en "unidades familiares" (soltero o pareja),
     * calcula un orden por nivel (heurística de baricentro) y asigna
     * coordenadas x/y a cada persona. No es un layout perfecto (minimizar
     * cruces de líneas en un árbol genealógico real es NP-difícil), pero es
     * legible para un árbol familiar típico.
     */
    _prepararLayout() {
        const { nodos, filiaciones, uniones } = this.datos;
        this.nodoPorId = new Map(nodos.map((n) => [n.id, { ...n }]));

        const parejaDe = new Map();
        uniones.forEach((u) => {
            parejaDe.set(u.persona1, u.persona2);
            parejaDe.set(u.persona2, u.persona1);
        });

        const yaAsignado = new Set();
        this.unidades = [];
        this.unidadPorPersona = new Map();

        nodos
            .slice()
            .sort((a, b) => a.generacion - b.generacion)
            .forEach((n) => {
                if (yaAsignado.has(n.id)) return;
                const parejaId = parejaDe.get(n.id);
                const miembros = [n.id];
                if (
                    parejaId !== undefined &&
                    this.nodoPorId.has(parejaId) &&
                    !yaAsignado.has(parejaId) &&
                    this.nodoPorId.get(parejaId).generacion === n.generacion
                ) {
                    miembros.push(parejaId);
                    yaAsignado.add(parejaId);
                }
                yaAsignado.add(n.id);
                const unidad = { id: 'u' + this.unidades.length, miembros, generacion: n.generacion, orden: this.unidades.length };
                this.unidades.push(unidad);
                miembros.forEach((m) => this.unidadPorPersona.set(m, unidad));
            });

        // Padre "principal" de cada persona -> define de qué unidad cuelga en
        // el layout. Si hay más de un padre registrado (adoptivo, tutor, etc.),
        // los adicionales se dibujan como línea punteada extra sin afectar el
        // orden del árbol.
        this.padrePrincipalDe = new Map();
        this.aristasExtra = [];
        const filiacionesPorHijo = new Map();
        filiaciones.forEach((f) => {
            if (!filiacionesPorHijo.has(f.hijo)) filiacionesPorHijo.set(f.hijo, []);
            filiacionesPorHijo.get(f.hijo).push(f);
        });
        filiacionesPorHijo.forEach((lista, hijoId) => {
            lista.sort((a, b) => (a.tipo === 'biologico' ? -1 : 1));
            this.padrePrincipalDe.set(hijoId, lista[0].padre);
            lista.slice(1).forEach((f) => this.aristasExtra.push(f));
        });

        this.unidades.forEach((u) => {
            const padreId = this.padrePrincipalDe.get(u.miembros[0]);
            u.unidadPadre = padreId !== undefined ? this.unidadPorPersona.get(padreId) : null;
        });

        this._ordenarNiveles();
        this._asignarCoordenadas();
    }

    _ordenarNiveles() {
        const nivelesAsc = [...new Set(this.unidades.map((u) => u.generacion))].sort((a, b) => a - b);

        for (let pasada = 0; pasada < 4; pasada++) {
            nivelesAsc.forEach((gen) => {
                const unidadesNivel = this.unidades.filter((u) => u.generacion === gen);
                unidadesNivel.forEach((u) => {
                    if (u.unidadPadre) {
                        u.claveOrden = u.unidadPadre.orden;
                    }
                });
                unidadesNivel
                    .sort((a, b) => (a.claveOrden ?? a.orden) - (b.claveOrden ?? b.orden))
                    .forEach((u, i) => {
                        u.orden = i;
                    });
            });
        }
    }

    _asignarCoordenadas() {
        const anchoUnidad = (u) => (u.miembros.length === 2 ? CONFIG.anchoNodo * 2 + 20 : CONFIG.anchoNodo);
        const generaciones = [...new Set(this.unidades.map((u) => u.generacion))].sort((a, b) => a - b);

        generaciones.forEach((gen) => {
            const unidadesNivel = this.unidades.filter((u) => u.generacion === gen).sort((a, b) => a.orden - b.orden);
            let xAcumulado = 0;
            unidadesNivel.forEach((u) => {
                u.x = xAcumulado + anchoUnidad(u) / 2;
                xAcumulado += anchoUnidad(u) + CONFIG.espacioHorizontal;
            });
            const centro = (xAcumulado - CONFIG.espacioHorizontal) / 2;
            unidadesNivel.forEach((u) => {
                u.x -= centro;
            });
        });

        this.unidades.forEach((u) => {
            u.y = u.generacion * (CONFIG.altoNodo + CONFIG.espacioVertical);
            if (u.miembros.length === 2) {
                const mitad = CONFIG.anchoNodo / 2 + 10;
                this.nodoPorId.get(u.miembros[0]).x = u.x - mitad;
                this.nodoPorId.get(u.miembros[1]).x = u.x + mitad;
            } else {
                this.nodoPorId.get(u.miembros[0]).x = u.x;
            }
            u.miembros.forEach((m) => {
                this.nodoPorId.get(m).y = u.y;
            });
        });
    }

    // ============================================================
    // Dibujo
    // ============================================================

    _dibujar() {
        this._dibujarAristasFiliacion();
        this._dibujarAristasExtra();
        this._dibujarUniones();
        this._dibujarNodos();
    }

    /** Conector tipo organigrama: baja de la unidad padre y se reparte en horizontal a los hijos. */
    _rutaOrganigrama(x1, y1, x2, y2) {
        const yMedio = y1 + (y2 - y1) / 2;
        return `M${x1},${y1} V${yMedio} H${x2} V${y2}`;
    }

    _dibujarAristasFiliacion() {
        const lineas = [];
        this.unidades.forEach((u) => {
            if (!u.unidadPadre) return;
            lineas.push({
                id: u.id,
                x1: u.unidadPadre.x,
                y1: u.unidadPadre.y + CONFIG.altoNodo / 2,
                x2: u.x,
                y2: u.y - CONFIG.altoNodo / 2,
            });
        });

        this.capaAristas
            .selectAll('path.og-arista')
            .data(lineas, (d) => d.id)
            .join('path')
            .attr('class', 'og-arista')
            .attr('d', (d) => this._rutaOrganigrama(d.x1, d.y1, d.x2, d.y2));
    }

    /** Padres secundarios (adoptivo, tutor, etc.): línea punteada directa persona-persona. */
    _dibujarAristasExtra() {
        const lineas = this.aristasExtra
            .map((f) => {
                const hijo = this.nodoPorId.get(f.hijo);
                const padre = this.nodoPorId.get(f.padre);
                if (!hijo || !padre) return null;
                return { id: `${f.padre}-${f.hijo}`, x1: padre.x, y1: padre.y, x2: hijo.x, y2: hijo.y, tipo: f.tipo };
            })
            .filter(Boolean);

        this.capaAristasExtra
            .selectAll('path.og-arista-extra')
            .data(lineas, (d) => d.id)
            .join('path')
            .attr('class', 'og-arista-extra')
            .attr('d', (d) => `M${d.x1},${d.y1} L${d.x2},${d.y2}`)
            .append('title')
            .text((d) => d.tipo);
    }

    _dibujarUniones() {
        const lineas = this.unidades
            .filter((u) => u.miembros.length === 2)
            .map((u) => {
                const [a, b] = u.miembros.map((m) => this.nodoPorId.get(m));
                return { id: u.id, x1: a.x, y1: a.y, x2: b.x, y2: b.y };
            });

        this.capaAristas
            .selectAll('line.og-union')
            .data(lineas, (d) => d.id)
            .join('line')
            .attr('class', 'og-union')
            .attr('x1', (d) => d.x1)
            .attr('y1', (d) => d.y1)
            .attr('x2', (d) => d.x2)
            .attr('y2', (d) => d.y2);
    }

    _dibujarNodos() {
        const nodos = [...this.nodoPorId.values()];
        const radioFoto = CONFIG.altoNodo / 2 - 6;

        const grupos = this.capaNodos
            .selectAll('g.og-nodo')
            .data(nodos, (d) => d.id)
            .join((enter) => {
                const g = enter
                    .append('g')
                    .attr('class', 'og-nodo')
                    .attr('data-id', (d) => d.id)
                    .on('click', (evento, d) => this._alClicNodo(d));

                g.append('rect').attr('class', 'og-nodo-caja').attr('width', CONFIG.anchoNodo).attr('height', CONFIG.altoNodo).attr('rx', 10);

                g.append('clipPath')
                    .attr('id', (d) => `og-clip-${d.id}`)
                    .append('circle');

                g.append('circle').attr('class', 'og-nodo-foto-fondo');
                g.append('image').attr('class', 'og-nodo-foto').attr('preserveAspectRatio', 'xMidYMid slice');
                g.append('text').attr('class', 'og-nodo-iniciales').attr('text-anchor', 'middle');
                g.append('text').attr('class', 'og-nodo-nombre').attr('text-anchor', 'middle');
                g.append('text').attr('class', 'og-nodo-fallecido').attr('text-anchor', 'middle').text('✝');
                g.append('title');

                return g;
            });

        grupos.attr('transform', (d) => `translate(${d.x - CONFIG.anchoNodo / 2}, ${d.y - CONFIG.altoNodo / 2})`);
        grupos.classed('og-nodo-raiz', (d) => d.esRaiz);
        grupos.classed('og-nodo-seleccionado', (d) => this._esSeleccionado(d.id));

        grupos
            .select('clipPath circle')
            .attr('cx', CONFIG.anchoNodo / 2)
            .attr('cy', 16)
            .attr('r', radioFoto);

        grupos
            .select('.og-nodo-foto-fondo')
            .attr('cx', CONFIG.anchoNodo / 2)
            .attr('cy', 16)
            .attr('r', radioFoto);

        grupos
            .select('.og-nodo-foto')
            .attr('href', (d) => urlFoto(d.foto))
            .attr('clip-path', (d) => `url(#og-clip-${d.id})`)
            .attr('x', CONFIG.anchoNodo / 2 - radioFoto)
            .attr('y', 16 - radioFoto)
            .attr('width', radioFoto * 2)
            .attr('height', radioFoto * 2)
            .style('display', (d) => (d.foto ? null : 'none'));

        grupos
            .select('.og-nodo-iniciales')
            .attr('x', CONFIG.anchoNodo / 2)
            .attr('y', 21)
            .style('display', (d) => (d.foto ? 'none' : null))
            .text((d) => iniciales(d.nombre));

        grupos
            .select('.og-nodo-nombre')
            .attr('x', CONFIG.anchoNodo / 2)
            .attr('y', CONFIG.altoNodo - 12)
            .text((d) => this._nombreCorto(d.nombre));

        grupos
            .select('.og-nodo-fallecido')
            .attr('x', CONFIG.anchoNodo - 14)
            .attr('y', 14)
            .style('display', (d) => (d.vivo ? 'none' : null));

        grupos.select('title').text((d) => d.nombre);
    }

    _nombreCorto(nombre) {
        return nombre && nombre.length > 20 ? nombre.slice(0, 18) + '…' : nombre;
    }

    _esSeleccionado(id) {
        return (this.personaA && this.personaA.id === id) || (this.personaB && this.personaB.id === id);
    }

    _alClicNodo(persona) {
        if (!this.modoComparar) {
            // Fuera del modo comparar: un clic en cualquier nodo navega el árbol
            // hacia esa persona (la vuelve la nueva raíz), sin tener que tocar la URL.
            if (persona.id !== this.idRaiz) this._irAConURL(persona.id);
            return;
        }
        if (!this.personaA) {
            this.personaA = persona;
        } else if (!this.personaB && persona.id !== this.personaA.id) {
            this.personaB = persona;
        } else {
            this.personaA = persona;
            this.personaB = null;
        }
        this._dibujarNodos();
        this._actualizarBotonComparar();
        if (this.personaA && this.personaB) {
            this.compararParentesco(this.personaA.id, this.personaB.id);
        }
    }

    // ============================================================
    // Comparar parentesco
    // ============================================================

    async compararParentesco(idA, idB) {
        const resp = await fetch(API.parentesco(idA, idB));
        const json = await resp.json();
        if (json.codigo !== 1) {
            this._mostrarPanelParentesco(null, json.mensaje);
            return;
        }
        const resultado = json.datos;
        this._resaltarRuta(resultado);
        this._mostrarPanelParentesco(resultado);
    }

    _mostrarPanelParentesco(resultado, error) {
        const panel = document.querySelector('#og-panel-parentesco');
        if (!panel) return;

        if (error) {
            panel.innerHTML = `<p class="og-panel-error">${error}</p>`;
            panel.classList.add('og-panel-visible');
            return;
        }
        if (!resultado) {
            panel.innerHTML = '';
            panel.classList.remove('og-panel-visible');
            return;
        }

        const nombreA = this.nodoPorId.get(resultado.idA)?.nombre || `#${resultado.idA}`;
        const nombreB = this.nodoPorId.get(resultado.idB)?.nombre || `#${resultado.idB}`;

        let detalle = '';
        if (resultado.descripcionAB) {
            detalle = `<p><strong>${nombreA}</strong> es ${resultado.descripcionAB} de <strong>${nombreB}</strong>.</p>
                 <p><strong>${nombreB}</strong> es ${resultado.descripcionBA} de <strong>${nombreA}</strong>.</p>`;
        }

        panel.innerHTML = `
      <h4>${nombreA} &harr; ${nombreB}</h4>
      <p class="og-panel-etiqueta">${resultado.etiquetaGeneral}</p>
      ${detalle}
    `;
        panel.classList.add('og-panel-visible');
    }

    /** Marca con una clase CSS los nodos y aristas que forman la ruta de parentesco. */
    _resaltarRuta(resultado) {
        this.capaNodos.selectAll('.og-nodo').classed('og-nodo-en-ruta', false);
        this.capaAristas.selectAll('.og-arista, .og-union').classed('og-arista-en-ruta', false);
        this.capaRuta.selectAll('*').remove();

        const idsEnRuta = new Set();
        (resultado.ruta || []).forEach((tramo) => {
            (tramo.caminoA || []).forEach((id) => idsEnRuta.add(id));
            (tramo.caminoB || []).forEach((id) => idsEnRuta.add(id));
        });

        this.capaNodos.selectAll('.og-nodo').classed('og-nodo-en-ruta', (d) => idsEnRuta.has(d.id));

        (resultado.ruta || []).forEach((tramo) => {
            this._dibujarLineaResaltada(tramo.caminoA);
            this._dibujarLineaResaltada(tramo.caminoB);
        });
    }

    _dibujarLineaResaltada(camino) {
        if (!camino || camino.length < 2) return;
        const puntos = camino.map((id) => this.nodoPorId.get(id)).filter(Boolean);
        if (puntos.length < 2) return;
        const generador = d3
            .line()
            .x((d) => d.x)
            .y((d) => d.y)
            .curve(d3.curveMonotoneY);
        this.capaRuta.append('path').attr('class', 'og-linea-resaltada').attr('d', generador(puntos));
    }

    limpiarComparacion() {
        this.personaA = null;
        this.personaB = null;
        this.capaNodos.selectAll('.og-nodo').classed('og-nodo-en-ruta og-nodo-seleccionado', false);
        this.capaAristas.selectAll('.og-arista, .og-union').classed('og-arista-en-ruta', false);
        this.capaRuta.selectAll('*').remove();
        this._mostrarPanelParentesco(null);
        this._actualizarBotonComparar();
        const a = document.querySelector('#og-buscar-a');
        const b = document.querySelector('#og-buscar-b');
        if (a) a.value = '';
        if (b) b.value = '';
    }

    activarModoComparar(activo) {
        this.modoComparar = activo;
        this.contenedor.classed('og-modo-comparar', activo);
    }

    // ============================================================
    // Navegación
    // ============================================================

    _centrarEnRaiz() {
        const contenedorNode = this.contenedor.node();

        // "Zoom to fit": en vez de centrar solo en la raíz a una escala fija
        // (que puede dejar el resto del árbol muy lejos de cámara si el árbol
        // es grande/asimétrico), calculamos la caja que contiene a TODOS los
        // nodos dibujados y ajustamos escala + traslado para que quepan.
        const centrar = () => {
            // Solo nodos con coordenadas numéricas válidas: un solo NaN/undefined
            // (por ejemplo una persona cuya "generacion" no se pudo calcular)
            // contamina Math.min/Math.max y deja TODO el árbol invisible (el
            // transform del SVG queda como "translate(NaN,NaN)...", sin dar
            // ningún error en consola).
            const nodos = [...this.nodoPorId.values()].filter((n) => Number.isFinite(n.x) && Number.isFinite(n.y));
            if (!nodos.length) return;

            const rect = contenedorNode.getBoundingClientRect();
            const width = rect.width || window.innerWidth;
            const height = rect.height || window.innerHeight;

            const margen = 60;
            const minX = Math.min(...nodos.map((n) => n.x)) - CONFIG.anchoNodo / 2 - margen;
            const maxX = Math.max(...nodos.map((n) => n.x)) + CONFIG.anchoNodo / 2 + margen;
            const minY = Math.min(...nodos.map((n) => n.y)) - CONFIG.altoNodo / 2 - margen;
            const maxY = Math.max(...nodos.map((n) => n.y)) + CONFIG.altoNodo / 2 + margen;

            const anchoArbol = Math.max(maxX - minX, 1);
            const altoArbol = Math.max(maxY - minY, 1);
            const centroX = (minX + maxX) / 2;
            const centroY = (minY + maxY) / 2;

            if (!Number.isFinite(centroX) || !Number.isFinite(centroY)) return;

            // No ampliar más de 1x aunque el árbol sea chico (se vería gigante);
            // sí permitir reducir todo lo necesario (respetando el scaleExtent del zoom).
            const escala = Math.min(width / anchoArbol, height / altoArbol, 1);
            const escalaFinal = Math.max(escala, this.zoom.scaleExtent()[0]);

            const transformInicial = d3.zoomIdentity
                .translate(width / 2, height / 2)
                .scale(escalaFinal)
                .translate(-centroX, -centroY);
            this.svg.call(this.zoom.transform, transformInicial);
        };

        // El contenedor está dentro de un layout flex y su alto/ancho real
        // puede seguir cambiando varias veces antes de "asentarse" (fuentes
        // cargando, reflow tardío del layout de la página). Centrar apenas se
        // ve el PRIMER tamaño (>0) fue el bug: ese primer aviso a veces no es
        // el tamaño final, así que centrábamos con un alto más chico del real
        // y el árbol quedaba arriba, con espacio vacío abajo.
        //
        // Ahora esperamos a que el tamaño deje de cambiar (debounce: cada
        // aviso reinicia el temporizador) antes de centrar — así usamos el
        // tamaño YA estable, sin importar cuántas veces cambie antes.
        if (window.ResizeObserver) {
            let temporizador = null;
            const observador = new ResizeObserver((entradas) => {
                const { height } = entradas[0].contentRect;
                if (height <= 0) return;
                clearTimeout(temporizador);
                temporizador = setTimeout(centrar, 150);
            });
            observador.observe(contenedorNode);

            // En cuanto el usuario interactúa a mano (pan/zoom), dejamos de
            // "robarle" la vista recentrando solos si el contenedor vuelve a
            // cambiar de tamaño más adelante (p. ej. al redimensionar la ventana).
            const detener = () => {
                clearTimeout(temporizador);
                observador.disconnect();
                this.svg.on('pointerdown.autocentrado', null);
            };
            this.svg.on('pointerdown.autocentrado', detener);
        } else {
            requestAnimationFrame(centrar);
        }
    }

    /** Cambia la persona raíz y vuelve a cargar. */
    async irA(idPersona, arriba = null, abajo = null) {
        this.idRaiz = idPersona;
        await this.cargar(arriba, abajo);
    }
}

/**
 * Pantalla inicial cuando se entra a /organigrama sin ?id=: dos opciones
 * claras (ver el árbol de UNA persona, o comparar parentesco entre DOS),
 * cada una con su propio buscador — nunca los dos buscadores a la vez, para
 * no amontonar campos.
 */
function mostrarSelectorDeRaiz() {
    // A esta altura no hay árbol cargado todavía, así que la barra de arriba
    // (con SU propio "Ir a persona") no aplica: la ocultamos para no repetir
    // el mismo buscador dos veces en la misma pantalla.
    const barra = document.querySelector('.og-barra');
    if (barra) barra.style.display = 'none';

    const contenedor = document.querySelector('#organigrama-contenedor');
    contenedor.innerHTML = `
    <div style="padding:60px 20px; max-width:560px; margin:0 auto; text-align:center;">
      <p style="font-family:'Inter',sans-serif; color:#4a3f28; margin-bottom:20px; font-size:1rem;">
        ¿Qué quieres hacer?
      </p>
      <div style="display:flex; gap:14px; justify-content:center; flex-wrap:wrap;">
        <button id="og-opcion-arbol" type="button" class="og-btn">
          Ver el árbol genealógico de una persona
        </button>
        <button id="og-opcion-comparar" type="button" class="og-btn">
          Comparar parentesco (consanguinidad) entre dos personas
        </button>
      </div>

      <div id="og-panel-opcion-arbol" style="display:none; margin-top:24px;">
        <div class="og-buscador" style="position:relative; max-width:360px; margin:0 auto;">
          <input id="og-buscar-raiz-inicial" type="text" placeholder="Buscar persona..." style="width:100%; box-sizing:border-box;">
          <div id="og-resultados-raiz-inicial" class="og-resultados"></div>
        </div>
      </div>

      <div id="og-panel-opcion-comparar" style="display:none; margin-top:24px;">
        <div style="display:flex; gap:12px; justify-content:center; flex-wrap:wrap;">
          <div class="og-buscador" style="position:relative;">
            <input id="og-buscar-inicial-a" type="text" placeholder="Buscar persona A...">
            <div id="og-resultados-inicial-a" class="og-resultados"></div>
          </div>
          <div class="og-buscador" style="position:relative;">
            <input id="og-buscar-inicial-b" type="text" placeholder="Buscar persona B...">
            <div id="og-resultados-inicial-b" class="og-resultados"></div>
          </div>
        </div>
        <button id="og-btn-ir-comparar" type="button" class="og-btn" style="margin-top:14px;" disabled>Ver parentesco</button>
      </div>
    </div>
  `;

    const panelArbol = document.querySelector('#og-panel-opcion-arbol');
    const panelComparar = document.querySelector('#og-panel-opcion-comparar');

    document.querySelector('#og-opcion-arbol').addEventListener('click', () => {
        panelArbol.style.display = 'block';
        panelComparar.style.display = 'none';
    });
    document.querySelector('#og-opcion-comparar').addEventListener('click', () => {
        panelComparar.style.display = 'block';
        panelArbol.style.display = 'none';
    });

    conectarBuscador('#og-buscar-raiz-inicial', '#og-resultados-raiz-inicial', (p) => {
        window.location.href = `${BASE}/organigrama?id=${p.id}`;
    });

    let personaA = null;
    let personaB = null;
    const btnComparar = document.querySelector('#og-btn-ir-comparar');
    const actualizarBtnComparar = () => {
        btnComparar.disabled = !(personaA && personaB);
    };
    conectarBuscador('#og-buscar-inicial-a', '#og-resultados-inicial-a', (p) => {
        personaA = p;
        actualizarBtnComparar();
    });
    conectarBuscador('#og-buscar-inicial-b', '#og-resultados-inicial-b', (p) => {
        personaB = p;
        actualizarBtnComparar();
    });
    btnComparar.addEventListener('click', () => {
        if (!personaA || !personaB) return;
        // Cargamos el árbol de A como raíz, y le pedimos a la vista que en
        // cuanto cargue abra el modo comparar con B ya seleccionada.
        window.location.href = `${BASE}/organigrama?id=${personaA.id}&compararCon=${personaB.id}`;
    });
}

// ── Arranque (igual patrón que arbol/index.js) ──────────────────────────────
async function iniciar() {
    if (!ID_RAIZ_INICIAL) {
        mostrarSelectorDeRaiz();
        return;
    }

    const organigrama = new Organigrama('#organigrama-contenedor', ID_RAIZ_INICIAL);

    // Límite por defecto: 3 generaciones arriba y 3 abajo del centro. En
    // familias grandes, "todo el árbol sin límite" puede juntar 5-6+
    // generaciones y decenas de personas — la caja termina siendo tan grande
    // que, sin importar cuánto se ajuste el zoom, o se ve microscópico o se
    // corta algo. Con un límite razonable el árbol entra completo y legible;
    // "Ver árbol completo" (abajo) lo quita si de verdad lo quieres así.
    let sinLimite = false;
    await organigrama.cargar(3, 3);

    const btnModo = document.querySelector('#og-btn-modo-comparar');
    const modoNormal = document.querySelector('#og-modo-normal');
    const controles = document.querySelector('#og-controles-comparar');

    /** Alterna entre "Ir a persona" y "Comparar A/B" — nunca los dos a la vez. */
    const alternarModoComparar = (activo) => {
        controles.style.display = activo ? 'flex' : 'none';
        modoNormal.style.display = activo ? 'none' : 'flex';
        organigrama.activarModoComparar(activo);
        btnModo?.classList.toggle('og-btn-activo', activo);
        if (!activo) organigrama.limpiarComparacion();
    };

    btnModo?.addEventListener('click', () => {
        const activo = controles.style.display === 'none';
        alternarModoComparar(activo);
    });

    document.querySelector('#og-btn-limpiar')?.addEventListener('click', () => alternarModoComparar(false));

    const btnCompleto = document.querySelector('#og-btn-completo');
    btnCompleto?.addEventListener('click', async () => {
        sinLimite = !sinLimite;
        btnCompleto.textContent = sinLimite ? 'Limitar generaciones visibles' : 'Ver árbol completo';
        btnCompleto.classList.toggle('og-btn-activo', sinLimite);
        if (sinLimite) {
            await organigrama.cargar(null, null);
        } else {
            await organigrama.cargar(3, 3);
        }
    });

    // Si llegamos desde la pantalla inicial con "comparar A vs B" (A = esta
    // raíz), abrimos el modo comparar directo con B ya seleccionada, en vez
    // de obligar al usuario a volver a buscar a ambos.
    const compararConId = Number(new URLSearchParams(window.location.search).get('compararCon') || 0);
    if (compararConId > 0 && compararConId !== ID_RAIZ_INICIAL) {
        alternarModoComparar(true);
        organigrama.personaA = { id: ID_RAIZ_INICIAL };
        organigrama.personaB = { id: compararConId };
        organigrama._actualizarBotonComparar();
        await organigrama.compararParentesco(ID_RAIZ_INICIAL, compararConId);
    }
}

iniciar();