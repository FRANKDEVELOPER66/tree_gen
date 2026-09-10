<?php

namespace Model;

/**
 * FamiliaGrafo
 * -------------
 * Utilidad para tree_gen que arma el grafo de parentesco (personas + filiaciones + uniones)
 * en memoria y resuelve dos cosas:
 *
 *   1) obtenerSubgrafo($idRaiz, ...)   -> nodos y aristas relevantes para dibujar el
 *                                         "organigrama" (ascendientes, descendientes,
 *                                         y de rebote: hermanos/tíos/primos de esos
 *                                         ascendientes) alrededor de una persona raíz.
 *
 *   2) buscarParentesco($idA, $idB)    -> encuentra el/los ancestro(s) común(es) entre
 *                                         dos personas y devuelve una etiqueta en
 *                                         español ("primos hermanos", "tío abuelo", etc.)
 *                                         más la ruta de nodos para resaltar en el front.
 *
 * DISEÑO: esta clase NO conoce tu ORM. Se le "inyectan" los arrays ya consultados
 * (personas, filiaciones, uniones) vía cargar(). Así puedes probarla con datos de
 * prueba sin tocar la base de datos (ver Tests/FamiliaGrafoTest.php), y en el
 * controlador solo tienes que resolver esos 3 SELECT con tu ActiveRecord real.
 *
 * ===================== AJUSTA A TU ESQUEMA REAL =====================
 * Se asume:
 *   personas:    id, nombres, apellidos, genero ('M'|'F'|null), foto, fecha_nacimiento,
 *                fecha_fallecimiento (null si vive)
 *   filiaciones: persona_id (hijo), padre_madre_id (padre/madre), tipo_relacion
 *                ('biologico'|'adoptivo'|'padrastro_madrastra'|'tutor', etc.)
 *   uniones:     persona1_id, persona2_id (pareja, sin importar el tipo de unión)
 * Si tus columnas se llaman distinto, ajusta solo el método Controllers\OrganigramaController::cargarGrafo()
 * (o el equivalente donde hagas los SELECT) — esta clase no necesita cambios.
 * =======================================================================
 */
class FamiliaGrafo
{
    /** @var array<int, array> id => fila de personas */
    private static $personas = [];

    /** @var array<int, array[]> hijo_id => [ ['padre_id'=>int,'tipo'=>string], ... ] */
    private static $padresPorHijo = [];

    /** @var array<int, int[]> padre_id => [hijo_id, ...] (índice inverso de $padresPorHijo) */
    private static $hijosPorPadre = [];

    /** @var array<int, int[]> persona_id => [pareja_id, ...] */
    private static $unionesPorPersona = [];

    private static $cargado = false;

    // ------------------------------------------------------------------
    // Carga de datos (inyección de dependencias, ver nota arriba)
    // ------------------------------------------------------------------

    /**
     * @param array $personas    filas de la tabla personas
     * @param array $filiaciones filas de la tabla filiaciones
     * @param array $uniones     filas de la tabla uniones
     */
    public static function cargar(array $personas, array $filiaciones, array $uniones): void
    {
        self::$personas = [];
        foreach ($personas as $p) {
            self::$personas[(int)$p['id']] = $p;
        }

        self::$padresPorHijo = [];
        self::$hijosPorPadre = [];
        foreach ($filiaciones as $f) {
            $hijoId = (int)$f['persona_id'];
            $padreId = (int)$f['padre_madre_id'];
            self::$padresPorHijo[$hijoId][] = [
                'padre_id' => $padreId,
                'tipo' => $f['tipo_relacion'] ?? 'biologico',
            ];
            self::$hijosPorPadre[$padreId][] = $hijoId;
        }

        self::$unionesPorPersona = [];
        foreach ($uniones as $u) {
            $p1 = (int)$u['persona1_id'];
            $p2 = (int)$u['persona2_id'];
            self::$unionesPorPersona[$p1][] = $p2;
            self::$unionesPorPersona[$p2][] = $p1;
        }

        self::$cargado = true;
    }

