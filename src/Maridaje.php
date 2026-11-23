<?php

declare(strict_types=1);

namespace WineMatch;

use PDO;

/**
 * El corazon de WineMatch: dado un plato (y el tipo de vino que prefiera el
 * comensal) devolver la recomendacion mas ajustada. Si no hay regla exacta,
 * degrada con criterio: mismo plato -> misma especie aromatica -> cualquiera.
 */
final class Maridaje
{
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

    /** @return list<array{id: int, nombre: string, bodega: string}> */
    public static function vinos(PDO $pdo): array
    {
        return $pdo->query('SELECT id, nombre, bodega FROM Vino ORDER BY nombre')->fetchAll() ?: [];
    }

    /** @return list<array<string, mixed>> platos de la carta con su especie aromatica */
    public static function carta(PDO $pdo, int $restauranteId): array
    {
        $stmt = $pdo->prepare(
            'SELECT c.id, c.nombre, c.slug, c.descripcion, e.nombre AS especie
             FROM Comida c JOIN Especie e ON e.id = c.especie_id
             WHERE c.restaurante_id = ? ORDER BY c.id'
        );
        $stmt->execute([$restauranteId]);
        return $stmt->fetchAll() ?: [];
    }

    /** @return array<string, mixed>|null plato + especie + restaurante */
    public static function platoPorSlug(PDO $pdo, string $restauranteSlug, string $platoSlug): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT c.id, c.nombre, c.slug, c.descripcion, e.id AS especie_id, e.nombre AS especie,
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

    /**
     * Recomienda un vino para el plato.
     * @return array{recomendacion_id: int, mensaje: string, exacta: bool, motivo: string, vino: array<string, mixed>}
     */
    public static function recomendar(PDO $pdo, array $plato, ?int $tipoId): array
    {
        $campos = 'r.id AS recomendacion_id, r.mensaje, r.especie_id, r.comida_id,
                   v.id, v.nombre, v.bodega, v.botella, v.variedad, v.descripcion,
                   t.nombre AS tipo, t.id AS tipo_id';
        $joins = 'FROM Recomendacion r
                  JOIN Vino v ON v.id = r.vino_id
                  JOIN TipoVino t ON t.id = v.tipo_id';
        $filtroTipo = $tipoId !== null ? ' AND v.tipo_id = ?' : '';
        $paramsTipo = $tipoId !== null ? [$tipoId] : [];

        // 1. Regla exacta: este plato, con su especie, y el tipo de vino pedido.
        $candidatas = self::buscar($pdo, "SELECT {$campos} {$joins} WHERE r.comida_id = ? AND r.especie_id = ?{$filtroTipo}", [$plato['id'], $plato['especie_id'], ...$paramsTipo]);
        if ($candidatas !== []) {
            return self::empaquetar(self::unoDe($candidatas), true, 'Regla de la casa para este plato.');
        }

        // 2. Misma especie aromatica en otro plato (con el tipo pedido si lo hay).
        $candidatas = self::buscar($pdo, "SELECT {$campos} {$joins} JOIN Comida c ON c.id = r.comida_id WHERE r.especie_id = ?{$filtroTipo}", [$plato['especie_id'], ...$paramsTipo]);
        if ($candidatas !== []) {
            return self::empaquetar(self::unoDe($candidatas), false, "No hay regla propia para este plato; por su perfil aromático de {$plato['especie']}, te sugiero el maridaje pensado para un plato parecido.");
        }

        // 3. Sin filtro de tipo: misma especie aromatica.
        if ($tipoId !== null) {
            $candidatas = self::buscar($pdo, "SELECT {$campos} {$joins} WHERE r.especie_id = ?", [$plato['especie_id']]);
            if ($candidatas !== []) {
                return self::empaquetar(self::unoDe($candidatas), false, "No hay {$plato['especie']} en el tipo de vino que pediste; para ese perfil, sin filtrar por tipo, te sugiero:");
            }
        }

        // 4. Cualquier regla de la casa.
        $candidatas = self::buscar($pdo, "SELECT {$campos} {$joins} ORDER BY r.id", []);
        if ($candidatas === []) {
            throw new \RuntimeException('No hay recomendaciones cargadas');
        }
        return self::empaquetar(self::unoDe($candidatas), false, 'Aún no tenemos una regla para este perfil; esta es una sugerencia de la casa para empezar.');
    }

