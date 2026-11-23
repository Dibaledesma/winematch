<?php

declare(strict_types=1);

namespace WineMatch;

use PDO;

/**
 * Conexion PDO con parametros seguros: excepciones, fetch asociativo,
 * prepares nativos. En SQLite crea el esquema la primera vez y activa
 * las claves foraneas (SQLite las desactiva por conexion).
 */
final class Db
{
    public static function connect(Config $config): PDO
    {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $pdo = new PDO($config->dbDsn, $config->dbUser, $config->dbPass, $options);

        if ($config->dbDriver === 'sqlite') {
            $pdo->exec('PRAGMA foreign_keys = ON');
            // PDO crea el archivo vacio al conectar, asi que se mira si existe el esquema.
            $tablas = (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'Usuarios'")->fetchColumn();
            if ($tablas === 0) {
                self::createSchema($pdo);
            }
        }

        return $pdo;
    }

    public static function createSchema(PDO $pdo): void
    {
        $pdo->exec((string) file_get_contents(dirname(__DIR__) . '/database/schema.sqlite.sql'));
    }

    /** Base de datos en memoria con el esquema vacio: para tests. */
    public static function memory(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        self::createSchema($pdo);
        return $pdo;
    }
}