    private static function verificarCargado(): void
    {
        if (!self::$cargado) {
            throw new \RuntimeException('FamiliaGrafo::cargar() debe llamarse antes de usar esta clase.');
        }
    }

    private static function genero(int $id): ?string
    {
        $g = self::$personas[$id]['genero'] ?? null;
        if ($g === 'M' || $g === 'F') {
            return $g;
        }
        return null;
    }

    private static function nombre(int $id): string
    {
        $p = self::$personas[$id] ?? null;
        if (!$p) {
            return "persona #$id";
        }
        return trim(($p['nombres'] ?? '') . ' ' . ($p['apellidos'] ?? ''));
    }

    // ------------------------------------------------------------------
    // BFS auxiliares
    // ------------------------------------------------------------------

    /** @return array<int,int> ancestro_id => distancia (1 = padre/madre, 2 = abuelo/a, ...) */
    private static function ancestrosConDistancia(int $id): array
    {
        $distancias = [];
        $visitado = [$id => true];
        $cola = [[$id, 0]];
        while ($cola) {
            [$actual, $dist] = array_shift($cola);
            foreach (self::$padresPorHijo[$actual] ?? [] as $p) {
                $pid = $p['padre_id'];
                if (!isset($visitado[$pid])) {
                    $visitado[$pid] = true;
                    $distancias[$pid] = $dist + 1;
                    $cola[] = [$pid, $dist + 1];
                }
            }
        }
        return $distancias;
    }

    /** @return array<int,int> descendiente_id => distancia (1 = hijo/a, 2 = nieto/a, ...) */
    private static function descendientesConDistancia(int $id): array
    {
        $distancias = [];
        $visitado = [$id => true];
        $cola = [[$id, 0]];
        while ($cola) {
            [$actual, $dist] = array_shift($cola);
            foreach (self::$hijosPorPadre[$actual] ?? [] as $hid) {
                if (!isset($visitado[$hid])) {
                    $visitado[$hid] = true;
                    $distancias[$hid] = $dist + 1;
                    $cola[] = [$hid, $dist + 1];
                }
            }
        }
        return $distancias;
    }

    /** Hermanos de sangre (comparten al menos un padre/madre), sin incluir a la persona misma. */
    private static function hermanosDe(int $id): array
    {
        $padres = array_column(self::$padresPorHijo[$id] ?? [], 'padre_id');
        $hermanos = [];
        foreach ($padres as $padreId) {
            foreach (self::$hijosPorPadre[$padreId] ?? [] as $hid) {
                if ($hid !== $id) {
                    $hermanos[$hid] = true;
                }
            }
        }
        return array_keys($hermanos);
    }

    // ------------------------------------------------------------------
    // 1) Subgrafo para el organigrama grande
    // ------------------------------------------------------------------

