<?php
/**
 * Modelo de Base de Datos: Cotizaciones y Pedidos
 *
 * Maneja transacciones de ventas, Kanban y reglas de inmutabilidad.
 *
 * @package SuiteEmpleados\Models
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Suite_Model_Quote extends Suite_Model_Base {

    protected function set_table_name() {
        return 'suite_cotizaciones';
    }

    /**
     * Crea una cotización completa usando Transacciones SQL Seguras.
     */
    public function create_quote( $client_data, $items, $meta ) {
        // 1. Iniciar Transacción
        $this->wpdb->query( 'START TRANSACTION' );

        try {
            // --- GESTIÓN DEL CLIENTE ---
            $client_model = new Suite_Model_Client();
            
            $rif_limpio = strtoupper( preg_replace( '/[^A-Z0-9]/', '', $client_data['rif_ci'] ) );
            if ( empty( $rif_limpio ) ) throw new Exception( 'El RIF proporcionado no es válido.' );
            $client_data['rif_ci'] = $rif_limpio;

            $existing_client = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT id FROM {$this->wpdb->prefix}suite_clientes WHERE rif_ci = %s", $rif_limpio ) );

            $cliente_id = 0;
            if ( $existing_client ) {
                $cliente_id = $existing_client->id;
            } else {
                $cliente_id = $client_model->insert( $client_data );
                if ( ! $cliente_id ) throw new Exception( 'Fallo al registrar el nuevo cliente.' );
            }

            // --- GENERACIÓN DE CÓDIGO ---
            $hoy_start = date( 'Y-m-d 00:00:00' ); 
            $hoy_end   = date( 'Y-m-d 23:59:59' );
            $count = $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name} WHERE fecha_emision BETWEEN '$hoy_start' AND '$hoy_end'" );
            $codigo = date( 'Y' ) . str_pad( $count + 1, 2, '0', STR_PAD_LEFT ) . date( 'd' ) . date( 'm' );

            // --- INSERCIÓN CABECERA ---
            $quote_id = $this->insert( [
                'codigo_cotizacion' => $codigo,
                'cliente_id'        => $cliente_id,
                'cliente_nombre'    => $client_data['nombre_razon'],
                'cliente_rif'       => $rif_limpio,
                'direccion_entrega' => $client_data['direccion'],
                'vendedor_id'       => $meta['vendedor_id'],
                'tasa_bcv'          => floatval( $meta['tasa'] ),
                'validez_dias'      => intval( $meta['validez'] ),
                'moneda'            => sanitize_text_field( $meta['moneda'] ),
                'total_usd'         => 0,
                'fecha_emision'     => current_time( 'mysql' ),
                'estado'            => 'emitida'
            ] );

            if ( ! $quote_id ) throw new Exception( 'Fallo al generar la cabecera.' );

            // --- INSERCIÓN DETALLE ---
            $total_usd = 0;
            $table_items = $this->wpdb->prefix . 'suite_cotizaciones_items';
            
            foreach ( $items as $item ) {
                $sub = intval( $item['qty'] ) * floatval( $item['price'] );
                $total_usd += $sub;

                $this->wpdb->insert( $table_items, [
                    'cotizacion_id'       => $quote_id,
                    'sku'                 => sanitize_text_field( $item['sku'] ),
                    'producto_nombre'     => sanitize_text_field( $item['name'] ),
                    'cantidad'            => intval( $item['qty'] ),
                    'precio_unitario_usd' => floatval( $item['price'] ),
                    'tiempo_entrega'      => isset( $item['time'] ) ? sanitize_text_field( $item['time'] ) : 'Inmediata',
                    'subtotal_usd'        => $sub
                ] );
            }

            // --- ACTUALIZAR TOTALES ---
            $this->update( $quote_id, [
                'total_usd' => $total_usd,
                'total_bs'  => $total_usd * floatval( $meta['tasa'] )
            ] );

            $this->wpdb->query( 'COMMIT' );
            return [ 'id' => $codigo, 'internal_id' => $quote_id ];

        } catch ( Exception $e ) {
            $this->wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'db_error', $e->getMessage() );
        }
    }

    /**
     * Obtiene el historial de cotizaciones (Tabla)
     */
    public function get_vendor_history( $user_id, $limit = 50, $is_admin = false ) {
        $tabla_cli = $this->wpdb->prefix . 'suite_clientes';
        $sql = "SELECT c.*, cli.telefono as cliente_telefono, cli.nombre_razon as cliente_nombre_real 
                FROM {$this->table_name} c 
                LEFT JOIN {$tabla_cli} cli ON c.cliente_id = cli.id";
        
        if ( ! $is_admin ) $sql .= $this->wpdb->prepare( " WHERE c.vendedor_id = %d", $user_id );
        
        $sql .= $this->wpdb->prepare( " ORDER BY c.id DESC LIMIT %d", $limit );
        return $this->wpdb->get_results( $sql );
    }

    /**
     * Obtiene los pedidos agrupados por estado (Kanban)
     */
    public function get_kanban_orders( $vendedor_id = null, $is_admin = false ) {
        $tabla_cli = $this->wpdb->prefix . 'suite_clientes';
        
        $sql = "SELECT c.id, c.codigo_cotizacion, c.total_usd, c.fecha_emision, c.estado, c.vendedor_id, cli.nombre_razon AS cliente_nombre 
                FROM {$this->table_name} c 
                LEFT JOIN {$tabla_cli} cli ON c.cliente_id = cli.id";
        
        if ( ! $is_admin && $vendedor_id ) $sql .= $this->wpdb->prepare( " WHERE c.vendedor_id = %d", intval( $vendedor_id ) );
        $sql .= " ORDER BY c.fecha_emision DESC LIMIT 200";
        
        $resultados = $this->wpdb->get_results( $sql );
        
        $kanban_data = [ 'emitida' => [], 'proceso' => [], 'pagado' => [], 'despachado' => [] ];
        
        foreach ( $resultados as $row ) {
            $estado = empty( $row->estado ) ? 'emitida' : strtolower( $row->estado );
            if ( ! isset( $kanban_data[ $estado ] ) ) $kanban_data[ $estado ] = [];
            
            $row->total_fmt = number_format( floatval( $row->total_usd ), 2 );
            $row->fecha_fmt = date( 'd/m', strtotime( $row->fecha_emision ) );
            $kanban_data[ $estado ][] = $row;
        }
        
        return $kanban_data;
    }

    /**
     * MÓDULO 2 Y 3: Inmutabilidad y Descuento de Inventario
     * Actualiza el estado del pedido y ejecuta reglas de negocio financieras y logísticas.
     */
    public function update_order_status( $quote_id, $new_status ) {
        $new_status = strtolower( sanitize_text_field( $new_status ) );
        
        // 1. Obtener estado actual del pedido
        $current_order = $this->get( $quote_id );
        if ( ! $current_order ) {
            return new WP_Error( 'not_found', 'El pedido no existe.' );
        }

        $current_status = strtolower( $current_order->estado );
        $protected_statuses = [ 'pagado', 'despachado' ];

        // 2. CANDADO DE INMUTABILIDAD (Módulo 2)
        // Si el pedido ya está pagado/despachado, solo el Admin puede regresarlo a pendiente.
        $is_admin = current_user_can( 'manage_options' );
        if ( in_array( $current_status, $protected_statuses ) && ! $is_admin ) {
            return new WP_Error( 'immutable_lock', 'Candado de Inmutabilidad: Este pedido ya ha sido procesado (Pagado/Enviado) y no puede ser modificado por su nivel de acceso.' );
        }

        // 3. Actualizar el estado en Base de Datos
        $updated = $this->update( $quote_id, [ 'estado' => $new_status ] );

        if ( ! $updated ) {
            return false;
        }

        // 4. DESCUENTO DE INVENTARIO (Módulo 3)
        // Regla: Descontar SOLO si pasa a 'pagado' o 'despachado', y NO estaba ya en uno de esos estados (evita doble descuento).
        if ( in_array( $new_status, $protected_statuses ) && ! in_array( $current_status, $protected_statuses ) ) {
            $this->process_inventory_discount( $quote_id );
        }

        return true;
    }

    /**
     * MÓDULO 3: Helper para el descuento de inventario.
     */
    private function process_inventory_discount( $quote_id ) {
        // 1. Obtener los ítems de este pedido
        $table_items = $this->wpdb->prefix . 'suite_cotizaciones_items';
        $items = $this->wpdb->get_results( $this->wpdb->prepare(
            "SELECT sku, cantidad FROM {$table_items} WHERE cotizacion_id = %d",
            $quote_id
        ), ARRAY_A ); // Retornamos ARRAY asociativo para que coincida con el modelo de Inventario

        if ( empty( $items ) ) return;

        // 2. Instanciar el modelo de Inventario y descontar
        $inventory_model = new Suite_Model_Inventory();
        $inventory_model->discount_stock( $items );
    }
}