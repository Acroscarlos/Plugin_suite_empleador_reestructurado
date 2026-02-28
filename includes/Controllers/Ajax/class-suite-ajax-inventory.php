<?php
/**
 * Controlador AJAX: Módulo de Inventario
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Suite_Ajax_Get_Inventory extends Suite_AJAX_Controller {

    protected $action_name = 'suite_get_inventory';
    protected $required_capability = 'read';

    protected function process() {
        $csv_path = SUITE_PATH . 'output/reporte_final.csv';
        
        if ( ! file_exists( $csv_path ) || ( $handle = fopen( $csv_path, 'r' ) ) === false ) {
            $this->send_error( 'El archivo de inventario no se encuentra disponible actualmente.' );
        }

        $headers = fgetcsv( $handle, 2000, ',' );
        if ( ! $headers ) {
            fclose( $handle );
            $this->send_error( 'El archivo CSV está vacío o corrupto.' );
        }

        // 1. Mapeo Heurístico (Smart Indexing) para ubicar la posición de las columnas
        $map = [];
        foreach ( $headers as $index => $header ) {
            $h_clean = strtolower( trim( $header ) );
            if ( strpos( $h_clean, 'sku' ) !== false ) $map['sku'] = $index;
            elseif ( strpos( $h_clean, 'nombre' ) !== false ) $map['nombre'] = $index;
            elseif ( strpos( $h_clean, 'status' ) !== false ) $map['status'] = $index;
            elseif ( strpos( $h_clean, 'total' ) !== false ) $map['stock_total'] = $index;
            elseif ( strpos( $h_clean, 'gale' ) !== false ) $map['disponibilidad_galerias'] = $index;
            elseif ( strpos( $h_clean, 'mille' ) !== false ) $map['disponibilidad_millennium'] = $index;
            elseif ( strpos( $h_clean, 'transito' ) !== false ) $map['cantidad_en_transito'] = $index;
        }

        $data = [];
        // 2. Procesamiento y Limpieza de Datos
        while ( ( $row = fgetcsv( $handle, 2000, ',' ) ) !== false ) {
            $data[] = [
                'sku' => isset($map['sku'], $row[$map['sku']]) ? sanitize_text_field($row[$map['sku']]) : 'N/D',
                'nombre' => isset($map['nombre'], $row[$map['nombre']]) ? sanitize_text_field($row[$map['nombre']]) : 'N/D',
                'status' => isset($map['status'], $row[$map['status']]) ? sanitize_text_field($row[$map['status']]) : '-',
                'stock_total' => isset($map['stock_total'], $row[$map['stock_total']]) ? intval($row[$map['stock_total']]) : 0,
                'disponibilidad_galerias' => isset($map['disponibilidad_galerias'], $row[$map['disponibilidad_galerias']]) ? intval($row[$map['disponibilidad_galerias']]) : 0,
                'disponibilidad_millennium' => isset($map['disponibilidad_millennium'], $row[$map['disponibilidad_millennium']]) ? intval($row[$map['disponibilidad_millennium']]) : 0,
                'cantidad_en_transito' => isset($map['cantidad_en_transito'], $row[$map['cantidad_en_transito']]) ? intval($row[$map['cantidad_en_transito']]) : 0,
            ];
        }

        fclose( $handle );
        $this->send_success( $data );
    }
}