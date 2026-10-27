<?php
/**
 * CU07 · Ficha del médico: datos, especialidades, horario semanal y accesos a su agenda.
 * Variables: $medico, $horarios, $pendientes, $puedeGestionar.
 */
use App\Nucleo\Autenticacion;

$m = $medico;
$activo = $m['estado'] === 'activo';
$porDia = [];
foreach ($horarios as $h) {
    $porDia[(int) $h['dia_semana']][] = $h;
}
?>
<section class="tarjeta">
    <div class="tarjeta-cuerpo ficha-cabecera">
        <span class="avatar avatar-grande"><?= e(iniciales($m['nombres'] . ' ' . $m['apellidos'])) ?></span>
        <div>
            <h2>Dr(a). <?= e($m['nombres'] . ' ' . $m['apellidos']) ?></h2>
            <div class="texto-suave"><?= e($m['codigo']) ?> · Registro <?= e($m['registro_profesional']) ?> · <?= estado_registro($m['estado']) ?></div>
        </div>
        <div class="acciones">
            <?php if (Autenticacion::puede('agenda.ver')): ?>
                <a class="boton boton-secundario" href="<?= url('/agenda', ['medico' => $m['id_medico']]) ?>"><?= icono('agenda') ?> Agenda del día</a>
                <a class="boton boton-secundario" href="<?= url('/agenda/semana', ['medico' => $m['id_medico']]) ?>"><?= icono('semana') ?> Semana</a>
            <?php endif; ?>
            <?php if ($puedeGestionar): ?>
                <a class="boton boton-secundario" href="<?= url("/medicos/{$m['id_medico']}/editar") ?>"><?= icono('editar') ?> Modificar</a>
                <form method="post" action="<?= url("/medicos/{$m['id_medico']}/estado") ?>"
                      data-confirmar="<?= $activo ? '¿Desactivar al médico? No recibirá citas nuevas' . ($pendientes ? " y se cancelarán sus {$pendientes} cita(s) pendiente(s)" : '') . '. Su historial se conserva.' : '¿Activar al médico? Podrá recibir citas nuevas.' ?>">
                    <?= csrf_campo() ?>
                    <button class="boton <?= $activo ? 'boton-peligro' : 'boton-primario' ?>"><?= icono('encendido') ?> <?= $activo ? 'Desactivar' : 'Activar' ?></button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php if (!$activo): ?>
<div class="alerta alerta-aviso alerta-plana mt-2"><?= icono('info') ?><p>Este médico está inactivo: no aparece al programar citas (RN04).</p></div>
<?php endif; ?>

<div class="rejilla rejilla-lateral">
    <section class="tarjeta">
        <div class="tarjeta-cabecera">
            <div><h2><?= icono('reloj') ?> Horario de atención</h2><p>Bloques por día de la semana y duración de cada cita.</p></div>
            <?php if (Autenticacion::puede('horarios.gestionar')): ?>
                <a class="boton boton-secundario boton-chico" href="<?= url('/horarios', ['medico' => $m['id_medico']]) ?>"><?= icono('editar') ?> Gestionar horarios</a>
            <?php endif; ?>
        </div>
        <div class="tarjeta-cuerpo">
            <?php if (!$horarios): ?>
                <div class="vacio"><?= icono('reloj') ?><p>Sin horarios definidos: no puede recibir citas.</p></div>
            <?php else: ?>
            <div class="bloques-dia">
                <?php foreach (DIAS_SEMANA as $n => $dia): ?>
                <div class="bloque-columna">
                    <h3><?= e($dia) ?></h3>
                    <div class="bloque-lista">
                        <?php foreach ($porDia[$n] ?? [] as $h): ?>
                            <div class="bloque <?= $h['estado'] !== 'activo' ? 'inactivo' : '' ?>">
                                <b><?= e(hora($h['hora_inicio'])) ?>–<?= e(hora($h['hora_fin'])) ?></b>
                                <?= (int) $h['duracion_cita'] ?> min<?= $h['estado'] !== 'activo' ? ' · inactivo' : '' ?>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($porDia[$n])): ?><span class="texto-tenue texto-chico texto-centro">—</span><?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <aside class="tarjeta">
        <div class="tarjeta-cabecera"><h2><?= icono('persona') ?> Datos</h2></div>
        <div class="tarjeta-cuerpo">
            <dl class="datos datos-1">
                <div><dt>Especialidades</dt><dd><?= e($m['especialidades'] ?: '—') ?></dd></div>
                <div><dt>Teléfono</dt><dd><?= e($m['telefono']) ?></dd></div>
                <div><dt>Correo</dt><dd><?= e($m['correo']) ?></dd></div>
                <div><dt>Citas pendientes</dt><dd><?= (int) $pendientes ?></dd></div>
                <div><dt>Registrado</dt><dd><?= e(fecha_hora($m['creado_en'])) ?></dd></div>
            </dl>
        </div>
    </aside>
</div>
