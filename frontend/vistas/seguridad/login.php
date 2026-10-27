<?php
/**
 * CU01 · Iniciar sesión (layout_simple). Validación en el navegador (app.js) y en el servidor (CU01).
 * Variables: $anterior, $errores.
 */
?>
<h2>Iniciar sesión</h2>
<p class="subtitulo">Ingrese con su usuario o correo electrónico.</p>

<form method="post" action="<?= url('/login') ?>" class="formulario" data-validar novalidate>
    <?= csrf_campo() ?>
    <div class="campo<?= clase_error($errores, 'usuario') ?>">
        <label for="usuario">Usuario o correo</label>
        <input type="text" id="usuario" name="usuario" value="<?= viejo($anterior, 'usuario') ?>"
               autocomplete="username" autocapitalize="none" spellcheck="false" required minlength="3" maxlength="120" autofocus>
        <?= error_campo($errores, 'usuario') ?>
    </div>
    <div class="campo<?= clase_error($errores, 'clave') ?>">
        <label for="clave">Contraseña</label>
        <div class="campo-clave">
            <input type="password" id="clave" name="clave" autocomplete="current-password" required maxlength="64">
            <button type="button" class="ver-clave" data-ver-clave="clave" aria-label="Mostrar contraseña"><?= icono('ojo') ?></button>
        </div>
        <?= error_campo($errores, 'clave') ?>
    </div>
    <button type="submit" class="boton boton-primario boton-bloque"><?= icono('candado') ?> Ingresar</button>
</form>

<p class="acceso-enlace">¿Es paciente y aún no tiene cuenta? <a href="<?= url('/registro') ?>">Crear cuenta de paciente</a></p>