    /**
     * Arma el grafo relevante alrededor de $idRaiz: sus ascendientes, los
     * descendientes de esos ascendientes (lo que trae hermanos/tíos/primos
     * de regalo) y los cónyuges de todos, para dibujar un organigrama
     * doble (ascendientes arriba, descendientes abajo).
     *
     * @param int|null $generacionesArriba límite de generaciones hacia arriba (null = sin límite)
     * @param int|null $generacionesAbajo  límite de generaciones hacia abajo (null = sin límite)
     */
    public static function obtenerSubgrafo(int $idRaiz, ?int $generacionesArriba = null, ?int $generacionesAbajo = null): ?array
    {
        self::verificarCargado();
        if (!isset(self::$personas[$idRaiz])) {
            return null;
        }

        $ancestros = self::ancestrosConDistancia($idRaiz);

        $relevantes = [$idRaiz => true];
        foreach ($ancestros as $aid => $dist) {
            if ($generacionesArriba !== null && $dist > $generacionesArriba) {
                continue;
            }
            $relevantes[$aid] = true;
        }

        // Descendientes de la raíz y de cada ascendiente (trae hermanos, tíos, primos, sobrinos...)
        foreach (array_keys($relevantes) as $pid) {
            foreach (self::descendientesConDistancia($pid) as $did => $dist) {
                $relevantes[$did] = true;
            }
        }

        // Cónyuges de todos, solo para contexto visual (no aportan sangre)
        foreach (array_keys($relevantes) as $pid) {
            foreach (self::$unionesPorPersona[$pid] ?? [] as $cid) {
                $relevantes[$cid] = true;
            }
        }

        $generaciones = self::calcularGeneraciones($idRaiz, $relevantes);

        if ($generacionesArriba !== null || $generacionesAbajo !== null) {
            foreach ($generaciones as $id => $g) {
                if ($generacionesArriba !== null && $g < -$generacionesArriba) {
                    unset($relevantes[$id]);
                }
                if ($generacionesAbajo !== null && $g > $generacionesAbajo) {
                    unset($relevantes[$id]);
                }
            }
        }

        $nodos = [];
        foreach (array_keys($relevantes) as $id) {
            if (!isset(self::$personas[$id])) {
                continue;
            }
            $p = self::$personas[$id];
            $nodos[] = [
                'id' => $id,
                'nombre' => self::nombre($id),
                'genero' => $p['genero'] ?? null,
                'foto' => $p['foto'] ?? null,
                'vivo' => empty($p['fecha_fallecimiento']),
                'generacion' => $generaciones[$id] ?? 0,
                'esRaiz' => ($id === $idRaiz),
            ];
        }

        $aristasFiliacion = [];
        foreach (self::$padresPorHijo as $hijoId => $padres) {
            if (!isset($relevantes[$hijoId])) {
                continue;
            }
            foreach ($padres as $p) {
                if (isset($relevantes[$p['padre_id']])) {
                    $aristasFiliacion[] = [
                        'padre' => $p['padre_id'],
                        'hijo' => $hijoId,
                        'tipo' => $p['tipo'],
                    ];
                }
            }
        }

        $aristasUnion = [];
        $vistos = [];
        foreach (self::$unionesPorPersona as $pid => $parejas) {
            if (!isset($relevantes[$pid])) {
                continue;
            }
            foreach ($parejas as $cid) {
                if (!isset($relevantes[$cid])) {
                    continue;
                }
                $clave = $pid < $cid ? "$pid-$cid" : "$cid-$pid";
                if (isset($vistos[$clave])) {
                    continue;
                }
                $vistos[$clave] = true;
                $aristasUnion[] = ['persona1' => $pid, 'persona2' => $cid];
            }
        }

        return [
            'raiz' => $idRaiz,
            'nodos' => $nodos,
            'filiaciones' => $aristasFiliacion,
            'uniones' => $aristasUnion,
        ];
    }

    /**
     * Propaga generaciones relativas a $idRaiz (0) a través de TODO el subgrafo
     * relevante: padre = hijo - 1, hijo = padre + 1, cónyuge = misma generación.
     * Así hermanos/tíos/primos quedan alineados correctamente aunque no estén
     * en la línea directa de la raíz.
     *
     * @param array<int,true> $relevantes
     * @return array<int,int>
     */
    private static function calcularGeneraciones(int $idRaiz, array $relevantes): array
    {
        $gen = [$idRaiz => 0];
        $cola = [$idRaiz];
        while ($cola) {
            $actual = array_shift($cola);
            $g = $gen[$actual];

            foreach (self::$padresPorHijo[$actual] ?? [] as $p) {
                $pid = $p['padre_id'];
                if (isset($relevantes[$pid]) && !isset($gen[$pid])) {
                    $gen[$pid] = $g - 1;
                    $cola[] = $pid;
                }
            }
            foreach (self::$hijosPorPadre[$actual] ?? [] as $hid) {
                if (isset($relevantes[$hid]) && !isset($gen[$hid])) {
                    $gen[$hid] = $g + 1;
                    $cola[] = $hid;
                }
            }
            foreach (self::$unionesPorPersona[$actual] ?? [] as $cid) {
                if (isset($relevantes[$cid]) && !isset($gen[$cid])) {
                    $gen[$cid] = $g;
                    $cola[] = $cid;
                }
            }
        }
        return $gen;
    }

    // ------------------------------------------------------------------
    // 2) Parentesco entre dos personas
    // ------------------------------------------------------------------

