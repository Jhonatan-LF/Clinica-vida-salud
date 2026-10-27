<?php
/**
 * CU08 · Horarios de atención por médico y días no laborables (feriados y ausencias).
 * Variables: $medicos, $medico, $bloques, $feriados, $anterior, $errores.
 */
$porDia = [];
foreach ($bloques as $b) {
    $porDia[(int) $b['dia_semana']][] = $b;
}
$idMedico = $medico['id_medico'] ?? null;
$duraciones = [15, 20, 30, 45, 60];
?>
<section class="tarjeta">
    <form method="get" action="<?= url('/horarios') ?>" class="filtros">
        <div class="campo campo-ancho">
            <label for="medico">Médico</label>
            <select id="medico" name="medico" data-enviar-al-cambiar>
                <?php foreach ($medicos as $op): ?>
                    <option value="<?= $op['id_medico'] ?>"<?= seleccionado($idMedico, $op['id_medico']) ?>><?= e($op['nombre'] . ' · ' . $op['codigo'] . ($op['estado'] !== 'activo' ? ' (inactivo)' : '')) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <noscript><button class="boton boton-primario">Ver</button></noscript>
        <?php if ($medico): ?>
        <div class="acciones"><a class="boton boton-secundario" href="<?= url("/medicos/{$idMedico}") ?>"><?= icono('medico') ?> Ficha del médico</a></div>
        <?php endif; ?>
    </form>

    <?php if (!$medico): ?>
        <div class="vacio"><?= icono('medico') ?><p>Primero registre un médico.</p></div>
    <?php else: ?>
    <div class="tarjeta-cuerpo">
        <h2 class="mb-1"><?= e($medico['nombre'] ?? 'Dr(a). ' . $medico['nombres'] . ' ' . $medico['apellidos']) ?> <span class="texto-suave texto-chico"><?= e($medico['especialidades']) ?></span></h2>
        <?php if ($medico['estado'] !== 'activo'): ?>
            <div class="alerta alerta-aviso alerta-plana mb-2"><?= icono('info') ?><p>El médico está inactivo: actívelo antes de asignarle horarios.</p></div>
        <?php endif; ?>
        <div class="bloques-dia">
            <?php foreach (DIAS_SEMANA as $n => $dia): ?>
            <div class="bloque-columna">
                <h3><?= e($dia) ?></h3>
                <div class="bloque-lista">
                    <?php foreach ($porDia[$n] ?? [] as $b): $activo = $b['estado'] === 'activo'; ?>
                    <div class="bloque <?= $activo ? '' : 'inactivo' ?>">
                        <b><?= e(hora($b['hora_inicio'])) ?>–<?= e(hora($b['hora_fin'])) ?></b>
                        Citas de <?= (int) $b['duracion_cita'] ?> min<?= $activo ? '' : ' · inactivo' ?>
                        <div class="acciones">
                            <button type="button" class="boton boton-texto boton-chico" data-modal-abrir="bloque-<?= $b['id_horario'] ?>" title="Modificar"><?= icono('editar') ?><span class="solo-lector">Modificar</span></button>
                            <form method="post" action="<?= url("/horarios/{$b['id_horario']}/estado") ?>" data-confirmar="<?= $activo ? '¿Desactivar este bloque? No se ofrecerán citas en este horario.' : '¿Activar este bloque?' ?>">
                                <?= csrf_campo() ?>
                                <button class="boton boton-texto boton-chico" title="<?= $activo ? 'Desactivar' : 'Activar' ?>"><?= icono('encendido') ?><span class="solo-lector"><?= $activo ? 'Desactivar' : 'Activar' ?></span></button>
                            </form>
                        </div>
                    </div>
                    <dialog class="modal" id="bloque-<?= $b['id_horario'] ?>">
                        <form method="post" action="<?= url("/horarios/{$b['id_horario']}") ?>" class="formulario" data-validar novalidate>
                            <?= csrf_campo() ?>
                            <div class="modal-cabecera"><h2>Modificar bloque</h2>
                                <button type="button" class="modal-cerrar" data-modal-cerrar aria-label="Cerrar"><?= icono('cerrar') ?></button></div>
                            <div class="modal-cuerpo">
                                <div class="fila-campos">
                                    <div class="campo"><label for="dia-<?= $b['id_horario'] ?>">Día</label>
                                        <select id="dia-<?= $b['id_horario'] ?>" name="dia_semana" required>
                                            <?php foreach (DIAS_SEMANA as $nd => $nombre): ?><option value="<?= $nd ?>"<?= seleccionado($b['dia_semana'], $nd) ?>><?= e($nombre) ?></option><?php endforeach; ?>
                                        </select></div>
                                    <div class="campo"><label for="dur-<?= $b['id_horario'] ?>">Duración de cada cita</label>
                                        <select id="dur-<?= $b['id_horario'] ?>" name="duracion_cita" required>
                                            <?php foreach ($duraciones as $d): ?><option value="<?= $d ?>"<?= seleccionado($b['duracion_cita'], $d) ?>><?= $d ?> minutos</option><?php endforeach; ?>
                                        </select></div>
                                    <div class="campo"><label for="ini-<?= $b['id_horario'] ?>">Hora inicial</label>
                                        <input type="time" id="ini-<?= $b['id_horario'] ?>" name="hora_inicio" value="<?= e(hora($b['hora_inicio'])) ?>" required min="06:00" max="22:00" step="300"></div>
                                    <div class="campo"><label for="fin-<?= $b['id_horario'] ?>">Hora final</label>
                                        <input type="time" id="fin-<?= $b['id_horario'] ?>" name="hora_fin" value="<?= e(hora($b['hora_fin'])) ?>" required min="06:00" max="22:00" step="300" data-posterior-a="ini-<?= $b['id_horario'] ?>"></div>
                                </div>
                                <p class="campo-ayuda">No se puede recortar un bloque si deja fuera citas pendientes.</p>
                            </div>
                            <div class="modal-pie">
                                <button type="button" class="boton boton-secundario" data-modal-cerrar>Cancelar</button>
                                <button class="boton boton-primario"><?= icono('check') ?> Guardar</button>
                            </div>
                        </form>
                    </dialog>
                    <?php endforeach; ?>
                    <?php if (empty($porDia[$n])): ?><span class="texto-tenue texto-chico texto-centro">Sin atención</span><?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</section>

