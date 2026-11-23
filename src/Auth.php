<?php

declare(strict_types=1);

namespace WineMatch;

use PDO;

/**
 * Registro, login y sesion. Consultas siempre preparadas, contrasenas
 * siempre con hash, y respuestas que no desvelan si un email existe.
 */
final class Auth
{
    public const MIN_PASSWORD = 8;

    /** Hash bcrypt real de una contrasena basura: iguala el tiempo de un login fallido. */
    private const HASH_CEBBO = '$2y$10$/GBlgFFtAZU2zGjxgmIGzeJY2XRC6SWcBdbnWhIsZWaVpowHWvuA2';

    /** @return array<string, mixed>|null el usuario o null si no existe o la clave no coincide */
    public static function login(PDO $pdo, string $email, string $password): ?array
    {
        if (self::emailValido($email) === false) {
            return null;
        }
        $stmt = $pdo->prepare('SELECT id, nombre, email, rol, password_hash FROM Usuarios WHERE email = ?');
        $stmt->execute([strtolower($email)]);
        $usuario = $stmt->fetch();
        if ($usuario === false) {
            // Mismo trabajo que un login fallido: no se filtra si el email existe.
            password_verify($password, self::HASH_CEBBO);
            return null;
        }
        if (password_verify($password, $usuario['password_hash']) === false) {
            return null;
        }
        unset($usuario['password_hash']);
        return $usuario;
    }

    /** @return array{id: int, nombre: string, email: string, rol: string} usuario creado */
    public static function registrar(PDO $pdo, string $nombre, string $email, string $password): array
    {
        $nombre = trim($nombre);
        $email = strtolower(trim($email));
        self::validarRegistro($pdo, $nombre, $email, $password);

        $stmt = $pdo->prepare('INSERT INTO Usuarios (nombre, email, password_hash) VALUES (?, ?, ?)');
        $stmt->execute([$nombre, $email, password_hash($password, PASSWORD_DEFAULT)]);

        return ['id' => (int) $pdo->lastInsertId(), 'nombre' => $nombre, 'email' => $email, 'rol' => 'usuario'];
    }

    /** @throws \InvalidArgumentException con mensaje que se puede mostrar al usuario */
    private static function validarRegistro(PDO $pdo, string $nombre, string $email, string $password): void
    {
        $longitud = mb_strlen($nombre);
        if ($longitud < 2 || $longitud > 80) {
            throw new \InvalidArgumentException('El nombre debe tener entre 2 y 80 caracteres.');
        }
        if (self::emailValido($email) === false || strlen($email) > 190) {
            throw new \InvalidArgumentException('El correo electrónico no es válido.');
        }
        if (strlen($password) < self::MIN_PASSWORD) {
            throw new \InvalidArgumentException('La contraseña debe tener al menos ' . self::MIN_PASSWORD . ' caracteres.');
        }
        if (strlen($password) > 72) {
            throw new \InvalidArgumentException('La contraseña no puede superar 72 caracteres.');
        }
        if (in_array(strtolower($password), ['password', 'contrasena', '12345678', 'qwerty12', '123456789'], true)) {
            throw new \InvalidArgumentException('La contraseña es demasiado común. Elige otra.');
        }
        $stmt = $pdo->prepare('SELECT 1 FROM Usuarios WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() !== false) {
            throw new \InvalidArgumentException('Ya hay una cuenta con ese correo electrónico.');
        }
    }

    public static function emailValido(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /** Inicia la sesion del usuario ya autenticado (rotando el id de sesion). */
    public static function iniciarSesion(array $usuario): void
    {
        session_regenerate_id(true);
        $_SESSION['uid'] = (int) $usuario['id'];
    }

    public static function cerrarSesion(): void
    {
        $_SESSION = [];
        $parametros = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $parametros['path'],
            'domain' => $parametros['domain'],
            'secure' => $parametros['secure'],
            'httponly' => $parametros['httponly'],
            'samesite' => $parametros['samesite'] ?? 'Lax',
        ]);
        session_destroy();
    }

    /** @return array<string, mixed>|null usuario de la sesion actual */
    public static function actual(PDO $pdo): ?array
    {
        $uid = $_SESSION['uid'] ?? null;
        if ($uid === null) {
            return null;
        }
        $stmt = $pdo->prepare('SELECT id, nombre, email, rol FROM Usuarios WHERE id = ?');
        $stmt->execute([(int) $uid]);
        $usuario = $stmt->fetch();
        return $usuario === false ? null : $usuario;
    }

    public static function requiereLogin(PDO $pdo): array
    {
        $usuario = self::actual($pdo);
        if ($usuario === null) {
            Html::redirigir('/login');
        }
        return $usuario;
    }

    public static function requiereAdmin(PDO $pdo): array
    {
        $usuario = self::requiereLogin($pdo);
        if ($usuario['rol'] !== 'admin') {
            http_response_code(403);
            Html::cabecerasHtml();
            echo Html::render('error', ['titulo' => 'Acceso denegado', 'mensaje' => 'No tienes permiso para entrar aquí.', 'usuario' => $usuario]);
            exit;
        }
        return $usuario;
    }
}