    /**
     * Encuentra cómo están emparentados $idA y $idB.
     *
     * @return array{
     *   tipo: string,
     *   idA:int, idB:int,
     *   etiquetaGeneral:string,
     *   descripcionAB:?string, descripcionBA:?string,
     *   ancestrosComunes:int[],
     *   distanciaA:?int, distanciaB:?int,
     *   parentescoCompleto:?bool,
     *   ruta: array
     * }
     */
    public static function buscarParentesco(int $idA, int $idB): array
    {
        self::verificarCargado();

        if ($idA === $idB) {
            return [
                'tipo' => 'misma_persona',
                'idA' => $idA,
                'idB' => $idB,
                'etiquetaGeneral' => 'la misma persona',
                'descripcionAB' => null,
                'descripcionBA' => null,
                'ancestrosComunes' => [],
                'distanciaA' => 0,
                'distanciaB' => 0,
                'parentescoCompleto' => null,
                'ruta' => ['comun' => [$idA]],
            ];
        }

        $ancestrosA = self::ancestrosConDistancia($idA);
        $ancestrosB = self::ancestrosConDistancia($idB);

        // Caso especial: uno de los dos ES ancestro directo del otro (no aparece
        // en la intersección porque ancestrosX no se incluye a sí mismo).
        if (isset($ancestrosB[$idA])) {
            return self::armarResultadoDirecto($idA, $idB, 0, $ancestrosB[$idA], [$idA]);
        }
        if (isset($ancestrosA[$idB])) {
            return self::armarResultadoDirecto($idA, $idB, $ancestrosA[$idB], 0, [$idB]);
        }

        $comunes = array_intersect_key($ancestrosA, $ancestrosB);

        if (empty($comunes)) {
            return self::parentescoPolitico($idA, $idB);
        }

        $mejorSuma = null;
        foreach ($comunes as $id => $distA) {
            $suma = $distA + $ancestrosB[$id];
            if ($mejorSuma === null || $suma < $mejorSuma) {
                $mejorSuma = $suma;
            }
        }

        $ancestrosClave = [];
        foreach ($comunes as $id => $distA) {
            if ($distA + $ancestrosB[$id] === $mejorSuma) {
                $ancestrosClave[] = $id;
            }
        }

        $distA = $ancestrosA[$ancestrosClave[0]];
        $distB = $ancestrosB[$ancestrosClave[0]];

        // Si hay 2 ancestros clave a la misma distancia, casi siempre es una pareja
        // (comparten ambos abuelos/bisabuelos, etc.) => parentesco "completo".
        // Si solo hay 1, comparten un solo ancestro de ese nivel (p.ej. un solo abuelo) => "medio".
        $parentescoCompleto = count($ancestrosClave) >= 2;

        $etiqueta = self::nombrarParentesco($distA, $distB, self::genero($idA), self::genero($idB), $parentescoCompleto);

        $rutas = [];
        foreach ($ancestrosClave as $ancestroId) {
            $rutas[] = [
                'ancestro' => $ancestroId,
                'caminoA' => self::caminoHaciaAncestro($idA, $ancestroId, $ancestrosA),
                'caminoB' => self::caminoHaciaAncestro($idB, $ancestroId, $ancestrosB),
            ];
        }

        return [
            'tipo' => $etiqueta['tipo'],
            'idA' => $idA,
            'idB' => $idB,
            'etiquetaGeneral' => $etiqueta['etiquetaGeneral'],
            'descripcionAB' => $etiqueta['descripcionAB'],
            'descripcionBA' => $etiqueta['descripcionBA'],
            'ancestrosComunes' => $ancestrosClave,
            'distanciaA' => $distA,
            'distanciaB' => $distB,
            'parentescoCompleto' => $parentescoCompleto,
            'ruta' => $rutas,
        ];
    }

