<?php

declare(strict_types=1);

/**
 * Front controller: sesion con flags, guarda de origen en todo POST,
 * enrutado y manejo de errores. Docroot = public/.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use WineMatch\Admin;
use WineMatch\Auth;
use WineMatch\Config;
use WineMatch\Db;
use WineMatch\Html;
use WineMatch\Maridaje;
use WineMatch\Opiniones;

$config = new Config();

if ($config->appEnv === 'production') {
    ini_set('display_errors', '0');
}

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $config->secureCookies,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_name('winematch');
session_start();

$pdo = Db::connect($config);

set_exception_handler(static function (\Throwable $e) use ($config): void {
    error_log($e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    Html::cabecerasHtml();
    if ($config->appEnv === 'production') {
        echo '<!DOCTYPE html><html lang="es"><meta charset="utf-8"><title>Error</title><h1>Algo ha fallado</h1><p>Vuelve a intentarlo en unos minutos.</p></html>';
    } else {
        echo '<!DOCTYPE html><html lang="es"><meta charset="utf-8"><title>Error</title><pre>' . Html::e((string) $e) . '</pre></html>';
    }
    exit;
});

/** Guarda de origen: todo POST debe venir de este mismo sitio (CSRF). */
function origenValido(): bool
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return true;
    }
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $origen = $_SERVER['HTTP_ORIGIN'] ?? '';
    return $host !== '' && ($origen === 'http://' . $host || $origen === 'https://' . $host);
}

function pagina404(): never
{
    http_response_code(404);
    Html::cabecerasHtml();
    echo Html::render('error', ['codigo' => 404, 'titulo' => 'No encontrado', 'mensaje' => 'La página que buscas no existe.', 'usuario' => null]);
    exit;
}

$ruta = $_SERVER['REQUEST_URI'] ?? '/';
$uri = rawurldecode((string) parse_url($ruta, PHP_URL_PATH));
$metodo = $_SERVER['REQUEST_METHOD'];

// Servidor integrado: los estaticos de public/static los sirve el propio servidor.
$real = realpath(__DIR__ . $uri);
if ($metodo === 'GET' && $real !== false && str_starts_with($real, __DIR__ . DIRECTORY_SEPARATOR . 'static' . DIRECTORY_SEPARATOR)) {
    return false;
}

if ($metodo === 'POST' && !origenValido()) {
    http_response_code(403);
    Html::cabecerasHtml();
    echo '<!DOCTYPE html><html lang="es"><meta charset="utf-8"><title>403</title><h1>403</h1><p>Solicitud rechazada.</p></html>';
    exit;
}

$usuario = Auth::actual($pdo);
$vars = ['usuario' => $usuario, 'flashes' => Html::flashes()];

