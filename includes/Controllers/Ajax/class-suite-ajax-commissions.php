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
 * Controlador AJAX: Cierre de Mes y Adjudicación de Premios (Fase 2.2)
 */
class Suite_Ajax_Freeze_Commissions extends Suite_AJAX_Controller {

    protected $action_name = 'suite_freeze_commissions';
    protected $required_capability = 'suite_freeze_commissions';

    protected function process() {

        $fecha_corte = isset( $_POST['fecha_corte'] ) ? sanitize_text_field( $_POST['fecha_corte'] ) : current_time('mysql');
        $dale_play_winner_id = isset( $_POST['dale_play_winner_id'] ) ? intval( $_POST['dale_play_winner_id'] ) : 0;

        if ( ! $dale_play_winner_id ) {
            $this->send_error( 'Se requiere especificar el ganador manual del premio Dale Play.' );
        }

        global $wpdb;
        $tabla_ledger = $wpdb->prefix . 'suite_comisiones_ledger';

        // ==========================================
        // 2. CÁLCULO DE PREMIOS AUTOMÁTICOS
        // ==========================================
        
        // A) Ganador "🐟 Pez Gordo" (Suma más alta de monto base)
        // Ignoramos deducciones (< 0) y bonos sin venta (= 0)
        $pez_gordo = $wpdb->get_row( $wpdb->prepare(
            "SELECT vendedor_id, SUM(monto_base_usd) as total_vendido 
             FROM {$tabla_ledger} 
             WHERE estado_pago = 'pendiente' AND created_at <= %s AND monto_base_usd > 0
             GROUP BY vendedor_id ORDER BY total_vendido DESC LIMIT 1",
            $fecha_corte
        ) );

        // B) Ganador "🏃 Deja pa' los demás" (Mayor cantidad de órdenes)
        $deja_pa = $wpdb->get_row( $wpdb->prepare(
            "SELECT vendedor_id, COUNT(id) as total_ordenes 
             FROM {$tabla_ledger} 
             WHERE estado_pago = 'pendiente' AND created_at <= %s AND monto_base_usd > 0
             GROUP BY vendedor_id ORDER BY total_ordenes DESC LIMIT 1",
            $fecha_corte
        ) );

        $premios = [
            'dale_play'         => [ 'id' => $dale_play_winner_id, 'monto' => 20.00, 'nombre' => "▶️ Dale Play" ],
            'pez_gordo'         => [ 'id' => $pez_gordo ? $pez_gordo->vendedor_id : 0, 'monto' => 50.00, 'nombre' => "🐟 Pez Gordo" ],
            'deja_pa_los_demas' => [ 'id' => $deja_pa ? $deja_pa->vendedor_id : 0, 'monto' => 30.00, 'nombre' => "🏃 Deja pa' los demás" ]
        ];

        // ==========================================
        // 3. INYECCIÓN DE DINERO Y SALÓN DE LA FAMA
        // ==========================================
        
        $historial_ganadores = [];

        foreach ( $premios as $key => $data ) {
            if ( $data['id'] > 0 ) {
                // Inyectamos fila en el Ledger
                $wpdb->insert(
                    $tabla_ledger,
                    [
                        'quote_id'            => 0, // 0 indica bono, no amarrado a una orden física
                        'vendedor_id'         => $data['id'],
                        'monto_base_usd'      => 0,
                        'comision_ganada_usd' => $data['monto'],
                        'estado_pago'         => 'pagado', // Nace como 'pagado' para quedar congelado en este mismo corte
                        'notas'               => "Bono Premio Mensual: {$data['nombre']}"
                    ],
                    [ '%d', '%d', '%f', '%f', '%s', '%s' ]
                );

                // Recopilamos datos para el Salón de la Fama
                $user_info = get_userdata( $data['id'] );
                $historial_ganadores[$key] = [
                    'vendedor_id'   => $data['id'],
                    'vendedor_name' => $user_info ? $user_info->display_name : 'Desconocido',
                    'monto_premio'  => $data['monto'],
                    'premio_nombre' => $data['nombre']
                ];
            }
        }

        // Guardar el JSON Inmutable del Salón de la Fama en wp_options
        $mes_cierre = date( 'Y_m', strtotime( $fecha_corte ) );
        update_option( 'suite_hall_of_fame_' . $mes_cierre, wp_json_encode( $historial_ganadores ), false );

        // ==========================================
        // 4. CIERRE GENERAL (CONGELAMIENTO)
        // ==========================================
        
        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$tabla_ledger} 
                 SET estado_pago = 'pagado' 
                 WHERE estado_pago = 'pendiente' AND created_at <= %s",
                $fecha_corte
            )
        );

        if ( $updated !== false ) {
            // Auditoría
            if ( function_exists('suite_record_log') ) {
                suite_record_log( 'cierre_mes', "Cierre de Mes ejecutado. {$updated} comisiones congeladas. Premios adjudicados en el Ledger." );
            }
            $this->send_success( "Cierre contable ejecutado. Se congelaron {$updated} registros y se adjudicaron los premios exitosamente." );
        } else {
            $this->send_error( 'Fallo de integridad en base de datos al intentar congelar el Ledger.', 500 );
        }
    }
}

