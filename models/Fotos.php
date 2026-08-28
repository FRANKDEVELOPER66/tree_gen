<?php

namespace Model;

class Fotos extends ActiveRecord
{
    protected static $tabla = 'fotos';
    protected static $columnasDB = [
        'persona_id',
        'ruta',
        'descripcion',
        'orden',
    ];

    public $id;
    public $persona_id;
    public $ruta;
    public $descripcion;
    public $orden = 0;

    public function __construct($args = [])
    {
        $this->id          = $args['id'] ?? null;
        $this->persona_id  = $args['persona_id'] ?? null;
        $this->ruta        = $args['ruta'] ?? null;
        $this->descripcion = $args['descripcion'] ?? null;
        $this->orden       = $args['orden'] ?? 0;
    }

    public static function dePersona(int $personaId): array
    {
        return self::fetchArray(
            'SELECT * FROM fotos WHERE persona_id = ? ORDER BY orden, id',
            [$personaId]
        );
    }
}
