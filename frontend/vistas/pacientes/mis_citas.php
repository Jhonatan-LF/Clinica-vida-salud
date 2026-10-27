<?php
/**
 * CU05 / CU11 · Portal del paciente: próximas, pasadas, canceladas e inasistencias.
 * El paciente cancela en línea con al menos N horas de anticipación (config: citas.horas_minimas_cancelar_portal).
 * Variables: $paciente, $grupos, $pestana.
 */
use App\Nucleo\Aplicacion;

$horas = (int) Aplicacion::config('citas.horas_minimas_cancelar_portal', 2);
$pestanas = [
    'proximas'      => ['Próximas', 'calendario'],
    'pasadas'       => ['Pasadas', 'check-circulo'],
    'canceladas'    => ['Canceladas', 'x-circulo'],
    'inasistencias' => ['Inasistencias', 'alerta'],
];
$lista = $grupos[$pestana];
?>
<div class="cabecera-seccion">
    <p>Siga sus citas. Puede cancelar en línea hasta <?= $horas ?> horas antes; después, llame a recepción.</p>
    <div class="acciones"><a href="<?= url('/reservar') ?>" class="boton boton-primario"><?= icono('calendario-mas') ?> Reservar cita</a></div>
</div>

<section class="tarjeta">
    <nav class="pestanas" aria-label="Tipo de citas">
        <?php foreach ($pestanas as $clave => [$texto, $ico]): ?>
            <a href="<?= url('/mis-citas', ['ver' => $clave]) ?>"<?= $clave === $pestana ? ' class="activa" aria-current="page"' : '' ?>>
                <?= icono($ico) ?> <?= e($texto) ?> <span class="contador"><?= count($grupos[$clave]) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if (!$lista): ?>
        <div class="vacio"><?= icono('calendario') ?>
            <p><?= $pestana === 'proximas' ? 'No tiene citas próximas.' : 'No hay citas en esta sección.' ?></p>
            <?php if ($pestana === 'proximas'): ?><p class="mt-1"><a href="<?= url('/reservar') ?>">Reservar una cita ahora</a></p><?php endif; ?>
        </div>
    <?php else: foreach ($lista as $c):
        $puedeCancelar = in_array($c['estado'], ['programada', 'confirmada'], true)
            && strtotime($c['fecha'] . ' ' . $c['hora_inicio']) - time() >= $horas * 3600; ?>
        <div class="cita-fila">
            <div class="cita-fecha-bloque"><b><?= date('d', strtotime($c['fecha'])) ?></b><small><?= e(mb_substr(MESES[(int) date('n', strtotime($c['fecha']))], 0, 3)) ?></small></div>
            <div class="cita-info">
                <strong><?= e($c['especialidad']) ?> · <?= e(hora($c['hora_inicio'])) ?> a <?= e(hora($c['hora_fin'])) ?></strong>
                <span><?= e(ucfirst(fecha_larga($c['fecha']))) ?> · <?= e($c['medico']) ?> · <?= e($c['codigo']) ?>
                    <?= $c['modalidad'] === 'virtual' ? ' · <span class="chip chip-virtual">' . icono('video') . ' Virtual</span>' : '' ?></span>
                <?php if ($c['estado'] === 'cancelada' && $c['motivo_cancelacion']): ?><br><span class="texto-tenue">Motivo: <?= e($c['motivo_cancelacion']) ?></span><?php endif; ?>
            </div>
            <?= estado_cita($c['estado']) ?>
            <div class="acciones">
                <a class="boton boton-texto boton-chico" href="<?= url("/citas/{$c['id_cita']}") ?>">Detalle</a>
                <a class="boton boton-texto boton-chico" href="<?= url("/citas/{$c['id_cita']}/comprobante") ?>" target="_blank" rel="noopener" title="Comprobante"><?= icono('imprimir') ?><span class="solo-lector">Comprobante</span></a>
                <?php if ($puedeCancelar): ?>
                    <button type="button" class="boton boton-peligro boton-chico" data-modal-abrir="cancelar-<?= $c['id_cita'] ?>"><?= icono('x-circulo') ?> Cancelar</button>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($puedeCancelar): ?>
        <dialog class="modal" id="cancelar-<?= $c['id_cita'] ?>" aria-labelledby="titulo-cancelar-<?= $c['id_cita'] ?>">
            <form method="post" action="<?= url("/mis-citas/{$c['id_cita']}/cancelar") ?>" class="formulario" data-validar novalidate>
                <?= csrf_campo() ?>
                <div class="modal-cabecera"><h2 id="titulo-cancelar-<?= $c['id_cita'] ?>">Cancelar cita <?= e($c['codigo']) ?></h2>
                    <button type="button" class="modal-cerrar" data-modal-cerrar aria-label="Cerrar"><?= icono('cerrar') ?></button></div>
                <div class="modal-cuerpo">
                    <p><?= e(ucfirst(fecha_larga($c['fecha']))) ?> a las <?= e(hora($c['hora_inicio'])) ?> con <?= e($c['medico']) ?>.</p>
                    <div class="campo mb-0">
                        <label for="motivo-<?= $c['id_cita'] ?>">Motivo de la cancelación <span class="requerido">*</span></label>
                        <textarea id="motivo-<?= $c['id_cita'] ?>" name="motivo" required minlength="5" maxlength="250" rows="3" placeholder="Ej. Tengo un viaje imprevisto"></textarea>
                    </div>
                </div>
                <div class="modal-pie">
                    <button type="button" class="boton boton-secundario" data-modal-cerrar>Volver</button>
                    <button class="boton boton-peligro-lleno"><?= icono('x-circulo') ?> Cancelar cita</button>
                </div>
            </form>
        </dialog>
        <?php endif; ?>
    <?php endforeach; endif; ?>
</section>