    /** Arma el resultado cuando $idA o $idB es ancestro directo del otro (distancia 0 de un lado). */
    private static function armarResultadoDirecto(int $idA, int $idB, int $distA, int $distB, array $ancestrosClave): array
    {
        $etiqueta = self::nombrarParentesco($distA, $distB, self::genero($idA), self::genero($idB), true);
        $ancestroId = $ancestrosClave[0];
        return [
            'tipo' => $etiqueta['tipo'],
            'idA' => $idA,
            'idB' => $idB,
            'etiquetaGeneral' => $etiqueta['etiquetaGeneral'],
            'descripcionAB' => $etiqueta['descripcionAB'],
            'descripcionBA' => $etiqueta['descripcionBA'],
            'ancestrosComunes' => $ancestrosClave,
            'distanciaA' => $distA,
            'distanciaB' => $distB,
            'parentescoCompleto' => true,
            'ruta' => [[
                'ancestro' => $ancestroId,
                'caminoA' => $distA === 0 ? [$idA] : self::caminoHaciaAncestro($idA, $ancestroId, self::ancestrosConDistancia($idA)),
                'caminoB' => $distB === 0 ? [$idB] : self::caminoHaciaAncestro($idB, $ancestroId, self::ancestrosConDistancia($idB)),
            ]],
        ];
    }

    /** Reconstruye el camino (lista de ids) de $id hacia $ancestroId subiendo por padres. */
    private static function caminoHaciaAncestro(int $id, int $ancestroId, array $distancias): array
    {
        $camino = [$id];
        $actual = $id;
        $distActual = 0;
        while ($actual !== $ancestroId) {
            $siguiente = null;
            foreach (self::$padresPorHijo[$actual] ?? [] as $p) {
                $pid = $p['padre_id'];
                if ($pid === $ancestroId || (($distancias[$pid] ?? null) === $distActual + 1)) {
                    $siguiente = $pid;
                    break;
                }
            }
            if ($siguiente === null) {
                break; // no debería pasar si ancestroId realmente es ancestro de id
            }
            $camino[] = $siguiente;
            $actual = $siguiente;
            $distActual++;
        }
        return $camino;
    }

    /**
     * Traduce (distanciaA, distanciaB) hasta el ancestro común a una etiqueta en español.
     */
    private static function nombrarParentesco(int $distA, int $distB, ?string $generoA, ?string $generoB, bool $completo): array
    {
        // --- Línea directa (uno es ancestro/descendiente directo del otro) ---
        if ($distA === 0 || $distB === 0) {
            // A es ancestro de B, o viceversa
            $ancestroEsA = $distA === 0;
            $nivel = $ancestroEsA ? $distB : $distA;
            $generoAncestro = $ancestroEsA ? $generoA : $generoB;
            $generoDescendiente = $ancestroEsA ? $generoB : $generoA;

            $palabraAsc = self::palabraGeneracion($nivel, $generoAncestro, true);
            $palabraDesc = self::palabraGeneracion($nivel, $generoDescendiente, false);

            return [
                'tipo' => 'linea_directa',
                'etiquetaGeneral' => "línea directa: $palabraAsc / $palabraDesc",
                'descripcionAB' => $ancestroEsA ? $palabraAsc : $palabraDesc,
                'descripcionBA' => $ancestroEsA ? $palabraDesc : $palabraAsc,
            ];
        }

        $min = min($distA, $distB);
        $max = max($distA, $distB);
        $aEsMenor = $distA <= $distB;

        // --- Hermanos ---
        if ($min === 1 && $max === 1) {
            $palabraA = ($generoA === 'F') ? 'hermana' : 'hermano';
            $palabraB = ($generoB === 'F') ? 'hermana' : 'hermano';
            $prefijo = $completo ? '' : 'medio(a) ';
            return [
                'tipo' => 'hermanos',
                'etiquetaGeneral' => $completo ? 'hermanos/as' : 'medios hermanos/as (comparten un solo padre o madre)',
                'descripcionAB' => $prefijo . $palabraB,
                'descripcionBA' => $prefijo . $palabraA,
            ];
        }

        // --- Tío/tía <-> sobrino/a (incluye tío abuelo, bisabuelo...) ---
        if ($min === 1 && $max >= 2) {
            $nivelExtra = $max - 2; // 0 = tío directo, 1 = tío abuelo, 2 = tío bisabuelo...
            $tioEsA = $distA < $distB;
            $generoTio = $tioEsA ? $generoA : $generoB;
            $generoSobrino = $tioEsA ? $generoB : $generoA;

            $palabraTio = ($generoTio === 'F') ? 'tía' : 'tío';
            $palabraSobrino = ($generoSobrino === 'F') ? 'sobrina' : 'sobrino';
            if ($nivelExtra > 0) {
                $palabraTio .= ' ' . self::sufijoAscendente($nivelExtra);
                $palabraSobrino .= ' ' . self::sufijoDescendente($nivelExtra);
            }
            $prefijo = $completo ? '' : 'medio(a) ';

            return [
                'tipo' => 'tio_sobrino',
                'etiquetaGeneral' => $prefijo . "$palabraTio / $palabraSobrino",
                'descripcionAB' => $prefijo . ($tioEsA ? $palabraTio : $palabraSobrino),
                'descripcionBA' => $prefijo . ($tioEsA ? $palabraSobrino : $palabraTio),
            ];
        }

        // --- Primos (min >= 2) ---
        $grado = $min - 1;       // 1 = primos hermanos, 2 = primos segundos, 3 = primos terceros...
        $removido = $max - $min; // 0 = sin remover, 1 = una vez removido, 2 = dos veces removido...

        $gradoTexto = self::gradoPrimoTexto($grado);
        $removidoTexto = self::removidoTexto($removido);
        $prefijo = $completo ? '' : 'medios ';

        $etiqueta = "{$prefijo}primos {$gradoTexto}{$removidoTexto}";

        return [
            'tipo' => 'primos',
            'etiquetaGeneral' => $etiqueta,
            'descripcionAB' => $etiqueta,
            'descripcionBA' => $etiqueta,
        ];
    }

