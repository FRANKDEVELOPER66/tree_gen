<?php

namespace Model;

use PDO;

class ActiveRecord
{

    // Base DE DATOS
    protected static $db;
    protected static $tabla = '';
    protected static $columnasDB = [];

    protected static $idTabla = '';

    // Alertas y Mensajes
    protected static $alertas = [];

    // Definir la conexión a la BD - includes/database.php
    public static function setDB($database)
    {
        self::$db = $database;
    }

    public static function setAlerta($tipo, $mensaje)
    {
        static::$alertas[$tipo][] = $mensaje;
    }
    // Validación
    public static function getAlertas()
    {
        return static::$alertas;
    }

    public function validar()
    {
        static::$alertas = [];
        return static::$alertas;
    }

    // Registros - CRUD
    public function guardar()
    {
        $resultado = '';
        // FIX: era "?? 'id'" -- $idTabla por defecto es '' (no null), y
        // '' ?? 'id' sigue siendo '', nunca cae al fallback. Con ?: si.
        $id = static::$idTabla ?: 'id';
        if (!is_null($this->$id)) {
            // actualizar
            $resultado = $this->actualizar();
        } else {
            // Creando un nuevo registro
            $resultado = $this->crear();
        }
        return $resultado;
    }

    public static function all()
    {
        $query = "SELECT * FROM " . static::$tabla;
        $resultado = self::consultarSQL($query);

        // debuguear($resultado);
        return $resultado;
    }

    // Busca un registro por su id
    public static function find($id = [])
    {
        $id_col    = static::$idTabla ?: 'id';
        $query     = "SELECT * FROM " . static::$tabla . " WHERE " . $id_col . " = " . self::$db->quote($id) . " LIMIT 1";
        $resultado = self::consultarSQL($query);
        return array_shift($resultado);
    }

    // Obtener Registro
    public static function get($limite)
    {
        $query = "SELECT * FROM " . static::$tabla . " LIMIT ${limite}";
        $resultado = self::consultarSQL($query);
        return array_shift($resultado);
    }

    public static function getDB()
    {
        return self::$db;
    }

    // Busqueda Where con Columna 
    public static function where($columna, $valor, $condicion = '=')
    {
        $query = "SELECT * FROM " . static::$tabla . " WHERE ${columna} ${condicion} '${valor}'";
        $resultado = self::consultarSQL($query);
        return  $resultado;
    }

    // SQL para Consultas Avanzadas.
    public static function SQL($consulta)
    {
        $query = $consulta;
        $resultado = self::$db->query($query);
        return $resultado;
    }

    // crea un nuevo registro
    public function crear()
    {
        // Sanitizar los datos
        $atributos = $this->sanitizarAtributos();

        // Insertar en la base de datos
        $query = " INSERT INTO " . static::$tabla . " ( ";
        $query .= join(', ', array_keys($atributos));
        $query .= " ) VALUES (";
        $query .= join(", ", array_values($atributos));
        $query .= " ) ";

        // Resultado de la consulta
        $resultado = self::$db->exec($query);

        return [
            'resultado' =>  $resultado,
            'id' => self::$db->lastInsertId(static::$tabla)
        ];
    }

    public function actualizar()
    {
        // Sanitizar los datos
        $atributos = $this->sanitizarAtributos();

        // Iterar para ir agregando cada campo de la BD
        $valores = [];
        foreach ($atributos as $key => $value) {
            $valores[] = "{$key}={$value}";
        }
        // FIX: era "?? 'id'" -- mismo motivo que en guardar()
        $id = static::$idTabla ?: 'id';

        $query = "UPDATE " . static::$tabla . " SET ";
        $query .=  join(', ', $valores);

        if (is_array(static::$idTabla)) {
            foreach (static::$idTabla as $key => $value) {
                if ($value == reset(static::$idTabla)) {
                    $query .= " WHERE $value = " . self::$db->quote($this->$value);
                } else {
                    $query .= " AND $value = " . self::$db->quote($this->$value);
                }
            }
        } else {
            $query .= " WHERE " . $id . " = " . self::$db->quote($this->$id) . " ";
        }

        $resultado = self::$db->exec($query);
        return [
            'resultado' =>  $resultado,
        ];
    }

