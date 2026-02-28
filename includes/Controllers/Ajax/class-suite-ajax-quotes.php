<?php
/**
 * Controlador AJAX: Cotizador y Venta (Módulo 2: Seguridad Aplicada)
 *
 * Contiene los manejadores para guardar cotizaciones, consultar el historial 
 * y cambiar estados (con candado de inmutabilidad y protección IDOR).
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
			'vendedor_id'      => get_current_user_id(),
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

        // MÓDULO 2: SEGURIDAD - CONTROL DE PRECIOS MÍNIMOS (MIDDLEWARE OPTIMIZADO N+1)
        global $wpdb;
        $tabla_inv = $wpdb->prefix . 'suite_inventario_cache';
        $is_admin = current_user_can( 'manage_options' );

        // Si es admin, nos saltamos la validación en BD para ahorrar recursos
        if ( ! $is_admin && ! empty( $items ) ) {
            $skus_a_verificar = [];
            foreach ( $items as $item ) {
                $sku = sanitize_text_field( $item['sku'] );
                if ( ! in_array( strtoupper( $sku ), ['MANUAL', 'GENERICO'] ) ) {
                    $skus_a_verificar[] = $sku;
                }
            }

            if ( ! empty( $skus_a_verificar ) ) {
                // 1. Pre-cargar precios permitidos con un solo query (IN)
                $placeholders = implode( ',', array_fill( 0, count( $skus_a_verificar ), '%s' ) );
                $sql_precios = $wpdb->prepare( "SELECT sku, precio FROM {$tabla_inv} WHERE sku IN ($placeholders)", ...$skus_a_verificar );
                // Usamos OBJECT_K para que el array resultante tenga los SKUs como llaves
                $resultados_precios = $wpdb->get_results( $sql_precios, OBJECT_K );

                // 2. Validar precios en memoria
                foreach ( $items as $item ) {
                    $sku = strtoupper( sanitize_text_field( $item['sku'] ) );

                    if ( isset( $resultados_precios[$sku] ) ) {
                        $minimum_price = floatval( $resultados_precios[$sku]->precio );
                        $selling_price = floatval( $item['price'] );

                        if ( $selling_price < $minimum_price ) {
                            $precio_fmt = number_format( $minimum_price, 2 );
                            $this->send_error( "El precio del producto '{$item['name']}' ({$sku}) está por debajo del mínimo permitido (\${$precio_fmt}). Requiere autorización de un supervisor.", 403 );
                        }
                    }
                }
            }
        }

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

        $is_admin = current_user_can( 'manage_options' );

        // 1. SEGURIDAD: PREVENCIÓN DE IDOR
        $user = wp_get_current_user();
        $is_admin = current_user_can( 'manage_options' );
        $is_logistica = in_array( 'suite_logistica', (array) $user->roles );
        $is_owner = ( intval( $current_order->vendedor_id ) === get_current_user_id() );

        // 1. SEGURIDAD: PREVENCIÓN DE IDOR (Con Bypass para Logística)
        if ( ! $is_admin && ! $is_owner ) {
            // Permitir SOLO si es Logística y está intentando mover a 'despachado'
            if ( ! ( $is_logistica && $new_status === 'despachado' ) ) {
                if ( function_exists('suite_record_log') ) {
                    suite_record_log( 'violacion_idor', "Usuario " . get_current_user_id() . " intentó modificar el pedido #{$quote_id}." );
                }
                $this->send_error( 'Acceso Denegado: No tiene permisos para modificar un pedido que no le pertenece.', 403 );
            }
        }

        $current_status = strtolower( $current_order->estado );
        $protected_statuses = [ 'pagado', 'despachado' ];

        // 2. CANDADO DE INMUTABILIDAD
        if ( in_array( $current_status, $protected_statuses ) && ! $is_admin ) {
            $this->send_error( 'Candado de Inmutabilidad 🔒: Este pedido ya ha sido procesado y no puede ser modificado.', 403 );
        }
		
        // --- INICIO INYECCIÓN: EVENTO DE LOGÍSTICA INVERSA ---
        if ( $current_status === 'despachado' && $new_status === 'proceso' ) {
            // Re-verificación estricta (Zero-Trust)
            if ( ! current_user_can( 'manage_options' ) ) {
                $this->send_error( 'Acceso Denegado. Solo el Administrador puede aplicar Logística Inversa.', 403 );
            }
            // Registro obligatorio en el historial
            if ( function_exists('suite_record_log') ) {
                suite_record_log( 'logistica_inversa', "Logística Inversa: El Administrador (ID: " . get_current_user_id() . ") revirtió el pedido #{$quote_id} de 'despachado' a 'proceso'." );
            }

            // --- NUEVO: REVERSO CONTABLE EN EL LEDGER ---
            $commission_model = new Suite_Model_Commission();
            $commission_model->reverse_commission( $quote_id );

            // Nota de Arquitectura: Si el módulo descuenta inventario físico al llegar a 'despachado',
            // este es el punto exacto para invocar una función que restaure dicho stock.
        }
        // --- FIN INYECCIÓN ---

		// CORRECCIÓN 1: Se agregó 'por_enviar' al array de estados válidos
        $estados_validos = ['emitida', 'proceso', 'pagado', 'por_enviar', 'anulado', 'despachado'];
        if ( ! in_array( $new_status, $estados_validos ) ) {
            $this->send_error( 'Estado no válido.', 400 );
        }

        // 3. MÓDULO 4: CAPTURAR DATOS DE CIERRE DE VENTA
        if ( $new_status === 'pagado' ) {
            $recibo = isset($_POST['recibo_loyverse']) ? sanitize_text_field($_POST['recibo_loyverse']) : '';
            
            // CORRECCIÓN 2: Validar Unicidad del Recibo en la Base de Datos
            if ( ! empty( $recibo ) ) {
                global $wpdb;
                $tabla_cot = $wpdb->prefix . 'suite_cotizaciones';
                
                // Buscamos si el recibo ya existe en OTRO pedido distinto al que estamos procesando
                $existe = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$tabla_cot} WHERE recibo_loyverse = %s AND id != %d", $recibo, $quote_id ) );
                
                if ( $existe ) {
                    $this->send_error( 'El número de recibo o factura (' . esc_html($recibo) . ') ya está asignado al pedido #' . $existe . '. No se permiten duplicados.', 400 );
                    return; // Detenemos la ejecución inmediatamente
                }
            }

            $extra_data = [
                'canal_venta'      => isset($_POST['canal_venta']) ? sanitize_text_field($_POST['canal_venta']) : '',
                'metodo_pago'      => isset($_POST['metodo_pago']) ? sanitize_text_field($_POST['metodo_pago']) : '',
                'metodo_entrega'   => isset($_POST['metodo_entrega']) ? sanitize_text_field($_POST['metodo_entrega']) : '',
                'url_captura_pago' => isset($_POST['url_captura']) ? esc_url_raw($_POST['url_captura']) : '',
                'recibo_loyverse'  => $recibo,
            ];
            $quote_model->update( $quote_id, $extra_data );
        }

        // 4. CAMBIAR ESTADO
        $result = $quote_model->update_order_status( $quote_id, $new_status );

        if ( is_wp_error( $result ) ) {
            $this->send_error( $result->get_error_message(), 500 );
        }

        if ( $result ) {
			
			// 5. DISPARAR COMISIÓN AUTOMÁTICA
			if ( $new_status === 'pagado' && $current_status !== 'pagado' ) {
				$commission_model = new Suite_Model_Commission();

				// Recepción y Sanitización Estricta (Casteo de cada elemento a Integer)
				$colaboradores_raw = isset( $_POST['colaboradores'] ) ? $_POST['colaboradores'] : [];
				$colaboradores_clean = is_array( $colaboradores_raw ) ? array_map( 'intval', $colaboradores_raw ) : [];

				$commission_model->calculate_and_save_commission(
					$quote_id,
					$current_order->vendedor_id,
					$current_order->total_usd,
					$colaboradores_clean // <-- 4TO PARÁMETRO INYECTADO
				);
			}

            $this->send_success( [ 'message' => 'Estado actualizado a ' . strtoupper( $new_status ) ] );
        } else {
            $this->send_error( 'Error al actualizar la base de datos o el estado ya era el mismo.' );
        }
    }
}


/**
 * Endpoint para Imprimir Cotización en PDF/HTML (Sustituye a mod-impresion.php)
 */
