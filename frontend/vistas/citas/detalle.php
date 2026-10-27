<?php
/**
 * CU13 · Detalle de una cita con su historial de cambios (RN10).
 * Desde aquí: confirmar, atender, registrar inasistencia (CU12), reprogramar (CU10) y cancelar (CU11).
 * Variables: $cita, $historial, $acciones (posibles según EstadoCita), $paciente.
 */
use App\Nucleo\Aplicacion;
use App\Nucleo\Autenticacion;

$c = $cita;
$u = Autenticacion::usuario();
$esPaciente = $u['rol'] === 'paciente';
$esSuMedico = $u['rol'] === 'medico' && (int) $u['id_medico'] === (int) $c['id_medico'];
$horasPortal = (int) Aplicacion::config('citas.horas_minimas_cancelar_portal', 2);

// Botones visibles: acción posible (EstadoCita) Y permiso del usuario (RN12).
$botones = [];
foreach ($acciones as $a) {
    $permitido = match ($a) {
        'confirmada'   => Autenticacion::puede('citas.confirmar'),
        'inasistencia' => Autenticacion::puede('citas.confirmar'),
        'atendida'     => Autenticacion::puede('citas.atender') && ($u['rol'] !== 'medico' || $esSuMedico),
        'cancelada'    => Autenticacion::puede('citas.cancelar')
                          || ($esPaciente && strtotime($c['fecha'] . ' ' . $c['hora_inicio']) - time() >= $horasPortal * 3600),
        'reprogramar'  => Autenticacion::puede('citas.reprogramar'),
        default        => false,
    };
    if ($permitido) {
        $botones[] = $a;
    }
}
$accionCancelar = $esPaciente ? url("/mis-citas/{$c['id_cita']}/cancelar") : url("/citas/{$c['id_cita']}/cancelar");
$verInfoMedica = $paciente && (Autenticacion::puede('pacientes.ver') || ($esSuMedico && $paciente['compartir_info_medica']));
$textosEstado = ['confirmada' => ['Confirmar asistencia', 'check', 'boton-secundario'],
                 'atendida' => ['Marcar como atendida', 'check-circulo', 'boton-exito'],
                 'inasistencia' => ['Registrar inasistencia', 'alerta', 'boton-secundario']];
?>
<section class="tarjeta">
    <div class="tarjeta-cuerpo ficha-cabecera">
        <div class="cita-fecha-bloque"><b><?= date('d', strtotime($c['fecha'])) ?></b><small><?= e(mb_substr(MESES[(int) date('n', strtotime($c['fecha']))], 0, 3)) ?></small></div>
        <div>
            <h2><?= e($c['codigo']) ?> · <?= e($c['especialidad']) ?></h2>
            <div class="texto-suave"><?= e(ucfirst(fecha_larga($c['fecha']))) ?> · <?= e(hora($c['hora_inicio'])) ?> a <?= e(hora($c['hora_fin'])) ?> · <?= estado_cita($c['estado']) ?></div>
        </div>
        <div class="acciones">
            <?php foreach ($botones as $b): if (isset($textosEstado[$b])): [$texto, $ico, $clase] = $textosEstado[$b]; ?>
                <form method="post" action="<?= url("/citas/{$c['id_cita']}/estado") ?>"<?= $b === 'inasistencia' ? ' data-confirmar="¿Registrar que el paciente no se presentó?"' : '' ?>>
                    <?= csrf_campo() ?>
                    <input type="hidden" name="estado" value="<?= $b ?>">
                    <input type="hidden" name="volver" value="/citas/<?= (int) $c['id_cita'] ?>">
                    <button class="boton <?= $clase ?>"><?= icono($ico) ?> <?= $texto ?></button>
                </form>
            <?php endif; endforeach; ?>
            <a class="boton boton-secundario" href="<?= url("/citas/{$c['id_cita']}/comprobante") ?>" target="_blank" rel="noopener"><?= icono('imprimir') ?> Comprobante</a>
            <?php if (in_array('reprogramar', $botones, true)): ?>
                <a class="boton boton-secundario" href="<?= url("/citas/{$c['id_cita']}/reprogramar") ?>"><?= icono('calendario') ?> Reprogramar</a>
            <?php endif; ?>
            <?php if (in_array('cancelada', $botones, true)): ?>
                <button type="button" class="boton boton-peligro" data-modal-abrir="modal-cancelar"><?= icono('x-circulo') ?> Cancelar cita</button>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php if ($c['estado'] === 'cancelada'): ?>
<div class="alerta alerta-info alerta-plana mt-2"><?= icono('info') ?><p>Cita cancelada: <?= e($c['motivo_cancelacion']) ?>. El horario quedó libre y la cita se conserva en el historial (RN09, RN10).</p></div>
<?php endif; ?>

