<?php
/**
 * Controlador AJAX: Cotizador y Venta (Módulo 2: Seguridad Aplicada)
 *
 * Contiene los manejadores para guardar cotizaciones (con control de precios mínimos),
 * consultar el historial y cambiar estados (con candado de inmutabilidad).
 *
 * @package SuiteEmpleados\Controllers\Ajax
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 1. Endpoint para Guardar Cotizaciones y Actualizar Clientes
 */
class Suite_Ajax_Quote_Save extends Suite_AJAX_Controller {

    protected $action_name = 'suite_save_quote_crm'; 
    protected $required_capability = 'read';

    protected function process() {
        // 1. Recibir datos del frontend
        $client_data = [
            'rif_ci'           => isset( $_POST['rif'] ) ? $_POST['rif'] : '',
            'nombre_razon'     => sanitize_text_field( $_POST['nombre'] ),
            'direccion'        => sanitize_textarea_field( $_POST['direccion'] ),
            'telefono'         => sanitize_text_field( $_POST['telefono'] ),
            'email'            => sanitize_email( $_POST['email'] ),
            'ciudad'           => sanitize_text_field( $_POST['ciudad'] ),
            'estado'           => sanitize_text_field( $_POST['estado'] ),
            'contacto_persona' => sanitize_text_field( $_POST['contacto'] ),
            'notas'            => sanitize_textarea_field( $_POST['notas'] )
        ];

        // 2. MODO ACTUALIZACIÓN DE PERFIL
        $is_update_only = isset( $_POST['is_update_only'] ) && $_POST['is_update_only'] == 'true';
        if ( $is_update_only ) {
            $client_id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
            if ( $client_id > 0 ) {
                $client_model = new Suite_Model_Client();
                $client_model->update( $client_id, $client_data );
                $this->send_success( [ 'message' => 'Perfil actualizado correctamente.' ] );
            }
            $this->send_error( 'ID de cliente inválido.' );
            return;
        }

        // 3. MODO CREAR COTIZACIÓN
        $items = isset( $_POST['items'] ) && is_array( $_POST['items'] ) ? $_POST['items'] : [];
        if ( empty( $items ) ) {
            $this->send_error( 'El carrito no puede estar vacío.' );
        }

        // ==============================================================================
        // MÓDULO 2: SEGURIDAD - CONTROL DE PRECIOS MÍNIMOS (MIDDLEWARE)
        // ==============================================================================
        global $wpdb;
        $tabla_inv = $wpdb->prefix . 'suite_inventario_cache';
        $is_admin  = current_user_can( 'manage_options' );

        foreach ( $items as $item ) {
            $sku = sanitize_text_field( $item['sku'] );
            
            // Ignorar productos genéricos o manuales (no tienen costo base en BD)
            if ( in_array( strtoupper( $sku ), ['MANUAL', 'GENERICO'] ) ) {
                continue;
            }

            // Obtener el precio base (o costo) registrado en el inventario
            $base_price = $wpdb->get_var( $wpdb->prepare( "SELECT precio FROM {$tabla_inv} WHERE sku = %s", $sku ) );
            
            if ( $base_price !== null ) {
                $selling_price = floatval( $item['price'] );
                $minimum_price = floatval( $base_price ); // Aquí puedes aplicar una fórmula de margen si lo deseas (Ej: $base_price * 0.90)

                // Si se vende por debajo del mínimo y no es Administrador -> RECHAZAR
                if ( $selling_price < $minimum_price && ! $is_admin ) {
                    $precio_fmt = number_format( $minimum_price, 2 );
                    $this->send_error( "El precio de venta del producto '{$item['name']}' ({$sku}) está por debajo del mínimo permitido (\${$precio_fmt}). Requiere autorización de un supervisor.", 403 );
                }
            }
        }
        // ==============================================================================

        $meta = [
            'vendedor_id' => get_current_user_id(),
            'tasa'        => floatval( $_POST['tasa'] ),
            'validez'     => intval( $_POST['validez'] ),
            'moneda'      => sanitize_text_field( $_POST['moneda'] )
        ];

        // 4. Instanciar Modelo y Guardar
        $quote_model = new Suite_Model_Quote();
        $result = $quote_model->create_quote( $client_data, $items, $meta );

        if ( is_wp_error( $result ) ) {
            $this->send_error( $result->get_error_message(), 500 );
        }

        $this->send_success( $result );
    }
}

/**
 * 2. Endpoint para Listar el Historial de Cotizaciones
 */
class Suite_Ajax_Quote_History extends Suite_AJAX_Controller {

    protected $action_name = 'suite_get_history_ajax';
    protected $required_capability = 'read';

