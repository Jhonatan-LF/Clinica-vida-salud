<?php
/**
 * CU13 · Citas por fecha o rango, médico, especialidad, estado y texto (RF11, prueba 8).
 * Variables: $citas, $total, $resumen (estado => n), $pagina, $paginas, $filtros, $medicos, $especialidades.
 */
use App\Nucleo\Autenticacion;
use App\Nucleo\Vista;

$hoy = date('Y-m-d');
$estados = ['programada', 'confirmada', 'atendida', 'cancelada', 'inasistencia'];
?>
<div class="cabecera-seccion">
    <p>Por defecto se muestran las citas de hoy. Filtre por fecha, médico, especialidad o estado.</p>
    <div class="acciones">
        <a class="boton boton-secundario" href="<?= url('/citas', ['desde' => $hoy, 'hasta' => date('Y-m-d', strtotime('+6 days'))]) ?>"><?= icono('semana') ?> Próximos 7 días</a>
        <?php if (Autenticacion::puede('citas.programar')): ?>
            <a class="boton boton-primario" href="<?= url('/citas/nueva') ?>"><?= icono('calendario-mas') ?> Programar cita</a>
        <?php endif; ?>
    </div>
</div>

<section class="tarjeta">
    <form method="get" action="<?= url('/citas') ?>" class="filtros">
        <div class="campo"><label for="desde">Desde</label><input type="date" id="desde" name="desde" value="<?= e($filtros['desde']) ?>"></div>
        <div class="campo"><label for="hasta">Hasta</label><input type="date" id="hasta" name="hasta" value="<?= e($filtros['hasta']) ?>"></div>
        <div class="campo">
            <label for="medico">Médico</label>
            <select id="medico" name="medico"><option value="">Todos</option>
                <?php foreach ($medicos as $m): ?><option value="<?= $m['id_medico'] ?>"<?= seleccionado($filtros['medico'], $m['id_medico']) ?>><?= e($m['nombre']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="campo">
            <label for="especialidad">Especialidad</label>
            <select id="especialidad" name="especialidad"><option value="">Todas</option>
                <?php foreach ($especialidades as $esp): ?><option value="<?= $esp['id_especialidad'] ?>"<?= seleccionado($filtros['especialidad'], $esp['id_especialidad']) ?>><?= e($esp['nombre']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="campo">
            <label for="estado">Estado</label>
            <select id="estado" name="estado"><option value="">Todos</option>
                <?php foreach ($estados as $est): ?><option value="<?= $est ?>"<?= seleccionado($filtros['estado'], $est) ?>><?= ucfirst($est) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="campo campo-ancho">
            <label for="q">Paciente o código</label>
            <div class="buscador"><?= icono('buscar') ?><input type="search" id="q" name="q" value="<?= e($filtros['q']) ?>" placeholder="Nombre, CI o CM-0001"></div>
        </div>
        <div class="acciones">
            <button class="boton boton-primario"><?= icono('filtro') ?> Buscar</button>
            <a class="boton boton-texto" href="<?= url('/citas') ?>">Hoy</a>
        </div>
    </form>

    <div class="tarjeta-cuerpo totales" aria-label="Totales por estado">
        <span class="chip"><b><?= (int) $total ?> cita(s)</b></span>
        <?php foreach ($estados as $est): if (!empty($resumen[$est])): ?>
            <span class="total-estado"><?= estado_cita($est) ?> <b><?= (int) $resumen[$est] ?></b></span>
        <?php endif; endforeach; ?>
    </div>

    <?php if (!$citas): ?>
        <div class="vacio"><?= icono('calendario') ?><p>No hay citas con esos filtros.</p></div>
    <?php else: ?>
    <div class="tabla-contenedor">
        <table class="tabla tabla-adaptable tabla-citas">
            <thead><tr><th>Código</th><th>Fecha</th><th>Hora</th><th>Paciente</th><th>Médico</th><th>Especialidad</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($citas as $c): ?>
                <tr>
                    <td data-titulo="Código" class="mono"><a href="<?= url("/citas/{$c['id_cita']}") ?>"><?= e($c['codigo']) ?></a></td>
                    <td data-titulo="Fecha" class="nowrap"><?= e(fecha_corta($c['fecha'])) ?></td>
                    <td data-titulo="Hora" class="nowrap mono"><?= e(hora($c['hora_inicio'])) ?>–<?= e(hora($c['hora_fin'])) ?></td>
                    <td data-titulo="Paciente"><strong><?= e($c['paciente']) ?></strong><br><span class="texto-suave texto-chico"><?= e($c['ci_paciente'] ? 'CI ' . $c['ci_paciente'] : $c['codigo_paciente']) ?></span></td>
                    <td><?= e($c['medico']) ?></td>
                    <td data-titulo="Especialidad"><?= especialidad_color($c['especialidad'], $c['color_especialidad']) ?></td>
                    <td data-titulo="Estado"><?= estado_cita($c['estado']) ?><?= $c['modalidad'] === 'virtual' ? ' <span class="chip chip-virtual">' . icono('video') . ' Virtual</span>' : '' ?></td>
                    <td class="celda-acciones"><a class="boton boton-secundario boton-chico" href="<?= url("/citas/{$c['id_cita']}") ?>"><?= icono('ojo') ?> Ver</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <?= Vista::parcial('compartido/paginacion', ['pagina' => $pagina, 'paginas' => $paginas, 'total' => $total, 'ruta' => '/citas', 'parametros' => $filtros]) ?>
</section>
