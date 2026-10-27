<?php
/**
 * CU03 · Registrar o modificar paciente (recepción). Incluye la información médica
 * y el contacto de emergencia del perfil (punto 3.4). Validación en el navegador y en el servidor.
 * Variables: $paciente (null = nuevo), $contacto, $anterior, $errores.
 */
use App\Paquetes\Pacientes\ReglasPaciente;

$p = $paciente ?? [];
$c = $contacto ?? [];
$esNuevo = !$paciente;
$accion = $esNuevo ? url('/pacientes') : url("/pacientes/{$p['id_paciente']}");
$v = static fn (string $campo, mixed $actual = '') => viejo($anterior, $campo, $actual);
?>
<form method="post" action="<?= $accion ?>" class="formulario" data-validar novalidate>
    <?= csrf_campo() ?>
    <div class="rejilla rejilla-lateral">
        <div>
            <section class="tarjeta">
                <div class="tarjeta-cabecera">
                    <div><h2><?= $esNuevo ? 'Nuevo paciente' : e($p['codigo'] . ' · ' . $p['nombres'] . ' ' . $p['apellidos']) ?></h2>
                        <p>Los campos con <span class="requerido">*</span> son obligatorios. El código PAC se asigna solo.</p></div>
                </div>
                <div class="tarjeta-cuerpo">
                    <fieldset class="grupo">
                        <legend><?= icono('persona') ?> Datos personales</legend>
                        <div class="fila-campos">
                            <div class="campo<?= clase_error($errores, 'ci') ?>">
                                <label for="ci">CI <span class="opcional">(recomendado)</span></label>
                                <input type="text" id="ci" name="ci" value="<?= $v('ci', $p['ci'] ?? '') ?>" maxlength="15" data-regla="ci" placeholder="Ej. 1234567 o 1234567-1B" autocapitalize="characters">
                                <p class="campo-ayuda">De 5 a 12 dígitos y complemento opcional. Debe ser único.</p>
                                <?= error_campo($errores, 'ci') ?>
                            </div>
                            <div class="campo<?= clase_error($errores, 'fecha_nacimiento') ?>">
                                <label for="fecha_nacimiento">Fecha de nacimiento <span class="requerido">*</span></label>
                                <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" value="<?= $v('fecha_nacimiento', $p['fecha_nacimiento'] ?? '') ?>" required min="1900-01-01" max="<?= date('Y-m-d') ?>">
                                <?= error_campo($errores, 'fecha_nacimiento') ?>
                            </div>
                            <div class="campo<?= clase_error($errores, 'nombres') ?>">
                                <label for="nombres">Nombres <span class="requerido">*</span></label>
                                <input type="text" id="nombres" name="nombres" value="<?= $v('nombres', $p['nombres'] ?? '') ?>" required minlength="2" maxlength="60" data-regla="nombre" autocomplete="off">
                                <?= error_campo($errores, 'nombres') ?>
                            </div>
                            <div class="campo<?= clase_error($errores, 'apellidos') ?>">
                                <label for="apellidos">Apellidos <span class="requerido">*</span></label>
                                <input type="text" id="apellidos" name="apellidos" value="<?= $v('apellidos', $p['apellidos'] ?? '') ?>" required minlength="2" maxlength="60" data-regla="nombre" autocomplete="off">
                                <?= error_campo($errores, 'apellidos') ?>
                            </div>
                            <div class="campo<?= clase_error($errores, 'sexo') ?>">
                                <label for="sexo">Sexo <span class="requerido">*</span></label>
                                <select id="sexo" name="sexo" required>
                                    <option value="">Seleccione…</option>
                                    <?php foreach (['femenino' => 'Femenino', 'masculino' => 'Masculino', 'otro' => 'Otro'] as $valor => $texto): ?>
                                        <option value="<?= $valor ?>"<?= seleccionado($anterior['sexo'] ?? ($p['sexo'] ?? ''), $valor) ?>><?= $texto ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?= error_campo($errores, 'sexo') ?>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="grupo">
                        <legend><?= icono('telefono') ?> Contacto</legend>
                        <div class="fila-campos">
                            <div class="campo<?= clase_error($errores, 'telefono') ?>">
                                <label for="telefono">Teléfono <span class="requerido">*</span></label>
                                <input type="tel" id="telefono" name="telefono" value="<?= $v('telefono', $p['telefono'] ?? '') ?>" required maxlength="20" data-regla="telefono" placeholder="Ej. 71234567">
                                <?= error_campo($errores, 'telefono') ?>
                            </div>
                            <div class="campo<?= clase_error($errores, 'correo') ?>">
                                <label for="correo">Correo <span class="opcional">(opcional)</span></label>
                                <input type="email" id="correo" name="correo" value="<?= $v('correo', $p['correo'] ?? '') ?>" maxlength="120">
                                <?= error_campo($errores, 'correo') ?>
                            </div>
                            <div class="campo campo-doble<?= clase_error($errores, 'direccion') ?>">
                                <label for="direccion">Dirección <span class="opcional">(opcional)</span></label>
                                <input type="text" id="direccion" name="direccion" value="<?= $v('direccion', $p['direccion'] ?? '') ?>" minlength="3" maxlength="200">
                                <?= error_campo($errores, 'direccion') ?>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="grupo">
                        <legend><?= icono('corazon') ?> Información médica <span class="opcional texto-chico">(opcional)</span></legend>
                        <div class="fila-campos">
                            <div class="campo<?= clase_error($errores, 'tipo_sangre') ?>">
                                <label for="tipo_sangre">Tipo de sangre</label>
                                <select id="tipo_sangre" name="tipo_sangre">
                                    <option value="">No sabe / sin dato</option>
                                    <?php foreach (ReglasPaciente::TIPOS_SANGRE as $t): ?>
                                        <option value="<?= $t ?>"<?= seleccionado($anterior['tipo_sangre'] ?? ($p['tipo_sangre'] ?? ''), $t) ?>><?= $t ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?= error_campo($errores, 'tipo_sangre') ?>
                            </div>
                            <div class="campo<?= clase_error($errores, 'alergias') ?>">
                                <label for="alergias">Alergias</label>
                                <input type="text" id="alergias" name="alergias"<?= ReglasPaciente::atributoOpciones('alergias') ?> value="<?= $v('alergias', $p['alergias'] ?? '') ?>" maxlength="250" placeholder="Ej. Penicilina">
                                <?= error_campo($errores, 'alergias') ?>
                            </div>
                            <div class="campo<?= clase_error($errores, 'enfermedades_cronicas') ?>">
                                <label for="enfermedades_cronicas">Enfermedades crónicas</label>
                                <input type="text" id="enfermedades_cronicas" name="enfermedades_cronicas"<?= ReglasPaciente::atributoOpciones('enfermedades_cronicas') ?> value="<?= $v('enfermedades_cronicas', $p['enfermedades_cronicas'] ?? '') ?>" maxlength="250">
                                <?= error_campo($errores, 'enfermedades_cronicas') ?>
                            </div>
                            <div class="campo<?= clase_error($errores, 'medicamentos') ?>">
                                <label for="medicamentos">Medicamentos actuales</label>
                                <input type="text" id="medicamentos" name="medicamentos"<?= ReglasPaciente::atributoOpciones('medicamentos') ?> value="<?= $v('medicamentos', $p['medicamentos'] ?? '') ?>" maxlength="250">
                                <?= error_campo($errores, 'medicamentos') ?>
                            </div>
                            <div class="campo campo-doble<?= clase_error($errores, 'antecedentes') ?>">
                                <label for="antecedentes">Antecedentes</label>
                                <textarea id="antecedentes" name="antecedentes"<?= ReglasPaciente::atributoOpciones('antecedentes') ?> maxlength="500" rows="2"><?= $v('antecedentes', $p['antecedentes'] ?? '') ?></textarea>
                                <?= error_campo($errores, 'antecedentes') ?>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="grupo mb-0">
                        <legend><?= icono('alerta') ?> Contacto de emergencia <span class="opcional texto-chico">(si llena uno, llene los tres)</span></legend>
                        <div class="fila-campos fila-campos-3">
                            <div class="campo<?= clase_error($errores, 'contacto_nombre') ?>">
                                <label for="contacto_nombre">Nombre</label>
                                <input type="text" id="contacto_nombre" name="contacto_nombre" value="<?= $v('contacto_nombre', $c['nombre'] ?? '') ?>" maxlength="120" data-regla="nombre" data-grupo="emergencia">
                                <?= error_campo($errores, 'contacto_nombre') ?>
                            </div>
                            <div class="campo<?= clase_error($errores, 'contacto_parentesco') ?>">
                                <label for="contacto_parentesco">Parentesco</label>
                                <input type="text" id="contacto_parentesco" name="contacto_parentesco" value="<?= $v('contacto_parentesco', $c['parentesco'] ?? '') ?>" maxlength="40" list="parentescos" data-grupo="emergencia">
                                <?= error_campo($errores, 'contacto_parentesco') ?>
                            </div>
                            <div class="campo<?= clase_error($errores, 'contacto_telefono') ?>">
                                <label for="contacto_telefono">Teléfono</label>
                                <input type="tel" id="contacto_telefono" name="contacto_telefono" value="<?= $v('contacto_telefono', $c['telefono'] ?? '') ?>" maxlength="20" data-regla="telefono" data-grupo="emergencia">
                                <?= error_campo($errores, 'contacto_telefono') ?>
                            </div>
                        </div>
                        <datalist id="parentescos"><option value="Madre"><option value="Padre"><option value="Esposa"><option value="Esposo"><option value="Hijo"><option value="Hija"><option value="Hermano"><option value="Hermana"><option value="Tutor"></datalist>
                    </fieldset>
                </div>
                <div class="tarjeta-pie acciones-formulario">
                    <a href="<?= url($esNuevo ? '/pacientes' : "/pacientes/{$p['id_paciente']}") ?>" class="boton boton-secundario">Cancelar</a>
                    <button type="submit" class="boton boton-primario"><?= icono('check') ?> <?= $esNuevo ? 'Registrar paciente' : 'Guardar cambios' ?></button>
                </div>
            </section>
        </div>

        <aside class="tarjeta">
            <div class="tarjeta-cuerpo">
                <h3><?= icono('info') ?> Reglas del registro</h3>
                <ul class="texto-suave texto-chico">
                    <li><strong>RN01:</strong> no puede haber dos pacientes con el mismo CI.</li>
                    <li>Los nombres se guardan con mayúscula inicial («maría LÓPEZ» → «María López»).</li>
                    <li>La fecha de nacimiento no puede ser futura.</li>
                    <li><strong>RN11:</strong> un paciente nunca se elimina; se desactiva y conserva su historial.</li>
                    <li>El paciente puede completar su información médica desde su portal.</li>
                </ul>
            </div>
        </aside>
    </div>
</form>
