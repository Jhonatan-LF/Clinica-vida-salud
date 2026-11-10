<?php
/**
 * Formulario para crear una nueva contraseña con token.
 * Variables: $token, $nombre, $anterior, $errores.
 */
?>
<h2>Crear nueva contraseña</h2>
<p class="subtitulo">Hola <strong><?= e($nombre) ?></strong>, ingrese su nueva contraseña de acceso.</p>

<form method="post" action="<?= url('/restablecer-clave') ?>" class="formulario" data-validar novalidate>
    <?= csrf_campo() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">

    <div class="campo<?= clase_error($errores, 'clave') ?>">
        <label for="clave">Nueva contraseña</label>
        <div class="campo-clave">
            <input type="password" id="clave" name="clave" autocomplete="new-password" required maxlength="64" autofocus>
            <button type="button" class="ver-clave" data-ver-clave="clave" aria-label="Mostrar contraseña"><?= icono('ojo') ?></button>
        </div>
        <p class="pista">Mínimo 8 caracteres, al menos una mayúscula, una minúscula y un número.</p>
        <?= error_campo($errores, 'clave') ?>
    </div>

    <div class="campo<?= clase_error($errores, 'clave_confirmacion') ?>">
        <label for="clave_confirmacion">Confirmar nueva contraseña</label>
        <div class="campo-clave">
            <input type="password" id="clave_confirmacion" name="clave_confirmacion" autocomplete="new-password" required maxlength="64">
            <button type="button" class="ver-clave" data-ver-clave="clave_confirmacion" aria-label="Mostrar contraseña"><?= icono('ojo') ?></button>
        </div>
        <?= error_campo($errores, 'clave_confirmacion') ?>
    </div>

    <button type="submit" class="boton boton-primario boton-bloque"><?= icono('candado') ?> Guardar nueva contraseña</button>
</form>

<p class="acceso-enlace"><a href="<?= url('/login') ?>">← Cancelar y volver al inicio</a></p>
