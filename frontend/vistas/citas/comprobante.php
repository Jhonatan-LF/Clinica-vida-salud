<?php
/**
 * CU16 · Comprobante de cita: página propia (sin menú), lista para imprimir o «Guardar como PDF».
 * Variables: $titulo, $cita (fila de v_cita_detalle).
 */
use App\Nucleo\Aplicacion;
use App\Nucleo\Peticion;

$c = $cita;
$cancelada = $c['estado'] === 'cancelada';
$pendiente = in_array($c['estado'], ['programada', 'confirmada'], true);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo) ?> · <?= e(Aplicacion::config('app.nombre')) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= recurso('img/favicon.svg') ?>">
    <link rel="stylesheet" href="<?= recurso('css/app.css') ?>">
</head>
<body class="pagina-comprobante" data-base="<?= e(Peticion::base()) ?>">
<div class="comprobante-barra no-imprimir">
    <a class="boton boton-secundario" href="<?= url("/citas/{$c['id_cita']}") ?>"><?= icono('volver') ?> Volver a la cita</a>
    <button type="button" class="boton boton-primario" data-imprimir><?= icono('imprimir') ?> Imprimir / Guardar PDF</button>
</div>

<main class="comprobante<?= $cancelada ? ' anulado' : '' ?>">
    <header class="comprobante-cabecera">
        <span class="marca-icono"><?= icono('logo') ?></span>
        <div>
            <strong>Vida Salud</strong>
            <span>Gestión de citas médicas · Santa Cruz, Bolivia</span>
        </div>
        <div class="comprobante-codigo">
            <span>Comprobante de cita</span>
            <b><?= e($c['codigo']) ?></b>
        </div>
    </header>

    <?php if ($cancelada): ?>
        <div class="comprobante-sello">CANCELADA</div>
    <?php endif; ?>

    <section class="comprobante-fecha">
        <div class="cita-fecha-bloque"><b><?= date('d', strtotime($c['fecha'])) ?></b><small><?= e(mb_substr(MESES[(int) date('n', strtotime($c['fecha']))], 0, 3)) ?></small></div>
        <div>
            <strong><?= e(ucfirst(fecha_larga($c['fecha']))) ?></strong>
            <span><?= e(hora($c['hora_inicio'])) ?> a <?= e(hora($c['hora_fin'])) ?> · <?= $c['modalidad'] === 'virtual' ? 'Consulta virtual' : 'Consulta presencial' ?></span>
        </div>
        <?= estado_cita($c['estado']) ?>
    </section>

    <dl class="datos comprobante-datos">
        <div><dt>Paciente</dt><dd><?= e($c['paciente']) ?></dd></div>
        <div><dt>CI · Código</dt><dd><?= e(($c['ci_paciente'] ?: 'sin CI') . ' · ' . $c['codigo_paciente']) ?></dd></div>
        <div><dt>Médico</dt><dd><?= e($c['medico']) ?></dd></div>
        <div><dt>Especialidad</dt><dd><?= e($c['especialidad']) ?></dd></div>
        <div class="campo-total"><dt>Motivo de la consulta</dt><dd><?= e($c['motivo'] ?: 'No indicado') ?></dd></div>
        <?php if ($cancelada): ?>
            <div class="campo-total"><dt>Motivo de la cancelación</dt><dd><?= e($c['motivo_cancelacion']) ?></dd></div>
        <?php endif; ?>
    </dl>

    <?php if ($pendiente): ?>
    <section class="comprobante-indicaciones">
        <h3><?= icono('info') ?> Indicaciones</h3>
        <ul>
            <?php if ($c['modalidad'] === 'virtual'): ?>
                <li>Esté atento a su teléfono o correo 10 minutos antes: recibirá el enlace de la consulta.</li>
            <?php else: ?>
                <li>Preséntese en recepción <strong>10 minutos antes</strong> con su carnet de identidad.</li>
            <?php endif; ?>
            <li>Traiga sus exámenes y recetas anteriores, si los tiene.</li>
            <li>Si no puede asistir, cancele con al menos 2 horas de anticipación desde «Mis citas» o llamando a recepción.</li>
        </ul>
    </section>
    <?php endif; ?>

    <footer class="comprobante-pie">
        <span>Registrada el <?= e(fecha_hora($c['creado_en'])) ?><?= $c['registrado_por'] ? ' por ' . e($c['registrado_por']) : '' ?></span>
        <span>Impreso el <?= date('d/m/Y H:i') ?></span>
    </footer>
</main>
<script src="<?= recurso('js/app.js') ?>"></script>
</body>
</html>
