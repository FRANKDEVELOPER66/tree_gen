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

            // Si ya existe esa filiacion (por ejemplo, se cargo antes de
            // que existiera la union con el otro progenitor), se
            // actualiza en vez de rechazarla -- asi el union_id/tipo_relacion
            // quedan al dia sin tener que borrar y volver a crear.
            $existente = Filiaciones::buscar($hijoId, $progenitorId);
            if ($existente) {
                $existente->sincronizar($_POST);
                $existente->actualizar();
                responderJSON(1, 'Filiación actualizada correctamente', ['id' => $existente->id]);
                return;
            }

            $filiacion = new Filiaciones($_POST);
            $resultado = $filiacion->crear();

            responderJSON(1, 'Filiación registrada correctamente', ['id' => $resultado['id']]);
        } catch (Exception $e) {
            responderJSON(0, $e->getMessage());
        }
    }

    /**
     * Asigna (o corrige) el union_id de una filiacion YA existente, sin
     * tocar su tipo_relacion. Se usa cuando alguien ya estaba vinculado a
     * un progenitor desde antes de que existiera la union (union_id
     * quedo en NULL), para no duplicarlo como "hijo suelto" en el arbol.
     */
    public static function asignarUnionAPI()
    {
        try {
            $hijoId = (int) ($_POST['hijo_id'] ?? 0);
            $progenitorId = (int) ($_POST['progenitor_id'] ?? 0);
            $unionId = !empty($_POST['union_id']) ? (int) $_POST['union_id'] : null;

            $filiacion = Filiaciones::buscar($hijoId, $progenitorId);
            if (!$filiacion) {
                throw new Exception('Esa filiación no existe todavía');
            }

            $filiacion->union_id = $unionId;
            $filiacion->actualizar();

            responderJSON(1, 'Unión asignada correctamente');
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