class Suite_Ajax_Print_Quote extends Suite_AJAX_Controller {

    protected $action_name = 'suite_print_quote';
    protected $required_capability = 'read';

    public function handle_request() {
        if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( $_GET['nonce'], 'suite_quote_nonce' ) ) {
            wp_die( 'Enlace caducado o inválido por seguridad (CSRF).', 'Acceso Denegado', [ 'response' => 403 ] );
        }
        $this->process();
    }

    protected function process() {
        if ( ! current_user_can( 'read' ) ) {
            wp_die( 'Privilegios insuficientes.', 'Acceso Denegado', [ 'response' => 403 ] );
        }

        global $wpdb;
        $id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

        // 1. OBTENER DATOS PRINCIPALES
        $cot = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}suite_cotizaciones WHERE id = %d", $id ) );
        if ( ! $cot ) wp_die( 'Cotización no encontrada.', 'Error', [ 'response' => 404 ] );

        $items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}suite_cotizaciones_items WHERE cotizacion_id = %d", $id ) );

        // 2. OBTENER DATOS EXTENDIDOS DEL CLIENTE
        $cliente_extra = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}suite_clientes WHERE id = %d", $cot->cliente_id ) );

        // 3. DATOS VENDEDOR
        $vendedor = get_userdata( $cot->vendedor_id );
        $vendedor_nombre = $vendedor ? $vendedor->display_name : 'Equipo de Ventas';
        $tel_db = get_user_meta($cot->vendedor_id, 'suite_telefono', true);
        $vendedor_telefono = !empty($tel_db) ? $tel_db : "+58 424-844-2132";

        // 4. LÓGICA DE MONEDA (USD vs BS)
        $es_bolivares = ( $cot->moneda === 'BS' );
        $simbolo = $es_bolivares ? 'Bs.' : '$';
        $tasa_calc = $es_bolivares ? floatval( $cot->tasa_bcv ) : 1;

        // 5. CÁLCULOS TOTALES
        $subtotal_base = floatval( $cot->total_usd ) * $tasa_calc;
        $descuento = 0; 
        $base_imponible = $subtotal_base - $descuento;
        $iva_pct = 0.16;
        $monto_iva = $base_imponible * $iva_pct;
        $total_final = $base_imponible + $monto_iva;

        // Formateo
        $subtotal_fmt = number_format( $subtotal_base, 2 );
        $iva_fmt = number_format( $monto_iva, 2 );
        $total_fmt = number_format( $total_final, 2 );

        // LÓGICA DE LIMPIEZA DE DATOS
        $validar_dato = function($val) {
            return (!empty($val) && $val !== 'N/A' && $val !== 'N/D');
        };

        $show_dir = $validar_dato($cot->direccion_entrega) ? $cot->direccion_entrega : false;
        
        $ciudad_raw = isset($cliente_extra->ciudad) ? $cliente_extra->ciudad : '';
        $estado_raw = isset($cliente_extra->estado) ? $cliente_extra->estado : '';
        $show_ubicacion = trim("$ciudad_raw $estado_raw");
        if(!$validar_dato($show_ubicacion)) $show_ubicacion = false;

        $show_atencion = (isset($cliente_extra->contacto_persona) && $validar_dato($cliente_extra->contacto_persona)) ? $cliente_extra->contacto_persona : false;
        $tel_raw = (isset($cliente_extra->telefono) && $validar_dato($cliente_extra->telefono)) ? "Telf: " . $cliente_extra->telefono : '';
        $email_raw = (isset($cliente_extra->email) && $validar_dato($cliente_extra->email)) ? "Email: " . $cliente_extra->email : '';
        $show_contacto = trim("$tel_raw $email_raw");
        
        if(empty($tel_raw) && empty($email_raw)) $show_contacto = false;

		// --- INICIO HTML ---
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <title>Cotización #<?php echo esc_attr( $cot->codigo_cotizacion ); ?></title>
            <style>
                /* REGLAS MAESTRAS DE IMPRESIÓN Y COLORES */
                * {
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                    color-adjust: exact !important;
                }
                @media print {
                    @page { margin: 1cm; size: letter portrait; }
                    body { margin: 0; padding: 0; }
                }
                
                /* TIPOGRAFÍA ÚNICA Y GLOBAL */
                body { 
                    font-family: Arial, Helvetica, sans-serif; 
                    padding: 30px; 
                    color: #555555; 
                    font-size: 12px; 
                    margin: 0;
                }
                
                /* CLASES DE COLORES ESPECÍFICAS */
                .text-gray { color: #555555 !important; }
                .text-black-bold { color: #000000 !important; font-weight: bold !important; }
                .text-red-bold { color: #d0121b !important; font-weight: bold !important; }
                .bg-red-unit { background-color: #d0121b !important; color: #ffffff !important; }
                
                /* SEPARADORES */
                .separator-red { border: none; border-top: 2px solid #d0121b; margin: 15px 0; }
                .separator-light { border: none; border-top: 1px solid #e5e7eb; margin: 8px 0 12px 0; }
                .separator-elegant { border: none; border-top: 1px solid #d1d5db; margin: 18px 0; }
                
                /* =========================================
                   ENCABEZADO: LOGO (IZQ) / COTIZACIÓN (DER) 
                   ========================================= */
                .header-table { width: 100%; border-collapse: collapse; margin-bottom: 5px; }
                .header-table td { vertical-align: top; }
                
                /* IZQUIERDA: LOGO Y EMPRESA */
                .header-left { width: 55%; padding-right: 20px; text-align: left; }
                .empresa-info-container img { height: 60px; margin-bottom: 8px; }
                .empresa-info { font-size: 11px; line-height: 1.4; }
                
                /* DERECHA: COTIZACIÓN */
                .header-right { width: 45%; text-align: right; }
                .cotizacion-title { 
                    margin: 0 0 5px 0; 
                    font-size: 18px; /* Elegante y proporcionado */
                    letter-spacing: 2px; 
                    font-weight: bold;
                    color: #000;
					text-align: left;
                }
                .separator-header-right { border-bottom: 1px solid #d1d5db; margin: 5px 0 10px auto; width: 100%; }
                
                .cotizacion-datos { border-collapse: collapse; font-size: 12px; text-align: left; width: 100%; }
                .cotizacion-datos td { padding: 3px 0; }
                .cotizacion-datos td:first-child { width: 80px; } 
                
                /* =========================================
                   DATOS DEL CLIENTE
                   ========================================= */
                .client-box { 
                    border: 1px solid #d1d5db; 
                    padding: 15px; 
                    border-radius: 8px; /* Esquinas curvas */
                    margin-bottom: 25px; /* Espaciado antes de la tabla */
                }
                .client-title { font-size: 13px; margin: 0; }
                .client-table { width: 100%; border-collapse: collapse; font-size: 11px; }
                .client-table td { padding: 5px 0; vertical-align: top; border: none; }
                
                /* =========================================
                   TABLA DE PRODUCTOS
                   ========================================= */
                .products-table { width: 100%; border-collapse: collapse; font-size: 11px; margin-top: 5px; }
                /* Alineación forzada a la izquierda */
                .products-table th, .products-table td { padding: 8px; text-align: left !important; }
                .products-table th { font-weight: bold; border: 1px solid #d0121b; } 
                .products-table td { border-bottom: 1px solid #f3f4f6; } 
                
                /* =========================================
                   TOTALES Y FOOTER
                   ========================================= */
                .totales-table { width: 280px; float: right; border-collapse: collapse; font-size: 12px; }
                .totales-table td { padding: 6px 10px; text-align: left; border: none; }
                .totales-table td:last-child { text-align: right; }
                
                /* Fila del Gran Total */
                .grand-total td { 
                    font-size: 16px !important; 
                    font-weight: bold !important; 
                    color: #d0121b !important; 
                    border-top: 1.5px solid #d0121b !important; /* Separador rojo del IVA al Total */
                    padding-top: 10px;
                    margin-top: 5px;
                }
                
                .footer { text-align: center; font-style: italic; font-size: 12px; clear: both; width: 100%; padding-top: 5px; }
            </style>
        </head>
        <body onload="window.print()">
            
            <table class="header-table">
                <tr>
                    <td class="header-left">
                        <div class="empresa-info-container">
                            <img src="https://mitiendaunit.com/wp-content/uploads/2025/09/LOGO-UNI-T-RENOVADO_Mesa-de-trabajo-1-2.png" alt="UNI-T">
                            <div class="empresa-info text-gray">
                                <strong class="text-black-bold">UNI-T VENEZUELA, C.A.</strong><br>
                                C.C. Galerias Avila, nivel Feria, Local F67<br>
                                Caracas, 1010, Venezuela.<br>
                                R.I.F.: J-50174299-5<br>
                                Web: www.mitiendaunit.com
                            </div>
                        </div>
                    </td>

                    <td class="header-right">
                        <h2 class="cotizacion-title">COTIZACIÓN</h2>
                        <div class="separator-header-right"></div>
                        <table class="cotizacion-datos">
                            <tr>
                                <td class="text-black-bold">N°:</td>
                                <td class="text-red-bold">#<?php echo esc_html( $cot->codigo_cotizacion ); ?></td>
                            </tr>
                            <tr>
                                <td class="text-black-bold">Fecha:</td>
                                <td class="text-gray"><?php echo date( 'd/m/Y', strtotime( $cot->fecha_emision ) ); ?></td>
                            </tr>
                            <tr>
                                <td class="text-black-bold">Validez:</td>
                                <td class="text-gray"><?php echo esc_html( $cot->validez_dias ); ?> Días</td>
                            </tr>
                            <tr>
                                <td class="text-black-bold">Vendedor:</td>
                                <td class="text-gray"><?php echo esc_html( $vendedor_nombre ); ?></td>
                            </tr>
                            <tr>
                                <td class="text-black-bold">Teléfono:</td>
                                <td class="text-gray"><?php echo esc_html( $vendedor_telefono ); ?></td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            <hr class="separator-red">

            <div class="client-box">
                <div class="client-title text-red-bold">DATOS DEL CLIENTE</div>
                <hr class="separator-light">
                <table class="client-table">
                    <tr>
                        <td style="width: 50%;">
                            <strong class="text-black-bold">RAZÓN SOCIAL:</strong> 
                            <span class="text-black-bold"><?php echo esc_html( mb_strtoupper( $cot->cliente_nombre, 'UTF-8' ) ); ?></span>
                        </td>
                        <td style="width: 50%;">
                            <strong class="text-black-bold">RIF/CI:</strong> 
                            <span class="text-gray"><?php echo esc_html( mb_strtoupper( $cot->cliente_rif, 'UTF-8' ) ); ?></span>
                        </td>
                    </tr>
                    <?php if ( $show_dir || $show_ubicacion ): ?>
                    <tr>
                        <?php if ( $show_dir ): ?>
                            <td <?php echo !$show_ubicacion ? 'colspan="2"' : ''; ?>>
                                <strong class="text-black-bold">DIRECCIÓN:</strong> 
                                <span class="text-gray"><?php echo esc_html( mb_strtoupper( $show_dir, 'UTF-8' ) ); ?></span>
                            </td>
                        <?php endif; ?>
                        <?php if ( $show_ubicacion ): ?>
                            <td <?php echo !$show_dir ? 'colspan="2"' : ''; ?>>
                                <strong class="text-black-bold">CIUDAD/ESTADO:</strong> 
                                <span class="text-gray"><?php echo esc_html( mb_strtoupper( $show_ubicacion, 'UTF-8' ) ); ?></span>
                            </td>
                        <?php endif; ?>
                    </tr>
                    <?php endif; ?>
                    <?php if ( $show_contacto ): ?>
                    <tr>
                        <td colspan="2">
                            <strong class="text-black-bold">CONTACTO:</strong> 
                            <span class="text-gray"><?php echo esc_html( mb_strtoupper( $show_contacto, 'UTF-8' ) ); ?></span>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>

            <table class="products-table">
                <thead>
                    <tr>
                        <th class="bg-red-unit" style="width: 33%;">DESCRIPCIÓN</th>
                        <th class="bg-red-unit" style="width: 20%;">PRECIO POR UNIDAD</th>
                        <th class="bg-red-unit" style="width: 10%;">CANTIDAD</th>
                        <th class="bg-red-unit" style="width: 20%;">TIEMPO DE ENTREGA</th>
                        <th class="bg-red-unit" style="width: 17%;">MONTO</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $items as $item ): 
                        $precio_unit = floatval($item->precio_unitario_usd) * $tasa_calc;
                        $subtotal_item = floatval($item->subtotal_usd) * $tasa_calc;
                    ?>
                    <tr>
                        <td>
                            <strong class="text-black-bold"><?php echo esc_html( $item->sku ); ?></strong><br>
                            <span class="text-gray"><?php echo esc_html( mb_strtoupper( $item->producto_nombre, 'UTF-8' ) ); ?></span>
                        </td>
                        <td class="text-gray"><?php echo $simbolo . ' ' . number_format( $precio_unit, 2 ); ?></td>
                        <td class="text-gray"><?php echo intval( $item->cantidad ); ?></td>
                        <td class="text-gray"><?php echo !empty($item->tiempo_entrega) ? esc_html( mb_strtoupper( $item->tiempo_entrega, 'UTF-8' ) ) : 'INMEDIATA'; ?></td>
                        <td class="text-gray"><?php echo $simbolo . ' ' . number_format( $subtotal_item, 2 ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <hr class="separator-elegant">

            <table class="totales-table">
                <tr>
                    <td class="text-gray">Subtotal:</td>
                    <td class="text-black-bold" style="width: 130px;"><?php echo $simbolo . ' ' . $subtotal_fmt; ?></td>
                </tr>
                <?php if ( $descuento > 0 ): ?>
                <tr>
                    <td class="text-gray">Descuento:</td>
                    <td class="text-gray">- <?php echo $simbolo . ' ' . number_format( $descuento, 2 ); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td class="text-gray">I.V.A. (16%):</td>
                    <td class="text-black-bold"><?php echo $simbolo . ' ' . $iva_fmt; ?></td>
                </tr>
                <tr class="grand-total">
                    <td>TOTAL:</td>
                    <td><?php echo $simbolo . ' ' . $total_fmt; ?></td>
                </tr>
            </table>

            <div style="clear: both;"></div>

            <hr class="separator-elegant">

            <div class="footer text-gray">
                Gracias por su preferencia. Esta cotización está sujeta a disponibilidad de inventario.
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}



/**
 * 4. Endpoint para Obtener Detalles de Cotización (Acción: Clonar)
 */
class Suite_Ajax_Quote_Details extends Suite_AJAX_Controller {
    protected $action_name = 'suite_get_quote_details_ajax';
    protected $required_capability = 'read';

    protected function process() {
        global $wpdb;
        $quote_id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;

        if ( ! $quote_id ) {
            $this->send_error( 'ID de cotización inválido.' );
        }

        $user = wp_get_current_user();
        $is_admin = current_user_can( 'manage_options' ) || in_array( 'suite_gerente', (array) $user->roles );
        $user_id = get_current_user_id();

        $cotizacion = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}suite_cotizaciones WHERE id = %d", $quote_id ) );

        if ( ! $cotizacion ) {
            $this->send_error( 'Cotización no encontrada.' );
        }

        // Validación RLS: Zero-Trust para clonación
        if ( ! $is_admin && intval( $cotizacion->vendedor_id ) !== $user_id ) {
            $this->send_error( 'Acceso denegado. No puede clonar una cotización que no le pertenece.', 403 );
        }

        $items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}suite_cotizaciones_items WHERE cotizacion_id = %d", $quote_id ) );
        $cliente = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}suite_clientes WHERE id = %d", $cotizacion->cliente_id ) );

        $this->send_success( [
            'cotizacion' => $cotizacion,
            'items'      => $items,
            'cliente'    => $cliente
        ] );
    }
}