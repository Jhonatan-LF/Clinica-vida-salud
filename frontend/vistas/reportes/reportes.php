<?php
/**
 * CU14 · Reportes administrativos (RF13): en pantalla, impresión/PDF (Ctrl+P) y CSV.
 * Variables: $tipo, $tipos, $filtros (desde, hasta, estado), $filas, $totales (solo "pacientes").
 */
$f = $filtros;
$soloFecha = $tipo === 'agenda';
$estados = ['programada', 'confirmada', 'atendida', 'cancelada', 'inasistencia'];
$periodo = $soloFecha || $f['desde'] === $f['hasta']
    ? 'del ' . fecha_corta($f['desde'])
    : 'del ' . fecha_corta($f['desde']) . ' al ' . fecha_corta($f['hasta']);
$suma = static fn (string $col) => array_sum(array_map(static fn ($r) => (int) $r[$col], $filas));
$sexos = ['femenino' => 'F', 'masculino' => 'M', 'otro' => 'Otro'];
?>
<section class="tarjeta no-imprimir">
    <div class="tarjeta-cuerpo">
        <span class="etiqueta">Tipo de reporte</span>
        <div class="tipos-reporte mt-1">
            <?php foreach ($tipos as $clave => $texto): ?>
                <a class="boton <?= $clave === $tipo ? 'boton-primario' : 'boton-secundario' ?> boton-chico" href="<?= url('/reportes', ['tipo' => $clave]) ?>"><?= e($texto) ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    <form method="get" action="<?= url('/reportes') ?>" class="filtros">
        <input type="hidden" name="tipo" value="<?= e($tipo) ?>">
        <div class="campo"><label for="desde"><?= $soloFecha ? 'Fecha' : 'Desde' ?></label><input type="date" id="desde" name="desde" value="<?= e($f['desde']) ?>" required></div>
        <?php if (!$soloFecha): ?>
            <div class="campo"><label for="hasta">Hasta</label><input type="date" id="hasta" name="hasta" value="<?= e($f['hasta']) ?>" required></div>
        <?php endif; ?>
        <?php if ($tipo === 'fecha'): ?>
        <div class="campo">
            <label for="estado">Estado</label>
            <select id="estado" name="estado"><option value="">Todos</option>
                <?php foreach ($estados as $est): ?><option value="<?= $est ?>"<?= seleccionado($f['estado'], $est) ?>><?= ucfirst($est) ?></option><?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="acciones">
            <button class="boton boton-primario"><?= icono('filtro') ?> Generar</button>
            <button type="button" class="boton boton-secundario" data-imprimir><?= icono('imprimir') ?> Imprimir / PDF</button>
            <a class="boton boton-secundario" href="<?= url('/reportes', ['tipo' => $tipo, 'desde' => $f['desde'], 'hasta' => $soloFecha ? null : $f['hasta'], 'estado' => $f['estado'], 'formato' => 'csv']) ?>"><?= icono('descargar') ?> CSV</a>
        </div>
    </form>
</section>

