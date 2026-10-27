<?php
/**
 * CU10 · Reprogramar cita: nuevas fechas y horas libres del MISMO médico.
 * La cita actual no cuenta como ocupada (parámetro excluir en /api/disponibilidad).
 * Variables: $cita, $anterior, $errores.
 */
$c = $cita;
?>
<form method="post" action="<?= url("/citas/{$c['id_cita']}/reprogramar") ?>" id="form-cita" class="formulario"
      data-reprogramar-cita data-medico="<?= (int) $c['id_medico'] ?>" data-excluir="<?= (int) $c['id_cita'] ?>"
      data-restaurar="<?= e(json_encode(array_intersect_key($anterior, array_flip(['fecha', 'hora_inicio'])))) ?>" novalidate>
    <?= csrf_campo() ?>
    <input type="hidden" name="fecha" id="campo-fecha" value="">
    <input type="hidden" name="hora_inicio" id="campo-hora" value="">

    <div class="rejilla rejilla-lateral">
        <div class="pasos">
            <section class="paso activo" id="paso-3">
                <div class="paso-cabecera">
                    <span class="paso-numero">1</span>
                    <div><h2>Nueva fecha</h2><p>Días con turnos libres de <?= e($c['medico']) ?>.</p></div>
                </div>
                <div class="paso-cuerpo">
                    <div id="bloque-dias">
                        <div class="tira-dias-cabecera">
                            <span class="etiqueta" id="titulo-dias">Elija el día</span>
                            <div class="acciones">
                                <button type="button" class="boton boton-secundario boton-chico boton-icono" id="dias-anteriores" aria-label="Días anteriores"><?= icono('izquierda') ?></button>
                                <button type="button" class="boton boton-secundario boton-chico boton-icono" id="dias-siguientes" aria-label="Días siguientes"><?= icono('derecha') ?></button>
                            </div>
                        </div>
                        <div class="tira-dias" id="tira-dias"></div>
                    </div>
                </div>
            </section>

            <section class="paso bloqueado" id="paso-4">
                <div class="paso-cabecera">
                    <span class="paso-numero">2</span>
                    <div><h2>Nueva hora y motivo del cambio</h2><p id="texto-fecha-elegida">Las horas ocupadas aparecen tachadas.</p></div>
                </div>
                <div class="paso-cuerpo">
                    <div class="horas" id="lista-horas" role="radiogroup" aria-label="Hora"></div>
                    <div class="leyenda"><span><i class="l-libre"></i>Libre</span><span><i class="l-ocupada"></i>Ocupada</span><span><i class="l-elegida"></i>Elegida</span></div>
                    <div class="campo mt-2<?= clase_error($errores, 'motivo_cambio') ?>">
                        <label for="motivo_cambio">Motivo del cambio <span class="opcional">(opcional, queda en el historial)</span></label>
                        <input type="text" id="motivo_cambio" name="motivo_cambio" value="<?= viejo($anterior, 'motivo_cambio') ?>" minlength="3" maxlength="200" placeholder="Ej. El paciente pidió otro día">
                        <?= error_campo($errores, 'motivo_cambio') ?>
                    </div>
                </div>
            </section>
        </div>

        <aside class="ticket" aria-live="polite">
            <div class="ticket-cabecera"><?= icono('ticket') ?><h2>Cita <?= e($c['codigo']) ?></h2></div>
            <div class="ticket-cuerpo">
                <div class="ticket-fila"><span>Paciente</span><b><?= e($c['paciente']) ?></b></div>
                <div class="ticket-fila"><span>Médico</span><b><?= e($c['medico']) ?></b></div>
                <div class="ticket-fila"><span>Especialidad</span><b><?= e($c['especialidad']) ?></b></div>
                <div class="ticket-fila"><span>Fecha actual</span><b><?= e(fecha_corta($c['fecha'])) ?> · <?= e(hora($c['hora_inicio'])) ?></b></div>
                <div class="ticket-fila"><span>Nueva fecha</span><b id="t-fecha" class="vacio-dato">Sin elegir</b></div>
                <div class="ticket-fila"><span>Nueva hora</span><b id="t-hora" class="vacio-dato">Sin elegir</b></div>
            </div>
            <div class="ticket-corte" aria-hidden="true"></div>
            <div class="ticket-pie">
                <button type="submit" class="boton boton-primario boton-bloque" id="boton-confirmar" disabled><?= icono('calendario') ?> Reprogramar</button>
                <a class="boton boton-texto boton-bloque mt-1" href="<?= url("/citas/{$c['id_cita']}") ?>">Volver sin cambios</a>
                <p class="campo-ayuda" id="ayuda-confirmar">Elija la nueva fecha y hora.</p>
            </div>
        </aside>
    </div>
</form>
