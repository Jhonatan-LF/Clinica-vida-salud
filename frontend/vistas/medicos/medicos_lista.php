<?php
/**
 * CU07 · Lista de médicos con búsqueda por nombre, código, registro o especialidad (RF03).
 * Variables: $medicos, $especialidades, $filtros (q, especialidad, estado), $puedeGestionar.
 */
use App\Nucleo\Autenticacion;
?>
<div class="cabecera-seccion">
    <p>Plantel médico. Un médico inactivo no recibe citas nuevas y conserva su historial (RN04, RN11).</p>
    <?php if ($puedeGestionar): ?>
    <div class="acciones"><a href="<?= url('/medicos/nuevo') ?>" class="boton boton-primario"><?= icono('mas') ?> Registrar médico</a></div>
    <?php endif; ?>
</div>

<section class="tarjeta">
    <form method="get" action="<?= url('/medicos') ?>" class="filtros" role="search">
        <div class="campo campo-ancho">
            <label for="q">Buscar</label>
            <div class="buscador"><?= icono('buscar') ?><input type="search" id="q" name="q" value="<?= e($filtros['q']) ?>" placeholder="Nombre, código, registro o especialidad"></div>
        </div>
        <div class="campo">
            <label for="especialidad">Especialidad</label>
            <select id="especialidad" name="especialidad">
                <option value="">Todas</option>
                <?php foreach ($especialidades as $esp): ?>
                    <option value="<?= $esp['id_especialidad'] ?>"<?= seleccionado($filtros['especialidad'], $esp['id_especialidad']) ?>><?= e($esp['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo">
            <label for="estado">Estado</label>
            <select id="estado" name="estado">
                <option value="">Todos</option>
                <option value="activo"<?= seleccionado($filtros['estado'], 'activo') ?>>Activos</option>
                <option value="inactivo"<?= seleccionado($filtros['estado'], 'inactivo') ?>>Inactivos</option>
            </select>
        </div>
        <div class="acciones">
            <button class="boton boton-primario"><?= icono('buscar') ?> Buscar</button>
            <a class="boton boton-texto" href="<?= url('/medicos') ?>">Limpiar</a>
        </div>
    </form>

    <?php if (!$medicos): ?>
        <div class="vacio"><?= icono('medico') ?><p>No se encontraron médicos.</p></div>
    <?php else: ?>
    <div class="tabla-contenedor">
        <table class="tabla tabla-adaptable">
            <thead><tr><th>Médico</th><th>Especialidades</th><th>Contacto</th><th>Horarios</th><th>Citas pendientes</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($medicos as $m): ?>
                <tr class="<?= $m['estado'] !== 'activo' ? 'fila-inactiva' : '' ?>">
                    <td data-titulo="Médico">
                        <a class="persona-celda" href="<?= url("/medicos/{$m['id_medico']}") ?>">
                            <span class="avatar"><?= e(iniciales($m['nombres'] . ' ' . $m['apellidos'])) ?></span>
                            <div><strong>Dr(a). <?= e($m['nombres'] . ' ' . $m['apellidos']) ?></strong><span><?= e($m['codigo']) ?> · Reg. <?= e($m['registro_profesional']) ?></span></div>
                        </a>
                    </td>
                    <td data-titulo="Especialidades"><?= e($m['especialidades'] ?: '—') ?></td>
                    <td data-titulo="Contacto" class="texto-chico"><?= e($m['telefono']) ?><br><span class="texto-suave"><?= e($m['correo']) ?></span></td>
                    <td data-titulo="Horarios"><?= (int) $m['bloques'] ? (int) $m['bloques'] . ' bloque(s)' : '<span class="chip chip-alerta">Sin horario</span>' ?></td>
                    <td data-titulo="Citas pendientes"><?= (int) $m['citas_pendientes'] ?></td>
                    <td data-titulo="Estado"><?= estado_registro($m['estado']) ?></td>
                    <td class="celda-acciones">
                        <div class="acciones">
                            <a class="boton boton-secundario boton-chico" href="<?= url("/medicos/{$m['id_medico']}") ?>"><?= icono('ojo') ?> Ficha</a>
                            <?php if (Autenticacion::puede(['agenda.ver'])): ?>
                            <a class="boton boton-texto boton-chico" href="<?= url('/agenda', ['medico' => $m['id_medico']]) ?>" title="Agenda"><?= icono('agenda') ?><span class="solo-lector">Agenda</span></a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="paginacion"><span><?= count($medicos) ?> médico(s)</span></div>
    <?php endif; ?>
</section>
