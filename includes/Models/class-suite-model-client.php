<?php
/**
 * Modelo de Base de Datos: Clientes (CRM)
 *
 * Maneja las consultas específicas de la tabla suite_clientes.
 * Hereda de Suite_Model_Base los métodos genéricos.
 * ACTUALIZADO: Módulo 1 (Zero-Trust) y Anti-Secuestro de Cartera.
 *
 * @package SuiteEmpleados\Models
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Suite_Model_Client extends Suite_Model_Base {

    protected function set_table_name() {
        return 'suite_clientes';
    }

    /**
     * Override del método Base: Devuelve clientes respetando la seguridad RLS
     */
    public function get_all( $limit = 100, $offset = 0 ) {
        $user = wp_get_current_user();
        $is_admin = current_user_can('manage_options');
        $is_gerente = in_array('suite_gerente', (array)$user->roles) || in_array('gerente', (array)$user->roles);
        
        $sql = "SELECT * FROM {$this->table_name}";
        
        // Zero-Trust: Filtro estricto si es vendedor regular
        if ( ! $is_admin && ! $is_gerente ) {
            $sql .= $this->wpdb->prepare(" WHERE vendedor_id = %d", get_current_user_id());
        }
        
        $sql .= $this->wpdb->prepare(" ORDER BY id DESC LIMIT %d OFFSET %d", intval($limit), intval($offset));
        return $this->wpdb->get_results( $sql );
    }

    /**
     * Busca clientes por Nombre/Razón Social o por RIF/CI con Seguridad RLS.
     */
    public function search_clients( $term ) {
        $user = wp_get_current_user();
        $is_admin = current_user_can('manage_options');
        $is_gerente = in_array('suite_gerente', (array)$user->roles) || in_array('gerente', (array)$user->roles);
        
        $term_clean = strtoupper( preg_replace( '/[^A-Z0-9]/', '', $term ) );
        $term_name = $term;

        // Construcción SQL Base con paréntesis para aislar el OR
        $sql = "SELECT * FROM {$this->table_name} WHERE (nombre_razon LIKE %s OR rif_ci LIKE %s)";
        
        // Zero-Trust: El vendedor solo puede buscar dentro de sus propios clientes
        if ( ! $is_admin && ! $is_gerente ) {
            $sql .= $this->wpdb->prepare(" AND vendedor_id = %d", get_current_user_id());
        }
        
        $sql .= " LIMIT 10";

        // Preparar y ejecutar
        $prepared_sql = $this->wpdb->prepare(
            $sql, 
            '%' . $this->wpdb->esc_like( $term_name ) . '%', 
            '%' . $this->wpdb->esc_like( $term_clean ) . '%'
        );

        return $this->wpdb->get_results( $prepared_sql );
    }

    /**
     * Obtiene los KPIs y estadísticas de compras de un cliente específico.
     * ACTUALIZADO: Solo contabiliza las compras hechas con EL VENDEDOR ACTUAL (RLS).
     */
    public function get_client_stats( $client_id ) {
        $user = wp_get_current_user();
        $is_admin = current_user_can('manage_options');
        $is_gerente = in_array('suite_gerente', (array)$user->roles) || in_array('gerente', (array)$user->roles);

        $tabla_cotizaciones = $this->wpdb->prefix . 'suite_cotizaciones';

        $sql = $this->wpdb->prepare( 
            "SELECT 
                SUM(total_usd) as total, 
                COUNT(id) as count, 
                MIN(fecha_emision) as first, 
                MAX(fecha_emision) as last 
            FROM {$tabla_cotizaciones} 
            WHERE cliente_id = %d", 
            intval( $client_id ) 
        );

        // Zero-Trust
        if ( ! $is_admin && ! $is_gerente ) {
            $sql .= $this->wpdb->prepare(" AND vendedor_id = %d", get_current_user_id());
        }

        return $this->wpdb->get_row( $sql );
    }

    /**
     * Historial RLS: Un vendedor solo ve las compras que ESE cliente hizo con ÉL.
     */
    public function get_client_history( $client_id ) {
        $user = wp_get_current_user();
        $is_admin = current_user_can('manage_options');
        $is_gerente = in_array('suite_gerente', (array)$user->roles) || in_array('gerente', (array)$user->roles);
        
        $tabla_cotizaciones = $this->wpdb->prefix . 'suite_cotizaciones';
        
        $sql = $this->wpdb->prepare(
            "SELECT id, codigo_cotizacion as codigo, fecha_emision as fecha, total_usd as total, estado 
             FROM {$tabla_cotizaciones} 
             WHERE cliente_id = %d", 
             intval($client_id)
        );
        
        // Zero-Trust
        if ( ! $is_admin && ! $is_gerente ) {
            $sql .= $this->wpdb->prepare(" AND vendedor_id = %d", get_current_user_id());
        }
        
        $sql .= " ORDER BY id DESC LIMIT 10";
        
        return $this->wpdb->get_results( $sql );
    }
}