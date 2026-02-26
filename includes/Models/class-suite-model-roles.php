<?php
/**
 * Modelo de Base de Datos: Gestor Dinámico de Roles y Permisos
 *
 * Actúa como un Wrapper (envoltura) de la clase global WP_Roles de WordPress.
 * Permite gestionar roles personalizados y asignar capacidades (Capabilities)
 * de forma dinámica para el ERP.
 *
 * @package SuiteEmpleados\Models
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Suite_Model_Roles {

    /**
     * Devuelve el diccionario maestro de capacidades del ERP agrupadas por módulos.
     * Este array alimenta la matriz de checkboxes en la interfaz de usuario.
     *
     * @return array
     */
    public function get_capabilities_dictionary() {
        return [
            'crm' => [
                'suite_view_crm'       => 'Ver Clientes y Cotizar',
                'suite_manage_clients' => 'Crear, Editar y Eliminar Clientes',
                'suite_change_status'  => 'Cambiar Estados (Kanban)'
            ],
            'logistica' => [
                'suite_view_logistics' => 'Ver Almacén y Despacho',
                'suite_print_picking'  => 'Imprimir Hoja de Picking',
                'suite_upload_pod'     => 'Subir Comprobante de Entrega (POD)'
            ],
            'admin' => [
                'suite_export_data'    => 'Exportar a Excel/CSV',
                'suite_view_marketing' => 'Ver Analíticas y Marketing (BI)',
                'suite_manage_team'    => 'Gestionar Empleados y Roles'
            ]
        ];
    }

    /**
     * Obtiene todos los roles registrados en el sistema WordPress.
     *
     * @return array Array de roles.
     */
    public function get_all_roles() {
        global $wp_roles;
        
        // Asegurar que la clase global esté instanciada
        if ( ! isset( $wp_roles ) ) {
            $wp_roles = wp_roles();
        }

        return $wp_roles->roles;
    }

    /**
     * Crea un rol nuevo o actualiza uno existente (Nombre y Capacidades).
     *
     * @param string $role_key Identificador único (slug) del rol (ej. 'vendedor_senior').
     * @param string $display_name Nombre público del rol (ej. 'Vendedor Senior').
     * @param array $capabilities_array Array simple con los keys de las capacidades activadas.
     * @return true|WP_Error
     */
    public function create_or_update_role( $role_key, $display_name, $capabilities_array ) {
        global $wp_roles;

        if ( ! isset( $wp_roles ) ) {
            $wp_roles = wp_roles();
        }

        $role_key     = sanitize_key( $role_key );
        $display_name = sanitize_text_field( $display_name );
        $role_obj     = get_role( $role_key );

        if ( ! $role_obj ) {
            // 1. CREACIÓN: El rol no existe, usar add_role
            $role_obj = add_role( $role_key, $display_name );
            if ( ! $role_obj ) {
                return new WP_Error( 'role_creation_failed', 'Error interno al intentar crear el rol.' );
            }
        } else {
            // 2. ACTUALIZACIÓN DE NOMBRE: WordPress no tiene función nativa para renombrar. 
            // Se debe actualizar directamente en el array global y guardar en la BD.
            $wp_roles->roles[ $role_key ]['name'] = $display_name;
            $wp_roles->role_names[ $role_key ]    = $display_name;
            update_option( $wp_roles->role_key, $wp_roles->roles );
        }

        // 3. LIMPIEZA DE CAPACIDADES: Revocar todas las capacidades específicas de la Suite
        // Esto garantiza que si el usuario desmarcó un checkbox, el permiso se elimine.
        $dictionary = $this->get_capabilities_dictionary();
        foreach ( $dictionary as $group => $caps ) {
            foreach ( $caps as $cap_key => $cap_label ) {
                $role_obj->remove_cap( $cap_key );
            }
        }

        // 4. ASIGNACIÓN: Otorgar las capacidades recibidas en el payload
        if ( is_array( $capabilities_array ) && ! empty( $capabilities_array ) ) {
            foreach ( $capabilities_array as $cap ) {
                $role_obj->add_cap( sanitize_key( $cap ) );
            }
        }

        // 5. PERMISOS BASE OBLIGATORIOS
        $role_obj->add_cap( 'read' );         // Necesario para entrar al wp-admin/intranet
        $role_obj->add_cap( 'suite_access' ); // Bandera general de pertenencia al ERP

        return true;
    }

    /**
     * Elimina un rol del sistema.
     * Posee una barrera de seguridad para no destruir roles críticos.
     *
     * @param string $role_key Identificador único del rol.
     * @return true|WP_Error
     */
    public function delete_role( $role_key ) {
        $role_key = sanitize_key( $role_key );

        // BARRERA DE SEGURIDAD: Prohibido borrar roles críticos
        $protected_roles = [ 'administrator', 'subscriber', 'editor', 'author', 'contributor' ];
        
        if ( in_array( $role_key, $protected_roles, true ) ) {
            return new WP_Error( 'protected_role', 'Seguridad: No se permite eliminar los roles nativos del núcleo de WordPress.' );
        }

        $role_obj = get_role( $role_key );
        
        if ( ! $role_obj ) {
            return new WP_Error( 'role_not_found', 'El rol que intenta eliminar no existe.' );
        }

        // TODO: (Opcional Arquitectónico) Verificar si existen usuarios con este rol antes de borrarlo
        // y pasarlos a un rol "subscriber" temporal.

        remove_role( $role_key );

        return true;
    }

}