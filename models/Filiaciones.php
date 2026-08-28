<?php

namespace Model;

class Filiaciones extends ActiveRecord
{
    protected static $tabla = 'filiaciones';
    protected static $columnasDB = [
        'hijo_id',
        'progenitor_id',
        'union_id',
        'tipo_relacion',
        'notas',
    ];

    public $id;
    public $hijo_id;
    public $progenitor_id;
    public $union_id;
    public $tipo_relacion = 'biologico';
    public $notas;

    public function __construct($args = [])
    {
        $this->id            = $args['id'] ?? null;
        $this->hijo_id       = $args['hijo_id'] ?? null;
        $this->progenitor_id = $args['progenitor_id'] ?? null;
        $this->union_id      = !empty($args['union_id']) ? $args['union_id'] : null;
        $this->tipo_relacion = $args['tipo_relacion'] ?? 'biologico';
        $this->notas         = !empty($args['notas']) ? $args['notas'] : null;
    }

    /** Todos los progenitores registrados de un hijo (con nombre/apellido ya resueltos) */
    public static function deHijo(int $hijoId): array
    {
        return self::fetchArray(
            "SELECT f.*, p.nombres, p.apellidos
             FROM filiaciones f
             JOIN personas p ON p.id = f.progenitor_id
             WHERE f.hijo_id = ?",
            [$hijoId]
        );
    }

    /** true si ya existe esa filiacion exacta (hijo + progenitor); evita duplicados antes de crear() */
    public static function existe(int $hijoId, int $progenitorId): bool
    {
        $fila = self::fetchArray(
            'SELECT id FROM filiaciones WHERE hijo_id = ? AND progenitor_id = ? LIMIT 1',
            [$hijoId, $progenitorId]
        );
        return !empty($fila);
    }
}
