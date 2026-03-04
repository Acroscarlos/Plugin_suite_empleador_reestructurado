<?php
/**
 * Controlador AJAX: Gestión de Empleados (FASE 2-A)
 *
 * Maneja las operaciones CRUD de los usuarios/empleados del ERP.
 * Implementa seguridad estricta basada en capacidades (Zero-Trust).
 *
 * @package SuiteEmpleados\Controllers\Ajax
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 1. Obtener lista de todos los empleados
 */
class Suite_Ajax_Employee_List extends Suite_AJAX_Controller {

    protected $action_name = 'suite_get_employees';
    protected $required_capability = 'read';

    protected function process() {
        // Zero-Trust Security: Barrera estricta de capacidades
        if ( ! current_user_can('manage_options') && ! current_user_can('suite_manage_team') ) {
            $this->send_error('Acceso denegado', 403);
        }

        $model = new Suite_Model_Employee();
        $employees = $model->get_all_employees();

        $this->send_success( $employees );
    }
}

/**
 * 2. Crear o Editar un Empleado
 */
class Suite_Ajax_Employee_Save extends Suite_AJAX_Controller {

    protected $action_name = 'suite_save_employee';
    protected $required_capability = 'read';

    protected function process() {
        // Zero-Trust Security: Barrera estricta de capacidades
        if ( ! current_user_can('manage_options') && ! current_user_can('suite_manage_team') ) {
            $this->send_error('Acceso denegado', 403);
        }

        // Recibir y mapear los datos del POST
        $data = [
            'ID'         => isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0,
            'email'      => isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '',
            'password'   => isset( $_POST['password'] ) ? $_POST['password'] : '',
            'first_name' => isset( $_POST['first_name'] ) ? sanitize_text_field( $_POST['first_name'] ) : '',
            'last_name'  => isset( $_POST['last_name'] ) ? sanitize_text_field( $_POST['last_name'] ) : '',
            'role'       => isset( $_POST['role'] ) ? sanitize_text_field( $_POST['role'] ) : '',
            'telefono'   => isset( $_POST['telefono'] ) ? sanitize_text_field( $_POST['telefono'] ) : '',
        ];

        // Validaciones básicas de campos obligatorios
        if ( empty( $data['email'] ) ) {
            $this->send_error( 'El correo electrónico es obligatorio.' );
        }

        $model = new Suite_Model_Employee();
        $result = $model->save_employee( $data );

        // Manejo de errores devueltos por el Modelo
        if ( is_wp_error( $result ) ) {
            $this->send_error( $result->get_error_message() );
        }

		// --- INICIO FASE 3.1: ELEGIBILIDAD ---
        $user_id = $result; // El modelo nos devuelve el ID del usuario guardado
        
        // Guardado de la preferencia de Elegibilidad (Gamificación y Comisiones)
        $participa_comisiones = isset( $_POST['participa_comisiones'] ) && $_POST['participa_comisiones'] === '1' ? 1 : 0;
        update_user_meta( $user_id, 'suite_participa_comisiones', $participa_comisiones );
        // --- FIN FASE 3.1 ---		

		// --- INICIO FASE 5.1: IDENTIDAD B2B ---
        $is_b2b = isset( $_POST['is_b2b'] ) && $_POST['is_b2b'] == '1' ? 1 : 0;
        update_user_meta( $user_id, 'suite_is_b2b', $is_b2b );
        // --- FIN FASE 5.1 ---		
		
        $this->send_success( [
            'message' => 'Empleado guardado correctamente.',
            'user_id' => $result
        ] );	
    }
}

/**
 * 3. Eliminar un Empleado
 */
class Suite_Ajax_Employee_Delete extends Suite_AJAX_Controller {

    protected $action_name = 'suite_delete_employee';
    protected $required_capability = 'read';

    protected function process() {
        // Zero-Trust Security: Barrera estricta de capacidades
        if ( ! current_user_can('manage_options') && ! current_user_can('suite_manage_team') ) {
            $this->send_error('Acceso denegado', 403);
        }

        $id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;

        if ( ! $id ) {
            $this->send_error( 'ID de empleado inválido.' );
        }

        // Prevención de auto-eliminación
        if ( get_current_user_id() === $id ) {
            $this->send_error( 'Seguridad: No puedes eliminar tu propia cuenta desde el ERP.', 403 );
        }

        $model = new Suite_Model_Employee();
        $result = $model->delete_employee( $id );

        // Manejo de errores devueltos por el Modelo
        if ( is_wp_error( $result ) ) {
            $this->send_error( $result->get_error_message() );
        }

        $this->send_success( 'Empleado eliminado correctamente.' );
    }
}