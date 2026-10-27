<?php
/**
 * CU01 · Flujo alternativo: cambiar la contraseña propia.
 * Variables: $anterior, $errores.
 */
$campos = [
    ['clave_actual', 'Contraseña actual', 'current-password', ''],
    ['clave', 'Nueva contraseña', 'new-password', 'De 8 a 64 caracteres, con letras y números.'],
    ['clave_confirmacion', 'Repita la nueva contraseña', 'new-password', ''],
];
?>
<div class="rejilla rejilla-lateral">
    <section class="tarjeta">
        <div class="tarjeta-cabecera">
            <div><h2>Cambiar contraseña</h2><p>Por seguridad se pide la contraseña actual.</p></div>
        </div>
        <form method="post" action="<?= url('/cuenta/clave') ?>" class="tarjeta-cuerpo formulario" data-validar novalidate>
            <?= csrf_campo() ?>
            <?php foreach ($campos as [$nombre, $etiqueta, $auto, $ayuda]): ?>
            <div class="campo<?= clase_error($errores, $nombre) ?>">
                <label for="<?= $nombre ?>"><?= e($etiqueta) ?></label>
                <div class="campo-clave">
                    <input type="password" id="<?= $nombre ?>" name="<?= $nombre ?>" autocomplete="<?= $auto ?>" required maxlength="64"
                        <?= $nombre === 'clave' ? 'minlength="8" data-regla="clave"' : '' ?>
                        <?= $nombre === 'clave_confirmacion' ? 'data-igual-a="clave"' : '' ?>>
                    <button type="button" class="ver-clave" data-ver-clave="<?= $nombre ?>" aria-label="Mostrar contraseña"><?= icono('ojo') ?></button>
                </div>
                <?php if ($ayuda): ?><p class="campo-ayuda"><?= e($ayuda) ?></p><?php endif; ?>
                <?= error_campo($errores, $nombre) ?>
            </div>
            <?php endforeach; ?>
            <div class="acciones-formulario">
                <a href="<?= url('/panel') ?>" class="boton boton-secundario">Cancelar</a>
                <button type="submit" class="boton boton-primario"><?= icono('llave') ?> Guardar contraseña</button>
            </div>
        </form>
    </section>
    <aside class="tarjeta">
        <div class="tarjeta-cuerpo">
            <h3><?= icono('escudo') ?> Recomendaciones</h3>
            <ul class="texto-suave texto-chico">
                <li>No use su CI, su fecha de nacimiento ni su nombre.</li>
                <li>Combine letras, números y algún símbolo.</li>
                <li>Tras 5 intentos fallidos la cuenta se bloquea 15 minutos.</li>
                <li>La sesión se cierra sola tras 30 minutos sin uso.</li>
            </ul>
        </div>
    </aside>
</div>
