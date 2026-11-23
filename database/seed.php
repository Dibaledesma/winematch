<?php

declare(strict_types=1);

/**
 * Crea el esquema (si no existe) y carga los datos de ejemplo.
 * Uso: composer seed   o   php database/seed.php
 * La contrasena del admin se genera aleatoriamente y se muestra una sola vez.
 */

require __DIR__ . '/../vendor/autoload.php';

use WineMatch\Config;
use WineMatch\Db;
use WineMatch\Seed;

$opciones = getopt('', ['admin-email::', 'recrear']);
$adminEmail = $opciones['admin-email'] ?? 'admin@winematch.local';

$config = new Config();
if (($config->dbDriver === 'sqlite') && isset($opciones['recrear']) && is_file($config->dbPath)) {
    unlink($config->dbPath);
}
$pdo = Db::connect($config);   // en SQLite crea el esquema si falta

$existe = $pdo->query('SELECT COUNT(*) FROM TipoVino')->fetchColumn();
if ((int) $existe > 0 && !isset($opciones['recrear'])) {
    fwrite(STDERR, "La base de datos ya tiene datos. Usa --recrear para empezar de cero.\n");
    exit(1);
}

$pass = Seed::run($pdo, $adminEmail);
echo "Datos de ejemplo cargados.\n";
echo "  Admin:  {$adminEmail}\n  Clave:  {$pass}\n";
echo "  (esta clave no se guarda en claro y no se vuelve a mostrar)\n";
