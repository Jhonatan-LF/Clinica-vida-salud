<?php
/**
 * CU13 · Agenda semanal: cuadrícula horas × días (lunes a domingo) con colores por estado.
 * Marca los días no laborables y los días que el médico no atiende.
 * Variables: $medicos, $medico, $lunes, $domingo, $dias (fecha => [fecha, citas, no_laborable, atiende]), $soloPropia.
 */
$idMedico = $medico['id_medico'] ?? null;
$hoy = date('Y-m-d');

// Rango de horas a mostrar: de la primera a la última cita (mínimo 08:00–18:00).
$desdeHora = 8;
$hastaHora = 18;
$total = 0;
$especialidadesSemana = [];          // id => [nombre, color] para la leyenda
foreach ($dias as $d) {
    foreach ($d['citas'] as $c) {
        $desdeHora = min($desdeHora, (int) substr($c['hora_inicio'], 0, 2));
        $hastaHora = max($hastaHora, (int) substr($c['hora_fin'], 0, 2) + (substr($c['hora_fin'], 3, 2) !== '00' ? 1 : 0));
        $especialidadesSemana[$c['id_especialidad']] = [$c['especialidad'], $c['color_especialidad']];
        $total++;
    }
}
$parametros = ['medico' => $soloPropia ? null : $idMedico];
?>
<section class="tarjeta">
    <form method="get" action="<?= url('/agenda/semana') ?>" class="filtros">
        <?php if (!$soloPropia): ?>
        <div class="campo campo-ancho">
            <label for="medico">Médico</label>
            <select id="medico" name="medico" data-enviar-al-cambiar>
                <?php foreach ($medicos as $m): ?><option value="<?= $m['id_medico'] ?>"<?= seleccionado($idMedico, $m['id_medico']) ?>><?= e($m['nombre']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="campo">
            <label for="fecha">Semana del</label>
            <div class="selector-fecha">
                <a class="boton boton-secundario boton-icono" href="<?= url('/agenda/semana', $parametros + ['fecha' => date('Y-m-d', strtotime($lunes . ' -7 days'))]) ?>" aria-label="Semana anterior"><?= icono('izquierda') ?></a>
                <input type="date" id="fecha" name="fecha" value="<?= e($lunes) ?>" data-enviar-al-cambiar>
                <a class="boton boton-secundario boton-icono" href="<?= url('/agenda/semana', $parametros + ['fecha' => date('Y-m-d', strtotime($lunes . ' +7 days'))]) ?>" aria-label="Semana siguiente"><?= icono('derecha') ?></a>
            </div>
        </div>
        <div class="acciones">
            <noscript><button class="boton boton-primario">Ver</button></noscript>
            <a class="boton boton-texto" href="<?= url('/agenda/semana', $parametros) ?>">Esta semana</a>
            <a class="boton boton-secundario" href="<?= url('/agenda', $parametros) ?>"><?= icono('agenda') ?> Día</a>
            <button type="button" class="boton boton-secundario" data-imprimir><?= icono('imprimir') ?> Imprimir</button>
        </div>
    </form>

    <?php if (!$medico): ?>
        <div class="vacio"><?= icono('medico') ?><p>No hay médicos registrados.</p></div>
    <?php else: ?>
    <div class="tarjeta-cuerpo">
        <div class="reporte-titulo-impresion">
            <h1>Agenda semanal de <?= e($medico['nombre']) ?></h1>
            <p>Del <?= e(fecha_corta($lunes)) ?> al <?= e(fecha_corta($domingo)) ?> · Clínica Vida Salud</p>
        </div>
        <div class="cabecera-seccion mb-2">
            <div>
                <h2 class="mb-0">Del <?= e(fecha_larga($lunes, false)) ?> al <?= e(fecha_larga($domingo)) ?></h2>
                <p><?= e($medico['nombre']) ?> · <?= e($medico['especialidades']) ?> · <?= $total ?> cita(s)</p>
            </div>
            <div class="leyenda mt-0">
                <span><?= estado_cita('programada') ?></span><span><?= estado_cita('confirmada') ?></span><span><?= estado_cita('atendida') ?></span>
                <span><?= estado_cita('cancelada') ?></span><span><?= estado_cita('inasistencia') ?></span>
            </div>
        </div>
        <?php if ($especialidadesSemana): ?>
        <div class="leyenda leyenda-especialidades mb-2" aria-label="Colores por especialidad">
            <span class="texto-suave">Especialidades:</span>
            <?php foreach ($especialidadesSemana as [$nombreEsp, $colorEsp]): ?>
                <span><?= especialidad_color($nombreEsp, $colorEsp) ?></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="semana-contenedor">
            <div class="semana" role="table" aria-label="Agenda semanal">
                <div class="semana-cabecera" role="columnheader"><span class="solo-lector">Hora</span></div>
                <?php foreach ($dias as $d): $t = strtotime($d['fecha']); ?>
                    <div class="semana-cabecera<?= $d['fecha'] === $hoy ? ' hoy' : '' ?><?= $d['no_laborable'] ? ' no-laborable' : '' ?>" role="columnheader">
                        <?= e(DIAS_SEMANA[(int) date('N', $t)]) ?><strong><?= date('d', $t) ?></strong>
                        <span class="texto-chico texto-suave"><?= $d['no_laborable'] ? e($d['no_laborable']) : (!$d['atiende'] ? 'No atiende' : count(array_filter($d['citas'], static fn ($c) => $c['estado'] !== 'cancelada')) . ' cita(s)') ?></span>
                    </div>
                <?php endforeach; ?>

                <?php for ($h = $desdeHora; $h < $hastaHora; $h++): ?>
                    <div class="semana-hora" role="rowheader"><?= sprintf('%02d:00', $h) ?></div>
                    <?php foreach ($dias as $d):
                        $enHora = array_filter($d['citas'], static fn ($c) => (int) substr($c['hora_inicio'], 0, 2) === $h);
                        $clase = $d['no_laborable'] ? ' bloqueada' : (!$d['atiende'] ? ' no-atiende' : ''); ?>
                        <div class="semana-celda<?= $clase ?>" role="cell">
                            <?php foreach ($enHora as $c): ?>
                                <a class="semana-cita <?= e($c['estado']) ?>" href="<?= url("/citas/{$c['id_cita']}") ?>" title="<?= e($c['codigo'] . ' · ' . $c['paciente'] . ' · ' . $c['estado']) ?>">
                                    <i class="esp-punto" style="background:<?= color_especialidad($c['color_especialidad']) ?>" aria-hidden="true"></i><b><?= e(hora($c['hora_inicio'])) ?></b> <?= e($c['paciente']) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endfor; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</section>
