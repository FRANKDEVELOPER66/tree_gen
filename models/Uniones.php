<?php

namespace Model;

class Uniones extends ActiveRecord
{
    protected static $tabla = 'uniones';
    protected static $columnasDB = [
        'persona_a_id',
        'persona_b_id',
        'tipo',
        'fecha_inicio',
        'fecha_fin',
        'estado',
        'notas',
    ];

    public $id;
    public $persona_a_id;
    public $persona_b_id;
    public $tipo = 'matrimonio';
    public $fecha_inicio;
    public $fecha_fin;
    public $estado = 'activa';
    public $notas;

    public function __construct($args = [])
    {
        $this->id           = $args['id'] ?? null;
        $this->persona_a_id = $args['persona_a_id'] ?? null;
        $this->persona_b_id = !empty($args['persona_b_id']) ? $args['persona_b_id'] : null;
        $this->tipo         = $args['tipo'] ?? 'matrimonio';
        $this->fecha_inicio = !empty($args['fecha_inicio']) ? $args['fecha_inicio'] : null;
        $this->fecha_fin    = !empty($args['fecha_fin']) ? $args['fecha_fin'] : null;
        $this->estado       = $args['estado'] ?? 'activa';
        $this->notas        = !empty($args['notas']) ? $args['notas'] : null;
    }

    /** Hijos que nacieron/fueron integrados dentro de esta union especifica */
    public static function hijos(int $unionId): array
    {
        // Un hijo puede tener DOS filas de filiacion para la misma union
        // (una por cada progenitor), y a veces con tipo_relacion distinto
        // entre ambas (ej. biologico con uno, padrastro con el otro). Se
        // agrupa por hijo y, si hay mezcla, se prioriza mostrar el tipo
        // "especial" (no biologico), para que la etiqueta en el arbol
        // refleje que con al menos uno de los dos no es de sangre.
        return self::fetchArray(
            "SELECT p.id, p.nombres, p.apellidos, p.foto_perfil, p.genero,
                    p.fecha_nacimiento, p.fecha_fallecimiento,
                    CASE
                        WHEN COUNT(DISTINCT f.tipo_relacion) > 1
                            THEN MAX(CASE WHEN f.tipo_relacion <> 'biologico' THEN f.tipo_relacion END)
                        ELSE MAX(f.tipo_relacion)
                    END AS tipo_relacion
             FROM filiaciones f
             JOIN personas p ON p.id = f.hijo_id
             WHERE f.union_id = ?
             GROUP BY p.id, p.nombres, p.apellidos, p.foto_perfil, p.genero,
                      p.fecha_nacimiento, p.fecha_fallecimiento
             ORDER BY p.fecha_nacimiento",
            [$unionId]
        );
    }
}
