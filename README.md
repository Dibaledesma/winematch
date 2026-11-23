# WineMatch v2 · maridador de vinos para restaurantes

Ayuda a quien no sabe de vinos a elegir bien: el comensal elige el restaurante,
ve la carta, elige el plato (y si quiere, el tipo de vino) y recibe una
recomendación de la casa con el porqué del maridaje.

Es la **versión 2, reconstruida y endurecida**, del proyecto final del CFGS
Desarrollo de Aplicaciones Web (abril 2025). Misma memoria, mismos requisitos,
otro nivel de ingeniería.

## De la v1 a la v2

La v1 nació en 2025 como proyecto de ciclo: PHP + MySQL, mucho ChatGPT,
muchas ganas. Funcionaba, pero con la distancia de un año y una formación en
ciberseguridad encima, leí el código y encontré exactamente lo que un reclutador
técnico habría encontrado. La v2 corrige cada punto, con test que lo prueba:

| v1 (2025) | v2 (esta) |
|---|---|
| `conexion.php` con `root` / contraseña en el repositorio | Credenciales solo por variables de entorno ([`Config`](src/Config.php)); [`.env.example`](.env.example) sin secretos |
| SQL interpolado en registro y login: `"... WHERE email = '$email'"` | PDO con consultas preparadas en el 100 % del código; test de inyección SQL |
| Un flujo de registro guardaba la contraseña **en texto plano** | `password_hash` / `password_verify` siempre; un único flujo |
| Dos flujos duplicados de login y registro (uno roto) | Uno solo, cubierto por tests |
| Administrador decidido por un email literal | Columna `rol` y `Auth::requiereAdmin()` (403 al resto) |
| Errores de base de datos mostrados al usuario | Se registran en log; página neutra; en producción sin detalles |
| Sin `htmlspecialchars` en casi ninguna plantilla (XSS) | Toda la salida pasa por `Html::e()`, CSP estricta sin inline; tests de XSS |
| Sin flags de cookie, sin protección CSRF | `HttpOnly`, `SameSite=Lax`, `Secure` en producción; guarda de origen en todo POST |
| El restaurante estaba "pegado" en dos páginas (`seleccion-mas-bosson.php`, `seleccion-quatre.php`) | Entidad `Restaurante`, carta por slug, alta de platos desde el panel |
| Ids numéricos secuenciales en las URLs | Slugs públicos: `/restaurante/mas-bosson/arroz-de-bogavante` |
| `ORDER BY RAND()`, esquema con tablas creadas dos veces | Esquema limpio SQLite/MySQL; aleatoriedad en PHP |
| Sin tests, sin README, sin git | 71 tests (unitarios + HTTP reales) y CI en GitHub Actions |
| 40 MB de imágenes sin optimizar | Un CSS, cero imágenes |

## Requisitos de la memoria → dónde se cumplen

| Requisito | Dónde |
|---|---|
| RF1 Registro con nombre, correo y contraseña | `/registro` · [`Auth::registrar`](src/Auth.php) |
| RF2 Login y cierre de sesión seguros | `/login`, `/salir` · sesión con flags y rotación de id |
| RF3 Elegir restaurante → carta → plato (+ preferencia) | `/restaurante/{slug}` y `/restaurante/{slug}/{plato}?tipo=N` |
| RF4 Recomendación con vino, bodega y botella | [`Maridaje::recomendar`](src/Maridaje.php) |
| RF5 Resultado claro y atractivo | [`templates/resultado.php`](templates/resultado.php) |
| RF6 Interfaz intuitiva y responsive | [`public/static/css/winematch.css`](public/static/css/winematch.css) |
| Actor Administrador: ver usuarios, consultar comentarios y valoraciones | `/admin` · [`Admin`](src/Admin.php) |
| Carga de carta | `/admin/plato` y `/admin/recomendacion` |
| RNF Seguridad | ver siguiente sección |
| RNF Mantenibilidad: código documentado y organizado | `src/`, `templates/`, `tests/`, este README |

## Cómo funciona el maridaje

El corazón es [`Maridaje::recomendar()`](src/Maridaje.php). Cada plato tiene una
**especie aromática** (cítricos, frutos rojos, especias…) y el administrador define
**reglas de la casa**: vino + plato + el porqué. El motor busca en este orden y
explica al comensal qué está viendo:

