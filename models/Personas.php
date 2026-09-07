<?php

namespace Model;

class Personas extends ActiveRecord
{
    protected static $tabla = 'personas';
    protected static $columnasDB = [
        'nombres',
        'apellidos',
        'apodo',
        'genero',
        'fecha_nacimiento',
        'fecha_fallecimiento',
        'lugar_nacimiento',
        'biografia',
        'foto_perfil',
    ];

    public $id;
    public $nombres;
    public $apellidos;
    public $apodo;
    public $genero = 'desconocido';
    public $fecha_nacimiento;
    public $fecha_fallecimiento;
    public $lugar_nacimiento;
    public $biografia;
    public $foto_perfil;

    public function __construct($args = [])
    {
        $this->id                  = $args['id'] ?? null;
        $this->nombres             = $args['nombres'] ?? '';
        $this->apellidos           = $args['apellidos'] ?? '';
        $this->apodo               = !empty($args['apodo']) ? $args['apodo'] : null;
        $this->genero               = $args['genero'] ?? 'desconocido';
        $this->fecha_nacimiento    = !empty($args['fecha_nacimiento']) ? $args['fecha_nacimiento'] : null;
        $this->fecha_fallecimiento = !empty($args['fecha_fallecimiento']) ? $args['fecha_fallecimiento'] : null;
        $this->lugar_nacimiento    = !empty($args['lugar_nacimiento']) ? $args['lugar_nacimiento'] : null;
        $this->biografia           = !empty($args['biografia']) ? $args['biografia'] : null;
        $this->foto_perfil         = $args['foto_perfil'] ?? null;
    }

    // ── BUSQUEDAS ────────────────────────────────────────────────────────────

    public static function buscarPorTexto(string $texto): array
    {
        return self::fetchArray(
            "SELECT id, nombres, apellidos, foto_perfil FROM personas
             WHERE CONCAT(nombres, ' ', apellidos) LIKE ?
             ORDER BY apellidos, nombres LIMIT 20",
            ['%' . $texto . '%']
        );
    }

    // ── RELACIONES (joins -> fetchArray, arrays planos) ────────────────────

    /** Todas las uniones (parejas) en las que participa esta persona, con datos de la pareja */
    public static function uniones(int $personaId): array
    {
        return self::fetchArray(
            "SELECT u.*,
                    pa.id AS a_id, pa.nombres AS a_nombres, pa.apellidos AS a_apellidos, pa.foto_perfil AS a_foto,
                    pb.id AS b_id, pb.nombres AS b_nombres, pb.apellidos AS b_apellidos, pb.foto_perfil AS b_foto
             FROM uniones u
             JOIN personas pa ON pa.id = u.persona_a_id
             LEFT JOIN personas pb ON pb.id = u.persona_b_id
             WHERE u.persona_a_id = ? OR u.persona_b_id = ?
             ORDER BY u.fecha_inicio",
            [$personaId, $personaId]
        );
    }

    /** Progenitores directos de una persona, con el tipo de relacion (biologico, adoptivo, etc) */
    public static function progenitores(int $personaId): array
    {
        return self::fetchArray(
            "SELECT p.id, p.nombres, p.apellidos, p.foto_perfil, f.tipo_relacion, f.id AS filiacion_id
             FROM filiaciones f
             JOIN personas p ON p.id = f.progenitor_id
             WHERE f.hijo_id = ?",
            [$personaId]
        );
    }

    /** Hermanos: personas que comparten al menos un progenitor */
    /**
     * Hermanos/as: cualquiera que comparta al menos un progenitor con esta
     * persona, marcando si son hermanos "completo" (comparten TODOS los
     * mismos progenitores) o "medio" (comparten solo algunos).
     */
    public static function hermanos(int $personaId): array
    {
        $totalPropios = count(self::progenitores($personaId));

        $candidatos = self::fetchArray(
            "SELECT p.id, p.nombres, p.apellidos, p.foto_perfil,
                    COUNT(DISTINCT f2.progenitor_id) AS compartidos,
                    (SELECT COUNT(*) FROM filiaciones fx WHERE fx.hijo_id = p.id) AS total_progenitores
             FROM filiaciones f1
             JOIN filiaciones f2 ON f1.progenitor_id = f2.progenitor_id AND f1.hijo_id <> f2.hijo_id
             JOIN personas p ON p.id = f2.hijo_id
             WHERE f1.hijo_id = ?
             GROUP BY p.id, p.nombres, p.apellidos, p.foto_perfil",
            [$personaId]
        );

        foreach ($candidatos as &$c) {
            $compartidos = (int) $c['compartidos'];
            $totalSuyos = (int) $c['total_progenitores'];
            $c['tipo'] = ($compartidos === $totalPropios && $compartidos === $totalSuyos)
                ? 'completo'
                : 'medio';
            unset($c['compartidos'], $c['total_progenitores']);
        }

        return $candidatos;
    }

