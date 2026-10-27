<?php
/**
 * CU04 · Registro en línea del paciente (layout_simple). Si recepción ya registró su CI,
 * la cuenta se vincula cuando coincide la fecha de nacimiento.
 * Variables: $anterior, $errores.
 */
$v = static fn (string $campo) => viejo($anterior, $campo);
?>
<div class="formulario-registro">
    <h2>Crear cuenta de paciente</h2>
    <p class="subtitulo">Reserve y siga sus citas desde el celular. ¿Ya tiene cuenta? <a href="<?= url('/login') ?>">Inicie sesión</a>.</p>

    <form method="post" action="<?= url('/registro') ?>" class="formulario" data-validar novalidate>
        <?= csrf_campo() ?>
        <fieldset class="grupo">
            <legend><?= icono('persona') ?> Sus datos</legend>
            <div class="fila-campos">
                <div class="campo<?= clase_error($errores, 'ci') ?>">
                    <label for="ci">CI <span class="requerido">*</span></label>
                    <input type="text" id="ci" name="ci" value="<?= $v('ci') ?>" required maxlength="15" data-regla="ci" placeholder="Ej. 1234567">
                    <?= error_campo($errores, 'ci') ?>
                </div>
                <div class="campo<?= clase_error($errores, 'fecha_nacimiento') ?>">
                    <label for="fecha_nacimiento">Fecha de nacimiento <span class="requerido">*</span></label>
                    <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" value="<?= $v('fecha_nacimiento') ?>" required min="1900-01-01" max="<?= date('Y-m-d') ?>">
                    <?= error_campo($errores, 'fecha_nacimiento') ?>
                </div>
                <div class="campo<?= clase_error($errores, 'nombres') ?>">
                    <label for="nombres">Nombres <span class="requerido">*</span></label>
                    <input type="text" id="nombres" name="nombres" value="<?= $v('nombres') ?>" required minlength="2" maxlength="60" data-regla="nombre" autocomplete="given-name">
                    <?= error_campo($errores, 'nombres') ?>
                </div>
                <div class="campo<?= clase_error($errores, 'apellidos') ?>">
                    <label for="apellidos">Apellidos <span class="requerido">*</span></label>
                    <input type="text" id="apellidos" name="apellidos" value="<?= $v('apellidos') ?>" required minlength="2" maxlength="60" data-regla="nombre" autocomplete="family-name">
                    <?= error_campo($errores, 'apellidos') ?>
                </div>
                <div class="campo<?= clase_error($errores, 'sexo') ?>">
                    <label for="sexo">Sexo <span class="requerido">*</span></label>
                    <select id="sexo" name="sexo" required>
                        <option value="">Seleccione…</option>
                        <?php foreach (['femenino' => 'Femenino', 'masculino' => 'Masculino', 'otro' => 'Otro'] as $valor => $texto): ?>
                            <option value="<?= $valor ?>"<?= seleccionado($anterior['sexo'] ?? '', $valor) ?>><?= $texto ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= error_campo($errores, 'sexo') ?>
                </div>
                <div class="campo<?= clase_error($errores, 'telefono') ?>">
                    <label for="telefono">Teléfono / celular <span class="requerido">*</span></label>
                    <input type="tel" id="telefono" name="telefono" value="<?= $v('telefono') ?>" required maxlength="20" data-regla="telefono" autocomplete="tel">
                    <?= error_campo($errores, 'telefono') ?>
                </div>
                <div class="campo campo-doble<?= clase_error($errores, 'direccion') ?>">
                    <label for="direccion">Dirección <span class="opcional">(opcional)</span></label>
                    <input type="text" id="direccion" name="direccion" value="<?= $v('direccion') ?>" minlength="3" maxlength="200" autocomplete="street-address">
                    <?= error_campo($errores, 'direccion') ?>
                </div>
            </div>
        </fieldset>

        <fieldset class="grupo">
            <legend><?= icono('candado') ?> Su cuenta</legend>
            <div class="fila-campos">
                <div class="campo<?= clase_error($errores, 'correo') ?>">
                    <label for="correo">Correo <span class="requerido">*</span></label>
                    <input type="email" id="correo" name="correo" value="<?= $v('correo') ?>" required maxlength="120" autocomplete="email">
                    <?= error_campo($errores, 'correo') ?>
                </div>
                <div class="campo<?= clase_error($errores, 'nombre_usuario') ?>">
                    <label for="nombre_usuario">Nombre de usuario <span class="requerido">*</span></label>
                    <input type="text" id="nombre_usuario" name="nombre_usuario" value="<?= $v('nombre_usuario') ?>" required minlength="4" maxlength="40" data-regla="usuario" autocapitalize="none" spellcheck="false" autocomplete="username" placeholder="Ej. maria.lopez">
                    <?= error_campo($errores, 'nombre_usuario') ?>
                </div>
                <div class="campo<?= clase_error($errores, 'clave') ?>">
                    <label for="clave">Contraseña <span class="requerido">*</span></label>
                    <div class="campo-clave">
                        <input type="password" id="clave" name="clave" required minlength="8" maxlength="64" data-regla="clave" autocomplete="new-password">
                        <button type="button" class="ver-clave" data-ver-clave="clave" aria-label="Mostrar contraseña"><?= icono('ojo') ?></button>
                    </div>
                    <p class="campo-ayuda">De 8 a 64 caracteres, con letras y números.</p>
                    <?= error_campo($errores, 'clave') ?>
                </div>
                <div class="campo<?= clase_error($errores, 'clave_confirmacion') ?>">
                    <label for="clave_confirmacion">Repita la contraseña <span class="requerido">*</span></label>
                    <input type="password" id="clave_confirmacion" name="clave_confirmacion" required maxlength="64" data-igual-a="clave" autocomplete="new-password">
                    <?= error_campo($errores, 'clave_confirmacion') ?>
                </div>
            </div>
            <div class="campo<?= clase_error($errores, 'acepta') ?>">
                <label class="casilla">
                    <input type="checkbox" name="acepta" value="1" required<?= marcado(!empty($anterior['acepta'])) ?>>
                    <span>Acepto que la clínica use mis datos para gestionar mis citas y mi atención médica.</span>
                </label>
                <?= error_campo($errores, 'acepta') ?>
            </div>
        </fieldset>

        <button type="submit" class="boton boton-primario boton-bloque"><?= icono('persona-mas') ?> Crear mi cuenta</button>
    </form>
    <p class="acceso-enlace"><a href="<?= url('/login') ?>"><?= icono('volver') ?> Volver al inicio de sesión</a></p>
</div>
