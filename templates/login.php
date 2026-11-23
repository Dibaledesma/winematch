<?php
/** @var string $email */
use WineMatch\Html;
?>
<section class="tarjeta tarjeta-estreta">
    <h1>Entrar</h1>
    <form method="post" action="/login" class="formulario">
        <label for="email">Correo electrónico</label>
        <input type="email" id="email" name="email" value="<?= Html::e($email) ?>" required autocomplete="email">
        <label for="password">Contraseña</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">
        <button type="submit" class="boton boton-primario">Entrar</button>
    </form>
    <p class="nota">¿No tienes cuenta? <a href="/registro">Créala en un minuto</a>.</p>
</section>
