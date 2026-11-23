<?php

declare(strict_types=1);

namespace WineMatch\Tests;

use PDO;
use WineMatch\Config;
use WineMatch\Db;
use WineMatch\Seed;

/**
 * Levanta una vez por proceso el servidor integrado de PHP contra una base de
 * datos SQLite temporal sembrada, y lo apaga al terminar.
 */
final class Servidor
{
    public const PUERTO = 8099;
    public const EMAIL_ADMIN = 'admin@ejemplo.test';
    public static string $adminPass = '';
    /** @var resource|null */
    private static $proceso = null;
    private static ?string $dbPath = null;

    public static function url(): string
    {
        if (self::$proceso !== null) {
            return 'http://127.0.0.1:' . self::PUERTO;
        }

        self::$dbPath = sys_get_temp_dir() . '/winematch-test-' . getmypid() . '.sqlite3';
        @unlink(self::$dbPath);
        putenv('APP_ENV=test');
        putenv('DB_DRIVER=sqlite');
        putenv('DB_PATH=' . self::$dbPath);
        putenv('DB_HOST');
        putenv('DB_USER');
        putenv('DB_PASS');
        putenv('DB_NAME');

        $pdo = new PDO('sqlite:' . self::$dbPath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        Db::createSchema($pdo);
        self::$adminPass = Seed::run($pdo, self::EMAIL_ADMIN);

        $raiz = dirname(__DIR__);
        $log = fopen(sys_get_temp_dir() . '/winematch-server.log', 'a');
        $cmd = [PHP_BINARY, '-S', '127.0.0.1:' . self::PUERTO, '-t', 'public', 'public/index.php'];
        self::$proceso = proc_open($cmd, [0 => ['pipe', 'r'], 1 => $log, 2 => $log], $pipes, $raiz);
        if (self::$proceso === false) {
            throw new \RuntimeException('No se pudo arrancar el servidor de pruebas');
        }
        fclose($pipes[0]);
        register_shutdown_function(static function (): void {
            if (self::$proceso !== null) {
                proc_terminate(self::$proceso);
                proc_close(self::$proceso);
            }
            if (self::$dbPath !== null) {
                @unlink(self::$dbPath);
            }
        });

        $url = 'http://127.0.0.1:' . self::PUERTO;
        for ($i = 0; $i < 60; $i++) {
            $ctx = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 1]]);
            if (@file_get_contents($url . '/login', false, $ctx) !== false) {
                return $url;
            }
            usleep(100_000);
        }
        throw new \RuntimeException('El servidor de pruebas no respondió');
    }

    public static function pdo(): PDO
    {
        self::url();
        return new PDO('sqlite:' . self::$dbPath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }
}
