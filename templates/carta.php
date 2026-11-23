<?php
/** @var array<string, mixed> $restaurante  @var list<array<string, mixed>> $platos */
use WineMatch\Html;
?>
<nav class="migas"><a href="/">Restaurantes</a> › <?= Html::e($restaurante['nombre']) ?></nav>
<h1><?= Html::e($restaurante['nombre']) ?></h1>
<p class="nota"><?= Html::e($restaurante['descripcion']) ?></p>
<p class="nota">Elige un plato y te digo qué vino lo acompaña.</p>
<ul class="lista-platos">
    <?php foreach ($platos as $p): ?>
        <li>
            <a class="plato" href="/restaurante/<?= Html::e($restaurante['slug']) ?>/<?= Html::e($p['slug']) ?>">
                <span class="plato-nombre"><?= Html::e($p['nombre']) ?></span>
                <span class="plato-descripcion"><?= Html::e($p['descripcion']) ?></span>
                <span class="etiqueta"><?= Html::e($p['especie']) ?></span>
            </a>
        </li>
    <?php endforeach; ?>
</ul>
