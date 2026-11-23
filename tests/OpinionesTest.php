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

    private function restaurante(string $slug): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM Restaurante WHERE slug = ?');
        $stmt->execute([$slug]);
        return (int) $stmt->fetchColumn();
    }

    private function especie(string $slug, string $nombre): int
    {
        $stmt = $this->pdo->prepare('SELECT e.id FROM Especie e JOIN Restaurante r ON r.id = e.restaurante_id WHERE r.slug = ? AND e.nombre = ?');
        $stmt->execute([$slug, $nombre]);
        return (int) $stmt->fetchColumn();
    }

    public function testValorarGuardaYSeActualizaSinDuplicar(): void
    {
        $vino = $this->vino('Trenca Closques');
        Opiniones::guardar($this->pdo, 1, $vino, 4, null);
        Opiniones::guardar($this->pdo, 1, $vino, 2, null);

        $this->assertSame(2, Opiniones::mia($this->pdo, 1, $vino)['puntuacion']);
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM Valoracion WHERE vino_id = ? AND usuario_id = 1');
        $stmt->execute([$vino]);
        $this->assertSame(1, (int) $stmt->fetchColumn(), 'La segunda valoracion actualiza, no duplica');
    }

    public function testComentarGuardaElTexto(): void
    {
        $vino = $this->vino('Clos de Agon');
        Opiniones::guardar($this->pdo, 1, $vino, null, '  Un clásico  ');
        $this->assertSame('Un clásico', Opiniones::mia($this->pdo, 1, $vino)['comentario']);
    }

    #[DataProvider('opinionesInvalidas')]
    public function testOpinionInvalidaSeRechaza(?int $puntuacion, ?string $comentario, string $mensaje): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($mensaje);
        Opiniones::guardar($this->pdo, 1, $this->vino('Clos de Agon'), $puntuacion, $comentario);
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

    public function testAdminCreaUnaEspeciaEnSuRestaurante(): void
    {
        $id = Admin::crearEspecie($this->pdo, $this->restaurante('quatre-lents'), 'Ahumados');
        $this->assertGreaterThan(7, $id);
        $this->assertSame(8, count(Admin::especies($this->pdo)));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ya existe');
        Admin::crearEspecie($this->pdo, $this->restaurante('quatre-lents'), 'Curry');
    }

    public function testAdminCreaUnPlatoEnLaCarta(): void
    {
        $quatre = $this->restaurante('quatre-lents');
        $id = Admin::crearPlato($this->pdo, $quatre, 'Gambas al ajillo', 'Segundo', 'Gambas frescas, ajo y guindilla.', $this->especie('quatre-lents', 'Curry'));

        $this->assertGreaterThan(6, $id);
        $carta = Maridaje::carta($this->pdo, $quatre);
        $this->assertCount(3, $carta);
        $this->assertSame('gambas-al-ajillo', end($carta)['slug']);
    }

    #[DataProvider('platosInvalidos')]
    public function testAdminCreaPlatoInvalidoSeRechaza(int $restaurante, string $tipo, int $especie, string $mensaje): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($mensaje);
        Admin::crearPlato($this->pdo, $restaurante, 'Plato nuevo', $tipo, '', $especie);
    }

    public static function platosInvalidos(): array
    {
        return [
            'restaurante inexistente' => [999, 'Primero', 1, 'Restaurante no válido'],
            'tipo inválido' => [1, 'Entrante', 1, 'Primero, Segundo o Postre'],
            'especia de otro restaurante' => [2, 'Primero', 1, 'no es del restaurante'],
        ];
    }

    public function testAdminCreaMaridajeYElPlatoYaTieneMatchExacto(): void
    {
        $quatre = $this->restaurante('quatre-lents');
        $plato = Admin::crearPlato($this->pdo, $quatre, 'Gambas al ajillo', 'Segundo', '', $this->especie('quatre-lents', 'Curry'));
        $antes = Maridaje::recomendar($this->pdo, Maridaje::platoPorSlug($this->pdo, 'quatre-lents', 'gambas-al-ajillo'), null);
        $this->assertFalse($antes['exacta']);

        Admin::crearRecomendacion($this->pdo, $this->vino('Jhana Rosat'), $plato, 'Clos Mogador', 'Jhana Rosat', 'La fruta rosada del Viognier suaviza el ajo y la guindilla.');
        $rec = Maridaje::recomendar($this->pdo, Maridaje::platoPorSlug($this->pdo, 'quatre-lents', 'gambas-al-ajillo'), null);
        $this->assertTrue($rec['exacta']);
        $this->assertSame('Jhana Rosat', $rec['botella']);
        $this->assertSame('Clos Mogador', $rec['bodega']);
    }

    public function testMaridajeDuplicadoSeRechaza(): void
    {
        $plato = (int) $this->pdo->query("SELECT id FROM Comida WHERE slug = 'crema-catalana'")->fetchColumn();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Ya existe');
        Admin::crearRecomendacion($this->pdo, $this->vino('Camins del Priorat'), $plato, 'Otra', 'Otra', 'Otra vez');
    }

    public function testMaridajeSinBodegaSeRechaza(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Indica la bodega');
        Admin::crearRecomendacion($this->pdo, $this->vino('Calcari'), 6, '', 'Calcari', 'Mensaje');
    }

    public function testListadosDelPanelTienenFormaEsperada(): void
    {
        $usuarios = Admin::usuarios($this->pdo);
        $this->assertCount(3, $usuarios);
        $this->assertSame('admin', $usuarios[2]['rol']);
        $this->assertArrayHasKey('consultas', $usuarios[0]);

        $comentarios = Admin::comentarios($this->pdo);
        $this->assertCount(2, $comentarios);
        $this->assertSame('Calcari', $comentarios[0]['vino']);

        $valoraciones = Admin::valoraciones($this->pdo);
        $this->assertCount(6, $valoraciones);
        $this->assertSame('Pas Curtei', $valoraciones[0]['nombre']);
        $this->assertSame(5.0, (float) $valoraciones[0]['media']);
        $this->assertCount(6, Admin::platos($this->pdo));
        $this->assertCount(7, Admin::especies($this->pdo));
        $this->assertSame('Quatre Lents', Admin::especies($this->pdo)[4]['restaurante']);
    }
}
