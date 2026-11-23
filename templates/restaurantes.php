<?php
/** @var list<array{id: int, nombre: string, slug: string, descripcion: string}> $restaurantes */
use WineMatch\Html;
?>
<section class="cabecera-pagina">
    <img src="/static/img/logo-ancho.webp" alt="WineMatch" class="logo-ancho">
    <h1>Selecciona tu restaurante</h1>
    <p class="nota">Mira su carta, elige un plato y te digo qué vino lo acompaña.</p>
</section>
<div class="tarjetas">
    <?php foreach ($restaurantes as $r): ?>
        <a class="tarjeta tarjeta-enlace tarjeta-restaurante" href="/restaurante/<?= Html::e($r['slug']) ?>">
            <img src="/static/img/restaurante-<?= Html::e($r['slug']) ?>.webp" alt="" class="escudo">
            <h2><?= Html::e($r['nombre']) ?></h2>
            <p><?= Html::e($r['descripcion']) ?></p>
            <span class="enlace">Ver la carta →</span>
        </a>
    <?php endforeach; ?>
</div>
