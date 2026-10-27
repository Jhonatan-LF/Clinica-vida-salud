<?php
/**
 * CU05 · Historial de citas de un paciente (RF12): incluye canceladas e inasistencias.
 * Variables: $paciente, $citas, $resumen, $filtros (estado, desde, hasta), $esPortal.
 */
use App\Nucleo\Autenticacion;

$p = $paciente;
$verCita = Autenticacion::puede(['citas.ver', 'agenda.propia']);
?>
<section class="tarjeta">
    <div class="tarjeta-cuerpo ficha-cabecera">
        <span class="avatar avatar-grande"><?= e(iniciales($p['nombres'] . ' ' . $p['apellidos'])) ?></span>
        <div>
            <h2><?= e($p['nombres'] . ' ' . $p['apellidos']) ?></h2>
            <div class="texto-suave"><?= e($p['codigo']) ?> · CI <?= e($p['ci'] ?: 'sin registrar') ?> · <?= e(edad($p['fecha_nacimiento'])) ?></div>
        </div>
        <div class="acciones">
            <div class="totales">
                <?php foreach ($resumen as $estado => $n): ?>
                    <span class="total-estado"><?= estado_cita($estado) ?> <b><?= (int) $n ?></b></span>
                <?php endforeach; ?>
            </div>
            <?php if (Autenticacion::puede('pacientes.ver')): ?>
                <a class="boton boton-secundario" href="<?= url("/pacientes/{$p['id_paciente']}") ?>"><?= icono('persona') ?> Ficha</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="tarjeta">
    <form method="get" action="<?= url("/pacientes/{$p['id_paciente']}/historial") ?>" class="filtros">
        <div class="campo">
            <label for="estado">Estado</label>
            <select id="estado" name="estado">
                <option value="">Todos</option>
                <?php foreach (array_keys($resumen) as $estado): ?>
                    <option value="<?= $estado ?>"<?= seleccionado($filtros['estado'], $estado) ?>><?= e(ucfirst($estado)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo"><label for="desde">Desde</label><input type="date" id="desde" name="desde" value="<?= e($filtros['desde']) ?>"></div>
        <div class="campo"><label for="hasta">Hasta</label><input type="date" id="hasta" name="hasta" value="<?= e($filtros['hasta']) ?>"></div>
        <div class="acciones">
            <button class="boton boton-primario"><?= icono('filtro') ?> Filtrar</button>
            <a class="boton boton-texto" href="<?= url("/pacientes/{$p['id_paciente']}/historial") ?>">Limpiar</a>
            <button type="button" class="boton boton-secundario" data-imprimir><?= icono('imprimir') ?> Imprimir</button>
        </div>
    </form>

    <div class="reporte-titulo-impresion">
        <h1>Historial de citas · <?= e($p['nombres'] . ' ' . $p['apellidos']) ?></h1>
        <p><?= e($p['codigo']) ?> · CI <?= e($p['ci']) ?> · Clínica Vida Salud · impreso el <?= date('d/m/Y H:i') ?></p>
    </div>

    <?php if (!$citas): ?>
        <div class="vacio"><?= icono('historial') ?><p>No hay citas con esos filtros.</p></div>
    <?php else: ?>
    <div class="tabla-contenedor">
        <table class="tabla tabla-adaptable">
            <thead><tr><th>Código</th><th>Fecha y hora</th><th>Médico</th><th>Especialidad</th><th>Motivo</th><th>Estado</th><th>Registrada</th></tr></thead>
            <tbody>
            <?php foreach ($citas as $c): ?>
                <tr>
                    <td data-titulo="Código" class="mono"><?= $verCita ? '<a href="' . url("/citas/{$c['id_cita']}") . '">' . e($c['codigo']) . '</a>' : e($c['codigo']) ?></td>
                    <td data-titulo="Fecha" class="nowrap"><?= e(fecha_corta($c['fecha'])) ?> · <?= e(hora($c['hora_inicio'])) ?></td>
                    <td data-titulo="Médico"><?= e($c['medico']) ?></td>
                    <td data-titulo="Especialidad"><?= e($c['especialidad']) ?></td>
                    <td data-titulo="Motivo" class="texto-chico"><?= e($c['estado'] === 'cancelada' ? 'Cancelada: ' . $c['motivo_cancelacion'] : ($c['motivo'] ?: '—')) ?></td>
                    <td data-titulo="Estado"><?= estado_cita($c['estado']) ?></td>
                    <td data-titulo="Registrada" class="nowrap texto-chico"><?= e(fecha_hora($c['creado_en'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <div class="paginacion"><span><?= count($citas) ?> cita(s) en el historial</span></div>
</section>