    public static function registrarConsulta(PDO $pdo, int $usuarioId, int $recomendacionId): void
    {
        $pdo->prepare('INSERT INTO Consulta (usuario_id, recomendacion_id) VALUES (?, ?)')->execute([$usuarioId, $recomendacionId]);
    }

    /**
     * El comensal elige el vino y el sistema dice si hace match con el plato.
     * @return array{vino: array<string, mixed>, veredicto: string, titulo: string, mensaje: string, sugerencia: array<string, mixed>}
     */
    public static function comprobar(PDO $pdo, array $plato, int $vinoId): array
    {
        $stmt = $pdo->prepare('SELECT id, nombre, bodega, botella, variedad, descripcion, tipo_id FROM Vino WHERE id = ?');
        $stmt->execute([$vinoId]);
        $vino = $stmt->fetch();
        if ($vino === false) {
            throw new \InvalidArgumentException('Ese vino no existe.');
        }
        $sugerencia = self::recomendar($pdo, $plato, null);

        // 1. Regla de la casa para este vino con este plato.
        $stmt = $pdo->prepare('SELECT mensaje FROM Recomendacion WHERE vino_id = ? AND comida_id = ?');
        $stmt->execute([$vinoId, $plato['id']]);
        $mensaje = $stmt->fetchColumn();
        if ($mensaje !== false) {
            return self::veredicto($vino, 'exacto', 'Match de la casa', (string) $mensaje, $sugerencia);
        }

        // 2. Ese vino ya marida otro plato con la misma especie aromatica.
        $stmt = $pdo->prepare(
            'SELECT r.mensaje, c.nombre AS otro_plato
             FROM Recomendacion r JOIN Comida c ON c.id = r.comida_id
             WHERE r.vino_id = ? AND r.especie_id = ? AND r.comida_id <> ?
             ORDER BY r.id LIMIT 1'
        );
        $stmt->execute([$vinoId, $plato['especie_id'], $plato['id']]);
        $parecido = $stmt->fetch();
        if ($parecido !== false) {
            $texto = sprintf(
                'No hay regla propia para %s, pero este vino ya es el maridaje de %s, que comparte su perfil de %s. %s',
                $plato['nombre'],
                $parecido['otro_plato'],
                $plato['especie'],
                $parecido['mensaje']
            );
            return self::veredicto($vino, 'parecido', 'Compatible por perfil', $texto, $sugerencia);
        }

        // 3. Sin regla: se dice con honestidad y se ofrece la sugerencia de la casa.
        $texto = sprintf('No tenemos %s definido para %s. Si te fías de la casa, esto es lo que sí tenemos:', $vino['nombre'], $plato['nombre']);
        return self::veredicto($vino, 'sin_regla', 'Sin regla todavía', $texto, $sugerencia);
    }

    /** @param array<string, mixed> $vino @param array<string, mixed> $sugerencia */
    private static function veredicto(array $vino, string $veredicto, string $titulo, string $mensaje, array $sugerencia): array
    {
        return [
            'vino' => ['id' => (int) $vino['id'], 'nombre' => $vino['nombre'], 'bodega' => $vino['bodega'], 'variedad' => $vino['variedad']],
            'veredicto' => $veredicto,
            'titulo' => $titulo,
            'mensaje' => $mensaje,
            'sugerencia' => $sugerencia,
        ];
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
            'exacta' => $exacta,
            'motivo' => $motivo,
            'vino' => [
                'id' => (int) $fila['id'],
                'nombre' => $fila['nombre'],
                'bodega' => $fila['bodega'],
                'botella' => $fila['botella'],
                'variedad' => $fila['variedad'],
                'descripcion' => $fila['descripcion'],
                'tipo' => $fila['tipo'],
                'tipo_id' => (int) $fila['tipo_id'],
            ],
        ];
    }

    /** @param list<mixed> $params @return list<array<string, mixed>> */
    private static function buscar(PDO $pdo, string $sql, array $params): array
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }
}
