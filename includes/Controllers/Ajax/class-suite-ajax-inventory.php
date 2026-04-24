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

        // 2. Mapeo Heurístico (Smart Indexing)
        $map = [];
        foreach ( $headers as $index => $header ) {
            $h_clean = strtolower( trim( $header ) );
            if ( strpos( $h_clean, 'sku' ) !== false ) $map['sku'] = $index;
            elseif ( strpos( $h_clean, 'nombre' ) !== false ) $map['nombre'] = $index;
            elseif ( strpos( $h_clean, 'precio_venta' ) !== false || $h_clean === 'precio' ) $map['precio_venta'] = $index;
            elseif ( strpos( $h_clean, 'divisa' ) !== false ) $map['precio_divisas'] = $index; 
			elseif ( strpos( $h_clean, 'velocidad' ) !== false ) $map['velocidad_venta'] = $index; 			
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
            
            // ¡Cálculo de Stock Total al vuelo! 
            $stock_total = $disp_gale + $disp_mille; 
            
            // Precios
            $precio_raw = isset($map['precio_venta'], $row[$map['precio_venta']]) ? trim($row[$map['precio_venta']]) : '0';
            $precio_float = floatval( str_replace( '"', '', $precio_raw ) );

            // Extracción de Divisas
            $divisa_raw = isset($map['precio_divisas'], $row[$map['precio_divisas']]) ? trim($row[$map['precio_divisas']]) : '0';
            $divisa_float = floatval( str_replace( '"', '', $divisa_raw ) );

            // Extracción de Velocidad y Cálculo de Autonomía (NUEVO)
            $velocidad_raw = isset($map['velocidad_venta'], $row[$map['velocidad_venta']]) ? floatval($row[$map['velocidad_venta']]) : 0;
            
            // Protección matemática: Evitar división por cero si la velocidad es 0
            $runway_dias = ($velocidad_raw > 0) ? round($stock_total / $velocidad_raw) : 999; 

            $data[] = [
                'sku' => isset($map['sku'], $row[$map['sku']]) ? sanitize_text_field($row[$map['sku']]) : 'N/D',
                'nombre' => isset($map['nombre'], $row[$map['nombre']]) ? sanitize_text_field($row[$map['nombre']]) : 'N/D',
                'precio_venta' => $precio_float,
                'precio_divisas' => $divisa_float, 
                'velocidad_venta' => $velocidad_raw, // <--- Dato extraído del CSV
                'runway_dias' => $runway_dias,       // <--- Indicador de Inteligencia calculado
                'status' => isset($map['status_prediccion'], $row[$map['status_prediccion']]) ? sanitize_text_field($row[$map['status_prediccion']]) : '-',
                'disponibilidad_galerias' => $disp_gale,
                'disponibilidad_millennium' => $disp_mille,
                'stock_total' => $stock_total, 
                'inventario_entrante' => isset($map['inventario_entrante'], $row[$map['inventario_entrante']]) ? sanitize_text_field($row[$map['inventario_entrante']]) : 'No',
            ];
        }

        fclose( $handle );
        $this->send_success( $data );
    }
}