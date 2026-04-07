<?php
/**
 * Controlador AJAX: Tablero Kanban (Módulo 1)
 *
 * Contiene los manejadores para poblar las columnas del Kanban 
 * y actualizar los estados tras un evento Drag & Drop.
 *
 * @package SuiteEmpleados\Controllers\Ajax
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Endpoint para obtener todos los pedidos y poblar el Tablero Kanban
 */
class Suite_Ajax_Kanban_Data extends Suite_AJAX_Controller {

    protected $action_name = 'suite_get_kanban_data';
    protected $required_capability = 'read';

	protected function process() {
        $user_id = get_current_user_id();
        $user = wp_get_current_user();
        
        // Row-Level Security (Control de Accesos Módulo 2)
        $is_admin = current_user_can( 'manage_options' );
        $is_logistica = in_array( 'suite_logistica', (array) $user->roles );
        // AGREGADO: Validación de gerente consultando directamente el objeto $user
        $is_gerente = in_array( 'suite_gerente', (array) $user->roles ) || in_array( 'gerente', (array) $user->roles );
        
        $tiene_acceso_global = ( $is_admin || $is_logistica || $is_gerente );

        $quote_model = new Suite_Model_Quote();
        
        // Invocar método del modelo agrupando por columnas
        $kanban_data = $quote_model->get_kanban_orders( $user_id, $tiene_acceso_global );
		
		
		// --- INICIO FASE 5.3: IDENTIFICADOR B2B PARA GATILLO MANUAL ---
        // Recorremos cada columna (emitida, proceso, etc.) y cada orden dentro de ellas
        foreach ( $kanban_data as $columna => $ordenes ) {
            if ( is_array( $ordenes ) ) {
                foreach ( $ordenes as &$order ) {
                    // Consultamos si el vendedor de esta orden es Aliado Comercial
                    // Usamos el ID del vendedor que ya viene en el objeto de la orden
                    $vendedor_id = isset( $order->vendedor_id ) ? $order->vendedor_id : 0;
                    $order->vendedor_is_b2b = ( get_user_meta( $vendedor_id, 'suite_is_b2b', true ) === '1' );
                }
            }
        }
        // --- FIN FASE 5.3 ---
		
		
		
        $this->send_success( $kanban_data );
    }
}

/**
 * Endpoint para procesar el Drop de una tarjeta en una nueva columna
 */
class Suite_Ajax_Kanban_Status extends Suite_AJAX_Controller {

    protected $action_name = 'suite_update_kanban_status';
    protected $required_capability = 'read';

    protected function process() {
        // 1. Recibir datos del Frontend (Drag & Drop)
        $quote_id   = isset( $_POST['quote_id'] ) ? intval( $_POST['quote_id'] ) : 0;
        $new_status = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : '';

        if ( ! $quote_id || empty( $new_status ) ) {
            $this->send_error( 'Parámetros insuficientes para mover la tarjeta.' );
        }

        // TODO: [Módulo 2 - Seguridad] 
        // Implementar Candado de Inmutabilidad aquí:
        // Si la cotización ya estaba en "pagado" y el usuario no es Admin/Supervisor, 
        // bloquear el retroceso a "emitida".

        // 2. Instanciar y actualizar
        $quote_model = new Suite_Model_Quote();
        $updated = $quote_model->update_order_status( $quote_id, $new_status );

        // 3. Respuesta
        if ( $updated ) {
            $this->send_success( [ 'message' => 'El pedido fue movido correctamente a ' . strtoupper( $new_status ) ] );
        } else {
            $this->send_error( 'Fallo al mover el pedido. Es posible que ya estuviese en ese estado.' );
        }
    }
}


/**
 * Controlador AJAX: Logística Inversa y Reverso Financiero (Fase 2.1)
 */
class Suite_Ajax_Reverse_Logistics extends Suite_AJAX_Controller {

    protected $action_name = 'suite_reverse_logistics';
    
    // Barrera Zero-Trust: Solo el Administrador puede ejecutar este endpoint
    protected $required_capability = 'manage_options'; 

    protected function process() {
        global $wpdb;

        $order_id = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : 0;

        if ( ! $order_id ) {
            $this->send_error( 'ID de orden inválido.' );
        }

        // 1. Acción Principal: Devolver la orden a estado 'pagado'
        $tabla_cotizaciones = $wpdb->prefix . 'suite_cotizaciones';
        $actualizado = $wpdb->update(
            $tabla_cotizaciones,
            [ 'estado' => 'pagado' ],
            [ 'id' => $order_id ],
            [ '%s' ],
            [ '%d' ]
        );

        if ( $actualizado === false ) {
            $this->send_error( 'Error en base de datos al intentar cambiar el estado del pedido.' );
        }

        // 2. Lógica Contable (El Ledger)
        $tabla_ledger = $wpdb->prefix . 'suite_comisiones_ledger';
        
        $comisiones = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$tabla_ledger} WHERE quote_id = %d", 
            $order_id 
        ) );

        foreach ( $comisiones as $comision ) {
            // Regla Idempotente: Evitar procesar deducciones (números negativos) que ya existen
            if ( floatval( $comision->comision_ganada_usd ) < 0 ) {
                continue;
            }

            if ( $comision->estado_pago === 'pendiente' ) {
                // Caso B: Si está pendiente, se anula para no pagarla a fin de mes
                $wpdb->update(
                    $tabla_ledger,
                    [ 'estado_pago' => 'anulado' ],
                    [ 'id' => $comision->id ],
                    [ '%s' ],
                    [ '%d' ]
                );
            } elseif ( $comision->estado_pago === 'pagado' ) {
                // Caso C: Si ya fue cobrada, se inserta la deducción negativa para el mes actual
                $wpdb->insert(
                    $tabla_ledger,
                    [
                        'quote_id'            => $comision->quote_id,
                        'vendedor_id'         => $comision->vendedor_id,
                        'monto_base_usd'      => -floatval( $comision->monto_base_usd ),
                        'comision_ganada_usd' => -floatval( $comision->comision_ganada_usd ),
                        'estado_pago'         => 'pendiente',
                        'notas'               => "Deducción por Logística Inversa - Orden #{$order_id}"
                    ],
                    [ '%d', '%d', '%f', '%f', '%s', '%s' ]
                );
            }
        }

        // 3. Auditoría: Registro inmutable del movimiento
        if ( function_exists( 'suite_record_log' ) ) {
            $user_id = get_current_user_id();
            suite_record_log( 
                'logistica_inversa', 
                "El Administrador (ID: {$user_id}) aplicó Logística Inversa a la orden #{$order_id}. La orden retornó a 'pagado' y el Ledger fue ajustado automáticamente." 
            );
        }

        $this->send_success( 'Logística inversa aplicada y Ledger ajustado con éxito.' );
    }
}