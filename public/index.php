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

/**
 * Guarda anti-CSRF para todo POST. Acepta solo peticiones del mismo origen, con
 * la senal mas fiable disponible: Fetch Metadata (Sec-Fetch-Site, no falsificable
 * por JavaScript) > Origin exacto > Referer del mismo origen. Sin ninguna: 403.
 */
function origenValido(): bool
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return true;
    }
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host === '') {
        return false;
    }
    $fetchSite = strtolower($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '');
    if ($fetchSite !== '') {
        return $fetchSite === 'same-origin';
    }
    $origen = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origen !== '') {
        return mismoHost($origen, $host);
    }
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if ($referer !== '') {
        return mismoHost($referer, $host);
    }
    return false;
}

/** Comprueba si una URL (Origin o Referer) apunta al mismo host:puerto que la peticion. */
function mismoHost(string $url, string $host): bool
{
    $u = parse_url($url);
    if (!is_array($u) || !isset($u['host'])) {
        return false;
    }
    $conPuerto = $u['host'] . (isset($u['port']) ? ':' . $u['port'] : '');
    $sinPuerto = $u['host'];
    $hostSinPuerto = preg_replace('/:\d+$/', '', $host) ?? $host;
    // Con puerto explicito debe coincidir; sin puerto solo si la peticion tampoco lo lleva
    // (80/443 implicitos).
    return $conPuerto === $host || (!isset($u['port']) && $sinPuerto === $hostSinPuerto);
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
// Un byte nulo en la URL hace que realpath() lance excepcion: se corta antes.
$real = str_contains($uri, "\0") ? false : realpath(__DIR__ . $uri);
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
        Html::cabecerasHtml();
        echo Html::render('restaurantes', ['restaurantes' => Maridaje::restaurantes($pdo), 'titulo' => 'Restaurantes', 'fondo' => 'index'] + $vars);
        break;

    case 'GET /registro':
        if ($usuario !== null) {
            Html::redirigir('/');
        }
        $valores = $_SESSION['_formulario'] ?? ['nombre' => '', 'email' => ''];
        unset($_SESSION['_formulario']);
        Html::cabecerasHtml();
        echo Html::render('registro', ['titulo' => 'Crear cuenta', 'valores' => $valores, 'fondo' => 'registro'] + $vars);
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
        echo Html::render('login', ['titulo' => 'Entrar', 'email' => $previo, 'fondo' => 'login'] + $vars);
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
            echo Html::render('carta', ['restaurante' => $restaurante, 'platos' => Maridaje::carta($pdo, (int) $restaurante['id']), 'vinos' => Maridaje::vinos($pdo), 'titulo' => $restaurante['nombre'], 'fondo' => 'carta'] + $vars);
            break;
        }
        // GET /restaurante/{slug}/maridar?vino=N&plato=slug : el formulario "Buscar Vino" de la v1.
        if ($metodo === 'GET' && preg_match('#^/restaurante/([a-z0-9-]+)/maridar$#', $uri, $m) === 1) {
            $usuario = Auth::requiereLogin($pdo);
            if (Maridaje::restaurantePorSlug($pdo, $m[1]) === null || !isset($_GET['plato'])) {
                pagina404();
            }
            $plato = Maridaje::platoPorSlug($pdo, $m[1], (string) $_GET['plato']);
            if ($plato === null) {
                pagina404();
            }
            $vino = isset($_GET['vino']) && ctype_digit((string) $_GET['vino']) ? '?vino=' . (int) $_GET['vino'] : '';
            Html::redirigir("/restaurante/{$m[1]}/{$plato['slug']}{$vino}");
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
            // Modo "marida tu": el comensal ya tiene un vino y quiere saber si hace match.
            $eleccion = null;
            if (isset($_GET['vino']) && ctype_digit((string) $_GET['vino'])) {
                try {
                    $eleccion = Maridaje::comprobar($pdo, $plato, (int) $_GET['vino']);
                } catch (\InvalidArgumentException) {
                    $eleccion = null;
                }
            }
            Html::cabecerasHtml();
            echo Html::render('resultado', [
                'plato' => $plato, 'rec' => $rec, 'tipos' => $tipos, 'tipoElegido' => $tipoElegido,
                'opiniones' => Maridaje::opiniones($pdo, $rec['vino']['id']),
                'mia' => Opiniones::mia($pdo, (int) $usuario['id'], $rec['vino']['id']),
                'vinos' => Maridaje::vinos($pdo),
                'eleccion' => $eleccion,
                'fondo' => 'resultado',
                'titulo' => $plato['nombre'] . ' · ' . $rec['botella'],
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
        if ($metodo === 'POST' && $uri === '/admin/especie') {
            Auth::requiereAdmin($pdo);
            try {
                Admin::crearEspecie($pdo, (int) ($_POST['restaurante_id'] ?? 0), (string) ($_POST['nombre'] ?? ''));
                Html::flash('ok', 'Especia añadida.');
            } catch (\InvalidArgumentException $e) {
                Html::flash('error', $e->getMessage());
            }
            Html::redirigir('/admin');
        }
        if ($metodo === 'POST' && $uri === '/admin/plato') {
            Auth::requiereAdmin($pdo);
            try {
                Admin::crearPlato($pdo, (int) ($_POST['restaurante_id'] ?? 0), (string) ($_POST['nombre'] ?? ''), (string) ($_POST['tipo'] ?? ''), (string) ($_POST['descripcion'] ?? ''), (int) ($_POST['especie_id'] ?? 0));
                Html::flash('ok', 'Plato añadido a la carta.');
            } catch (\InvalidArgumentException $e) {
                Html::flash('error', $e->getMessage());
            }
            Html::redirigir('/admin');
        }
        if ($metodo === 'POST' && $uri === '/admin/recomendacion') {
            Auth::requiereAdmin($pdo);
            try {
                Admin::crearRecomendacion($pdo, (int) ($_POST['vino_id'] ?? 0), (int) ($_POST['comida_id'] ?? 0), (string) ($_POST['bodega'] ?? ''), (string) ($_POST['botella'] ?? ''), (string) ($_POST['mensaje'] ?? ''));
                Html::flash('ok', 'Maridaje de la casa creado.');
            } catch (\InvalidArgumentException $e) {
                Html::flash('error', $e->getMessage());
            }
            Html::redirigir('/admin');
        }
        pagina404();
}
