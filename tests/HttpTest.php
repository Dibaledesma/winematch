<?php

declare(strict_types=1);

namespace WineMatch\Tests;

use PHPUnit\Framework\TestCase;

/** Pruebas de extremo a extremo contra el servidor integrado de PHP. */
final class HttpTest extends TestCase
{
    private Http $h;

    protected function setUp(): void
    {
        $this->h = new Http();
    }

    public function testLoginSeSirveConCabecerasDuras(): void
    {
        $r = $this->h->peticion('GET', '/login');
        $this->assertSame(200, $r['status']);
        $this->assertStringContainsString('<h1>Entrar</h1>', $r['body']);
        $this->assertStringContainsString("default-src 'self'", (string) $this->h->cabecera('content-security-policy'));
        $this->assertSame('nosniff', $this->h->cabecera('x-content-type-options'));
        $this->assertSame('no-referrer', $this->h->cabecera('referrer-policy'));
        $this->assertSame('no-store', $this->h->cabecera('cache-control'));
        $this->assertStringStartsWith('text/html; charset=utf-8', (string) $this->h->cabecera('content-type'));
    }

    public function testRaizSinSesionRedirigeALogin(): void
    {
        $r = $this->h->peticion('GET', '/');
        $this->assertSame(303, $r['status']);
        $this->assertSame('/login', $this->h->cabecera('location'));
    }

    public function testPostSinOrigenSeRechaza(): void
    {
        $r = $this->h->peticion('POST', '/login', ['email' => Servidor::EMAIL_ADMIN, 'password' => 'lo-que-sea']);
        $this->assertSame(403, $r['status']);
    }

    public function testGuardaAceptaSenalesMismoOrigenYRechazaAjenas(): void
    {
        $ok = ['email' => Servidor::EMAIL_ADMIN, 'password' => Servidor::$adminPass];
        $casos = [
            'Sec-Fetch-Site' => ['same-origin', 303],
            'Referer' => [$this->h->origen() . '/login', 303],
        ];
        foreach ($casos as $cabecera => [$valor, $esperado]) {
            $h = new Http();
            $this->assertSame($esperado, $h->peticion('POST', '/login', $ok, [$cabecera => $valor])['status'], $cabecera);
        }
        // Fetch Metadata manda sobre el resto: cross-site con Origin bueno sigue siendo 403.
        $r = $this->h->peticion('POST', '/login', $ok, ['Sec-Fetch-Site' => 'cross-site', 'Origin' => $this->h->origen()]);
        $this->assertSame(403, $r['status']);
        $r = $this->h->peticion('POST', '/login', $ok, ['Referer' => 'https://evil.example/login']);
        $this->assertSame(403, $r['status']);
    }

    public function testPostConOrigenAjenoSeRechaza(): void
    {
        $r = $this->h->peticion('POST', '/login', ['email' => Servidor::EMAIL_ADMIN, 'password' => 'x'], ['Origin' => 'https://evil.example']);
        $this->assertSame(403, $r['status']);
    }

    public function testLoginValidoCreaSesionConFlags(): void
    {
        $r = $this->h->peticion('POST', '/login', ['email' => Servidor::EMAIL_ADMIN, 'password' => Servidor::$adminPass], ['Origin' => $this->h->origen()]);
        $this->assertSame(303, $r['status']);
        $this->assertSame('/', $this->h->cabecera('location'));
        $cookie = implode("\n", $this->h->cabeceras['set-cookie'] ?? []);
        $this->assertStringContainsString('HttpOnly', $cookie);
        $this->assertStringContainsString('SameSite=Lax', $cookie);

        $inicio = $this->h->peticion('GET', '/');
        $this->assertSame(200, $inicio['status']);
        $this->assertStringContainsString('Mas Guasón', $inicio['body']);
        $this->assertStringContainsString('href="/admin"', $inicio['body']);
    }

    public function testLoginFallidoNoDistingueEmailInexistenteDeClaveMal(): void
    {
        $casos = [
            ['email' => Servidor::EMAIL_ADMIN, 'password' => 'clave-incorrecta'],
            ['email' => 'nadie@ejemplo.test', 'password' => 'clave-incorrecta'],
        ];
        $textos = [];
        foreach ($casos as $caso) {
            $h = new Http();
            $r = $h->peticion('POST', '/login', $caso, ['Origin' => $h->origen()]);
            $this->assertSame(303, $r['status']);
            $this->assertSame('/login', $h->cabecera('location'));
            $cuerpo = $h->peticion('GET', '/login')['body'];
            $this->assertSame(303, $h->peticion('GET', '/')['status']);
            // El aviso es identico y no menciona si el correo existe.
            if (preg_match('/<div class="aviso aviso-error" role="status">(.*?)<\/div>/', $cuerpo, $m) !== 1) {
                $this->fail('No se mostro aviso de error');
            }
            $textos[] = $m[1];
        }
        $this->assertSame($textos[0], $textos[1]);
        $this->assertSame('Correo o contraseña incorrectos.', $textos[0]);
    }

