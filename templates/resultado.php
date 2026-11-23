<?php
/**
 * @var array<string, mixed> $plato
 * @var array<string, mixed> $rec
 * @var list<array{id: int, nombre: string}> $tipos
 * @var array<string, mixed> $opiniones
 * @var array{puntuacion: int|null, comentario: string} $mia
 * @var int|null $tipoElegido
 * @var list<array{id: int, nombre: string, bodega: string}> $vinos
 * @var array<string, mixed>|null $eleccion
 */
use WineMatch\Html;

$base = '/restaurante/' . Html::e($plato['restaurante_slug']) . '/' . Html::e($plato['slug']);
?>
<nav class="migas"><a href="/">Restaurantes</a> › <a href="/restaurante/<?= Html::e($plato['restaurante_slug']) ?>"><?= Html::e($plato['restaurante']) ?></a> › <?= Html::e($plato['nombre']) ?></nav>

<section class="tarjeta">
    <p class="etiqueta"><?= Html::e($plato['especie']) ?></p>
    <h1><?= Html::e($plato['nombre']) ?></h1>
    <p><?= Html::e($plato['descripcion']) ?></p>
    <div class="selector-tipo" role="navigation" aria-label="Tipo de vino">
        <span>Prefiero:</span>
        <a class="<?= $tipoElegido === null ? 'activo' : '' ?>" href="<?= $base ?>">Cualquiera</a>
        <?php foreach ($tipos as $t): ?>
            <a class="<?= $tipoElegido === (int) $t['id'] ? 'activo' : '' ?>" href="<?= $base ?>?tipo=<?= (int) $t['id'] ?>"><?= Html::e($t['nombre']) ?></a>
        <?php endforeach; ?>
    </div>
</section>

<section class="tarjeta recomendacion">
    <p class="motivo"><?= Html::e($rec['motivo']) ?></p>
    <?php if ($rec['exacta']): ?><span class="insignia">Maridaje de la casa</span><?php endif; ?>
    <h2><?= Html::e($rec['vino']['nombre']) ?></h2>
    <p class="bodega"><?= Html::e($rec['vino']['bodega']) ?> · <?= Html::e($rec['vino']['tipo']) ?> · <?= Html::e($rec['vino']['botella']) ?></p>
    <p class="variedad"><?= Html::e($rec['vino']['variedad']) ?></p>
    <p class="mensaje"><?= Html::e($rec['mensaje']) ?></p>
    <p class="nota"><?= Html::e($rec['vino']['descripcion']) ?></p>
</section>

<section class="tarjeta">
    <h3>¿Ya tienes un vino en mente? ¿Marida?</h3>
    <form method="get" action="<?= $base ?>" class="formulario formulario-inline">
        <label for="vino">Elige el vino</label>
        <select id="vino" name="vino" required>
            <option value="">— elige —</option>
            <?php foreach ($vinos as $v): ?>
                <option value="<?= (int) $v['id'] ?>" <?= $eleccion !== null && $eleccion['vino']['id'] === (int) $v['id'] ? 'selected' : '' ?>><?= Html::e($v['nombre']) ?> (<?= Html::e($v['bodega']) ?>)</option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="boton boton-secundario">¿Marida?</button>
    </form>
    <?php if ($eleccion !== null): ?>
        <div class="veredicto veredicto-<?= Html::e($eleccion['veredicto']) ?>" role="status">
            <p class="veredicto-titulo"><?= Html::e($eleccion['titulo']) ?></p>
            <p><strong><?= Html::e($eleccion['vino']['nombre']) ?></strong> (<?= Html::e($eleccion['vino']['bodega']) ?>) con <?= Html::e($plato['nombre']) ?>:</p>
            <p><?= Html::e($eleccion['mensaje']) ?></p>
            <?php if ($eleccion['veredicto'] !== 'exacto'): ?>
                <p class="nota">La sugerencia de la casa para este plato es <strong><?= Html::e($eleccion['sugerencia']['vino']['nombre']) ?></strong>.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<section class="tarjeta">
    <h3>Lo que dicen de este vino</h3>
    <p class="media"><?= Html::e($opiniones['media'] === null ? 'Sin valoraciones aún' : '★ ' . $opiniones['media'] . ' (' . $opiniones['total'] . ' valoraciones)') ?></p>
    <?php if ($opiniones['comentarios'] === []): ?>
        <p class="nota">Nadie ha comentado todavía. Sé el primero.</p>
    <?php else: ?>
        <ul class="comentarios">
            <?php foreach ($opiniones['comentarios'] as $c): ?>
                <li><strong><?= Html::e($c['nombre']) ?></strong> · <?= Html::e($c['fecha']) ?><br><?= Html::e($c['comentario']) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="tarjeta">
    <h3>Tu opinión</h3>
    <form method="post" action="<?= $base ?>/opinar<?= $tipoElegido !== null ? '?tipo=' . $tipoElegido : '' ?>" class="formulario">
        <input type="hidden" name="vino_id" value="<?= (int) $rec['vino']['id'] ?>">
        <fieldset class="valoracion">
            <legend>¿Qué te ha parecido <?= Html::e($rec['vino']['nombre']) ?>?</legend>
            <?php for ($i = 5; $i >= 1; $i--): ?>
                <input type="radio" id="p<?= $i ?>" name="puntuacion" value="<?= $i ?>" <?= $mia['puntuacion'] === $i ? 'checked' : '' ?>>
                <label for="p<?= $i ?>" title="<?= $i ?> de 5"><?= $i ?>★</label>
            <?php endfor; ?>
        </fieldset>
        <label for="comentario">Comentario (opcional)</label>
        <textarea id="comentario" name="comentario" rows="3" maxlength="500"><?= Html::e($mia['comentario']) ?></textarea>
        <button type="submit" class="boton boton-primario"><?= $mia['puntuacion'] !== null || $mia['comentario'] !== '' ? 'Actualizar' : 'Enviar' ?></button>
    </form>
</section>
