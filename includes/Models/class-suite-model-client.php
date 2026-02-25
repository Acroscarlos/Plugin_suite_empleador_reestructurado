<?php
/**
 * Modelo de Base de Datos: Clientes (CRM)
 *
 * Maneja las consultas específicas de la tabla suite_clientes.
 * Hereda de Suite_Model_Base los métodos genéricos (get, get_all, insert, update, delete).
 *
 * @package SuiteEmpleados\Models
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Suite_Model_Client extends Suite_Model_Base {

    /**
     * Define el nombre de la tabla sin el prefijo wp_
     * (Requerido obligatoriamente por la clase abstracta Suite_Model_Base)
     *
     * @return string
     */
    protected function set_table_name() {
        // Al retornar esto, la clase base armará: wp_suite_clientes [4]
        return 'suite_clientes';
    }

    /**
     * Busca clientes por Nombre/Razón Social o por RIF/CI.
     * Aplica la limpieza híbrida de caracteres para evitar fallos por guiones.
     *
     * @param string $term Término de búsqueda introducido por el vendedor.
     * @return array Array de objetos con los resultados (Límite 10).
     */
    public function search_clients( $term ) {
        // 1. Limpiamos el término para la búsqueda de RIF (ej. "J-505" -> "J505") [1, 5]
        $term_clean = strtoupper( preg_replace( '/[^A-Z0-9]/', '', $term ) );
        
        // 2. Mantenemos el original para buscar por Razón Social [1]
        $term_name  = $term;

        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name}
            WHERE nombre_razon LIKE %s
            OR rif_ci LIKE %s
            LIMIT 10",
            '%' . $this->wpdb->esc_like( $term_name ) . '%',
            '%' . $this->wpdb->esc_like( $term_clean ) . '%'
        );

        return $this->wpdb->get_results( $sql );
    }

    /**
     * Obtiene los KPIs y estadísticas de compras de un cliente específico.
     * Cruza la información con la tabla wp_suite_cotizaciones.
     *
     * @param int $client_id ID del cliente en la base de datos.
     * @return object|null Objeto con los totales (total, count, first, last).
     */
    public function get_client_stats( $client_id ) {
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

        return $this->wpdb->get_row( $sql );
    }

    /**
     * Obtiene el historial reciente de cotizaciones/compras de un cliente.
     *
     * @param int $client_id ID del cliente en la base de datos.
     * @return array Array de objetos con las últimas 10 cotizaciones.
     */
    public function get_client_history( $client_id ) {
        $tabla_cotizaciones = $this->wpdb->prefix . 'suite_cotizaciones';

        $sql = $this->wpdb->prepare( 
            "SELECT 
                id, 
                codigo_cotizacion as codigo, 
                fecha_emision as fecha, 
                total_usd as total, 
                estado 
            FROM {$tabla_cotizaciones} 
            WHERE cliente_id = %d 
            ORDER BY id DESC 
            LIMIT 10", 
            intval( $client_id ) 
        );

        return $this->wpdb->get_results( $sql );
    }

}