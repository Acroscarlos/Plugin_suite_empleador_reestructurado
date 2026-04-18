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
    protected $required_capability = 'read';

    protected function process() {
		
		if ( ! current_user_can('manage_options') && ! current_user_can('suite_freeze_commissions') ) {
            $this->send_error( 'Privilegios insuficientes para realizar esta acción.' );
            return;
        }		
		

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
             WHERE estado_pago IN ('pendiente', 'pagado') 
             AND MONTH(created_at) = MONTH(%s) AND YEAR(created_at) = YEAR(%s) 
             AND created_at <= %s AND monto_base_usd > 0
             GROUP BY vendedor_id ORDER BY total_vendido DESC LIMIT 1",
            $fecha_corte, $fecha_corte, $fecha_corte
        ) );
		
		
		
		

        // B) Ganador "🏃 Deja pa' los demás" (Mayor cantidad de órdenes)
        $deja_pa = $wpdb->get_row( $wpdb->prepare(
            "SELECT vendedor_id, COUNT(id) as total_ordenes 
             FROM {$tabla_ledger} 
             WHERE estado_pago IN ('pendiente', 'pagado') 
             AND MONTH(created_at) = MONTH(%s) AND YEAR(created_at) = YEAR(%s) 
             AND created_at <= %s AND monto_base_usd > 0
             GROUP BY vendedor_id ORDER BY total_ordenes DESC LIMIT 1",
            $fecha_corte, $fecha_corte, $fecha_corte
        ) );

        $premios = [
            'dale_play'         => [ 'id' => $dale_play_winner_id, 'monto' => 20.00, 'nombre' => "▶️ Dale Play" ],
            'pez_gordo'         => [ 'id' => $pez_gordo ? $pez_gordo->vendedor_id : 0, 'monto' => 20.00, 'nombre' => "🐟 Pez Gordo" ],
            'deja_pa_los_demas' => [ 'id' => $deja_pa ? $deja_pa->vendedor_id : 0, 'monto' => 20.00, 'nombre' => "🏃 Deja pa' los demás" ]
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
                        'estado_pago'         => 'pagado' // Nace como 'pagado' para quedar congelado en este mismo corte
                    ],
                    [ '%d', '%d', '%f', '%f', '%s' ]
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

        // Sentencia SQL Base (Sanitizada: Retiramos 'l.notas' para evitar fallos de esquema)
        $sql = "SELECT l.id, l.quote_id, l.monto_base_usd, l.comision_ganada_usd, 
                       l.estado_pago, l.recibo_loyverse, l.estado_auditoria, l.created_at, 
                       UNIX_TIMESTAMP(l.created_at) AS timestamp_orden,
                       u.display_name AS vendedor_nombre 
                FROM {$tabla_ledger} l
                LEFT JOIN {$tabla_users} u ON l.vendedor_id = u.ID";

        // Barrera Zero-Trust (Row-Level Security)
        if ( ! $is_admin_gerente ) {
            $sql .= $wpdb->prepare( " WHERE l.vendedor_id = %d", $user_id );
        }

        $sql .= " ORDER BY l.id DESC LIMIT 1000";

        $resultados = $wpdb->get_results( $sql );

        // Enviamos la data a la vista. El DataTables (JS) ahora sí tiene todas las piezas.
        $this->send_success( $resultados );
    }
}





/**
 * Controlador AJAX: Acciones Manuales del Auditor de Loyverse (Fase 5.2)
 * Permite al administrador aprobar forzosamente o anular comisiones incongruentes.
 */
class Suite_Ajax_Process_Audit_Action extends Suite_AJAX_Controller {

    protected $action_name = 'suite_process_audit_action';
    
    // BARRERA ABSOLUTA: Solo los administradores financieros pueden tocar esto
    protected $required_capability = 'manage_options'; 

