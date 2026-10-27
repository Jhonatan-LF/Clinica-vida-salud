<?php
/**
 * CU02 · Matriz de permisos por rol (tablas permiso y rol_permiso).
 * Variables: $roles, $permisos, $matriz (id_rol => [id_permiso…]).
 */
$modulos = [];
foreach ($permisos as $p) {
    $modulos[$p['modulo']][] = $p;
}
$nombresModulo = ['agenda' => 'Agenda', 'citas' => 'Citas', 'medicos' => 'Médicos y horarios', 'pacientes' => 'Pacientes',
                  'portal' => 'Portal del paciente', 'reportes' => 'Reportes', 'seguridad' => 'Seguridad'];
?>
<div class="cabecera-seccion">
    <p>Marque qué puede hacer cada rol. Los cambios se aplican en la siguiente pantalla que abra cada usuario (RN12).</p>
</div>

<form method="post" action="<?= url('/permisos') ?>" class="tarjeta" data-confirmar="¿Guardar los permisos de todos los roles?">
    <?= csrf_campo() ?>
    <div class="tabla-contenedor">
        <table class="tabla matriz">
            <thead>
                <tr>
                    <th>Permiso</th>
                    <?php foreach ($roles as $r): ?><th><?= e(ucfirst($r['nombre'])) ?></th><?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($modulos as $modulo => $lista): ?>
                <tr class="modulo"><td colspan="<?= count($roles) + 1 ?>"><?= e($nombresModulo[$modulo] ?? ucfirst($modulo)) ?></td></tr>
                <?php foreach ($lista as $p): ?>
                <tr>
                    <td><?= e($p['descripcion']) ?><br><code><?= e($p['codigo']) ?></code></td>
                    <?php foreach ($roles as $r):
                        $marcadoAhora = in_array((int) $p['id_permiso'], $matriz[(int) $r['id_rol']] ?? [], true);
                        $fijo = $r['nombre'] === 'administrador' && $p['codigo'] === 'usuarios.gestionar'; ?>
                    <td>
                        <label class="solo-lector" for="p<?= $r['id_rol'] ?>_<?= $p['id_permiso'] ?>"><?= e($r['nombre'] . ': ' . $p['codigo']) ?></label>
                        <input type="checkbox" id="p<?= $r['id_rol'] ?>_<?= $p['id_permiso'] ?>" name="permisos[<?= $r['id_rol'] ?>][]" value="<?= $p['id_permiso'] ?>"<?= marcado($marcadoAhora || $fijo) ?><?= $fijo ? ' disabled title="El administrador siempre gestiona usuarios"' : '' ?>>
                        <?php if ($fijo): ?><input type="hidden" name="permisos[<?= $r['id_rol'] ?>][]" value="<?= $p['id_permiso'] ?>"><?php endif; ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="tarjeta-pie acciones-formulario">
        <a href="<?= url('/permisos') ?>" class="boton boton-secundario">Descartar cambios</a>
        <button class="boton boton-primario"><?= icono('escudo') ?> Guardar permisos</button>
    </div>
</form>
