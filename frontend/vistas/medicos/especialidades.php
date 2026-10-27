<?php
/**
 * CU06 · Especialidades: registrar, modificar (ventana modal) y activar/desactivar.
 * El nombre es único sin importar tildes, mayúsculas ni espacios (RF04).
 * Variables: $especialidades, $estado, $anterior, $errores.
 */
?>
<div class="rejilla rejilla-lateral">
    <section class="tarjeta">
        <div class="tarjeta-cabecera">
            <div><h2>Especialidades médicas</h2><p>Una especialidad con citas pendientes no se puede desactivar.</p></div>
            <form method="get" action="<?= url('/especialidades') ?>" class="acciones">
                <label class="solo-lector" for="estado">Estado</label>
                <select id="estado" name="estado" data-enviar-al-cambiar>
                    <option value="">Todas</option>
                    <option value="activo"<?= seleccionado($estado, 'activo') ?>>Activas</option>
                    <option value="inactivo"<?= seleccionado($estado, 'inactivo') ?>>Inactivas</option>
                </select>
            </form>
        </div>
        <?php if (!$especialidades): ?>
            <div class="vacio"><?= icono('especialidad') ?><p>No hay especialidades.</p></div>
        <?php else: ?>
        <div class="tabla-contenedor">
            <table class="tabla tabla-adaptable">
                <thead><tr><th>Especialidad</th><th>Médicos activos</th><th>Citas</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($especialidades as $esp): $activa = $esp['estado'] === 'activo'; ?>
                    <tr class="<?= $activa ? '' : 'fila-inactiva' ?>">
                        <td data-titulo="Especialidad"><strong><?= especialidad_color($esp['nombre'], $esp['color']) ?></strong><br><span class="texto-suave texto-chico"><?= e($esp['descripcion'] ?: 'Sin descripción') ?></span></td>
                        <td data-titulo="Médicos activos"><?= (int) $esp['medicos_activos'] ?></td>
                        <td data-titulo="Citas"><?= (int) $esp['total_citas'] ?></td>
                        <td data-titulo="Estado"><?= estado_registro($esp['estado']) ?></td>
                        <td class="celda-acciones">
                            <div class="acciones">
                                <button type="button" class="boton boton-secundario boton-chico" data-modal-abrir="editar-<?= $esp['id_especialidad'] ?>"><?= icono('editar') ?> Modificar</button>
                                <form method="post" action="<?= url("/especialidades/{$esp['id_especialidad']}/estado") ?>"
                                      data-confirmar="<?= $activa ? '¿Desactivar «' . e($esp['nombre']) . '»? No se podrán programar citas nuevas de esta especialidad.' : '¿Activar «' . e($esp['nombre']) . '»?' ?>">
                                    <?= csrf_campo() ?>
                                    <button class="boton <?= $activa ? 'boton-peligro' : 'boton-secundario' ?> boton-chico"><?= icono('encendido') ?> <?= $activa ? 'Desactivar' : 'Activar' ?></button>
                                </form>
                            </div>
                            <dialog class="modal" id="editar-<?= $esp['id_especialidad'] ?>">
                                <form method="post" action="<?= url("/especialidades/{$esp['id_especialidad']}") ?>" class="formulario" data-validar novalidate>
                                    <?= csrf_campo() ?>
                                    <div class="modal-cabecera"><h2>Modificar especialidad</h2>
                                        <button type="button" class="modal-cerrar" data-modal-cerrar aria-label="Cerrar"><?= icono('cerrar') ?></button></div>
                                    <div class="modal-cuerpo">
                                        <div class="campo">
                                            <label for="nombre-<?= $esp['id_especialidad'] ?>">Nombre <span class="requerido">*</span></label>
                                            <input type="text" id="nombre-<?= $esp['id_especialidad'] ?>" name="nombre" value="<?= e($esp['nombre']) ?>" required minlength="3" maxlength="80" data-regla="nombre">
                                        </div>
                                        <div class="campo">
                                            <label for="descripcion-<?= $esp['id_especialidad'] ?>">Descripción <span class="opcional">(opcional)</span></label>
                                            <textarea id="descripcion-<?= $esp['id_especialidad'] ?>" name="descripcion" maxlength="250" rows="3"><?= e($esp['descripcion']) ?></textarea>
                                        </div>
                                        <div class="campo mb-0">
                                            <label for="color-<?= $esp['id_especialidad'] ?>">Color en la agenda</label>
                                            <input type="color" id="color-<?= $esp['id_especialidad'] ?>" name="color" value="<?= e(color_especialidad($esp['color'])) ?>" class="selector-color" required>
                                        </div>
                                    </div>
                                    <div class="modal-pie">
                                        <button type="button" class="boton boton-secundario" data-modal-cerrar>Cancelar</button>
                                        <button class="boton boton-primario"><?= icono('check') ?> Guardar</button>
                                    </div>
                                </form>
                            </dialog>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

    <aside class="tarjeta">
        <div class="tarjeta-cabecera"><h2><?= icono('mas') ?> Nueva especialidad</h2></div>
        <form method="post" action="<?= url('/especialidades') ?>" class="tarjeta-cuerpo formulario" data-validar novalidate>
            <?= csrf_campo() ?>
            <div class="campo<?= clase_error($errores, 'nombre') ?>">
                <label for="nombre">Nombre <span class="requerido">*</span></label>
                <input type="text" id="nombre" name="nombre" value="<?= viejo($anterior, 'nombre') ?>" required minlength="3" maxlength="80" data-regla="nombre" placeholder="Ej. Dermatología">
                <p class="campo-ayuda">«Pediatría», «pediatria» y « PEDIATRÍA » se consideran el mismo nombre.</p>
                <?= error_campo($errores, 'nombre') ?>
            </div>
            <div class="campo<?= clase_error($errores, 'descripcion') ?>">
                <label for="descripcion">Descripción <span class="opcional">(opcional)</span></label>
                <textarea id="descripcion" name="descripcion" maxlength="250" rows="3"><?= viejo($anterior, 'descripcion') ?></textarea>
                <?= error_campo($errores, 'descripcion') ?>
            </div>
            <div class="campo<?= clase_error($errores, 'color') ?>">
                <label for="color">Color en la agenda</label>
                <input type="color" id="color" name="color" value="<?= e(color_especialidad($anterior['color'] ?? '#0d9488')) ?>" class="selector-color" required>
                <p class="campo-ayuda">Se usa para distinguir la especialidad en la agenda y en las citas.</p>
                <?= error_campo($errores, 'color') ?>
            </div>
            <button class="boton boton-primario boton-bloque"><?= icono('mas') ?> Registrar especialidad</button>
        </form>
    </aside>
</div>