<?php if ($medico): ?>
<div class="rejilla rejilla-2">
    <section class="tarjeta">
        <div class="tarjeta-cabecera"><div><h2><?= icono('mas') ?> Agregar bloque de atención</h2><p>La hora final debe ser posterior a la inicial y no superponerse con otro bloque (RF05).</p></div></div>
        <form method="post" action="<?= url('/horarios') ?>" class="tarjeta-cuerpo formulario" data-validar novalidate>
            <?= csrf_campo() ?>
            <input type="hidden" name="id_medico" value="<?= (int) $idMedico ?>">
            <div class="fila-campos">
                <div class="campo<?= clase_error($errores, 'dia_semana') ?>">
                    <label for="dia_semana">Día <span class="requerido">*</span></label>
                    <select id="dia_semana" name="dia_semana" required>
                        <option value="">Seleccione…</option>
                        <?php foreach (DIAS_SEMANA as $nd => $nombre): ?><option value="<?= $nd ?>"<?= seleccionado($anterior['dia_semana'] ?? '', $nd) ?>><?= e($nombre) ?></option><?php endforeach; ?>
                    </select>
                    <?= error_campo($errores, 'dia_semana') ?>
                </div>
                <div class="campo<?= clase_error($errores, 'duracion_cita') ?>">
                    <label for="duracion_cita">Duración de cada cita <span class="requerido">*</span></label>
                    <select id="duracion_cita" name="duracion_cita" required>
                        <?php foreach ($duraciones as $d): ?><option value="<?= $d ?>"<?= seleccionado($anterior['duracion_cita'] ?? 30, $d) ?>><?= $d ?> minutos</option><?php endforeach; ?>
                    </select>
                    <?= error_campo($errores, 'duracion_cita') ?>
                </div>
                <div class="campo<?= clase_error($errores, 'hora_inicio') ?>">
                    <label for="hora_inicio">Hora inicial <span class="requerido">*</span></label>
                    <input type="time" id="hora_inicio" name="hora_inicio" value="<?= viejo($anterior, 'hora_inicio', '08:00') ?>" required min="06:00" max="22:00" step="300">
                    <?= error_campo($errores, 'hora_inicio') ?>
                </div>
                <div class="campo<?= clase_error($errores, 'hora_fin') ?>">
                    <label for="hora_fin">Hora final <span class="requerido">*</span></label>
                    <input type="time" id="hora_fin" name="hora_fin" value="<?= viejo($anterior, 'hora_fin', '12:00') ?>" required min="06:00" max="22:00" step="300" data-posterior-a="hora_inicio">
                    <?= error_campo($errores, 'hora_fin') ?>
                </div>
            </div>
            <div class="acciones-formulario"><button class="boton boton-primario"<?= $medico['estado'] !== 'activo' ? ' disabled' : '' ?>><?= icono('mas') ?> Agregar bloque</button></div>
        </form>
    </section>

    <section class="tarjeta">
        <div class="tarjeta-cabecera"><div><h2><?= icono('calendario') ?> Feriados y ausencias</h2><p>Las fechas bloqueadas no se ofrecen en la agenda.</p></div></div>
        <form method="post" action="<?= url('/feriados') ?>" class="tarjeta-cuerpo formulario" data-validar novalidate>
            <?= csrf_campo() ?>
            <input type="hidden" name="volver_medico" value="<?= (int) $idMedico ?>">
            <div class="fila-campos">
                <div class="campo"><label for="fecha">Fecha <span class="requerido">*</span></label>
                    <input type="date" id="fecha" name="fecha" value="<?= viejo($anterior, 'fecha') ?>" required min="<?= date('Y-m-d') ?>"></div>
                <div class="campo"><label for="id_medico_feriado">Alcance</label>
                    <select id="id_medico_feriado" name="id_medico">
                        <option value="">Toda la clínica (feriado)</option>
                        <option value="<?= (int) $idMedico ?>">Solo este médico (ausencia)</option>
                    </select></div>
                <div class="campo campo-doble"><label for="descripcion">Descripción <span class="requerido">*</span></label>
                    <input type="text" id="descripcion" name="descripcion" value="<?= viejo($anterior, 'descripcion') ?>" required minlength="3" maxlength="120" placeholder="Ej. Feriado de Todos Santos · Vacaciones"></div>
            </div>
            <div class="acciones-formulario"><button class="boton boton-secundario"><?= icono('candado') ?> Bloquear fecha</button></div>
        </form>
        <?php if ($feriados): ?>
        <div class="tabla-contenedor">
            <table class="tabla">
                <thead><tr><th>Fecha</th><th>Descripción</th><th>Alcance</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($feriados as $f): ?>
                    <tr>
                        <td class="nowrap"><?= e(fecha_corta($f['fecha'])) ?><br><span class="texto-tenue texto-chico"><?= e(DIAS_SEMANA[(int) date('N', strtotime($f['fecha']))]) ?></span></td>
                        <td><?= e($f['descripcion']) ?></td>
                        <td class="texto-chico"><?= e($f['alcance']) ?></td>
                        <td class="celda-acciones">
                            <form method="post" action="<?= url("/feriados/{$f['id_dia_no_laborable']}/quitar") ?>" data-confirmar="¿Volver a habilitar el <?= e(fecha_corta($f['fecha'])) ?>?">
                                <?= csrf_campo() ?>
                                <input type="hidden" name="volver_medico" value="<?= (int) $idMedico ?>">
                                <button class="boton boton-texto boton-chico"><?= icono('cerrar') ?> Quitar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="tarjeta-pie texto-suave texto-chico">No hay fechas bloqueadas próximas.</div>
        <?php endif; ?>
    </section>
</div>
<?php endif; ?>
