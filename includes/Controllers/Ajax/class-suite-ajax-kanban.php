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