    /** Hijos ya vinculados a esta persona como progenitora (para excluirlos de selectores) */
    public static function hijos(int $progenitorId): array
    {
        return self::fetchArray(
            "SELECT p.id, p.nombres, p.apellidos
             FROM filiaciones f
             JOIN personas p ON p.id = f.hijo_id
             WHERE f.progenitor_id = ?",
            [$progenitorId]
        );
    }

    /**
     * Hijos vinculados a esta persona SIN ninguna union asignada (union_id
     * NULL) -- el caso de un progenitor soltero, o con pareja pero esa
     * pareja todavia no esta cargada en el sistema. El arbol necesita
     * esto aparte, porque su recorrido normal solo encuentra hijos a
     * traves de una union registrada.
     */
    public static function hijosSinUnion(int $progenitorId): array
    {
        return self::fetchArray(
            "SELECT p.id, p.nombres, p.apellidos, p.foto_perfil, p.genero,
                    p.fecha_nacimiento, p.fecha_fallecimiento, f.tipo_relacion
             FROM filiaciones f
             JOIN personas p ON p.id = f.hijo_id
             WHERE f.progenitor_id = ? AND f.union_id IS NULL",
            [$progenitorId]
        );
    }

    /** Parejas (por union) de esta persona -- para no dejar vincular a la pareja como hijo/a tambien */
    public static function parejas(int $personaId): array
    {
        $filas = self::uniones($personaId);
        $resultado = [];
        foreach ($filas as $u) {
            $esA = (int) $u['persona_a_id'] === $personaId;
            $parejaId = $esA ? $u['b_id'] : $u['a_id'];
            if ($parejaId) {
                $resultado[] = [
                    'id' => (int) $parejaId,
                    'nombres' => $esA ? $u['b_nombres'] : $u['a_nombres'],
                    'apellidos' => $esA ? $u['b_apellidos'] : $u['a_apellidos'],
                ];
            }
        }
        return $resultado;
    }

    /**
     * Uniones de esta persona con el id de la union y los datos de la
     * pareja incluidos (para el selector "Vincular hijo/a": a que union
     * pertenece el hijo, y a quien mas vincular automaticamente).
     */
    public static function unionesResumen(int $personaId): array
    {
        $filas = self::uniones($personaId);
        $resultado = [];
        foreach ($filas as $u) {
            $esA = (int) $u['persona_a_id'] === $personaId;
            $parejaId = $esA ? $u['b_id'] : $u['a_id'];
            $resultado[] = [
                'union_id' => (int) $u['id'],
                'tipo' => $u['tipo'],
                'estado' => $u['estado'],
                'pareja' => $parejaId ? [
                    'id' => (int) $parejaId,
                    'nombres' => $esA ? $u['b_nombres'] : $u['a_nombres'],
                    'apellidos' => $esA ? $u['b_apellidos'] : $u['a_apellidos'],
                ] : null,
            ];
        }
        return $resultado;
    }

