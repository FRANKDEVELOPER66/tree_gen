<?php

namespace Controllers;

use Model\Personas;
use Model\Uniones;
use Model\Filiaciones;
use Model\FamiliaGrafo;
use MVC\Router;

/**
 * OrganigramaController
 * ----------------------
 * Vista extra de árbol "tipo organigrama grande": ascendientes arriba,
 * descendientes abajo, con hermanos/tíos/primos visibles alrededor, y un
 * modo para seleccionar dos personas y ver cómo están emparentadas.
 *
 * Columnas confirmadas contra el esquema real:
 * filiaciones: id, hijo_id, progenitor_id, union_id, tipo_relacion, notas, creado_en
 * uniones:     id, persona_a_id, persona_b_id, tipo, fecha_inicio, fecha_fin, estado, notas, creado_en
 */
class OrganigramaController
{
    /** Página: el lienzo del organigrama. Ruta: $router->get('/organigrama', ...) */
    public static function index(Router $router)
    {
        $router->render('organigrama/index', [
            'titulo' => 'Organigrama familiar',
            'idRaiz' => (int) ($_GET['id'] ?? 0),
        ]);
    }

    /**
     * API: subgrafo familiar alrededor de una persona (para dibujar el organigrama).
     * Ruta: $router->get('/api/organigrama/subgrafo', [OrganigramaController::class, 'subgrafoAPI']);
     * Uso:  /api/organigrama/subgrafo?id=123&arriba=5&abajo=5  (arriba/abajo opcionales)
     */
    public static function subgrafoAPI()
    {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            responderJSON(0, 'Debes indicar el id de la persona raíz.');
        }

        $arriba = isset($_GET['arriba']) && $_GET['arriba'] !== '' ? (int) $_GET['arriba'] : null;
        $abajo = isset($_GET['abajo']) && $_GET['abajo'] !== '' ? (int) $_GET['abajo'] : null;

        self::cargarGrafo();
        $sub = FamiliaGrafo::obtenerSubgrafo($id, $arriba, $abajo);

        if ($sub === null) {
            responderJSON(0, 'No se encontró la persona indicada.');
        }

        responderJSON(1, '', $sub);
    }

    /**
     * API: parentesco entre dos personas.
     * Ruta: $router->get('/api/organigrama/parentesco', [OrganigramaController::class, 'parentescoAPI']);
     * Uso:  /api/organigrama/parentesco?idA=123&idB=456
     */
    public static function parentescoAPI()
    {
        $idA = (int) ($_GET['idA'] ?? 0);
        $idB = (int) ($_GET['idB'] ?? 0);

        if ($idA <= 0 || $idB <= 0) {
            responderJSON(0, 'Debes indicar idA e idB.');
        }

        self::cargarGrafo();
        $resultado = FamiliaGrafo::buscarParentesco($idA, $idB);

        responderJSON(1, '', $resultado);
    }

    // Nota: para el buscador de personas (elegir persona A / persona B en el
    // modo "comparar parentesco") reutilizamos tu endpoint que ya existe:
    // GET /api/personas/buscar => ArbolController::buscarAPI (Personas::buscarPorTexto).
    // No se agrega un método duplicado aquí.

    /**
     * Carga personas + filiaciones + uniones en FamiliaGrafo, usando tus
     * modelos reales (Personas/Uniones/Filiaciones heredan fetchArray() de
     * ActiveRecord). Se llama una vez al inicio de cada acción de este
     * controlador.
     */
    private static function cargarGrafo(): void
    {
        // genero real: 'femenino' | 'masculino' -> lo normalizo a 'F' | 'M'
        // para que FamiliaGrafo (agnóstica de tu esquema) no dependa de tus
        // valores exactos.
        $personas = Personas::fetchArray(
            "SELECT id,
                    nombres,
                    apellidos,
                    CASE genero WHEN 'femenino' THEN 'F' WHEN 'masculino' THEN 'M' ELSE NULL END AS genero,
                    foto_perfil AS foto,
                    fecha_nacimiento,
                    fecha_fallecimiento
             FROM personas"
        );

        $filiaciones = Filiaciones::fetchArray(
            "SELECT hijo_id AS persona_id, progenitor_id AS padre_madre_id, tipo_relacion
             FROM filiaciones"
        );

        $uniones = Uniones::fetchArray(
            "SELECT persona_a_id AS persona1_id, persona_b_id AS persona2_id
             FROM uniones"
        );

        FamiliaGrafo::cargar($personas, $filiaciones, $uniones);
    }
}
