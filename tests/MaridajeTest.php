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

    private function plato(string $slug): array
    {
        $p = Maridaje::platoPorSlug($this->pdo, 'mas-bosson', $slug);
        if ($p === null) {
            self::fail("Plato no encontrado: {$slug}");
        }
        return $p;
    }

    /** Agrega un plato nuevo sin reglas de maridaje a la especie indicada. */
    private function platoSinReglas(string $nombre, string $especie): array
    {
        $stmt = $this->pdo->prepare('INSERT INTO Comida (restaurante_id, nombre, slug, especie_id) SELECT r.id, ?, ?, e.id FROM Restaurante r, Especie e WHERE r.slug = ? AND e.nombre = ?');
        $stmt->execute([$nombre, Texto::slug($nombre), 'mas-bosson', $especie]);
        return $this->plato(Texto::slug($nombre));
    }

    public function testLaCartaListaLosPlatosDeSuRestaurante(): void
    {
        $carta = Maridaje::carta($this->pdo, 1);
        $this->assertCount(5, $carta);
        $this->assertSame('Arroz de bogavante', $carta[0]['nombre']);
        $this->assertSame('Fruta madura', $carta[0]['especie']);
        $this->assertCount(5, Maridaje::carta($this->pdo, 2));
    }

    public function testLosSlugsDeLaCartaSeDerivanDelNombre(): void
    {
        foreach ($this->pdo->query('SELECT nombre, slug FROM Comida') as $fila) {
            $this->assertSame(Texto::slug($fila['nombre']), $fila['slug']);
        }
    }

    public function testRecomendacionExactaCuandoHayReglaParaElPlato(): void
    {
        $rec = Maridaje::recomendar($this->pdo, $this->plato('arroz-de-bogavante'), null);
        $this->assertTrue($rec['exacta']);
        $this->assertStringContainsString('Regla de la casa', $rec['motivo']);
        $this->assertContains($rec['vino']['nombre'], ['Martín Códax', 'Muga Rosado'], 'Dos reglas exactas para este plato');
    }

    public function testElTipoDeVinoPedidoSeRespeta(): void
    {
        $rosado = Maridaje::recomendar($this->pdo, $this->plato('arroz-de-bogavante'), $this->tipoId('Rosado'));
        $this->assertSame('Muga Rosado', $rosado['vino']['nombre']);
        $this->assertTrue($rosado['exacta']);

        $blanco = Maridaje::recomendar($this->pdo, $this->plato('arroz-de-bogavante'), $this->tipoId('Blanco'));
        $this->assertSame('Martín Códax', $blanco['vino']['nombre']);
    }

    public function testSinReglaPropiaDegradaAMismaEspecieAromaticas(): void
    {
        $nuevo = $this->platoSinReglas('Fideuà de rape', 'Fruta madura');
        for ($i = 0; $i < 8; $i++) {
            $rec = Maridaje::recomendar($this->pdo, $nuevo, null);
            $this->assertFalse($rec['exacta']);
            $this->assertStringContainsString('perfil aromático de Fruta madura', $rec['motivo']);
            $this->assertContains($rec['vino']['nombre'], ['Martín Códax', 'Muga Rosado', 'Marqués de Cáceres Crianza']);
        }
    }

    public function testConTipoSinResultadosDegradaSinFiltrar(): void
    {
        $nuevo = $this->platoSinReglas('Cabra al horno', 'Especias');   // reglas: Cune (tinto), Malaveïna (tinto)
        $rec = Maridaje::recomendar($this->pdo, $nuevo, $this->tipoId('Espumoso'));
        $this->assertFalse($rec['exacta']);
        $this->assertStringContainsString('sin filtrar por tipo', $rec['motivo']);
        $this->assertNotSame('Espumoso', $rec['vino']['tipo']);
    }

    public function testSinNingunaReglaCercanaSugiereCualquiera(): void
    {
        $pdo = Db::memory();
        $pdo->exec("INSERT INTO Restaurante (nombre, slug) VALUES ('Prueba', 'prueba')");
        $pdo->exec("INSERT INTO Especie (nombre) VALUES ('Ahumados'), ('Otra cosa')");
        $pdo->exec("INSERT INTO Comida (restaurante_id, nombre, slug, especie_id) VALUES (1, 'Salmón ahumado', 'salmon-ahumado', 1)");
        $pdo->exec("INSERT INTO TipoVino (nombre) VALUES ('Tinto')");
        $pdo->exec("INSERT INTO Vino (nombre, bodega, tipo_id) VALUES ('Un tinto', 'Una bodega', 1)");
        $pdo->exec("INSERT INTO Comida (restaurante_id, nombre, slug, especie_id) VALUES (1, 'Otro plato', 'otro-plato', 2)");
        // La unica regla es de otra especie: no hay nada cercano al salmon ahumado.
        $pdo->exec("INSERT INTO Recomendacion (vino_id, especie_id, comida_id, mensaje) VALUES (1, 2, 2, 'Mensaje')");

        $otro = Maridaje::platoPorSlug($pdo, 'prueba', 'salmon-ahumado');
        $rec = Maridaje::recomendar($pdo, $otro, null);
        $this->assertFalse($rec['exacta']);
        $this->assertStringContainsString('Aún no tenemos', $rec['motivo']);
        $this->assertSame('Un tinto', $rec['vino']['nombre']);
    }

    public function testPlatoDeOtroRestauranteODesconocidoEsNull(): void
    {
        $this->assertNull(Maridaje::platoPorSlug($this->pdo, 'mas-bosson', 'no-existe'));
        $this->assertNull(Maridaje::platoPorSlug($this->pdo, 'quatre-vents', 'arroz-de-bogavante'));
        $this->assertNull(Maridaje::restaurantePorSlug($this->pdo, 'no-existe'));
    }

    public function testLaConsultaQuedaRegistradaParaElUsuario(): void
    {
        $rec = Maridaje::recomendar($this->pdo, $this->plato('bacalao-con-pisto'), null);
        Maridaje::registrarConsulta($this->pdo, 1, $rec['recomendacion_id']);
        $fila = $this->pdo->query('SELECT usuario_id, recomendacion_id FROM Consulta')->fetch();
        $this->assertSame(1, (int) $fila['usuario_id']);
        $this->assertSame($rec['recomendacion_id'], (int) $fila['recomendacion_id']);
    }

    public function testOpinionesDelVinoConMediaYComentarios(): void
    {
        $vino = (int) $this->pdo->query("SELECT id FROM Vino WHERE nombre = 'Martín Códax'")->fetchColumn();
        $op = Maridaje::opiniones($this->pdo, $vino);
        $this->assertSame(5.0, $op['media']);
        $this->assertSame(1, $op['total']);
        $this->assertCount(1, $op['comentarios']);
        $this->assertSame('Marta', $op['comentarios'][0]['nombre']);
    }

    public function testOpinionesVaciasParaUnVinoSinValoraciones(): void
    {
        $vino = (int) $this->pdo->query("SELECT id FROM Vino WHERE nombre = 'Viña Sol'")->fetchColumn();
        $op = Maridaje::opiniones($this->pdo, $vino);
        $this->assertSame(3.0, $op['media']);
        $this->assertSame([], $op['comentarios']);
    }

    private function vino(string $nombre): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM Vino WHERE nombre = ?');
        $stmt->execute([$nombre]);
        return (int) $stmt->fetchColumn();
    }

    public function testMaridaTuMatchDeLaCasa(): void
    {
        $r = Maridaje::comprobar($this->pdo, $this->plato('arroz-de-bogavante'), $this->vino('Martín Códax'));
        $this->assertSame('exacto', $r['veredicto']);
        $this->assertSame('Match de la casa', $r['titulo']);
        $this->assertStringContainsString('Albariño', $r['mensaje']);
    }

    public function testMaridaTuCompatiblePorPerfilAromatico(): void
    {
        $r = Maridaje::comprobar($this->pdo, $this->plato('arroz-de-bogavante'), $this->vino('Marqués de Cáceres Crianza'));
        $this->assertSame('parecido', $r['veredicto']);
        $this->assertStringContainsString('Bacalao con pisto', $r['mensaje']);
        $this->assertStringContainsString('perfil de Fruta madura', $r['mensaje']);
    }

    public function testMaridaTuSinReglaEsHonestoYSugiere(): void
    {
        $r = Maridaje::comprobar($this->pdo, $this->plato('canelones-de-ternera'), $this->vino('Recaredo Brut Nature'));
        $this->assertSame('sin_regla', $r['veredicto']);
        $this->assertStringContainsString('No tenemos Recaredo Brut Nature definido para Canelones de ternera', $r['mensaje']);
        $this->assertSame('Cune Crianza', $r['sugerencia']['vino']['nombre']);
    }

    public function testMaridaTuConVinoInexistenteLanza(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Maridaje::comprobar($this->pdo, $this->plato('arroz-de-bogavante'), 9999);
    }
}