    // Eliminar un registro - Toma el ID de Active Record
    public function eliminar()
    {
        $id_col = static::$idTabla ?: 'id';
        $query  = "DELETE FROM " . static::$tabla . " WHERE " . $id_col . " = " . self::$db->quote($this->$id_col);
        return self::$db->exec($query);
    }

    public static function consultarSQL($query)
    {
        // Consultar la base de datos
        $resultado = self::$db->query($query);

        // Iterar los resultados
        $array = [];
        while ($registro = $resultado->fetch(PDO::FETCH_ASSOC)) {
            $array[] = static::crearObjeto($registro);
        }

        // liberar la memoria
        $resultado->closeCursor();

        // retornar los resultados
        return $array;
    }

    /**
     * Ejecutar query con parámetros preparados y retornar array
     */
    public static function fetchArray($query, $params = [])
    {
        if (empty($params)) {
            // Si no hay parámetros, usar el método original
            $resultado = self::$db->query($query);
        } else {
            // Si hay parámetros, usar prepared statement
            $stmt = self::$db->prepare($query);
            $stmt->execute($params);
            $resultado = $stmt;
        }

        $respuesta = $resultado->fetchAll(PDO::FETCH_ASSOC);
        $data = [];
        foreach ($respuesta as $value) {
            $data[] = array_change_key_case($value);
        }
        $resultado->closeCursor();
        return $data;
    }

    /**
     * Ejecutar query con parámetros preparados y retornar primer resultado
     */
    public static function fetchFirst($query, $params = [])
    {
        if (empty($params)) {
            // Si no hay parámetros, usar el método original
            $resultado = self::$db->query($query);
        } else {
            // Si hay parámetros, usar prepared statement
            $stmt = self::$db->prepare($query);
            $stmt->execute($params);
            $resultado = $stmt;
        }

        $respuesta = $resultado->fetchAll(PDO::FETCH_ASSOC);
        $data = [];
        foreach ($respuesta as $value) {
            $data[] = array_change_key_case($value);
        }
        $resultado->closeCursor();
        return array_shift($data);
    }

    protected static function crearObjeto($registro)
    {
        $objeto = new static;

        foreach ($registro as $key => $value) {
            $key = strtolower($key);
            if (property_exists($objeto, $key)) {
                $objeto->$key = $value;
            }
        }

        return $objeto;
    }

    // Identificar y unir los atributos de la BD
    public function atributos()
    {
        $atributos = [];
        foreach (static::$columnasDB as $columna) {
            $columna = strtolower($columna);
            // Para vehículos, la placa SÍ debe incluirse en el INSERT
            $atributos[$columna] = $this->$columna;
        }
        return $atributos;
    }

    public function sanitizarAtributos()
    {
        $atributos = $this->atributos();
        $sanitizado = [];
        foreach ($atributos as $key => $value) {
            // FIX: si es null, insertar NULL de verdad, no comillas vacías
            $sanitizado[$key] = is_null($value) ? 'NULL' : self::$db->quote($value);
        }
        return $sanitizado;
    }

    public function sincronizar($args = [])
    {
        foreach ($args as $key => $value) {
            if (property_exists($this, $key)) {
                // FIX: antes solo asignaba si !is_null($value), pero los
                // campos opcionales vacios de un form llegan como '' (no
                // null), y se guardaban como texto vacio en vez de NULL,
                // rompiendo columnas DATE/INT nullable. Ahora '' se
                // convierte a null explicitamente antes de asignar.
                $this->$key = ($value === '') ? null : $value;
            }
        }
    }
}