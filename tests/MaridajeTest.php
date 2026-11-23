<?php

declare(strict_types=1);

namespace WineMatch\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use WineMatch\Db;
use WineMatch\Maridaje;
use WineMatch\Seed;
use WineMatch\Texto;

final class MaridajeTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = Db::memory();
        Seed::run($this->pdo);
    }

    private function tipoId(string $nombre): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM TipoVino WHERE nombre = ?');
        $stmt->execute([$nombre]);
        return (int) $stmt->fetchColumn();
    }

    private function vino(string $nombre): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM Vino WHERE nombre = ?');
        $stmt->execute([$nombre]);
        return (int) $stmt->fetchColumn();
    }

    private function plato(string $slug): array
    {
        $p = Maridaje::platoPorSlug($this->pdo, 'mas-guason', $slug);
        if ($p === null) {
            self::fail("Plato no encontrado: {$slug}");
        }
        return $p;
    }

    /** Agrega un plato nuevo en un restaurante ligado a una especia existente de ese restaurante. */
    private function platoNuevo(string $restaurante, string $especie, string $nombre): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO Comida (restaurante_id, nombre, slug, tipo, especie_id)
             SELECT r.id, ?, ?, \'Segundo\', e.id FROM Restaurante r, Especie e WHERE r.slug = ? AND e.nombre = ? AND e.restaurante_id = r.id'
        );
        $stmt->execute([$nombre, Texto::slug($nombre), $restaurante, $especie]);
        $p = Maridaje::platoPorSlug($this->pdo, $restaurante, Texto::slug($nombre));
        self::assertNotNull($p);
        return $p;
    }

    public function testLaCartaListaLosPlatosPorTipo(): void
    {
        $carta = Maridaje::carta($this->pdo, 1);
        $this->assertSame(['Carpaccio de pie de cerdo', 'Esqueixada', 'Rodaballo al horno', 'Crema catalana'], array_column($carta, 'nombre'));
        $this->assertSame(['Primero', 'Primero', 'Segundo', 'Postre'], array_column($carta, 'tipo'));
        $this->assertSame('Pimienta negra', $carta[0]['especie']);
        $this->assertCount(2, Maridaje::carta($this->pdo, 2));
    }

    public function testLosSlugsDeLaCartaSeDerivanDelNombre(): void
    {
        foreach ($this->pdo->query('SELECT nombre, slug FROM Comida') as $fila) {
            $this->assertSame(Texto::slug($fila['nombre']), $fila['slug']);
        }
        $this->assertSame('tataki-de-atun', Texto::slug('Tataki de atún'));
    }

    public function testMaridajeExactoConLosDatosDeLaV1(): void
    {
        $rec = Maridaje::recomendar($this->pdo, $this->plato('crema-catalana'), null);
        $this->assertTrue($rec['exacta']);
        $this->assertSame('Camins del Priorat', $rec['vino']['nombre']);
        $this->assertSame('Parés Baltà', $rec['bodega']);
        $this->assertSame('Camins del Priorat', $rec['botella']);
        $this->assertSame('Priorat', $rec['vino']['denominacion_origen']);
        $this->assertSame('Excelente para maridar con carnes de cerdo o guisos contundentes.', $rec['mensaje']);
    }

    public function testElTipoDeVinoPedidoSeRespetaCuandoHayMaridaje(): void
    {
        $rec = Maridaje::recomendar($this->pdo, $this->plato('carpaccio-de-pie-de-cerdo'), $this->tipoId('Tinto'));
        $this->assertTrue($rec['exacta']);
        $this->assertSame('Pas Curtei', $rec['vino']['nombre']);
        $this->assertSame('Tinto', $rec['vino']['tipo']);
    }

    public function testConTipoSinMaridajeDegradaASinFiltrar(): void
    {
        // La crema catalana solo tiene maridaje con un tinto: pidiendo rosado, se ofrece ese tinto y se explica.
        $rec = Maridaje::recomendar($this->pdo, $this->plato('crema-catalana'), $this->tipoId('Rosado'));
        $this->assertFalse($rec['exacta']);
        $this->assertStringContainsString('sin filtrar por tipo', $rec['motivo']);
        $this->assertSame('Camins del Priorat', $rec['vino']['nombre']);
    }

    public function testSinMaridajePropioDegradaAMismaEspecia(): void
    {
        $nuevo = $this->platoNuevo('mas-guason', 'Pimienta blanca', 'Flan de huevo');
        $rec = Maridaje::recomendar($this->pdo, $nuevo, null);
        $this->assertFalse($rec['exacta']);
        $this->assertStringContainsString('toque de Pimienta blanca', $rec['motivo']);
        $this->assertSame('Camins del Priorat', $rec['vino']['nombre']);
    }

    public function testSinNingunMaridajeCercanoSugiereCualquiera(): void
    {
        $pdo = Db::memory();
        $pdo->exec("INSERT INTO Restaurante (nombre, slug) VALUES ('Prueba', 'prueba')");
        $pdo->exec("INSERT INTO Especie (restaurante_id, nombre) VALUES (1, 'Ahumados')");
        $pdo->exec("INSERT INTO Comida (restaurante_id, nombre, slug, tipo, especie_id) VALUES (1, 'Salmón ahumado', 'salmon-ahumado', 'Primero', 1)");
        $pdo->exec("INSERT INTO TipoVino (nombre) VALUES ('Tinto')");
        $pdo->exec("INSERT INTO Vino (nombre, tipo_id) VALUES ('Un tinto', 1)");
        $pdo->exec("INSERT INTO Comida (restaurante_id, nombre, slug, tipo, especie_id) VALUES (1, 'Otro plato', 'otro-plato', 'Segundo', 1)");
        $pdo->exec("INSERT INTO Recomendacion (vino_id, especie_id, comida_id, nombre_bodega, nombre_botella, mensaje) VALUES (1, 1, 2, 'Una bodega', 'Un tinto', 'Mensaje')");

        $otro = Maridaje::platoPorSlug($pdo, 'prueba', 'salmon-ahumado');
        $rec = Maridaje::recomendar($pdo, $otro, null);
        // Mismo restaurante y la unica especia coincide: degrada a "misma especia", no a cualquiera.
        $this->assertFalse($rec['exacta']);
        $this->assertSame('Un tinto', $rec['vino']['nombre']);
    }

    public function testPlatoDeOtroRestauranteODesconocidoEsNull(): void
    {
        $this->assertNull(Maridaje::platoPorSlug($this->pdo, 'mas-guason', 'no-existe'));
        $this->assertNull(Maridaje::platoPorSlug($this->pdo, 'quatre-lents', 'crema-catalana'));
        $this->assertNull(Maridaje::restaurantePorSlug($this->pdo, 'no-existe'));
    }

    public function testLaConsultaQuedaRegistradaParaElUsuario(): void
    {
        $rec = Maridaje::recomendar($this->pdo, $this->plato('rodaballo-al-horno'), null);
        Maridaje::registrarConsulta($this->pdo, 1, $rec['recomendacion_id']);
        $fila = $this->pdo->query('SELECT usuario_id, recomendacion_id FROM Consulta')->fetch();
        $this->assertSame(1, (int) $fila['usuario_id']);
        $this->assertSame($rec['recomendacion_id'], (int) $fila['recomendacion_id']);
    }

    public function testOpinionesDelVinoConMediaYComentarios(): void
    {
        $op = Maridaje::opiniones($this->pdo, $this->vino('Pas Curtei'));
        $this->assertSame(5.0, $op['media']);
        $this->assertSame(1, $op['total']);
        $this->assertCount(1, $op['comentarios']);
        $this->assertSame('Marta', $op['comentarios'][0]['nombre']);

        $op = Maridaje::opiniones($this->pdo, $this->vino('Jhana Rosat'));
        $this->assertNull($op['media']);
        $this->assertSame([], $op['comentarios']);
    }

    public function testMaridaTuMatchDeLaCasa(): void
    {
        $r = Maridaje::comprobar($this->pdo, $this->plato('crema-catalana'), $this->vino('Camins del Priorat'));
        $this->assertSame('exacto', $r['veredicto']);
        $this->assertSame('Match de la casa', $r['titulo']);
        $this->assertStringContainsString('Parés Baltà', $r['mensaje']);
        $this->assertSame('Tinto', $r['vino']['tipo']);
    }

    public function testMaridaTuCompatiblePorToque(): void
    {
        $nuevo = $this->platoNuevo('mas-guason', 'Pimienta blanca', 'Flan de huevo');
        $r = Maridaje::comprobar($this->pdo, $nuevo, $this->vino('Camins del Priorat'));
        $this->assertSame('parecido', $r['veredicto']);
        $this->assertStringContainsString('ya es el maridaje de Crema catalana', $r['mensaje']);
    }

    public function testMaridaTuSinMaridajeEsHonestoYSugiere(): void
    {
        $r = Maridaje::comprobar($this->pdo, $this->plato('crema-catalana'), $this->vino('Calcari'));
        $this->assertSame('sin_regla', $r['veredicto']);
        $this->assertStringContainsString('No tenemos Calcari definido para Crema catalana', $r['mensaje']);
        $this->assertSame('Camins del Priorat', $r['sugerencia']['botella']);
    }

    public function testMaridaTuConVinoInexistenteLanza(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Maridaje::comprobar($this->pdo, $this->plato('crema-catalana'), 9999);
    }
}
