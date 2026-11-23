<?php

declare(strict_types=1);

namespace WineMatch\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use WineMatch\Config;
use WineMatch\Db;
use WineMatch\Seed;

/** Esqueleto y modelo de datos: el esquema se crea, las claves foraneas se cumplen y el seed carga. */
final class ModeloTest extends TestCase
{
    private function pdo(): PDO
    {
        return Db::memory();
    }

    public function testElEsquemaSeCreaConTodasLasTablas(): void
    {
        $tablas = $this->pdo()->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
        $esperadas = ['Comentario', 'Comida', 'Consulta', 'Especie', 'Recomendacion', 'Restaurante', 'TipoVino', 'Usuarios', 'Valoracion', 'Vino'];
        $this->assertSame($esperadas, $tablas);
    }

    public function testElSeedCargaDosRestaurantesDiezPlatosYCatorceReglas(): void
    {
        $pdo = $this->pdo();
        Seed::run($pdo);

        $this->assertSame(2, (int) $pdo->query('SELECT COUNT(*) FROM Restaurante')->fetchColumn());
        $this->assertSame(10, (int) $pdo->query('SELECT COUNT(*) FROM Comida')->fetchColumn());
        $this->assertSame(14, (int) $pdo->query('SELECT COUNT(*) FROM Recomendacion')->fetchColumn());
        $this->assertSame(8, (int) $pdo->query('SELECT COUNT(*) FROM Vino')->fetchColumn());
        $this->assertSame(3, (int) $pdo->query('SELECT COUNT(*) FROM Usuarios')->fetchColumn());
    }

    public function testCadaPlatoTieneAlMenosUnMaridajeQueCoincideConSuEspecie(): void
    {
        $pdo = $this->pdo();
        Seed::run($pdo);
        $sql = 'SELECT c.nombre, COUNT(r.id) AS reglas
                FROM Comida c
                LEFT JOIN Recomendacion r ON r.comida_id = c.id AND r.especie_id = c.especie_id
                GROUP BY c.id';
        foreach ($pdo->query($sql) as $fila) {
            $this->assertGreaterThanOrEqual(1, (int) $fila['reglas'], "El plato {$fila['nombre']} no tiene maridaje");
        }
    }

    public function testLaClaveForaneaSeCumple(): void
    {
        $pdo = $this->pdo();
        Seed::run($pdo);
        $this->expectException(\PDOException::class);
        $pdo->prepare('INSERT INTO Comida (restaurante_id, nombre, especie_id) VALUES (999, ?, 1)')->execute(['Plato huérfano']);
    }

    public function testElAdminSeCreaConHashYRolAdmin(): void
    {
        $pdo = $this->pdo();
        $pass = Seed::run($pdo, 'jefe@ejemplo.test');
        $fila = $pdo->query("SELECT rol, password_hash FROM Usuarios WHERE email = 'jefe@ejemplo.test'")->fetch();
        $this->assertSame('admin', $fila['rol']);
        $this->assertTrue(password_verify($pass, $fila['password_hash']));
        $this->assertNotSame($pass, $fila['password_hash']);
    }

    public function testConfigSinCredencialesMysqlFallaConMensajeClaro(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DB_NAME');
        new Config(['DB_DRIVER' => 'mysql']);
    }

    public function testConfigSqlitePorDefecto(): void
    {
        $c = new Config(['APP_ENV' => 'test']);
        $this->assertSame('sqlite', $c->dbDriver);
        $this->assertFalse($c->secureCookies);
        $this->assertStringStartsWith('sqlite:', $c->dbDsn);
    }

    public function testElPrimerArranqueCreaElEsquemaEnUnArchivoNuevo(): void
    {
        $ruta = sys_get_temp_dir() . '/winematch-primer-arranque-' . getmypid() . '.sqlite3';
        @unlink($ruta);
        $pdo = Db::connect(new Config(['APP_ENV' => 'test', 'DB_PATH' => $ruta]));
        $tablas = (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'Usuarios'")->fetchColumn();
        @unlink($ruta);
        $this->assertSame(1, $tablas);
    }
}