1. **Regla exacta** para ese plato (y el tipo de vino pedido, si lo pidió).
2. Si no hay: **misma especie aromática en otro plato** — "perfil parecido".
3. Si el tipo de vino pedido no tiene nada: **misma especie sin filtrar**.
4. Si nada de lo anterior: **cualquier sugerencia de la casa**.

Cada consulta queda registrada para el usuario (`Consulta`), como pedía el ER de
la memoria.

## Decisiones de seguridad

- **Autenticación**: hash bcrypt, rotación del id de sesión al entrar, mensaje de
  error idéntico para "usuario inexistente" y "contraseña incorrecta", trabajo
  constante en el login fallido (sin oráculo de existencia).
- **Sesión**: cookie `HttpOnly` + `SameSite=Lax`; `Secure` con `APP_ENV=production`.
- **CSRF**: todo POST sin `Origin` exacto de este sitio recibe 403. Sin tokens
  que filtrar.
- **XSS**: escape de toda la salida y CSP `default-src 'self'` sin una sola línea
  de JavaScript o CSS inline.
- **Inyección SQL**: imposible por construcción — no hay ninguna concatenación de
  datos en SQL; los tests disparan payloads reales.
- **Autorización**: por rol en base de datos; `/admin` es 403 para el resto.
- **Enumeración**: 404 idéntico byte a byte para restaurante o plato inexistente;
  URLs con slug, sin ids secuenciales.
- **Configuración**: nada sensible en el repositorio; en producción, sin detalles
  de error al usuario.

Verificado desde fuera con [`blindspot-mcp`](https://github.com/Dibaledesma/blindspot-mcp):
sus seis comprobaciones adversariales pasan contra esta app (404 neutro, ids no
secuenciales, cabeceras, guarda de origen, cookies, cero inline). Las sondas de
404 se lanzan con sesión iniciada, porque aquí los recursos viven tras el login.
No es decorativo: esa pasada encontró dos bugs reales antes de publicar — el primer
arranque no creaba el esquema, y una URL con `%00` provocaba un 500.

## Ejecutar en local

Requisitos: PHP 8.2+ con `pdo_sqlite`, `intl`, `mbstring`, `curl` y [Composer](https://getcomposer.org).

```bash
composer install
composer seed          # crea la base de datos SQLite y carga datos de ejemplo
composer serve         # http://127.0.0.1:8099
```

`composer seed` genera una contraseña aleatoria para el admin y la muestra **una
sola vez**. Los datos de ejemplo incluyen dos restaurantes, ocho vinos, diez platos
y catorce reglas de maridaje.

Para MySQL: copia `.env.example` a `.env`, define `DB_DRIVER=mysql` y las
credenciales, carga `database/schema.mysql.sql` y vuelve a ejecutar `composer seed -- --recrear`.

Para desplegar, el docroot es `public/`.

## Tests

```bash
composer test
```

71 tests, dos familias:

- **Unitarios** sobre SQLite en memoria: modelo, autenticación, motor de maridaje
  (exacto y cada escalón de degradación), opiniones y administración.
- **HTTP de extremo a extremo**: se levanta el servidor integrado de PHP contra
  una base de datos temporal y se disparan peticiones reales con curl — POST sin
  origen, origen ajeno, inyección SQL, XSS en nombre, plato y comentario, 404
  idéntico, flags de cookie, rol admin, alta de carta.

## Estructura

```
public/index.php          front controller: sesión, guarda de origen, rutas, errores
public/static/           CSS
src/                     Config, Db, Auth, Maridaje, Opiniones, Admin, Html, Texto, Seed
templates/               PHP pinto con escape en cada salida
database/                esquema SQLite y MySQL, seed
tests/                   PHPUnit + helpers de servidor y cliente HTTP
```

Sin framework: PHP 8 y PDO. Es lo que pedía la memoria y lo suficiente para
esto; la complejidad está donde toca, en el motor y en la seguridad, no en
dependencias.

## Vías futuras (de la memoria, para la v3)

Recomendación híbrida con el historial del usuario, filtros por precio, zona
y sabor, favoritos, versión móvil, catas.

## Licencia

MIT — ver [LICENSE](LICENSE).
