<?php
/** @var string $contenido  @var array<string, mixed>|null $usuario  @var array $flashes  @var string $titulo  @var string $fondo */
use WineMatch\Html;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= Html::e($titulo ?? 'WineMatch') ?> · WineMatch</title>
    <link rel="icon" href="/static/img/logo.webp">
    <link rel="stylesheet" href="/static/css/winematch.css">
</head>
<body class="<?= isset($fondo) ? 'fondo-' . Html::e($fondo) : '' ?>">
<header class="barra">
    <a class="marca" href="/"><img src="/static/img/logo.webp" alt="" class="marca-logo">WineMatch</a>
    <nav class="barra-nav">
        <?php if ($usuario !== null): ?>
            <span class="barra-usuario">Hola, <?= Html::e($usuario['nombre']) ?></span>
            <?php if ($usuario['rol'] === 'admin'): ?><a href="/admin">Admin</a><?php endif; ?>
            <form method="post" action="/salir" class="inline"><button type="submit" class="boton boton-secundario">Salir</button></form>
        <?php else: ?>
            <a href="/login">Entrar</a>
            <a href="/registro" class="boton boton-primario">Crear cuenta</a>
        <?php endif; ?>
    </nav>
</header>
<?php foreach (($flashes ?? []) as $f): ?>
    <div class="aviso aviso-<?= Html::e($f['tipo']) ?>" role="status"><?= Html::e($f['texto']) ?></div>
<?php endforeach; ?>
<main class="contenedor"><?= $contenido ?></main>
<footer class="pie">WineMatch · el maridador de vinos del restaurante</footer>
</body>
</html>