    /**
     * Agrupa a todas las personas en "familias": cada union (pareja) forma
     * una familia con sus hijos vinculados a esa union; los progenitores
     * solteros (filiacion sin union) forman su propia familia monoparental;
     * y quien no tenga ningun vinculo (ni union, ni hijos, ni progenitores)
     * queda sin asociar.
     */
    public static function familias(): array
    {
        $primerApellido = function ($apellidos) {
            $partes = explode(' ', trim((string) $apellidos));
            return $partes[0] ?? '';
        };

        $uniones = self::fetchArray(
            "SELECT u.id AS union_id, u.persona_a_id, u.persona_b_id,
                    pa.nombres AS a_nombres, pa.apellidos AS a_apellidos, pa.foto_perfil AS a_foto, pa.genero AS a_genero,
                    pb.nombres AS b_nombres, pb.apellidos AS b_apellidos, pb.foto_perfil AS b_foto, pb.genero AS b_genero
             FROM uniones u
             JOIN personas pa ON pa.id = u.persona_a_id
             LEFT JOIN personas pb ON pb.id = u.persona_b_id"
        );

        $filiaciones = self::fetchArray(
            "SELECT f.hijo_id, f.progenitor_id, f.union_id,
                    p.nombres, p.apellidos, p.foto_perfil
             FROM filiaciones f
             JOIN personas p ON p.id = f.hijo_id"
        );

        $familias = [];
        $agrupados = [];

        // 1) Una familia por cada union, con sus hijos (por union_id)
        foreach ($uniones as $u) {
            $unionId = (int) $u['union_id'];

            // Nombre de familia: apellido del hombre primero (convencion
            // cultural), sea o no persona_a en la union. Si no hay pareja
            // o los generos no distinguen, se usa el orden original.
            if ($u['persona_b_id'] && $u['a_genero'] === 'femenino' && $u['b_genero'] === 'masculino') {
                $apPrimero = $primerApellido($u['b_apellidos']);
                $apSegundo = $primerApellido($u['a_apellidos']);
            } else {
                $apPrimero = $primerApellido($u['a_apellidos']);
                $apSegundo = $u['persona_b_id'] ? $primerApellido($u['b_apellidos']) : null;
            }

            $miembros = [[
                'id' => (int) $u['persona_a_id'],
                'nombres' => $u['a_nombres'],
                'apellidos' => $u['a_apellidos'],
                'foto_perfil' => $u['a_foto'],
                'rol' => 'Cónyuge',
            ]];
            $agrupados[(int) $u['persona_a_id']] = true;

            if ($u['persona_b_id']) {
                $miembros[] = [
                    'id' => (int) $u['persona_b_id'],
                    'nombres' => $u['b_nombres'],
                    'apellidos' => $u['b_apellidos'],
                    'foto_perfil' => $u['b_foto'],
                    'rol' => 'Cónyuge',
                ];
                $agrupados[(int) $u['persona_b_id']] = true;
            }

            foreach ($filiaciones as $f) {
                if ((int) $f['union_id'] === $unionId) {
                    $existe = array_filter($miembros, fn($m) => $m['id'] === (int) $f['hijo_id']);
                    if (!$existe) {
                        $miembros[] = [
                            'id' => (int) $f['hijo_id'],
                            'nombres' => $f['nombres'],
                            'apellidos' => $f['apellidos'],
                            'foto_perfil' => $f['foto_perfil'],
                            'rol' => 'Hijo/a',
                        ];
                    }
                    $agrupados[(int) $f['hijo_id']] = true;
                }
            }

            $familias[] = [
                'id' => 'union_' . $unionId,
                'nombre' => 'Familia ' . trim($apPrimero . ' ' . ($apSegundo ?? '')),
                'miembros' => $miembros,
            ];
        }

        // 2) Filiaciones sin union (progenitor soltero) -> agregar al hijo a
        //    una familia donde ya aparezca ese progenitor, o crear una nueva
        //    familia monoparental si el progenitor no esta en ninguna
        $sueltasPorProgenitor = [];
        foreach ($filiaciones as $f) {
            if ($f['union_id']) {
                continue;
            }
            $sueltasPorProgenitor[$f['progenitor_id']][] = $f;
        }

        foreach ($sueltasPorProgenitor as $progenitorId => $hijos) {
            $progenitorId = (int) $progenitorId;
            $familiaExistente = null;

            foreach ($familias as &$fam) {
                foreach ($fam['miembros'] as $m) {
                    // Solo cuenta si el progenitor aparece ahi como cabeza
                    // de familia (Conyuge/Progenitor-a) -- si aparece como
                    // "Hijo/a", esa es la familia DONDE NACIO, no la suya
                    // propia, y no hay que meter a sus propios hijos ahi.
                    if ($m['id'] === $progenitorId && $m['rol'] !== 'Hijo/a') {
                        $familiaExistente = &$fam;
                        break 2;
                    }
                }
            }
            unset($fam);

            if ($familiaExistente !== null) {
                foreach ($hijos as $h) {
                    $existe = array_filter($familiaExistente['miembros'], fn($m) => $m['id'] === (int) $h['hijo_id']);
                    if (!$existe) {
                        $familiaExistente['miembros'][] = [
                            'id' => (int) $h['hijo_id'],
                            'nombres' => $h['nombres'],
                            'apellidos' => $h['apellidos'],
                            'foto_perfil' => $h['foto_perfil'],
                            'rol' => 'Hijo/a',
                        ];
                    }
                    $agrupados[(int) $h['hijo_id']] = true;
                }
                unset($familiaExistente);
            } else {
                $progenitor = self::find($progenitorId);
                if (!$progenitor) {
                    continue;
                }
                $miembros = [[
                    'id' => $progenitorId,
                    'nombres' => $progenitor->nombres,
                    'apellidos' => $progenitor->apellidos,
                    'foto_perfil' => $progenitor->foto_perfil,
                    'rol' => 'Progenitor/a',
                ]];
                $agrupados[$progenitorId] = true;

                foreach ($hijos as $h) {
                    $miembros[] = [
                        'id' => (int) $h['hijo_id'],
                        'nombres' => $h['nombres'],
                        'apellidos' => $h['apellidos'],
                        'foto_perfil' => $h['foto_perfil'],
                        'rol' => 'Hijo/a',
                    ];
                    $agrupados[(int) $h['hijo_id']] = true;
                }

                $familias[] = [
                    'id' => 'progenitor_' . $progenitorId,
                    'nombre' => 'Familia ' . $primerApellido($progenitor->apellidos),
                    'miembros' => $miembros,
                ];
            }
        }

        // 3) Quien no quedo en ninguna familia, va suelto
        $sinAsociar = [];
        foreach (self::all() as $p) {
            if (!isset($agrupados[(int) $p->id])) {
                $sinAsociar[] = $p->atributos() + ['id' => $p->id];
            }
        }

        return ['familias' => $familias, 'sin_asociar' => $sinAsociar];
    }