    private static function palabraGeneracion(int $nivel, ?string $genero, bool $ascendente): string
    {
        $tablaAsc = [
            1 => ['M' => 'padre', 'F' => 'madre'],
            2 => ['M' => 'abuelo', 'F' => 'abuela'],
            3 => ['M' => 'bisabuelo', 'F' => 'bisabuela'],
            4 => ['M' => 'tatarabuelo', 'F' => 'tatarabuela'],
        ];
        $tablaDesc = [
            1 => ['M' => 'hijo', 'F' => 'hija'],
            2 => ['M' => 'nieto', 'F' => 'nieta'],
            3 => ['M' => 'bisnieto', 'F' => 'bisnieta'],
            4 => ['M' => 'tataranieto', 'F' => 'tataranieta'],
        ];
        $tabla = $ascendente ? $tablaAsc : $tablaDesc;
        $g = ($genero === 'F') ? 'F' : 'M'; // sin dato de género => forma masculina por defecto
        if (isset($tabla[$nivel])) {
            return $tabla[$nivel][$g];
        }
        return ($ascendente ? 'ascendiente' : 'descendiente') . " de {$nivel}ª generación";
    }

    private static function sufijoAscendente(int $nivelExtra): string
    {
        $tabla = [1 => 'abuelo', 2 => 'bisabuelo', 3 => 'tatarabuelo'];
        return $tabla[$nivelExtra] ?? "de nivel " . ($nivelExtra + 2);
    }

    private static function sufijoDescendente(int $nivelExtra): string
    {
        $tabla = [1 => 'nieto', 2 => 'bisnieto', 3 => 'tataranieto'];
        return $tabla[$nivelExtra] ?? "de nivel " . ($nivelExtra + 2);
    }

    private static function gradoPrimoTexto(int $grado): string
    {
        $tabla = [1 => 'hermanos', 2 => 'segundos', 3 => 'terceros', 4 => 'cuartos', 5 => 'quintos'];
        return $tabla[$grado] ?? "de grado $grado";
    }

    private static function removidoTexto(int $removido): string
    {
        if ($removido === 0) {
            return '';
        }
        $tabla = [1 => 'una vez removidos', 2 => 'dos veces removidos', 3 => 'tres veces removidos'];
        $texto = $tabla[$removido] ?? "$removido veces removidos";
        return ", $texto";
    }