    protected function process() {
        global $wpdb;
        $tabla_ledger = $wpdb->prefix . 'suite_comisiones_ledger';

        $ledger_id = isset( $_POST['ledger_id'] ) ? intval( $_POST['ledger_id'] ) : 0;
        $action    = isset( $_POST['audit_action'] ) ? sanitize_text_field( $_POST['audit_action'] ) : '';

        if ( ! $ledger_id || ! in_array( $action, ['force_approve', 'reject_fraud'] ) ) {
            $this->send_error( 'Datos de auditoría inválidos o corruptos.', 400 );
        }

        if ( $action === 'force_approve' ) {
            // APROBACIÓN FORZADA: Pasa la auditoría a verificado, el pago sigue pendiente hasta cierre de mes.
            $actualizado = $wpdb->update(
                $tabla_ledger,
                [ 'estado_auditoria' => 'verificado' ],
                [ 'id' => $ledger_id ],
                [ '%s' ],
                [ '%d' ]
            );

            if ( $actualizado === false ) $this->send_error('Error al aprobar en BD.');
            $this->send_success( ['message' => 'Comisión verificada forzosamente.'] );

        } elseif ( $action === 'reject_fraud' ) {
            // ANULACIÓN POR FRAUDE: Mata la auditoría y anula el pago permanentemente.
            $actualizado = $wpdb->update(
                $tabla_ledger,
                [ 
                    'estado_auditoria' => 'incongruente',
                    'estado_pago'      => 'anulado'
                ],
                [ 'id' => $ledger_id ],
                [ '%s', '%s' ],
                [ '%d' ]
            );

            if ( $actualizado === false ) $this->send_error('Error al anular en BD.');
            $this->send_success( ['message' => 'Comisión anulada permanentemente por fraude o error.'] );
        }
    }
}



/**
 * Controlador AJAX: Ejecución Manual del Auditor de Loyverse (Fase 5.3)
 * Conecta con la API REST de Loyverse, aplica sanitización de formato y concilia montos.
 */
class Suite_Ajax_Run_Manual_Audit extends Suite_AJAX_Controller {

    protected $action_name = 'suite_run_manual_audit';
    
    // Solo la gerencia/administración puede disparar la auditoría masiva
    protected $required_capability = 'manage_options'; 

    protected function process() {
        global $wpdb;
        $tabla_ledger = $wpdb->prefix . 'suite_comisiones_ledger';

        // 🚨 ATENCIÓN CARLOS: Pega aquí tu Token de Loyverse
        $api_token = '012d2a9b2e0a4930a60d76ce769f1ec8';

        // 1. AGRUPACIÓN INTELIGENTE
        $pendientes = $wpdb->get_results( "
            SELECT recibo_loyverse, SUM(monto_base_usd) as total_erp
            FROM {$tabla_ledger}
            WHERE estado_auditoria = 'pendiente' 
              AND recibo_loyverse IS NOT NULL 
              AND recibo_loyverse != ''
            GROUP BY recibo_loyverse
            LIMIT 50
        " );

        if ( empty( $pendientes ) ) {
            $this->send_success( ['message' => 'El Ledger está limpio. No hay recibos pendientes por auditar.'] );
        }

        $verificados = 0;
        $incongruentes = 0;

        // 2. CICLO DE AUDITORÍA
        foreach ( $pendientes as $req ) {
            
            // --- INICIO MAGIA DE FORMATEO (Fase 5.3) ---
            $raw_receipt = trim( $req->recibo_loyverse );
            
            // A. Quitamos guiones accidentales y todos los ceros a la izquierda
            $clean_receipt = ltrim( str_replace('-', '', $raw_receipt), '0' );
            
            // B. Aplicamos máscara "nn-nnnn" o "n-nnnn" (Guion antes de los últimos 4 dígitos)
            $len = strlen( $clean_receipt );
            if ( $len >= 5 ) {
                $formatted_receipt = substr( $clean_receipt, 0, $len - 4 ) . '-' . substr( $clean_receipt, -4 );
            } else {
                // Si el recibo es extrañamente corto, lo mandamos tal cual para que Loyverse decida
                $formatted_receipt = $clean_receipt;
            }
            
            // Codificamos para URL segura (ej: 45-1243)
            $recibo_url = urlencode( $formatted_receipt );
            // --- FIN MAGIA DE FORMATEO ---

            $url = "https://api.loyverse.com/v1.0/receipts/{$recibo_url}";

            $response = wp_remote_get( $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_token,
                    'Accept'        => 'application/json'
                ],
                'timeout' => 10 
            ]);

			
            $nuevo_estado = 'incongruente';
			$log_detalle = "Recibo: {$formatted_receipt} | ";

			if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) == 200 ) {
				$body = json_decode( wp_remote_retrieve_body( $response ), true );

				if ( isset( $body['total_money'] ) ) {
					$monto_loyverse = floatval( $body['total_money'] );
					$monto_erp      = floatval( $req->total_erp );

					$diferencia = abs( $monto_loyverse - $monto_erp );
					$log_detalle .= "Loyverse: {$monto_loyverse} | ERP: {$monto_erp} | Dif: {$diferencia}";

					if ( $diferencia <= 0.05 ) {
						$nuevo_estado = 'verificado';
					}
				}
			} else {
				$code = wp_remote_retrieve_response_code( $response );
				$log_detalle .= "ERROR API: Código HTTP {$code}";
			}

			// REGISTRO DE TELEMETRÍA: Para que veas qué pasó en la tabla suite_logs
			if ( function_exists('suite_record_log') ) {
				suite_record_log( 'auditoria_pos', $log_detalle );
			}

            // 4. ACTUALIZACIÓN DEL LEDGER EN MASA (Usamos el recibo original guardado en la BD para el WHERE)
            $wpdb->update(
                $tabla_ledger,
                [ 'estado_auditoria' => $nuevo_estado ],
                [ 'recibo_loyverse' => $req->recibo_loyverse, 'estado_auditoria' => 'pendiente' ],
                [ '%s' ],
                [ '%s', '%s' ]
            );

            if ( $nuevo_estado === 'verificado' ) {
                $verificados++;
            } else {
                $incongruentes++;
            }
        }

        $this->send_success( [
            'message' => "Auditoría finalizada.<br>✅ <b>{$verificados}</b> Verificados.<br>🚨 <b>{$incongruentes}</b> Incongruencias o errores."
        ] );
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



