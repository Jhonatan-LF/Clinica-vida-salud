<?php
/**
 * CU02 · Registrar o modificar un usuario. Según el rol se vincula a un médico o a un paciente
 * (app.js muestra el campo que corresponde; el servidor lo vuelve a exigir).
 * Variables: $usuario (null = nuevo), $roles, $medicos, $pacientes, $anterior, $errores.
 */
$u = $usuario ?? [];
$esNuevo = !$usuario;
$accion = $esNuevo ? url('/usuarios') : url("/usuarios/{$u['id_usuario']}");
$rolActual = $anterior['id_rol'] ?? ($u['id_rol'] ?? '');
?>
<form method="post" action="<?= $accion ?>" class="formulario" data-validar novalidate>
    <?= csrf_campo() ?>
    <div class="rejilla rejilla-lateral">
        <section class="tarjeta">
            <div class="tarjeta-cabecera"><div><h2><?= $esNuevo ? 'Datos de la cuenta' : 'Cuenta ' . e($u['nombre_usuario']) ?></h2>
                <p>Los campos con <span class="requerido">*</span> son obligatorios.</p></div></div>
            <div class="tarjeta-cuerpo">
                <div class="fila-campos">
                    <div class="campo campo-doble<?= clase_error($errores, 'nombre_completo') ?>">
                        <label for="nombre_completo">Nombre completo <span class="requerido">*</span></label>
                        <input type="text" id="nombre_completo" name="nombre_completo" value="<?= viejo($anterior, 'nombre_completo', $u['nombre_completo'] ?? '') ?>" required minlength="3" maxlength="120" data-regla="nombre">
                        <?= error_campo($errores, 'nombre_completo') ?>
                    </div>
                    <div class="campo<?= clase_error($errores, 'nombre_usuario') ?>">
                        <label for="nombre_usuario">Nombre de usuario <span class="requerido">*</span></label>
                        <input type="text" id="nombre_usuario" name="nombre_usuario" value="<?= viejo($anterior, 'nombre_usuario', $u['nombre_usuario'] ?? '') ?>" required minlength="4" maxlength="40" data-regla="usuario" autocapitalize="none" spellcheck="false">
                        <p class="campo-ayuda">Minúsculas, números, punto o guion bajo.</p>
                        <?= error_campo($errores, 'nombre_usuario') ?>
                    </div>
                    <div class="campo<?= clase_error($errores, 'correo') ?>">
                        <label for="correo">Correo <span class="requerido">*</span></label>
                        <input type="email" id="correo" name="correo" value="<?= viejo($anterior, 'correo', $u['correo'] ?? '') ?>" required maxlength="120">
                        <?= error_campo($errores, 'correo') ?>
                    </div>
                    <div class="campo<?= clase_error($errores, 'id_rol') ?>">
                        <label for="id_rol">Rol <span class="requerido">*</span></label>
                        <select id="id_rol" name="id_rol" required data-selector-rol>
                            <option value="">Seleccione…</option>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?= $r['id_rol'] ?>" data-rol="<?= e($r['nombre']) ?>"<?= seleccionado($rolActual, $r['id_rol']) ?>><?= e(ucfirst($r['nombre'])) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= error_campo($errores, 'id_rol') ?>
                    </div>
                    <div class="campo" data-solo-rol="medico">
                        <label for="id_medico">Médico vinculado <span class="requerido">*</span></label>
                        <select id="id_medico" name="id_medico">
                            <option value="">Seleccione…</option>
                            <?php foreach ($medicos as $m): ?>
                                <option value="<?= $m['id_medico'] ?>"<?= seleccionado($anterior['id_medico'] ?? ($u['id_medico'] ?? ''), $m['id_medico']) ?>><?= e($m['codigo'] . ' · ' . $m['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= error_campo($errores, 'id_medico') ?>
                    </div>
                    <div class="campo" data-solo-rol="paciente">
                        <label for="id_paciente">Paciente vinculado <span class="requerido">*</span></label>
                        <select id="id_paciente" name="id_paciente">
                            <option value="">Seleccione…</option>
                            <?php foreach ($pacientes as $p): ?>
                                <option value="<?= $p['id_paciente'] ?>"<?= seleccionado($anterior['id_paciente'] ?? ($u['id_paciente'] ?? ''), $p['id_paciente']) ?>><?= e($p['codigo'] . ' · ' . $p['nombre'] . ($p['ci'] ? ' · CI ' . $p['ci'] : '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= error_campo($errores, 'id_paciente') ?>
                    </div>
                </div>

                <fieldset class="grupo mt-2">
                    <legend><?= icono('llave') ?> <?= $esNuevo ? 'Contraseña' : 'Restablecer contraseña' ?></legend>
                    <?php if (!$esNuevo): ?><p class="campo-ayuda mb-1">Déjela en blanco para conservar la contraseña actual.</p><?php endif; ?>
                    <div class="fila-campos">
                        <div class="campo<?= clase_error($errores, 'clave') ?>">
                            <label for="clave">Contraseña<?= $esNuevo ? ' <span class="requerido">*</span>' : '' ?></label>
                            <div class="campo-clave">
                                <input type="password" id="clave" name="clave" autocomplete="new-password" <?= $esNuevo ? 'required' : '' ?> minlength="8" maxlength="64" data-regla="clave">
                                <button type="button" class="ver-clave" data-ver-clave="clave" aria-label="Mostrar contraseña"><?= icono('ojo') ?></button>
                            </div>
                            <p class="campo-ayuda">De 8 a 64 caracteres, con letras y números.</p>
                            <?= error_campo($errores, 'clave') ?>
                        </div>
                        <div class="campo<?= clase_error($errores, 'clave_confirmacion') ?>">
                            <label for="clave_confirmacion">Confirmación</label>
                            <input type="password" id="clave_confirmacion" name="clave_confirmacion" autocomplete="new-password" maxlength="64" data-igual-a="clave">
                            <?= error_campo($errores, 'clave_confirmacion') ?>
                        </div>
                    </div>
                </fieldset>

                <div class="acciones-formulario">
                    <a href="<?= url('/usuarios') ?>" class="boton boton-secundario">Cancelar</a>
                    <button type="submit" class="boton boton-primario"><?= icono('check') ?> <?= $esNuevo ? 'Registrar usuario' : 'Guardar cambios' ?></button>
                </div>
            </div>
        </section>

        <aside class="tarjeta">
            <div class="tarjeta-cuerpo">
                <h3><?= icono('info') ?> Roles del sistema</h3>
                <dl class="datos datos-1">
                    <?php foreach ($roles as $r): ?>
                        <div><dt><?= e($r['nombre']) ?> · <?= (int) $r['usuarios'] ?> usuario(s)</dt><dd class="texto-suave texto-chico"><?= e($r['descripcion']) ?></dd></div>
                    <?php endforeach; ?>
                </dl>
                <?php if (!$esNuevo): ?>
                <p class="texto-chico texto-suave mt-2">Último acceso: <?= $u['ultimo_acceso'] ? e(fecha_hora($u['ultimo_acceso'])) : 'nunca' ?>.</p>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</form>
