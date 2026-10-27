<?php
/**
 * CU09 · Programar cita (recepción) o Reservar cita (paciente en línea).
 * Pasos: paciente → especialidad → médico y fecha (solo horas libres) → hora, modalidad y motivo.
 * assets/js/citas.js carga médicos, días y horas con fetch desde /api/... sin recargar la página.
 * El servidor vuelve a verificar TODO al guardar (RN13).
 * Variables: $modo ('recepcion' | 'portal'), $paciente, $especialidades, $accion, $anterior, $errores.
 */
use App\Nucleo\Aplicacion;

$esPortal = $modo === 'portal';
$restaurar = array_intersect_key($anterior, array_flip(['id_especialidad', 'id_medico', 'fecha', 'hora_inicio', 'modalidad']));
$modalidad = $anterior['modalidad'] ?? 'presencial';
$diasMax = (int) Aplicacion::config('citas.dias_maximos_reserva', 60);
?>
<form method="post" action="<?= e($accion) ?>" id="form-cita" class="formulario" data-programar-cita
      data-restaurar="<?= e(json_encode($restaurar, JSON_UNESCAPED_UNICODE)) ?>" novalidate>
    <?= csrf_campo() ?>
    <input type="hidden" name="fecha" id="campo-fecha" value="">
    <input type="hidden" name="hora_inicio" id="campo-hora" value="">

    <div class="rejilla rejilla-lateral">
        <div class="pasos">
            <!-- Paso 1 · Paciente -->
            <section class="paso activo" id="paso-1">
                <div class="paso-cabecera">
                    <span class="paso-numero">1</span>
                    <div><h2>Paciente</h2><p><?= $esPortal ? 'La cita se reserva a su nombre.' : 'Busque por CI, código, nombre o apellido.' ?></p></div>
                </div>
                <div class="paso-cuerpo">
                    <?php if ($esPortal || $paciente): ?>
                        <div class="paciente-elegido" id="paciente-elegido">
                            <span class="avatar"><?= e(iniciales($paciente['nombres'] . ' ' . $paciente['apellidos'])) ?></span>
                            <div><strong id="nombre-paciente"><?= e($paciente['nombres'] . ' ' . $paciente['apellidos']) ?></strong>
                                <span><?= e($paciente['codigo']) ?><?= $paciente['ci'] ? ' · CI ' . e($paciente['ci']) : '' ?> · <?= e(edad($paciente['fecha_nacimiento'])) ?></span></div>
                            <?php if (!$esPortal): ?><button type="button" class="boton boton-texto boton-chico" data-cambiar-paciente>Cambiar</button><?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!$esPortal): ?>
                        <input type="hidden" name="id_paciente" id="campo-paciente" value="<?= e($paciente['id_paciente'] ?? '') ?>">
                        <div class="campo mb-0<?= $paciente ? ' oculto' : '' ?><?= clase_error($errores, 'id_paciente') ?>" id="buscador-paciente">
                            <label for="buscar-paciente">Buscar paciente</label>
                            <div class="buscador"><?= icono('buscar') ?><input type="search" id="buscar-paciente" placeholder="Ej. 6234517 o Vargas" autocomplete="off"></div>
                            <div class="resultados-busqueda oculto" id="resultados-paciente" role="listbox" aria-label="Pacientes encontrados"></div>
                            <p class="campo-ayuda">¿Paciente nuevo? <a href="<?= url('/pacientes/nuevo') ?>">Regístrelo primero</a>.</p>
                            <?= error_campo($errores, 'id_paciente') ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Paso 2 · Especialidad -->
            <section class="paso" id="paso-2">
                <div class="paso-cabecera">
                    <span class="paso-numero">2</span>
                    <div><h2>Especialidad</h2><p>Se cargan sus médicos activos.</p></div>
                </div>
                <div class="paso-cuerpo">
                    <?php if (!$especialidades): ?>
                        <p class="texto-suave">No hay especialidades con médicos activos y horarios.</p>
                    <?php endif; ?>
                    <div class="opciones-tarjeta" role="radiogroup" aria-label="Especialidad">
                        <?php foreach ($especialidades as $esp): ?>
                        <div class="opcion-tarjeta">
                            <input type="radio" name="id_especialidad" id="esp-<?= $esp['id_especialidad'] ?>" value="<?= $esp['id_especialidad'] ?>" data-nombre="<?= e($esp['nombre']) ?>">
                            <label for="esp-<?= $esp['id_especialidad'] ?>"><span class="esp-icono" style="color:<?= color_especialidad($esp['color']) ?>"><?= icono('especialidad') ?></span><span><?= e($esp['nombre']) ?><small><?= e(mb_strimwidth((string) $esp['descripcion'], 0, 60, '…')) ?></small></span></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <!-- Paso 3 · Médico y fecha -->
            <section class="paso bloqueado" id="paso-3">
                <div class="paso-cabecera">
                    <span class="paso-numero">3</span>
                    <div><h2>Médico y fecha</h2><p>Solo se muestran los días con turnos libres (hasta <?= $diasMax ?> días adelante).</p></div>
                </div>
                <div class="paso-cuerpo">
                    <!-- CU15 · Buscar el primer turno libre de cualquier médico de la especialidad -->
                    <div class="primer-turno">
                        <button type="button" class="boton boton-secundario" id="boton-primer-turno"><?= icono('reloj') ?> Buscar el primer turno libre</button>
                        <span class="texto-suave texto-chico">o elija el médico y el día:</span>
                    </div>
                    <div id="resultado-primer-turno" aria-live="polite"></div>
                    <div class="opciones-tarjeta" id="lista-medicos" role="radiogroup" aria-label="Médico"></div>
                    <div class="mt-2 oculto" id="bloque-dias">
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

            <!-- Paso 4 · Hora, modalidad y motivo -->
            <section class="paso bloqueado" id="paso-4">
                <div class="paso-cabecera">
                    <span class="paso-numero">4</span>
                    <div><h2>Hora, modalidad y motivo</h2><p id="texto-fecha-elegida">Las horas ocupadas aparecen tachadas.</p></div>
                </div>
                <div class="paso-cuerpo">
                    <div class="horas" id="lista-horas" role="radiogroup" aria-label="Hora"></div>
                    <div class="leyenda"><span><i class="l-libre"></i>Libre</span><span><i class="l-ocupada"></i>Ocupada</span><span><i class="l-elegida"></i>Elegida</span></div>

                    <div class="fila-campos mt-3">
                        <div class="campo">
                            <span class="etiqueta">Modalidad</span>
                            <div class="segmentos" role="radiogroup" aria-label="Modalidad">
                                <label class="segmento"><input type="radio" name="modalidad" value="presencial"<?= marcado($modalidad === 'presencial') ?>><span><?= icono('hospital') ?> Presencial</span></label>
                                <label class="segmento"><input type="radio" name="modalidad" value="virtual"<?= marcado($modalidad === 'virtual') ?>><span><?= icono('video') ?> Virtual</span></label>
                            </div>
                        </div>
                        <div class="campo<?= clase_error($errores, 'motivo') ?>">
                            <label for="motivo">Motivo breve <span class="opcional">(opcional)</span></label>
                            <input type="text" id="motivo" name="motivo" value="<?= viejo($anterior, 'motivo') ?>" minlength="3" maxlength="250" placeholder="Ej. Control de presión arterial">
                            <?= error_campo($errores, 'motivo') ?>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <!-- Resumen tipo ticket -->
        <aside class="ticket" aria-live="polite">
            <div class="ticket-cabecera"><?= icono('ticket') ?><h2>Resumen de la cita</h2></div>
            <div class="ticket-cuerpo">
                <div class="ticket-fila"><span>Paciente</span><b id="t-paciente" class="<?= $paciente ? '' : 'vacio-dato' ?>"><?= $paciente ? e($paciente['nombres'] . ' ' . $paciente['apellidos']) : 'Sin elegir' ?></b></div>
                <div class="ticket-fila"><span>Especialidad</span><b id="t-especialidad" class="vacio-dato">Sin elegir</b></div>
                <div class="ticket-fila"><span>Médico</span><b id="t-medico" class="vacio-dato">Sin elegir</b></div>
                <div class="ticket-fila"><span>Fecha</span><b id="t-fecha" class="vacio-dato">Sin elegir</b></div>
                <div class="ticket-fila"><span>Hora</span><b id="t-hora" class="vacio-dato">Sin elegir</b></div>
                <div class="ticket-fila"><span>Modalidad</span><b id="t-modalidad"><?= $modalidad === 'virtual' ? 'Virtual' : 'Presencial' ?></b></div>
            </div>
            <div class="ticket-corte" aria-hidden="true"></div>
            <div class="ticket-pie">
                <button type="submit" class="boton boton-primario boton-bloque" id="boton-confirmar" disabled><?= icono('check') ?> <?= $esPortal ? 'Confirmar reserva' : 'Registrar cita' ?></button>
                <p class="campo-ayuda" id="ayuda-confirmar">Complete los 4 pasos para confirmar.</p>
            </div>
        </aside>
    </div>
</form>
<noscript><section class="alerta alerta-aviso mt-2"><?= icono('alerta') ?><p>Para elegir horarios libres active JavaScript en su navegador.</p></section></noscript>