<div class="rejilla rejilla-lateral">
    <div>
        <section class="tarjeta">
            <div class="tarjeta-cabecera"><h2><?= icono('citas') ?> Datos de la cita</h2></div>
            <div class="tarjeta-cuerpo">
                <dl class="datos datos-3">
                    <div><dt>Paciente</dt><dd><?= Autenticacion::puede('pacientes.ver') ? '<a href="' . url("/pacientes/{$c['id_paciente']}") . '">' . e($c['paciente']) . '</a>' : e($c['paciente']) ?></dd></div>
                    <div><dt>CI / Código</dt><dd><?= e(($c['ci_paciente'] ?: 'sin CI') . ' · ' . $c['codigo_paciente']) ?></dd></div>
                    <div><dt>Teléfono</dt><dd><?= e($c['telefono_paciente']) ?></dd></div>
                    <div><dt>Médico</dt><dd><?= e($c['medico']) ?></dd></div>
                    <div><dt>Especialidad</dt><dd><?= especialidad_color($c['especialidad'], $c['color_especialidad']) ?></dd></div>
                    <div><dt>Modalidad</dt><dd><?= $c['modalidad'] === 'virtual' ? '<span class="chip chip-virtual">' . icono('video') . ' Virtual</span>' : 'Presencial' ?></dd></div>
                    <div class="campo-total"><dt>Motivo de la consulta</dt><dd><?= e($c['motivo'] ?: 'No indicado') ?></dd></div>
                    <div><dt>Registrada</dt><dd><?= e(fecha_hora($c['creado_en'])) ?></dd></div>
                    <div><dt>Registrada por</dt><dd><?= e($c['registrado_por'] ?: '—') ?></dd></div>
                    <div><dt>Última modificación</dt><dd><?= e(fecha_hora($c['actualizado_en'])) ?></dd></div>
                </dl>
            </div>
        </section>

        <?php if ($verInfoMedica): ?>
        <section class="tarjeta">
            <div class="tarjeta-cabecera"><h2><?= icono('corazon') ?> Información médica del paciente</h2></div>
            <div class="tarjeta-cuerpo">
                <dl class="datos datos-3">
                    <div><dt>Edad</dt><dd><?= e(edad($paciente['fecha_nacimiento'])) ?></dd></div>
                    <div><dt>Tipo de sangre</dt><dd><?= e($paciente['tipo_sangre'] ?: '—') ?></dd></div>
                    <div><dt>Alergias</dt><dd><?= $paciente['alergias'] ? '<span class="chip chip-alerta">' . icono('alerta') . ' ' . e($paciente['alergias']) . '</span>' : 'Ninguna registrada' ?></dd></div>
                    <div><dt>Enfermedades crónicas</dt><dd><?= e($paciente['enfermedades_cronicas'] ?: '—') ?></dd></div>
                    <div><dt>Medicamentos</dt><dd><?= e($paciente['medicamentos'] ?: '—') ?></dd></div>
                    <div><dt>Antecedentes</dt><dd><?= e($paciente['antecedentes'] ?: '—') ?></dd></div>
                </dl>
                <?php if (Autenticacion::puede('pacientes.historial')): ?>
                    <a class="boton boton-texto boton-chico mt-2" href="<?= url("/pacientes/{$c['id_paciente']}/historial") ?>"><?= icono('historial') ?> Historial de citas del paciente</a>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>
    </div>

    <aside class="tarjeta">
        <div class="tarjeta-cabecera"><h2><?= icono('historial') ?> Historial de la cita</h2></div>
        <div class="tarjeta-cuerpo">
            <ol class="linea-tiempo">
            <?php foreach ($historial as $h):
                $clase = 'evento-' . $h['accion'] . ($h['estado_nuevo'] === 'cancelada' ? ' evento-cancelada' : '');
                $titulo = match ($h['accion']) {
                    'registro'       => 'Cita registrada',
                    'estado'         => "Cambio de estado de {$h['estado_anterior']} a {$h['estado_nuevo']}",
                    'reprogramacion' => 'Reprogramada del ' . fecha_corta($h['fecha_anterior']) . ' ' . hora($h['hora_anterior'])
                                        . ' al ' . fecha_corta($h['fecha_nueva']) . ' ' . hora($h['hora_nueva']),
                    default          => $h['accion'],
                }; ?>
                <li class="<?= e($clase) ?>">
                    <strong><?= e($titulo) ?></strong>
                    <?php if ($h['detalle'] && $h['detalle'] !== $titulo): ?><span class="texto-chico"><?= e($h['detalle']) ?></span><br><?php endif; ?>
                    <span class="texto-suave"><?= e(fecha_hora($h['fecha_hora'])) ?><?= $h['usuario'] ? ' · ' . e($h['usuario']) : '' ?></span>
                </li>
            <?php endforeach; ?>
            </ol>
        </div>
    </aside>
</div>

<?php if (in_array('cancelada', $botones, true)): ?>
<dialog class="modal" id="modal-cancelar" aria-labelledby="titulo-cancelar">
    <form method="post" action="<?= $accionCancelar ?>" class="formulario" data-validar novalidate>
        <?= csrf_campo() ?>
        <div class="modal-cabecera"><h2 id="titulo-cancelar">Cancelar cita <?= e($c['codigo']) ?></h2>
            <button type="button" class="modal-cerrar" data-modal-cerrar aria-label="Cerrar"><?= icono('cerrar') ?></button></div>
        <div class="modal-cuerpo">
            <p>La cita no se elimina: cambia a <strong>cancelada</strong>, libera el horario y queda en el historial.</p>
            <div class="campo mb-0">
                <label for="motivo">Motivo de la cancelación <span class="requerido">*</span></label>
                <textarea id="motivo" name="motivo" required minlength="5" maxlength="250" rows="3" placeholder="Ej. El paciente avisó que no podrá asistir"></textarea>
            </div>
        </div>
        <div class="modal-pie">
            <button type="button" class="boton boton-secundario" data-modal-cerrar>Volver</button>
            <button class="boton boton-peligro-lleno"><?= icono('x-circulo') ?> Cancelar cita</button>
        </div>
    </form>
</dialog>
<?php endif; ?>
