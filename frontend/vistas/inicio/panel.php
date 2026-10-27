<?php
/**
 * Panel principal según el rol (RF01).
 *  - Administrador / recepción: indicadores de la clínica, citas de los últimos y próximos días y citas de hoy.
 *  - Médico: su día y su semana.
 *  - Paciente: recordatorios de las próximas 48 h y sus próximas citas.
 * Variables: $usuario, $indicadores y, según el rol, $serie, $estadosMes, $hoy, $proximas, $recordatorios.
 */
use App\Nucleo\Autenticacion;
use App\Nucleo\Vista;

$rol = $usuario['rol'];
$k = $indicadores;
$nombre = explode(' ', preg_replace('/^(Dr|Dra)\.\s+/u', '', $usuario['nombre_completo']))[0];
?>
<div class="bienvenida">
    <div>
        <h2><?= e(saludo()) ?>, <?= e($nombre) ?></h2>
        <p><?= e(ucfirst(fecha_larga(date('Y-m-d')))) ?> · <?= match ($rol) {
            'paciente' => 'Este es su portal de salud.',
            'medico'   => 'Esta es su jornada de hoy.',
            default    => 'Resumen de la actividad de la clínica.',
        } ?></p>
    </div>
    <div class="acciones">
        <?php if (Autenticacion::puede('citas.programar')): ?>
            <a class="boton boton-secundario" href="<?= url('/citas/nueva') ?>"><?= icono('calendario-mas') ?> Programar cita</a>
        <?php elseif (Autenticacion::puede('portal.reservar')): ?>
            <a class="boton boton-secundario" href="<?= url('/reservar') ?>"><?= icono('calendario-mas') ?> Reservar cita</a>
        <?php elseif (Autenticacion::puede('agenda.propia')): ?>
            <a class="boton boton-secundario" href="<?= url('/agenda') ?>"><?= icono('agenda') ?> Ver mi agenda</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($rol === 'paciente'): ?>
    <?php foreach ($recordatorios as $r): ?>
        <div class="recordatorio">
            <?= icono('campana', 'ico-grande') ?>
            <div><strong>Recordatorio: <?= e($r['especialidad']) ?> <?= $r['fecha'] === date('Y-m-d') ? 'hoy' : 'el ' . e(fecha_larga($r['fecha'], false)) ?> a las <?= e(hora($r['hora_inicio'])) ?></strong><br>
                <span class="texto-suave"><?= e($r['medico']) ?> · <?= e($r['codigo']) ?> · <?= $r['modalidad'] === 'virtual' ? 'Consulta virtual' : 'Preséntese 10 minutos antes en recepción' ?></span></div>
        </div>
    <?php endforeach; ?>

    <div class="rejilla rejilla-3">
        <div class="tarjeta indicador indicador-azul"><span class="indicador-icono"><?= icono('calendario') ?></span><div><div class="indicador-valor"><?= (int) $k['pendientes'] ?></div><div class="indicador-etiqueta">Citas pendientes</div></div></div>
        <div class="tarjeta indicador"><span class="indicador-icono"><?= icono('check-circulo') ?></span><div><div class="indicador-valor"><?= (int) $k['atendidas'] ?></div><div class="indicador-etiqueta">Consultas atendidas</div></div></div>
        <div class="tarjeta indicador indicador-naranja"><span class="indicador-icono"><?= icono('x-circulo') ?></span><div><div class="indicador-valor"><?= (int) $k['canceladas'] ?></div><div class="indicador-etiqueta">Canceladas</div></div></div>
    </div>

    <section class="tarjeta">
        <div class="tarjeta-cabecera"><h2><?= icono('calendario') ?> Mis próximas citas</h2><a class="boton boton-texto boton-chico" href="<?= url('/mis-citas') ?>">Ver todas <?= icono('derecha') ?></a></div>
        <?php if (!$proximas): ?>
            <div class="vacio"><?= icono('calendario-mas') ?><p>No tiene citas próximas. <a href="<?= url('/reservar') ?>">Reserve una ahora</a>.</p></div>
        <?php else: foreach ($proximas as $c): ?>
            <div class="cita-fila">
                <div class="cita-fecha-bloque"><b><?= date('d', strtotime($c['fecha'])) ?></b><small><?= e(mb_substr(MESES[(int) date('n', strtotime($c['fecha']))], 0, 3)) ?></small></div>
                <div class="cita-info"><strong><?= e($c['especialidad']) ?> · <?= e(hora($c['hora_inicio'])) ?></strong><span><?= e($c['medico']) ?> · <?= e($c['codigo']) ?></span></div>
                <?= estado_cita($c['estado']) ?>
                <a class="boton boton-texto boton-chico" href="<?= url("/citas/{$c['id_cita']}") ?>">Detalle</a>
            </div>
        <?php endforeach; endif; ?>
    </section>

