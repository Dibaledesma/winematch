<?php

declare(strict_types=1);

namespace WineMatch;

use PDO;

/** Panel de administracion: usuarios, opiniones y carga de carta (platos y reglas de maridaje). */
final class Admin
{
    /** @return list<array<string, mixed>> */
    public static function usuarios(PDO $pdo): array
    {
        return $pdo->query(
            "SELECT u.id, u.nombre, u.email, u.rol, u.creado_en,
                    (SELECT COUNT(*) FROM Consulta c WHERE c.usuario_id = u.id) AS consultas,
                    (SELECT COUNT(*) FROM Valoracion v WHERE v.usuario_id = u.id) AS valoraciones
             FROM Usuarios u ORDER BY u.id DESC"
        )->fetchAll() ?: [];
    }

    /** @return list<array<string, mixed>> los ultimos comentarios con su vino y autor */
    public static function comentarios(PDO $pdo, int $limite = 30): array
    {
        $stmt = $pdo->prepare(
            'SELECT c.id, c.comentario, c.fecha, u.nombre AS usuario, v.nombre AS vino, v.id AS vino_id
             FROM Comentario c JOIN Usuarios u ON u.id = c.usuario_id JOIN Vino v ON v.id = c.vino_id
             ORDER BY c.id DESC LIMIT ?'
        );
        $stmt->bindValue(1, max(1, min(200, $limite)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /** @return list<array<string, mixed>> resumen de valoraciones por vino */
    public static function valoraciones(PDO $pdo): array
    {
        return $pdo->query(
            'SELECT v.id, v.nombre, v.bodega, COUNT(x.id) AS total, ROUND(AVG(x.puntuacion), 1) AS media
             FROM Vino v LEFT JOIN Valoracion x ON x.vino_id = v.id
             GROUP BY v.id ORDER BY media DESC, v.nombre'
        )->fetchAll() ?: [];
    }

    /** @return list<array<string, mixed>> */
    public static function vinos(PDO $pdo): array
    {
        return $pdo->query('SELECT id, nombre, bodega, tipo_id FROM Vino ORDER BY nombre')->fetchAll() ?: [];
    }

    /** @return list<array<string, mixed>> */
    public static function especies(PDO $pdo): array
    {
        return $pdo->query('SELECT id, nombre FROM Especie ORDER BY nombre')->fetchAll() ?: [];
    }

    /** @return list<array<string, mixed>> */
    public static function platos(PDO $pdo): array
    {
        return $pdo->query(
            'SELECT c.id, c.nombre, c.slug, r.nombre AS restaurante, e.nombre AS especie,
                    (SELECT COUNT(*) FROM Recomendacion x WHERE x.comida_id = c.id) AS reglas
             FROM Comida c JOIN Restaurante r ON r.id = c.restaurante_id JOIN Especie e ON e.id = c.especie_id
             ORDER BY r.nombre, c.id'
        )->fetchAll() ?: [];
    }

    /** Da de alta un plato en la carta. @throws \InvalidArgumentException mensaje mostrable */
    public static function crearPlato(PDO $pdo, int $restauranteId, string $nombre, string $descripcion, int $especieId): int
    {
        $nombre = trim($nombre);
        $slug = Texto::slug($nombre);
        if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 160 || $slug === '') {
            throw new \InvalidArgumentException('El nombre del plato debe tener entre 2 y 160 caracteres y generar una URL.');
        }
        $stmt = $pdo->prepare('SELECT 1 FROM Restaurante WHERE id = ?');
        $stmt->execute([$restauranteId]);
        if ($stmt->fetchColumn() === false) {
            throw new \InvalidArgumentException('Restaurante no válido.');
        }
        $stmt = $pdo->prepare('SELECT 1 FROM Especie WHERE id = ?');
        $stmt->execute([$especieId]);
        if ($stmt->fetchColumn() === false) {
            throw new \InvalidArgumentException('Especie aromática no válida.');
        }
        $stmt = $pdo->prepare('INSERT INTO Comida (restaurante_id, nombre, slug, descripcion, especie_id) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$restauranteId, $nombre, $slug, trim($descripcion), $especieId]);
        return (int) $pdo->lastInsertId();
    }

    /** Crea una regla de maridaje (la especie es la del plato). @throws \InvalidArgumentException */
    public static function crearRecomendacion(PDO $pdo, int $vinoId, int $comidaId, string $mensaje): int
    {
        $mensaje = trim($mensaje);
        if ($mensaje === '' || mb_strlen($mensaje) > 400) {
            throw new \InvalidArgumentException('El mensaje de maridaje debe tener entre 1 y 400 caracteres.');
        }
        $stmt = $pdo->prepare('SELECT especie_id FROM Comida WHERE id = ?');
        $stmt->execute([$comidaId]);
        $especie = $stmt->fetchColumn();
        if ($especie === false) {
            throw new \InvalidArgumentException('Plato no válido.');
        }
        $stmt = $pdo->prepare('SELECT 1 FROM Vino WHERE id = ?');
        $stmt->execute([$vinoId]);
        if ($stmt->fetchColumn() === false) {
            throw new \InvalidArgumentException('Vino no válido.');
        }
        try {
            $pdo->prepare('INSERT INTO Recomendacion (vino_id, especie_id, comida_id, mensaje) VALUES (?, ?, ?, ?)')
                ->execute([$vinoId, (int) $especie, $comidaId, $mensaje]);
        } catch (\PDOException $e) {
            if (($e->errorInfo[1] ?? null) === 19 || str_contains($e->getMessage(), 'Duplicate')) {
                throw new \InvalidArgumentException('Ya existe una regla de maridaje para ese vino y ese plato.');
            }
            throw $e;
        }
        return (int) $pdo->lastInsertId();
    }
}
