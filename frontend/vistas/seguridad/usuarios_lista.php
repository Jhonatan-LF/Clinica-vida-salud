<?php
/**
 * CU02 · Lista de usuarios con filtros por texto, rol y estado.
 * Variables: $usuarios, $roles, $filtros (buscar, rol, estado).
 */
use App\Nucleo\Autenticacion;
?>
<div class="cabecera-seccion">
    <p>Cuentas de acceso al sistema. Cada usuario ve solo las opciones de su rol.</p>
    <div class="acciones">
        <a href="<?= url('/permisos') ?>" class="boton boton-secundario"><?= icono('escudo') ?> Roles y permisos</a>
        <a href="<?= url('/usuarios/nuevo') ?>" class="boton boton-primario"><?= icono('persona-mas') ?> Nuevo usuario</a>
    </div>
</div>

<section class="tarjeta">
    <form method="get" action="<?= url('/usuarios') ?>" class="filtros">
        <div class="campo campo-ancho">
            <label for="q">Buscar</label>
            <div class="buscador"><?= icono('buscar') ?><input type="search" id="q" name="q" value="<?= e($filtros['buscar']) ?>" placeholder="Usuario, nombre o correo"></div>
        </div>
        <div class="campo">
            <label for="rol">Rol</label>
            <select id="rol" name="rol">
                <option value="">Todos</option>
                <?php foreach ($roles as $r): ?>
                    <option value="<?= e($r['nombre']) ?>"<?= seleccionado($filtros['rol'], $r['nombre']) ?>><?= e(ucfirst($r['nombre'])) ?></option>
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
            <button class="boton boton-primario"><?= icono('filtro') ?> Filtrar</button>
            <a class="boton boton-texto" href="<?= url('/usuarios') ?>">Limpiar</a>
        </div>
    </form>

    <?php if (!$usuarios): ?>
        <div class="vacio"><?= icono('usuarios') ?><p>No hay usuarios con esos filtros.</p></div>
    <?php else: ?>
    <div class="tabla-contenedor">
        <table class="tabla tabla-adaptable">
            <thead><tr><th>Usuario</th><th>Rol</th><th>Vinculado a</th><th>Último acceso</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($usuarios as $u): ?>
                <tr class="<?= $u['estado'] !== 'activo' ? 'fila-inactiva' : '' ?>">
                    <td data-titulo="Usuario">
                        <div class="persona-celda">
                            <span class="avatar"><?= e(iniciales($u['nombre_completo'])) ?></span>
                            <div><strong><?= e($u['nombre_completo']) ?></strong><span><?= e($u['nombre_usuario']) ?> · <?= e($u['correo']) ?></span></div>
                        </div>
                    </td>
                    <td data-titulo="Rol"><span class="chip"><?= e(ucfirst($u['rol'])) ?></span></td>
                    <td data-titulo="Vinculado a"><?= e($u['codigo_medico'] ?? $u['codigo_paciente'] ?? '—') ?></td>
                    <td data-titulo="Último acceso" class="nowrap"><?= $u['ultimo_acceso'] ? e(fecha_hora($u['ultimo_acceso'])) : '<span class="texto-tenue">Nunca</span>' ?></td>
                    <td data-titulo="Estado">
                        <?= estado_registro($u['estado']) ?>
                        <?php if ($u['esta_bloqueado']): ?><span class="chip chip-alerta"><?= icono('candado') ?> Bloqueado</span><?php endif; ?>
                    </td>
                    <td class="celda-acciones">
                        <div class="acciones">
                            <a class="boton boton-secundario boton-chico" href="<?= url("/usuarios/{$u['id_usuario']}/editar") ?>"><?= icono('editar') ?> Modificar</a>
                            <?php if ((int) $u['id_usuario'] !== Autenticacion::id()): ?>
                            <form method="post" action="<?= url("/usuarios/{$u['id_usuario']}/estado") ?>"
                                  data-confirmar="<?= $u['estado'] === 'activo' ? '¿Desactivar la cuenta «' . e($u['nombre_usuario']) . '»? No podrá ingresar al sistema.' : '¿Activar la cuenta «' . e($u['nombre_usuario']) . '»?' ?>">
                                <?= csrf_campo() ?>
                                <button class="boton <?= $u['estado'] === 'activo' ? 'boton-peligro' : 'boton-secundario' ?> boton-chico"><?= icono('encendido') ?> <?= $u['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?></button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="paginacion"><span><?= count($usuarios) ?> usuario(s)</span></div>
    <?php endif; ?>
</section>