    public function testInyeccionSqlEnEmailEsTextoNormal(): void
    {
        $r = $this->h->peticion('POST', '/login', ['email' => "' OR '1'='1' --", 'password' => 'x'], ['Origin' => $this->h->origen()]);
        $this->assertSame(303, $r['status']);
        $this->assertSame('/login', $this->h->cabecera('location'));
        $despues = $this->h->peticion('GET', '/');
        $this->assertSame(303, $despues['status'], 'La inyeccion no debe iniciar sesion');
        $this->assertSame('/login', $this->h->cabecera('location'));
    }

    public function testRegistroGuardaHashYNoContrasenaEnClaro(): void
    {
        $this->h->registrarYEntrar('nuevo@ejemplo.test', 'una-clave-muy-larga');
        $hash = Servidor::pdo()->query("SELECT password_hash FROM Usuarios WHERE email = 'nuevo@ejemplo.test'")->fetchColumn();
        $this->assertNotFalse(password_verify('una-clave-muy-larga', $hash));
        $this->assertNotSame('una-clave-muy-larga', $hash);
        $this->assertSame(200, $this->h->peticion('GET', '/')['status']);
    }

    public function testRegistroConClaveCortaVuelveConAviso(): void
    {
        $r = $this->h->peticion('POST', '/registro', ['nombre' => 'Ana', 'email' => 'corta@ejemplo.test', 'password' => 'corta'], ['Origin' => $this->h->origen()]);
        $this->assertSame(303, $r['status']);
        $this->assertSame('/registro', $this->h->cabecera('location'));
        $formulario = $this->h->peticion('GET', '/registro');
        $this->assertStringContainsString('al menos 8 caracteres', $formulario['body']);
        $this->assertStringContainsString('value="corta@ejemplo.test"', $formulario['body']);
    }

