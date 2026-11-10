<?php
/**
 * Formulario para solicitar enlace de recuperación de contraseña.
 * Variables: $anterior, $errores.
 */
?>
<h2>Recuperar contraseña</h2>
<p class="subtitulo">Ingrese el correo electrónico asociado a su cuenta y le enviaremos un enlace para restablecerla.</p>

<form method="post" action="<?= url('/recuperar-clave') ?>" class="formulario" data-validar novalidate>
    <?= csrf_campo() ?>
    <div class="campo<?= clase_error($errores, 'correo') ?>">
        <label for="correo">Correo electrónico</label>
        <input type="email" id="correo" name="correo" value="<?= viejo($anterior, 'correo') ?>"
               autocomplete="email" required maxlength="120" autofocus placeholder="ejemplo@correo.com">
        <?= error_campo($errores, 'correo') ?>
    </div>
    <button type="submit" class="boton boton-primario boton-bloque"><?= icono('correo') ?> Enviar enlace de recuperación</button>
</form>

<p class="acceso-enlace"><a href="<?= url('/login') ?>">← Volver a Iniciar sesión</a></p>
