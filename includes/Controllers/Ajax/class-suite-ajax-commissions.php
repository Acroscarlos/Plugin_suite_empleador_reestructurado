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