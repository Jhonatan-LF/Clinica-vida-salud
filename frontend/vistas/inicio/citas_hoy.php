<?php
/**
 * Panel · Citas de hoy con acciones rápidas (CU12: confirmar, atender, inasistencia).
 * Uso: Vista::parcial('inicio/citas_hoy', ['citas' => $hoy, 'mostrarMedico' => true])
 */
use App\Nucleo\Autenticacion;
use App\Paquetes\Citas\Servicios\EstadoCita;

$u = Autenticacion::usuario();
$textos = ['confirmada' => ['Confirmar', 'check', 'boton-secundario'], 'atendida' => ['Atendida', 'check-circulo', 'boton-exito'],
           'inasistencia' => ['No asistió', 'alerta', 'boton-secundario']];
$pendientes = count(array_filter($citas, static fn ($c) => in_array($c['estado'], ['programada', 'confirmada'], true)));
?>
<section class="tarjeta">
    <div class="tarjeta-cabecera">
        <div><h2><?= icono('agenda') ?> Citas de hoy</h2><p><?= count($citas) ?> cita(s) · <?= $pendientes ?> pendiente(s)</p></div>
        <?php if (Autenticacion::puede(['agenda.ver', 'agenda.propia'])): ?>
            <a class="boton boton-texto boton-chico" href="<?= url('/agenda') ?>">Abrir agenda <?= icono('derecha') ?></a>
        <?php endif; ?>
    </div>
    <?php if (!$citas): ?>
        <div class="vacio"><?= icono('calendario') ?><p>No hay citas para hoy.</p></div>
    <?php else: foreach ($citas as $c):
        $acciones = array_filter(EstadoCita::acciones($c), static fn ($a) => match ($a) {
            'confirmada', 'inasistencia' => Autenticacion::puede('citas.confirmar'),
            'atendida' => Autenticacion::puede('citas.atender') && ($u['rol'] !== 'medico' || (int) $u['id_medico'] === (int) $c['id_medico']),
            default => false,
        }); ?>
        <div class="cita-fila">
            <div class="cita-hora"><?= e(hora($c['hora_inicio'])) ?><span><?= e(hora($c['hora_fin'])) ?></span></div>
            <div class="cita-info">
                <strong><?= e($c['paciente']) ?></strong>
                <span><?= $mostrarMedico ? e($c['medico']) . ' · ' : '' ?><?= especialidad_color($c['especialidad'], $c['color_especialidad']) ?> · <a href="<?= url("/citas/{$c['id_cita']}") ?>"><?= e($c['codigo']) ?></a></span>
            </div>
            <?= estado_cita($c['estado']) ?>
            <div class="acciones">
                <?php foreach ($acciones as $a): [$texto, $ico, $clase] = $textos[$a]; ?>
                <form method="post" action="<?= url("/citas/{$c['id_cita']}/estado") ?>"<?= $a === 'inasistencia' ? ' data-confirmar="¿Registrar que el paciente no se presentó?"' : '' ?>>
                    <?= csrf_campo() ?>
                    <input type="hidden" name="estado" value="<?= $a ?>">
                    <input type="hidden" name="volver" value="/panel">
                    <button class="boton <?= $clase ?> boton-chico"><?= icono($ico) ?> <?= $texto ?></button>
                </form>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; endif; ?>
</section>
