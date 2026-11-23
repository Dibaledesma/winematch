<?php
/** @var list<array{id: int, nombre: string, slug: string, descripcion: string}> $restaurantes */
use WineMatch\Html;
?>
<h1>¿Dónde vas a comer hoy?</h1>
<p class="nota">Elige el restaurante y te sugiero el vino que mejor acompaña cada plato de su carta.</p>
<div class="tarjetas">
    <?php foreach ($restaurantes as $r): ?>
        <a class="tarjeta tarjeta-enlace" href="/restaurante/<?= Html::e($r['slug']) ?>">
            <h2><?= Html::e($r['nombre']) ?></h2>
            <p><?= Html::e($r['descripcion']) ?></p>
            <span class="enlace">Ver la carta →</span>
        </a>
    <?php endforeach; ?>
</div>
