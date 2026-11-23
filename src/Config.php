<?php

declare(strict_types=1);

namespace WineMatch;

/**
 * Configuracion leida de variables de entorno (ver .env.example).
 * Ninguna credencial vive en el repositorio: si falta algo obligatorio, se falla
 * con un mensaje claro en vez de usar un valor por defecto inseguro.
 */
final class Config
{
    public readonly string $appEnv;
    public readonly string $dbDriver;
    public readonly string $dbDsn;
    public readonly ?string $dbUser;
    public readonly ?string $dbPass;
    public readonly string $dbPath;
    public readonly bool $secureCookies;

    /** @param array<string, string|false> $env */
    public function __construct(array $env = [])
    {
        $env = $env !== [] ? $env : getenv();
        $this->appEnv = self::str($env, 'APP_ENV', 'dev');

        $this->dbDriver = strtolower(self::str($env, 'DB_DRIVER', 'sqlite'));
        if ($this->dbDriver === 'sqlite') {
            $this->dbPath = self::str($env, 'DB_PATH', dirname(__DIR__) . '/data/winematch.sqlite3');
            $this->dbDsn = 'sqlite:' . $this->dbPath;
            $this->dbUser = null;
            $this->dbPass = null;
        } elseif ($this->dbDriver === 'mysql') {
            $host = self::str($env, 'DB_HOST', '127.0.0.1');
            $port = self::str($env, 'DB_PORT', '3306');
            $name = self::required($env, 'DB_NAME');
            $this->dbDsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
            $this->dbUser = self::required($env, 'DB_USER');
            $this->dbPass = self::required($env, 'DB_PASS');
            $this->dbPath = '';
        } else {
            throw new \RuntimeException("DB_DRIVER no soportado: {$this->dbDriver} (use sqlite o mysql)");
        }

        $this->secureCookies = $this->appEnv === 'production';
    }

    /** @param array<string, string|false> $env */
    private static function str(array $env, string $key, string $default): string
    {
        $v = $env[$key] ?? false;
        return $v === false || $v === '' ? $default : (string) $v;
    }

    /** @param array<string, string|false> $env */
    private static function required(array $env, string $key): string
    {
        $v = $env[$key] ?? false;
        if ($v === false || $v === '') {
            throw new \RuntimeException("Falta la variable de entorno {$key} (obligatoria con DB_DRIVER=mysql)");
        }
        return (string) $v;
    }
}