/**
 * Controlador: Liquidación de Comisiones Seleccionadas
 */
class Suite_Ajax_Pay_Selected extends Suite_AJAX_Controller {
    protected $action_name = 'suite_pay_selected_commissions';
    protected $required_capability = 'read';

    protected function process() {
        global $wpdb;
		
		if ( ! current_user_can('manage_options') && ! current_user_can('suite_action_approve_commissions') ) {
            $this->send_error( 'Privilegios insuficientes para realizar esta acción.' );
            return;
        }
		
        $ids = isset( $_POST['ledger_ids'] ) ? array_map( 'intval', $_POST['ledger_ids'] ) : [];

        if ( empty( $ids ) ) {
            $this->send_error( 'No se seleccionaron comisiones para pagar.' );
        }

        $ids_string = implode( ',', $ids );
        $tabla_ledger = $wpdb->prefix . 'suite_comisiones_ledger';

        // UPDATE blindado: Solo afecta a los IDs enviados que sigan estando 'pendiente'
        $updated = $wpdb->query( "UPDATE {$tabla_ledger} SET estado_pago = 'pagado' WHERE id IN ({$ids_string}) AND estado_pago = 'pendiente'" );

        if ( $updated !== false ) {
            if ( function_exists('suite_record_log') ) {
                suite_record_log( 'pago_comisiones', "Se liquidaron {$updated} líneas contables de forma manual." );
            }
            $this->send_success( "Se han pagado {$updated} registros exitosamente." );
        } else {
            $this->send_error( 'Fallo de integridad al actualizar el Ledger.' );
        }
    }
}

/**
 * Controlador: Registro de Abonos / Anticipos
 */
class Suite_Ajax_Register_Abono extends Suite_AJAX_Controller {
    protected $action_name = 'suite_register_abono';
    protected $required_capability = 'read';
	
    protected function process() {
        global $wpdb;
		
		if ( ! current_user_can('manage_options') && ! current_user_can('suite_action_approve_commissions') ) {
            $this->send_error( 'Privilegios insuficientes para realizar esta acción.' );
            return;
        }		
		
        $vendedor_id = isset( $_POST['vendedor_id'] ) ? intval( $_POST['vendedor_id'] ) : 0;
        $monto = isset( $_POST['monto'] ) ? floatval( $_POST['monto'] ) : 0;

        if ( ! $vendedor_id || $monto <= 0 ) {
            $this->send_error( 'Datos de abono inválidos.' );
        }

		// El abono nace como PENDIENTE y NEGATIVO para restar en la próxima liquidación
        $insert = $wpdb->insert(
            $wpdb->prefix . 'suite_comisiones_ledger',
            [
                'quote_id'            => 0,
                'vendedor_id'         => $vendedor_id,
                'monto_base_usd'      => 0,
                'comision_ganada_usd' => -$monto,
                'estado_pago'         => 'pendiente'
                // ELIMINADA LA COLUMNA 'notas' PARA EVITAR EL CRASH DE MYSQL
            ],
            [ '%d', '%d', '%f', '%f', '%s' ] // ELIMINADO UN '%s' AL FINAL
        );

        if ( $insert ) {
            if ( function_exists('suite_record_log') ) {
                suite_record_log( 'abono_comision', "Abono de \${$monto} registrado para el usuario ID {$vendedor_id}." );
            }
            $this->send_success( "Abono de \${$monto} registrado con éxito en el estado de cuenta." );
        } else {
            $this->send_error( 'No se pudo registrar el abono.' );
        }
    }
}