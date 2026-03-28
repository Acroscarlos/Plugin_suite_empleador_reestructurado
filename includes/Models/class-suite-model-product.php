<?php
/**
 * Modelo de Base de Datos: Productos y Precios
 *
 * Extrae la metadata de WooCommerce y la cruza con los precios (precios.csv)
 * y el inventario en tiempo real (reporte_final.csv).
 *
 * @package SuiteEmpleados\Models
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Suite_Model_Product {

    public function get_products_with_csv_prices( $term = '' ) {
        global $wpdb;

        // 1. CONSULTA SQL OPTIMIZADA A WOOCOMMERCE
        $sql = "SELECT p.ID, p.post_title AS nombre, pm.meta_value AS sku
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_sku'
                WHERE p.post_type = 'product' AND p.post_status = 'publish'";

        if ( ! empty( $term ) ) {
            $like = '%' . $wpdb->esc_like( $term ) . '%';
            $sql .= $wpdb->prepare( " AND (p.post_title LIKE %s OR pm.meta_value LIKE %s)", $like, $like );
        }

        $sql .= " LIMIT 50"; // Límite de seguridad
        $woo_products = $wpdb->get_results( $sql );

        if ( empty( $woo_products ) ) {
            return [];
        }

        // 2. PROCESAMIENTO DE LA MATRIZ UNIFICADA
        $csv_data = [];
        $csv_path = SUITE_PATH . 'output/Matriz_unificada_Woocommerce.csv';

        if ( file_exists( $csv_path ) && ( $handle = fopen( $csv_path, 'r' ) ) !== false ) {
            fgetcsv( $handle, 1000, ',' ); // Saltamos la cabecera

            while ( ( $data = fgetcsv( $handle, 1000, ',' ) ) !== false ) {
                $sku = isset( $data[0] ) ? trim( $data[0] ) : '';

                if ( ! empty( $sku ) ) {
                    // Precio (Columna 2 = índice 2 en array PHP)
                    $precio_raw = isset( $data[2] ) ? trim( $data[2] ) : '0';
                    $precio_raw = str_replace( '"', '', $precio_raw ); 
                    $precio_float = floatval( $precio_raw );

                    // Stocks (Columna 5 = índice 5, Columna 6 = índice 6)
                    $stock_mille = isset( $data[5] ) ? floatval( $data[5] ) : 0;
                    $stock_gale  = isset( $data[6] ) ? floatval( $data[6] ) : 0;
                    
                    // Cálculo al vuelo del Stock Total
                    $stock_total = $stock_mille + $stock_gale;

                    $csv_data[ strtoupper( $sku ) ] = [
                        'precio' => $precio_float,
                        'total'  => intval( $stock_total ),
                        'gale'   => intval( $stock_gale )
                    ];
                }
            }
            fclose( $handle );
        }

        // 3. EL CRUCE EN MEMORIA (MERGE FINAL)
        $final_products = [];

        foreach ( $woo_products as $prod ) {
            $sku_clean = ! empty( $prod->sku ) ? strtoupper( trim( $prod->sku ) ) : '';

            // Regla de Negocio: Extraídas de la matriz unificada en un solo paso
            $precio      = ( $sku_clean && isset( $csv_data[ $sku_clean ] ) ) ? $csv_data[ $sku_clean ]['precio'] : 0.00;
            $stock_total = ( $sku_clean && isset( $csv_data[ $sku_clean ] ) ) ? $csv_data[ $sku_clean ]['total'] : 'N/D';
            $stock_gale  = ( $sku_clean && isset( $csv_data[ $sku_clean ] ) ) ? $csv_data[ $sku_clean ]['gale']  : 'N/D';

            $final_products[] = [
                'id'          => $prod->ID,
                'nombre'      => esc_html( $prod->nombre ),
                'sku'         => $sku_clean ? $sku_clean : 'N/A',
                'precio'      => number_format( $precio, 2, '.', '' ),
                'stock_gale'  => (string) $stock_gale,
                'stock_total' => (string) $stock_total
            ];
        }

        return $final_products;
    }
}