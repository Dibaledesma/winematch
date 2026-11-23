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
        $this->assertStringContainsString('Mas Bosson', $inicio['body']);
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
        $carta = $this->h->peticion('GET', '/restaurante/mas-bosson');
        $this->assertSame(200, $carta['status']);
        $this->assertStringContainsString('Arroz de bogavante', $carta['body']);
        $this->assertStringContainsString('href="/restaurante/mas-bosson/arroz-de-bogavante"', $carta['body']);

        $resultado = $this->h->peticion('GET', '/restaurante/mas-bosson/arroz-de-bogavante');
        $this->assertSame(200, $resultado['status']);
        $this->assertStringContainsString('Maridaje de la casa', $resultado['body']);
        $this->assertMatchesRegularExpression('/(Martín Códax|Muga Rosado)/u', $resultado['body']);

        $rosado = (int) Servidor::pdo()->query("SELECT id FROM TipoVino WHERE nombre = 'Rosado'")->fetchColumn();
        $conTipo = $this->h->peticion('GET', '/restaurante/mas-bosson/arroz-de-bogavante?tipo=' . $rosado);
        $this->assertSame(200, $conTipo['status']);
        $this->assertStringContainsString('Muga Rosado', $conTipo['body']);
    }

    public function testTipoDeVinoInvalidoSeIgnoraSinError(): void
    {
        $this->h->registrarYEntrar('tipo@ejemplo.test');
        foreach (['999', 'abc', '-1', '1 OR 1=1'] as $valor) {
            $r = $this->h->peticion('GET', '/restaurante/mas-bosson/arroz-de-bogavante?tipo=' . rawurlencode($valor));
            $this->assertSame(200, $r['status'], "tipo={$valor}");
        }
    }

    public function testLaConsultaQuedaRegistradaAlVerUnMaridaje(): void
    {
        $antes = (int) Servidor::pdo()->query('SELECT COUNT(*) FROM Consulta')->fetchColumn();
        $this->h->registrarYEntrar('historial@ejemplo.test');
        $this->h->peticion('GET', '/restaurante/quatre-vents/chuleton-de-vaca-vieja');
        $despues = (int) Servidor::pdo()->query('SELECT COUNT(*) FROM Consulta')->fetchColumn();
        $this->assertSame($antes + 1, $despues);
    }

    public function testRestauranteOPlatoInexistenteDan404Neutro(): void
    {
        $this->h->registrarYEntrar('cuatro@ejemplo.test');
        $base = $this->h->peticion('GET', '/no-existe')['body'];
        foreach (['/restaurante/no-existe', '/restaurante/mas-bosson/no-existe', '/restaurante/quatre-vents/arroz-de-bogavante'] as $ruta) {
            $r = $this->h->peticion('GET', $ruta);
            $this->assertSame(404, $r['status'], $ruta);
            $this->assertSame($base, $r['body'], $ruta);
        }
    }

    public function testSinSesionLaCartaRedirigeALogin(): void
    {
        $r = $this->h->peticion('GET', '/restaurante/mas-bosson');
        $this->assertSame(303, $r['status']);
        $this->assertSame('/login', $this->h->cabecera('location'));
    }

    public function testXssEnNombreDePlatoSeEscapa(): void
    {
        $this->h->registrarYEntrar('plato@ejemplo.test');
        Servidor::pdo()->exec("UPDATE Comida SET nombre = '<script>alert(1)</script>' WHERE slug = 'arroz-de-bogavante'");
        $carta = $this->h->peticion('GET', '/restaurante/mas-bosson');
        $this->assertStringNotContainsString('<script>alert(1)</script>', $carta['body']);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $carta['body']);
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