switch ("{$metodo} {$uri}") {
    case 'GET /':
        $usuario = Auth::requiereLogin($pdo);
        $restaurantes = $pdo->query('SELECT id, nombre, slug, descripcion FROM Restaurante ORDER BY nombre')->fetchAll();
        Html::cabecerasHtml();
        echo Html::render('restaurantes', ['restaurantes' => $restaurantes, 'titulo' => 'Restaurantes'] + $vars);
        break;

    case 'GET /registro':
        if ($usuario !== null) {
            Html::redirigir('/');
        }
        $valores = $_SESSION['_formulario'] ?? ['nombre' => '', 'email' => ''];
        unset($_SESSION['_formulario']);
        Html::cabecerasHtml();
        echo Html::render('registro', ['titulo' => 'Crear cuenta', 'valores' => $valores] + $vars);
        break;

    case 'POST /registro':
        $valores = ['nombre' => (string) ($_POST['nombre'] ?? ''), 'email' => (string) ($_POST['email'] ?? '')];
        try {
            $nuevo = Auth::registrar($pdo, $valores['nombre'], $valores['email'], (string) ($_POST['password'] ?? ''));
        } catch (\InvalidArgumentException $e) {
            Html::flash('error', $e->getMessage());
            $_SESSION['_formulario'] = $valores;
            Html::redirigir('/registro');
        }
        Auth::iniciarSesion($nuevo);
        Html::flash('ok', 'Cuenta creada. Elige un restaurante para empezar.');
        Html::redirigir('/');

    case 'GET /login':
        if ($usuario !== null) {
            Html::redirigir('/');
        }
        $previo = $_SESSION['_formulario']['email'] ?? '';
        unset($_SESSION['_formulario']);
        Html::cabecerasHtml();
        echo Html::render('login', ['titulo' => 'Entrar', 'email' => $previo] + $vars);
        break;

    case 'POST /login':
        $email = (string) ($_POST['email'] ?? '');
        $nuevo = Auth::login($pdo, $email, (string) ($_POST['password'] ?? ''));
        if ($nuevo === null) {
            Html::flash('error', 'Correo o contraseña incorrectos.');
            $_SESSION['_formulario'] = ['email' => $email];
            Html::redirigir('/login');
        }
        Auth::iniciarSesion($nuevo);
        Html::redirigir('/');

    case 'POST /salir':
        Auth::cerrarSesion();
        Html::redirigir('/login');

    default:
        // /restaurante/{slug} : la carta.
        if ($metodo === 'GET' && preg_match('#^/restaurante/([a-z0-9-]+)$#', $uri, $m) === 1) {
            $usuario = Auth::requiereLogin($pdo);
            $restaurante = Maridaje::restaurantePorSlug($pdo, $m[1]);
            if ($restaurante === null) {
                pagina404();
            }
            Html::cabecerasHtml();
            echo Html::render('carta', ['restaurante' => $restaurante, 'platos' => Maridaje::carta($pdo, (int) $restaurante['id']), 'titulo' => $restaurante['nombre']] + $vars);
            break;
        }
        // /restaurante/{slug}/{plato}[?tipo=N] : el maridador.
        if ($metodo === 'GET' && preg_match('#^/restaurante/([a-z0-9-]+)/([a-z0-9-]+)$#', $uri, $m) === 1) {
            $usuario = Auth::requiereLogin($pdo);
            $plato = Maridaje::platoPorSlug($pdo, $m[1], $m[2]);
            if ($plato === null) {
                pagina404();
            }
            $tipos = Maridaje::tiposVino($pdo);
            $tipoElegido = null;
            if (isset($_GET['tipo']) && ctype_digit((string) $_GET['tipo'])) {
                $id = (int) $_GET['tipo'];
                foreach ($tipos as $t) {
                    if ((int) $t['id'] === $id) {
                        $tipoElegido = $id;
                    }
                }
            }
            $rec = Maridaje::recomendar($pdo, $plato, $tipoElegido);
            Maridaje::registrarConsulta($pdo, (int) $usuario['id'], $rec['recomendacion_id']);
            Html::cabecerasHtml();
            echo Html::render('resultado', [
                'plato' => $plato, 'rec' => $rec, 'tipos' => $tipos, 'tipoElegido' => $tipoElegido,
                'opiniones' => Maridaje::opiniones($pdo, $rec['vino']['id']),
                'mia' => Opiniones::mia($pdo, (int) $usuario['id'], $rec['vino']['id']),
                'titulo' => $plato['nombre'] . ' · ' . $rec['vino']['nombre'],
            ] + $vars);
            break;
        }
        // POST /restaurante/{slug}/{plato}/opinar : valorar o comentar el vino recomendado.
        if ($metodo === 'POST' && preg_match('#^/restaurante/([a-z0-9-]+)/([a-z0-9-]+)/opinar$#', $uri, $m) === 1) {
            $usuario = Auth::requiereLogin($pdo);
            $plato = Maridaje::platoPorSlug($pdo, $m[1], $m[2]);
            if ($plato === null) {
                pagina404();
            }
            $volver = "/restaurante/{$m[1]}/{$m[2]}" . (isset($_GET['tipo']) && ctype_digit((string) $_GET['tipo']) ? '?tipo=' . (int) $_GET['tipo'] : '');
            $puntuacion = isset($_POST['puntuacion']) && $_POST['puntuacion'] !== '' && ctype_digit((string) $_POST['puntuacion']) ? (int) $_POST['puntuacion'] : null;
            try {
                Opiniones::guardar($pdo, (int) $usuario['id'], (int) ($_POST['vino_id'] ?? 0), $puntuacion, isset($_POST['comentario']) ? (string) $_POST['comentario'] : null);
                Html::flash('ok', 'Gracias por tu opinión.');
            } catch (\InvalidArgumentException $e) {
                Html::flash('error', $e->getMessage());
            }
            Html::redirigir($volver);
        }
        // /admin : panel de administracion (solo rol admin).
        if ($metodo === 'GET' && $uri === '/admin') {
            $usuario = Auth::requiereAdmin($pdo);
            Html::cabecerasHtml();
            echo Html::render('admin', [
                'titulo' => 'Administración',
                'usuarios' => Admin::usuarios($pdo),
                'comentarios' => Admin::comentarios($pdo),
                'valoraciones' => Admin::valoraciones($pdo),
                'platos' => Admin::platos($pdo),
                'vinos' => Admin::vinos($pdo),
                'especies' => Admin::especies($pdo),
                'restaurantes' => Maridaje::restaurantes($pdo),
            ] + $vars);
            break;
        }
        if ($metodo === 'POST' && $uri === '/admin/plato') {
            Auth::requiereAdmin($pdo);
            try {
                Admin::crearPlato($pdo, (int) ($_POST['restaurante_id'] ?? 0), (string) ($_POST['nombre'] ?? ''), (string) ($_POST['descripcion'] ?? ''), (int) ($_POST['especie_id'] ?? 0));
                Html::flash('ok', 'Plato añadido a la carta.');
            } catch (\InvalidArgumentException $e) {
                Html::flash('error', $e->getMessage());
            }
            Html::redirigir('/admin');
        }
        if ($metodo === 'POST' && $uri === '/admin/recomendacion') {
            Auth::requiereAdmin($pdo);
            try {
                Admin::crearRecomendacion($pdo, (int) ($_POST['vino_id'] ?? 0), (int) ($_POST['comida_id'] ?? 0), (string) ($_POST['mensaje'] ?? ''));
                Html::flash('ok', 'Regla de maridaje creada.');
            } catch (\InvalidArgumentException $e) {
                Html::flash('error', $e->getMessage());
            }
            Html::redirigir('/admin');
        }
        pagina404();
}
