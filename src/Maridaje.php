<?php

declare(strict_types=1);

namespace WineMatch;

use PDO;

/**
 * El corazon de WineMatch: dado un plato (y el tipo de vino que prefiera el
 * comensal) devolver la recomendacion mas ajustada. Si no hay regla exacta,
 * degrada con criterio: misma especia en otro plato -> sin filtrar -> cualquiera.
 */
final class Maridaje
{
    public const TIPOS_PLATO = ['Primero', 'Segundo', 'Postre'];

    /** @return list<array{id: int, nombre: string, slug: string, descripcion: string}> */
    public static function restaurantes(PDO $pdo): array
    {
        return $pdo->query('SELECT id, nombre, slug, descripcion FROM Restaurante ORDER BY nombre')->fetchAll() ?: [];
    }

    /** @return array<string, mixed>|null */
    public static function restaurantePorSlug(PDO $pdo, string $slug): ?array
    {
        $stmt = $pdo->prepare('SELECT id, nombre, slug, descripcion FROM Restaurante WHERE slug = ?');
        $stmt->execute([$slug]);
        $r = $stmt->fetch();
        return $r === false ? null : $r;
    }

    /** @return list<array<string, mixed>> platos de la carta con su especia, ordenados por tipo */
    public static function carta(PDO $pdo, int $restauranteId): array
    {
        $stmt = $pdo->prepare(
            "SELECT c.id, c.nombre, c.slug, c.tipo, c.descripcion, e.id AS especie_id, e.nombre AS especie
             FROM Comida c JOIN Especie e ON e.id = c.especie_id
             WHERE c.restaurante_id = ?
             ORDER BY CASE c.tipo WHEN 'Primero' THEN 1 WHEN 'Segundo' THEN 2 ELSE 3 END, c.id"
        );
        $stmt->execute([$restauranteId]);
        return $stmt->fetchAll() ?: [];
    }

