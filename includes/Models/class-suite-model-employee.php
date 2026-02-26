<?php
/**
 * Modelo de Base de Datos: Gestor de Empleados (Usuarios)
 *
 * Actúa como un Wrapper de las funciones nativas de WordPress para la gestión 
 * de usuarios (WP_User, wp_insert_user, get_users). Maneja la lógica de negocio
 * del CRUD de empleados del ERP.
 *
 * @package SuiteEmpleados\Models
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Suite_Model_Employee {

    /**
     * Obtiene todos los empleados registrados en el ERP.
     * Excluye a los usuarios con rol 'subscriber' puro y trae el teléfono de los metadatos.
     *
     * @return array Lista de empleados con sus datos formateados.
     */
	public function get_all_employees() {
        // Obtenemos todos los usuarios excepto los suscriptores y clientes genéricos
        $args = [
            'role__not_in' => [ 'subscriber', 'customer' ],
            'orderby'      => 'display_name',
            'order'        => 'ASC'
        ];

        $users = get_users( $args );
        $employees = [];

        foreach ( $users as $u ) {
            // Obtener el meta_key 'suite_telefono'. Si no existe, buscamos el fallback 'billing_phone'.
            $telefono = get_user_meta( $u->ID, 'suite_telefono', true );
            if ( empty( $telefono ) ) {
                $telefono = get_user_meta( $u->ID, 'billing_phone', true );
            }

            $employees[] = [
                'id'         => $u->ID,
                'nombre'     => $u->display_name,
                'email'      => $u->user_email,
                'telefono'   => ! empty( $telefono ) ? $telefono : '-',
                'roles'      => array_values( $u->roles ), // CORRECCIÓN: Forzamos un array indexado limpio para JS
            ];
        }

        return $employees;
    }

    /**
     * Obtiene los datos detallados de un empleado en específico.
     *
     * @param int $id ID del usuario.
     * @return array|false Datos del empleado o false si no existe.
     */
    public function get_employee( $id ) {
        $user = get_userdata( intval( $id ) );

        if ( ! $user ) {
            return false;
        }

        $telefono = get_user_meta( $user->ID, 'suite_telefono', true );
        if ( empty( $telefono ) ) {
            $telefono = get_user_meta( $user->ID, 'billing_phone', true );
        }

        return [
            'id'         => $user->ID,
            'nombre'     => $user->display_name,
            'first_name' => $user->first_name,
            'last_name'  => $user->last_name,
            'email'      => $user->user_email,
            'telefono'   => $telefono,
            'roles'      => $user->roles
        ];
    }

    /**
     * Crea o actualiza un empleado en la base de datos de WordPress.
     *
     * @param array $data Array con los campos: ID (opcional), email, password, first_name, last_name, role, telefono.
     * @return int|WP_Error El ID del usuario guardado o un objeto de error.
     */
    public function save_employee( $data ) {
        $id         = isset( $data['ID'] ) ? intval( $data['ID'] ) : 0;
        $email      = sanitize_email( $data['email'] );
        $password   = isset( $data['password'] ) ? $data['password'] : '';
        $first_name = isset( $data['first_name'] ) ? sanitize_text_field( $data['first_name'] ) : '';
        $last_name  = isset( $data['last_name'] ) ? sanitize_text_field( $data['last_name'] ) : '';
        $role       = isset( $data['role'] ) ? sanitize_text_field( $data['role'] ) : '';
        $telefono   = isset( $data['telefono'] ) ? sanitize_text_field( $data['telefono'] ) : '';

        if ( empty( $email ) ) {
            return new WP_Error( 'empty_email', 'El correo electrónico es obligatorio.' );
        }

        // Preparar arreglo para wp_insert_user / wp_update_user
        $user_data = [
            'user_email' => $email,
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'role'       => $role
        ];

        // Construir Display Name automáticamente si hay datos
        $display_name = trim( $first_name . ' ' . $last_name );
        if ( ! empty( $display_name ) ) {
            $user_data['display_name'] = $display_name;
        }

        // Lógica dividida: Actualización vs Creación
        if ( $id > 0 ) {
            $user_data['ID'] = $id;

            // Seguridad: Prevenir que un Administrador se quite su propio rol por accidente
            $current_user_id = get_current_user_id();
            if ( $id === $current_user_id && $role !== 'administrator' ) {
                $user_obj = get_userdata( $id );
                if ( in_array( 'administrator', (array) $user_obj->roles, true ) ) {
                    return new WP_Error( 'self_demotion', 'Seguridad: No puedes quitarte el rol de Administrador a ti mismo.' );
                }
            }

            // Solo actualizamos la contraseña si se envió una nueva
            if ( ! empty( $password ) ) {
                $user_data['user_pass'] = $password;
            }

        } else {
            // Es un usuario nuevo
            if ( username_exists( $email ) || email_exists( $email ) ) {
                return new WP_Error( 'user_exists', 'El correo electrónico ya está registrado en el sistema.' );
            }

            if ( empty( $password ) ) {
                return new WP_Error( 'empty_password', 'La contraseña es obligatoria para crear nuevos usuarios.' );
            }

            // Usamos el email como nombre de usuario de login
            $user_data['user_login'] = $email;
            $user_data['user_pass']  = $password;
        }

        // wp_insert_user maneja tanto INSERT como UPDATE (si se envía el 'ID' en el array)
        $user_id = wp_insert_user( $user_data );

        if ( is_wp_error( $user_id ) ) {
            return $user_id; // Retorna el error al controlador
        }

        // Guardar la Meta Data (Teléfono usado en impresiones PDF del ERP)
        update_user_meta( $user_id, 'suite_telefono', $telefono );
        
        // Opcional: Respaldar en 'billing_phone' por compatibilidad con código Legacy
        update_user_meta( $user_id, 'billing_phone', $telefono );

        return $user_id;
    }

    /**
     * Elimina a un empleado del sistema.
     *
     * @param int $id ID del usuario a eliminar.
     * @param int|null $reassign ID de otro usuario al que se le reasignarán los posts/datos (opcional).
     * @return true|WP_Error
     */
    public function delete_employee( $id, $reassign = null ) {
        $id = intval( $id );

        // Seguridad: Evitar auto-eliminación
        if ( $id === get_current_user_id() ) {
            return new WP_Error( 'self_deletion', 'Seguridad: No puedes eliminar tu propia cuenta activa.' );
        }

        // wp_delete_user requiere incluir manualmente el archivo de administración de usuarios en el frontend o AJAX
        if ( ! function_exists( 'wp_delete_user' ) ) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }

        $deleted = wp_delete_user( $id, $reassign );

        if ( $deleted ) {
            return true;
        }

        return new WP_Error( 'delete_failed', 'Fallo interno al intentar eliminar al empleado.' );
    }

}