    /**
     * Recorre TODA la red familiar conectada a esta persona: progenitores,
     * hijos, y parejas, en cascada (asi de padres se llega a abuelos, de
     * abuelos a tios, de tios a primos, etc). Se usa para no ofrecer como
     * pareja/hijo a alguien que ya es parte de la familia de esta persona,
     * sin importar el grado de parentesco.
     */
    /**
     * Recorre la LINEA DE SANGRE conectada a esta persona: progenitores e
     * hijos, en cascada (asi de padres se llega a abuelos, de abuelos a
     * tios -por sus hijos-, de tios a primos, etc). A proposito NO
     * recorre parejas -- un padrastro/madrastra (la pareja de un
     * progenitor) no es pariente de sangre, y debe poder elegirse como
     * segundo progenitor o como pareja de otra persona sin problema.
     */
    /**
     * Recorre la LINEA DE SANGRE conectada a esta persona: progenitores e
     * hijos, en cascada, PERO SOLO por vinculos biologico/adoptivo (asi de
     * padres se llega a abuelos, de abuelos a tios, de tios a primos,
     * etc). A proposito no cuenta parejas, ni vinculos de padrastro/
     * madrastra/tutor -- un padrastro no es pariente de sangre, y no debe
     * bloquear que se lo elija como segundo progenitor o como pareja de
     * otra persona, ni que el recorrido "salte" a traves de el hacia
     * gente que tampoco es de sangre.
     */
    /**
     * Todos los parientes de SANGRE de esta persona, calculado en dos
     * pasadas para evitar "cruzar" por matrimonio hacia familias ajenas:
     *
     * 1) Ascendientes: solo subiendo (progenitor de progenitor...). Esto
     *    nunca puede agarrar a alguien que no sea sangre.
     * 2) Para CADA ascendiente (incluida la persona misma), todos SUS
     *    descendientes: solo bajando (hijo de hijo...). Esto encuentra
     *    hermanos, medios hermanos, tios, primos, sobrinos -- pero SIN
     *    nunca subir por el "otro" progenitor de un hijo compartido, que
     *    es justamente lo que antes hacia que la pareja de un tio (que
     *    se caso hacia adentro de la familia) arrastrara a TODA su propia
     *    familia de sangre como si fuera parte de esta.
     */
    public static function redFamiliar(int $personaId): array
    {
        // 1) Ascendientes (incluida la propia persona)
        $ascendientes = [$personaId => true];
        $cola = [$personaId];
        while ($cola) {
            $actual = array_shift($cola);
            $progenitores = self::fetchArray(
                "SELECT progenitor_id AS id FROM filiaciones WHERE hijo_id = ? AND tipo_relacion IN ('biologico', 'adoptivo')",
                [$actual]
            );
            foreach ($progenitores as $p) {
                $pid = (int) $p['id'];
                if (!isset($ascendientes[$pid])) {
                    $ascendientes[$pid] = true;
                    $cola[] = $pid;
                }
            }
        }

        // 2) Descendientes de cada ascendiente (solo bajando, nunca
        //    subiendo por el otro progenitor de un hijo)
        $sangre = [];
        foreach (array_keys($ascendientes) as $ancestroId) {
            $cola = [$ancestroId];
            while ($cola) {
                $actual = array_shift($cola);
                $sangre[$actual] = true;

                $hijos = self::fetchArray(
                    "SELECT hijo_id AS id FROM filiaciones WHERE progenitor_id = ? AND tipo_relacion IN ('biologico', 'adoptivo')",
                    [$actual]
                );
                foreach ($hijos as $h) {
                    $hid = (int) $h['id'];
                    if (!isset($sangre[$hid])) {
                        $cola[] = $hid;
                    }
                }
            }
        }

        unset($sangre[$personaId]);
        return array_keys($sangre);
    }