    /** @return array<string, mixed>|null plato + especia + restaurante */
    public static function platoPorSlug(PDO $pdo, string $restauranteSlug, string $platoSlug): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT c.id, c.nombre, c.slug, c.tipo, c.descripcion, e.id AS especie_id, e.nombre AS especie,
                    r.id AS restaurante_id, r.nombre AS restaurante, r.slug AS restaurante_slug
             FROM Comida c
             JOIN Especie e ON e.id = c.especie_id
             JOIN Restaurante r ON r.id = c.restaurante_id
             WHERE r.slug = ? AND c.slug = ?'
        );
        $stmt->execute([$restauranteSlug, $platoSlug]);
        $p = $stmt->fetch();
        return $p === false ? null : $p;
    }

    /** @return list<array{id: int, nombre: string}> */
    public static function tiposVino(PDO $pdo): array
    {
        return $pdo->query('SELECT id, nombre FROM TipoVino ORDER BY id')->fetchAll() ?: [];
    }

    /** @return list<array{id: int, nombre: string, variedad: string, denominacion_origen: string, tipo_id: int}> */
    public static function vinos(PDO $pdo): array
    {
        return $pdo->query('SELECT id, nombre, variedad, denominacion_origen, tipo_id FROM Vino ORDER BY nombre')->fetchAll() ?: [];
    }

    /**
     * Recomienda un vino para el plato.
     * @return array{recomendacion_id: int, mensaje: string, bodega: string, botella: string, exacta: bool, motivo: string, vino: array<string, mixed>}
     */
    public static function recomendar(PDO $pdo, array $plato, ?int $tipoId): array
    {
        $candidatas = self::candidatas($pdo, "r.comida_id = ? AND r.especie_id = ?", [$plato['id'], $plato['especie_id']], $tipoId);
        if ($candidatas !== []) {
            return self::empaquetar(self::unoDe($candidatas), true, 'Maridaje de la casa para este plato.');
        }

        // Misma especia en otro plato (con el tipo de vino pedido, si lo hay).
        $candidatas = self::candidatas($pdo, 'r.especie_id = ?', [$plato['especie_id']], $tipoId);
        if ($candidatas !== []) {
            return self::empaquetar(self::unoDe($candidatas), false, "No hay maridaje propio para este plato; por su toque de {$plato['especie']}, te sugiero el pensado para un plato parecido.");
        }

        // Con tipo de vino pedido y nada que lo cumpla: misma especia sin filtrar.
        if ($tipoId !== null) {
            $candidatas = self::candidatas($pdo, 'r.especie_id = ?', [$plato['especie_id']], null);
            if ($candidatas !== []) {
                return self::empaquetar(self::unoDe($candidatas), false, "No hay {$plato['especie']} en el tipo de vino que pediste; para ese toque, sin filtrar por tipo, te sugiero:");
            }
        }

        // Cualquier maridaje de la casa.
        $candidatas = self::candidatas($pdo, '1 = 1', [], null);
        if ($candidatas === []) {
            throw new \RuntimeException('No hay maridajes cargados');
        }
        return self::empaquetar(self::unoDe($candidatas), false, 'Aún no tenemos un maridaje para este perfil; esta es una sugerencia de la casa para empezar.');
    }

    /**
     * El comensal elige el vino y el sistema dice si hace match con el plato.
     * @return array{vino: array<string, mixed>, veredicto: string, titulo: string, mensaje: string, sugerencia: array<string, mixed>}
     */
    public static function comprobar(PDO $pdo, array $plato, int $vinoId): array
    {
        $stmt = $pdo->prepare('SELECT v.id, v.nombre, v.variedad, v.denominacion_origen, v.tipo_id, t.nombre AS tipo
                               FROM Vino v JOIN TipoVino t ON t.id = v.tipo_id WHERE v.id = ?');
        $stmt->execute([$vinoId]);
        $vino = $stmt->fetch();
        if ($vino === false) {
            throw new \InvalidArgumentException('Ese vino no existe.');
        }
        $sugerencia = self::recomendar($pdo, $plato, null);

        // 1. Maridaje de la casa para este vino con este plato.
        $stmt = $pdo->prepare('SELECT id, mensaje, nombre_bodega, nombre_botella FROM Recomendacion WHERE vino_id = ? AND comida_id = ?');
        $stmt->execute([$vinoId, $plato['id']]);
        $regla = $stmt->fetch();
        if ($regla !== false) {
            $texto = sprintf('%s (%s, %s). %s', $regla['nombre_botella'], $regla['nombre_bodega'], $plato['especie'], $regla['mensaje']);
            return self::veredicto($vino, 'exacto', 'Match de la casa', $texto, $sugerencia, $regla);
        }

        // 2. Ese vino ya marida otro plato con la misma especia.
        $stmt = $pdo->prepare(
            'SELECT r.id, r.mensaje, r.nombre_bodega, r.nombre_botella, c.nombre AS otro_plato
             FROM Recomendacion r JOIN Comida c ON c.id = r.comida_id
             WHERE r.vino_id = ? AND r.especie_id = ? AND r.comida_id <> ?
             ORDER BY r.id LIMIT 1'
        );
        $stmt->execute([$vinoId, $plato['especie_id'], $plato['id']]);
        $parecido = $stmt->fetch();
        if ($parecido !== false) {
            $texto = sprintf(
                'No hay maridaje propio para %s, pero este vino ya es el maridaje de %s, que comparte su toque de %s. %s',
                $plato['nombre'], $parecido['otro_plato'], $plato['especie'], $parecido['mensaje']
            );
            return self::veredicto($vino, 'parecido', 'Compatible por toque', $texto, $sugerencia, $parecido);
        }

        // 3. Sin regla: se dice con honestidad y se ofrece la sugerencia de la casa.
        $texto = sprintf('No tenemos %s definido para %s. Si te fías de la casa, esto es lo que sí tenemos:', $vino['nombre'], $plato['nombre']);
        return self::veredicto($vino, 'sin_regla', 'Sin maridaje todavía', $texto, $sugerencia, null);
    }

    public static function registrarConsulta(PDO $pdo, int $usuarioId, int $recomendacionId): void
    {
        $pdo->prepare('INSERT INTO Consulta (usuario_id, recomendacion_id) VALUES (?, ?)')->execute([$usuarioId, $recomendacionId]);
    }

    /** Opiniones del vino: media, total y comentarios. @return array{media: float|null, total: int, comentarios: list<array<string, mixed>>} */
    public static function opiniones(PDO $pdo, int $vinoId): array
    {
        $stmt = $pdo->prepare('SELECT AVG(puntuacion) AS media, COUNT(*) AS total FROM Valoracion WHERE vino_id = ?');
        $stmt->execute([$vinoId]);
        $resumen = $stmt->fetch() ?: ['media' => null, 'total' => 0];
        $stmt = $pdo->prepare(
            'SELECT u.nombre, c.comentario, c.fecha FROM Comentario c JOIN Usuarios u ON u.id = c.usuario_id
             WHERE c.vino_id = ? ORDER BY c.fecha DESC, c.id DESC'
        );
        $stmt->execute([$vinoId]);
        return [
            'media' => $resumen['media'] === null ? null : round((float) $resumen['media'], 1),
            'total' => (int) $resumen['total'],
            'comentarios' => $stmt->fetchAll() ?: [],
        ];
    }

    /** @param list<mixed> $params @return list<array<string, mixed>> */
    private static function candidatas(PDO $pdo, string $condicion, array $params, ?int $tipoId): array
    {
        $sql = 'SELECT r.id AS recomendacion_id, r.mensaje, r.nombre_bodega, r.nombre_botella,
                       v.id, v.nombre, v.variedad, v.denominacion_origen,
                       t.nombre AS tipo, t.id AS tipo_id
                FROM Recomendacion r
                JOIN Vino v ON v.id = r.vino_id
                JOIN TipoVino t ON t.id = v.tipo_id
                WHERE ' . $condicion . ($tipoId !== null ? ' AND v.tipo_id = ?' : '') . '
                ORDER BY r.id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($tipoId !== null ? [...$params, $tipoId] : $params);
        return $stmt->fetchAll() ?: [];
    }

    /** @param list<array<string, mixed>> $candidatas */
    private static function unoDe(array $candidatas): array
    {
        return $candidatas[random_int(0, count($candidatas) - 1)];
    }

    /** @param array<string, mixed> $fila */
    private static function empaquetar(array $fila, bool $exacta, string $motivo): array
    {
        return [
            'recomendacion_id' => (int) $fila['recomendacion_id'],
            'mensaje' => $fila['mensaje'],
            'bodega' => $fila['nombre_bodega'],
            'botella' => $fila['nombre_botella'],
            'exacta' => $exacta,
            'motivo' => $motivo,
            'vino' => self::datosVino($fila),
        ];
    }

    /** @param array<string, mixed> $fila @param array<string, mixed>|null $regla */
    private static function veredicto(array $vino, string $veredicto, string $titulo, string $mensaje, array $sugerencia, ?array $regla): array
    {
        return [
            'vino' => self::datosVino($vino),
            'veredicto' => $veredicto,
            'titulo' => $titulo,
            'mensaje' => $mensaje,
            'sugerencia' => $sugerencia,
            'regla' => $regla,
        ];
    }

    /** @param array<string, mixed> $fila */
    private static function datosVino(array $fila): array
    {
        return [
            'id' => (int) $fila['id'],
            'nombre' => $fila['nombre'],
            'variedad' => $fila['variedad'],
            'denominacion_origen' => $fila['denominacion_origen'],
            'tipo' => $fila['tipo'],
            'tipo_id' => (int) $fila['tipo_id'],
        ];
    }
}
