<?php
 
namespace Model;
 
/**
 * Ajustes clave-valor (ej. que persona es la raiz del arbol).
 * Su llave primaria es 'clave' (varchar), no 'id' — igual que tu modelo
 * Vehiculos usa 'placa' en vez de 'id'. Por eso sobreescribe find() y
 * eliminar(), que en el ActiveRecord base asumen 'id'.
 */
class Configuracion extends ActiveRecord
{
    protected static $tabla = 'configuracion';
    protected static $columnasDB = [
        'clave',
        'valor',
    ];
 
    public $clave;
    public $valor;
 
    public function __construct($args = [])
    {
        $this->clave = $args['clave'] ?? '';
        $this->valor = $args['valor'] ?? null;
    }
 
    public static function find($clave = [])
    {
        $resultado = self::consultarSQL(
            'SELECT * FROM configuracion WHERE clave = ' . self::$db->quote($clave) . ' LIMIT 1'
        );
        return array_shift($resultado);
    }
 
    public function eliminar()
    {
        $query = 'DELETE FROM configuracion WHERE clave = ' . self::$db->quote($this->clave);
        return self::$db->exec($query);
    }
 
    /** Valor guardado para una clave, o null si no existe */
    public static function obtener(string $clave): ?string
    {
        $config = self::find($clave);
        return $config ? $config->valor : null;
    }
 
    /** Crea o actualiza el valor de una clave (upsert) */
    public static function fijar(string $clave, ?string $valor): void
    {
        $claveEsc = self::$db->quote($clave);
        $valorEsc = $valor === null ? 'NULL' : self::$db->quote($valor);
 
        self::$db->exec(
            "INSERT INTO configuracion (clave, valor) VALUES ({$claveEsc}, {$valorEsc})
             ON DUPLICATE KEY UPDATE valor = {$valorEsc}"
        );
    }
}
