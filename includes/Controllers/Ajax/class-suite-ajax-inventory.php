<?php
/**
 * Controlador AJAX: Módulo de Inventario
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Suite_Ajax_Get_Inventory extends Suite_AJAX_Controller {

    protected $action_name = 'suite_get_inventory';
    protected $required_capability = 'read';

    protected function process() {
        // 1. Apuntamos a la nueva matriz unificada
        $csv_path = SUITE_PATH . 'output/Matriz_unificada_Woocommerce.csv';
        
        if ( ! file_exists( $csv_path ) || ( $handle = fopen( $csv_path, 'r' ) ) === false ) {
            $this->send_error( 'El archivo de inventario unificado no se encuentra disponible actualmente.' );
        }

        $headers = fgetcsv( $handle, 2000, ',' );
        if ( ! $headers ) {
            fclose( $handle );
            $this->send_error( 'El archivo CSV está vacío o corrupto.' );
        }

        // 2. Mapeo Heurístico (Smart Indexing) actualizado a las nuevas columnas
        $map = [];
        foreach ( $headers as $index => $header ) {
            $h_clean = strtolower( trim( $header ) );
            if ( strpos( $h_clean, 'sku' ) !== false ) $map['sku'] = $index;
            elseif ( strpos( $h_clean, 'nombre' ) !== false ) $map['nombre'] = $index;
            elseif ( strpos( $h_clean, 'precio' ) !== false ) $map['precio_venta'] = $index;
            elseif ( strpos( $h_clean, 'status' ) !== false ) $map['status_prediccion'] = $index;
            elseif ( strpos( $h_clean, 'entrante' ) !== false ) $map['inventario_entrante'] = $index;
            elseif ( strpos( $h_clean, 'gale' ) !== false ) $map['disponibilidad_galerias'] = $index;
            elseif ( strpos( $h_clean, 'mille' ) !== false ) $map['disponibilidad_millennium'] = $index;
        }

        $data = [];
        // 3. Procesamiento, Limpieza y Cálculo al Vuelo
        while ( ( $row = fgetcsv( $handle, 2000, ',' ) ) !== false ) {
            
            // Extraer disponibilidades asegurando que sean números
            $disp_gale = isset($map['disponibilidad_galerias'], $row[$map['disponibilidad_galerias']]) ? floatval($row[$map['disponibilidad_galerias']]) : 0;
            $disp_mille = isset($map['disponibilidad_millennium'], $row[$map['disponibilidad_millennium']]) ? floatval($row[$map['disponibilidad_millennium']]) : 0;
            
            // El precio de WooCommerce ya viene estandarizado (16274.95)
            $precio_raw = isset($map['precio_venta'], $row[$map['precio_venta']]) ? trim($row[$map['precio_venta']]) : '0';
            $precio_raw = str_replace( '"', '', $precio_raw ); // Solo quitamos comillas por seguridad
            $precio_float = floatval( $precio_raw );

            $data[] = [
                'sku' => isset($map['sku'], $row[$map['sku']]) ? sanitize_text_field($row[$map['sku']]) : 'N/D',
                'nombre' => isset($map['nombre'], $row[$map['nombre']]) ? sanitize_text_field($row[$map['nombre']]) : 'N/D',
                'precio_venta' => $precio_float,
                'status' => isset($map['status_prediccion'], $row[$map['status_prediccion']]) ? sanitize_text_field($row[$map['status_prediccion']]) : '-',
                'disponibilidad_galerias' => $disp_gale,
                'disponibilidad_millennium' => $disp_mille,
                // ¡Cálculo matemático al vuelo! 
                'stock_total' => $disp_gale + $disp_mille, 
                'inventario_entrante' => isset($map['inventario_entrante'], $row[$map['inventario_entrante']]) ? sanitize_text_field($row[$map['inventario_entrante']]) : 'No',
            ];
        }

        fclose( $handle );
        $this->send_success( $data );
    }
}