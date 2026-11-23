<?php
/**
 * @var array<string, mixed> $restaurante
 * @var list<array<string, mixed>> $platos
 * @var list<array{id: int, nombre: string, variedad: string, denominacion_origen: string, tipo_id: int}> $vinos
 * @var list<array{id: int, nombre: string}> $tipos
 */
use WineMatch\Html;

$slug = Html::e($restaurante['slug']);
$porTipo = ['Primero' => [], 'Segundo' => [], 'Postre' => []];
foreach ($platos as $p) {
    $porTipo[$p['tipo']][] = $p;
}
?>
<nav class="migas"><a href="/">Restaurantes</a> › <?= Html::e($restaurante['nombre']) ?></nav>

<section class="tarjeta cabecera-restaurante">
    <img src="/static/img/restaurante-<?= $slug ?>.webp" alt="" class="escudo escudo-grande">
    <div>
        <h1><?= Html::e($restaurante['nombre']) ?></h1>
        <p class="nota"><?= Html::e($restaurante['descripcion']) ?></p>
    </div>
</section>

<section class="tarjeta">
    <h2>Selecciona tu maridaje</h2>
    <form method="get" action="/restaurante/<?= $slug ?>/maridar" class="formulario formulario-maridar">
        <div class="campo campo-con-icono">
            <img src="/static/img/botella.webp" alt="" class="icono-campo">
            <div>
                <label for="vino">Vino (variedad)</label>
                <select name="vino" id="vino" required>
                    <option value="">--Selecciona vino--</option>
                    <?php foreach ($vinos as $v): ?>
                        <option value="<?= (int) $v['id'] ?>"><?= Html::e($v['nombre']) ?> · <?= Html::e($v['variedad']) ?> (<?= Html::e($v['denominacion_origen']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="campo campo-con-icono">
            <img src="/static/img/plato.webp" alt="" class="icono-campo">
            <div>
                <label for="plato">Plato a maridar</label>
                <select name="plato" id="plato" required>
                    <option value="">--Selecciona plato--</option>
                    <?php foreach ($porTipo as $tipo => $lista): if ($lista === []) continue; ?>
                        <optgroup label="<?= Html::e($tipo) ?>">
                            <?php foreach ($lista as $p): ?>
                                <option value="<?= Html::e($p['slug']) ?>"><?= Html::e($p['nombre']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <button type="submit" class="boton boton-primario boton-buscar">Buscar Vino</button>
    </form>
</section>

<section class="tarjeta">
    <h2>La carta</h2>
    <?php foreach ($porTipo as $tipo => $lista): if ($lista === []) continue; ?>
        <h3 class="tipo-plato"><?= Html::e($tipo) ?>s</h3>
        <ul class="lista-platos">
            <?php foreach ($lista as $p): ?>
                <li>
                    <a class="plato" href="/restaurante/<?= $slug ?>/<?= Html::e($p['slug']) ?>">
                        <span class="plato-nombre"><?= Html::e($p['nombre']) ?></span>
                        <span class="plato-descripcion"><?= Html::e($p['descripcion']) ?></span>
                        <span class="etiqueta"><img src="/static/img/especia.webp" alt="" class="icono-etiqueta"><?= Html::e($p['especie']) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endforeach; ?>
</section>

<section class="tarjeta bodegas">
    <h3>Bodegas colaboradoras</h3>
    <div class="bodegas-logos">
        <a href="https://closdagon.com/ca/" target="_blank" rel="noopener"><img src="/static/img/bodega-1.webp" alt="Clos d'Agon"></a>
        <a href="https://www.alvaropalacios.com/" target="_blank" rel="noopener"><img src="/static/img/bodega-2.webp" alt="Álvaro Palacios"></a>
        <a href="https://pascona.com/" target="_blank" rel="noopener"><img src="/static/img/bodega-3.webp" alt="Pascona"></a>
    </div>
</section>
