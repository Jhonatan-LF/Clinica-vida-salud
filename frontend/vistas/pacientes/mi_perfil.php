<?php
/**
 * CU04 · Perfil del paciente (punto 3.4): datos personales y de contacto, información médica,
 * contacto de emergencia y cuenta (recordatorios y privacidad). Cada sección se guarda por separado.
 * Variables: $paciente, $contacto, $seccion, $anterior, $errores.
 */
use App\Paquetes\Pacientes\ReglasPaciente;

$p = $paciente;
$c = $contacto ?? [];
$secciones = [
    'personales' => ['Datos personales', 'persona'],
    'medica'     => ['Información médica', 'corazon'],
    'emergencia' => ['Contacto de emergencia', 'alerta'],
    'cuenta'     => ['Cuenta y privacidad', 'candado'],
];
$seccion = array_key_exists($seccion, $secciones) ? $seccion : 'personales';
$v = static fn (string $campo, mixed $actual = '') => viejo($anterior, $campo, $actual);
?>
<section class="tarjeta">
    <div class="tarjeta-cuerpo ficha-cabecera">
        <span class="avatar avatar-grande"><?= e(iniciales($p['nombres'] . ' ' . $p['apellidos'])) ?></span>
        <div>
            <h2><?= e($p['nombres'] . ' ' . $p['apellidos']) ?></h2>
            <div class="texto-suave"><?= e($p['codigo']) ?> · CI <?= e($p['ci']) ?> · <?= e(edad($p['fecha_nacimiento'])) ?></div>
        </div>
        <div class="acciones">
            <a class="boton boton-primario" href="<?= url('/reservar') ?>"><?= icono('calendario-mas') ?> Reservar cita</a>
        </div>
    </div>
    <nav class="pestanas" aria-label="Secciones del perfil">
        <?php foreach ($secciones as $clave => [$texto, $ico]): ?>
            <a href="<?= url('/mi-perfil', ['seccion' => $clave]) ?>"<?= $clave === $seccion ? ' class="activa" aria-current="page"' : '' ?>><?= icono($ico) ?> <?= e($texto) ?></a>
        <?php endforeach; ?>
    </nav>

    <div class="tarjeta-cuerpo">
    <?php if ($seccion === 'personales'): ?>
        <form method="post" action="<?= url('/mi-perfil/personales') ?>" class="formulario" data-validar novalidate>
            <?= csrf_campo() ?>
            <div class="fila-campos">
                <div class="campo"><label for="ci">CI</label><input type="text" id="ci" value="<?= e($p['ci']) ?>" readonly>
                    <p class="campo-ayuda">El CI y la fecha de nacimiento solo los corrige recepción.</p></div>
                <div class="campo"><label for="fn">Fecha de nacimiento</label><input type="text" id="fn" value="<?= e(fecha_corta($p['fecha_nacimiento'])) ?>" readonly></div>
                <div class="campo<?= clase_error($errores, 'nombres') ?>">
                    <label for="nombres">Nombres <span class="requerido">*</span></label>
                    <input type="text" id="nombres" name="nombres" value="<?= $v('nombres', $p['nombres']) ?>" required minlength="2" maxlength="60" data-regla="nombre">
                    <?= error_campo($errores, 'nombres') ?>
                </div>
                <div class="campo<?= clase_error($errores, 'apellidos') ?>">
                    <label for="apellidos">Apellidos <span class="requerido">*</span></label>
                    <input type="text" id="apellidos" name="apellidos" value="<?= $v('apellidos', $p['apellidos']) ?>" required minlength="2" maxlength="60" data-regla="nombre">
                    <?= error_campo($errores, 'apellidos') ?>
                </div>
                <div class="campo<?= clase_error($errores, 'sexo') ?>">
                    <label for="sexo">Sexo <span class="requerido">*</span></label>
                    <select id="sexo" name="sexo" required>
                        <?php foreach (['femenino' => 'Femenino', 'masculino' => 'Masculino', 'otro' => 'Otro'] as $valor => $texto): ?>
                            <option value="<?= $valor ?>"<?= seleccionado($anterior['sexo'] ?? $p['sexo'], $valor) ?>><?= $texto ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= error_campo($errores, 'sexo') ?>
                </div>
                <div class="campo<?= clase_error($errores, 'telefono') ?>">
                    <label for="telefono">Teléfono <span class="requerido">*</span></label>
                    <input type="tel" id="telefono" name="telefono" value="<?= $v('telefono', $p['telefono']) ?>" required maxlength="20" data-regla="telefono">
                    <?= error_campo($errores, 'telefono') ?>
                </div>
                <div class="campo<?= clase_error($errores, 'correo') ?>">
                    <label for="correo">Correo</label>
                    <input type="email" id="correo" name="correo" value="<?= $v('correo', $p['correo']) ?>" maxlength="120">
                    <?= error_campo($errores, 'correo') ?>
                </div>
                <div class="campo<?= clase_error($errores, 'direccion') ?>">
                    <label for="direccion">Dirección</label>
                    <input type="text" id="direccion" name="direccion" value="<?= $v('direccion', $p['direccion']) ?>" minlength="3" maxlength="200">
                    <?= error_campo($errores, 'direccion') ?>
                </div>
            </div>
            <div class="acciones-formulario"><button class="boton boton-primario"><?= icono('check') ?> Guardar datos</button></div>
        </form>

    <?php elseif ($seccion === 'medica'): ?>
        <form method="post" action="<?= url('/mi-perfil/medica') ?>" class="formulario" data-validar novalidate>
            <?= csrf_campo() ?>
            <p class="texto-suave">Esta información ayuda a su médico en la consulta. <?= $p['compartir_info_medica'] ? 'Actualmente <strong>se comparte</strong> con su médico.' : 'Actualmente <strong>no se comparte</strong> con su médico (cámbielo en «Cuenta y privacidad»).' ?></p>
            <div class="fila-campos">
                <div class="campo<?= clase_error($errores, 'tipo_sangre') ?>">
                    <label for="tipo_sangre">Tipo de sangre</label>
                    <select id="tipo_sangre" name="tipo_sangre">
                        <option value="">No sé / sin dato</option>
                        <?php foreach (ReglasPaciente::TIPOS_SANGRE as $t): ?>
                            <option value="<?= $t ?>"<?= seleccionado($anterior['tipo_sangre'] ?? $p['tipo_sangre'], $t) ?>><?= $t ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="campo<?= clase_error($errores, 'alergias') ?>">
                    <label for="alergias">Alergias</label>
                    <input type="text" id="alergias" name="alergias"<?= ReglasPaciente::atributoOpciones('alergias') ?> value="<?= $v('alergias', $p['alergias']) ?>" maxlength="250" placeholder="Medicamentos, alimentos, látex…">
                    <?= error_campo($errores, 'alergias') ?>
                </div>
                <div class="campo<?= clase_error($errores, 'enfermedades_cronicas') ?>">
                    <label for="enfermedades_cronicas">Enfermedades crónicas</label>
                    <input type="text" id="enfermedades_cronicas" name="enfermedades_cronicas"<?= ReglasPaciente::atributoOpciones('enfermedades_cronicas') ?> value="<?= $v('enfermedades_cronicas', $p['enfermedades_cronicas']) ?>" maxlength="250">
                    <?= error_campo($errores, 'enfermedades_cronicas') ?>
                </div>
                <div class="campo<?= clase_error($errores, 'medicamentos') ?>">
                    <label for="medicamentos">Medicamentos que toma</label>
                    <input type="text" id="medicamentos" name="medicamentos"<?= ReglasPaciente::atributoOpciones('medicamentos') ?> value="<?= $v('medicamentos', $p['medicamentos']) ?>" maxlength="250">
                    <?= error_campo($errores, 'medicamentos') ?>
                </div>
                <div class="campo campo-doble<?= clase_error($errores, 'antecedentes') ?>">
                    <label for="antecedentes">Antecedentes (cirugías, embarazos, familiares…)</label>
                    <textarea id="antecedentes" name="antecedentes"<?= ReglasPaciente::atributoOpciones('antecedentes') ?> maxlength="500" rows="3"><?= $v('antecedentes', $p['antecedentes']) ?></textarea>
                    <?= error_campo($errores, 'antecedentes') ?>
                </div>
            </div>
            <div class="acciones-formulario"><button class="boton boton-primario"><?= icono('check') ?> Guardar información médica</button></div>
        </form>

    <?php elseif ($seccion === 'emergencia'): ?>
        <form method="post" action="<?= url('/mi-perfil/emergencia') ?>" class="formulario" data-validar novalidate>
            <?= csrf_campo() ?>
            <p class="texto-suave">Persona a quien la clínica llamará en caso de emergencia.</p>
            <div class="fila-campos fila-campos-3">
                <div class="campo<?= clase_error($errores, 'contacto_nombre') ?>">
                    <label for="contacto_nombre">Nombre completo <span class="requerido">*</span></label>
                    <input type="text" id="contacto_nombre" name="contacto_nombre" value="<?= $v('contacto_nombre', $c['nombre'] ?? '') ?>" required minlength="3" maxlength="120" data-regla="nombre">
                    <?= error_campo($errores, 'contacto_nombre') ?>
                </div>
                <div class="campo<?= clase_error($errores, 'contacto_parentesco') ?>">
                    <label for="contacto_parentesco">Parentesco <span class="requerido">*</span></label>
                    <input type="text" id="contacto_parentesco" name="contacto_parentesco" value="<?= $v('contacto_parentesco', $c['parentesco'] ?? '') ?>" required minlength="3" maxlength="40" list="parentescos">
                    <?= error_campo($errores, 'contacto_parentesco') ?>
                </div>
                <div class="campo<?= clase_error($errores, 'contacto_telefono') ?>">
                    <label for="contacto_telefono">Teléfono <span class="requerido">*</span></label>
                    <input type="tel" id="contacto_telefono" name="contacto_telefono" value="<?= $v('contacto_telefono', $c['telefono'] ?? '') ?>" required maxlength="20" data-regla="telefono">
                    <?= error_campo($errores, 'contacto_telefono') ?>
                </div>
            </div>
            <datalist id="parentescos"><option value="Madre"><option value="Padre"><option value="Esposa"><option value="Esposo"><option value="Hijo"><option value="Hija"><option value="Hermano"><option value="Hermana"></datalist>
            <div class="acciones-formulario"><button class="boton boton-primario"><?= icono('check') ?> Guardar contacto</button></div>
        </form>

    <?php else: ?>
        <form method="post" action="<?= url('/mi-perfil/cuenta') ?>" class="formulario">
            <?= csrf_campo() ?>
            <div class="rejilla rejilla-2">
                <label class="casilla casilla-tarjeta">
                    <input type="checkbox" name="recibir_recordatorios" value="1"<?= marcado((bool) $p['recibir_recordatorios']) ?>>
                    <span><strong><?= icono('campana') ?> Recordatorios de citas</strong><br><span class="texto-suave texto-chico">Ver en el panel las citas de las próximas 48 horas.</span></span>
                </label>
                <label class="casilla casilla-tarjeta">
                    <input type="checkbox" name="compartir_info_medica" value="1"<?= marcado((bool) $p['compartir_info_medica']) ?>>
                    <span><strong><?= icono('escudo') ?> Compartir mi información médica</strong><br><span class="texto-suave texto-chico">Su médico verá alergias, enfermedades y medicamentos en su agenda.</span></span>
                </label>
            </div>
            <div class="acciones-formulario mt-2">
                <a class="boton boton-secundario" href="<?= url('/cuenta/clave') ?>"><?= icono('llave') ?> Cambiar contraseña</a>
                <button class="boton boton-primario"><?= icono('check') ?> Guardar preferencias</button>
            </div>
        </form>
    <?php endif; ?>
    </div>
</section>
