<?php
/**
 * CU03 · Ficha del paciente: datos, información médica, contacto de emergencia y últimas citas.
 * Variables: $paciente, $contactos, $resumen (estado => n), $recientes, $puedeGestionar.
 */
use App\Nucleo\Autenticacion;

$p = $paciente;
$activo = $p['estado'] === 'activo';
$sexos = ['femenino' => 'Femenino', 'masculino' => 'Masculino', 'otro' => 'Otro'];
$totalCitas = array_sum($resumen);
?>
<section class="tarjeta">
    <div class="tarjeta-cuerpo ficha-cabecera">
        <span class="avatar avatar-grande"><?= e(iniciales($p['nombres'] . ' ' . $p['apellidos'])) ?></span>
        <div>
            <h2><?= e($p['nombres'] . ' ' . $p['apellidos']) ?></h2>
            <div class="texto-suave"><?= e($p['codigo']) ?> · CI <?= e($p['ci'] ?: 'sin registrar') ?> · <?= e(edad($p['fecha_nacimiento'])) ?> · <?= estado_registro($p['estado']) ?></div>
        </div>
        <div class="acciones">
            <?php if (Autenticacion::puede('pacientes.historial')): ?>
                <a class="boton boton-secundario" href="<?= url("/pacientes/{$p['id_paciente']}/historial") ?>"><?= icono('historial') ?> Historial</a>
            <?php endif; ?>
            <?php if ($puedeGestionar): ?>
                <a class="boton boton-secundario" href="<?= url("/pacientes/{$p['id_paciente']}/editar") ?>"><?= icono('editar') ?> Modificar</a>
                <form method="post" action="<?= url("/pacientes/{$p['id_paciente']}/estado") ?>"
                      data-confirmar="<?= $activo ? '¿Desactivar a este paciente? Se cancelarán sus citas pendientes, pero su historial se conserva.' : '¿Reactivar a este paciente?' ?>">
                    <?= csrf_campo() ?>
                    <button class="boton <?= $activo ? 'boton-peligro' : 'boton-secundario' ?>"><?= icono('encendido') ?> <?= $activo ? 'Desactivar' : 'Reactivar' ?></button>
                </form>
            <?php endif; ?>
            <?php if ($activo && Autenticacion::puede('citas.programar')): ?>
                <a class="boton boton-primario" href="<?= url('/citas/nueva', ['paciente' => $p['id_paciente']]) ?>"><?= icono('calendario-mas') ?> Programar cita</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<div class="rejilla rejilla-lateral">
    <div>
        <section class="tarjeta">
            <div class="tarjeta-cabecera"><h2><?= icono('persona') ?> Datos personales y de contacto</h2></div>
            <div class="tarjeta-cuerpo">
                <dl class="datos datos-3">
                    <div><dt>Fecha de nacimiento</dt><dd><?= e(fecha_corta($p['fecha_nacimiento'])) ?></dd></div>
                    <div><dt>Sexo</dt><dd><?= e($sexos[$p['sexo']] ?? $p['sexo']) ?></dd></div>
                    <div><dt>Teléfono</dt><dd><?= e($p['telefono']) ?></dd></div>
                    <div><dt>Correo</dt><dd><?= e($p['correo'] ?: '—') ?></dd></div>
                    <div><dt>Dirección</dt><dd><?= e($p['direccion'] ?: '—') ?></dd></div>
                    <div><dt>Cuenta en el portal</dt><dd><?= $p['cuenta'] ? e($p['cuenta']) : '<span class="texto-tenue">Sin cuenta</span>' ?></dd></div>
                    <div><dt>Registrado</dt><dd><?= e(fecha_hora($p['creado_en'])) ?></dd></div>
                    <div><dt>Recordatorios</dt><dd><?= $p['recibir_recordatorios'] ? 'Sí' : 'No' ?></dd></div>
                </dl>
            </div>
        </section>

        <section class="tarjeta">
            <div class="tarjeta-cabecera"><h2><?= icono('corazon') ?> Información médica</h2>
                <?php if (!$p['compartir_info_medica']): ?><span class="chip"><?= icono('candado') ?> No se comparte con el médico</span><?php endif; ?></div>
            <div class="tarjeta-cuerpo">
                <dl class="datos">
                    <div><dt>Tipo de sangre</dt><dd><?= $p['tipo_sangre'] ? '<span class="chip">' . icono('gota') . ' ' . e($p['tipo_sangre']) . '</span>' : '—' ?></dd></div>
                    <div><dt>Alergias</dt><dd><?= $p['alergias'] ? '<span class="chip chip-alerta">' . icono('alerta') . ' ' . e($p['alergias']) . '</span>' : 'Ninguna registrada' ?></dd></div>
                    <div><dt>Enfermedades crónicas</dt><dd><?= e($p['enfermedades_cronicas'] ?: '—') ?></dd></div>
                    <div><dt>Medicamentos</dt><dd><?= e($p['medicamentos'] ?: '—') ?></dd></div>
                    <div class="campo-total"><dt>Antecedentes</dt><dd><?= e($p['antecedentes'] ?: '—') ?></dd></div>
                </dl>
            </div>
        </section>

        <section class="tarjeta">
            <div class="tarjeta-cabecera"><h2><?= icono('citas') ?> Últimas citas</h2>
                <?php if (Autenticacion::puede('pacientes.historial')): ?><a href="<?= url("/pacientes/{$p['id_paciente']}/historial") ?>" class="boton boton-texto boton-chico">Ver historial completo <?= icono('derecha') ?></a><?php endif; ?></div>
            <?php if (!$recientes): ?>
                <div class="vacio"><?= icono('calendario') ?><p>El paciente aún no tiene citas.</p></div>
            <?php else: foreach ($recientes as $c): ?>
                <div class="cita-fila">
                    <div class="cita-fecha-bloque"><b><?= date('d', strtotime($c['fecha'])) ?></b><small><?= e(mb_substr(MESES[(int) date('n', strtotime($c['fecha']))], 0, 3)) ?></small></div>
                    <div class="cita-info">
                        <strong><?= e($c['especialidad']) ?> · <?= e(hora($c['hora_inicio'])) ?></strong>
                        <span><?= e($c['medico']) ?> · <?= e($c['codigo']) ?></span>
                    </div>
                    <?= estado_cita($c['estado']) ?>
                    <?php if (Autenticacion::puede('citas.ver')): ?><a class="boton boton-texto boton-chico" href="<?= url("/citas/{$c['id_cita']}") ?>"><?= icono('derecha') ?><span class="solo-lector">Ver cita</span></a><?php endif; ?>
                </div>
            <?php endforeach; endif; ?>
        </section>
    </div>

    <aside>
        <section class="tarjeta">
            <div class="tarjeta-cabecera"><h2><?= icono('alerta') ?> Contacto de emergencia</h2></div>
            <div class="tarjeta-cuerpo">
                <?php if (!$contactos): ?>
                    <p class="texto-suave mb-0">Sin contacto de emergencia registrado.</p>
                <?php else: foreach ($contactos as $ce): ?>
                    <p class="mb-0"><strong><?= e($ce['nombre']) ?></strong><br>
                        <span class="texto-suave"><?= e($ce['parentesco']) ?> · <?= icono('telefono') ?> <?= e($ce['telefono']) ?></span></p>
                <?php endforeach; endif; ?>
            </div>
        </section>
        <section class="tarjeta">
            <div class="tarjeta-cabecera"><h2><?= icono('reporte') ?> Resumen de citas</h2></div>
            <div class="tarjeta-cuerpo">
                <div class="barras-h">
                    <?php foreach ($resumen as $estado => $n): ?>
                    <div class="barra-h">
                        <span><?= e(ucfirst($estado)) ?></span>
                        <span class="barra-h-pista"><span class="barra-h-relleno <?= e($estado) ?>" style="width: <?= $totalCitas ? round(100 * $n / $totalCitas) : 0 ?>%"></span></span>
                        <b class="texto-derecha mono"><?= (int) $n ?></b>
                    </div>
                    <?php endforeach; ?>
                </div>
                <p class="texto-suave texto-chico mt-2 mb-0">Total: <?= $totalCitas ?> cita(s) registradas.</p>
            </div>
        </section>
    </aside>
</div>
