<?php

namespace Controllers;

use Model\Personas;
use Model\Uniones;
use Model\Configuracion;
use MVC\Router;

class ArbolController
{
    /** Pagina principal: el lienzo interactivo del arbol */
    public static function index(Router $router)
    {
        $router->render('arbol/index', [
            'titulo' => 'Árbol genealógico',
        ]);
    }

    /**
     * API: "nucleo familiar" de una persona -> sus datos, la(s) union(es)
     * en las que participa (con pareja e hijos de cada una) y sus propios
     * progenitores. Es el bloque que arbol.js pinta cada vez que se hace
     * click/zoom en un nodo.
     */
    public static function nucleoAPI()
    {
        $personaId = (int) ($_GET['id'] ?? 0);
        $persona = Personas::find($personaId);

        if (!$persona) {
            responderJSON(0, 'Persona no encontrada');
        }

        $uniones = Personas::uniones($personaId);
        $nucleos = array_map(function ($u) use ($personaId) {
            $esA = (int) $u['persona_a_id'] === $personaId;
            $parejaId = $esA ? $u['b_id'] : $u['a_id'];
            $pareja = null;
            if ($parejaId) {
                $pareja = [
                    'id' => (int) $parejaId,
                    'nombres' => $esA ? $u['b_nombres'] : $u['a_nombres'],
                    'apellidos' => $esA ? $u['b_apellidos'] : $u['a_apellidos'],
                    'foto_perfil' => $esA ? $u['b_foto'] : $u['a_foto'],
                ];
            }

            return [
                'union_id' => (int) $u['id'],
                'tipo' => $u['tipo'],
                'estado' => $u['estado'],
                'fecha_inicio' => $u['fecha_inicio'],
                'fecha_fin' => $u['fecha_fin'],
                'pareja' => $pareja,
                'hijos' => Uniones::hijos((int) $u['id']),
            ];
        }, $uniones);

        responderJSON(1, '', [
            'persona' => $persona->atributos() + ['id' => $persona->id],
            'nucleos' => $nucleos,
            'progenitores' => Personas::progenitores($personaId),
        ]);
    }

    /** API: persona configurada como raiz del arbol (o la primera sin progenitores, si no hay ninguna) */
    public static function raizAPI()
    {
        $raizId = Configuracion::obtener('raiz_persona_id');

        // Si la raiz configurada apunta a alguien que ya no existe (se
        // elimino despues de fijarla), cae al fallback en vez de quedar
        // apuntando a un id fantasma.
        if ($raizId && !Personas::find((int) $raizId)) {
            $raizId = null;
        }

        if (!$raizId) {
            $fila = Personas::fetchArray(
                "SELECT p.id FROM personas p
                 LEFT JOIN filiaciones f ON f.hijo_id = p.id
                 WHERE f.id IS NULL
                 ORDER BY p.id LIMIT 1"
            );
            $raizId = $fila[0]['id'] ?? null;
        }

        responderJSON(1, '', ['persona_id' => $raizId ? (int) $raizId : null]);
    }

    /** API: define que persona abre el arbol por defecto */
    public static function fijarRaizAPI()
    {
        $personaId = (int) ($_POST['persona_id'] ?? 0);

        if (!$personaId || !Personas::find($personaId)) {
            responderJSON(0, 'Persona no válida');
        }

        Configuracion::fijar('raiz_persona_id', (string) $personaId);
        responderJSON(1, 'Raíz del árbol actualizada');
    }

    /** API: buscador de personas (autocompletar en formularios) */
    public static function buscarAPI()
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        if (mb_strlen($q) < 2) {
            responderJSON(1, '', []);
        }
        responderJSON(1, '', Personas::buscarPorTexto($q));
    }
}
