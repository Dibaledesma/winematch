<?php

declare(strict_types=1);

namespace WineMatch\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use WineMatch\Config;
use WineMatch\Db;
use WineMatch\Seed;

/** Esqueleto y modelo de datos de la v1: el esquema se crea, las claves foraneas se cumplen y el seed carga los datos originales. */
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

    public function testElSeedCargaLosDatosDeLaV1(): void
    {
        $pdo = $this->pdo();
        Seed::run($pdo);

        $this->assertSame(2, (int) $pdo->query('SELECT COUNT(*) FROM Restaurante')->fetchColumn());
        $this->assertSame(3, (int) $pdo->query('SELECT COUNT(*) FROM TipoVino')->fetchColumn());
        $this->assertSame(6, (int) $pdo->query('SELECT COUNT(*) FROM Vino')->fetchColumn());
        $this->assertSame(7, (int) $pdo->query('SELECT COUNT(*) FROM Especie')->fetchColumn());
        $this->assertSame(6, (int) $pdo->query('SELECT COUNT(*) FROM Comida')->fetchColumn());
        $this->assertSame(6, (int) $pdo->query('SELECT COUNT(*) FROM Recomendacion')->fetchColumn());
        $this->assertSame(3, (int) $pdo->query('SELECT COUNT(*) FROM Usuarios')->fetchColumn());

        $vinos = $pdo->query('SELECT nombre FROM Vino ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame(['Camins del Priorat', 'Clos de Agon', 'Pas Curtei', 'Calcari', 'Trenca Closques', 'Jhana Rosat'], $vinos);
        $especias = $pdo->query('SELECT nombre FROM Especie ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame(['Pimienta negra', 'Pimienta blanca', 'Canela', 'Comino', 'Clavo', 'Curry', 'Menta'], $especias);
        $tipos = $pdo->query('SELECT tipo FROM Comida ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame(['Primero', 'Primero', 'Segundo', 'Postre', 'Primero', 'Segundo'], $tipos);
    }

    public function testLosRestaurantesTienenNombreInventado(): void
    {
        $pdo = $this->pdo();
        Seed::run($pdo);
        $slugs = $pdo->query('SELECT slug FROM Restaurante ORDER BY slug')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame(['mas-guason', 'quatre-lents'], $slugs);
        $this->assertSame(['Mas Guasón', 'Quatre Lents'], array_keys(Seed::RESTAURANTES));
    }

    public function testCadaPlatoTieneSuMaridajeConSuEspeciaYLosDatosDeLaV1(): void
    {
        $pdo = $this->pdo();
        Seed::run($pdo);
        $sql = 'SELECT c.nombre, r.nombre_bodega, r.nombre_botella, r.mensaje, v.nombre AS vino
                FROM Comida c
                JOIN Recomendacion r ON r.comida_id = c.id AND r.especie_id = c.especie_id
                JOIN Vino v ON v.id = r.vino_id';
        $filas = $pdo->query($sql)->fetchAll();
        $this->assertCount(6, $filas);
        $porPlato = array_column($filas, null, 'nombre');
        $this->assertSame('Parés Baltà', $porPlato['Crema catalana']['nombre_bodega']);
        $this->assertSame('Camins del Priorat', $porPlato['Crema catalana']['nombre_botella']);
        $this->assertSame('Excelente para maridar con carnes de cerdo o guisos contundentes.', $porPlato['Crema catalana']['mensaje']);
        $this->assertSame('Clos d\'Agon', $porPlato['Rodaballo al horno']['nombre_bodega']);
        $this->assertSame('Cellers Can Blau', $porPlato['Tataki de atún']['nombre_bodega']);
    }

    public function testLasEspeciasPertenecenASuRestauranteDeLaV1(): void
    {
        $pdo = $this->pdo();
        Seed::run($pdo);
        $filas = $pdo->query('SELECT r.slug, e.nombre FROM Especie e JOIN Restaurante r ON r.id = e.restaurante_id')->fetchAll();
        $por = ['mas-guason' => [], 'quatre-lents' => []];
        foreach ($filas as $f) {
            $por[$f['slug']][] = $f['nombre'];
        }
        sort($por['mas-guason']);
        sort($por['quatre-lents']);
        $this->assertSame(['Canela', 'Comino', 'Pimienta blanca', 'Pimienta negra'], $por['mas-guason']);
        $this->assertSame(['Clavo', 'Curry', 'Menta'], $por['quatre-lents']);
    }

    public function testLaClaveForaneaSeCumple(): void
    {
        $pdo = $this->pdo();
        Seed::run($pdo);
        $this->expectException(\PDOException::class);
        $pdo->prepare('INSERT INTO Comida (restaurante_id, nombre, slug, tipo, especie_id) VALUES (999, ?, ?, ?, 1)')->execute(['Plato huérfano', 'plato-huerfano', 'Primero']);
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