    protected function process() {
        $user_id = get_current_user_id();
        $user = wp_get_current_user();
        
        $is_admin = current_user_can( 'manage_options' );
        $is_logistica = in_array( 'suite_logistica', (array) $user->roles );
        $tiene_acceso_global = ( $is_admin || $is_logistica );

        $quote_model = new Suite_Model_Quote();
        $history = $quote_model->get_vendor_history( $user_id, 50, $tiene_acceso_global );

        foreach ( $history as $r ) {
            $r->fecha_fmt = date( 'd/m/Y', strtotime( $r->fecha_emision ) );
            $r->total_fmt = number_format( floatval( $r->total_usd ), 2 );
            $r->cliente_nombre = empty( $r->cliente_nombre ) ? 'N/A' : esc_html( $r->cliente_nombre );

            $raw_tel = isset( $r->cliente_telefono ) ? $r->cliente_telefono : '';
            $wa_phone = preg_replace( '/[^0-9]/', '', $raw_tel );
            
            if ( strlen( $wa_phone ) === 11 && strpos( $wa_phone, '0' ) === 0 ) {
                $wa_phone = '58' . substr( $wa_phone, 1 );
            } elseif ( strlen( $wa_phone ) === 10 ) {
                $wa_phone = '58' . $wa_phone;
            }
            $r->wa_phone = $wa_phone;

            if ( empty( $r->estado ) ) {
                $r->estado = 'emitida';
            }
            $r->can_change_status = $tiene_acceso_global;
        }

        $this->send_success( $history );
    }
}

/**
 * 3. Endpoint para Cambiar el Estado de una Cotización (Manual / Kanban)
 * Modificado para Módulo 4: Recibe comprobantes y dispara comisiones.
 */
class Suite_Ajax_Quote_Status extends Suite_AJAX_Controller {

    protected $action_name = 'suite_change_status_ajax'; 
    protected $required_capability = 'read';

    protected function process() {
        $quote_id   = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
        $new_status = isset( $_POST['estado'] ) ? strtolower( sanitize_text_field( $_POST['estado'] ) ) : '';

        if ( ! $quote_id || empty( $new_status ) ) {
            $this->send_error( 'Datos insuficientes para cambiar el estado.' );
        }

        $quote_model = new Suite_Model_Quote();
        $current_order = $quote_model->get( $quote_id );

        if ( ! $current_order ) {
            $this->send_error( 'La cotización no existe.', 404 );
        }

        $current_status = strtolower( $current_order->estado );
        $protected_statuses = [ 'pagado', 'despachado' ];
        $is_admin = current_user_can( 'manage_options' );

        // 1. CANDADO DE INMUTABILIDAD
        if ( in_array( $current_status, $protected_statuses ) && ! $is_admin ) {
            $this->send_error( 'Candado de Inmutabilidad 🔒: Este pedido ya ha sido procesado y no puede ser modificado.', 403 );
        }

        $estados_validos = ['emitida', 'pagado', 'anulado', 'despachado'];
        if ( ! in_array( $new_status, $estados_validos ) ) {
            $this->send_error( 'Estado no válido.', 400 );
        }

        // 2. MÓDULO 4: CAPTURAR DATOS DE CIERRE DE VENTA
        if ( $new_status === 'pagado' ) {
            $extra_data = [
                'canal_venta'      => isset($_POST['canal_venta']) ? sanitize_text_field($_POST['canal_venta']) : '',
                'metodo_pago'      => isset($_POST['metodo_pago']) ? sanitize_text_field($_POST['metodo_pago']) : '',
                'metodo_entrega'   => isset($_POST['metodo_entrega']) ? sanitize_text_field($_POST['metodo_entrega']) : '',
                'url_captura_pago' => isset($_POST['url_captura']) ? esc_url_raw($_POST['url_captura']) : '',
                'recibo_loyverse'  => isset($_POST['recibo_loyverse']) ? sanitize_text_field($_POST['recibo_loyverse']) : '',
            ];
            // Actualizamos la cabecera antes de cambiar el estado formalmente
            $quote_model->update( $quote_id, $extra_data );
        }

        // 3. CAMBIAR ESTADO (Esto también descuenta inventario si aplica)
        $result = $quote_model->update_order_status( $quote_id, $new_status );

        if ( is_wp_error( $result ) ) {
            $this->send_error( $result->get_error_message(), 500 );
        }

        if ( $result ) {
            // 4. MÓDULO 4: DISPARAR COMISIÓN AUTOMÁTICA
            // Solo pagamos comisión si es un nuevo "Pagado" (evita duplicar comisiones si un admin lo edita)
            if ( $new_status === 'pagado' && $current_status !== 'pagado' ) {
                $commission_model = new Suite_Model_Commission();
                $commission_model->calculate_and_save_commission(
                    $quote_id,
                    $current_order->vendedor_id,
                    $current_order->total_usd
                );
            }

            $this->send_success( [ 'message' => 'Estado actualizado a ' . strtoupper( $new_status ) ] );
        } else {
            $this->send_error( 'Error al actualizar la base de datos o el estado ya era el mismo.' );
        }
    }
}