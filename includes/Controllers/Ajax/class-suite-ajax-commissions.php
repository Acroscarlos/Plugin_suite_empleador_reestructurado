<?php
/**
 * Controlador AJAX: Dashboard Financiero y Gamificación (Módulo 4)
 *
 * Sirve los datos estadísticos de comisiones y premios para la vista del vendedor.
 *
 * @package SuiteEmpleados\Controllers\Ajax
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Suite_Ajax_Dashboard_Stats extends Suite_AJAX_Controller {

    protected $action_name = 'suite_get_dashboard_stats';
    
    // Todos los empleados pueden ver las estadísticas
    protected $required_capability = 'read'; 

    protected function process() {
        $vendedor_id = get_current_user_id();
        
        // Determinar mes y año actual
        $mes  = (int) date('m');
        $anio = (int) date('Y');

        global $wpdb;
        $tabla_ledger = $wpdb->prefix . 'suite_comisiones_ledger';

        // 1. Obtener la comisión acumulada del vendedor solicitante en el mes actual
        $sql_comision = $wpdb->prepare(
            "SELECT SUM(comision_ganada_usd) 
             FROM {$tabla_ledger} 
             WHERE vendedor_id = %d 
             AND MONTH(created_at) = %d 
             AND YEAR(created_at) = %d",
            $vendedor_id, $mes, $anio
        );
        $comision_mes = floatval( $wpdb->get_var( $sql_comision ) );

        // 2. Obtener el ranking de Gamificación desde el Modelo
        $commission_model = new Suite_Model_Commission();
        $gamification = $commission_model->get_gamification_winners( $mes, $anio );

        // Formateo visual para el frontend
        if ( $gamification['pez_gordo'] ) {
            $gamification['pez_gordo']->total_vendido = number_format( floatval( $gamification['pez_gordo']->total_vendido ), 2 );
        }
        
        // 3. Devolver los datos listos para pintar
        $this->send_success( [
            'mes_evaluado'    => date_i18n( 'F Y' ),
            'comision_actual' => number_format( $comision_mes, 2 ),
            'gamificacion'    => $gamification
        ] );
    }
}


/**
 * Endpoint Exclusivo Administrativo: Cierre de Mes (Freeze de Comisiones)
 * 
 * Convierte el estado 'pendiente' a 'pagado' congelando la modificación del
 * Ledger contable.
 * ARCHIVO: includes/Controllers/Ajax/class-suite-ajax-commissions.php
 */
class Suite_Ajax_Freeze_Commissions extends Suite_AJAX_Controller {

    protected $action_name = 'suite_freeze_commissions';
    protected $required_capability = 'read';

    protected function process() {
        // 1. Barrera Zero-Trust (Solo Administradores / Gerentes)
        $user = wp_get_current_user();
        $is_admin = current_user_can( 'manage_options' );
        $is_gerente = in_array( 'suite_gerente', (array)$user->roles );

        if ( ! $is_admin && ! $is_gerente ) {
            $this->send_error( 'Acceso Denegado. Función financiera exclusiva de gerencia.', 403 );
        }

        // Fecha de corte para evitar liquidar ventas de hoy si el cierre es del mes anterior
        $fecha_corte = isset( $_POST['fecha_corte'] ) ? sanitize_text_field( $_POST['fecha_corte'] ) : current_time('mysql');

        global $wpdb;
        $tabla_ledger = $wpdb->prefix . 'suite_comisiones_ledger';

        // 2. Congelamiento Masivo y Atómico (Update Bulk)
        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$tabla_ledger}
                 SET estado_pago = 'pagado'
                 WHERE estado_pago = 'pendiente' AND created_at <= %s",
                $fecha_corte
            )
        );

        if ( $updated !== false ) {
            // --- NUEVO: ADJUDICACIÓN DE PREMIOS GAMIFICACIÓN ---
            // Extraemos el mes y año en base a la fecha del corte que se acaba de congelar
            $timestamp_corte = strtotime( $fecha_corte );
            $mes_corte = (int) date( 'm', $timestamp_corte );
            $anio_corte = (int) date( 'Y', $timestamp_corte );
            
            // Adjudicamos los premios (Nacerán como "pendientes" y se liquidarán en el siguiente cierre)
            $commission_model = new Suite_Model_Commission();
            $commission_model->award_monthly_prizes( $mes_corte, $anio_corte );

            // Opcional: Escribir en la tabla Logs de Auditoría
            
            
            if ( function_exists('suite_record_log') ) {
                suite_record_log('cierre_mes', "Se ejecutó el Cierre Contable de Comisiones. ({$updated} registros congelados hasta: {$fecha_corte}).");
            }
            $this->send_success( "Cierre de mes ejecutado exitosamente. Se han liquidado y congelado {$updated} comisiones en el sistema." );
        } else {
            $this->send_error( 'Ocurrió un error en la base de datos al intentar congelar el Ledger.', 500 );
        }
    }
}