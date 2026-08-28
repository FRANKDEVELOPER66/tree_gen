<?php

namespace Controllers;

use Exception;
use Model\Filiaciones;

class FiliacionesController
{
    public static function guardarAPI()
    {
        try {
            foreach (['hijo_id', 'progenitor_id'] as $campo) {
                if (empty($_POST[$campo])) {
                    throw new Exception('Selecciona al hijo y al progenitor');
                }
            }

            $hijoId = (int) $_POST['hijo_id'];
            $progenitorId = (int) $_POST['progenitor_id'];

            if ($hijoId === $progenitorId) {
                throw new Exception('Una persona no puede ser progenitora de sí misma');
            }

            if (Filiaciones::existe($hijoId, $progenitorId)) {
                throw new Exception('Esa filiación ya existe');
            }

            $filiacion = new Filiaciones($_POST);
            $resultado = $filiacion->crear();

            responderJSON(1, 'Filiación registrada correctamente', ['id' => $resultado['id']]);
        } catch (Exception $e) {
            responderJSON(0, $e->getMessage());
        }
    }

    public static function eliminarAPI()
    {
        $id = (int) ($_POST['id'] ?? 0);
        $filiacion = Filiaciones::find($id);

        if (!$filiacion) {
            responderJSON(0, 'Filiación no encontrada');
        }

        $filiacion->eliminar();
        responderJSON(1, 'Filiación eliminada correctamente');
    }
}
