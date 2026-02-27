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

        // 2. PROCESAMIENTO DEL CSV DE PRECIOS
        $csv_prices = [];
        $csv_path_precios = SUITE_PATH . 'output/precios.csv';

        if ( file_exists( $csv_path_precios ) && ( $handle = fopen( $csv_path_precios, 'r' ) ) !== false ) {
            fgetcsv( $handle, 1000, ',' ); // Saltamos la cabecera

            while ( ( $data = fgetcsv( $handle, 1000, ',' ) ) !== false ) {
                $sku = isset( $data[0] ) ? trim( $data[0] ) : '';
                
                // Limpieza numérica estricta (Convierte "12.018,00" -> 12018.00)
                $precio_raw = isset( $data[1] ) ? trim( $data[1] ) : '0';
                $precio_raw = str_replace( '"', '', $precio_raw ); 
                $precio_raw = str_replace( '.', '', $precio_raw ); 
                $precio_raw = str_replace( ',', '.', $precio_raw ); 
                $precio_float = floatval( $precio_raw );

                if ( ! empty( $sku ) ) {
                    $csv_prices[ strtoupper( $sku ) ] = $precio_float;
                }
            }
            fclose( $handle );
        }

        // 3. PROCESAMIENTO DEL CSV DE INVENTARIO (reporte_final.csv)
        $csv_inventory = [];
        $csv_path_inventario = SUITE_PATH . 'output/reporte_final.csv';

        if ( file_exists( $csv_path_inventario ) && ( $handle2 = fopen( $csv_path_inventario, 'r' ) ) !== false ) {
            fgetcsv( $handle2, 1000, ',' ); // Saltamos la cabecera

            while ( ( $data2 = fgetcsv( $handle2, 1000, ',' ) ) !== false ) {
                $sku = isset( $data2[0] ) ? trim( $data2[0] ) : '';
                
                // Extraemos Stock Total (Columna 3) y Disponibilidad Galerías (Columna 8)
                $stock_total = isset( $data2[3] ) ? trim( $data2[3] ) : '0';
                $stock_gale  = isset( $data2[8] ) ? trim( $data2[8] ) : '0';

                if ( ! empty( $sku ) ) {
                    // Usamos intval(floatval()) para manejar casos donde el CSV diga "20.0" limpiándolo a "20"
                    $csv_inventory[ strtoupper( $sku ) ] = [
                        'total' => intval( floatval( $stock_total ) ),
                        'gale'  => intval( floatval( $stock_gale ) )
                    ];
                }
            }
            fclose( $handle2 );
        }

        // 4. EL CRUCE TRIPLE (MERGE) EN MEMORIA
        $final_products = [];

        foreach ( $woo_products as $prod ) {
            $sku_clean = ! empty( $prod->sku ) ? strtoupper( trim( $prod->sku ) ) : '';

            // Regla de Negocio de Precios: Si existe en CSV usar precio, si no, es 0.00
            $precio = ( $sku_clean && isset( $csv_prices[ $sku_clean ] ) ) ? $csv_prices[ $sku_clean ] : 0.00;

            // Regla de Negocio de Inventario: Si existe el SKU extraemos stocks, sino 'N/D'
            $stock_total = ( $sku_clean && isset( $csv_inventory[ $sku_clean ] ) ) ? $csv_inventory[ $sku_clean ]['total'] : 'N/D';
            $stock_gale  = ( $sku_clean && isset( $csv_inventory[ $sku_clean ] ) ) ? $csv_inventory[ $sku_clean ]['gale']  : 'N/D';

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