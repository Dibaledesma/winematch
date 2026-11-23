<?php

declare(strict_types=1);

namespace WineMatch;

/** Salida segura y render de plantillas. Todo texto pasa por e(). */
final class Html
{
    public static function e(mixed $valor): string
    {
        return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** @param array<string, mixed> $vars */
    public static function render(string $plantilla, array $vars = []): string
    {
        $layout = dirname(__DIR__) . '/templates/layout.php';
        $ruta = dirname(__DIR__) . "/templates/{$plantilla}.php";
        if (!is_file($ruta)) {
            throw new \RuntimeException("Plantilla inexistente: {$plantilla}");
        }
        $contenido = self::capturar($ruta, $vars);
        return self::capturar($layout, ['contenido' => $contenido] + $vars);
    }

    /** @param array<string, mixed> $vars */
    private static function capturar(string $ruta, array $vars): string
    {
        extract($vars, EXTR_SKIP);
        ob_start();
        include $ruta;
        return (string) ob_get_clean();
    }

    public static function flash(string $tipo, string $texto): void
    {
        $_SESSION['_flash'][] = ['tipo' => $tipo, 'texto' => $texto];
    }

    /** @return list<array{tipo: string, texto: string}> */
    public static function flashes(): array
    {
        $lista = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $lista;
    }

    public static function redirigir(string $ruta, int $codigo = 303): never
    {
        header('Location: ' . $ruta, true, $codigo);
        exit;
    }

    /** Cabeceras comunes a toda respuesta HTML. */
    public static function cabecerasHtml(): void
    {
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Security-Policy: default-src \'self\'; style-src \'self\'; img-src \'self\' data:; frame-ancestors \'none\'; base-uri \'none\'; form-action \'self\'');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
        header('X-Frame-Options: DENY');
        header('Cache-Control: no-store');
    }
}
