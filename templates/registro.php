<?php
/** @var array{nombre: string, email: string} $valores */
use WineMatch\Html;
?>
<section class="tarjeta tarjeta-estreta">
    <h1>Crear cuenta</h1>
    <form method="post" action="/registro" class="formulario">
        <label for="nombre">Nombre</label>
        <input type="text" id="nombre" name="nombre" value="<?= Html::e($valores['nombre']) ?>" required autocomplete="name" maxlength="80">
        <label for="email">Correo electrónico</label>
        <input type="email" id="email" name="email" value="<?= Html::e($valores['email']) ?>" required autocomplete="email">
        <label for="password">Contraseña</label>
        <input type="password" id="password" name="password" required autocomplete="new-password" minlength="8" maxlength="72">
        <p class="nota">Al menos 8 caracteres. No hace falta símbolos ni mayúsculas: larga y que recuerdes.</p>
        <button type="submit" class="boton boton-primario">Crear cuenta</button>
    </form>
    <p class="nota">¿Ya tienes cuenta? <a href="/login">Entra</a>.</p>
</section>