/**
 * Controlador AJAX: Auditoría de Comisiones (RLS Aplicado)
 */
class Suite_Ajax_Commission_Audit extends Suite_AJAX_Controller {

    protected $action_name = 'suite_get_commission_audit';
    protected $required_capability = 'read';

    protected function process() {
        global $wpdb;

        $user_id = get_current_user_id();
        $user_roles = (array) wp_get_current_user()->roles;
        
        // Validación de gerencia unificada (Admin, suite_gerente, o gerente)
        $is_admin_gerente = current_user_can( 'manage_options' ) || in_array( 'suite_gerente', $user_roles ) || in_array( 'gerente', $user_roles );

        $tabla_ledger = $wpdb->prefix . 'suite_comisiones_ledger';
        $tabla_users  = $wpdb->users;

        // Sentencia SQL Base
        $sql = "SELECT l.quote_id, l.monto_base_usd, l.comision_ganada_usd, l.estado_pago, l.created_at, u.display_name AS vendedor_nombre 
                FROM {$tabla_ledger} l
                LEFT JOIN {$tabla_users} u ON l.vendedor_id = u.ID";

        // Barrera Zero-Trust (Row-Level Security)
        if ( ! $is_admin_gerente ) {
            $sql .= $wpdb->prepare( " WHERE l.vendedor_id = %d", $user_id );
        }

        $sql .= " ORDER BY l.id DESC LIMIT 1000";

        $resultados = $wpdb->get_results( $sql );

        // Enviamos la data cruda a la vista. El DataTables (JS) se encargará de darle formato a la fecha.
        $this->send_success( $resultados );
    }
}

/**
 * Controlador AJAX: Salón de la Fama Histórico
 */
class Suite_Ajax_Hall_of_Fame extends Suite_AJAX_Controller {

    protected $action_name = 'suite_get_hall_of_fame';
    protected $required_capability = 'read';

    protected function process() {
        global $wpdb;

        // Leemos las opciones inmutables generadas en la Fase 2.2
        $resultados = $wpdb->get_results( "
            SELECT option_name, option_value 
            FROM {$wpdb->options} 
            WHERE option_name LIKE 'suite_hall_of_fame_%'
            ORDER BY option_name DESC
        " );

        $fame_data = [];

        foreach ( $resultados as $row ) {
            // Limpiamos el string para dejar solo el año_mes
            $mes_raw = str_replace( 'suite_hall_of_fame_', '', $row->option_name );
            $premios = json_decode( $row->option_value, true );

            if ( is_array( $premios ) ) {
                $fame_data[] = [
                    'mes'     => $mes_raw,
                    'premios' => $premios
                ];
            }
        }

        $this->send_success( $fame_data );
    }
}