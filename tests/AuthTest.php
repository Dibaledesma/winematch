<?php

declare(strict_types=1);

namespace WineMatch\Tests;

use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WineMatch\Auth;
use WineMatch\Db;

final class AuthTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = Db::memory();
    }

    public function testRegistrarGuardaHashYNuncaLaContrasena(): void
    {
        $u = Auth::registrar($this->pdo, 'Diego', 'Diego@Ejemplo.test', 'vino tinto de la casa');
        $stmt = $this->pdo->prepare('SELECT password_hash FROM Usuarios WHERE id = ?');
        $stmt->execute([$u['id']]);
        $hash = $stmt->fetchColumn();

        $this->assertSame('diego@ejemplo.test', $u['email']);
        $this->assertSame('usuario', $u['rol']);
        $this->assertTrue(password_verify('vino tinto de la casa', $hash));
        $this->assertNotSame('vino tinto de la casa', $hash);
        $this->assertStringStartsWith('$2y$', $hash);
    }

    public function testPasswordLargaConEspaciosPermitida(): void
    {
        $u = Auth::registrar($this->pdo, 'Ana', 'ana@ejemplo.test', 'correct horse battery staple');
        $this->assertNotNull(Auth::login($this->pdo, 'ana@ejemplo.test', 'correct horse battery staple'));
        $this->assertSame('ana@ejemplo.test', $u['email']);
    }

    #[DataProvider('provesInvalidas')]
    public function testRegistroInvalidoSeRechaza(string $nombre, string $email, string $password, string $mensaje): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($mensaje);
        Auth::registrar($this->pdo, $nombre, $email, $password);
    }

    public static function provesInvalidas(): array
    {
        return [
            'nombre vacío' => ['', 'a@b.test', 'unaclave-larga', 'entre 2 y 80'],
            'email inválido' => ['Ana', 'no-es-email', 'unaclave-larga', 'no es válido'],
            'password corta' => ['Ana', 'a@b.test', 'corta', 'al menos 8'],
            'password común' => ['Ana', 'a@b.test', 'password', 'demasiado común'],
            'password enorme' => ['Ana', 'a@b.test', str_repeat('a', 73), 'no puede superar 72'],
        ];
    }

    public function testEmailRepetidoSeRechaza(): void
    {
        Auth::registrar($this->pdo, 'Ana', 'ana@ejemplo.test', 'una-clave-fuerte');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Ya hay una cuenta');
        Auth::registrar($this->pdo, 'Ana María', 'ANA@ejemplo.test', 'otra-clave-fuerte');
    }

    public function testLoginCorrectoDevuelveUsuarioSinHash(): void
    {
        Auth::registrar($this->pdo, 'Diego', 'diego@ejemplo.test', 'vino-de-casa');
        $u = Auth::login($this->pdo, 'DIEGO@ejemplo.test', 'vino-de-casa');
        $this->assertNotNull($u);
        $this->assertSame('Diego', $u['nombre']);
        $this->assertArrayNotHasKey('password_hash', $u);
    }

    #[DataProvider('credencialesInvalidas')]
    public function testLoginInvalidoDevuelveNull(string $email, string $password): void
    {
        Auth::registrar($this->pdo, 'Diego', 'diego@ejemplo.test', 'vino-de-casa');
        $this->assertNull(Auth::login($this->pdo, $email, $password));
    }

    public static function credencialesInvalidas(): array
    {
        return [
            'contraseña mal' => ['diego@ejemplo.test', 'vino-de-casa-x'],
            'usuario inexistente' => ['nadie@ejemplo.test', 'vino-de-casa'],
            'email mal formado' => ['no-es-email', 'vino-de-casa'],
            'inyección SQL' => ["diego@ejemplo.test' OR '1'='1", 'x'],
            'vacío' => ['', ''],
        ];
    }
}
