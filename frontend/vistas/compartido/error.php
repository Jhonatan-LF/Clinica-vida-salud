<?php
/**
 * Página de error (403, 404, 500). Nunca muestra datos internos:
 * $detalle solo llega cuando 'depuracion' está activado en la configuración.
 * Variables: $titulo, $codigo, $mensaje, $detalle (opcional).
 */
use App\Nucleo\Autenticacion;
?>
<div class="pagina-error">
    <div class="codigo"><?= e($codigo ?? 'Error') ?></div>
    <h2><?= e($titulo ?? 'Algo salió mal') ?></h2>
    <p class="texto-suave"><?= e($mensaje ?? 'No pudimos completar la operación.') ?></p>
    <?php if (!empty($detalle)): ?>
        <pre class="detalle-tecnico">Modo depuración: <?= e($detalle) ?></pre>
    <?php endif; ?>
    <div class="acciones">
        <a class="boton boton-secundario" href="javascript:history.back()"><?= icono('volver') ?> Volver</a>
        <a class="boton boton-primario" href="<?= url(Autenticacion::hayUsuario() ? '/panel' : '/login') ?>">
            <?= icono('inicio') ?> <?= Autenticacion::hayUsuario() ? 'Ir al panel' : 'Iniciar sesión' ?>
        </a>
    </div>
</div>
