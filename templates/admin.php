<?php
/**
 * @var array<string, mixed> $usuario
 * @var list<array<string, mixed>> $usuarios
 * @var list<array<string, mixed>> $comentarios
 * @var list<array<string, mixed>> $valoraciones
 * @var list<array<string, mixed>> $platos
 * @var list<array<string, mixed>> $vinos
 * @var list<array<string, mixed>> $especies
 * @var list<array<string, mixed>> $restaurantes
 */
use WineMatch\Html;
?>
<h1>Administración</h1>

<section class="tarjeta">
    <h2>Cargar carta</h2>
    <div class="dos-columnas">
        <form method="post" action="/admin/plato" class="formulario">
            <h3>Nuevo plato</h3>
            <label for="restaurante">Restaurante</label>
            <select id="restaurante" name="restaurante_id" required>
                <?php foreach ($restaurantes as $r): ?><option value="<?= (int) $r['id'] ?>"><?= Html::e($r['nombre']) ?></option><?php endforeach; ?>
            </select>
            <label for="nombre">Nombre del plato</label>
            <input id="nombre" name="nombre" required maxlength="160">
            <label for="descripcion">Descripción</label>
            <textarea id="descripcion" name="descripcion" rows="2" maxlength="400"></textarea>
            <label for="especie">Especie aromática</label>
            <select id="especie" name="especie_id" required>
                <?php foreach ($especies as $e): ?><option value="<?= (int) $e['id'] ?>"><?= Html::e($e['nombre']) ?></option><?php endforeach; ?>
            </select>
            <button type="submit" class="boton boton-primario">Añadir a la carta</button>
        </form>
        <form method="post" action="/admin/recomendacion" class="formulario">
            <h3>Regla de maridaje</h3>
            <label for="comida">Plato</label>
            <select id="comida" name="comida_id" required>
                <?php foreach ($platos as $p): ?><option value="<?= (int) $p['id'] ?>"><?= Html::e($p['restaurante']) ?> · <?= Html::e($p['nombre']) ?></option><?php endforeach; ?>
            </select>
            <label for="vino">Vino</label>
            <select id="vino" name="vino_id" required>
                <?php foreach ($vinos as $v): ?><option value="<?= (int) $v['id'] ?>"><?= Html::e($v['nombre']) ?> (<?= Html::e($v['bodega']) ?>)</option><?php endforeach; ?>
            </select>
            <label for="mensaje">Por qué marida</label>
            <textarea id="mensaje" name="mensaje" rows="3" required maxlength="400"></textarea>
            <button type="submit" class="boton boton-primario">Crear regla</button>
        </form>
    </div>
</section>

<section class="tarjeta">
    <h2>Valoraciones por vino</h2>
    <table class="tabla">
        <thead><tr><th>Vino</th><th>Bodega</th><th>Media</th><th>Votos</th></tr></thead>
        <tbody>
        <?php foreach ($valoraciones as $v): ?>
            <tr><td><?= Html::e($v['nombre']) ?></td><td><?= Html::e($v['bodega']) ?></td><td><?= Html::e($v['media'] ?? '—') ?></td><td><?= (int) $v['total'] ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<section class="tarjeta">
    <h2>Últimos comentarios</h2>
    <table class="tabla">
        <thead><tr><th>Fecha</th><th>Usuario</th><th>Vino</th><th>Comentario</th></tr></thead>
        <tbody>
        <?php foreach ($comentarios as $c): ?>
            <tr><td><?= Html::e($c['fecha']) ?></td><td><?= Html::e($c['usuario']) ?></td><td><?= Html::e($c['vino']) ?></td><td><?= Html::e($c['comentario']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<section class="tarjeta">
    <h2>Usuarios registrados</h2>
    <table class="tabla">
        <thead><tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Alta</th><th>Consultas</th><th>Valoraciones</th></tr></thead>
        <tbody>
        <?php foreach ($usuarios as $u): ?>
            <tr><td><?= Html::e($u['nombre']) ?></td><td><?= Html::e($u['email']) ?></td><td><?= Html::e($u['rol']) ?></td><td><?= Html::e($u['creado_en']) ?></td><td><?= (int) $u['consultas'] ?></td><td><?= (int) $u['valoraciones'] ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
