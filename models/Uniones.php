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
        return self::fetchArray(
            "SELECT DISTINCT p.id, p.nombres, p.apellidos, p.foto_perfil, p.genero,
                    p.fecha_nacimiento, p.fecha_fallecimiento
             FROM filiaciones f
             JOIN personas p ON p.id = f.hijo_id
             WHERE f.union_id = ?
             ORDER BY p.fecha_nacimiento",
            [$unionId]
        );
    }
}
