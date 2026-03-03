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



/**
 * Controlador AJAX: Actualizador de Capacidades de la Matriz RBAC (Fase 4.1)
 */
class Suite_Ajax_Update_Role_Cap extends Suite_AJAX_Controller {

    protected $action_name = 'suite_update_role_cap';
    // Barrera Zero-Trust: Solo quien puede gestionar opciones (El Admin maestro)
    protected $required_capability = 'manage_options'; 

    protected function process() {
        $role_name  = isset( $_POST['role'] ) ? sanitize_text_field( $_POST['role'] ) : '';
        $capability = isset( $_POST['capability'] ) ? sanitize_text_field( $_POST['capability'] ) : '';
        $is_granted = isset( $_POST['is_granted'] ) && $_POST['is_granted'] == '1';

        if ( empty( $role_name ) || empty( $capability ) ) {
            $this->send_error( 'Faltan parámetros requeridos (rol o capacidad).' );
        }

        // Blindaje adicional: Proteger al administrador maestro
        if ( $role_name === 'administrator' ) {
            $this->send_error( 'Acción bloqueada: No se pueden modificar las capacidades del Administrador maestro.' );
        }

        $role_obj = get_role( $role_name );

        if ( ! $role_obj ) {
            $this->send_error( 'El rol especificado no existe en el sistema.' );
        }

        // Lógica Central: Añadir o Quitar Capacidad
        if ( $is_granted ) {
            $role_obj->add_cap( $capability );
            $mensaje = "Capacidad '{$capability}' ASIGNADA al rol '{$role_name}'.";
        } else {
            $role_obj->remove_cap( $capability );
            $mensaje = "Capacidad '{$capability}' REVOCADA del rol '{$role_name}'.";
        }

        // Auditoría
        if ( function_exists( 'suite_record_log' ) ) {
            suite_record_log( 'matriz_rbac', "Matriz de Permisos: " . $mensaje );
        }

        $this->send_success( $mensaje );
    }
}