    public function testXssEnNombreSeEscapaAlMostrar(): void
    {
        $this->h->registrarYEntrar('xss@ejemplo.test');
        Servidor::pdo()->exec("UPDATE Usuarios SET nombre = '<script>alert(1)</script>' WHERE email = 'xss@ejemplo.test'");
        $inicio = $this->h->peticion('GET', '/');
        $this->assertStringNotContainsString('<script>alert(1)</script>', $inicio['body']);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $inicio['body']);
    }

    public function testRutasInexistentesDevuelven404Identico(): void
    {
        $a = $this->h->peticion('GET', '/no-existe');
        $b = $this->h->peticion('GET', '/tampoco-existe-987');
        $this->assertSame(404, $a['status']);
        $this->assertSame(404, $b['status']);
        $this->assertSame($a['body'], $b['body']);
    }

    public function testCartaYMaridajeDeUnPlato(): void
    {
        $this->h->registrarYEntrar('come@ejemplo.test');
        $carta = $this->h->peticion('GET', '/restaurante/mas-guason');
        $this->assertSame(200, $carta['status']);
        $this->assertStringContainsString('Crema catalana', $carta['body']);
        $this->assertStringContainsString('href="/restaurante/mas-guason/crema-catalana"', $carta['body']);

        $resultado = $this->h->peticion('GET', '/restaurante/mas-guason/crema-catalana');
        $this->assertSame(200, $resultado['status']);
        $this->assertStringContainsString('Maridaje de la casa', $resultado['body']);
        $this->assertStringContainsString('Camins del Priorat', $resultado['body']);
        $this->assertStringContainsString('Parés Baltà', $resultado['body']);

        // El tipo de vino pedido se respeta cuando hay maridaje: carpaccio con tinto.
        $tinto = (int) Servidor::pdo()->query("SELECT id FROM TipoVino WHERE nombre = 'Tinto'")->fetchColumn();
        $conTipo = $this->h->peticion('GET', '/restaurante/mas-guason/carpaccio-de-pie-de-cerdo?tipo=' . $tinto);
        $this->assertSame(200, $conTipo['status']);
        $this->assertStringContainsString('Pas Curtei', $conTipo['body']);
    }

    public function testElFormularioBuscarVinoDeLaV1LlevaAlResultado(): void
    {
        $this->h->registrarYEntrar('busca@ejemplo.test');
        $carta = $this->h->peticion('GET', '/restaurante/mas-guason');
        $this->assertStringContainsString('Selecciona tu maridaje', $carta['body']);
        $this->assertStringContainsString('Buscar Vino', $carta['body']);
        $this->assertStringContainsString('Bodegas colaboradoras', $carta['body']);
        $this->assertStringContainsString('/static/img/restaurante-mas-guason.webp', $carta['body']);

        $calcari = (int) Servidor::pdo()->query("SELECT id FROM Vino WHERE nombre = 'Calcari'")->fetchColumn();
        $r = $this->h->peticion('GET', "/restaurante/mas-guason/maridar?vino={$calcari}&plato=crema-catalana");
        $this->assertSame(303, $r['status']);
        $this->assertSame("/restaurante/mas-guason/crema-catalana?vino={$calcari}", $this->h->cabecera('location'));

        // Un plato de otro restaurante no se puede maridar desde esta carta.
        $r = $this->h->peticion('GET', "/restaurante/mas-guason/maridar?vino={$calcari}&plato=tataki-de-atun");
        $this->assertSame(404, $r['status']);
    }

    public function testTipoDeVinoInvalidoSeIgnoraSinError(): void
    {
        $this->h->registrarYEntrar('tipo@ejemplo.test');
        foreach (['999', 'abc', '-1', '1 OR 1=1'] as $valor) {
            $r = $this->h->peticion('GET', '/restaurante/mas-guason/crema-catalana?tipo=' . rawurlencode($valor));
            $this->assertSame(200, $r['status'], "tipo={$valor}");
        }
    }

    public function testLaConsultaQuedaRegistradaAlVerUnMaridaje(): void
    {
        $antes = (int) Servidor::pdo()->query('SELECT COUNT(*) FROM Consulta')->fetchColumn();
        $this->h->registrarYEntrar('historial@ejemplo.test');
        $this->h->peticion('GET', '/restaurante/quatre-lents/tataki-de-atun');
        $despues = (int) Servidor::pdo()->query('SELECT COUNT(*) FROM Consulta')->fetchColumn();
        $this->assertSame($antes + 1, $despues);
    }

    public function testRestauranteOPlatoInexistenteDan404Neutro(): void
    {
        $this->h->registrarYEntrar('cuatro@ejemplo.test');
        $base = $this->h->peticion('GET', '/no-existe')['body'];
        foreach (['/restaurante/no-existe', '/restaurante/mas-guason/no-existe', '/restaurante/quatre-lents/crema-catalana'] as $ruta) {
            $r = $this->h->peticion('GET', $ruta);
            $this->assertSame(404, $r['status'], $ruta);
            $this->assertSame($base, $r['body'], $ruta);
        }
    }

    public function testSinSesionLaCartaRedirigeALogin(): void
    {
        $r = $this->h->peticion('GET', '/restaurante/mas-guason');
        $this->assertSame(303, $r['status']);
        $this->assertSame('/login', $this->h->cabecera('location'));
    }

    public function testUrlMalformadaConByteNuloNoProvocaError(): void
    {
        $anonimo = $this->h->peticion('GET', '/restaurante/%00');
        $this->assertSame(404, $anonimo['status'], 'Rechazo de sintaxis antes de login, nunca 500');

        $this->h->registrarYEntrar('nulo@ejemplo.test');
        $base = $this->h->peticion('GET', '/no-existe')['body'];
        foreach (['/restaurante/%00', '/restaurante/zzzzzzzzzzzzzzzzzz', '/restaurante/not-an-id'] as $ruta) {
            $r = $this->h->peticion('GET', $ruta);
            $this->assertSame(404, $r['status'], $ruta);
            $this->assertSame($base, $r['body'], $ruta);
        }
    }

    public function testXssEnNombreDePlatoSeEscapa(): void
    {
        $this->h->registrarYEntrar('plato@ejemplo.test');
        Servidor::pdo()->exec("UPDATE Comida SET nombre = '<script>alert(1)</script>' WHERE slug = 'crema-catalana'");
        $carta = $this->h->peticion('GET', '/restaurante/mas-guason');
        $this->assertStringNotContainsString('<script>alert(1)</script>', $carta['body']);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $carta['body']);
    }

    public function testMaridaTuDaUnVeredictoSegunElVinoElegido(): void
    {
        $this->h->registrarYEntrar('marida@ejemplo.test');
        $pdo = Servidor::pdo();
        $camins = (int) $pdo->query("SELECT id FROM Vino WHERE nombre = 'Camins del Priorat'")->fetchColumn();
        $calcari = (int) $pdo->query("SELECT id FROM Vino WHERE nombre = 'Calcari'")->fetchColumn();
        $base = '/restaurante/mas-guason/crema-catalana';

        $pagina = $this->h->peticion('GET', $base);
        $this->assertStringContainsString('¿Marida?', $pagina['body']);
        $this->assertStringNotContainsString('veredicto-', $pagina['body']);

        $match = $this->h->peticion('GET', $base . '?vino=' . $camins);
        $this->assertStringContainsString('Match de la casa', $match['body']);

        $sin = $this->h->peticion('GET', $base . '?vino=' . $calcari);
        $this->assertStringContainsString('Sin maridaje todavía', $sin['body']);

        $invalido = $this->h->peticion('GET', $base . '?vino=999');
        $this->assertSame(200, $invalido['status']);
        $this->assertStringNotContainsString('veredicto-', $invalido['body']);
    }

    private function entrarComoAdmin(): Http
    {
        $h = new Http();
        $r = $h->peticion('POST', '/login', ['email' => Servidor::EMAIL_ADMIN, 'password' => Servidor::$adminPass], ['Origin' => $h->origen()]);
        if ($r['status'] !== 303) {
            self::fail('Login admin falló: ' . $r['status']);
        }
        return $h;
    }

    public function testOpinarGuardaYSeMuestraEnElResultado(): void
    {
        $this->h->registrarYEntrar('opina@ejemplo.test');
        $tinto = (int) Servidor::pdo()->query("SELECT id FROM TipoVino WHERE nombre = 'Tinto'")->fetchColumn();
        $url = '/restaurante/mas-guason/carpaccio-de-pie-de-cerdo?tipo=' . $tinto;
        $pagina = $this->h->peticion('GET', $url);
        preg_match('/name="vino_id" value="(\d+)"/', $pagina['body'], $m);
        $vinoId = (int) $m[1];

        $r = $this->h->peticion('POST', '/restaurante/mas-guason/carpaccio-de-pie-de-cerdo/opinar?tipo=' . $tinto, ['vino_id' => $vinoId, 'puntuacion' => '4', 'comentario' => 'Muy buen maridaje'], ['Origin' => $this->h->origen()]);
        $this->assertSame(303, $r['status']);
        $this->assertSame($url, $this->h->cabecera('location'));

        $despues = $this->h->peticion('GET', $url);
        $this->assertStringContainsString('Muy buen maridaje', $despues['body']);
        $this->assertStringContainsString('(2 valoraciones)', $despues['body']);
    }

    public function testOpinarSinOrigenSeRechaza(): void
    {
        $this->h->registrarYEntrar('opina2@ejemplo.test');
        $r = $this->h->peticion('POST', '/restaurante/mas-guason/crema-catalana/opinar', ['vino_id' => 1, 'puntuacion' => '5']);
        $this->assertSame(403, $r['status']);
    }

    public function testOpinionInvalidaVuelveConAvisoYNoGuarda(): void
    {
        $this->h->registrarYEntrar('opina3@ejemplo.test');
        $antes = (int) Servidor::pdo()->query('SELECT COUNT(*) FROM Valoracion')->fetchColumn();
        $this->h->peticion('POST', '/restaurante/mas-guason/crema-catalana/opinar', ['vino_id' => 1, 'puntuacion' => '9', 'comentario' => ''], ['Origin' => $this->h->origen()]);
        $pagina = $this->h->peticion('GET', '/restaurante/mas-guason/crema-catalana');
        $this->assertStringContainsString('entre 1 y 5', $pagina['body']);
        $this->assertSame($antes, (int) Servidor::pdo()->query('SELECT COUNT(*) FROM Valoracion')->fetchColumn());
    }

    public function testXssEnComentarioSeEscapa(): void
    {
        $this->h->registrarYEntrar('opina4@ejemplo.test');
        $this->h->peticion('POST', '/restaurante/mas-guason/crema-catalana/opinar', ['vino_id' => 1, 'puntuacion' => '', 'comentario' => '<script>alert(1)</script>'], ['Origin' => $this->h->origen()]);
        $pagina = $this->h->peticion('GET', '/restaurante/mas-guason/crema-catalana');
        $this->assertStringNotContainsString('<script>alert(1)</script>', $pagina['body']);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $pagina['body']);
    }

    public function testElPanelAdminRequiereRolAdmin(): void
    {
        $this->h->registrarYEntrar('normal@ejemplo.test');
        $r = $this->h->peticion('GET', '/admin');
        $this->assertSame(403, $r['status']);
        $this->assertStringContainsString('Acceso denegado', $r['body']);

        $admin = $this->entrarComoAdmin();
        $panel = $admin->peticion('GET', '/admin');
        $this->assertSame(200, $panel['status']);
        $this->assertStringContainsString('Usuarios registrados', $panel['body']);
        $this->assertStringContainsString('normal@ejemplo.test', $panel['body']);
        $this->assertStringContainsString('Últimos comentarios', $panel['body']);
    }

    public function testAdminCargaUnaEspeciaUnPlatoYUnMaridaje(): void
    {
        $admin = $this->entrarComoAdmin();
        $pdo = Servidor::pdo();
        $restaurante = (int) $pdo->query("SELECT id FROM Restaurante WHERE slug = 'quatre-lents'")->fetchColumn();

        $r = $admin->peticion('POST', '/admin/especie', ['restaurante_id' => $restaurante, 'nombre' => 'Ahumados'], ['Origin' => $admin->origen()]);
        $this->assertSame(303, $r['status']);
        $especie = (int) $pdo->query("SELECT e.id FROM Especie e WHERE e.nombre = 'Ahumados' AND e.restaurante_id = {$restaurante}")->fetchColumn();
        $vino = (int) $pdo->query("SELECT id FROM Vino WHERE nombre = 'Jhana Rosat'")->fetchColumn();

        $r = $admin->peticion('POST', '/admin/plato', ['restaurante_id' => $restaurante, 'nombre' => 'Ostras de la lonja', 'tipo' => 'Primero', 'descripcion' => 'Media docena.', 'especie_id' => $especie], ['Origin' => $admin->origen()]);
        $this->assertSame(303, $r['status']);
        $this->assertStringContainsString('Ostras de la lonja', $admin->peticion('GET', '/restaurante/quatre-lents')['body']);

        $plato = (int) $pdo->query("SELECT id FROM Comida WHERE slug = 'ostras-de-la-lonja'")->fetchColumn();
        $r = $admin->peticion('POST', '/admin/recomendacion', ['vino_id' => $vino, 'comida_id' => $plato, 'bodega' => 'Clos Mogador', 'botella' => 'Jhana Rosat', 'mensaje' => 'La fruta rosada del Viognier abraza la sal de la ostra.'], ['Origin' => $admin->origen()]);
        $this->assertSame(303, $r['status']);

        $resultado = $admin->peticion('GET', '/restaurante/quatre-lents/ostras-de-la-lonja');
        $this->assertStringContainsString('Maridaje de la casa', $resultado['body']);
        $this->assertStringContainsString('Jhana Rosat', $resultado['body']);
        $this->assertStringContainsString('Clos Mogador', $resultado['body']);
    }

    public function testAdminSinOrigenOConDatosInvalidosNoGuarda(): void
    {
        $admin = $this->entrarComoAdmin();
        $antes = (int) Servidor::pdo()->query('SELECT COUNT(*) FROM Comida')->fetchColumn();

        $r = $admin->peticion('POST', '/admin/plato', ['restaurante_id' => 1, 'nombre' => 'X', 'especie_id' => 1]);
        $this->assertSame(403, $r['status']);

        $r = $admin->peticion('POST', '/admin/plato', ['restaurante_id' => 999, 'nombre' => 'Plato', 'tipo' => 'Primero', 'especie_id' => 1], ['Origin' => $admin->origen()]);
        $this->assertSame(303, $r['status']);
        $this->assertStringContainsString('Restaurante no válido', $admin->peticion('GET', '/admin')['body']);
        $this->assertSame($antes, (int) Servidor::pdo()->query('SELECT COUNT(*) FROM Comida')->fetchColumn());
    }

    public function testSalirCierraLaSesion(): void
    {
        $this->h->registrarYEntrar('sale@ejemplo.test');
        $r = $this->h->peticion('POST', '/salir', [], ['Origin' => $this->h->origen()]);
        $this->assertSame(303, $r['status']);
        $this->assertSame('/login', $this->h->cabecera('location'));
        $despues = $this->h->peticion('GET', '/');
        $this->assertSame(303, $despues['status']);
        $this->assertSame('/login', $this->h->cabecera('location'));
    }
}