    /** IDs de quienes ya tienen 2 o mas progenitores registrados (para excluirlos de "Vincular hijo/a") */
    /**
     * IDs de quienes ya tienen 2 progenitores de tipo biologico/adoptivo
     * (el "cupo" real de padres, que es de maximo 2). Los vinculos de
     * padrastro/madrastra/tutor NO cuentan para este cupo -- alguien
     * puede tener un padrastro Y despues seguir necesitando vincular a
     * su padre biologico real si aparece mas adelante.
     */
    public static function conDosProgenitores(): array
    {
        $filas = self::fetchArray(
            "SELECT hijo_id FROM filiaciones
             WHERE tipo_relacion IN ('biologico', 'adoptivo')
             GROUP BY hijo_id HAVING COUNT(*) >= 2"
        );
        return array_map(fn($f) => (int) $f['hijo_id'], $filas);
    }

    /**
     * Mapa persona_id => datos de su pareja, para quienes tienen HOY una
     * union con estado 'activa'. Se usa para avisar (no bloquear) si en
     * "Agregar union" se elige a alguien que ya esta activamente
     * comprometido/a con otra persona.
     */
    public static function unionesActivasPorPersona(): array
    {
        $filas = self::fetchArray(
            "SELECT u.persona_a_id, u.persona_b_id,
                    pa.nombres AS a_nombres, pa.apellidos AS a_apellidos,
                    pb.nombres AS b_nombres, pb.apellidos AS b_apellidos
             FROM uniones u
             JOIN personas pa ON pa.id = u.persona_a_id
             LEFT JOIN personas pb ON pb.id = u.persona_b_id
             WHERE u.estado = 'activa'"
        );

        $mapa = [];
        foreach ($filas as $f) {
            if (!$f['persona_b_id']) {
                continue;
            }
            $mapa[(int) $f['persona_a_id']] = [
                'id' => (int) $f['persona_b_id'],
                'nombres' => $f['b_nombres'],
                'apellidos' => $f['b_apellidos'],
            ];
            $mapa[(int) $f['persona_b_id']] = [
                'id' => (int) $f['persona_a_id'],
                'nombres' => $f['a_nombres'],
                'apellidos' => $f['a_apellidos'],
            ];
        }
        return $mapa;
    }

    /**
     * Solo ascendientes y descendientes DIRECTOS (padres, abuelos, hijos,
     * nietos...), sin saltar por hermanos hacia SUS otros progenitores.
     * Se usa para "Vincular como hijo/a de..." -- ahi no hay que excluir
     * a la pareja de un hermano/a (ej. un padrastro que es progenitor
     * biologico de un medio hermano), porque justamente puede ser un
     * candidato valido como tu propio progenitor no biologico.
     */
    public static function lineaDirecta(int $personaId): array
    {
        $ids = [];

        $cola = [$personaId];
        while ($cola) {
            $actual = array_shift($cola);
            $progenitores = self::fetchArray(
                "SELECT progenitor_id AS id FROM filiaciones WHERE hijo_id = ? AND tipo_relacion IN ('biologico', 'adoptivo')",
                [$actual]
            );
            foreach ($progenitores as $p) {
                $pid = (int) $p['id'];
                if (!isset($ids[$pid])) {
                    $ids[$pid] = true;
                    $cola[] = $pid;
                }
            }
        }

        $cola = [$personaId];
        while ($cola) {
            $actual = array_shift($cola);
            $hijos = self::fetchArray(
                "SELECT hijo_id AS id FROM filiaciones WHERE progenitor_id = ? AND tipo_relacion IN ('biologico', 'adoptivo')",
                [$actual]
            );
            foreach ($hijos as $h) {
                $hid = (int) $h['id'];
                if (!isset($ids[$hid])) {
                    $ids[$hid] = true;
                    $cola[] = $hid;
                }
            }
        }

        unset($ids[$personaId]);
        return array_keys($ids);
    }
}
