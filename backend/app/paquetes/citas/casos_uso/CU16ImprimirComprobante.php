<?php
namespace App\Paquetes\Citas\CasosUso;

use App\Nucleo\Autenticacion;
use App\Nucleo\Bitacora;
use App\Nucleo\Controlador;
use App\Nucleo\Vista;
use App\Paquetes\Citas\Modelos\CitaModelo;

/**
 * CU16 · Imprimir comprobante de cita (mejora de CU09 y CU13)
 * Requisitos: RF06 · RF11 · RN12 · Actores: Recepcionista, Paciente, Médico
 *
 * Flujo principal:
 *  1. Desde el detalle de la cita o desde «Mis citas», el usuario pulsa «Comprobante».
 *  2. El sistema verifica que pueda ver esa cita (el paciente solo las suyas; el médico solo las propias).
 *  3. Muestra un ticket listo para imprimir o «Guardar como PDF» con el código de la cita, paciente,
 *     médico, especialidad, fecha, hora, modalidad e indicaciones. Una cita cancelada sale marcada.
 *
 * ──────────────── Ficha del caso de uso ────────────────
 * Paquete.........: citas
 * Actores.........: Recepcionista, Paciente, Médico
 * Requisitos......: RF06 · RF11 · RN12 (cada usuario solo imprime lo que puede ver)
 * Base de datos...: vista v_cita_detalle (solo lectura), bitacora
 * Rutas...........: GET /citas/{id}/comprobante (citas.ver | agenda.propia | portal.mis_citas)
 * Vistas..........: frontend/vistas/citas/comprobante.php (página propia, sin menú, estilo de impresión)
 * Usa.............: CitaModelo, Bitacora
 * Precondición....: La cita existe y el usuario tiene permiso para verla.
 * Postcondición...: Se muestra el comprobante; no se modifica ningún dato.
 * Flujo...........: rutas.php -> este archivo -> CitaModelo -> PostgreSQL -> vista en frontend/vistas/citas/
 * Diagramas.......: doc/staruml (clases de diseño) y doc/Grupo19_SI1_Clinica_Vida_Salud.docx (6.2 secuencia)
 * Estado..........: IMPLEMENTADO y probado.
 */
final class CU16ImprimirComprobante extends Controlador
{
    /** GET /citas/{id}/comprobante */
    public function comprobante(int $id): void
    {
        $cita = (new CitaModelo())->obtener($id) ?? $this->noEncontrado('La cita');
        $u = $this->usuario();
        // Paso 2: un médico solo imprime sus citas; un paciente solo las suyas (RN12).
        if (($u['rol'] === 'medico' && (int) $cita['id_medico'] !== (int) $u['id_medico'] && !$this->puede('citas.ver'))
            || ($u['rol'] === 'paciente' && (int) $cita['id_paciente'] !== (int) $u['id_paciente'])) {
            Autenticacion::exigirPermiso('citas.ver');
        }
        Bitacora::registrar('comprobante', 'citas', "Comprobante de la cita {$cita['codigo']}.");
        Vista::mostrar('citas/comprobante', ['titulo' => 'Comprobante ' . $cita['codigo'], 'cita' => $cita], 'ninguno');
    }
}
