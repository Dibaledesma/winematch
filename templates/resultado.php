<?php
/**
 * @var array<string, mixed> $plato
 * @var array<string, mixed> $rec
 * @var list<array{id: int, nombre: string}> $tipos
 * @var array<string, mixed> $opiniones
 * @var array{puntuacion: int|null, comentario: string} $mia
 * @var int|null $tipoElegido
 * @var list<array{id: int, nombre: string, variedad: string, denominacion_origen: string, tipo_id: int}> $vinos
 * @var array<string, mixed>|null $eleccion
 */
use WineMatch\Html;

$base = '/restaurante/' . Html::e($plato['restaurante_slug']) . '/' . Html::e($plato['slug']);
$iconoTipo = static fn(string $tipo): string => '/static/img/tipo-' . Html::e(strtolower($tipo)) . '.webp';
?>
<nav class="migas"><a href="/">Restaurantes</a> › <a href="/restaurante/<?= Html::e($plato['restaurante_slug']) ?>"><?= Html::e($plato['restaurante']) ?></a> › <?= Html::e($plato['nombre']) ?></nav>

<div class="resultado">
    <section class="tarjeta tarjeta-plato">
        <p class="etiqueta"><img src="/static/img/especia.webp" alt="" class="icono-etiqueta"><?= Html::e($plato['especie']) ?></p>
        <h1><?= Html::e($plato['nombre']) ?></h1>
        <p class="nota"><?= Html::e($plato['tipo']) ?> · <?= Html::e($plato['descripcion']) ?></p>
        <div class="selector-tipo" role="navigation" aria-label="Tipo de vino">
            <span>Prefiero:</span>
            <a class="<?= $tipoElegido === null ? 'activo' : '' ?>" href="<?= $base ?>">Cualquiera</a>
            <?php foreach ($tipos as $t): ?>
                <a class="<?= $tipoElegido === (int) $t['id'] ? 'activo' : '' ?>" href="<?= $base ?>?tipo=<?= (int) $t['id'] ?>"><img src="<?= $iconoTipo($t['nombre']) ?>" alt="" class="icono-tipo"><?= Html::e($t['nombre']) ?></a>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="tarjeta recomendacion">
        <div class="recomendacion-cabecera">
            <img src="<?= $iconoTipo($rec['vino']['tipo']) ?>" alt="" class="copa">
            <div>
                <p class="motivo"><?= Html::e($rec['motivo']) ?></p>
                <?php if ($rec['exacta']): ?><span class="insignia">Maridaje de la casa</span><?php endif; ?>
                <h2>Tu maridaje ideal</h2>
                <h3><?= Html::e($rec['botella']) ?></h3>
            </div>
            <img src="/static/img/botella.webp" alt="" class="botella-img">
        </div>
        <dl class="ficha-vino">
            <div><dt>Bodega</dt><dd><?= Html::e($rec['bodega']) ?></dd></div>
            <div><dt>Variedad</dt><dd><?= Html::e($rec['vino']['variedad']) ?></dd></div>
            <div><dt>Denominación de origen</dt><dd><?= Html::e($rec['vino']['denominacion_origen']) ?></dd></div>
            <div><dt>Tipo</dt><dd><?= Html::e($rec['vino']['tipo']) ?></dd></div>
            <div><dt>Especie sugerida</dt><dd><?= Html::e($plato['especie']) ?></dd></div>
            <div><dt>Plato a maridar</dt><dd><?= Html::e($plato['nombre']) ?></dd></div>
        </dl>
        <p class="mensaje"><strong>Mensaje de la bodega:</strong> <?= Html::e($rec['mensaje']) ?></p>
    </section>

    <div class="cepa"><img src="/static/img/cepa.webp" alt=""></div>
</div>

<section class="tarjeta">
    <h3>¿Ya tienes un vino en mente? ¿Marida?</h3>
    <form method="get" action="<?= $base ?>" class="formulario formulario-inline">
        <label for="vino">Elige el vino</label>
        <select id="vino" name="vino" required>
            <option value="">— elige —</option>
            <?php foreach ($vinos as $v): ?>
                <option value="<?= (int) $v['id'] ?>" <?= $eleccion !== null && $eleccion['vino']['id'] === (int) $v['id'] ? 'selected' : '' ?>><?= Html::e($v['nombre']) ?> (<?= Html::e($v['denominacion_origen']) ?>)</option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="boton boton-secundario">¿Marida?</button>
    </form>
    <?php if ($eleccion !== null): ?>
        <div class="veredicto veredicto-<?= Html::e($eleccion['veredicto']) ?>" role="status">
            <p class="veredicto-titulo"><?= Html::e($eleccion['titulo']) ?></p>
            <p><strong><?= Html::e($eleccion['vino']['nombre']) ?></strong> (<?= Html::e($eleccion['vino']['denominacion_origen']) ?>) con <?= Html::e($plato['nombre']) ?>:</p>
            <p><?= Html::e($eleccion['mensaje']) ?></p>
            <?php if ($eleccion['veredicto'] !== 'exacto'): ?>
                <p class="nota">El maridaje de la casa para este plato es <strong><?= Html::e($eleccion['sugerencia']['botella']) ?></strong> (<?= Html::e($eleccion['sugerencia']['bodega']) ?>).</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<section class="tarjeta">
    <h3>Lo que dicen de este vino</h3>
    <p class="media"><?= Html::e($opiniones['media'] === null ? 'Sin valoraciones aún' : '★ ' . $opiniones['media'] . ' (' . $opiniones['total'] . ' valoraciones)') ?></p>
    <?php if ($opiniones['comentarios'] === []): ?>
        <p class="nota">No hay comentarios todavía.</p>
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
            <legend>¿Qué te ha parecido <?= Html::e($rec['botella']) ?>?</legend>
            <?php for ($i = 5; $i >= 1; $i--): ?>
                <input type="radio" id="p<?= $i ?>" name="puntuacion" value="<?= $i ?>" <?= $mia['puntuacion'] === $i ? 'checked' : '' ?>>
                <label for="p<?= $i ?>" title="<?= $i ?> de 5"><?= $i ?>★</label>
            <?php endfor; ?>
        </fieldset>
        <label for="comentario">Déjanos tu comentario (opcional)</label>
        <textarea id="comentario" name="comentario" rows="3" maxlength="500" placeholder="Escribe aquí..."><?= Html::e($mia['comentario']) ?></textarea>
        <button type="submit" class="boton boton-primario"><?= $mia['puntuacion'] !== null || $mia['comentario'] !== '' ? 'Actualizar' : 'Enviar' ?></button>
    </form>
</section>
