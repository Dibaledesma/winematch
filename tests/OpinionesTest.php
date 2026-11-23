<?php

declare(strict_types=1);

namespace WineMatch\Tests;

use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WineMatch\Admin;
use WineMatch\Db;
use WineMatch\Maridaje;
use WineMatch\Opiniones;
use WineMatch\Seed;
use WineMatch\Texto;

final class OpinionesTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = Db::memory();
        Seed::run($this->pdo);
    }

    private function vino(string $nombre): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM Vino WHERE nombre = ?');
        $stmt->execute([$nombre]);
        return (int) $stmt->fetchColumn();
    }

    public function testValorarGuardaYSeActualizaSinDuplicar(): void
    {
        $vino = $this->vino('Viña Sol');
        Opiniones::guardar($this->pdo, 1, $vino, 4, null);
        Opiniones::guardar($this->pdo, 1, $vino, 2, null);

        $this->assertSame(2, Opiniones::mia($this->pdo, 1, $vino)['puntuacion']);
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM Valoracion WHERE vino_id = ? AND usuario_id = 1');
        $stmt->execute([$vino]);
        $this->assertSame(1, (int) $stmt->fetchColumn(), 'La segunda valoracion actualiza, no duplica');
    }

    public function testComentarGuardaElTexto(): void
    {
        $vino = $this->vino('Cune Crianza');
        Opiniones::guardar($this->pdo, 1, $vino, null, '  Un clásico  ');
        $this->assertSame('Un clásico', Opiniones::mia($this->pdo, 1, $vino)['comentario']);
    }

    #[DataProvider('opinionesInvalidas')]
    public function testOpinionInvalidaSeRechaza(?int $puntuacion, ?string $comentario, string $mensaje): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($mensaje);
        Opiniones::guardar($this->pdo, 1, $this->vino('Cune Crianza'), $puntuacion, $comentario);
    }

    public static function opinionesInvalidas(): array
    {
        return [
            'vacío' => [null, '   ', 'Valora el vino'],
            'cero' => [0, null, 'entre 1 y 5'],
            'seis' => [6, null, 'entre 1 y 5'],
            'demasiado largo' => [5, str_repeat('a', 501), 'no puede superar'],
        ];
    }

    public function testVinoInexistenteSeRechaza(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('no existe');
        Opiniones::guardar($this->pdo, 1, 9999, 5, null);
    }

    public function testAdminCreaUnPlatoEnLaCarta(): void
    {
        $restaurante = (int) $this->pdo->query("SELECT id FROM Restaurante WHERE slug = 'quatre-vents'")->fetchColumn();
        $especie = (int) $this->pdo->query("SELECT id FROM Especie WHERE nombre = 'Flores blancas'")->fetchColumn();
        $id = Admin::crearPlato($this->pdo, $restaurante, 'Gambas al ajillo', 'Gambas frescas, ajo y guindilla.', $especie);

        $this->assertGreaterThan(10, $id);
        $carta = Maridaje::carta($this->pdo, $restaurante);
        $this->assertSame('gambas-al-ajillo', end($carta)['slug']);
        $this->assertNotNull(Maridaje::platoPorSlug($this->pdo, 'quatre-vents', 'gambas-al-ajillo'));
    }

    public function testAdminCreaPlatoInvalidoSeRechaza(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Restaurante');
        Admin::crearPlato($this->pdo, 999, 'Plato', '', 1);
    }

    public function testAdminCreaReglaYElPlatoYaTieneMaridajeExacto(): void
    {
        $restaurante = (int) $this->pdo->query("SELECT id FROM Restaurante WHERE slug = 'quatre-vents'")->fetchColumn();
        $especie = (int) $this->pdo->query("SELECT id FROM Especie WHERE nombre = 'Flores blancas'")->fetchColumn();
        $plato = Admin::crearPlato($this->pdo, $restaurante, 'Ostras', 'Ostras de la lonja.', $especie);
        $antes = Maridaje::recomendar($this->pdo, Maridaje::platoPorSlug($this->pdo, 'quatre-vents', 'ostras'), null);
        $this->assertFalse($antes['exacta']);

        Admin::crearRecomendacion($this->pdo, $this->vino('Anna de Codorníu Brut'), $plato, 'La burbuja fresca y la sal de la ostra se necesitan.');
        $rec = Maridaje::recomendar($this->pdo, Maridaje::platoPorSlug($this->pdo, 'quatre-vents', 'ostras'), null);
        $this->assertTrue($rec['exacta']);
        $this->assertSame('Anna de Codorníu Brut', $rec['vino']['nombre']);
        $this->assertSame('La burbuja fresca y la sal de la ostra se necesitan.', $rec['mensaje']);
    }

    public function testReglaDuplicadaSeRechaza(): void
    {
        $plato = (int) $this->pdo->query("SELECT id FROM Comida WHERE slug = 'lubina-a-la-sal'")->fetchColumn();
        $vino = $this->vino('Martín Códax');   // ya tiene regla para la lubina
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Ya existe');
        Admin::crearRecomendacion($this->pdo, $vino, $plato, 'Otra vez');
    }

    public function testListadosDelPanelTienenFormaEsperada(): void
    {
        $usuarios = Admin::usuarios($this->pdo);
        $this->assertCount(3, $usuarios);
        $this->assertSame('admin', $usuarios[2]['rol']);
        $this->assertArrayHasKey('consultas', $usuarios[0]);

        $comentarios = Admin::comentarios($this->pdo);
        $this->assertCount(2, $comentarios);
        $this->assertSame('Finca Malaveïna', $comentarios[0]['vino']);

        $valoraciones = Admin::valoraciones($this->pdo);
        $this->assertCount(8, $valoraciones);
        $this->assertSame(5.0, (float) $valoraciones[0]['media']);
        $this->assertCount(10, Admin::platos($this->pdo));
        $this->assertSame(Texto::slug('Arroz de bogavante'), 'arroz-de-bogavante');
    }
}
