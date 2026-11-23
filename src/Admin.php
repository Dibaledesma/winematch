<?php

declare(strict_types=1);

namespace WineMatch;

use PDO;

/** Panel de administracion: usuarios, opiniones y carga de carta (especias, platos y maridajes). */
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
            'SELECT v.id, v.nombre, v.denominacion_origen, COUNT(x.id) AS total, ROUND(AVG(x.puntuacion), 1) AS media
             FROM Vino v LEFT JOIN Valoracion x ON x.vino_id = v.id
             GROUP BY v.id ORDER BY media DESC, v.nombre'
        )->fetchAll() ?: [];
    }

    /** @return list<array{id: int, nombre: string, variedad: string, denominacion_origen: string, tipo_id: int}> */
    public static function vinos(PDO $pdo): array
    {
        return Maridaje::vinos($pdo);
    }

    /** @return list<array{id: int, nombre: string, restaurante_id: int, restaurante: string}> especias por restaurante */
    public static function especies(PDO $pdo): array
    {
        return $pdo->query(
            'SELECT e.id, e.nombre, e.restaurante_id, r.nombre AS restaurante
             FROM Especie e JOIN Restaurante r ON r.id = e.restaurante_id
             ORDER BY r.nombre, e.nombre'
        )->fetchAll() ?: [];
    }

    /** @return list<array<string, mixed>> */
    public static function platos(PDO $pdo): array
    {
        return $pdo->query(
            'SELECT c.id, c.nombre, c.slug, c.tipo, r.nombre AS restaurante, e.nombre AS especie,
                    (SELECT COUNT(*) FROM Recomendacion x WHERE x.comida_id = c.id) AS maridajes
             FROM Comida c JOIN Restaurante r ON r.id = c.restaurante_id JOIN Especie e ON e.id = c.especie_id
             ORDER BY r.nombre, c.id'
        )->fetchAll() ?: [];
    }

    /** Da de alta una especia en la carta de un restaurante. @throws \InvalidArgumentException */
    public static function crearEspecie(PDO $pdo, int $restauranteId, string $nombre): int
    {
        $nombre = trim($nombre);
        if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 120) {
            throw new \InvalidArgumentException('El nombre de la especia debe tener entre 2 y 120 caracteres.');
        }
        self::existe($pdo, 'Restaurante', $restauranteId, 'Restaurante no válido.');
        $stmt = $pdo->prepare('SELECT 1 FROM Especie WHERE restaurante_id = ? AND nombre = ?');
        $stmt->execute([$restauranteId, $nombre]);
        if ($stmt->fetchColumn() !== false) {
            throw new \InvalidArgumentException('Esa especia ya existe en este restaurante.');
        }
        $pdo->prepare('INSERT INTO Especie (restaurante_id, nombre) VALUES (?, ?)')->execute([$restauranteId, $nombre]);
        return (int) $pdo->lastInsertId();
    }

    /** Da de alta un plato en la carta. La especia debe ser del mismo restaurante. @throws \InvalidArgumentException */
    public static function crearPlato(PDO $pdo, int $restauranteId, string $nombre, string $tipo, string $descripcion, int $especieId): int
    {
        $nombre = trim($nombre);
        $slug = Texto::slug($nombre);
        if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 160 || $slug === '') {
            throw new \InvalidArgumentException('El nombre del plato debe tener entre 2 y 160 caracteres y generar una URL.');
        }
        if (!in_array($tipo, Maridaje::TIPOS_PLATO, true)) {
            throw new \InvalidArgumentException('El tipo de plato debe ser Primero, Segundo o Postre.');
        }
        self::existe($pdo, 'Restaurante', $restauranteId, 'Restaurante no válido.');
        $stmt = $pdo->prepare('SELECT restaurante_id FROM Especie WHERE id = ?');
        $stmt->execute([$especieId]);
        $de = $stmt->fetchColumn();
        if ($de === false) {
            throw new \InvalidArgumentException('Especia no válida.');
        }
        if ((int) $de !== $restauranteId) {
            throw new \InvalidArgumentException('Esa especia no es del restaurante del plato.');
        }
        $stmt = $pdo->prepare('INSERT INTO Comida (restaurante_id, nombre, slug, tipo, descripcion, especie_id) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$restauranteId, $nombre, $slug, $tipo, trim($descripcion), $especieId]);
        return (int) $pdo->lastInsertId();
    }

    /** Crea un maridaje de la casa (la especia es la del plato). @throws \InvalidArgumentException */
    public static function crearRecomendacion(PDO $pdo, int $vinoId, int $comidaId, string $bodega, string $botella, string $mensaje): int
    {
        $bodega = trim($bodega);
        $botella = trim($botella);
        $mensaje = trim($mensaje);
        if ($bodega === '' || mb_strlen($bodega) > 200 || $botella === '' || mb_strlen($botella) > 200) {
            throw new \InvalidArgumentException('Indica la bodega y la botella (hasta 200 caracteres cada una).');
        }
        if ($mensaje === '' || mb_strlen($mensaje) > 400) {
            throw new \InvalidArgumentException('El mensaje de maridaje debe tener entre 1 y 400 caracteres.');
        }
        $stmt = $pdo->prepare('SELECT especie_id FROM Comida WHERE id = ?');
        $stmt->execute([$comidaId]);
        $especie = $stmt->fetchColumn();
        if ($especie === false) {
            throw new \InvalidArgumentException('Plato no válido.');
        }
        self::existe($pdo, 'Vino', $vinoId, 'Vino no válido.');
        try {
            $pdo->prepare('INSERT INTO Recomendacion (vino_id, especie_id, comida_id, nombre_bodega, nombre_botella, mensaje) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$vinoId, (int) $especie, $comidaId, $bodega, $botella, $mensaje]);
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), 'UNIQUE')) {
                throw new \InvalidArgumentException('Ya existe un maridaje para ese vino y ese plato.');
            }
            throw $e;
        }
        return (int) $pdo->lastInsertId();
    }

    private static function existe(PDO $pdo, string $tabla, int $id, string $mensaje): void
    {
        // Nombres de tabla salen de codigo, nunca de peticiones.
        $stmt = $pdo->prepare("SELECT 1 FROM {$tabla} WHERE id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() === false) {
            throw new \InvalidArgumentException($mensaje);
        }
    }
}
