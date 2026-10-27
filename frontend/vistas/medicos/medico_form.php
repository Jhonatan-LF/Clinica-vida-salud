<?php
/**
 * CU07 · Registrar o modificar médico con una o varias especialidades.
 * Variables: $medico (null = nuevo), $especialidades, $anterior, $errores.
 */
$m = $medico ?? [];
$esNuevo = !$medico;
$accion = $esNuevo ? url('/medicos') : url("/medicos/{$m['id_medico']}");
$elegidas = array_map('intval', (array) ($anterior['especialidades'] ?? ($m['ids_especialidades'] ?? [])));
$v = static fn (string $campo) => viejo($anterior, $campo, $m[$campo] ?? '');
?>
<form method="post" action="<?= $accion ?>" class="formulario" data-validar novalidate>
    <?= csrf_campo() ?>
    <div class="rejilla rejilla-lateral">
        <section class="tarjeta">
            <div class="tarjeta-cabecera"><div>
                <h2><?= $esNuevo ? 'Nuevo médico' : e($m['codigo'] . ' · Dr(a). ' . $m['nombres'] . ' ' . $m['apellidos']) ?></h2>
                <p>El código MED se asigna automáticamente. Luego defina sus horarios de atención.</p></div></div>
            <div class="tarjeta-cuerpo">
                <div class="fila-campos">
                    <div class="campo<?= clase_error($errores, 'nombres') ?>">
                        <label for="nombres">Nombres <span class="requerido">*</span></label>
                        <input type="text" id="nombres" name="nombres" value="<?= $v('nombres') ?>" required minlength="2" maxlength="60" data-regla="nombre">
                        <?= error_campo($errores, 'nombres') ?>
                    </div>
                    <div class="campo<?= clase_error($errores, 'apellidos') ?>">
                        <label for="apellidos">Apellidos <span class="requerido">*</span></label>
                        <input type="text" id="apellidos" name="apellidos" value="<?= $v('apellidos') ?>" required minlength="2" maxlength="60" data-regla="nombre">
                        <?= error_campo($errores, 'apellidos') ?>
                    </div>
                    <div class="campo<?= clase_error($errores, 'registro_profesional') ?>">
                        <label for="registro_profesional">Registro profesional <span class="requerido">*</span></label>
                        <input type="text" id="registro_profesional" name="registro_profesional" value="<?= $v('registro_profesional') ?>" required minlength="3" maxlength="30" placeholder="Ej. MP-SC-1234" autocapitalize="characters">
                        <?= error_campo($errores, 'registro_profesional') ?>
                    </div>
                    <div class="campo<?= clase_error($errores, 'telefono') ?>">
                        <label for="telefono">Teléfono <span class="requerido">*</span></label>
                        <input type="tel" id="telefono" name="telefono" value="<?= $v('telefono') ?>" required maxlength="20" data-regla="telefono">
                        <?= error_campo($errores, 'telefono') ?>
                    </div>
                    <div class="campo campo-doble<?= clase_error($errores, 'correo') ?>">
                        <label for="correo">Correo <span class="requerido">*</span></label>
                        <input type="email" id="correo" name="correo" value="<?= $v('correo') ?>" required maxlength="120">
                        <?= error_campo($errores, 'correo') ?>
                    </div>
                </div>

                <fieldset class="grupo mb-0<?= clase_error($errores, 'especialidades') ?>" data-al-menos-uno>
                    <legend><?= icono('especialidad') ?> Especialidades <span class="requerido">*</span></legend>
                    <div class="casillas">
                        <?php foreach ($especialidades as $esp): ?>
                        <label class="casilla casilla-tarjeta">
                            <input type="checkbox" name="especialidades[]" value="<?= $esp['id_especialidad'] ?>"<?= marcado(in_array((int) $esp['id_especialidad'], $elegidas, true)) ?>>
                            <span><?= e($esp['nombre']) ?><?= $esp['estado'] !== 'activo' ? ' <span class="texto-tenue">(inactiva)</span>' : '' ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <?= error_campo($errores, 'especialidades') ?>
                </fieldset>
            </div>
            <div class="tarjeta-pie acciones-formulario">
                <a href="<?= url($esNuevo ? '/medicos' : "/medicos/{$m['id_medico']}") ?>" class="boton boton-secundario">Cancelar</a>
                <button type="submit" class="boton boton-primario"><?= icono('check') ?> <?= $esNuevo ? 'Registrar médico' : 'Guardar cambios' ?></button>
            </div>
        </section>
        <aside class="tarjeta">
            <div class="tarjeta-cuerpo">
                <h3><?= icono('info') ?> Reglas</h3>
                <ul class="texto-suave texto-chico">
                    <li><strong>RN02:</strong> el código y el registro profesional son únicos.</li>
                    <li>No se puede quitar una especialidad con la que el médico ya tiene citas (integridad del historial).</li>
                    <li><strong>RN04:</strong> solo un médico activo recibe citas nuevas.</li>
                    <li>Para que aparezca al programar citas necesita al menos un horario activo.</li>
                </ul>
            </div>
        </aside>
    </div>
</form>