<?php elseif ($rol === 'medico'): ?>
    <div class="rejilla rejilla-4">
        <div class="tarjeta indicador"><span class="indicador-icono"><?= icono('agenda') ?></span><div><div class="indicador-valor"><?= (int) $k['citas_hoy'] ?></div><div class="indicador-etiqueta">Citas hoy</div></div></div>
        <div class="tarjeta indicador"><span class="indicador-icono"><?= icono('check-circulo') ?></span><div><div class="indicador-valor"><?= (int) $k['atendidas_hoy'] ?></div><div class="indicador-etiqueta">Atendidas hoy</div></div></div>
        <div class="tarjeta indicador indicador-azul"><span class="indicador-icono"><?= icono('semana') ?></span><div><div class="indicador-valor"><?= (int) $k['proxima_semana'] ?></div><div class="indicador-etiqueta">Próximos 7 días</div></div></div>
        <div class="tarjeta indicador indicador-turquesa"><span class="indicador-icono"><?= icono('reporte') ?></span><div><div class="indicador-valor"><?= (int) $k['atendidas_mes'] ?></div><div class="indicador-etiqueta">Atendidas este mes</div></div></div>
    </div>
    <?= Vista::parcial('inicio/citas_hoy', ['citas' => $hoy, 'mostrarMedico' => false]) ?>

<?php else: ?>
    <div class="rejilla rejilla-4">
        <div class="tarjeta indicador"><span class="indicador-icono"><?= icono('agenda') ?></span><div><div class="indicador-valor"><?= (int) $k['citas_hoy'] ?></div><div class="indicador-etiqueta">Citas hoy · <?= (int) $k['atendidas_hoy'] ?> atendidas</div></div></div>
        <div class="tarjeta indicador indicador-azul"><span class="indicador-icono"><?= icono('campana') ?></span><div><div class="indicador-valor"><?= (int) $k['por_confirmar'] ?></div><div class="indicador-etiqueta">Por confirmar (hoy y mañana)</div></div></div>
        <div class="tarjeta indicador indicador-turquesa"><span class="indicador-icono"><?= icono('check-circulo') ?></span><div><div class="indicador-valor"><?= (int) $k['asistencia'] ?>%</div><div class="indicador-etiqueta">Asistencia del mes</div></div></div>
        <div class="tarjeta indicador indicador-naranja"><span class="indicador-icono"><?= icono('pacientes') ?></span><div><div class="indicador-valor"><?= (int) $k['pacientes'] ?></div><div class="indicador-etiqueta">Pacientes · <?= (int) $k['medicos'] ?> médicos activos</div></div></div>
    </div>

    <div class="rejilla rejilla-lateral">
        <section class="tarjeta">
            <div class="tarjeta-cabecera"><div><h2><?= icono('reporte') ?> Citas por día</h2><p>Últimos 10 días y próximos 4 (sin canceladas).</p></div></div>
            <div class="tarjeta-cuerpo">
                <?php $max = max(1, ...array_map(static fn ($s) => (int) $s['total'], $serie)); ?>
                <div class="grafico" role="img" aria-label="Gráfico de citas por día">
                    <?php foreach ($serie as $s): $esHoy = $s['fecha'] === date('Y-m-d'); $futura = $s['fecha'] > date('Y-m-d'); ?>
                    <div class="grafico-columna" title="<?= e(fecha_corta($s['fecha'])) ?>: <?= (int) $s['total'] ?> cita(s)">
                        <span class="grafico-valor"><?= (int) $s['total'] ?></span>
                        <span class="grafico-barra<?= $esHoy ? ' hoy' : ($futura ? ' futura' : '') ?>" style="height: <?= round(100 * (int) $s['total'] / $max) ?>%"></span>
                        <span class="grafico-etiqueta<?= $esHoy ? ' hoy' : '' ?>"><?= $esHoy ? 'Hoy' : e(mb_substr(DIAS_SEMANA[(int) date('N', strtotime($s['fecha']))], 0, 2) . ' ' . date('d', strtotime($s['fecha']))) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <section class="tarjeta">
            <div class="tarjeta-cabecera"><div><h2><?= icono('citas') ?> Estados del mes</h2><p><?= e(ucfirst(MESES[(int) date('n')])) ?> de <?= date('Y') ?></p></div></div>
            <div class="tarjeta-cuerpo">
                <?php $totalMes = array_sum(array_map(static fn ($e) => (int) $e['total'], $estadosMes)); ?>
                <div class="barras-h">
                    <?php foreach ($estadosMes as $em): ?>
                    <div class="barra-h">
                        <span><?= e(ucfirst($em['estado'])) ?></span>
                        <span class="barra-h-pista"><span class="barra-h-relleno <?= e($em['estado']) ?>" style="width: <?= $totalMes ? round(100 * $em['total'] / $totalMes) : 0 ?>%"></span></span>
                        <b class="texto-derecha mono"><?= (int) $em['total'] ?></b>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if (Autenticacion::puede('reportes.ver')): ?>
                    <a class="boton boton-texto boton-chico mt-2" href="<?= url('/reportes', ['tipo' => 'estado']) ?>"><?= icono('reporte') ?> Ver reporte por estado</a>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <?= Vista::parcial('inicio/citas_hoy', ['citas' => $hoy, 'mostrarMedico' => true]) ?>
<?php endif; ?>