<section class="tarjeta">
    <div class="reporte-titulo-impresion tarjeta-cuerpo">
        <h1>Clínica Vida Salud · <?= e($tipos[$tipo]) ?></h1>
        <p>Período <?= e($periodo) ?> · generado el <?= date('d/m/Y H:i') ?></p>
    </div>
    <div class="tarjeta-cabecera">
        <div><h2><?= e($tipos[$tipo]) ?></h2><p>Período <?= e($periodo) ?></p></div>
        <span class="chip"><b><?= count($filas) ?></b>&nbsp;fila(s)</span>
    </div>

    <?php if (!$filas): ?>
        <div class="vacio"><?= icono('reporte') ?><p>No hay datos para el período elegido.</p></div>

    <?php elseif ($tipo === 'fecha' || $tipo === 'agenda'): ?>
        <div class="tabla-contenedor">
            <table class="tabla tabla-adaptable">
                <thead><tr><?= $tipo === 'agenda' ? '<th>Médico</th>' : '<th>Fecha</th>' ?><th>Hora</th><th>Código</th><th>Paciente</th><th>CI</th><?= $tipo === 'fecha' ? '<th>Médico</th>' : '' ?><th>Especialidad</th><th>Estado</th><th>Motivo</th></tr></thead>
                <tbody>
                <?php $medicoAnterior = null; foreach ($filas as $r): ?>
                    <tr>
                        <?php if ($tipo === 'agenda'): ?>
                            <td data-titulo="Médico"><?= $r['medico'] !== $medicoAnterior ? '<strong>' . e($r['medico']) . '</strong>' : '' ?></td>
                        <?php else: ?>
                            <td data-titulo="Fecha" class="nowrap"><?= e(fecha_corta($r['fecha'])) ?></td>
                        <?php endif; $medicoAnterior = $r['medico']; ?>
                        <td data-titulo="Hora" class="nowrap mono"><?= e(hora($r['hora_inicio'])) ?>–<?= e(hora($r['hora_fin'])) ?></td>
                        <td data-titulo="Código" class="mono"><?= e($r['codigo']) ?></td>
                        <td data-titulo="Paciente"><?= e($r['paciente']) ?></td>
                        <td data-titulo="CI" class="mono"><?= e($r['ci_paciente']) ?></td>
                        <?php if ($tipo === 'fecha'): ?><td data-titulo="Médico"><?= e($r['medico']) ?></td><?php endif; ?>
                        <td data-titulo="Especialidad"><?= e($r['especialidad']) ?></td>
                        <td data-titulo="Estado"><?= estado_cita($r['estado']) ?></td>
                        <td data-titulo="Motivo" class="texto-chico"><?= e($r['motivo']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr><td colspan="<?= $tipo === 'fecha' ? 9 : 8 ?>">Total: <?= count($filas) ?> cita(s)</td></tr></tfoot>
            </table>
        </div>

    <?php elseif ($tipo === 'medico' || $tipo === 'especialidad'): ?>
        <div class="tabla-contenedor">
            <table class="tabla tabla-adaptable">
                <thead><tr><th><?= $tipo === 'medico' ? 'Médico' : 'Especialidad' ?></th><th class="texto-derecha">Total</th><th class="texto-derecha">Programadas</th><th class="texto-derecha">Confirmadas</th><th class="texto-derecha">Atendidas</th><th class="texto-derecha">Canceladas</th><th class="texto-derecha">Inasistencias</th></tr></thead>
                <tbody>
                <?php foreach ($filas as $r): ?>
                    <tr>
                        <td data-titulo="<?= $tipo === 'medico' ? 'Médico' : 'Especialidad' ?>"><strong><?= e($r['grupo']) ?></strong></td>
                        <?php foreach (['total', 'programadas', 'confirmadas', 'atendidas', 'canceladas', 'inasistencias'] as $col): ?>
                            <td data-titulo="<?= ucfirst($col) ?>" class="texto-derecha mono"><?= (int) $r[$col] ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr><td>Total</td><?php foreach (['total', 'programadas', 'confirmadas', 'atendidas', 'canceladas', 'inasistencias'] as $col): ?><td class="texto-derecha mono"><?= $suma($col) ?></td><?php endforeach; ?></tr></tfoot>
            </table>
        </div>

    <?php elseif ($tipo === 'estado'): $total = $suma('total'); ?>
        <div class="tarjeta-cuerpo">
            <div class="barras-h">
                <?php foreach ($filas as $r): ?>
                <div class="barra-h">
                    <span><?= estado_cita($r['estado']) ?></span>
                    <span class="barra-h-pista"><span class="barra-h-relleno <?= e($r['estado']) ?>" style="width: <?= $total ? round(100 * $r['total'] / $total) : 0 ?>%"></span></span>
                    <b class="texto-derecha mono"><?= (int) $r['total'] ?></b>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="tabla-contenedor">
            <table class="tabla">
                <thead><tr><th>Estado</th><th class="texto-derecha">Citas</th><th class="texto-derecha">Porcentaje</th></tr></thead>
                <tbody>
                <?php foreach ($filas as $r): ?>
                    <tr><td><?= e(ucfirst($r['estado'])) ?></td><td class="texto-derecha mono"><?= (int) $r['total'] ?></td><td class="texto-derecha mono"><?= $total ? number_format(100 * $r['total'] / $total, 1, ',', '.') : '0,0' ?> %</td></tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr><td>Total</td><td class="texto-derecha mono"><?= $total ?></td><td class="texto-derecha mono">100 %</td></tr></tfoot>
            </table>
        </div>

    <?php elseif ($tipo === 'pacientes'): ?>
        <div class="tarjeta-cuerpo totales">
            <span class="chip">Registrados en el período: <b>&nbsp;<?= count($filas) ?></b></span>
            <span class="chip">Total en la clínica: <b>&nbsp;<?= (int) $totales['total'] ?></b></span>
            <span class="total-estado"><?= estado_registro('activo') ?> <b><?= (int) $totales['activos'] ?></b></span>
            <span class="total-estado"><?= estado_registro('inactivo') ?> <b><?= (int) $totales['inactivos'] ?></b></span>
        </div>
        <div class="tabla-contenedor">
            <table class="tabla tabla-adaptable">
                <thead><tr><th>Código</th><th>CI</th><th>Paciente</th><th>Sexo</th><th>Edad</th><th>Teléfono</th><th>Estado</th><th>Registrado</th><th class="texto-derecha">Citas</th></tr></thead>
                <tbody>
                <?php foreach ($filas as $r): ?>
                    <tr>
                        <td data-titulo="Código" class="mono"><?= e($r['codigo']) ?></td>
                        <td data-titulo="CI" class="mono"><?= e($r['ci']) ?></td>
                        <td data-titulo="Paciente"><?= e($r['paciente']) ?></td>
                        <td data-titulo="Sexo"><?= e($sexos[$r['sexo']] ?? $r['sexo']) ?></td>
                        <td data-titulo="Edad" class="nowrap"><?= e(edad($r['fecha_nacimiento'])) ?></td>
                        <td data-titulo="Teléfono"><?= e($r['telefono']) ?></td>
                        <td data-titulo="Estado"><?= estado_registro($r['estado']) ?></td>
                        <td data-titulo="Registrado" class="nowrap"><?= e(fecha_hora($r['creado_en'])) ?></td>
                        <td data-titulo="Citas" class="texto-derecha mono"><?= (int) $r['citas'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr><td colspan="8">Total</td><td class="texto-derecha mono"><?= $suma('citas') ?></td></tr></tfoot>
            </table>
        </div>

    <?php elseif ($tipo === 'asistencia'): ?>
        <?php $at = $suma('atendidas'); $ina = $suma('inasistencias'); ?>
        <div class="rejilla rejilla-4 tarjeta-cuerpo">
            <div class="indicador"><span class="indicador-icono"><?= icono('citas') ?></span><div><div class="indicador-valor"><?= $suma('total') ?></div><div class="indicador-etiqueta">Citas del período</div></div></div>
            <div class="indicador"><span class="indicador-icono"><?= icono('check-circulo') ?></span><div><div class="indicador-valor"><?= $at ?></div><div class="indicador-etiqueta">Atendidas</div></div></div>
            <div class="indicador indicador-naranja"><span class="indicador-icono"><?= icono('alerta') ?></span><div><div class="indicador-valor"><?= $ina ?></div><div class="indicador-etiqueta">Inasistencias</div></div></div>
            <div class="indicador indicador-turquesa"><span class="indicador-icono"><?= icono('reporte') ?></span><div><div class="indicador-valor"><?= ($at + $ina) ? round(100 * $at / ($at + $ina)) : 0 ?>%</div><div class="indicador-etiqueta">Tasa de asistencia</div></div></div>
        </div>
        <div class="tabla-contenedor">
            <table class="tabla tabla-adaptable">
                <thead><tr><th>Fecha</th><th class="texto-derecha">Total</th><th class="texto-derecha">Atendidas</th><th class="texto-derecha">Canceladas</th><th class="texto-derecha">Inasistencias</th><th class="texto-derecha">Pendientes</th></tr></thead>
                <tbody>
                <?php foreach ($filas as $r): ?>
                    <tr>
                        <td data-titulo="Fecha" class="nowrap"><?= e(fecha_corta($r['fecha'])) ?> <span class="texto-tenue texto-chico"><?= e(mb_substr(DIAS_SEMANA[(int) date('N', strtotime($r['fecha']))], 0, 3)) ?></span></td>
                        <td data-titulo="Total" class="texto-derecha mono"><?= (int) $r['total'] ?></td>
                        <td data-titulo="Atendidas" class="texto-derecha mono"><?= (int) $r['atendidas'] ?></td>
                        <td data-titulo="Canceladas" class="texto-derecha mono"><?= (int) $r['canceladas'] ?></td>
                        <td data-titulo="Inasistencias" class="texto-derecha mono"><?= (int) $r['inasistencias'] ?></td>
                        <td data-titulo="Pendientes" class="texto-derecha mono"><?= (int) $r['programadas'] + (int) $r['confirmadas'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr><td>Total</td><td class="texto-derecha mono"><?= $suma('total') ?></td><td class="texto-derecha mono"><?= $at ?></td><td class="texto-derecha mono"><?= $suma('canceladas') ?></td><td class="texto-derecha mono"><?= $ina ?></td><td class="texto-derecha mono"><?= $suma('programadas') + $suma('confirmadas') ?></td></tr></tfoot>
            </table>
        </div>
    <?php endif; ?>
</section>
