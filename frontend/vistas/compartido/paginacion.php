<?php
/**
 * Paginación reutilizable.
 * Uso: Vista::parcial('compartido/paginacion', ['pagina' => 1, 'paginas' => 5, 'total' => 80,
 *                                               'ruta' => '/pacientes', 'parametros' => ['q' => '...']])
 */
$pagina = (int) ($pagina ?? 1);
$paginas = (int) ($paginas ?? 1);
$parametros = array_filter($parametros ?? [], static fn ($v) => $v !== null && $v !== '');
if ($paginas <= 1) {
    if (isset($total)) {
        echo '<div class="paginacion"><span>' . e($total) . ' registro(s)</span></div>';
    }
    return;
}
$enlace = static fn (int $n): string => url($ruta, $parametros + ['pagina' => $n]);
$desde = max(1, $pagina - 2);
$hasta = min($paginas, $pagina + 2);
?>
<nav class="paginacion" aria-label="Paginación">
    <span>Página <?= $pagina ?> de <?= $paginas ?><?= isset($total) ? ' · ' . e($total) . ' registro(s)' : '' ?></span>
    <ul>
        <li><a href="<?= $enlace(max(1, $pagina - 1)) ?>" class="<?= $pagina <= 1 ? 'deshabilitado' : '' ?>" aria-label="Página anterior"><?= icono('izquierda') ?></a></li>
        <?php if ($desde > 1): ?><li><a href="<?= $enlace(1) ?>">1</a></li><?php if ($desde > 2): ?><li><span>…</span></li><?php endif; endif; ?>
        <?php for ($n = $desde; $n <= $hasta; $n++): ?>
            <li><?= $n === $pagina ? '<span class="actual" aria-current="page">' . $n . '</span>' : '<a href="' . $enlace($n) . '">' . $n . '</a>' ?></li>
        <?php endfor; ?>
        <?php if ($hasta < $paginas): if ($hasta < $paginas - 1): ?><li><span>…</span></li><?php endif; ?><li><a href="<?= $enlace($paginas) ?>"><?= $paginas ?></a></li><?php endif; ?>
        <li><a href="<?= $enlace(min($paginas, $pagina + 1)) ?>" class="<?= $pagina >= $paginas ? 'deshabilitado' : '' ?>" aria-label="Página siguiente"><?= icono('derecha') ?></a></li>
    </ul>
</nav>
