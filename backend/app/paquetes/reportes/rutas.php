<?php
/**
 * Rutas del paquete REPORTES (CU14, CU17).
 */
use App\Nucleo\Enrutador;
use App\Paquetes\Reportes\CasosUso\CU14GenerarReportes;
use App\Paquetes\Reportes\CasosUso\CU17ConsultarEstadisticas;

return static function (Enrutador $r): void {
    // CU14 · Generar reportes (vista, impresión/PDF y CSV)
    $r->get('/reportes', [CU14GenerarReportes::class, 'generar'], 'reportes.ver');

    // CU17 · Consultar estadísticas (gráficos por mes, inasistencia y especialidades)
    $r->get('/estadisticas', [CU17ConsultarEstadisticas::class, 'mostrar'], 'reportes.ver');
};
