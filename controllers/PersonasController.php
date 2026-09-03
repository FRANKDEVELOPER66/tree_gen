<?php

namespace Controllers;

use Exception;
use Model\Fotos;
use Model\Personas;
use MVC\Router;

class PersonasController
{
    public static function index(Router $router)
    {
        $router->render('personas/index', [
            'titulo' => 'Personas',
        ]);
    }

    public static function listarAPI()
    {
        $personas = array_map(fn($p) => $p->atributos() + ['id' => $p->id], Personas::all());
        responderJSON(1, '', $personas);
    }

    public static function detalleAPI()
    {
        $id = (int) ($_GET['id'] ?? 0);
        $persona = Personas::find($id);

        if (!$persona) {
            responderJSON(0, 'Persona no encontrada');
        }

        responderJSON(1, '', [
            'persona' => $persona->atributos() + ['id' => $persona->id],
            'fotos' => Fotos::dePersona($id),
            'progenitores' => Personas::progenitores($id),
        ]);
    }

    // ── GUARDAR ──────────────────────────────────────────────────────────

    public static function guardarAPI()
    {
        try {
            foreach (['nombres', 'apellidos'] as $campo) {
                if (empty($_POST[$campo])) {
                    throw new Exception("El campo {$campo} es obligatorio");
                }
            }

            $nombreFoto = null;
            if (!empty($_FILES['foto_perfil']['name'])) {
                $nombreFoto = subirImagen($_FILES['foto_perfil'], 'perfil');
            }

            $persona = new Personas($_POST);
            $persona->foto_perfil = $nombreFoto;
            $resultado = $persona->crear();

            responderJSON(1, 'Persona registrada correctamente', ['id' => $resultado['id']]);
        } catch (Exception $e) {
            responderJSON(0, $e->getMessage());
        }
    }

    // ── MODIFICAR ────────────────────────────────────────────────────────

    public static function modificarAPI()
    {
        try {
            $id = (int) ($_POST['id'] ?? 0);
            $persona = Personas::find($id);

            if (!$persona) {
                responderJSON(0, 'Persona no encontrada');
            }

            foreach (['nombres', 'apellidos'] as $campo) {
                if (empty($_POST[$campo])) {
                    throw new Exception("El campo {$campo} es obligatorio");
                }
            }

            if (!empty($_FILES['foto_perfil']['name'])) {
                $nuevaFoto = subirImagen($_FILES['foto_perfil'], 'perfil');
                if ($nuevaFoto) {
                    eliminarImagen($persona->foto_perfil);
                    $_POST['foto_perfil'] = $nuevaFoto;
                }
            } else {
                unset($_POST['foto_perfil']); // conservar la foto actual
            }

            $persona->sincronizar($_POST);
            $persona->actualizar();

            responderJSON(1, 'Persona actualizada correctamente');
        } catch (Exception $e) {
            responderJSON(0, $e->getMessage());
        }
    }

    // ── ELIMINAR ─────────────────────────────────────────────────────────

    public static function eliminarAPI()
    {
        $id = (int) ($_POST['id'] ?? 0);
        $persona = Personas::find($id);

        if (!$persona) {
            responderJSON(0, 'Persona no encontrada');
        }

        eliminarImagen($persona->foto_perfil);
        foreach (Fotos::dePersona($id) as $foto) {
            eliminarImagen($foto['ruta']);
        }

        $persona->eliminar();
        responderJSON(1, 'Persona eliminada correctamente');
    }

    // ── GALERIA DE FOTOS ─────────────────────────────────────────────────

    public static function agregarFotoAPI()
    {
        try {
            $personaId = (int) ($_POST['persona_id'] ?? 0);
            if (!Personas::find($personaId)) {
                throw new Exception('Persona no encontrada');
            }
            if (empty($_FILES['foto']['name'])) {
                throw new Exception('Selecciona una imagen');
            }

            $nombreArchivo = subirImagen($_FILES['foto'], 'galeria');
            $foto = new Fotos([
                'persona_id' => $personaId,
                'ruta' => $nombreArchivo,
                'descripcion' => $_POST['descripcion'] ?? null,
            ]);
            $foto->crear();

            responderJSON(1, 'Foto agregada correctamente', ['ruta' => $nombreArchivo]);
        } catch (Exception $e) {
            responderJSON(0, $e->getMessage());
        }
    }

    public static function eliminarFotoAPI()
    {
        $id = (int) ($_POST['id'] ?? 0);
        $foto = Fotos::find($id);

        if (!$foto) {
            responderJSON(0, 'Foto no encontrada');
        }

        eliminarImagen($foto->ruta);
        $foto->eliminar();
        responderJSON(1, 'Foto eliminada correctamente');
    }

    /** API: hijos ya vinculados a una persona (para excluirlos del selector "Vincular hijo/a") */
    public static function hijosAPI()
    {
        $id = (int) ($_GET['id'] ?? 0);
        responderJSON(1, '', Personas::hijos($id));
    }

    /** API: parejas (por union) de una persona (para excluirlas del selector "Vincular hijo/a") */
    public static function parejasAPI()
    {
        $id = (int) ($_GET['id'] ?? 0);
        responderJSON(1, '', Personas::parejas($id));
    }

    /** API: progenitores de una persona (para excluirlos del selector "Agregar unión" — no puede ser pareja de su padre/madre) */
    public static function progenitoresAPI()
    {
        $id = (int) ($_GET['id'] ?? 0);
        responderJSON(1, '', Personas::progenitores($id));
    }

    /** API: hermanos/as de una persona, distinguiendo completos vs medios hermanos */
    public static function hermanosAPI()
    {
        $id = (int) ($_GET['id'] ?? 0);
        responderJSON(1, '', Personas::hermanos($id));
    }

    /** API: todas las personas agrupadas por familia (union), + las que no tienen ningun vinculo */
    public static function familiasAPI()
    {
        responderJSON(1, '', Personas::familias());
    }

    /** API: toda la red familiar conectada a una persona (para excluirla completa de selectores de pareja/hijo) */
    public static function redFamiliarAPI()
    {
        $id = (int) ($_GET['id'] ?? 0);
        responderJSON(1, '', Personas::redFamiliar($id));
    }

    /** API: IDs de quienes ya tienen 2+ progenitores (para excluirlos de "Vincular hijo/a") */
    public static function conDosProgenitoresAPI()
    {
        responderJSON(1, '', Personas::conDosProgenitores());
    }

    /** API: mapa persona_id -> pareja actual, para quienes tienen una union activa (aviso en "Agregar union") */
    public static function unionesActivasAPI()
    {
        responderJSON(1, '', Personas::unionesActivasPorPersona());
    }

    /** API: uniones de una persona con datos de pareja (para elegir a que union pertenece un hijo) */
    public static function unionesAPI()
    {
        $id = (int) ($_GET['id'] ?? 0);
        responderJSON(1, '', Personas::unionesResumen($id));
    }
}
