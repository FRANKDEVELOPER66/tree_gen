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
            "SELECT p.id, p.nombres, p.apellidos, p.foto_perfil, f.tipo_relacion
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
                    pa.nombres AS a_nombres, pa.apellidos AS a_apellidos, pa.foto_perfil AS a_foto,
                    pb.nombres AS b_nombres, pb.apellidos AS b_apellidos, pb.foto_perfil AS b_foto
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
            $apA = $primerApellido($u['a_apellidos']);
            $apB = $u['persona_b_id'] ? $primerApellido($u['b_apellidos']) : null;

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
                'nombre' => 'Familia ' . trim($apA . ' ' . ($apB ?? '')),
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
                    if ($m['id'] === $progenitorId) {
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
}
