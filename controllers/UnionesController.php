<?php

namespace Controllers;

use Exception;
use Model\Uniones;

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

            responderJSON(1, 'Unión registrada correctamente', ['id' => $resultado['id']]);
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
