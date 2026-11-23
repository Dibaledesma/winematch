<?php

declare(strict_types=1);

namespace WineMatch;

use PDO;

/** Valoraciones y comentarios de los vinos. Una valoracion por usuario y vino. */
final class Opiniones
{
    public const MAX_COMENTARIO = 500;

    /** @throws \InvalidArgumentException mensaje mostrable al usuario */
    public static function guardar(PDO $pdo, int $usuarioId, int $vinoId, ?int $puntuacion, ?string $comentario): void
    {
        $comentario = trim((string) $comentario);
        if ($puntuacion === null && $comentario === '') {
            throw new \InvalidArgumentException('Valora el vino o escribe un comentario.');
        }
        if ($puntuacion !== null && ($puntuacion < 1 || $puntuacion > 5)) {
            throw new \InvalidArgumentException('La puntuación debe estar entre 1 y 5.');
        }
        if (mb_strlen($comentario) > self::MAX_COMENTARIO) {
            throw new \InvalidArgumentException('El comentario no puede superar ' . self::MAX_COMENTARIO . ' caracteres.');
        }
        $stmt = $pdo->prepare('SELECT 1 FROM Vino WHERE id = ?');
        $stmt->execute([$vinoId]);
        if ($stmt->fetchColumn() === false) {
            throw new \InvalidArgumentException('Ese vino no existe.');
        }

        if ($puntuacion !== null) {
            $pdo->prepare(
                'INSERT INTO Valoracion (usuario_id, vino_id, puntuacion) VALUES (?, ?, ?)
                 ON CONFLICT (usuario_id, vino_id) DO UPDATE SET puntuacion = excluded.puntuacion, fecha = CURRENT_TIMESTAMP'
            )->execute([$usuarioId, $vinoId, $puntuacion]);
        }
        if ($comentario !== '') {
            $pdo->prepare('INSERT INTO Comentario (usuario_id, vino_id, comentario) VALUES (?, ?, ?)')
                ->execute([$usuarioId, $vinoId, $comentario]);
        }
    }

    /** @return array{puntuacion: int|null, comentario: string} valoracion y ultimo comentario del usuario sobre un vino */
    public static function mia(PDO $pdo, int $usuarioId, int $vinoId): array
    {
        $stmt = $pdo->prepare('SELECT puntuacion FROM Valoracion WHERE usuario_id = ? AND vino_id = ?');
        $stmt->execute([$usuarioId, $vinoId]);
        $puntuacion = $stmt->fetchColumn();
        $stmt = $pdo->prepare('SELECT comentario FROM Comentario WHERE usuario_id = ? AND vino_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$usuarioId, $vinoId]);
        $comentario = $stmt->fetchColumn();
        return [
            'puntuacion' => $puntuacion === false ? null : (int) $puntuacion,
            'comentario' => $comentario === false ? '' : (string) $comentario,
        ];
    }
}