    /**
     * No hay ancestro de sangre en común: revisa relaciones "políticas" básicas
     * (cónyuges, suegro/yerno, cuñados). Si no encuentra nada, regresa "sin parentesco".
     */
    private static function parentescoPolitico(int $idA, int $idB): array
    {
        $base = [
            'idA' => $idA,
            'idB' => $idB,
            'ancestrosComunes' => [],
            'distanciaA' => null,
            'distanciaB' => null,
            'parentescoCompleto' => null,
            'ruta' => [],
        ];

        // Cónyuges directos
        if (in_array($idB, self::$unionesPorPersona[$idA] ?? [], true)) {
            return $base + [
                'tipo' => 'conyuges',
                'etiquetaGeneral' => 'cónyuges / pareja',
                'descripcionAB' => 'cónyuge',
                'descripcionBA' => 'cónyuge',
            ];
        }

        // Suegro/a <-> yerno/nuera: padres de la pareja de A (B es el suegro/a de A)
        foreach (self::$unionesPorPersona[$idA] ?? [] as $parejaA) {
            $padresParejaA = array_column(self::$padresPorHijo[$parejaA] ?? [], 'padre_id');
            if (in_array($idB, $padresParejaA, true)) {
                $suegro = (self::genero($idB) === 'F') ? 'suegra' : 'suegro';
                $yerno = (self::genero($idA) === 'F') ? 'nuera' : 'yerno';
                return $base + [
                    'tipo' => 'suegro_yerno',
                    'etiquetaGeneral' => "$suegro / $yerno",
                    'descripcionAB' => $yerno,
                    'descripcionBA' => $suegro,
                ];
            }
        }
        // Mismo chequeo al revés (B es quien tiene la pareja, A es el suegro/a de esa pareja)
        foreach (self::$unionesPorPersona[$idB] ?? [] as $parejaB) {
            $padresParejaB = array_column(self::$padresPorHijo[$parejaB] ?? [], 'padre_id');
            if (in_array($idA, $padresParejaB, true)) {
                $suegro = (self::genero($idA) === 'F') ? 'suegra' : 'suegro';
                $yerno = (self::genero($idB) === 'F') ? 'nuera' : 'yerno';
                return $base + [
                    'tipo' => 'suegro_yerno',
                    'etiquetaGeneral' => "$suegro / $yerno",
                    'descripcionAB' => $suegro,
                    'descripcionBA' => $yerno,
                ];
            }
        }

        // Cuñados/as: hermano/a de la pareja, o pareja de un hermano/a
        $hermanosA = self::hermanosDe($idA);
        foreach (self::$unionesPorPersona[$idA] ?? [] as $parejaA) {
            if (in_array($idB, self::hermanosDe($parejaA), true)) {
                $cA = (self::genero($idA) === 'F') ? 'cuñada' : 'cuñado';
                $cB = (self::genero($idB) === 'F') ? 'cuñada' : 'cuñado';
                return $base + [
                    'tipo' => 'cunados',
                    'etiquetaGeneral' => 'cuñados/as',
                    'descripcionAB' => $cB,
                    'descripcionBA' => $cA,
                ];
            }
        }
        foreach (self::$unionesPorPersona[$idB] ?? [] as $parejaB) {
            if (in_array($parejaB, $hermanosA, true)) {
                $cA = (self::genero($idA) === 'F') ? 'cuñada' : 'cuñado';
                $cB = (self::genero($idB) === 'F') ? 'cuñada' : 'cuñado';
                return $base + [
                    'tipo' => 'cunados',
                    'etiquetaGeneral' => 'cuñados/as',
                    'descripcionAB' => $cB,
                    'descripcionBA' => $cA,
                ];
            }
        }

        return $base + [
            'tipo' => 'sin_parentesco',
            'etiquetaGeneral' => 'no se encontró parentesco entre estas dos personas en el árbol registrado',
            'descripcionAB' => null,
            'descripcionBA' => null,
        ];
    }
}
