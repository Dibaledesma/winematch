<?php
/** @var string $titulo  @var string $mensaje  @var int|null $codigo */
use WineMatch\Html;
?>
<section class="tarjeta tarjeta-estreta centrado">
    <?php if (isset($codigo)): ?><p class="codigo"><?= (int) $codigo ?></p><?php endif; ?>
    <h1><?= Html::e($titulo) ?></h1>
    <p><?= Html::e($mensaje) ?></p>
    <a href="/" class="boton boton-secundario">Volver al inicio</a>
</section>
