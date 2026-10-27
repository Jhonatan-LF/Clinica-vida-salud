<?php
/**
 * CU02 · Bitácora de acciones: quién, qué, cuándo, desde qué IP y con qué resultado.
 * Variables: $registros, $total, $pagina, $paginas, $filtros, $modulos, $acciones.
 */
use App\Nucleo\Vista;

$nombresAccion = ['inicio_sesion' => 'Inicio de sesión', 'cierre_sesion' => 'Cierre de sesión', 'inicio_fallido' => 'Ingreso fallido',
                  'cuenta_bloqueada' => 'Cuenta bloqueada', 'operacion' => 'Operación', 'exportacion' => 'Exportación', 'instalacion' => 'Instalación'];
?>
<div class="cabecera-seccion">
    <p>Registro inalterable de las acciones de los usuarios. Nunca guarda contraseñas ni el contenido de los formularios.</p>
    <div class="acciones">
        <a class="boton boton-secundario" href="<?= url('/bitacora', $filtros + ['formato' => 'csv']) ?>"><?= icono('descargar') ?> Exportar CSV</a>
    </div>
</div>

<section class="tarjeta">
    <form method="get" action="<?= url('/bitacora') ?>" class="filtros">
        <div class="campo campo-ancho">
            <label for="usuario">Usuario</label>
            <div class="buscador"><?= icono('buscar') ?><input type="search" id="usuario" name="usuario" value="<?= e($filtros['usuario']) ?>" placeholder="Nombre de usuario"></div>
        </div>
        <div class="campo">
            <label for="modulo">Módulo</label>
            <select id="modulo" name="modulo"><option value="">Todos</option>
                <?php foreach ($modulos as $m): ?><option value="<?= e($m) ?>"<?= seleccionado($filtros['modulo'], $m) ?>><?= e(ucfirst($m)) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="campo">
            <label for="accion">Acción</label>
            <select id="accion" name="accion"><option value="">Todas</option>
                <?php foreach ($acciones as $a): ?><option value="<?= e($a) ?>"<?= seleccionado($filtros['accion'], $a) ?>><?= e($nombresAccion[$a] ?? $a) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="campo"><label for="desde">Desde</label><input type="date" id="desde" name="desde" value="<?= e($filtros['desde']) ?>"></div>
        <div class="campo"><label for="hasta">Hasta</label><input type="date" id="hasta" name="hasta" value="<?= e($filtros['hasta']) ?>"></div>
        <div class="acciones">
            <button class="boton boton-primario"><?= icono('filtro') ?> Filtrar</button>
            <a class="boton boton-texto" href="<?= url('/bitacora') ?>">Limpiar</a>
        </div>
    </form>

    <?php if (!$registros): ?>
        <div class="vacio"><?= icono('bitacora') ?><p>No hay registros con esos filtros.</p></div>
    <?php else: ?>
    <div class="tabla-contenedor">
        <table class="tabla tabla-adaptable">
            <thead><tr><th>Fecha y hora</th><th>Usuario</th><th>Acción</th><th>Módulo</th><th>Detalle</th><th>IP</th></tr></thead>
            <tbody>
            <?php foreach ($registros as $r):
                $conError = str_contains((string) $r['detalle'], 'con error') || in_array($r['accion'], ['inicio_fallido', 'cuenta_bloqueada'], true); ?>
                <tr>
                    <td data-titulo="Fecha" class="nowrap mono"><?= e(date('d/m/Y H:i:s', strtotime($r['fecha_hora']))) ?></td>
                    <td data-titulo="Usuario"><strong><?= e($r['usuario']) ?></strong><?php if ($r['nombre_completo']): ?><br><span class="texto-suave texto-chico"><?= e($r['nombre_completo']) ?></span><?php endif; ?></td>
                    <td data-titulo="Acción"><span class="chip<?= $conError ? ' chip-alerta' : '' ?>"><?= e($nombresAccion[$r['accion']] ?? $r['accion']) ?></span></td>
                    <td data-titulo="Módulo"><?= e($r['modulo']) ?></td>
                    <td data-titulo="Detalle" class="texto-chico"><?= e($r['detalle']) ?></td>
                    <td data-titulo="IP" class="mono texto-chico"><?= e($r['ip']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <?= Vista::parcial('compartido/paginacion', ['pagina' => $pagina, 'paginas' => $paginas, 'total' => $total, 'ruta' => '/bitacora', 'parametros' => $filtros]) ?>
</section>
