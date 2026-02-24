<?php
/**
 * Modelo de Base de Datos: Inventario y Logística (Módulo 3)
 *
 * Maneja las consultas de la tabla suite_inventario_cache.
 * Provee métodos para el descuento de stock y reservas temporales.
 *
 * @package SuiteEmpleados\Models
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Suite_Model_Inventory extends Suite_Model_Base {

    /**
     * Define el nombre de la tabla (sin el prefijo wp_)
     */
    protected function set_table_name() {
        return 'suite_inventario_cache';
    }

    /**
     * Descuenta el stock físico de los productos vendidos.
     * Por defecto, descuenta del almacén principal (stock_gale).
     *
     * @param array $items Array asociativo con 'sku' y 'cantidad'
     * @return void
     */
    public function discount_stock( $items ) {
        if ( empty( $items ) || ! is_array( $items ) ) {
            return;
        }

        foreach ( $items as $item ) {
            $sku = isset( $item['sku'] ) ? sanitize_text_field( $item['sku'] ) : '';
            $qty = isset( $item['cantidad'] ) ? intval( $item['cantidad'] ) : 0;

            // Ignorar productos manuales o genéricos que no están en el catálogo
            if ( empty( $sku ) || $qty <= 0 || in_array( strtoupper( $sku ), ['MANUAL', 'GENERICO'] ) ) {
                continue;
            }

            // Consulta directa para restar el stock atómicamente a nivel de base de datos
            // Esto previene condiciones de carrera si dos usuarios compran al mismo exacto milisegundo
            $sql = $this->wpdb->prepare(
                "UPDATE {$this->table_name} 
                 SET stock_gale = stock_gale - %d 
                 WHERE sku = %s",
                $qty,
                $sku
            );

            $this->wpdb->query( $sql );
            
            // Log de auditoría (Opcional, si tienes suite_record_log global)
            if ( function_exists('suite_record_log') ) {
                suite_record_log('descuento_inventario', "Se descontaron {$qty} unidades del SKU: {$sku}");
            }
        }
    }

    /**
     * Reserva stock temporalmente (Hard Commit) para clientes "En Espera de Pago".
     * TODO: [Módulo 3 - Logística] Requiere la creación de la tabla wp_suite_reservas.
     *
     * @param string $sku   El código del producto
     * @param int    $qty   Cantidad a reservar
     * @param int    $hours Tiempo de vida de la reserva en horas
     * @return bool
     */
    public function reserve_stock_temporal( $sku, $qty, $hours = 24 ) {
        /*
        $expiration = date('Y-m-d H:i:s', strtotime("+{$hours} hours"));
        
        return $this->wpdb->insert( $this->wpdb->prefix . 'suite_reservas', [
            'sku'        => sanitize_text_field($sku),
            'cantidad'   => intval($qty),
            'expira_en'  => $expiration
        ] );
        */
        
        return true; // Placeholder hasta construir la tabla
    }
}
