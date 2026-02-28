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
     * Crea una cotización completa usando Transacciones SQL Seguras y 
     * validación cruzada de precios para evitar vulnerabilidades.
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
                'total_usd'         => 0, // Se actualizará luego del loop
                'fecha_emision'     => current_time( 'mysql' ),
                'estado'            => 'emitida'
            ] );

            if ( ! $quote_id ) throw new Exception( 'Fallo al generar la cabecera.' );

            // --- INSERCIÓN DETALLE (Optimización N+1) ---
            $total_usd = 0;
            $table_items = $this->wpdb->prefix . 'suite_cotizaciones_items';
            $table_inv   = $this->wpdb->prefix . 'suite_inventario_cache';

            // 1. Pre-cargar todos los precios en 1 sola consulta
            $skus_a_buscar = [];
            foreach ( $items as $item ) {
                if ( ! in_array( strtoupper( $item['sku'] ), ['MANUAL', 'GENERICO'] ) ) {
                    $skus_a_buscar[] = sanitize_text_field( $item['sku'] );
                }
            }
            
            $precios_db = [];
            if ( ! empty( $skus_a_buscar ) ) {
                $placeholders = implode( ',', array_fill( 0, count( $skus_a_buscar ), '%s' ) );
                $sql_precios = $this->wpdb->prepare( "SELECT sku, precio FROM {$table_inv} WHERE sku IN ($placeholders)", ...$skus_a_buscar );
                $resultados_precios = $this->wpdb->get_results( $sql_precios );
                foreach ( $resultados_precios as $rp ) {
                    $precios_db[ strtoupper($rp->sku) ] = floatval( $rp->precio );
                }
            }

            // 2. Procesar inserciones en memoria
			foreach ( $items as $item ) {
                $sku = sanitize_text_field( $item['sku'] );
                $qty = intval( $item['qty'] );
                $safe_price = floatval( $item['price'] ); // Precio introducido por el vendedor

                if ( ! in_array( strtoupper( $sku ), ['MANUAL', 'GENERICO'] ) ) {
                    if ( isset( $precios_db[ strtoupper($sku) ] ) ) {
                        
                        $precio_minimo_db = floatval( $precios_db[ strtoupper($sku) ] );
                        
                        // CANDADO INTELIGENTE: 
                        // Solo forzamos el precio de la BD si el vendedor lo puso más barato.
                        // Si el vendedor lo puso más caro, se respeta su $safe_price original.
                        if ( $safe_price < $precio_minimo_db ) {
                            $safe_price = $precio_minimo_db;
                        }

                    } else {
                        throw new Exception( "El producto con SKU '{$sku}' no existe en el catálogo." );
                    }
                }

                $sub = $qty * $safe_price;
                $total_usd += $sub;

                $this->wpdb->insert( $table_items, [
                    'cotizacion_id'       => $quote_id,
                    'sku'                 => $sku,
                    'producto_nombre'     => sanitize_text_field( $item['name'] ),
                    'cantidad'            => $qty,
                    'precio_unitario_usd' => $safe_price,
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
        // --- INICIO: REGLA 3 ZERO-TRUST (Visibilidad del Kanban) ---
        $user = wp_get_current_user();
        $roles = (array) $user->roles;
        
        $is_admin_real = current_user_can( 'manage_options' );
        $is_gerente = in_array( 'suite_gerente', $roles ) || in_array( 'gerente', $roles );
        $is_logistica = in_array( 'suite_logistica', $roles );
        
        // El acceso global se otorga si tiene el rol gerencial/logístico, o si el parámetro legacy es true
        $tiene_acceso_global = ( $is_admin_real || $is_gerente || $is_logistica || $is_admin );
        $current_vendedor_id = get_current_user_id();
        // --- FIN: ZERO-TRUST ---

        $tabla_cli = $this->wpdb->prefix . 'suite_clientes';
        
        $sql = "SELECT c.id, c.codigo_cotizacion, c.total_usd, c.fecha_emision, c.estado, c.vendedor_id, cli.nombre_razon AS cliente_nombre 
                FROM {$this->table_name} c
                LEFT JOIN {$tabla_cli} cli ON c.cliente_id = cli.id
                WHERE 1=1";

        // 1. FILTRO DINÁMICO: Ocultar cotizaciones vencidas del Kanban (fecha_emision + validez < hoy)
        $sql .= " AND NOT (c.estado = 'emitida' AND DATE_ADD(c.fecha_emision, INTERVAL c.validez_dias DAY) < NOW())";

        // APLICACIÓN RLS: Si no tiene acceso global, forzamos a que solo vea lo suyo
        if ( ! $tiene_acceso_global ) {
            $sql .= $this->wpdb->prepare( " AND c.vendedor_id = %d", intval( $current_vendedor_id ) );
        } elseif ( $vendedor_id && $vendedor_id != $current_vendedor_id ) {
            // CORRECCIÓN BYPASS: Si es gerente/admin, aplicamos filtro específico si se solicitó
            $sql .= $this->wpdb->prepare( " AND c.vendedor_id = %d", intval( $vendedor_id ) );
        }
        $sql .= " ORDER BY c.fecha_emision DESC LIMIT 200";
        
        $resultados = $this->wpdb->get_results( $sql );
        
        // AGREGADO: Únicamente la llave 'por_enviar' (Protegido de tu versión original)
        $kanban_data = [ 'emitida' => [], 'proceso' => [], 'pagado' => [], 'por_enviar' => [], 'despachado' => [] ];
        
        foreach ( $resultados as $row ) {
            $estado = empty( $row->estado ) ? 'emitida' : strtolower( $row->estado );
            if ( ! isset( $kanban_data[ $estado ] ) ) $kanban_data[ $estado ] = [];

            $row->total_fmt = number_format( floatval( $row->total_usd ), 2 );
            $row->fecha_fmt = date( 'd/m', strtotime( $row->fecha_emision ) );

            // PREVENCIÓN XSS: Escapar variables sensibles antes de enviarlas al JSON de Vue/Vanilla JS
            $row->cliente_nombre    = esc_html( $row->cliente_nombre );
            $row->codigo_cotizacion = esc_html( $row->codigo_cotizacion );

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
        $is_admin = current_user_can( 'manage_options' );
        if ( in_array( $current_status, $protected_statuses ) && ! $is_admin ) {
            return new WP_Error( 'immutable_lock', 'Candado de Inmutabilidad: Este pedido ya ha sido procesado (Pagado/Enviado) y no puede ser modificado por su nivel de acceso.' );
        }

        // 3. Actualizar el estado en Base de Datos de forma ATÓMICA (Bloqueo Optimista)
        $sql_update = $this->wpdb->prepare(
            "UPDATE {$this->table_name} SET estado = %s WHERE id = %d AND estado = %s",
            $new_status, intval($quote_id), $current_status
        );
        $filas_afectadas = $this->wpdb->query( $sql_update );

        // Si filas_afectadas es 0, otro proceso ya cambió el estado en este milisegundo (Race Condition)
        if ( ! $filas_afectadas ) {
            return new WP_Error( 'race_condition', 'Conflicto de concurrencia: El pedido ya fue procesado por otro usuario.' );
        }

        // 4. DESCUENTO DE INVENTARIO (Módulo 3)
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
        ), ARRAY_A );

        if ( empty( $items ) ) return;

        // 2. Instanciar el modelo de Inventario y descontar
        $inventory_model = new Suite_Model_Inventory();
        $inventory_model->discount_stock( $items );
    }
}