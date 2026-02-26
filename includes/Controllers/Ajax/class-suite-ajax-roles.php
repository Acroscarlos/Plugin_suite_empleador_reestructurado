<?php
/**
 * Controlador AJAX: Gestión de Roles y Permisos Dinámicos (FASE 2-B)
 *
 * Maneja las peticiones del frontend para listar, crear, editar y eliminar 
 * roles personalizados (RBAC) basándose en las Capacidades (Capabilities).
 *
 * @package SuiteEmpleados\Controllers\Ajax
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 1. Obtener Lista de Roles y el Diccionario de Capacidades
 */
class Suite_Ajax_Role_List extends Suite_AJAX_Controller {

    protected $action_name = 'suite_get_roles';
    protected $required_capability = 'read';

    protected function process() {
        // Zero-Trust Security: Barrera estricta de capacidades
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'suite_manage_team' ) ) {
            $this->send_error( 'Acceso denegado', 403 );
        }

        $model = new Suite_Model_Roles();

        $this->send_success( [
            'roles'       => $model->get_all_roles(),
            'diccionario' => $model->get_capabilities_dictionary()
        ] );
    }
}

/**
 * 2. Crear o Actualizar un Rol (y sus Capacidades)
 */
class Suite_Ajax_Role_Save extends Suite_AJAX_Controller {

    protected $action_name = 'suite_save_role';
    protected $required_capability = 'read';

    protected function process() {
        // Zero-Trust Security: Barrera estricta de capacidades
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'suite_manage_team' ) ) {
            $this->send_error( 'Acceso denegado', 403 );
        }

        $role_key     = isset( $_POST['role_key'] ) ? sanitize_key( $_POST['role_key'] ) : '';
        $display_name = isset( $_POST['display_name'] ) ? sanitize_text_field( $_POST['display_name'] ) : '';
        // AJAX puede enviar arrays si se estructuran correctamente en el FormData o Payload
        $capabilities = isset( $_POST['capabilities'] ) && is_array( $_POST['capabilities'] ) ? $_POST['capabilities'] : [];

        if ( empty( $role_key ) || empty( $display_name ) ) {
            $this->send_error( 'El identificador del rol y el nombre a mostrar son obligatorios.' );
        }

        $model = new Suite_Model_Roles();
        $result = $model->create_or_update_role( $role_key, $display_name, $capabilities );

        if ( is_wp_error( $result ) ) {
            $this->send_error( $result->get_error_message() );
        }

        $this->send_success( 'Rol guardado y permisos actualizados correctamente.' );
    }
}

/**
 * 3. Eliminar un Rol
 */
class Suite_Ajax_Role_Delete extends Suite_AJAX_Controller {

    protected $action_name = 'suite_delete_role';
    protected $required_capability = 'read';

    protected function process() {
        // Zero-Trust Security: Barrera estricta de capacidades
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'suite_manage_team' ) ) {
            $this->send_error( 'Acceso denegado', 403 );
        }

        $role_key = isset( $_POST['role_key'] ) ? sanitize_key( $_POST['role_key'] ) : '';

        if ( empty( $role_key ) ) {
            $this->send_error( 'Identificador del rol no válido.' );
        }

        $model = new Suite_Model_Roles();
        $result = $model->delete_role( $role_key );

        if ( is_wp_error( $result ) ) {
            $this->send_error( $result->get_error_message(), 403 ); // 403 si intentó borrar rol protegido
        }

        $this->send_success( 'Rol eliminado del sistema.' );
    }
}