<?php

namespace Controllers;

use Exception;
use Model\Uniones;
use Model\Filiaciones;

class UnionesController
{
    public static function guardarAPI()
    {
        try {
            if (empty($_POST['persona_a_id'])) {
                throw new Exception('Selecciona la primera persona de la unión');
            }

            $union = new Uniones($_POST);
            $resultado = $union->crear();
            $unionId = (int) $resultado['id'];

            // Si ambas personas ya tenian, cada una por separado, una
            // filiacion con el mismo hijo (cargada antes de que existiera
            // esta union), se la asignamos de forma retroactiva.
            if (!empty($_POST['persona_b_id'])) {
                Filiaciones::backfillUnion($unionId, (int) $_POST['persona_a_id'], (int) $_POST['persona_b_id']);
            }

            responderJSON(1, 'Unión registrada correctamente', ['id' => $unionId]);
        } catch (Exception $e) {
            responderJSON(0, $e->getMessage());
        }
    }

    public static function modificarAPI()
    {
        try {
            $id = (int) ($_POST['id'] ?? 0);
            $union = Uniones::find($id);

            if (!$union) {
                throw new Exception('Unión no encontrada');
            }

            $union->sincronizar($_POST);
            $union->actualizar();

            responderJSON(1, 'Unión actualizada correctamente');
        } catch (Exception $e) {
            responderJSON(0, $e->getMessage());
        }
    }

    public static function eliminarAPI()
    {
        $id = (int) ($_POST['id'] ?? 0);
        $union = Uniones::find($id);

        if (!$union) {
            responderJSON(0, 'Unión no encontrada');
        }

        $union->eliminar();
        responderJSON(1, 'Unión eliminada correctamente');
    }
}
